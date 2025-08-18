<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', function (&$view) {
            $prefix = $view->lang_prefix ?? $view->getName();
            $view->with('currentViewName', $view->currentViewName ?? $prefix); 
            $view->with('_', function($key, $params = [], $lang = null) use ($prefix){
                return __(str_replace('.','/',$prefix).'.'.$key, $params, $lang);
            });
        });
    }
}
