<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOrganizacionIdToWaInboxConversations extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('wa_inbox_conversations')) {
            return;
        }

        if (!Schema::hasColumn('wa_inbox_conversations', 'organizacion_id')) {
            Schema::table('wa_inbox_conversations', function (Blueprint $table) {
                $table->unsignedInteger('organizacion_id')->nullable()->after('session_id');
            });
        }

        if (Schema::hasTable('wa_inbox_sessions')) {
            DB::statement(
                'UPDATE wa_inbox_conversations c
                 INNER JOIN wa_inbox_sessions s ON s.id = c.session_id
                 SET c.organizacion_id = s.organizacion_id
                 WHERE c.organizacion_id IS NULL AND s.organizacion_id IS NOT NULL'
            );
        }

        $this->backfillOrganizacionDesdeCoordinacion();

        $indexExists = collect(DB::select(
            "SHOW INDEX FROM wa_inbox_conversations WHERE Key_name = 'wa_inbox_conv_org_last_idx'"
        ))->isNotEmpty();
        if (!$indexExists) {
            Schema::table('wa_inbox_conversations', function (Blueprint $table) {
                $table->index(['organizacion_id', 'last_message_at'], 'wa_inbox_conv_org_last_idx');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('wa_inbox_conversations') || !Schema::hasColumn('wa_inbox_conversations', 'organizacion_id')) {
            return;
        }

        $indexExists = collect(DB::select(
            "SHOW INDEX FROM wa_inbox_conversations WHERE Key_name = 'wa_inbox_conv_org_last_idx'"
        ))->isNotEmpty();
        Schema::table('wa_inbox_conversations', function (Blueprint $table) use ($indexExists) {
            if ($indexExists) {
                $table->dropIndex('wa_inbox_conv_org_last_idx');
            }
            $table->dropColumn('organizacion_id');
        });
    }

    /**
     * Conversaciones creadas con el número Meta de org 1 al enviar rotulado de un socio.
     */
    private function backfillOrganizacionDesdeCoordinacion()
    {
        if (
            !Schema::hasTable('whatsapp_coordinacion_batches')
            || !Schema::hasColumn('whatsapp_coordinacion_batches', 'phone_e164')
            || !Schema::hasColumn('whatsapp_coordinacion_batches', 'id_cotizacion')
            || !Schema::hasTable('contenedor_consolidado_cotizacion')
            || !Schema::hasColumn('contenedor_consolidado_cotizacion', 'organizacion_id')
        ) {
            return;
        }

        DB::statement(
            "UPDATE wa_inbox_conversations c
             INNER JOIN whatsapp_coordinacion_batches b
                ON REPLACE(REPLACE(IFNULL(b.phone_e164, ''), '+', ''), ' ', '') = c.phone_e164
             INNER JOIN contenedor_consolidado_cotizacion cot
                ON cot.id = b.id_cotizacion
             SET c.organizacion_id = cot.organizacion_id
             WHERE cot.organizacion_id IS NOT NULL
               AND cot.organizacion_id > 1
               AND (c.organizacion_id IS NULL OR c.organizacion_id = 1)"
        );
    }
}
