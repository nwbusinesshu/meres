<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Exceptions\AuthFailException;
use App\Models\Enums\UserType;
use App\Models\User;

class Auth
{
  /**
   * Authenticates and authorizes the user.
   *
   * @param  Request  $request
   * @param  Closure  $next
   * @param  string  $utype list of the usertypes that are allowed
   * @return void redirect() | abort()
   */
  public function handle(Request $request, Closure $next, $utype)
  {
    if($utype != UserType::GUEST){
      try{
        // checking if user is logged in
        if(!$this->isLoggedIn()){
          throw new AuthFailException(__('auth.login-first'), 'login');
        }
  
        // logging off if there is a newer login
        if(session()->getId() != User::find(session('uid'))->getLastLogin()->token){
          $request->session()->flush();
          return redirect('login')->with('warning', __('login.other-device-logout'));
        }
  
        // authorize user
        if(!$this->isAuthorized($utype)){
          throw new AuthFailException(__('auth.access-denied'), 'home-redirect');
        }
      }catch(AuthFailException $e){
        return ! $request->expectsJson()
          ? redirect($e->getFallback())->with('error', $e->getMessage())
          : abort(403, $e->getMessage());
      }
    }else{
      try{
        // checking if user is logged in
        if(Auth::isLoggedIn()){
          return redirect('home-redirect');
          //throw new AuthFailException(__('auth.already-logged-in'), 'home-redirect');
        }
      }catch(AuthFailException $e){
        return ! $request->expectsJson()
          ? redirect($e->getFallback())->with('error', $e->getMessage())
          : abort(403, $e->getMessage());
      }
    }
    
    return $next($request);
  }
  
  /**
   * Check if the current user is logged in
   *
   * @return bool
   */
  public static function isLoggedIn(){
    return session('uid') ? true : false;
  }
  
  /**
   * Check if the current user is authorized for the given type
   * 
   * @param  string $utype
   * @return bool
   */
  public static function isAuthorized($utype){
    if(!session()->has('utype')){ return false; }
    
    if(session('utype') == UserType::ADMIN){
      return true;
    }

    if($utype == session('utype')){
      return true;
    }

    if($utype == UserType::NORMAL && session('utype') == UserType::CEO){
      return true;
    }
    
    return false;
  }
}