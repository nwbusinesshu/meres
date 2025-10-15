<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

use App\Models\Organization;
use App\Models\OrganizationProfile;
use App\Models\User;
use App\Models\Enums\UserType;
use App\Services\OrgConfigService;
use App\Services\PasswordSetupService;
use Illuminate\Support\Facades\Schema;


class RegistrationController extends Controller
{
    /**
     * Többlépéses regisztráció nézet
     */
    public function show(Request $request)
    {
        return view('register');
    }

    /**
     * Véglegesítés: org + admin + profil + config + ceo rangok + initial payment + password-setup email
     */
    public function register(Request $request)
    {
        $country = strtoupper($request->input('country_code', 'HU'));
        $isHu = ($country === 'HU');
        $EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','HU'];
        $isEu = in_array($country, $EU, true);

        // Base validation rules
        $rules = [
            // 1) Admin - REQUIRED
            'admin_name'  => 'required|string|max:255',
            'admin_email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('user', 'email'),
            ],

            // 2) Company + Billing - REQUIRED fields
            'org_name'       => 'required|string|max:255',
            'country_code'   => 'required|string|size:2',
            'employee_limit' => 'required|integer|min:1',
            'postal_code'    => 'required|string|max:16',
            'city'           => 'required|string|max:64',
            'street'         => 'required|string|max:128',
            'house_number'   => 'required|string|max:32',
            'phone'          => 'required|string|max:32',
            
            // Region: optional (hidden for HU)
            'region'         => 'nullable|string|max:64',

            // 3) Settings - optional checkboxes
            'ai_telemetry_enabled' => 'nullable|in:on,1,true',
            'enable_multi_level'   => 'nullable|in:on,1,true',
            'show_bonus_malus'     => 'nullable|in:on,1,true',
        ];

        // Conditional validation for tax fields
        if ($isHu) {
            // Hungary: tax_number required, eu_vat_number optional
            $rules['tax_number'] = 'required|string|min:8|max:191';
            $rules['eu_vat_number'] = 'nullable|string|max:32';
        } elseif ($isEu) {
            // Other EU: eu_vat_number required, tax_number not used
            $rules['eu_vat_number'] = 'required|string|max:32';
            $rules['tax_number'] = 'nullable|string|max:191';
        } else {
            // Non-EU: both optional
            $rules['tax_number'] = 'nullable|string|max:191';
            $rules['eu_vat_number'] = 'nullable|string|max:32';
        }

        $request->validate($rules);

        // reCAPTCHA validation
        if (!\App\Services\RecaptchaService::validateRequest($request)) {
            return back()
                ->withErrors(['g-recaptcha-response' => __('auth.recaptcha_failed')])
                ->withInput($request->except('g-recaptcha-response'));
        }

        // Additional validation: uniqueness checks for tax numbers
        $tax = trim((string) $request->input('tax_number', ''));
        $euVat = strtoupper(trim((string) $request->input('eu_vat_number', '')));

        if ($isHu) {
            // Hungary: tax_number must be unique
            if ($tax === '' || mb_strlen($tax) < 8) {
                return back()->withErrors(['tax_number' => __('register.errors.tax_number_invalid')])->withInput();
            }
            $dup = DB::table('organization_profiles')
                ->whereNotNull('tax_number')
                ->where('tax_number', $tax)
                ->exists();
            if ($dup) {
                return back()->withErrors(['tax_number' => __('register.errors.tax_number_exists')])->withInput();
            }

            // EU VAT is optional for Hungary, but if provided, check format and uniqueness
            if ($euVat !== '') {
                if (!preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                    return back()->withErrors(['eu_vat_number' => __('register.errors.eu_vat_invalid_format')])->withInput();
                }
                $dup = DB::table('organization_profiles')
                    ->whereNotNull('eu_vat_number')
                    ->where(DB::raw('UPPER(TRIM(eu_vat_number))'), '=', $euVat)
                    ->exists();
                if ($dup) {
                    return back()->withErrors(['eu_vat_number' => __('register.errors.eu_vat_exists')])->withInput();
                }
            }
        } elseif ($isEu) {
            // Other EU: EU VAT must be valid and unique
            if ($euVat === '' || !preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                return back()->withErrors(['eu_vat_number' => __('register.errors.eu_vat_invalid_or_missing')])->withInput();
            }
            $dup = DB::table('organization_profiles')
                ->whereNotNull('eu_vat_number')
                ->where(DB::raw('UPPER(TRIM(eu_vat_number))'), '=', $euVat)
                ->exists();
            if ($dup) {
                return back()->withErrors(['eu_vat_number' => __('register.errors.eu_vat_exists')])->withInput();
            }
        }

        return DB::transaction(function () use ($request, $country) {

            // 1) Szervezet létrehozása
            $org = Organization::create([
                'name'       => $request->input('org_name'),
                'created_at' => now(),
            ]);

            // 2) Admin user létrehozása – aktív duplikátum ellenőrzés
            $existingActive = User::where('email', $request->input('admin_email'))
                ->when(Schema::hasColumn('user','removed_at'), fn($q) => $q->whereNull('removed_at'))
                ->when(Schema::hasColumn('user','deleted_at'), fn($q) => $q->whereNull('deleted_at'))
                ->first();

            if ($existingActive) {
                return back()->withErrors(['admin_email' => __('register.errors.email_exists')])->withInput();
            }

            // Új user rekord
            $user = User::create([
                'name'       => $request->input('admin_name'),
                'email'      => $request->input('admin_email'),
                'created_at' => now(),
            ]);

            $user->type = UserType::ADMIN;
            $user->save();

            // 3) Kapcsolás (pivot) – admin szerep
            $org->users()->attach($user->id, ['role' => 'admin']);

            // 4) Profil (számlázás + telefon + employee_limit)
            OrganizationProfile::create([
                'organization_id'   => $org->id,
                'country_code'      => $country,
                'postal_code'       => $request->input('postal_code'),
                'region'            => $request->input('region'),
                'city'              => $request->input('city'),
                'street'            => $request->input('street'),
                'house_number'      => $request->input('house_number'),
                'phone'             => $request->input('phone'),
                'employee_limit'    => $request->input('employee_limit'),
                'tax_number'        => $request->input('tax_number'),
                'eu_vat_number'     => strtoupper((string) $request->input('eu_vat_number')),
                'subscription_type' => 'pro',
                'created_at'        => now(),
            ]);

            // 5) Alap CEO rangok átmásolása
            $defaultRanks = \App\Models\CeoRank::whereNull('organization_id')->get();
            foreach ($defaultRanks as $rank) {
                \App\Models\CeoRank::create([
                    'organization_id' => $org->id,
                    'name'            => $rank->name,
                    'value'           => $rank->value,
                    'min'             => $rank->min,
                    'max'             => $rank->max,
                    'removed_at'      => null,
                ]);
            }

            // 6) Alapbeállítások mentése
            $aiTelemetry = $request->boolean('ai_telemetry_enabled');
            $multiLevel  = $request->boolean('enable_multi_level');
            $showBM      = $request->boolean('show_bonus_malus');

            OrgConfigService::setBool($org->id, OrgConfigService::AI_TELEMETRY_KEY, $aiTelemetry);
            OrgConfigService::setBool($org->id, 'enable_multi_level', $multiLevel);
            OrgConfigService::setBool($org->id, 'show_bonus_malus', $showBM);

            // 7) Default Bonus/Malus multipliers (Hungarian standard)
            $defaultMultipliers = [
                1 => 0.00, 2 => 0.40, 3 => 0.70, 4 => 0.90, 5 => 1.00,
                6 => 1.50, 7 => 2.00, 8 => 2.75, 9 => 3.50, 10 => 4.25,
                11 => 5.25, 12 => 6.25, 13 => 7.25, 14 => 8.25, 15 => 10.00
            ];

            $bonusConfigData = [];
            foreach ($defaultMultipliers as $level => $multiplier) {
                $bonusConfigData[] = [
                    'organization_id' => $org->id,
                    'level' => $level,
                    'multiplier' => $multiplier
                ];
            }
            DB::table('bonus_malus_config')->insert($bonusConfigData);

            // 7) INITIAL PAYMENT CREATION
            $employeeLimit = (int) $request->input('employee_limit', 1);
            $amountHuf = (int) ($employeeLimit * 950);

            if ($amountHuf > 0) {
                DB::table('payments')->insert([
                    'organization_id' => $org->id,
                    'assessment_id'   => null,
                    'amount_huf'      => $amountHuf,
                    'status'          => 'initial',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // 8) Password-setup email küldése
            PasswordSetupService::createAndSend($org->id, $user->id, null);

            // 9) Válasz – vissza a loginra
            return redirect()
                ->route('login')
                ->with('success', __('register.success_message'));
        });
    }

    /**
     * AJAX validáció lépésenként (email + adószám egyediség)
     */
    public function validateStepAjax(Request $request)
    {
        $step = (int) $request->input('step', -1);

        if ($step === 0) {
            // EMAIL egyediség
            $email = trim((string) $request->input('admin_email', ''));
            if ($email === '') {
                return response()->json(['ok' => false, 'errors' => ['admin_email' => __('register.errors.email_required')]], 200);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['ok' => false, 'errors' => ['admin_email' => __('register.errors.email_invalid')]], 200);
            }

            $exists = DB::table('user')
                ->where('email', $email)
                ->when(Schema::hasColumn('user','removed_at'), fn($q) => $q->whereNull('removed_at'))
                ->when(Schema::hasColumn('user','deleted_at'), fn($q) => $q->whereNull('deleted_at'))
                ->exists();

            if ($exists) {
                return response()->json(['ok' => false, 'errors' => ['admin_email' => __('register.errors.email_in_use')]], 200);
            }

            return response()->json(['ok' => true], 200);

        } elseif ($step === 1) {
            // Adószám / EU ÁFA egyediség
            $country = strtoupper(trim((string) $request->input('country_code', 'HU')));
            $tax = trim((string) $request->input('tax_number', ''));
            $euVat = strtoupper(trim((string) $request->input('eu_vat_number', '')));
            
            $EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','HU'];
            $isHu = ($country === 'HU');
            $isEu = in_array($country, $EU, true);

            $errors = [];

            if ($isHu) {
                // Hungary: check tax_number
                if ($tax !== '') {
                    $dup = DB::table('organization_profiles')
                        ->whereNotNull('tax_number')
                        ->where('tax_number', $tax)
                        ->exists();
                    if ($dup) {
                        $errors['tax_number'] = __('register.errors.tax_number_exists');
                    }
                }

                // EU VAT optional for Hungary
                if ($euVat !== '') {
                    if (!preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                        $errors['eu_vat_number'] = __('register.errors.eu_vat_invalid_format');
                    } else {
                        $dup = DB::table('organization_profiles')
                            ->whereNotNull('eu_vat_number')
                            ->where(DB::raw('UPPER(TRIM(eu_vat_number))'), '=', $euVat)
                            ->exists();
                        if ($dup) {
                            $errors['eu_vat_number'] = __('register.errors.eu_vat_exists');
                        }
                    }
                }
            } elseif ($isEu) {
                // Other EU: check eu_vat_number
                if ($euVat !== '') {
                    if (!preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                        $errors['eu_vat_number'] = __('register.errors.eu_vat_invalid_format');
                    } else {
                        $dup = DB::table('organization_profiles')
                            ->whereNotNull('eu_vat_number')
                            ->where(DB::raw('UPPER(TRIM(eu_vat_number))'), '=', $euVat)
                            ->exists();
                        if ($dup) {
                            $errors['eu_vat_number'] = __('register.errors.eu_vat_exists');
                        }
                    }
                }
            }

            if (!empty($errors)) {
                return response()->json(['ok' => false, 'errors' => $errors], 200);
            }

            return response()->json(['ok' => true], 200);
        }

        return response()->json(['ok' => true], 200);
    }
}