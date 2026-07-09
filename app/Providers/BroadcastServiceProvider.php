<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $driver = config('broadcasting.default');

        if (in_array($driver, [null, 'null'], true)) {
            return;
        }

        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
