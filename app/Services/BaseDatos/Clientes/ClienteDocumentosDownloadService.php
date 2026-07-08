<?php

namespace App\Services\BaseDatos\Clientes;

use App\Models\BaseDatos\Clientes\Cliente;
use App\Models\CargaConsolidada\Cotizacion;
use App\Traits\UsesObjectStorage;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ClienteDocumentosDownloadService
{
    use UsesObjectStorage;

    private const LEGACY_INTRANET_DOMAINS = [
        'https://intranet.probusiness.pe',
        'https://intranetback.probusiness.pe',
    ];

    /**
     * Genera un ZIP con cotización inicial, final y contrato de cada consolidado del cliente.
     *
     * @return array{success:bool,message?:string,status?:int,zipPath?:string,zipName?:string}
     */
    public function generarZip($clienteId)
    {
        $cliente = Cliente::find($clienteId);
        if (!$cliente) {
            return [
                'success' => false,
                'message' => 'Cliente no encontrado',
                'status' => 404,
            ];
        }

        $serviciosConsolidado = array_values(array_filter($cliente->servicios, function ($servicio) {
            return isset($servicio['servicio']) && $servicio['servicio'] === 'Consolidado';
        }));

        if (empty($serviciosConsolidado)) {
            return [
                'success' => false,
                'message' => 'El cliente no tiene consolidados registrados',
                'status' => 404,
            ];
        }

        $serviciosConsolidado = $this->ordenarConsolidados($serviciosConsolidado);

        $tempDir = storage_path('app/temp/cliente_documentos_' . $clienteId . '_' . time());
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el directorio temporal',
                'status' => 500,
            ];
        }

        $zipName = 'documentos_cliente_' . $clienteId . '_' . date('Y-m-d_His') . '.zip';
        $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipName;
        $tempFiles = [];
        $filesAdded = 0;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el archivo ZIP',
                'status' => 500,
            ];
        }

        foreach ($serviciosConsolidado as $servicio) {
            $cotizacion = Cotizacion::find($servicio['id']);
            if (!$cotizacion) {
                continue;
            }

            $carga = (int) ($servicio['carga'] ?? $cotizacion->carga ?? 0);
            $anio = (int) date('Y', strtotime($servicio['fecha']));
            $folder = $this->sanitizePathSegment($carga . '-' . $anio);

            $documentos = $this->resolverDocumentosCotizacion($cotizacion);
            foreach ($documentos as $doc) {
                $resolved = $this->resolverArchivoLocal($doc['path'], $tempFiles);
                if (!$resolved) {
                    continue;
                }

                $zipInnerPath = $folder . '/' . $doc['filename'];
                if ($zip->addFile($resolved, $zipInnerPath)) {
                    $filesAdded++;
                }
            }
        }

        $zip->close();

        foreach ($tempFiles as $tmp) {
            if (is_string($tmp) && file_exists($tmp)) {
                @unlink($tmp);
            }
        }

        if ($filesAdded === 0 || !file_exists($zipPath)) {
            $this->limpiarDirectorio($tempDir);
            return [
                'success' => false,
                'message' => 'No se encontraron documentos disponibles para descargar',
                'status' => 404,
            ];
        }

        return [
            'success' => true,
            'zipPath' => $zipPath,
            'zipName' => $zipName,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $servicios
     * @return array<int, array<string, mixed>>
     */
    private function ordenarConsolidados(array $servicios)
    {
        usort($servicios, function ($a, $b) {
            $anioA = (int) date('Y', strtotime($a['fecha']));
            $anioB = (int) date('Y', strtotime($b['fecha']));
            if ($anioA !== $anioB) {
                return $anioB - $anioA;
            }

            $cargaA = (int) ($a['carga'] ?? 0);
            $cargaB = (int) ($b['carga'] ?? 0);
            return $cargaB - $cargaA;
        });

        return $servicios;
    }

    /**
     * @return array<int, array{path:string,filename:string}>
     */
    private function resolverDocumentosCotizacion(Cotizacion $cotizacion)
    {
        $docs = [];

        if (!empty($cotizacion->cotizacion_file_url)) {
            $docs[] = [
                'path' => $cotizacion->cotizacion_file_url,
                'filename' => 'cotizacion_inicial' . $this->extensionDesdeRuta($cotizacion->cotizacion_file_url),
            ];
        }

        if (!empty($cotizacion->cotizacion_final_url)) {
            $docs[] = [
                'path' => $cotizacion->cotizacion_final_url,
                'filename' => 'cotizacion_final' . $this->extensionDesdeRuta($cotizacion->cotizacion_final_url),
            ];
        }

        $contratoPath = null;
        if (!empty($cotizacion->cotizacion_contrato_firmado_url)) {
            $contratoPath = $cotizacion->cotizacion_contrato_firmado_url;
        } elseif (!empty($cotizacion->cotizacion_contrato_autosigned_url)) {
            $contratoPath = $cotizacion->cotizacion_contrato_autosigned_url;
        } elseif (!empty($cotizacion->cotizacion_contrato_url)) {
            $contratoPath = $cotizacion->cotizacion_contrato_url;
        }

        if (!empty($contratoPath)) {
            $docs[] = [
                'path' => $contratoPath,
                'filename' => 'contrato' . $this->extensionDesdeRuta($contratoPath, '.pdf'),
            ];
        }

        return $docs;
    }

    /**
     * @param array<int, string> $tempFiles
     * @return string|null
     */
    private function resolverArchivoLocal($dbPath, array &$tempFiles)
    {
        if (empty($dbPath)) {
            return null;
        }

        $originalPath = (string) $dbPath;
        $normalizedPath = $originalPath;
        if (filter_var($normalizedPath, FILTER_VALIDATE_URL)) {
            $normalizedPath = $this->objectStorage()->normalizeRelativePath($normalizedPath);
        }

        $uploadPath = $this->storageUploadPathFromDb($normalizedPath);
        if (empty($uploadPath)) {
            Log::warning('ClienteDocumentosDownloadService: ruta inválida', [
                'dbPath' => $originalPath,
            ]);
            return null;
        }

        $storage = $this->objectStorage();
        if (method_exists($storage, 'existsOnS3') && $storage->existsOnS3($uploadPath)) {
            $fromS3 = $this->materializarArchivoDesdeStorage($uploadPath, $originalPath, $tempFiles);
            if ($fromS3 !== null) {
                return $fromS3;
            }
        }

        $fromLegacy = $this->resolverArchivoDesdeDominiosLegacy($originalPath, $uploadPath, $tempFiles);
        if ($fromLegacy !== null) {
            return $fromLegacy;
        }

        Log::warning('ClienteDocumentosDownloadService: archivo no encontrado', [
            'dbPath' => $originalPath,
            'uploadPath' => $uploadPath,
        ]);

        return null;
    }

    /**
     * @param array<int, string> $tempFiles
     * @return string|null
     */
    private function materializarArchivoDesdeStorage($uploadPath, $referencePath, array &$tempFiles)
    {
        try {
            $local = $this->storageLocalPath($uploadPath);
            if (is_readable($local)) {
                return $local;
            }
        } catch (\Exception $e) {
            // Continuar con stream temporal
        }

        $stream = $this->objectStorage()->readStream($uploadPath);
        if (!$stream) {
            return null;
        }

        return $this->guardarStreamEnTemporal($stream, $referencePath, $tempFiles);
    }

    /**
     * @param array<int, string> $tempFiles
     * @return string|null
     */
    private function resolverArchivoDesdeDominiosLegacy($originalPath, $uploadPath, array &$tempFiles)
    {
        $urls = [];

        if (filter_var($originalPath, FILTER_VALIDATE_URL) && $this->esUrlDominioLegacy($originalPath)) {
            $urls[] = $originalPath;
        }

        foreach (self::LEGACY_INTRANET_DOMAINS as $domain) {
            $urls[] = $this->construirUrlLegacy($domain, 'storage', $uploadPath);
            if (strpos($uploadPath, 'contratos/') === 0) {
                $urls[] = $this->construirUrlLegacy($domain, 'files', $uploadPath);
            }
        }

        $urls = array_values(array_unique($urls));
        foreach ($urls as $url) {
            $downloaded = $this->descargarArchivoHttp($url, $originalPath, $tempFiles);
            if ($downloaded !== null) {
                return $downloaded;
            }
        }

        return null;
    }

    private function esUrlDominioLegacy($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        return $host === 'intranet.probusiness.pe' || $host === 'intranetback.probusiness.pe';
    }

    private function construirUrlLegacy($domain, $prefix, $uploadPath)
    {
        $segments = explode('/', ltrim(str_replace('\\', '/', $uploadPath), '/'));
        $encoded = implode('/', array_map('rawurlencode', $segments));

        return rtrim($domain, '/') . '/' . trim($prefix, '/') . '/' . $encoded;
    }

    /**
     * @param array<int, string> $tempFiles
     * @return string|null
     */
    private function descargarArchivoHttp($url, $referencePath, array &$tempFiles)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init();
        if (!$ch) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ProBusiness-ClienteDocumentos/1.0',
        ]);

        $content = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false || $error !== '' || $httpCode !== 200 || $content === '') {
            Log::debug('ClienteDocumentosDownloadService: fallo descarga HTTP legacy', [
                'url' => $url,
                'http_code' => $httpCode,
                'error' => $error,
            ]);

            return null;
        }

        $ext = $this->extensionDesdeRuta($referencePath, '.bin');
        $tmp = tempnam(sys_get_temp_dir(), 'cli_doc_');
        if ($tmp === false) {
            return null;
        }

        $tmpWithExt = $tmp . $ext;
        @unlink($tmp);

        if (file_put_contents($tmpWithExt, $content) === false) {
            return null;
        }

        $tempFiles[] = $tmpWithExt;

        return $tmpWithExt;
    }

    /**
     * @param resource $stream
     * @param array<int, string> $tempFiles
     * @return string|null
     */
    private function guardarStreamEnTemporal($stream, $referencePath, array &$tempFiles)
    {
        $ext = $this->extensionDesdeRuta($referencePath, '.bin');
        $tmp = tempnam(sys_get_temp_dir(), 'cli_doc_');
        if ($tmp === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return null;
        }

        $tmpWithExt = $tmp . $ext;
        @unlink($tmp);

        $out = fopen($tmpWithExt, 'wb');
        if (!$out) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return null;
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $tempFiles[] = $tmpWithExt;

        return $tmpWithExt;
    }

    private function extensionDesdeRuta($path, $fallback = '')
    {
        $basename = basename(parse_url((string) $path, PHP_URL_PATH) ?: (string) $path);
        $ext = pathinfo($basename, PATHINFO_EXTENSION);
        if ($ext === '' || $ext === false) {
            return $fallback;
        }

        return '.' . strtolower($ext);
    }

    private function sanitizePathSegment($value)
    {
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) $value);
        return trim($clean, '_') ?: 'consolidado';
    }

    private function limpiarDirectorio($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
