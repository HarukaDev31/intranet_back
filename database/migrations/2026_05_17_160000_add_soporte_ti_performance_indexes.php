<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoporteTiPerformanceIndexes extends Migration
{
    public function up()
    {
        if (Schema::hasTable('soporte_ti_solicitudes')) {
            Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_solicitudes', 'idx_st_solicitudes_solicitante_user')) {
                    $table->index('solicitante_user_id', 'idx_st_solicitudes_solicitante_user');
                }
                if (!$this->indexExists('soporte_ti_solicitudes', 'idx_st_solicitudes_solicitante_id')) {
                    $table->index(array('solicitante_user_id', 'id'), 'idx_st_solicitudes_solicitante_id');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_solicitud_estados')) {
            Schema::table('soporte_ti_solicitud_estados', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_solicitud_estados', 'idx_st_sol_est_sol_est_created')) {
                    $table->index(
                        array('solicitud_id', 'estado_id', 'created_at'),
                        'idx_st_sol_est_sol_est_created'
                    );
                }
            });
        }

        if (Schema::hasTable('soporte_ti_mensaje_imagenes')) {
            Schema::table('soporte_ti_mensaje_imagenes', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_mensaje_imagenes', 'idx_st_msg_img_mensaje_id')) {
                    $table->index('mensaje_id', 'idx_st_msg_img_mensaje_id');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_mensajes')) {
            Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_mensajes', 'idx_st_mensajes_sala_sis_user')) {
                    $table->index(
                        array('sala_id', 'es_sistema', 'usuario_id'),
                        'idx_st_mensajes_sala_sis_user'
                    );
                }
            });
        }

        if (Schema::hasTable('soporte_ti_chat_miembros')) {
            Schema::table('soporte_ti_chat_miembros', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_chat_miembros', 'idx_st_chat_miembros_usuario_id')) {
                    $table->index('usuario_id', 'idx_st_chat_miembros_usuario_id');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_mensaje_lecturas')) {
            Schema::table('soporte_ti_mensaje_lecturas', function (Blueprint $table) {
                if (!$this->indexExists('soporte_ti_mensaje_lecturas', 'idx_st_lecturas_usuario_mensaje')) {
                    $table->index(array('usuario_id', 'mensaje_id'), 'idx_st_lecturas_usuario_mensaje');
                }
            });

            $this->dropIndexIfExistsOnTable('soporte_ti_mensaje_lecturas', array('mensaje_id', 'usuario_id'));
        }
    }

    public function down()
    {
        if (Schema::hasTable('soporte_ti_mensaje_lecturas')) {
            Schema::table('soporte_ti_mensaje_lecturas', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_mensaje_lecturas', 'idx_st_lecturas_usuario_mensaje')) {
                    $table->dropIndex('idx_st_lecturas_usuario_mensaje');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_chat_miembros')) {
            Schema::table('soporte_ti_chat_miembros', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_chat_miembros', 'idx_st_chat_miembros_usuario_id')) {
                    $table->dropIndex('idx_st_chat_miembros_usuario_id');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_mensajes')) {
            Schema::table('soporte_ti_mensajes', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_mensajes', 'idx_st_mensajes_sala_sis_user')) {
                    $table->dropIndex('idx_st_mensajes_sala_sis_user');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_mensaje_imagenes')) {
            Schema::table('soporte_ti_mensaje_imagenes', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_mensaje_imagenes', 'idx_st_msg_img_mensaje_id')) {
                    $table->dropIndex('idx_st_msg_img_mensaje_id');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_solicitud_estados')) {
            Schema::table('soporte_ti_solicitud_estados', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_solicitud_estados', 'idx_st_sol_est_sol_est_created')) {
                    $table->dropIndex('idx_st_sol_est_sol_est_created');
                }
            });
        }

        if (Schema::hasTable('soporte_ti_solicitudes')) {
            Schema::table('soporte_ti_solicitudes', function (Blueprint $table) {
                if ($this->indexExists('soporte_ti_solicitudes', 'idx_st_solicitudes_solicitante_id')) {
                    $table->dropIndex('idx_st_solicitudes_solicitante_id');
                }
                if ($this->indexExists('soporte_ti_solicitudes', 'idx_st_solicitudes_solicitante_user')) {
                    $table->dropIndex('idx_st_solicitudes_solicitante_user');
                }
            });
        }
    }

    /**
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    /**
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    protected function indexExists($table, $indexName)
    {
        try {
            $connection = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $connection->listTableIndexes($table);

            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Quita índice no único duplicado (el UNIQUE ya cubre mensaje_id + usuario_id).
     *
     * @param string $table
     * @param array  $columns
     */
    protected function dropIndexIfExistsOnTable($tableName, array $columns)
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropIndex($columns);
            });
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
