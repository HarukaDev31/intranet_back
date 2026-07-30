<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores_items_excel_conf', function (Blueprint $table) {
            if (!Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores_items_excel_conf', 'id_item_origen')) {
                $table->unsignedInteger('id_item_origen')->nullable()->after('id_proveedor');
                $table->unique(['id_proveedor', 'id_item_origen'], 'uniq_cccp_items_excel_conf_origen');
            }
        });

        // Migrar confirmaciones ya guardadas en ítems de cotización → excel_conf (sin borrar cotización)
        $items = DB::table('contenedor_consolidado_cotizacion_proveedores_items')
            ->where(function ($q) {
                $q->whereNotNull('confirmacion_qty')
                    ->orWhereNotNull('confirmacion_precio')
                    ->orWhereNotNull('caracteristicas');
            })
            ->get(['id', 'id_cotizacion', 'id_proveedor', 'initial_name', 'tipo_producto', 'caracteristicas', 'confirmacion_qty', 'confirmacion_precio']);

        foreach ($items as $item) {
            $exists = DB::table('contenedor_consolidado_cotizacion_proveedores_items_excel_conf')
                ->where('id_proveedor', $item->id_proveedor)
                ->where('id_item_origen', $item->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $caracteristicas = $item->caracteristicas;
            if (is_string($caracteristicas)) {
                $decoded = json_decode($caracteristicas, true);
                $caracteristicas = is_array($decoded) ? $decoded : null;
            }

            DB::table('contenedor_consolidado_cotizacion_proveedores_items_excel_conf')->insert([
                'id_cotizacion' => $item->id_cotizacion,
                'id_proveedor' => $item->id_proveedor,
                'id_item_origen' => $item->id,
                'initial_name' => $item->initial_name,
                'tipo_producto' => $item->tipo_producto ?: 'GENERAL',
                'caracteristicas' => $caracteristicas ? json_encode($caracteristicas) : null,
                'confirmacion_qty' => $item->confirmacion_qty,
                'confirmacion_precio' => $item->confirmacion_precio,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('contenedor_consolidado_cotizacion_proveedores_items_excel_conf', function (Blueprint $table) {
            if (Schema::hasColumn('contenedor_consolidado_cotizacion_proveedores_items_excel_conf', 'id_item_origen')) {
                $table->dropUnique('uniq_cccp_items_excel_conf_origen');
                $table->dropColumn('id_item_origen');
            }
        });
    }
};
