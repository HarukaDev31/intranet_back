<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesForClientesPerformance extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Función helper para verificar si un índice existe
        $indexExists = function($tableName, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'");
            return count($indexes) > 0;
        };

        // Índices para pedido_curso
        if (Schema::hasTable('pedido_curso')) {
            if (!$indexExists('pedido_curso', 'idx_pedido_curso_estado_entidad')) {
                Schema::table('pedido_curso', function (Blueprint $table) {
                    $table->index(['Nu_Estado', 'ID_Entidad'], 'idx_pedido_curso_estado_entidad');
                });
            }
            
            if (Schema::hasColumn('pedido_curso', 'id_cliente') && !$indexExists('pedido_curso', 'idx_pedido_curso_cliente')) {
                Schema::table('pedido_curso', function (Blueprint $table) {
                    $table->index('id_cliente', 'idx_pedido_curso_cliente');
                });
            }
        }

        // Índices para entidad
        if (Schema::hasTable('entidad')) {
            if (!$indexExists('entidad', 'idx_entidad_celular')) {
                Schema::table('entidad', function (Blueprint $table) {
                    $table->index('Nu_Celular_Entidad', 'idx_entidad_celular');
                });
            }
            
            if (!$indexExists('entidad', 'idx_entidad_documento')) {
                Schema::table('entidad', function (Blueprint $table) {
                    $table->index('Nu_Documento_Identidad', 'idx_entidad_documento');
                });
            }
            
            if (!$indexExists('entidad', 'idx_entidad_email')) {
                // Usar SQL directo para índices en columnas TEXT
                DB::statement('ALTER TABLE `entidad` ADD INDEX `idx_entidad_email` (`Txt_Email_Entidad`(255))');
            }
            
            if (!$indexExists('entidad', 'idx_entidad_fecha_registro')) {
                Schema::table('entidad', function (Blueprint $table) {
                    $table->index('Fe_Registro', 'idx_entidad_fecha_registro');
                });
            }
        }

        // Índices para contenedor_consolidado_cotizacion
        if (Schema::hasTable('contenedor_consolidado_cotizacion')) {
            if (!$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_telefono')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index('telefono', 'idx_cotizacion_telefono');
                });
            }
            
            if (!$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_documento')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index('documento', 'idx_cotizacion_documento');
                });
            }
            
            if (!$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_correo')) {
                // Usar SQL directo para índices en columnas TEXT
                DB::statement('ALTER TABLE `contenedor_consolidado_cotizacion` ADD INDEX `idx_cotizacion_correo` (`correo`(255))');
            }
            
            if (!$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_estados')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index(['estado_cotizador', 'estado_cliente'], 'idx_cotizacion_estados');
                });
            }
            
            if (!$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_fecha')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index('fecha', 'idx_cotizacion_fecha');
                });
            }
            
            if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente') && !$indexExists('contenedor_consolidado_cotizacion', 'idx_cotizacion_cliente')) {
                Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                    $table->index('id_cliente', 'idx_cotizacion_cliente');
                });
            }
        }

        // Índices para clientes
        if (Schema::hasTable('clientes')) {
            if (!$indexExists('clientes', 'idx_clientes_contacto')) {
                // Usar SQL directo para índices compuestos con columnas TEXT
                DB::statement('ALTER TABLE `clientes` ADD INDEX `idx_clientes_contacto` (`telefono`, `documento`, `correo`(255))');
            }
            
            if (!$indexExists('clientes', 'idx_clientes_fecha')) {
                Schema::table('clientes', function (Blueprint $table) {
                    $table->index('fecha', 'idx_clientes_fecha');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Eliminar índices de pedido_curso
        if (Schema::hasTable('pedido_curso')) {
            Schema::table('pedido_curso', function (Blueprint $table) {
                $table->dropIndex('idx_pedido_curso_estado_entidad');
                if (Schema::hasColumn('pedido_curso', 'id_cliente')) {
                    $table->dropIndex('idx_pedido_curso_cliente');
                }
            });
        }

        // Eliminar índices de entidad
        if (Schema::hasTable('entidad')) {
            Schema::table('entidad', function (Blueprint $table) {
                $table->dropIndex('idx_entidad_celular');
                $table->dropIndex('idx_entidad_documento');
                $table->dropIndex('idx_entidad_email');
                $table->dropIndex('idx_entidad_fecha_registro');
            });
        }

        // Eliminar índices de contenedor_consolidado_cotizacion
        if (Schema::hasTable('contenedor_consolidado_cotizacion')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->dropIndex('idx_cotizacion_telefono');
                $table->dropIndex('idx_cotizacion_documento');
                $table->dropIndex('idx_cotizacion_correo');
                $table->dropIndex('idx_cotizacion_estados');
                $table->dropIndex('idx_cotizacion_fecha');
                if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente')) {
                    $table->dropIndex('idx_cotizacion_cliente');
                }
            });
        }

        // Eliminar índices de clientes
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex('idx_clientes_contacto');
                $table->dropIndex('idx_clientes_fecha');
            });
        }
    }
} 