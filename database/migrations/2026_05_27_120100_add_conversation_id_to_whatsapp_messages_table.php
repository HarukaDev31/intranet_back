<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConversationIdToWhatsappMessagesTable extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id')->nullable()->after('id')->index();
            $table->foreign('conversation_id')
                ->references('id')
                ->on('copiloto_conversaciones')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn('conversation_id');
        });
    }
}
