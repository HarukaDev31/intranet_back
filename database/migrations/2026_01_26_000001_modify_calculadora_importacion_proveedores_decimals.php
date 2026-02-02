<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cambia todos los campos decimal(10,2) a decimal(20,10) para soportar 10 decimales.
     */
    public function up(): void
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            $table->decimal('cbm', 20, 10)->change();
            $table->decimal('peso', 20, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion_proveedores', function (Blueprint $table) {
            $table->decimal('cbm', 10, 2)->change();
            $table->decimal('peso', 10, 2)->change();
        });
    }
};
