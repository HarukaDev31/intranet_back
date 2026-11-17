<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PRY-26-0003 (#1963): Pro Business (organización 1) es cliente de Almacén China en Perú.
 * Si no tiene país de origen, se le pone Perú para que aparezca al elegir ese país en «Nueva operación».
 */
return new class extends Migration
{
    public function up(): void
    {
        $peru = DB::table('pais')
            ->whereRaw("LOWER(REPLACE(No_Pais, 'ú', 'u')) = 'peru'")
            ->orderBy('ID_Pais')
            ->value('ID_Pais');
        if (!$peru) {
            return;
        }

        DB::table('organizacion')
            ->where('ID_Organizacion', 1)
            ->whereNull('pais_origen_id')
            ->update(['pais_origen_id' => (int) $peru]);
    }

    public function down(): void
    {
        // Sin cambios: el país de origen puede haberse editado después.
    }
};
