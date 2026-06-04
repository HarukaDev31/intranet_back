<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_message_insights')) {
            Schema::create('wa_copiloto_message_insights', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('message_id')->index();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('phone_e164', 20)->index();
                $table->enum('kind', ['temperatura', 'comentario', 'sugerencia']);
                $table->string('label', 120)->nullable();
                $table->text('body');
                $table->unsignedTinyInteger('score')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['message_id', 'sort_order'], 'wa_copiloto_insights_msg_sort_idx');

                $table->foreign('message_id')
                    ->references('id')
                    ->on('wa_copiloto_messages')
                    ->onDelete('cascade');
                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('wa_copiloto_conversations')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wa_copiloto_message_insights');
    }
};
