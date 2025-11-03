<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Enums\OrgRole; 

class UserImportService
{
    /**
     * Validate import data and return structured results
     * 
     * 🔒 SECURITY: Only allows employee, manager, ceo roles
     * ✅ FIXED: Updated to use 'employee' instead of 'normal'
     */
    public function validateImportData(array $rows, int $orgId): array
    {
        $enableMultiLevel = OrgConfigService::getBool($orgId, 'enable_multi_level', false);
        
        // Get employee limit info
        $employeeLimit = DB::table('organization_profiles')
            ->where('organization_id', $orgId)
            ->value('employee_limit');
        
        // ✅ FIXED: Count excludes admins using org_role
        $currentCount = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->where('ou.role', '!=', OrgRole::ADMIN)
            ->count();
        
        $hasClosedAssessment = DB::table('assessment')
            ->where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->exists();
        
        $showLimitWarning = false;
        if (!$hasClosedAssessment && $employeeLimit && ($currentCount + count($rows)) > $employeeLimit) {
            $showLimitWarning = true;
        }
        
        // Get all existing emails (across all orgs)
        $existingEmails = DB::table('user')
            ->whereNull('removed_at')
            ->pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->toArray();
        
        // Get existing departments
        $existingDepartments = [];
        if ($enableMultiLevel) {
            $existingDepartments = DB::table('organization_departments')
                ->where('organization_id', $orgId)
                ->whereNull('removed_at')
                ->pluck('department_name')
                ->map(fn($d) => strtolower(trim($d)))
                ->toArray();
        }
        
        $validRows = [];
        $invalidRows = [];
        $warningRows = [];
        $emailsInFile = [];
        $newDepartments = [];
        
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $errors = [];
            $warnings = [];
            
            // Normalize data
            $email = strtolower(trim($row['email'] ?? ''));
            $name = trim($row['name'] ?? '');
            $type = strtolower(trim($row['type'] ?? ''));
            $deptName = isset($row['department_name']) ? trim($row['department_name']) : null;
            
            // ========================================
            // VALIDATION RULES
            // ========================================
            
            // 1. Required fields
            if (empty($name)) {
                $errors[] = __('admin/employees.import-name-required');
            }
            
            if (empty($email)) {
                $errors[] = __('admin/employees.import-email-required');
            }
            
            if (empty($type)) {
                $errors[] = __('admin/employees.import-type-required');
            }
            
            // 2. Email format
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = __('admin/employees.import-email-invalid');
            }
            
            // 3. Email uniqueness (existing in DB)
            if (!empty($email) && in_array($email, $existingEmails)) {
                $errors[] = __('admin/employees.import-email-exists');
            }
            
            // 4. Email uniqueness (within file)
            if (!empty($email)) {
                if (in_array($email, $emailsInFile)) {
                    $errors[] = __('admin/employees.import-email-duplicate');
                } else {
                    $emailsInFile[] = $email;
                }
            }
            
            // 🔒 5. Valid type - SECURITY: Only allow employee, manager, ceo
            // ✅ FIXED: Changed from 'normal' to 'employee'
            if (!empty($type)) {
                if (!in_array($type, ['employee', 'manager', 'ceo'])) {
                    $errors[] = __('admin/employees.import-type-invalid');
                }
                
                // 🔒 SECURITY: Explicitly block admin/owner roles
                if (in_array($type, ['admin', 'owner'])) {
                    $errors[] = __('admin/employees.import-admin-blocked');
                }
            }
            
            // 6. Wage validation
            if (!empty($row['wage'])) {
                if (!is_numeric($row['wage'])) {
                    $errors[] = __('admin/employees.import-wage-numeric');
                } elseif ((float)$row['wage'] < 0) {
                    $errors[] = __('admin/employees.import-wage-negative');
                }
            }
            
            // 7. Currency validation
            if (!empty($row['currency']) && strlen($row['currency']) !== 3) {
                $errors[] = __('admin/employees.import-currency-invalid');
            }
            
            // 8. Department logic (multi-level only)
            if ($enableMultiLevel && !empty($deptName)) {
                $deptLower = strtolower($deptName);
                
                if (!in_array($deptLower, $existingDepartments) && !in_array($deptLower, $newDepartments)) {
                    $newDepartments[] = $deptLower;
                    $warnings[] = __('admin/employees.import-dept-will-create', ['dept' => $deptName]);
                }
                
                // Manager + Department = becomes dept manager
                if ($type === 'manager') {
                    $warnings[] = __('admin/employees.import-manager-assigned', ['dept' => $deptName]);
                } elseif ($type === 'employee') {  // ✅ FIXED: Changed from 'normal' to 'employee'
                    $warnings[] = __('admin/employees.import-employee-assigned', ['dept' => $deptName]);
                }
            } elseif ($type === 'manager' && empty($deptName) && $enableMultiLevel) {
                $warnings[] = __('admin/employees.import-manager-no-dept');
            }
            
            // 9. CEO should not have department
            if ($type === 'ceo' && !empty($deptName)) {
                $warnings[] = __('admin/employees.import-ceo-dept-ignored');
            }
            
            // Categorize row
            $rowData = [
                'row_number' => $rowNumber,
                'data' => $row,
                'messages' => array_merge($errors, $warnings),
            ];
            
            if (!empty($errors)) {
                $rowData['status'] = 'error';
                $invalidRows[] = $rowData;
            } elseif (!empty($warnings)) {
                $rowData['status'] = 'warning';
                $warningRows[] = $rowData;
            } else {
                $rowData['status'] = 'valid';
                $validRows[] = $rowData;
            }
        }
        
        $totalValid = count($validRows) + count($warningRows);
        
        return [
            'valid' => count($invalidRows) === 0,
            'show_limit_warning' => $showLimitWarning,
            'limit_info' => [
                'current' => $currentCount,
                'limit' => $employeeLimit,
                'importing' => count($rows),
                'total_after' => $currentCount + $totalValid,
            ],
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => count($validRows),
                'warning_rows' => count($warningRows),
                'error_rows' => count($invalidRows),
                'new_departments' => count($newDepartments),
            ],
            'rows' => array_merge($validRows, $warningRows, $invalidRows),
            'new_departments_list' => array_values(array_unique($newDepartments)),
        ];
    }
}