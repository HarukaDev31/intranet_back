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
use App\Services\CargaConsolidada\SeguimientoConsolidadoDriveCellSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        if (!SeguimientoConsolidadoVincularEligibility::tieneFInicio($contenedor)) {
            return ['success' => true, 'data' => null];
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

        if (!SeguimientoConsolidadoVincularEligibility::puedeOperarSeguimientoDrive($contenedor)) {
            $message = SeguimientoConsolidadoVincularEligibility::tieneFInicio($contenedor)
                ? 'Consolidado fuera de alcance para Excel seguimiento Drive.'
                : SeguimientoConsolidadoVincularEligibility::mensajeSinFInicio();

            $this->log('info', 'Vincular rechazado: no elegible', [
                'id_contenedor' => (int) $idContenedor,
                'carga' => $contenedor->carga,
                'f_inicio' => $contenedor->f_inicio,
            ]);

            return ['success' => false, 'message' => $message];
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
     * Si el consolidado es elegible y aún no tiene Excel en Drive, encola la vinculación.
     *
     * @param int $idContenedor
     */
    public function ensureVinculadoIfEligible($idContenedor)
    {
        $idContenedor = (int) $idContenedor;
        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || !SeguimientoConsolidadoVincularEligibility::puedeVincular($contenedor)) {
            return;
        }

        $this->queueVincular($idContenedor);
    }

    /**
     * Encola vinculación inicial para consolidados elegibles sin Excel en Drive (cron).
     *
     * @return array{encolados: int, total: int, ids: int[]}
     */
    public function queueVincularPendientes()
    {
        $pendientes = SeguimientoConsolidadoVincularEligibility::contenedoresPendientesVincular();
        $encolados = 0;
        $ids = [];

        foreach ($pendientes as $contenedor) {
            $result = $this->queueVincular((int) $contenedor->id);
            if (empty($result['success'])) {
                continue;
            }

            $encolados++;
            $ids[] = (int) $contenedor->id;
        }

        $this->log('info', 'Auto-vincular pendientes', [
            'total_elegibles' => $pendientes->count(),
            'encolados' => $encolados,
            'ids' => $ids,
        ]);

        return [
            'encolados' => $encolados,
            'total' => $pendientes->count(),
            'ids' => $ids,
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
        $flow = new SeguimientoConsolidadoFlowContext($idContenedor, 'vincular_execute');

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || !SeguimientoConsolidadoVincularEligibility::puedeOperarSeguimientoDrive($contenedor)) {
            $this->logFlow('vincular_omitido_no_elegible', $flow, 'warning');

            return [
                'success' => false,
                'message' => $contenedor && !SeguimientoConsolidadoVincularEligibility::tieneFInicio($contenedor)
                    ? SeguimientoConsolidadoVincularEligibility::mensajeSinFInicio()
                    : 'Consolidado no elegible para Excel seguimiento Drive.',
            ];
        }

        $this->logFlow('vincular_iniciado', $flow, 'info', [
            'carga' => $contenedor->carga,
        ]);

        DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->update(['excel_seguimiento_link_status' => ExcelSeguimientoLinkStatus::PROCESSING]);

        $result = $this->syncToDrive($idContenedor, $request, true, $flow);
        if (empty($result['success'])) {
            $this->markLinkFailed($idContenedor, $result['message'] ?? 'Error al vincular');
            $this->logFlow('vincular_fallido', $flow, 'error', [
                'message' => $result['message'] ?? 'unknown',
                'duration_ms' => $flow->elapsedMs(),
            ]);

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

        $this->logFlow('vincular_completado', $flow, 'info', [
            'file_name' => $result['file_name'],
            'file_id' => $result['file_id'],
            'drive_link' => $result['drive_link'],
            'duration_ms' => $flow->elapsedMs(),
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
     * @param SeguimientoConsolidadoFlowContext|null $flow
     * @return array<string, mixed>
     */
    public function executeSync($idContenedor, Request $request = null, SeguimientoConsolidadoFlowContext $flow = null)
    {
        $idContenedor = (int) $idContenedor;
        $flow = $flow ?: new SeguimientoConsolidadoFlowContext($idContenedor, 'execute_sync');

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || empty($contenedor->excel_seguimiento_drive_link)) {
            $this->logFlow('sync_skip_no_vinculado', $flow, 'info', [
                'reason' => 'sin excel_seguimiento_drive_link',
            ]);

            return ['success' => false, 'message' => 'El consolidado no está vinculado a Drive'];
        }

        if (!SeguimientoConsolidadoVincularEligibility::puedeOperarSeguimientoDrive($contenedor)) {
            $this->logFlow('sync_skip_no_elegible', $flow, 'info', [
                'reason' => 'sin f_inicio o fuera de alcance',
                'f_inicio' => $contenedor->f_inicio,
            ]);

            return ['success' => false, 'message' => SeguimientoConsolidadoVincularEligibility::mensajeSinFInicio()];
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($contenedor->excel_seguimiento_link_status)) {
            $this->logFlow('sync_skip_vinculacion_en_curso', $flow, 'info', [
                'link_status' => $contenedor->excel_seguimiento_link_status,
            ]);

            return ['success' => false, 'message' => 'Vinculación en curso, se omitió sync'];
        }

        $this->logFlow('sync_iniciado', $flow, 'info', [
            'carga' => $contenedor->carga,
            'file_id' => $contenedor->excel_seguimiento_drive_file_id,
            'file_name' => $contenedor->excel_seguimiento_file_name,
        ]);

        $result = $this->syncToDrive($idContenedor, $request, false, $flow);

        if (!empty($result['success'])) {
            $this->logFlow('sync_completado', $flow, 'info', [
                'file_name' => $result['file_name'] ?? null,
                'file_id' => $result['file_id'] ?? null,
                'duration_ms' => $flow->elapsedMs(),
            ]);
        } else {
            $this->logFlow('sync_fallido', $flow, 'warning', [
                'message' => $result['message'] ?? 'unknown',
                'duration_ms' => $flow->elapsedMs(),
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
            ->select(['excel_seguimiento_drive_link', 'excel_seguimiento_link_status', 'f_inicio'])
            ->first();

        if (!$row) {
            return;
        }

        if (empty($row->excel_seguimiento_drive_link)) {
            $contenedor = Contenedor::find($idContenedor);
            if ($contenedor && SeguimientoConsolidadoVincularEligibility::puedeVincular($contenedor)) {
                $this->log('info', 'Sync: consolidado sin Drive, se encola vinculación inicial', [
                    'flow' => 'seguimiento_drive',
                    'step' => 'queue_vincular_desde_sync',
                    'id_contenedor' => $idContenedor,
                    'estado_china' => $contenedor->estado_china,
                ]);
                $this->queueVincular($idContenedor);
            }

            return;
        }

        if (empty($row->f_inicio)) {
            return;
        }

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor || !SeguimientoConsolidadoVincularEligibility::puedeOperarSeguimientoDrive($contenedor)) {
            return;
        }

        if (ExcelSeguimientoLinkStatus::isProcessing($row->excel_seguimiento_link_status)) {
            $this->log('info', 'Sync no encolado: vinculación en curso', [
                'flow' => 'seguimiento_drive',
                'step' => 'queue_skip_vinculacion_en_curso',
                'id_contenedor' => $idContenedor,
                'link_status' => $row->excel_seguimiento_link_status,
            ]);

            return;
        }

        $this->log('info', 'Sync solicitado por cambio de datos', [
            'flow' => 'seguimiento_drive',
            'step' => 'queue_data_change',
            'id_contenedor' => $idContenedor,
        ]);

        $this->enqueueSyncJob($idContenedor, 'data_change');
    }

    /**
     * Evita encolar el mismo consolidado varias veces seguidas (observers + scheduler).
     * Si el debounce está activo, marca dirty; el job libera el debounce al terminar y re-encola.
     *
     * @param int $idContenedor
     * @param string $reason
     * @return bool true si se encoló el job
     */
    public function enqueueSyncJob($idContenedor, $reason = 'manual')
    {
        $idContenedor = (int) $idContenedor;
        if ($idContenedor <= 0) {
            return false;
        }

        if (!$this->acquireSyncDebounce($idContenedor)) {
            $this->markSyncDirty($idContenedor);
            $this->log('info', 'Sync no encolado: debounce activo (marcado dirty)', [
                'flow' => 'seguimiento_drive',
                'step' => 'enqueue_debounce_omitido',
                'id_contenedor' => $idContenedor,
                'reason' => $reason,
                'debounce_minutes' => (int) config('carga_consolidada.seguimiento_sync_debounce_minutes', 10),
            ]);

            return false;
        }

        $this->log('info', 'Sync encolado', [
            'flow' => 'seguimiento_drive',
            'step' => 'enqueue_ok',
            'id_contenedor' => $idContenedor,
            'reason' => $reason,
            'queue' => config('carga_consolidada.queue', 'carga_consolidada'),
        ]);

        SyncSeguimientoConsolidadoExcelJob::dispatch($idContenedor);

        return true;
    }

    /**
     * @param int $idContenedor
     * @return bool
     */
    public function acquireSyncDebounce($idContenedor)
    {
        $minutes = (int) config('carga_consolidada.seguimiento_sync_debounce_minutes', 10);
        if ($minutes <= 0) {
            return true;
        }

        return Cache::add(
            $this->syncDebounceCacheKey($idContenedor),
            1,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Libera debounce tras fallo para permitir reintento antes del TTL.
     *
     * @param int $idContenedor
     */
    public function releaseSyncDebounce($idContenedor)
    {
        Cache::forget($this->syncDebounceCacheKey((int) $idContenedor));
    }

    /**
     * Marca que hubo cambios mientras el debounce impedía encolar (p. ej. pagos → URGENCIA).
     *
     * @param int $idContenedor
     */
    public function markSyncDirty($idContenedor)
    {
        Cache::put(
            $this->syncDirtyCacheKey((int) $idContenedor),
            1,
            now()->addHours(2)
        );
    }

    /**
     * Consume el flag dirty (si existía).
     *
     * @param int $idContenedor
     * @return bool
     */
    public function consumeSyncDirty($idContenedor)
    {
        return (bool) Cache::pull($this->syncDirtyCacheKey((int) $idContenedor));
    }

    /**
     * Tras un sync: si hubo cambios durante debounce/ejecución, libera debounce y re-encola.
     *
     * @param int $idContenedor
     * @param string $reason
     * @return bool
     */
    public function requeueIfDirty($idContenedor, $reason = 'dirty_retry')
    {
        $idContenedor = (int) $idContenedor;
        if ($idContenedor <= 0 || !$this->consumeSyncDirty($idContenedor)) {
            $this->log('info', 'Re-encola dirty: nada pendiente', [
                'flow' => 'seguimiento_drive',
                'step' => 'requeue_dirty_skip',
                'id_contenedor' => $idContenedor,
                'reason' => $reason,
            ]);

            return false;
        }

        $this->releaseSyncDebounce($idContenedor);

        $this->log('info', 'Re-encolando sync por cambios durante debounce/ejecución', [
            'flow' => 'seguimiento_drive',
            'step' => 'requeue_dirty',
            'id_contenedor' => $idContenedor,
            'reason' => $reason,
        ]);

        return $this->enqueueSyncJob($idContenedor, $reason);
    }

    /**
     * @param int $idContenedor
     * @return string
     */
    private function syncDebounceCacheKey($idContenedor)
    {
        return 'seguimiento_drive:sync_debounce:' . (int) $idContenedor;
    }

    /**
     * @param int $idContenedor
     * @return string
     */
    private function syncDirtyCacheKey($idContenedor)
    {
        return 'seguimiento_drive:sync_dirty:' . (int) $idContenedor;
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
            ->whereNotNull('f_inicio')
            ->where('estado_finanzas', 'PENDIENTE')
            ->where(function ($q) {
                $q->whereNull('estado_china')
                    ->orWhere('estado_china', '')
                    ->orWhere('estado_china', '!=', 'COMPLETADO');
            })
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
     * @param SeguimientoConsolidadoFlowContext|null $flow
     * @return array<string, mixed>
     */
    private function syncToDrive($idContenedor, Request $request = null, $isInitialLink = false, SeguimientoConsolidadoFlowContext $flow = null)
    {
        $idContenedor = (int) $idContenedor;
        $flow = $flow ?: new SeguimientoConsolidadoFlowContext($idContenedor, $isInitialLink ? 'vincular' : 'sync_to_drive');

        $contenedor = Contenedor::find($idContenedor);
        if (!$contenedor) {
            $this->logFlow('sync_to_drive_sin_contenedor', $flow, 'error');

            return ['success' => false, 'message' => 'Consolidado no encontrado'];
        }

        $tmpPath = null;
        try {
            $fileName = $this->excelService->buildFileName(
                (string) $contenedor->carga,
                (string) ($contenedor->parte ?? ''),
                (int) $contenedor->id
            );
            if (!$isInitialLink && !empty($contenedor->excel_seguimiento_file_name)
                && SeguimientoConsolidadoVincularEligibility::tieneExcelDrivePropio($contenedor)
            ) {
                $fileName = $contenedor->excel_seguimiento_file_name;
            }

            $this->logFlow('generar_excel_inicio', $flow, 'info', [
                'carga' => $contenedor->carga,
                'file_name' => $fileName,
                'is_initial_link' => $isInitialLink,
                'existing_file_id' => $contenedor->excel_seguimiento_drive_file_id,
            ]);

            // Siempre pull notas manuales si ya hay archivo en Drive (también en re-vincular).
            if (!empty($contenedor->excel_seguimiento_drive_file_id)) {
                $pullStarted = microtime(true);
                $this->logFlow('pull_pre_sync_inicio', $flow, 'info', [
                    'file_id' => $contenedor->excel_seguimiento_drive_file_id,
                ]);

                $pullResult = app(SeguimientoConsolidadoDriveCellSyncService::class)
                    ->pullFromDrive($idContenedor, 'pre_sync', $flow);

                if (empty($pullResult['success'])) {
                    $this->logFlow('pull_pre_sync_fallido', $flow, 'warning', [
                        'message' => $pullResult['message'] ?? 'unknown',
                        'duration_ms' => (int) round((microtime(true) - $pullStarted) * 1000),
                    ]);
                } else {
                    $this->logFlow('pull_pre_sync_ok', $flow, 'info', [
                        'cells_upserted' => $pullResult['cells_upserted'] ?? 0,
                        'cells_history' => $pullResult['cells_history'] ?? 0,
                        'duration_ms' => (int) round((microtime(true) - $pullStarted) * 1000),
                    ]);
                }
            } else {
                $this->logFlow('pull_pre_sync_omitido', $flow, 'info', [
                    'reason' => 'sin excel_seguimiento_drive_file_id',
                ]);
            }

            $excelStarted = microtime(true);
            $tmpPath = $this->excelService->writeTempFile($idContenedor, $request);
            $this->logFlow('excel_temporal_generado', $flow, 'info', [
                'tmp_path' => $tmpPath,
                'size_bytes' => is_file($tmpPath) ? filesize($tmpPath) : null,
                'duration_ms' => (int) round((microtime(true) - $excelStarted) * 1000),
            ]);

            $manualStarted = microtime(true);
            $manualResult = app(SeguimientoConsolidadoDriveCellSyncService::class)
                ->applyManualCellsToLocalFile($idContenedor, $tmpPath, $flow);
            $this->logFlow('celdas_manuales_aplicadas', $flow, 'info', array_merge($manualResult, [
                'duration_ms' => (int) round((microtime(true) - $manualStarted) * 1000),
            ]));

            $folder = $this->resolveDriveFolderSegments($contenedor);

            $uploadStarted = microtime(true);
            $this->logFlow('upload_drive_inicio', $flow, 'info', [
                'year_folder' => $folder['year'],
                'mes_folder' => $folder['mes'],
                'file_name' => $fileName,
            ]);

            $driveLink = $this->driveService->uploadForConsolidado(
                $folder['mes'],
                $tmpPath,
                $fileName,
                $this->resolveExistingDriveFileId($contenedor),
                $folder['year']
            );

            if (!$driveLink) {
                $this->logFlow('upload_drive_fallido', $flow, 'error', [
                    'file_name' => $fileName,
                    'duration_ms' => (int) round((microtime(true) - $uploadStarted) * 1000),
                ]);

                return [
                    'success' => false,
                    'message' => 'No se pudo subir el Excel a Google Drive.',
                ];
            }

            $fileId = $this->extractFileIdFromDriveUrl($driveLink);

            $this->logFlow('upload_drive_ok', $flow, 'info', [
                'year_folder' => $folder['year'],
                'mes_folder' => $folder['mes'],
                'file_name' => $fileName,
                'file_id' => $fileId,
                'drive_link' => $driveLink,
                'duration_ms' => (int) round((microtime(true) - $uploadStarted) * 1000),
            ]);

            if (!$isInitialLink) {
                DB::table('carga_consolidada_contenedor')
                    ->where('id', $idContenedor)
                    ->update([
                        'excel_seguimiento_drive_file_id' => $fileId,
                        'excel_seguimiento_drive_link' => $driveLink,
                        'excel_seguimiento_file_name' => $fileName,
                    ]);

                $postPullStarted = microtime(true);
                $this->logFlow('pull_post_sync_inicio', $flow, 'info');

                $postPull = app(SeguimientoConsolidadoDriveCellSyncService::class)
                    ->pullFromDrive($idContenedor, 'post_sync', $flow);

                if (!empty($postPull['success'])) {
                    $this->logFlow('pull_post_sync_ok', $flow, 'info', [
                        'cells_upserted' => $postPull['cells_upserted'] ?? 0,
                        'cells_history' => $postPull['cells_history'] ?? 0,
                        'duration_ms' => (int) round((microtime(true) - $postPullStarted) * 1000),
                    ]);
                } else {
                    $this->logFlow('pull_post_sync_fallido', $flow, 'warning', [
                        'message' => $postPull['message'] ?? 'unknown',
                        'duration_ms' => (int) round((microtime(true) - $postPullStarted) * 1000),
                    ]);
                }
            }

            $this->logFlow('sync_to_drive_ok', $flow, 'info', [
                'duration_ms' => $flow->elapsedMs(),
            ]);

            return [
                'success' => true,
                'drive_link' => $driveLink,
                'file_id' => $fileId,
                'file_name' => $fileName,
            ];
        } catch (\Throwable $e) {
            $this->logFlow('sync_to_drive_excepcion', $flow, 'error', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $flow->elapsedMs(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al sincronizar Excel: ' . $e->getMessage(),
            ];
        } finally {
            if ($tmpPath && is_file($tmpPath)) {
                @unlink($tmpPath);
                $this->logFlow('excel_temporal_eliminado', $flow, 'info', [
                    'tmp_path' => $tmpPath,
                ]);
            }
        }
    }

    /**
     * file_id solo si este consolidado no comparte Excel con otro.
     *
     * @param Contenedor $contenedor
     * @return string|null
     */
    private function resolveExistingDriveFileId(Contenedor $contenedor)
    {
        if (!SeguimientoConsolidadoVincularEligibility::tieneExcelDrivePropio($contenedor)) {
            return null;
        }

        $fileId = trim((string) ($contenedor->excel_seguimiento_drive_file_id ?? ''));

        return $fileId !== '' ? $fileId : null;
    }

    /**
     * Carpetas Drive: {año}/{Mes} (p. ej. 2026/Agosto).
     *
     * @param Contenedor $contenedor
     * @return array{year: string, mes: string}
     */
    private function resolveDriveFolderSegments(Contenedor $contenedor)
    {
        $anio = SeguimientoConsolidadoVincularEligibility::resolveAnioContenedor($contenedor);
        if ($anio <= 0) {
            $anio = (int) Carbon::now('America/Lima')->format('Y');
        }

        return [
            'year' => (string) $anio,
            'mes' => $this->resolveMesDriveFolder($contenedor),
        ];
    }

    /**
     * Nombre de mes en Drive (Setiembre unificado; acepta número 1–12).
     *
     * @param Contenedor $contenedor
     * @return string
     */
    private function resolveMesDriveFolder(Contenedor $contenedor)
    {
        $mesesNum = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Setiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        $mes = trim((string) $contenedor->mes);
        if ($mes !== '') {
            $upper = strtoupper($mes);
            if ($upper === 'SEPTIEMBRE') {
                return 'Setiembre';
            }
            if (isset(Contenedor::MESES[$upper])) {
                $label = Contenedor::MESES[$upper];

                return $label === 'Septiembre' ? 'Setiembre' : $label;
            }
            if (is_numeric($mes) && isset($mesesNum[(int) $mes])) {
                return $mesesNum[(int) $mes];
            }
        }

        if (!empty($contenedor->f_inicio)) {
            $n = (int) Carbon::parse($contenedor->f_inicio)->format('n');
            if (isset($mesesNum[$n])) {
                return $mesesNum[$n];
            }
        }

        return 'Sin-mes';
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
     * Reset: borra Excel en Drive, limpia BD y opcionalmente re-encola vincular.
     *
     * @param int|null $idContenedor
     * @param bool $all Todos los vinculados + purga carpeta raíz Drive
     * @param bool $keepHistorico Conservar cortes CONTACTAR y row_sync
     * @param bool $revincular Encolar vincular al finalizar
     * @return array<string, mixed>
     */
    public function resetSeguimientoDrive($idContenedor = null, $all = false, $keepHistorico = false, $revincular = true)
    {
        if (!$all && ($idContenedor === null || (int) $idContenedor <= 0)) {
            return [
                'success' => false,
                'message' => 'Indique idContenedor o use --all.',
            ];
        }

        if (!$this->driveService->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Google Drive no está configurado para seguimiento consolidado.',
            ];
        }

        $query = DB::table('carga_consolidada_contenedor');

        if ($all) {
            $query->whereNotNull('excel_seguimiento_drive_link')
                ->where('excel_seguimiento_drive_link', '!=', '');
        } else {
            $query->where('id', (int) $idContenedor);
        }

        $contenedores = $query->get(['id', 'carga', 'excel_seguimiento_drive_file_id']);

        if ($contenedores->isEmpty()) {
            return [
                'success' => false,
                'message' => $all
                    ? 'No hay consolidados vinculados a Drive.'
                    : 'Consolidado no encontrado o sin vínculo Drive.',
            ];
        }

        $ids = $contenedores->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();

        $fileIds = $contenedores->pluck('excel_seguimiento_drive_file_id')
            ->filter(function ($fileId) {
                return trim((string) $fileId) !== '';
            })
            ->unique()
            ->values();

        $driveDeleted = 0;
        $driveErrors = [];

        foreach ($fileIds as $fileId) {
            if ($this->driveService->deleteFileById((string) $fileId)) {
                $driveDeleted++;
            } else {
                $driveErrors[] = 'No se pudo borrar file_id ' . $fileId;
            }
        }

        $purged = ['deleted' => 0, 'errors' => []];
        if ($all) {
            $purged = $this->driveService->purgeSeguimientoRoot();
        }

        $this->clearSeguimientoLinkFields($ids);

        if (!$keepHistorico) {
            $this->clearSeguimientoHistorico($all ? null : (int) $idContenedor);
        }

        $encolados = 0;
        $revincularIds = [];

        if ($revincular) {
            if ($all) {
                $queueResult = $this->queueVincularPendientes();
                $encolados = (int) ($queueResult['encolados'] ?? 0);
                $revincularIds = $queueResult['ids'] ?? [];
            } else {
                $contenedor = Contenedor::find((int) $idContenedor);
                if ($contenedor && SeguimientoConsolidadoVincularEligibility::puedeVincular($contenedor)) {
                    $result = $this->queueVincular((int) $idContenedor);
                    if (!empty($result['success'])) {
                        $encolados = 1;
                        $revincularIds = [(int) $idContenedor];
                    }
                }
            }
        }

        $this->log('info', 'Reset seguimiento Drive completado', [
            'all' => $all,
            'ids' => $ids,
            'drive_deleted_by_id' => $driveDeleted,
            'drive_purged' => $purged['deleted'],
            'keep_historico' => $keepHistorico,
            'revincular_encolados' => $encolados,
        ]);

        return [
            'success' => true,
            'message' => 'Reset de Excel seguimiento en Drive completado.',
            'contenedores' => count($ids),
            'ids' => $ids,
            'drive_deleted_by_id' => $driveDeleted,
            'drive_purged' => (int) $purged['deleted'],
            'drive_errors' => array_merge($driveErrors, $purged['errors']),
            'historico_limpiado' => !$keepHistorico,
            'revincular_encolados' => $encolados,
            'revincular_ids' => $revincularIds,
        ];
    }

    /**
     * @param array<int, int> $ids
     */
    private function clearSeguimientoLinkFields(array $ids)
    {
        if (empty($ids)) {
            return;
        }

        DB::table('carga_consolidada_contenedor')
            ->whereIn('id', $ids)
            ->update([
                'excel_seguimiento_drive_file_id' => null,
                'excel_seguimiento_drive_link' => null,
                'excel_seguimiento_vinculado_at' => null,
                'excel_seguimiento_file_name' => null,
                'excel_seguimiento_link_status' => null,
                'excel_seguimiento_link_error' => null,
            ]);
    }

    /**
     * @param int|null $idContenedor null = todos los contenedores
     */
    private function clearSeguimientoHistorico($idContenedor = null)
    {
        if ($idContenedor !== null && $idContenedor > 0) {
            if (Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
                DB::table('contenedor_seguimiento_corte_periodos')
                    ->where('id_contenedor', (int) $idContenedor)
                    ->delete();
            }

            if (Schema::hasTable('contenedor_seguimiento_row_sync')) {
                DB::table('contenedor_seguimiento_row_sync')
                    ->where('id_contenedor', (int) $idContenedor)
                    ->delete();
            }

            return;
        }

        if (Schema::hasTable('contenedor_seguimiento_corte_clientes')) {
            DB::table('contenedor_seguimiento_corte_clientes')->delete();
        }

        if (Schema::hasTable('contenedor_seguimiento_corte_periodos')) {
            DB::table('contenedor_seguimiento_corte_periodos')->delete();
        }

        if (Schema::hasTable('contenedor_seguimiento_row_sync')) {
            DB::table('contenedor_seguimiento_row_sync')->delete();
        }
    }

    /**
     * @param string $step
     * @param SeguimientoConsolidadoFlowContext $flow
     * @param string $level
     * @param array<string, mixed> $context
     */
    private function logFlow($step, SeguimientoConsolidadoFlowContext $flow, $level = 'info', array $context = [])
    {
        $this->log($level, $step, array_merge($flow->baseContext(), [
            'step' => $step,
        ], $context));
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
