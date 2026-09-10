<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Http\Controllers\Controller;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedorArchivoIa;
use App\Models\CargaConsolidada\CotizacionProveedorResumen;
use App\Services\CargaConsolidada\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Flujo "resumen": organizaciones sin items (calculadora), que suben un
 * documento y lo leen con IA en vez de cargar producto por producto.
 *
 * La extracción ocurre ANTES de que exista Cotizacion/CotizacionProveedor
 * (se crean recien al finalizar el wizard), asi que el archivo se guarda en
 * una ruta de staging por organizacion; el registro de auditoria
 * (CotizacionProveedorArchivoIa) se crea recien al finalizar, cuando ya
 * existen los ids reales de contenedor/cotizacion/proveedor.
 */
class CotizacionResumenController extends Controller
{
    private const GEMINI_SUPPORTED_MIMES = [
        'application/pdf',
    ];

    private const MAX_FILE_SIZE_KB = 10240; // 10 MB

    private function objectStorage(): ObjectStorageConnectorInterface
    {
        return app(ObjectStorageConnectorInterface::class);
    }

    /**
     * Sube el documento de la cotización y lo procesa con IA.
     * POST /api/carga-consolidada/cotizacion-resumen/extraer-documento
     */
    public function extraerDocumento(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:' . self::MAX_FILE_SIZE_KB,
        ]);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Archivo inválido'], 422);
        }

        $authUser = auth()->user();
        $orgId = (int) $authUser->getAttribute('ID_Organizacion');

        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();

        $storedPath = $this->objectStorage()->storeUploadedFile(
            $file,
            'cargaconsolidada/cotizacion-resumen/staging/' . $orgId,
            Str::uuid()->toString() . '-' . $originalName
        );

        $data = null;
        $extractedByAi = false;
        $error = null;

        if (in_array($mimeType, self::GEMINI_SUPPORTED_MIMES, true)) {
            $gemini = new GeminiService();
            $filePath = $this->objectStorage()->localPath($storedPath);
            $result = $gemini->extractFromCotizacionResumen($filePath, $mimeType);

            if ($result['success']) {
                $data = $result['data'];
                $extractedByAi = true;
            } else {
                $error = $result['error'];
                Log::warning('CotizacionResumenController: Gemini no pudo extraer datos', [
                    'organizacion_id' => $orgId,
                    'mime_type' => $mimeType,
                    'error' => $error,
                ]);
            }
        } else {
            Log::info('CotizacionResumenController: mime no soportado para extracción IA', [
                'mime_type' => $mimeType,
            ]);
        }

        return response()->json([
            'success' => true,
            'extracted_by_ai' => $extractedByAi,
            'message' => $extractedByAi
                ? null
                : ($error ?? 'No se pudo leer el documento automáticamente; completa los datos a mano.'),
            'data' => $data,
            'archivo' => [
                'path' => $storedPath,
                'nombre_original' => $originalName,
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * Listado de cotizaciones en modo "resumen" (sin items).
     * GET /api/carga-consolidada/cotizacion-resumen
     */
    public function index(Request $request)
    {
        try {
            $query = Cotizacion::query()
                ->whereIn('id', function ($q) {
                    $q->select('id_cotizacion')
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('modo_cotizacion', 'resumen');
                });

            if ($request->filled('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }
            if ($request->filled('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }
            if ($request->filled('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('id_contenedor')) {
                $query->where('id_contenedor', $request->id_contenedor);
            }
            if ($request->filled('id_usuario')) {
                $query->where('id_usuario', $request->id_usuario);
            }
            if ($request->filled('search')) {
                $term = $request->search;
                $query->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', "%{$term}%")
                        ->orWhere('documento', 'like', "%{$term}%")
                        ->orWhere('telefono', 'like', "%{$term}%");
                });
            }

            $perPage = (int) $request->input('per_page', 10);
            $page = (int) $request->input('page', 1);
            if ($perPage <= 0) $perPage = 10;
            if ($perPage > 100) $perPage = 100;
            if ($page <= 0) $page = 1;

            $paginator = $query->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

            $cotizacionIds = collect($paginator->items())->map(fn ($c) => $c->getAttribute('id'))->values()->all();

            $proveedoresPorCotizacion = CotizacionProveedor::query()
                ->where('modo_cotizacion', 'resumen')
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->with('resumen')
                ->get()
                ->groupBy(fn ($p) => $p->getAttribute('id_cotizacion'));

            $contenedores = Contenedor::query()
                ->whereIn('id', collect($paginator->items())->map(fn ($c) => $c->getAttribute('id_contenedor'))->unique()->values())
                ->get()
                ->keyBy(fn ($ct) => $ct->getAttribute('id'));

            $usuarios = \App\Models\Usuario::query()
                ->whereIn('ID_Usuario', collect($paginator->items())->map(fn ($c) => $c->getAttribute('id_usuario'))->filter()->unique()->values())
                ->get()
                ->keyBy(fn ($u) => $u->getAttribute('ID_Usuario'));

            $data = collect($paginator->items())->map(function ($c) use ($proveedoresPorCotizacion, $contenedores, $usuarios) {
                $proveedores = $proveedoresPorCotizacion->get($c->getAttribute('id'), collect());
                $totalCbm = $proveedores->sum(fn ($p) => (float) ($p->getAttribute('cbm_total') ?? 0));
                $totalCajas = $proveedores->sum(fn ($p) => (int) ($p->getAttribute('qty_box') ?? 0));
                $contenedor = $contenedores->get($c->getAttribute('id_contenedor'));
                $usuario = $usuarios->get($c->getAttribute('id_usuario'));

                return [
                    'id' => $c->getAttribute('id'),
                    'fecha' => $c->getAttribute('fecha'),
                    'nombre' => $c->getAttribute('nombre'),
                    'documento' => $c->getAttribute('documento'),
                    'telefono' => $c->getAttribute('telefono'),
                    'correo' => $c->getAttribute('correo'),
                    'estado' => $c->getAttribute('estado'),
                    'id_contenedor' => $c->getAttribute('id_contenedor'),
                    'contenedor' => optional($contenedor)->getAttribute('carga'),
                    'id_usuario' => $c->getAttribute('id_usuario'),
                    'vendedor' => optional($usuario)->getAttribute('No_Nombres_Apellidos'),
                    'total_cbm' => $totalCbm,
                    'total_cajas' => $totalCajas,
                    'proveedores' => $proveedores->map(function ($p) {
                        $resumen = $p->getRelation('resumen');
                        return [
                            'id' => $p->getAttribute('id'),
                            'estado_china' => $p->getAttribute('estados_proveedor'),
                            'producto' => optional($resumen)->getAttribute('producto'),
                            'volumen_cbm' => $p->getAttribute('cbm_total'),
                            'peso_total' => $p->getAttribute('peso'),
                            'qty_cajas' => $p->getAttribute('qty_box'),
                            'costo_unitario_estimado' => optional($resumen)->getAttribute('costo_unitario_estimado'),
                            'inversion_total' => optional($resumen)->getAttribute('inversion_total'),
                        ];
                    })->values(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CotizacionResumenController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar cotizaciones'], 500);
        }
    }

    /**
     * Crea la cotización "resumen" (cabecera + proveedores) al finalizar el wizard.
     * POST /api/carga-consolidada/cotizacion-resumen
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_contenedor' => 'required|integer',
            'id_usuario' => 'required|integer',
            'cliente' => 'required|array',
            'cliente.nombre' => 'required|string|max:150',
            'cliente.tipo_documento' => 'nullable|string|in:ID,RUC',
            'cliente.documento' => 'nullable|string|max:50',
            'cliente.whatsapp' => 'nullable|string|max:50',
            'cliente.correo' => 'nullable|string|max:150',
            'proveedores' => 'required|array|min:1',
            'proveedores.*.cbm_total' => 'required|numeric|min:0',
            'proveedores.*.peso_total' => 'nullable|numeric|min:0',
            'proveedores.*.qty_cajas' => 'nullable|integer|min:0',
            'proveedores.*.productos' => 'required|string|max:500',
            'descuento' => 'nullable|numeric|min:0',
            'archivo' => 'nullable|array',
            'archivo.path' => 'required_with:archivo|string',
            'archivo.nombre_original' => 'nullable|string',
            'archivo.mime_type' => 'nullable|string',
        ]);

        $contenedor = Contenedor::find($request->id_contenedor);
        if (!$contenedor) {
            return response()->json(['success' => false, 'message' => 'Contenedor no encontrado'], 404);
        }

        DB::beginTransaction();
        try {
            $cliente = $request->input('cliente');

            $cotizacion = Cotizacion::create([
                'id_contenedor' => $request->id_contenedor,
                'uuid' => Str::uuid()->toString(),
                'id_usuario' => $request->id_usuario,
                // El wizard no distingue cliente nuevo/antiguo/socio todavía; 1 = NUEVO.
                'id_tipo_cliente' => 1,
                'nombre' => $cliente['nombre'],
                'documento' => $cliente['documento'] ?? null,
                'correo' => $cliente['correo'] ?? null,
                'telefono' => $cliente['whatsapp'] ?? null,
                'estado' => 'PENDIENTE',
                'from_calculator' => 0,
            ]);

            $primerProveedorId = null;
            foreach ($request->input('proveedores') as $prov) {
                $proveedor = CotizacionProveedor::create([
                    'id_cotizacion' => $cotizacion->getAttribute('id'),
                    'id_contenedor' => $request->id_contenedor,
                    'modo_cotizacion' => 'resumen',
                    'cbm_total' => $prov['cbm_total'],
                    'peso' => $prov['peso_total'] ?? null,
                    'qty_box' => $prov['qty_cajas'] ?? null,
                    'products' => $prov['productos'],
                    'estados_proveedor' => 'WAIT',
                ]);

                CotizacionProveedorResumen::create([
                    'id_contenedor' => $request->id_contenedor,
                    'id_cotizacion' => $cotizacion->getAttribute('id'),
                    'id_proveedor' => $proveedor->getAttribute('id'),
                    'producto' => $prov['productos'],
                    'volumen_cbm' => $prov['cbm_total'],
                ]);

                if ($primerProveedorId === null) {
                    $primerProveedorId = $proveedor->getAttribute('id');
                }
            }

            $archivo = $request->input('archivo');
            if ($archivo && $primerProveedorId) {
                CotizacionProveedorArchivoIa::create([
                    'id_contenedor' => $request->id_contenedor,
                    'id_cotizacion' => $cotizacion->getAttribute('id'),
                    'id_proveedor' => $primerProveedorId,
                    'archivo_path' => $archivo['path'],
                    'archivo_nombre_original' => $archivo['nombre_original'] ?? null,
                    'estado' => 'procesado',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cotización registrada exitosamente',
                'data' => ['id' => $cotizacion->getAttribute('id')],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CotizacionResumenController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al registrar la cotización'], 500);
        }
    }

    /**
     * Cambia el estado (COTIZADO/CONFIRMADO) de una cotización "resumen".
     * PUT /api/carga-consolidada/cotizacion-resumen/{id}/estado
     */
    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string|in:PENDIENTE,CONFIRMADO,DECLINADO',
        ]);

        $cotizacion = Cotizacion::find($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $cotizacion->update(['estado' => $request->estado]);

        return response()->json(['success' => true, 'message' => 'Estado actualizado exitosamente']);
    }
}
