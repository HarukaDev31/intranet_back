<?php

namespace App\Listeners;

use App\Events\UsuarioDatosFacturacionImportFinished;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateContabilidadNotificationForUsuarioDatosFacturacionImportFinished implements ShouldQueue
{
    /** @var string */
    public $queue = 'notificaciones';

    public function handle(UsuarioDatosFacturacionImportFinished $event)
    {
        $isSuccess = strtoupper((string) $event->status) === 'COMPLETADO';

        $titulo = $isSuccess
            ? 'Importacion de datos de facturacion completada'
            : 'Importacion de datos de facturacion con errores';

        $message = $event->message;
        $stats = is_array($event->estadisticas) ? $event->estadisticas : [];
        $creados = isset($stats['creados']) ? (int) $stats['creados'] : 0;
        $errores = isset($stats['errores']) ? (int) $stats['errores'] : 0;
        $omitidos = isset($stats['omitidos']) ? (int) $stats['omitidos'] : 0;

        $descripcion = 'Archivo: ' . ($event->import->nombre_archivo ?? '-')
            . '. Creados: ' . $creados
            . ', Omitidos: ' . $omitidos
            . ', Errores: ' . $errores . '.';

        Notificacion::create([
            'titulo' => $titulo,
            'mensaje' => $message,
            'descripcion' => $descripcion,
            'modulo' => Notificacion::MODULO_BASE_DATOS,
            'rol_destinatario' => Usuario::ROL_CONTABILIDAD,
            'navigate_to' => '/datos-facturacion',
            'navigate_params' => [
                'idImport' => (int) $event->import->id,
            ],
            'tipo' => $isSuccess ? Notificacion::TIPO_SUCCESS : Notificacion::TIPO_ERROR,
            'icono' => 'i-heroicons-document-check',
            'prioridad' => $isSuccess ? Notificacion::PRIORIDAD_MEDIA : Notificacion::PRIORIDAD_ALTA,
            'referencia_tipo' => 'import_usuario_datos_facturacion',
            'referencia_id' => (int) $event->import->id,
            'activa' => true,
            'creado_por' => null,
            'configuracion_roles' => [
                Usuario::ROL_CONTABILIDAD => [
                    'titulo' => $titulo,
                    'mensaje' => $message,
                    'descripcion' => $descripcion,
                ],
            ],
        ]);
    }
}
