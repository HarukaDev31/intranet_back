<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

interface ObjectStorageConnectorInterface
{
    public function uploadDisk(): string;

    public function storeUploadedFile(UploadedFile $file, string $directory, string $filename): string;

    /**
     * Guarda contenido binario (PDF, etc.) en ruta relativa. Retorna la misma ruta normalizada.
     */
    public function putContents(string $relativePath, string $contents): string;

    /**
     * Solo ruta relativa para BD (sin URL, sin storage/app/public).
     */
    public function normalizeRelativePath(?string $path): ?string;

    public function put(string $relativePath, $contents): bool;

    public function exists(string $relativePath): bool;

    public function delete(string $relativePath): bool;

    /**
     * URL pública o firmada para el front / WhatsApp (HTTPS).
     */
    public function url(string $relativePath): ?string;

    public function temporaryUrl(string $relativePath, ?int $minutes = null): string;

    /**
     * Ruta absoluta en disco local (descarga temporal si el archivo está en S3).
     */
    public function localPath(string $relativePath): string;

    public function mimeType(string $relativePath): ?string;

    /**
     * Respuesta HTTP para servir/descargar archivo (stream o redirect firmado).
     */
    public function fileResponse(
        string $relativePath,
        ?string $mimeType = null,
        string $disposition = 'inline',
        ?string $downloadFilename = null
    ): Response;

    public function readStream(string $relativePath);

    public function diskForPath(string $relativePath): ?string;
}
