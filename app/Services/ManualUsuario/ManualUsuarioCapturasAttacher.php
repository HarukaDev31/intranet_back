<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use App\Models\ManualUsuario\ManualPagina;
use App\Traits\UsesObjectStorage;

/**
 * Sube capturas de resources/manual/capturas y las enlaza a bloques media.
 * No borra páginas ni reescribe copy.
 */
class ManualUsuarioCapturasAttacher
{
    use UsesObjectStorage;

    /**
     * @return array{uploaded:int, linked:int, skipped:int}
     */
    public function attach()
    {
        $dir = resource_path('manual/capturas');
        $mediaIds = [];
        $uploaded = 0;

        if (is_dir($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.png');
            if (!is_array($files)) {
                $files = [];
            }
            foreach ($files as $abs) {
                $key = pathinfo($abs, PATHINFO_FILENAME);
                if ($key === '') {
                    continue;
                }
                $mediaIds[$key] = $this->storePng($abs, $key);
                $uploaded++;
            }
        }

        $linked = 0;
        $skipped = 0;
        $bloques = ManualBloque::query()
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->get();

        foreach ($bloques as $bloque) {
            $pagina = ManualPagina::query()->find($bloque->pagina_id);
            $parent = $bloque->parent_id
                ? ManualBloque::query()->find($bloque->parent_id)
                : null;
            $key = $this->shotKey(
                $pagina ? (string) $pagina->modulo_key : '',
                $parent ? (string) $parent->titulo : '',
                (string) $bloque->titulo
            );
            if ($key === null || empty($mediaIds[$key])) {
                $skipped++;
                continue;
            }
            $payload = is_array($bloque->payload) ? $bloque->payload : [];
            if (!isset($payload['snapshot']) || !is_array($payload['snapshot'])) {
                $payload['snapshot'] = [];
            }
            $payload['snapshot']['media_id'] = (int) $mediaIds[$key];
            $bloque->payload = $payload;
            $bloque->save();
            $linked++;
        }

        return compact('uploaded', 'linked', 'skipped');
    }

    /**
     * @return int
     */
    private function storePng($abs, $key)
    {
        $bin = file_get_contents($abs);
        if ($bin === false) {
            throw new \RuntimeException('No se pudo leer ' . $abs);
        }

        $filename = $key . '.png';
        $relative = 'manual/capturas/' . $filename;
        $localDir = storage_path('app/manual/capturas');
        if (!is_dir($localDir)) {
            mkdir($localDir, 0775, true);
        }
        file_put_contents($localDir . DIRECTORY_SEPARATOR . $filename, $bin);

        $dbPath = $relative;
        try {
            $dbPath = $this->storagePutContentsForCdn($relative, $bin);
        } catch (\Throwable $e) {
            $dbPath = $relative;
        }

        $media = ManualMedia::query()
            ->where('path', 'like', '%manual/capturas/' . $filename)
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
     * @return string|null
     */
    private function shotKey($modulo, $flow, $titulo)
    {
        $m = mb_strtolower((string) $modulo);
        $f = mb_strtolower((string) $flow);
        $t = mb_strtolower((string) $titulo);

        if (strpos($m, 'cargaconsolidada') !== false) {
            if (strpos($f, 'cotización final') !== false || strpos($f, 'cotizacion final') !== false) {
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
