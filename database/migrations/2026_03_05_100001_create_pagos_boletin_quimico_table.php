<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_boletin_quimico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_boletin_quimico_item');
            $table->decimal('monto', 12, 4);
            $table->string('voucher_url', 512)->nullable();
            $table->string('payment_date', 32)->nullable();
            $table->string('banco', 128)->nullable();
            $table->string('status', 32)->default('PENDIENTE')->comment('PENDIENTE, CONFIRMADO, OBSERVADO');
            $table->timestamp('confirmation_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('id_boletin_quimico_item')->references('id')->on('boletin_quimico_cotizacion_item')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_boletin_quimico');
    }
};
