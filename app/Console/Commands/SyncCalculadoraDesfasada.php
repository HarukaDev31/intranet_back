<?php

namespace App\Console\Commands;

use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionProducto;
use App\Models\CalculadoraImportacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedorItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCalculadoraDesfasada extends Command
{
    protected $signature = 'calculadora:sync-desfasados
                            {--calculadora_id= : ID de calculadora_importacion}
                            {--cotizacion_id= : ID de contenedor_consolidado_cotizacion}
                            {--force : Aplica cambios. Sin --force solo previsualiza}';

    protected $description = 'Sincroniza proveedores/items faltantes desde cotizacion hacia calculadora_importacion.';

    public function handle()
    {
        $calculadoraId = (int) $this->option('calculadora_id');
        $cotizacionId = (int) $this->option('cotizacion_id');
        $force = (bool) $this->option('force');

        if ($calculadoraId <= 0 && $cotizacionId <= 0) {
            $this->error('Debes enviar --calculadora_id o --cotizacion_id.');
            return 1;
        }

        $calculadora = null;
        if ($calculadoraId > 0) {
            $calculadora = CalculadoraImportacion::find($calculadoraId);
            if (!$calculadora) {
                $this->error('No existe calculadora_importacion con ID ' . $calculadoraId);
                return 1;
            }
            if (!$cotizacionId) {
                $cotizacionId = (int) ($calculadora->id_cotizacion ?: 0);
            }
        }

        if ($cotizacionId <= 0) {
            $this->error('No se pudo resolver cotizacion_id. Envia --cotizacion_id manualmente.');
            return 1;
        }

        if (!$calculadora && $cotizacionId > 0) {
            $calculadora = CalculadoraImportacion::where('id_cotizacion', $cotizacionId)->first();
            if (!$calculadora) {
                $this->error('No existe calculadora_importacion vinculada a cotizacion_id=' . $cotizacionId);
                return 1;
            }
        }

        $this->info('Calculadora: ' . $calculadora->id . ' | Cotizacion: ' . $cotizacionId);
        if (!$force) {
            $this->warn('Modo preview (sin cambios). Usa --force para aplicar.');
        }

        $cotizacionProveedores = CotizacionProveedor::where('id_cotizacion', $cotizacionId)
            ->orderBy('id')
            ->get();

        if ($cotizacionProveedores->isEmpty()) {
            $this->warn('La cotización no tiene proveedores. Nada que sincronizar.');
            return 0;
        }

        $creadosProv = 0;
        $vinculadosProv = 0;
        $creadosItems = 0;
        $actualizadosCbm = 0;

        $tx = function () use ($cotizacionProveedores, $cotizacionId, $calculadora, &$creadosProv, &$vinculadosProv, &$creadosItems, &$actualizadosCbm) {
            foreach ($cotizacionProveedores as $provCot) {
                $provCalc = CalculadoraImportacionProveedor::where('id_calculadora_importacion', $calculadora->id)
                    ->where('id_proveedor', $provCot->id)
                    ->first();

                if (!$provCalc && !empty($provCot->code_supplier)) {
                    $provCalc = CalculadoraImportacionProveedor::where('id_calculadora_importacion', $calculadora->id)
                        ->where('code_supplier', $provCot->code_supplier)
                        ->first();
                }

                // Si encontró fila existente por id_proveedor o por code_supplier:
                // forzar sincronía con la fuente (cotización) en ambos campos.
                if ($provCalc) {
                    $dirty = false;
                    if ((int) ($provCalc->id_proveedor ?: 0) !== (int) $provCot->id) {
                        $provCalc->id_proveedor = $provCot->id;
                        $dirty = true;
                    }
                    $codeCot = $provCot->code_supplier ?: null;
                    $codeCalc = $provCalc->code_supplier ?: null;
                    if ($codeCalc !== $codeCot) {
                        $provCalc->code_supplier = $codeCot;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $provCalc->save();
                        $vinculadosProv++;
                    }
                }

                if (!$provCalc) {
                    $cbm = $provCot->cbm_total_china !== null ? $provCot->cbm_total_china : ($provCot->cbm_total !== null ? $provCot->cbm_total : 0);
                    $qty = $provCot->qty_box_china !== null ? $provCot->qty_box_china : ($provCot->qty_box !== null ? $provCot->qty_box : 0);
                    $provCalc = CalculadoraImportacionProveedor::create([
                        'id_calculadora_importacion' => $calculadora->id,
                        'id_proveedor' => $provCot->id,
                        'cbm' => (float) $cbm,
                        'peso' => (float) ($provCot->peso ?: 0),
                        'qty_caja' => (int) $qty,
                        'code_supplier' => $provCot->code_supplier ?: null,
                    ]);
                    $creadosProv++;
                }

                $itemsCot = CotizacionProveedorItem::where('id_cotizacion', $cotizacionId)
                    ->where('id_proveedor', $provCot->id)
                    ->orderBy('id')
                    ->get();

                if ($itemsCot->isEmpty()) {
                    continue;
                }

                $itemsCalc = CalculadoraImportacionProducto::where('id_proveedor', $provCalc->id)->get();
                $stock = [];
                foreach ($itemsCalc as $itemCalc) {
                    $k = $this->makeItemKey($itemCalc->nombre, $itemCalc->precio, $itemCalc->cantidad);
                    if (!isset($stock[$k])) {
                        $stock[$k] = 0;
                    }
                    $stock[$k]++;
                }

                foreach ($itemsCot as $itemCot) {
                    $nombre = $itemCot->final_name ?: $itemCot->initial_name;
                    $precio = $itemCot->final_price !== null ? $itemCot->final_price : $itemCot->initial_price;
                    $cantidad = $itemCot->final_qty !== null ? $itemCot->final_qty : $itemCot->initial_qty;

                    $nombre = trim((string) $nombre);
                    $precio = (float) ($precio ?: 0);
                    $cantidad = (float) ($cantidad ?: 0);

                    if ($nombre === '') {
                        $nombre = 'ITEM_' . $itemCot->id;
                    }
                    if ($cantidad <= 0) {
                        $cantidad = 1;
                    }

                    $k = $this->makeItemKey($nombre, $precio, $cantidad);
                    $disponible = isset($stock[$k]) ? $stock[$k] : 0;
                    if ($disponible > 0) {
                        $stock[$k] = $disponible - 1;
                        continue;
                    }

                    CalculadoraImportacionProducto::create([
                        'id_proveedor' => $provCalc->id,
                        'nombre' => $nombre,
                        'precio' => $precio,
                        'valoracion' => 0,
                        'cantidad' => $cantidad,
                        'antidumping_cu' => 0,
                        'ad_valorem_p' => 0,
                    ]);
                    $creadosItems++;
                }

                // Corrección: los items no tienen CBM.
                // Tomar CBM desde proveedor de cotización (preferir cbm_total, fallback cbm_total_china).
                $cbmProductos = $provCot->cbm_total !== null ? (float) $provCot->cbm_total : (float) ($provCot->cbm_total_china ?: 0);
                $cbmProductos = round($cbmProductos, 10);
                $cbmActual = round((float) ($provCalc->cbm ?: 0), 10);
                if ($cbmActual !== $cbmProductos) {
                    $provCalc->cbm = $cbmProductos;
                    $provCalc->save();
                    $actualizadosCbm++;
                }
            }
        };

        if ($force) {
            DB::transaction($tx);
        } else {
            DB::beginTransaction();
            try {
                $tx();
            } finally {
                DB::rollBack();
            }
        }

        $this->newLine();
        $this->info('Resumen');
        $this->line('- Proveedores creados en calculadora: ' . $creadosProv);
        $this->line('- Proveedores vinculados (id_proveedor): ' . $vinculadosProv);
        $this->line('- Items creados en calculadora: ' . $creadosItems);
        $this->line('- Proveedores con cbm recalculado: ' . $actualizadosCbm);

        if (!$force) {
            $this->warn('No se aplicaron cambios (preview). Ejecuta con --force para guardar.');
        }

        return 0;
    }

    private function makeItemKey($nombre, $precio, $cantidad)
    {
        $nombreNorm = mb_strtolower(trim((string) $nombre));
        return $nombreNorm . '|' . number_format((float) $precio, 4, '.', '') . '|' . number_format((float) $cantidad, 4, '.', '');
    }
}

