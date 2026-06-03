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

    protected function storageLocalPath(string $relativePath): string
    {
        return $this->objectStorage()->localPath($relativePath);
    }
}
