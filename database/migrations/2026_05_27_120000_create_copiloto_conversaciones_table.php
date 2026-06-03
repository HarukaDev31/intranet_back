<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCopilotoConversacionesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('copiloto_conversaciones')) {
        Schema::create('copiloto_conversaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('thread_key', 64)->unique();
            $table->string('phone', 20)->index();
            $table->string('bitrix_chat_id')->nullable();
            $table->string('linea', 50)->nullable()->index();
            $table->string('contact_name', 255)->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('last_message_preview', 500)->nullable();
            $table->enum('last_direction', ['in', 'out'])->nullable();
            $table->unsignedInteger('messages_count')->default(0);
            $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('copiloto_conversaciones');
    }
}
