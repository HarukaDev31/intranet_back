<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (!Schema::hasColumn('calculadora_importacion', 'tipo_cotizacion')) {
                $table->enum('tipo_cotizacion', ['PESO', 'VOLUMEN'])
                    ->default('VOLUMEN')
                    ->after('tipo_cliente');
            }
        });

        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion', 'tipo_cotizacion')) {
                $table->enum('tipo_cotizacion', ['PESO', 'VOLUMEN'])
                    ->default('VOLUMEN')
                    ->after('tipo_servicio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculadora_importacion', function (Blueprint $table) {
            if (Schema::hasColumn('calculadora_importacion', 'tipo_cotizacion')) {
                $table->dropColumn('tipo_cotizacion');
            }
        });

        Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'tipo_cotizacion')) {
                $table->dropColumn('tipo_cotizacion');
            }
        });
    }
};
