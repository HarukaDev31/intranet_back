<?php

namespace App\Services\SoporteTi;

use App\Models\SoporteTi\SoporteTiSlaHoras;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SLA tipo A: horas de fases PM por complejidad del PM + configuración por complejidad del analista.
 */
class SoporteTiTipoASlaHelper
{
  const FASES_PM = array('levantamiento', 'maqueta', 'pruebas', 'capacitacion');

  const AMBITO_PM_FASES = 'pm_fases';

  const AMBITO_ANALISTA_CONFIG = 'analista_config';

  /**
   * @param string|null $criticidad
   * @return bool
   */
  public function complejidadValida($criticidad)
  {
    return in_array(trim((string) $criticidad), array('Baja', 'Media', 'Alta', 'Máxima'), true);
  }

  /**
   * Horas de fases PM (sin configuración) según la complejidad que asigna el PM.
   *
   * @param string $criticidad
   * @return int
   */
  public function horasFasesPmPorComplejidad($criticidad)
  {
    $c = trim((string) $criticidad);
    if (!\Schema::hasTable('soporte_ti_fase_horas_a')) {
      return $this->horasFasesPmPorComplejidadFallback($c);
    }
    $sum = (int) DB::table('soporte_ti_fase_horas_a')
      ->whereIn('fase_codigo', self::FASES_PM)
      ->where('criticidad', $c)
      ->sum('horas');
    if ($sum > 0) {
      return $sum;
    }
    return $this->horasFasesPmPorComplejidadFallback($c);
  }

  /**
   * @param string $criticidad
   * @return int
   */
  protected function horasFasesPmPorComplejidadFallback($criticidad)
  {
    $map = array('Baja' => 48, 'Media' => 64, 'Alta' => 80, 'Máxima' => 96);
    if (!isset($map[$criticidad])) {
      throw new \InvalidArgumentException('Complejidad PM no válida.');
    }
    return (int) $map[$criticidad];
  }

  /**
   * @return array{fases: array, complejidades: array, celdas: array}
   */
  public function listarFaseHorasMatriz()
  {
    $labels = $this->labelsFasesPm();
    $fases = array();
    foreach (self::FASES_PM as $codigo) {
      $fases[] = array(
        'codigo' => $codigo,
        'nombre' => isset($labels[$codigo]) ? $labels[$codigo] : $codigo,
      );
    }
    $complejidades = array('Baja', 'Media', 'Alta', 'Máxima');

    return array(
      'fases' => $fases,
      'complejidades' => $complejidades,
      'celdas' => $this->listarFaseHorasCeldas(),
    );
  }

  /**
   * @return array
   */
  public function listarFaseHorasCeldas()
  {
    if (!\Schema::hasTable('soporte_ti_fase_horas_a')) {
      return array();
    }
    $labels = $this->labelsFasesPm();
    $query = DB::table('soporte_ti_fase_horas_a')->whereIn('fase_codigo', self::FASES_PM);
    if (\Schema::hasColumn('soporte_ti_fase_horas_a', 'criticidad')) {
      $query->orderByRaw("FIELD(criticidad, 'Baja','Media','Alta','Máxima')")
        ->orderByRaw("FIELD(fase_codigo, 'levantamiento','maqueta','pruebas','capacitacion')");
    } else {
      $query->orderByRaw("FIELD(fase_codigo, 'levantamiento','maqueta','pruebas','capacitacion')");
    }
    $rows = $query->get();
    $out = array();
    foreach ($rows as $row) {
      $codigo = $row->fase_codigo;
      $crit = isset($row->criticidad) ? trim((string) $row->criticidad) : 'Media';
      $out[] = array(
        'id' => (int) $row->id,
        'fase_codigo' => $codigo,
        'fase_nombre' => isset($labels[$codigo]) ? $labels[$codigo] : $codigo,
        'criticidad' => $crit,
        'horas' => (int) $row->horas,
        'updated_at' => isset($row->updated_at) ? $row->updated_at : null,
      );
    }
    return $out;
  }

  /**
   * @return array
   */
  protected function labelsFasesPm()
  {
    return array(
      'levantamiento' => 'Levantamiento',
      'maqueta' => 'Maqueta',
      'pruebas' => 'Pruebas',
      'capacitacion' => 'Capacitación',
    );
  }

  /**
   * @param array $items [{ id, horas }]
   * @return array
   */
  public function actualizarFaseHoras(array $items)
  {
    foreach ($items as $item) {
      if (!is_array($item)) {
        throw new \InvalidArgumentException('Formato de fila inválido.');
      }
      $id = isset($item['id']) ? (int) $item['id'] : 0;
      $horas = isset($item['horas']) ? (int) $item['horas'] : -1;
      if ($id <= 0 || $horas < 1 || $horas > 9999) {
        throw new \InvalidArgumentException('Cada fase debe tener id y horas entre 1 y 9999.');
      }
      $updated = DB::table('soporte_ti_fase_horas_a')
        ->where('id', $id)
        ->update(array('horas' => $horas, 'updated_at' => now()));
      if (!$updated) {
        throw new \InvalidArgumentException('Fase no encontrada.');
      }
    }
    return $this->listarFaseHorasMatriz();
  }

  /**
   * @param string $criticidad
   * @return int
   */
  public function horasConfigAnalista($criticidad)
  {
    $c = trim((string) $criticidad);
    $row = SoporteTiSlaHoras::where('tipo_solicitud', 'A')
      ->where('criticidad', $c)
      ->where(function ($q) {
        $q->where('ambito', self::AMBITO_ANALISTA_CONFIG)->orWhereNull('ambito');
      })
      ->first();
    if ($row) {
      return (int) $row->horas;
    }
    $map = array('Baja' => 12, 'Media' => 24, 'Alta' => 36, 'Máxima' => 48);
    if (!isset($map[$c])) {
      throw new \InvalidArgumentException('Complejidad no válida.');
    }
    return (int) $map[$c];
  }

  /**
   * @return array{min: int, max: int}
   */
  public function rangoHorasConfigAnalista()
  {
    $rows = SoporteTiSlaHoras::where('tipo_solicitud', 'A')
      ->where(function ($q) {
        $q->where('ambito', self::AMBITO_ANALISTA_CONFIG)->orWhereNull('ambito');
      })
      ->pluck('horas');
    if ($rows->isEmpty()) {
      return array('min' => 12, 'max' => 48);
    }
    return array(
      'min' => (int) $rows->min(),
      'max' => (int) $rows->max(),
    );
  }

  /**
   * @param SoporteTiSolicitud $s
   * @return array{horas: int|null, etiqueta: string|null, es_rango: bool}
   */
  public function resolverSla(SoporteTiSolicitud $s)
  {
    $pmOk = $this->complejidadValida($s->complejidad_pm);
    $anOk = $this->complejidadValida($s->complejidad_analista);

    if (!$pmOk) {
      return array('horas' => null, 'etiqueta' => null, 'es_rango' => false);
    }

    $pmHoras = $this->horasFasesPmPorComplejidad($s->complejidad_pm);

    if ($anOk) {
      $config = $this->horasConfigAnalista($s->complejidad_analista);
      $total = $pmHoras + $config;
      return array(
        'horas' => $total,
        'etiqueta' => $total . ' h',
        'es_rango' => false,
      );
    }

    $rango = $this->rangoHorasConfigAnalista();
    $min = $pmHoras + $rango['min'];
    $max = $pmHoras + $rango['max'];
    return array(
      'horas' => (int) round(($min + $max) / 2),
      'etiqueta' => $min . '–' . $max . ' h',
      'es_rango' => true,
    );
  }

  /**
   * @param SoporteTiSolicitud $s
   * @param Carbon|null $base
   * @return string
   */
  public function terminoEstimadoTexto(SoporteTiSolicitud $s, Carbon $base = null)
  {
    $sla = $this->resolverSla($s);
    if (!$sla['etiqueta']) {
      return 'Por definir';
    }
    if ($sla['es_rango']) {
      return $sla['etiqueta'];
    }
    $base = $base ?: Carbon::now();
    $fin = $base->copy()->addHours((int) $sla['horas']);
    $meses = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
    return $fin->format('d') . ' ' . $meses[(int) $fin->format('n') - 1] . ' ' . $fin->format('H:i');
  }

  /**
   * @param SoporteTiSolicitud $s
   * @return void
   */
  public function aplicarSlaEnSolicitud(SoporteTiSolicitud $s)
  {
    $sla = $this->resolverSla($s);
    if ($sla['horas'] !== null) {
      $s->sla_horas = (int) $sla['horas'];
    } else {
      $s->sla_horas = 0;
    }
  }

  /**
   * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
   * @return bool
   */
  public function usuarioEsPm($user)
  {
    if (!$user instanceof Usuario) {
      return false;
    }
    $user->loadMissing('grupo');
    $nombre = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
    return $nombre === strtolower(Usuario::ROL_PM);
  }

  /**
   * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
   * @return bool
   */
  public function usuarioEsAnalista($user)
  {
    if (!$user instanceof Usuario) {
      return false;
    }
    $user->loadMissing('grupo');
    $nombre = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
    return $nombre === strtolower(Usuario::ROL_SOPORTE);
  }
}
