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
            'Es la vista donde se registran los clientes que se inscriben a un curso mediante el formulario público de inscripción de Pro Business.');
        $this->qa($page->id, $root->id, $orden++, '¿Quién lo utiliza?',
            "1. Rol Comercial: puede consultar y gestionar la información de los alumnos según los permisos asignados.\n"
            . "2. Jefe de Marketing: puede consultar la información y abrir el detalle del alumno, pero no puede modificar las listas ni el importe.");
        $this->qa($page->id, $root->id, $orden++, '¿Para qué sirve?',
            "Desde esta sección puedes:\n"
            . "• Consultar las inscripciones.\n"
            . "• Buscar y filtrar alumnos.\n"
            . "• Cambiar el curso o la campaña asignada.\n"
            . "• Crear el usuario del aula virtual.\n"
            . "• Generar la constancia.\n"
            . "• Enviar recordatorios de pago.");

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Consultar y filtrar alumnos', [
            $this->itemFlujo(
                'Ingresar al listado',
                "1. Ingresa a Pedidos de Curso.\n"
                . "2. Selecciona Alumnos.\n"
                . "3. Se mostrará el listado de alumnos registrados.\n"
                . "4. Por defecto, los registros más recientes aparecen primero.",
                'Recorta la pestaña Alumnos con buscador, filtros y las primeras filas. Datos ficticios.',
                [
                    'capture_key' => 'curso-alumnos__consultar-y-filtrar-alumnos__paso-01-entrar-al-listado',
                ]
            ),
            $this->itemFlujo(
                'Buscar un alumno',
                "1. Escribe en el buscador cualquiera de estos datos:\n"
                . "• Nombre.\n"
                . "• Documento.\n"
                . "• Correo electrónico.\n"
                . "2. El listado se actualiza con los resultados encontrados.\n"
                . "3. Importante: la búsqueda solo permite localizar registros. No modifica la información del alumno.",
                '',
                ['sin_captura' => true]
            ),
            $this->itemFlujo(
                'Filtrar alumnos',
                "1. Utiliza los filtros disponibles:\n"
                . "• Fecha.\n"
                . "• Campaña.\n"
                . "• Estado de pago.\n"
                . "• Tipo de curso.\n"
                . "2. Selecciona el filtro que necesites y aplica los cambios para actualizar el listado.\n"
                . "3. Si no encuentras al alumno que buscas:\n"
                . "• Limpia el campo de búsqueda.\n"
                . "• Revisa los filtros aplicados.\n"
                . "• Cambia o elimina el filtro que pueda estar limitando los resultados.\n"
                . "• Vuelve a consultar el listado.",
                '',
                ['sin_captura' => true]
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Cambiar Curso, Campaña o Usuario', [
            $this->itemFlujo(
                'Curso y Campaña',
                "1. Ubica la fila del alumno.\n"
                . "2. Curso y Campaña son listas (no texto fijo).\n"
                . "3. En Curso elige Virtual o En vivo; se guarda al elegir, sin otro botón.\n"
                . "4. En Campaña elige la campaña; también se guarda al elegir.\n"
                . "5. Si la lista no se abre, tu vista es solo consulta: pide el cambio a quien sí edita Alumnos.",
                'Recorta las listas Curso y Campaña de una fila. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Usuario a Creado',
                "1. En Usuario elige Creado si estaba Pendiente.\n"
                . "2. Se guarda al elegir y crea el usuario del aula: verás un aviso con Usuario y Password.\n"
                . "3. Anótalos.\n"
                . "4. Constancia no se elige aquí: sale sola cuando ya hay constancia generada.",
                'Recorta la lista Usuario en Creado y el aviso de Moodle. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Acciones de la fila', [
            $this->itemFlujo(
                'Importe y estado',
                "1. A la derecha verás Ver (ojo), Eliminar, Guardar e ícono de mensaje.\n"
                . "2. Si cambia el precio, escríbelo en Importe y pulsa Guardar (disquete). No es lista.\n"
                . "3. Estado (Pendiente, Adelanto, Pagado, Sobrepago) está bloqueado: se calcula con Importe vs. lo pagado en Pagos.\n"
                . "4. No lo elijas a mano.",
                'Recorta Importe, Guardar y el Estado bloqueado de una fila. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Mensaje o eliminar',
                "1. Mensaje envía un recordatorio de pago por WhatsApp: confirma en la ventana; no abre otra pantalla.\n"
                . "2. Eliminar pide confirmación y no se deshace.\n"
                . "3. Si no ves Guardar, Mensaje ni Eliminar, solo puedes Ver.",
                'Recorta Mensaje o la confirmación de Eliminar. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Ver la ficha del alumno', [
            $this->itemFlujo(
                'Abrir la ficha',
                "1. Pulsa Ver (ojo).\n"
                . "2. Entras a la ficha del pedido: es otra pantalla, no un recuadro.\n"
                . "3. Regresar vuelve al listado.\n"
                . "4. A la izquierda: Información del alumno (nombre, DNI, correo, WhatsApp, nacimiento, sexo, red social, país y, si es Perú, departamento / provincia / distrito).",
                'Recorta la ficha con Información del alumno. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Aula o Crear usuario',
                "1. Si Usuario está en Creado, a la derecha aparece ACCESO AULA VIRTUAL (usuario y contraseña, solo lectura) y el botón de constancia.\n"
                . "2. Si el pedido ya está pagado y el usuario aún no está Creado, aparece Crear usuario (misma acción que elegir Creado en la tabla).\n"
                . "3. Si no ves el lápiz de editar, esta ficha es solo consulta.",
                'Recorta ACCESO AULA VIRTUAL o el botón Crear usuario. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Editar datos en la ficha', [
            $this->itemFlujo(
                'Lápiz y campos',
                "1. En la ficha, pulsa el lápiz junto a Información del alumno.\n"
                . "2. Los campos pasan a editables.\n"
                . "3. Nombre, DNI, correo y WhatsApp se escriben.\n"
                . "4. Fecha de nacimiento se elige en el calendario.\n"
                . "5. Sexo, red social, país, departamento, provincia y distrito son listas.\n"
                . "6. Provincia se habilita al elegir departamento; distrito, al elegir provincia (solo si el país es Perú).\n"
                . "7. Si no hay lápiz, no editas desde aquí.",
                'Recorta Información del alumno en modo edición (lápiz y listas). Datos ficticios.'
            ),
            $this->itemFlujo(
                'Guardar',
                "1. Pulsa Guardar y espera el aviso; la ficha vuelve a solo lectura.\n"
                . "2. No hay botón Cancelar.\n"
                . "3. Volver a pulsar el lápiz solo sale del modo edición: si no quieres conservar lo escrito, regresa al listado y abre la ficha otra vez sin pulsar Guardar.",
                'Recorta Guardar en la ficha en edición. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Crear usuario del aula virtual', [
            $this->itemFlujo(
                'Crear la cuenta',
                "1. Desde la tabla: en Usuario elige Creado.\n"
                . "2. O desde la ficha: si ves Crear usuario, púlsalo.\n"
                . "3. El sistema crea la cuenta.\n"
                . "4. Aparece un aviso con Usuario y Password: anótalos o envíaselos al alumno.\n"
                . "5. Si no aparece Crear usuario, el pedido aún no está pagado o el usuario ya está Creado.",
                'Recorta Crear usuario o el aviso con Usuario y Password. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Ver el aula y cambiar clave',
                "1. Vuelve a abrir Ver: verás ACCESO AULA VIRTUAL con esas credenciales (solo lectura).\n"
                . "2. En esa caja puedes pulsar Enviar instrucciones para cambiar contraseña.\n"
                . "3. Confirma y se envía por WhatsApp.",
                'Recorta ACCESO AULA VIRTUAL y Enviar instrucciones. Datos ficticios.'
            ),
        ]);

        $this->writeFlowWithCapturas($page->id, $root->id, $orden, 'Generar y enviar constancia', [
            $this->itemFlujo(
                'Generar o regenerar',
                "1. La constancia no se genera desde la tabla.\n"
                . "2. Primero Usuario debe estar en Creado (si no, no aparece el botón).\n"
                . "3. Pulsa Ver.\n"
                . "4. En la ficha, pulsa Generar y Enviar Constancia.\n"
                . "5. Confirma ¿Estás seguro de querer generar y enviar la constancia?\n"
                . "6. Si ya existía, el botón dice Regenerar Constancia y Enviar.",
                'Recorta Generar y Enviar Constancia (o Regenerar) y la confirmación. Datos ficticios.'
            ),
            $this->itemFlujo(
                'Vista previa',
                "1. Al terminar ves la vista previa (PDF o imagen).\n"
                . "2. Puedes abrirla en otra pestaña o descargarla.\n"
                . "3. En la tabla, Usuario puede mostrar Constancia (lista bloqueada) cuando ya hay archivo: no se elige a mano.",
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
                'body' => ManualUsuarioPlantillaTextFormatter::formatNumberedSteps(
                    "1. Ver (ojo) abre la ficha del pedido. Ahí se editan datos (lápiz), se crea el aula y se genera la constancia. Mensaje y Eliminar no salen de la tabla.\n"
                    . "2. La constancia solo aparece si Usuario ya está Creado. Constancia en la lista de la tabla no se elige: se marca sola cuando ya hay archivo.\n"
                    . "3. Jefe Marketing: solo Ver en la tabla; en la ficha no hay lápiz de editar.\n"
                    . "4. Un mismo cliente puede aparecer más de una vez si se inscribe a más de un curso."
                ),
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
            "1. Ana Torres Ramírez, DNI 00000000, WhatsApp 999 999 999.\n"
            . "2. En la tabla, Comercial pasa Usuario a Creado y anota el usuario Moodle del aviso.\n"
            . "3. Luego Ver → Generar y Enviar Constancia, confirma, y ve el PDF.\n"
            . "4. Si el importe era S/ 380 y hay S/ 150 pagados, Estado sigue en Adelanto.");

        $this->block($page->id, $root->id, ManualBloque::TIPO_CALLOUT, null, $orden++, [
            'subtitulo' => null,
            'snapshot' => [
                'tone' => 'success',
                'title' => 'Resultado esperado:',
                'body' => ManualUsuarioPlantillaTextFormatter::formatNumberedSteps(
                    "1. El alumno queda en la tabla con curso y campaña correctos.\n"
                    . "2. Si creaste el aula, hay usuario Moodle.\n"
                    . "3. Si generaste constancia, ves la vista previa y el envío.\n"
                    . "4. El recordatorio de pago, si lo enviaste, llega por WhatsApp."
                ),
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
            if (!empty($step['sin_captura'])) {
                continue;
            }
            $accion = isset($step['title']) ? trim((string) $step['title']) : '';
            if ($accion === '') {
                $accion = 'Paso ' . ($i + 1);
            }
            $caption = isset($step['captura']) && $step['captura'] !== ''
                ? $step['captura']
                : 'Recorta esa acción en pantalla. Datos ficticios, sin nombres reales de clientes.';
            $captureKey = ManualUsuarioCaptureKey::make(
                self::MODULO_KEY,
                self::ROLE_SLUG,
                (string) $titulo,
                $accion,
                $i + 1,
                isset($step['capture_key']) ? (string) $step['capture_key'] : null
            );
            $aliasOf = !empty($step['capture_alias_of']) ? (string) $step['capture_alias_of'] : null;
            $identity = ManualUsuarioCaptureKey::identity($captureKey, $aliasOf);
            $captureOutput = !empty($step['capture_output'])
                ? (string) $step['capture_output']
                : ManualUsuarioCaptureKey::output($identity ?: $captureKey);
            $snapshot = [
                'caption' => $caption,
                'alt' => 'Foto ' . $m . ' — ' . $accion,
                'media_id' => null,
                'capture_key' => $captureKey,
                'capture_role' => self::ROLE_SLUG,
                'capture_screen' => self::MODULO_KEY,
                'capture_screen_url' => '/curso?tab=alumnos',
                'capture_modulo' => self::MODULO_KEY,
                'capture_flow' => (string) $titulo,
                'capture_step' => ['number' => $i + 1, 'title' => $accion],
                'capture_hint' => $caption,
                'capture_output' => $captureOutput,
                'capture_config' => $this->captureConfig($step),
            ];
            if (!empty($step['capture_alias_of'])) {
                $snapshot['capture_alias_of'] = (string) $step['capture_alias_of'];
            }
            $snapshot['nombre'] = ManualUsuarioCapturaNombre::fromSnapshot($snapshot, 'Foto ' . $m . ' — ' . $accion, null);
            $this->block($paginaId, $flow->id, ManualBloque::TIPO_MEDIA, 'Foto ' . $m . ' — ' . $accion, $m, [
                'subtitulo' => 'Subir: ' . $titulo . ' — ' . $m . '. ' . $accion,
                'snapshot' => $snapshot,
            ], 'captura');
            $m++;
        }
    }

    private function captureConfig(array $step)
    {
        $config = [];
        foreach ([
            'type',
            'target',
            'actions',
            'expectedText',
            'padding',
            'masks',
            'piiAllow',
            'expectedHash',
            'enabled',
            'url',
            'sin_captura',
        ] as $field) {
            if (array_key_exists($field, $step)) {
                $config[$field] = $step[$field];
            }
        }

        return $config;
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
                'body' => ManualUsuarioPlantillaTextFormatter::formatQaBlock((string) $titulo, (string) $body),
            ],
        ]);
    }
}
