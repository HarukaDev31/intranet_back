<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('calculadora_importacion_proveedores', 'maxcbm')) {
                $table->decimal('maxcbm', 10, 4)->default(0)->after('cbm');
            }
        });

        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'maxcbm')) {
                $table->decimal('maxcbm', 10, 4)->default(0)->after('cbm_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_importacion_proveedores', 'maxcbm')) {
                $table->dropColumn('maxcbm');
            }
        });

        Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'maxcbm')) {
                $table->dropColumn('maxcbm');
            }
        });
    }
};
