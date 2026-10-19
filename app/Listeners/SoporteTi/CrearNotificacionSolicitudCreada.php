<?php

namespace App\Listeners\SoporteTi;

use App\Events\SoporteTi\SoporteTiSolicitudCreada;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

/**
 * Al crear un ticket, deja una notificación en campana para Soporte y otra para PM.
 */
class CrearNotificacionSolicitudCreada
{
    public function handle(SoporteTiSolicitudCreada $event)
    {
        $solicitud = isset($event->payload['solicitud']) ? $event->payload['solicitud'] : null;
        if (!is_array($solicitud) || empty($solicitud['id'])) {
            return;
        }

        $creadoPor = isset($solicitud['solicitante_user_id']) ? (int) $solicitud['solicitante_user_id'] : 0;
        if ($creadoPor <= 0) {
            Log::warning('CrearNotificacionSolicitudCreada: ticket sin solicitante, no se crea notificación.', array(
                'solicitud_id' => $solicitud['id'],
            ));
            return;
        }

        try {
            $codigo = isset($solicitud['codigo']) && trim((string) $solicitud['codigo']) !== ''
                ? trim((string) $solicitud['codigo'])
                : ('#' . $solicitud['id']);
            $tituloTicket = isset($solicitud['titulo']) && trim((string) $solicitud['titulo']) !== ''
                ? trim((string) $solicitud['titulo'])
                : 'Nueva solicitud';
            $solicitante = isset($solicitud['solicitante']) && trim((string) $solicitud['solicitante']) !== ''
                ? trim((string) $solicitud['solicitante'])
                : 'Un usuario';
            $area = isset($solicitud['area']) ? trim((string) $solicitud['area']) : '';
            $tipo = (isset($solicitud['tipo_solicitud']) && $solicitud['tipo_solicitud'] === 'A')
                ? 'Proyecto'
                : 'Requerimiento';

            $titulo = 'Nuevo ticket ' . $codigo;
            $mensaje = $solicitante . ' registró el ticket ' . $codigo . ': ' . $tituloTicket;
            $descripcion = 'Tipo: ' . $tipo . ($area !== '' ? ' | Área: ' . $area : '');
            $roles = array(Usuario::ROL_SOPORTE, Usuario::ROL_PM);

            foreach ($roles as $rol) {
                Notificacion::create(array(
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'descripcion' => $descripcion,
                    'modulo' => Notificacion::MODULO_SOPORTE_TI,
                    'rol_destinatario' => $rol,
                    'navigate_to' => 'soporte-ti/' . (int) $solicitud['id'],
                    'tipo' => Notificacion::TIPO_INFO,
                    'icono' => 'mdi:ticket-confirmation-outline',
                    'prioridad' => Notificacion::PRIORIDAD_MEDIA,
                    'referencia_tipo' => 'soporte_ti_solicitud',
                    'referencia_id' => (int) $solicitud['id'],
                    'activa' => true,
                    'creado_por' => $creadoPor,
                    'configuracion_roles' => array(
                        $rol => array(
                            'titulo' => $titulo,
                            'mensaje' => $mensaje,
                            'descripcion' => $descripcion,
                        ),
                    ),
                ));
            }
        } catch (\Exception $e) {
            Log::error('CrearNotificacionSolicitudCreada: ' . $e->getMessage(), array(
                'solicitud_id' => $solicitud['id'],
            ));
        }
    }
}
