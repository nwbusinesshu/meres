<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enums\UserType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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
        $request->validate(['org_id' => 'required|integer']);

        $orgId = (int) $request->input('org_id');
        $isSuperAdmin = (session('utype') === UserType::SUPERADMIN);

        if ($isSuperAdmin) {
            // superadmin bármely org-ot választhat
            session(['org_id' => $orgId]);
            return redirect('home-redirect')->with('info', 'Szervezet kiválasztva.');
        }

        // nem superadmin → ellenőrizzük a tagságot
        $user = User::find(session('uid'));
        $allowed = $user->organizations()->where('organization.id', $orgId)->exists();

        if (!$allowed) {
            return redirect()->route('org.select')->with('error', 'Nincs jogosultságod ehhez a szervezethez.');
        }

        session(['org_id' => $orgId]);
        return redirect('home-redirect')->with('info', 'Szervezet kiválasztva.');
    }
}
