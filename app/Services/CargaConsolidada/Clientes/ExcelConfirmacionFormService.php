<?php

namespace App\Services\CargaConsolidada\Clientes;

use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedorItemExcelConf;
use App\Models\CargaConsolidada\CotizacionProveedorItems;
use App\Jobs\WhatsApp\SendCoordinacionWhatsAppJob;
use App\Services\Storage\S3ObjectStorageConnector;
use App\Support\WhatsApp\CoordinacionWhatsappPayload;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExcelConfirmacionFormService
{
    use UsesObjectStorage;

    private const S3_TEMPLATES_BASE = 'templates';

    private const TEMPLATE_RELATIVE = 'excel-confirmacion/EXCEL_DE_CONFIRMACION_GENERAL.xlsx';

    private const CAMPO_NOMBRE_COMERCIAL = 'NOMBRE COMERCIAL';

    private const CAMPO_FOTO = 'FOTO/IMAGEN';

    public function __construct(
        private ExcelConfirmacionDocumentosService $excelService
    ) {
    }

    public function findCotizacionByUuid(string $uuid): ?object
    {
        return DB::table('contenedor_consolidado_cotizacion')
            ->select('id', 'uuid', 'nombre', 'id_contenedor')
            ->where('uuid', $uuid)
            ->whereNull('deleted_at')
            ->first();
    }

    public function buildShowPayload(string $uuid): ?array
    {
        $cotizacion = $this->findCotizacionByUuid($uuid);
        if (!$cotizacion) {
            return null;
        }

        $contenedor = DB::table('carga_consolidada_contenedor')
            ->select('carga')
            ->where('id', $cotizacion->id_contenedor)
            ->first();

        $carga = $contenedor->carga ?? '';
        $cargaCode = is_numeric($carga) ? str_pad((string) $carga, 2, '0', STR_PAD_LEFT) : (string) $carga;

        $proveedores = CotizacionProveedor::where('id_cotizacion', $cotizacion->id)
            ->orderBy('id')
            ->get(['id', 'code_supplier', 'supplier', 'excel_conf_status', 'excel_conf_status_final', 'excel_conf_form_cerrado']);

        $proveedorIds = $proveedores->pluck('id')->all();
        $itemsByProveedor = CotizacionProveedorItems::whereIn('id_proveedor', $proveedorIds)
            ->orderBy('id')
            ->get()
            ->groupBy('id_proveedor');
        $excelConfItemsByProveedor = CotizacionProveedorItemExcelConf::whereIn('id_proveedor', $proveedorIds)
            ->orderBy('id')
            ->get()
            ->groupBy('id_proveedor');

        $proveedoresPayload = $proveedores->map(function (CotizacionProveedor $proveedor) use ($itemsByProveedor, $excelConfItemsByProveedor) {
            $excelConfItems = $excelConfItemsByProveedor->get($proveedor->id) ?? collect();
            $overlaysByOrigen = $excelConfItems
                ->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen !== null)
                ->keyBy('id_item_origen');

            $originalItems = ($itemsByProveedor->get($proveedor->id) ?? collect())
                ->map(function (CotizacionProveedorItems $item) use ($overlaysByOrigen) {
                    $overlay = $overlaysByOrigen->get($item->id);

                    return $this->mapOriginalItemPayload($item, $overlay instanceof CotizacionProveedorItemExcelConf ? $overlay : null);
                });

            $newExcelConfItems = $excelConfItems
                ->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen === null)
                ->map(fn (CotizacionProveedorItemExcelConf $item) => $this->mapExcelConfItemPayload($item));

            $items = $originalItems->concat($newExcelConfItems)->values();

            return [
                'id' => $proveedor->id,
                'code_supplier' => $proveedor->code_supplier,
                'supplier' => $proveedor->supplier,
                'excel_conf_status' => $proveedor->excel_conf_status,
                'excel_conf_status_final' => $proveedor->excel_conf_status_final,
                'excel_conf_form_cerrado' => strcasecmp((string) $proveedor->excel_conf_status, 'Revisado') === 0,
                'items' => $items,
            ];
        })->values();

        return [
            'uuid' => $cotizacion->uuid,
            'id' => $cotizacion->id,
            'carga' => $cargaCode,
            'nombre_cliente' => trim((string) ($cotizacion->nombre ?? '')),
            'proveedores' => $proveedoresPayload,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $proveedoresData
     */
    public function saveConfirmation(string $uuid, array $proveedoresData, bool $ignoreCerrado = false): array
    {
        $cotizacion = $this->findCotizacionByUuid($uuid);
        if (!$cotizacion) {
            return ['success' => false, 'code' => 'COTIZACION_NOT_FOUND', 'status' => 404];
        }

        $labelsMap = $this->excelService->getLabelsPorTipoProductoFiltradas();
        $proveedorIdsCotizacion = CotizacionProveedor::where('id_cotizacion', $cotizacion->id)
            ->pluck('id')
            ->all();

        DB::beginTransaction();

        try {
            $savedAny = false;
            foreach ($proveedoresData as $proveedorData) {
                $proveedorId = (int) ($proveedorData['id'] ?? 0);
                if (!in_array($proveedorId, $proveedorIdsCotizacion, true)) {
                    DB::rollBack();

                    return ['success' => false, 'code' => 'PROVEEDOR_INVALIDO', 'status' => 422];
                }

                // Revisado: el cliente no puede modificar ese proveedor.
                if (!$ignoreCerrado && $this->isProveedorCerrado($proveedorId)) {
                    continue;
                }

                $savedAny = true;

                $itemIdsProveedor = CotizacionProveedorItems::where('id_proveedor', $proveedorId)
                    ->pluck('id')
                    ->all();
                $excelConfKeptIds = [];

                foreach ($proveedorData['items'] ?? [] as $itemData) {
                    $itemId = (int) ($itemData['id'] ?? 0);
                    $isNewItem = (bool) ($itemData['is_new'] ?? false) || $itemId <= 0;

                    if ($isNewItem) {
                        $excelConfKeptIds[] = $this->upsertExcelConfItem(
                            $proveedorId,
                            (int) $cotizacion->id,
                            $itemId,
                            $itemData,
                            null
                        );
                        continue;
                    }

                    if (!in_array($itemId, $itemIdsProveedor, true)) {
                        DB::rollBack();

                        return ['success' => false, 'code' => 'ITEM_INVALIDO', 'status' => 422];
                    }

                    // Ítems de cotización: SOLO se guarda overlay en excel_conf (id_item_origen).
                    // Nunca se escribe sobre initial_name / initial_qty / initial_price ni otras columnas de cotización.
                    $excelConfKeptIds[] = $this->upsertExcelConfItem(
                        $proveedorId,
                        (int) $cotizacion->id,
                        0,
                        $itemData,
                        $itemId
                    );
                }

                $orphanQuery = CotizacionProveedorItemExcelConf::where('id_proveedor', $proveedorId);
                if ($excelConfKeptIds !== []) {
                    $orphanQuery->whereNotIn('id', $excelConfKeptIds);
                }
                $orphanQuery->delete();

                if ($ignoreCerrado) {
                    // Coordinación: completa → Recibido, incompleta → Pendiente (sin tocar Revisado).
                    $this->refreshProveedorExcelConfStatus($proveedorId, $labelsMap);
                } else {
                    // Cliente: si estaba Pendiente (u Observado), al guardar pasa a Recibido.
                    $this->markExcelConfRecibidoTrasGuardadoCliente($proveedorId);
                }
            }

            if (!$ignoreCerrado && !$savedAny) {
                DB::rollBack();

                return [
                    'success' => false,
                    'code' => 'FORMULARIO_CERRADO',
                    'status' => 423,
                ];
            }

            DB::commit();

            return ['success' => true, 'code' => 'GUARDADO_OK', 'status' => 200];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ExcelConfirmacionFormService::saveConfirmation — ' . $e->getMessage(), ['uuid' => $uuid]);

            return ['success' => false, 'code' => 'ERROR_INTERNO', 'status' => 500];
        }
    }

    /**
     * Notifica por WhatsApp (Meta + inbox) que el cliente envió el Excel de confirmación.
     * No lanza: el guardado ya quedó OK.
     */
    public function notifyClientExcelConfRecibido(string $uuid): void
    {
        try {
            $cotizacion = DB::table('contenedor_consolidado_cotizacion')
                ->select('id', 'telefono', 'id_contenedor')
                ->where('uuid', $uuid)
                ->whereNull('deleted_at')
                ->first();

            if (!$cotizacion) {
                return;
            }

            $telefono = preg_replace('/\s+/', '', (string) ($cotizacion->telefono ?? ''));
            if ($telefono === '') {
                Log::warning('ExcelConfirmacionFormService: sin teléfono para notificar Excel conf. recibido', [
                    'uuid' => $uuid,
                ]);

                return;
            }

            $contenedor = DB::table('carga_consolidada_contenedor')
                ->select('carga')
                ->where('id', $cotizacion->id_contenedor)
                ->first();

            $carga = $contenedor->carga ?? '';
            $cargaCode = is_numeric($carga)
                ? str_pad((string) $carga, 2, '0', STR_PAD_LEFT)
                : (string) $carga;

            $payload = CoordinacionWhatsappPayload::docsExcelConfRecibido($telefono, $cargaCode);
            SendCoordinacionWhatsAppJob::dispatch($payload);

            Log::info('ExcelConfirmacionFormService: notificado Excel conf. recibido', [
                'uuid' => $uuid,
                'carga' => $cargaCode,
            ]);
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionFormService::notifyClientExcelConfRecibido — ' . $e->getMessage(), [
                'uuid' => $uuid,
            ]);
        }
    }

    public function isProveedorCerrado(int $proveedorId): bool
    {
        $status = CotizacionProveedor::where('id', $proveedorId)->value('excel_conf_status');

        return strcasecmp((string) $status, 'Revisado') === 0;
    }

    public function setProveedorCerrado(int $proveedorId, bool $cerrado): bool
    {
        $proveedor = CotizacionProveedor::find($proveedorId);
        if (!$proveedor) {
            return false;
        }

        $proveedor->excel_conf_form_cerrado = $cerrado;
        if ($cerrado) {
            $proveedor->excel_conf_status = 'Revisado';
        } elseif (strcasecmp((string) $proveedor->excel_conf_status, 'Revisado') === 0) {
            $proveedor->excel_conf_status = 'Recibido';
        }

        if (!$proveedor->save()) {
            return false;
        }

        if ($cerrado) {
            $this->generateAndStoreExcelConfirmacion($proveedorId);
        }

        return true;
    }

    /**
     * Genera el Excel individual del proveedor y lo guarda en excel_confirmacion (documentación).
     */
    public function generateAndStoreExcelConfirmacion(int $proveedorId): ?string
    {
        try {
            $payload = $this->buildProveedorExportPayload($proveedorId);
            if (!$payload) {
                return null;
            }

            $templatePath = $this->resolveTemplatePath();
            if ($templatePath === null) {
                Log::warning('ExcelConfirmacionFormService::generateAndStoreExcelConfirmacion — plantilla no disponible', [
                    'id_proveedor' => $proveedorId,
                ]);
                return null;
            }

            $outputDir = storage_path('app/temp/excel-confirmacion');
            if (!is_dir($outputDir)) {
                @mkdir($outputDir, 0775, true);
            }

            $codeSupplier = (string) ($payload['code_supplier'] ?? '');
            $suffix = $codeSupplier !== '' ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $codeSupplier) : ('prov_' . $proveedorId);
            $fileName = 'excel_confirmacion_' . $suffix . '_' . time() . '.xlsx';
            $fullPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

            $ok = $this->excelService->generarArchivoPorProveedor($templatePath, $fullPath, $payload);
            if (!$ok || !file_exists($fullPath)) {
                Log::error('ExcelConfirmacionFormService::generateAndStoreExcelConfirmacion — no se generó el archivo', [
                    'id_proveedor' => $proveedorId,
                ]);
                return null;
            }

            $contents = file_get_contents($fullPath);
            @unlink($fullPath);
            if ($contents === false) {
                return null;
            }

            $relativePath = 'assets/images/agentecompra/' . $fileName;
            $stored = $this->storagePutContents($relativePath, $contents);

            $proveedor = CotizacionProveedor::find($proveedorId);
            if (!$proveedor) {
                return $stored;
            }

            if (!empty($proveedor->excel_confirmacion)) {
                try {
                    $oldPath = $this->storageUploadPathFromDb($proveedor->excel_confirmacion) ?: $proveedor->excel_confirmacion;
                    if ($oldPath && $this->objectStorage()->exists($oldPath)) {
                        $this->objectStorage()->delete($oldPath);
                    }
                } catch (\Throwable $e) {
                    Log::warning('ExcelConfirmacionFormService::generateAndStoreExcelConfirmacion — no se pudo borrar archivo previo: ' . $e->getMessage());
                }
            }

            $proveedor->excel_confirmacion = $stored;
            $proveedor->save();

            return $stored;
        } catch (\Throwable $e) {
            Log::error('ExcelConfirmacionFormService::generateAndStoreExcelConfirmacion — ' . $e->getMessage(), [
                'id_proveedor' => $proveedorId,
            ]);
            return null;
        }
    }

    public function buildProveedorExportPayload(int $proveedorId): ?array
    {
        $proveedor = CotizacionProveedor::with('items')->find($proveedorId);
        if (!$proveedor) {
            return null;
        }

        $excelConfItems = CotizacionProveedorItemExcelConf::where('id_proveedor', $proveedorId)
            ->orderBy('id')
            ->get();
        $overlaysByOrigen = $excelConfItems
            ->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen !== null)
            ->keyBy('id_item_origen');

        $originalItems = $proveedor->items->map(function (CotizacionProveedorItems $item) use ($overlaysByOrigen) {
            $overlay = $overlaysByOrigen->get($item->id);

            return $this->mapOriginalItemPayload(
                $item,
                $overlay instanceof CotizacionProveedorItemExcelConf ? $overlay : null,
                forExport: true
            );
        });
        $newExcelConfItems = $excelConfItems
            ->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen === null)
            ->map(fn (CotizacionProveedorItemExcelConf $item) => $this->mapExcelConfItemPayload($item, forExport: true));

        return [
            'id' => $proveedor->id,
            'code_supplier' => $proveedor->code_supplier,
            'items' => $originalItems->concat($newExcelConfItems)->values()->all(),
        ];
    }

    /**
     * @return array{uuid: string, nombre_cliente: string, proveedores: array<int, array<string, mixed>>}|null
     */
    public function buildCotizacionExportPayload(string $uuid): ?array
    {
        $cotizacion = $this->findCotizacionByUuid($uuid);
        if (!$cotizacion) {
            return null;
        }

        $proveedores = CotizacionProveedor::where('id_cotizacion', $cotizacion->id)
            ->orderBy('id')
            ->get();

        $payloads = [];
        foreach ($proveedores as $proveedor) {
            $payload = $this->buildProveedorExportPayload((int) $proveedor->id);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        if ($payloads === []) {
            return null;
        }

        return [
            'uuid' => $uuid,
            'nombre_cliente' => (string) ($cotizacion->nombre ?? ''),
            'proveedores' => $payloads,
        ];
    }

    public function resolveTemplatePath(): ?string
    {
        $s3Relative = self::S3_TEMPLATES_BASE . '/' . self::TEMPLATE_RELATIVE;
        $fromS3 = $this->resolveReadableFileFromS3($s3Relative);
        if ($fromS3 !== null) {
            return $fromS3;
        }

        foreach ($this->localTemplateCandidates() as $localPath) {
            if (is_file($localPath)) {
                return $localPath;
            }
        }

        Log::warning('ExcelConfirmacionFormService: plantilla no encontrada en S3 ni local', [
            's3' => $s3Relative,
            'local' => $this->localTemplateCandidates(),
        ]);

        return null;
    }

    private function resolveReadableFileFromS3(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $storage = $this->objectStorage();
        if (!$storage instanceof S3ObjectStorageConnector || config('object_storage.upload_disk') !== 's3') {
            return null;
        }

        try {
            if (!$storage->existsOnS3($relative)) {
                return null;
            }

            $path = $storage->localPath($relative);

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            Log::warning('ExcelConfirmacionFormService: fallo lectura S3', [
                'relative' => $relative,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function localTemplateCandidates(): array
    {
        $file = self::TEMPLATE_RELATIVE;

        return [
            storage_path('app/public/' . self::S3_TEMPLATES_BASE . '/' . $file),
            storage_path('app/' . self::S3_TEMPLATES_BASE . '/' . $file),
            storage_path('app/' . $file),
        ];
    }

    /**
     * Cuando el cliente guarda un proveedor en Pendiente/Observado (o vacío),
     * el estado Excel Conf. pasa a Recibido. No toca Revisado.
     */
    private function markExcelConfRecibidoTrasGuardadoCliente(int $proveedorId): void
    {
        $proveedor = CotizacionProveedor::find($proveedorId);
        if (!$proveedor) {
            return;
        }

        $current = trim((string) ($proveedor->excel_conf_status ?? ''));
        if (strcasecmp($current, 'Revisado') === 0) {
            return;
        }

        // Solo avanzamos desde Pendiente / Observado / vacío (no regresamos desde Recibido).
        $shouldMarkRecibido = $current === ''
            || strcasecmp($current, 'Pendiente') === 0
            || strcasecmp($current, 'Observado') === 0;

        if (!$shouldMarkRecibido) {
            return;
        }

        $proveedor->excel_conf_status = 'Recibido';
        $proveedor->excel_conf_form_cerrado = false;
        $proveedor->save();
    }

    /**
     * @param  array<string, array<int, string>>  $labelsMap
     */
    private function refreshProveedorExcelConfStatus(int $proveedorId, array $labelsMap): void
    {
        $currentStatus = CotizacionProveedor::where('id', $proveedorId)->value('excel_conf_status');
        // Si ya está Revisado, no reabrir automáticamente por un guardado de coordinación.
        if (strcasecmp((string) $currentStatus, 'Revisado') === 0) {
            return;
        }

        $originalItems = CotizacionProveedorItems::where('id_proveedor', $proveedorId)->get();
        $excelConfItems = CotizacionProveedorItemExcelConf::where('id_proveedor', $proveedorId)->get();
        $overlaysByOrigen = $excelConfItems
            ->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen !== null)
            ->keyBy('id_item_origen');
        $newItems = $excelConfItems->filter(fn (CotizacionProveedorItemExcelConf $item) => $item->id_item_origen === null);

        if ($originalItems->isEmpty() && $newItems->isEmpty()) {
            return;
        }

        $allComplete = true;

        foreach ($originalItems as $item) {
            $overlay = $overlaysByOrigen->get($item->id);
            if (!$overlay instanceof CotizacionProveedorItemExcelConf || !$this->isItemComplete($overlay, $labelsMap)) {
                $allComplete = false;
                break;
            }
        }

        if ($allComplete) {
            foreach ($newItems as $item) {
                if (!$this->isItemComplete($item, $labelsMap)) {
                    $allComplete = false;
                    break;
                }
            }
        }

        CotizacionProveedor::where('id', $proveedorId)->update([
            'excel_conf_status' => $allComplete ? 'Recibido' : 'Pendiente',
            'excel_conf_form_cerrado' => false,
        ]);
    }

    /**
     * @param  CotizacionProveedorItems|CotizacionProveedorItemExcelConf  $item
     * @param  array<string, array<int, string>>  $labelsMap
     */
    private function isItemComplete($item, array $labelsMap): bool
    {
        if ($item->confirmacion_qty === null || $item->confirmacion_precio === null) {
            return false;
        }

        $tipo = strtoupper((string) ($item->tipo_producto ?? 'GENERAL'));
        $labels = $labelsMap[$tipo] ?? $labelsMap['GENERAL'] ?? [];
        $caracteristicas = $this->normalizeCaracteristicasKeys(
            is_array($item->caracteristicas) ? $item->caracteristicas : []
        );

        foreach ($labels as $label) {
            $normalized = strtolower(rtrim(trim((string) $label), ':'));
            // Marca y Modelo son opcionales (en front se llenan con S/M al confirmar guardar)
            if ($normalized === 'marca' || $normalized === 'modelo') {
                continue;
            }

            $value = trim((string) ($caracteristicas[$label] ?? ''));
            if ($value === '') {
                return false;
            }

            $unitKey = $this->unidadKeyForLabel((string) $label);
            if ($unitKey !== null && trim((string) ($caracteristicas[$unitKey] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function upsertExcelConfItem(
        int $proveedorId,
        int $cotizacionId,
        int $itemId,
        array $itemData,
        ?int $idItemOrigen = null
    ): int {
        $existingRow = null;
        if ($idItemOrigen !== null) {
            $existingRow = CotizacionProveedorItemExcelConf::where('id_proveedor', $proveedorId)
                ->where('id_item_origen', $idItemOrigen)
                ->first();
        } elseif ($itemId > 0) {
            $existingRow = CotizacionProveedorItemExcelConf::where('id', $itemId)
                ->where('id_proveedor', $proveedorId)
                ->whereNull('id_item_origen')
                ->first();
        }

        $caracteristicas = $this->sanitizeAndApplyFoto(
            $this->normalizeCaracteristicasKeys(
                is_array($itemData['caracteristicas'] ?? null) ? $itemData['caracteristicas'] : []
            ),
            $itemData['foto_file'] ?? null,
            $proveedorId,
            is_array($existingRow?->caracteristicas) ? $existingRow->caracteristicas : null
        );
        $payload = [
            'id_cotizacion' => $cotizacionId,
            'id_proveedor' => $proveedorId,
            'id_item_origen' => $idItemOrigen,
            'initial_name' => $this->resolveNombreComercial($caracteristicas),
            'tipo_producto' => strtoupper((string) ($itemData['tipo_producto'] ?? 'GENERAL')),
            'caracteristicas' => $caracteristicas,
            'confirmacion_qty' => $itemData['qty'] ?? null,
            'confirmacion_precio' => $itemData['precio_unitario'] ?? null,
        ];

        if ($idItemOrigen !== null) {
            if ($existingRow) {
                $origen = CotizacionProveedorItems::where('id', $idItemOrigen)->first();
                if ($origen && filled($origen->initial_name)) {
                    $payload['initial_name'] = $origen->initial_name;
                    $payload['tipo_producto'] = strtoupper((string) ($origen->tipo_producto ?: $payload['tipo_producto']));
                }
                $existingRow->update($payload);

                return (int) $existingRow->id;
            }

            $origen = CotizacionProveedorItems::where('id', $idItemOrigen)->first();
            if ($origen && filled($origen->initial_name)) {
                $payload['initial_name'] = $origen->initial_name;
                $payload['tipo_producto'] = strtoupper((string) ($origen->tipo_producto ?: $payload['tipo_producto']));
            }

            $created = CotizacionProveedorItemExcelConf::create($payload);

            return (int) $created->id;
        }

        if ($existingRow) {
            $existingRow->update($payload);

            return (int) $existingRow->id;
        }

        $created = CotizacionProveedorItemExcelConf::create($payload);

        return (int) $created->id;
    }

    /**
     * @param  array<string, mixed>  $caracteristicas
     */
    private function resolveNombreComercial(array $caracteristicas): ?string
    {
        $nombre = trim((string) ($caracteristicas[self::CAMPO_NOMBRE_COMERCIAL] ?? ''));

        return $nombre !== '' ? $nombre : null;
    }

    private function mapOriginalItemPayload(
        CotizacionProveedorItems $item,
        ?CotizacionProveedorItemExcelConf $overlay = null,
        bool $forExport = false
    ): array {
        $payload = [
            'id' => $item->id,
            'initial_name' => $item->initial_name,
            'tipo_producto' => $item->tipo_producto,
            'initial_qty' => $item->initial_qty,
            'initial_price' => $item->initial_price,
            'caracteristicas' => $this->resolveFotoDisplayValue(
                $this->normalizeCaracteristicasKeys(
                    is_array($overlay?->caracteristicas) ? $overlay->caracteristicas : []
                )
            ),
            'qty' => $overlay?->confirmacion_qty,
            'precio_unitario' => $overlay?->confirmacion_precio,
            'is_new' => false,
        ];

        if ($forExport) {
            unset($payload['is_new']);
        }

        return $payload;
    }

    private function mapExcelConfItemPayload(CotizacionProveedorItemExcelConf $item, bool $forExport = false): array
    {
        $caracteristicas = $this->resolveFotoDisplayValue(
            $this->normalizeCaracteristicasKeys(
                is_array($item->caracteristicas) ? $item->caracteristicas : []
            )
        );
        $payload = [
            'id' => $item->id,
            'initial_name' => $item->initial_name ?? $this->resolveNombreComercial($caracteristicas) ?? '',
            'tipo_producto' => $item->tipo_producto,
            'initial_qty' => null,
            'initial_price' => null,
            'caracteristicas' => $caracteristicas,
            'qty' => $item->confirmacion_qty,
            'precio_unitario' => $item->confirmacion_precio,
            'is_new' => true,
        ];

        if ($forExport) {
            unset($payload['is_new']);
        }

        return $payload;
    }

    /**
     * Migra keys legacy y asegura claves de unidad (misma estructura para overlays y productos nuevos).
     *
     * @param  array<string, mixed>  $caracteristicas
     * @return array<string, mixed>
     */
    private function normalizeCaracteristicasKeys(array $caracteristicas): array
    {
        $renames = [
            'Tamaño:' => 'Tamaño (Producto):',
            'Tamaño del producto:' => 'Tamaño (Producto):',
            'Tamaño (Metros):' => 'Tamaño (Producto):',
            'Capacidad (ml o kg):' => 'Capacidad:',
            'Peso Neto:' => 'Peso Neto (Producto):',
            'Peso' => 'Peso Neto (Producto):',
            'Pares o Piezas:' => 'Unidad de medida:',
        ];

        $wasTamanoMetros = false;
        foreach (array_keys($caracteristicas) as $key) {
            $normalizedKey = strtolower(rtrim(trim((string) $key), ':'));
            if ($normalizedKey === 'tamaño (metros)') {
                $wasTamanoMetros = true;
            }
        }

        $normalized = [];
        foreach ($caracteristicas as $key => $value) {
            $canonical = $renames[(string) $key] ?? (string) $key;
            $incoming = trim((string) $value);
            $existing = trim((string) ($normalized[$canonical] ?? ''));
            if ($existing === '' && $incoming !== '') {
                $normalized[$canonical] = $incoming;
            } elseif (!array_key_exists($canonical, $normalized)) {
                $normalized[$canonical] = $incoming;
            }
        }

        if ($wasTamanoMetros && trim((string) ($normalized['Unidad Tamaño:'] ?? '')) === '') {
            $normalized['Unidad Tamaño:'] = 'metros';
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $proveedoresData
     * @return array<int, array<string, mixed>>
     */
    public function attachFotoFilesFromRequest(array $proveedoresData, $request): array
    {
        $fotos = $request->file('fotos');
        if (!is_array($fotos)) {
            return $proveedoresData;
        }

        foreach ($proveedoresData as $pIdx => $proveedorData) {
            $proveedorId = (int) ($proveedorData['id'] ?? 0);
            if ($proveedorId <= 0) {
                continue;
            }

            $provFotos = $fotos[(string) $proveedorId] ?? $fotos[$proveedorId] ?? null;
            if (!is_array($provFotos)) {
                continue;
            }

            foreach ($proveedorData['items'] ?? [] as $iIdx => $itemData) {
                $itemId = (int) ($itemData['id'] ?? 0);
                $file = $provFotos[(string) $itemId] ?? $provFotos[$itemId] ?? null;
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $proveedoresData[$pIdx]['items'][$iIdx]['foto_file'] = $file;
                }
            }
        }

        return $proveedoresData;
    }

    /**
     * @param  array<string, mixed>  $caracteristicas
     * @return array<string, mixed>
     */
    private function sanitizeAndApplyFoto(
        array $caracteristicas,
        $fotoFile,
        int $proveedorId,
        ?array $existingCaracteristicas = null
    ): array {
        $fotoKey = self::CAMPO_FOTO;
        $incomingRaw = '';
        foreach ($caracteristicas as $key => $value) {
            $normalized = strtolower(rtrim(trim((string) $key), ':'));
            if ($normalized !== 'foto/imagen') {
                continue;
            }
            $fotoKey = (string) $key;
            $incomingRaw = trim((string) $value);
            break;
        }

        $existingFoto = '';
        if (is_array($existingCaracteristicas)) {
            foreach ($existingCaracteristicas as $key => $value) {
                $normalized = strtolower(rtrim(trim((string) $key), ':'));
                if ($normalized !== 'foto/imagen') {
                    continue;
                }
                $candidate = trim((string) $value);
                if (
                    $candidate !== ''
                    && stripos($candidate, 'data:image/') !== 0
                    && !filter_var($candidate, FILTER_VALIDATE_URL)
                ) {
                    $existingFoto = $candidate;
                }
                break;
            }
        }

        if ($fotoFile instanceof UploadedFile && $fotoFile->isValid()) {
            $ext = strtolower($fotoFile->getClientOriginalExtension() ?: 'jpg');
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
            $filename = 'excel_conf_' . $proveedorId . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
            $stored = $this->storageStoreUpload($fotoFile, 'excel-confirmacion/fotos', $filename);
            $caracteristicas[$fotoKey] = $stored;

            return $caracteristicas;
        }

        $isDisplayUrlOrBase64 = $incomingRaw !== ''
            && (stripos($incomingRaw, 'data:image/') === 0 || (bool) filter_var($incomingRaw, FILTER_VALIDATE_URL));

        if ($isDisplayUrlOrBase64) {
            $caracteristicas[$fotoKey] = $existingFoto;
        } elseif ($incomingRaw === '') {
            $caracteristicas[$fotoKey] = '';
        } else {
            $caracteristicas[$fotoKey] = $incomingRaw;
        }

        return $caracteristicas;
    }

    /**
     * @param  array<string, mixed>  $caracteristicas
     * @return array<string, mixed>
     */
    private function resolveFotoDisplayValue(array $caracteristicas): array
    {
        foreach ($caracteristicas as $key => $value) {
            $normalized = strtolower(rtrim(trim((string) $key), ':'));
            if ($normalized !== 'foto/imagen') {
                continue;
            }

            $foto = trim((string) $value);
            if ($foto === '') {
                break;
            }

            if (stripos($foto, 'data:image/') === 0) {
                $caracteristicas[$key] = '';
                break;
            }

            if (filter_var($foto, FILTER_VALIDATE_URL)) {
                break;
            }

            $url = $this->objectStorage()->url($foto);
            if (is_string($url) && $url !== '') {
                $caracteristicas[$key] = $url;
            }
            break;
        }

        return $caracteristicas;
    }

    private function unidadKeyForLabel(string $label): ?string
    {
        $normalized = strtolower(rtrim(trim($label), ':'));

        return match ($normalized) {
            'tamaño (producto)', 'tamaño', 'tamaño del producto', 'tamaño (metros)' => 'Unidad Tamaño:',
            'capacidad', 'capacidad (ml o kg)' => 'Unidad Capacidad:',
            'peso neto (producto)', 'peso neto', 'peso' => 'Unidad Peso Neto:',
            default => null,
        };
    }
}
