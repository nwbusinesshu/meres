<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserImportService;
use App\Jobs\ProcessUserImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\OrgConfigService;

class AdminEmployeeImportController extends Controller
{
    /**
     * Download Excel template based on organization settings
     */
    public function downloadTemplate(string $type)
    {
        // Validate type
        if (!in_array($type, ['legacy', 'multilevel'])) {
            abort(400, 'Invalid template type');
        }
        
        $orgId = session('org_id');
        $enableMultiLevel = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
        
        // If requesting multilevel but not enabled, force legacy
        if ($type === 'multilevel' && !$enableMultiLevel) {
            $type = 'legacy';
        }
        
        $file = public_path("templates/employee-import-{$type}.xlsx");
        
        if (!file_exists($file)) {
            abort(404, 'Template file not found');
        }
        
        return response()->download($file, "employee-import-template-{$type}.xlsx");
    }

    /**
     * Validate uploaded employee data
     */
    public function validateImport(Request $request)
    {
        $request->validate([
            'file_data' => 'required|array|min:1|max:500',
            'file_data.*.name' => 'required|string|max:255',
            'file_data.*.email' => 'required|string|max:255',
            'file_data.*.type' => 'required|string|max:50',
            'file_data.*.position' => 'nullable|string|max:255',
            'file_data.*.department_name' => 'nullable|string|max:255',
            'file_data.*.wage' => 'nullable',
            'file_data.*.currency' => 'nullable|string|max:10',
        ]);
        
        $orgId = session('org_id');
        $service = new UserImportService();
        
        $validationResult = $service->validateImportData(
            $request->file_data,
            $orgId
        );
        
        return response()->json($validationResult);
    }

    /**
     * Start the import process
     */
    public function start(Request $request)
    {
        $request->validate([
            'validated_data' => 'required|array|max:500',
            'send_emails' => 'boolean',
        ]);
        
        $orgId = session('org_id');
        $createdBy = auth()->id();
        $sendEmails = $request->input('send_emails', true);
        
        // Create job record
        $jobId = DB::table('user_import_jobs')->insertGetId([
            'organization_id' => $orgId,
            'created_by' => $createdBy,
            'filename' => 'import_' . now()->format('YmdHis') . '.json',
            'original_filename' => 'employee_import.xlsx',
            'total_rows' => count($request->validated_data),
            'send_emails' => $sendEmails,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Store validated data temporarily
        Storage::put(
            "imports/{$jobId}.json",
            json_encode($request->validated_data)
        );
        
        // Dispatch job to queue
        ProcessUserImport::dispatch($jobId, $orgId, $createdBy)
            ->onQueue('default');
        
        \Log::info('employee.import.started', [
            'job_id' => $jobId,
            'org_id' => $orgId,
            'total_rows' => count($request->validated_data),
            'send_emails' => $sendEmails
        ]);
        
        return response()->json([
            'success' => true,
            'job_id' => $jobId
        ]);
    }

    /**
     * Get import job status
     */
    public function status(int $jobId)
    {
        $orgId = session('org_id');
        
        $job = DB::table('user_import_jobs')
            ->where('id', $jobId)
            ->where('organization_id', $orgId)
            ->first();
        
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }
        
        $percentage = $job->total_rows > 0 
            ? round(($job->processed_rows / $job->total_rows) * 100, 2)
            : 0;
        
        return response()->json([
            'status' => $job->status,
            'progress' => [
                'processed' => $job->processed_rows,
                'successful' => $job->successful_rows,
                'failed' => $job->failed_rows,
                'total' => $job->total_rows,
                'percentage' => $percentage,
            ],
            'departments_created' => $job->departments_created,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
    }

    /**
     * Download import results report
     */
    public function downloadReport(int $jobId)
    {
        $orgId = session('org_id');
        
        // Verify job belongs to organization
        $job = DB::table('user_import_jobs')
            ->where('id', $jobId)
            ->where('organization_id', $orgId)
            ->first();
            
        if (!$job) {
            abort(404, 'Import job not found');
        }
        
        $results = DB::table('user_import_results')
            ->where('import_job_id', $jobId)
            ->orderBy('row_number')
            ->get();
        
        // Generate CSV
        $csv = "Row,Email,Name,Department,Status,Action,Error Message\n";
        
        foreach ($results as $result) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s\n",
                $result->row_number,
                $this->escapeCsv($result->email),
                $this->escapeCsv($result->name),
                $this->escapeCsv($result->department_name ?? ''),
                $result->status,
                $result->action_taken ?? '',
                $this->escapeCsv($result->error_message ?? '')
            );
        }
        
        $filename = "import_report_{$jobId}_" . now()->format('Ymd_His') . ".csv";
        
        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
    
    /**
     * Escape CSV field
     */
    private function escapeCsv($field): string
    {
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }

    /**
 * Check if current user has an active import
 */
public function checkActiveImport()
{
    $orgId = session('org_id');
    $userId = auth()->id();
    
    // Check for any pending or processing imports for this organization
    $activeJob = DB::table('user_import_jobs')
        ->where('organization_id', $orgId)
        ->where('created_by', $userId)
        ->whereIn('status', ['pending', 'processing'])
        ->orderBy('created_at', 'desc')
        ->first();
    
    if (!$activeJob) {
        return response()->json([
            'has_active_import' => false
        ]);
    }
    
    $percentage = $activeJob->total_rows > 0 
        ? round(($activeJob->processed_rows / $activeJob->total_rows) * 100, 2)
        : 0;
    
    return response()->json([
        'has_active_import' => true,
        'job_id' => $activeJob->id,
        'status' => $activeJob->status,
        'progress' => [
            'processed' => $activeJob->processed_rows,
            'successful' => $activeJob->successful_rows,
            'failed' => $activeJob->failed_rows,
            'total' => $activeJob->total_rows,
            'percentage' => $percentage,
        ],
        'departments_created' => $activeJob->departments_created,
        'started_at' => $activeJob->started_at,
    ]);
}
}