<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * En WSL + MySQL en Windows (XAMPP), Horizon/queue:work pueden usar mysql_local
 * cuando APP_ENV=local y DB_HOST_LOCAL_WSL está definido (sin cambiar BD por dominio).
 */
class WslLocalDatabaseConnection
{
    public static function applyForQueueWorkers(): void
    {
        if (! static::shouldApply()) {
            return;
        }

        static::useMysqlLocal();
    }

    public static function shouldApply(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if (! env('DB_HOST_LOCAL_WSL')) {
            return false;
        }

        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = implode(' ', $_SERVER['argv'] ?? []);

        return (bool) preg_match('/\b(horizon(:|$|\s)|queue:work|queue:listen|schedule:run)\b/', $argv);
    }

    public static function useMysqlLocal(): void
    {
        config(['database.default' => 'mysql_local']);
        config(['telescope.storage.database.connection' => 'mysql_local']);
        config(['queue.failed.database' => 'mysql_local']);
        DB::setDefaultConnection('mysql_local');
    }
}
