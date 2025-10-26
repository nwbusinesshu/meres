<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\EmployeeCreationService;
use App\Services\OrgConfigService;
use App\Models\Enums\OrgRole;  // ✅ ADDED: For role validation

class ProcessUserImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 1;

    protected $jobId;
    protected $orgId;
    protected $createdBy;

    // ===== TESTING: Add artificial delay =====
    // Set to 0 for production, or a number (in seconds) for testing
    // Example: 2 = 2 seconds delay per row
    // With 10 rows: 2 seconds × 10 = 20 seconds total
    private const TESTING_DELAY_SECONDS = 0; // ← CHANGE THIS VALUE

    public function __construct(int $jobId, int $orgId, int $createdBy)
    {
        $this->jobId = $jobId;
        $this->orgId = $orgId;
        $this->createdBy = $createdBy;
    }

    public function handle()
    {
        Log::info('employee.import.processing', [
            'job_id' => $this->jobId,
            'org_id' => $this->orgId
        ]);
        
        // Mark job as processing
        DB::table('user_import_jobs')
            ->where('id', $this->jobId)
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        try {
            // Load validated data
            $jsonPath = "imports/{$this->jobId}.json";
            
            if (!Storage::exists($jsonPath)) {
                throw new \Exception("Import data file not found: {$jsonPath}");
            }
            
            $data = json_decode(Storage::get($jsonPath), true);
            
            if (!is_array($data)) {
                throw new \Exception("Invalid import data format");
            }
            
            // 🔒 SECURITY VALIDATION: Check all roles before processing
            // This prevents any admin/owner roles from slipping through
            Log::info('employee.import.security_check', [
                'job_id' => $this->jobId,
                'total_rows' => count($data)
            ]);
            
            foreach ($data as $index => $row) {
                $type = strtolower(trim($row['data']['type'] ?? ''));
                
                // 🔒 SECURITY: Block admin and owner roles
                if (in_array($type, ['admin', 'owner'])) {
                    throw new \Exception("SECURITY: Cannot import admin or owner users. Row " . ($index + 1) . " has invalid role: {$type}");
                }
                
                // 🔒 SECURITY: Only allow employee, manager, ceo
                if (!in_array($type, ['employee', 'manager', 'ceo'])) {
                    throw new \Exception("Invalid role in row " . ($index + 1) . ": {$type}. Only employee, manager, ceo are allowed.");
                }
                
                // ✅ Validate it's a real OrgRole value
                if (!OrgRole::isValid($type)) {
                    throw new \Exception("Invalid OrgRole in row " . ($index + 1) . ": {$type}");
                }
            }
            
            Log::info('employee.import.security_check_passed', [
                'job_id' => $this->jobId,
                'message' => 'All rows passed security validation'
            ]);
            
            $totalRows = count($data);
            $processedRows = 0;
            $successfulRows = 0;
            $failedRows = 0;
            $departmentsCreated = 0;
            $createdUserIds = [];
            
            $enableMultiLevel = OrgConfigService::getBool($this->orgId, 'enable_multi_level', false);
            
            // Cache departments to avoid repeated queries
            $departmentCache = [];
            
            // Process in chunks of 50 to avoid memory issues
            $chunks = array_chunk($data, 50);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                DB::transaction(function () use (
                    $chunk, 
                    &$processedRows, 
                    &$successfulRows, 
                    &$failedRows,
                    &$departmentsCreated,
                    &$departmentCache,
                    &$createdUserIds,
                    $enableMultiLevel
                ) {
                    foreach ($chunk as $row) {
                        $rowNumber = $processedRows + 1;
                        
                        try {
                            // Extract department info if multi-level
                            $deptName = null;
                            if ($enableMultiLevel && isset($row['data']['department_name'])) {
                                $deptName = trim($row['data']['department_name']);
                                if (empty($deptName)) {
                                    $deptName = null;
                                }
                            }
                            
                            // Create employee using service
                            $user = EmployeeCreationService::createEmployee(
                                $row['data'],
                                $this->orgId
                            );
                            
                            $departmentId = null;
                            $type = strtolower(trim($row['data']['type']));
                            
                            // ✅ FIXED: Handle department assignment with proper role checks
                            // 🔒 SECURITY: CEO cannot be assigned to departments
                            // Only employee and manager can be assigned to departments
                            if ($enableMultiLevel && $deptName && in_array($type, [OrgRole::EMPLOYEE, 'employee', OrgRole::MANAGER, 'manager'])) {
                                $deptLower = strtolower($deptName);
                                
                                // Check cache first
                                if (isset($departmentCache[$deptLower])) {
                                    $departmentId = $departmentCache[$deptLower];
                                } else {
                                    // Get or create department
                                    $departmentId = EmployeeCreationService::getOrCreateDepartment(
                                        $deptName,
                                        $this->orgId
                                    );
                                    
                                    // Track if we created it in this import
                                    if (!DB::table('organization_departments')
                                        ->where('id', $departmentId)
                                        ->whereDate('created_at', '<', now()->subSeconds(5))
                                        ->exists()
                                    ) {
                                        $departmentsCreated++;
                                    }
                                    
                                    $departmentCache[$deptLower] = $departmentId;
                                }
                                
                                // Assign to department
                                EmployeeCreationService::assignToDepartment(
                                    $user,
                                    $this->orgId,
                                    $departmentId,
                                    $type
                                );
                            }
                            
                            // Log success
                            DB::table('user_import_results')->insert([
                                'import_job_id' => $this->jobId,
                                'row_number' => $rowNumber,
                                'user_id' => $user->id,
                                'email' => $user->email,
                                'name' => $user->name,
                                'department_name' => $deptName,
                                'status' => 'success',
                                'action_taken' => 'created',
                                'error_message' => null,
                            ]);
                            
                            $createdUserIds[] = $user->id;
                            $successfulRows++;
                            
                        } catch (\Exception $e) {
                            // Log failure
                            DB::table('user_import_results')->insert([
                                'import_job_id' => $this->jobId,
                                'row_number' => $rowNumber,
                                'user_id' => null,
                                'email' => $row['data']['email'] ?? '',
                                'name' => $row['data']['name'] ?? '',
                                'department_name' => $row['data']['department_name'] ?? null,
                                'status' => 'failed',
                                'action_taken' => null,
                                'error_message' => substr($e->getMessage(), 0, 500),
                            ]);
                            
                            $failedRows++;
                            
                            Log::warning('employee.import.row_failed', [
                                'job_id' => $this->jobId,
                                'row' => $rowNumber,
                                'email' => $row['data']['email'] ?? '',
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        $processedRows++;

                        // ===== TESTING DELAY =====
                        if (self::TESTING_DELAY_SECONDS > 0) {
                            sleep(self::TESTING_DELAY_SECONDS);
                        }
                        // =========================
                    }
                });
                
                // Update progress after each chunk
                DB::table('user_import_jobs')
                    ->where('id', $this->jobId)
                    ->update([
                        'processed_rows' => $processedRows,
                        'successful_rows' => $successfulRows,
                        'failed_rows' => $failedRows,
                        'departments_created' => $departmentsCreated,
                        'updated_at' => now(),
                    ]);
                
                Log::info('employee.import.chunk_completed', [
                    'job_id' => $this->jobId,
                    'chunk' => $chunkIndex + 1,
                    'total_chunks' => count($chunks),
                    'processed' => $processedRows,
                    'successful' => $successfulRows,
                    'failed' => $failedRows
                ]);
            }
            
            // Mark job as completed
            DB::table('user_import_jobs')
                ->where('id', $this->jobId)
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            
            Log::info('employee.import.completed', [
                'job_id' => $this->jobId,
                'successful' => $successfulRows,
                'failed' => $failedRows,
                'departments_created' => $departmentsCreated
            ]);
            
            // Dispatch email batch job if emails should be sent
            $sendEmails = DB::table('user_import_jobs')
                ->where('id', $this->jobId)
                ->value('send_emails');
            
            if ($sendEmails && count($createdUserIds) > 0) {
                // SendImportPasswordEmails::dispatch($this->jobId, $createdUserIds, $this->orgId);
                // Note: Uncomment when email job is ready
                Log::info('employee.import.emails_queued', [
                    'job_id' => $this->jobId,
                    'user_count' => count($createdUserIds)
                ]);
            }
            
        } catch (\Exception $e) {
            // Mark job as failed
            DB::table('user_import_jobs')
                ->where('id', $this->jobId)
                ->update([
                    'status' => 'failed',
                    'error_report' => json_encode([
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]),
                    'updated_at' => now(),
                ]);
            
            Log::error('employee.import.failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        } finally {
            // Clean up temporary file
            Storage::delete("imports/{$this->jobId}.json");
        }
    }
}