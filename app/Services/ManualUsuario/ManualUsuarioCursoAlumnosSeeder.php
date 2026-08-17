<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;

/**
 * Siembra el artículo plantilla: Comercial → Pedidos de Curso → Alumnos.
 */
class ManualUsuarioCursoAlumnosSeeder
{
    const ROLE_SLUG = 'comercial';
    const ID_GRUPO = 1203;
    const MODULO_KEY = 'curso/alumnos';

    /**
     * @return array{page_id:int, created:bool}
     */
    public function seed()
    {
        $created = false;

        $page = ManualPagina::query()->firstOrNew([
            'role_slug' => self::ROLE_SLUG,
            'modulo_key' => self::MODULO_KEY,
        ]);

        if (!$page->exists) {
            $created = true;
        }

        $page->id_grupo = self::ID_GRUPO;
        $page->titulo = 'Alumnos — consultar y dar seguimiento a inscripciones';
        $page->descripcion = 'Comercial → Pedidos de Curso → Alumnos';
        $page->orden = $page->orden ?: 1;
        $page->publicado = true;
        $page->save();

        $this->wipeBlocks((int) $page->id);

        $root = $this->block($page->id, null, ManualBloque::TIPO_GRUPO, 'Alumnos', 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'articulo',
                'breadcrumb' => 'Inicio → Comercial → Pedidos de Curso → Alumnos',
                'tags' => [
                    'Rol: Comercial',
                    'Módulo: Pedidos de Curso',
                    'alumno',
                    'inscripción',
                    'estado de pago',
                ],
            ],
        ], '/curso?tab=alumnos');

        $orden = 1;
        $this->qa($page->id, $root->id, $orden++, '¿Qué es?',
            'La vista donde aparecen los clientes que se registraron a través del formulario público de inscripción a un curso (curso.probusiness.pe).');
        $this->qa($page->id, $root->id, $orden++, '¿Para qué sirve?',
            'Permite ver, buscar, filtrar y dar seguimiento a cada alumno inscrito, incluyendo su estado de pago y el envío de recordatorios.');
        $this->qa($page->id, $root->id, $orden++, '¿Quién lo utiliza?',
            'Rol Comercial. (si un usuario ve todos los alumnos o solo los asignados a él vía la columna “Usuario”: pendiente de definir.)');
        $this->qa($page->id, $root->id, $orden++, '¿Cuándo utilizarlo?',
            'Cuando un cliente completa el formulario de inscripción y aparece un nuevo registro, o cuando se necesita consultar o actualizar el estado de un alumno existente.');

        $this->block($page->id, $root->id, ManualBloque::TIPO_FLOW, 'Pasos — Consultar y filtrar alumnos', $orden++, [
            'subtitulo' => null,
            'snapshot' => [
                'steps' => [
                    ['title' => '', 'body' => 'Ingresa a Pedidos de Curso → Alumnos.'],
                    ['title' => '', 'body' => 'En la pestaña “Alumnos”, revisa la tabla; los registros más recientes aparecen primero.'],
                    ['title' => '', 'body' => 'Si buscas un alumno específico, usa “Buscar” e ingresa nombre, documento o correo.'],
                    ['title' => '', 'body' => 'Para acotar por fecha, campaña, estado de pago o tipo de curso, abre “Filtros” y elige de las listas desplegables.'],
                    ['title' => '', 'body' => 'Aplica los filtros para actualizar la tabla.'],
                ],
            ],
        ]);

        $this->block($page->id, $root->id, ManualBloque::TIPO_FLOW, 'Pasos — Dar seguimiento a un alumno', $orden++, [
            'subtitulo' => null,
            'snapshot' => [
                'steps' => [
                    ['title' => '', 'body' => 'Ubica al alumno en la tabla.'],
                    ['title' => '', 'body' => 'Usa “Ver” para revisar el detalle completo del registro.'],
                    ['title' => '', 'body' => 'Si el importe cambia, edítalo en la columna Importe y presiona “Guardar”.'],
                    ['title' => '', 'body' => 'Para recordar un pago pendiente, presiona “Mensaje” y confirma en la ventana emergente para enviarlo por WhatsApp.'],
                    ['title' => '', 'body' => 'Si el registro ya no es válido, presiona “Eliminar” y confirma en la ventana emergente.'],
                ],
            ],
        ]);

        $campos = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Campos que deben completarse', $orden++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'campos');
        $this->block($page->id, $campos->id, ManualBloque::TIPO_TABLA, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'doc',
                'columns' => ['Campo', 'Origen', 'Ejemplo'],
                'rows' => [
                    ['Fecha', 'Automático, al registrarse', '12-08-2026'],
                    ['Cliente (nombre, documento, teléfono, correo, ciudad)', 'Llenado por el alumno en el formulario público', 'Ana Torres Ramírez'],
                    ['Curso', 'Seleccionado por el alumno (En vivo / Virtual)', 'En vivo'],
                    ['Campaña', 'Asignado por el sistema o el equipo comercial', '23'],
                    ['Usuario', 'pendiente de definir su asignación exacta', '3'],
                    ['Importe', 'Editable por el usuario comercial', 'S/ 380.00'],
                    ['Estado', 'Pendiente / Adelanto / Pagado', 'Adelanto'],
                ],
            ],
        ]);

        $consideraciones = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Consideraciones importantes', $orden++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'consideraciones');
        $this->block($page->id, $consideraciones->id, ManualBloque::TIPO_TEXTO, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'body' => "Los datos del cliente provienen directamente del formulario público; no se validan manualmente antes de aparecer en la tabla.\n\nUn mismo cliente puede aparecer más de una vez si se inscribe a más de un curso, o si primero queda “pendiente” y luego “pagado” tras un nuevo registro. Antes de eliminar un registro que parece duplicado, verifica si corresponde a una inscripción distinta.",
            ],
        ]);

        $errores = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Errores frecuentes', $orden++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'errores');
        $this->block($page->id, $errores->id, ManualBloque::TIPO_TABLA, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'doc',
                'columns' => ['Situación', 'Causa probable', 'Solución'],
                'rows' => [
                    ['pendiente de definir', 'pendiente de definir', 'pendiente de definir'],
                ],
            ],
        ]);
        $this->block($page->id, $errores->id, ManualBloque::TIPO_CALLOUT, null, 2, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'warning',
                'title' => 'Completar con el equipo',
                'body' => 'El material no reporta errores conocidos de esta pantalla; se recomienda completar esta tabla junto con el equipo de soporte y los usuarios comerciales antes de publicar el manual.',
            ],
        ]);

        $this->qa($page->id, $root->id, $orden++, 'Ejemplo práctico (datos ficticios)',
            'Ana Torres Ramírez, DNI 00000000, WhatsApp 999 999 999, correo ana.torres@ejemplo.com, Lima. Inscrita al curso “En vivo”, campaña 23, estado “Adelanto”, importe S/ 380.00.');

        $this->block($page->id, $root->id, ManualBloque::TIPO_CALLOUT, null, $orden++, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'success',
                'title' => 'Resultado esperado:',
                'body' => 'el alumno queda visible en la tabla con su estado de pago actualizado y, si corresponde, recibe el recordatorio de pago por WhatsApp tras confirmar el envío.',
            ],
        ]);

        $this->qa($page->id, $root->id, $orden++, 'Ver también',
            'Pagos · Planes / Landing Web · Procedimiento “Enviar un recordatorio de pago”.');

        return [
            'page_id' => (int) $page->id,
            'created' => $created,
        ];
    }

    /**
     * @param  int  $paginaId
     */
    private function wipeBlocks($paginaId)
    {
        ManualBloque::query()->where('pagina_id', $paginaId)->update(['parent_id' => null]);
        ManualBloque::query()->where('pagina_id', $paginaId)->delete();
    }

    /**
     * @param  int         $paginaId
     * @param  int|null    $parentId
     * @param  string      $tipo
     * @param  string|null $titulo
     * @param  int         $orden
     * @param  array       $payload
     * @param  string|null $clave
     * @return ManualBloque
     */
    private function block($paginaId, $parentId, $tipo, $titulo, $orden, array $payload, $clave = null)
    {
        return ManualBloque::query()->create([
            'pagina_id' => $paginaId,
            'parent_id' => $parentId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'clave' => $clave,
            'payload' => $payload,
            'orden' => $orden,
        ]);
    }

    /**
     * @param  int         $paginaId
     * @param  int         $parentId
     * @param  int         $orden
     * @param  string|null $titulo
     * @param  string      $body
     * @return ManualBloque
     */
    private function qa($paginaId, $parentId, $orden, $titulo, $body)
    {
        return $this->block($paginaId, $parentId, ManualBloque::TIPO_TEXTO, $titulo, $orden, [
            'subtitulo' => null,
            'snapshot' => [
                'qa' => true,
                'body' => $body,
            ],
        ]);
    }
}
