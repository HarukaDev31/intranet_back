<?php

namespace App\Services\CargaConsolidada\Documentacion;

use App\Events\FacturaComercialBatchFinished;
use App\Http\Controllers\CargaConsolidada\Documentacion\DocumentacionController;
use App\Jobs\GenerateFacturaComercialJob;
use App\Models\CargaConsolidada\ConsolidadoFacturaComercialBatch;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\Log;

class FacturaComercialBatchService
{
    use UsesObjectStorage;

    /** @var DocumentacionController */
    protected $documentacionController;

    public function __construct(DocumentacionController $documentacionController)
    {
        $this->documentacionController = $documentacionController;
    }

    public function enqueue($idContenedor, $createdBy = null)
    {
        $idContenedor = (int) $idContenedor;

        $existing = ConsolidadoFacturaComercialBatch::query()
            ->where('id_contenedor', $idContenedor)
            ->where('estado', 'PENDING')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return [
                'batch_id' => (int) $existing->id,
                'estado' => $existing->estado,
                'id_contenedor' => $idContenedor,
                'already_queued' => true,
            ];
        }

        $this->documentacionController->assertFacturaComercialSources($idContenedor);

        $nombre = 'factura_procesada_' . $idContenedor . '_' . time() . '.xlsx';

        $batch = ConsolidadoFacturaComercialBatch::create([
            'id_contenedor' => $idContenedor,
            'estado' => 'PENDING',
            'created_by' => $createdBy ? (int) $createdBy : null,
            'nombre_archivo' => $nombre,
        ]);

        GenerateFacturaComercialJob::dispatch((int) $batch->id)
            ->onQueue((string) config('carga_consolidada.queue', 'carga_consolidada'));

        return [
            'batch_id' => (int) $batch->id,
            'estado' => $batch->estado,
            'id_contenedor' => $idContenedor,
            'already_queued' => false,
        ];
    }

    public function processBatchById($batchId)
    {
        $batch = ConsolidadoFacturaComercialBatch::find((int) $batchId);
        if (!$batch) {
            throw new \InvalidArgumentException('Lote de factura general no encontrado.');
        }

        if ($batch->estado === 'COMPLETED') {
            return $batch;
        }

        $batch->update([
            'fecha_inicio' => now(),
            'estado' => 'PENDING',
            'mensaje_error' => null,
        ]);

        $tempPath = null;

        try {
            $tempPath = $this->documentacionController->generateFacturaComercialToPath(
                (int) $batch->id_contenedor
            );

            $relative = 'facturas-comerciales/' . $batch->id_contenedor . '_' . $batch->id . '_' . time() . '.xlsx';
            $contents = file_get_contents($tempPath);
            if ($contents === false) {
                throw new \RuntimeException('No se pudo leer el Excel generado.');
            }

            $stored = $this->storagePutContents($relative, $contents);
            $nombre = $batch->nombre_archivo ?: ('factura_procesada_' . $batch->id_contenedor . '.xlsx');

            $batch->update([
                'file_path' => $stored,
                'nombre_archivo' => $nombre,
                'fecha_fin' => now(),
                'estado' => 'COMPLETED',
                'mensaje_error' => null,
            ]);

            event(new FacturaComercialBatchFinished(
                $batch->fresh(),
                'La factura general se generó correctamente.'
            ));
        } catch (\Throwable $e) {
            Log::error('FacturaComercialBatchService::processBatchById', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage(),
            ]);

            $batch->update([
                'fecha_fin' => now(),
                'estado' => 'FAILED',
                'mensaje_error' => $e->getMessage(),
            ]);

            event(new FacturaComercialBatchFinished(
                $batch->fresh(),
                'Error al generar la factura general: ' . $e->getMessage()
            ));

            throw $e;
        } finally {
            if ($tempPath && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $batch->fresh();
    }

    public function listByContenedor($idContenedor, $limit = 100)
    {
        $limit = max(1, min((int) $limit, 500));

        return ConsolidadoFacturaComercialBatch::query()
            ->where('id_contenedor', (int) $idContenedor)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ConsolidadoFacturaComercialBatch $batch) {
                return $this->mapBatchForApi($batch);
            })
            ->values()
            ->all();
    }

    public function findBatchOrFail($id)
    {
        $batch = ConsolidadoFacturaComercialBatch::find((int) $id);
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

    protected function mapBatchForApi(ConsolidadoFacturaComercialBatch $batch)
    {
        $hasFile = !empty($batch->file_path)
            && strtoupper((string) $batch->estado) === 'COMPLETED'
            && $this->objectStorage()->exists($batch->file_path);

        return [
            'id' => (int) $batch->id,
            'id_contenedor' => (int) $batch->id_contenedor,
            'estado' => $batch->estado,
            'fecha_inicio' => $batch->fecha_inicio ? $batch->fecha_inicio->toIso8601String() : null,
            'fecha_fin' => $batch->fecha_fin ? $batch->fecha_fin->toIso8601String() : null,
            'created_by' => $batch->created_by ? (int) $batch->created_by : null,
            'file_path' => $batch->file_path,
            'nombre_archivo' => $batch->nombre_archivo,
            'has_file' => $hasFile,
            'mensaje_error' => $batch->mensaje_error,
            'created_at' => $batch->created_at ? $batch->created_at->toIso8601String() : null,
        ];
    }
}
