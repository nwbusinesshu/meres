<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enums\UserType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class OrganizationController extends Controller
{
    public function select(Request $request)
    {
        /** @var User $user */
        $user = User::find(session('uid'));
        $isSuperAdmin = (session('utype') === UserType::SUPERADMIN);

        // SUPERADMIN: lásson mindent
        if ($isSuperAdmin) {
            // ha akarsz szűrni a töröltekre, hagyd benne a whereNull-t
            $orgs = Organization::whereNull('removed_at')->orderBy('name')->get();
        } else {
            // normál/ceo/admin: csak a saját szervezetei
            $orgs = $user->organizations()->whereNull('removed_at')->orderBy('name')->get();
        }

        // ideiglenes napló a hibakereséshez
        Log::info('org.select', [
            'uid'    => session('uid'),
            'utype'  => session('utype'),
            'count'  => $orgs->count(),
            'ids'    => $orgs->pluck('id')->all(),
        ]);

        return view('org.select', [
            'orgs'         => $orgs,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

public function switch(Request $request)
{
    $data = $request->validate([
        'org_id' => ['required', 'integer', 'exists:organization,id'],
    ]);

    $orgId = (int) $data['org_id'];
    $user  = Auth::user();

    // Szuperadmin bárhová válthat
    if ($user->type === UserType::SUPERADMIN) {
        session()->put('org_id', $orgId);
        return to_route('home-redirect')->with('info', 'Szervezet kiválasztva.');
    }

    // Tag-e a felhasználó az adott szervezetben?
    $hasAccess = $user->organizations()
        ->where('organization.id', $orgId)
        ->exists();

    if (! $hasAccess) {
        return to_route('org.select')
            ->with('error', 'Nincs jogosultságod ehhez a szervezethez.');
    }

    session()->put('org_id', $orgId);

    return to_route('home-redirect')->with('info', 'Szervezet kiválasztva.');
}


}
