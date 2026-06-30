<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena file_path en carga_consolidada_aduana_files cuando falta,
 * usando la ruta relativa CDN: cargaconsolidada/aduana/{id_contenedor}/{file_name}.
 */
class BackfillCargaConsolidadaAduanaFilesPath extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('carga_consolidada_aduana_files')) {
            return;
        }

        if (!Schema::hasColumn('carga_consolidada_aduana_files', 'file_path')
            || !Schema::hasColumn('carga_consolidada_aduana_files', 'file_name')
            || !Schema::hasColumn('carga_consolidada_aduana_files', 'id_contenedor')) {
            return;
        }

        DB::statement(<<<'SQL'
              UPDATE carga_consolidada_aduana_files
            SET file_path = CONCAT(
                'cargaconsolidada/aduana/',
                id_contenedor,
                '/',
                TRIM(file_name)
            )
            WHERE id_contenedor IS NOT NULL
              AND NULLIF(TRIM(file_name), '') IS NOT NULL
              AND (
                    file_path IS NULL
                    OR TRIM(file_path) = ''
              )
SQL);
    }

    public function down()
    {
        // No se revierten rutas generadas: no hay forma fiable de distinguir
        // filas backfillearon de las que ya tenían file_path válido.
    }
}
