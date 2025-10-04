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
     * Többlépéses regisztráció nézet (login.blade.css mintájára).
     */
    public function show(Request $request)
    {
        return view('register');
    }

    /**
     * Véglegesítés: org + admin + profil + config + ceo rangok + password-setup email.
     * Úgy viselkedik, mintha superadmin hozta volna létre (azonos logikai lépések).
     */
    public function register(Request $request)
    {
        $country = strtoupper($request->input('country_code', 'HU'));

        $request->validate([
            // 1) Admin
            'admin_name'  => 'required|string|max:255',
            'admin_email' => [
                'required','email','max:255',
                \Illuminate\Validation\Rule::unique('user','email'), // tábla: user
            ],
            'employee_limit' => 'required|integer|min:1',

            // 2) Cég + számlázás
            'org_name'     => 'required|string|max:255',
            'country_code' => 'required|string|size:2',
            'postal_code'  => 'nullable|string|max:16',
            'region'       => 'nullable|string|max:64',
            'city'         => 'nullable|string|max:64',
            'street'       => 'nullable|string|max:128',
            'house_number' => 'nullable|string|max:32',
            'phone'        => 'nullable|string|max:32',

            // 3) Alapbeállítások
            'ai_telemetry_enabled' => 'nullable|in:on,1,true',
            'enable_multi_level'   => 'nullable|in:on,1,true',
            'show_bonus_malus'     => 'nullable|in:on,1,true',
        ]);

        // Országfüggő végső ellenőrzések (adószám / EU ÁFA) – hogy race condition esetén se csússzon át
        $country = strtoupper($request->input('country_code', 'HU'));
        $tax     = trim((string) $request->input('tax_number', ''));
        $euVat   = strtoupper(trim((string) $request->input('eu_vat_number', '')));
        $EU      = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','HU'];

        if (in_array($country, $EU, true)) {
            if ($euVat === '' || !preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                return back()->withErrors(['eu_vat_number' => 'Érvénytelen vagy hiányzó EU ÁFA-szám.'])->withInput();
            }
            $dup = \Illuminate\Support\Facades\DB::table('organization_profiles')
                ->whereNotNull('eu_vat_number')
                ->whereRaw('UPPER(eu_vat_number) = ?', [$euVat])
                ->exists();
            if ($dup) {
                return back()->withErrors(['eu_vat_number' => 'Ezzel az EU ÁFA-számmal már létezik szervezet.'])->withInput();
            }
        } else {
            if ($tax === '' || mb_strlen($tax) < 6) {
                return back()->withErrors(['tax_number' => 'Érvénytelen vagy hiányzó adószám.'])->withInput();
            }
            $dup = \Illuminate\Support\Facades\DB::table('organization_profiles')
                ->whereNotNull('tax_number')
                ->where('tax_number', $tax)
                ->exists();
            if ($dup) {
                return back()->withErrors(['tax_number' => 'Ezzel az adószámmal már létezik szervezet.'])->withInput();
            }
        }

        return DB::transaction(function () use ($request, $country) {

            // 1) Szervezet
            $org = Organization::create([
                'name'       => $request->input('org_name'),
                'created_at' => now(),
            ]);

            // 2) Admin user létrehozása – aktív duplikátum ellenőrzés, töröltet NEM vesszük figyelembe
            $existingActive = \App\Models\User::where('email', $request->input('admin_email'))
                ->when(Schema::hasColumn('user','removed_at'), fn($q) => $q->whereNull('removed_at'))
                ->when(Schema::hasColumn('user','deleted_at'), fn($q) => $q->whereNull('deleted_at'))
                ->first();

            if ($existingActive) {
                return back()->withErrors(['admin_email' => 'Ezzel az e-mail címmel már van aktív felhasználó.'])->withInput();
            }

            // új, tiszta user rekord — akkor is, ha létezett már TÖRÖLT példány ugyanazzal az e-maillel
            $user = \App\Models\User::create([
                'name'       => $request->input('admin_name'),
                'email'      => $request->input('admin_email'),
                'type'       => \App\Models\Enums\UserType::ADMIN,
                'created_at' => now(),
            ]);

            // 3) Kapcsolás (pivot) – admin szerep
            $org->users()->attach($user->id, ['role' => 'admin']);

            // 4) Profil (számlázás + telefon + employee_limit) – superadmin store mintájára
            OrganizationProfile::create([
                'organization_id'   => $org->id,
                'country_code'      => $country,
                'postal_code'       => $request->input('postal_code'),
                'region'            => $request->input('region'),
                'city'              => $request->input('city'),
                'street'            => $request->input('street'),
                'house_number'      => $request->input('house_number'),
                'phone'             => $request->input('phone'),
                'tax_number'        => $request->input('tax_number'),
                'eu_vat_number'     => $request->input('eu_vat_number'),
                'employee_limit'    => $request->input('employee_limit'),
                'subscription_type' => 'pro',
                'created_at'        => now(),
            ]);

            // 5) Config (OrgConfigService)
            OrgConfigService::setBool($org->id, 'ai_telemetry_enabled', (bool) $request->input('ai_telemetry_enabled'));
            OrgConfigService::setBool($org->id, 'enable_multi_level',   (bool) $request->input('enable_multi_level'));
            OrgConfigService::setBool($org->id, 'show_bonus_malus',     (bool) $request->input('show_bonus_malus'));

            // 6) CEO rangok létrehozása (alapértelmezett 4 szint) – superadmin store mintájára
            $this->createDefaultCeoRanks($org->id);

            // 7) Initial payment létrehozása
            $employeeLimit = (int) $request->input('employee_limit');
            $amountHuf = $employeeLimit * 950;
            
            DB::table('payments')->insert([
                'organization_id' => $org->id,
                'assessment_id'   => null,
                'amount_huf'      => $amountHuf,
                'status'          => 'initial',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // 8) Password setup email küldése
            PasswordSetupService::createAndSend($org->id, $user->id, $user->id);


            // redirect welcome screen-re
            return redirect()->route('login')->with('success', 'Regisztráció sikeres! Elküldtük a jelszóbeállítás linkjét e-mailben.');
        });
    }

    /**
     * AJAX validáció lépésenként (email egyediség, adószám/EU VAT egyediség).
     */
    public function validateStepAjax(Request $request)
    {
        $step = (int) $request->input('step', -1);

        // STEP 0: admin email egyediség
        if ($step === 0) {
            $email = trim((string) $request->input('admin_email', ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['ok' => false, 'errors' => ['admin_email' => 'Érvénytelen e-mail cím.']], 200);
            }
            $exists = \App\Models\User::where('email', $email)
                ->when(Schema::hasColumn('user','removed_at'), fn($q) => $q->whereNull('removed_at'))
                ->when(Schema::hasColumn('user','deleted_at'), fn($q) => $q->whereNull('deleted_at'))
                ->exists();
            if ($exists) {
                return response()->json(['ok' => false, 'errors' => ['admin_email' => 'Ezzel az e-mail címmel már van aktív felhasználó.']], 200);
            }
            return response()->json(['ok' => true], 200);
        }

        // STEP 1: org name + adószám/EU VAT egyediség
        if ($step === 1) {
            $errors = [];
            $country = strtoupper(trim((string) $request->input('country_code', 'HU')));
            $tax     = trim((string) $request->input('tax_number', ''));
            $euVat   = strtoupper(trim((string) $request->input('eu_vat_number', '')));
            $EU      = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','HU'];

            if (in_array($country, $EU, true)) {
                // EU ország: kötelező EU VAT
                if ($euVat === '' || !preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                    $errors['eu_vat_number'] = 'Érvénytelen EU ÁFA-szám.';
                } else {
                    $dup = \Illuminate\Support\Facades\DB::table('organization_profiles')
                        ->whereNotNull('eu_vat_number')
                        ->whereRaw('UPPER(eu_vat_number) = ?', [$euVat])
                        ->exists();
                    if ($dup) {
                        $errors['eu_vat_number'] = 'Ezzel az EU ÁFA-számmal már létezik szervezet.';
                    }
                }
            } else {
                // engedékeny alapellenőrzés (min. 6 karakter)
                if (mb_strlen($tax) < 6) {
                    $errors['tax_number'] = 'Érvénytelen adószám.';
                } else {
                    $dup = \Illuminate\Support\Facades\DB::table('organization_profiles')
                        ->whereNotNull('tax_number')
                        ->where('tax_number', $tax)
                        ->exists();
                    if ($dup) {
                        $errors['tax_number'] = 'Ezzel az adószámmal már létezik szervezet.';
                    }
                }
            }

            if (!empty($errors)) {
                return response()->json(['ok' => false, 'errors' => $errors], 200);
            }
            return response()->json(['ok' => true], 200);
        }

        return response()->json(['ok' => false, 'errors' => ['step' => 'Ismeretlen lépés.']], 200);
    }

    /**
     * Alapértelmezett CEO rangok létrehozása (4 szint).
     */
    private function createDefaultCeoRanks(int $orgId): void
{
    $ranks = [
        ['name' => 'Kiemelkedő', 'value' => 100, 'min' => null, 'max' => null],
        ['name' => 'Jó',         'value' => 80,  'min' => null, 'max' => null],
        ['name' => 'Megfelelő',  'value' => 60,  'min' => null, 'max' => null],
        ['name' => 'Fejlesztendő', 'value' => 40, 'min' => null, 'max' => null],
    ];

    foreach ($ranks as $rank) {
        \App\Models\CeoRank::create([
            'organization_id' => $orgId,
            'name'            => $rank['name'],
            'value'           => $rank['value'],
            'min'             => $rank['min'],  // Changed from min_employees
            'max'             => $rank['max'],  // Changed from max_employees
        ]);
    }
}
}