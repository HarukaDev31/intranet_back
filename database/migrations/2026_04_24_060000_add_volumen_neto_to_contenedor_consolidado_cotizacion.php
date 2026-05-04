<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'volumen_neto')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->decimal('volumen_neto', 10, 2)->nullable()->after('volumen');
            });
        }

        // Backfill para cotizaciones existentes: tomar el volumen ya registrado.
        DB::table('contenedor_consolidado_cotizacion')
            ->whereNull('volumen_neto')
            ->update(['volumen_neto' => DB::raw('volumen')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'volumen_neto')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropColumn('volumen_neto');
            });
        }
    }
};
