<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWaCopilotoInboxContactsTable20260605 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_copiloto_inbox_contacts')) {
            Schema::create('wa_copiloto_inbox_contacts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('copiloto_session_id')->index();
                $table->unsignedBigInteger('wa_inbox_conversation_id')->nullable()->index();
                $table->string('phone_e164', 20);
                $table->string('contact_name', 255)->nullable();
                $table->unsignedBigInteger('copiloto_conversation_id')->nullable()->index();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['copiloto_session_id', 'phone_e164'],
                    'wa_copiloto_inbox_contacts_session_phone_uq'
                );

                $table->foreign('copiloto_session_id')
                    ->references('id')
                    ->on('wa_copiloto_sessions')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('wa_copiloto_inbox_contacts');
    }
}
