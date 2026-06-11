<?php

namespace App\Services\CargaConsolidada;

use App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus;
use App\Events\CargaConsolidada\SeguimientoConsolidadoDriveLinkUpdated;
use App\Jobs\SyncSeguimientoConsolidadoExcelJob;
use App\Jobs\VincularSeguimientoConsolidadoExcelJob;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\Usuario;
use App\Services\Google\GoogleDriveSeguimientoConsolidadoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SeguimientoConsolidadoDriveService
{
    private const LOG_PREFIX = '[SeguimientoDrive]';

    /** Campos de proveedor que impactan hoja Seguimiento (YIWU / Recibir / Contactar). */
    public const PROVEEDOR_SEGUIMIENTO_FIELDS = [
        'estados_proveedor',
        'estados',
        'cbm_total_china',
        'cbm_total',
        'qty_pallet_china',
        'arrive_date',
        'arrive_date_china',
    ];

    /** Estados China/coordinación que mueven filas entre tablas del Excel. */
    private const ESTADOS_SEGUIMIENTO_EXCEL = [
        'LOADED', 'WAIT', 'NC', 'C', 'R', 'NS', 'NO LOADED', 'INSPECTION', 'NP',
        'ROTULADO', 'RESERVADO', 'COBRANDO', 'DATOS PROVEEDOR', 'INSPECCIONADO',
        'NO RESERVADO', 'EMBARCADO', 'NO EMBARCADO',
    ];

    /** @var SeguimientoConsolidadoExcelService */
    private $excelService;

    /** @var GoogleDriveSeguimientoConsolidadoService */
    private $driveService;

    public function __construct(
        SeguimientoConsolidadoExcelService $excelService,
        GoogleDriveSeguimientoConsolidadoService $driveService
    ) {
        $this->excelService = $excelService;
        $this->driveService = $driveService;
    }

    /**
     * @param int $idContenedor
     * @return array<string, mixed>
     */
    public function getStatus($idContenedor)
    {
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            return ['success' => false, 'message' => 'Consolidado no encontrado'];
        }

        return [
            'success' => true,
            'data' => $this->formatStatusData($contenedor),
        ];
    }

    /**
     * @param \App\Models\Usuario|null $user
     * @return bool
     */
    public static function userCanManageDriveSeguimiento($user)
    {
        if (!$user) {
            return false;
        }

        return $user->getNombreGrupo() === Usuario::ROL_COTIZADOR
            && (int) $user->getIdUsuario() !== Usuario::ID_JEFE_VENTAS;
    }

    /**
     * @param Contenedor $contenedor
     * @return array<string, mixed>
     */
    public function formatStatusData(Contenedor $contenedor)
    {
        $linkStatus = $contenedor->excel_seguimiento_link_status;

        return [
            'vinculado' => !empty($contenedor->excel_seguimiento_drive_link),
            'link_status' => $linkStatus,
            'processing' => ExcelSeguimientoLinkStatus::isProcessing($linkStatus),
            'link_error' => $contenedor->excel_seguimiento_link_error,
            'drive_link' => $contenedor->excel_seguimiento_drive_link,
            'vinculado_at' => $contenedor->excel_seguimiento_vinculado_at,
            'file_name' => $contenedor->excel_seguimiento_file_name,
            'drive_configured' => $this->driveService->isConfigured(),
        ];
    }

    /**
     * Encola la vinculación inicial (respuesta inmediata al usuario).
     *
     * @param int $idContenedor
     * @return array<string, mixed>
     */
    public function queueVincular($idContenedor)
    {
        if (!$this->driveService->isConfigured()) {
            $this->log('warning', 'Vincular rechazado: Drive no configurado', [
                'id_contenedor' => (int) $idContenedor,
            ]);

            return [
                'success' => false,
                'message' => 'Google Drive no está configurado para seguimiento consolidado (EXCEL_SEGUIMIENTO_CONSOLIDADO_ID).',
            ];
        }

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || empty($contenedor->carga)) {
            $this->log('warning', 'Vincular rechazado: consolidado no encontrado', [
                'id_contenedor' => (int) $idContenedor,
            ]);

            return ['success' => false, 'message' => 'Consolidado no encontrado'];
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            $this->log('info', 'Vincular omitido: proceso en curso', [
                'id_contenedor' => (int) $idContenedor,
                'link_status' => $contenedor->excel_seguimiento_link_status,
            ]);

            return [
                'success' => false,
                'queued' => false,
                'message' => 'Ya hay una vinculación en curso. Espera a que termine.',
                'data' => $this->formatStatusData($contenedor),
            ];
        }

        $yaVinculado = !empty($contenedor->excel_seguimiento_drive_link);

        $this->log('info', 'Vincular encolado', [
            'id_contenedor' => (int) $idContenedor,
            'carga' => $contenedor->carga,
            'queue' => config('carga_consolidada.queue', 'carga_consolidada'),
            'nuevo_documento' => true,
            'reemplaza_vinculo_anterior' => $yaVinculado,
        ]);

        DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->update([
                'excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::QUEUED,
                'excel_seguimiento_link_error' => null,
            ]);

        $contenedor = Contenedor::find($idContenedor);

        VincularSeguimientoConsolidadoExcelJob::dispatch((int) $idContenedor);

        return [
            'success' => true,
            'queued' => true,
            'message' => $yaVinculado
                ? 'Se está creando un nuevo Excel en Drive.'
                : 'La vinculación a Drive se está procesando en segundo plano.',
            'data' => $this->formatStatusData($contenedor),
        ];
    }

    /**
     * Ejecuta la vinculación (solo desde job).
     *
     * @param int $idContenedor
     * @param Request|null $request
     * @return array<string, mixed>
     */
    public function executeVincular($idContenedor, Request $request = null)
    {
        $idContenedor = (int) $idContenedor;

        $this->log('info', 'Vincular iniciado (job)', ['id_contenedor' => $idContenedor]);

        DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->update(['excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::PROCESSING]);

        $result = $this->syncToDrive($idContenedor, $request, true);
        if (empty($result['success'])) {
            $this->markLinkFailed($idContenedor, $result['message'] ?? 'Error al vincular');

            return $result;
        }

        DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->update([
                'excel_seguimiento_drive_file_id' => $result['file_id'],
                'excel_seguimiento_drive_link' => $result['drive_link'],
                'excel_seguimiento_file_name' => $result['file_name'],
                'excel_seguimiento_vinculado_at' => Carbon::now(),
                'excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::COMPLETED,
                'excel_seguimiento_link_error' => null,
            ]);

        $this->broadcastLinkStatus($idContenedor);

        $this->log('info', 'Vincular completado', [
            'id_contenedor' => $idContenedor,
            'file_name' => $result['file_name'],
            'file_id' => $result['file_id'],
            'drive_link' => $result['drive_link'],
        ]);

        return [
            'success' => true,
            'message' => 'Excel vinculado a Google Drive correctamente.',
            'data' => [
                'drive_link' => $result['drive_link'],
                'file_name' => $result['file_name'],
            ],
        ];
    }

    /**
     * Ejecuta sincronización (solo desde job).
     *
     * @param int $idContenedor
     * @param Request|null $request
     * @return array<string, mixed>
     */
    public function executeSync($idContenedor, Request $request = null)
    {
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || empty($contenedor->excel_seguimiento_drive_link)) {
            $this->log('debug', 'Sync omitido: no vinculado', [
                'id_contenedor' => (int) $idContenedor,
            ]);

            return ['success' => false, 'message' => 'El consolidado no está vinculado a Drive'];
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            $this->log('info', 'Sync omitido: vinculación en curso', [
                'id_contenedor' => (int) $idContenedor,
                'link_status' => $contenedor->excel_seguimiento_link_status,
            ]);

            return ['success' => false, 'message' => 'Vinculación en curso, se omitió sync'];
        }

        $this->log('info', 'Sync iniciado (job)', [
            'id_contenedor' => (int) $idContenedor,
            'carga' => $contenedor->carga,
        ]);

        $result = $this->syncToDrive($idContenedor, $request, false);

        if (!empty($result['success'])) {
            $this->log('info', 'Sync completado', [
                'id_contenedor' => (int) $idContenedor,
                'file_name' => $result['file_name'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * @param int $idContenedor
     * @param string $message
     */
    public function markLinkFailed($idContenedor, $message)
    {
        $this->log('error', 'Vinculación fallida', [
            'id_contenedor' => (int) $idContenedor,
            'error' => $message,
        ]);

        DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->update([
                'excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::FAILED,
                'excel_seguimiento_link_error' => $message,
            ]);

        $this->broadcastLinkStatus($idContenedor);
    }

    /**
     * @param int $idContenedor
     */
    private function broadcastLinkStatus($idContenedor)
    {
        $status = $this->getStatus($idContenedor);
        if (empty($status['success']) || empty($status['data'])) {
            return;
        }

        $this->log('debug', 'Broadcast WS estado vinculación', [
            'id_contenedor' => (int) $idContenedor,
            'link_status' => $status['data']['link_status'] ?? null,
            'vinculado' => $status['data']['vinculado'] ?? null,
        ]);

        event(new SeguimientoConsolidadoDriveLinkUpdated((int) $idContenedor, $status['data']));
    }

    /**
     * Sync cuando el proveedor cambió campos relevantes (Observer / Eloquent).
     *
     * @param CotizacionProveedor $proveedor
     */
    public function queueSyncIfLinkedFromProveedor(CotizacionProveedor $proveedor)
    {
        if (empty($proveedor->id_contenedor)) {
            return;
        }

        if (!$proveedor->wasRecentlyCreated) {
            $relevantChange = false;
            foreach (self::PROVEEDOR_SEGUIMIENTO_FIELDS as $field) {
                if ($proveedor->wasChanged($field)) {
                    $relevantChange = true;
                    break;
                }
            }

            if (!$relevantChange) {
                return;
            }
        }

        $this->queueSyncIfLinked((int) $proveedor->id_contenedor);
    }

    /**
     * Sync tras updateEstadoCotizacionProveedor (usa DB::table, no dispara Observer).
     *
     * @param int $idContenedor
     * @param string $estado
     */
    public function queueSyncIfLinkedFromEstadoChange($idContenedor, $estado)
    {
        $idContenedor = (int) $idContenedor;
        if ($idContenedor <= 0) {
            return;
        }

        if (!$this->estadoAfectaSeguimientoExcel($estado)) {
            return;
        }

        $this->queueSyncIfLinked($idContenedor);
    }

    /**
     * @param string $estado
     * @return bool
     */
    private function estadoAfectaSeguimientoExcel($estado)
    {
        return in_array(strtoupper(trim((string) $estado)), self::ESTADOS_SEGUIMIENTO_EXCEL, true);
    }

    /**
     * @param int $idContenedor
     */
    public function queueSyncIfLinked($idContenedor)
    {
        $idContenedor = (int) $idContenedor;
        if ($idContenedor <= 0) {
            return;
        }

        $row = DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->select(['excel_seguimiento_drive_link', 'excel_seguimiento_link_status'])
            ->first();

        if (!$row || empty($row->excel_seguimiento_drive_link)) {
            return;
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($row->excel_seguimiento_link_status)) {
            $this->log('debug', 'Sync no encolado: vinculación en curso', [
                'id_contenedor' => $idContenedor,
            ]);

            return;
        }

        $this->log('debug', 'Sync encolado por cambio de datos', [
            'id_contenedor' => $idContenedor,
            'queue' => config('carga_consolidada.queue', 'carga_consolidada'),
        ]);

        SyncSeguimientoConsolidadoExcelJob::dispatch($idContenedor);
    }

    /**
     * Corte diario 20:00 Perú (ejecutado desde job).
     *
     * @param int|null $idContenedor
     */
    public function procesarCorteDatosProveedor($idContenedor = null)
    {
        if (!Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
            $this->log('warning', 'Corte omitido: tabla de periodos no existe');

            return;
        }

        $periodo = SeguimientoConsolidadoCorteConfig::periodoCorteJob();
        $periodoInicio = $periodo['inicio'];
        $periodoFin = $periodo['fin'];

        $this->log('info', 'Corte DATOS PROVEEDOR iniciado', [
            'id_contenedor' => $idContenedor,
            'hora_corte' => SeguimientoConsolidadoCorteConfig::settings()['hora'],
            'periodo_inicio' => $periodoInicio->toDateTimeString(),
            'periodo_fin' => $periodoFin->toDateTimeString(),
        ]);

        $query = DB::table('carga_consolidada_contenedor')
            ->whereNotNull('excel_seguimiento_drive_link')
            ->select('id');

        if ($idContenedor !== null) {
            $query->where('id', (int) $idContenedor);
        }

        $contenedores = $query->pluck('id');

        $this->log('info', 'Corte: consolidados vinculados a procesar', [
            'total' => $contenedores->count(),
            'ids' => $contenedores->values()->all(),
        ]);

        foreach ($contenedores as $cid) {
            $this->registrarCorteParaContenedor((int) $cid, $periodoInicio, $periodoFin);
            $this->queueSyncIfLinked((int) $cid);
        }

        $this->log('info', 'Corte DATOS PROVEEDOR finalizado');
    }

    /**
     * @param int $idContenedor
     * @param Carbon $periodoInicio
     * @param Carbon $periodoFin
     */
    private function registrarCorteParaContenedor($idContenedor, Carbon $periodoInicio, Carbon $periodoFin)
    {
        $yaRegistrado = DB::table('contenedor_seguimiento_corte_periodos')
            ->where('id_contenedor', $idContenedor)
            ->where('periodo_fin', $periodoFin->toDateTimeString())
            ->exists();

        if ($yaRegistrado) {
            $this->log('info', 'Corte ya registrado para este periodo', [
                'id_contenedor' => $idContenedor,
                'periodo_fin' => $periodoFin->toDateTimeString(),
            ]);

            return;
        }

        $trackingTable = $this->resolveTrackingTable();
        if (!$trackingTable) {
            $this->log('warning', 'Corte omitido: tabla tracking no encontrada', [
                'id_contenedor' => $idContenedor,
            ]);

            return;
        }

        $inicioUtc = $periodoInicio->copy()->timezone('UTC');
        $finUtc = $periodoFin->copy()->timezone('UTC');

        $transiciones = DB::table($trackingTable . ' as T')
            ->join('contenedor_consolidado_cotizacion_proveedores as P', 'P.id', '=', 'T.id_proveedor')
            ->join('contenedor_consolidado_cotizacion as C', 'C.id', '=', 'P.id_cotizacion')
            ->where('P.id_contenedor', $idContenedor)
            ->where('T.estado', 'DATOS PROVEEDOR')
            ->where(function ($q) use ($inicioUtc, $finUtc) {
                $q->whereBetween('T.created_at', [$inicioUtc, $finUtc])
                    ->orWhereBetween('T.updated_at', [$inicioUtc, $finUtc]);
            })
            ->select([
                'T.id_proveedor',
                'T.id_cotizacion',
                'T.created_at',
                'T.updated_at',
                'P.code_supplier',
                'P.products',
                'C.nombre as nombre_cliente',
            ])
            ->get();

        if ($transiciones->isEmpty()) {
            $this->log('info', 'Corte sin transiciones DATOS PROVEEDOR', [
                'id_contenedor' => $idContenedor,
                'tracking_table' => $trackingTable,
            ]);

            return;
        }

        $corteId = DB::table('contenedor_seguimiento_corte_periodos')->insertGetId([
            'id_contenedor' => $idContenedor,
            'periodo_inicio' => $periodoInicio->toDateTimeString(),
            'periodo_fin' => $periodoFin->toDateTimeString(),
            'created_at' => Carbon::now(),
        ]);

        $insertedProveedores = [];
        foreach ($transiciones as $t) {
            if (isset($insertedProveedores[$t->id_proveedor])) {
                continue;
            }
            $insertedProveedores[$t->id_proveedor] = true;

            $fechaCambio = $t->updated_at ?: $t->created_at;

            DB::table('contenedor_seguimiento_corte_clientes')->insert([
                'id_corte' => $corteId,
                'id_proveedor' => $t->id_proveedor,
                'id_cotizacion' => $t->id_cotizacion,
                'nombre_cliente' => $t->nombre_cliente,
                'code_supplier' => $t->code_supplier,
                'products' => $t->products,
                'fecha_cambio' => $fechaCambio,
                'created_at' => Carbon::now(),
            ]);
        }

        $this->log('info', 'Corte registrado', [
            'id_contenedor' => $idContenedor,
            'id_corte' => $corteId,
            'proveedores' => count($insertedProveedores),
            'transiciones_encontradas' => $transiciones->count(),
        ]);
    }

    /**
     * @param int $idContenedor
     * @param Request|null $request
     * @param bool $isInitialLink
     * @return array<string, mixed>
     */
    private function syncToDrive($idContenedor, Request $request = null, $isInitialLink = false)
    {
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            return ['success' => false, 'message' => 'Consolidado no encontrado'];
        }

        $tmpPath = null;
        try {
            $fileName = $isInitialLink
                ? $this->excelService->buildFileName((string) $contenedor->carga)
                : (
                    !empty($contenedor->excel_seguimiento_file_name)
                        ? $contenedor->excel_seguimiento_file_name
                        : $this->excelService->buildFileName((string) $contenedor->carga)
                );

            $this->log('info', 'Generando Excel temporal', [
                'id_contenedor' => $idContenedor,
                'carga' => $contenedor->carga,
                'file_name' => $fileName,
                'is_initial_link' => $isInitialLink,
                'nuevo_archivo_drive' => $isInitialLink,
            ]);

            $tmpPath = $this->excelService->writeTempFile($idContenedor, $request);

            $this->log('info', 'Excel temporal generado', [
                'id_contenedor' => $idContenedor,
                'tmp_path' => $tmpPath,
                'size_bytes' => is_file($tmpPath) ? filesize($tmpPath) : null,
            ]);

            $driveLink = $this->driveService->uploadForConsolidado(
                (string) $contenedor->carga,
                $tmpPath,
                $fileName
            );

            if (!$driveLink) {
                $this->log('error', 'Subida a Drive fallida', [
                    'id_contenedor' => $idContenedor,
                    'file_name' => $fileName,
                ]);

                return [
                    'success' => false,
                    'message' => 'No se pudo subir el Excel a Google Drive.',
                ];
            }

            $fileId = $this->extractFileIdFromDriveUrl($driveLink);

            $this->log('info', 'Excel subido a Drive', [
                'id_contenedor' => $idContenedor,
                'file_name' => $fileName,
                'file_id' => $fileId,
                'drive_link' => $driveLink,
            ]);

            if (!$isInitialLink) {
                DB::table('carga_consolidada_contenedor')
                    ->where('id', $idContenedor)
                    ->update([
                        'excel_seguimiento_drive_file_id' => $fileId,
                        'excel_seguimiento_drive_link' => $driveLink,
                        'excel_seguimiento_file_name' => $fileName,
                    ]);
            }

            return [
                'success' => true,
                'drive_link' => $driveLink,
                'file_id' => $fileId,
                'file_name' => $fileName,
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'Error en syncToDrive', [
                'id_contenedor' => $idContenedor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al sincronizar Excel: ' . $e->getMessage(),
            ];
        } finally {
            if ($tmpPath && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * @param string $url
     * @return string|null
     */
    private function extractFileIdFromDriveUrl($url)
    {
        if (preg_match('#/file/d/([^/]+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function resolveTrackingTable()
    {
        if (Schema::hasTable('contenedor_proveedor_estados_tracking_estados')) {
            return 'contenedor_proveedor_estados_tracking_estados';
        }
        if (Schema::hasTable('contenedor_proveedor_estados_tracking')) {
            return 'contenedor_proveedor_estados_tracking';
        }

        return null;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function log($level, $message, array $context = [])
    {
        Log::log($level, self::LOG_PREFIX . ' ' . $message, $context);
    }
}
