<?php

namespace Unquam\IngWebPaySdk\Providers;

use Illuminate\Support\ServiceProvider;


class IngWebPayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/ing-web-pay.php' => config_path('ing-web-pay.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ing-web-pay.php', 'ing-web-pay'
        );
    }
}