<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait DatabaseConnectionTrait
{
    /**
     * La BD se define por .env (DB_CONNECTION) en cada despliegue (prod / qa / local).
     * El parámetro $domain se conserva solo por compatibilidad con jobs existentes.
     */
    protected function setDatabaseConnection($domain = null): string
    {
        $connection = (string) config('database.default', 'mysql');
        DB::setDefaultConnection($connection);

        return $connection;
    }
}
