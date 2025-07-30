<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimestampsToAntidumpingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Agregar timestamps a la tabla principal de antidumping
        Schema::table('bd_productos_regulaciones_antidumping', function (Blueprint $table) {
            if (!Schema::hasColumn('bd_productos_regulaciones_antidumping', 'created_at')) {
                $table->timestamp('created_at')->nullable()->comment('Fecha de creación');
            }
            if (!Schema::hasColumn('bd_productos_regulaciones_antidumping', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->comment('Fecha de actualización');
            }
        });

        // Agregar timestamps a la tabla de media
        Schema::table('bd_productos_regulaciones_antidumping_media', function (Blueprint $table) {
            if (!Schema::hasColumn('bd_productos_regulaciones_antidumping_media', 'created_at')) {
                $table->timestamp('created_at')->nullable()->comment('Fecha de creación');
            }
            if (!Schema::hasColumn('bd_productos_regulaciones_antidumping_media', 'updated_at')) {
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
        // Remover timestamps de la tabla principal
        Schema::table('bd_productos_regulaciones_antidumping', function (Blueprint $table) {
            if (Schema::hasColumn('bd_productos_regulaciones_antidumping', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('bd_productos_regulaciones_antidumping', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });

        // Remover timestamps de la tabla de media
        Schema::table('bd_productos_regulaciones_antidumping_media', function (Blueprint $table) {
            if (Schema::hasColumn('bd_productos_regulaciones_antidumping_media', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('bd_productos_regulaciones_antidumping_media', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
}
