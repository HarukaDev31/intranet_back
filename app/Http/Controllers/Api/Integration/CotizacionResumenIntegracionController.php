<?php

namespace App\Http\Controllers\Api\Integration;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Http\Controllers\CargaConsolidada\CotizacionResumenController;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * API de integración: cotizaciones resumen de UNA organización (la del token).
 *
 * - Crear: JSON estructurado (sin IA) + PDF de respaldo obligatorio (queda como archivo de la
 *   cotización para mapear de dónde salió la información). Reutiliza la lógica del store del panel,
 *   actuando como el usuario BOT de la organización.
 * - Listar: solo lectura, siempre filtrado por la organización del token.
 */
class CotizacionResumenIntegracionController extends Controller
{
    /** Claves permitidas del payload (cualquier otra se rechaza). */
    private const CLAVES_PAYLOAD = ['id_contenedor', 'cliente', 'proveedores', 'descuento', 'qty_proveedores'];
    private const CLAVES_CLIENTE = ['id', 'nombre', 'tipo_documento', 'documento', 'whatsapp', 'correo'];
    private const CLAVES_PROVEEDOR = ['cbm_total', 'cbm_imo', 'peso_total', 'qty_cajas', 'productos', 'unidades', 'incoterm', 'moneda', 'costos'];
    private const CLAVES_COSTO = ['concepto', 'valor', 'valor_bs', 'tasa_cambio'];

    private const MAX_PROVEEDORES = 50;
    private const MAX_COSTOS = 50;

    /** Marcadores de PDF activo / archivos embebidos que no se aceptan. */
    private const PDF_PATRON_PELIGROSO = '/\/(JavaScript|JS|Launch|EmbeddedFile|RichMedia|SubmitForm|ImportData)\b/i';

    // -----------------------------------------------------------------------
    // GET /api/integration/v1/cotizaciones-resumen
    // -----------------------------------------------------------------------
    public function listar(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $orgId = (int) $client->organizacion_id;

        $v = Validator::make($request->query(), [
            'estado' => 'nullable|string|in:COTIZADO,CONFIRMADO',
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d',
            'id_contenedor' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        if ($v->fails()) {
            return $this->invalido($v->errors()->toArray());
        }

        $query = Cotizacion::query()
            ->where('organizacion_id', $orgId)
            ->whereIn('id', function ($sub) use ($orgId) {
                $sub->select('id_cotizacion')
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->where('organizacion_id', $orgId)
                    ->where('modo_cotizacion', 'resumen');
            });

        if ($request->filled('estado')) {
            $query->where('estado_resumen', $request->query('estado'));
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->query('fecha_inicio'));
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->query('fecha_fin'));
        }
        if ($request->filled('id_contenedor')) {
            $query->where('id_contenedor', (int) $request->query('id_contenedor'));
        }
        if ($request->filled('search')) {
            $like = '%' . addcslashes((string) $request->query('search'), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('nombre', 'like', $like)
                    ->orWhere('documento', 'like', $like)
                    ->orWhere('telefono', 'like', $like);
            });
        }

        $paginator = $query->orderByDesc('id')->paginate((int) $request->query('per_page', 20));
        $ids = collect($paginator->items())->pluck('id')->all();

        $proveedores = CotizacionProveedor::query()
            ->where('organizacion_id', $orgId)
            ->where('modo_cotizacion', 'resumen')
            ->whereIn('id_cotizacion', $ids)
            ->with('resumen.costos')
            ->orderBy('id')
            ->get()
            ->groupBy('id_cotizacion');

        $conBs = Schema::hasColumn('cotizacion_proveedor_resumen_costo', 'valor_bs');

        $data = collect($paginator->items())->map(function ($c) use ($proveedores, $conBs) {
            return [
                'id' => $c->id,
                'fecha' => $c->fecha,
                'estado' => $c->estado_resumen ?: 'COTIZADO',
                'id_contenedor' => $c->id_contenedor,
                'cliente' => [
                    'nombre' => $c->nombre,
                    'documento' => $c->documento,
                    'whatsapp' => $c->telefono,
                    'correo' => $c->correo,
                ],
                'volumen' => $c->volumen,
                'fob' => $c->fob,
                'logistica' => $c->monto,
                'impuestos' => $c->impuestos,
                'proveedores' => $proveedores->get($c->id, collect())->map(function ($p) use ($conBs) {
                    $resumen = $p->getRelation('resumen');
                    $costos = $resumen && $resumen->getRelation('costos') ? $resumen->getRelation('costos') : collect();

                    return [
                        'id' => $p->id,
                        'code_supplier' => $p->code_supplier,
                        'productos' => $resumen ? $resumen->producto : $p->products,
                        'cbm_total' => (float) $p->cbm_total + (float) $p->cbm_imo,
                        'cbm_imo' => (float) $p->cbm_imo,
                        'peso_total' => $p->peso,
                        'qty_cajas' => $p->qty_box,
                        'unidades' => $resumen ? $resumen->unidades : null,
                        'costos' => $costos->map(function ($k) use ($conBs) {
                            $fila = ['concepto' => $k->concepto, 'valor' => $k->valor];
                            if ($conBs) {
                                $fila['valor_bs'] = $k->valor_bs;
                                $fila['tasa_cambio'] = $k->tasa_cambio;
                            }

                            return $fila;
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        $request->attributes->set('audit_meta', ['resultados' => count($data), 'page' => $paginator->currentPage()]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/integration/v1/cotizaciones-resumen  (multipart: archivo + payload JSON)
    // -----------------------------------------------------------------------
    public function crear(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $orgId = (int) $client->organizacion_id;

        // Reintento seguro: misma Idempotency-Key => misma respuesta, sin duplicar la cotización.
        $idem = $this->idempotencyKey($request);
        if ($idem === false) {
            return $this->invalido(['Idempotency-Key' => ['Debe tener entre 8 y 64 caracteres alfanuméricos, guion o guion bajo.']]);
        }
        $claveIdem = $idem !== null ? 'api-client-idem:' . $client->id . ':' . $idem : null;
        if ($claveIdem !== null && ($previo = Cache::get($claveIdem)) !== null) {
            $request->attributes->set('audit_meta', ['idempotent_replay' => true, 'id_cotizacion' => $previo['data']['id'] ?? null]);

            return response()->json($previo, 200, ['Idempotent-Replay' => 'true']);
        }

        $v = Validator::make($request->all(), [
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:' . (int) config('api_clients.pdf_max_kb', 10240)],
            'payload' => ['required', 'string', 'max:' . (int) config('api_clients.payload_max_bytes', 262144)],
        ]);
        if ($v->fails()) {
            return $this->invalido($v->errors()->toArray());
        }

        $file = $request->file('archivo');
        $errorPdf = $this->validarPdf($file);
        if ($errorPdf !== null) {
            return $this->invalido(['archivo' => [$errorPdf]]);
        }

        [$payload, $errorPayload] = $this->payloadLimpio((string) $request->input('payload'));
        if ($errorPayload !== null) {
            return $this->invalido(['payload' => [$errorPayload]]);
        }

        // Guardado del PDF en la carpeta de la organización con nombre generado por el servidor.
        $storage = app(ObjectStorageConnectorInterface::class);
        $nombreOriginal = $this->nombreSeguro($file->getClientOriginalName());
        $sha256 = hash_file('sha256', $file->getRealPath());
        $bytes = (int) $file->getSize();
        $rutaPdf = null;

        try {
            $rutaPdf = $storage->storeUploadedFile(
                $file,
                'assets/images/agentecompra/' . $orgId,
                Str::uuid()->toString() . '.pdf'
            );

            // El request interno NUNCA toma id_usuario ni archivo del cliente: los fija el servidor.
            $interno = Request::create('/api/carga-consolidada/cotizacion-resumen', 'POST', $payload + [
                'id_usuario' => (int) $client->id_usuario_bot,
                'archivo' => [
                    'path' => $rutaPdf,
                    'nombre_original' => $nombreOriginal,
                    'mime_type' => 'application/pdf',
                ],
            ]);
            $interno->setUserResolver(function () {
                return Auth::guard('api')->user();
            });

            $respuesta = app(CotizacionResumenController::class)->store($interno);
        } catch (ValidationException $e) {
            $this->borrarPdf($storage, $rutaPdf);

            return $this->invalido($e->errors());
        } catch (\Throwable $e) {
            $this->borrarPdf($storage, $rutaPdf);
            Log::error('api.integration crear cotizacion resumen: ' . $e->getMessage(), ['api_client_id' => $client->id]);
            $request->attributes->set('audit_error', 'excepcion_crear');

            return response()->json(['success' => false, 'message' => 'No se pudo registrar la cotización.'], 500);
        }

        $cuerpo = json_decode($respuesta->getContent(), true);
        if (!is_array($cuerpo) || empty($cuerpo['success'])) {
            $this->borrarPdf($storage, $rutaPdf);
            $status = $respuesta->getStatusCode() >= 400 ? $respuesta->getStatusCode() : 422;
            $request->attributes->set('audit_error', 'store_rechazo');

            return response()->json([
                'success' => false,
                'message' => is_array($cuerpo) && !empty($cuerpo['message']) && is_string($cuerpo['message'])
                    ? $cuerpo['message']
                    : 'No se pudo registrar la cotización.',
            ], $status);
        }

        $idCotizacion = (int) ($cuerpo['data']['id'] ?? 0);
        $proveedores = CotizacionProveedor::query()
            ->where('organizacion_id', $orgId)
            ->where('id_cotizacion', $idCotizacion)
            ->orderBy('id')
            ->get(['id', 'products'])
            ->map(function ($p) {
                return ['id' => $p->id, 'productos' => $p->products];
            })->values();

        $resultado = [
            'success' => true,
            'message' => 'Cotización registrada',
            'data' => [
                'id' => $idCotizacion,
                'estado' => 'COTIZADO',
                'proveedores' => $proveedores,
                'archivo' => ['nombre_original' => $nombreOriginal, 'sha256' => $sha256, 'bytes' => $bytes],
            ],
        ];

        if ($claveIdem !== null) {
            Cache::put($claveIdem, $resultado, now()->addDay());
        }
        $request->attributes->set('audit_meta', [
            'id_cotizacion' => $idCotizacion,
            'proveedores' => count($proveedores),
            'pdf_sha256' => $sha256,
            'pdf_bytes' => $bytes,
        ]);

        return response()->json($resultado, 201);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function client(Request $request): ApiClient
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');

        return $client;
    }

    /**
     * @param array<string, mixed> $errores
     */
    private function invalido(array $errores): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Datos inválidos.', 'errors' => $errores], 422);
    }

    /**
     * @return string|false|null null = no enviada; false = inválida
     */
    private function idempotencyKey(Request $request)
    {
        $valor = $request->header('Idempotency-Key');
        if ($valor === null || $valor === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{8,64}$/', (string) $valor) ? (string) $valor : false;
    }

    /**
     * PDF real: extensión/MIME pdf, cabecera %PDF- y sin JavaScript ni archivos embebidos.
     */
    private function validarPdf(?UploadedFile $file): ?string
    {
        if (!$file || !$file->isValid()) {
            return 'El archivo no se pudo recibir.';
        }
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'pdf' || $file->getMimeType() !== 'application/pdf') {
            return 'Solo se acepta un archivo PDF.';
        }
        $ruta = $file->getRealPath();
        if (!$ruta || !is_file($ruta)) {
            return 'El archivo no se pudo leer.';
        }
        $h = fopen($ruta, 'rb');
        $cabecera = $h ? (string) fread($h, 5) : '';
        if ($h) {
            fclose($h);
        }
        if ($cabecera !== '%PDF-') {
            return 'El archivo no es un PDF válido.';
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            return 'El archivo no se pudo leer.';
        }
        if (preg_match(self::PDF_PATRON_PELIGROSO, $contenido)) {
            return 'El PDF contiene elementos activos (JavaScript o archivos embebidos) y no se acepta.';
        }

        return null;
    }

    /**
     * Decodifica y limpia el payload: solo claves permitidas, tipos básicos y tamaños acotados.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function payloadLimpio(string $json): array
    {
        $data = json_decode($json, true, 8);
        if (!is_array($data) || ($data !== [] && array_keys($data) === range(0, count($data) - 1))) {
            return [null, 'El payload debe ser un objeto JSON válido.'];
        }
        if ($extra = array_diff(array_keys($data), self::CLAVES_PAYLOAD)) {
            return [null, 'Campos no permitidos en el payload: ' . implode(', ', array_slice($extra, 0, 5))];
        }
        if (!isset($data['cliente']) || !is_array($data['cliente'])) {
            return [null, 'Falta cliente.'];
        }
        if ($extra = array_diff(array_keys($data['cliente']), self::CLAVES_CLIENTE)) {
            return [null, 'Campos no permitidos en cliente: ' . implode(', ', array_slice($extra, 0, 5))];
        }
        if (!isset($data['proveedores']) || !is_array($data['proveedores']) || $data['proveedores'] === []
            || count($data['proveedores']) > self::MAX_PROVEEDORES
        ) {
            return [null, 'proveedores debe tener entre 1 y ' . self::MAX_PROVEEDORES . ' elementos.'];
        }
        foreach ($data['proveedores'] as $i => $prov) {
            if (!is_array($prov)) {
                return [null, 'El proveedor #' . ($i + 1) . ' no es válido.'];
            }
            if ($extra = array_diff(array_keys($prov), self::CLAVES_PROVEEDOR)) {
                return [null, 'Campos no permitidos en el proveedor #' . ($i + 1) . ': ' . implode(', ', array_slice($extra, 0, 5))];
            }
            $costos = isset($prov['costos']) ? $prov['costos'] : [];
            if (!is_array($costos) || count($costos) > self::MAX_COSTOS) {
                return [null, 'costos del proveedor #' . ($i + 1) . ' debe tener como máximo ' . self::MAX_COSTOS . ' elementos.'];
            }
            foreach ($costos as $costo) {
                if (!is_array($costo) || array_diff(array_keys($costo), self::CLAVES_COSTO)) {
                    return [null, 'costos del proveedor #' . ($i + 1) . ' tiene campos no permitidos.'];
                }
            }
        }

        return [$data, null];
    }

    private function nombreSeguro(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombre) ?? 'documento.pdf';

        return mb_substr($nombre !== '' ? $nombre : 'documento.pdf', 0, 120);
    }

    private function borrarPdf(ObjectStorageConnectorInterface $storage, ?string $ruta): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }
        try {
            $storage->delete($ruta);
        } catch (\Throwable $e) {
            Log::warning('api.integration: no se pudo borrar el PDF huérfano: ' . $e->getMessage());
        }
    }
}
