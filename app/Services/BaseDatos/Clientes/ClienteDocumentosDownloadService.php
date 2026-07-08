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

        $finalPath = !empty($cotizacion->cotizacion_final_url)
            ? $cotizacion->cotizacion_final_url
            : $cotizacion->cotizacion_final_file_url;
        if (!empty($finalPath)) {
            $docs[] = [
                'path' => $finalPath,
                'filename' => 'cotizacion_final' . $this->extensionDesdeRuta($finalPath),
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

        if (filter_var($dbPath, FILTER_VALIDATE_URL)) {
            $dbPath = $this->objectStorage()->normalizeRelativePath($dbPath);
        }

        $uploadPath = $this->storageUploadPathFromDb($dbPath);
        if (empty($uploadPath) || !$this->objectStorage()->exists($uploadPath)) {
            Log::warning('ClienteDocumentosDownloadService: archivo no encontrado', [
                'dbPath' => $dbPath,
                'uploadPath' => $uploadPath,
            ]);
            return null;
        }

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

        $ext = $this->extensionDesdeRuta($dbPath, '.bin');
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
