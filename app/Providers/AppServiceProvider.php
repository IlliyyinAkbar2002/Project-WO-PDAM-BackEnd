<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
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
        if (config('app.env') === 'local' || config('app.env') === 'development') {
        // Paksa semua URL menjadi HTTPS agar Laravel tahu bahwa koneksi aman
        URL::forceScheme('https');
        Config::set('session.same_site', 'None');
    }
    }
}
