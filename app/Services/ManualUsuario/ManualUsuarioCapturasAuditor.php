<?php

namespace App\Services\ManualUsuario;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ManualUsuarioCapturasAuditor
{
    public function audit(array $manifest, string $directory, array $options = []): array
    {
        $minimumWidth = (int) ($options['minimum_width'] ?? 800);
        $minimumHeight = (int) ($options['minimum_height'] ?? 450);
        $captures = $this->normalizeCaptures($manifest);
        $files = $this->pngFiles($directory);
        $expected = [];
        $issues = [];
        $fileDetails = [];
        $keys = [];

        foreach ($captures as $index => $capture) {
            $key = isset($capture['capture_key']) ? trim((string) $capture['capture_key']) : '';
            if ($key === '') {
                $issues[] = $this->issue('missing_capture_key', 'error', [
                    'block_id' => $capture['block_id'] ?? null,
                    'index' => $index,
                ]);
                continue;
            }
            try {
                ManualUsuarioCaptureKey::validate($key);
            } catch (\InvalidArgumentException $e) {
                $issues[] = $this->issue('invalid_capture_key', 'error', [
                    'capture_key' => $key,
                    'message' => $e->getMessage(),
                ]);
            }
            $scope = ($capture['roles'][0] ?? '') . '|' . ($capture['screen'] ?? '') . '|' . $key;
            if (isset($keys[$scope])) {
                $issues[] = $this->issue('duplicate_capture_key', 'error', [
                    'capture_key' => $key,
                    'role' => $capture['roles'][0] ?? null,
                    'screen' => $capture['screen'] ?? null,
                    'block_ids' => [$keys[$scope], $capture['block_id'] ?? null],
                ]);
            }
            $keys[$scope] = $capture['block_id'] ?? $index;

            $identity = null;
            try {
                $identity = ManualUsuarioCaptureKey::identity(
                    $key,
                    isset($capture['alias_of']) ? (string) $capture['alias_of'] : null
                );
            } catch (\InvalidArgumentException $e) {
                $identity = $key;
            }
            $canonicalOutput = ManualUsuarioCaptureKey::output($identity);
            $declaredOutput = !empty($capture['output'])
                ? str_replace('\\', '/', (string) $capture['output'])
                : $canonicalOutput;
            $expected[$canonicalOutput] = $capture;
            if ($declaredOutput !== $canonicalOutput) {
                $expected[$declaredOutput] = $capture;
            }
            $pngFound = isset($files[$canonicalOutput]) || isset($files[$declaredOutput]);
            if (!$pngFound) {
                foreach ($files as $relative => $absolute) {
                    if (basename($relative) === basename($canonicalOutput)) {
                        $pngFound = true;
                        $expected[$relative] = $capture;
                        break;
                    }
                }
            }
            if (!$pngFound) {
                $issues[] = $this->issue('missing_png', 'error', [
                    'capture_key' => $key,
                    'identity' => $identity,
                    'output' => $canonicalOutput,
                ]);
            }
            if (empty($capture['media_id'])) {
                $issues[] = $this->issue('missing_media_id', 'error', [
                    'capture_key' => $key,
                    'block_id' => $capture['block_id'] ?? null,
                ]);
            }
        }

        $knownOutputs = $expected;
        foreach ($files as $relative => $absolute) {
            if (!isset($knownOutputs[$relative])) {
                $variantCapture = $this->captureForVariantOutput($relative, $captures);
                if ($variantCapture !== null) {
                    $knownOutputs[$relative] = $variantCapture;
                }
            }
            if (!isset($knownOutputs[$relative])) {
                $issues[] = $this->issue('orphan_png', 'error', ['output' => $relative]);
            }

            $size = @getimagesize($absolute);
            $width = is_array($size) ? (int) $size[0] : 0;
            $height = is_array($size) ? (int) $size[1] : 0;
            $hash = is_file($absolute) ? hash_file('sha256', $absolute) : false;
            $fileDetails[$relative] = [
                'width' => $width,
                'height' => $height,
                'sha256' => $hash ?: null,
                'bytes' => is_file($absolute) ? filesize($absolute) : null,
            ];
            if ($width === 0 || $height === 0) {
                $issues[] = $this->issue('invalid_png', 'error', ['output' => $relative]);
            } elseif ($width < $minimumWidth || $height < $minimumHeight) {
                $issues[] = $this->issue('small_dimensions', 'warning', [
                    'output' => $relative,
                    'width' => $width,
                    'height' => $height,
                    'minimum_width' => $minimumWidth,
                    'minimum_height' => $minimumHeight,
                ]);
            }
        }

        $hashGroups = [];
        foreach ($fileDetails as $output => $details) {
            if (!empty($details['sha256'])) {
                $hashGroups[$details['sha256']][] = $output;
            }
        }
        foreach ($hashGroups as $hash => $outputs) {
            if (count($outputs) < 2 || $this->duplicateHashIsAliased($outputs, $knownOutputs)) {
                continue;
            }
            $issues[] = $this->issue('duplicate_hash_without_alias', 'error', [
                'sha256' => $hash,
                'outputs' => $outputs,
            ]);
        }

        $errors = count(array_filter($issues, fn (array $issue) => $issue['severity'] === 'error'));
        $warnings = count($issues) - $errors;

        return [
            'summary' => [
                'captures' => count($captures),
                'png_files' => count($files),
                'with_media_id' => count(array_filter($captures, fn (array $item) => !empty($item['media_id']))),
                'errors' => $errors,
                'warnings' => $warnings,
            ],
            'issues' => $issues,
            'files' => $fileDetails,
            'ok' => $errors === 0,
        ];
    }

    private function normalizeCaptures(array $manifest): array
    {
        if (isset($manifest['captures']) && is_array($manifest['captures'])) {
            return $manifest['captures'];
        }

        $captures = [];
        foreach ($manifest['roles'] ?? [] as $role) {
            foreach ($role['screens'] ?? [] as $screen) {
                foreach ($screen['shots'] ?? [] as $shot) {
                    $manual = isset($shot['manual']) && is_array($shot['manual']) ? $shot['manual'] : [];
                    $key = $shot['id'] ?? $manual['captureKey'] ?? null;
                    $captures[] = [
                        'capture_key' => $key,
                        'roles' => [(string) ($role['slug'] ?? '')],
                        'screen' => (string) ($screen['id'] ?? ''),
                        'screen_url' => $screen['url'] ?? null,
                        'output' => $manual['output'] ?? ($key
                            ? ManualUsuarioCaptureKey::runnerOutput(
                                (string) ($role['slug'] ?? ''),
                                (string) ($screen['id'] ?? ''),
                                (string) $key
                            )
                            : null),
                        'alias_of' => $manual['aliasOf'] ?? null,
                        'media_id' => $manual['mediaId'] ?? null,
                        'page_id' => $manual['pageId'] ?? null,
                        'block_id' => $manual['blockId'] ?? null,
                    ];
                }
            }
        }

        return $captures;
    }

    private function captureForVariantOutput(string $output, array $captures): ?array
    {
        foreach ($captures as $capture) {
            $expected = str_replace('\\', '/', (string) ($capture['output'] ?? ''));
            if (!preg_match('/^(.*?)(?:--[^\/]+)?\.png$/', $expected, $matches)) {
                continue;
            }
            if (preg_match('/^' . preg_quote($matches[1], '/') . '--[^\/]+\.png$/', $output)) {
                return $capture;
            }
        }

        return null;
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
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'png') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $files[$relative] = $file->getPathname();
        }
        ksort($files);

        return $files;
    }

    private function duplicateHashIsAliased(array $outputs, array $expected): bool
    {
        $items = [];
        foreach ($outputs as $output) {
            if (!isset($expected[$output]) || empty($expected[$output]['capture_key'])) {
                return false;
            }
            $items[] = $expected[$output];
        }

        $identities = array_unique(array_map(function (array $item) {
            try {
                return ManualUsuarioCaptureKey::identity(
                    isset($item['capture_key']) ? (string) $item['capture_key'] : null,
                    isset($item['alias_of']) ? (string) $item['alias_of'] : null
                ) ?: ($item['capture_key'] ?? '');
            } catch (\InvalidArgumentException $e) {
                return ($item['roles'][0] ?? '') . '|' . ($item['screen'] ?? '') . '|'
                    . ($item['capture_key'] ?? '');
            }
        }, $items));
        if (count($identities) === 1) {
            return true;
        }

        foreach ($items as $candidate) {
            $canonical = (string) $candidate['capture_key'];
            $valid = true;
            foreach ($items as $item) {
                if ((string) $item['capture_key'] === $canonical) {
                    continue;
                }
                if (($item['alias_of'] ?? null) !== $canonical) {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                return true;
            }
        }

        return false;
    }

    private function issue(string $code, string $severity, array $context): array
    {
        return array_merge(['code' => $code, 'severity' => $severity], $context);
    }
}
