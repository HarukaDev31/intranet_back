<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de lectura para el módulo Carga Consolidada (listados, CBM, entrega, pagos).
 * Idempotente: no falla si el índice ya existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addContenedorIndexes();
        $this->addCotizacionIndexes();
        $this->addProveedorIndexes();
        $this->addProveedorItemsIndexes();
        $this->addCalculadoraIndexes();
        $this->addCoordinacionPagosIndexes();
        $this->addDeliveryAndComprobanteIndexes();
        $this->addAduanaTramitesIndexes();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('carga_consolidada_contenedor', 'cc_contenedor_list_china_idx');
        $this->dropIndexIfExists('carga_consolidada_contenedor', 'cc_contenedor_finanzas_finicio_idx');
        $this->dropIndexIfExists('carga_consolidada_contenedor', 'cc_contenedor_origen_idx');

        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_contenedor_estado_deleted_idx');
        $this->dropIndexIfExists('contenedor_consolidado_cotizacion', 'cc_cot_contenedor_list_idx');

        $this->dropIndexIfExists('contenedor_consolidado_cotizacion_proveedores', 'cc_prov_contenedor_cotizacion_idx');

        $this->dropIndexIfExists('contenedor_consolidado_cotizacion_proveedores_items', 'cc_prov_items_cot_proveedor_idx');

        $this->dropIndexIfExists('calculadora_importacion', 'calc_imp_cotizacion_estado_idx');

        $this->dropIndexIfExists('contenedor_consolidado_cotizacion_coordinacion_pagos', 'cc_pagos_contenedor_cotizacion_idx');

        $this->dropIndexIfExists('consolidado_comprobante_forms', 'cc_comprobante_contenedor_cotizacion_idx');
        $this->dropIndexIfExists('consolidado_delivery_form_lima', 'cc_delivery_lima_contenedor_cotizacion_idx');
        $this->dropIndexIfExists('consolidado_delivery_form_province', 'cc_delivery_prov_contenedor_cotizacion_idx');

        $this->dropIndexIfExists('consolidado_cotizacion_aduana_tramites', 'cc_aduana_consolidado_cotizacion_idx');
    }

    private function addContenedorIndexes(): void
    {
        if (!Schema::hasTable('carga_consolidada_contenedor')) {
            return;
        }

        if (Schema::hasColumn('carga_consolidada_contenedor', 'estado_china')
            && Schema::hasColumn('carga_consolidada_contenedor', 'f_inicio')
            && !$this->indexExists('carga_consolidada_contenedor', 'cc_contenedor_list_china_idx')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->index(
                    ['estado_china', 'f_inicio', 'id'],
                    'cc_contenedor_list_china_idx'
                );
            });
        }

        if (Schema::hasColumn('carga_consolidada_contenedor', 'estado_finanzas')
            && Schema::hasColumn('carga_consolidada_contenedor', 'f_inicio')
            && !$this->indexExists('carga_consolidada_contenedor', 'cc_contenedor_finanzas_finicio_idx')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->index(
                    ['estado_finanzas', 'f_inicio', 'id'],
                    'cc_contenedor_finanzas_finicio_idx'
                );
            });
        }

        if (Schema::hasColumn('carga_consolidada_contenedor', 'id_contenedor_origen')
            && !$this->indexExists('carga_consolidada_contenedor', 'cc_contenedor_origen_idx')) {
            Schema::table('carga_consolidada_contenedor', function (Blueprint $table) {
                $table->index(['id_contenedor_origen'], 'cc_contenedor_origen_idx');
            });
        }
    }

    private function addCotizacionIndexes(): void
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion')) {
            return;
        }

        $hasDeletedAt = Schema::hasColumn('contenedor_consolidado_cotizacion', 'deleted_at');

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_contenedor')
            && Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_cotizador')
            && $hasDeletedAt
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_contenedor_estado_deleted_idx')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'estado_cotizador', 'deleted_at'],
                    'cc_cot_contenedor_estado_deleted_idx'
                );
            });
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_contenedor')
            && Schema::hasColumn('contenedor_consolidado_cotizacion', 'id_cliente_importacion')
            && Schema::hasColumn('contenedor_consolidado_cotizacion', 'estado_cotizador')
            && $hasDeletedAt
            && !$this->indexExists('contenedor_consolidado_cotizacion', 'cc_cot_contenedor_list_idx')) {
            Schema::table('contenedor_consolidado_cotizacion', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'deleted_at', 'id_cliente_importacion', 'estado_cotizador'],
                    'cc_cot_contenedor_list_idx'
                );
            });
        }
    }

    private function addProveedorIndexes(): void
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion_proveedores')) {
            return;
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'id_contenedor')
            && Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores', 'id_cotizacion')
            && !$this->indexExists('contenedor_consolidado_cotizacion_proveedores', 'cc_prov_contenedor_cotizacion_idx')) {
            Schema::table('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cotizacion'],
                    'cc_prov_contenedor_cotizacion_idx'
                );
            });
        }
    }

    private function addProveedorItemsIndexes(): void
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion_proveedores_items')) {
            return;
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores_items', 'id_cotizacion')
            && Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores_items', 'id_proveedor')
            && !$this->indexExists('contenedor_consolidado_cotizacion_proveedores_items', 'cc_prov_items_cot_proveedor_idx')) {
            Schema::table('contenedor_consolidado_cotizacion_proveedores_items', function (Blueprint $table) {
                $table->index(
                    ['id_cotizacion', 'id_proveedor'],
                    'cc_prov_items_cot_proveedor_idx'
                );
            });
        }
    }

    private function addCalculadoraIndexes(): void
    {
        if (!Schema::hasTable('calculadora_importacion')) {
            return;
        }

        if (Schema::hasColumn('calculadora_importacion', 'id_cotizacion')
            && Schema::hasColumn('calculadora_importacion', 'estado')
            && !$this->indexExists('calculadora_importacion', 'calc_imp_cotizacion_estado_idx')) {
            Schema::table('calculadora_importacion', function (Blueprint $table) {
                $table->index(
                    ['id_cotizacion', 'estado'],
                    'calc_imp_cotizacion_estado_idx'
                );
            });
        }
    }

    private function addCoordinacionPagosIndexes(): void
    {
        if (!Schema::hasTable('contenedor_consolidado_cotizacion_coordinacion_pagos')) {
            return;
        }

        if (Schema::hasColumn('contenedor_consolidado_cotizacion_coordinacion_pagos', 'id_contenedor')
            && Schema::hasColumn('contenedor_consolidado_cotizacion_coordinacion_pagos', 'id_cotizacion')
            && !$this->indexExists('contenedor_consolidado_cotizacion_coordinacion_pagos', 'cc_pagos_contenedor_cotizacion_idx')) {
            Schema::table('contenedor_consolidado_cotizacion_coordinacion_pagos', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cotizacion'],
                    'cc_pagos_contenedor_cotizacion_idx'
                );
            });
        }
    }

    private function addDeliveryAndComprobanteIndexes(): void
    {
        if (Schema::hasTable('consolidado_comprobante_forms')
            && Schema::hasColumn('consolidado_comprobante_forms', 'id_contenedor')
            && Schema::hasColumn('consolidado_comprobante_forms', 'id_cotizacion')
            && !$this->indexExists('consolidado_comprobante_forms', 'cc_comprobante_contenedor_cotizacion_idx')) {
            Schema::table('consolidado_comprobante_forms', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cotizacion'],
                    'cc_comprobante_contenedor_cotizacion_idx'
                );
            });
        }

        if (Schema::hasTable('consolidado_delivery_form_lima')
            && Schema::hasColumn('consolidado_delivery_form_lima', 'id_contenedor')
            && Schema::hasColumn('consolidado_delivery_form_lima', 'id_cotizacion')
            && !$this->indexExists('consolidado_delivery_form_lima', 'cc_delivery_lima_contenedor_cotizacion_idx')) {
            Schema::table('consolidado_delivery_form_lima', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cotizacion'],
                    'cc_delivery_lima_contenedor_cotizacion_idx'
                );
            });
        }

        if (Schema::hasTable('consolidado_delivery_form_province')
            && Schema::hasColumn('consolidado_delivery_form_province', 'id_contenedor')
            && Schema::hasColumn('consolidado_delivery_form_province', 'id_cotizacion')
            && !$this->indexExists('consolidado_delivery_form_province', 'cc_delivery_prov_contenedor_cotizacion_idx')) {
            Schema::table('consolidado_delivery_form_province', function (Blueprint $table) {
                $table->index(
                    ['id_contenedor', 'id_cotizacion'],
                    'cc_delivery_prov_contenedor_cotizacion_idx'
                );
            });
        }
    }

    private function addAduanaTramitesIndexes(): void
    {
        if (!Schema::hasTable('consolidado_cotizacion_aduana_tramites')) {
            return;
        }

        if (Schema::hasColumn('consolidado_cotizacion_aduana_tramites', 'id_consolidado')
            && Schema::hasColumn('consolidado_cotizacion_aduana_tramites', 'id_cotizacion')
            && !$this->indexExists('consolidado_cotizacion_aduana_tramites', 'cc_aduana_consolidado_cotizacion_idx')) {
            Schema::table('consolidado_cotizacion_aduana_tramites', function (Blueprint $table) {
                $table->index(
                    ['id_consolidado', 'id_cotizacion'],
                    'cc_aduana_consolidado_cotizacion_idx'
                );
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
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

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }
};
