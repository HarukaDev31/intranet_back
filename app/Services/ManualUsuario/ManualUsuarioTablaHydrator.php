<?php

namespace App\Services\ManualUsuario;

/**
 * Hidrata snapshots de widgets con respuestas reales de API (tablas, filtros, modales, etc.).
 */
class ManualUsuarioTablaHydrator
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $liveApi
     * @return array<string, mixed>
     */
    public function hydrate(
        array $snapshot,
        ?array $liveApi,
        ?string $bearerToken,
        ?string $roleSlug = null,
        string $tipo = 'tabla'
    ): array {
        $liveApi = $liveApi ?: ($snapshot['live_api'] ?? null);
        $kind = is_array($liveApi) ? (string) ($liveApi['kind'] ?? '') : '';
        if ($kind === '') {
            $kind = $this->defaultKind($tipo);
        }

        if (!is_array($liveApi) || empty($liveApi['path']) || !$bearerToken) {
            return $this->normalizeSnapshot($snapshot, $tipo);
        }

        try {
            $json = $this->fetchLive($liveApi, $bearerToken, $roleSlug);
        } catch (\Throwable $e) {
            $snapshot['hydrate_error'] = $e->getMessage();

            return $this->normalizeSnapshot($snapshot, $tipo);
        }

        if ($json === null) {
            $snapshot['hydrate_error'] = $snapshot['hydrate_error'] ?? 'Sin respuesta';

            return $this->normalizeSnapshot($snapshot, $tipo);
        }

        $snapshot = match ($kind) {
            'filter_options', 'filtros' => $this->hydrateFilters($snapshot, $json, $liveApi),
            'form_options', 'modal' => $this->hydrateFormOptions($snapshot, $json, $liveApi),
            default => $this->hydrateList($snapshot, $json, $liveApi, $roleSlug),
        };

        $snapshot['live_api'] = $liveApi;
        $snapshot['live_at'] = now()->toIso8601String();
        $snapshot['role_slug'] = $roleSlug;
        unset($snapshot['hydrate_error']);

        return $snapshot;
    }

    private function defaultKind(string $tipo): string
    {
        return match ($tipo) {
            'filtros' => 'filter_options',
            'modal' => 'form_options',
            default => 'list',
        };
    }

    /**
     * @param  array<string, mixed>  $liveApi
     * @return array<string, mixed>|null
     */
    private function fetchLive(array $liveApi, string $bearerToken, ?string $roleSlug): ?array
    {
        $path = '/' . ltrim((string) $liveApi['path'], '/');
        $method = strtoupper((string) ($liveApi['method'] ?? 'GET'));
        $params = is_array($liveApi['params'] ?? null) ? $liveApi['params'] : [];

        if (($liveApi['kind'] ?? 'list') === 'list' || empty($liveApi['kind'])) {
            if (!isset($params['limit'])) {
                $params['limit'] = 15;
            }
            if (!isset($params['page'])) {
                $params['page'] = 1;
            }
        }

        $sub = \Illuminate\Http\Request::create($path, $method, $params);
        $sub->headers->set('Authorization', 'Bearer ' . $bearerToken);
        $sub->headers->set('Accept', 'application/json');
        if ($roleSlug) {
            $sub->headers->set('X-Manual-Role-Slug', $roleSlug);
        }

        $response = app()->handle($sub);
        $status = $response->getStatusCode();
        $json = json_decode($response->getContent(), true);

        if ($status < 200 || $status >= 300 || !is_array($json)) {
            throw new \RuntimeException('HTTP ' . $status);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $liveApi
     * @return array<string, mixed>
     */
    private function hydrateList(array $snapshot, array $json, array $liveApi, ?string $roleSlug): array
    {
        $rows = $this->extractRows($json, $liveApi['data_key'] ?? null);
        $columns = $this->normalizeColumns($snapshot['columns'] ?? []);
        $snapshot['columns'] = $columns;
        $snapshot['rows'] = $this->mapRows(array_slice($rows, 0, 15), $columns);

        return $snapshot;
    }

    /**
     * Rellena options de filtros desde API (lista de fields o mapa key → options).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $liveApi
     * @return array<string, mixed>
     */
    private function hydrateFilters(array $snapshot, array $json, array $liveApi): array
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $payload = $json['data'] ?? $json;

        // data: [ { key, label, options }, ... ]
        if (is_array($payload) && $this->isList($payload) && isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['key'])) {
            $byKey = [];
            $merged = [];
            foreach ($payload as $item) {
                if (!is_array($item) || empty($item['key'])) {
                    continue;
                }
                $key = (string) $item['key'];
                $field = [
                    'key' => $key,
                    'label' => (string) ($item['label'] ?? $key),
                    'type' => (string) ($item['type'] ?? 'select'),
                    'placeholder' => (string) ($item['placeholder'] ?? 'Seleccionar'),
                    'value' => $item['value'] ?? ($item['options'][0]['value'] ?? ''),
                    'options' => $this->normalizeOptions($item['options'] ?? []),
                ];
                $byKey[$key] = $field;
                $merged[] = $field;
            }
            // Conservar fields estáticos no presentes en API
            foreach ($fields as $f) {
                if (!is_array($f) || empty($f['key'])) {
                    continue;
                }
                $k = (string) $f['key'];
                if (!isset($byKey[$k])) {
                    $merged[] = $f;
                }
            }
            $snapshot['fields'] = $merged;

            return $snapshot;
        }

        // data: { campanas: [...], estados_pago: [...] }
        if (is_array($payload) && !$this->isList($payload)) {
            foreach ($fields as &$field) {
                if (!is_array($field) || empty($field['key'])) {
                    continue;
                }
                $key = (string) $field['key'];
                $opts = $payload[$key] ?? $payload[$this->altKey($key)] ?? null;
                if (is_array($opts)) {
                    $field['options'] = $this->normalizeOptions($opts);
                    if (($field['value'] ?? '') === '' && isset($field['options'][0]['value'])) {
                        $field['value'] = $field['options'][0]['value'];
                    }
                }
            }
            unset($field);
            $snapshot['fields'] = $fields;

            return $snapshot;
        }

        return $snapshot;
    }

    /**
     * Igual que filtros pero sobre snapshot.fields de un modal.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $liveApi
     * @return array<string, mixed>
     */
    private function hydrateFormOptions(array $snapshot, array $json, array $liveApi): array
    {
        $asFilters = $this->hydrateFilters(['fields' => $snapshot['fields'] ?? []], $json, $liveApi);
        $snapshot['fields'] = $asFilters['fields'] ?? ($snapshot['fields'] ?? []);

        return $snapshot;
    }

    private function altKey(string $key): string
    {
        // fecha_inicio ↔ fechaInicio
        if (str_contains($key, '_')) {
            return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
        }

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key) ?? $key);
    }

    /**
     * @param  mixed  $options
     * @return array<int, array{label: string, value: string}>
     */
    private function normalizeOptions($options): array
    {
        if (!is_array($options)) {
            return [];
        }
        $out = [];
        foreach ($options as $opt) {
            if (is_string($opt) || is_numeric($opt)) {
                $out[] = ['label' => (string) $opt, 'value' => (string) $opt];
                continue;
            }
            if (!is_array($opt)) {
                continue;
            }
            $label = (string) ($opt['label'] ?? $opt['name'] ?? $opt['No_Campana'] ?? $opt['value'] ?? '');
            $value = $opt['value'] ?? $opt['id'] ?? $opt['ID_Campana'] ?? $label;
            if ($label === '' && $value === '') {
                continue;
            }
            $out[] = ['label' => $label !== '' ? $label : (string) $value, 'value' => (string) $value];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function normalizeSnapshot(array $snapshot, string $tipo): array
    {
        if ($tipo === 'filtros' || $tipo === 'modal') {
            if (!isset($snapshot['fields']) || !is_array($snapshot['fields'])) {
                $snapshot['fields'] = [];
            }

            return $snapshot;
        }

        $snapshot['columns'] = $this->normalizeColumns($snapshot['columns'] ?? []);
        if (!isset($snapshot['rows']) || !is_array($snapshot['rows'])) {
            $snapshot['rows'] = [];
        }

        return $snapshot;
    }

    /**
     * @param  mixed  $json
     * @return array<int, array<string, mixed>>
     */
    private function extractRows($json, ?string $dataKey): array
    {
        if (!is_array($json)) {
            return [];
        }

        if ($dataKey) {
            $rows = data_get($json, $dataKey, []);
            if (is_array($rows) && $this->isList($rows)) {
                return $rows;
            }
        }

        if (isset($json['data']) && is_array($json['data']) && $this->isList($json['data'])) {
            return $json['data'];
        }

        if (isset($json['data']['data']) && is_array($json['data']['data']) && $this->isList($json['data']['data'])) {
            return $json['data']['data'];
        }

        if ($this->isList($json)) {
            return $json;
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return array<int, array{accessorKey: string, header: string}>
     */
    private function normalizeColumns(array $columns): array
    {
        $out = [];
        foreach ($columns as $i => $col) {
            if (is_string($col)) {
                $key = 'c' . $i;
                $header = $col;
            } elseif (is_array($col)) {
                $key = (string) ($col['accessorKey'] ?? $col['key'] ?? ('c' . $i));
                $header = (string) ($col['header'] ?? $col['label'] ?? $key);
            } else {
                continue;
            }
            if (in_array(strtolower($key), ['acciones', 'action', 'actions'], true)
                || strtolower($header) === 'acciones') {
                continue;
            }
            $out[] = ['accessorKey' => $key, 'header' => $header];
        }

        return $out ?: [
            ['accessorKey' => 'c0', 'header' => 'Columna 1'],
            ['accessorKey' => 'c1', 'header' => 'Columna 2'],
            ['accessorKey' => 'c2', 'header' => 'Columna 3'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array{accessorKey: string, header: string}>  $columns
     * @return array<int, array<string, mixed>>
     */
    private function mapRows(array $rows, array $columns): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = ['id' => $row['id'] ?? $row['ID_Pedido_Curso'] ?? $i];
            foreach ($columns as $col) {
                $key = $col['accessorKey'];
                $mapped[$key] = $this->cellValue($row, $key, $i);
            }
            $out[] = $mapped;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function cellValue(array $row, string $accessorKey, int $index): string
    {
        if ($accessorKey === 'index' || $accessorKey === 'n' || $accessorKey === 'N.') {
            return (string) ($index + 1);
        }

        if (array_key_exists($accessorKey, $row) && !is_array($row[$accessorKey])) {
            return $this->stringify($row[$accessorKey]);
        }

        return match (strtolower($accessorKey)) {
            'cliente', 'contacto' => $this->joinFields($row, [
                'No_Entidad', 'Nu_Documento_Identidad', 'Nu_Celular_Entidad', 'Txt_Email_Entidad',
            ]),
            'fecha' => $this->stringify($row['Fe_Registro'] ?? $row['fecha'] ?? $row['Fecha'] ?? ''),
            'precio' => $this->stringify($row['Ss_Total'] ?? $row['precio'] ?? ''),
            'pagado' => $this->stringify($row['total_pagos'] ?? $row['pagado'] ?? ''),
            'adelanto' => $this->stringify($row['adelanto'] ?? (is_array($row['pagos_details'] ?? null) ? count($row['pagos_details']) . ' pago(s)' : '')),
            'campana', 'campaña' => $this->stringify($row['No_Campana'] ?? $row['campana'] ?? $row['ID_Campana'] ?? ''),
            'usuario' => $this->stringify($row['Nu_Estado_Usuario_Externo'] ?? $row['usuario'] ?? ''),
            'tipo_curso', 'curso' => $this->stringify($row['tipo_curso'] ?? $row['No_Tipo_Curso'] ?? ''),
            'estado', 'estado_pago' => $this->stringify($row['estado_pago'] ?? $row['Estado'] ?? $row['estado'] ?? ''),
            default => $this->stringify(data_get($row, $accessorKey, '')),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $fields
     */
    private function joinFields(array $row, array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            $v = $row[$f] ?? null;
            if ($v !== null && $v !== '') {
                $parts[] = $this->stringify($v);
            }
        }

        return implode(' · ', $parts);
    }

    private function stringify($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return '';
    }

    private function isList(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
