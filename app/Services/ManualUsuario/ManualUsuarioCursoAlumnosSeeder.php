<?php

namespace App\Services\ManualUsuario;

use App\Models\ManualUsuario\ManualBloque;
use App\Models\ManualUsuario\ManualPagina;

/**
 * Siembra el artículo plantilla: Comercial → Pedidos de Curso → Alumnos.
 */
class ManualUsuarioCursoAlumnosSeeder
{
    use ManualUsuarioFlowItems;

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
            'Ver inscripciones, cambiar curso o campaña, crear el usuario del aula, generar la constancia y enviar recordatorios de pago.');
        $this->qa($page->id, $root->id, $orden++, '¿Quién lo utiliza?',
            'Rol Comercial. Jefe Marketing solo consulta: ve la tabla y puede abrir Ver, pero no cambia listas ni importe.');
        $this->qa($page->id, $root->id, $orden++, '¿Cuándo utilizarlo?',
            'Cuando llega una inscripción nueva, hay que crear el aula, emitir constancia, corregir datos o recordar un pago.');

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Consultar y filtrar alumnos', [
            $this->itemFlujo(
                'Entrar al listado',
                'Entra a Pedidos de Curso y abre Alumnos. Ves la tabla; los más recientes primero. Buscar (nombre, documento o correo) y Filtros (fecha, campaña, estado de pago, tipo de curso) solo encuentran la fila: no cambian al alumno. Aplica los filtros para actualizar la tabla. Si no ves a alguien, limpia búsqueda o cambia el filtro.',
                'Recorta la pestaña Alumnos con buscador, filtros y las primeras filas. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Cambiar Curso, Campaña o Usuario', [
            $this->itemFlujo(
                'Curso y Campaña',
                'Ubica la fila. Curso y Campaña son listas (no texto fijo). En Curso elige Virtual o En vivo; se guarda al elegir, sin otro botón. En Campaña elige la campaña; también se guarda al elegir. Si la lista no se abre, tu vista es solo consulta: pide el cambio a quien sí edita Alumnos.',
                'Recorta las listas Curso y Campaña de una fila. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Usuario a Creado',
                'En Usuario elige Creado si estaba Pendiente. Se guarda al elegir y crea el usuario del aula: verás un aviso con Usuario y Password. Anótalos. Constancia no se elige aquí: sale sola cuando ya hay constancia generada.',
                'Recorta la lista Usuario en Creado y el aviso de Moodle. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Acciones de la fila', [
            $this->itemFlujo(
                'Importe y estado',
                'A la derecha: Ver (ojo), Eliminar, Guardar e ícono de mensaje. Si cambia el precio, escríbelo en Importe y pulsa Guardar (disquete). No es lista. Estado (Pendiente, Adelanto, Pagado, Sobrepago) está bloqueado: se calcula con Importe vs. lo pagado en Pagos. No lo elijas a mano.',
                'Recorta Importe, Guardar y el Estado bloqueado de una fila. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Mensaje o eliminar',
                'Mensaje envía un recordatorio de pago por WhatsApp: confirma en la ventana; no abre otra pantalla. Eliminar pide confirmación y no se deshace. Si no ves Guardar, Mensaje ni Eliminar, solo puedes Ver.',
                'Recorta Mensaje o la confirmación de Eliminar. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Ver la ficha del alumno', [
            $this->itemFlujo(
                'Abrir la ficha',
                'Pulsa Ver (ojo). Entras a la ficha del pedido: es otra pantalla, no un recuadro. Regresar vuelve al listado. A la izquierda: Información del alumno (nombre, DNI, correo, WhatsApp, nacimiento, sexo, red social, país y, si es Perú, departamento / provincia / distrito).',
                'Recorta la ficha con Información del alumno. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Aula o Crear usuario',
                'Si Usuario está en Creado, a la derecha aparece ACCESO AULA VIRTUAL (usuario y contraseña, solo lectura) y el botón de constancia. Si el pedido ya está pagado y el usuario aún no está Creado, aparece Crear usuario (misma acción que elegir Creado en la tabla). Si no ves el lápiz de editar, esta ficha es solo consulta.',
                'Recorta ACCESO AULA VIRTUAL o el botón Crear usuario. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Editar datos en la ficha', [
            $this->itemFlujo(
                'Lápiz y campos',
                'En la ficha, pulsa el lápiz junto a Información del alumno. Los campos pasan a editables. Nombre, DNI, correo y WhatsApp se escriben. Fecha de nacimiento se elige en el calendario. Sexo, red social, país, departamento, provincia y distrito son listas. Provincia se habilita al elegir departamento; distrito, al elegir provincia (solo si el país es Perú). Si no hay lápiz, no editas desde aquí.',
                'Recorta Información del alumno en modo edición (lápiz y listas). Datos ficticios.'
            ),
            $this->itemFlujo(
                'Guardar',
                'Pulsa Guardar. Si cancelas, vuelve a pulsar el lápiz para salir del modo edición sin guardar (pendiente de definir si descarta los cambios al instante).',
                'Recorta Guardar en la ficha en edición. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Crear usuario del aula virtual', [
            $this->itemFlujo(
                'Crear la cuenta',
                'Desde la tabla: en Usuario elige Creado. O desde la ficha: si ves Crear usuario, púlsalo. El sistema crea la cuenta. Aparece un aviso con Usuario y Password: anótalos o envíaselos al alumno. Si no aparece Crear usuario, el pedido aún no está pagado o el usuario ya está Creado.',
                'Recorta Crear usuario o el aviso con Usuario y Password. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Ver el aula y cambiar clave',
                'Vuelve a abrir Ver: verás ACCESO AULA VIRTUAL con esas credenciales (solo lectura). En esa caja puedes pulsar Enviar instrucciones para cambiar contraseña: confirma y se envía por WhatsApp.',
                'Recorta ACCESO AULA VIRTUAL y Enviar instrucciones. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Generar y enviar constancia', [
            $this->itemFlujo(
                'Generar o regenerar',
                'La constancia no se genera desde la tabla. Primero Usuario debe estar en Creado (si no, no aparece el botón). Pulsa Ver. En la ficha, pulsa Generar y Enviar Constancia. Confirma ¿Estás seguro de querer generar y enviar la constancia? Si ya existía, el botón dice Regenerar Constancia y Enviar.',
                'Recorta Generar y Enviar Constancia (o Regenerar) y la confirmación. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Vista previa',
                'Al terminar ves la vista previa (PDF o imagen). Puedes abrirla en otra pestaña o descargarla. En la tabla, Usuario puede mostrar Constancia (lista bloqueada) cuando ya hay archivo: no se elige a mano.',
                'Recorta la vista previa de la constancia. Datos ficticios.'
            ),
        ]);

        $campos = $this->block($page->id, $root->id, ManualBloque::TIPO_GRUPO, 'Campos que deben completarse', $orden++, [
            'subtitulo' => null,
            'snapshot' => ['colapsable' => true],
        ], 'campos');
        $this->block($page->id, $campos->id, ManualBloque::TIPO_TABLA, null, 1, [
            'subtitulo' => null,
            'snapshot' => [
                'variant' => 'doc',
                'columns' => ['Campo', 'Cómo se completa', 'Ejemplo'],
                'rows' => [
                    ['Fecha', 'Solo lectura. Sale automático al inscribirse.', '12-08-2026'],
                    ['Cliente (nombre, documento, teléfono, correo, ciudad)', 'Solo lectura. Viene del formulario público.', 'Ana Torres Ramírez'],
                    ['Curso', 'Lista desplegable en la tabla. Elige Virtual o En vivo; se guarda al elegir. Jefe Marketing la ve bloqueada.', 'En vivo'],
                    ['Campaña', 'Lista desplegable en la tabla. Elige la campaña; se guarda al elegir. Jefe Marketing la ve bloqueada.', 'Agosto en vivo'],
                    ['Usuario', 'Lista desplegable: Pendiente o Creado (se guarda al elegir y crea Moodle). Constancia es solo lectura cuando ya se generó.', 'Creado'],
                    ['Importe', 'Caja de texto en la tabla. Escribe y pulsa Guardar en Acciones.', 'S/ 380.00'],
                    ['Estado de pago', 'Lista bloqueada en la tabla. Sale de Importe vs. pagos.', 'Adelanto'],
                    ['Sexo / red social / país / ubigeo', 'Listas desplegables en la ficha, solo con el lápiz de editar. Luego Guardar.', 'Femenino · Lima'],
                    ['Constancia', 'Botón en la ficha si Usuario está Creado. No es un campo de la tabla.', 'PDF enviado'],
                    ['Filtros (fecha, campaña, estado, tipo de curso)', 'Listas desplegables encima de la tabla. Solo filtran; no cambian el alumno.', 'Todos'],
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
                'body' => "Ver (ojo) abre la ficha del pedido. Ahí se editan datos (lápiz), se crea el aula y se genera la constancia. Mensaje y Eliminar no salen de la tabla.\n\nLa constancia solo aparece si Usuario ya está Creado. Constancia en la lista de la tabla no se elige: se marca sola cuando ya hay archivo.\n\nJefe Marketing: solo Ver en la tabla; en la ficha no hay lápiz de editar.\n\nUn mismo cliente puede aparecer más de una vez si se inscribe a más de un curso.",
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
                    ['No aparece Generar constancia', 'Usuario no está en Creado', 'En la tabla pasa Usuario a Creado (o pulsa Crear usuario en la ficha) y vuelve a abrir Ver'],
                    ['No aparece Crear usuario en la ficha', 'El pedido aún no está pagado o el usuario ya está Creado', 'Cierra el pago en Pagos o usa la lista Usuario de la tabla'],
                    ['No puedo editar nombre o DNI', 'No pulsaste el lápiz, o tu rol es Jefe Marketing', 'Pulsa el lápiz en Información del alumno; si no está, pide a Comercial'],
                    ['Las listas de la tabla no se abren', 'Rol de consulta (Jefe Marketing)', 'Pide el cambio a Comercial'],
                    ['El estado de pago no cambia a mano', 'La lista está bloqueada a propósito', 'Registra o quita adelantos en la pestaña Pagos'],
                ],
            ],
        ]);
        $this->block($page->id, $errores->id, ManualBloque::TIPO_CALLOUT, null, 2, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'info',
                'title' => 'Si algo no cuadra',
                'body' => 'Si un botón no aparece (constancia, crear usuario, lápiz), revisa la tabla de errores frecuentes: casi siempre falta un paso anterior (Creado, pagado o permiso de rol).',
            ],
        ]);

        $this->qa($page->id, $root->id, $orden++, 'Ejemplo práctico (datos ficticios)',
            'Ana Torres Ramírez, DNI 00000000, WhatsApp 999 999 999. En la tabla, Comercial pasa Usuario a Creado y anota el usuario Moodle del aviso. Luego Ver → Generar y Enviar Constancia, confirma, y ve el PDF. Si el importe era S/ 380 y hay S/ 150 pagados, Estado sigue en Adelanto.');

        $this->block($page->id, $root->id, ManualBloque::TIPO_CALLOUT, null, $orden++, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'success',
                'title' => 'Resultado esperado:',
                'body' => 'el alumno queda en la tabla con curso y campaña correctos; si creaste el aula, hay usuario Moodle; si generaste constancia, ves la vista previa y el envío; el recordatorio de pago, si lo enviaste, llega por WhatsApp.',
            ],
        ]);

        $this->qa($page->id, $root->id, $orden++, 'Ver también',
            'Pagos · Campañas · Planes landing web.');

        return [
            'page_id' => (int) $page->id,
            'created' => $created,
        ];
    }

    /**
     * Un flow con una foto hija por cada paso.
     *
     * @param  int    $paginaId
     * @param  int    $parentId
     * @param  int    $orden
     * @param  string $titulo
     * @param  array  $steps
     */
    private function writeFlowWithCapturas($paginaId, $parentId, &$orden, $titulo, array $steps)
    {
        $snapshotSteps = [];
        foreach ($steps as $step) {
            $snapshotSteps[] = [
                'title' => isset($step['title']) ? $step['title'] : '',
                'body' => isset($step['body']) ? $step['body'] : '',
            ];
        }
        $flow = $this->block($paginaId, $parentId, ManualBloque::TIPO_FLOW, $titulo, $orden++, [
            'subtitulo' => null,
            'snapshot' => ['steps' => $snapshotSteps],
        ]);
        $m = 1;
        foreach ($steps as $i => $step) {
            $accion = isset($step['title']) ? trim((string) $step['title']) : '';
            if ($accion === '') {
                $accion = 'Paso ' . ($i + 1);
            }
            $caption = isset($step['captura']) && $step['captura'] !== ''
                ? $step['captura']
                : 'Recorta esa acción en pantalla. Datos ficticios, sin nombres reales de clientes.';
            $this->block($paginaId, $flow->id, ManualBloque::TIPO_MEDIA, 'Foto ' . $m . ' — ' . $accion, $m, [
                'subtitulo' => 'Subir: ' . $titulo . ' — ' . $m . '. ' . $accion,
                'snapshot' => [
                    'caption' => $caption,
                    'alt' => 'Foto ' . $m . ' — ' . $accion,
                    'media_id' => null,
                ],
            ], 'captura');
            $m++;
        }
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
