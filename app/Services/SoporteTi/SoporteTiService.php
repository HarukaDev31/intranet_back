<?php

namespace App\Services\SoporteTi;

use App\Events\SoporteTi\SoporteTiEstadoActualizado;
use App\Events\SoporteTi\SoporteTiMensajeCreado;
use App\Models\SoporteTi\SoporteTiChatMiembro;
use App\Models\SoporteTi\SoporteTiChatSala;
use App\Models\SoporteTi\SoporteTiEstado;
use App\Models\SoporteTi\SoporteTiMaqueta;
use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiMensajeImagen;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\SoporteTi\SoporteTiSolicitudEstado;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SoporteTiService
{
    const CHAT_PAGE_SIZE = 25;

    /**
     * @return array
     */
    public function listarSolicitudes(array $filters = array())
    {
        $query = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta'))
            ->orderBy('id', 'desc');

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

        return $query->get()->map(function (SoporteTiSolicitud $s) {
            return $this->mapSolicitud($s);
        })->values()->all();
    }

    /**
     * @return array
     */
    public function obtenerSolicitud($id)
    {
        $s = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta'))
            ->findOrFail($id);

        return $this->mapSolicitud($s);
    }

    /**
     * @param array $data
     * @return array
     */
    public function crearSolicitud(array $data, Usuario $user = null)
    {
        $user = $user ?: Auth::user();
        $tipo = isset($data['tipo_solicitud']) ? $data['tipo_solicitud'] : 'B';
        $sla = $tipo === 'A' ? 72 : 8;
        $now = Carbon::now();

        return DB::transaction(function () use ($data, $user, $tipo, $sla, $now) {
            $solicitud = SoporteTiSolicitud::create(array(
                'codigo' => $this->generarCodigo($tipo),
                'tipo_solicitud' => $tipo,
                'subtipo_b' => $tipo === 'B' ? (isset($data['subtipo_b']) ? $data['subtipo_b'] : null) : null,
                'titulo' => isset($data['titulo']) ? $data['titulo'] : 'Nueva solicitud',
                'area' => isset($data['area']) ? $data['area'] : 'Ventas',
                'solicitante' => isset($data['solicitante']) ? $data['solicitante'] : $this->nombreUsuario($user),
                'solicitante_user_id' => $user ? $user->ID_Usuario : null,
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
                $this->agregarMiembroSala($sala->id, $user->ID_Usuario, 'solicitante');
            }

            $this->registrarHistorialEstado($solicitud, 1, null, $user, 'Solicitud creada');

            $this->crearMensajeSistema(
                $sala,
                $solicitud,
                'Ticket ' . $solicitud->codigo . ' creado. SLA: ' . $sla . 'h.'
            );

            $solicitud->load(array('estadoActual', 'salaChat', 'maqueta'));

            return $this->mapSolicitud($solicitud);
        });
    }

    /**
     * @param array $data
     * @return array
     */
    public function actualizarSolicitud($id, array $data, Usuario $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta'))
            ->findOrFail($id);

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
        if (array_key_exists('maqueta', $data)) {
            $this->syncMaqueta($solicitud, $data['maqueta'], $user);
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

        $solicitud->load(array('estadoActual', 'salaChat', 'maqueta'));

        return $this->mapSolicitud($solicitud);
    }

    /**
     * @return array
     */
    public function cambiarEstado($id, $estadoId, $comentario = null, Usuario $user = null)
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
    public function historialEstados($solicitudId)
    {
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
    public function mensajesPaginados($chatUuid, $limit = null, $beforeId = null, Usuario $user = null)
    {
        $user = $user ?: Auth::user();
        $limit = $limit ? (int) $limit : self::CHAT_PAGE_SIZE;
        if ($limit < 1) {
            $limit = self::CHAT_PAGE_SIZE;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $sala = SoporteTiChatSala::where('chat_uuid', $chatUuid)->firstOrFail();

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
     * @param string $texto
     * @param int|null $replyToId
     * @param UploadedFile[] $imagenes
     * @return array
     */
    public function enviarMensaje($solicitudId, $texto, $replyToId = null, array $imagenes = array(), Usuario $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = SoporteTiSolicitud::with('salaChat')->findOrFail($solicitudId);
        $sala = $solicitud->salaChat;
        if (!$sala) {
            throw new \RuntimeException('Sala de chat no encontrada');
        }

        $meta = $this->metaRemitente($user);

        return DB::transaction(function () use ($sala, $solicitud, $texto, $replyToId, $imagenes, $user, $meta) {
            $mensaje = SoporteTiMensaje::create(array(
                'sala_id' => $sala->id,
                'usuario_id' => $user ? $user->ID_Usuario : null,
                'remitente' => $meta['nombre'],
                'iniciales' => $meta['iniciales'],
                'color' => $meta['color'],
                'texto' => $texto,
                'es_sistema' => false,
                'reply_to_id' => $replyToId,
            ));

            $orden = 0;
            foreach ($imagenes as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $path = $file->store('soporte-ti/' . $solicitud->id . '/chat', 'public');
                $url = Storage::disk('public')->url($path);
                $tamano = $file->getSize() > 1048576
                    ? round($file->getSize() / 1048576, 1) . ' MB'
                    : round($file->getSize() / 1024) . ' KB';

                SoporteTiMensajeImagen::create(array(
                    'mensaje_id' => $mensaje->id,
                    'url' => $url,
                    'nombre' => $file->getClientOriginalName(),
                    'tamano' => $tamano,
                    'orden' => $orden++,
                ));
            }

            $solicitud->ultima_actualizacion = Carbon::now();
            $solicitud->save();

            $mensaje->load(array('imagenes', 'replyTo'));
            $payload = $this->mapMensaje($mensaje, $user);

            event(new SoporteTiMensajeCreado($solicitud, $payload));

            return $payload;
        });
    }

    /**
     * @param UploadedFile $archivo
     * @return array
     */
    public function subirMaqueta($solicitudId, UploadedFile $archivo, $mensajePm = null, Usuario $user = null)
    {
        $user = $user ?: Auth::user();
        $solicitud = SoporteTiSolicitud::with(array('salaChat', 'maqueta'))->findOrFail($solicitudId);
        $path = $archivo->store('soporte-ti/' . $solicitud->id . '/maqueta', 'public');
        $url = Storage::disk('public')->url($path);
        $tamano = $archivo->getSize() > 1048576
            ? round($archivo->getSize() / 1048576, 1) . ' MB'
            : round($archivo->getSize() / 1024) . ' KB';

        $maqueta = SoporteTiMaqueta::updateOrCreate(
            array('solicitud_id' => $solicitud->id),
            array(
                'nombre' => $archivo->getClientOriginalName(),
                'tamano' => $tamano,
                'ruta_archivo' => $path,
                'url_preview' => $url,
                'fecha_entrega' => Carbon::now()->toDateString(),
                'aprobada' => false,
                'subida_por_user_id' => $user ? $user->ID_Usuario : null,
            )
        );

        $solicitud->ultima_actualizacion = Carbon::now();
        $solicitud->save();

        $texto = $mensajePm ? $mensajePm : 'He subido la maqueta para revisión.';
        $meta = $this->metaRemitente($user, 'PM');

        if ($solicitud->salaChat) {
            $mensaje = SoporteTiMensaje::create(array(
                'sala_id' => $solicitud->salaChat->id,
                'usuario_id' => $user ? $user->ID_Usuario : null,
                'remitente' => $meta['nombre'],
                'iniciales' => $meta['iniciales'],
                'color' => $meta['color'],
                'texto' => $texto,
                'es_sistema' => false,
                'archivo_nombre' => $maqueta->nombre,
            ));

            SoporteTiMensajeImagen::create(array(
                'mensaje_id' => $mensaje->id,
                'url' => $url,
                'nombre' => $maqueta->nombre,
                'tamano' => $tamano,
                'orden' => 0,
            ));

            $mensaje->load(array('imagenes', 'replyTo'));
            event(new SoporteTiMensajeCreado($solicitud, $this->mapMensaje($mensaje, $user)));

            $this->crearMensajeSistema(
                $solicitud->salaChat,
                $solicitud,
                'Maqueta "' . $maqueta->nombre . '" subida. Pendiente de aprobación del solicitante.'
            );
        }

        $solicitud->load(array('estadoActual', 'salaChat', 'maqueta'));

        return $this->mapSolicitud($solicitud);
    }

    /**
     * @return SoporteTiSolicitudEstado
     */
    protected function registrarHistorialEstado(
        SoporteTiSolicitud $solicitud,
        $estadoId,
        $estadoAnteriorId,
        Usuario $user = null,
        $comentario = null
    ) {
        return SoporteTiSolicitudEstado::create(array(
            'solicitud_id' => $solicitud->id,
            'estado_id' => $estadoId,
            'estado_anterior_id' => $estadoAnteriorId,
            'usuario_id' => $user ? $user->ID_Usuario : null,
            'comentario' => $comentario,
            'created_at' => Carbon::now(),
        ));
    }

    protected function crearMensajeSistema(SoporteTiChatSala $sala, SoporteTiSolicitud $solicitud, $texto)
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

    protected function syncMaqueta(SoporteTiSolicitud $solicitud, $data, Usuario $user = null)
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
                'subida_por_user_id' => $user ? $user->ID_Usuario : null,
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

    protected function nombreUsuario(Usuario $user = null)
    {
        if (!$user) {
            return 'Usuario';
        }
        if (!empty($user->No_Nombres_Apellidos)) {
            return $user->No_Nombres_Apellidos;
        }
        if (!empty($user->No_Usuario)) {
            return $user->No_Usuario;
        }
        return 'Usuario #' . $user->ID_Usuario;
    }

    /**
     * @param string|null $rolDemo PM|Solicitante|Analista
     * @return array
     */
    protected function metaRemitente(Usuario $user = null, $rolDemo = null)
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

        return $out;
    }

    public function mapMensaje(SoporteTiMensaje $m, Usuario $viewer = null)
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
        if ($viewer && $m->usuario_id && (int) $m->usuario_id === (int) $viewer->ID_Usuario) {
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
