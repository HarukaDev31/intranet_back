<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSeguimientoToCalculadoraImportacion extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('calculadora_razon_descarte')) {
            Schema::create('calculadora_razon_descarte', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('calculadora_importacion')) {
            if (!Schema::hasColumn('calculadora_importacion', 'seguimiento')) {
                Schema::table('calculadora_importacion', function (Blueprint $table) {
                    $table->string('seguimiento', 20)->nullable()->after('estado');
                });
            }
            if (!Schema::hasColumn('calculadora_importacion', 'id_razon_descarte')) {
                Schema::table('calculadora_importacion', function (Blueprint $table) {
                    $table->unsignedInteger('id_razon_descarte')->nullable()->after('seguimiento');
                    $table->foreign('id_razon_descarte', 'fk_calc_imp_razon_descarte')
                        ->references('id')
                        ->on('calculadora_razon_descarte')
                        ->onDelete('set null');
                });
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('calculadora_importacion')) {
            if (Schema::hasColumn('calculadora_importacion', 'id_razon_descarte')) {
                Schema::table('calculadora_importacion', function (Blueprint $table) {
                    $table->dropForeign('fk_calc_imp_razon_descarte');
                    $table->dropColumn('id_razon_descarte');
                });
            }
            if (Schema::hasColumn('calculadora_importacion', 'seguimiento')) {
                Schema::table('calculadora_importacion', function (Blueprint $table) {
                    $table->dropColumn('seguimiento');
                });
            }
        }

        Schema::dropIfExists('calculadora_razon_descarte');
    }
}
