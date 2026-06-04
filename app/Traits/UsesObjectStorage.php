<?php

namespace App\Traits;

use App\Contracts\ObjectStorageConnectorInterface;
use Illuminate\Http\UploadedFile;

trait UsesObjectStorage
{
    protected function objectStorage(): ObjectStorageConnectorInterface
    {
        return app(ObjectStorageConnectorInterface::class);
    }

    /** Subida con ruta relativa lista para BD (nunca URL). */
    protected function storageStoreUpload(UploadedFile $file, string $directory, string $filename): string
    {
        return $this->objectStorage()->storeUploadedFile($file, $directory, $filename);
    }

    protected function storagePutContents(string $relativePath, string $contents): string
    {
        return $this->objectStorage()->putContents($relativePath, $contents);
    }

    /**
     * Sube a storage (S3/local) y devuelve la ruta para BD + CDN (ej. probusiness/contratos/archivo.pdf).
     */
    protected function storagePutContentsForCdn(string $relativePath, string $contents): string
    {
        $stored = $this->objectStorage()->putContents($relativePath, $contents);

        if ($this->objectStorage()->uploadDisk() === 's3' && !$this->objectStorage()->existsOnS3($stored)) {
            throw new \RuntimeException('El PDF no quedó guardado en S3: ' . $stored);
        }

        return $this->storageCdnDbPath($stored);
    }

    /** Clave tras el dominio CDN (incluye AWS_UPLOAD_PREFIX si aplica). */
    protected function storageCdnDbPath(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');

        if ($prefix === '' || stripos($relativePath, $prefix . '/') === 0) {
            return $relativePath;
        }

        return $prefix . '/' . $relativePath;
    }

    /** Valida S3 y devuelve ruta para BD/CDN tras storageStoreUpload. */
    protected function storageFinalizeCdnPath(string $uploadPath): string
    {
        if ($this->objectStorage()->uploadDisk() === 's3' && !$this->objectStorage()->existsOnS3($uploadPath)) {
            throw new \RuntimeException('El archivo no quedó guardado en S3: ' . $uploadPath);
        }

        return $this->storageCdnDbPath($uploadPath);
    }

    /** Ruta de subida (sin prefijo de bucket) a partir del valor en BD. */
    protected function storageUploadPathFromDb(?string $dbPath): ?string
    {
        $path = $this->objectStorage()->normalizeRelativePath($dbPath);
        if ($path === null) {
            return null;
        }

        $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');
        if ($prefix !== '' && stripos($path, $prefix . '/') === 0) {
            return substr($path, strlen($prefix) + 1);
        }

        return $path;
    }

    protected function storageLocalPath(string $relativePath): string
    {
        return $this->objectStorage()->localPath($relativePath);
    }
}
