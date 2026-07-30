<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores_items', function (Blueprint $table) {
            $table->json('caracteristicas')->nullable()->after('tipo_producto');
            $table->decimal('confirmacion_qty', 12, 2)->nullable()->after('caracteristicas');
            $table->decimal('confirmacion_precio', 12, 2)->nullable()->after('confirmacion_qty');
        });
    }

    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores_items', function (Blueprint $table) {
            $table->dropColumn(['caracteristicas', 'confirmacion_qty', 'confirmacion_precio']);
        });
    }
};
