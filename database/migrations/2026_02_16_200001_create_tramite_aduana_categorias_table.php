<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carpetas/categorías por trámite. Cada trámite tiene N categorías (por nombre).
     */
    public function up(): void
    {
        Schema::create('tramite_aduana_categorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite');
            $table->string('nombre', 255);
            $table->timestamps();

            $table->foreign('id_tramite')
                ->references('id')
                ->on('consolidado_cotizacion_aduana_tramites')
                ->onDelete('cascade');

            $table->unique(['id_tramite', 'nombre']);
            $table->index('id_tramite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_aduana_categorias');
    }
};
