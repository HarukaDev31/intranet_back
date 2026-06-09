<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COTIZACION_IDS = [9910, 9679];

    /**
     * Corrige calculadoras vinculadas a cotizaciones IMO cuyo flag se perdió al duplicar/editar.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('calculadora_importacion', 'es_imo')) {
            return;
        }

        $ids = implode(',', array_map('intval', self::COTIZACION_IDS));

        DB::statement("
            UPDATE calculadora_importacion
            SET es_imo = 1
            WHERE id_cotizacion IN ({$ids})
        ");

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'es_imo')) {
            DB::statement("
                UPDATE contenedor_consolidado_cotizacion
                SET es_imo = 1
                WHERE id IN ({$ids})
                  AND deleted_at IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('calculadora_importacion', 'es_imo')) {
            return;
        }

        $ids = implode(',', array_map('intval', self::COTIZACION_IDS));

        DB::statement("
            UPDATE calculadora_importacion
            SET es_imo = 0
            WHERE id_cotizacion IN ({$ids})
        ");

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'es_imo')) {
            DB::statement("
                UPDATE contenedor_consolidado_cotizacion
                SET es_imo = 0
                WHERE id IN ({$ids})
                  AND deleted_at IS NULL
            ");
        }
    }
};
