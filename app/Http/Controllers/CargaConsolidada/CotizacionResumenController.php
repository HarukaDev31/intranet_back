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
use App\Models\Pais;
use App\Models\PaisFlag;
use App\Models\Usuario;
use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use App\Services\CargaConsolidada\CustomersHeadersService;
use App\Services\CargaConsolidada\GeminiService;
use App\Support\CargaConsolidada\ResumenClienteCampos;
use App\Support\CargaConsolidada\ResumenCostoClasificador;
use App\Support\Phone\CountryPhoneHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Flujo "resumen": organizaciones sin items (calculadora), que suben un
 * documento y lo leen con IA en vez de cargar producto por producto.
 *
 * El PDF/Excel se guarda en la carpeta final de la org
 * (assets/images/agentecompra/{organizacion_id}/). El registro
 * CotizacionProveedorArchivoIa se crea al finalizar el wizard, cuando ya
 * existen los ids reales de contenedor/cotizacion/proveedor.
 */
class CotizacionResumenController extends Controller
{
    use FileTrait;

    private const GEMINI_PDF_MIMES = [
        'application/pdf',
    ];

    private const GEMINI_SHEET_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'application/csv',
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

        try {

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Archivo inválido'], 422);
        }

        $orgId = $this->orgIdAutenticada();

        $originalName = $file->getClientOriginalName();
        $mimeType = $this->mimeParaExtraccion($file);
        $localPath = $file->getRealPath();

        $data = null;
        $extractedByAi = false;
        $error = null;
        $esPdf = in_array($mimeType, self::GEMINI_PDF_MIMES, true);
        $esHoja = in_array($mimeType, self::GEMINI_SHEET_MIMES, true);

        if ($localPath && is_file($localPath) && ($esPdf || $esHoja)) {
            $gemini = new GeminiService();
            if ($esHoja) {
                $texto = $this->hojaCalculoATexto($localPath);
                $result = $texto !== ''
                    ? $gemini->extractFromCotizacionResumenText($texto)
                    : ['success' => false, 'error' => 'La hoja está vacía', 'data' => null];
            } else {
                $result = $gemini->extractFromCotizacionResumen($localPath, $mimeType);
            }

            if (!empty($result['success'])) {
                $data = $result['data'];
                $extractedByAi = true;
            } else {
                $error = isset($result['error']) ? $result['error'] : 'No se pudo leer el documento';
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

        $storedPath = $this->objectStorage()->storeUploadedFile(
            $file,
            $this->directorioArchivoFinal($orgId),
            Str::uuid()->toString() . '-' . $originalName
        );

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
        } catch (\Exception $e) {
            Log::error('CotizacionResumenController@extraerDocumento: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'extracted_by_ai' => false,
                'message' => 'No se pudo procesar el archivo. Intenta de nuevo o completa los datos a mano.',
                'data' => null,
            ], 500);
        }
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
                $nombre = ResumenClienteCampos::textoONulo($c->getAttribute('nombre')) ?: '';
                return [
                    'id' => (int) $c->getAttribute('id'),
                    'nombre' => $nombre !== '' ? $nombre : null,
                    'documento' => ResumenClienteCampos::documentoIdentidad($c->getAttribute('documento'), $tel),
                    'correo' => ResumenClienteCampos::correo($c->getAttribute('correo')),
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

                $paginator = $query->orderByDesc('fecha')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page);

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
                $isd = (float) ($c->getAttribute('isd') ?? 0);
                if ($fob <= 0 && $logistica <= 0 && $impuesto <= 0 && $isd <= 0) {
                    $fob = $totalesCosto['fob'];
                    $logistica = $totalesCosto['logistica'];
                    $impuesto = $totalesCosto['impuesto'];
                    $isd = $totalesCosto['isd'];
                } else {
                    if ($logistica <= 0) {
                        $logistica = $totalesCosto['logistica'];
                    }
                    if ($isd <= 0) {
                        $isd = $totalesCosto['isd'];
                    }
                }
                $totalInversion = $proveedores->sum(fn ($p) => (float) (optional($p->getRelation('resumen'))->getAttribute('inversion_total') ?? 0));
                $contenedor = $contenedores->get($c->getAttribute('id_contenedor'));
                $usuario = $usuarios->get($c->getAttribute('id_usuario'));
                $archivo = optional($archivosPorCotizacion->get($c->getAttribute('id'), collect())->first());
                $carga = optional($contenedor)->getAttribute('carga');

                $clienteVista = $this->clienteResumenParaVista($c);

                return [
                    'id' => $c->getAttribute('id'),
                    'fecha' => $c->getAttribute('fecha'),
                    'nombre' => $clienteVista['nombre'],
                    'documento' => $clienteVista['documento'],
                    'telefono' => $clienteVista['whatsapp'],
                    'correo' => $clienteVista['correo'],
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
                    'isd' => $isd,
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

            $headers = (new CustomersHeadersService())->buildForResumen(
                $orgEfectiva > 0 ? [$orgEfectiva] : [],
                (string) $request->input('search', ''),
                $request->input('estado_china', 'todos')
            );

            return response()->json([
                'success' => true,
                'data' => $data->values(),
                'headers' => $headers,
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
            'qty_proveedores' => 'nullable|integer|min:1',
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
            $cliente = ResumenClienteCampos::sanitizar((array) $request->input('cliente', []), true);
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
            if ($archivo && !empty($archivo['path'])) {
                $archivo['path'] = $this->persistirArchivoCotizacion($archivo['path'], $orgId);
            }
            $cotizacionUpdate = [
                'volumen' => $totalCbmFull,
                'es_imo' => $totalCbmImo > 0,
                'fob' => $totalesCosto['fob'],
                'isd' => $totalesCosto['isd'],
                'monto' => $totalesCosto['logistica'],
                'impuestos' => $totalesCosto['impuesto'],
                'tarifa' => $this->tarifaDesdeLogistica($totalesCosto['logistica'], $totalCbmFull),
                'tarifa_descuento' => $request->input('descuento', 0),
                'qty_proveedores' => $this->qtyProveedoresDesdeRequest($request),
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
                'qty_proveedores' => $this->qtyProveedoresGuardado($cotizacion, $proveedores->count()),
                'tarifa' => (float) ($cotizacion->getAttribute('tarifa') ?? 0),
                'fob' => (float) ($cotizacion->getAttribute('fob') ?? 0),
                'isd' => (float) ($cotizacion->getAttribute('isd') ?? 0),
                'logistica' => (float) ($cotizacion->getAttribute('monto') ?? 0),
                'impuesto' => (float) ($cotizacion->getAttribute('impuestos') ?? 0),
                'cliente' => $this->clienteResumenParaVista($cotizacion, true),
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
            'qty_proveedores' => 'nullable|integer|min:1',
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
            $cliente = ResumenClienteCampos::sanitizar((array) $request->input('cliente', []), true);
            $totalesCosto = $this->sumarCostosRequest($request->input('proveedores', []));
            $orgId = (int) $cotizacion->getAttribute('organizacion_id') ?: $this->orgIdAutenticada();
            $clienteExistente = $this->resolverClienteDeLaOrg($cliente, $orgId);
            if (!empty($cliente['id']) && !$clienteExistente) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El cliente no pertenece a tu organización'], 403);
            }

            $archivo = $request->input('archivo');
            if ($archivo && !empty($archivo['path'])) {
                $archivo['path'] = $this->persistirArchivoCotizacion($archivo['path'], $orgId);
            }
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
                'isd' => $totalesCosto['isd'],
                'monto' => $totalesCosto['logistica'],
                'impuestos' => $totalesCosto['impuesto'],
                'tarifa' => $this->tarifaDesdeLogistica(
                    $totalesCosto['logistica'],
                    $this->cbmFullDeProveedores($request->input('proveedores', []))
                ),
                'qty_proveedores' => $this->qtyProveedoresDesdeRequest($request),
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
            $nueva->cotizacion_file_url = $this->persistirArchivoCotizacion(
                $nueva->getAttribute('cotizacion_file_url'),
                $orgId
            );
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
                $nuevoArchivo->archivo_path = $this->persistirArchivoCotizacion(
                    $nuevoArchivo->getAttribute('archivo_path'),
                    $orgId
                ) ?: $nuevoArchivo->getAttribute('archivo_path');
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
     * Parte la cotización: clona la cabecera en otro consolidado de la misma org/país
     * y mueve un subconjunto de proveedores (no todos). Sin pagos ni Excel Drive.
     * POST /api/carga-consolidada/cotizacion-resumen/{id}/partir
     */
    public function partir(Request $request, $id)
    {
        $request->validate([
            'id_contenedor' => 'required|integer',
            'proveedores' => 'required|array|min:1',
            'proveedores.*' => 'integer',
        ]);

        $origen = $this->findResumen($id);
        if (!$origen) {
            return response()->json(['success' => false, 'message' => 'Cotización no encontrada'], 404);
        }

        $idDestino = (int) $request->input('id_contenedor');
        $idOrigenContenedor = (int) $origen->getAttribute('id_contenedor');
        if ($idDestino <= 0 || $idDestino === $idOrigenContenedor) {
            return response()->json(['success' => false, 'message' => 'Elige un consolidado distinto'], 422);
        }

        $contenedorDestino = Contenedor::find($idDestino);
        if (!$contenedorDestino) {
            return response()->json(['success' => false, 'message' => 'Consolidado no encontrado'], 404);
        }
        if (!$this->contenedorPerteneceALaOrg($contenedorDestino)) {
            return response()->json(['success' => false, 'message' => 'El consolidado no pertenece a tu organización'], 403);
        }

        $contenedorOrigen = $idOrigenContenedor ? Contenedor::find($idOrigenContenedor) : null;
        $paisOrigen = $contenedorOrigen ? (int) $contenedorOrigen->getAttribute('id_pais') : 0;
        $paisDestino = (int) $contenedorDestino->getAttribute('id_pais');
        if ($paisOrigen > 0 && $paisDestino > 0 && $paisOrigen !== $paisDestino) {
            return response()->json(['success' => false, 'message' => 'Solo puedes partir a un consolidado del mismo país'], 422);
        }

        $idsMover = array_values(array_unique(array_map('intval', $request->input('proveedores', []))));
        $proveedores = CotizacionProveedor::query()
            ->where('id_cotizacion', $id)
            ->where('modo_cotizacion', 'resumen')
            ->orderBy('id')
            ->get();
        if ($proveedores->count() < 2) {
            return response()->json(['success' => false, 'message' => 'Se necesitan al menos dos proveedores para partir'], 422);
        }

        $idsActuales = $proveedores->pluck('id')->map(function ($v) {
            return (int) $v;
        })->all();
        foreach ($idsMover as $idProv) {
            if (!in_array($idProv, $idsActuales, true)) {
                return response()->json(['success' => false, 'message' => 'Hay proveedores que no pertenecen a esta cotización'], 422);
            }
        }
        if (count($idsMover) >= count($idsActuales)) {
            return response()->json(['success' => false, 'message' => 'Debe quedar al menos un proveedor en la cotización original'], 422);
        }

        $orgId = (int) $contenedorDestino->getAttribute('organizacion_id') ?: $this->orgIdAutenticada();

        DB::beginTransaction();
        try {
            $nueva = $origen->replicate();
            $nueva->uuid = Str::uuid()->toString();
            $nueva->id_contenedor = $idDestino;
            $nueva->id_contenedor_pago = null;
            $nueva->id_contenedor_destino = null;
            $nueva->organizacion_id = $orgId;
            $nueva->save();

            foreach ($idsMover as $idProv) {
                $proveedor = $proveedores->first(function ($p) use ($idProv) {
                    return (int) $p->getAttribute('id') === $idProv;
                });
                if (!$proveedor) {
                    continue;
                }
                $proveedor->id_cotizacion = $nueva->getAttribute('id');
                $proveedor->id_contenedor = $idDestino;
                $proveedor->id_contenedor_pago = null;
                $proveedor->organizacion_id = $orgId;
                $proveedor->save();

                $resumen = CotizacionProveedorResumen::query()->where('id_proveedor', $idProv)->first();
                if ($resumen) {
                    $resumen->id_cotizacion = $nueva->getAttribute('id');
                    $resumen->id_contenedor = $idDestino;
                    $resumen->organizacion_id = $orgId;
                    $resumen->save();
                }

                CotizacionProveedorArchivoIa::query()
                    ->where('id_proveedor', $idProv)
                    ->get()
                    ->each(function ($archivo) use ($nueva, $idDestino) {
                        $archivo->id_cotizacion = $nueva->getAttribute('id');
                        $archivo->id_contenedor = $idDestino;
                        $archivo->save();
                    });
            }

            $this->refrescarTotalesResumen($origen);
            $this->refrescarTotalesResumen($nueva);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cotización partida',
                'data' => [
                    'id' => $origen->getAttribute('id'),
                    'id_nueva' => $nueva->getAttribute('id'),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CotizacionResumenController@partir: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al partir la cotización'], 500);
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

        $estadoResumen = (string) ($cotizacion->getAttribute('estado_resumen') ?: 'COTIZADO');
        if ($estadoResumen === 'CONFIRMADO') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede eliminar una cotización en estado COTIZADO.',
            ], 422);
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

    /**
     * Tarifa = logística / CBM. Se persiste en `tarifa`; el front no la calcula.
     *
     * @param float $logistica
     * @param float $cbm
     * @return float
     */
    /**
     * Qty proveedores del wizard: el valor enviado, o el conteo de filas si no vino.
     *
     * @param Request $request
     * @return int
     */
    private function qtyProveedoresDesdeRequest(Request $request)
    {
        $qty = $request->input('qty_proveedores');
        if ($qty !== null && $qty !== '') {
            return max(1, (int) $qty);
        }
        $proveedores = $request->input('proveedores', []);

        return max(1, is_array($proveedores) ? count($proveedores) : 0);
    }

    /**
     * Qty persistido; si es cotización vieja sin columna, usa el conteo de proveedores.
     *
     * @param Cotizacion $cotizacion
     * @param int $conteoProveedores
     * @return int
     */
    private function qtyProveedoresGuardado(Cotizacion $cotizacion, $conteoProveedores)
    {
        $qty = (int) ($cotizacion->getAttribute('qty_proveedores') ?? 0);
        if ($qty >= 1) {
            return $qty;
        }

        return max(0, (int) $conteoProveedores);
    }

    /**
     * Recalcula CBM, costos y qty_proveedores después de partir.
     *
     * @param Cotizacion $cotizacion
     * @return void
     */
    private function refrescarTotalesResumen(Cotizacion $cotizacion)
    {
        $proveedores = CotizacionProveedor::query()
            ->where('id_cotizacion', $cotizacion->getAttribute('id'))
            ->where('modo_cotizacion', 'resumen')
            ->with('resumen.costos')
            ->get();

        $totales = $this->sumarCostosProveedores($proveedores);
        $totalCbm = 0.0;
        $totalCbmImo = 0.0;
        foreach ($proveedores as $p) {
            $cbmNormal = (float) ($p->getAttribute('cbm_total') ?? 0);
            $cbmImo = (float) ($p->getAttribute('cbm_imo') ?? 0);
            $totalCbm += $cbmNormal + $cbmImo;
            $totalCbmImo += $cbmImo;
        }

        $payload = [
            'volumen' => $totalCbm,
            'es_imo' => $totalCbmImo > 0,
            'fob' => $totales['fob'],
            'isd' => $totales['isd'],
            'monto' => $totales['logistica'],
            'impuestos' => $totales['impuesto'],
            'tarifa' => $this->tarifaDesdeLogistica($totales['logistica'], $totalCbm),
            'qty_proveedores' => $proveedores->count(),
        ];
        $cotizacion->fill($payload);
        $cotizacion->save();
    }

    private function tarifaDesdeLogistica($logistica, $cbm)
    {
        $cbm = (float) $cbm;
        if ($cbm <= 0) {
            return 0.0;
        }

        return round(((float) $logistica) / $cbm, 2);
    }

    /**
     * @param array<int, array<string, mixed>> $proveedores
     * @return float
     */
    private function cbmFullDeProveedores(array $proveedores)
    {
        $total = 0.0;
        foreach ($proveedores as $prov) {
            $partido = $this->partirCbmProveedor($prov);
            if ($partido) {
                $total += $partido['cbm_full'];
            }
        }

        return $total;
    }

    /**
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    private function mimeParaExtraccion($file)
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $porExtension = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
        ];
        if (isset($porExtension[$ext])) {
            return $porExtension[$ext];
        }

        return (string) $file->getMimeType();
    }

    /**
     * @param string $path
     * @return string
     */
    private function hojaCalculoATexto($path)
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (\Exception $e) {
            Log::warning('CotizacionResumenController: no se pudo leer la hoja', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }

        $lines = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $lines[] = 'Hoja: ' . $sheet->getTitle();
            $highestRow = min((int) $sheet->getHighestDataRow(), 200);
            $highestCol = $sheet->getHighestDataColumn();
            $highestColIndex = min(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol),
                30
            );
            for ($row = 1; $row <= $highestRow; $row++) {
                $cells = [];
                for ($col = 1; $col <= $highestColIndex; $col++) {
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                    $cells[] = trim((string) $sheet->getCell($coord)->getFormattedValue());
                }
                $line = implode("\t", $cells);
                if (trim($line) !== '') {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", $lines);
    }

    private function clasificarCosto($concepto, $valor, &$fob, &$logistica, &$impuesto, &$isd)
    {
        $tipo = ResumenCostoClasificador::tipo($concepto);
        if ($tipo === ResumenCostoClasificador::ISD) {
            $isd += $valor;
            return;
        }
        if ($tipo === ResumenCostoClasificador::FOB) {
            $fob += $valor;
            return;
        }
        if ($tipo === ResumenCostoClasificador::IMPUESTO) {
            $impuesto += $valor;
            return;
        }
        if ($tipo === ResumenCostoClasificador::LOGISTICA) {
            $logistica += $valor;
        }
    }

    private function sumarCostosRequest(array $proveedores)
    {
        $fob = 0.0;
        $logistica = 0.0;
        $impuesto = 0.0;
        $isd = 0.0;
        foreach ($proveedores as $prov) {
            foreach (($prov['costos'] ?? []) as $costo) {
                $this->clasificarCosto($costo['concepto'] ?? '', (float) ($costo['valor'] ?? 0), $fob, $logistica, $impuesto, $isd);
            }
        }
        return ['fob' => $fob, 'logistica' => $logistica, 'impuesto' => $impuesto, 'isd' => $isd];
    }

    private function sumarCostosProveedores($proveedores)
    {
        $fob = 0.0;
        $logistica = 0.0;
        $impuesto = 0.0;
        $isd = 0.0;
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
                    $impuesto,
                    $isd
                );
            }
        }
        return ['fob' => $fob, 'logistica' => $logistica, 'impuesto' => $impuesto, 'isd' => $isd];
    }

    /**
     * Carpeta definitiva de archivos de cotización (misma que el cotizador).
     *
     * @param int $orgId
     * @return string
     */
    private function directorioArchivoFinal($orgId)
    {
        return 'assets/images/agentecompra/' . (int) $orgId;
    }

    /**
     * Si el archivo quedó en staging (wizard previo), lo copia a la carpeta final
     * de la org y borra el staging. Si ya está en destino, no hace nada.
     *
     * @param string|null $path
     * @param int $orgId
     * @return string|null
     */
    private function persistirArchivoCotizacion($path, $orgId)
    {
        $storage = $this->objectStorage();
        $path = $storage->normalizeRelativePath($path);
        if ($path === null || $path === '') {
            return null;
        }

        $orgId = (int) $orgId;
        $stagingPrefix = 'cargaconsolidada/cotizacion-resumen/staging/' . $orgId . '/';
        if (strpos($path, $stagingPrefix) !== 0) {
            return $path;
        }

        $destino = $this->directorioArchivoFinal($orgId) . '/' . basename($path);
        if ($destino === $path) {
            return $path;
        }

        try {
            $local = $storage->localPath($path);
            $contents = file_get_contents($local);
            if ($contents === false) {
                Log::warning('CotizacionResumenController: no se pudo leer el archivo de staging', array(
                    'path' => $path,
                ));
                return $path;
            }
            $storage->putContents($destino, $contents);
            $storage->delete($path);
            return $destino;
        } catch (\Exception $e) {
            Log::warning('CotizacionResumenController: no se pudo mover staging a carpeta final', array(
                'path' => $path,
                'error' => $e->getMessage(),
            ));
            return $path;
        }
    }

    private function urlArchivo($path)
    {
        if (!$path) {
            return null;
        }
        $path = (string) $path;
        try {
            $url = $this->objectStorage()->url($path);
            if ($url) {
                return $url;
            }
        } catch (\Exception $e) {
            Log::warning('CotizacionResumenController@urlArchivo: ' . $e->getMessage(), array(
                'path' => $path,
            ));
        }

        return $this->cdnStorageUrl($path);
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

    /**
     * @param  Cotizacion  $cotizacion
     * @param  bool  $conId
     * @return array<string, mixed>
     */
    private function clienteResumenParaVista($cotizacion, $conId = false)
    {
        $s = ResumenClienteCampos::sanitizar([
            'nombre' => $cotizacion->getAttribute('nombre'),
            'documento' => $cotizacion->getAttribute('documento'),
            'whatsapp' => $cotizacion->getAttribute('telefono'),
            'correo' => $cotizacion->getAttribute('correo'),
        ]);
        if ($conId) {
            $s['id'] = $cotizacion->getAttribute('id_cliente');
        }
        return $s;
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
            if (in_array((int) $existente->getAttribute('id'), $idsKeep, true)) {
                continue;
            }
            $this->eliminarProveedorResumenSiNoTieneCodigo($existente);
        }

        $totalesCosto = $this->sumarCostosRequest($proveedoresPayload);
        $cotizacion->update([
            'volumen' => $totalCbmFull,
            'es_imo' => $totalCbmImo > 0,
            'fob' => $totalesCosto['fob'],
            'isd' => $totalesCosto['isd'],
            'monto' => $totalesCosto['logistica'],
            'impuestos' => $totalesCosto['impuesto'],
            'tarifa' => $this->tarifaDesdeLogistica($totalesCosto['logistica'], $totalCbmFull),
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
     * Al reemplazar el archivo en COTIZADO se borran proveedores/ítems sin
     * code_supplier. Los que ya tienen código no se tocan.
     *
     * @param CotizacionProveedor $proveedor
     */
    private function eliminarProveedorResumenSiNoTieneCodigo($proveedor)
    {
        $code = trim((string) $proveedor->getAttribute('code_supplier'));
        if ($code !== '') {
            return;
        }

        $id = (int) $proveedor->getAttribute('id');
        $resumenes = CotizacionProveedorResumen::query()->where('id_proveedor', $id)->get();
        foreach ($resumenes as $resumen) {
            CotizacionProveedorResumenCosto::query()
                ->where('id_cotizacion_proveedor_resumen', $resumen->getAttribute('id'))
                ->delete();
            $resumen->delete();
        }
        DB::table('contenedor_consolidado_cotizacion_proveedores_items')
            ->where('id_proveedor', $id)
            ->delete();
        $proveedor->delete();
    }

    /**
     * Asigna code_supplier solo a proveedores que aún no tienen.
     * No reordena ni pisa códigos existentes (re-confirmación / edición previa).
     */
    private function asignarCodeSuppliersResumen(Cotizacion $cotizacion)
    {
        $orgId = (int) $cotizacion->getAttribute('organizacion_id');
        $contenedor = $cotizacion->getAttribute('id_contenedor')
            ? Contenedor::find($cotizacion->getAttribute('id_contenedor'))
            : null;
        $carga = $contenedor ? $contenedor->getAttribute('carga') : '';
        $nombreCliente = (string) $cotizacion->getAttribute('nombre');
        list($nombrePais, $iso2) = $this->paisCodeSupplierDesdeContenedor($contenedor);
        $base = CodeSupplierHelper::basePrefixWithPais($nombrePais, $orgId, $nombreCliente, $carga, $iso2);

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
            $proveedor->code_supplier = CodeSupplierHelper::generateWithPaisPrefix(
                $nombrePais,
                $orgId,
                $nombreCliente,
                $carga,
                $next,
                $iso2
            );
            $proveedor->save();
            $next++;
        }
    }

    /**
     * Nombre e ISO-2 del país del consolidado (id_pais → pais_flags / pais).
     *
     * @param Contenedor|null $contenedor
     * @return array{0: string, 1: string}
     */
    private function paisCodeSupplierDesdeContenedor($contenedor)
    {
        if (!$contenedor) {
            return ['', ''];
        }
        $idPais = (int) $contenedor->getAttribute('id_pais');
        if ($idPais <= 0) {
            return ['', ''];
        }

        $nombre = '';
        $iso2 = '';
        $flag = PaisFlag::query()->where('id_pais', $idPais)->first();
        if ($flag) {
            $nombre = (string) $flag->getAttribute('nombre');
            $iso2 = (string) $flag->getAttribute('iso2');
        }
        if ($nombre === '') {
            $pais = Pais::find($idPais);
            $nombre = $pais ? (string) $pais->getAttribute('No_Pais') : '';
        }

        return [$nombre, $iso2];
    }
}
