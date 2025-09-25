<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AddUuidToContenedorConsolidadoCotizacionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar columna UUID a la tabla
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index('uuid');
        });

        // Generar UUIDs para todos los registros existentes
        $this->generateUuidsForExistingRecords();

        // Hacer que la columna UUID sea obligatoria después de generar los UUIDs
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    /**
     * Generar UUIDs para todos los registros existentes
     */
    private function generateUuidsForExistingRecords()
    {
        // Obtener todos los registros que no tienen UUID
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->whereNull('uuid')
            ->select('id')
            ->get();

        foreach ($cotizaciones as $cotizacion) {
            DB::table('contenedor_consolidado_cotizacion')
                ->where('id', $cotizacion->id)
                ->update(['uuid' => Str::uuid()]);
        }

        // Log del proceso
        $totalUpdated = $cotizaciones->count();
        Log::info("UUIDs generados para {$totalUpdated} cotizaciones existentes");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
}
