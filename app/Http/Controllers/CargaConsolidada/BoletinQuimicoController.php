<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Models\CargaConsolidada\BoletinQuimicoCotizacionItem;
use App\Traits\FileTrait;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedorItem;
use App\Models\CargaConsolidada\PagoBoletinQuimico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class BoletinQuimicoController extends Controller
{
    use FileTrait;
    /**
     * Listado para DataTable agrupado por cotización: una fila por cotización con items como subarray.
     * GET api/carga-consolidada/boletin-quimico
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 50);
            $page = (int) $request->input('page', 1);
            $search = trim((string) $request->input('search', ''));

            $groupedQuery = BoletinQuimicoCotizacionItem::query()
                ->select('boletin_quimico_cotizacion_item.id_cotizacion', 'boletin_quimico_cotizacion_item.id_contenedor')
                ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'boletin_quimico_cotizacion_item.id_cotizacion')
                ->join('carga_consolidada_contenedor as CONT', 'CONT.id', '=', 'boletin_quimico_cotizacion_item.id_contenedor')
                ->groupBy('boletin_quimico_cotizacion_item.id_cotizacion', 'boletin_quimico_cotizacion_item.id_contenedor')
                ->orderBy('boletin_quimico_cotizacion_item.id_cotizacion');

            if ($search !== '') {
                $term = '%' . $search . '%';
                $groupedQuery->where(function ($q) use ($term) {
                    $q->where('C.nombre', 'like', $term)
                        ->orWhere('CONT.carga', 'like', $term);
                });
            }

            $total = DB::table(DB::raw('(' . $groupedQuery->toSql() . ') as sub'))
                ->mergeBindings($groupedQuery->getQuery())
                ->count();

            $pairs = (clone $groupedQuery)->skip(($page - 1) * $perPage)->take($perPage)->get();
            $cotizacionIds = $pairs->pluck('id_cotizacion')->unique()->values()->all();

            if (empty($cotizacionIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'last_page' => (int) max(1, ceil($total / $perPage)),
                        'per_page' => $perPage,
                        'total' => $total,
                        'from' => 0,
                        'to' => 0,
                    ],
                ]);
            }

            $items = BoletinQuimicoCotizacionItem::query()
                ->select('boletin_quimico_cotizacion_item.*')
                ->with(['cotizacion:id,nombre,id_contenedor', 'cotizacionProveedorItem:id,final_name,initial_name', 'contenedor:id,carga,f_cierre', 'pagos'])
                ->withSum('pagos as total_pagado', 'monto')
                ->withCount('pagos as pagos_count')
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->orderBy('id_cotizacion')
                ->orderBy('id', 'desc')
                ->get();

            $groupedByCotizacion = $items->groupBy('id_cotizacion');

            $data = collect($pairs)->map(function ($pair) use ($groupedByCotizacion) {
                $rows = $groupedByCotizacion->get($pair->id_cotizacion, collect());
                $first = $rows->first();
                $cotizacion = $first ? $first->cotizacion : null;
                $contenedor = $first ? $first->contenedor : null;

                $consolidadoLabel = '—';
                if ($contenedor) {
                    $carga = $contenedor->carga ?? '';
                    $anio = $contenedor->f_cierre ? $contenedor->f_cierre->format('Y') : '';
                    $consolidadoLabel = $anio !== '' ? '#' . $carga . '-' . $anio : ($carga !== '' ? '#' . $carga : '—');
                }

                $itemsPayload = $rows->map(function ($row) {
                    $proveedorItem = $row->cotizacionProveedorItem;
                    $itemName = $proveedorItem ? ($proveedorItem->final_name ?: $proveedorItem->initial_name) : '—';
                    $pagosDetails = $row->pagos->map(function ($p) {
                        return [
                            'id_pago' => $p->id,
                            'monto' => (float) $p->monto,
                            'concepto' => (object) ['name' => 'Boletín químico'],
                            'status' => $p->status,
                            'payment_date' => $p->payment_date,
                            'banco' => $p->banco,
                            'voucher_url' => $this->generateImageUrl($p->voucher_url),
                        ];
                    })->values()->all();

                    return [
                        'id' => $row->id,
                        'item_nombre' => $itemName,
                        'monto_boletin' => (float) $row->monto_boletin,
                        'estado' => $row->estado,
                        'total_pagado' => (float) ($row->total_pagado ?? 0),
                        'pagos_count' => (int) ($row->pagos_count ?? 0),
                        'pagos_details' => $pagosDetails,
                    ];
                })->values()->all();

                return [
                    'id_cotizacion' => $pair->id_cotizacion,
                    'id_contenedor' => $pair->id_contenedor,
                    'cliente' => $cotizacion ? $cotizacion->nombre : '—',
                    'consolidado' => $consolidadoLabel,
                    'items' => $itemsPayload,
                ];
            })->values()->all();

            $lastPage = (int) max(1, ceil($total / $perPage));
            $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
            $to = min($page * $perPage, $total);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $from,
                    'to' => $to,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Contenedores (consolidados) para el modal.
     * GET api/carga-consolidada/boletin-quimico/contenedores
     */
    public function getContenedores(Request $request)
    {
        try {
            $contenedores = Contenedor::select('id', 'carga', 'empresa')
                ->orderByRaw('CAST(carga AS UNSIGNED) DESC')
                ->limit(200)
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'carga' => $c->carga, 'label' => '#' . $c->carga . ' - ' . ($c->empresa ?? '')]);
            return response()->json(['success' => true, 'data' => $contenedores]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getContenedores: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Clientes (cotizaciones) de un contenedor para el modal.
     * GET api/carga-consolidada/boletin-quimico/contenedor/{idContenedor}/clientes
     */
    public function getClientesByContenedor($idContenedor)
    {
        try {
            $clientes = Cotizacion::where('id_contenedor', $idContenedor)
                ->whereNotNull('estado_cliente')
                ->whereNull('id_cliente_importacion')
                ->where('estado_cotizador', 'CONFIRMADO')
                ->select('id', 'nombre', 'documento', 'telefono')
                ->orderBy('nombre')
                ->get();
            return response()->json(['success' => true, 'data' => $clientes]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getClientesByContenedor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Items (cotizacion_proveedor_items) de un contenedor para el modal.
     * GET api/carga-consolidada/boletin-quimico/contenedor/{idContenedor}/items
     */
    public function getItemsByContenedor($idContenedor)
    {
        try {
            $items = CotizacionProveedorItem::where('id_contenedor', $idContenedor)
                ->select(
                    'contenedor_consolidado_cotizacion_proveedores_items.id',
                    'contenedor_consolidado_cotizacion_proveedores_items.id_cotizacion',
                    'contenedor_consolidado_cotizacion_proveedores_items.final_name',
                    'contenedor_consolidado_cotizacion_proveedores_items.initial_name'
                )
                ->get()
                ->map(function ($i) {
                    $name = $i->final_name ?: $i->initial_name;
                    return [
                        'id' => $i->id,
                        'id_cotizacion' => $i->id_cotizacion,
                        'nombre' => $name ?: 'Item #' . $i->id,
                    ];
                });
            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getItemsByContenedor: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Items (cotizacion_proveedor_items) de una cotización. Con id_cotizacion es suficiente.
     * GET api/carga-consolidada/boletin-quimico/cotizacion/{idCotizacion}/items
     */
    public function getItemsByCotizacion($idCotizacion)
    {
        try {
            $items = CotizacionProveedorItem::where('id_cotizacion', $idCotizacion)
                ->select(
                    'contenedor_consolidado_cotizacion_proveedores_items.id',
                    'contenedor_consolidado_cotizacion_proveedores_items.id_cotizacion',
                    'contenedor_consolidado_cotizacion_proveedores_items.final_name',
                    'contenedor_consolidado_cotizacion_proveedores_items.initial_name'
                )
                ->get()
                ->map(function ($i) {
                    $name = $i->final_name ?: $i->initial_name;
                    return [
                        'id' => $i->id,
                        'id_cotizacion' => $i->id_cotizacion,
                        'nombre' => $name ?: 'Item #' . $i->id,
                    ];
                });
            return response()->json(['success' => true, 'data' => $items]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getItemsByCotizacion: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear registros de boletín químico (uno por cada cliente+item seleccionado con monto).
     * POST api/carga-consolidada/boletin-quimico
     * body: { id_contenedor, items: [{ id_cotizacion, id_cotizacion_proveedor_item, monto_boletin }] }
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_contenedor' => 'required|integer|exists:carga_consolidada_contenedor,id',
            'items' => 'required|array',
            'items.*.id_cotizacion' => 'required|integer|exists:contenedor_consolidado_cotizacion,id',
            'items.*.id_cotizacion_proveedor_item' => 'nullable|integer|exists:contenedor_consolidado_cotizacion_proveedores_items,id',
            'items.*.monto_boletin' => 'required|numeric|min:0',
        ]);

        try {
            $idContenedor = (int) $request->id_contenedor;
            $created = [];
            foreach ($request->items as $item) {
                $exists = BoletinQuimicoCotizacionItem::where('id_cotizacion', $item['id_cotizacion'])
                    ->where('id_cotizacion_proveedor_item', $item['id_cotizacion_proveedor_item'] ?? null)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $row = BoletinQuimicoCotizacionItem::create([
                    'id_contenedor' => $idContenedor,
                    'id_cotizacion' => $item['id_cotizacion'],
                    'id_cotizacion_proveedor_item' => $item['id_cotizacion_proveedor_item'] ?? null,
                    'monto_boletin' => $item['monto_boletin'],
                    'estado' => BoletinQuimicoCotizacionItem::ESTADO_PENDIENTE,
                ]);
                $created[] = $row;
            }
            return response()->json(['success' => true, 'message' => count($created) . ' registro(s) creado(s)', 'data' => $created]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pagos de un item (para PagosGrid).
     * GET api/carga-consolidada/boletin-quimico/item/{id}/pagos
     */
    public function getPagosByItem($id)
    {
        try {
            $item = BoletinQuimicoCotizacionItem::findOrFail($id);
            $pagos = $item->pagos()->orderBy('id', 'desc')->get();
            $pagosDetails = $pagos->map(function ($p) {
                return [
                    'id_pago' => $p->id,
                    'monto' => (float) $p->monto,
                    'concepto' => (object) ['name' => 'Boletín químico'],
                    'status' => $p->status,
                    'payment_date' => $p->payment_date,
                    'banco' => $p->banco,
                    'voucher_url' => $this->generateImageUrl($p->voucher_url),
                ];
            })->values()->all();
            return response()->json(['success' => true, 'data' => $pagosDetails, 'item' => $item]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getPagosByItem: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar un pago (adelanto) para un item. Actualiza estado del item según total pagado.
     * POST api/carga-consolidada/boletin-quimico/pago
     */
    public function storePago(Request $request)
    {
        $request->validate([
            'id_boletin_quimico_item' => 'required|integer|exists:boletin_quimico_cotizacion_item,id',
            'monto' => 'required|numeric|min:0.01',
            'banco' => 'nullable|string|max:128',
            'fecha' => 'required|string',
            'voucher' => 'nullable|file',
        ]);

        try {
            $user = JWTAuth::parseToken()->authenticate();
            $item = BoletinQuimicoCotizacionItem::findOrFail($request->id_boletin_quimico_item);

            $voucherUrl = null;
            if ($request->hasFile('voucher') && $request->file('voucher')->isValid()) {
                $path = $request->file('voucher')->store('boletin-quimico/vouchers', 'public');
                $voucherUrl = $path; // Se guarda ruta relativa al disco; generateImageUrl() devuelve URL absoluta en respuestas
            }

            $pago = PagoBoletinQuimico::create([
                'id_boletin_quimico_item' => $item->id,
                'monto' => $request->monto,
                'voucher_url' => $voucherUrl,
                'payment_date' => date('Y-m-d', strtotime($request->fecha)),
                'banco' => $request->banco ?? null,
                'status' => PagoBoletinQuimico::STATUS_PENDIENTE,
                'created_by' => $user->ID_Usuario ?? null,
            ]);

            $this->actualizarEstadoItem($item);
            $item->refresh();

            $pagoData = $pago->toArray();
            $pagoData['voucher_url'] = $this->generateImageUrl($pago->voucher_url);

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado',
                'data' => $pagoData,
                'estado_item' => $item->estado,
            ]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController storePago: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar estado de un pago (verificación ADMINISTRACION).
     * PUT api/carga-consolidada/boletin-quimico/pago/{idPago}
     */
    public function updateEstadoPago(Request $request, $idPago)
    {
        $request->validate(['status' => 'required|in:PENDIENTE,CONFIRMADO,OBSERVADO']);

        try {
            $pago = PagoBoletinQuimico::findOrFail($idPago);
            $pago->status = $request->status;
            if ($request->status === PagoBoletinQuimico::STATUS_CONFIRMADO) {
                $pago->confirmation_date = now();
            } else {
                $pago->confirmation_date = null;
            }
            $pago->save();

            $this->actualizarEstadoItem($pago->boletinQuimicoItem);
            $item = $pago->boletinQuimicoItem->fresh();

            $pagoData = $pago->toArray();
            $pagoData['voucher_url'] = $this->generateImageUrl($pago->voucher_url);

            return response()->json([
                'success' => true,
                'data' => $pagoData,
                'estado_item' => $item->estado,
            ]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController updateEstadoPago: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Detalle de un item para vista verificacion (por item, con pagos y opción verificar).
     * GET api/carga-consolidada/boletin-quimico/item/{id}
     */
    public function getItemDetalle($id)
    {
        try {
            $item = BoletinQuimicoCotizacionItem::with(['cotizacion:id,nombre,documento,telefono', 'contenedor:id,carga', 'cotizacionProveedorItem', 'pagos'])
                ->findOrFail($id);
            $proveedorItem = $item->cotizacionProveedorItem;
            $itemNombre = $proveedorItem ? ($proveedorItem->final_name ?: $proveedorItem->initial_name) : '—';
            $pagosDetalle = $item->pagos->map(fn ($p) => [
                'id' => $p->id,
                'monto' => (float) $p->monto,
                'status' => $p->status,
                'payment_date' => $p->payment_date,
                'banco' => $p->banco,
                'voucher_url' => $this->generateImageUrl($p->voucher_url),
                'confirmation_date' => $p->confirmation_date ? $p->confirmation_date->toIso8601String() : null,
            ])->values()->all();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $item->id,
                    'cliente' => $item->cotizacion ? $item->cotizacion->nombre : null,
                    'consolidado' => $item->contenedor ? $item->contenedor->carga : null,
                    'item_nombre' => $itemNombre,
                    'monto_boletin' => (float) $item->monto_boletin,
                    'estado' => $item->estado,
                    'total_pagado' => (float) $item->pagos->sum('monto'),
                    'pagos' => $pagosDetalle,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BoletinQuimicoController getItemDetalle: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function actualizarEstadoItem(BoletinQuimicoCotizacionItem $item): void
    {
        $totalPagado = (float) $item->pagos()->sum('monto');
        $montoBoletin = (float) $item->monto_boletin;

        if ($totalPagado <= 0) {
            $item->estado = BoletinQuimicoCotizacionItem::ESTADO_PENDIENTE;
        } elseif ($totalPagado >= $montoBoletin) {
            $item->estado = BoletinQuimicoCotizacionItem::ESTADO_PAGADO;
        } else {
            $item->estado = BoletinQuimicoCotizacionItem::ESTADO_ADELANTO_PAGADO;
        }
        $item->save();
    }
}
