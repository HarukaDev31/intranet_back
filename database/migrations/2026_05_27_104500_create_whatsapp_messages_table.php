<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('phone', 20)->index();
            $table->string('bitrix_chat_id')->nullable();
            $table->string('bitrix_msg_id')->nullable()->unique();
            $table->enum('direction', ['in', 'out']);
            $table->text('body')->nullable();
            $table->enum('source', ['bitrix', 'evolution', 'phone'])->default('evolution');
            $table->string('linea', 50)->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['phone', 'sent_at'], 'idx_whatsapp_phone_sent_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
}

