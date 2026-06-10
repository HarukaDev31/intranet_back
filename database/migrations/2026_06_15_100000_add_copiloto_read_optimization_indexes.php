<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de lectura para Copiloto / WaCopiloto (listado, kanban, KPIs, IA, historial).
 * Idempotente: no falla si el índice ya existe.
 */
class AddCopilotoReadOptimizationIndexes extends Migration
{
    public function up()
    {
        $this->addWaCopilotoConversationIndexes();
        $this->addWaCopilotoMessageIndexes();
        $this->addWaCopilotoPipelineIndexes();
        $this->addCopilotoFichaIndexes();
        $this->addCotizacionHistorialIndexes();
        $this->addConsolidadoContextIndexes();
    }

    public function down()
    {
        $this->dropIndexIfExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_inbound_last_idx');
        $this->dropIndexIfExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_assign_inbound_last_idx');
        $this->dropIndexIfExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_stage_last_idx');
        $this->dropIndexIfExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_ai_temp_idx');

        $this->dropIndexIfExists('wa_copiloto_messages', 'wa_copiloto_msg_conv_dir_id_idx');

        $this->dropIndexIfExists('wa_copiloto_pipeline_stages', 'wa_copiloto_stage_active_sort_idx');
        $this->dropIndexIfExists('wa_copiloto_pipeline_transitions', 'wa_copiloto_pt_conv_id_idx');
        $this->dropIndexIfExists('wa_copiloto_assignment_logs', 'wa_copiloto_al_conv_id_idx');

        $this->dropIndexIfExists('copiloto_fichas', 'idx_copiloto_fichas_phone_updated_id');

        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_historial_active_fecha_idx');
        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_correo_idx');
        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_documento_idx');

        $this->dropIndexIfExists('carga_consolidada_contenedor', 'cc_contenedor_estado_fcierre_idx');
    }

    private function addWaCopilotoConversationIndexes()
    {
        if (!Schema::hasTable('wa_copiloto_conversations')) {
            return;
        }

        // Cola / kanban con solo_cliente_inbound + orden por último mensaje.
        if (!$this->indexExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_inbound_last_idx')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->index(
                    ['session_id', 'customer_initiated_at', 'last_message_at', 'id'],
                    'wa_copiloto_conv_session_inbound_last_idx'
                );
            });
        }

        // Vista asesor (mis) + inbound + orden reciente.
        if (!$this->indexExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_assign_inbound_last_idx')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->index(
                    ['session_id', 'assigned_user_id', 'customer_initiated_at', 'last_message_at'],
                    'wa_copiloto_conv_session_assign_inbound_last_idx'
                );
            });
        }

        // Kanban por etapa dentro de sesión.
        if (!$this->indexExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_stage_last_idx')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->index(
                    ['session_id', 'pipeline_stage_id', 'last_message_at', 'id'],
                    'wa_copiloto_conv_session_stage_last_idx'
                );
            });
        }

        // KPI hot leads (ai_temperatura >= 70) acotado por sesión.
        if (Schema::hasColumn('wa_copiloto_conversations', 'ai_temperatura')
            && !$this->indexExists('wa_copiloto_conversations', 'wa_copiloto_conv_session_ai_temp_idx')) {
            Schema::table('wa_copiloto_conversations', function (Blueprint $table) {
                $table->index(
                    ['session_id', 'ai_temperatura', 'pipeline_stage_id'],
                    'wa_copiloto_conv_session_ai_temp_idx'
                );
            });
        }
    }

    private function addWaCopilotoMessageIndexes()
    {
        if (!Schema::hasTable('wa_copiloto_messages')) {
            return;
        }

        // Primer outbound del asesor + ventana de contexto IA por conversación.
        if (!$this->indexExists('wa_copiloto_messages', 'wa_copiloto_msg_conv_dir_id_idx')) {
            Schema::table('wa_copiloto_messages', function (Blueprint $table) {
                $table->index(
                    ['conversation_id', 'direction', 'id'],
                    'wa_copiloto_msg_conv_dir_id_idx'
                );
            });
        }
    }

    private function addWaCopilotoPipelineIndexes()
    {
        if (Schema::hasTable('wa_copiloto_pipeline_stages')
            && !$this->indexExists('wa_copiloto_pipeline_stages', 'wa_copiloto_stage_active_sort_idx')) {
            Schema::table('wa_copiloto_pipeline_stages', function (Blueprint $table) {
                $table->index(
                    ['is_active', 'sort_order', 'id'],
                    'wa_copiloto_stage_active_sort_idx'
                );
            });
        }

        if (Schema::hasTable('wa_copiloto_pipeline_transitions')
            && !$this->indexExists('wa_copiloto_pipeline_transitions', 'wa_copiloto_pt_conv_id_idx')) {
            Schema::table('wa_copiloto_pipeline_transitions', function (Blueprint $table) {
                $table->index(
                    ['conversation_id', 'id'],
                    'wa_copiloto_pt_conv_id_idx'
                );
            });
        }

        if (Schema::hasTable('wa_copiloto_assignment_logs')
            && !$this->indexExists('wa_copiloto_assignment_logs', 'wa_copiloto_al_conv_id_idx')) {
            Schema::table('wa_copiloto_assignment_logs', function (Blueprint $table) {
                $table->index(
                    ['conversation_id', 'id'],
                    'wa_copiloto_al_conv_id_idx'
                );
            });
        }
    }

    private function addCopilotoFichaIndexes()
    {
        if (!Schema::hasTable('copiloto_fichas')) {
            return;
        }

        // Lookup/batch temperatura: WHERE phone IN (...) ORDER BY updated_at DESC.
        if (!$this->indexExists('copiloto_fichas', 'idx_copiloto_fichas_phone_updated_id')) {
            Schema::table('copiloto_fichas', function (Blueprint $table) {
                $table->index(
                    ['phone', 'updated_at', 'id'],
                    'idx_copiloto_fichas_phone_updated_id'
                );
            });
        }
    }

    private function addCotizacionHistorialIndexes()
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion')) {
            return;
        }

        $hasDeletedAt = Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_at');
        $hasClienteImport = Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente_importacion');
        $hasFecha = Schema::hasColumn('contenedor_consolidado_cotizacion', 'fecha');

        if ($hasDeletedAt && $hasClienteImport && $hasFecha
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_historial_active_fecha_idx')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->index(
                    ['deleted_at', 'id_cliente_importacion', 'fecha', 'id'],
                    'cc_cot_historial_active_fecha_idx'
                );
            });
        }

        // Índices en correo/documento: ver migración 2026_06_15_110000 (VARCHAR 510 + índice completo).
        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'correo')
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_correo_idx')) {
            if ($this->columnIsVarchar('contenedor_consolidado_cotizacion', 'correo')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index(['correo'], 'cc_cot_correo_idx');
                });
            } else {
                DB::statement('ALTER TABLE `contenedor_consolidado_cotizacion` ADD INDEX `cc_cot_correo_idx` (`correo`(255))');
            }
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'documento')
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_documento_idx')) {
            if ($this->columnIsVarchar('contenedor_consolidado_cotizacion', 'documento')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index(['documento'], 'cc_cot_documento_idx');
                });
            } else {
                DB::statement('ALTER TABLE `contenedor_consolidado_cotizacion` ADD INDEX `cc_cot_documento_idx` (`documento`(64))');
            }
        }
    }

    private function addConsolidadoContextIndexes()
    {
        if (!Schema::hasTable('carga_consolidada_contenedor')) {
            return;
        }

        if (Schema::hasColumn('carga_consolidada_contenedor', 'estado')
            && Schema::hasColumn('carga_consolidada_contenedor', 'f_cierre')
            && !$this->indexExists('carga_consolidada_contenedor', 'cc_contenedor_estado_fcierre_idx')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->index(
                    ['estado', 'f_cierre', 'id'],
                    'cc_contenedor_estado_fcierre_idx'
                );
            });
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

    /**
     * @param  string  $table
     * @param  string  $column
     * @return bool
     */
    private function columnIsVarchar($table, $column)
    {
        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS
             WHERE table_schema = ? AND table_name = ? AND column_name = ?
             LIMIT 1',
            [$database, $table, $column]
        );

        if (count($rows) === 0) {
            return false;
        }

        return strtolower((string) $rows[0]->DATA_TYPE) === 'varchar';
    }
}
