<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Packing China (lista_embarque_url) ya cierra el contenedor.
 * Packing Real es un segundo archivo opcional visible en documentación.
 */
return new class extends Migration
{
    public function up()
    {
        $contenedor = 'carga_consolidada_contenedor';
        $folders = 'contenedor_consolidado_documentacion_folders';

        if (Schema::hasTable($contenedor) && !Schema::hasColumn($contenedor, 'lista_embarque_real_url')) {
            Schema::table($contenedor, function (Blueprint $table) {
                $table->string('lista_embarque_real_url', 500)->nullable()->after('lista_embarque_uploaded_at');
            });
        }

        if (Schema::hasTable($folders)) {
            // Alinear nombre global del packing de China
            DB::table($folders)
                ->whereNull('id_contenedor')
                ->whereRaw("LOWER(TRIM(folder_name)) IN ('packing list', 'packing list china')")
                ->where('id', 1)
                ->update(['folder_name' => 'Packing China']);

            DB::table($folders)
                ->whereRaw("LOWER(TRIM(folder_name)) = 'packing list china'")
                ->update(['folder_name' => 'Packing China']);

            $existsReal = DB::table($folders)
                ->whereNull('id_contenedor')
                ->whereRaw("LOWER(TRIM(folder_name)) = 'packing real'")
                ->exists();

            if (!$existsReal) {
                $insert = [
                    'folder_name' => 'Packing Real',
                    'id_contenedor' => null,
                    'only_doc_profile' => 0,
                ];
                if (Schema::hasColumn($folders, 'organizacion_id')) {
                    $insert['organizacion_id'] = 1;
                }
                if (Schema::hasColumn($folders, 'created_at')) {
                    $insert['created_at'] = now();
                }
                if (Schema::hasColumn($folders, 'updated_at')) {
                    $insert['updated_at'] = now();
                }
                DB::table($folders)->insert($insert);
            } else {
                // Carpetas globales de docs usan org 1 (como Packing China) para pasar OrganizacionScope
                DB::table($folders)
                    ->whereNull('id_contenedor')
                    ->whereRaw("LOWER(TRIM(folder_name)) = 'packing real'")
                    ->where(function ($q) {
                        $q->whereNull('organizacion_id')->orWhere('organizacion_id', 0);
                    })
                    ->update(['organizacion_id' => 1]);
            }
        }
    }

    public function down()
    {
        $contenedor = 'carga_consolidada_contenedor';
        $folders = 'contenedor_consolidado_documentacion_folders';

        if (Schema::hasTable($folders)) {
            DB::table($folders)
                ->whereNull('id_contenedor')
                ->whereRaw("LOWER(TRIM(folder_name)) = 'packing real'")
                ->delete();
        }

        if (Schema::hasTable($contenedor) && Schema::hasColumn($contenedor, 'lista_embarque_real_url')) {
            Schema::table($contenedor, function (Blueprint $table) {
                $table->dropColumn('lista_embarque_real_url');
            });
        }
    }
};
