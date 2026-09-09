<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualMedia;
use Illuminate\Support\Collection;
use RuntimeException;

class ManualUsuarioCapturasInventory
{
    public const SCHEMA_VERSION = 1;

    public function build(): array
    {
        return $this->buildFlat();
    }

    public function buildFlat(): array
    {
        $blocks = ManualBloque::query()
            ->with(['pagina', 'parent'])
            ->where('tipo', ManualBloque::TIPO_MEDIA)
            ->orderBy('pagina_id')
            ->orderBy('parent_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $mediaIds = $blocks->map(function (ManualBloque $block) {
            return (int) data_get($block->payload, 'snapshot.media_id', 0);
        })->filter()->unique()->values();
        $media = $mediaIds->isEmpty()
            ? collect()
            : ManualMedia::query()->whereIn('id', $mediaIds)->get()->keyBy('id');

        $manifest = $this->fromBlocks($blocks, $media);
        $pageIds = $blocks->pluck('pagina_id')->unique()->values();
        $routes = $pageIds->isEmpty()
            ? collect()
            : ManualBloque::query()
                ->whereIn('pagina_id', $pageIds)
                ->whereNull('parent_id')
                ->orderBy('id')
                ->get()
                ->keyBy('pagina_id');
        foreach ($manifest['captures'] as &$capture) {
            if ($capture['screen_url'] === null && ($root = $routes->get($capture['page_id']))) {
                $capture['screen_url'] = $root->clave ? (string) $root->clave : null;
            }
            $capture['url'] = $capture['screen_url'];
        }
        unset($capture);
        $manifest['screens'] = [];
        foreach ($manifest['captures'] as $capture) {
            if (!empty($capture['screen']) && !empty($capture['screen_url'])) {
                $manifest['screens'][$capture['screen']] = ['url' => $capture['screen_url']];
            }
        }
        ksort($manifest['screens']);

        return $manifest;
    }

    public function fromBlocks(iterable $blocks, ?Collection $media = null): array
    {
        $media = $media ?: collect();
        $items = [];

        foreach ($blocks as $block) {
            $snapshot = data_get($block->payload, 'snapshot', []);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $page = $block->pagina;
            $parent = $block->parent;
            $captureKey = !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
            $mediaId = !empty($snapshot['media_id']) ? (int) $snapshot['media_id'] : null;
            $mediaRow = $mediaId ? $media->get($mediaId) : null;
            $step = isset($snapshot['capture_step']) && is_array($snapshot['capture_step'])
                ? $snapshot['capture_step']
                : [
                    'number' => (int) $block->orden,
                    'title' => (string) $block->titulo,
                ];

            $config = isset($snapshot['capture_config']) && is_array($snapshot['capture_config'])
                ? $snapshot['capture_config']
                : [];
            $item = [
                'capture_key' => $captureKey,
                'roles' => array_values(array_filter([
                    !empty($snapshot['capture_role'])
                        ? (string) $snapshot['capture_role']
                        : ($page ? (string) $page->role_slug : null),
                ])),
                'screen' => !empty($snapshot['capture_screen'])
                    ? (string) $snapshot['capture_screen']
                    : ($page ? (string) $page->modulo_key : null),
                'screen_url' => !empty($snapshot['capture_screen_url'])
                    ? (string) $snapshot['capture_screen_url']
                    : null,
                'modulo' => !empty($snapshot['capture_modulo'])
                    ? (string) $snapshot['capture_modulo']
                    : ($page ? (string) $page->modulo_key : null),
                'flow' => !empty($snapshot['capture_flow'])
                    ? (string) $snapshot['capture_flow']
                    : ($parent ? (string) $parent->titulo : null),
                'step' => $step,
                'hint' => !empty($snapshot['capture_hint'])
                    ? (string) $snapshot['capture_hint']
                    : (isset($snapshot['caption']) ? (string) $snapshot['caption'] : null),
                'output' => !empty($snapshot['capture_output'])
                    ? (string) $snapshot['capture_output']
                    : ($this->identityOf($snapshot)
                        ? ManualUsuarioCaptureKey::output($this->identityOf($snapshot))
                        : null),
                'config' => $config,
                'alias_of' => !empty($snapshot['capture_alias_of'])
                    ? (string) $snapshot['capture_alias_of']
                    : null,
                'media_id' => $mediaId,
                'media_path' => $mediaRow ? (string) $mediaRow->path : null,
                'page_id' => (int) $block->pagina_id,
                'block_id' => (int) $block->id,
            ];
            foreach ([
                'type' => 'type',
                'target' => 'target',
                'actions' => 'actions',
                'expectedText' => 'expected_text',
                'padding' => 'padding',
                'masks' => 'masks',
                'piiAllow' => 'pii_allow',
                'expectedHash' => 'expected_hash',
                'enabled' => 'enabled',
                'url' => 'url',
            ] as $source => $target) {
                if (array_key_exists($source, $config)) {
                    $item[$target] = $config[$source];
                }
            }
            $items[] = $item;
        }

        usort($items, function (array $a, array $b) {
            return [
                $a['roles'][0] ?? '',
                $a['modulo'] ?? '',
                $a['flow'] ?? '',
                $a['step']['number'] ?? 0,
                $a['block_id'],
            ] <=> [
                $b['roles'][0] ?? '',
                $b['modulo'] ?? '',
                $b['flow'] ?? '',
                $b['step']['number'] ?? 0,
                $b['block_id'],
            ];
        });

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'captures' => $items,
        ];
    }

    public function toRunnerManifest(array $flatManifest): array
    {
        $roles = [];
        $seenIdentities = [];
        $ordered = $this->capturesCanonicalFirst($flatManifest['captures'] ?? []);
        foreach ($ordered as $capture) {
            $roleSlug = (string) ($capture['roles'][0] ?? '');
            $screenSource = (string) ($capture['screen'] ?? $capture['modulo'] ?? '');
            $screenId = ManualUsuarioCaptureKey::screenId($screenSource);
            if (!isset($roles[$roleSlug])) {
                $roles[$roleSlug] = ['slug' => $roleSlug, 'screens' => []];
            }
            if (!isset($roles[$roleSlug]['screens'][$screenId])) {
                $screenUrl = (string) ($capture['screen_url'] ?? '');
                $roles[$roleSlug]['screens'][$screenId] = [
                    'id' => $screenId,
                    'url' => $screenUrl,
                    'enabled' => $screenUrl !== '',
                    'disabledReason' => $screenUrl === ''
                        ? 'La aplicación no implementa una pantalla navegable para este módulo.'
                        : null,
                    'shots' => [],
                ];
            }

            $config = isset($capture['config']) && is_array($capture['config'])
                ? $capture['config']
                : [];
            $identity = $this->identityOfCapture($capture);
            $canonicalOutput = $identity ? ManualUsuarioCaptureKey::output($identity) : ($capture['output'] ?? null);
            $isAlias = $identity !== null && (
                !empty($capture['alias_of']) || isset($seenIdentities[$identity])
            );
            if ($identity && !$isAlias) {
                $seenIdentities[$identity] = true;
            }
            $shot = array_merge([
                'id' => $capture['capture_key'] ?: $identity,
                'type' => 'page',
            ], $config);
            if ($isAlias) {
                $shot['enabled'] = false;
            }
            $shot['intent'] = [
                'title' => (string) ($capture['step']['title'] ?? ''),
                'hint' => (string) ($capture['hint'] ?? ''),
            ];
            $shot['manual'] = [
                'captureKey' => $capture['capture_key'],
                'aliasOf' => $isAlias ? $identity : ($capture['alias_of'] ?? null),
                'output' => $canonicalOutput,
                'hint' => $capture['hint'] ?? null,
                'flow' => $capture['flow'] ?? null,
                'step' => $capture['step'] ?? null,
                'mediaId' => $capture['media_id'] ?? null,
                'pageId' => $capture['page_id'] ?? null,
                'blockId' => $capture['block_id'] ?? null,
            ];
            $roles[$roleSlug]['screens'][$screenId]['shots'][] = $shot;
        }

        foreach ($roles as &$role) {
            $role['screens'] = array_values($role['screens']);
            usort($role['screens'], fn (array $a, array $b) => $a['id'] <=> $b['id']);
            foreach ($role['screens'] as &$screen) {
                usort($screen['shots'], fn (array $a, array $b) => $a['id'] <=> $b['id']);
            }
            unset($screen);
        }
        unset($role);
        ksort($roles);

        return [
            'version' => self::SCHEMA_VERSION,
            'roles' => array_values($roles),
        ];
    }

    public function validateRunnerManifest(array $manifest): array
    {
        $issues = [];
        $types = ['control', 'fila', 'modal', 'destino', 'seccion', 'page'];
        foreach ($manifest['roles'] ?? [] as $role) {
            $roleSlug = (string) ($role['slug'] ?? '');
            foreach ($role['screens'] ?? [] as $screen) {
                $screenId = (string) ($screen['id'] ?? '');
                $url = (string) ($screen['url'] ?? '');
                if (($screen['enabled'] ?? true) === false) {
                    continue;
                }
                if ($url === '' || strpos($url, '/') !== 0) {
                    $issues[] = $roleSlug . '/' . $screenId . ': url relativa faltante o inválida';
                }
                $shotIds = [];
                foreach ($screen['shots'] ?? [] as $shot) {
                    $shotId = (string) ($shot['id'] ?? '');
                    if ($shotId === '') {
                        $issues[] = $roleSlug . '/' . $screenId . ': shot sin id/capture_key';
                        continue;
                    }
                    if (isset($shotIds[$shotId]) && ($shot['enabled'] ?? true) !== false) {
                        $issues[] = $roleSlug . '/' . $screenId . ': shot duplicado ' . $shotId;
                    }
                    $shotIds[$shotId] = true;
                    $type = (string) ($shot['type'] ?? '');
                    if (!in_array($type, $types, true)) {
                        $issues[] = $roleSlug . '/' . $screenId . '/' . $shotId . ': type inválido';
                    } elseif ($type !== 'page' && empty($shot['target'])) {
                        $issues[] = $roleSlug . '/' . $screenId . '/' . $shotId . ': target obligatorio';
                    }
                    if (($shot['enabled'] ?? true) === false) {
                        continue;
                    }
                    $output = (string) ($shot['manual']['output'] ?? '');
                    $canonical = $shotId !== '' ? ManualUsuarioCaptureKey::output($shotId) : '';
                    $legacy = $shotId !== ''
                        ? ManualUsuarioCaptureKey::runnerOutput($roleSlug, $screenId, $shotId)
                        : '';
                    $canonical1920 = preg_replace('/\.png$/i', '--1920x1200.png', $canonical);
                    $legacy1920 = preg_replace('/\.png$/i', '--1920x1200.png', $legacy);
                    if (!in_array($output, [$canonical, $legacy, $canonical1920, $legacy1920], true)) {
                        $issues[] = $roleSlug . '/' . $screenId . '/' . $shotId
                            . ': output no coincide con la ruta canónica del runner';
                    }
                }
            }
        }

        return $issues;
    }

    public function write(string $path, ?array $manifest = null): string
    {
        $manifest = $manifest ?: $this->build();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio del manifiesto: ' . $directory);
        }

        $json = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException('No se pudo escribir el manifiesto: ' . $path);
        }

        return $path;
    }

    private function identityOf(array $snapshot): ?string
    {
        try {
            return ManualUsuarioCaptureKey::identityFromSnapshot($snapshot);
        } catch (\InvalidArgumentException $e) {
            return !empty($snapshot['capture_key']) ? (string) $snapshot['capture_key'] : null;
        }
    }

    private function identityOfCapture(array $capture): ?string
    {
        try {
            return ManualUsuarioCaptureKey::identity(
                isset($capture['capture_key']) ? (string) $capture['capture_key'] : null,
                isset($capture['alias_of']) ? (string) $capture['alias_of'] : null
            );
        } catch (\InvalidArgumentException $e) {
            return !empty($capture['capture_key']) ? (string) $capture['capture_key'] : null;
        }
    }

    /**
     * Canónicas primero para que el runner capture una sola vez por identity.
     *
     * @param  array<int, array<string, mixed>>  $captures
     * @return array<int, array<string, mixed>>
     */
    private function capturesCanonicalFirst(array $captures): array
    {
        usort($captures, function (array $a, array $b) {
            $aAlias = empty($a['alias_of']) ? 0 : 1;
            $bAlias = empty($b['alias_of']) ? 0 : 1;

            return [$aAlias, $a['block_id'] ?? 0] <=> [$bAlias, $b['block_id'] ?? 0];
        });

        return $captures;
    }
}
