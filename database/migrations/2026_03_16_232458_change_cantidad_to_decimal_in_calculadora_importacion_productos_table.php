<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permite cantidades decimales (ej. 756.9 metros de tela).
     */
    public function up(): void
    {
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->decimal('cantidad', 20, 10)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculadora_importacion_productos', function (Blueprint $table) {
            $table->integer('cantidad')->change();
        });
    }
};
