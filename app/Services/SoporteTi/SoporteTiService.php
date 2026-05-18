<?php

namespace App\Services\SoporteTi;

use App\Events\SoporteTi\SoporteTiEstadoActualizado;
use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Jobs\SoporteTi\ProcessSoporteTiChatAdjuntosJob;
use App\Jobs\SoporteTi\ProcessSoporteTiMaquetaUploadJob;
use App\Models\SoporteTi\SoporteTiChatMiembro;
use App\Models\SoporteTi\SoporteTiChatSala;
use App\Models\SoporteTi\SoporteTiMensajeLectura;
use App\Jobs\SoporteTi\ProcessSoporteTiMarcarLeidosJob;
use App\Models\SoporteTi\SoporteTiEstado;
use App\Models\SoporteTi\SoporteTiMaqueta;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeImagen;
use App\Models\SoporteTi\SoporteTiSlaHoras;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\SoporteTi\SoporteTiSolicitudEstado;
use App\Models\SoporteTi\SoporteTiSolicitudEvidencia;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SoporteTiService
{
    const CHAT_PAGE_SIZE = 25;

    /** Zona horaria de visualización (Perú). */
    const TZ_PERU = 'America/Lima';

    /** Estados en los que el tiempo SLA del solicitante sigue corriendo. */
    const ESTADOS_SLA_CORRE = array('en_progreso', 'hecho');

    /** Estados en los que se muestra el contador (corriendo o pausado). */
    const ESTADOS_SLA_CONTADOR_VISIBLE = array('en_progreso', 'hecho', 'desplegado', 'observado');

    /** @var SoporteTiCacheService */
    protected $cache;

    public function __construct(SoporteTiCacheService $cache = null)
    {
        $this->cache = $cache ?: app(SoporteTiCacheService::class);
    }

    /**
     * @return SoporteTiCacheService
     */
    public function cacheService()
    {
        return $this->cache;
    }

    /**
     * @return SoporteTiTipoASlaHelper
     */
    protected function tipoASla()
    {
        return new SoporteTiTipoASlaHelper();
    }

    /**
     * ID del usuario intranet (tabla `usuario`, PK `ID_Usuario`) o del modelo User si aplica.
     *
     * @return int|null
     */
    protected function authUserId(?Authenticatable $user = null)
    {
        return $user ? (int) $user->getKey() : null;
    }

    /**
     * PM y Soporte ven todas las solicitudes; el resto solo las propias (solicitante_user_id).
     *
     * @return bool
     */
    protected function usuarioEsStaffSoporteTi(?Authenticatable $user)
    {
        if (!$user instanceof Usuario) {
            return false;
        }
        $user->loadMissing('grupo');
        $nombre = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
        return $nombre === strtolower(Usuario::ROL_PM) || $nombre === strtolower(Usuario::ROL_SOPORTE);
    }

    /**
     * @param int|string $id
     * @param array $with relaciones Eloquent para eager load
     * @return SoporteTiSolicitud
     */
    protected function asegurarAccesoSolicitud($id, ?Authenticatable $authUser, array $with = array())
    {
        $authUser = $authUser ?: Auth::user();
        $s = SoporteTiSolicitud::with($with)->findOrFail($id);
        if (!$authUser) {
            throw new AuthorizationException('No autenticado');
        }
        if (!$this->usuarioEsStaffSoporteTi($authUser)) {
            $uid = (int) $authUser->getKey();
            if ($s->solicitante_user_id === null || (int) $s->solicitante_user_id !== $uid) {
                throw new AuthorizationException('No autorizado para acceder a esta solicitud');
            }
        }
        return $s;
    }

    /**
     * @param SoporteTiSolicitud $s
     * @return void
     */
    protected function asegurarAccesoSolicitudModel(SoporteTiSolicitud $s, ?Authenticatable $authUser)
    {
        $authUser = $authUser ?: Auth::user();
        if (!$authUser) {
            throw new AuthorizationException('No autenticado');
        }
        if ($this->usuarioEsStaffSoporteTi($authUser)) {
            return;
        }
        $uid = (int) $authUser->getKey();
        if ($s->solicitante_user_id === null || (int) $s->solicitante_user_id !== $uid) {
            throw new AuthorizationException('No autorizado para acceder a esta solicitud');
        }
    }

    /**
     * Contadores para cards del listado (alineado al front: pendiente; en_progreso + en_maqueta + hecho; operativo).
     *
     * @param \Illuminate\Support\Collection $solicitudes Colección de SoporteTiSolicitud con estadoActual cargado
     * @return array
     */
    protected function resumenListadoSolicitudes($solicitudes)
    {
        $pendientes = 0;
        $enProgreso = 0;
        $operativas = 0;
        $enProgresoCodigos = array('en_progreso', 'en_maqueta', 'hecho');
        foreach ($solicitudes as $s) {
            /** @var SoporteTiSolicitud $s */
            $cod = $s->estadoActual ? $s->estadoActual->codigo : null;
            if ($cod === 'pendiente') {
                ++$pendientes;
            }
            if ($cod !== null && in_array($cod, $enProgresoCodigos, true)) {
                ++$enProgreso;
            }
            if ($cod === 'operativo') {
                ++$operativas;
            }
        }

        return array(
            'total' => (int) $solicitudes->count(),
            'pendientes' => $pendientes,
            'en_progreso' => $enProgreso,
            'operativas' => $operativas,
        );
    }

    /**
     * @return array Con claves `solicitudes` (listado mapeado) y `resumen` (totales para cards del índice).
     */
    public function listarSolicitudes(array $filters = array(), ?Authenticatable $authUser = null)
    {
        $authUser = $authUser ?: Auth::user();
        $viewerId = $this->authUserId($authUser) ?: 0;
        $isStaff = $this->usuarioEsStaffSoporteTi($authUser);

        return $this->cache->rememberListado($viewerId, $isStaff, $filters, function () use ($filters, $authUser) {
            $query = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'))
                ->orderBy('prioridad', 'asc')
                ->orderBy('created_at', 'desc');

            if ($authUser && !$this->usuarioEsStaffSoporteTi($authUser)) {
                $query->where('solicitante_user_id', (int) $authUser->getKey());
            }

            if (!empty($filters['q'])) {
                $q = $filters['q'];
                $query->where(function ($sub) use ($q) {
                    $sub->where('codigo', 'like', '%' . $q . '%')
                        ->orWhere('titulo', 'like', '%' . $q . '%')
                        ->orWhere('solicitante', 'like', '%' . $q . '%');
                });
            }

            if (!empty($filters['tipo_solicitud']) && $filters['tipo_solicitud'] !== 'todos') {
                $query->where('tipo_solicitud', $filters['tipo_solicitud']);
            }

            $rows = $query->get();
            $resumen = $this->resumenListadoSolicitudes($rows);
            $solicitudes = $rows->map(function (SoporteTiSolicitud $s) use ($authUser) {
                return $this->mapSolicitud($s, $authUser);
            })->values()->all();

            return array(
                'solicitudes' => $solicitudes,
                'resumen' => $resumen,
            );
        });
    }

    /**
     * @return array
     */
    public function obtenerSolicitud($id, ?Authenticatable $authUser = null)
    {
        $authUser = $authUser ?: Auth::user();
        $viewerId = $this->authUserId($authUser) ?: 0;

        return $this->cache->rememberSolicitudShow($id, $viewerId, function () use ($id, $authUser) {
            $s = $this->asegurarAccesoSolicitud(
                $id,
                $authUser,
                array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
            );

            return $this->mapSolicitud($s, $authUser);
        });
    }

    /**
     * Crea solicitud. El usuario debe ser el modelo intranet (p. ej. App\Models\Usuario).
     * Opcional: $imagenesIniciales — cada archivo genera mensaje de chat propio y registro en solicitud_evidencias.
     *
     * @param array                                  $data
     * @param \Illuminate\Http\UploadedFile[]|array  $imagenesIniciales
     * @return array
     */
    public function crearSolicitud(array $data, ?Authenticatable $user = null, array $imagenesIniciales = array())
    {
        $user = $user ?: Auth::user();
        $tipo = isset($data['tipo_solicitud']) ? $data['tipo_solicitud'] : 'B';
        $sla = $tipo === 'A' ? 0 : 8;
        $now = Carbon::now();

        $mapped = DB::transaction(function () use ($data, $user, $tipo, $sla, $now, $imagenesIniciales) {
                $solicitud = SoporteTiSolicitud::create(array(
                'codigo' => $this->generarCodigo($tipo),
                'tipo_solicitud' => $tipo,
                'subtipo_b' => $tipo === 'B' ? (isset($data['subtipo_b']) ? $data['subtipo_b'] : null) : null,
                'titulo' => isset($data['titulo']) ? $data['titulo'] : 'Nueva solicitud',
                'area' => isset($data['area']) ? $data['area'] : 'Ventas',
                'solicitante' => $this->nombreUsuario($user),
                'solicitante_user_id' => $this->authUserId($user),
                'pm' => $tipo === 'A' ? 'Por asignar' : null,
                'analista' => 'Por asignar',
                'criticidad' => 'Por definir',
                'complejidad_pm' => 'Por definir',
                'complejidad_analista' => 'Por definir',
                'estado_actual_id' => 1,
                'fase_index' => 0,
                'progreso' => 0,
                'sla_horas' => $sla,
                'horas_transcurridas' => 0,
                'seccion_ruta' => isset($data['seccion_ruta']) ? $data['seccion_ruta'] : null,
                'descripcion' => isset($data['descripcion']) ? $data['descripcion'] : null,
                'ultima_actualizacion' => $now,
            ));

            $sala = SoporteTiChatSala::create(array(
                'chat_uuid' => (string) Str::uuid(),
                'solicitud_id' => $solicitud->id,
            ));

            if ($user) {
                $this->agregarMiembroSala($sala->id, $this->authUserId($user), 'solicitante');
            }

            $this->registrarHistorialEstado($solicitud, 1, null, $user, 'Solicitud creada');

            $msgCreado = 'Ticket ' . $solicitud->codigo . ' creado.';
            if ($tipo === 'B') {
                $msgCreado .= ' SLA: ' . $sla . 'h.';
            }
            $this->crearMensajeSistema($sala, $solicitud, $msgCreado);

            $meta = $this->metaRemitente($user);
            $ordenEvid = $this->maxOrdenEvidencia($solicitud->id) + 1;

            $descripcionTxt = isset($data['descripcion']) ? trim((string) $data['descripcion']) : '';
            if ($descripcionTxt !== '') {
                $msgDetalle = $this->crearMensajeUsuarioSimple(
                    $sala,
                    $solicitud,
                    $descripcionTxt,
                    null,
                    $user,
                    $meta
                );
                $this->registrarEvidenciaTexto($solicitud->id, $msgDetalle->id, $descripcionTxt, $ordenEvid);
                ++$ordenEvid;
                $msgDetalle->load(array('imagenes', 'replyTo'));
                event(new SoporteTiMensajeCreado($solicitud, $this->mapMensaje($msgDetalle, null)));
            }

            $filesInicial = array();
            foreach ($imagenesIniciales as $f) {
                if ($f instanceof UploadedFile) {
                    $filesInicial[] = $f;
                }
            }

            if (count($filesInicial) > 0) {
                $txtCabecera = trim((string) $solicitud->titulo) . ' — Evidencia';
                $msgCab = $this->crearMensajeUsuarioSimple($sala, $solicitud, $txtCabecera, null, $user, $meta);
                $this->registrarEvidenciaTexto($solicitud->id, $msgCab->id, $txtCabecera, $ordenEvid);
                ++$ordenEvid;
                $msgCab->load(array('imagenes', 'replyTo'));
                event(new SoporteTiMensajeCreado($solicitud, $this->mapMensaje($msgCab, null)));

                foreach ($filesInicial as $file) {
                    $this->encolarMensajeChatUnArchivo($sala, $solicitud, $user, $meta, '', null, $file, $ordenEvid);
                    ++$ordenEvid;
                }
            }

            $solicitud->load(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'));

            return $this->mapSolicitud($solicitud, $user);
        });

        $fresh = SoporteTiSolicitud::with('salaChat')->find(isset($mapped['id']) ? (int) $mapped['id'] : 0);
        if ($fresh) {
            $this->cache->invalidateAfterSolicitudWrite($fresh);
        }

        return $mapped;
    }

    /**
     * @param string|null $c
     * @return bool
     */
    protected function criticidadSinDefinir($c)
    {
        $s = strtolower(trim((string) $c));

        return $s === '' || strpos($s, 'definir') !== false;
    }

    /**
     * @param string $criticidad
     * @return string
     */
    protected function normalizarComplejidad($criticidad)
    {
        $c = trim((string) $criticidad);
        $validas = array('Baja', 'Media', 'Alta', 'Máxima');
        if (!in_array($c, $validas, true)) {
            throw new \InvalidArgumentException('Complejidad no válida. Use Baja, Media, Alta o Máxima.');
        }

        return $c;
    }

    /**
     * @param string $estadoCodigo
     * @return int
     */
    protected function resolverEstadoIdPorCodigo($estadoCodigo)
    {
        $codigo = trim((string) $estadoCodigo);
        if ($codigo === '') {
            throw new \InvalidArgumentException('Código de estado requerido.');
        }

        $estado = SoporteTiEstado::where('codigo', $codigo)->where('activo', true)->first();
        if (!$estado) {
            throw new \InvalidArgumentException('Estado no válido.');
        }

        return (int) $estado->id;
    }

    /**
     * @param int $estadoId
     * @return SoporteTiEstado
     */
    protected function obtenerEstadoActivoPorId($estadoId)
    {
        $estado = SoporteTiEstado::where('id', (int) $estadoId)->where('activo', true)->first();
        if (!$estado) {
            throw new \InvalidArgumentException('Estado no válido.');
        }

        return $estado;
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param SoporteTiEstado $nuevoEstado
     */
    /**
     * @param string $tipoSolicitud
     * @param string $criticidad
     * @return int
     */
    protected function slaHorasPorComplejidad($tipoSolicitud, $criticidad)
    {
        $c = trim((string) $criticidad);
        $tipo = $tipoSolicitud === 'A' ? 'A' : 'B';

        $query = SoporteTiSlaHoras::where('tipo_solicitud', $tipo)->where('criticidad', $c);
        if ($tipo === 'A') {
            $query->where(function ($q) {
                $q->where('ambito', 'analista_config')->orWhereNull('ambito');
            });
        }
        $row = $query->first();
        if ($row) {
            return (int) $row->horas;
        }

        $mapA = array('Baja' => 12, 'Media' => 24, 'Alta' => 36, 'Máxima' => 48);
        $mapB = array('Baja' => 4, 'Media' => 8, 'Alta' => 16, 'Máxima' => 24);
        $map = $tipo === 'A' ? $mapA : $mapB;
        if (!isset($map[$c])) {
            throw new \InvalidArgumentException('Complejidad no válida.');
        }

        return (int) $map[$c];
    }

    /**
     * Catálogo de horas SLA por tipo y complejidad (mantenedor).
     *
     * @param string $tipoSolicitud A|B
     * @param Authenticatable|null $user
     * @return array
     */
    public function listarSlaHoras($tipoSolicitud, ?Authenticatable $user = null, $ambito = null)
    {
        $user = $user ?: Auth::user();
        if (!$this->usuarioEsStaffSoporteTi($user)) {
            throw new AuthorizationException('Solo soporte o PM puede consultar el mantenedor de horas SLA.');
        }

        $tipo = $tipoSolicitud === 'A' ? 'A' : 'B';
        $ambitoFiltro = $this->normalizarAmbitoSlaHoras($tipo, $ambito);
        $cacheKey = $tipo . ':' . $ambitoFiltro;

        return $this->cache->rememberSlaHoras($cacheKey, function () use ($tipo, $ambitoFiltro) {
            $orden = array('Baja' => 0, 'Media' => 1, 'Alta' => 2, 'Máxima' => 3);

            $query = SoporteTiSlaHoras::where('tipo_solicitud', $tipo);
            if ($tipo === 'A') {
                if ($ambitoFiltro === 'pm_fases') {
                    $query->where('ambito', 'pm_fases');
                } else {
                    $query->where(function ($q) {
                        $q->where('ambito', 'analista_config')->orWhereNull('ambito');
                    });
                }
            }
            return $query->get()
                ->sortBy(function (SoporteTiSlaHoras $row) use ($orden) {
                    return isset($orden[$row->criticidad]) ? $orden[$row->criticidad] : 99;
                })
                ->values()
                ->map(function (SoporteTiSlaHoras $row) {
                    return array(
                        'id' => (int) $row->id,
                        'tipo_solicitud' => $row->tipo_solicitud,
                        'criticidad' => $row->criticidad,
                        'ambito' => isset($row->ambito) ? $row->ambito : null,
                        'horas' => (int) $row->horas,
                        'updated_at' => $row->updated_at ? $row->updated_at->toIso8601String() : null,
                    );
                })
                ->values()
                ->all();
        });
    }

    /**
     * @param string $tipo A|B
     * @param string|null $ambito
     * @return string
     */
    protected function normalizarAmbitoSlaHoras($tipo, $ambito)
    {
        if ($tipo !== 'A') {
            return 'general';
        }
        $a = strtolower(trim((string) $ambito));
        if ($a === 'pm_fases' || $a === 'pm') {
            return 'pm_fases';
        }
        return 'analista_config';
    }

    /**
     * Actualiza horas SLA del mantenedor.
     *
     * @param string $tipoSolicitud
     * @param array $items [{ id, horas }]
     * @param Authenticatable|null $user
     * @return array
     */
    public function actualizarSlaHoras($tipoSolicitud, array $items, ?Authenticatable $user = null, $ambito = null)
    {
        $user = $user ?: Auth::user();
        if (!$this->usuarioEsStaffSoporteTi($user)) {
            throw new AuthorizationException('Solo soporte o PM puede actualizar las horas SLA.');
        }

        $tipo = $tipoSolicitud === 'A' ? 'A' : 'B';
        $ambitoFiltro = $this->normalizarAmbitoSlaHoras($tipo, $ambito);
        if ($tipo === 'A') {
            $helperA = $this->tipoASla();
            if ($ambitoFiltro === 'pm_fases') {
                if (!$helperA->usuarioEsPm($user)) {
                    throw new AuthorizationException('Solo el PM puede modificar las horas de fases del tipo A.');
                }
            } elseif (!$helperA->usuarioEsAnalista($user)) {
                throw new AuthorizationException('Solo el analista puede modificar las horas de configuración del tipo A.');
            }
        }
        $permitidas = array('Baja', 'Media', 'Alta', 'Máxima');

        $result = DB::transaction(function () use ($tipo, $items, $permitidas, $user, $ambitoFiltro) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Formato de fila inválido.');
                }
                $id = isset($item['id']) ? (int) $item['id'] : 0;
                $horas = isset($item['horas']) ? (int) $item['horas'] : -1;
                if ($id <= 0 || $horas < 1 || $horas > 9999) {
                    throw new \InvalidArgumentException('Cada fila debe tener id y horas entre 1 y 9999.');
                }

                $rowQuery = SoporteTiSlaHoras::where('id', $id)->where('tipo_solicitud', $tipo);
                if ($tipo === 'A') {
                    if ($ambitoFiltro === 'pm_fases') {
                        $rowQuery->where('ambito', 'pm_fases');
                    } else {
                        $rowQuery->where(function ($q) {
                            $q->where('ambito', 'analista_config')->orWhereNull('ambito');
                        });
                    }
                }
                $row = $rowQuery->first();
                if (!$row) {
                    throw new \InvalidArgumentException('Registro de horas no encontrado.');
                }
                if (!in_array($row->criticidad, $permitidas, true)) {
                    throw new \InvalidArgumentException('Complejidad no válida.');
                }

                $row->horas = $horas;
                $row->save();
            }

            return $this->listarSlaHoras($tipo, $user, $ambitoFiltro);
        });

        $this->cache->invalidateSlaHoras($tipo);

        return $result;
    }

    /**
     * Horas por fase (tipo A, PM — sin configuración).
     *
     * @param Authenticatable|null $user
     * @return array
     */
    public function listarFaseHorasA(?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        if (!$this->usuarioEsStaffSoporteTi($user)) {
            throw new AuthorizationException('Solo soporte o PM puede consultar las horas por fase.');
        }
        return $this->tipoASla()->listarFaseHorasMatriz();
    }

    /**
     * @param array $items
     * @param Authenticatable|null $user
     * @return array
     */
    public function actualizarFaseHorasA(array $items, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        if (!$this->usuarioEsStaffSoporteTi($user)) {
            throw new AuthorizationException('Solo soporte o PM puede actualizar las horas por fase.');
        }
        if (!$this->tipoASla()->usuarioEsPm($user)) {
            throw new AuthorizationException('Solo el PM puede modificar las horas de fases del tipo A.');
        }
        $result = $this->tipoASla()->actualizarFaseHoras($items);
        $this->cache->invalidateSlaHoras('A');
        return $result;
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param string $criticidad
     */
    protected function aplicarSlaYFechaPorComplejidad(SoporteTiSolicitud $solicitud, $criticidad)
    {
        if ($solicitud->tipo_solicitud === 'A') {
            $this->tipoASla()->aplicarSlaEnSolicitud($solicitud);
            $horas = (int) $solicitud->sla_horas;
        } else {
            $horas = $this->slaHorasPorComplejidad($solicitud->tipo_solicitud, $criticidad);
            $solicitud->sla_horas = $horas;
        }

        if ($horas <= 0) {
            $solicitud->fecha_fin_estimado = null;
            return;
        }

        $inicioEnProgreso = $this->obtenerInicioEnProgreso($solicitud);
        if ($inicioEnProgreso) {
            $solicitud->fecha_fin_estimado = $inicioEnProgreso->copy()->addHours($horas)->toDateString();
        } else {
            $solicitud->fecha_fin_estimado = Carbon::now()->addHours($horas)->toDateString();
        }
    }

    /**
     * Momento en que el ticket entró a En progreso (base del SLA para el solicitante).
     *
     * @param SoporteTiSolicitud $solicitud
     * @return Carbon|null
     */
    protected function obtenerInicioEnProgreso(SoporteTiSolicitud $solicitud)
    {
        $enProgreso = SoporteTiEstado::where('activo', true)->where('codigo', 'en_progreso')->first();
        if (!$enProgreso) {
            return null;
        }

        $entrada = SoporteTiSolicitudEstado::where('solicitud_id', (int) $solicitud->id)
            ->where('estado_id', (int) $enProgreso->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$entrada || !$entrada->created_at) {
            return null;
        }

        return Carbon::parse($entrada->created_at);
    }

    /**
     * Fecha/hora límite del SLA desde el inicio de En progreso + horas configuradas.
     *
     * @param SoporteTiSolicitud $solicitud
     * @param Carbon $inicioEnProgreso
     * @return Carbon|null
     */
    protected function calcularFinSlaContador(SoporteTiSolicitud $solicitud, Carbon $inicioEnProgreso)
    {
        if ($this->slaConfigurado($solicitud)) {
            return $inicioEnProgreso->copy()->addHours((int) $solicitud->sla_horas);
        }

        if ($solicitud->fecha_fin_estimado) {
            return Carbon::parse($solicitud->fecha_fin_estimado)->endOfDay();
        }

        return null;
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return bool
     */
    protected function slaConfigurado(SoporteTiSolicitud $solicitud)
    {
        if ((int) $solicitud->sla_horas <= 0) {
            return false;
        }
        if ($solicitud->tipo_solicitud === 'A') {
            $helper = $this->tipoASla();

            return $helper->complejidadValida($solicitud->complejidad_pm)
                || $helper->complejidadValida($solicitud->complejidad_analista);
        }

        return $this->complejidadValida($solicitud->criticidad);
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     */
    protected function acumularSegmentoSlaActivo(SoporteTiSolicitud $solicitud)
    {
        if (!$solicitud->sla_reanudado_en) {
            return;
        }
        $inicio = Carbon::parse($solicitud->sla_reanudado_en);
        $delta = max(0, Carbon::now()->getTimestamp() - $inicio->getTimestamp());
        $solicitud->sla_segundos_acumulados = (int) $solicitud->sla_segundos_acumulados + (int) $delta;
        $solicitud->sla_reanudado_en = null;
    }

    /**
     * Suma segundos en los que el ticket estuvo en estados donde el SLA corre (historial).
     *
     * @param SoporteTiSolicitud $solicitud
     * @return int
     */
    protected function calcularSegundosSlaActivosDesdeHistorial(SoporteTiSolicitud $solicitud)
    {
        $historial = SoporteTiSolicitudEstado::where('solicitud_id', (int) $solicitud->id)
            ->with('estado')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($historial->isEmpty()) {
            return 0;
        }

        $totalSeg = 0;
        $now = Carbon::now();
        $count = $historial->count();

        for ($i = 0; $i < $count; $i++) {
            $entrada = $historial[$i];
            $codigo = $entrada->estado ? $entrada->estado->codigo : null;
            if (!in_array($codigo, self::ESTADOS_SLA_CORRE, true) || !$entrada->created_at) {
                continue;
            }
            $inicio = Carbon::parse($entrada->created_at);
            if ($i + 1 < $count) {
                $fin = Carbon::parse($historial[$i + 1]->created_at);
            } else {
                $fin = $now;
            }
            $totalSeg += max(0, $fin->getTimestamp() - $inicio->getTimestamp());
        }

        return $totalSeg;
    }

    /**
     * Inicio del tramo SLA actual (última entrada al estado vigente).
     *
     * @param SoporteTiSolicitud $solicitud
     * @return Carbon|null
     */
    protected function obtenerInicioSegmentoSlaActual(SoporteTiSolicitud $solicitud)
    {
        $codigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        if (!in_array($codigo, self::ESTADOS_SLA_CORRE, true)) {
            return null;
        }

        $ultimo = SoporteTiSolicitudEstado::where('solicitud_id', (int) $solicitud->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$ultimo || !$ultimo->created_at) {
            return null;
        }

        return Carbon::parse($ultimo->created_at);
    }

    /**
     * Tickets previos a sla_reanudado_en: reconstruye acumulado desde el historial (En progreso / Hecho).
     *
     * @param SoporteTiSolicitud $solicitud
     */
    protected function asegurarSlaContadorSincronizado(SoporteTiSolicitud $solicitud)
    {
        if (!$this->slaConfigurado($solicitud) || !$this->obtenerInicioEnProgreso($solicitud)) {
            return;
        }

        $activosTotal = $this->calcularSegundosSlaActivosDesdeHistorial($solicitud);
        $necesitaSync = (int) $solicitud->sla_segundos_acumulados === 0
            && $solicitud->sla_reanudado_en === null
            && $activosTotal > 0;

        if (!$necesitaSync) {
            return;
        }

        $codigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;

        if (in_array($codigo, self::ESTADOS_SLA_CORRE, true)) {
            $inicioSegmento = $this->obtenerInicioSegmentoSlaActual($solicitud);
            if ($inicioSegmento) {
                $enSegmento = max(0, Carbon::now()->getTimestamp() - $inicioSegmento->getTimestamp());
                $solicitud->sla_segundos_acumulados = max(0, $activosTotal - $enSegmento);
                $solicitud->sla_reanudado_en = $inicioSegmento;
            } else {
                $solicitud->sla_segundos_acumulados = $activosTotal;
            }
        } else {
            $solicitud->sla_segundos_acumulados = $activosTotal;
            $solicitud->sla_reanudado_en = null;
        }

        $this->persistirHorasTranscurridasSla($solicitud);
        $solicitud->save();
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     */
    protected function iniciarSegmentoSla(SoporteTiSolicitud $solicitud)
    {
        if (!$this->slaConfigurado($solicitud)) {
            return;
        }
        $solicitud->sla_reanudado_en = Carbon::now();
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return int
     */
    protected function segundosSlaTranscurridos(SoporteTiSolicitud $solicitud)
    {
        $this->asegurarSlaContadorSincronizado($solicitud);

        $seg = (int) $solicitud->sla_segundos_acumulados;
        $codigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        if (
            in_array($codigo, self::ESTADOS_SLA_CORRE, true)
            && $solicitud->sla_reanudado_en
        ) {
            $inicio = Carbon::parse($solicitud->sla_reanudado_en);
            $seg += max(0, Carbon::now()->getTimestamp() - $inicio->getTimestamp());
        }

        return $seg;
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     */
    protected function persistirHorasTranscurridasSla(SoporteTiSolicitud $solicitud)
    {
        $solicitud->horas_transcurridas = round($this->segundosSlaTranscurridos($solicitud) / 3600, 2);
    }

    /**
     * Pausa o reanuda el contador SLA según el cambio de estado.
     *
     * @param SoporteTiSolicitud $solicitud
     * @param string|null        $codigoAnterior
     * @param string             $codigoNuevo
     */
    protected function gestionarSlaContadorTransicion(SoporteTiSolicitud $solicitud, $codigoAnterior, $codigoNuevo)
    {
        if ($codigoAnterior && in_array($codigoAnterior, self::ESTADOS_SLA_CORRE, true)) {
            $this->acumularSegmentoSlaActivo($solicitud);
        }
        if (in_array($codigoNuevo, self::ESTADOS_SLA_CORRE, true)) {
            $this->iniciarSegmentoSla($solicitud);
        }
        $this->persistirHorasTranscurridasSla($solicitud);
    }

    /**
     * Operativo / Observado: solo el usuario que creó la solicitud y con ticket desplegado.
     *
     * @param SoporteTiSolicitud $solicitud
     * @param SoporteTiEstado $nuevoEstado
     * @param Authenticatable|null $user
     */
    /**
     * @param SoporteTiSolicitud $solicitud
     * @param Authenticatable|null $user
     * @return bool
     */
    protected function esCreadorSolicitud(SoporteTiSolicitud $solicitud, ?Authenticatable $user = null)
    {
        $uid = $this->authUserId($user ?: Auth::user());
        if ($uid === null || $solicitud->solicitante_user_id === null) {
            return false;
        }

        return (int) $solicitud->solicitante_user_id === (int) $uid;
    }

    /**
     * @param string|null $criticidad
     * @return bool
     */
    protected function complejidadValida($criticidad)
    {
        return in_array(trim((string) $criticidad), array('Baja', 'Media', 'Alta', 'Máxima'), true);
    }

    /**
     * Reglas de flujo para En progreso (tipo A/B, maqueta, complejidad).
     *
     * @param SoporteTiSolicitud $solicitud
     * @return bool
     */
    protected function puedeEnProgreso(SoporteTiSolicitud $solicitud)
    {
        if ($this->criticidadSinDefinir($solicitud->criticidad)) {
            return false;
        }
        $prevCodigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        if ($solicitud->tipo_solicitud === 'A') {
            if ($prevCodigo === 'en_maqueta') {
                return $this->tipoASla()->complejidadValida($solicitud->complejidad_pm);
            }
            if ($prevCodigo === 'observado') {
                return true;
            }

            return false;
        }
        if ($solicitud->tipo_solicitud === 'B') {
            return in_array($prevCodigo, array('pendiente', 'observado'), true);
        }

        return false;
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param Authenticatable|null $user
     * @return \Illuminate\Support\Collection|SoporteTiEstado[]
     */
    protected function estadosParaUsuario(SoporteTiSolicitud $solicitud, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        if ($this->esCreadorSolicitud($solicitud, $user)) {
            return SoporteTiEstado::where('activo', true)
                ->whereIn('codigo', array('operativo', 'observado'))
                ->orderBy('orden_kanban')
                ->get();
        }
        if ($this->usuarioEsStaffSoporteTi($user)) {
            return SoporteTiEstado::where('activo', true)
                ->whereNotIn('codigo', array('operativo', 'observado'))
                ->where(function ($q) use ($solicitud) {
                    $q->whereNull('tipo_solicitud')
                        ->orWhere('tipo_solicitud', $solicitud->tipo_solicitud);
                })
                ->orderBy('orden_kanban')
                ->get();
        }

        return collect();
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return string
     */
    protected function terminoEstimado(SoporteTiSolicitud $solicitud)
    {
        $contador = $this->contadorSla($solicitud);
        if ($contador['activo'] && !empty($contador['fin'])) {
            return $this->formatearMarcaTiempo(Carbon::parse($contador['fin']));
        }

        if ($solicitud->tipo_solicitud === 'A') {
            $inicio = $this->obtenerInicioEnProgreso($solicitud);
            return $this->tipoASla()->terminoEstimadoTexto($solicitud, $inicio ?: Carbon::now());
        }

        if ($this->complejidadValida($solicitud->criticidad)) {
            try {
                $horas = $this->slaHorasPorComplejidad($solicitud->tipo_solicitud, $solicitud->criticidad);
                $inicio = $this->obtenerInicioEnProgreso($solicitud);
                $base = $inicio ?: Carbon::now();

                return $this->formatearMarcaTiempo($base->copy()->addHours($horas));
            } catch (\Exception $e) {
                // sin SLA calculable
            }
        }

        if ($solicitud->fecha_fin_estimado) {
            return $this->formatearFechaCorta(Carbon::parse($solicitud->fecha_fin_estimado));
        }

        return 'Por definir';
    }

    /**
     * Contador SLA del creador: desde el último paso a En progreso hasta fecha fin / horas SLA.
     *
     * @param SoporteTiSolicitud $solicitud
     * @return array{activo: bool, fin: string|null, vencido: bool}
     */
    protected function contadorSla(SoporteTiSolicitud $solicitud)
    {
        $inactivo = array(
            'activo' => false,
            'pausado' => false,
            'fin' => null,
            'restante_segundos' => null,
            'vencido' => false,
        );

        $estadoCodigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        if (!in_array($estadoCodigo, self::ESTADOS_SLA_CONTADOR_VISIBLE, true)) {
            return $inactivo;
        }

        if (!$this->slaConfigurado($solicitud)) {
            return $inactivo;
        }

        $inicio = $this->obtenerInicioEnProgreso($solicitud);
        if (!$inicio) {
            return $inactivo;
        }

        $totalSeg = (int) $solicitud->sla_horas * 3600;
        if ($totalSeg <= 0) {
            return $inactivo;
        }

        $transcurridos = $this->segundosSlaTranscurridos($solicitud);
        $restanteSeg = max(0, $totalSeg - $transcurridos);
        $pausado = !in_array($estadoCodigo, self::ESTADOS_SLA_CORRE, true);
        $vencido = $restanteSeg <= 0;
        $finIso = $this->calcularIsoFinContadorSla($solicitud, $totalSeg, $pausado, $restanteSeg);

        return array(
            'activo' => true,
            'pausado' => $pausado,
            'fin' => $finIso,
            'restante_segundos' => $restanteSeg,
            'vencido' => $vencido,
        );
    }

    /**
     * Fecha límite fija del contador: inicio del tramo + SLA restante (no se recalcula cada segundo).
     *
     * @param SoporteTiSolicitud $solicitud
     * @param int                $totalSeg
     * @param bool               $pausado
     * @param int                $restanteSeg
     * @return string|null
     */
    protected function calcularIsoFinContadorSla(SoporteTiSolicitud $solicitud, $totalSeg, $pausado, $restanteSeg)
    {
        if ($pausado) {
            return Carbon::now()->addSeconds($restanteSeg)->toIso8601String();
        }

        if ($solicitud->sla_reanudado_en) {
            $acum = (int) $solicitud->sla_segundos_acumulados;

            return Carbon::parse($solicitud->sla_reanudado_en)
                ->addSeconds(max(0, $totalSeg - $acum))
                ->toIso8601String();
        }

        $inicio = $this->obtenerInicioEnProgreso($solicitud);
        if ($inicio) {
            return $inicio->copy()->addSeconds($totalSeg)->toIso8601String();
        }

        return Carbon::now()->addSeconds($restanteSeg)->toIso8601String();
    }

    /**
     * Permisos y metadatos de UI para el usuario actual (listado y detalle).
     *
     * @param SoporteTiSolicitud $solicitud
     * @param Authenticatable|null $user
     * @return array
     */
    protected function mapGestion(SoporteTiSolicitud $solicitud, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $esCreador = $this->esCreadorSolicitud($solicitud, $user);
        $esStaff = $this->usuarioEsStaffSoporteTi($user);
        $estadoCodigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        $helperA = $this->tipoASla();
        $esTipoA = $solicitud->tipo_solicitud === 'A';
        $esPm = $helperA->usuarioEsPm($user);
        $esAnalista = $helperA->usuarioEsAnalista($user);
        $pmOk = $helperA->complejidadValida($solicitud->complejidad_pm);
        $anOk = $helperA->complejidadValida($solicitud->complejidad_analista);
        $complejidadOk = $esTipoA ? ($pmOk || $anOk) : $this->complejidadValida($solicitud->criticidad);

        $estadosSelect = $this->estadosParaUsuario($solicitud, $user)->map(function (SoporteTiEstado $e) {
            return array(
                'id' => (int) $e->id,
                'codigo' => $e->codigo,
                'nombre' => $e->nombre,
            );
        })->values()->all();

        $slaEtiqueta = null;
        $tiempoEstimadoRango = false;
        if ($esTipoA && $pmOk) {
            $slaRes = $helperA->resolverSla($solicitud);
            $slaEtiqueta = $slaRes['etiqueta'];
            $tiempoEstimadoRango = $slaRes['es_rango'];
        } elseif ($complejidadOk && !$esTipoA) {
            try {
                $slaEtiqueta = $this->slaHorasPorComplejidad($solicitud->tipo_solicitud, $solicitud->criticidad) . ' h';
            } catch (\Exception $e) {
                if ($solicitud->sla_horas) {
                    $slaEtiqueta = (int) $solicitud->sla_horas . ' h';
                }
            }
        }

        $contador = $this->contadorSla($solicitud);

        $puedeComplejidadPm = $esStaff && $esTipoA && $esPm;
        $puedeComplejidadAnalista = $esStaff && $esTipoA && $esAnalista;
        $estadoEditableStaff = $esTipoA
            ? ($esPm ? $pmOk : ($esAnalista ? $anOk : $complejidadOk))
            : $complejidadOk;

        return array(
            'es_creador' => $esCreador,
            'es_staff' => $esStaff,
            'puede_complejidad' => $esStaff && !$esTipoA,
            'puede_complejidad_pm' => $puedeComplejidadPm,
            'puede_complejidad_analista' => $puedeComplejidadAnalista,
            'puede_estado' => $esCreador || $esStaff,
            'estados' => $estadosSelect,
            'estado_valor' => $estadoCodigo,
            'complejidad_valor' => $complejidadOk && !$esTipoA ? trim((string) $solicitud->criticidad) : null,
            'complejidad_pm_valor' => $pmOk ? trim((string) $solicitud->complejidad_pm) : null,
            'complejidad_analista_valor' => $anOk ? trim((string) $solicitud->complejidad_analista) : null,
            'estado_editable' => $esCreador ? true : ($esStaff && $estadoEditableStaff),
            'tiempo_estimado_rango' => $tiempoEstimadoRango,
            'puede_confirmar' => $esCreador && $estadoCodigo === 'desplegado' && $complejidadOk,
            'estado_placeholder' => $solicitud->estadoActual
                ? $solicitud->estadoActual->nombre
                : 'Elegir',
            'termino_estimado' => $this->terminoEstimado($solicitud),
            'sla_etiqueta' => $slaEtiqueta,
            'ver_sla' => $esStaff && ($esTipoA ? $pmOk : $complejidadOk),
            'puede_en_progreso' => $this->puedeEnProgreso($solicitud),
            'contador_activo' => $contador['activo'],
            'contador_pausado' => $contador['pausado'],
            'contador_fin' => $contador['fin'],
            'contador_restante_segundos' => $contador['restante_segundos'],
            'contador_vencido' => $contador['vencido'],
        );
    }

    /**
     * Valida quién puede aplicar cada transición de estado.
     *
     * @param SoporteTiSolicitud $solicitud
     * @param SoporteTiEstado $nuevoEstado
     * @param Authenticatable|null $user
     */
    protected function validarCambioEstadoPorRol(
        SoporteTiSolicitud $solicitud,
        SoporteTiEstado $nuevoEstado,
        ?Authenticatable $user = null
    ) {
        $user = $user ?: Auth::user();
        $codigo = $nuevoEstado->codigo;
        $esCreador = $this->esCreadorSolicitud($solicitud, $user);
        $esStaff = $this->usuarioEsStaffSoporteTi($user);

        if ($esStaff && !$esCreador && ($codigo === 'operativo' || $codigo === 'observado')) {
            throw new \InvalidArgumentException(
                'El analista no puede marcar Operativo u Observado.'
            );
        }

        if ($codigo === 'operativo' || $codigo === 'observado') {
            $this->validarPermisoCambioEstado($solicitud, $nuevoEstado, $user);

            return;
        }

        if ($esStaff) {
            return;
        }

        if ($esCreador) {
            throw new \InvalidArgumentException(
                'Como creador de la solicitud solo puede elegir Operativo u Observado.'
            );
        }

        throw new AuthorizationException('No puede cambiar el estado de esta solicitud.');
    }

    protected function validarPermisoCambioEstado(
        SoporteTiSolicitud $solicitud,
        SoporteTiEstado $nuevoEstado,
        ?Authenticatable $user = null
    ) {
        $user = $user ?: Auth::user();
        $codigo = $nuevoEstado->codigo;

        if ($codigo !== 'operativo' && $codigo !== 'observado') {
            return;
        }

        if (!$this->esCreadorSolicitud($solicitud, $user)) {
            throw new \InvalidArgumentException(
                'Solo quien creó la solicitud puede marcar Operativo u Observado.'
            );
        }

        $actual = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
        if ($actual !== 'desplegado') {
            throw new \InvalidArgumentException(
                'Solo puede confirmar operativo u observado cuando el ticket está Desplegado.'
            );
        }
    }

    protected function validarTransicionEstado(SoporteTiSolicitud $solicitud, SoporteTiEstado $nuevoEstado)
    {
        if ($nuevoEstado->codigo !== 'en_progreso') {
            return;
        }

        if (!$this->puedeEnProgreso($solicitud)) {
            throw new \InvalidArgumentException(
                'No se puede pasar a En progreso. Revise la complejidad y el flujo del ticket.'
            );
        }
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return array
     */
    protected function mapSolicitudRecargada(SoporteTiSolicitud $solicitud, ?Authenticatable $user = null)
    {
        $solicitud->load(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'));

        return $this->mapSolicitud($solicitud, $user);
    }

    /**
     * Actualiza campos generales (fase, progreso, maqueta). No cambia estado ni complejidad.
     *
     * @param int|string $id
     * @return array
     */
    public function actualizarSolicitud($id, array $data, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = $this->asegurarAccesoSolicitud(
            $id,
            $user,
            array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
        );

        $patch = array();

        if (isset($data['fase_index'])) {
            $patch['fase_index'] = (int) $data['fase_index'];
        }
        if (isset($data['progreso'])) {
            $patch['progreso'] = (int) $data['progreso'];
        }
        if (array_key_exists('maqueta', $data)) {
            $this->syncMaqueta($solicitud, $data['maqueta'], $user);
        }
        if (isset($data['prioridad'])) {
            if (!$this->tipoASla()->usuarioEsPm($user)) {
                throw new AuthorizationException('Solo el PM puede cambiar la prioridad.');
            }
            $prioridad = (int) $data['prioridad'];
            if (!in_array($prioridad, array(1, 2, 3), true)) {
                throw new \InvalidArgumentException('Prioridad no válida.');
            }
            $patch['prioridad'] = $prioridad;
        }

        if (!empty($patch)) {
            $patch['ultima_actualizacion'] = Carbon::now();
            $solicitud->fill($patch);
            $solicitud->save();
        }

        $mapped = $this->mapSolicitudRecargada($solicitud, $user);
        $this->cache->invalidateAfterSolicitudWrite($solicitud);

        return $mapped;
    }

    /**
     * Solo persiste la complejidad (criticidad).
     *
     * @param int|string $id
     * @param string $criticidad
     * @return array
     */
    public function actualizarComplejidad($id, $criticidad, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        if (!$this->usuarioEsStaffSoporteTi($user)) {
            throw new AuthorizationException('Solo soporte o PM puede asignar la complejidad.');
        }
        $solicitud = $this->asegurarAccesoSolicitud(
            $id,
            $user,
            array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
        );

        $c = $this->normalizarComplejidad($criticidad);
        $helperA = $this->tipoASla();
        if ($solicitud->tipo_solicitud === 'A') {
            if ($helperA->usuarioEsPm($user)) {
                $solicitud->complejidad_pm = $c;
            } elseif ($helperA->usuarioEsAnalista($user)) {
                $solicitud->complejidad_analista = $c;
            }
            $solicitud->criticidad = $c;
        } else {
            $solicitud->criticidad = $c;
        }
        $this->aplicarSlaYFechaPorComplejidad($solicitud, $c);
        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $mapped = $this->mapSolicitudRecargada($solicitud, $user);
        $this->cache->invalidateAfterSolicitudWrite($solicitud);

        return $mapped;
    }

    /**
     * Solo cambia el estado (historial, mensaje de sistema y broadcast).
     *
     * @param int|string $id
     * @param int $estadoId
     * @return array
     */
    public function actualizarEstado($id, $estadoId, $comentario = null, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = $this->asegurarAccesoSolicitud(
            $id,
            $user,
            array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
        );

        $nuevoEstadoId = (int) $estadoId;
        $estadoAnteriorId = (int) $solicitud->estado_actual_id;

        if ($nuevoEstadoId === $estadoAnteriorId) {
            return $this->mapSolicitudRecargada($solicitud);
        }

        $nuevoEstado = $this->obtenerEstadoActivoPorId($nuevoEstadoId);
        $this->validarCambioEstadoPorRol($solicitud, $nuevoEstado, $user);
        $this->validarTransicionEstado($solicitud, $nuevoEstado);

        $codigoAnterior = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;

        $solicitud->estado_actual_id = $nuevoEstadoId;
        if ($nuevoEstado->codigo === 'operativo') {
            $solicitud->progreso = 100;
        }
        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $historial = $this->registrarHistorialEstado(
            $solicitud,
            $nuevoEstadoId,
            $estadoAnteriorId,
            $user,
            $comentario
        );

        $solicitud->load('estadoActual', 'salaChat');
        $this->gestionarSlaContadorTransicion($solicitud, $codigoAnterior, $nuevoEstado->codigo);
        $solicitud->save();
        $estado = $solicitud->estadoActual;
        if ($solicitud->salaChat && $estado) {
            $this->crearMensajeSistema(
                $solicitud->salaChat,
                $solicitud,
                'Estado actualizado a "' . $estado->nombre . '".'
            );
            event(new SoporteTiEstadoActualizado($solicitud, $historial));
        }

        $mapped = $this->mapSolicitudRecargada($solicitud, $user);
        $this->cache->invalidateAfterSolicitudWrite($solicitud);

        return $mapped;
    }

    /**
     * Solo resuelve código de estado y delega en actualizarEstado.
     *
     * @param int|string $id
     * @param string $estadoCodigo
     * @return array
     */
    public function actualizarEstadoPorCodigo($id, $estadoCodigo, $comentario = null, ?Authenticatable $user = null)
    {
        $estadoId = $this->resolverEstadoIdPorCodigo($estadoCodigo);

        return $this->actualizarEstado($id, $estadoId, $comentario, $user);
    }

    /**
     * Alias de actualizarEstado (ruta POST legacy).
     *
     * @return array
     */
    public function cambiarEstado($id, $estadoId, $comentario = null, ?Authenticatable $user = null)
    {
        return $this->actualizarEstado($id, $estadoId, $comentario, $user);
    }

    /**
     * @return array
     */
    public function listarEstados()
    {
        return $this->cache->rememberEstados(function () {
            return SoporteTiEstado::where('activo', true)
                ->orderBy('orden_kanban')
                ->get()
                ->map(function (SoporteTiEstado $e) {
                    return $this->mapEstado($e);
                })
                ->values()
                ->all();
        });
    }

    /**
     * @return array
     */
    public function historialEstados($solicitudId, ?Authenticatable $authUser = null)
    {
        $this->asegurarAccesoSolicitud($solicitudId, $authUser, array());
        $sid = (int) $solicitudId;

        return $this->cache->rememberHistorialEstados($sid, function () use ($sid) {
            return SoporteTiSolicitudEstado::with(array('estado', 'estadoAnterior', 'usuario'))
                ->where('solicitud_id', $sid)
                ->orderBy('id', 'desc')
                ->get()
                ->map(function (SoporteTiSolicitudEstado $h) {
                    return $this->mapHistorial($h);
                })
                ->values()
                ->all();
        });
    }

    /**
     * @return array
     */
    public function mensajesPaginados($chatUuid, $limit = null, $beforeId = null, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $limit = $limit ? (int) $limit : self::CHAT_PAGE_SIZE;
        if ($limit < 1) {
            $limit = self::CHAT_PAGE_SIZE;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $viewerId = $this->authUserId($user) ?: 0;
        $chatUuid = trim((string) $chatUuid);

        return $this->cache->rememberMensajesPagina(
            $chatUuid,
            $viewerId,
            $limit,
            $beforeId,
            function () use ($chatUuid, $limit, $beforeId, $user) {
                $sala = SoporteTiChatSala::where('chat_uuid', $chatUuid)->with('solicitud')->firstOrFail();
                if (!$sala->solicitud) {
                    throw new \RuntimeException('Solicitud no encontrada para esta sala');
                }
                $this->asegurarAccesoSolicitudModel($sala->solicitud, $user);

                $query = SoporteTiMensaje::with(array('imagenes', 'replyTo'))
                    ->where('sala_id', $sala->id)
                    ->orderBy('id', 'desc');

                if ($beforeId) {
                    $query->where('id', '<', (int) $beforeId);
                }

                $rows = $query->limit($limit + 1)->get();
                $hasMore = $rows->count() > $limit;
                if ($hasMore) {
                    $rows = $rows->slice(0, $limit);
                }

                $viewerIdInner = $this->authUserId($user);
                $lecturasCtx = $this->buildLecturasContextForMensajes($sala->id, $rows->all(), $viewerIdInner);

                $mensajes = $rows->reverse()->values()->map(function (SoporteTiMensaje $m) use ($user, $lecturasCtx) {
                    return $this->mapMensaje($m, $user, $lecturasCtx);
                })->all();

                $oldestId = count($mensajes) ? $mensajes[0]['id'] : null;
                $newestId = count($mensajes) ? $mensajes[count($mensajes) - 1]['id'] : null;

                if ($hasMore && $oldestId) {
                    $hasMoreOlder = SoporteTiMensaje::where('sala_id', $sala->id)
                        ->where('id', '<', $oldestId)
                        ->exists();
                } else {
                    $hasMoreOlder = false;
                }

                return array(
                    'data' => $mensajes,
                    'pagination' => array(
                        'has_more' => $hasMoreOlder,
                        'oldest_id' => $oldestId,
                        'newest_id' => $newestId,
                        'per_page' => $limit,
                        'total' => null,
                    ),
                );
            }
        );
    }

    /**
     * Envía mensaje al chat. Con archivos: una sola burbuja (caption + adjuntos en un job).
     * Solo texto: mensaje inmediato con evento WS.
     *
     * @param string $texto
     * @param int|null $replyToId
     * @param UploadedFile[] $imagenes
     * @return array Mensaje mapeado
     */
    public function enviarMensaje($solicitudId, $texto, $replyToId = null, array $imagenes = array(), ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = $this->asegurarAccesoSolicitud($solicitudId, $user, array('salaChat'));
        $sala = $solicitud->salaChat;
        if (!$sala) {
            throw new \RuntimeException('Sala de chat no encontrada');
        }

        $meta = $this->metaRemitente($user);
        $texto = $texto !== null ? (string) $texto : '';
        $files = array();
        foreach ($imagenes as $file) {
            if ($file instanceof UploadedFile) {
                $files[] = $file;
            }
        }

        $payload = DB::transaction(function () use ($sala, $solicitud, $texto, $replyToId, $files, $user, $meta) {
            $ordenEvid = $this->maxOrdenEvidencia($solicitud->id) + 1;

            if (count($files) > 0) {
                return $this->encolarMensajeChatVariosArchivos(
                    $sala,
                    $solicitud,
                    $user,
                    $meta,
                    $texto,
                    $replyToId,
                    $files,
                    $ordenEvid
                );
            }

            if (trim($texto) !== '') {
                $msg = $this->crearMensajeUsuarioSimple($sala, $solicitud, $texto, $replyToId, $user, $meta);
                $this->registrarEvidenciaTexto($solicitud->id, $msg->id, $texto, $ordenEvid);
                $msg->load(array('imagenes', 'replyTo'));
                $mapped = $this->mapMensaje($msg, $user);
                event(new SoporteTiMensajeCreado($solicitud, $this->mapMensaje($msg, null)));

                return $mapped;
            }

            throw new \InvalidArgumentException('Mensaje vacío: indique texto o al menos un archivo.');
        });

        $extra = array();
        $uid = $this->authUserId($user);
        if ($uid) {
            $extra[] = $uid;
        }
        $this->cache->invalidateAfterMensajeWrite($solicitud, $sala->chat_uuid, $extra);

        return $payload;
    }

    /**
     * @param UploadedFile $archivo
     * @return array
     */
    public function subirMaqueta($solicitudId, UploadedFile $archivo, $mensajePm = null, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = $this->asegurarAccesoSolicitud($solicitudId, $user, array('salaChat', 'maqueta'));
        $tamano = $archivo->getSize() > 1048576
            ? round($archivo->getSize() / 1048576, 1) . ' MB'
            : round($archivo->getSize() / 1024) . ' KB';

        $mapped = DB::transaction(function () use ($archivo, $solicitud, $tamano, $mensajePm, $user) {
            $maqueta = SoporteTiMaqueta::updateOrCreate(
                array('solicitud_id' => $solicitud->id),
                array(
                    'nombre' => $archivo->getClientOriginalName(),
                    'tamano' => $tamano,
                    'ruta_archivo' => null,
                    'url_preview' => null,
                    'fecha_entrega' => Carbon::now()->toDateString(),
                    'aprobada' => false,
                    'subida_por_user_id' => $this->authUserId($user),
                )
            );

            $solicitud->ultima_actualizacion = Carbon::now();
            $solicitud->save();

            $texto = $mensajePm ? $mensajePm : 'He subido la maqueta para revisión.';
            $meta = $this->metaRemitente($user, 'PM');
            $mensajeId = 0;

            if ($solicitud->salaChat) {
                $mensaje = SoporteTiMensaje::create(array(
                    'sala_id' => $solicitud->salaChat->id,
                    'usuario_id' => $this->authUserId($user),
                    'remitente' => $meta['nombre'],
                    'iniciales' => $meta['iniciales'],  
                    'color' => $meta['color'],
                    'texto' => $texto,
                    'es_sistema' => false,
                    'archivo_nombre' => $maqueta->nombre,
                ));
                $mensajeId = (int) $mensaje->id;
            }

            $batch = (string) Str::uuid();
            $pendingRel = $archivo->store('soporte-ti/pending-maqueta/' . $batch, 'local');

            ProcessSoporteTiMaquetaUploadJob::dispatch(
                (int) $solicitud->id,
                $mensajeId,
                (int) $maqueta->id,
                $pendingRel
            )->afterCommit()->afterResponse();

            $solicitud->load(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'));

            return $this->mapSolicitud($solicitud, $user);
        });

        $fresh = SoporteTiSolicitud::with('salaChat')->find((int) $solicitud->id);
        if ($fresh) {
            $this->cache->invalidateAfterSolicitudWrite($fresh);
        }

        return $mapped;
    }

    /**
     * @return SoporteTiSolicitudEstado
     */
    protected function registrarHistorialEstado(
        SoporteTiSolicitud $solicitud,
        $estadoId,
        $estadoAnteriorId,
        ?Authenticatable $user = null,
        $comentario = null
    ) {
        return SoporteTiSolicitudEstado::create(array(
            'solicitud_id' => $solicitud->id,
            'estado_id' => $estadoId,
            'estado_anterior_id' => $estadoAnteriorId,
            'usuario_id' => $this->authUserId($user),
            'comentario' => $comentario,
            'created_at' => Carbon::now(),
        ));
    }

    public function crearMensajeSistema(SoporteTiChatSala $sala, SoporteTiSolicitud $solicitud, $texto)
    {
        $mensaje = SoporteTiMensaje::create(array(
            'sala_id' => $sala->id,
            'usuario_id' => null,
            'remitente' => 'Sistema',
            'iniciales' => 'SYS',
            'color' => '#64748b',
            'texto' => $texto,
            'es_sistema' => true,
        ));

        $payload = $this->mapMensaje($mensaje, null);
        event(new SoporteTiMensajeCreado($solicitud, $payload));

        $this->cache->invalidateAfterMensajeWrite($solicitud, $sala->chat_uuid);

        return $mensaje;
    }

    protected function agregarMiembroSala($salaId, $usuarioId, $rol)
    {
        SoporteTiChatMiembro::firstOrCreate(
            array('sala_id' => $salaId, 'usuario_id' => $usuarioId),
            array('rol_en_ticket' => $rol, 'joined_at' => Carbon::now())
        );
    }

    /**
     * Expuesto para jobs (lecturas).
     *
     * @param int    $salaId
     * @param int    $usuarioId
     * @param string $rol
     */
    public function asegurarMiembroSalaPublico($salaId, $usuarioId, $rol)
    {
        $this->agregarMiembroSala($salaId, $usuarioId, $rol);
    }

    /**
     * Marca en lote mensajes ajenos como leídos por el usuario actual (job + WS).
     *
     * @param string            $chatUuid
     * @param int[]             $mensajeIds
     * @param Authenticatable|null $user
     * @return array
     */
    public function marcarMensajesLeidos($chatUuid, array $mensajeIds, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $sala = SoporteTiChatSala::where('chat_uuid', $chatUuid)->with('solicitud')->firstOrFail();
        if (!$sala->solicitud) {
            throw new \RuntimeException('Solicitud no encontrada');
        }
        $this->asegurarAccesoSolicitudModel($sala->solicitud, $user);

        $lectorId = $this->authUserId($user);
        if (!$lectorId) {
            throw new AuthorizationException('No autenticado');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $mensajeIds), function ($id) {
            return $id > 0;
        })));

        if (empty($ids)) {
            return array('success' => true, 'queued' => 0, 'mensaje_ids' => array());
        }

        $validos = SoporteTiMensaje::where('sala_id', $sala->id)
            ->whereIn('id', $ids)
            ->where('es_sistema', false)
            ->whereNotNull('usuario_id')
            ->where('usuario_id', '!=', $lectorId)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if (empty($validos)) {
            return array('success' => true, 'queued' => 0, 'mensaje_ids' => array());
        }

        $this->asegurarMiembroSalaPublico($sala->id, $lectorId, 'participante');

        ProcessSoporteTiMarcarLeidosJob::dispatch($sala->solicitud->id, $lectorId, $validos);

        return array(
            'success' => true,
            'queued' => count($validos),
            'mensaje_ids' => $validos,
        );
    }

    /**
     * Info de lectura estilo WhatsApp para un mensaje propio.
     *
     * @param string $chatUuid
     * @param int    $mensajeId
     * @param Authenticatable|null $user
     * @return array
     */
    public function infoLecturaMensaje($chatUuid, $mensajeId, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $sala = SoporteTiChatSala::where('chat_uuid', $chatUuid)->with('solicitud')->firstOrFail();
        if (!$sala->solicitud) {
            throw new \RuntimeException('Solicitud no encontrada');
        }
        $this->asegurarAccesoSolicitudModel($sala->solicitud, $user);

        $mensaje = SoporteTiMensaje::with(array('imagenes', 'replyTo'))
            ->where('sala_id', $sala->id)
            ->where('id', (int) $mensajeId)
            ->firstOrFail();

        $viewerId = $this->authUserId($user);
        if (!$mensaje->usuario_id || (int) $mensaje->usuario_id !== $viewerId) {
            throw new AuthorizationException('Solo el autor puede ver la info del mensaje');
        }

        $lecturas = SoporteTiMensajeLectura::with('usuario')
            ->where('mensaje_id', $mensaje->id)
            ->where('usuario_id', '!=', $viewerId)
            ->orderBy('leido_en', 'asc')
            ->get();

        $leidoPor = array();
        foreach ($lecturas as $lec) {
            $u = $lec->usuario;
            $nombre = $u && $u->No_Nombres_Apellidos ? $u->No_Nombres_Apellidos : ($u ? $u->No_Usuario : 'Usuario');
            $leidoPor[] = array(
                'usuario_id' => (int) $lec->usuario_id,
                'nombre' => $nombre,
                'iniciales' => $this->inicialesDesdeNombre($nombre),
                'telefono' => $u && $u->Nu_Celular ? (string) $u->Nu_Celular : null,
                'email' => $u && $u->Txt_Email ? (string) $u->Txt_Email : null,
                'leido_en' => $lec->leido_en ? $lec->leido_en->toIso8601String() : null,
                'leido_en_fmt' => $lec->leido_en ? $this->formatearMarcaTiempoLectura($lec->leido_en) : null,
            );
        }

        return array(
            'mensaje' => $this->mapMensaje($mensaje, $user),
            'entregado_en_fmt' => $this->formatearMarcaTiempo(Carbon::parse($mensaje->created_at)),
            'leido_por' => $leidoPor,
            'leido_por_todos' => $this->mensajeLeidoPorDestinatarios($mensaje),
            'destinatarios_count' => count($this->idsDestinatariosMensaje($mensaje)),
            'lecturas_count' => count($leidoPor),
        );
    }

    /**
     * @param SoporteTiMensaje $m
     * @return int[]
     */
    protected function idsDestinatariosMensaje(SoporteTiMensaje $m)
    {
        $autorId = (int) $m->usuario_id;
        if (!$autorId) {
            return array();
        }

        $ids = SoporteTiChatMiembro::where('sala_id', $m->sala_id)
            ->where('usuario_id', '!=', $autorId)
            ->pluck('usuario_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Leído cuando todos los miembros (excepto autor) registraron lectura.
     * Sin miembros: basta una lectura de otro usuario.
     *
     * @param SoporteTiMensaje $m
     * @return bool
     */
    public function mensajeLeidoPorDestinatarios(SoporteTiMensaje $m)
    {
        $autorId = (int) $m->usuario_id;
        if (!$autorId) {
            return false;
        }

        $dest = $this->idsDestinatariosMensaje($m);
        if (empty($dest)) {
            return SoporteTiMensajeLectura::where('mensaje_id', $m->id)
                ->where('usuario_id', '!=', $autorId)
                ->exists();
        }

        $leidos = SoporteTiMensajeLectura::where('mensaje_id', $m->id)
            ->whereIn('usuario_id', $dest)
            ->pluck('usuario_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        return count(array_intersect($dest, $leidos)) === count($dest);
    }

    /**
     * Precarga lecturas y miembros de sala para mapear un lote de mensajes sin N+1.
     *
     * @param int        $salaId
     * @param array      $mensajes SoporteTiMensaje[]
     * @param int|null   $viewerId
     * @return array|null
     */
    protected function buildLecturasContextForMensajes($salaId, array $mensajes, $viewerId)
    {
        if (!$viewerId) {
            return null;
        }

        $ownIds = array();
        foreach ($mensajes as $m) {
            if ($m instanceof SoporteTiMensaje && $m->usuario_id && (int) $m->usuario_id === (int) $viewerId) {
                $ownIds[] = (int) $m->id;
            }
        }

        if (empty($ownIds)) {
            return null;
        }

        $miembrosIds = SoporteTiChatMiembro::where('sala_id', (int) $salaId)
            ->pluck('usuario_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $lecturasPorMensaje = array();
        $counts = array();
        $lecturasRows = SoporteTiMensajeLectura::whereIn('mensaje_id', $ownIds)
            ->where('usuario_id', '!=', (int) $viewerId)
            ->get(array('mensaje_id', 'usuario_id'));

        foreach ($lecturasRows as $row) {
            $mid = (int) $row->mensaje_id;
            $uid = (int) $row->usuario_id;
            if (!isset($lecturasPorMensaje[$mid])) {
                $lecturasPorMensaje[$mid] = array();
            }
            $lecturasPorMensaje[$mid][] = $uid;
        }

        foreach ($lecturasPorMensaje as $mid => $uids) {
            $counts[$mid] = count($uids);
        }

        return array(
            'viewer_id' => (int) $viewerId,
            'miembros_sala_ids' => $miembrosIds,
            'lecturas_usuario_ids_by_mensaje' => $lecturasPorMensaje,
            'lecturas_count_by_mensaje' => $counts,
        );
    }

    /**
     * @param array|null $lecturasCtx Resultado de buildLecturasContextForMensajes
     * @return array{lecturas_count: int, destinatarios_count: int, leido: bool}
     */
    protected function metaLecturasMensaje(SoporteTiMensaje $m, array $lecturasCtx)
    {
        $mid = (int) $m->id;
        $lecturasCount = isset($lecturasCtx['lecturas_count_by_mensaje'][$mid])
            ? (int) $lecturasCtx['lecturas_count_by_mensaje'][$mid]
            : 0;

        $dest = array();
        foreach ($lecturasCtx['miembros_sala_ids'] as $uid) {
            if ((int) $uid !== (int) $m->usuario_id) {
                $dest[] = (int) $uid;
            }
        }

        $destinatariosCount = count($dest);
        if (empty($dest)) {
            $leido = $lecturasCount > 0;
        } else {
            $leidos = isset($lecturasCtx['lecturas_usuario_ids_by_mensaje'][$mid])
                ? $lecturasCtx['lecturas_usuario_ids_by_mensaje'][$mid]
                : array();
            $leido = count(array_intersect($dest, $leidos)) === count($dest);
        }

        return array(
            'lecturas_count' => $lecturasCount,
            'destinatarios_count' => $destinatariosCount,
            'leido' => $leido,
        );
    }

    protected function inicialesDesdeNombre($nombre)
    {
        $palabras = preg_split('/\s+/', trim((string) $nombre), -1, PREG_SPLIT_NO_EMPTY);
        if (count($palabras) >= 2) {
            return strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 1));
        }
        $n = trim((string) $nombre);

        return strtoupper(substr($n, 0, 2));
    }

    /**
     * Convierte un instante almacenado (UTC) a hora de Perú para mostrar en UI.
     *
     * @param Carbon|string $dt
     * @return Carbon
     */
    protected function enZonaPeru($dt)
    {
        return Carbon::parse($dt)->setTimezone(self::TZ_PERU);
    }

    protected function formatearMarcaTiempoLectura(Carbon $dt)
    {
        $dt = $this->enZonaPeru($dt);

        return $dt->format('j/n/Y') . ' a la(s) ' . $dt->format('g:i a');
    }

    protected function syncMaqueta(SoporteTiSolicitud $solicitud, $data, ?Authenticatable $user = null)
    {
        if ($data === null) {
            SoporteTiMaqueta::where('solicitud_id', $solicitud->id)->delete();
            return;
        }
        if (!is_array($data)) {
            return;
        }
        SoporteTiMaqueta::updateOrCreate(
            array('solicitud_id' => $solicitud->id),
            array(
                'nombre' => isset($data['nombre']) ? $data['nombre'] : 'Maqueta',
                'tamano' => isset($data['tamano']) ? $data['tamano'] : null,
                'url_preview' => isset($data['url_preview']) ? $data['url_preview'] : null,
                'fecha_entrega' => isset($data['fecha_entrega']) ? $data['fecha_entrega'] : Carbon::now()->toDateString(),
                'aprobada' => !empty($data['aprobada']),
                'subida_por_user_id' => $this->authUserId($user),
            )
        );
    }

    protected function generarCodigo($tipo)
    {
        $pref = $tipo === 'A' ? 'PRJ' : 'REQ';
        do {
            $codigo = $pref . '-' . random_int(100, 999);
        } while (SoporteTiSolicitud::where('codigo', $codigo)->exists());

        return $codigo;
    }

    protected function nombreUsuario(?Authenticatable $user = null)
    {
        if (!$user) {
            return 'Usuario';
        }
        if ($user instanceof Usuario) {
            $n = trim((string) $user->No_Nombres_Apellidos);
            if ($n !== '') {
                return $n;
            }
            $e = trim((string) $user->Txt_Email);
            if ($e !== '') {
                return $e;
            }
        }
        if (!empty($user->name)) {
            $ln = !empty($user->lastname) ? ' ' . $user->lastname : '';
            return trim($user->name . $ln);
        }
        if (!empty($user->nombre)) {
            return $user->nombre;
        }
        return 'Usuario #' . $user->getKey();
    }

    /**
     * @param string|null $rolDemo PM|Solicitante|Analista
     * @return array
     */
    protected function metaRemitente(?Authenticatable $user = null, $rolDemo = null)
    {
        $colores = array(
            'Solicitante' => '#6d28d9',
            'PM' => '#1d4ed8',
            'Analista' => '#047857',
        );
        $nombre = $this->nombreUsuario($user);
        $partes = preg_split('/\s+/', trim($nombre));
        $iniciales = '';
        if (count($partes) >= 2) {
            $iniciales = strtoupper(substr($partes[0], 0, 1) . substr($partes[1], 0, 1));
        } elseif (strlen($nombre) >= 2) {
            $iniciales = strtoupper(substr($nombre, 0, 2));
        } else {
            $iniciales = 'US';
        }

        $color = '#64748b';
        if ($rolDemo && isset($colores[$rolDemo])) {
            $color = $colores[$rolDemo];
        }

        return array(
            'nombre' => $nombre,
            'iniciales' => $iniciales,
            'color' => $color,
        );
    }

    protected function formatearFechaCorta(Carbon $dt)
    {
        $dt = $this->enZonaPeru($dt);
        $meses = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');

        return $dt->format('j') . ' ' . $meses[(int) $dt->format('n') - 1];
    }

    protected function formatearMarcaTiempo(Carbon $dt)
    {
        $dt = $this->enZonaPeru($dt);
        $meses = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');

        return $dt->format('j') . ' ' . $meses[(int) $dt->format('n') - 1] . ' ' . $dt->format('H:i');
    }

    protected function maxOrdenEvidencia($solicitudId)
    {
        $m = SoporteTiSolicitudEvidencia::where('solicitud_id', (int) $solicitudId)->max('orden');

        return $m !== null ? (int) $m : -1;
    }

    protected function registrarEvidenciaTexto($solicitudId, $mensajeId, $texto, $orden)
    {
        SoporteTiSolicitudEvidencia::create(array(
            'solicitud_id' => (int) $solicitudId,
            'mensaje_id' => $mensajeId !== null ? (int) $mensajeId : null,
            'tipo' => 'texto',
            'texto' => $texto,
            'orden' => (int) $orden,
        ));
    }

    /**
     * @return SoporteTiMensaje
     */
    protected function crearMensajeUsuarioSimple(
        SoporteTiChatSala $sala,
        SoporteTiSolicitud $solicitud,
        $texto,
        $replyToId,
        ?Authenticatable $user,
        array $meta
    ) {
        $mensaje = SoporteTiMensaje::create(array(
            'sala_id' => $sala->id,
            'usuario_id' => $this->authUserId($user),
            'remitente' => $meta['nombre'],
            'iniciales' => $meta['iniciales'],
            'color' => $meta['color'],
            'texto' => $texto !== null ? $texto : '',
            'es_sistema' => false,
            'reply_to_id' => $replyToId,
        ));
        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $uid = $this->authUserId($user);
        if ($uid) {
            $this->agregarMiembroSala($sala->id, $uid, 'participante');
        }

        return $mensaje;
    }

    /**
     * Un mensaje con uno o más adjuntos en cola asíncrona (una burbuja).
     *
     * @param UploadedFile[] $files
     * @return array
     */
    protected function encolarMensajeChatVariosArchivos(
        SoporteTiChatSala $sala,
        SoporteTiSolicitud $solicitud,
        ?Authenticatable $user,
        array $meta,
        $texto,
        $replyToId,
        array $files,
        $ordenEvidencia
    ) {
        $mensaje = SoporteTiMensaje::create(array(
            'sala_id' => $sala->id,
            'usuario_id' => $this->authUserId($user),
            'remitente' => $meta['nombre'],
            'iniciales' => $meta['iniciales'],
            'color' => $meta['color'],
            'texto' => $texto !== null ? $texto : '',
            'es_sistema' => false,
            'reply_to_id' => $replyToId,
        ));

        $batch = (string) Str::uuid();
        $archivosPendientes = array();
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $rel = $file->store('soporte-ti/pending-chat/' . $batch, 'local');
            $mime = $file->getClientMimeType();
            $archivosPendientes[] = array(
                'local_path' => $rel,
                'nombre_original' => $file->getClientOriginalName(),
                'tamano_bytes' => $file->getSize(),
                'mime' => $mime ? $mime : null,
            );
        }

        if (count($archivosPendientes) === 0) {
            throw new \InvalidArgumentException('No se recibieron archivos válidos.');
        }

        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $mensaje->load(array('imagenes', 'replyTo'));
        $payload = $this->mapMensaje($mensaje, $user);
        $payload['adjunto_pendiente'] = true;

        ProcessSoporteTiChatAdjuntosJob::dispatch(
            (int) $solicitud->id,
            (int) $mensaje->id,
            $archivosPendientes,
            (int) $ordenEvidencia
        )->afterCommit()->afterResponse();

        return $payload;
    }

    /**
     * @param UploadedFile $file
     * @return array
     */
    protected function encolarMensajeChatUnArchivo(
        SoporteTiChatSala $sala,
        SoporteTiSolicitud $solicitud,
        ?Authenticatable $user,
        array $meta,
        $texto,
        $replyToId,
        UploadedFile $file,
        $ordenEvidencia
    ) {
        return $this->encolarMensajeChatVariosArchivos(
            $sala,
            $solicitud,
            $user,
            $meta,
            $texto,
            $replyToId,
            array($file),
            $ordenEvidencia
        );
    }

    public function mapEstado(SoporteTiEstado $e)
    {
        return array(
            'id' => (int) $e->id,
            'codigo' => $e->codigo,
            'nombre' => $e->nombre,
            'tipo_solicitud' => $e->tipo_solicitud,
            'orden_kanban' => $e->orden_kanban !== null ? (int) $e->orden_kanban : null,
        );
    }

    public function mapHistorial(SoporteTiSolicitudEstado $h)
    {
        $usuarioNombre = null;
        if ($h->usuario) {
            $usuarioNombre = $this->nombreUsuario($h->usuario);
        }

        return array(
            'id' => (int) $h->id,
            'solicitud_id' => (int) $h->solicitud_id,
            'estado_id' => (int) $h->estado_id,
            'estado_anterior_id' => $h->estado_anterior_id !== null ? (int) $h->estado_anterior_id : null,
            'usuario_id' => $h->usuario_id !== null ? (int) $h->usuario_id : null,
            'usuario_nombre' => $usuarioNombre,
            'comentario' => $h->comentario,
            'created_at' => $h->created_at ? $h->created_at->toIso8601String() : null,
            'estado' => $h->estado ? $this->mapEstado($h->estado) : null,
            'estado_anterior' => $h->estadoAnterior ? $this->mapEstado($h->estadoAnterior) : null,
        );
    }

    public function mapSolicitud(SoporteTiSolicitud $s, ?Authenticatable $viewer = null)
    {
        $viewer = $viewer ?: Auth::user();
        $estado = $s->estadoActual;
        $chatUuid = $s->salaChat ? $s->salaChat->chat_uuid : null;
        $ultima = $s->ultima_actualizacion ? Carbon::parse($s->ultima_actualizacion) : Carbon::parse($s->updated_at);
        $registro = Carbon::parse($s->created_at);

        $out = array(
            'id' => (int) $s->id,
            'chat_uuid' => $chatUuid,
            'codigo' => $s->codigo,
            'tipo_solicitud' => $s->tipo_solicitud,
            'subtipo_b' => $s->subtipo_b,
            'titulo' => $s->titulo,
            'area' => $s->area,
            'solicitante' => $s->solicitante,
            'solicitante_user_id' => $s->solicitante_user_id !== null ? (int) $s->solicitante_user_id : null,
            'pm' => $s->pm,
            'analista' => $s->analista,
            'criticidad' => $s->criticidad,
            'complejidad_pm' => $s->complejidad_pm,
            'complejidad_analista' => $s->complejidad_analista,
            'estado_id' => (int) $s->estado_actual_id,
            'fase_index' => (int) $s->fase_index,
            'progreso' => (int) $s->progreso,
            'sla_horas' => (int) $s->sla_horas,
            'horas_transcurridas' => round($this->segundosSlaTranscurridos($s) / 3600, 2),
            'fecha_registro' => $this->formatearMarcaTiempo($registro),
            'fecha_registro_iso' => $registro->toIso8601String(),
            'prioridad' => (int) (isset($s->prioridad) ? $s->prioridad : 2),
            'ultima_actualizacion' => $this->formatearMarcaTiempo($ultima),
            'fecha_fin_estimado' => $s->fecha_fin_estimado
                ? $this->formatearFechaCorta(Carbon::parse($s->fecha_fin_estimado))
                : null,
            'seccion_ruta' => $s->seccion_ruta,
            'descripcion' => $s->descripcion,
            'maqueta' => null,
        );

        if ($estado) {
            $out['estado'] = $this->mapEstado($estado);
            $out['estado_codigo'] = $estado->codigo;
        }

        if ($s->maqueta) {
            $mq = $s->maqueta;
            $out['maqueta'] = array(
                'nombre' => $mq->nombre,
                'tamano' => $mq->tamano,
                'fecha_entrega' => $mq->fecha_entrega
                    ? $this->formatearFechaCorta(Carbon::parse($mq->fecha_entrega))
                    : null,
                'aprobada' => (bool) $mq->aprobada,
                'url_preview' => $mq->url_preview,
            );
        }

        if ($s->relationLoaded('evidencias') && $s->evidencias) {
            $out['evidencias'] = $s->evidencias->map(function (SoporteTiSolicitudEvidencia $ev) {
                return array(
                    'id' => (int) $ev->id,
                    'tipo' => $ev->tipo,
                    'texto' => $ev->texto,
                    'url' => $ev->url,
                    'nombre' => $ev->nombre,
                    'tamano' => $ev->tamano,
                    'mime' => $ev->mime,
                    'orden' => (int) $ev->orden,
                );
            })->values()->all();
        }

        $out['gestion'] = $this->mapGestion($s, $viewer);

        return $out;
    }

    public function mapMensaje(SoporteTiMensaje $m, ?Authenticatable $viewer = null, $lecturasCtx = null)
    {
        $reply = null;
        if ($m->replyTo) {
            $origen = $m->replyTo;
            if (!$origen->relationLoaded('imagenes')) {
                $origen->load('imagenes');
            }
            $texto = $origen->texto ? $origen->texto : '';
            if (strlen($texto) > 80) {
                $texto = substr($texto, 0, 80) . '…';
            }
            $imagenUrl = null;
            if ($origen->imagenes && $origen->imagenes->count() > 0) {
                $imagenUrl = $origen->imagenes->first()->url;
            }
            $reply = array(
                'id' => (int) $origen->id,
                'remitente' => $origen->remitente,
                'texto' => $texto,
                'tiene_imagen' => $imagenUrl !== null,
                'imagen_url' => $imagenUrl,
            );
        }

        $imagenes = array();
        if ($m->relationLoaded('imagenes')) {
            foreach ($m->imagenes as $img) {
                $imagenes[] = array(
                    'url' => $img->url,
                    'nombre' => $img->nombre,
                    'tamano' => $img->tamano,
                );
            }
        }

        $esPropio = false;
        if ($viewer && $m->usuario_id && (int) $m->usuario_id === $this->authUserId($viewer)) {
            $esPropio = true;
        }

        $lecturasCount = 0;
        $destinatariosCount = 0;
        $leido = false;
        if ($esPropio && $m->usuario_id) {
            if (is_array($lecturasCtx)) {
                $metaLec = $this->metaLecturasMensaje($m, $lecturasCtx);
                $lecturasCount = $metaLec['lecturas_count'];
                $destinatariosCount = $metaLec['destinatarios_count'];
                $leido = $metaLec['leido'];
            } else {
                $lecturasCount = (int) SoporteTiMensajeLectura::where('mensaje_id', $m->id)
                    ->where('usuario_id', '!=', (int) $m->usuario_id)
                    ->count();
                $destinatariosCount = count($this->idsDestinatariosMensaje($m));
                $leido = $this->mensajeLeidoPorDestinatarios($m);
            }
        }

        return array(
            'id' => (int) $m->id,
            'usuario_id' => $m->usuario_id !== null ? (int) $m->usuario_id : null,
            'remitente' => $m->remitente,
            'iniciales' => $m->iniciales,
            'color' => $m->color,
            'texto' => $m->texto ? $m->texto : '',
            'es_sistema' => (bool) $m->es_sistema,
            'marca_tiempo' => $this->formatearMarcaTiempo(Carbon::parse($m->created_at)),
            'created_at_iso' => Carbon::parse($m->created_at)->toIso8601String(),
            'es_propio' => $esPropio,
            'leido' => $leido,
            'lecturas_count' => $lecturasCount,
            'destinatarios_count' => $destinatariosCount,
            'archivo_nombre' => $m->archivo_nombre,
            'reply_to_id' => $m->reply_to_id !== null ? (int) $m->reply_to_id : null,
            'reply_to' => $reply,
            'imagenes' => count($imagenes) ? $imagenes : null,
            'adjunto_pendiente' => false,
        );
    }
}
