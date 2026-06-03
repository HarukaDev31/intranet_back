<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappCoordinacionBatchesTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_coordinacion_batches')) {
            Schema::create('whatsapp_coordinacion_batches', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 40);
            $table->unsignedBigInteger('id_cotizacion')->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('cliente', 255)->nullable();
            $table->string('carga', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('completed_items')->default(0);
            $table->unsignedSmallInteger('failed_items')->default(0);
            $table->string('laravel_batch_id', 36)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['id_cotizacion', 'tipo', 'created_at'], 'wa_coord_batch_cot_tipo_idx');
            $table->index('status', 'wa_coord_batch_status_idx');
            });
        }

        if (!Schema::hasTable('whatsapp_coordinacion_batch_items')) {
            Schema::create('whatsapp_coordinacion_batch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('step_key', 80);
            $table->string('label', 255);
            $table->string('template_name', 120)->nullable();
            $table->string('payload_type', 30)->default('template');
            $table->string('status', 20)->default('pending');
            $table->string('last_error', 500)->nullable();
            $table->unsignedBigInteger('bitrix_registro_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')
                ->references('id')
                ->on('whatsapp_coordinacion_batches')
                ->onDelete('cascade');

            $table->index(['batch_id', 'status'], 'wa_coord_batch_item_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_coordinacion_batch_items');
        Schema::dropIfExists('whatsapp_coordinacion_batches');
    }
}
