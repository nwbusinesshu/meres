<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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

        // --- Users (+ pivot, dept, role) ---
        $users = DB::table('organization_user as ou')
            ->join('user as u', 'u.id', '=', 'ou.user_id')
            ->leftJoin('organization_departments as d', 'd.id', '=', 'ou.department_id')
            ->where('ou.organization_id', $orgId)
            ->whereNull('u.removed_at')
            ->orderBy('u.name')
            ->get([
                'u.id',
                'u.name',
                'u.email',
                'u.type',
                'u.password',
                'ou.department_id',
                'ou.role',
            ]);

        // --- User competencies (JOIN + szűrés a konzisztens készletre) ---
        $userCompMap = DB::table('user_competency as uc')
            ->join('competency as c', 'c.id', '=', 'uc.competency_id')
            ->where('uc.organization_id', $orgId)
            ->whereNull('c.removed_at') // csak aktív kompetenciák
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

        $usersPayload = [];
        foreach ($users as $u) {
            $usersPayload[] = [
                'id'            => (int) $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'type'          => $u->type,     // 'superadmin' / 'admin' / 'normal' stb.
                'role'          => $u->role,     // 'owner' / 'admin' / 'manager' / 'employee'
                'department_id' => $u->department_id ? (int) $u->department_id : null,
                'login_mode'    => $u->password ? 'password' : 'passwordless',
                'competencies'  => $userCompMap[$u->id] ?? [],
            ];
        }

        // --- Departments (manager névvel) ---
        $departments = DB::table('organization_departments as od')
            ->leftJoin('user as mu', 'mu.id', '=', 'od.manager_id')
            ->where('od.organization_id', $orgId)
            ->whereNull('od.removed_at')
            ->orderBy('od.department_name')
            ->get([
                'od.id',
                'od.department_name',
                'od.manager_id',
                'mu.name as manager_name',
            ])
            ->map(fn ($d) => [
                'id'           => (int) $d->id,
                'name'         => $d->department_name,
                'manager_id'   => $d->manager_id ? (int) $d->manager_id : null,
                'manager_name' => $d->manager_name,
            ])
            ->values()
            ->all();

        // --- Relations ---
        $relations = DB::table('user_relation')
            ->where('organization_id', $orgId)
            ->orderBy('user_id')
            ->get(['user_id', 'target_id', 'type', 'organization_id'])
            ->map(fn ($r) => [
                'user_id'        => (int) $r->user_id,
                'target_id'      => (int) $r->target_id,
                'type'           => $r->type, // 'self' | 'colleague' | 'subordinate' | ...
                'organization_id'=> (int) $r->organization_id,
            ])
            ->values()
            ->all();

        // --- Locale ---
        $locale = app()->getLocale() ?? config('app.locale') ?? 'hu';

        return [
            'captured_at' => Carbon::now('UTC')->toIso8601String(),
            'organization' => [
                'id'   => (int) $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
            ],
            'config'      => $config,
            'users'       => $usersPayload,
            'departments' => $departments,
            'relations'   => $relations,
            'i18n'        => ['locale' => $locale],
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
}
