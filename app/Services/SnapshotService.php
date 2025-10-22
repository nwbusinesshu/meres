<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Enums\OrgRole;

class SnapshotService
{
    public const VERSION = 'v1';

    /**
     * Összerakja az org snapshotot a megadott szervezethez.
     */
    public function buildOrgSnapshot(int $orgId): array
{
    // --- Organization ---
    $org = DB::table('organization')
        ->where('id', $orgId)
        ->select('id', 'name', 'slug')
        ->first();

    if (!$org) {
        throw new \RuntimeException("Organization not found: {$orgId}");
    }

    // --- Config (kulcs -> érték) + típus-casting ---
    $cfg = DB::table('organization_config')
        ->where('organization_id', $orgId)
        ->pluck('value', 'name')
        ->toArray();

    $config = $this->castConfig($cfg);

    $enableBonusCalculation = OrgConfigService::getBool($orgId, 'enable_bonus_calculation', false);

    // --- Users (+ pivot, dept, role) ---
    // Megjegyzés: organization_user tartalmazza a department_id-t és a role-t (owner/admin/manager/employee)
    $orgUsers = DB::table('organization_user as ou')
        ->join('user as u', 'u.id', '=', 'ou.user_id')
        ->leftJoin('organization_departments as d', function ($join) {
            $join->on('d.id', '=', 'ou.department_id');
        })
        ->where('ou.organization_id', $orgId)
        ->whereNull('u.removed_at')
        ->orderBy('u.name')
        ->get([
            'u.id',
            'u.name',
            'u.email',
            'u.type',          // superadmin/admin/normal ... (felhasználói "type")
            'u.password',
            'ou.department_id',
            'ou.role',         // owner/admin/manager/employee  (szervezeten belüli "role")
        ]);

     foreach ($orgUsers as $user) {
        // ✅ CONDITIONAL: Only get wage data if bonus calculation is enabled
        if ($enableBonusCalculation) {
            $wageData = DB::table('user_wages')
                ->where('user_id', $user->id)
                ->where('organization_id', $orgId)
                ->first(['net_wage', 'currency']);
            
            $user->net_wage = $wageData ? (float) $wageData->net_wage : 0;
            $user->currency = $wageData ? $wageData->currency : 'HUF';
        } else {
            // Bonus calculation disabled - don't store sensitive wage data
            $user->net_wage = 0;
            $user->currency = 'HUF';
        }
    }
    
    // --- User competencies (csak a konzisztens készlet) ---
    $userCompMap = DB::table('user_competency as uc')
        ->join('competency as c', 'c.id', '=', 'uc.competency_id')
        ->where('uc.organization_id', $orgId)
        ->whereNull('c.removed_at')
        ->where(function ($q) use ($orgId) {
            $q->whereNull('c.organization_id')     // globális
              ->orWhere('c.organization_id', $orgId); // vagy céges
        })
        ->orderBy('uc.user_id')
        ->orderBy('uc.competency_id')
        ->get(['uc.user_id', 'uc.competency_id'])
        ->groupBy('user_id')
        ->map(fn ($g) => $g->pluck('competency_id')->values()->all())
        ->toArray();

    // --- Departments (multi-manager támogatással) ---
    $departments = DB::table('organization_departments as od')
        ->where('od.organization_id', $orgId)
        ->whereNull('od.removed_at')
        ->orderBy('od.department_name')
        ->get([
            'od.id',
            'od.department_name',
        ]);

    // Kapcsolótábla: organization_department_managers (sok manager / dept)
    $deptMgrRows = DB::table('organization_department_managers as odm')
        ->join('user as mu', 'mu.id', '=', 'odm.manager_id')
        ->where('odm.organization_id', $orgId)
        ->get([
            'odm.department_id',
            'odm.manager_id',
            'mu.name as manager_name',
        ]);

    // Dept -> [manager_id, ...] és Dept -> [manager_name, ...]
    $deptManagers = [];
    $deptManagerNames = [];
    foreach ($deptMgrRows as $row) {
        $deptManagers[$row->department_id][]     = (int) $row->manager_id;
        $deptManagerNames[$row->department_id][] = $row->manager_name;
    }

    // Dept -> [members user_id, ...]  (az orgUsers alapján)
    $deptMembers = [];
    foreach ($orgUsers as $u) {
        $did = $u->department_id ? (int) $u->department_id : null;
        if ($did) {
            $deptMembers[$did][] = (int) $u->id;
        }
    }

    // Departments payload felépítése
    $departmentsPayload = [];
    foreach ($departments as $d) {
        $did = (int) $d->id;
        $departmentsPayload[] = [
            'id'             => $did,
            'name'           => $d->department_name,
            'manager_ids'    => array_values($deptManagers[$did]     ?? []),    // több manager támogatása
            'manager_names'  => array_values($deptManagerNames[$did] ?? []),
            'member_ids'     => array_values($deptMembers[$did]      ?? []),    // a részleg tagjai
        ];
    }

    // --- Hasznos fordított leképezések a pontszámításhoz ---
    // manager_departments[manager_id] = [dept_id, ...]
    $managerDepartments = [];
    foreach ($deptManagers as $did => $mgrIds) {
        foreach ($mgrIds as $mid) {
            $managerDepartments[$mid][] = (int) $did;
        }
    }

    // manager_of[manager_id] = [user_id, ...] (összes tag, a saját részlegeiből)
    $managerOf = [];
    foreach ($managerDepartments as $mid => $deptIds) {
        $allMembers = [];
        foreach ($deptIds as $did) {
            if (!empty($deptMembers[$did])) {
                $allMembers = array_merge($allMembers, $deptMembers[$did]);
            }
        }
        // deduplikálás, rendezés
        $managerOf[$mid] = array_values(array_unique($allMembers));
        sort($managerOf[$mid]);
    }

    // user_managers[user_id] = [manager_id, ...]
    $userManagers = [];
    foreach ($deptManagers as $did => $mgrIds) {
        foreach ($deptMembers[$did] ?? [] as $uid) {
            foreach ($mgrIds as $mid) {
                $userManagers[$uid][] = (int) $mid;
            }
        }
    }
    foreach ($userManagers as $uid => $arr) {
        $userManagers[$uid] = array_values(array_unique($arr));
        sort($userManagers[$uid]);
    }

    // --- Latest Bonus/Malus per user (organization scoped) ---
// Legfrissebb hónap kiválasztása userenként (DATE mező!)
$latestBmPerUser = DB::table('user_bonus_malus as ubm')
    ->select('ubm.user_id', DB::raw('MAX(ubm.month) as max_month'))
    ->where('ubm.organization_id', $orgId)
    ->groupBy('ubm.user_id');

$bonusMalusRows = DB::table('user_bonus_malus as ubm')
    ->joinSub($latestBmPerUser, 'latest', function ($join) {
        $join->on('ubm.user_id', '=', 'latest.user_id')
             ->on('ubm.month',   '=', 'latest.max_month');
    })
    ->where('ubm.organization_id', $orgId)
    ->get(['ubm.user_id', 'ubm.level', 'ubm.month']);

// user_id -> ['level' => int, 'month' => 'YYYY-MM-DD']
$bonusMalusMap = [];
foreach ($bonusMalusRows as $bm) {
    $bonusMalusMap[(int) $bm->user_id] = [
        'level' => (int) $bm->level,
        'month' => (string) $bm->month, // DATE mezőből string
    ];
}

    $usersPayload = [];
    foreach ($orgUsers as $u) {
        $uid = (int) $u->id;
        $usersPayload[] = [
        'id'             => $uid,
        'name'           => $u->name,
        'email'          => $u->email,
        // GLOBÁLIS user típus (superadmin/admin/normal/ceo)
        'user_type'      => $u->type,
        // Cégen belüli szerep (admin/employee)
        'org_role'       => $u->role,
        'department_id'  => $u->department_id ? (int) $u->department_id : null,
        'login_mode'     => $u->password ? 'password' : 'passwordless',
        'competencies'   => $userCompMap[$uid] ?? [],
        // Menedzseri státusz (multi-manager térképből számolva korábban)
        'is_manager' => ($u->role === OrgRole::MANAGER),
        // CEO flag a globális user_type alapján
        'is_ceo' => ($u->role === OrgRole::CEO),
        // Bonus-Malus legfrissebb rekord (ha van)
        'bonus_malus'    => $bonusMalusMap[$uid] ?? null,
        ];
    }

    // --- Relations ---
    $relations = DB::table('user_relation')
        ->where('organization_id', $orgId)
        ->orderBy('user_id')
        ->get(['user_id', 'target_id', 'type', 'organization_id'])
        ->map(fn ($r) => [
            'user_id'         => (int) $r->user_id,
            'target_id'       => (int) $r->target_id,
            'type'            => $r->type, // 'self' | 'colleague' | 'subordinate' | ...
            'organization_id' => (int) $r->organization_id,
        ])
        ->values()
        ->all();

    // --- Unassigned users (nincs department) ---
    $unassignedUsers = [];
    foreach ($orgUsers as $u) {
        if (empty($u->department_id)) {
            $unassignedUsers[] = (int) $u->id;
        }
    }

    // --- Locale ---
    $locale = app()->getLocale() ?? config('app.locale') ?? 'hu';

    if (empty($usersPayload)) {
            throw new \RuntimeException("Nincsenek aktív felhasználók a szervezetben (org_id: {$orgId})");
    }
        
        if (empty($relations)) {
            throw new \RuntimeException("Nincsenek definiált kapcsolatok a szervezetben (org_id: {$orgId})");
    }

    return [
        'captured_at'        => Carbon::now('UTC')->toIso8601String(),
        'organization'       => [
            'id'   => (int) $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
        ],
        'config'             => $config,
        'users'              => $usersPayload,
        'departments'        => $departmentsPayload,
        'relations'          => $relations,
        // --- ÚJ, pontszámításhoz optimális térképek ---
        'maps'               => [
            'dept_members'       => array_map('array_values', $deptMembers),    // dept_id -> [user_id...]
            'manager_departments' => array_map('array_values', $managerDepartments), // manager_id -> [dept_id...]
            'manager_of'         => $managerOf,                                 // manager_id -> [user_id...]
            'user_managers'      => $userManagers,                              // user_id -> [manager_id...]
            'unassigned_users'   => $unassignedUsers,                           // [user_id...]
        ],
        'i18n'               => ['locale' => $locale],
    ];
}

    /**
     * Típus-casting az org confighoz: bool / int / float.
     */
    private function castConfig(array $cfg): array
    {
        // Ismert booleán kulcsok – bővíthető
        $boolKeys = [
            'enable_multi_level',
            'strict_anonymous_mode',
            'ai_telemetry_enabled',
            'use_telemetry_trust',
            'no_forced_demotion_if_high_cohesion',
        ];

        $out = [];
        foreach ($cfg as $k => $v) {
            if (in_array($k, $boolKeys, true)) {
                $out[$k] = $v === '1' || $v === 1 || $v === true;
            } elseif (is_numeric($v)) {
                $out[$k] = str_contains((string) $v, '.') ? (float) $v : (int) $v;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

   /**
     * Save user results to assessment snapshot.
     * This is called when closing an assessment to cache all user results.
     * 
     * @param int $assessmentId
     * @param array $userResults Array keyed by user_id with result data
     * @return bool Success/failure
     */
    public function saveUserResultsToSnapshot(int $assessmentId, array $userResults): bool
    {
        try {
            return DB::transaction(function () use ($assessmentId, $userResults) {
                // Load current assessment with row lock
                $assessment = DB::table('assessment')
                    ->where('id', $assessmentId)
                    ->lockForUpdate()
                    ->first(['id', 'org_snapshot']);

                if (!$assessment) {
                    Log::error('SnapshotService: Assessment not found', [
                        'assessment_id' => $assessmentId
                    ]);
                    return false;
                }

                // Decode existing snapshot
                $snapshot = json_decode($assessment->org_snapshot, true);
                
                if (!is_array($snapshot)) {
                    Log::error('SnapshotService: Invalid snapshot JSON', [
                        'assessment_id' => $assessmentId,
                        'json_error' => json_last_error_msg()
                    ]);
                    return false;
                }

                // Validate userResults is not empty
                if (empty($userResults)) {
                    Log::error('SnapshotService: Empty user results', [
                        'assessment_id' => $assessmentId
                    ]);
                    return false;
                }

                // Add user_results section
                $snapshot['user_results'] = $userResults;

                // Encode and validate
                $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($snapshotJson === false) {
                    Log::error('SnapshotService: JSON encoding failed', [
                        'assessment_id' => $assessmentId,
                        'json_error' => json_last_error_msg()
                    ]);
                    return false;
                }

                // Check JSON size (should be reasonable, not empty, not > 16MB MySQL limit)
                $jsonSize = strlen($snapshotJson);
                if ($jsonSize === 0) {
                    Log::error('SnapshotService: Generated JSON is empty', [
                        'assessment_id' => $assessmentId
                    ]);
                    return false;
                }
                
                if ($jsonSize > 15000000) { // 15MB, leave margin for MySQL 16MB limit
                    Log::error('SnapshotService: JSON too large', [
                        'assessment_id' => $assessmentId,
                        'size_mb' => round($jsonSize / 1024 / 1024, 2)
                    ]);
                    return false;
                }

                // Save back
                $updated = DB::table('assessment')
                    ->where('id', $assessmentId)
                    ->update(['org_snapshot' => $snapshotJson]);

                if (!$updated) {
                    Log::error('SnapshotService: Database update failed', [
                        'assessment_id' => $assessmentId
                    ]);
                    return false;
                }

                Log::info('SnapshotService: User results saved to snapshot', [
                    'assessment_id' => $assessmentId,
                    'user_count' => count($userResults),
                    'snapshot_size_kb' => round($jsonSize / 1024, 2)
                ]);

                return true;
            });

        } catch (\Throwable $e) {
            Log::error('SnapshotService: Failed to save user results', [
                'assessment_id' => $assessmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
