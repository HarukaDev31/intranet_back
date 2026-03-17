<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // clientes (base-datos/clientes)
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                // Búsquedas frecuentes
                if (!$this->hasIndex('clientes', 'idx_clientes_documento')) {
                    $table->index('documento', 'idx_clientes_documento');
                }
                if (!$this->hasIndex('clientes', 'idx_clientes_correo')) {
                    $table->index('correo', 'idx_clientes_correo');
                }
                if (!$this->hasIndex('clientes', 'idx_clientes_telefono')) {
                    $table->index('telefono', 'idx_clientes_telefono');
                }
                if (Schema::hasColumn('clientes', 'ruc') && !$this->hasIndex('clientes', 'idx_clientes_ruc')) {
                    $table->index('ruc', 'idx_clientes_ruc');
                }
                if (Schema::hasColumn('clientes', 'fecha') && !$this->hasIndex('clientes', 'idx_clientes_fecha')) {
                    $table->index('fecha', 'idx_clientes_fecha');
                }
                if (Schema::hasColumn('clientes', 'created_at') && !$this->hasIndex('clientes', 'idx_clientes_created_at')) {
                    $table->index('created_at', 'idx_clientes_created_at');
                }
                if (Schema::hasColumn('clientes', 'id_cliente_importacion') && !$this->hasIndex('clientes', 'idx_clientes_id_cliente_importacion')) {
                    $table->index('id_cliente_importacion', 'idx_clientes_id_cliente_importacion');
                }
            });
        }

        // pedido_curso (servicios Curso)
        if (Schema::hasTable('pedido_curso')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                if (Schema::hasColumn('pedido_curso', 'id_cliente') && !$this->hasIndex('pedido_curso', 'idx_pedido_curso_id_cliente_estado')) {
                    $table->index(['id_cliente', 'Nu_Estado'], 'idx_pedido_curso_id_cliente_estado');
                }
                if (Schema::hasColumn('pedido_curso', 'ID_Entidad') && !$this->hasIndex('pedido_curso', 'idx_pedido_curso_entidad_estado')) {
                    $table->index(['ID_Entidad', 'Nu_Estado'], 'idx_pedido_curso_entidad_estado');
                }
                if (!$this->hasIndex('pedido_curso', 'idx_pedido_curso_estado')) {
                    $table->index('Nu_Estado', 'idx_pedido_curso_estado');
                }
            });
        }

        // entidad (datos Curso)
        if (Schema::hasTable('entidad')) {
            Schema::table('entidad', function (Blueprint $table) {
                if (!$this->hasIndex('entidad', 'idx_entidad_fe_registro')) {
                    $table->index('Fe_Registro', 'idx_entidad_fe_registro');
                }
                if (Schema::hasColumn('entidad', 'Nu_Documento_Identidad') && !$this->hasIndex('entidad', 'idx_entidad_documento')) {
                    $table->index('Nu_Documento_Identidad', 'idx_entidad_documento');
                }
                if (Schema::hasColumn('entidad', 'Txt_Email_Entidad') && !$this->hasIndex('entidad', 'idx_entidad_email')) {
                    $table->index('Txt_Email_Entidad', 'idx_entidad_email');
                }
                if (Schema::hasColumn('entidad', 'Nu_Celular_Entidad') && !$this->hasIndex('entidad', 'idx_entidad_celular')) {
                    $table->index('Nu_Celular_Entidad', 'idx_entidad_celular');
                }
                if (Schema::hasColumn('entidad', 'ID_Provincia') && !$this->hasIndex('entidad', 'idx_entidad_id_provincia')) {
                    $table->index('ID_Provincia', 'idx_entidad_id_provincia');
                }
            });
        }

        // contenedor_consolidado_cotizacion (servicios Consolidado)
        if (Schema::hasTable('contenedor_consolidado_cotizacion')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente') && !$this->hasIndex('contenedor_consolidado_cotizacion', 'idx_ccc_id_cliente_estado_fecha')) {
                    $table->index(['id_cliente', 'estado_cotizador', 'fecha'], 'idx_ccc_id_cliente_estado_fecha');
                }
                if (!$this->hasIndex('contenedor_consolidado_cotizacion', 'idx_ccc_estado_cotizador')) {
                    $table->index('estado_cotizador', 'idx_ccc_estado_cotizador');
                }
                if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_cliente') && !$this->hasIndex('contenedor_consolidado_cotizacion', 'idx_ccc_estado_cliente')) {
                    $table->index('estado_cliente', 'idx_ccc_estado_cliente');
                }
                if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'fecha') && !$this->hasIndex('contenedor_consolidado_cotizacion', 'idx_ccc_fecha')) {
                    $table->index('fecha', 'idx_ccc_fecha');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_clientes_documento');
                $this->dropIndexIfExists($table, 'idx_clientes_correo');
                $this->dropIndexIfExists($table, 'idx_clientes_telefono');
                $this->dropIndexIfExists($table, 'idx_clientes_ruc');
                $this->dropIndexIfExists($table, 'idx_clientes_fecha');
                $this->dropIndexIfExists($table, 'idx_clientes_created_at');
                $this->dropIndexIfExists($table, 'idx_clientes_id_cliente_importacion');
            });
        }

        if (Schema::hasTable('pedido_curso')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_pedido_curso_id_cliente_estado');
                $this->dropIndexIfExists($table, 'idx_pedido_curso_entidad_estado');
                $this->dropIndexIfExists($table, 'idx_pedido_curso_estado');
            });
        }

        if (Schema::hasTable('entidad')) {
            Schema::table('entidad', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_entidad_fe_registro');
                $this->dropIndexIfExists($table, 'idx_entidad_documento');
                $this->dropIndexIfExists($table, 'idx_entidad_email');
                $this->dropIndexIfExists($table, 'idx_entidad_celular');
                $this->dropIndexIfExists($table, 'idx_entidad_id_provincia');
            });
        }

        if (Schema::hasTable('contenedor_consolidado_cotizacion')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $this->dropIndexIfExists($table, 'idx_ccc_id_cliente_estado_fecha');
                $this->dropIndexIfExists($table, 'idx_ccc_estado_cotizador');
                $this->dropIndexIfExists($table, 'idx_ccc_estado_cliente');
                $this->dropIndexIfExists($table, 'idx_ccc_fecha');
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $connection->listTableIndexes($table);
            return array_key_exists($indexName, $indexes);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        try {
            $table->dropIndex($indexName);
        } catch (\Throwable $e) {
            // ignore
        }
    }
};

