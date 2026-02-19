<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobantes de pago del tramitador subidos en Verificación.
     * No se guardan en categorías/documentos sino en esta tabla.
     */
    public function up(): void
    {
        Schema::create('pagos_permiso_tramite', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite');
            $table->string('ruta');
            $table->string('nombre_original')->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->decimal('monto', 12, 2)->nullable();
            $table->string('banco', 100)->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->timestamps();

            $table->foreign('id_tramite')->references('id')->on('consolidado_cotizacion_aduana_tramites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_permiso_tramite');
    }
};
