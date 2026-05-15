<?php

namespace App\Services\SoporteTi;

use App\Events\SoporteTi\SoporteTiEstadoActualizado;
use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Jobs\SoporteTi\ProcessSoporteTiChatAdjuntosJob;
use App\Jobs\SoporteTi\ProcessSoporteTiMaquetaUploadJob;
use App\Models\SoporteTi\SoporteTiChatMiembro;
use App\Models\SoporteTi\SoporteTiChatSala;
use App\Models\SoporteTi\SoporteTiEstado;
use App\Models\SoporteTi\SoporteTiMaqueta;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeImagen;
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

        $query = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'))
            ->orderBy('id', 'desc');

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
        $solicitudes = $rows->map(function (SoporteTiSolicitud $s) {
            return $this->mapSolicitud($s);
        })->values()->all();

        return array(
            'solicitudes' => $solicitudes,
            'resumen' => $resumen,
        );
    }

    /**
     * @return array
     */
    public function obtenerSolicitud($id, ?Authenticatable $authUser = null)
    {
        $s = $this->asegurarAccesoSolicitud(
            $id,
            $authUser,
            array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
        );

        return $this->mapSolicitud($s);
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
        $sla = $tipo === 'A' ? 72 : 8;
        $now = Carbon::now();

        return DB::transaction(function () use ($data, $user, $tipo, $sla, $now, $imagenesIniciales) {
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

            $this->crearMensajeSistema(
                $sala,
                $solicitud,
                'Ticket ' . $solicitud->codigo . ' creado. SLA: ' . $sla . 'h.'
            );

            $meta = $this->metaRemitente($user);
            $ordenEvid = $this->maxOrdenEvidencia($solicitud->id) + 1;

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
                event(new SoporteTiMensajeCreado($solicitud, $this->mapMensaje($msgCab, $user)));

                foreach ($filesInicial as $file) {
                    $this->encolarMensajeChatUnArchivo($sala, $solicitud, $user, $meta, '', null, $file, $ordenEvid);
                    ++$ordenEvid;
                }
            }

            $solicitud->load(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'));

            return $this->mapSolicitud($solicitud);
        });
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

    public function actualizarSolicitud($id, array $data, ?Authenticatable $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = $this->asegurarAccesoSolicitud(
            $id,
            $user,
            array('estadoActual', 'salaChat', 'maqueta', 'evidencias')
        );

        $estadoAnteriorId = $solicitud->estado_actual_id;
        $patch = array();

        if (isset($data['estado_id'])) {
            $patch['estado_actual_id'] = (int) $data['estado_id'];
        }
        if (isset($data['fase_index'])) {
            $patch['fase_index'] = (int) $data['fase_index'];
        }
        if (isset($data['progreso'])) {
            $patch['progreso'] = (int) $data['progreso'];
        }
        if (isset($data['criticidad'])) {
            $c = trim((string) $data['criticidad']);
            $validas = array('Baja', 'Media', 'Alta', 'Máxima');
            if (!in_array($c, $validas, true)) {
                throw new \InvalidArgumentException('Complejidad no válida. Use Baja, Media, Alta o Máxima.');
            }
            $patch['criticidad'] = $c;
        }
        if (array_key_exists('maqueta', $data)) {
            $this->syncMaqueta($solicitud, $data['maqueta'], $user);
        }

        if (isset($patch['estado_actual_id'])) {
            $nuevoEstado = SoporteTiEstado::find($patch['estado_actual_id']);
            if ($nuevoEstado && $nuevoEstado->codigo === 'en_progreso') {
                $crit = isset($patch['criticidad']) ? $patch['criticidad'] : $solicitud->criticidad;
                if ($this->criticidadSinDefinir($crit)) {
                    throw new \InvalidArgumentException('Defina la complejidad antes de pasar a En progreso.');
                }
                $prevCodigo = $solicitud->estadoActual ? $solicitud->estadoActual->codigo : null;
                if ($solicitud->tipo_solicitud === 'A' && $prevCodigo === 'en_maqueta') {
                    $m = $solicitud->maqueta;
                    if (!$m || !$m->aprobada) {
                        throw new \InvalidArgumentException('La maqueta debe estar aprobada antes de pasar a En progreso.');
                    }
                }
            }
        }

        $patch['ultima_actualizacion'] = Carbon::now();
        $solicitud->fill($patch);
        $solicitud->save();

        if (isset($patch['estado_actual_id']) && $patch['estado_actual_id'] !== $estadoAnteriorId) {
            $historial = $this->registrarHistorialEstado(
                $solicitud,
                $patch['estado_actual_id'],
                $estadoAnteriorId,
                $user,
                isset($data['comentario']) ? $data['comentario'] : null
            );
            $solicitud->load('estadoActual', 'salaChat');
            $estado = $solicitud->estadoActual;
            if ($solicitud->salaChat && $estado) {
                $this->crearMensajeSistema(
                    $solicitud->salaChat,
                    $solicitud,
                    'Estado actualizado a "' . $estado->nombre . '".'
                );
                event(new SoporteTiEstadoActualizado($solicitud, $historial));
            }
        }

        $solicitud->load(array('estadoActual', 'salaChat', 'maqueta', 'evidencias'));

        return $this->mapSolicitud($solicitud);
    }

    /**
     * @return array
     */
    public function cambiarEstado($id, $estadoId, $comentario = null, ?Authenticatable $user = null)
    {
        return $this->actualizarSolicitud($id, array(
            'estado_id' => $estadoId,
            'comentario' => $comentario,
        ), $user);
    }

    /**
     * @return array
     */
    public function listarEstados()
    {
        return SoporteTiEstado::where('activo', true)
            ->orderBy('orden_kanban')
            ->get()
            ->map(function (SoporteTiEstado $e) {
                return $this->mapEstado($e);
            })
            ->values()
            ->all();
    }

    /**
     * @return array
     */
    public function historialEstados($solicitudId, ?Authenticatable $authUser = null)
    {
        $this->asegurarAccesoSolicitud($solicitudId, $authUser, array());
        return SoporteTiSolicitudEstado::with(array('estado', 'estadoAnterior', 'usuario'))
            ->where('solicitud_id', $solicitudId)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function (SoporteTiSolicitudEstado $h) {
                return $this->mapHistorial($h);
            })
            ->values()
            ->all();
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

        $mensajes = $rows->reverse()->values()->map(function (SoporteTiMensaje $m) use ($user) {
            return $this->mapMensaje($m, $user);
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

    /**
     * Envía mensaje al chat. Texto y cada archivo generan burbuja separada; cada parte registra una fila en solicitud_evidencias.
     *
     * @param string $texto
     * @param int|null $replyToId
     * @param UploadedFile[] $imagenes
     * @return array Último mensaje mapeado (el de la última parte enviada)
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

        return DB::transaction(function () use ($sala, $solicitud, $texto, $replyToId, $files, $user, $meta) {
            $lastPayload = null;
            $ordenEvid = $this->maxOrdenEvidencia($solicitud->id) + 1;
            $replyUsar = $replyToId;

            if (trim($texto) !== '') {
                $msg = $this->crearMensajeUsuarioSimple($sala, $solicitud, $texto, $replyUsar, $user, $meta);
                $this->registrarEvidenciaTexto($solicitud->id, $msg->id, $texto, $ordenEvid);
                ++$ordenEvid;
                $msg->load(array('imagenes', 'replyTo'));
                $lastPayload = $this->mapMensaje($msg, $user);
                event(new SoporteTiMensajeCreado($solicitud, $lastPayload));
                $replyUsar = null;
            }

            foreach ($files as $file) {
                $lastPayload = $this->encolarMensajeChatUnArchivo(
                    $sala,
                    $solicitud,
                    $user,
                    $meta,
                    '',
                    $replyUsar,
                    $file,
                    $ordenEvid
                );
                ++$ordenEvid;
                $replyUsar = null;
            }

            if ($lastPayload === null) {
                throw new \InvalidArgumentException('Mensaje vacío: indique texto o al menos un archivo.');
            }

            return $lastPayload;
        });
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

        return DB::transaction(function () use ($archivo, $solicitud, $tamano, $mensajePm, $user) {
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

            return $this->mapSolicitud($solicitud);
        });
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

        return $mensaje;
    }

    protected function agregarMiembroSala($salaId, $usuarioId, $rol)
    {
        SoporteTiChatMiembro::firstOrCreate(
            array('sala_id' => $salaId, 'usuario_id' => $usuarioId),
            array('rol_en_ticket' => $rol, 'joined_at' => Carbon::now())
        );
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
        $meses = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
        return $dt->format('j') . ' ' . $meses[(int) $dt->format('n') - 1];
    }

    protected function formatearMarcaTiempo(Carbon $dt)
    {
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

        return $mensaje;
    }

    /**
     * Un mensaje de chat con un solo adjunto en cola asíncrona + registro imagen en evidencias (orden fijado).
     *
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
        $rel = $file->store('soporte-ti/pending-chat/' . $batch, 'local');
        $mime = $file->getClientMimeType();
        $archivosPendientes = array(array(
            'local_path' => $rel,
            'nombre_original' => $file->getClientOriginalName(),
            'tamano_bytes' => $file->getSize(),
            'mime' => $mime ? $mime : null,
        ));

        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $mensaje->load(array('imagenes', 'replyTo'));
        $payload = $this->mapMensaje($mensaje, $user);

        ProcessSoporteTiChatAdjuntosJob::dispatch(
            (int) $solicitud->id,
            (int) $mensaje->id,
            $archivosPendientes,
            (int) $ordenEvidencia
        )->afterCommit()->afterResponse();

        return $payload;
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

    public function mapSolicitud(SoporteTiSolicitud $s)
    {
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
            'estado_id' => (int) $s->estado_actual_id,
            'fase_index' => (int) $s->fase_index,
            'progreso' => (int) $s->progreso,
            'sla_horas' => (int) $s->sla_horas,
            'horas_transcurridas' => (float) $s->horas_transcurridas,
            'fecha_registro' => $this->formatearFechaCorta($registro),
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

        return $out;
    }

    public function mapMensaje(SoporteTiMensaje $m, ?Authenticatable $viewer = null)
    {
        $reply = null;
        if ($m->replyTo) {
            $origen = $m->replyTo;
            $texto = $origen->texto ? $origen->texto : '';
            if (strlen($texto) > 80) {
                $texto = substr($texto, 0, 80) . '…';
            }
            $reply = array(
                'id' => (int) $origen->id,
                'remitente' => $origen->remitente,
                'texto' => $texto,
                'tiene_imagen' => $origen->imagenes()->exists(),
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

        return array(
            'id' => (int) $m->id,
            'remitente' => $m->remitente,
            'iniciales' => $m->iniciales,
            'color' => $m->color,
            'texto' => $m->texto ? $m->texto : '',
            'es_sistema' => (bool) $m->es_sistema,
            'marca_tiempo' => $this->formatearMarcaTiempo(Carbon::parse($m->created_at)),
            'es_propio' => $esPropio,
            'archivo_nombre' => $m->archivo_nombre,
            'reply_to_id' => $m->reply_to_id !== null ? (int) $m->reply_to_id : null,
            'reply_to' => $reply,
            'imagenes' => count($imagenes) ? $imagenes : null,
        );
    }
}
