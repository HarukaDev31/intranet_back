<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de lectura para entornos donde ya corrieron las migraciones create_* anteriores.
 * Idempotente: no falla si el índice ya existe.
 */
class AddReadOptimizationIndexesWaInboxAndCoordBatches extends Migration
{
    public function up()
    {
        $this->addWaInboxIndexes();
        $this->addCoordinacionBatchIndexes();
    }

    public function down()
    {
        $this->dropIndexIfExists('wa_inbox_conversations', 'wa_inbox_conv_session_last_id_idx');
        $this->dropIndexIfExists('wa_inbox_messages', 'wa_inbox_msg_conv_sent_id_idx');
        $this->dropIndexIfExists('wa_inbox_messages', 'wa_inbox_msg_pending_conv_idx');
        $this->dropIndexIfExists('wa_inbox_webhook_logs', 'wa_inbox_webhook_pending_idx');

        $this->dropIndexIfExists('whatsapp_coordinacion_batch_items', 'wa_coord_item_batch_status_sort_idx');
        $this->dropIndexIfExists('whatsapp_coordinacion_batch_items', 'wa_coord_item_inbox_msg_idx');
        $this->dropIndexIfExists('whatsapp_coordinacion_batches', 'wa_coord_batch_laravel_id_idx');
        $this->dropIndexIfExists('whatsapp_coordinacion_batches', 'wa_coord_batch_outbound_id_idx');
    }

    private function addWaInboxIndexes()
    {
        if (Schema::hasTable('wa_inbox_conversations')
            && !$this->indexExists('wa_inbox_conversations', 'wa_inbox_conv_session_last_id_idx')) {
            Schema::table('wa_inbox_conversations', function (Blueprint $table) {
                $table->index(
                    ['session_id', 'last_message_at', 'id'],
                    'wa_inbox_conv_session_last_id_idx'
                );
            });
        }

        if (Schema::hasTable('wa_inbox_messages')) {
            if (!$this->indexExists('wa_inbox_messages', 'wa_inbox_msg_conv_sent_id_idx')) {
                Schema::table('wa_inbox_messages', function (Blueprint $table) {
                    $table->index(
                        ['conversation_id', 'sent_at', 'id'],
                        'wa_inbox_msg_conv_sent_id_idx'
                    );
                });
            }
            if (!$this->indexExists('wa_inbox_messages', 'wa_inbox_msg_pending_conv_idx')) {
                Schema::table('wa_inbox_messages', function (Blueprint $table) {
                    $table->index(
                        ['conversation_id', 'delivery_status', 'id'],
                        'wa_inbox_msg_pending_conv_idx'
                    );
                });
            }
        }

        if (Schema::hasTable('wa_inbox_webhook_logs')
            && !$this->indexExists('wa_inbox_webhook_logs', 'wa_inbox_webhook_pending_idx')) {
            Schema::table('wa_inbox_webhook_logs', function (Blueprint $table) {
                $table->index(['processed_at'], 'wa_inbox_webhook_pending_idx');
            });
        }
    }

    private function addCoordinacionBatchIndexes()
    {
        if (Schema::hasTable('whatsapp_coordinacion_batch_items')) {
            if (!$this->indexExists('whatsapp_coordinacion_batch_items', 'wa_coord_item_batch_status_sort_idx')) {
                Schema::table('whatsapp_coordinacion_batch_items', function (Blueprint $table) {
                    $table->index(
                        ['batch_id', 'status', 'sort_order'],
                        'wa_coord_item_batch_status_sort_idx'
                    );
                });
            }

            $inboxCol = Schema::hasColumn('whatsapp_coordinacion_batch_items', 'inbox_message_id')
                ? 'inbox_message_id'
                : (Schema::hasColumn('whatsapp_coordinacion_batch_items', 'bitrix_registro_id')
                    ? 'bitrix_registro_id'
                    : null);

            if ($inboxCol !== null
                && !$this->indexExists('whatsapp_coordinacion_batch_items', 'wa_coord_item_inbox_msg_idx')) {
                Schema::table('whatsapp_coordinacion_batch_items', function (Blueprint $table) use ($inboxCol) {
                    $table->index([$inboxCol], 'wa_coord_item_inbox_msg_idx');
                });
            }
        }

        if (Schema::hasTable('whatsapp_coordinacion_batches')) {
            if (Schema::hasColumn('whatsapp_coordinacion_batches', 'laravel_batch_id')
                && !$this->indexExists('whatsapp_coordinacion_batches', 'wa_coord_batch_laravel_id_idx')) {
                Schema::table('whatsapp_coordinacion_batches', function (Blueprint $table) {
                    $table->index(['laravel_batch_id'], 'wa_coord_batch_laravel_id_idx');
                });
            }
            if (Schema::hasColumn('whatsapp_coordinacion_batches', 'outbound_laravel_batch_id')
                && !$this->indexExists('whatsapp_coordinacion_batches', 'wa_coord_batch_outbound_id_idx')) {
                Schema::table('whatsapp_coordinacion_batches', function (Blueprint $table) {
                    $table->index(['outbound_laravel_batch_id'], 'wa_coord_batch_outbound_id_idx');
                });
            }
        }
    }

    /**
     * @param  string  $table
     * @param  string  $indexName
     * @return bool
     */
    private function indexExists($table, $indexName)
    {
        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return count($rows) > 0;
    }

    /**
     * @param  string  $table
     * @param  string  $indexName
     */
    private function dropIndexIfExists($table, $indexName)
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }
}
