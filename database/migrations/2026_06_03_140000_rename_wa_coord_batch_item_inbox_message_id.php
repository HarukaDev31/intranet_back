<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameWaCoordBatchItemInboxMessageId extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_coordinacion_batch_items')) {
            return;
        }

        if (Schema::hasColumn('whatsapp_coordinacion_batch_items', 'bitrix_registro_id')
            && !Schema::hasColumn('whatsapp_coordinacion_batch_items', 'inbox_message_id')) {
            Schema::table('whatsapp_coordinacion_batch_items', function (Blueprint $table) {
                $table->renameColumn('bitrix_registro_id', 'inbox_message_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('whatsapp_coordinacion_batch_items')) {
            return;
        }

        if (Schema::hasColumn('whatsapp_coordinacion_batch_items', 'inbox_message_id')
            && !Schema::hasColumn('whatsapp_coordinacion_batch_items', 'bitrix_registro_id')) {
            Schema::table('whatsapp_coordinacion_batch_items', function (Blueprint $table) {
                $table->renameColumn('inbox_message_id', 'bitrix_registro_id');
            });
        }
    }
}
