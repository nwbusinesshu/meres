<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\Enums\UserType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class SuperadminOrganizationController extends Controller
{
    public function create()
    {
        return view('superadmin.org.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:user,email',
        ]);

        DB::transaction(function () use ($validated) {
            $org = Organization::create([
                'name' => $validated['organization_name'],
                'created_at' => now(),
            ]);

            $password = Str::random(12); // vagy később küldjük e-mailben

            $admin = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'type' => UserType::ADMIN,
                'password' => Hash::make($password),
            ]);

            $admin->organizations()->attach($org->id, ['role' => 'admin']);

            $defaults = [
            // Módválasztó
            'threshold_mode'        => 'fixed',
            // Fix ponthatárok (klasszikus)
            'normal_level_up'       => '85',
            'normal_level_down'     => '70',
            // Hibrid / Dinamikus előkészítés (alapértékek)
            'threshold_min_abs_up'  => '80', // hibrid: alsó fix küszöb
            'threshold_top_pct'     => '15', // hibrid+dinamikus: felső X%
            'threshold_bottom_pct'  => '20', // dinamikus: alsó Y%
        ];

        foreach ($defaults as $key => $val) {
            DB::table('organization_config')->updateOrInsert(
                ['organization_id' => $org->id, 'name' => $key],
                ['value' => (string) $val]
            );
        }
    });

        return redirect()->route('admin.home')->with('success', 'Új cég és admin sikeresen létrehozva!');
    }
}
