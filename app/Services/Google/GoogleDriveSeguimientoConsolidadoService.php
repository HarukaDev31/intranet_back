<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Log;

/**
 * Sube el Excel de seguimiento consolidado a Google Drive.
 * Estructura: {EXCEL_SEGUIMIENTO_CONSOLIDADO_ID}/{numero_consolidado}/cotizaciones_#{carga}_{fecha}.xlsx
 */
class GoogleDriveSeguimientoConsolidadoService extends GoogleDriveExcelConfirmacionService
{
    /**
     * @return string
     */
    protected function resolveRootFolderId()
    {
        return trim((string) config('google.drive_excel_seguimiento_consolidado_root_folder_id', ''));
    }

    /**
     * @return string|null URL pública edit?usp=sharing
     */
    public function uploadForConsolidado($cargaCode, $localPath, $fileName)
    {
        if (!$this->isConfigured() || !is_file($localPath)) {
            Log::warning('[SeguimientoDrive] Subida omitida: Drive no configurado o archivo inexistente', [
                'carga' => $cargaCode,
                'file' => $fileName,
                'configured' => $this->isConfigured(),
                'file_exists' => is_file($localPath),
            ]);

            return null;
        }

        try {
            Log::info('[SeguimientoDrive] Subiendo Excel a Drive', [
                'carga' => $cargaCode,
                'file' => $fileName,
                'size_bytes' => filesize($localPath),
            ]);

            $this->bootDrive();

            $consolidadoFolderId = $this->ensureFolder(
                $this->getRootFolderId(),
                $this->sanitizeName((string) $cargaCode)
            );

            $driveLink = $this->uploadOrReplace($consolidadoFolderId, $localPath, $fileName);

            Log::info('[SeguimientoDrive] Excel subido a carpeta consolidado', [
                'carga' => $cargaCode,
                'file' => $fileName,
                'folder_id' => $consolidadoFolderId,
                'drive_link' => $driveLink,
            ]);

            return $driveLink;
        } catch (\Throwable $e) {
            Log::error('[SeguimientoDrive] Fallo al subir Excel', [
                'carga' => $cargaCode,
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return string
     */
    public function getRootFolderId()
    {
        return $this->resolveRootFolderId();
    }
}
