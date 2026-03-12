<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * TC Yuan por consolidado: periodo de vigencia dado por created_at / updated_at.
     */
    public function up(): void
    {
        //drop if exists
        Schema::dropIfExists('carga_consolidada_contenedor_tc_yuan');
        Schema::create('carga_consolidada_contenedor_tc_yuan', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_contenedor')->unique();
            $table->decimal('tc_yuan', 18, 8);
            $table->timestamps();

            $table->foreign('id_contenedor')
                ->references('id')
                ->on('carga_consolidada_contenedor')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carga_consolidada_contenedor_tc_yuan');
    }
};
