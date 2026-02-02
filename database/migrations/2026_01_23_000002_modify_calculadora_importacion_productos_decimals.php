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
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->decimal('precio', 20, 10)->change();
            $table->decimal('antidumping_cu', 20, 10)->default(0)->change();
            $table->decimal('ad_valorem_p', 20, 10)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->change();
            $table->decimal('antidumping_cu', 10, 2)->default(0)->change();
            $table->decimal('ad_valorem_p', 10, 2)->default(0)->change();
        });
    }
};
