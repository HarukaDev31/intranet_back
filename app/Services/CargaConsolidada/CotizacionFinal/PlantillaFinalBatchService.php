<?php

namespace App\Services\CargaConsolidada\CotizacionFinal;

use App\Events\PlantillaFinalBatchFinished;
use App\Http\Controllers\CargaConsolidada\CotizacionFinal\CotizacionFinalController;
use App\Jobs\GenerateMassiveExcelPayrollsJob;
use App\Models\CargaConsolidada\ConsolidadoPlantillaFinalBatch;
use Illuminate\Http\UploadedFile;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class PlantillaFinalBatchService
{
    use UsesObjectStorage;
    /** @var CotizacionFinalController */
    protected $cotizacionFinalController;

    public function __construct(CotizacionFinalController $cotizacionFinalController)
    {
        $this->cotizacionFinalController = $cotizacionFinalController;
    }

    public function enqueue($file, $idContenedor, $createdBy = null)
    {
        $idContenedor = (int) $idContenedor;
        $clientesExcel = $this->countClientsInExcel($file);

        $plantillaRelative = $this->storageStoreUpload(
            $file,
            'plantillas-finales/uploads',
            $idContenedor . '_' . time() . '_' . uniqid() . '_' . $file->getClientOriginalName()
        );

        $zipRelative = 'plantillas-finales/zips/' . $idContenedor . '_' . time() . '_' . uniqid() . '.zip';

        $batch = ConsolidadoPlantillaFinalBatch::create([
            'id_contenedor' => $idContenedor,
            'clientes_excel' => $clientesExcel,
            'clientes_completados' => 0,
            'clientes_error' => 0,
            'estado' => 'PENDING',
            'created_by' => $createdBy ? (int) $createdBy : null,
            'plantilla_url' => $plantillaRelative,
            'zip_path' => $zipRelative,
            'nombre_plantilla' => $file->getClientOriginalName(),
        ]);

        GenerateMassiveExcelPayrollsJob::dispatch((int) $batch->id);

        return [
            'batch_id' => (int) $batch->id,
            'estado' => $batch->estado,
            'id_contenedor' => $idContenedor,
        ];
    }

    public function processBatchById($batchId)
    {
        $batch = ConsolidadoPlantillaFinalBatch::find((int) $batchId);
        if (!$batch) {
            throw new \InvalidArgumentException('Lote de plantilla final no encontrado.');
        }

        if ($batch->estado === 'COMPLETED') {
            return $batch;
        }

        $batch->update([
            'fecha_inicio' => now(),
            'estado' => 'PENDING',
        ]);

        try {
            $stats = $this->runMassiveGeneration($batch);
            $batch->update([
                'clientes_completados' => (int) $stats['completados'],
                'clientes_error' => (int) $stats['errores'],
                'detalle_json' => $stats['detalle'],
                'fecha_fin' => now(),
                'estado' => 'COMPLETED',
                'mensaje_error' => null,
            ]);

            event(new PlantillaFinalBatchFinished(
                $batch->fresh(),
                'Generación de plantillas finales completada.'
            ));
        } catch (\Throwable $e) {
            Log::error('PlantillaFinalBatchService::processBatchById', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            $batch->update([
                'fecha_fin' => now(),
                'estado' => 'FAILED',
                'mensaje_error' => $e->getMessage(),
            ]);

            event(new PlantillaFinalBatchFinished(
                $batch->fresh(),
                'Error al generar plantillas finales: ' . $e->getMessage()
            ));

            throw $e;
        }

        return $batch->fresh();
    }

    public function listByContenedor($idContenedor, $limit = 100)
    {
        $limit = max(1, min((int) $limit, 500));

        return ConsolidadoPlantillaFinalBatch::query()
            ->where('id_contenedor', (int) $idContenedor)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ConsolidadoPlantillaFinalBatch $batch) {
                return $this->mapBatchForApi($batch);
            })
            ->values()
            ->all();
    }

    public function findBatchOrFail($id)
    {
        $batch = ConsolidadoPlantillaFinalBatch::find((int) $id);
        if (!$batch) {
            throw new \InvalidArgumentException('Registro no encontrado.');
        }
        return $batch;
    }

    public function resolveStoragePath($relativePath)
    {
        $relativePath = ltrim((string) $relativePath, '/');
        if (!$this->objectStorage()->exists($relativePath)) {
            throw new \InvalidArgumentException('Archivo no encontrado.');
        }

        return $this->storageLocalPath($relativePath);
    }

    protected function mapBatchForApi(ConsolidadoPlantillaFinalBatch $batch)
    {
        return [
            'id' => (int) $batch->id,
            'id_contenedor' => (int) $batch->id_contenedor,
            'clientes_excel' => (int) $batch->clientes_excel,
            'clientes_completados' => (int) $batch->clientes_completados,
            'clientes_error' => (int) $batch->clientes_error,
            'detalle' => (int) $batch->clientes_completados . ' exitosos / ' . (int) $batch->clientes_error . ' con error',
            'detalle_json' => $batch->detalle_json ?: ['exitosos' => [], 'fallidos' => []],
            'estado' => $batch->estado,
            'fecha_inicio' => $batch->fecha_inicio ? $batch->fecha_inicio->toIso8601String() : null,
            'fecha_fin' => $batch->fecha_fin ? $batch->fecha_fin->toIso8601String() : null,
            'created_by' => $batch->created_by ? (int) $batch->created_by : null,
            'plantilla_url' => $batch->plantilla_url,
            'zip_path' => $batch->zip_path,
            'nombre_plantilla' => $batch->nombre_plantilla,
            'has_plantilla' => !empty($batch->plantilla_url),
            'has_zip' => !empty($batch->zip_path) && $this->objectStorage()->exists($batch->zip_path),
            'mensaje_error' => $batch->mensaje_error,
            'created_at' => $batch->created_at ? $batch->created_at->toIso8601String() : null,
        ];
    }

    protected function countClientsInExcel($file)
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $worksheet->getHighestRow();
            $count = 0;
            for ($row = 2; $row <= $highestRow; $row++) {
                $name = trim((string) $worksheet->getCell('A' . $row)->getValue());
                if ($name !== '') {
                    $count++;
                }
            }
            return $count;
        } catch (\Exception $e) {
            Log::warning('No se pudo contar clientes del excel: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Ejecuta la generación masiva (lógica extraída del controlador).
     *
     * @return array{completados:int,errores:int}
     */
    protected function runMassiveGeneration(ConsolidadoPlantillaFinalBatch $batch)
    {
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '2048M');

        $idContainer = (int) $batch->id_contenedor;
        Log::info('PlantillaFinalBatchService: inicio generación masiva', [
            'batch_id' => $batch->id,
            'id_contenedor' => $idContainer,
            'upload_disk' => config('object_storage.upload_disk'),
            's3_bucket' => config('filesystems.disks.s3.bucket'),
        ]);

        $excelFullPath = $this->resolveStoragePath($batch->plantilla_url);
        $zipRelative = ltrim($batch->zip_path, '/');
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $zipFullPath = $tempDir . DIRECTORY_SEPARATOR . basename($zipRelative);
        if (file_exists($zipFullPath)) {
            unlink($zipFullPath);
        }

        $uploaded = new UploadedFile(
            $excelFullPath,
            $batch->nombre_plantilla ?: basename($excelFullPath),
            null,
            null,
            true
        );

        $controller = $this->cotizacionFinalController;
        $data = $controller->getMassiveExcelData($uploaded);

        $result = DB::table('contenedor_consolidado_cotizacion as cc')
            ->join('contenedor_consolidado_tipo_cliente as tc', 'cc.id_tipo_cliente', '=', 'tc.id')
            ->select([
                'cc.id',
                'cc.tarifa',
                'cc.nombre',
                'tc.id as id_tipo_cliente',
                'tc.name as tipoCliente',
                'cc.correo',
                'cc.vol_selected',
                'cc.volumen',
                'cc.volumen_china',
                'cc.volumen_doc',
            ])
            ->where('id_contenedor', $idContainer)
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereNotNull('estado_cliente')
            ->whereNull('cc.deleted_at')
            ->whereNull('id_cliente_importacion')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('contenedor_consolidado_cotizacion_proveedores')
                    ->whereRaw('contenedor_consolidado_cotizacion_proveedores.id_cotizacion = cc.id');
            })
            ->get();

        foreach ($data as &$cliente) {
            $nombreCliente = $cliente['cliente']['nombre'];
            $item = $this->resolveCotizacionMatchForCliente($nombreCliente, $result);

            if ($item) {
                $cliente['cliente']['tarifa'] = $item->tarifa ?? 0;
                $cliente['cliente']['correo'] = $item->correo ?? '';
                $cliente['cliente']['tipo_cliente'] = $item->tipoCliente ?? '';
                $cliente['cliente']['id_tipo_cliente'] = $item->id_tipo_cliente ?? 0;
                $cliente['id'] = $item->id;

                $volumenAsignado = 0;
                if ($item->vol_selected == 'volumen' && is_numeric($item->volumen)) {
                    $volumenAsignado = (float) $item->volumen;
                } elseif ($item->vol_selected == 'volumen_china' && is_numeric($item->volumen_china)) {
                    $volumenAsignado = (float) $item->volumen_china;
                } elseif ($item->vol_selected == 'volumen_doc' && is_numeric($item->volumen_doc)) {
                    $volumenAsignado = (float) $item->volumen_doc;
                } elseif (is_numeric($item->volumen) && $item->volumen > 0) {
                    $volumenAsignado = (float) $item->volumen;
                } elseif (is_numeric($item->volumen_china) && $item->volumen_china > 0) {
                    $volumenAsignado = (float) $item->volumen_china;
                } elseif (is_numeric($item->volumen_doc) && $item->volumen_doc > 0) {
                    $volumenAsignado = (float) $item->volumen_doc;
                }
                $cliente['cliente']['volumen'] = $volumenAsignado;
            } else {
                $cliente['cliente']['tarifa'] = 0;
                $cliente['cliente']['correo'] = '';
                $cliente['cliente']['tipo_cliente'] = '';
                $cliente['cliente']['id_tipo_cliente'] = 0;
                $cliente['cliente']['volumen'] = 0;
                $cliente['id'] = 0;
            }
        }
        unset($cliente);

        $zip = new ZipArchive();
        $zipResult = $zip->open($zipFullPath, ZipArchive::CREATE);
        if ($zipResult !== true) {
            throw new \Exception('No se pudo crear el archivo ZIP (código ' . $zipResult . ')');
        }

        $processedCount = 0;
        $errorCount = 0;
        $totalExcel = is_array($data) ? count($data) : 0;
        $detalle = $this->buildEmptyDetalle();

        foreach ($data as $value) {
            $nombre = $this->clienteNombreFromValue($value);

            if (!isset($value['cliente']['tarifa']) || $value['cliente']['tarifa'] == 0) {
                $errorCount++;
                $detalle['fallidos'][] = [
                    'nombre' => $nombre,
                    'motivo' => 'Sin tarifa válida',
                ];
                continue;
            }
            if (!isset($value['id']) || $value['id'] == 0) {
                $errorCount++;
                $detalle['fallidos'][] = [
                    'nombre' => $nombre,
                    'motivo' => 'No coincide con un cliente confirmado del contenedor',
                ];
                continue;
            }
            if (!isset($value['cliente']['volumen']) || $value['cliente']['volumen'] == 0) {
                $errorCount++;
                $detalle['fallidos'][] = [
                    'nombre' => $nombre,
                    'motivo' => 'Sin volumen válido',
                ];
                continue;
            }
            if (!isset($value['cliente']['productos']) || empty($value['cliente']['productos'])) {
                $errorCount++;
                $detalle['fallidos'][] = [
                    'nombre' => $nombre,
                    'motivo' => 'Sin productos en el Excel',
                ];
                continue;
            }

            try {
                $templatePath = public_path('assets/templates/Boleta_Template.xlsx');
                if (!file_exists($templatePath)) {
                    throw new \Exception('Plantilla de cotización no encontrada');
                }
                $objPHPExcel = IOFactory::load($templatePath);
                $genResult = $controller->getFinalCotizacionExcelv2($objPHPExcel, $value, $idContainer);
                Log::info('GenResult: ' . json_encode($genResult));
                if (!$genResult || !isset($genResult['excel_file_name'], $genResult['excel_file_path'])) {
                    $errorCount++;
                    $detalle['fallidos'][] = [
                        'nombre' => $nombre,
                        'motivo' => 'No se pudo generar el Excel de cotización final',
                    ];
                    continue;
                }

                $excelStoragePath = (string) $genResult['excel_file_path'];
                if (!$this->objectStorage()->exists($excelStoragePath)) {
                    throw new \RuntimeException(
                        'Excel generado no encontrado en almacenamiento ('
                        . config('object_storage.upload_disk') . '): ' . $excelStoragePath
                    );
                }

                $idCotizacion = (int) ($value['id'] ?? 0);
                $idFromGen = isset($genResult['id']) ? (int) $genResult['id'] : 0;
                if ($idFromGen > 0) {
                    $idCotizacion = $idFromGen;
                }

                $persisted = $this->persistCotizacionFinalLink($idCotizacion, $idContainer, $genResult, $nombre);
                if (!$persisted) {
                    $errorCount++;
                    $detalle['fallidos'][] = [
                        'nombre' => $nombre,
                        'motivo' => 'No se pudo vincular la cotización final en base de datos',
                    ];
                    continue;
                }

                $fullExcelPath = $this->storageLocalPath((string) $genResult['excel_file_path']);
                if (file_exists($fullExcelPath)) {
                    if (!$zip->addFile($fullExcelPath, $genResult['excel_file_name'])) {
                        Log::warning('PlantillaFinalBatchService: Excel generado pero no se pudo agregar al ZIP', [
                            'batch_id' => $batch->id,
                            'id_cotizacion' => $idCotizacion,
                            'archivo' => $genResult['excel_file_name'],
                        ]);
                    }
                } else {
                    Log::warning('PlantillaFinalBatchService: cotización vinculada pero archivo Excel no encontrado para ZIP', [
                        'batch_id' => $batch->id,
                        'id_cotizacion' => $idCotizacion,
                        'path' => $genResult['excel_file_path'],
                    ]);
                }

                $processedCount++;
                $detalle['exitosos'][] = [
                    'nombre' => $nombre,
                    'id_cotizacion' => $idCotizacion,
                    'archivo' => isset($genResult['excel_file_name']) ? (string) $genResult['excel_file_name'] : null,
                ];
            } catch (\Exception $e) {
                Log::error('Error procesando cliente en batch plantilla final: ' . $e->getMessage());
                $errorCount++;
                $detalle['fallidos'][] = [
                    'nombre' => $nombre,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        if ($zip->numFiles === 0) {
            $infoContent = "No se encontraron clientes válidos para procesar.\n\nTotal en Excel: {$totalExcel}\nProcesados: {$processedCount}\n";
            $zip->addFromString('INFORMACION.txt', $infoContent);
        }

        $zip->close();
        ini_set('memory_limit', $originalMemoryLimit);
        gc_collect_cycles();

        if (!file_exists($zipFullPath) || filesize($zipFullPath) === 0) {
            throw new \Exception('El archivo ZIP no se generó correctamente');
        }

        $this->storagePutContents($zipRelative, file_get_contents($zipFullPath));
        @unlink($zipFullPath);

        return [
            'completados' => $processedCount,
            'errores' => $errorCount,
            'detalle' => $detalle,
        ];
    }

    /**
     * Persiste cotizacion_final_url y montos en contenedor_consolidado_cotizacion.
     * Devuelve false si no se actualizó ninguna fila o falló tras reintentos.
     */
    protected function persistCotizacionFinalLink($idCotizacion, $idContenedor, array $genResult, $nombreCliente)
    {
        $idCotizacion = (int) $idCotizacion;
        $idContenedor = (int) $idContenedor;

        if ($idCotizacion <= 0) {
            Log::warning('PlantillaFinalBatchService: id de cotización inválido al vincular', [
                'nombre' => $nombreCliente,
                'id' => $idCotizacion,
            ]);
            return false;
        }

        $estadoCotizacionFinal = DB::table('contenedor_consolidado_cotizacion')
            ->where('id', $idCotizacion)
            ->where('id_contenedor', $idContenedor)
            ->whereNull('deleted_at')
            ->where('estado_cotizacion_final', '!=', 'PENDIENTE')
            ->whereNotNull('estado_cotizacion_final')
            ->first();

        $updateData = [
            'cotizacion_final_url' => $genResult['cotizacion_final_url'],
            'volumen_final' => $genResult['volumen_final'],
            'monto_final' => $genResult['monto_final'],
            'tarifa_final' => $genResult['tarifa_final'],
            'impuestos_final' => $genResult['impuestos_final'],
            'logistica_final' => $genResult['logistica_final'],
            'servicios_extra_final' => $genResult['servicios_extra_final'] ?? 0,
            'fob_final' => $genResult['fob_final'],
            'peso_final' => $genResult['peso_final'],
            'recargos_descuentos_final' => $genResult['recargos_descuentos_final'],
        ];
        if (!$estadoCotizacionFinal) {
            $updateData['estado_cotizacion_final'] = 'COTIZADO';
        }

        try {
            $affected = $this->applyCotizacionFinalUpdate($idCotizacion, $idContenedor, $updateData);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Out of range value') === false) {
                Log::error('PlantillaFinalBatchService: error al vincular cotización final', [
                    'id_cotizacion' => $idCotizacion,
                    'nombre' => $nombreCliente,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            Log::warning('PlantillaFinalBatchService: valores fuera de rango, reintento con límites', [
                'id_cotizacion' => $idCotizacion,
                'nombre' => $nombreCliente,
            ]);

            $limitedData = $updateData;
            foreach (['monto_final', 'logistica_final', 'impuestos_final', 'fob_final'] as $field) {
                if (isset($limitedData[$field]) && is_numeric($limitedData[$field])) {
                    $limitedData[$field] = min((float) $limitedData[$field], 9999999999999.99);
                }
            }

            try {
                $affected = $this->applyCotizacionFinalUpdate($idCotizacion, $idContenedor, $limitedData);
            } catch (\Throwable $retryError) {
                Log::error('PlantillaFinalBatchService: falló reintento al vincular cotización final', [
                    'id_cotizacion' => $idCotizacion,
                    'nombre' => $nombreCliente,
                    'error' => $retryError->getMessage(),
                ]);
                return false;
            }
        }

        if ($affected < 1) {
            Log::warning('PlantillaFinalBatchService: UPDATE sin filas afectadas', [
                'id_cotizacion' => $idCotizacion,
                'id_contenedor' => $idContenedor,
                'nombre' => $nombreCliente,
            ]);
            return false;
        }

        return true;
    }

    protected function applyCotizacionFinalUpdate($idCotizacion, $idContenedor, array $updateData)
    {
        return DB::table('contenedor_consolidado_cotizacion')
            ->where('id', (int) $idCotizacion)
            ->where('id_contenedor', (int) $idContenedor)
            ->whereNull('deleted_at')
            ->update($updateData);
    }

    /**
     * Resuelve la cotización a usar cuando hay varias con el mismo nombre (p. ej. fila reemplazada tras soft-delete).
     * Prioriza cotización activa (deleted_at null) y mayor id.
     */
    protected function resolveCotizacionMatchForCliente($nombreCliente, $result)
    {
        $candidates = [];
        foreach ($result as $item) {
            if ($this->matchClientName($nombreCliente, $item->nombre)) {
                $candidates[] = $item;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        if (count($candidates) > 1) {
            Log::warning('PlantillaFinalBatchService: varias cotizaciones coinciden con el nombre del Excel', [
                'nombre_excel' => $nombreCliente,
                'ids' => array_map(function ($c) {
                    return (int) $c->id;
                }, $candidates),
            ]);
        }

        usort($candidates, function ($a, $b) {
            $aDeleted = !empty($a->deleted_at);
            $bDeleted = !empty($b->deleted_at);
            if ($aDeleted !== $bDeleted) {
                return $aDeleted <=> $bDeleted;
            }

            return (int) $b->id <=> (int) $a->id;
        });

        $chosen = $candidates[0];
        if (!empty($chosen->deleted_at)) {
            Log::warning('PlantillaFinalBatchService: solo hay cotizaciones eliminadas para este nombre', [
                'nombre_excel' => $nombreCliente,
                'id' => (int) $chosen->id,
            ]);
            return null;
        }

        return $chosen;
    }

    protected function buildEmptyDetalle()
    {
        return [
            'exitosos' => [],
            'fallidos' => [],
        ];
    }

    protected function clienteNombreFromValue($value)
    {
        if (isset($value['cliente']['nombre']) && trim((string) $value['cliente']['nombre']) !== '') {
            return trim((string) $value['cliente']['nombre']);
        }
        return 'Sin nombre';
    }

    protected function matchClientName($fullName, $partialName)
    {
        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        $fullName = $this->normalizeString($fullName);
        $partialName = $this->normalizeString($partialName);

        if (empty($fullName) || empty($partialName)) {
            return false;
        }

        if ($fullName === $partialName) {
            return true;
        }

        $fullWords = array_filter(explode(' ', $fullName));
        $partialWords = array_filter(explode(' ', $partialName));

        if (count($fullWords) !== count($partialWords)) {
            return false;
        }

        if (empty($fullWords) || empty($partialWords)) {
            return false;
        }

        sort($fullWords);
        sort($partialWords);

        for ($i = 0; $i < count($fullWords); $i++) {
            if ($fullWords[$i] !== $partialWords[$i]) {
                return false;
            }
        }

        return true;
    }

    protected function normalizeString($string)
    {
        $string = strtolower(trim($string));
        $accents = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ];
        return strtr($string, $accents);
    }
}
