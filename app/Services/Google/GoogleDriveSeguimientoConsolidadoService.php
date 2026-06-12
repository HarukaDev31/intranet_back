<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Log;

/**
 * Sube el Excel de seguimiento consolidado a Google Drive.
 * Estructura: {EXCEL_SEGUIMIENTO_CONSOLIDADO_ID}/{mes}/cotizaciones_#{carga}_{fecha}.xlsx
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
    public function uploadForConsolidado($mesFolder, $localPath, $fileName)
    {
        if (!$this->isConfigured() || !is_file($localPath)) {
            Log::warning('[SeguimientoDrive] Subida omitida: Drive no configurado o archivo inexistente', [
                'mes_folder' => $mesFolder,
                'file' => $fileName,
                'configured' => $this->isConfigured(),
                'file_exists' => is_file($localPath),
            ]);

            return null;
        }

        try {
            Log::info('[SeguimientoDrive] Subiendo Excel a Drive', [
                'mes_folder' => $mesFolder,
                'file' => $fileName,
                'size_bytes' => filesize($localPath),
            ]);

            $this->bootDrive();

            $mesFolderId = $this->ensureFolder(
                $this->getRootFolderId(),
                $this->sanitizeName((string) $mesFolder)
            );

            $driveLink = $this->uploadOrReplace($mesFolderId, $localPath, $fileName);

            Log::info('[SeguimientoDrive] Excel subido a carpeta del mes', [
                'mes_folder' => $mesFolder,
                'file' => $fileName,
                'folder_id' => $mesFolderId,
                'drive_link' => $driveLink,
            ]);

            return $driveLink;
        } catch (\Throwable $e) {
            Log::error('[SeguimientoDrive] Fallo al subir Excel', [
                'mes_folder' => $mesFolder,
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
