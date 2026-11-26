<?php

namespace App\Services\CargaConsolidada;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filtros de año, país, empresa y estado China del listado de consolidados.
 * Cada filtro acepta varios valores (`anio[]=2026&anio[]=2027` o `anio=2026,2027`);
 * las facetas cuentan cada opción con los demás filtros aplicados.
 * El controlador solo llama a filtrar(): esto deja el listado filtrado y devuelve lo que pinta la barra.
 */
class ConsolidadoFiltrosService
{
    public const CLAVES = ['anio', 'id_pais', 'empresa', 'estado_china'];

    /** Etiquetas en inglés del Almacén China → valor guardado. */
    private const ESTADOS_ALIAS = ['WAITING' => 'PENDIENTE', 'RECEIVING' => 'RECIBIENDO', 'FINISH' => 'COMPLETADO'];

    /**
     * Aplica año, país, empresa y estado al listado y devuelve las opciones de la barra.
     *
     * @return array{facets: array<string, array<int, array{value: string, label: string, count: int}>>, total: int, anios: array<int, int>, empresas: array<int, string>, paises: array<int, array{label: string, value: string}>}
     */
    public function filtrar(Builder $query, Request $request): array
    {
        $seleccion = self::seleccion($request);
        $facetas = self::facetas($query, $seleccion);
        $total = (clone $query)->count();
        self::aplicar($query, $seleccion);

        return [
            'facets' => $facetas,
            'total' => $total,
            'anios' => array_map('intval', array_column($facetas['anio'], 'value')),
            'empresas' => array_column($facetas['empresa'], 'value'),
            'paises' => array_map(fn ($p) => ['label' => $p['label'], 'value' => $p['value']], $facetas['id_pais']),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function seleccion(Request $request): array
    {
        $alias = ['anio' => 'year', 'id_pais' => 'pais', 'empresa' => 'company'];
        $seleccion = [];
        foreach (self::CLAVES as $clave) {
            $raw = $request->input($clave, isset($alias[$clave]) ? $request->input($alias[$clave]) : null);
            $valores = is_array($raw) ? $raw : explode(',', (string) $raw);
            $valores = array_values(array_unique(array_filter(
                array_map(fn ($v) => trim((string) $v), $valores),
                fn ($v) => $v !== '' && strtolower($v) !== 'todos'
            )));
            if ($clave === 'estado_china') {
                $valores = array_values(array_unique(array_map(fn ($v) => self::ESTADOS_ALIAS[strtoupper($v)] ?? strtoupper($v), $valores)));
            }
            $seleccion[$clave] = $valores;
        }

        return $seleccion;
    }

    /**
     * @param  array<string, array<int, string>>  $seleccion
     */
    public static function aplicar(Builder $query, array $seleccion, ?string $excepto = null): Builder
    {
        foreach ($seleccion as $clave => $valores) {
            if ($clave === $excepto || $valores === []) {
                continue;
            }
            match ($clave) {
                'anio' => $query->where(function ($q) use ($valores) {
                    $anios = array_values(array_filter(array_map('intval', $valores), fn ($y) => $y >= 2000 && $y <= 2100));
                    $anios === [] ? $q->whereRaw('1 = 0') : $q->whereIn(DB::raw('YEAR(f_inicio)'), $anios);
                }),
                'id_pais' => $query->whereIn('id_pais', array_map('intval', $valores)),
                'empresa' => $query->whereIn(DB::raw('LOWER(TRIM(empresa))'), array_map(fn ($v) => mb_strtolower($v), $valores)),
                'estado_china' => $query->whereIn('estado_china', $valores),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Opciones de cada filtro con su conteo, calculado con los demás filtros aplicados.
     *
     * @param  array<string, array<int, string>>  $seleccion
     * @return array<string, array<int, array{value: string, label: string, count: int}>>
     */
    public static function facetas(Builder $base, array $seleccion): array
    {
        $consulta = function (string $clave) use ($base, $seleccion) {
            $query = self::aplicar(clone $base, $seleccion, $clave);
            $query->getQuery()->orders = null;

            // Consultas agrupadas: se leen como filas planas (stdClass), no como modelos.
            return $query->without(['pais', 'tcYuan', 'paisFlag', 'organizacion'])->toBase();
        };

        $anios = $consulta('anio')->whereNotNull('f_inicio')
            ->selectRaw('YEAR(f_inicio) as valor, COUNT(*) as total')
            ->groupBy('valor')->orderByDesc('valor')->get()
            ->filter(fn ($r) => (int) $r->valor > 0)
            ->map(fn ($r) => ['value' => (string) $r->valor, 'label' => (string) $r->valor, 'count' => (int) $r->total])
            ->values()->all();

        $porPais = $consulta('id_pais')->where('id_pais', '>', 0)
            ->selectRaw('id_pais as valor, COUNT(*) as total')->groupBy('id_pais')->pluck('total', 'valor');
        $paises = $porPais->isEmpty() ? [] : DB::table('pais')->whereIn('ID_Pais', $porPais->keys()->all())
            ->orderBy('No_Pais')->get(['ID_Pais', 'No_Pais'])
            ->map(fn ($p) => ['value' => (string) $p->ID_Pais, 'label' => trim((string) $p->No_Pais), 'count' => (int) $porPais[$p->ID_Pais]])
            ->values()->all();

        $empresas = $consulta('empresa')->whereNotNull('empresa')->where('empresa', '!=', '')->where('empresa', '!=', '1')
            ->selectRaw('MIN(TRIM(empresa)) as valor, COUNT(*) as total')->groupBy(DB::raw('LOWER(TRIM(empresa))'))
            ->orderBy('valor')->get()
            ->map(fn ($r) => ['value' => (string) $r->valor, 'label' => (string) $r->valor, 'count' => (int) $r->total])
            ->values()->all();

        $porEstado = $consulta('estado_china')->selectRaw('estado_china as valor, COUNT(*) as total')
            ->groupBy('estado_china')->pluck('total', 'valor');
        $estados = collect(['PENDIENTE', 'RECIBIENDO', 'COMPLETADO'])
            ->map(fn ($estado) => ['value' => $estado, 'label' => $estado, 'count' => (int) ($porEstado[$estado] ?? 0)])
            ->all();

        return ['anio' => $anios, 'id_pais' => $paises, 'empresa' => $empresas, 'estado_china' => $estados];
    }
}
