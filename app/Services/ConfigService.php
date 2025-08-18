<?php

namespace App\Services;

use App\Models\Config;

class ConfigService
{
  public static function getConfigItem($name){
    return Config::where('name', $name)->first()?->value;
  }
}
