<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * País de la organización: su ISO-2 (PE, EC…) es el prefijo del code_supplier
 * en cotizaciones resumen (socios). Org 1 (admin) no usa este prefijo.
 */
class AddIdPaisToOrganizacion extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('organizacion') || Schema::hasColumn('organizacion', 'id_pais')) {
            return;
        }

        Schema::table('organizacion', function (Blueprint $table) {
            $table->unsignedInteger('id_pais')->nullable()->after('Txt_Organizacion');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('organizacion') || !Schema::hasColumn('organizacion', 'id_pais')) {
            return;
        }

        Schema::table('organizacion', function (Blueprint $table) {
            $table->dropColumn('id_pais');
        });
    }
}
