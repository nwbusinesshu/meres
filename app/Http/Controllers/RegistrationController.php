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

            // 2) Admin user (ha létezik e-mail, nem hozzuk létre újra)
            $user = User::firstOrCreate(
                ['email' => $request->input('admin_email')],
                [
                    'name'       => $request->input('admin_name'),
                    'type'       => UserType::ADMIN,
                    'created_at' => now(),
                ]
            );

            // 3) Kapcsolás (pivot) – admin szerep
            $org->users()->attach($user->id, ['role' => 'admin']);

            // 4) Profil (számlázás + telefon) – superadmin store mintájára
            OrganizationProfile::create([
                'organization_id'   => $org->id,
                'country_code'      => $country,
                'postal_code'       => $request->input('postal_code'),
                'region'            => $request->input('region'),
                'city'              => $request->input('city'),
                'street'            => $request->input('street'),
                'house_number'      => $request->input('house_number'),
                'phone'             => $request->input('phone'), // ÚJ mező
                'tax_number'        => $request->input('tax_number'),
                'eu_vat_number'     => strtoupper((string) $request->input('eu_vat_number')),
                'subscription_type' => null,
                'created_at'        => now(),
            ]);

            // 5) Alap CEO rangok átmásolása (ahogy a SuperAdminController.store is teszi)
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

            // 6) Alapbeállítások mentése (checkboxok → bool)
            $aiTelemetry = $request->boolean('ai_telemetry_enabled');
            $multiLevel  = $request->boolean('enable_multi_level');
            $showBM      = $request->boolean('show_bonus_malus');

            // strict anon nem része a regisztrációs wizardnak – default: OFF (false)
            OrgConfigService::setBool($org->id, OrgConfigService::AI_TELEMETRY_KEY, $aiTelemetry);          // ai_telemetry_enabled
            OrgConfigService::setBool($org->id, 'enable_multi_level', $multiLevel);                          // enable_multi_level
            OrgConfigService::setBool($org->id, 'show_bonus_malus', $showBM);                                // show_bonus_malus

            // 7) Password-setup email küldése (MEGLÉVŐ SERVICE)
            PasswordSetupService::createAndSend($org->id, $user->id, null); // setup email küldés :contentReference[oaicite:6]{index=6}

            // 8) Válasz – vissza a loginra tájékoztatással
            return redirect()
                ->route('login')
                ->with('success', 'Sikeres regisztráció. Be tudsz lépni OAuth-tal, vagy állítsd be a jelszavad a kiküldött emailből.');
        });
    }

    public function validateStepAjax(Request $request)
{
    // step: 0 → admin (email), 1 → cég+számlázás (adószám / EU ÁFA)
    $step = (int) $request->input('step', -1);

    if ($step === 0) {
        // EMAIL egyediség
        $email = trim((string) $request->input('admin_email', ''));
        if ($email === '') {
            return response()->json(['ok' => false, 'errors' => ['admin_email' => 'E-mail szükséges.']], 200);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'errors' => ['admin_email' => 'Érvénytelen e-mail cím.']], 200);
        }

        // user tábla neve nálunk "user"
        $exists = \App\Models\User::where('email', $email)->exists();
        if ($exists) {
            return response()->json(['ok' => false, 'errors' => ['admin_email' => 'Ezzel az e-mail címmel már van felhasználó.']], 200);
        }

        return response()->json(['ok' => true], 200);
    }

    if ($step === 1) {
        // ADÓSZÁM / EU ÁFA egyediség org_profiles-ban
        $country = strtoupper((string) $request->input('country_code', 'HU'));
        $tax     = trim((string) $request->input('tax_number', ''));
        $euVat   = strtoupper(trim((string) $request->input('eu_vat_number', '')));

        $errors = [];

        // EU logika: ha EU ország, EU ÁFA kötelező; különben sima adószám kötelező.
        $EU = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','HU'];

        if (in_array($country, $EU, true)) {
            if ($euVat === '') {
                $errors['eu_vat_number'] = 'EU ÁFA-szám kötelező EU-s országoknál.';
            } else {
                // laza minta: 2 betű + 2..12 alfanumerikus
                if (!preg_match('/^[A-Z]{2}[A-Za-z0-9]{2,12}$/', $euVat)) {
                    $errors['eu_vat_number'] = 'Érvénytelen EU ÁFA-szám formátum.';
                } else {
                    $dup = \Illuminate\Support\Facades\DB::table('organization_profiles')
                        ->whereNotNull('eu_vat_number')
                        ->whereRaw('UPPER(eu_vat_number) = ?', [$euVat])
                        ->exists();
                    if ($dup) {
                        $errors['eu_vat_number'] = 'Ezzel az EU ÁFA-számmal már létezik szervezet.';
                    }
                }
            }
        } else {
            if ($tax === '') {
                $errors['tax_number'] = 'Adószám kötelező nem EU-s országoknál.';
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
        }

        if (!empty($errors)) {
            return response()->json(['ok' => false, 'errors' => $errors], 200);
        }
        return response()->json(['ok' => true], 200);
    }

    return response()->json(['ok' => false, 'errors' => ['step' => 'Ismeretlen lépés.']], 200);
}

}
