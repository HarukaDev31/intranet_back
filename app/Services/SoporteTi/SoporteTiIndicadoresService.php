<?php

namespace App\Services\SoporteTi;

use App\Models\SoporteTi\SoporteTiSlaHoras;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Panel de indicadores de Soporte TI (resumen y detalle operativo).
 * Los números se calculan aquí y se cachean con el epoch de staff.
 */
class SoporteTiIndicadoresService
{
    const TZ = 'America/Lima';

    const NIVELES = array('Baja', 'Media', 'Alta', 'Máxima');

    const ESTADOS_RELOJ = array('en_progreso', 'hecho', 'observado');

    const ESTADOS_A = array(
        'pendiente' => 'Pendiente',
        'en_maqueta' => 'En maqueta',
        'en_progreso' => 'En progreso',
        'desplegado' => 'Desplegado',
        'observado' => 'Observado',
        'operativo' => 'Operativo',
    );

    const ESTADOS_B = array(
        'pendiente' => 'Pendiente',
        'en_progreso' => 'En proceso',
        'hecho' => 'Hecho',
        'desplegado' => 'Desplegado',
        'observado' => 'Observado',
        'operativo' => 'Operativo',
    );

    /** @var SoporteTiCacheService */
    protected $cache;

    /** @var SoporteTiBusinessHoursHelper */
    protected $bh;

    public function __construct(SoporteTiCacheService $cache = null, SoporteTiBusinessHoursHelper $bh = null)
    {
        $this->cache = $cache ?: app(SoporteTiCacheService::class);
        $this->bh = $bh ?: new SoporteTiBusinessHoursHelper();
    }

    /**
     * @param string|null $mes  Y-m
     * @param string|null $area
     * @param Authenticatable|null $user
     * @return array
     */
    public function obtener($mes, $area, ?Authenticatable $user = null)
    {
        if (!$this->esStaff($user)) {
            throw new AuthorizationException('No autorizado para ver los indicadores.');
        }

        $mes = $this->normalizarMes($mes);
        $area = trim((string) $area);
        if ($area === '') {
            $area = '';
        }

        return $this->cache->rememberIndicadores($mes, $area, function () use ($mes, $area) {
            return $this->calcular($mes, $area);
        });
    }

    /**
     * @param string $mes
     * @param string $area
     * @return array
     */
    protected function calcular($mes, $area)
    {
        $solicitudes = SoporteTiSolicitud::with(array(
            'estadoActual',
            'historialEstados.estado',
            'historialEstados.estadoAnterior',
        ))->get();

        $metas = $this->metasSla();
        $mesesSerie = $this->mesesAtras($mes, 6);
        $evolucion = array();
        foreach ($mesesSerie as $punto) {
            $evolucion[] = $this->puntoEvolucion($solicitudes, $punto, $area);
        }

        $corte = $this->corteMes($mes);
        $delMes = $this->creadasEn($solicitudes, $mes, $area);
        $delMesTodas = $this->creadasEn($solicitudes, $mes, '');
        $total = $delMes->count();
        $promedio = $this->promedioPrevio($solicitudes, $mes, $area);

        $resueltas = $this->resueltasEn($solicitudes, $mes, $area, $corte);
        $abiertas = $this->abiertasAlCorte($solicitudes, $area, $corte);

        $sla = $this->cumplimiento($resueltas, null);
        $slaA = $this->cumplimiento($resueltas, 'A');
        $slaB = $this->cumplimiento($resueltas, 'B');
        $vencidas = $this->vencidas($abiertas, null);
        $vencidasA = $this->vencidas($abiertas, 'A');
        $vencidasB = $this->vencidas($abiertas, 'B');
        $cierre = $this->cierre($delMes, null);
        $cierreA = $this->cierre($delMes, 'A');
        $cierreB = $this->cierre($delMes, 'B');

        $variacion = $this->variacionTotal($total, $promedio);

        return array(
            'mes' => $mes,
            'mes_label' => $this->etiquetaMes($mes),
            'area' => $area !== '' ? $area : null,
            'actualizado_en' => Carbon::now(self::TZ)->format('d/m/Y H:i'),
            'meses' => $this->opcionesMes($solicitudes),
            'areas' => $this->nombresAreas($solicitudes),
            'resumen' => array(
                'total' => $total,
                'total_estado' => $variacion['estado'],
                'total_etiqueta' => $variacion['etiqueta'],
                'total_detalle' => $variacion['detalle'],
                'sla_pct' => $sla['pct'],
                'sla_estado' => $sla['estado'],
                'sla_resueltas' => $sla['total'],
                'vencidas' => $vencidas['cantidad'],
                'vencidas_pct' => $vencidas['pct'],
                'vencidas_estado' => $vencidas['estado'],
                'abiertas' => $vencidas['abiertas'],
                'cierre_pct' => $cierre['pct'],
                'cierre_estado' => $cierre['estado'],
                'cierre_operativas' => $cierre['operativas'],
                'cierre_total' => $cierre['total'],
                'alertas' => $this->alertas($variacion, $sla, $vencidas, $cierre),
            ),
            'por_area' => $this->porArea($delMesTodas),
            'comparacion' => array(
                'proyectos' => $this->tarjetaTipo($delMes, $resueltas, $abiertas, 'A'),
                'requerimientos' => $this->tarjetaTipo($delMes, $resueltas, $abiertas, 'B'),
            ),
            'evolucion' => $evolucion,
            'detalle' => array(
                'por_tipo' => $this->porTipo($delMes),
                'tipo_a' => $this->bloqueTipo($delMes, $resueltas, $abiertas, 'A', $metas),
                'tipo_b' => $this->bloqueTipo($delMes, $resueltas, $abiertas, 'B', $metas),
                'atencion' => $this->atencion($abiertas),
                'finalizadas' => $this->finalizadas($resueltas),
                'mensual' => $evolucion,
            ),
        );
    }

    /**
     * @param Collection $solicitudes
     * @param string $mes
     * @param string $area
     * @return array
     */
    protected function puntoEvolucion(Collection $solicitudes, $mes, $area)
    {
        $corte = $this->corteMes($mes);
        $delMes = $this->creadasEn($solicitudes, $mes, $area);
        $resueltas = $this->resueltasEn($solicitudes, $mes, $area, $corte);
        $abiertas = $this->abiertasAlCorte($solicitudes, $area, $corte);
        $sla = $this->cumplimiento($resueltas, null);
        $vencidas = $this->vencidas($abiertas, null);
        $cierre = $this->cierre($delMes, null);

        return array(
            'mes' => $mes,
            'label' => $this->etiquetaMesCorta($mes),
            'total' => $delMes->count(),
            'sla_pct' => $sla['pct'],
            'vencidas' => $vencidas['cantidad'],
            'cierre_pct' => $cierre['pct'],
        );
    }

    /**
     * @param Collection $delMes
     * @param Collection $resueltas
     * @param Collection $abiertas
     * @param string $tipo
     * @param array $metas
     * @return array
     */
    protected function bloqueTipo(Collection $delMes, Collection $resueltas, Collection $abiertas, $tipo, array $metas)
    {
        $sla = $this->cumplimiento($resueltas, $tipo);
        $vencidas = $this->vencidas($abiertas, $tipo);
        $cierre = $this->cierre($delMes, $tipo);
        $estados = $tipo === 'A' ? self::ESTADOS_A : self::ESTADOS_B;
        $cohort = $this->deTipo($delMes, $tipo);
        $counts = array();
        foreach ($estados as $codigo => $nombre) {
            $counts[$codigo] = 0;
        }
        foreach ($cohort as $s) {
            $codigo = $this->codigoActual($s);
            if (isset($counts[$codigo])) {
                $counts[$codigo]++;
            }
        }
        $porEstado = array();
        foreach ($estados as $codigo => $nombre) {
            $porEstado[] = array(
                'codigo' => $codigo,
                'nombre' => $nombre,
                'total' => $counts[$codigo],
            );
        }
        $pendientes = $counts['pendiente'];
        $completadas = $counts['operativo'] + $counts['desplegado'];

        return array(
            'sla_pct' => $sla['pct'],
            'sla_estado' => $sla['estado'],
            'sla_resueltas' => $sla['total'],
            'vencidas' => $vencidas['cantidad'],
            'vencidas_pct' => $vencidas['pct'],
            'vencidas_estado' => $vencidas['estado'],
            'abiertas' => $vencidas['abiertas'],
            'cierre_pct' => $cierre['pct'],
            'cierre_estado' => $cierre['estado'],
            'pendientes' => $pendientes,
            'en_curso' => $cohort->count() - $pendientes - $completadas,
            'operativas' => $completadas,
            'recibidas' => $cohort->count(),
            'niveles' => $this->niveles($this->deTipo($resueltas, $tipo), $tipo, $metas),
            'por_estado' => $porEstado,
        );
    }

    /**
     * @param Collection $resueltas
     * @param string $tipo
     * @param array $metas
     * @return array
     */
    protected function niveles(Collection $resueltas, $tipo, array $metas)
    {
        $out = array();
        foreach (self::NIVELES as $nivel) {
            $grupo = $resueltas->filter(function (SoporteTiSolicitud $s) use ($nivel, $tipo) {
                return $this->nivelDe($s, $tipo) === $nivel;
            });
            $conPlazo = $grupo->filter(function (SoporteTiSolicitud $s) {
                return (int) $s->sla_horas > 0;
            });
            $total = $conPlazo->count();
            $cumplidos = 0;
            $sumaHoras = 0;
            foreach ($conPlazo as $s) {
                $horas = $this->horas($s);
                $sumaHoras += $horas;
                if ($horas <= (int) $s->sla_horas) {
                    $cumplidos++;
                }
            }
            $pct = $total > 0 ? $this->pct($cumplidos, $total) : null;
            $promedio = $total > 0 ? round($sumaHoras / $total, 1) : null;
            $meta = isset($metas[$tipo][$nivel]) ? $metas[$tipo][$nivel] : null;
            $pctTiempo = ($promedio !== null && $meta > 0) ? $this->pct($promedio, $meta) : null;
            $out[] = array(
                'nivel' => $nivel,
                'total' => $grupo->count(),
                'con_plazo' => $total,
                'cumplidos' => $cumplidos,
                'pct' => $pct,
                'estado' => $this->estadoSla($pct),
                'meta_horas' => $meta,
                'promedio_horas' => $promedio,
                'tiempo_pct' => $pctTiempo,
                'tiempo_estado' => $this->estadoTiempo($pctTiempo),
            );
        }

        return $out;
    }

    /**
     * @param Collection $abiertas
     * @return array
     */
    protected function atencion(Collection $abiertas)
    {
        $items = array();
        foreach ($abiertas as $s) {
            $codigo = $this->codigoActual($s);
            if (!in_array($codigo, self::ESTADOS_RELOJ, true)) {
                continue;
            }
            $sla = (int) $s->sla_horas;
            if ($sla <= 0) {
                continue;
            }
            $horas = $this->horas($s);
            $pct = $this->pct($horas, $sla);
            if ($pct < 75) {
                continue;
            }
            $tipo = $s->tipo_solicitud === 'A' ? 'A' : 'B';
            $atraso = $horas - $sla;
            $items[] = array(
                'id' => (int) $s->id,
                'codigo' => $s->codigo,
                'titulo' => (string) $s->titulo,
                'area' => (string) $s->area,
                'tipo' => $tipo,
                'estado' => $this->nombreEstado($s, $tipo),
                'prioridad' => $this->etiquetaPrioridad($s->prioridad),
                'complejidad' => $this->nivelDe($s, $tipo) ?: 'Sin nivel',
                'pct' => $pct,
                'zona' => $pct > 100 ? 'red' : 'amber',
                'horas' => round($horas, 1),
                'sla_horas' => $sla,
                'atraso' => $this->textoAtraso($atraso),
            );
        }

        usort($items, function ($a, $b) {
            if ($a['tipo'] !== $b['tipo']) {
                return $a['tipo'] === 'A' ? -1 : 1;
            }
            if ($a['pct'] == $b['pct']) {
                return 0;
            }

            return $a['pct'] < $b['pct'] ? 1 : -1;
        });

        return $items;
    }

    /**
     * @param Collection $delMes
     * @return array
     */
    protected function porTipo(Collection $delMes)
    {
        $mapa = array(
            'Proyectos' => array('tipo' => 'A', 'total' => 0),
            'Incidencias' => array('tipo' => 'B', 'total' => 0),
            'Configuración' => array('tipo' => 'B', 'total' => 0),
        );
        foreach ($delMes as $s) {
            $nombre = $this->categoriaDe($s);
            if (!isset($mapa[$nombre])) {
                $mapa[$nombre] = array('tipo' => $s->tipo_solicitud === 'A' ? 'A' : 'B', 'total' => 0);
            }
            $mapa[$nombre]['total']++;
        }
        $out = array();
        foreach ($mapa as $nombre => $row) {
            $out[] = array(
                'nombre' => $nombre,
                'tipo' => $row['tipo'],
                'total' => $row['total'],
            );
        }

        return $out;
    }

    /**
     * @param Collection $delMesTodas
     * @return array
     */
    protected function porArea(Collection $delMesTodas)
    {
        $mapa = array();
        foreach ($delMesTodas as $s) {
            $nombre = trim((string) $s->area);
            if ($nombre === '') {
                $nombre = 'Sin área';
            }
            if (!isset($mapa[$nombre])) {
                $mapa[$nombre] = array('nombre' => $nombre, 'total' => 0, 'tipo_a' => 0, 'tipo_b' => 0);
            }
            $mapa[$nombre]['total']++;
            if ($s->tipo_solicitud === 'A') {
                $mapa[$nombre]['tipo_a']++;
            } else {
                $mapa[$nombre]['tipo_b']++;
            }
        }
        $out = array_values($mapa);
        usort($out, function ($a, $b) {
            if ($a['total'] == $b['total']) {
                return 0;
            }

            return $a['total'] < $b['total'] ? 1 : -1;
        });

        return $out;
    }

    /**
     * @param Collection $delMes
     * @param Collection $resueltas
     * @param Collection $abiertas
     * @param string $tipo
     * @return array
     */
    protected function tarjetaTipo(Collection $delMes, Collection $resueltas, Collection $abiertas, $tipo)
    {
        $sla = $this->cumplimiento($resueltas, $tipo);
        $vencidas = $this->vencidas($abiertas, $tipo);
        $cierre = $this->cierre($delMes, $tipo);

        return array(
            'total' => $this->deTipo($delMes, $tipo)->count(),
            'sla_pct' => $sla['pct'],
            'sla_estado' => $sla['estado'],
            'vencidas' => $vencidas['cantidad'],
            'vencidas_estado' => $vencidas['estado'],
            'cierre_pct' => $cierre['pct'],
            'cierre_estado' => $cierre['estado'],
        );
    }

    /**
     * @param Collection $resueltas
     * @param string|null $tipo
     * @return array
     */
    protected function cumplimiento(Collection $resueltas, $tipo)
    {
        $lista = $this->deTipo($resueltas, $tipo)->filter(function (SoporteTiSolicitud $s) {
            return (int) $s->sla_horas > 0;
        });
        $cumplidos = 0;
        foreach ($lista as $s) {
            if ($this->horas($s) <= (int) $s->sla_horas) {
                $cumplidos++;
            }
        }
        $total = $lista->count();
        $pct = $total > 0 ? $this->pct($cumplidos, $total) : null;

        return array(
            'pct' => $pct,
            'estado' => $this->estadoSla($pct),
            'total' => $total,
            'cumplidos' => $cumplidos,
        );
    }

    /**
     * @param Collection $abiertas
     * @param string|null $tipo
     * @return array
     */
    protected function vencidas(Collection $abiertas, $tipo)
    {
        $lista = $this->deTipo($abiertas, $tipo);
        $cantidad = 0;
        foreach ($lista as $s) {
            $sla = (int) $s->sla_horas;
            if ($sla > 0 && $this->horas($s) > $sla) {
                $cantidad++;
            }
        }
        $abiertasN = $lista->count();
        $pct = $abiertasN > 0 ? $this->pct($cantidad, $abiertasN) : 0;

        return array(
            'cantidad' => $cantidad,
            'pct' => $pct,
            'estado' => $this->estadoVencidas($pct),
            'abiertas' => $abiertasN,
        );
    }

    /**
     * @param Collection $delMes
     * @param string|null $tipo
     * @return array
     */
    protected function cierre(Collection $delMes, $tipo)
    {
        $lista = $this->deTipo($delMes, $tipo);
        $operativas = 0;
        foreach ($lista as $s) {
            if ($this->estaCompletada($this->codigoActual($s))) {
                $operativas++;
            }
        }
        $total = $lista->count();
        $pct = $total > 0 ? $this->pct($operativas, $total) : null;

        return array(
            'pct' => $pct,
            'estado' => $this->estadoCierre($pct),
            'operativas' => $operativas,
            'total' => $total,
        );
    }

    /**
     * @param array $variacion
     * @param array $sla
     * @param array $vencidas
     * @param array $cierre
     * @return array
     */
    protected function alertas(array $variacion, array $sla, array $vencidas, array $cierre)
    {
        $items = array();
        if ($variacion['estado'] === 'amber') {
            $items[] = array(
                'nivel' => 'amber',
                'texto' => 'El volumen de solicitudes se aleja más de 20% del promedio de los meses anteriores.',
            );
        }
        if ($sla['estado'] === 'red' || $sla['estado'] === 'amber') {
            $items[] = array(
                'nivel' => $sla['estado'],
                'texto' => 'El cumplimiento de SLA del mes está en ' . $this->textoPct($sla['pct']) . '. La meta es 90% o más.',
            );
        }
        if ($vencidas['estado'] === 'red' || $vencidas['estado'] === 'amber') {
            $items[] = array(
                'nivel' => $vencidas['estado'],
                'texto' => $vencidas['cantidad'] . ' solicitudes abiertas ya superaron su plazo.',
            );
        }
        if ($cierre['estado'] === 'red' || $cierre['estado'] === 'amber') {
            $items[] = array(
                'nivel' => $cierre['estado'],
                'texto' => 'La tasa de cierre del mes está en ' . $this->textoPct($cierre['pct']) . '. La meta es 90% o más.',
            );
        }
        if (count($items) === 0) {
            $items[] = array(
                'nivel' => 'green',
                'texto' => 'Ningún indicador del mes está en atención o crítico.',
            );
        }

        return $items;
    }

    /**
     * @param Collection $solicitudes
     * @param string $mes
     * @param string $area
     * @return Collection
     */
    protected function creadasEn(Collection $solicitudes, $mes, $area)
    {
        return $solicitudes->filter(function (SoporteTiSolicitud $s) use ($mes, $area) {
            if ($area !== '' && trim((string) $s->area) !== $area) {
                return false;
            }
            $creada = Carbon::parse($s->created_at)->timezone(self::TZ);

            return $creada->format('Y-m') === $mes;
        })->values();
    }

    /**
     * Resueltas = pasaron a Desplegado u Operativo en el mes y siguen en uno de esos estados.
     *
     * @param Collection $solicitudes
     * @param string $mes
     * @param string $area
     * @param Carbon $corte
     * @return Collection
     */
    protected function resueltasEn(Collection $solicitudes, $mes, $area, Carbon $corte)
    {
        return $solicitudes->filter(function (SoporteTiSolicitud $s) use ($mes, $area, $corte) {
            if ($area !== '' && trim((string) $s->area) !== $area) {
                return false;
            }
            if (!$this->estaCompletada($this->codigoActual($s))) {
                return false;
            }
            $fecha = $this->fechaCompletada($s);
            if (!$fecha || $fecha->gt($corte)) {
                return false;
            }

            return $fecha->timezone(self::TZ)->format('Y-m') === $mes;
        })->values();
    }

    /**
     * Abiertas al corte: ya existían y no estaban Desplegado ni Operativo.
     *
     * @param Collection $solicitudes
     * @param string $area
     * @param Carbon $corte
     * @return Collection
     */
    protected function abiertasAlCorte(Collection $solicitudes, $area, Carbon $corte)
    {
        return $solicitudes->filter(function (SoporteTiSolicitud $s) use ($area, $corte) {
            if ($area !== '' && trim((string) $s->area) !== $area) {
                return false;
            }
            $creada = Carbon::parse($s->created_at);
            if ($creada->gt($corte)) {
                return false;
            }
            $codigo = $this->codigoAlCorte($s, $corte);

            return !$this->estaCompletada($codigo);
        })->values();
    }

    /**
     * @param SoporteTiSolicitud $s
     * @param Carbon $corte
     * @return string
     */
    protected function codigoAlCorte(SoporteTiSolicitud $s, Carbon $corte)
    {
        $codigo = null;
        $eventos = $s->historialEstados->sortBy(function ($h) {
            return $h->created_at ? $h->created_at->timestamp : 0;
        });
        foreach ($eventos as $h) {
            if (!$h->created_at || $h->created_at->gt($corte)) {
                break;
            }
            if ($h->estado) {
                $codigo = $h->estado->codigo;
            }
        }
        if ($codigo !== null) {
            return $codigo;
        }
        $primero = $eventos->first();
        if ($primero && $primero->estadoAnterior) {
            return $primero->estadoAnterior->codigo;
        }

        return 'pendiente';
    }

    /**
     * Solicitudes finalizadas en el mes. El plazo es el guardado en la solicitud
     * (sla_horas), no el catálogo vigente: un cambio de 3 h a 2 h no reescribe las anteriores.
     *
     * @param Collection $resueltas
     * @return array
     */
    protected function finalizadas(Collection $resueltas)
    {
        $items = array();
        foreach ($resueltas as $s) {
            $fecha = $this->fechaCompletada($s);
            if (!$fecha) {
                continue;
            }
            $tipo = $s->tipo_solicitud === 'A' ? 'A' : 'B';
            $horas = round($this->horas($s), 1);
            $sla = (int) $s->sla_horas;
            $items[] = array(
                'id' => (int) $s->id,
                'codigo' => $s->codigo,
                'titulo' => (string) $s->titulo,
                'area' => (string) $s->area,
                'tipo' => $tipo,
                'estado' => $this->nombreEstado($s, $tipo),
                'complejidad' => $this->nivelDe($s, $tipo) ?: 'Sin nivel',
                'finalizado_en' => $fecha->timezone(self::TZ)->format('d/m/Y H:i'),
                'finalizado_ts' => $fecha->timezone(self::TZ)->getTimestamp(),
                'horas' => $horas,
                'sla_horas' => $sla,
                'a_tiempo' => $sla > 0 && $horas <= $sla,
            );
        }

        usort($items, function ($a, $b) {
            return $b['finalizado_ts'] - $a['finalizado_ts'];
        });

        $out = array();
        foreach ($items as $item) {
            unset($item['finalizado_ts']);
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Primera vez que llegó a Desplegado u Operativo.
     *
     * @param SoporteTiSolicitud $s
     * @return Carbon|null
     */
    protected function fechaCompletada(SoporteTiSolicitud $s)
    {
        $fecha = null;
        foreach ($s->historialEstados as $h) {
            if (!$h->estado || !$this->estaCompletada($h->estado->codigo) || !$h->created_at) {
                continue;
            }
            if ($fecha === null || $h->created_at->lt($fecha)) {
                $fecha = $h->created_at->copy();
            }
        }
        if ($fecha === null && $this->estaCompletada($this->codigoActual($s))) {
            $marca = $s->ultima_actualizacion ?: $s->updated_at;
            if ($marca) {
                return Carbon::parse($marca);
            }
        }

        return $fecha;
    }

    /**
     * @param string $codigo
     * @return bool
     */
    protected function estaCompletada($codigo)
    {
        return $codigo === 'operativo' || $codigo === 'desplegado';
    }

    /**
     * Misma cuenta que el reloj de la solicitud: segundos acumulados más el tramo abierto.
     *
     * @param SoporteTiSolicitud $s
     * @return float
     */
    protected function horas(SoporteTiSolicitud $s)
    {
        $seg = (int) $s->sla_segundos_acumulados;
        $codigo = $this->codigoActual($s);
        if (in_array($codigo, self::ESTADOS_RELOJ, true) && $s->sla_reanudado_en) {
            $seg += $this->bh->segundosHabilesEntre(Carbon::parse($s->sla_reanudado_en), Carbon::now());
        }

        return $seg / 3600;
    }

    /**
     * @param SoporteTiSolicitud $s
     * @return string
     */
    protected function codigoActual(SoporteTiSolicitud $s)
    {
        return $s->estadoActual ? (string) $s->estadoActual->codigo : '';
    }

    /**
     * @param SoporteTiSolicitud $s
     * @param string $tipo
     * @return string
     */
    protected function nombreEstado(SoporteTiSolicitud $s, $tipo)
    {
        $codigo = $this->codigoActual($s);
        $mapa = $tipo === 'A' ? self::ESTADOS_A : self::ESTADOS_B;
        if (isset($mapa[$codigo])) {
            return $mapa[$codigo];
        }

        return $s->estadoActual ? (string) $s->estadoActual->nombre : $codigo;
    }

    /**
     * @param SoporteTiSolicitud $s
     * @param string $tipo
     * @return string|null
     */
    protected function nivelDe(SoporteTiSolicitud $s, $tipo)
    {
        $candidatos = $tipo === 'A'
            ? array($s->complejidad_pm, $s->complejidad_analista, $s->criticidad)
            : array($s->criticidad);
        foreach ($candidatos as $c) {
            $c = trim((string) $c);
            if (in_array($c, self::NIVELES, true)) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @param SoporteTiSolicitud $s
     * @return string
     */
    protected function categoriaDe(SoporteTiSolicitud $s)
    {
        if ($s->tipo_solicitud === 'A') {
            return 'Proyectos';
        }
        $sub = strtoupper(trim((string) $s->subtipo_b));
        if ($sub === 'B2' || $sub === 'CFG') {
            return 'Configuración';
        }

        return 'Incidencias';
    }

    /**
     * @param Collection $lista
     * @param string|null $tipo
     * @return Collection
     */
    protected function deTipo(Collection $lista, $tipo)
    {
        if ($tipo !== 'A' && $tipo !== 'B') {
            return $lista;
        }

        return $lista->filter(function (SoporteTiSolicitud $s) use ($tipo) {
            return $s->tipo_solicitud === $tipo;
        })->values();
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected function metasSla()
    {
        $metas = array('A' => array(), 'B' => array());
        $rows = SoporteTiSlaHoras::query()->get();
        foreach ($rows as $row) {
            $tipo = $row->tipo_solicitud === 'A' ? 'A' : 'B';
            $nivel = trim((string) $row->criticidad);
            if (!in_array($nivel, self::NIVELES, true)) {
                continue;
            }
            if ($tipo === 'A' && $row->ambito && $row->ambito !== 'analista_config') {
                continue;
            }
            $metas[$tipo][$nivel] = (int) $row->horas;
        }
        $defectoA = array('Baja' => 12, 'Media' => 24, 'Alta' => 36, 'Máxima' => 48);
        $defectoB = array('Baja' => 4, 'Media' => 8, 'Alta' => 16, 'Máxima' => 24);
        foreach (self::NIVELES as $nivel) {
            if (!isset($metas['A'][$nivel])) {
                $metas['A'][$nivel] = $defectoA[$nivel];
            }
            if (!isset($metas['B'][$nivel])) {
                $metas['B'][$nivel] = $defectoB[$nivel];
            }
        }

        return $metas;
    }

    /**
     * @param Collection $solicitudes
     * @param string $mes
     * @param string $area
     * @return float|null
     */
    protected function promedioPrevio(Collection $solicitudes, $mes, $area)
    {
        $sumas = array();
        for ($i = 1; $i <= 3; $i++) {
            $prev = $this->mesAnterior($mes, $i);
            $n = $this->creadasEn($solicitudes, $prev, $area)->count();
            if ($n > 0) {
                $sumas[] = $n;
            }
        }
        if (count($sumas) === 0) {
            return null;
        }

        return array_sum($sumas) / count($sumas);
    }

    /**
     * @param int $total
     * @param float|null $promedio
     * @return array
     */
    protected function variacionTotal($total, $promedio)
    {
        if ($promedio === null) {
            return array(
                'estado' => 'muted',
                'etiqueta' => 'Sin datos',
                'detalle' => 'Sin meses previos para comparar',
            );
        }
        $diff = $promedio > 0 ? (($total - $promedio) / $promedio) * 100 : 0;
        $estado = abs($diff) > 20 ? 'amber' : 'green';

        return array(
            'estado' => $estado,
            'etiqueta' => $estado === 'amber' ? 'Variación alta' : 'Estable',
            'detalle' => 'Promedio meses previos: ' . (int) round($promedio),
        );
    }

    /**
     * @param Collection $solicitudes
     * @return array
     */
    protected function opcionesMes(Collection $solicitudes)
    {
        $vistos = array(Carbon::now(self::TZ)->format('Y-m') => true);
        foreach ($solicitudes as $s) {
            $vistos[Carbon::parse($s->created_at)->timezone(self::TZ)->format('Y-m')] = true;
            $fz = $this->fechaCompletada($s);
            if ($fz) {
                $vistos[$fz->timezone(self::TZ)->format('Y-m')] = true;
            }
        }
        $meses = array_keys($vistos);
        rsort($meses);
        $out = array();
        foreach ($meses as $mes) {
            $out[] = array('value' => $mes, 'label' => $this->etiquetaMes($mes));
        }

        return $out;
    }

    /**
     * @param Collection $solicitudes
     * @return array
     */
    protected function nombresAreas(Collection $solicitudes)
    {
        $nombres = array();
        foreach ($solicitudes as $s) {
            $nombre = trim((string) $s->area);
            if ($nombre !== '') {
                $nombres[$nombre] = true;
            }
        }
        $out = array_keys($nombres);
        sort($out);

        return $out;
    }

    /**
     * @param string|null $mes
     * @return string
     */
    protected function normalizarMes($mes)
    {
        $mes = trim((string) $mes);
        if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return $mes;
        }

        return Carbon::now(self::TZ)->format('Y-m');
    }

    /**
     * @param string $mes
     * @return Carbon
     */
    protected function corteMes($mes)
    {
        $fin = Carbon::createFromFormat('Y-m-d', $mes . '-01', self::TZ)->endOfMonth();
        $ahora = Carbon::now(self::TZ);
        if ($fin->gt($ahora)) {
            return $ahora;
        }

        return $fin;
    }

    /**
     * @param string $mes
     * @param int $n
     * @return array
     */
    protected function mesesAtras($mes, $n)
    {
        $out = array();
        for ($i = $n - 1; $i >= 0; $i--) {
            $out[] = $this->mesAnterior($mes, $i);
        }

        return $out;
    }

    /**
     * @param string $mes
     * @param int $back
     * @return string
     */
    protected function mesAnterior($mes, $back)
    {
        return Carbon::createFromFormat('Y-m-d', $mes . '-01', self::TZ)
            ->startOfMonth()
            ->subMonths($back)
            ->format('Y-m');
    }

    /**
     * @param string $mes
     * @return string
     */
    protected function etiquetaMes($mes)
    {
        $nombres = array('enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
        $partes = explode('-', $mes);
        $indice = (int) $partes[1] - 1;
        $nombre = isset($nombres[$indice]) ? $nombres[$indice] : $mes;

        return ucfirst($nombre) . ' ' . $partes[0];
    }

    /**
     * @param string $mes
     * @return string
     */
    protected function etiquetaMesCorta($mes)
    {
        $nombres = array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');
        $partes = explode('-', $mes);
        $indice = (int) $partes[1] - 1;
        $nombre = isset($nombres[$indice]) ? $nombres[$indice] : $mes;

        return $nombre . ' ' . substr($partes[0], 2);
    }

    /**
     * @param mixed $prioridad
     * @return string
     */
    protected function etiquetaPrioridad($prioridad)
    {
        $n = (int) $prioridad;
        if ($n === 1) {
            return 'Alta';
        }
        if ($n === 3) {
            return 'Baja';
        }

        return 'Media';
    }

    /**
     * @param float $atraso
     * @return string
     */
    protected function textoAtraso($atraso)
    {
        if ($atraso <= 0) {
            return '';
        }
        if ($atraso >= 9.5) {
            return '+' . (int) round($atraso / 9.5) . ' d';
        }

        return '+' . max(1, (int) round($atraso)) . ' h';
    }

    /**
     * @param float|null $pct
     * @return string
     */
    protected function textoPct($pct)
    {
        if ($pct === null) {
            return 'sin datos';
        }

        return $pct . '%';
    }

    /**
     * @param float $num
     * @param float $den
     * @return float
     */
    protected function pct($num, $den)
    {
        if ($den <= 0) {
            return 0;
        }

        return round(($num / $den) * 1000) / 10;
    }

    /**
     * @param float|null $pct
     * @return string
     */
    protected function estadoSla($pct)
    {
        if ($pct === null) {
            return 'muted';
        }
        if ($pct >= 90) {
            return 'green';
        }
        if ($pct >= 80) {
            return 'amber';
        }

        return 'red';
    }

    /**
     * @param float $pct
     * @return string
     */
    protected function estadoVencidas($pct)
    {
        if ($pct <= 5) {
            return 'green';
        }
        if ($pct <= 15) {
            return 'amber';
        }

        return 'red';
    }

    /**
     * @param float|null $pct
     * @return string
     */
    protected function estadoCierre($pct)
    {
        if ($pct === null) {
            return 'muted';
        }
        if ($pct >= 90) {
            return 'green';
        }
        if ($pct >= 70) {
            return 'amber';
        }

        return 'red';
    }

    /**
     * @param float|null $pct
     * @return string
     */
    protected function estadoTiempo($pct)
    {
        if ($pct === null) {
            return 'muted';
        }
        if ($pct <= 100) {
            return 'green';
        }
        if ($pct <= 130) {
            return 'amber';
        }

        return 'red';
    }

    /**
     * @param Authenticatable|null $user
     * @return bool
     */
    protected function esStaff(?Authenticatable $user)
    {
        if (!$user instanceof Usuario) {
            return false;
        }
        $user->loadMissing('grupo');
        $nombre = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';

        return in_array($nombre, array(
            strtolower(Usuario::ROL_PM),
            strtolower(Usuario::ROL_SOPORTE),
            strtolower(Usuario::ROL_COORDINADOR_GENERAL),
        ), true);
    }
}
