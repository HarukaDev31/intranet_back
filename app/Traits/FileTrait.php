<?php

namespace App\Traits;

use App\Contracts\ObjectStorageConnectorInterface;

trait FileTrait
{
    /**
     * URL pública CDN sin consultar S3 (listados con muchas filas).
     * BD: ruta relativa (contratos/...) o clave CDN (probusiness/contratos/...).
     */
    public function cdnStorageUrl(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            if (stripos($ruta, 'cdn.') !== false) {
                return $ruta;
            }

            $ruta = app(ObjectStorageConnectorInterface::class)->normalizeRelativePath($ruta);
            if ($ruta === null) {
                return null;
            }
        }

        $ruta = ltrim(str_replace('\\', '/', $ruta), '/');
        $prefix = trim((string) config('object_storage.s3_prefix', ''), '/');

        if ($prefix !== '' && stripos($ruta, $prefix . '/') !== 0) {
            $ruta = $prefix . '/' . $ruta;
        }

        $base = rtrim((string) config('object_storage.cdn_base_url', 'https://cdn.probusiness.pe'), '/');

        return $base . '/' . $ruta;
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