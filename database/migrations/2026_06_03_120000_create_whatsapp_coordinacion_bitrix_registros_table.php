<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappCoordinacionBitrixRegistrosTable extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_coordinacion_bitrix_registros', function (Blueprint $table) {
            $table->id();
            $table->string('phone_e164', 20)->nullable();
            $table->unsignedBigInteger('bitrix_contact_id')->nullable();
            $table->unsignedInteger('bitrix_chat_id')->nullable();
            $table->string('template_name', 120);
            $table->text('bitrix_message');
            $table->boolean('meta_ok')->default(false);
            $table->string('meta_error', 500)->nullable();
            $table->boolean('include_timeline')->default(false);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('payload_extra')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_coordinacion_bitrix_registros');
    }
}
