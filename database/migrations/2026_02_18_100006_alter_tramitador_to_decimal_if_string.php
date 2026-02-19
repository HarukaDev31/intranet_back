<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asegurar que tramitador sea decimal(10,2).
     * Si ya se ejecutó 100005 con string, aquí se convierte a decimal.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('consolidado_cotizacion_aduana_tramites', 'tramitador')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE consolidado_cotizacion_aduana_tramites MODIFY tramitador DECIMAL(10,2) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE consolidado_cotizacion_aduana_tramites ALTER COLUMN tramitador TYPE DECIMAL(10,2) USING (tramitador::decimal(10,2))');
        }
    }

    public function down(): void
    {
        //
    }
};
