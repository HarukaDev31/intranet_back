<?php

namespace App\Services\SoporteTi;

use App\Models\SoporteTi\SoporteTiMensaje;
use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Models\SoporteTi\SoporteTiSolicitudEstado;
use App\Models\Usuario;
use App\Traits\FileTrait;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Exporta un ticket completo (datos, historial de estados y TODO el chat desde el primer mensaje) como texto plano,
 * con la ruta guardada y la URL de cada adjunto. Solo para el rol Soporte: sirve para depurar.
 */
class SoporteTiExportService
{
    use FileTrait;

    const TZ = 'America/Lima';

    const FORMATO = 'd/m/Y H:i:s';

    /**
     * @param int|string $solicitudId
     * @return array{codigo: string, titulo: string, total_mensajes: int, total_adjuntos: int, desde: string|null, hasta: string|null, generado_en: string, texto: string}
     */
    public function exportar($solicitudId, ?Authenticatable $user = null): array
    {
        $user = $user ?: Auth::user();
        $this->asegurarSoporte($user);

        $s = SoporteTiSolicitud::with(array('estadoActual', 'salaChat', 'maqueta'))->findOrFail((int) $solicitudId);
        $mensajes = $s->salaChat
            ? SoporteTiMensaje::with('imagenes')->where('sala_id', $s->salaChat->id)->orderBy('id')->get()
            : new Collection();
        $historial = SoporteTiSolicitudEstado::with(array('estado', 'estadoAnterior', 'usuario'))
            ->where('solicitud_id', $s->id)->orderBy('id')->get();

        $totalAdjuntos = (int) $mensajes->sum(function (SoporteTiMensaje $m) {
            return $m->imagenes->count();
        });
        $primero = $mensajes->first();
        $ultimo = $mensajes->last();
        $generado = $this->fecha(Carbon::now());

        $lineas = array_merge(
            $this->cabecera($s, $user, $generado, $mensajes->count(), $totalAdjuntos, $primero, $ultimo),
            $this->bloqueEstados($historial),
            $this->bloqueChat($mensajes)
        );

        return array(
            'codigo' => (string) $s->codigo,
            'titulo' => (string) $s->titulo,
            'total_mensajes' => $mensajes->count(),
            'total_adjuntos' => $totalAdjuntos,
            'desde' => $primero ? Carbon::parse($primero->created_at)->setTimezone(self::TZ)->toIso8601String() : null,
            'hasta' => $ultimo ? Carbon::parse($ultimo->created_at)->setTimezone(self::TZ)->toIso8601String() : null,
            'generado_en' => $generado,
            'texto' => implode("\n", $lineas),
        );
    }

    /**
     * @throws AuthorizationException
     */
    private function asegurarSoporte(?Authenticatable $user): void
    {
        $esSoporte = false;
        if ($user instanceof Usuario) {
            $user->loadMissing('grupo');
            $grupo = $user->grupo ? strtolower(trim((string) $user->grupo->No_Grupo)) : '';
            $esSoporte = $grupo === strtolower(Usuario::ROL_SOPORTE);
        }
        if (!$esSoporte) {
            throw new AuthorizationException('Solo el rol Soporte puede exportar el ticket.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function cabecera(SoporteTiSolicitud $s, ?Authenticatable $user, string $generado, int $mensajes, int $adjuntos, $primero, $ultimo): array
    {
        $estado = $s->estadoActual ? ($s->estadoActual->nombre ?: $s->estadoActual->codigo) : '-';
        $linea = str_repeat('=', 72);
        $out = array(
            $linea,
            'TICKET ' . $s->codigo . ' (id ' . $s->id . ') - ' . ($s->titulo ?: 'sin título'),
            $linea,
            'Tipo: ' . $s->tipo_solicitud . ($s->subtipo_b ? ' / ' . $s->subtipo_b : '') . ' | Estado: ' . $estado . ' | Prioridad: ' . $s->prioridad,
            'Área: ' . $s->area . ($s->seccion_ruta ? ' | Sección: ' . $s->seccion_ruta : ''),
            'Solicitante: ' . $s->solicitante . ' (user ' . $s->solicitante_user_id . ')',
            'PM: ' . ($s->pm ?: '-') . ' | Analista: ' . ($s->analista ?: '-'),
            'Creado: ' . $this->fecha($s->created_at) . ' | Última actualización: ' . $this->fecha($s->ultima_actualizacion),
            'Mensajes: ' . $mensajes . ' | Adjuntos: ' . $adjuntos
                . ' | Desde: ' . ($primero ? $this->fecha($primero->created_at) : '-')
                . ' | Hasta: ' . ($ultimo ? $this->fecha($ultimo->created_at) : '-'),
            'Exportado: ' . $generado . ' (' . self::TZ . ') por ' . ($user instanceof Usuario ? trim((string) $user->No_Nombres_Apellidos) : 'Soporte'),
        );
        if ($s->maqueta) {
            $out[] = 'Maqueta: ' . $s->maqueta->nombre . ' | ruta: ' . $s->maqueta->ruta_archivo . ' | aprobada: ' . ($s->maqueta->aprobada ? 'sí' : 'no');
        }
        if (trim((string) $s->descripcion) !== '') {
            $out[] = '';
            $out[] = 'DESCRIPCIÓN:';
            $out[] = trim((string) $s->descripcion);
        }

        return $out;
    }

    /**
     * @param Collection<int, SoporteTiSolicitudEstado> $historial
     * @return array<int, string>
     */
    private function bloqueEstados(Collection $historial): array
    {
        $out = array('', str_repeat('-', 72), 'HISTORIAL DE ESTADOS (' . $historial->count() . ')', str_repeat('-', 72));
        foreach ($historial as $h) {
            $de = $h->estadoAnterior ? ($h->estadoAnterior->nombre ?: $h->estadoAnterior->codigo) : '(inicio)';
            $a = $h->estado ? ($h->estado->nombre ?: $h->estado->codigo) : '?';
            $quien = $h->usuario ? trim((string) $h->usuario->No_Nombres_Apellidos) : ($h->usuario_id ? 'user ' . $h->usuario_id : 'sistema');
            $out[] = '[' . $this->fecha($h->created_at) . '] ' . $de . ' -> ' . $a . ' por ' . $quien
                . (trim((string) $h->comentario) !== '' ? ' | ' . trim((string) $h->comentario) : '');
        }

        return $out;
    }

    /**
     * @param Collection<int, SoporteTiMensaje> $mensajes
     * @return array<int, string>
     */
    private function bloqueChat(Collection $mensajes): array
    {
        $out = array('', str_repeat('-', 72), 'CHAT COMPLETO (' . $mensajes->count() . ' mensajes, del más antiguo al más reciente)', str_repeat('-', 72));
        foreach ($mensajes as $n => $m) {
            $etiquetas = array();
            if ($m->es_sistema) {
                $etiquetas[] = 'SISTEMA';
            }
            if ($m->es_maqueta) {
                $etiquetas[] = 'MAQUETA';
            }
            if ($m->revisado) {
                $etiquetas[] = 'REVISADO';
            }
            $out[] = '[' . $this->fecha($m->created_at) . '] #' . $m->id . ' (' . ($n + 1) . '/' . $mensajes->count() . ') '
                . ($m->remitente ?: 'Sistema') . ($m->usuario_id ? ' (user ' . $m->usuario_id . ')' : '')
                . ($etiquetas ? ' [' . implode(', ', $etiquetas) . ']' : '')
                . ($m->reply_to_id ? ' responde a #' . $m->reply_to_id : '');
            $texto = trim((string) $m->texto);
            if ($texto !== '') {
                foreach (preg_split('/\R/u', $texto) as $linea) {
                    $out[] = '    ' . $linea;
                }
            }
            if ($m->archivo_nombre && $m->imagenes->isEmpty()) {
                $out[] = '    Adjunto: ' . $m->archivo_nombre;
            }
            foreach ($m->imagenes as $img) {
                $out[] = '    Adjunto: ' . ($img->nombre ?: '(sin nombre)') . ($img->tamano ? ' (' . $img->tamano . ')' : '');
                $out[] = '      ruta: ' . $img->url;
                $out[] = '      url: ' . ($this->generateImageUrl($img->url) ?: '-');
            }
            $out[] = '';
        }

        return $out;
    }

    private function fecha($valor): string
    {
        return $valor ? Carbon::parse($valor)->setTimezone(self::TZ)->format(self::FORMATO) : '-';
    }
}
