<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        View::composer('*', function (&$view) {
            $prefix = $view->lang_prefix ?? $view->getName();
            $view->with('currentViewName', $view->currentViewName ?? $prefix); 
            $view->with('_', function($key, $params = [], $lang = null) use ($prefix){
                return __(str_replace('.','/',$prefix).'.'.$key, $params, $lang);
            });

            // 🔽 Céges adatok a nézetekbe
            $user = Auth::user();

            if ($user) {
                $organizations = $user->organizations ?? collect();
                $view->with('userOrganizations', $organizations);
                $view->with('currentOrgId', session('org_id'));
            }
        });
    }
}
