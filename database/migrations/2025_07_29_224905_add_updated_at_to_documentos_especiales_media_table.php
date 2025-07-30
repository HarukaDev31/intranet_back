<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedAtToDocumentosEspecialesMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bd_productos_regulaciones_documentos_especiales_media', function (Blueprint $table) {
            if (!Schema::hasColumn('bd_productos_regulaciones_documentos_especiales_media', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->comment('Fecha de actualización');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bd_productos_regulaciones_documentos_especiales_media', function (Blueprint $table) {
            if (Schema::hasColumn('bd_productos_regulaciones_documentos_especiales_media', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
}
