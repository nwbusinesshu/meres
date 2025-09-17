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
            return abort(403, 'Nincs futó mérés.');
        }

        $orgId   = (int) $assessment->organization_id;
        $userId  = (int) session('uid');
        $utype   = (string) session('utype'); // App\Models\Enums\UserType
        $multiOn = OrgConfigService::getBool($orgId, 'enable_multi_level', false);

        // Rang-kategóriák
        $ranks = CeoRank::where('organization_id', $orgId)
            ->whereNull('removed_at')
            ->orderByDesc('value')
            ->get();

        // Célcsoport (kit lehet rangsorolni)
        [$employees, $totalCount] = $this->getAllowedTargets($orgId, $userId, $utype, $multiOn);

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
        $utype   = (string) session('utype');
        $multiOn = OrgConfigService::getBool($orgId, 'enable_multi_level', false);

        $payload = $request->input('ranks', []);
        if (!is_array($payload)) {
            return AjaxService::error('Hibás kérés: ranks nem tömb.');
        }

        // Engedélyezett célfelhasználók meghatározása (biztonsági guard)
        [$allowedUsers, $totalCount] = $this->getAllowedTargets($orgId, $userId, $utype, $multiOn, wantIds: true);
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
                return AjaxService::error("Ismeretlen rang kategória: {$rid}");
            }
            // minden id-nek engedélyezettnek kell lennie
            foreach ($ids as $id) {
                if (!in_array($id, $allowedIds, true)) {
                    return AjaxService::error("Jogosulatlan felhasználó azonosító: {$id}");
                }
            }
            $rank = $rankRows[$rid];
            $calcMin = is_null($rank->min) ? null : (int) floor($employeesCount * ($rank->min / 100));
            $calcMax = is_null($rank->max) ? null : (int) ceil($employeesCount * ($rank->max / 100));
            $count   = count($ids);
            if (!is_null($calcMin) && $count < $calcMin) {
                $violations[] = "A(z) {$rank->name} kategóriában legalább {$calcMin} fő szükséges.";
            }
            if (!is_null($calcMax) && $count > $calcMax) {
                $violations[] = "A(z) {$rank->name} kategóriában legfeljebb {$calcMax} fő engedélyezett.";
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

        return response()->json(['message' => 'Mentve']);
    }

    /**
     * Meghatározza, kit rangsorolhat az aktuális felhasználó.
     *
     * @return array [\Illuminate\Support\Collection $users, int $totalCount]
     *               ha wantIds=true: [array $usersAsArraysWithId, int $totalCount]
     */
    private function getAllowedTargets(int $orgId, int $raterUserId, string $utype, bool $multiOn, bool $wantIds = false): array
    {
        // Multi-level OFF: csak CEO, minden normal user
        if (!$multiOn) {
            if ($utype !== UserType::CEO) {
                abort(403, 'A rangsor oldalhoz nincs jogosultság.');
            }
            $users = User::where('type', UserType::NORMAL)
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']);
            return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
        }

        // Multi-level ON
        if ($utype === UserType::CEO) {
            // CEO: managerek + részleg nélküli dolgozók
            $managerIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->where('role', 'manager')
                ->pluck('user_id')
                ->all();

            $unassignedIds = DB::table('organization_user')
                ->where('organization_id', $orgId)
                ->whereNull('department_id')
                ->where('role', '!=', 'manager')
                ->pluck('user_id')
                ->all();

            $targetIds = array_values(array_unique(array_merge($managerIds, $unassignedIds)));

            if (empty($targetIds)) {
                $users = collect([]);
                return [$users, 0];
            }

            $users = User::whereIn('id', $targetIds)
                ->where('type', UserType::NORMAL)
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
        }

        // Manager: csak a saját részlege(i) beosztottjai
        $deptIds = DB::table('organization_department_managers')
            ->where('organization_id', $orgId)
            ->where('manager_id', $raterUserId)
            ->pluck('department_id')
            ->all();

        if (empty($deptIds)) {
            abort(403, 'Nincs hozzárendelt részleg.');
        }

        $subordinateIds = DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->whereIn('department_id', $deptIds)
            ->where('role', '!=', 'manager')
            ->pluck('user_id')
            ->all();

        if (empty($subordinateIds)) {
            abort(403, 'Nincs beosztott a részlegeidben.');
        }

        $users = User::whereIn('id', $subordinateIds)
            ->where('type', UserType::NORMAL)
            ->whereNull('removed_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [$wantIds ? $users->map(fn($u) => ['id' => (int)$u->id])->all() : $users, $users->count()];
    }
}
