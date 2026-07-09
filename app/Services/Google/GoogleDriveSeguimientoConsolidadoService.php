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

            $driveLink = $this->uploadOrReplaceWithRetry($mesFolderId, $localPath, $fileName);

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

    /**
     * Elimina un archivo de Drive por ID (404 = OK).
     *
     * @param string $fileId
     * @return bool
     */
    public function deleteFileById($fileId)
    {
        if (!$this->isConfigured() || trim((string) $fileId) === '') {
            return false;
        }

        try {
            return $this->deleteDriveFileById((string) $fileId);
        } catch (\Throwable $e) {
            Log::warning('[SeguimientoDrive] No se pudo borrar archivo Drive', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Borra todos los archivos bajo la carpeta raíz de seguimiento (subcarpetas por mes).
     *
     * @return array{deleted: int, errors: string[]}
     */
    public function purgeSeguimientoRoot()
    {
        if (!$this->isConfigured()) {
            return ['deleted' => 0, 'errors' => ['Google Drive no configurado']];
        }

        $rootId = $this->getRootFolderId();
        if ($rootId === '') {
            return ['deleted' => 0, 'errors' => ['Carpeta raíz EXCEL_SEGUIMIENTO_CONSOLIDADO_ID vacía']];
        }

        $deleted = 0;
        $errors = [];

        try {
            $deleted += $this->purgeFilesInFolder($rootId, $errors);

            foreach ($this->listDriveChildren($rootId, 'application/vnd.google-apps.folder') as $folder) {
                $deleted += $this->purgeFilesInFolder($folder['id'], $errors);
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        Log::info('[SeguimientoDrive] Purga carpeta raíz completada', [
            'root_id' => $rootId,
            'deleted' => $deleted,
            'errors' => count($errors),
        ]);

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * @param string $folderId
     * @param array<int, string> $errors
     * @return int
     */
    private function purgeFilesInFolder($folderId, array &$errors)
    {
        $deleted = 0;

        foreach ($this->listDriveChildren($folderId) as $item) {
            if (($item['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                continue;
            }

            try {
                $this->deleteDriveFileById($item['id']);
                $deleted++;
            } catch (\Throwable $e) {
                $errors[] = ($item['name'] ?? $item['id']) . ': ' . $e->getMessage();
            }
        }

        return $deleted;
    }

    /**
     * Reintenta subidas ante errores transitorios de Google (503, rate limit, etc.).
     */
    private function uploadOrReplaceWithRetry(string $folderId, string $localPath, string $fileName, int $maxAttempts = 3): string
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->uploadOrReplace($folderId, $localPath, $fileName);
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts || !$this->isTransientDriveError($e)) {
                    throw $e;
                }

                $delaySeconds = min(2 ** ($attempt - 1), 8);

                Log::warning('[SeguimientoDrive] Error transitorio en subida Drive, reintentando', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay_seconds' => $delaySeconds,
                    'file' => $fileName,
                    'error' => $e->getMessage(),
                ]);

                sleep($delaySeconds);
                $this->resetDrive();
                $this->bootDrive();
            }
        }

        throw $lastException ?? new \RuntimeException('Fallo al subir archivo a Google Drive.');
    }

    private function isTransientDriveError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        if (
            str_contains($message, 'transient')
            || str_contains($message, 'backend error')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'user rate limit')
            || str_contains($message, 'quota')
        ) {
            return true;
        }

        if (preg_match('/\b(429|500|502|503|504)\b/', $message)) {
            return true;
        }

        if (method_exists($e, 'getCode')) {
            $code = (int) $e->getCode();

            return in_array($code, [429, 500, 502, 503, 504], true);
        }

        return false;
    }
}
