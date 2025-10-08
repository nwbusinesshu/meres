<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentBonus;
use App\Models\BonusMalusConfig;
use App\Models\UserWage;
use App\Services\OrgConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBonusesController extends Controller
{
    /**
     * Main bonuses page
     */
    public function index(Request $request)
    {
        $orgId = (int) session('org_id');
        
        // Check if bonus-malus is enabled
        $showBonusMalus = OrgConfigService::getBool($orgId, 'show_bonus_malus', true);
        if (!$showBonusMalus) {
            return redirect()->route('admin.home')
                ->with('error', __('admin/bonuses.feature-disabled'));
        }

        // Get closed assessments for dropdown
        $assessments = Assessment::where('organization_id', $orgId)
            ->whereNotNull('closed_at')
            ->orderBy('closed_at', 'desc')
            ->get(['id', 'started_at', 'closed_at']);

        // Get selected assessment (default to latest)
        $selectedAssessmentId = $request->input('assessment_id', $assessments->first()?->id);

        // Get bonuses for selected assessment
        $bonuses = [];
        $totalBonus = 0;
        $paidCount = 0;
        $unpaidCount = 0;

        if ($selectedAssessmentId) {
            $bonuses = AssessmentBonus::where('assessment_id', $selectedAssessmentId)
                ->with('user')
                ->get();

            $totalBonus = $bonuses->sum('bonus_amount');
            $paidCount = $bonuses->where('is_paid', true)->count();
            $unpaidCount = $bonuses->where('is_paid', false)->count();
        }

        // Multi-level settings
        $enableMultiLevel = OrgConfigService::getBool($orgId, 'enable_multi_level', false);

        // Employee visibility setting
        $employeesSeeBonuses = OrgConfigService::getBool($orgId, 'employees_see_bonuses', false);

        return view('admin.bonuses', [
            'assessments' => $assessments,
            'selectedAssessmentId' => $selectedAssessmentId,
            'bonuses' => $bonuses,
            'totalBonus' => $totalBonus,
            'paidCount' => $paidCount,
            'unpaidCount' => $unpaidCount,
            'enableMultiLevel' => $enableMultiLevel,
            'employeesSeeBonuses' => $employeesSeeBonuses,
        ]);
    }

    /**
     * Get user wage
     */
    public function getWage(Request $request)
    {
        $userId = $request->input('user_id');
        $orgId = (int) session('org_id');

        $wage = UserWage::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->first();

        return response()->json([
            'ok' => true,
            'wage' => $wage ? [
                'net_wage' => $wage->net_wage,
                'currency' => $wage->currency,
            ] : null,
        ]);
    }

    /**
     * Save user wage
     */
    public function saveWage(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:user,id',
            'net_wage' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        $orgId = (int) session('org_id');

        UserWage::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'organization_id' => $orgId,
            ],
            [
                'net_wage' => $request->net_wage,
                'currency' => strtoupper($request->currency),
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Get multiplier config
     */
    public function getMultiplierConfig()
    {
        $orgId = (int) session('org_id');
        
        $config = BonusMalusConfig::where('organization_id', $orgId)
            ->orderBy('level', 'desc')
            ->get();

        return response()->json([
            'ok' => true,
            'config' => $config,
        ]);
    }

    /**
     * Save multiplier config
     */
    public function saveMultiplierConfig(Request $request)
    {
        $request->validate([
            'multipliers' => 'required|array',
            'multipliers.*.level' => 'required|integer|between:1,15',
            'multipliers.*.multiplier' => 'required|numeric|between:0,15',
        ]);

        $orgId = (int) session('org_id');

        DB::transaction(function () use ($request, $orgId) {
            foreach ($request->multipliers as $item) {
                BonusMalusConfig::updateOrCreate(
                    [
                        'organization_id' => $orgId,
                        'level' => $item['level'],
                    ],
                    [
                        'multiplier' => $item['multiplier'],
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Toggle payment status
     */
    public function togglePayment(Request $request)
    {
        $request->validate([
            'bonus_id' => 'required|integer|exists:assessment_bonuses,id',
            'is_paid' => 'required|boolean',
        ]);

        $bonus = AssessmentBonus::findOrFail($request->bonus_id);
        
        $bonus->is_paid = $request->is_paid;
        $bonus->paid_at = $request->is_paid ? now() : null;
        $bonus->save();

        return response()->json(['ok' => true]);
    }
}