<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Http\Controllers\Controller;
use App\Traits\FileTrait;
use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedorArchivoIa;
use App\Models\CargaConsolidada\CotizacionProveedorResumen;
use App\Models\CargaConsolidada\CotizacionProveedorResumenCosto;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use App\Services\CargaConsolidada\GeminiService;
use App\Support\Phone\CountryPhoneHelper;
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
    use FileTrait;

    private const GEMINI_SUPPORTED_MIMES = [
        'application/pdf',
    ];

    private const MAX_FILE_SIZE_KB = 10240; // 10 MB

    private const ID_ORGANIZACION_ADMIN = 1;

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

        $orgId = $this->orgIdAutenticada();

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
     * Autocomplete de BD clientes de la org (número existente o uno nuevo).
     * GET /api/carga-consolidada/cotizacion-resumen/clientes?q=
     */
    public function searchClientes(Request $request)
    {
        $orgId = $this->orgIdEfectiva($request);
        if ($orgId <= 0) {
            return response()->json(['success' => false, 'message' => 'No autenticado', 'data' => []], 401);
        }

        $q = trim((string) $request->query('q', $request->input('q', '')));

        $query = Cliente::query()
            ->where('organizacion_id', $orgId)
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '');

        if ($q !== '') {
            $digits = CountryPhoneHelper::digits($q);
            $query->where(function ($inner) use ($q, $digits) {
                $inner->where('nombre', 'like', '%' . $q . '%')
                    ->orWhere('documento', 'like', '%' . $q . '%')
                    ->orWhere('correo', 'like', '%' . $q . '%')
                    ->orWhere('telefono', 'like', '%' . $q . '%');
                if ($digits !== '' && strlen($digits) >= 3) {
                    $inner->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?',
                        ['%' . $digits . '%']
                    );
                }
            });
        }

        $rows = $query->orderByDesc('id')->limit(20)->get([
            'id', 'nombre', 'documento', 'correo', 'telefono',
        ]);

        return response()->json([
            'success' => true,
            'data' => $rows->map(function ($c) {
                $tel = (string) $c->getAttribute('telefono');
                $nombre = (string) $c->getAttribute('nombre');
                return [
                    'id' => (int) $c->getAttribute('id'),
                    'nombre' => $nombre,
                    'documento' => $c->getAttribute('documento'),
                    'correo' => $c->getAttribute('correo'),
                    'telefono' => $tel,
                    'label' => $nombre !== '' ? ($tel . ' · ' . $nombre) : $tel,
                    'value' => $tel,
                ];
            })->values(),
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
                $estadoFiltro = $request->estado === 'PENDIENTE' ? 'COTIZADO' : $request->estado;
                $query->where('estado_resumen', $estadoFiltro);
            }
            if ($request->filled('id_contenedor')) {
                $query->where('id_contenedor', $request->id_contenedor);
            }
            if ($request->filled('id_usuario')) {
                $query->where('id_usuario', $request->id_usuario);
            }
            if ($request->filled('estado_china') && $request->estado_china !== 'todos') {
                $estadoChina = $request->estado_china;
                $query->whereIn('id', function ($q) use ($estadoChina) {
                    $q->select('id_cotizacion')
                        ->from('contenedor_consolidado_cotizacion_proveedores')
                        ->where('modo_cotizacion', 'resumen')
                        ->where('estados_proveedor', $estadoChina);
                });
            }
            $orgEfectiva = $this->orgIdEfectiva($request);
            if ($this->esOrgAdmin() && $request->filled('id_org')) {
                $query->where('organizacion_id', $orgEfectiva);
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
                ->with('resumen.costos')
                ->get()
                ->groupBy(fn ($p) => $p->getAttribute('id_cotizacion'));

            $contenedores = Contenedor::query()
                ->whereIn('id', collect($paginator->items())->map(fn ($c) => $c->getAttribute('id_contenedor'))->unique()->values())
                ->get()
                ->keyBy(fn ($ct) => $ct->getAttribute('id'));

            $usuarios = Usuario::query()
                ->whereIn('ID_Usuario', collect($paginator->items())->map(fn ($c) => $c->getAttribute('id_usuario'))->filter()->unique()->values())
                ->get()
                ->keyBy(fn ($u) => $u->getAttribute('ID_Usuario'));

            $archivosPorCotizacion = CotizacionProveedorArchivoIa::query()
                ->whereIn('id_cotizacion', $cotizacionIds)
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($a) => $a->getAttribute('id_cotizacion'));

            $data = collect($paginator->items())->map(function ($c) use ($proveedoresPorCotizacion, $contenedores, $usuarios, $archivosPorCotizacion) {
                $proveedores = $proveedoresPorCotizacion->get($c->getAttribute('id'), collect());
                $totalCbm = $proveedores->sum(function ($p) {
                    return (float) ($p->getAttribute('cbm_total') ?? 0) + (float) ($p->getAttribute('cbm_imo') ?? 0);
                });
                $totalCajas = $proveedores->sum(fn ($p) => (int) ($p->getAttribute('qty_box') ?? 0));
                $totalesCosto = $this->sumarCostosProveedores($proveedores);
                $fob = (float) ($c->getAttribute('fob') ?? 0);
                $logistica = (float) ($c->getAttribute('monto') ?? 0);
                $impuesto = (float) ($c->getAttribute('impuestos') ?? 0);
                if ($fob <= 0 && $logistica <= 0 && $impuesto <= 0) {
                    $fob = $totalesCosto['fob'];
                    $logistica = $totalesCosto['logistica'];
                    $impuesto = $totalesCosto['impuesto'];
                }
                $totalInversion = $proveedores->sum(fn ($p) => (float) (optional($p->getRelation('resumen'))->getAttribute('inversion_total') ?? 0));
                $contenedor = $contenedores->get($c->getAttribute('id_contenedor'));
                $usuario = $usuarios->get($c->getAttribute('id_usuario'));
                $archivo = optional($archivosPorCotizacion->get($c->getAttribute('id'), collect())->first());
                $carga = optional($contenedor)->getAttribute('carga');

                return [
                    'id' => $c->getAttribute('id'),
                    'fecha' => $c->getAttribute('fecha'),
                    'nombre' => $c->getAttribute('nombre'),
                    'documento' => $c->getAttribute('documento'),
                    'telefono' => $c->getAttribute('telefono'),
                    'correo' => $c->getAttribute('correo'),
                    'estado' => $c->getAttribute('estado_resumen') ?: 'COTIZADO',
                    'id_contenedor' => $c->getAttribute('id_contenedor'),
                    'contenedor' => $carga,
                    'campania' => $carga ? ('#' . $carga) : null,
                    'id_usuario' => $c->getAttribute('id_usuario'),
                    'vendedor' => optional($usuario)->getAttribute('No_Nombres_Apellidos'),
                    'total_cbm' => $totalCbm,
                    'total_cajas' => $totalCajas,
                    'total_inversion' => $totalInversion,
                    'fob' => $fob,
                    'logistica' => $logistica,
                    'impuesto' => $impuesto,
                    'tarifa' => (float) ($c->getAttribute('tarifa') ?? 0),
                    'descuento' => (float) ($c->getAttribute('tarifa_descuento') ?? 0),
                    'cod_cotizacion' => $c->getAttribute('cod_contract_calculator'),
                    'cod_contract' => $c->getAttribute('cod_contract'),
                    'cotizacion_file_url' => $this->urlArchivo($c->getAttribute('cotizacion_file_url')),
                    'url_cotizacion_pdf' => $this->urlArchivo($c->getAttribute('url_cotizacion_pdf')),
                    'archivo_url' => $this->urlArchivo($archivo ? $archivo->getAttribute('archivo_path') : null),
                    'archivo_nombre' => $archivo ? $archivo->getAttribute('archivo_nombre_original') : null,
                    'proveedores' => $proveedores->map(function ($p) {
                        $resumen = $p->getRelation('resumen');
                        $cbmNormal = (float) ($p->getAttribute('cbm_total') ?? 0);
                        $cbmImo = (float) ($p->getAttribute('cbm_imo') ?? 0);
                        return [
                            'id' => $p->getAttribute('id'),
                            'code_supplier' => $p->getAttribute('code_supplier'),
                            'estado_china' => $p->getAttribute('estados_proveedor'),
                            'producto' => optional($resumen)->getAttribute('producto'),
                            'volumen_cbm' => $cbmNormal + $cbmImo,
                            'cbm_normal' => $cbmNormal,
                            'cbm_imo' => $cbmImo,
                            'peso_total' => $p->getAttribute('peso'),
                            'qty_cajas' => $p->getAttribute('qty_box'),
                            'unidades' => optional($resumen)->getAttribute('unidades'),
                            'incoterm' => optional($resumen)->getAttribute('incoterm'),
                            'moneda' => optional($resumen)->getAttribute('moneda'),
                            'costo_unitario_estimado' => optional($resumen)->getAttribute('costo_unitario_estimado'),
                            'inversion_total' => optional($resumen)->getAttribute('inversion_total'),
                            'costos' => optional($resumen)->getRelation('costos')
                                ? $resumen->getRelation('costos')->map(fn ($costo) => [
                                    'concepto' => $costo->getAttribute('concepto'),
                                    'valor' => $costo->getAttribute('valor'),
                                ])->values()
                                : [],
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
            'cliente.id' => 'nullable|integer',
            'cliente.whatsapp' => 'nullable|string|max:50',
            'cliente.correo' => 'nullable|string|max:150',
            'proveedores' => 'required|array|min:1',
            'proveedores.*.cbm_total' => 'required|numeric|min:0',
            'proveedores.*.cbm_imo' => 'nullable|numeric|min:0',
            'proveedores.*.peso_total' => 'nullable|numeric|min:0',
            'proveedores.*.qty_cajas' => 'nullable|integer|min:0',
            'proveedores.*.productos' => 'required|string|max:500',
            'proveedores.*.unidades' => 'nullable|integer|min:0',
            'proveedores.*.incoterm' => 'nullable|string|max:50',
            'proveedores.*.moneda' => 'nullable|string|max:3',
            'proveedores.*.costos' => 'nullable|array',
            'proveedores.*.costos.*.concepto' => 'required_with:proveedores.*.costos|string|max:150',
            'proveedores.*.costos.*.valor' => 'required_with:proveedores.*.costos|numeric|min:0',
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
        if (!$this->contenedorPerteneceALaOrg($contenedor)) {
            return response()->json(['success' => false, 'message' => 'El consolidado no pertenece a tu organización'], 403);
        }
        if (!$this->vendedorPerteneceALaOrg((int) $request->id_usuario)) {
            return response()->json(['success' => false, 'message' => 'El vendedor no pertenece a tu organización'], 403);
        }

        DB::beginTransaction();
        try {
            $cliente = $request->input('cliente');
            $totalesCosto = $this->sumarCostosRequest($request->input('proveedores', []));
            $orgId = (int) $contenedor->getAttribute('organizacion_id') ?: $this->orgIdEfectiva($request);
            $clienteExistente = $this->resolverClienteDeLaOrg($cliente, $orgId);
            if (!empty($cliente['id']) && !$clienteExistente) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El cliente no pertenece a tu organización'], 403);
            }

            $cotizacion = Cotizacion::create([
                'id_contenedor' => $request->id_contenedor,
                'uuid' => Str::uuid()->toString(),
                'id_usuario' => $request->id_usuario,
                'fecha' => now(),
                'id_tipo_cliente' => $clienteExistente
                    ? $this->idTipoClientePorNombre('ANTIGUO', 1)
                    : $this->idTipoClientePorNombre('NUEVO', 1),
                'id_cliente' => $clienteExistente ? (int) $clienteExistente->id : null,
                'nombre' => $cliente['nombre'],
                'documento' => $cliente['documento'] ?? null,
                'correo' => $cliente['correo'] ?? null,
                'telefono' => $this->telefonoConPrefijoOrg($cliente['whatsapp'] ?? null, $orgId, $contenedor),
                'estado' => 'PENDIENTE',
                'estado_cotizador' => 'PENDIENTE',
                'estado_resumen' => 'COTIZADO',
                'from_calculator' => 0,
            ]);

            $primerProveedorId = null;
            $totalCbmFull = 0.0;
            $totalCbmImo = 0.0;
            foreach ($request->input('proveedores') as $idx => $prov) {
                $partido = $this->partirCbmProveedor($prov);
                if ($partido === null) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'El CBM IMO del proveedor #' . ($idx + 1) . ' no puede ser mayor al CBM total.',
                    ], 422);
                }

                $totalCbmFull += $partido['cbm_full'];
                $totalCbmImo += $partido['cbm_imo'];

                $proveedor = CotizacionProveedor::create([
                    'id_cotizacion' => $cotizacion->getAttribute('id'),
                    'id_contenedor' => $request->id_contenedor,
                    'modo_cotizacion' => 'resumen',
                    'cbm_total' => $partido['cbm_normal'],
                    'cbm_imo' => $partido['cbm_imo'],
                    'peso' => $prov['peso_total'] ?? null,
                    'qty_box' => $prov['qty_cajas'] ?? null,
                    'products' => $prov['productos'],
                    'estados_proveedor' => 'WAIT',
                    'tipo_rotulado' => 'pendiente',
                ]);

                $costos = $prov['costos'] ?? [];
                $inversionTotal = collect($costos)->sum(fn ($c) => (float) $c['valor']);
                $unidades = $prov['unidades'] ?? null;
                $costoUnitarioEstimado = ($unidades && (int) $unidades > 0)
                    ? round($inversionTotal / (int) $unidades, 4)
                    : null;

                $resumen = CotizacionProveedorResumen::create([
                    'id_contenedor' => $request->id_contenedor,
                    'id_cotizacion' => $cotizacion->getAttribute('id'),
                    'id_proveedor' => $proveedor->getAttribute('id'),
                    'producto' => $prov['productos'],
                    'volumen_cbm' => $partido['cbm_full'],
                    'unidades' => $unidades,
                    'incoterm' => $prov['incoterm'] ?? null,
                    'costo_unitario_estimado' => $costoUnitarioEstimado,
                    'inversion_total' => count($costos) > 0 ? $inversionTotal : null,
                    'moneda' => $prov['moneda'] ?? 'USD',
                ]);

                foreach ($costos as $orden => $costo) {
                    CotizacionProveedorResumenCosto::create([
                        'id_cotizacion_proveedor_resumen' => $resumen->getAttribute('id'),
                        'concepto' => $costo['concepto'],
                        'orden' => $orden,
                        'valor' => $costo['valor'],
                    ]);
                }

                if ($primerProveedorId === null) {
                    $primerProveedorId = $proveedor->getAttribute('id');
                }
            }

            $archivo = $request->input('archivo');
            $cotizacionUpdate = [
                'volumen' => $totalCbmFull,
                'es_imo' => $totalCbmImo > 0,
                'fob' => $totalesCosto['fob'],
                'monto' => $totalesCosto['logistica'],
                'impuestos' => $totalesCosto['impuesto'],
                'tarifa_descuento' => $request->input('descuento', 0),
            ];
            if ($archivo && !empty($archivo['path'])) {
                $cotizacionUpdate['cotizacion_file_url'] = $archivo['path'];
            }
            $cotizacion->update($cotizacionUpdate);

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
     * Detalle para editar el wizard.
     * GET /api/carga-consolidada/cotizacion-resumen/{id}
     */
    public function show($id)
    {
        $cotizacion = $this->findResumen($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $proveedores = CotizacionProveedor::query()
            ->where('id_cotizacion', $id)
            ->where('modo_cotizacion', 'resumen')
            ->with('resumen.costos')
            ->orderBy('id')
            ->get();

        $archivo = CotizacionProveedorArchivoIa::query()
            ->where('id_cotizacion', $id)
            ->orderBy('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cotizacion->getAttribute('id'),
                'estado' => $cotizacion->getAttribute('estado_resumen') ?: 'COTIZADO',
                'id_contenedor' => $cotizacion->getAttribute('id_contenedor'),
                'id_usuario' => $cotizacion->getAttribute('id_usuario'),
                'descuento' => (float) ($cotizacion->getAttribute('tarifa_descuento') ?? 0),
                'cliente' => [
                    'id' => $cotizacion->getAttribute('id_cliente'),
                    'nombre' => $cotizacion->getAttribute('nombre'),
                    'tipo_documento' => $this->tipoDocumentoCliente($cotizacion->getAttribute('documento')),
                    'documento' => $cotizacion->getAttribute('documento'),
                    'whatsapp' => $cotizacion->getAttribute('telefono'),
                    'correo' => $cotizacion->getAttribute('correo'),
                ],
                'archivo' => $archivo ? [
                    'path' => $archivo->getAttribute('archivo_path'),
                    'nombre_original' => $archivo->getAttribute('archivo_nombre_original'),
                    'url' => $this->urlArchivo($archivo->getAttribute('archivo_path')),
                ] : null,
                'proveedores' => $proveedores->map(function ($p) {
                    $resumen = $p->getRelation('resumen');
                    $cbmNormal = (float) ($p->getAttribute('cbm_total') ?? 0);
                    $cbmImo = (float) ($p->getAttribute('cbm_imo') ?? 0);
                    return [
                        'id' => $p->getAttribute('id'),
                        'code_supplier' => $p->getAttribute('code_supplier'),
                        'cbm_total' => $cbmNormal + $cbmImo,
                        'cbm_imo' => $cbmImo,
                        'peso_total' => $p->getAttribute('peso'),
                        'qty_cajas' => $p->getAttribute('qty_box'),
                        'productos' => optional($resumen)->getAttribute('producto') ?: $p->getAttribute('products'),
                        'unidades' => optional($resumen)->getAttribute('unidades'),
                        'incoterm' => optional($resumen)->getAttribute('incoterm'),
                        'moneda' => optional($resumen)->getAttribute('moneda'),
                        'costos' => optional($resumen)->getRelation('costos')
                            ? $resumen->getRelation('costos')->map(fn ($costo) => [
                                'concepto' => $costo->getAttribute('concepto'),
                                'valor' => $costo->getAttribute('valor'),
                            ])->values()
                            : [],
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Actualiza una cotización resumen. Solo si está en COTIZADO.
     * PUT /api/carga-consolidada/cotizacion-resumen/{id}
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'id_contenedor' => 'nullable|integer',
            'id_usuario' => 'required|integer',
            'cliente' => 'required|array',
            'cliente.nombre' => 'required|string|max:150',
            'cliente.tipo_documento' => 'nullable|string|in:ID,RUC',
            'cliente.documento' => 'nullable|string|max:50',
            'cliente.id' => 'nullable|integer',
            'cliente.whatsapp' => 'nullable|string|max:50',
            'cliente.correo' => 'nullable|string|max:150',
            'proveedores' => 'required|array|min:1',
            'proveedores.*.id' => 'nullable|integer',
            'proveedores.*.cbm_total' => 'required|numeric|min:0',
            'proveedores.*.cbm_imo' => 'nullable|numeric|min:0',
            'proveedores.*.peso_total' => 'nullable|numeric|min:0',
            'proveedores.*.qty_cajas' => 'nullable|integer|min:0',
            'proveedores.*.productos' => 'required|string|max:500',
            'proveedores.*.unidades' => 'nullable|integer|min:0',
            'proveedores.*.incoterm' => 'nullable|string|max:50',
            'proveedores.*.moneda' => 'nullable|string|max:3',
            'proveedores.*.costos' => 'nullable|array',
            'descuento' => 'nullable|numeric|min:0',
            'archivo' => 'nullable|array',
            'archivo.path' => 'required_with:archivo|string',
            'archivo.nombre_original' => 'nullable|string',
        ]);

        $cotizacion = $this->findResumen($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }
        if (($cotizacion->getAttribute('estado_resumen') ?: 'COTIZADO') !== 'COTIZADO') {
            return response()->json(['success' => false, 'message' => 'Solo se puede editar una cotización en estado COTIZADO'], 422);
        }

        $idContenedor = $request->filled('id_contenedor') ? (int) $request->id_contenedor : null;
        if ($idContenedor) {
            $contenedor = Contenedor::find($idContenedor);
            if (!$contenedor) {
                return response()->json(['success' => false, 'message' => 'Contenedor no encontrado'], 404);
            }
            if (!$this->contenedorPerteneceALaOrg($contenedor)) {
                return response()->json(['success' => false, 'message' => 'El consolidado no pertenece a tu organización'], 403);
            }
        }
        if (!$this->vendedorPerteneceALaOrg((int) $request->id_usuario)) {
            return response()->json(['success' => false, 'message' => 'El vendedor no pertenece a tu organización'], 403);
        }

        DB::beginTransaction();
        try {
            $cliente = $request->input('cliente');
            $totalesCosto = $this->sumarCostosRequest($request->input('proveedores', []));
            $orgId = (int) $cotizacion->getAttribute('organizacion_id') ?: $this->orgIdAutenticada();
            $clienteExistente = $this->resolverClienteDeLaOrg($cliente, $orgId);
            if (!empty($cliente['id']) && !$clienteExistente) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El cliente no pertenece a tu organización'], 403);
            }

            $archivo = $request->input('archivo');
            $cotizacionFill = [
                'id_contenedor' => $idContenedor,
                'id_usuario' => $request->id_usuario,
                'id_tipo_cliente' => $clienteExistente
                    ? $this->idTipoClientePorNombre('ANTIGUO', 1)
                    : $this->idTipoClientePorNombre('NUEVO', 1),
                'id_cliente' => $clienteExistente ? (int) $clienteExistente->id : null,
                'nombre' => $cliente['nombre'],
                'documento' => $cliente['documento'] ?? null,
                'correo' => $cliente['correo'] ?? null,
                'telefono' => $this->telefonoConPrefijoOrg(
                    $cliente['whatsapp'] ?? null,
                    $orgId,
                    isset($contenedor) ? $contenedor : $cotizacion->contenedor
                ),
                'tarifa_descuento' => $request->input('descuento', 0),
                'fob' => $totalesCosto['fob'],
                'monto' => $totalesCosto['logistica'],
                'impuestos' => $totalesCosto['impuesto'],
            ];
            if ($archivo && !empty($archivo['path'])) {
                $cotizacionFill['cotizacion_file_url'] = $archivo['path'];
            }
            $cotizacion->fill($cotizacionFill);
            $cotizacion->save();

            $this->sincronizarProveedoresResumen(
                $cotizacion,
                $request->input('proveedores', []),
                $idContenedor,
                $orgId,
                $archivo
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cotización actualizada', 'data' => ['id' => $cotizacion->getAttribute('id')]]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CotizacionResumenController@update: ' . $e->getMessage());
            $status = strpos($e->getMessage(), 'CBM IMO') !== false ? 422 : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Error al actualizar la cotización'], $status);
        }
    }

    /**
     * Copia en COTIZADO, sin contenedor y sin code_supplier.
     * POST /api/carga-consolidada/cotizacion-resumen/{id}/duplicar
     */
    public function duplicar($id)
    {
        $original = $this->findResumen($id);
        if (!$original) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        DB::beginTransaction();
        try {
            $orgId = (int) $original->getAttribute('organizacion_id') ?: $this->orgIdAutenticada();

            $nueva = $original->replicate();
            $nueva->id_contenedor = null;
            $nueva->uuid = Str::uuid()->toString();
            $nueva->fecha = now();
            $nueva->estado = 'PENDIENTE';
            $nueva->estado_cotizador = 'PENDIENTE';
            $nueva->estado_resumen = 'COTIZADO';
            $nueva->cod_contract = null;
            $nueva->fecha_confirmacion = null;
            $nueva->organizacion_id = $orgId;
            $nueva->save();

            $proveedores = CotizacionProveedor::query()
                ->where('id_cotizacion', $id)
                ->where('modo_cotizacion', 'resumen')
                ->with('resumen.costos')
                ->orderBy('id')
                ->get();

            $primerNuevoProveedorId = null;
            foreach ($proveedores as $prov) {
                $nuevoProv = $prov->replicate();
                $nuevoProv->id_cotizacion = $nueva->getAttribute('id');
                $nuevoProv->id_contenedor = null;
                $nuevoProv->code_supplier = null;
                $nuevoProv->estados_proveedor = 'WAIT';
                $nuevoProv->organizacion_id = $orgId;
                $nuevoProv->save();

                if ($primerNuevoProveedorId === null) {
                    $primerNuevoProveedorId = $nuevoProv->getAttribute('id');
                }

                $resumen = $prov->getRelation('resumen');
                if ($resumen) {
                    $nuevoResumen = $resumen->replicate();
                    $nuevoResumen->id_contenedor = null;
                    $nuevoResumen->id_cotizacion = $nueva->getAttribute('id');
                    $nuevoResumen->id_proveedor = $nuevoProv->getAttribute('id');
                    $nuevoResumen->organizacion_id = $orgId;
                    $nuevoResumen->save();

                    foreach ($resumen->getRelation('costos') ?: [] as $costo) {
                        $nuevoCosto = $costo->replicate();
                        $nuevoCosto->id_cotizacion_proveedor_resumen = $nuevoResumen->getAttribute('id');
                        $nuevoCosto->organizacion_id = $orgId;
                        $nuevoCosto->save();
                    }
                }
            }

            $archivo = CotizacionProveedorArchivoIa::query()
                ->where('id_cotizacion', $id)
                ->orderBy('id')
                ->first();
            if ($archivo && $primerNuevoProveedorId) {
                $nuevoArchivo = $archivo->replicate();
                $nuevoArchivo->id_contenedor = null;
                $nuevoArchivo->id_cotizacion = $nueva->getAttribute('id');
                $nuevoArchivo->id_proveedor = $primerNuevoProveedorId;
                $nuevoArchivo->organizacion_id = $orgId;
                $nuevoArchivo->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cotización duplicada',
                'data' => ['id' => $nueva->getAttribute('id')],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CotizacionResumenController@duplicar: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al duplicar la cotización'], 500);
        }
    }

    /**
     * Cambia el estado (COTIZADO/CONFIRMADO) de una cotización "resumen".
     * CONFIRMADO exige contenedor y genera code_supplier (sin pisar los ya asignados).
     * PUT /api/carga-consolidada/cotizacion-resumen/{id}/estado
     */
    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string|in:COTIZADO,CONFIRMADO',
        ]);

        $cotizacion = $this->findResumen($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $estadoResumen = $request->estado;
        if ($estadoResumen === 'CONFIRMADO' && !$cotizacion->getAttribute('id_contenedor')) {
            return response()->json([
                'success' => false,
                'message' => 'Asigna un consolidado antes de confirmar la cotización',
            ], 422);
        }

        $estadoCompartido = $estadoResumen === 'CONFIRMADO' ? 'CONFIRMADO' : 'PENDIENTE';
        $payload = [
            'estado_resumen' => $estadoResumen,
            'estado' => $estadoCompartido,
            'estado_cotizador' => $estadoCompartido,
        ];
        if ($estadoResumen === 'CONFIRMADO' && !$cotizacion->getAttribute('fecha_confirmacion')) {
            $payload['fecha_confirmacion'] = now();
        }

        $cotizacion->update($payload);

        if ($estadoResumen === 'CONFIRMADO') {
            $this->asignarCodeSuppliersResumen($cotizacion);
        }

        return response()->json(['success' => true, 'message' => 'Estado actualizado exitosamente']);
    }

    /**
     * DELETE /api/carga-consolidada/cotizacion-resumen/{id}
     */
    public function destroy($id)
    {
        $cotizacion = Cotizacion::find($id);
        if (!$cotizacion) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $esResumen = CotizacionProveedor::query()
            ->where('id_cotizacion', $id)
            ->where('modo_cotizacion', 'resumen')
            ->exists();
        if (!$esResumen) {
            return response()->json(['success' => false, 'message' => 'No es una cotización de resumen'], 422);
        }

        $cotizacion->delete();

        return response()->json(['success' => true, 'message' => 'Cotización eliminada']);
    }

    /**
     * Parte el CBM del formulario: cbm_total es el volumen completo y cbm_imo
     * la porción IMO. Se guarda cbm_normal = total - imo y cbm_imo aparte.
     *
     * @param  array  $prov
     * @return array{cbm_full: float, cbm_normal: float, cbm_imo: float}|null
     */
    private function partirCbmProveedor(array $prov)
    {
        $cbmFull = isset($prov['cbm_total']) ? (float) $prov['cbm_total'] : 0.0;
        $cbmImo = isset($prov['cbm_imo']) ? (float) $prov['cbm_imo'] : 0.0;
        if ($cbmImo < 0) {
            $cbmImo = 0.0;
        }
        if ($cbmImo - $cbmFull > 0.0001) {
            return null;
        }

        return [
            'cbm_full' => round($cbmFull, 4),
            'cbm_normal' => round($cbmFull - $cbmImo, 4),
            'cbm_imo' => round($cbmImo, 4),
        ];
    }

    private function orgIdAutenticada()
    {
        $user = auth()->user();
        return $user ? (int) $user->getAttribute('ID_Organizacion') : 0;
    }

    /**
     * @param array $cliente
     * @param int $orgId
     * @return Cliente|null
     */
    private function resolverClienteDeLaOrg(array $cliente, $orgId)
    {
        $idCliente = isset($cliente['id']) ? (int) $cliente['id'] : 0;
        if ($idCliente <= 0 || (int) $orgId <= 0) {
            return null;
        }

        return Cliente::query()
            ->where('id', $idCliente)
            ->where('organizacion_id', (int) $orgId)
            ->first();
    }

    /**
     * @param string $nombre
     * @param int $fallback
     * @return int
     */
    private function idTipoClientePorNombre($nombre, $fallback = 1)
    {
        $id = (int) DB::table('contenedor_consolidado_tipo_cliente')
            ->whereRaw('UPPER(TRIM(name)) = ?', [strtoupper((string) $nombre)])
            ->value('id');

        return $id > 0 ? $id : (int) $fallback;
    }

    private function esOrgAdmin()
    {
        return $this->orgIdAutenticada() === self::ID_ORGANIZACION_ADMIN;
    }

    /**
     * Org del creador (sesión). Solo org 1 puede mandar id_org / organizacion_id.
     */
    private function orgIdEfectiva(Request $request)
    {
        $authOrg = $this->orgIdAutenticada();
        if ($authOrg === self::ID_ORGANIZACION_ADMIN) {
            $fromRequest = $request->input('id_org', $request->input('organizacion_id'));
            if ($fromRequest !== null && $fromRequest !== '') {
                return (int) $fromRequest;
            }
        }
        return $authOrg;
    }

    private function telefonoConPrefijoOrg($whatsapp, $orgId, $contenedor = null)
    {
        $code = CountryPhoneHelper::codeForOrganizacionId($orgId)
            ?: CountryPhoneHelper::codeForContenedor($contenedor);
        $guardado = CountryPhoneHelper::ensureCountryCode($whatsapp, $code);

        return $guardado !== '' ? $guardado : null;
    }

    private function contenedorPerteneceALaOrg(Contenedor $contenedor)
    {
        if ($this->esOrgAdmin()) {
            return true;
        }
        return (int) $contenedor->getAttribute('organizacion_id') === $this->orgIdAutenticada();
    }

    private function vendedorPerteneceALaOrg($idUsuario)
    {
        if ($this->esOrgAdmin()) {
            return true;
        }
        $vendedor = Usuario::find($idUsuario);
        if (!$vendedor) {
            return false;
        }
        return (int) $vendedor->getAttribute('ID_Organizacion') === $this->orgIdAutenticada();
    }

    private function clasificarCosto($concepto, $valor, &$fob, &$logistica, &$impuesto)
    {
        $c = mb_strtolower((string) $concepto);
        if (strpos($c, 'mercader') !== false || strpos($c, 'fob') !== false) {
            $fob += $valor;
            return;
        }
        if (strpos($c, 'impuest') !== false || strpos($c, 'tribut') !== false || strpos($c, 'aduana') !== false) {
            $impuesto += $valor;
            return;
        }
        $logistica += $valor;
    }

    private function sumarCostosRequest(array $proveedores)
    {
        $fob = 0.0;
        $logistica = 0.0;
        $impuesto = 0.0;
        foreach ($proveedores as $prov) {
            foreach (($prov['costos'] ?? []) as $costo) {
                $this->clasificarCosto($costo['concepto'] ?? '', (float) ($costo['valor'] ?? 0), $fob, $logistica, $impuesto);
            }
        }
        return ['fob' => $fob, 'logistica' => $logistica, 'impuesto' => $impuesto];
    }

    private function sumarCostosProveedores($proveedores)
    {
        $fob = 0.0;
        $logistica = 0.0;
        $impuesto = 0.0;
        foreach ($proveedores as $p) {
            $resumen = $p->getRelation('resumen');
            if (!$resumen || !$resumen->getRelation('costos')) {
                continue;
            }
            foreach ($resumen->getRelation('costos') as $costo) {
                $this->clasificarCosto(
                    $costo->getAttribute('concepto'),
                    (float) $costo->getAttribute('valor'),
                    $fob,
                    $logistica,
                    $impuesto
                );
            }
        }
        return ['fob' => $fob, 'logistica' => $logistica, 'impuesto' => $impuesto];
    }

    private function urlArchivo($path)
    {
        if (!$path) {
            return null;
        }
        $cdn = $this->cdnStorageUrl($path);
        if ($cdn) {
            return $cdn;
        }
        try {
            return $this->objectStorage()->url($path);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function findResumen($id)
    {
        $cotizacion = Cotizacion::find($id);
        if (!$cotizacion) {
            return null;
        }
        $esResumen = CotizacionProveedor::query()
            ->where('id_cotizacion', $id)
            ->where('modo_cotizacion', 'resumen')
            ->exists();

        return $esResumen ? $cotizacion : null;
    }

    private function tipoDocumentoCliente($documento)
    {
        $doc = preg_replace('/\D/', '', (string) $documento);
        return strlen((string) $doc) >= 11 ? 'RUC' : 'ID';
    }

    /**
     * Crea/actualiza proveedores por id (nunca por orden) para no perder code_supplier.
     *
     * @param  Cotizacion  $cotizacion
     * @param  array  $proveedoresPayload
     * @param  int|null  $idContenedor
     * @param  int  $orgId
     * @param  array|null  $archivo
     */
    private function sincronizarProveedoresResumen($cotizacion, array $proveedoresPayload, $idContenedor, $orgId, $archivo = null)
    {
        $idCotizacion = (int) $cotizacion->getAttribute('id');
        $existentes = CotizacionProveedor::query()
            ->where('id_cotizacion', $idCotizacion)
            ->where('modo_cotizacion', 'resumen')
            ->orderBy('id')
            ->get()
            ->keyBy(function ($p) {
                return (int) $p->getAttribute('id');
            });

        $idsKeep = [];
        $totalCbmFull = 0.0;
        $totalCbmImo = 0.0;
        $primerProveedorId = null;

        foreach ($proveedoresPayload as $idx => $prov) {
            $partido = $this->partirCbmProveedor($prov);
            if ($partido === null) {
                throw new \Exception('El CBM IMO del proveedor #' . ($idx + 1) . ' no puede ser mayor al CBM total.');
            }
            $totalCbmFull += $partido['cbm_full'];
            $totalCbmImo += $partido['cbm_imo'];

            $idExistente = isset($prov['id']) ? (int) $prov['id'] : 0;
            $proveedor = ($idExistente && $existentes->has($idExistente))
                ? $existentes->get($idExistente)
                : new CotizacionProveedor();

            $proveedor->fill([
                'id_cotizacion' => $idCotizacion,
                'id_contenedor' => $idContenedor,
                'modo_cotizacion' => 'resumen',
                'cbm_total' => $partido['cbm_normal'],
                'cbm_imo' => $partido['cbm_imo'],
                'peso' => $prov['peso_total'] ?? null,
                'qty_box' => $prov['qty_cajas'] ?? null,
                'products' => $prov['productos'],
            ]);
            if (!$proveedor->exists) {
                $proveedor->estados_proveedor = 'WAIT';
                $proveedor->tipo_rotulado = 'pendiente';
                $proveedor->organizacion_id = $orgId;
            }
            $proveedor->save();
            $idsKeep[] = (int) $proveedor->getAttribute('id');
            if ($primerProveedorId === null) {
                $primerProveedorId = $proveedor->getAttribute('id');
            }

            $costos = $prov['costos'] ?? [];
            $inversionTotal = collect($costos)->sum(function ($c) {
                return (float) $c['valor'];
            });
            $unidades = $prov['unidades'] ?? null;
            $costoUnitarioEstimado = ($unidades && (int) $unidades > 0)
                ? round($inversionTotal / (int) $unidades, 4)
                : null;

            $resumen = CotizacionProveedorResumen::query()
                ->where('id_proveedor', $proveedor->getAttribute('id'))
                ->first();
            if (!$resumen) {
                $resumen = new CotizacionProveedorResumen();
                $resumen->organizacion_id = $orgId;
            }
            $resumen->fill([
                'id_contenedor' => $idContenedor,
                'id_cotizacion' => $idCotizacion,
                'id_proveedor' => $proveedor->getAttribute('id'),
                'producto' => $prov['productos'],
                'volumen_cbm' => $partido['cbm_full'],
                'unidades' => $unidades,
                'incoterm' => $prov['incoterm'] ?? null,
                'costo_unitario_estimado' => $costoUnitarioEstimado,
                'inversion_total' => count($costos) > 0 ? $inversionTotal : null,
                'moneda' => $prov['moneda'] ?? 'USD',
            ]);
            $resumen->save();

            CotizacionProveedorResumenCosto::query()
                ->where('id_cotizacion_proveedor_resumen', $resumen->getAttribute('id'))
                ->delete();
            foreach ($costos as $orden => $costo) {
                $linea = new CotizacionProveedorResumenCosto();
                $linea->organizacion_id = $orgId;
                $linea->fill([
                    'id_cotizacion_proveedor_resumen' => $resumen->getAttribute('id'),
                    'concepto' => $costo['concepto'],
                    'orden' => $orden,
                    'valor' => $costo['valor'],
                ]);
                $linea->save();
            }
        }

        if ($primerProveedorId) {
            CotizacionProveedorArchivoIa::query()
                ->where('id_cotizacion', $idCotizacion)
                ->update([
                    'id_proveedor' => $primerProveedorId,
                    'id_contenedor' => $idContenedor,
                ]);
        }

        foreach ($existentes as $existente) {
            if (!in_array((int) $existente->getAttribute('id'), $idsKeep, true)) {
                $existente->delete();
            }
        }

        $cotizacion->update([
            'volumen' => $totalCbmFull,
            'es_imo' => $totalCbmImo > 0,
        ]);

        if ($archivo && !empty($archivo['path']) && $primerProveedorId) {
            $registro = CotizacionProveedorArchivoIa::query()
                ->where('id_cotizacion', $idCotizacion)
                ->orderBy('id')
                ->first();
            if (!$registro) {
                $registro = new CotizacionProveedorArchivoIa();
                $registro->organizacion_id = $orgId;
            }
            $registro->fill([
                'id_contenedor' => $idContenedor,
                'id_cotizacion' => $idCotizacion,
                'id_proveedor' => $primerProveedorId,
                'archivo_path' => $archivo['path'],
                'archivo_nombre_original' => $archivo['nombre_original'] ?? null,
                'estado' => 'procesado',
            ]);
            $registro->save();
        } else {
            CotizacionProveedorArchivoIa::query()
                ->where('id_cotizacion', $idCotizacion)
                ->update(['id_contenedor' => $idContenedor]);
        }
    }

    /**
     * Asigna code_supplier solo a proveedores que aún no tienen.
     * No reordena ni pisa códigos existentes (re-confirmación / edición previa).
     */
    private function asignarCodeSuppliersResumen(Cotizacion $cotizacion)
    {
        $org = Organizacion::find($cotizacion->getAttribute('organizacion_id'));
        $nombreOrg = $org ? (string) $org->getAttribute('No_Organizacion') : '';
        $contenedor = $cotizacion->getAttribute('id_contenedor')
            ? Contenedor::find($cotizacion->getAttribute('id_contenedor'))
            : null;
        $carga = $contenedor ? $contenedor->getAttribute('carga') : '';
        $nombreCliente = (string) $cotizacion->getAttribute('nombre');
        $base = CodeSupplierHelper::basePrefixWithOrg($nombreOrg, $nombreCliente, $carga);

        $proveedores = CotizacionProveedor::query()
            ->where('id_cotizacion', $cotizacion->getAttribute('id'))
            ->where('modo_cotizacion', 'resumen')
            ->orderBy('id')
            ->get();

        $existentes = $proveedores->pluck('code_supplier')->all();
        $next = CodeSupplierHelper::maxSuffixForBase($base, $existentes) + 1;

        foreach ($proveedores as $proveedor) {
            $actual = trim((string) $proveedor->getAttribute('code_supplier'));
            if ($actual !== '') {
                continue;
            }
            $proveedor->code_supplier = CodeSupplierHelper::generateWithOrgPrefix(
                $nombreOrg,
                $nombreCliente,
                $carga,
                $next
            );
            $proveedor->save();
            $next++;
        }
    }
}
