<?php

namespace App\Services\ManualUsuario;

/**
 * Pantallas del manual (plantilla) y qué roles las ven según el menú del sidebar.
 */
class ManualUsuarioScreensCatalog
{
    use ManualUsuarioFlowItems;
    public function roles()
    {
        return [
            ['slug' => 'comercial', 'id_grupo' => 1203, 'nombre' => 'Comercial', 'screens' => [
                'curso/pagos', 'curso/campanas', 'curso/planes-web', 'basedatos/clientes',
            ]],
            ['slug' => 'administracion', 'id_grupo' => 1207, 'nombre' => 'Administración', 'screens' => [
                'verificacion', 'cargaconsolidada/completados', 'basedatos/clientes', 'basedatos/permisos',
                'soporte-ti', 'news', 'viaticos/pendientes', 'viaticos/completados',
            ]],
            ['slug' => 'asistente-comercial', 'id_grupo' => 1209, 'nombre' => 'Asistente Comercial', 'screens' => [
                'news', 'viaticos', 'agente-compra',
            ]],
            ['slug' => 'cotizador', 'id_grupo' => 1210, 'nombre' => 'Cotizador', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'basedatos/clientes',
                'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'basedatos/boletin-quimico',
                'cotizaciones', 'soporte-ti', 'mi-progreso', 'copiloto',
            ]],
            ['slug' => 'almacen-china', 'id_grupo' => 1213, 'nombre' => 'Almacen China', 'screens' => [
                'news', 'agente-compra-trading',
            ]],
            ['slug' => 'coordinacion', 'id_grupo' => 1214, 'nombre' => 'Coordinación', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'basedatos/clientes',
                'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'basedatos/boletin-quimico',
                'soporte-ti', 'calendar', 'coordinacion/whatsapp-inbox', 'news', 'viaticos',
            ]],
            ['slug' => 'contenedor-almacen', 'id_grupo' => 1215, 'nombre' => 'ContenedorAlmacen', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados',
            ]],
            ['slug' => 'documentacion', 'id_grupo' => 1216, 'nombre' => 'Documentacion', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'basedatos/clientes',
                'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'basedatos/boletin-quimico',
                'soporte-ti', 'calendar', 'news', 'viaticos',
            ]],
            ['slug' => 'marketing', 'id_grupo' => 1220, 'nombre' => 'Marketing', 'screens' => [
                'calendar', 'landing/leads', 'viaticos',
            ]],
            ['slug' => 'jefe-importacion', 'id_grupo' => 1221, 'nombre' => 'Jefe Importacion', 'screens' => [
                'basedatos/clientes', 'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos',
                'soporte-ti', 'calendar', 'cargaconsolidada/coordinacion/abiertos', 'cargaconsolidada/coordinacion/completados',
                'cargaconsolidada/documentacion/abiertos', 'cargaconsolidada/documentacion/completados',
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'news', 'viaticos',
            ]],
            ['slug' => 'contabilidad', 'id_grupo' => 1222, 'nombre' => 'Contabilidad', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'basedatos/clientes',
                'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'soporte-ti',
                'verificacion', 'inspeccionados', 'datos-facturacion', 'news', 'viaticos',
            ]],
            ['slug' => 'jefe-marketing', 'id_grupo' => 1223, 'nombre' => 'Jefe Marketing', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'calendar',
                'curso/alumnos-consulta', 'curso/campanas', 'landing/leads', 'viaticos',
            ]],
            ['slug' => 'trafiquer', 'id_grupo' => 1224, 'nombre' => 'trafiquer', 'screens' => [
                'landing/leads',
            ]],
            ['slug' => 'subgerencia', 'id_grupo' => 1225, 'nombre' => 'SUBGERENCIA', 'screens' => [
                'panel-acceso/cargos', 'panel-acceso/usuarios', 'panel-acceso/permisos', 'landing/leads',
            ]],
            ['slug' => 'pm', 'id_grupo' => 1227, 'nombre' => 'PM', 'screens' => [
                'soporte-ti',
            ]],
            ['slug' => 'finanzas', 'id_grupo' => 1228, 'nombre' => 'Finanzas', 'screens' => [
                'cargaconsolidada/abiertos', 'cargaconsolidada/completados',
            ]],
        ];
    }

    public function screen($key)
    {
        $all = $this->all();

        return isset($all[$key]) ? $all[$key] : null;
    }

    public function all()
    {
        return array_merge(
            $this->cursoScreens(),
            $this->clientesLandingScreens(),
            $this->cargaScreens(),
            $this->aduanasScreens(),
            $this->opsScreens()
        );
    }

    private function cursoScreens()
    {
        return [
            'curso/alumnos-consulta' => [
                'modulo_key' => 'curso/alumnos',
                'titulo' => 'Alumnos — consultar inscripciones',
                'descripcion' => '{rol} → Pedidos de Curso → Alumnos',
                'articulo_titulo' => 'Alumnos',
                'articulo_clave' => '/curso?tab=alumnos',
                'tags' => ['Módulo: Pedidos de Curso', 'alumno', 'consulta'],
                'que_es' => 'La vista de inscripciones a cursos. En este rol la tabla es principalmente de consulta: puedes ver el detalle, no editar importe ni enviar mensajes.',
                'para_que' => 'Revisar quién se inscribió y en qué estado de pago está, sin alterar los registros.',
                'quien' => 'Rol {rol}. Comercial sí edita; aquí solo Ver.',
                'cuando' => 'Cuando necesitas mirar inscripciones o campañas sin operar pagos.',
                'flows' => [
                    [
                        'titulo' => 'Consultar el listado',
                        'steps' => [
                            $this->itemFlujo(
                                'Entrar y orientarte',
                                'Entra a Pedidos de Curso → Alumnos. Ves la tabla de inscripciones (los más recientes primero). Buscar y los filtros solo encuentran la fila: no cambian al alumno. Si no ves a alguien, cambia de filtro o borra el texto de búsqueda.',
                                'Recorta la pestaña Alumnos con buscador, filtros y las primeras filas. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Ver la ficha',
                                'Pulsa Ver (ojo). Entras a la ficha: ves datos del alumno. No hay lápiz para editar, ni Guardar, Mensaje o Eliminar en la tabla. Si un botón no aparece, es porque esta vista es solo consulta.',
                                'Recorta una fila con el ojo y la ficha abierta (solo lectura). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Aula y constancia (si ya está Creado)',
                                'Si el usuario ya está Creado, en la ficha puedes ver el aula y, si aparece, generar o regenerar la constancia. No cambies Curso, Campaña ni Usuario: están bloqueados. Pide el cambio a quien sí edita Alumnos.',
                                'Recorta la caja de aula virtual o el botón de constancia, si se ve. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Fecha / Cliente', 'Solo lectura. Vienen de la inscripción.', 'Ana Torres · En vivo · Adelanto'],
                    ['Curso / Campaña / Usuario / Estado', 'Listas desplegables en la tabla, bloqueadas en este rol. Se ven, no se cambian.', 'En vivo · Adelanto'],
                    ['Filtros', 'Listas desplegables encima de la tabla. Solo acotan el listado.', 'Todos'],
                ],
                'consideraciones' => 'No ves la pestaña Pagos. Las listas de Curso, Campaña y Usuario están bloqueadas: pide el cambio a Comercial.',
                'errores' => [
                    ['Quiero cambiar el importe', 'El rol no edita Alumnos', 'Pide a Comercial que actualice el pedido'],
                ],
                'ejemplo' => 'Abres a Ana Torres Ramírez y solo consultas su ficha; el estado Adelanto lo ves, no lo cambias a mano.',
                'resultado' => 'consultas la inscripción sin modificar pagos ni datos.',
                'ver_tambien' => 'Campañas · Landing.',
            ],
            'curso/pagos' => [
                'modulo_key' => 'curso/pagos',
                'titulo' => 'Pedidos de Curso — Pagos',
                'descripcion' => '{rol} → Pedidos de Curso → Pagos',
                'articulo_titulo' => 'Pagos',
                'articulo_clave' => '/curso?tab=pagos',
                'tags' => ['Módulo: Pedidos de Curso', 'pago', 'adelanto', 'curso'],
                'que_es' => 'La pestaña Pagos de Pedidos de Curso. Lista cada inscripción con el precio, lo ya pagado y la grilla de adelantos.',
                'para_que' => 'Registrar o quitar pagos parciales (adelantos) y ver de un vistazo si el alumno cubrió el precio del curso.',
                'quien' => 'Rol {rol}. Jefe Marketing no ve esta pestaña; Comercial sí puede registrar y eliminar pagos.',
                'cuando' => 'Cuando el alumno deposita un adelanto o el saldo, o cuando hay que corregir un pago mal registrado.',
                'flows' => [
                    [
                        'titulo' => 'Consultar pagos',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir la pestaña',
                                'Entra a Pedidos de Curso y abre Pagos. Ves Contacto, Precio, Pagado y la grilla Adelanto. El estado de Alumnos es otra pantalla: aquí no hay lista de estado. Buscar encuentra por nombre, documento o teléfono; no registra el pago.',
                                'Recorta la pestaña Pagos con Precio, Pagado y la grilla Adelanto. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Registrar un adelanto',
                        'steps' => [
                            $this->itemFlujo(
                                'Completar la grilla',
                                'Ubica al alumno. En Adelanto completa el recuadro vacío (monto y datos del pago, con sustento si lo pide). Guarda y espera “Pago registrado”. Pagado se actualiza. Si falta un dato, no queda grabado.',
                                'Recorta la grilla Adelanto al registrar un monto. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Qué pasa con el estado',
                                'Si el total pagado iguala el Precio, en Alumnos pasa a Pagado; si es menor, Adelanto; si se pasa, Sobrepago. No elijas ese estado a mano: se calcula solo.',
                                'Recorta Pagado actualizado junto al Precio. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Eliminar un pago',
                        'steps' => [
                            $this->itemFlujo(
                                'Quitar un adelanto',
                                'En la grilla Adelanto elige el pago a quitar. Confirma: no se deshace. La fila se actualiza con el nuevo total. No elimines para “corregir” un monto: registra el pago correcto después.',
                                'Recorta la confirmación al eliminar un adelanto. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Fecha', 'Solo lectura. Sale al inscribirse.', '12-08-2026'],
                    ['Contacto (nombre, documento, WhatsApp)', 'Solo lectura. Viene del formulario público.', 'Ana Torres Ramírez'],
                    ['Precio', 'Solo lectura en esta pestaña. Es el importe del pedido.', 'S/ 380.00'],
                    ['Pagado', 'Solo lectura. Suma de adelantos registrados.', 'S/ 150.00'],
                    ['Adelanto (grilla)', 'Cajas de la grilla (no es lista desplegable). Completa monto y datos y guarda.', 'S/ 150.00'],
                ],
                'consideraciones' => "El estado de pago de la pestaña Alumnos no se elige a mano: se calcula comparando Precio vs. total pagado.\n\nDesde esta pestaña también puedes ir a Planes landing web (botón superior).",
                'errores' => [
                    ['No aparece la pestaña Pagos', 'El rol no la tiene (p. ej. Jefe Marketing)', 'Usa Alumnos o pide el acceso a Comercial'],
                    ['El pago no se guarda', 'Faltan datos del recuadro o error al subir el sustento', 'Completa el recuadro y reintenta; si persiste, avisa a soporte'],
                ],
                'ejemplo' => 'Ana Torres Ramírez paga S/ 150.00 de un curso de S/ 380.00. Tras guardar el adelanto, Pagado muestra S/ 150.00 y en Alumnos el estado queda “Adelanto”.',
                'resultado' => 'el adelanto queda en la grilla, Pagado se actualiza y el estado del alumno refleja pendiente, adelanto, pagado o sobrepago.',
                'ver_tambien' => 'Alumnos · Planes / Landing Web · Campañas.',
            ],
            'curso/campanas' => [
                'modulo_key' => 'curso/campanas',
                'titulo' => 'Pedidos de Curso — Campañas',
                'descripcion' => '{rol} → Pedidos de Curso → Campañas',
                'articulo_titulo' => 'Campañas',
                'articulo_clave' => '/curso/campanas',
                'tags' => ['Módulo: Pedidos de Curso', 'campaña', 'curso'],
                'que_es' => 'Listado de campañas de cursos (fechas, nombre y cantidad de personas). Desde Alumnos se abre con “Ver Campañas”.',
                'para_que' => 'Crear, revisar o eliminar el periodo comercial al que se asocian las inscripciones.',
                'quien' => 'Rol {rol}. Jefe Marketing puede ver pero no crea campañas (el botón “Crear Campaña” se oculta).',
                'cuando' => 'Al abrir un nuevo ciclo de curso o cuando un alumno debe quedar asociado a una campaña concreta.',
                'flows' => [
                    [
                        'titulo' => 'Consultar campañas',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir el listado',
                                'Desde Pedidos de Curso pulsa Ver Campañas, o entra a Campañas. Ves ID, fechas, nombre y cantidad de personas. Buscar solo encuentra; no crea la campaña.',
                                'Recorta la tabla de campañas (ID, fechas, nombre, personas). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Ver inscritos',
                                'Pulsa Ver (ojo). Entras a Estudiantes de la Campaña: es otra pantalla, no un recuadro. Regresar vuelve a Campañas. El ojo de un alumno abre la misma ficha que en Alumnos. Si ves Exportar, baja esa lista; si no, tu vista es solo consulta.',
                                'Recorta Estudiantes de la Campaña con el ojo de un alumno. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear una campaña',
                        'steps' => [
                            $this->itemFlujo(
                                'Alta',
                                'Pulsa Crear Campaña. Completa nombre y fechas: el periodo máximo del asistente es de 6 días. Guarda y espera el aviso. Si no ves el botón, esta vista no crea: solo consulta.',
                                'Recorta el recuadro Crear Campaña (nombre y fechas). Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Eliminar una campaña',
                        'steps' => [
                            $this->itemFlujo(
                                'Papelera',
                                'Pulsa la papelera de la fila y confirma. No se deshace. Antes de borrar, revisa si ya hay inscritos: pendiente de definir qué pasa con esos pedidos.',
                                'Recorta la confirmación al eliminar una campaña. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['ID', 'Automático', '23'],
                    ['Fecha de creación', 'Automático', '01-08-2026'],
                    ['Nombre de campaña', 'Lo escribe el usuario al crear', 'Agosto en vivo'],
                    ['Fecha de inicio / fin', 'Lo elige el usuario (máx. 6 días en el alta)', '10-08-2026 / 15-08-2026'],
                    ['Cantidad de personas', 'Calculado según inscripciones', '12 personas'],
                ],
                'consideraciones' => "En Alumnos, la columna Campaña es una lista desplegable: elige la campaña y se guarda al instante (Jefe Marketing la ve bloqueada).\n\nAntes de eliminar, verifica que no deje pedidos huérfanos: pendiente de definir el comportamiento exacto si la campaña ya tiene alumnos.",
                'errores' => [
                    ['No aparece “Crear Campaña”', 'El rol es Jefe Marketing', 'Pide a Comercial que cree la campaña'],
                    ['No se crea la campaña', 'Datos incompletos o error de servidor', 'Revisa fechas y nombre; reintenta'],
                ],
                'ejemplo' => 'Campaña “Agosto en vivo”, del 10-08-2026 al 15-08-2026. Luego, en Alumnos, se asigna esa campaña a las inscripciones de esa semana.',
                'resultado' => 'la campaña aparece en la tabla y puede elegirse en la columna Campaña de Alumnos.',
                'ver_tambien' => 'Alumnos · Pagos.',
            ],
            'curso/planes-web' => [
                'modulo_key' => 'curso/planes-web',
                'titulo' => 'Pedidos de Curso — Planes landing web',
                'descripcion' => '{rol} → Pedidos de Curso → Planes landing web',
                'articulo_titulo' => 'Planes landing web',
                'articulo_clave' => '/curso/planes-web',
                'tags' => ['Módulo: Pedidos de Curso', 'web', 'precios', 'landing'],
                'que_es' => 'Pantalla donde se definen los precios y textos de las tarjetas de plan que ve el visitante en la web del curso.',
                'para_que' => 'Publicar o actualizar lo que muestra curso.probusiness.pe (título, precio, precio tachado y beneficios) sin tocar código.',
                'quien' => 'Rol {rol}.',
                'cuando' => 'Cuando cambia el precio, el nombre del plan o los beneficios que deben verse en la landing.',
                'flows' => [
                    [
                        'titulo' => 'Consultar planes',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir Planes landing web',
                                'En Pedidos de Curso pulsa Planes landing web. Ves la tabla de planes vigentes. Lo que está aquí es lo que puede ver el público en la web: revisa montos antes de tocar nada.',
                                'Recorta la tabla de planes (título, monto, precio tachado). Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear o editar un plan',
                        'steps' => [
                            $this->itemFlujo(
                                'Completar y guardar',
                                'Pulsa Nuevo plan o abre uno existente. Completa orden, título, subtítulo, monto en soles y beneficios (un beneficio por línea). Si hay oferta, marca Precio tachado y escribe el precio de lista. Pulsa Guardar. Sin Guardar, la web no cambia. No uses datos de clientes reales en los beneficios.',
                                'Recorta el formulario del plan (título, montos, beneficios). Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Orden', 'Usuario', '1'],
                    ['Título', 'Usuario', 'Plan Pro Business'],
                    ['Subtítulo', 'Usuario', 'Clase en Vivo (4 días en Zoom)'],
                    ['Monto principal (PEN)', 'Usuario', '200'],
                    ['Precio tachado / precio de lista', 'Opcional, si hay descuento', '550'],
                    ['Beneficios', 'Usuario, un ítem por línea', 'Clases grabadas'],
                ],
                'consideraciones' => 'Lo que se guarda aquí es lo que ve el público en la landing. Revisa textos y montos antes de confirmar; no uses datos de clientes reales en los ejemplos de beneficios.',
                'errores' => [
                    ['No aparece el plan en la web', 'pendiente de definir el tiempo de publicación', 'Pulsa Recargar y verifica que el plan esté guardado; si no se ve, avisa a soporte'],
                ],
                'ejemplo' => 'Plan “Pro Business”, subtítulo “Clase en Vivo (4 días en Zoom)”, S/ 200, precio de lista tachado S/ 550, beneficios: clases grabadas, material y certificado.',
                'resultado' => 'el plan queda en la tabla y la landing puede mostrar el nuevo precio y textos.',
                'ver_tambien' => 'Alumnos · Pagos.',
            ],
        ];
    }

    private function clientesLandingScreens()
    {
        return [
            'basedatos/clientes' => [
                'modulo_key' => 'basedatos/clientes',
                'titulo' => 'Clientes — consultar la base',
                'descripcion' => '{rol} → Clientes',
                'articulo_titulo' => 'Clientes',
                'articulo_clave' => '/basedatos/clientes',
                'tags' => ['Módulo: Clientes', 'contacto', 'servicio'],
                'que_es' => 'La base de datos de clientes de la intranet: tabla con contacto, origen, servicio y categoría.',
                'para_que' => 'Buscar a una persona o empresa, filtrar por fechas o tipo de servicio (Curso / Consolidado) y abrir su ficha.',
                'quien' => 'Rol {rol}. “Cargar Cliente” e “Importar Facturación” solo los ve Administración. “Exportar Excel” adicional aparece para el usuario jefe de ventas.',
                'cuando' => 'Cuando necesitas ubicar un cliente, ver su historial o, si eres Administración, cargar o importar datos.',
                'flows' => [
                    [
                        'titulo' => 'Buscar y filtrar',
                        'steps' => [
                            $this->itemFlujo(
                                'Encontrar a alguien',
                                'Entra a Clientes. Buscar por nombre, documento o contacto. En Filtros: fechas y Servicio (Todos / Curso / Consolidado). Aplica para actualizar. Los filtros no cambian al cliente: solo recortan la lista. Si recargas desde fuera, se limpian.',
                                'Recorta buscador, Filtros (Servicio) y las primeras filas. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Ver la ficha del cliente',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir detalles',
                                'Pulsa el ojo. Entras a Detalles del Cliente (contacto, empresa, historial). Regresar vuelve al listado. En esta ficha no se editan los datos de contacto: pendiente de definir si hay otro botón de edición.',
                                'Recorta la ficha Detalles del Cliente. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Portal y contraseña',
                                'Si el cliente tiene portal o su primer servicio es Curso, puedes enviar el mensaje de recuperación de contraseña (WhatsApp) o copiar el texto. Si tiene usuario, abajo asignas una nueva contraseña (mín. 8 caracteres, confirmar y guardar). En el historial, el ojo de un servicio abre la documentación de ese servicio.',
                                'Recorta el recuadro de contraseña o el envío de recuperación, si se ve. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Fecha', 'Automático / registro del cliente', '12-08-2026'],
                    ['Contacto', 'Datos del cliente', 'Luis Pérez · 999 888 777'],
                    ['Provincia', 'Ficha del cliente', 'Lima'],
                    ['Origen', 'Cómo llegó el cliente', 'pendiente de definir'],
                    ['Servicio', 'Filtro: lista desplegable encima de la tabla (Todos / Curso / Consolidado). No cambia al cliente.', 'Consolidado'],
                    ['Categoría', 'Solo lectura en el listado. Valores: Cliente, Recurrente, Premium, Inactivo.', 'Recurrente'],
                ],
                'consideraciones' => "Exportar y cargar clientes no están para todos los roles: Administración ve Cargar Cliente e Importar Facturación; el resto consulta y abre la ficha.\n\nSi recargas la página desde fuera, los filtros se limpian.",
                'errores' => [
                    ['No encuentro al cliente', 'Filtro de servicio o fechas demasiado estrecho', 'Pon Servicio en Todos y borra fechas'],
                    ['No veo Cargar Cliente', 'Tu rol no es Administración', 'Pide a Administración que cargue el archivo'],
                ],
                'ejemplo' => 'Luis Pérez, WhatsApp 999 888 777, servicio Consolidado, categoría Recurrente. Al pulsar Ver se abre su ficha.',
                'resultado' => 'la tabla muestra los clientes que coinciden y puedes abrir la ficha con Ver.',
                'ver_tambien' => 'Pedidos de Curso (si el servicio es Curso) · Carga consolidada (si es Consolidado).',
            ],
            'landing/leads' => [
                'modulo_key' => 'landing/leads',
                'titulo' => 'Landing — leads captados',
                'descripcion' => '{rol} → Landing',
                'articulo_titulo' => 'Landing',
                'articulo_clave' => '/landing/leads',
                'tags' => ['Módulo: Landing', 'leads', 'web'],
                'que_es' => 'Bandeja de personas que llenaron las landings privadas: una pestaña para consolidado y otra para curso.',
                'para_que' => 'Consultar y exportar los leads (nombre, WhatsApp, correo) captados en la web.',
                'quien' => 'Rol {rol}.',
                'cuando' => 'Cuando Marketing o el equipo comercial necesita ver quién se registró en la landing.',
                'flows' => [
                    [
                        'titulo' => 'Consultar leads',
                        'steps' => [
                            $this->itemFlujo(
                                'Elegir landing y buscar',
                                'Entra a Landing. Elige Landing consolidado o Landing curso: no es el listado de Alumnos. Busca por nombre, WhatsApp o correo. Si la tabla está vacía, estás en la pestaña equivocada o el buscador tiene texto. Exportar baja el archivo de lo que ves.',
                                'Recorta las pestañas Landing consolidado / Landing curso, el buscador y dos filas. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Nombre', 'Formulario de la landing', 'María Gómez'],
                    ['WhatsApp', 'Formulario de la landing', '999 111 222'],
                    ['Correo', 'Formulario de la landing', 'maria@ejemplo.com'],
                    ['Fecha', 'Automático al enviar el formulario', '12-08-2026 10:30'],
                ],
                'consideraciones' => 'Son leads de landings privadas, no el listado de Alumnos de Pedidos de Curso. Un mismo contacto puede aparecer en ambas pestañas si llenó las dos landings.',
                'errores' => [
                    ['La tabla está vacía', 'Estás en la pestaña equivocada o el buscador tiene texto', 'Cambia de pestaña y borra la búsqueda'],
                ],
                'ejemplo' => 'María Gómez, 999 111 222, maria@ejemplo.com, registrada el 12-08-2026 en Landing curso.',
                'resultado' => 'ves el lead en la pestaña correcta y, si exportas, descargas el archivo.',
                'ver_tambien' => 'Pedidos de Curso · Clientes.',
            ],
            'cotizaciones' => [
                'modulo_key' => 'cotizaciones',
                'titulo' => 'Cotizador — calcular una importación',
                'descripcion' => '{rol} → Cotizador',
                'articulo_titulo' => 'Cotizador',
                'articulo_clave' => '/cotizaciones',
                'tags' => ['Módulo: Cotizador', 'importación', 'costos'],
                'que_es' => 'La calculadora de importación: un flujo de varios pasos para armar el costo (cliente, carga, resumen y tributos).',
                'para_que' => 'Obtener el desglose de FOB, flete, seguro, CIF y tributos (antidumping, ad valorem, IGV, IPM, percepción) antes de formalizar la cotización.',
                'quien' => 'Rol {rol}.',
                'cuando' => 'Cuando un cliente pide una cotización de importación y hay que calcular costos con sus productos y proveedores.',
                'flows' => [
                    [
                        'titulo' => 'Armar el cálculo',
                        'steps' => [
                            $this->itemFlujo(
                                'Cliente',
                                'Entra a Cotizador. En el primer paso completa nombre, documento, WhatsApp y correo. Sin esos datos no avanzas. No uses datos reales en capturas del manual.',
                                'Recorta el paso 1 (datos del cliente). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Proveedores y productos',
                                'Agrega proveedores y, en cada uno, productos (nombre, CBM, precio, cantidad). Los totales se recalculan al cambiar. Si falta un producto obligatorio, no pases al resumen.',
                                'Recorta un proveedor con un producto (CBM, precio, cantidad). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Resumen y tributos',
                                'Revisa el resumen y luego la tabla de cálculos (FOB, flete, seguro, CIF, antidumping, ad valorem, IGV, IPM, percepción). Guarda o genera la cotización con los botones de esa pantalla. Si un caso especial (IMO, yuan) no cuadra, pendiente de definir con el equipo: no inventes la fórmula.',
                                'Recorta el resumen o la tabla de tributos. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Nombre / DNI / WhatsApp / correo', 'Los escribe el cotizador', 'Carlos Ruiz · 00000000'],
                    ['Producto', 'Por proveedor', 'Mochila escolar'],
                    ['CBM, precio, cantidad', 'Usuario', '0.12 · 8.50 · 200'],
                    ['Tributos', 'Calculados por el sistema', 'IGV 16%'],
                ],
                'consideraciones' => "Los totales se recalculan al cambiar productos.\n\nEl detalle fino de cada fórmula (flete, seguro, percepción) puede ampliarse con el equipo de cotización: pendiente de definir casos especiales (IMO, yuan, etc.).",
                'errores' => [
                    ['No avanza de paso', 'Falta un campo obligatorio (cliente o producto)', 'Completa los marcados como requeridos'],
                ],
                'ejemplo' => 'Cliente Carlos Ruiz, un proveedor, producto “Mochila escolar”, 200 unidades. El resumen muestra FOB y tributos antes de enviar la cotización.',
                'resultado' => 'el cálculo queda armado con totales por proveedor y el desglose de tributos visible.',
                'ver_tambien' => 'Clientes · Carga consolidada.',
            ],
        ];
    }

    private function cargaScreens()
    {
        return [
            'cargaconsolidada/abiertos' => $this->cargaConsolidada(
                'cargaconsolidada/abiertos',
                'Carga consolidada — Abiertos',
                '/cargaconsolidada/abiertos',
                'Abiertos',
                'abiertos',
                'general'
            ),
            'cargaconsolidada/completados' => $this->cargaConsolidada(
                'cargaconsolidada/completados',
                'Carga consolidada — Completados',
                '/cargaconsolidada/completados',
                'Completados',
                'completados',
                'general'
            ),
            'cargaconsolidada/coordinacion/abiertos' => $this->cargaConsolidada(
                'cargaconsolidada/coordinacion/abiertos',
                'Coordinación — Abiertos',
                '/cargaconsolidada/coordinacion/abiertos',
                'Coordinación abiertos',
                'abiertos',
                'coord'
            ),
            'cargaconsolidada/coordinacion/completados' => $this->cargaConsolidada(
                'cargaconsolidada/coordinacion/completados',
                'Coordinación — Completados',
                '/cargaconsolidada/coordinacion/completados',
                'Coordinación completados',
                'completados',
                'coord'
            ),
            'cargaconsolidada/documentacion/abiertos' => $this->cargaConsolidada(
                'cargaconsolidada/documentacion/abiertos',
                'Documentación — Abiertos',
                '/cargaconsolidada/documentacion/abiertos',
                'Documentación abiertos',
                'abiertos',
                'doc'
            ),
            'cargaconsolidada/documentacion/completados' => $this->cargaConsolidada(
                'cargaconsolidada/documentacion/completados',
                'Documentación — Completados',
                '/cargaconsolidada/documentacion/completados',
                'Documentación completados',
                'completados',
                'doc'
            ),
        ];
    }

    /**
     * @param string $lista abiertos|completados
     * @param string $sabor general|coord|doc
     */
    private function cargaConsolidada($key, $titulo, $clave, $articulo, $lista, $sabor)
    {
        $abiertos = $lista === 'abiertos';
        $coord = $sabor === 'coord';
        $doc = $sabor === 'doc';

        if ($coord) {
            $queEs = $abiertos
                ? 'Tu lista de cargas que siguen en marcha. Desde aquí das de alta el contenedor, lo corriges y entras a cada paso.'
                : 'Tu lista de cargas que ya cerraron. Ya no das de alta ni partes un contenedor desde aquí; entras a los pasos que aún necesitas.';
            $quien = 'Tú, desde el menú Coordinación.';
            $paraQue = $abiertos
                ? 'Dejar lista la carga y trabajarla: cotización, clientes, papeles, cierre de costos, entrega y factura.'
                : 'Terminar o consultar lo que queda de una carga que ya no está abierta.';
            $cuando = $abiertos
                ? 'Cuando abres una carga nueva o trabajas una que todavía no cierra.'
                : 'Cuando la carga ya figura como completada y solo queda el cierre.';
        } elseif ($doc) {
            $queEs = $abiertos
                ? 'Tu lista de cargas con papeles en trámite: ficha del cliente, papeles del contenedor y aduana.'
                : 'Tu lista de cargas cuyo trámite de papeles ya cerró. Aquí solo consultas.';
            $quien = 'Tú, desde el menú Documentación.';
            $paraQue = $abiertos
                ? 'Completar papeles de cada cliente, papeles del contenedor y los datos de aduana (canal, naviera, levante, DUA, multa).'
                : 'Consultar papeles y aduana de una carga que ya no está en trámite.';
            $cuando = $abiertos
                ? 'Mientras los papeles de esa carga siguen abiertos.'
                : 'Cuando el trámite ya cerró y solo quieres revisar.';
        } else {
            $queEs = $abiertos
                ? 'La lista de cargas que todavía están en curso.'
                : 'La lista de cargas que ya cerraron en tu vista.';
            $quien = 'Tú.';
            $paraQue = 'Encontrar la carga y entrar a lo que te toca.';
            $cuando = $abiertos
                ? 'En el día a día, con una carga que aún no cierra.'
                : 'Cuando esa carga ya no está en Abiertos.';
        }

        $out = [
            'modulo_key' => $key,
            'titulo' => $titulo,
            'descripcion' => '{rol} → ' . $titulo,
            'articulo_titulo' => $articulo,
            'articulo_clave' => $clave,
            'tags' => ['Módulo: Carga consolidada', 'contenedor', $abiertos ? 'abiertos' : 'completados'],
            'que_es' => $queEs,
            'para_que' => $paraQue,
            'quien' => $quien,
            'cuando' => $cuando,
            'flows' => [],
            'campos' => $this->camposCarga($abiertos, $sabor),
            'consideraciones' => $this->consideracionesCarga($abiertos, $sabor),
            'errores' => $this->erroresCarga(),
            'ejemplo' => $this->ejemploCarga($abiertos, $sabor),
            'resultado' => $abiertos
                ? 'Encuentras la carga y entras a lo que te toca.'
                : 'Consultas o terminas lo que queda de una carga que ya no está en Abiertos.',
            'ver_tambien' => 'Cómo encontrar una carga · Cómo entrar al detalle.',
        ];

        if (!$coord && !$doc) {
            $out['que_es_por_rol'] = $this->cargaQaPorRol('que_es', $abiertos);
            $out['para_que_por_rol'] = $this->cargaQaPorRol('para_que', $abiertos);
            $out['quien_por_rol'] = $this->cargaQaPorRol('quien', $abiertos);
            $out['cuando_por_rol'] = $this->cargaQaPorRol('cuando', $abiertos);
            $out['consideraciones_por_rol'] = $this->cargaQaPorRol('consideraciones', $abiertos);
            $out['ejemplo_por_rol'] = $this->cargaQaPorRol('ejemplo', $abiertos);
            $out['resultado_por_rol'] = $this->cargaQaPorRol('resultado', $abiertos);
            $out['errores_por_rol'] = $this->cargaQaPorRol('errores', $abiertos);
            $out['campos_por_rol'] = $this->cargaQaPorRol('campos', $abiertos);
            $out['ver_tambien_por_rol'] = $this->cargaQaPorRol('ver_tambien', $abiertos);
        }

        if ($coord) {
            $out['pasos'] = $this->pasosCargaCoordinacion();
        } elseif ($doc) {
            $out['pasos'] = $this->pasosCargaDocumentacion(!$abiertos);
        } else {
            $out['pasos_por_rol'] = $this->pasosCargaPorRol($abiertos);
            $out['pasos'] = [];
        }

        return $out;
    }

    private function cargaQaPorRol($campo, $abiertos)
    {
        $map = [
            'cotizador' => [
                'que_es' => $abiertos
                    ? 'Tu lista de cargas en curso. El ojo de cada fila te lleva a la cotización de esa carga (prospectos, proveedores y productos).'
                    : 'Tu lista de cargas que ya cerraron. Entras para ver cómo quedó la cotización, no para armar una nueva.',
                'para_que' => $abiertos
                    ? 'Armar o actualizar la cotización: quién entra a la carga, qué se cotizó y con qué proveedor.'
                    : 'Revisar la cotización de una carga que ya cerró.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Cuando tienes que cotizar o corregir una cotización de una carga que sigue abierta.'
                    : 'Cuando la carga ya cerró y solo quieres consultar lo cotizado.',
                'consideraciones' => "En la lista, Buscar y los filtros solo te ayudan a encontrar la fila; no cambian nada.\n\nEl ojo no es un recuadro: abre la cotización de esa carga.\n\nCrear Prospecto es para dar de alta a alguien en esa carga. En cada fila puedes subir o quitar el archivo de cotización, pedir la firma, copiar el enlace de firma, ver papeles de esa cotización o eliminarla si ya no va.",
                'ejemplo' => $abiertos
                    ? 'Buscas la carga #101, entras con el ojo y usas Crear Prospecto para Carlos Ruiz. Subes su cotización y le pides la firma.'
                    : 'La #101 ya cerró. Entras solo para ver cómo quedó cotizada.',
                'resultado' => $abiertos
                    ? 'La cotización de esa carga queda armada: prospecto, archivo y, si aplica, firma en camino.'
                    : 'Ves con claridad cómo se cotizó esa carga.',
                'ver_tambien' => 'Cómo crear un prospecto · Cómo subir la cotización · Cómo pedir la firma.',
                'errores' => [
                    ['No veo la carga', 'Está en Completados o un filtro la esconde', 'Cambia a Completados o deja los filtros en Todos'],
                    ['No puedo subir la cotización', 'Esa fila ya viene de la calculadora, o falta el prospecto', 'Crea el prospecto primero; si ya hay PDF o Excel, ábrelo desde el ícono'],
                ],
                'campos' => [
                    ['Buscar', 'Escribes número o dato de la carga para encontrarla.', '101'],
                    ['Filtros (año / estado)', 'Solo recortan la lista.', 'Todos'],
                    ['Ojo', 'Entra a la cotización de esa carga.', '—'],
                ],
            ],
            'coordinacion' => [
                'que_es' => $abiertos
                    ? 'Tu lista de cargas en marcha. Aquí das de alta el contenedor, lo corriges y entras a cada paso.'
                    : 'Tu lista de cargas que ya cerraron. Ya no creas ni partes; entras a los pasos que faltan terminar.',
                'para_que' => $abiertos
                    ? 'Dejar lista la carga y llevarla: cotización, clientes, papeles, costos, entrega y factura.'
                    : 'Cerrar lo que queda de una carga que ya no está abierta.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Al abrir una carga nueva o en el día a día de una que sigue abierta.'
                    : 'Cuando la carga ya está completada y queda el cierre.',
                'consideraciones' => $abiertos
                    ? "Crear da de alta el contenedor. El lápiz corrige. Partir saca copias vacías si sigue pendiente. El tacho borra, solo en pendiente.\n\nGuardar junto al tipo de cambio deja el TC Yuan. El ojo abre los pasos.\n\nCadena de papeles: en Documentación, Factura General solo arma el Excel si las carpetas Factura Comercial, Packing List y Lista de Partidas ya tienen un Excel guardado (con esos nombres exactos). Guarda ese archivo. En Cotización final lo subes con Subir Factura, bajas Plantilla General, la revisas en tu computadora y la subes como Plantilla Final. Eso genera las cotizaciones finales y un ZIP. Descargar plantillas en Documentación no sustituye ninguno de esos pasos.\n\nSi el recuadro de una carpeta no te deja adjuntar, no puedes cambiar ese archivo desde aquí: solo consultas o bajas lo que ya hay."
                    : "En Completados ya no creas ni partes. El ojo abre los mismos pasos para terminar o consultar.\n\nSi aún falta el lote de cotizaciones finales: Factura General (con las tres carpetas en Excel) → Subir Factura → Plantilla General → revisar en tu PC → Plantilla Final. Mientras el lote diga En proceso, no vuelvas a subir el mismo archivo.",
                'ejemplo' => $abiertos
                    ? 'Creas la carga #101 con cierre 15-08-2026, guardas el tipo de cambio y entras con el ojo. En Documentación bajas Factura General (las tres carpetas ya tienen Excel), en Cotización final la subes, bajas Plantilla General, la revisas y la subes como Plantilla Final.'
                    : 'La #101 ya está completada. Entras a Cotización final: si el lote aún no existe, subes la Factura General, armas la plantilla y generas las cotizaciones finales.',
                'resultado' => $abiertos
                    ? 'El contenedor queda dado de alta y puedes trabajar cada paso.'
                    : 'Dejas cerrado lo que faltaba de esa carga.',
                'ver_tambien' => 'Cómo bajar Factura General · Cómo subir la factura y armar la plantilla · Cómo generar las cotizaciones finales.',
                'errores' => [
                    ['No veo Crear', 'Estás en Completados', 'Vuelve a Abiertos'],
                    ['No puedo partir ni borrar', 'La carga ya no está pendiente, o ya se partió', 'Solo se parte y se borra en pendiente'],
                    ['Factura General no baja', 'Falta Factura Comercial, Packing List o Lista de Partidas, o no son Excel', 'Revisa que esas tres carpetas, con esos nombres, ya tengan un Excel guardado. Si el recuadro no te deja adjuntar, no puedes cambiar esos archivos desde aquí'],
                    ['Plantilla General no baja', 'Aún no subiste la Factura General en Cotización final', 'Usa Subir Factura con el Excel que te bajó Factura General en Documentación. No uses el ZIP de Descargar plantillas'],
                    ['El lote de plantilla final no termina o sale con error', 'El Excel no coincide con los clientes o sigue En proceso', 'Espera el aviso; si falló, abre Detalle, corrige y vuelve a subir Plantilla Final'],
                    ['No veo la carga', 'Está en Completados o un filtro la esconde', 'Cambia de lista y deja filtros en Todos'],
                ],
                'campos' => [
                    ['Crear', 'Da de alta el contenedor: mes, país, empresa y fechas.', 'Agosto · China'],
                    ['TC Yuan + Guardar', 'Deja el tipo de cambio de la lista.', '0.52'],
                    ['Ojo / lápiz / partir / tacho', 'Entrar, corregir, copiar vacía o borrar (solo pendiente).', '—'],
                    ['Factura Comercial / Packing List / Lista de Partidas', 'Tienen que ser Excel y estar guardadas. Sin las tres, Factura General no arma el archivo.', '.xlsx'],
                    ['Factura General', 'Junta esas tres carpetas con los datos de la carga y te baja un Excel procesado. No es la carpeta cruda.', 'factura_procesada.xlsx'],
                    ['Subir Factura', 'En Cotización final: sube el Excel que te bajó Factura General.', '—'],
                    ['Plantilla General', 'Con la factura ya subida, baja el Excel de la carga. Solo entran clientes Confirmado.', 'plantilla_general.xlsx'],
                    ['Plantilla Final', 'Sube el Excel ya revisado. Genera las cotizaciones finales (se encola; no es instantáneo).', '.xlsx'],
                ],
            ],
            'documentacion' => [
                'que_es' => $abiertos
                    ? 'Tu lista de cargas con papeles en trámite.'
                    : 'Tu lista de cargas cuyo trámite de papeles ya cerró. Aquí solo miras.',
                'para_que' => $abiertos
                    ? 'Completar papeles de cada cliente, papeles del contenedor y los datos de aduana.'
                    : 'Consultar papeles y aduana, sin editar.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Mientras los papeles de esa carga siguen abiertos.'
                    : 'Cuando el trámite ya cerró y solo quieres revisar.',
                'consideraciones' => $abiertos
                    ? "En Estado eliges si esa carga sigue pendiente, ya está en papeles o ya cerró. Se guarda al elegir.\n\nEl ojo te lleva a Clientes, Documentación y Aduana.\n\nEn Clientes, el ojo de la fila abre la ficha y los papeles de esa persona. Reservado / No reservado indica si tiene cupo.\n\nEn Documentación (papeles del contenedor), Factura Comercial, Packing List y Lista de Partidas tienen que ser Excel y estar guardadas. Con esas tres, Factura General te entrega un Excel ya armado: guárdalo. Ese archivo es el que después se sube para armar la plantilla general; al revisarla y subirla como plantilla final se generan las cotizaciones finales. Descargar plantillas solo empaqueta lo ya subido. Nuevo documento (máximo 1 MB) agrega otro tipo de papel, no reemplaza esas tres carpetas."
                    : "En Completados no se edita. El ojo es para consultar papeles y aduana. Factura General y Descargar plantillas siguen sirviendo para bajar lo que ya quedó.",
                'ejemplo' => $abiertos
                    ? 'En la #101 marcas el estado, entras con el ojo, dejas Excel en Factura Comercial, Packing List y Lista de Partidas, pulsas Guardar en cada una y bajas Factura General. Completas la ficha del cliente y registras canal Verde y el DUA.'
                    : 'La #101 ya cerró. Entras solo para ver naviera y DUA, o para bajar la Factura General si aún la necesitas.',
                'resultado' => $abiertos
                    ? 'Los papeles del cliente y del contenedor, y los datos de aduana, quedan al día.'
                    : 'Puedes consultar el trámite sin cambiarlo.',
                'ver_tambien' => 'Cómo completar papeles del cliente · Cómo cargar las tres carpetas de Excel · Cómo bajar Factura General.',
                'errores' => [
                    ['No puedo editar', 'Estás en Completados', 'El trámite se completa en Abiertos'],
                    ['Factura General no baja', 'Falta Factura Comercial, Packing List o Lista de Partidas, o no son Excel', 'Sube las tres en Excel, pulsa Guardar y vuelve a intentar'],
                    ['No veo la carga', 'Está en la otra lista o un filtro la esconde', 'Cambia de Abiertos a Completados (o al revés) y deja filtros en Todos'],
                ],
                'campos' => [
                    ['Estado', 'Lista: pendiente, en papeles o completado. Se guarda al elegir.', 'DOCUMENTACION'],
                    ['Ojo', 'Entra a clientes, papeles del contenedor y aduana.', '—'],
                    ['Reservado / No reservado', 'En Clientes: si esa persona tiene cupo.', 'Reservado'],
                    ['Factura Comercial / Packing List / Lista de Partidas', 'Excel obligatorio. Sin las tres guardadas, Factura General no baja.', '.xlsx'],
                    ['Factura General', 'Arma y baja el Excel procesado de esas tres carpetas más los datos de la carga.', 'factura_procesada.xlsx'],
                    ['Descargar plantillas', 'Paquete de lo ya subido. No arma la Factura General ni las cotizaciones finales.', 'ZIP'],
                    ['Nuevo documento', 'Crea otra carpeta (nombre + archivo, máximo 1 MB). No reemplaza las tres de Excel.', 'BL adicional'],
                ],
            ],
            'jefe-importacion' => [
                'que_es' => $abiertos
                    ? 'Tu listado general de cargas en curso. Tienes tres menús: Carga (esta lista), Coordinación (alta y pasos) y Documentación (papeles y aduana).'
                    : 'Tu listado general de cargas que ya cerraron. Coordinación y Documentación siguen siendo menús aparte.',
                'para_que' => 'Encontrar la carga. Si vas a armar el contenedor, usa Coordinación. Si vas a papeles o aduana, usa Documentación.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Cuando quieres ver el listado o entrar al trámite que te toca hoy.'
                    : 'Cuando la carga ya no está en Abiertos y solo consultas.',
                'consideraciones' => "En esta lista, el ojo entra a papeles de esa carga.\n\nEstado te dice cómo va el trámite de papeles; se guarda al elegir.\n\nLos otros dos menús no son esta misma pantalla: Coordinación es el contenedor y los seis pasos; Documentación es papeles y aduana.",
                'ejemplo' => $abiertos
                    ? 'Buscas la #101 aquí. Si hay que partirla, vas a Coordinación. Si hay que ver el DUA, vas a Documentación.'
                    : 'La #101 ya cerró. La buscas en esta lista y, si hace falta el DUA, entras por Documentación.',
                'resultado' => 'Entras a la vista que necesitas, sin mezclar el alta del contenedor con los papeles.',
                'ver_tambien' => 'Cómo armar el contenedor · Cómo completar papeles · Cómo registrar aduana.',
                'errores' => [
                    ['No veo Crear', 'Esta lista no da de alta el contenedor', 'Usa el menú Coordinación'],
                    ['No veo la carga', 'Está en Completados o en otro de tus tres menús', 'Cambia de lista o de menú'],
                ],
                'campos' => [
                    ['Ojo', 'Entra a los papeles de esa carga.', '—'],
                    ['Estado', 'Cómo va el trámite de papeles.', 'DOCUMENTACION'],
                ],
            ],
            'jefe-marketing' => [
                'que_es' => 'Tu lista de cargas para ver cómo van la cotización y los clientes. No cambias datos.',
                'para_que' => 'Enterarte del avance, sin editar.',
                'quien' => 'Tú.',
                'cuando' => 'Cuando necesitas ver una carga, no tramitarla.',
                'consideraciones' => "El ojo te lleva a Cotización y Clientes en modo consulta.\n\nNo verás Crear Prospecto ni listas para cambiar Reservado. Si un botón no aparece, es porque esta vista es solo para mirar.",
                'ejemplo' => 'Entras a la #101, abres Cotización y Clientes y ves el avance de Carlos Ruiz.',
                'resultado' => 'Sabes cómo va la carga, sin haber cambiado nada.',
                'ver_tambien' => 'Cómo ver la cotización · Cómo ver los clientes.',
                'errores' => [
                    ['No puedo editar', 'Esta vista es solo consulta', 'Revisa con el ojo; no hay lápiz a propósito'],
                    ['No veo la carga', 'Está en Completados o un filtro la esconde', 'Cambia de lista y deja filtros en Todos'],
                ],
                'campos' => [
                    ['Ojo', 'Abre cotización y clientes para consultar.', '—'],
                    ['Buscar / filtros', 'Solo encuentran la fila.', '101'],
                ],
            ],
            'finanzas' => [
                'que_es' => $abiertos
                    ? 'Tu vista de cargas en curso. Aquí solo miras; no armas ni cierras la carga.'
                    : 'Tu lista de cargas que ya tienen plantillas finales. Si no aparece una, todavía no se generaron.',
                'para_que' => $abiertos
                    ? 'Ver la carga. Tu trabajo está en Completados, cuando ya hay plantillas finales.'
                    : 'Consultar y descargar las plantillas finales de esa carga.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Si necesitas ver una carga que aún no tiene plantillas.'
                    : 'Cuando ya hay plantillas finales y quieres bajarlas o ver el detalle.',
                'consideraciones' => $abiertos
                    ? "El ojo es para mirar. Completados es la lista que te importa: ahí aparecen las cargas con plantillas finales."
                    : "Descargar: baja la plantilla de esa fila. Descargar ZIP: baja el lote. Detalle: ves qué clientes salieron bien y cuáles con error.\n\nSi la carga no está, las plantillas aún no se generaron.",
                'ejemplo' => $abiertos
                    ? 'Ves la #101 en curso, sin editar, y pasas a Completados cuando ya existan plantillas.'
                    : 'La #101 ya tiene plantillas. Entras, descargas el ZIP y revisas el detalle.',
                'resultado' => $abiertos
                    ? 'Ves la carga sin tocarla.'
                    : 'Tienes las plantillas finales a la mano.',
                'ver_tambien' => 'Cómo encontrar la carga · Cómo descargar las plantillas finales.',
                'errores' => [
                    ['No veo la carga en Completados', 'Aún no hay plantillas finales de esa carga', 'Espera a que existan; no se generan desde esta pantalla'],
                    ['Descargar no aparece', 'Esa generación sigue en proceso', 'Espera a que deje de decir En proceso'],
                ],
                'campos' => [
                    ['Ojo', 'Consulta la carga.', '—'],
                    ['Descargar / ZIP / Detalle', 'En Completados, sobre cada generación de plantillas.', 'ZIP 15-08-2026'],
                ],
            ],
            'administracion' => [
                'que_es' => 'Tu lista de cargas ya cerradas para el cierre: clientes, papeles, pagos, entrega y factura.',
                'para_que' => 'Cobrar, dejar constancia de entrega y emitir factura y guía de esa carga.',
                'quien' => 'Tú.',
                'cuando' => 'Cuando la carga ya no está en curso y toca el cierre.',
                'consideraciones' => "El ojo te lleva a los pasos del cierre.\n\nEn Cotización y Cotización final usas la pestaña Pagos: recordatorio de pago y el seguimiento del cobro.\n\nEn Entrega: Fechas y horarios, Enviar formulario, Descargar plantillas, firmar cargo.\n\nEn Factura y guía: Enviar formulario, Subir factura o guía, o enviar por WhatsApp.",
                'ejemplo' => 'Entras a la #101, cobras en Cotización final, emites la factura y dejas la constancia de entrega.',
                'resultado' => 'El cierre de esa carga queda hecho: cobro, entrega y comprobantes.',
                'ver_tambien' => 'Cómo registrar un pago · Cómo dejar constancia de entrega · Cómo emitir factura y guía.',
                'errores' => [
                    ['No veo Abiertos', 'Tu menú es Completados', 'Trabaja desde Completados'],
                    ['No veo Crear Prospecto', 'No armas la cotización desde aquí', 'Usa Pagos, Entrega y Factura'],
                ],
                'campos' => [
                    ['Ojo', 'Entra a los pasos del cierre.', '—'],
                    ['Enviar formulario', 'En Entrega y en Factura: manda el formulario al cliente.', '—'],
                    ['Subir', 'Adjunta factura electrónica o guía de remisión.', 'PDF'],
                ],
            ],
            'contenedor-almacen' => [
                'que_es' => $abiertos
                    ? 'Tu lista de cargas que todavía estás recibiendo en China. El ojo abre esa carga para marcar proveedores y lo que llegó.'
                    : 'Tu lista de cargas que ya cerraste (Finish). Entras solo para consultar cómo quedó la recepción.',
                'para_que' => $abiertos
                    ? 'Contactar al proveedor, marcar la inspección, anotar lo que llegó y cerrar el contenedor cuando ya está cargado.'
                    : 'Consultar una carga que ya cerraste.',
                'quien' => 'Tú.',
                'cuando' => $abiertos
                    ? 'Cuando un proveedor te escribe, cuando inspeccionas, cuando llega mercancía o cuando el contenedor ya está listo para cerrar.'
                    : 'Cuando la carga ya está en Finish y solo quieres mirar.',
                'consideraciones' => $abiertos
                    ? "En la lista, el ojo abre esa carga. Waiting / Receiving / Finish solo filtran; no cambian la carga.\n\nEn cada proveedor, Status es para marcar cómo va: C (contactado), INSPECTION (inspeccionado), LOADED (ya cargó).\n\nGuardar (el disquete) deja cajas, pallets y fecha de llegada.\n\nUpload → Packing List: cuando subes esa lista, la carga pasa a cerrada y la verás en Completados."
                    : "Completados es Finish: la recepción ya cerró. El ojo sirve para consultar, no para marcar de nuevo.",
                'ejemplo' => $abiertos
                    ? 'Entras a la #101. Al proveedor de mochilas lo marcas C cuando contestó, INSPECTION cuando lo revisaste, anotas 20 cajas y la fecha, y al terminar subes el Packing List para cerrar el contenedor.'
                    : 'La #101 ya está en Finish. Entras solo para ver cómo quedó la recepción.',
                'resultado' => $abiertos
                    ? 'Cada proveedor queda marcado y, al subir el Packing List, el contenedor queda cerrado.'
                    : 'Puedes consultar la recepción ya cerrada.',
                'errores' => [
                    ['No veo la carga', 'Ya está en Completados (Finish) o un filtro la esconde', 'Cambia de lista o deja Estado en Todos'],
                    ['El estado no se guarda', 'No elegiste el Status del proveedor', 'En Status elige C, INSPECTION o LOADED; se guarda al elegir'],
                    ['Cajas o fecha no quedan', 'No pulsas Guardar en esa fila', 'Después de escribir, usa el disquete de esa fila'],
                    ['La carga no pasa a Completados', 'Aún no subes el Packing List', 'Upload → Packing List. Si te equivocaste de archivo, el tacho lo quita y subes otro'],
                ],
                'campos' => [
                    ['Ojo', 'Abre la recepción de esa carga.', '—'],
                    ['Status del proveedor', 'Se guarda al elegir: C contactado, INSPECTION inspeccionado, LOADED ya cargó.', 'C'],
                    ['QTY Box / Pallet / Arrive Date', 'Lo que llegó en China. Se graban con Guardar.', '20 · 2 · 15-08-2026'],
                    ['Upload / Packing List', 'Cierra el contenedor al subir la lista.', 'packing.xlsx'],
                ],
                'ver_tambien' => 'Cómo marcar contactado · Cómo marcar inspeccionado · Cómo registrar lo que llegó · Cómo cerrar el contenedor.',
            ],
            'contabilidad' => [
                'que_es' => $abiertos
                    ? 'Tu lista de cargas en curso para cobrar y facturar.'
                    : 'Tu lista de cargas ya cerradas, para terminar cobro y comprobantes.',
                'para_que' => 'Registrar pagos, cerrar costos y emitir factura y guía de esa carga.',
                'quien' => 'Tú.',
                'cuando' => 'Cuando toca liquidar o facturar esa carga.',
                'consideraciones' => "El ojo te lleva a Cotización (pestaña Pagos), Cotización final y Factura y guía.\n\nEn Pagos: recordatorio de pago y el seguimiento de lo cobrado.\n\nEn Factura y guía: Enviar formulario, Subir factura o guía, y enviar al cliente.\n\nDescargar embarque te sirve si necesitas ese Excel.",
                'ejemplo' => 'Entras a la #101, registras el pago en Cotización final y subes la factura electrónica.',
                'resultado' => 'El cobro y los comprobantes de esa carga quedan hechos.',
                'ver_tambien' => 'Cómo registrar un pago · Cómo emitir factura y guía.',
                'errores' => [
                    ['No veo prospectos para crear', 'No armas la cotización desde aquí', 'Usa la pestaña Pagos'],
                    ['No veo la carga', 'Está en la otra lista o un filtro la esconde', 'Cambia de Abiertos a Completados y deja filtros en Todos'],
                ],
                'campos' => [
                    ['Ojo', 'Entra a pagos, cotización final o factura.', '—'],
                    ['Recordatorio de pago', 'Avisa al cliente que tiene un saldo.', '—'],
                    ['Subir / Enviar formulario', 'Adjunta o manda factura y guía.', 'PDF'],
                ],
            ],
        ];

        $out = [];
        foreach ($map as $slug => $row) {
            if (isset($row[$campo])) {
                $out[$slug] = $row[$campo];
            }
        }

        return $out;
    }

    private function camposCarga($abiertos, $sabor)
    {
        $rows = [
            ['Buscar', 'Encuentra la carga por número o dato. No cambia nada.', '101'],
            ['Filtros', 'Recortan la lista (año, estado). No cambian la carga.', 'Todos'],
            ['Ojo', 'Entra al detalle de esa carga.', '—'],
        ];
        if ($sabor === 'coord') {
            $rows[] = ['Crear / lápiz', 'Dar de alta o corregir mes, país, empresa y fechas.', 'Agosto · China'];
            $rows[] = ['Partir / tacho', 'Copiar vacía o borrar. Solo si sigue pendiente.', '—'];
            $rows[] = ['TC Yuan + Guardar', 'Deja el tipo de cambio de la lista.', '0.52'];
            $rows[] = ['Factura General', 'Arma el Excel procesado si Factura Comercial, Packing List y Lista de Partidas ya tienen Excel.', 'factura_procesada.xlsx'];
            $rows[] = ['Subir Factura / Plantilla General / Plantilla Final', 'En Cotización final: subes la factura, bajas la plantilla, la revisas y la subes para generar las cotizaciones finales.', '—'];
        }
        if ($sabor === 'doc') {
            $rows[] = ['Estado', $abiertos ? 'Elige cómo va el trámite de papeles. Se guarda al elegir.' : 'Solo se ve; en Completados no se edita.', 'DOCUMENTACION'];
            $rows[] = ['Canal / naviera / DUA', 'Se ven o se llenan en el paso Aduana.', 'Verde · COSCO'];
            $rows[] = ['Factura Comercial / Packing List / Lista de Partidas', 'Excel obligatorio para poder bajar Factura General.', '.xlsx'];
            $rows[] = ['Factura General', 'Arma y baja el Excel procesado de esas tres carpetas.', 'factura_procesada.xlsx'];
        }

        return $rows;
    }

    private function consideracionesCarga($abiertos, $sabor)
    {
        if ($sabor === 'coord') {
            return $abiertos
                ? "Crear da de alta el contenedor. El lápiz corrige. Partir saca copias vacías si sigue pendiente. El tacho borra, también solo en pendiente.\n\nGuardar junto al tipo de cambio deja el TC Yuan.\n\nEl ojo abre los pasos: Cotización, Clientes, Documentación, Cotización final, Entrega y Factura.\n\nCadena de papeles: Factura General solo arma el Excel si Factura Comercial, Packing List y Lista de Partidas ya tienen Excel. Ese archivo se sube en Cotización final (Subir Factura), se baja Plantilla General, se revisa en tu PC y se sube como Plantilla Final para generar las cotizaciones finales."
                : "En Completados ya no creas ni partes. El ojo abre los mismos pasos para terminar o consultar.\n\nSi falta el lote de cotizaciones: Factura General → Subir Factura → Plantilla General → revisar → Plantilla Final.";
        }
        if ($sabor === 'doc') {
            return $abiertos
                ? "Estado indica cómo van los papeles de esa carga y se guarda al elegir.\n\nEl ojo abre Clientes (ficha de cada persona), Documentación (papeles del contenedor) y Aduana (canal, naviera, levante, DUA, multa).\n\nEn Documentación, Factura Comercial, Packing List y Lista de Partidas tienen que ser Excel. Con esas tres, Factura General te entrega el Excel procesado que más adelante se usa para la plantilla general, la plantilla final y las cotizaciones finales."
                : "En Completados solo consultas. El ojo abre papeles y aduana sin editar. Factura General y Descargar plantillas sirven para bajar lo que ya quedó.";
        }

        return $abiertos
            ? 'El ojo abre lo que te toca de esa carga. Buscar y filtros solo encuentran la fila.'
            : 'Esta es la lista de cargas que ya cerraron en tu vista. El ojo sigue abriendo el detalle.';
    }

    private function erroresCarga()
    {
        return [
            ['No veo la carga', 'Está en la otra lista o un filtro la esconde', 'Cambia de Abiertos a Completados (o al revés) y deja filtros en Todos'],
            ['Un botón no aparece', 'Esta pantalla no lo incluye', 'Usa el menú donde sí lo ves (Coordinación o Documentación)'],
        ];
    }

    private function ejemploCarga($abiertos, $sabor)
    {
        if ($sabor === 'coord') {
            return $abiertos
                ? 'Creas la #101, guardas el tipo de cambio y entras con el ojo a Cotización.'
                : 'La #101 ya cerró. Entras a Entrega y a Factura para terminar el cierre.';
        }
        if ($sabor === 'doc') {
            return $abiertos
                ? 'Entras a la #101, completas la ficha del cliente y registras canal Verde y el DUA.'
                : 'La #101 ya cerró. Entras solo para ver naviera y DUA.';
        }

        return $abiertos
            ? 'Buscas la carga #101 y entras con el ojo a lo que te toca.'
            : 'La #101 ya cerró. La buscas en Completados y consultas el detalle.';
    }

    private function pasoCarga($titulo, $body, $steps = [])
    {
        return [
            'titulo' => $titulo,
            'hint' => $body,
            'steps' => array_values($steps),
        ];
    }

    private function pasosCargaCoordinacion()
    {
        return [
            $this->pasoCarga(
                'Cotización',
                'Aquí ves quién cotizó en esa carga y cómo va cada fila. No armas el prospecto desde cero: sigues, mueves y bajas lo que ya está.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves pestañas: Prospectos, Por Embarcar y Pagos. Buscar y los filtros solo encuentran la fila; no cambian la cotización. Si no ves a alguien, está en otra pestaña o un filtro lo esconde. Cambia a Todos antes de asumir que no está en la carga.',
                        'Recorta el encabezado con las tres pestañas (Prospectos / Por Embarcar / Pagos), el buscador y las primeras filas. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Prospectos',
                        'En cada fila ves el contacto y el estado. El ojo abre los papeles de esa cotización. Copiar enlace de firma sirve para mandárselo al cliente por otro medio: solo si ya hay contrato o enlace; si no hay, no copies nada. La flecha pasa esa cotización al siguiente estado cuando ya corresponde: no la uses si aún faltan datos o firma. Lo que no puedas editar en la fila está bloqueado a propósito.',
                        'Recorta una fila de Prospectos con el ojo, el enlace de firma y la flecha de estado. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Abrir en Drive',
                        'Abre el Excel de seguimiento en la nube para ver el avance sin bajarlo. Si el botón no aparece, esa carga aún no tiene el archivo de seguimiento. No reemplaza Descargar Embarque.',
                        'Recorta el recuadro «Excel seguimiento» con el botón Abrir en Drive. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Por Embarcar y Descargar Embarque',
                        'Por Embarcar lista lo que va en el contenedor. Descargar Embarque baja ese Excel. Si no hay datos, no genera archivo: no es un error de tu usuario, falta información en la carga. Úsalo para revisar, no para armar la cotización.',
                        'Recorta la pestaña Por Embarcar activa y el botón Descargar Embarque. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Pagos',
                        'Aquí ves importe, lo pagado y la diferencia de esa cotización. Sirve para seguir el cobro, no para crear prospectos ni subir la cotización inicial.',
                        'Recorta la pestaña Pagos con columnas de importe, pagado y diferencia. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Clientes',
                'Aquí ves quién viaja en el contenedor y cómo van sus papeles. No es el directorio general de clientes: solo las personas de esta carga.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves pestañas: Seguimiento, Documentación y Variación. Buscar encuentra la fila. Si alguien no aparece, no está en esta carga o un filtro la esconde.',
                        'Recorta las pestañas Seguimiento / Documentación / Variación y el listado. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Reservado / No reservado',
                        'Indica si esa persona tiene cupo. Se guarda al elegir; no hace falta otro botón. Documentación en esa misma lista es otro estado del trámite de papeles, no el menú Documentación del contenedor.',
                        'Recorta la lista Reservado / No reservado / Documentación de una fila. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Recordatorio de firma',
                        'Le pide al cliente que firme. Confirma el recuadro. No lo pulses si ya firmó: volver a mandarlo no adelanta el trámite y puede confundir.',
                        'Recorta el botón o ícono de recordatorio de firma en la fila. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'La ficha (ojo)',
                        'El ojo abre la ficha de esa persona. Guardar cambios deja lo editado; si sales sin guardar, se pierde. Descargar Excel baja la confirmación. Nuevo documento agrega un papel; el archivo no queda hasta que pulsas Guardar.',
                        'Recorta la ficha abierta con Guardar cambios, Descargar Excel y el recuadro de archivos. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Fecha máxima de documentación',
                        'En Seguimiento puedes dejar la fecha tope de papeles y pulsar el disquete para grabarla. Si no pulsas guardar, la fecha no queda.',
                        'Recorta el recuadro F. Max. Documentación con la fecha y el botón de guardar. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Documentación',
                'Aquí están los papeles de toda la carga, organizados en carpetas. No es la ficha de cada cliente: es el expediente del contenedor. Lo que hagas aquí alimenta Cotización final.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves una carpeta por tipo de papel (nombre arriba y un recuadro debajo). Si el recuadro te deja adjuntar, sueltas el archivo, esperas a que aparezca en la lista y pulsas Guardar: sin Guardar no queda grabado. Si el recuadro está bloqueado, no puedes cambiar el archivo de esa carpeta: solo lo consultas o lo bajas si ya hay uno.',
                        'Recorta el grid de carpetas (nombre + recuadro de archivo), al menos dos carpetas visibles. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Las tres carpetas que habilitan Factura General',
                        'Factura General no funciona con “cualquier papel”. Necesita, a la vez, un Excel en estas tres carpetas, con esos nombres exactos: Factura Comercial, Packing List y Lista de Partidas. Tiene que ser .xlsx o .xls y estar guardado. Un PDF, un Word o una carpeta nueva con un nombre parecido no cuentan. Si falta una de las tres, o si el archivo no es Excel, el botón no arma nada.',
                        'Recorta las tres carpetas juntas: Factura Comercial, Packing List y Lista de Partidas, con el Excel ya visible en cada una. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Cómo dejar el Excel en cada carpeta (si te deja adjuntar)',
                        'Abre la carpeta vacía, elige o arrastra el Excel, pulsa Guardar y espera el aviso de éxito. Repite en las tres. Si te equivocaste de archivo, en la mayoría puedes quitarlo y subir el correcto; la primera carpeta (lista de embarque) no se borra. Si no te deja adjuntar, revisa si esas tres ya tienen Excel: con eso basta para el siguiente punto.',
                        'Recorta una carpeta en el momento de adjuntar: recuadro, archivo elegido y botón Guardar. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Factura General: qué hace de verdad',
                        'No te baja “la carpeta tal cual”. Junta los tres Excel con los datos de la carga y te entrega un archivo ya armado (factura procesada). Guárdalo entero en tu computadora: ese es el insumo de Cotización final. Si pulsas el botón y falla, casi siempre falta una de las tres carpetas o el archivo no es Excel. Completa eso y vuelve a intentar; Descargar plantillas no sustituye este paso.',
                        'Recorta la barra superior con el botón Factura General. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Descargar plantillas',
                        'Arma un paquete con todo lo que ya está subido en las carpetas. Sirve para archivar o revisar en tu PC. No reemplaza a Factura General, no arma la plantilla de cotización y no genera las cotizaciones finales.',
                        'Recorta el botón Descargar plantillas en la barra superior. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Nuevo documento',
                        'Si falta un tipo de papel que aún no tiene carpeta, escribes el nombre, adjuntas el archivo y se crea. El archivo no puede pesar más de 1 MB. Eso no reemplaza Factura Comercial, Packing List ni Lista de Partidas: esas tres ya existen y son las que usa Factura General.',
                        'Recorta el recuadro Nuevo documento / Crear documento: campo nombre y el archivo. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Cuándo pasar a Cotización final',
                        'No pases al siguiente paso hasta tener en tu computadora el Excel que te bajó Factura General. Sin ese archivo no puedes subir la factura, no se arma la plantilla general y no se generan las cotizaciones finales.',
                        'Recorta el menú de pasos de la carga con Documentación y Cotización final visibles. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Cotización final',
                'Aquí cierras costos y armas las cotizaciones finales de la carga. El trámite va en cadena: primero la Factura General, después la plantilla general, después la plantilla final. Si saltas un eslabón, el siguiente no arranca.',
                [
                    $this->itemFlujo(
                        'Qué tiene que estar listo',
                        'En Documentación ya bajaste Factura General (el Excel procesado) y lo tienes en tu computadora. Ese archivo no es el ZIP de Descargar plantillas ni un PDF de carpeta: es el Excel que armó el botón Factura General. Sin él, Plantilla General no deja bajar nada.',
                        'Recorta la barra de Cotización final con los cuatro botones (Subir Factura, Plantilla General, Plantilla Final, Ver plantillas finales). Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Subir Factura',
                        'Pulsa Subir Factura y carga exactamente ese Excel (o el mismo archivo si ya lo revisaste y corregiste). Queda guardado como factura general de esta carga. Tiene que ser Excel. Este paso no genera cotizaciones: solo deja el insumo para la plantilla. Si subes otro archivo, la plantilla saldrá mal o no saldrá.',
                        'Recorta el recuadro Subir Factura con el archivo Excel elegido. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Plantilla General',
                        'Con la factura ya subida, Plantilla General arma un Excel nuevo (clientes, productos, cantidades, tributos) y te lo baja. Solo entran clientes en estado Confirmado: si alguien no está confirmado, no aparece. Si aún no subiste la factura general, el botón falla (no encontró la factura general). Vuelve a Subir Factura y reintenta.',
                        'Recorta el botón Plantilla General en la barra. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Revisar el Excel en tu computadora',
                        'Abre la plantilla general y revísala: nombres, ítems, cantidades y precios. Corrige lo que no cuadre. Ese archivo ya revisado es el que sigue: no subas la plantilla cruda si viste errores. Este trabajo es fuera de la pantalla; la intranet no “aprueba” el Excel por ti.',
                        'Recorta una vista del Excel de plantilla general (encabezados CLIENTE, ITEM, CANTIDAD) con datos ficticios. Sin nombres reales.'
                    ),
                    $this->itemFlujo(
                        'Plantilla Final: generar las cotizaciones',
                        'Pulsa Plantilla Final y sube el Excel ya revisado (.xlsx o .xls). No es un guardado instantáneo: se encola y te avisa cuando termina. Mientras diga En proceso, no vuelvas a subir el mismo lote. Esa subida genera las cotizaciones finales de cada cliente de la carga y un ZIP con esos Excel: es el cierre del trámite de plantillas, no un archivo más en Documentación.',
                        'Recorta el recuadro Subir Plantilla Final y, si puedes, el aviso «Generación en curso». Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Ver plantillas finales',
                        'Abre el historial de cada generación. Si dice En proceso, espera el aviso. Cuando termine: Descargar baja la plantilla que subiste; Descargar ZIP baja el lote de cotizaciones; Detalle muestra quién salió bien y quién tuvo error (por ejemplo, el nombre del Excel no coincide con el cliente).',
                        'Recorta la tabla Historial de generaciones con estado, Descargar, ZIP y Detalle. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Si un cliente quedó mal en el lote',
                        'En la tabla, Subir cotización final te deja cargar a mano el Excel de esa fila. Úsalo si el lote falló para esa persona, no para saltarte Plantilla Final. Si el lote entero salió con errores, mira Detalle, corrige el Excel y vuelve a subir Plantilla Final; no reutilices un archivo a medias.',
                        'Recorta una fila de Cotización final (pestaña General) con Subir cotización final. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Entrega',
                'Aquí programas el despacho al cliente cuando la carga ya llegó: fechas, quién recibe y la firma. Esto no es la recepción en China.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves pestañas (clientes, entregas, delivery), el buscador y la tabla. Buscar y filtros solo encuentran la fila. Si no hay destinatario en la carga, Enviar formulario no manda nada.',
                        'Recorta el encabezado Entregas con pestañas, Enviar formulario y la tabla. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Fechas y Horarios',
                        'Arma la agenda: día, hora, distrito y tipo de receptor. Provincia se habilita al elegir departamento; no la escribas antes. Guarda antes de salir: si sales sin guardar, se pierde lo último que armaste.',
                        'Recorta la pantalla Fechas y Horarios con departamento, provincia, distrito y horario. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Enviar formulario',
                        'Manda el formulario al cliente. Elige a quiénes. Si no hay destinatario o no confirmas el envío, no sale. No lo uses como recordatorio de firma de cotización: es el formulario de esta etapa.',
                        'Recorta el recuadro Enviar formulario con la lista de destinatarios. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Descargar plantillas',
                        'Baja los formatos de esta etapa. No es Factura General ni las plantillas finales de cotización: son los papeles de entrega de esta carga.',
                        'Recorta el botón Descargar Plantillas en Entrega. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Detalle, firmar cargo y quitar',
                        'En la fila, ver detalle abre los datos de esa entrega. Firmar cargo deja la constancia de que se entregó. El tacho quita el registro si ya no va: pide confirmación y no hay deshacer.',
                        'Recorta una fila con ver detalle, firmar cargo y el tacho. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Factura y guía',
                'Aquí consultas factura y guía de esa carga. Desde esta vista no armas la cotización ni las plantillas finales.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Busca por nombre o teléfono. La tabla muestra si ya hay comprobante. Si la fila está vacía, aún no se emitió desde aquí: no es un error de búsqueda.',
                        'Recorta el encabezado Factura y Guía con el buscador y dos o tres filas. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Abrir o bajar el archivo',
                        'Si ya hay factura o guía, lo abres o lo bajas desde la fila. Si no ves Enviar formulario ni Subir, esta vista es de consulta: esos envíos se hacen en el cierre, no desde aquí.',
                        'Recorta una fila con el archivo de factura o guía ya cargado (ícono de bajar). Datos ficticios.'
                    ),
                ]
            ),
        ];
    }

    private function pasosCargaDocumentacion($consulta)
    {
        if ($consulta) {
            return [
                $this->pasoCarga(
                    'Clientes',
                    'Aquí consultas la ficha y los papeles de cada cliente. En Completados no se edita.',
                    [
                        $this->itemFlujo(
                            'Cómo consultar',
                            'El ojo abre la ficha para mirar. Reservado / No reservado se ve, no se cambia. Si no encuentras a alguien, no está en esta carga o un filtro la esconde.',
                            'Recorta la lista de clientes en Completados con el ojo de una fila. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Documentación',
                    'Aquí consultas los papeles del contenedor. El trámite de carga ya cerró: no subes ni quitas archivos.',
                    [
                        $this->itemFlujo(
                            'Qué puedes hacer',
                            'Recorres las carpetas y, si ya hay archivo, lo bajas. Factura General te deja bajar el Excel procesado si las carpetas Factura Comercial, Packing List y Lista de Partidas ya tenían Excel cuando se armó. Descargar plantillas baja el paquete de lo que ya está. No puedes adjuntar, Guardar ni crear un tipo de papel nuevo.',
                            'Recorta las carpetas en modo consulta y el botón Factura General. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Aduana',
                    'Aquí consultas canal, naviera, levante, DUA y multa.',
                    [
                        $this->itemFlujo(
                            'Solo lectura',
                            'Los datos se ven. Guardar no aplica: el trámite ya cerró. Esto no es el maestro de normas: es el seguimiento de esta carga.',
                            'Recorta el formulario de Aduana en Completados (naviera, canal, DUA). Datos ficticios.'
                        ),
                    ]
                ),
            ];
        }

        return [
            $this->pasoCarga(
                'Clientes',
                'Aquí completas la ficha y los papeles de cada cliente de esa carga. No es el directorio general: solo las personas de este contenedor.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves la lista de esa carga. Buscar encuentra la fila. El ojo abre la ficha. Si alguien no aparece, no está en esta carga o un filtro la esconde.',
                        'Recorta el listado de clientes con buscador y el ojo de una fila. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Reservado / No reservado y Status',
                        'Reservado / No reservado indica si esa persona tiene cupo. Status (Completado, Pendiente, Incompleto) dice cómo van sus papeles. Ambos se guardan al elegir: no hace falta otro botón.',
                        'Recorta las listas Reservado y Status de una fila. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'La ficha',
                        'Guardar cambios deja lo editado; si sales sin guardar, se pierde. Descargar Excel baja la confirmación. Nuevo documento agrega un papel de esa persona; Guardar sube el archivo. Sin Guardar, el archivo no queda.',
                        'Recorta la ficha abierta con Guardar cambios y el recuadro de documentos. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Documentación',
                'Aquí cargas los papeles de toda la carga (carpetas del contenedor, no la ficha de cada cliente). Tres de esas carpetas, en Excel, son las que permiten armar la Factura General. Ese archivo es el que más adelante se sube para generar la plantilla general, revisarla y, al subirla como plantilla final, las cotizaciones finales.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Al entrar ves una carpeta por tipo de papel. En cada una puedes elegir o arrastrar un archivo (PDF, Word o Excel, según el caso). El archivo aparece en la lista, pero no queda grabado hasta que pulsas Guardar y ves el aviso de éxito. Si sales sin Guardar, se pierde lo que soltaste.',
                        'Recorta el grid de carpetas con recuadros para adjuntar. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Las tres carpetas que tienen que ser Excel',
                        'Factura Comercial, Packing List y Lista de Partidas: esas tres, con esos nombres exactos, tienen que tener un Excel (.xlsx o .xls), no un PDF ni un Word. Sin las tres guardadas, Factura General no arma nada. Una carpeta nueva con un nombre parecido (Nuevo documento) no las sustituye.',
                        'Recorta las tres carpetas Factura Comercial, Packing List y Lista de Partidas. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Cómo completarlas',
                        'Abre la carpeta vacía, suelta el Excel, pulsa Guardar y espera el aviso. Repite en las tres. Si te equivocaste, en la mayoría puedes quitar el archivo y subir el correcto; la primera carpeta (lista de embarque) no se borra. El tacho pide confirmación: no hay deshacer.',
                        'Recorta una carpeta al guardar el Excel (archivo + Guardar). Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Factura General',
                        'Cuando las tres están, pulsa Factura General. No te entrega el archivo crudo de la carpeta: junta esos tres Excel con los datos de la carga y te baja un Excel ya procesado. Guárdalo en tu computadora. Ese archivo es el que después se sube en Cotización final (Subir Factura), con él se arma la Plantilla General, esa plantilla se revisa en tu PC y, al subirla como Plantilla Final, se generan las cotizaciones finales de cada cliente.',
                        'Recorta la barra superior con el botón Factura General. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Si el botón falla',
                        'El sistema no encontró una de esas tres carpetas o el archivo no es Excel. Completa lo que falta, pulsa Guardar otra vez y reintenta. Descargar plantillas no sirve para este fin: ese botón solo empaqueta lo ya subido, no arma la factura procesada.',
                        'Recorta el aviso de error al bajar Factura General (si lo tienes) o el botón junto a una carpeta vacía. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Descargar plantillas y Nuevo documento',
                        'Descargar plantillas baja un paquete con todo lo ya subido, para archivar o revisar. Nuevo documento crea otra carpeta: escribes el nombre, adjuntas el archivo (máximo 1 MB) y se agrega al expediente. Úsalo si falta un tipo de papel que aún no está; no lo uses para “reemplazar” Factura Comercial, Packing List o Lista de Partidas.',
                        'Recorta los botones Descargar plantillas y Nuevo documento, o el recuadro Crear documento. Datos ficticios.'
                    ),
                ]
            ),
            $this->pasoCarga(
                'Aduana',
                'Aquí registras cómo va el trámite aduanero de esa carga. No es el maestro de normas ni las carpetas de Documentación.',
                [
                    $this->itemFlujo(
                        'Cómo se ve y cómo interactúas',
                        'Es un formulario de esta carga. Naviera y tipo de contenedor (toneladas) se eligen en lista; no se escriben a mano. Canal de control también es lista. El resto se escribe.',
                        'Recorta la parte alta del formulario: Naviera, toneladas y Canal de control. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Fechas, DUA, multa y valores',
                        'Completa zarpe, arribo, levante, declaración, número DUA, multa, FOB, flete, costo destino, ajuste de valor y observaciones. Si un dato aún no existe, déjalo vacío; no inventes un DUA.',
                        'Recorta el bloque de fechas, DUA y multa. Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Archivos de tributos e impuestos',
                        'DOC. TRIBUTOS Y AJUSTES y RESUMEN DE IMPUESTOS PAGADOS admiten PDF, Word o Excel. Suelta el archivo; si está mal, lo quitas y subes otro.',
                        'Recorta los dos recuadros de archivos (tributos e impuestos). Datos ficticios.'
                    ),
                    $this->itemFlujo(
                        'Guardar',
                        'Pulsa Guardar (o Actualizar si ya había datos). Si sales sin guardar, se pierde lo último que escribiste. Esto no mueve la carga a Completados por sí solo.',
                        'Recorta el botón Guardar / Actualizar al pie del formulario. Datos ficticios.'
                    ),
                ]
            ),
        ];
    }

    private function pasosCargaPorRol($abiertos)
    {
        $consultaDoc = !$abiertos;

        return [
            'cotizador' => [
                $this->pasoCarga(
                    'Cotización',
                    'Aquí armas la cotización de esa carga: prospectos, proveedores y productos. Sin prospecto no hay fila para cotizar.',
                    [
                        $this->itemFlujo(
                            'Cómo se ve y cómo interactúas',
                            'Pestañas Prospectos y Por Embarcar. Buscar y filtros solo encuentran la fila. Crear Prospecto está arriba: da de alta a alguien que quiere entrar en esta carga. Completa el recuadro y confirma; si sales a medias, no se crea.',
                            'Recorta Prospectos con el botón Crear Prospecto y la tabla. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Subir o quitar la cotización',
                            'En la fila adjuntas el archivo de esa cotización. Si ya hay uno, el tacho lo quita para que subas otro. Sin archivo, el cliente no tiene cotización para firmar. Tiene que ser el documento de esa persona, no un Excel de otra carga.',
                            'Recorta una fila con el ícono de subir cotización y el tacho. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Firma: recordatorio y enlace',
                            'Recordatorio de firma le pide al cliente que firme. Confirma el recuadro; no lo pulses si ya firmó. Copiar enlace de firma es para mandárselo por WhatsApp u otro medio: solo si ya hay contrato o enlace.',
                            'Recorta los íconos de recordatorio y copiar enlace de firma. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Papeles y eliminar',
                            'El ojo abre los papeles de esa cotización. Eliminar saca esa cotización si ya no va en la carga: pide confirmación y no hay deshacer. No elimines para “corregir” un archivo: quita el archivo y sube otro.',
                            'Recorta una fila con el ojo y Eliminar. Datos ficticios.'
                        ),
                    ]
                ),
            ],
            'coordinacion' => $this->pasosCargaCoordinacion(),
            'documentacion' => $this->pasosCargaDocumentacion($consultaDoc),
            'jefe-importacion' => $this->pasosCargaDocumentacion($consultaDoc),
            'jefe-marketing' => [
                $this->pasoCarga(
                    'Cotización',
                    'Aquí ves cómo va la cotización: prospectos y proveedores. No cambias datos.',
                    [
                        $this->itemFlujo(
                            'Consultar',
                            'El ojo abre los papeles de esa fila para consultarlos. No verás Crear Prospecto ni subir archivo: si un botón no aparece, es porque esta vista es solo para mirar.',
                            'Recorta Prospectos en modo consulta (sin Crear Prospecto) y el ojo de una fila. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Clientes',
                    'Aquí ves quién está en la carga y cómo van sus papeles.',
                    [
                        $this->itemFlujo(
                            'Consultar',
                            'El ojo abre la ficha para consultar. Reservado / No reservado se ve; no se cambia desde aquí.',
                            'Recorta la lista de clientes con Reservado visible y el ojo. Datos ficticios.'
                        ),
                    ]
                ),
            ],
            'finanzas' => $abiertos ? [] : [
                $this->pasoCarga(
                    'Plantillas finales',
                    'Aquí ves las plantillas ya generadas de esa carga. No las armas desde esta pantalla.',
                    [
                        $this->itemFlujo(
                            'Cuándo aparece la carga',
                            'La carga aparece en Completados cuando esas plantillas existen. Si no está, todavía no se generaron: no hay un botón aquí para crearlas.',
                            'Recorta Completados con una carga que ya tiene plantillas. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Descargar, ZIP y Detalle',
                            'Descargar baja la plantilla de esa fila. Descargar ZIP baja el lote completo. Detalle muestra quién salió bien y quién tuvo error. Si dice En proceso, espera: Descargar no aparece hasta que termine.',
                            'Recorta el historial con Descargar, ZIP y Detalle. Datos ficticios.'
                        ),
                    ]
                ),
            ],
            'administracion' => [
                $this->pasoCarga(
                    'Clientes',
                    'Aquí revisas ficha y papeles de cada cliente para el cierre. No armas la cotización desde aquí.',
                    [
                        $this->itemFlujo(
                            'Ficha y firma',
                            'El ojo abre la ficha. Recordatorio de firma le pide que firme si aún falta; confirma el recuadro. No lo pulses si ya firmó.',
                            'Recorta la lista de clientes con el ojo y el recordatorio de firma. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Documentación',
                    'Aquí bajas los papeles del contenedor que necesitas para el cierre. No cambias las carpetas.',
                    [
                        $this->itemFlujo(
                            'Descargar plantillas',
                            'Baja el paquete de lo ya subido en esta carga. No es Factura General ni genera cotizaciones finales: es para archivar o revisar.',
                            'Recorta el botón Descargar plantillas en Documentación. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Cotización',
                    'Aquí sigues los pagos de esa cotización. La pestaña Pagos es la que te toca.',
                    [
                        $this->itemFlujo(
                            'Pagos',
                            'Ves importe, pagado y diferencia. Recordatorio de pago avisa al cliente que tiene un saldo: confirma el envío. Descargar Embarque baja el Excel de lo embarcado si lo necesitas; si no hay datos, no genera archivo.',
                            'Recorta la pestaña Pagos con recordatorio de pago y, si se ve, Descargar Embarque. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Cotización final',
                    'Aquí cierras el cobro de la carga. No armas la plantilla general ni el lote de cotizaciones finales.',
                    [
                        $this->itemFlujo(
                            'Pestaña Pagos',
                            'Es para el cobro: ves saldos y puedes mandar recordatorio de pago. Confirma el recuadro. La pestaña General te sirve para consultar, no para Subir Factura ni Plantilla Final.',
                            'Recorta Cotización final en Pagos (saldos y recordatorio). Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Entrega',
                    'Aquí dejas la constancia de entrega al cliente. No es la recepción en China.',
                    [
                        $this->itemFlujo(
                            'Agenda y formulario',
                            'Fechas y Horarios: ves o completas la agenda (provincia se habilita al elegir departamento). Guarda antes de salir. Enviar formulario manda el formulario al cliente: elige destinatarios; si no hay, no envía.',
                            'Recorta Entrega con Fechas y Horarios y Enviar formulario. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Plantillas y firmar cargo',
                            'Descargar plantillas baja los formatos de esta etapa. Firmar cargo de entrega deja la constancia de esa fila. El tacho quita el registro si ya no va (pide confirmación).',
                            'Recorta una fila con firmar cargo y Descargar Plantillas. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Factura y guía',
                    'Aquí emites o envías factura y guía de esa carga.',
                    [
                        $this->itemFlujo(
                            'Enviar formulario',
                            'Manda al cliente el formulario de comprobantes. Elige a quiénes. Si no hay destinatario, no envía.',
                            'Recorta el botón Enviar formulario en Factura y guía. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Subir factura o guía',
                            'Subir adjunta factura electrónica o guía de remisión. Si ya hay archivo, lo bajas o lo quitas para cargar otro. El tacho pide confirmación. Desde la fila también puedes enviarlo por WhatsApp cuando el archivo ya está.',
                            'Recorta una fila con Subir factura, Subir guía y el envío. Datos ficticios.'
                        ),
                    ]
                ),
            ],
            'contenedor-almacen' => $abiertos ? [
                $this->pasoCarga(
                    'Marcar contactado',
                    'Para dejar constancia de que ya hablaste con ese proveedor.',
                    [
                        $this->itemFlujo(
                            'Status C',
                            'En Status de esa fila elige C. Se guarda al elegir; no hace falta otro botón. NC es si todavía no lo contactas. WAIT si estás a la espera. No marques C si aún no te contestó.',
                            'Recorta la columna Status de un proveedor con C / NC / WAIT. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Marcar inspeccionado',
                    'Para dejar constancia de que ya revisaste la mercancía de ese proveedor.',
                    [
                        $this->itemFlujo(
                            'Status INSPECTION',
                            'En Status elige INSPECTION. Se guarda al elegir. El ojo de la fila abre los papeles de ese proveedor si necesitas verlos. No marques inspeccionado si aún no revisaste.',
                            'Recorta Status en INSPECTION y el ojo de la fila. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Registrar lo que llegó',
                    'Para anotar cajas, pallets y fecha de llegada en China.',
                    [
                        $this->itemFlujo(
                            'QTY Box, Pallet y fecha',
                            'Escribe QTY Box, QTY Pallet y Arrive Date en la parte de Supplier (China). Guardar (el disquete de esa fila) deja esos números grabados. Si no pulsas el disquete, los números no quedan.',
                            'Recorta QTY Box, QTY Pallet, Arrive Date y el disquete de una fila. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Cerrar el contenedor',
                    'Para dar por terminada la recepción de esa carga. Al cerrar, la verás en Completados (Finish).',
                    [
                        $this->itemFlujo(
                            'LOADED y Packing List',
                            'Cuando el proveedor ya cargó, en Status elige LOADED. Upload abre el panel. Packing List es para subir la lista: eso cierra la carga y pasa a Completados. Si el archivo está mal, el tacho lo quita y subes otro. No subas el Packing List si el contenedor aún no está listo: no hay un “deshacer cierre” desde Abiertos.',
                            'Recorta Upload / Packing List y Status LOADED. Datos ficticios.'
                        ),
                    ]
                ),
            ] : [
                $this->pasoCarga(
                    'Consultar una carga cerrada',
                    'Para ver cómo quedó la recepción de una carga que ya está en Finish.',
                    [
                        $this->itemFlujo(
                            'Solo consulta',
                            'El ojo abre la misma pantalla. Status, cajas y Packing List se ven; ya no cierras de nuevo ni cambias el Status para volver a Abiertos.',
                            'Recorta una carga en Completados (Finish) con Status y Packing List visibles. Datos ficticios.'
                        ),
                    ]
                ),
            ],
            'contabilidad' => [
                $this->pasoCarga(
                    'Cotización',
                    'Aquí sigues los pagos de esa cotización. No armas prospectos desde aquí.',
                    [
                        $this->itemFlujo(
                            'Pestaña Pagos',
                            'Ves importe, pagado y diferencia. Recordatorio de pago avisa al cliente que tiene un saldo: confirma el envío. Descargar Embarque baja el Excel si lo necesitas; si no hay datos, no genera archivo. Puedes exportar el Excel de pagos cuando estás en esta pestaña.',
                            'Recorta Pagos con recordatorio, importes y el botón de exportar si se ve. Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Cotización final',
                    'Aquí cierras el cobro. No armas la plantilla general ni el lote de cotizaciones finales.',
                    [
                        $this->itemFlujo(
                            'Cobro',
                            'La pestaña Pagos es la primera: registras o sigues lo cobrado y puedes mandar recordatorio. Confirma cada envío. Cargos extra se consulta si aparece. General es para mirar el detalle, no para Subir Factura.',
                            'Recorta Cotización final en Pagos (saldos). Datos ficticios.'
                        ),
                    ]
                ),
                $this->pasoCarga(
                    'Factura y guía',
                    'Aquí emites o envías factura y guía de esa carga.',
                    [
                        $this->itemFlujo(
                            'Enviar formulario',
                            'Manda al cliente el formulario. Elige destinatarios; si no hay, no envía.',
                            'Recorta Enviar formulario en Factura y guía. Datos ficticios.'
                        ),
                        $this->itemFlujo(
                            'Subir y enviar',
                            'Subir adjunta factura electrónica o guía. Si ya hay archivo, lo bajas o lo quitas (pide confirmación). Cuando el archivo está, puedes enviarlo por WhatsApp desde la fila.',
                            'Recorta una fila con Subir factura, Subir guía y WhatsApp. Datos ficticios.'
                        ),
                    ]
                ),
            ],
        ];
    }

    private function aduanasScreens()
    {
        return [
            'basedatos/productos' => [
                'modulo_key' => 'basedatos/productos',
                'titulo' => 'Aduanas — Productos',
                'descripcion' => '{rol} → Aduanas → Productos',
                'articulo_titulo' => 'Productos',
                'articulo_clave' => '/basedatos/productos',
                'tags' => ['Módulo: Aduanas', 'producto', 'historial', 'importación'],
                'que_es' => 'El historial de productos importados: catálogo con foto, nombre comercial, rubro, tipo, unidad, subpartida y la campaña (carga) a la que pertenecen.',
                'para_que' => 'Consultar cómo se clasificó un producto en cargas anteriores (tributos y requisitos de aduana) para cotizar o tramitar con criterio. Documentación además carga el catálogo por Excel, corrige la ficha y cambia la foto.',
                'quien' => 'Rol {rol}. Cotizador, Coordinación, Contabilidad y Jefe Importación consultan y exportan. Solo Documentación ve “Importar productos”, cambia la foto al pasar el cursor, edita la ficha y ve Eliminar en la fila.',
                'cuando' => 'Cuando necesitas saber si un ítem es libre o restringido, qué arancel o antidumping aplicó, o (Documentación) cuando llega un Excel de productos de una campaña.',
                'flows' => [
                    [
                        'titulo' => 'Consultar y filtrar',
                        'steps' => [
                            $this->itemFlujo(
                                'Entrar al historial',
                                'Entra a Productos. Ves Historial de productos importados y el total. No es el catálogo de la calculadora: es lo ya importado por campaña.',
                                'Recorta el encabezado Historial de productos y el total. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Buscar y filtrar',
                                'En Buscar producto escribe el nombre comercial o la subpartida. En Filtros: Rubro, Tipo o Campaña. Todos quita ese filtro. No cambia el producto: solo recorta la lista.',
                                'Recorta Buscar producto, Filtros y dos filas. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Foto y exportar',
                                'Si hay foto, pulsa la miniatura para verla grande. Exportar baja el Excel de lo filtrado, no de toda la base si hay filtro activo.',
                                'Recorta una miniatura abierta y el botón Exportar. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Ver la ficha',
                        'steps' => [
                            $this->itemFlujo(
                                'Ojo y observaciones',
                                'Pulsa el ojo: entras a la ficha (tributos, requisitos, enlace). Regresar vuelve al listado. La campana de la fila abre observaciones de aduana; si no hay, indica que no hay observaciones. Si no ves Editar, esta vista solo consulta.',
                                'Recorta la ficha del producto (tributos) y, si se ve, la campana. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Editar la ficha',
                        'steps' => [
                            $this->itemFlujo(
                                'Editar y tributos',
                                'En la ficha pulsa Editar (si no está, no te toca corregir). No hay lápiz en la tabla: primero Ver, luego Editar. Arancel Sunat, TLC, Correlativo y Antidumping son listas. Si Antidumping es SI, escribe el valor o no guarda.',
                                'Recorta el formulario de tributos con Antidumping. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Requisitos y guardar',
                                'Tipo: LIBRE o RESTRINGIDO (si es restringido, elige entidad). Etiquetado: NORMAL o ESPECIAL (si es especial, elige el tipo). Documento especial SI/NO. Si hay comentario, activa Observaciones de aduana y escribe. Completa características. Guardar aplica; Cancelar no. Si sales sin Guardar, se pierde.',
                                'Recorta requisitos (LIBRE/RESTRINGIDO) y el botón Guardar. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Cambiar la foto',
                        'steps' => [
                            $this->itemFlujo(
                                'Cámara en la miniatura',
                                'En el listado, pasa el cursor sobre la foto y pulsa la cámara. Elige JPG, PNG o WebP y espera a que termine. Si no ves la cámara, no cambias fotos desde aquí.',
                                'Recorta la miniatura con el ícono de cámara. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Importar el catálogo',
                        'steps' => [
                            $this->itemFlujo(
                                'Excel de productos',
                                'Pulsa Importar productos y luego Importar Excel de Productos. Sube .xlsx / .xls / .xlsm (máx. 10 MB) con las columnas del recuadro. Confirma Importar Excel. El archivo queda en el historial. Puedes descargarlo o quitarlo con la papelera de esa tabla. Si no ves Importar, esta vista no carga el catálogo.',
                                'Recorta Importar productos y el recuadro de columnas / archivo. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Foto', 'Del Excel o la cambia Documentación en el listado', 'miniatura del ítem'],
                    ['Nombre comercial', 'Excel / ficha', 'Mochila escolar 20 L'],
                    ['Rubro', 'Excel; también filtro', 'Textil'],
                    ['Tipo de producto', 'Lista desplegable en la ficha: LIBRE o RESTRINGIDO. Luego pulsa Guardar.', 'LIBRE'],
                    ['Correlativo', 'Lista desplegable sí/no en la ficha. Luego Guardar.', 'NO'],
                    ['Antidumping y valor', 'Lista desplegable SI/NO. Si es SI, escribe el valor. Luego Guardar.', 'NO'],
                    ['Entidad', 'Lista desplegable. Obligatoria si el tipo es RESTRINGIDO.', 'DIGESA'],
                    ['Etiquetado / tipo', 'Listas desplegables: NORMAL o ESPECIAL (tipo obligatorio si es especial).', 'NORMAL'],
                    ['Documento especial', 'Lista desplegable SI / NO.', 'NO'],
                    ['Unidad comercial', 'Excel', 'unidad'],
                    ['Subpartida', 'Excel; se puede buscar', '4202.92.00.00'],
                    ['Campaña', 'Carga del contenedor + año de cierre', '#101-2026'],
                    ['Año', 'Año de cierre de la carga', '2026'],
                    ['Enlace producto', 'Ficha (editable por Documentación)', 'www.alibaba.com/...'],
                    ['Arancel Sunat / TLC', 'Ficha, tributos', '6% / 0%'],
                    ['Observaciones de aduana', 'Si el interruptor está activo, el texto es obligatorio', 'El visado observó la medida'],
                    ['Características', 'Lista en la ficha', 'Tela oxford, cierre YKK'],
                ],
                'consideraciones' => "Este listado no es el catálogo de la calculadora de cotización: es el historial de productos ya importados por campaña.\n\nCotizador y el resto consultan para orientar al cliente; no reemplaza el trámite de Documentación.\n\nEl botón Eliminar de la fila solo lo ve Documentación. Quitar un lote importado se hace en Importar productos (papelera del archivo). Si Eliminar en la fila no responde, usa ese historial de archivos o avisa a soporte.\n\nAl editar: si Antidumping es SI hay que poner valor; si el tipo es RESTRINGIDO hay que elegir entidad; si el etiquetado es ESPECIAL hay que elegir el tipo.",
                'errores' => [
                    ['No encuentro el producto', 'Nombre distinto o filtro de rubro/campaña activo', 'Busca por un trozo del nombre o por subpartida; pon Rubro y Campaña en Todos'],
                    ['No veo “Importar productos” ni Editar', 'Tu rol no es Documentación', 'Consulta y exporta; pide a Documentación que cargue o corrija el catálogo'],
                    ['No guarda la ficha', 'Falta valor de antidumping, entidad o tipo de etiquetado según lo elegido', 'Completa el campo extra que aparece al lado (SI / RESTRINGIDO / ESPECIAL)'],
                    ['La foto no cambia', 'Archivo que no es imagen o se canceló la ventana', 'Usa JPG, PNG o WebP y espera a que desaparezca el ícono de carga'],
                    ['Falla al importar Excel', 'Extensión distinta a xlsx/xls/xlsm o archivo vacío', 'Respeta las columnas del recuadro y el tamaño máximo'],
                ],
                'ejemplo' => 'Producto ficticio “Mochila escolar 20 L”, rubro Textil, tipo LIBRE, subpartida 4202.92.00.00, campaña #101-2026. Documentación abre la ficha, deja Antidumping en NO y guarda. Cotizador busca “mochila”, ve la foto y exporta el listado filtrado.',
                'resultado' => 'encuentras el producto en el historial, ves tributos y requisitos en la ficha y, si eres Documentación, el Excel o la corrección quedan reflejados en la tabla.',
                'ver_tambien' => 'Regulaciones · Permisos · Boletín químico · Cotizador · Carga consolidada.',
            ],
            'basedatos/regulaciones' => [
                'modulo_key' => 'basedatos/regulaciones',
                'titulo' => 'Aduanas — Regulaciones',
                'descripcion' => '{rol} → Aduanas → Regulaciones',
                'articulo_titulo' => 'Regulaciones',
                'articulo_clave' => '/basedatos/regulaciones',
                'tags' => ['Módulo: Aduanas', 'antidumping', 'etiquetado', 'permisos', 'documentos especiales'],
                'que_es' => 'La pantalla “Regulación aduanera”. Cuatro pestañas: Antidumping, Permisos, Etiquetado y Doc. Especiales. A la izquierda hay categorías (producto o entidad) y a la derecha el detalle de esa categoría.',
                'para_que' => 'Consultar las reglas que aplican a un tipo de mercancía (aranceles antidumping, permisos de entidad, cómo etiquetar, documentos extras). Documentación además crea y corrige esas reglas.',
                'quien' => 'Rol {rol}. Cotizador, Coordinación, Contabilidad y Jefe Importación consultan (Ver, descargar, ver fotos). Solo Documentación ve Crear, lápiz y papelera.',
                'cuando' => 'Antes de cotizar o tramitar un ítem dudoso, o cuando Documentación debe cargar una norma nueva.',
                'flows' => [
                    [
                        'titulo' => 'Consultar',
                        'steps' => [
                            $this->itemFlujo(
                                'Pestaña y categoría',
                                'Entra a Regulaciones. Elige Antidumping, Permisos, Etiquetado o Doc. Especiales. Pulsa una categoría a la izquierda (borde naranja). A la derecha se abre el detalle. Si la derecha está vacía, no hay categoría elegida o aún no hay normas.',
                                'Recorta las cuatro pestañas y la lista izquierda con una categoría marcada. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Ver detalle',
                                'En Antidumping o Permisos, el ojo de una fila abre fotos/documentos y observaciones. En Etiquetado y Doc. Especiales amplías imágenes o descargas con la flecha. Esto no es el menú Permisos de trámites de una carga: aquí son las normas.',
                                'Recorta el detalle de la derecha (tabla o archivos). Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear una categoría',
                        'steps' => [
                            $this->itemFlujo(
                                'Regulación rápida',
                                'Si ves Regulación (junto a Crear) en Antidumping o Permisos, escribe el nombre (producto o entidad) y confirma. En Etiquetado o Doc. Especiales ese botón no aparece: el producto se crea dentro de Crear, con Crear Producto. Si no ves Crear, solo consultas.',
                                'Recorta el botón Regulación o Crear Producto. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear la regulación completa',
                        'steps' => [
                            $this->itemFlujo(
                                'Formulario Crear',
                                'Pulsa Crear. Completa los * de la pestaña activa (producto/entidad, montos, fotos o documentos). Guardar pide confirmación. Sin los obligatorios no guarda. Eliminar después no se deshace.',
                                'Recorta el formulario Crear de la pestaña activa. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Corregir o quitar',
                        'steps' => [
                            $this->itemFlujo(
                                'Lápiz y papelera',
                                'A la izquierda: lápiz para renombrar, papelera para quitar la categoría (Antidumping y Permisos). En la tabla: lápiz edita; papelera pide confirmación y no se deshace. Si no ves lápiz, no corriges desde aquí.',
                                'Recorta lápiz y papelera de una categoría o fila. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Pestaña', 'La elige el usuario', 'Antidumping'],
                    ['Categoría (lista izquierda)', 'Producto (antidumping, etiquetado, docs) o Entidad (permisos)', 'Zapatillas / DIGESA'],
                    ['Descripción (antidumping)', 'Documentación', 'Calzado deportivo de lona'],
                    ['Partida', 'Documentación', '6404.11.00.00'],
                    ['P. Declaración / Antidumping', 'Montos en dólares en la tabla', '$12.00 / $3.50'],
                    ['Nombre del permiso', 'Pestaña Permisos', 'Registro sanitario'],
                    ['C. Permiso / C. Tramitador', 'Soles en la tabla de Permisos', 'S/. 150 / S/. 80'],
                    ['Documentos a presentar', 'Adjuntos en Permisos y Doc. Especiales', 'ficha.pdf'],
                    ['Imágenes / descripciones mínimas', 'Pestaña Etiquetado', 'foto de etiqueta + texto'],
                    ['Observaciones / comentarios', 'Texto libre en el detalle', 'El visado pide medida en cm'],
                ],
                'consideraciones' => "Esta pantalla no es Aduanas → Permisos (trámites de una carga). Aquí se mantienen las normas; el menú Permisos es el seguimiento de trámites.\n\nNo sustituye el criterio de Documentación: Cotizador usa esto para orientar, no para comprometer al cliente si hay duda.\n\nCrear pide confirmar. Eliminar también: la acción no se deshace.\n\nEl botón “Regulación” (categoría rápida) solo aparece en Antidumping y Permisos, y solo para Documentación.",
                'errores' => [
                    ['No veo Crear ni lápiz', 'Tu rol no es Documentación', 'Consulta con Ver y descarga; pide a Documentación que cargue o corrija la norma'],
                    ['La tabla de la derecha está vacía', 'No hay categoría seleccionada o esa categoría aún no tiene regulaciones', 'Pulsa un ítem de la lista izquierda; si sigue vacío, Documentación debe usar Crear'],
                    ['En Doc. Especiales no veo el detalle', 'El producto de la izquierda no tiene documentos cargados', 'Documentación pulsa Crear, elige ese producto y adjunta los archivos'],
                    ['No guarda el formulario', 'Falta el producto/entidad u otro campo marcado con *', 'Completa los obligatorios y vuelve a Guardar'],
                    ['Confundo con Aduanas → Permisos', 'Son dos menús distintos', 'Regulaciones = normas. Permisos del menú = trámites de consolidado'],
                ],
                'ejemplo' => 'Cotizador abre Regulaciones → Antidumping, elige “Zapatillas deportivas” (dato ficticio), ve partida 6404.11.00.00 y antidumping $3.50, y usa eso al armar la cotización. Documentación, si la norma es nueva, pulsa Crear, asocia el producto, carga fotos y guarda.',
                'resultado' => 'ves la norma de la pestaña correcta (tabla, fotos o archivos) y, si eres Documentación, la categoría o la regulación queda creada o actualizada.',
                'ver_tambien' => 'Productos · Permisos (trámites) · Boletín químico · Cotizador.',
            ],
            'basedatos/permisos' => [
                'modulo_key' => 'basedatos/permisos',
                'titulo' => 'Aduanas — Permisos',
                'descripcion' => '{rol} → Aduanas → Permisos',
                'articulo_titulo' => 'Permisos',
                'articulo_clave' => '/basedatos/permisos',
                'tags' => ['Módulo: Aduanas', 'trámite', 'entidad', 'documentos', 'pagos'],
                'que_es' => 'El seguimiento de trámites de permiso por consolidado y cliente: entidad, tipos de permiso, montos (derecho, tramitador, servicio), fechas y estado. El ojo abre los documentos, el seguimiento y los pagos de ese trámite.',
                'para_que' => 'Ver en qué va cada permiso de una carga y, si tu rol lo permite, abrir el trámite, cargar archivos y registrar pagos.',
                'quien' => 'Rol {rol}. Documentación, Coordinación y Jefe Importación ven “Crear permiso”, lápiz, papelera y pueden subir archivos (Guardar todo). Administración, Cotizador y Contabilidad consultan el listado y los documentos. El estado de cada tipo de permiso se cambia en la tabla.',
                'cuando' => 'Cuando un cliente de una carga abierta necesita un permiso de entidad (DIGESA, SENASA, etc.) y hay que abrirlo, documentarlo o consultar el avance.',
                'flows' => [
                    [
                        'titulo' => 'Consultar',
                        'steps' => [
                            $this->itemFlujo(
                                'Listado y filtros',
                                'Entra a Permisos (trámites de carga, no las normas). Busca cliente, RUC, entidad o tipo. Filtro Estado: Pendiente, SD, Pagado, En tramite, Rechazado o Completado. Todos quita el filtro. El ojo abre documentos, seguimiento y pagos. El lápiz (si lo ves) edita montos y tipos, no es Ver.',
                                'Recorta la tabla Permisos con buscador, filtro Estado y el ojo. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear un trámite',
                        'steps' => [
                            $this->itemFlujo(
                                'Consolidado, cliente y entidad',
                                'Si ves Crear permiso, elige consolidado (*) y luego cliente (*) (el cliente no se habilita hasta que hay carga). Elige entidad (*). El + crea el nombre; el lápiz renombra; la papelera oculta del catálogo (no borra trámites ya creados). Si no ves Crear, solo consultas.',
                                'Recorta Crear trámite: consolidado, cliente y entidad. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Tipos, montos y Guardar',
                                'En cada fila: tipo de permiso (*) y Derecho (S/.) (*). El + crea un tipo; Agregar tipo de permiso suma otra fila. Completa Tramitador si aplica y Precio (*). Guardar se apaga si falta un *. El consolidado solo lista cargas que aún no cerraron documentación.',
                                'Recorta las filas de tipo de permiso, Derecho y Precio. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Documentos, seguimiento y pagos',
                        'steps' => [
                            $this->itemFlujo(
                                'Tres secciones',
                                'El ojo abre el trámite. Si hay varios tipos, cámbialos con las pestañas. 1 Documentos: sube el archivo de cada categoría; Nuevo documento pide nombre y archivo (queda pendiente hasta Guardar todo). 2 Seguimiento: adjuntos, fecha Caduca y, si aplica, RH o factura del tramitador. 3 Pagos: + voucher (Monto, Banco, F. cierre). Si no ves Guardar todo, ves y descargas; no subes ni borras.',
                                'Recorta las secciones 1, 2 y 3 o el botón Guardar todo. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Estado, editar o quitar',
                        'steps' => [
                            $this->itemFlujo(
                                'Estado en la tabla',
                                'Pulsa el recuadro de Estado de ese tipo y elige. Al subir ciertos documentos el estado puede subir solo (Expediente CPB → En tramite; Decreto u Hoja resumen → Completado). Rechazado no se pisa solo. Lápiz edita. Papelera: Eliminar trámite no se deshace.',
                                'Recorta el recuadro de Estado y lápiz/papelera de una fila. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Cliente', 'Del consolidado elegido al crear', 'María Pérez — 20123456789'],
                    ['Consolidado', 'Carga aún sin documentación cerrada', '#101'],
                    ['Entidad', 'Catálogo; se puede crear con +', 'DIGESA'],
                    ['T. Permiso', 'Uno o más por trámite', 'Registro sanitario'],
                    ['Derecho tramite', 'Soles por tipo; el color compara con comprobantes', 'S/. 150.00'],
                    ['Tramitador', 'Soles del trámite (compartido)', 'S/. 80.00'],
                    ['Servicio', 'Lo pagado vs el Precio; verde si cubre y está confirmado', 'S/. 200.00'],
                    ['Precio (S/.)', 'Al crear/editar, obligatorio', '200.00'],
                    ['F. Inicio', 'Se marca al subir un documento “Expediente CPB”', '12-08-2026'],
                    ['F. Termino', 'Se marca al subir “Decreto” u “Hoja resumen”', '20-08-2026'],
                    ['F. Caducidad', 'Calendario Caduca en Seguimiento', '20-02-2027'],
                    ['Días', 'Diferencia entre término e inicio, cuando ambas fechas existen', '8'],
                    ['Estado', 'Por tipo de permiso, en la tabla o al subir ciertos documentos', 'En tramite'],
                    ['Documentos / fotos', 'Pantalla del ojo, sección 1', 'expediente.pdf'],
                    ['Pago (voucher)', 'Sección 3: monto, banco, F. cierre', 'BCP · S/. 200.00'],
                ],
                'consideraciones' => "Esta pantalla no es la pestaña Permisos de Aduanas → Regulaciones. Aquí se siguen trámites de una carga; allá se mantienen las normas (costos de entidad, documentos tipo).\n\nAl crear, el consolidado solo lista cargas que aún no cerraron documentación. Si no aparece la tuya, esa carga ya no está en ese filtro.\n\nOcultar una entidad o un tipo (papelera del modal) la saca del catálogo; no borra trámites ya creados.\n\nEl color de Derecho, Tramitador y Servicio: gris (nada registrado), ámbar (falta monto o confirmación), verde (cubre).\n\nAl subir documentos, el estado puede subir solo: cualquier documento de trámite → SD; “Expediente CPB” → En tramite (y F. Inicio); “Decreto” u “Hoja resumen” → Completado (y F. Termino). Rechazado no se pisa solo. Borrar el archivo que daba ese estado lo recalcula.\n\nEliminar el trámite pide confirmación y no se deshace.",
                'errores' => [
                    ['No veo “Crear permiso” ni lápiz', 'Tu rol solo consulta (Administración, Cotizador o Contabilidad)', 'Usa buscar, el filtro y el ojo; pide a Documentación, Coordinación o Jefe Importación que abra o corrija el trámite'],
                    ['No aparece el consolidado al crear', 'Esa carga ya cerró documentación o el número no coincide', 'Revisa que la carga siga abierta en documentación; busca el número exacto'],
                    ['No puedo elegir cliente', 'Aún no hay consolidado, o esa carga no tiene clientes', 'Elige primero el consolidado y espera a que cargue la lista'],
                    ['Guardar está apagado', 'Falta consolidado, cliente, entidad, un tipo con derecho o el precio', 'Completa los * y al menos una fila de tipo de permiso'],
                    ['No veo “Guardar todo” en documentos', 'Tu rol no sube archivos', 'Consulta y descarga; el alta de archivos es de Documentación, Coordinación o Jefe Importación'],
                    ['El estado no baja al subir otro archivo', 'El sistema solo asciende (salvo Recalcular al borrar). Rechazado es manual', 'Cámbialo en la tabla o quita el documento que lo dejó en Completado / En tramite'],
                    ['Confundo con Regulaciones → Permisos', 'Son dos menús distintos', 'Este menú = trámites de consolidado. Regulaciones = normas de entidad'],
                ],
                'ejemplo' => 'Trámite ficticio: cliente “María Pérez”, carga #101, entidad DIGESA, tipo “Registro sanitario”, derecho S/. 150, precio S/. 200. Documentación pulsa Crear permiso, guarda, abre el ojo, sube “Expediente CPB” y Guardar todo: aparece F. Inicio y el estado pasa a En tramite. Cotizador busca “Pérez” y ve el recuadro En tramite.',
                'resultado' => 'encuentras el trámite de la carga, ves montos y estado, y, si tu rol edita, el permiso queda creado o los documentos y pagos quedan guardados.',
                'ver_tambien' => 'Regulaciones · Productos · Carga consolidada · Clientes · Verificación.',
            ],

            'basedatos/boletin-quimico' => [
                'modulo_key' => 'basedatos/boletin-quimico',
                'titulo' => 'Aduanas — Boletín químico',
                'descripcion' => '{rol} → Aduanas → Boletín químico',
                'articulo_titulo' => 'Boletín químico',
                'articulo_clave' => '/basedatos/boletin-quimico',
                'tags' => ['Módulo: Aduanas', 'boletín', 'ítems', 'adelantos'],
                'que_es' => 'La pantalla “Boletín Químico”: ítems de una cotización que requieren boletín, por consolidado y cliente, con monto, estado y adelantos (pagos).',
                'para_que' => 'Registrar qué ítems van a boletín y cargar los adelantos (voucher). La confirmación de esos pagos se hace en Verificación, no aquí.',
                'quien' => 'Rol {rol}. Quien entra ve “Nuevo” y puede registrar adelantos. El estado de la fila no se edita a mano. Administración confirma o observa los vouchers en Verificación → Boletín Químico.',
                'cuando' => 'Cuando un cliente de una carga tiene productos con control químico y hay que abrir el boletín o adjuntar un adelanto.',
                'flows' => [
                    [
                        'titulo' => 'Consultar',
                        'steps' => [
                            $this->itemFlujo(
                                'Buscar el boletín',
                                'Entra a Boletín químico. Busca por cliente o consolidado. Ves Items, Monto, Estado y Adelantos. El estado no se cambia en esta tabla: se mueve con los adelantos y con la confirmación en Verificación. Esto no es Verificación: aquí se crea y se sube el voucher.',
                                'Recorta la tabla Boletín (Items, Monto, Estado, Adelantos). Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear (Nuevo)',
                        'steps' => [
                            $this->itemFlujo(
                                'Carga, cliente e ítems',
                                'Pulsa Nuevo. Elige primero el consolidado; después se habilita Cliente. Marca uno o más ítems. Revisa las filas; la papelera quita una. Guardar se activa cuando hay filas. Cancelar cierra sin guardar. No hay lápiz después para cambiar carga o ítems: pendiente de definir si te equivocaste.',
                                'Recorta Nuevo Boletín: consolidado, cliente e ítems. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Registrar un adelanto',
                        'steps' => [
                            $this->itemFlujo(
                                'Voucher',
                                'En Adelantos pulsa +. Completa Monto, Banco (BCP, INTERBANK o YAPE), Fecha Cierre y el archivo. Guardar. Para ver uno ya cargado, pulsa el monto. Aquí no se elimina el pago: pendiente de definir. Administración confirma el voucher en Verificación.',
                                'Recorta Registrar Pago (monto, banco, voucher). Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Consolidado', 'Lista desplegable al crear. Elige la carga; se guarda con el formulario.', '#101'],
                    ['Cliente', 'Lista desplegable al crear. Se habilita después de elegir consolidado.', 'María Pérez'],
                    ['Items', 'Lista para marcar uno o más ítems de la cotización.', 'Alcohol en gel (Cot. 55)'],
                    ['Monto', 'Sale del ítem. Solo lectura en la tabla.', 'S/. 350.00'],
                    ['Estado', 'Lista desplegable bloqueada en esta tabla (Pendiente, Adelanto, Pagado). No se elige a mano.', 'Adelanto'],
                    ['Adelanto: Banco', 'Lista desplegable al registrar el pago: BCP, INTERBANK o YAPE.', 'BCP'],
                    ['Adelanto: Monto / Fecha Cierre / Voucher', 'Se escriben o se adjuntan al pulsar +.', 'S/. 100 · 12-08-2026'],
                ],
                'consideraciones' => "No hay lápiz para editar consolidado, cliente o ítems después de Guardar. Si te equivocaste, pendiente de definir el alta de un registro nuevo y avisar a soporte.\n\nEl color de Pagado pasa a verde cuando el estado es Pagado y todos los adelantos están Confirmado (eso lo marca Administración en Verificación).\n\nEsta pantalla no es Verificación → Boletín Químico: allá se confirman pagos; aquí se crean y se suben adelantos.",
                'errores' => [
                    ['No hay registros', 'Nadie creó un boletín o la búsqueda no coincide', 'Limpia el buscador o pulsa Nuevo'],
                    ['Guardar está apagado', 'No hay filas de ítems', 'Elige consolidado, cliente e ítems'],
                    ['No aparece el cliente', 'Aún no hay consolidado o esa carga no tiene cotización', 'Elige primero la carga'],
                    ['El estado no cambia', 'El select está bloqueado a propósito', 'Sube adelantos aquí; Administración confirma en Verificación'],
                    ['No puedo borrar un adelanto', 'En esta pantalla el detalle no elimina', 'pendiente de definir con Administración / soporte'],
                ],
                'ejemplo' => 'Cliente ficticio “María Pérez”, carga #101, ítem “Alcohol en gel”, monto S/. 350. Documentación pulsa Nuevo, elige la carga y el ítem, guarda. Luego + adelanto S/. 100 BCP. En Verificación, Administración confirma el voucher y el recuadro puede pasar a Pagado.',
                'resultado' => 'el boletín queda en la tabla con sus ítems y, si cargaste adelanto, el voucher aparece en Adelantos.',
                'ver_tambien' => 'Verificación · Permisos · Carga consolidada · Productos.',
            ],
        ];
    }

    private function tablaMaestra($key, $titulo, $clave, $articulo, $queEs, $cuando)
    {
        return [
            'modulo_key' => $key,
            'titulo' => $titulo,
            'descripcion' => '{rol} → ' . $titulo,
            'articulo_titulo' => $articulo,
            'articulo_clave' => $clave,
            'tags' => ['Módulo: Aduanas'],
            'que_es' => $queEs,
            'para_que' => 'Consultar, filtrar y abrir (o editar, si tu rol lo permite) los registros de este maestro.',
            'quien' => 'Rol {rol}. Las acciones de crear o editar pueden estar limitadas: pendiente de definir por cargo.',
            'cuando' => $cuando,
            'flows' => [
                [
                    'titulo' => 'Consultar',
                    'steps' => [
                        $this->itemFlujo(
                            'Abrir el maestro',
                            'Entra a esta opción. Usa buscar y filtros si la tabla es larga: no cambian el registro, solo recortan la lista. Abre un registro con Ver o Editar según los botones de la fila. Si no ves Editar, solo consultas.',
                            'Recorta el listado de este maestro con buscador y el ojo o lápiz de una fila. Datos ficticios.'
                        ),
                    ],
                ],
            ],
            'campos' => [
                ['pendiente de definir', 'Maestro / usuario', 'pendiente de definir'],
            ],
            'consideraciones' => 'Este artículo cubre el listado. Las pantallas de crear/editar (documentos, etiquetado, antidumping) se pueden ampliar después; no inventes un botón que no veas.',
            'errores' => [
                ['No puedo editar', 'El rol solo consulta', 'Pide a un rol con mantenimiento de aduanas'],
            ],
            'ejemplo' => 'pendiente de definir con un dato ficticio acordado con el área.',
            'resultado' => 'encuentras el registro y, si aplica, lo actualizas.',
            'ver_tambien' => 'Carga consolidada · Clientes.',
        ];
    }

    private function opsScreens()
    {
        return (new ManualUsuarioOpsScreens())->all();
    }
}
