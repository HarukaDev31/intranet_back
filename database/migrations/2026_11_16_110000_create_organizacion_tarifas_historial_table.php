<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de tarifas del socio (PRY-26-0003): una fila cada vez que se crean o cambian.
 * Con la fecha de una operación se sabe qué tarifas tenía el socio en ese momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizacion_tarifas_historial', function (Blueprint $table) {
            $table->id()->comment('Identificador del cambio de tarifas');
            $table->unsignedInteger('organizacion_id')->comment('Organización (socio) a la que pertenecen las tarifas');
            $table->decimal('tarifa_1', 12, 2)->nullable()->comment('Tarifa 1 en USD por CBM (menos de 1 CBM)');
            $table->decimal('tarifa_2', 12, 2)->nullable()->comment('Tarifa 2 en USD por CBM (1 CBM o más)');
            $table->dateTime('vigente_desde')->comment('Desde cuándo rigen estas tarifas');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Usuario que registró el cambio');
            $table->timestamps();

            $table->index(['organizacion_id', 'vigente_desde'], 'org_tarifas_hist_org_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizacion_tarifas_historial');
    }
};
