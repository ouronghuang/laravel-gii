<?php

namespace Orh\LaravelGii;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    protected $defer = true;

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gii.php' => config_path('gii.php'),
            ], 'gii-config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gii.php', 'gii');
    }
}