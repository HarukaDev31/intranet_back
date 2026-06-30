<?php

namespace App\Traits;

use App\Contracts\ObjectStorageConnectorInterface;
use App\Support\Storage\StoragePathSanitizer;

trait FileTrait
{
    /**
     * URL pública CDN sin consultar S3 (listados con muchas filas).
     * BD: ruta relativa (contratos/...) o clave CDN (probusiness/contratos/...).
     */
    public function cdnStorageUrl(?string $ruta): ?string
    {
        if ($ruta === null || trim($ruta) === '') {
            return null;
        }

        $storage = app(ObjectStorageConnectorInterface::class);

        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            $path = parse_url($ruta, PHP_URL_PATH);
            $normalized = $storage->normalizeRelativePath(is_string($path) && $path !== '' ? $path : $ruta);
        } else {
            $normalized = $storage->normalizeRelativePath($ruta);
        }

        if ($normalized === null || $normalized === '') {
            return null;
        }

        return $this->buildCdnPublicUrl($normalized);
    }

    protected function buildCdnPublicUrl(string $normalized): string
    {
        $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');
        if ($prefix !== '' && stripos($normalized, $prefix . '/') === 0) {
            $normalized = substr($normalized, strlen($prefix) + 1);
        }

        $base = rtrim((string) config('object_storage.cdn_base_url', ''), '/');
        if ($base === '') {
            $base = 'https://cdn.probusiness.pe';
        }

        $includePrefix = filter_var(config('object_storage.cdn_include_s3_prefix', false), FILTER_VALIDATE_BOOLEAN);
        // Contratos en CDN público: .../contratos/archivo.pdf (sin prefijo S3 en la URL)
        $isPublicContratoPath = stripos($normalized, 'contratos/') === 0
            || stripos($normalized, 'contratos\\') === 0;

        if ($includePrefix && $prefix !== '' && !$isPublicContratoPath) {
            $normalized = $prefix . '/' . ltrim($normalized, '/');
        }

        return $base . '/' . StoragePathSanitizer::encodeRelativePathForUrl(ltrim($normalized, '/'));
    }

    public function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        return app(ObjectStorageConnectorInterface::class)->url($ruta);
    }
    public function generateImageUrlRedisProyect($ruta){
        if (empty($ruta)) {
            return null;
        }
        
        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }
        //remote /public/ from ruta
        $ruta = str_replace('public/', '', $ruta);
        // Generar URL completa desde storage
        return env('APP_URL_REDIS').'/'.$ruta;
    }
}