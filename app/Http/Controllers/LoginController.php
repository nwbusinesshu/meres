<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $user = User::where('email', $u->getEmail())->whereNull('removed_at')->first();
        if(is_null($user)){
            return abort(403);
        }

        session(['uid' => $user->id]);
        session(['uname' => $user->name]);
        session(['utype' => $user->type]);
        session(['uavatar' => $u->getAvatar()]);

        $user->logins()->create([
            "logged_in_at" => date('Y-m-d H:i:s'),
            "token" =>session()->getId(),
        ]);

        return redirect('home-redirect');
    }
}

