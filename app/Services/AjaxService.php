<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AjaxService
{ 
  public static function DBTransaction(\Closure $func){
    try{
      $returned = DB::transaction($func);
    }catch(\Exception $e){
      $msg = config("app.debug") ? $e->getMessage() : $e->getLine();
      return AjaxService::error([__('global.database-error'), $msg]);
    }
    if(!is_null($returned)){
      return AjaxService::error($returned);
    }
    return null;
  }

  public static function error($msg = []){
    if(!is_array($msg)){
      $msg = [$msg];
    }
    throw ValidationException::withMessages($msg);
    exit();
  }
}