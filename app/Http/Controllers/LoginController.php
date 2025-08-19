<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Enums\UserType;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function index(Request $request){
        return view('login');
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect('login')->with('info', __('login.logged-out-normal'));
    }

    public function triggerLogin(Request $request){
        return Socialite::driver('google')->with(["prompt" => "select_account"])->redirect();
    }

    public function attemptLogin(Request $request){
        try{
            $u = Socialite::driver('google')->user();
        }catch(\Throwable $th){
            return redirect('login')->with('error', __('login.failed-login'));
        }

        /** @var User|null $user */
        $user = User::where('email', $u->getEmail())->whereNull('removed_at')->first();
        if (is_null($user)) {
            abort(403);
        }

        // Alap session adatok
        session([
            'uid'     => $user->id,
            'uname'   => $user->name,
            'utype'   => $user->type,
            'uavatar' => $u->getAvatar(),
        ]);

        // Login naplózása
        $user->logins()->create([
            "logged_in_at" => date('Y-m-d H:i:s'),
            "token"        => session()->getId(),
        ]);

        // --- ORG kiválasztás logika ---
        // 1) SUPERADMIN -> org választó (cégtagságtól függetlenül)
        if ($user->type === UserType::SUPERADMIN) {
            // biztos ami biztos: ne maradjon előző org_id a sessionben
            session()->forget('org_id');
            return redirect()->route('org.select');
        }

        // 2) Nem superadmin:
        //    - ha pontosan 1 org tagja -> automatikus org_id és tovább a home-redirectre
        //    - különben org választó
        $orgIds = $user->organizations()->pluck('organization.id')->toArray(); // pivot: organization_user

        if (count($orgIds) === 1) {
            session(['org_id' => $orgIds[0]]);
            return redirect('home-redirect');
        }

        // több vagy 0 tagság esetén válasszon
        session()->forget('org_id');
        return redirect()->route('org.select');
    }
}
