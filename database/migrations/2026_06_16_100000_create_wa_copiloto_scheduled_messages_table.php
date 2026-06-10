<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaCopilotoScheduledMessagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_copiloto_scheduled_messages')) {
            return;
        }

        Schema::create('wa_copiloto_scheduled_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedInteger('created_by_user_id')->index();
            $table->text('body');
            $table->string('message_type', 32)->default('text');
            $table->json('template_params')->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('failed_reason', 500)->nullable();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wa_copiloto_scheduled_messages');
    }
}
