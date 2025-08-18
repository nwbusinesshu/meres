<?php

namespace App\Services;

use Illuminate\Support\Facades\Lang;

class WelcomeMessageService
{
  public static function generate(){
    $hournow = date('H')*1;
    $dayState = 'evening';
    if($hournow >= 5 && $hournow < 9){
      $dayState = 'morning';
    }else if($hournow >= 9 && $hournow < 19){
      $dayState = 'day';
    }

    $messageParts = collect(__("welcomeMessages.$dayState"));
    $messageEnds = collect(["😀","🙂","😉","😁","😜","😄"]);
    $name = collect(explode(' ', session('uname')))->last();

    return $messageParts->random().', '.$name.'! '.$messageEnds->random();
  }
}
