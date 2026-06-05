<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_copiloto_suggestion_usages')) {
            return;
        }

        Schema::create('wa_copiloto_suggestion_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('insight_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('outcome', 16);
            $table->text('suggested_text');
            $table->text('final_text')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->foreign('conversation_id')
                ->references('id')
                ->on('wa_copiloto_conversations')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('wa_copiloto_suggestion_usages');
    }
};
