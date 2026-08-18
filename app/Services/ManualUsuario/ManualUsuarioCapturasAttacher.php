<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Traits\UsesObjectStorage;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Sube capturas de resources/manual/capturas y las enlaza a bloques media.
 * No borra páginas ni reescribe copy.
 */
class ManualUsuarioCapturasAttacher
{
    use UsesObjectStorage;

    /**
     * @param  array{dry_run?:bool,strict?:bool,legacy?:bool,directory?:string}  $options
     */
    public function attach(array $options = [])
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $strict = (bool) ($options['strict'] ?? false);
        $legacy = (bool) ($options['legacy'] ?? false);
        $dir = (string) ($options['directory'] ?? resource_path('manual/capturas'));
        $files = $this->pngFiles($dir);
        $matches = [];
        $issues = [];
        $usedOutputs = [];

        $blocks = ManualBloque::query()
            ->with(['pagina', 'parent'])
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('id')
            ->get();
        foreach ($blocks as $block) {
            $snapshot = data_get($block->payload, 'snapshot', []);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $key = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
            $aliasOf = !empty($snapshot['capture_alias_of']) ? (string) $snapshot['capture_alias_of'] : null;
            $resolution = 'exact';

            if ($key === null && $legacy) {
                $key = $this->shotKey(
                    $block->pagina ? (string) $block->pagina->modulo_key : '',
                    $block->parent ? (string) $block->parent->titulo : '',
                    (string) $block->titulo
                );
                if ($key === null || $this->resolvePng($files, $key, $key, null) === null) {
                    $key = $this->shotKeyGeneric(
                        $block->pagina ? (string) $block->pagina->modulo_key : '',
                        $block->parent ? (string) $block->parent->titulo : '',
                        (string) $block->titulo
                    );
                }
                $resolution = 'legacy';
            }

            if ($key === null && $aliasOf === null) {
                $issues[] = ['code' => 'missing_capture_key', 'block_id' => (int) $block->id];
                continue;
            }
            try {
                $identity = ManualUsuarioCaptureKey::identity($key, $aliasOf);
            } catch (\InvalidArgumentException $e) {
                $issues[] = [
                    'code' => 'invalid_capture_key',
                    'block_id' => (int) $block->id,
                    'capture_key' => $key,
                    'alias_of' => $aliasOf,
                ];
                continue;
            }
            if ($identity === null) {
                $issues[] = ['code' => 'missing_capture_key', 'block_id' => (int) $block->id];
                continue;
            }

            $declaredOutput = !empty($snapshot['capture_output'])
                ? str_replace('\\', '/', (string) $snapshot['capture_output'])
                : null;
            $output = $this->resolvePng($files, $identity, $key, $declaredOutput);
            if ($output === null && $legacy && $resolution === 'exact') {
                $legacyKey = $this->shotKey(
                    $block->pagina ? (string) $block->pagina->modulo_key : '',
                    $block->parent ? (string) $block->parent->titulo : '',
                    (string) $block->titulo
                );
                if ($legacyKey === null || $this->resolvePng($files, $legacyKey, $legacyKey, null) === null) {
                    $legacyKey = $this->shotKeyGeneric(
                        $block->pagina ? (string) $block->pagina->modulo_key : '',
                        $block->parent ? (string) $block->parent->titulo : '',
                        (string) $block->titulo
                    );
                }
                if ($legacyKey !== null) {
                    $legacyOutput = $this->resolvePng($files, $legacyKey, $legacyKey, null);
                    if ($legacyOutput !== null) {
                        $key = $legacyKey;
                        $identity = $legacyKey;
                        $output = $legacyOutput;
                        $resolution = 'legacy';
                    }
                }
            }
            if ($output === null) {
                $issues[] = [
                    'code' => 'missing_png',
                    'block_id' => (int) $block->id,
                    'capture_key' => $key,
                    'identity' => $identity,
                    'output' => $declaredOutput ?: ManualUsuarioCaptureKey::output($identity),
                    'resolution' => $resolution,
                ];
                continue;
            }

            $matches[] = compact('block', 'key', 'identity', 'output', 'resolution');
            $usedOutputs[$output] = true;
        }

        foreach (array_keys($files) as $output) {
            if (!isset($usedOutputs[$output]) && !$this->isViewportVariant($output, array_keys($usedOutputs))) {
                $issues[] = ['code' => 'orphan_png', 'output' => $output];
            }
        }
        if ($strict && $issues) {
            throw new RuntimeException(
                'Preflight estricto de capturas falló con ' . count($issues)
                . ' incidencia(s): ' . json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $mediaIds = [];
        $uploaded = 0;
        if (!$dryRun) {
            foreach ($matches as $match) {
                $identity = $match['identity'];
                if (!isset($mediaIds[$identity])) {
                    $mediaIds[$identity] = $this->storePng(
                        $files[$match['output']],
                        $identity,
                        $match['output'],
                        $strict
                    );
                    $uploaded++;
                }
            }
        }

        $linked = 0;
        if (!$dryRun) {
            foreach ($matches as $match) {
                $block = $match['block'];
                $payload = is_array($block->payload) ? $block->payload : [];
                if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                    $payload['snapshot'] = [];
                }
                $payload['snapshot']['media_id'] = (int) $mediaIds[$match['identity']];
                $payload['snapshot']['capture_key'] = $match['key'] ?: $match['identity'];
                if ($match['key'] && $match['key'] !== $match['identity']) {
                    $payload['snapshot']['capture_alias_of'] = $match['identity'];
                }
                $payload['snapshot']['capture_output'] = ManualUsuarioCaptureKey::output($match['identity']);
                if ($match['resolution'] === 'legacy') {
                    $payload['snapshot']['capture_legacy_key'] = $match['key'];
                }
                $block->payload = $payload;
                $block->save();
                $linked++;
            }
        }

        $sharedIdentities = [];
        foreach ($matches as $match) {
            $sharedIdentities[$match['identity']][] = (int) $match['block']->id;
        }

        return [
            'uploaded' => $uploaded,
            'linked' => $linked,
            'skipped' => count($blocks) - count($matches),
            'would_upload' => count(array_unique(array_column($matches, 'identity'))),
            'would_link' => count($matches),
            'shared_keys' => count(array_filter($sharedIdentities, fn (array $ids) => count($ids) > 1)),
            'shared_blocks' => array_sum(array_map(
                fn (array $ids) => count($ids) > 1 ? count($ids) : 0,
                $sharedIdentities
            )),
            'dry_run' => $dryRun,
            'strict' => $strict,
            'legacy' => $legacy,
            'issues' => $issues,
        ];
    }

    private function pngFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'png') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
                $files[$relative] = $file->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    private function isViewportVariant(string $output, array $expectedOutputs): bool
    {
        foreach ($expectedOutputs as $expected) {
            if (!preg_match('/^(.*?)(?:--[^\/]+)?\.png$/', $expected, $matches)) {
                continue;
            }
            if (preg_match('/^' . preg_quote($matches[1], '/') . '--[^\/]+\.png$/', $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resuelve el PNG canónico: {identity}.png, luego basename o ruta legacy por rol.
     *
     * @param  array<string, string>  $files
     */
    public function resolvePng(array $files, ?string $identity, ?string $key, ?string $declaredOutput): ?string
    {
        $candidates = [];
        if ($identity) {
            $candidates[] = ManualUsuarioCaptureKey::output($identity);
        }
        if ($key && $key !== $identity) {
            $candidates[] = ManualUsuarioCaptureKey::output($key);
        }
        if ($declaredOutput) {
            $candidates[] = str_replace('\\', '/', ltrim($declaredOutput, '/'));
            $candidates[] = basename(str_replace('\\', '/', $declaredOutput));
        }
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset($files[$candidate])) {
                return $candidate;
            }
        }

        $wanted = [];
        if ($identity) {
            $wanted[ManualUsuarioCaptureKey::output($identity)] = true;
        }
        if ($key) {
            $wanted[ManualUsuarioCaptureKey::output($key)] = true;
        }
        if ($declaredOutput) {
            $wanted[basename(str_replace('\\', '/', $declaredOutput))] = true;
        }
        foreach ($files as $relative => $absolute) {
            if (isset($wanted[basename($relative)])) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * @return int
     */
    private function storePng($abs, $key, $output = null, $strict = false)
    {
        $bin = file_get_contents($abs);
        if ($bin === false) {
            throw new \RuntimeException('No se pudo leer ' . $abs);
        }

        $output = $output ?: ($key . '.png');
        $output = ltrim(str_replace('\\', '/', $output), '/');
        if (strpos($output, '../') !== false) {
            throw new RuntimeException('Ruta de salida inválida: ' . $output);
        }
        $relative = 'manual/capturas/' . $output;
        $localFile = storage_path('app/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $localDir = dirname($localFile);
        if (!is_dir($localDir)) {
            mkdir($localDir, 0775, true);
        }
        file_put_contents($localFile, $bin);

        $dbPath = $relative;
        try {
            $dbPath = $this->storagePutContentsForCdn($relative, $bin);
        } catch (\Throwable $e) {
            if ($strict) {
                throw $e;
            }
            $dbPath = $relative;
        }

        $media = ManualMedia::query()
            ->where('alt', $key)
            ->orderByDesc('id')
            ->first();
        if ($media) {
            $media->path = $dbPath;
            $media->mime = 'image/png';
            $media->alt = $key;
            $media->save();

            return (int) $media->id;
        }

        $created = ManualMedia::query()->create([
            'path' => $dbPath,
            'alt' => $key,
            'mime' => 'image/png',
        ]);

        return (int) $created->id;
    }

    /**
     * Clave de módulo si aún no hay recorte de esa acción.
     *
     * @return string|null
     */
    private function shotKeyGeneric($modulo, $flow, $titulo)
    {
        $m = mb_strtolower((string) $modulo);
        $f = mb_strtolower((string) $flow);

        if (strpos($m, 'cargaconsolidada') !== false) {
            if (strpos($f, 'cotización final') !== false || strpos($f, 'cotizacion final') !== false) {
                return 'carga-cotizacion-final';
            }
            if (strpos($f, 'cotización') !== false || strpos($f, 'cotizacion') !== false) {
                return 'carga-cotizacion';
            }
            if (strpos($f, 'clientes') !== false) {
                return 'carga-clientes';
            }
            if (strpos($f, 'documentaci') !== false) {
                return 'carga-documentacion';
            }
            if (strpos($f, 'entrega') !== false) {
                return 'carga-entrega';
            }
            if (strpos($f, 'factura') !== false) {
                return 'carga-factura-guia';
            }
            if (strpos($f, 'aduana') !== false) {
                return 'carga-aduana';
            }

            return strpos($m, 'completados') !== false ? 'carga-completados' : 'carga-abiertos';
        }
        if (strpos($m, 'curso/alumnos') !== false) {
            return 'curso-alumnos';
        }
        if (strpos($m, 'curso/campanas') !== false) {
            return 'curso-campanas';
        }
        if (strpos($m, 'curso/pagos') !== false) {
            return 'curso-pagos';
        }
        if (strpos($m, 'curso/planes') !== false) {
            return 'curso-planes';
        }

        return $this->shotKey($modulo, $flow, $titulo);
    }

    /**
     * @return string|null
     */
    private function shotKey($modulo, $flow, $titulo)
    {
        $m = mb_strtolower((string) $modulo);
        $f = mb_strtolower((string) $flow);
        $t = mb_strtolower((string) $titulo);

        if (strpos($m, 'cargaconsolidada') !== false) {
            if (strpos($f, 'cotización final') !== false || strpos($f, 'cotizacion final') !== false) {
                if (strpos($t, 'subir factura') !== false) {
                    return 'carga-cotizacion-final-subir';
                }
                if (strpos($t, 'plantilla general') !== false) {
                    return 'carga-cotizacion-final-general';
                }
                if (strpos($t, 'plantilla final') !== false) {
                    return 'carga-cotizacion-final-final';
                }
                if (strpos($t, 'ver plantillas') !== false) {
                    return 'carga-cotizacion-final-ver';
                }

                return 'carga-cotizacion-final';
            }
            if (strpos($f, 'cotización') !== false || strpos($f, 'cotizacion') !== false) {
                if (strpos($t, 'embarcar') !== false) {
                    return 'carga-cotizacion-embarcar';
                }
                if (strpos($t, 'pagos') !== false) {
                    return 'carga-cotizacion-pagos';
                }

                return 'carga-cotizacion';
            }
            if (strpos($f, 'clientes') !== false) {
                return 'carga-clientes';
            }
            if (strpos($f, 'documentaci') !== false) {
                if (strpos($t, 'factura general') !== false) {
                    return 'carga-documentacion-factura-general';
                }
                if (strpos($t, 'nuevo documento') !== false) {
                    return 'carga-documentacion-nuevo';
                }
                if (strpos($t, 'carpeta') !== false || strpos($t, 'excel') !== false) {
                    return 'carga-documentacion-carpetas';
                }
                if (strpos($t, 'descargar plantillas') !== false) {
                    return 'carga-documentacion-plantillas';
                }

                return 'carga-documentacion';
            }
            if (strpos($f, 'entrega') !== false) {
                return 'carga-entrega';
            }
            if (strpos($f, 'factura') !== false) {
                return 'carga-factura-guia';
            }
            if (strpos($f, 'aduana') !== false) {
                return 'carga-aduana';
            }
            if (strpos($f, 'plantilla') !== false) {
                return 'carga-cotizacion-final';
            }

            return strpos($m, 'completados') !== false ? 'carga-completados' : 'carga-abiertos';
        }

        if (strpos($m, 'basedatos/clientes') !== false) {
            return 'bd-clientes';
        }
        if (strpos($m, 'basedatos/productos') !== false) {
            return 'bd-productos';
        }
        if (strpos($m, 'basedatos/permisos') !== false) {
            return 'bd-permisos';
        }
        if (strpos($m, 'basedatos/regulaciones') !== false) {
            return 'bd-regulaciones';
        }
        if (strpos($m, 'boletin') !== false) {
            return 'bd-boletin';
        }
        if (strpos($m, 'calendar') !== false) {
            return 'ops-calendar';
        }
        if ($m === 'news') {
            return 'ops-news';
        }
        if (strpos($m, 'viaticos') !== false) {
            return 'ops-viaticos';
        }
        if (strpos($m, 'soporte') !== false) {
            return 'ops-soporte';
        }
        if (strpos($m, 'whatsapp') !== false) {
            return 'ops-whatsapp';
        }
        if (strpos($m, 'curso/alumnos') !== false) {
            if (strpos($t, 'importe') !== false || (strpos($t, 'estado') !== false && strpos($t, 'mensaje') === false)) {
                return 'curso-alumnos-fila-importe';
            }
            if (strpos($t, 'mensaje') !== false || strpos($t, 'eliminar') !== false) {
                return 'curso-alumnos-fila-mensaje';
            }
            if (strpos($t, 'curso y campaña') !== false || strpos($t, 'usuario a creado') !== false) {
                return 'curso-alumnos-curso';
            }
            if (strpos($t, 'ficha') !== false || strpos($t, 'lápiz') !== false || strpos($t, 'lapiz') !== false) {
                return 'curso-alumnos-ficha';
            }
            if (strpos($t, 'aula') !== false || strpos($t, 'crear la cuenta') !== false || strpos($t, 'crear usuario') !== false) {
                return 'curso-alumnos-aula';
            }
            if (strpos($t, 'constancia') !== false || strpos($t, 'vista previa') !== false) {
                return 'curso-alumnos-constancia';
            }

            return 'curso-alumnos';
        }
        if (strpos($m, 'curso/campanas') !== false) {
            return 'curso-campanas';
        }
        if (strpos($m, 'curso/pagos') !== false) {
            return 'curso-pagos';
        }
        if (strpos($m, 'curso/planes') !== false) {
            return 'curso-planes';
        }
        if (strpos($m, 'cotizaciones') !== false) {
            return 'cotizador-cotizaciones';
        }
        if (strpos($m, 'mi-progreso') !== false) {
            return 'ops-progreso';
        }
        if (strpos($m, 'copiloto') !== false) {
            return 'ops-copiloto';
        }
        if (strpos($m, 'verificacion') !== false) {
            return 'ops-verificacion';
        }
        if (strpos($m, 'inspeccionados') !== false) {
            return 'ops-inspeccionados';
        }
        if (strpos($m, 'datos-facturacion') !== false) {
            return 'ops-facturacion';
        }
        if (strpos($m, 'landing') !== false) {
            return 'ops-leads';
        }
        if (strpos($m, 'panel-acceso/cargos') !== false) {
            return 'panel-cargos';
        }
        if (strpos($m, 'panel-acceso/usuarios') !== false) {
            return 'panel-usuarios';
        }
        if (strpos($m, 'panel-acceso/permisos') !== false) {
            return 'panel-permisos';
        }
        if (strpos($m, 'agente-compra') !== false) {
            return 'ops-agente';
        }

        return null;
    }
}
