<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCreatedByToCalculadoraImportacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->unsignedInteger('created_by')->nullable()->after('id_usuario');
            $table->foreign('created_by')->references('ID_Usuario')->on('usuario')->onDelete('set null');
        });

        // Copiar valores de id_usuario a created_by para registros existentes
        DB::table('calculadora_importacion')
            ->whereNotNull('id_usuario')
            ->update(['created_by' => DB::raw('id_usuario')]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
}
