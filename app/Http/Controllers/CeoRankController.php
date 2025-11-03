<?php

namespace App\Http\Controllers;

use App\Models\CeoRank;
use App\Models\User;
use App\Models\Enums\UserType;
use App\Services\AjaxService;
use App\Services\AssessmentService;
use App\Services\OrgConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\OrgRole;
use App\Services\RoleHelper; 

class CeoRankController extends Controller
{
    /**
     * Rangsorolási képernyő.
     * - Multi-level OFF: csak CEO fér hozzá, és MINDEN normal user rangsorolható (legacy).
     * - Multi-level ON:
     *      * CEO: csak a managerek + részleg nélküli dolgozók rangsorolhatók.
     *      * Manager: csak a saját részlege(i) beosztottjai rangsorolhatók (ha van legalább egy részleg és abban van beosztott).
     */
    public function index(Request $request)
    {
        $assessment = AssessmentService::getCurrentAssessment();
        if (!$assessment) {
            return abort(403, __('ceorank.no_running_assessment'));
        }

        $orgId   = (int) $assessment->organization_id;
        $userId  = (int) session('uid');
        $orgRole = (string) session('org_role');
        $multiOn = OrgConfigService::getBool($orgId, 'enable_multi_level', false);

        // Get current locale for translations
        $currentLocale = app()->getLocale();

        // Rang-kategóriák
        $ranks = CeoRank::where('organization_id', $orgId)
            ->whereNull('removed_at')
            ->orderByDesc('value')
            ->get();

        // Process translations for each rank
        foreach ($ranks as $rank) {
            $translatedData = $this->getTranslatedName($rank, $currentLocale);
            $rank->translated_name = $translatedData['text'];
            $rank->name_is_fallback = $translatedData['is_fallback'];
        }

        // Célcsoport (kit lehet rangsorolni)
        [$employees, $totalCount] = $this->getAllowedTargets($orgId, $userId, $orgRole, $multiOn);

        // Min/Max abszolút értékek számítása (frontend ezt várja: calcMin/calcMax)
        $employeesCount = $totalCount;
        foreach ($ranks as $rank) {
            $rank->calcMin = is_null($rank->min) ? null : (int) floor($employeesCount * ($rank->min / 100));
            $rank->calcMax = is_null($rank->max) ? null : (int) ceil($employeesCount * ($rank->max / 100));
        }

        return view('ceorank', [
            'ceoranks'  => $ranks,
            'employees' => $employees,
        ]);
    }

    /**
     * Get translated name with fallback logic
     */
    private function getTranslatedName($rank, $currentLocale)
    {
        // If no translations or we're in the original language, return original text
        if (empty($rank->name_json) || $currentLocale === ($rank->original_language ?? 'hu')) {
            return ['text' => $rank->name, 'is_fallback' => false];
        }
        
        $translations = json_decode($rank->name_json, true);
        if (!$translations || !is_array($translations)) {
            return ['text' => $rank->name, 'is_fallback' => true];
        }
        
        // Check if translation exists for current locale
        if (isset($translations[$currentLocale]) && !empty(trim($translations[$currentLocale]))) {
            return ['text' => $translations[$currentLocale], 'is_fallback' => false];
        }
        
        // Fallback to original text
        return ['text' => $rank->name, 'is_fallback' => true];
    }

    /**
     * Rangsor mentése.
     * Vár: ranks = [{rankId: <int>, employees: [userId, ...]}, ...]
     */
    public function submitRanking(Request $request)
    {
        $assessment = AssessmentService::getCurrentAssessment();
        if (!$assessment) {
            return AjaxService::error(__('global.assessment-not-running'));
        }

        $orgId   = (int) $assessment->organization_id;
        $userId  = (int) session('uid');
        $orgRole = (string) session('org_role');
        $multiOn = OrgConfigService::getBool($orgId, 'enable_multi_level', false);

        $payload = $request->input('ranks', []);
        if (!is_array($payload)) {
            return AjaxService::error(__('ceorank.invalid_request_ranks_not_array'));
        }

        // Engedélyezett célfelhasználók meghatározása (biztonsági guard)
        [$allowedUsers, $totalCount] = $this->getAllowedTargets($orgId, $userId, $orgRole, $multiOn, wantIds: true);
        $allowedIds = collect($allowedUsers)->map(fn ($u) => (int) (is_array($u) ? $u['id'] : $u->id))->all();

        // Rank id -> rank adatok
        $rankRows = CeoRank::where('organization_id', $orgId)
            ->whereNull('removed_at')
            ->get()
            ->keyBy('id');

        // Szerver oldali min/max ellenőrzés
        $employeesCount = $totalCount;

        $violations = [];
        foreach ($payload as $row) {
            $rid = (int) ($row['rankId'] ?? 0);
            $ids = array_map('intval', (array) ($row['employees'] ?? []));
            if (!$rid || !isset($rankRows[$rid])) {
                return AjaxService::error(__('ceorank.unknown_rank_category', ['id' => $rid]));
            }
            // minden id-nek engedélyezettnek kell lennie
            foreach ($ids as $id) {
                if (!in_array($id, $allowedIds, true)) {
                    return AjaxService::error(__('ceorank.unauthorized_user_id', ['id' => $id]));
                }
            }
            $rank = $rankRows[$rid];
            $calcMin = is_null($rank->min) ? null : (int) floor($employeesCount * ($rank->min / 100));
            $calcMax = is_null($rank->max) ? null : (int) ceil($employeesCount * ($rank->max / 100));
            $count   = count($ids);
            if (!is_null($calcMin) && $count < $calcMin) {
                $violations[] = __('ceorank.rank_minimum_required', ['rank' => $rank->name, 'min' => $calcMin]);
            }
            if (!is_null($calcMax) && $count > $calcMax) {
                $violations[] = __('ceorank.rank_maximum_exceeded', ['rank' => $rank->name, 'max' => $calcMax]);
            }
        }
        if (!empty($violations)) {
            return AjaxService::error($violations);
        }

        // Mentés tranzakcióban:
        $err = AjaxService::DBTransaction(function () use ($assessment, $userId, $payload, $rankRows) {

            // korábbi rangsor törlése az adott bírálótól
            DB::table('user_ceo_rank')
                ->where('assessment_id', $assessment->id)
                ->where('ceo_id', $userId)
                ->delete();

            // beszúrások
            foreach ($payload as $row) {
                $rid = (int) $row['rankId'];
                $ids = array_map('intval', (array) ($row['employees'] ?? []));
                if (!$rid || empty($ids)) {
                    continue;
                }
                $rank  = $rankRows[$rid];
                $value = (int) $rank->value;

                $insertRows = [];
                foreach ($ids as $uid) {
                    $insertRows[] = [
                        'assessment_id' => (int) $assessment->id,
                        'ceo_id'        => (int) $userId,
                        'user_id'       => (int) $uid,
                        'value'         => (int) $value,
                    ];
                }
                if (!empty($insertRows)) {
                    DB::table('user_ceo_rank')->insert($insertRows);
                }
            }

            return null; // ok
        });

        if ($err !== null) {
            return $err; // AjaxService::error formátum
        }

        return response()->json(['message' => __('ceorank.saved')]);
    }

    /**
     * Meghatározza, kit rangsorolhat az aktuális felhasználó.
     *
     * @return array [\Illuminate\Support\Collection $users, int $totalCount]
     *               ha wantIds=true: [array $usersAsArraysWithId, int $totalCount]
     */
    private function getAllowedTargets(int $orgId, int $raterUserId, string $orgRole, bool $multiOn, bool $wantIds = false): array
    {
        // ✅ FIXED: Multi-level OFF - Exclude both CEO and ADMIN roles
        if (!$multiOn) {
            if ($orgRole !== OrgRole::CEO) {
                abort(403, __('ceorank.no_access_to_ranking'));
            }
            $userIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', '!=', OrgRole::ADMIN)
                ->where('role', '!=', OrgRole::CEO)  // ✅ ADDED: Exclude CEO
                ->pluck('user_id')
                ->all();

            $users = User::whereIn('id', $userIds)
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']);
            return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
        }

        // ✅ FIXED: Multi-level ON - CEO ranks managers + unassigned employees
        if ($orgRole === OrgRole::CEO) {
            // Get all managers (who have departments assigned)
            $managerIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', OrgRole::MANAGER)
                ->pluck('user_id')
                ->all();

            // ✅ FIXED: Get unassigned users, excluding CEO, ADMIN, and MANAGER roles
            $unassignedIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereNull('department_id')
                ->where('role', '!=', OrgRole::MANAGER)  // ✅ Keep existing
                ->where('role', '!=', OrgRole::ADMIN)    // ✅ Keep existing
                ->where('role', '!=', OrgRole::CEO)      // ✅ ADDED: Exclude CEO
                ->pluck('user_id')
                ->all();

            $targetIds = array_values(array_unique(array_merge($managerIds, $unassignedIds)));

            if (empty($targetIds)) {
                $users = collect([]);
                return [$users, 0];
            }

            $users = User::whereIn('id', $targetIds)
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
        }

        // ✅ FIXED: Multi-level ON - Manager ranks their department employees only
        // Manager: csak a saját részlege(i) beosztottjai
        $deptIds = DB::table('organization_department_managers')
            ->where('organization_id', $orgId)
            ->where('manager_id', $raterUserId)
            ->pluck('department_id')
            ->all();

        if (empty($deptIds)) {
            abort(403, __('ceorank.no_assigned_department'));
        }

        // ✅ FIXED: Exclude CEO, ADMIN, and MANAGER roles from subordinates
        $subordinateIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->whereIn('department_id', $deptIds)
            ->where('role', '!=', OrgRole::MANAGER)  // ✅ Keep existing
            ->where('role', '!=', OrgRole::ADMIN)    // ✅ ADDED: Exclude ADMIN
            ->where('role', '!=', OrgRole::CEO)      // ✅ ADDED: Exclude CEO
            ->pluck('user_id')
            ->all();

        if (empty($subordinateIds)) {
            abort(403, __('ceorank.no_subordinates'));
        }

        $users = User::whereIn('id', $subordinateIds)
            ->whereNull('removed_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
    }
}
