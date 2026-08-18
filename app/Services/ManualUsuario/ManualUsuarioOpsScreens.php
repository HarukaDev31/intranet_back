<?php

namespace App\Services\ManualUsuario;

/**
 * Artículos de operación (soporte, noticias, viáticos, calendario, etc.).
 */
class ManualUsuarioOpsScreens
{
    use ManualUsuarioFlowItems;
    public function all()
    {
        return [
            'soporte-ti' => [
                'modulo_key' => 'soporte-ti',
                'titulo' => 'Soporte — tickets',
                'descripcion' => '{rol} → Soporte TI',
                'articulo_titulo' => 'Soporte',
                'articulo_clave' => '/soporte-ti',
                'tags' => ['Módulo: Soporte', 'ticket', 'kanban'],
                'que_es' => 'La bandeja “Soporte TI”: solicitudes a sistemas en tabla y tablero (kanban). El colaborador abre el caso; PM y analista lo gestionan.',
                'para_que' => 'Reportar una incidencia o un requerimiento y seguir el estado (chat y evidencias) hasta que quede operativo.',
                'quien' => 'Rol {rol}. Si eres solicitante ves “Nueva solicitud” y tus tickets. Cargo PM: prioridad, complejidad PM, horas tipo A/B y estados. Cargo Soporte (analista): complejidad analista y estados.',
                'cuando' => 'Cuando algo de la intranet no funciona o necesitas un cambio (proyecto del mes o requerimiento).',
                'flows' => [
                    [
                        'titulo' => 'Crear una solicitud',
                        'steps' => [
                            $this->itemFlujo(
                                'Nueva solicitud',
                                'Entra a Soporte TI y pulsa Nueva solicitud. Si no ves el botón, esta vista gestiona tickets ajenos, no el alta. Tipo A es proyecto del mes; Tipo B es requerimiento (B1 incidencia o B2 configuración). Área es lista (Ventas, Importaciones, Marketing, Administración y Finanzas, RR.HH, CEO). Completa URL, título y objetivo.',
                                'Recorta Nueva solicitud: Tipo, Área, título y URL. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Evidencia y seguimiento',
                                'Adjunta al menos 1 imagen (máx. 5, 10 MB c/u). Sin pantallazo no se crea. Confirma el alta. Sigue el ticket en la tabla o el kanban. En el detalle escribe en el chat y adjunta imagen o documento. El solicitante no cambia prioridad ni complejidad.',
                                'Recorta evidencias al crear y el chat del detalle. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Gestionar (PM / analista)',
                        'steps' => [
                            $this->itemFlujo(
                                'Filtrar y estado',
                                'Filtra por código/título o Tipo. El ojo abre el ticket. El kanban es la misma bandeja en tablero. Estado y complejidad son listas: se guardan al elegir. Estados: Pendiente, En maqueta (solo A), En progreso, Hecho (solo B), Desplegado, Observado, Operativo.',
                                'Recorta el kanban o el detalle con la lista Estado. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Prioridad, horas y evidencia',
                                'Si ves Prioridad y Horas tipo A/B, son tuyas. En tipo A puedes aprobar o rechazar la maqueta. Ver evidencia abre los pantallazos. Responde en el chat. Si no ves esas opciones, no las cambies desde aquí.',
                                'Recorta Prioridad o Ver evidencia. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Código', 'Automático', 'ST-104'],
                    ['Tipo', 'Lista desplegable al crear: A o B (B1 / B2). Luego confirma el alta.', 'Tipo B · B1'],
                    ['Área', 'Lista desplegable al crear.', 'Importaciones'],
                    ['URL / Título / Descripción', 'Se escriben al crear. No son lista desplegable.', 'https://intranet… · No carga el listado'],
                    ['Evidencias', 'Se adjuntan imágenes al crear (mín. 1).', 'pantalla.png'],
                    ['Estado', 'Lista desplegable en el detalle o kanban. Elige; se guarda al instante (PM / analista).', 'En progreso'],
                    ['Prioridad', 'Lista desplegable. Solo PM.', 'Alta'],
                    ['Complejidad PM / Analista', 'Lista desplegable. Solo staff.', 'M3'],
                ],
                'consideraciones' => "El solicitante no cambia prioridad ni complejidad.\n\nEstados del tablero: Pendiente, En maqueta (solo A), En progreso, Hecho (solo B), Desplegado, Observado, Operativo.",
                'errores' => [
                    ['No hay solicitudes que coincidan', 'Filtro de tipo o búsqueda activo', 'Pon Tipo en Todos y limpia el buscador'],
                    ['No se crea', 'Falta URL, título, descripción o pantallazo', 'Completa los obligatorios y al menos una imagen'],
                    ['No existe o no tienes acceso', 'El ticket no es tuyo o el código no existe', 'Revisa el código; si eres staff, busca en Todos'],
                    ['No ves Nueva solicitud', 'Tu cargo es PM o analista en esta pantalla', 'Usa el kanban; el alta es del colaborador'],
                ],
                'ejemplo' => 'Solicitud ficticia ST-104, Tipo B1, área Importaciones, título “No carga Permisos”. El colaborador adjunta un pantallazo. El analista pasa a En progreso y responde en el chat.',
                'resultado' => 'el ticket queda en la bandeja con código y estado, y el chat o la evidencia quedan registrados.',
                'ver_tambien' => 'Noticias · Manual de usuario.',
            ],
            'news' => [
                'modulo_key' => 'news',
                'titulo' => 'Noticias',
                'descripcion' => '{rol} → Noticias',
                'articulo_titulo' => 'Noticias',
                'articulo_clave' => '/news',
                'tags' => ['Módulo: Noticias', 'avisos'],
                'que_es' => 'La pantalla “Noticias y Actualizaciones”: avisos del sistema (Actualización, Nueva Funcionalidad, Corrección, Anuncio).',
                'para_que' => 'Enterarte de cambios y comunicados. Esta vista solo consulta; no publica.',
                'quien' => 'Rol {rol}. Lectura para quien tenga el menú. El alta de noticias (si existe en otro menú) no forma parte de esta pantalla.',
                'cuando' => 'Al entrar a la intranet o cuando te avisan de una novedad.',
                'flows' => [
                    [
                        'titulo' => 'Leer avisos',
                        'steps' => [
                            $this->itemFlujo(
                                'Tarjetas y detalle',
                                'Entra a Noticias. Ves tarjetas con tipo, título y resumen. Si hay enlace, Ver más detalles. Anterior / Siguiente si hay más páginas. Si falla la carga, Reintentar. Aquí no publicas ni editas: si no hay avisos, vuelve más tarde.',
                                'Recorta una tarjeta de noticia (tipo, título, resumen) y Ver más detalles si se ve. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Tipo', 'Del aviso', 'Nueva Funcionalidad'],
                    ['Título / resumen / contenido', 'Quien publica', 'Nuevo filtro en Permisos'],
                    ['Solicitada por', 'Si aplica (badge)', 'Coordinación'],
                    ['Enlace', 'Opcional, “Ver más detalles”', 'documento interno'],
                ],
                'consideraciones' => "No hay filtros en esta lectura. Vacío: “No hay noticias disponibles”.\n\nPublicar o editar no se hace aquí.",
                'errores' => [
                    ['No hay noticias disponibles', 'Aún no hay avisos publicados', 'Vuelve más tarde'],
                    ['Error al cargar', 'Fallo de red o del servicio', 'Pulsa Reintentar'],
                ],
                'ejemplo' => 'Aviso ficticio “Nueva Funcionalidad”: “Filtro de estado en Permisos”. Lees el resumen y, si hay enlace, Ver más detalles.',
                'resultado' => 'ves el aviso vigente y, si hay enlace, abres el detalle.',
                'ver_tambien' => 'Soporte · Manual de usuario.',
            ],
            'viaticos' => [
                'modulo_key' => 'viaticos',
                'titulo' => 'Viáticos y reintegros — Mis reintegros',
                'descripcion' => '{rol} → Viáticos',
                'articulo_titulo' => 'Mis reintegros',
                'articulo_clave' => '/viaticos',
                'tags' => ['Módulo: Viáticos', 'reintegro'],
                'que_es' => 'Tus solicitudes de viático o reintegro: crear, ver estado, editar (si no está Confirmado) y adjuntar comprobantes.',
                'para_que' => 'Pedir el reembolso de un gasto de trabajo y seguir si está Pendiente, Confirmado o Rechazado.',
                'quien' => 'Rol {rol}. El colaborador crea y edita las suyas. Administración confirma al subir retribuciones (en Pendientes / detalle), no en esta lista tuya.',
                'cuando' => 'Cuando viajaste o gastaste y debes pedir el reintegro.',
                'flows' => [
                    [
                        'titulo' => 'Crear y seguir',
                        'steps' => [
                            $this->itemFlujo(
                                'Crear viático o reintegro',
                                'Pulsa Crear viático o reintegro. Completa Asunto, Fecha de reintegro, Área (Marketing, Ventas, Importaciones, Administración, Otros) y Descripción. En cada ítem: Concepto, Monto (S/) y Comprobante (jpg, png, gif, pdf, doc). Agregar concepto suma otra fila. Guarda. Sin asunto, fecha, área o comprobante no se crea.',
                                'Recorta el formulario de alta (asunto, área, un ítem con comprobante). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Ojo, lápiz y filtros',
                                'El ojo abre el detalle (consulta). El lápiz abre el formulario para corregir, salvo si ya está Confirmado (ahí el lápiz no hace nada). Papelera elimina. Filtra por fechas y Estado (Todos / Pendiente / Confirmado / Rechazado). Busca por asunto, descripción o monto.',
                                'Recorta la tabla con ojo, lápiz y filtro Estado. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Asunto', 'Al crear', 'Viático Lima 12-ago'],
                    ['Fecha de reintegro', 'Al crear', '12-08-2026'],
                    ['Área', 'Lista desplegable al crear. Elige Marketing, Ventas, Importaciones, Administración u Otros. Luego Guardar.', 'Importaciones'],
                    ['Concepto / Monto / Comprobante', 'Por ítem: se escribe y se adjunta. No es lista desplegable.', 'Taxi · S/. 45 · voucher.pdf'],
                    ['Estado', 'Lista desplegable en el detalle. Pendiente y Confirmado salen bloqueados: se confirman al cubrir el monto. Rechazado sí se elige a mano.', 'Pendiente'],
                    ['Fecha de devolución', 'Cuando Administración paga', '20-08-2026'],
                ],
                'consideraciones' => "Si está Confirmado no se edita.\n\nAdministración pasa a Confirmado cuando la suma de retribuciones cubre el total; si no, queda Pendiente (aviso). Rechazado lo marca Administración en el detalle.",
                'errores' => [
                    ['No hay viáticos que coincidan', 'Filtro de fechas o estado', 'Pon Estado en Todos y amplia las fechas'],
                    ['El lápiz no funciona', 'Ya está Confirmado', 'Solo consulta; pide corrección a Administración'],
                    ['No se pudo crear', 'Falta asunto, fecha, área o un comprobante', 'Completa los campos y adjunta el archivo'],
                ],
                'ejemplo' => 'Reintegro ficticio “Taxi aeropuerto”, área Importaciones, S/. 45, voucher.pdf. Queda Pendiente. Administración sube la retribución y pasa a Confirmado.',
                'resultado' => 'tu solicitud queda en la lista con monto y estado, y los comprobantes quedan adjuntos.',
                'ver_tambien' => 'Viáticos pendientes · Viáticos completados.',
            ],
            'viaticos/pendientes' => [
                'modulo_key' => 'viaticos/pendientes',
                'titulo' => 'Viáticos — Pendientes',
                'descripcion' => '{rol} → Viáticos pendientes',
                'articulo_titulo' => 'Viáticos pendientes',
                'articulo_clave' => '/viaticos/pendientes',
                'tags' => ['Módulo: Viáticos', 'aprobación'],
                'que_es' => 'Bandeja de reintegros abiertos para Administración: ver solicitante, monto, comprobantes y entrar al detalle para retribución o rechazo.',
                'para_que' => 'Atender lo que aún no se cierra (pagar o rechazar).',
                'quien' => 'Rol {rol}. Solo Administración. Otro cargo es redirigido a Mis viáticos.',
                'cuando' => 'Cuando hay que revisar o pagar solicitudes abiertas.',
                'flows' => [
                    [
                        'titulo' => 'Atender un pendiente',
                        'steps' => [
                            $this->itemFlujo(
                                'Entrar al detalle',
                                'Entra a Viáticos pendientes. Filtra por fechas, Área o Solicitante. El lápiz abre el detalle (no es un recuadro). Revisa los comprobantes. Si te manda a Mis viáticos, esta bandeja no te toca.',
                                'Recorta Pendientes con filtros y el lápiz de una fila. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Pagar o rechazar',
                                'Para pagar: Agregar pago, completa y Guardar. Si la suma cubre el total, pasa a Confirmado; si no, sigue Pendiente. Para rechazar: en Estado, Pendiente y Confirmado están bloqueados; elige Rechazado. No marques Confirmado a mano.',
                                'Recorta Agregar pago o la lista Estado en Rechazado. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Código', 'Del sistema (lo ve Administración)', 'VT-88'],
                    ['Solicitante', 'Quien pidió el reintegro', 'Ana Torres'],
                    ['Asunto / Área / Monto', 'De la solicitud', 'Taxi · Importaciones · S/. 45'],
                    ['Comprobante', 'Del colaborador', 'voucher.pdf'],
                    ['Retribución', 'La sube Administración', 'transferencia.jpg'],
                    ['Estado', 'Lista desplegable en el detalle. Pendiente y Confirmado bloqueados; Rechazado se elige a mano. Se confirma solo al cubrir el monto.', 'Pendiente'],
                ],
                'consideraciones' => "Pendiente y Confirmado salen deshabilitados en el select de estado: Administración rechaza a mano o confirma al cubrir el monto con retribuciones.\n\nTambién puede verse “Crear viático o reintegro” en esta bandeja.",
                'errores' => [
                    ['Te manda a Mis viáticos', 'Tu rol no es Administración', 'Usa tu lista; esta bandeja es de control'],
                    ['Sigue Pendiente tras Guardar', 'La suma de retribuciones es menor al total', 'Agrega otro pago hasta cubrir'],
                ],
                'ejemplo' => 'VT-88 de Ana Torres, S/. 45 Pendiente. Administración agrega retribución S/. 45, Guardar: pasa a Confirmado.',
                'resultado' => 'el reintegro queda pagado (Confirmado) o Rechazado, o sigue Pendiente si falta monto.',
                'ver_tambien' => 'Mis reintegros · Viáticos completados.',
            ],
            'viaticos/completados' => [
                'modulo_key' => 'viaticos/completados',
                'titulo' => 'Viáticos — Completados',
                'descripcion' => '{rol} → Viáticos completados',
                'articulo_titulo' => 'Viáticos completados',
                'articulo_clave' => '/viaticos/completados',
                'tags' => ['Módulo: Viáticos', 'historial'],
                'que_es' => 'Historial de reintegros ya cerrados (Confirmado o Rechazado) para Administración.',
                'para_que' => 'Consultar o exportar lo ya procesado.',
                'quien' => 'Rol {rol}. Solo Administración. Otro cargo no usa esta bandeja.',
                'cuando' => 'Para revisar un reintegro que ya se pagó o se rechazó.',
                'flows' => [
                    [
                        'titulo' => 'Consultar el histórico',
                        'steps' => [
                            $this->itemFlujo(
                                'Filtrar y exportar',
                                'Entra a Viáticos completados. Filtra por fechas, Área o Solicitante. Abre el detalle si hace falta. Exportar baja el listado filtrado. Aquí no se crea ni se paga: el alta está en Mis reintegros y la aprobación en Pendientes.',
                                'Recorta Completados con filtros y Exportar. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Código / Solicitante / Asunto', 'De la solicitud cerrada', 'VT-88 · Ana Torres'],
                    ['Monto / Estado', 'Cierre', 'S/. 45 · Confirmado'],
                    ['Fecha de devolución', 'Cuando se pagó', '20-08-2026'],
                ],
                'consideraciones' => 'Aquí no se crea. El alta y la aprobación están en Mis viáticos / Pendientes.',
                'errores' => [
                    ['No aparecen filas', 'Filtro de fechas o área', 'Amplía el rango o quita Área / Solicitante'],
                    ['Te saca de la pantalla', 'No eres Administración', 'Usa Mis viáticos'],
                ],
                'ejemplo' => 'VT-88 Confirmado el 20-08-2026. Administración filtra agosto y Exportar.',
                'resultado' => 'ves el reintegro cerrado y, si exportas, bajas el Excel del filtro.',
                'ver_tambien' => 'Viáticos pendientes · Mis reintegros.',
            ],
            'calendar' => [
                'modulo_key' => 'calendar',
                'titulo' => 'Mi progreso — Calendario',
                'descripcion' => '{rol} → Calendario',
                'articulo_titulo' => 'Calendario',
                'articulo_clave' => '/calendar',
                'tags' => ['Módulo: Calendario', 'actividades', 'progreso'],
                'que_es' => 'El calendario mensual de actividades del equipo (tareas, responsables, fechas). El botón Progreso abre “TABLA DE PROGRESO”.',
                'para_que' => 'Ver el mes, actualizar tu estado o (si eres jefe del grupo) crear actividades y configurar el calendario.',
                'quien' => 'Rol {rol}. Jefe del grupo (p. ej. Jefe Importación): Crear Actividad, asignar, Configuración, progreso global. Miembro (Coordinación, Documentación, etc.): consulta, filtra y actualiza su estado y notas; no crea ni borra.',
                'cuando' => 'Para el avance diario de importaciones u otro grupo de calendario que tengas.',
                'flows' => [
                    [
                        'titulo' => 'Usar el mes',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir y filtrar',
                                'Entra a Calendario (en algunos menús: Mi Progreso Importaciones). Si hay varios grupos, elige el calendario en la lista de arriba. Filtra por Consolidado, Responsable (Todos / Yo) y fechas (Aplicar / Limpiar). Los filtros no cambian la actividad: solo recortan el mes.',
                                'Recorta el mes con el selector de grupo y los filtros. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Actualizar tu actividad',
                                'Pulsa una actividad: se abre el detalle (otra pantalla, no un recuadro). Ahí cambias tu estado y notas. Si no eres responsable, no podrás actualizarla. Pulsa Progreso para la tabla (Completadas / En progreso / Pendientes). Si ves Progreso global eres jefe; si ves Mi progreso, solo tus totales.',
                                'Recorta el detalle de una actividad (estado y notas) o la tabla Progreso. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Crear (si eres jefe del grupo)',
                        'steps' => [
                            $this->itemFlujo(
                                'Nueva actividad y configuración',
                                'Pulsa Crear Actividad (o Nueva actividad en la tabla). Completa nombre, responsables, fechas, prioridad y, si aplica, consolidado. Guarda. En Configuración: catálogo, colores, grupos y qué se muestra en Registro / Progreso. Si no ves Crear Actividad, eres miembro: actualiza tu estado; el alta es del jefe.',
                                'Recorta Crear Actividad o Configuración. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Actividad', 'Catálogo o alta del jefe', 'Enviar packing list'],
                    ['Estado', 'Pendiente / En progreso / Completada (el miembro la suya)', 'En progreso'],
                    ['Prioridad', 'El jefe', 'Alta'],
                    ['Consolidado', 'Si el grupo lo usa', '#101'],
                    ['F. Inicio / F. Fin / Duración', 'Al crear', '12-08-2026 · 14-08-2026'],
                    ['Responsables / Notas', 'Asignación y seguimiento', 'Luis · “Falta sello”'],
                ],
                'consideraciones' => "Esto no es la pantalla “Mi Progreso” de métricas de volumen del Cotizador (contenedor, FOB, impuestos).\n\nSi no eres responsable de la actividad, no podrás actualizarla.",
                'errores' => [
                    ['Error de carga', 'Fallo al traer el mes', 'Pulsa Reintentar'],
                    ['No eres responsable de esta actividad', 'No estás asignado', 'Pide al jefe que te agregue o filtra Yo'],
                    ['Esta actividad no tiene responsables', 'Alta incompleta', 'El jefe asigna al menos uno'],
                    ['No veo Crear Actividad', 'Eres miembro, no jefe del grupo', 'Actualiza tu estado; el alta es del jefe'],
                ],
                'ejemplo' => 'Actividad ficticia “Enviar packing list”, carga #101, responsable Luis, 12-08 a 14-08. Luis la pasa a En progreso. El jefe abre Progreso y ve 1 En progreso.',
                'resultado' => 'ves el mes o la tabla de progreso y, si aplica, la actividad queda creada o tu estado actualizado.',
                'ver_tambien' => 'Carga consolidada · Mi progreso (métricas Cotizador).',
            ],
            'mi-progreso' => [
                'modulo_key' => 'mi-progreso',
                'titulo' => 'Mi progreso',
                'descripcion' => '{rol} → Mi progreso',
                'articulo_titulo' => 'Mi progreso',
                'articulo_clave' => '/mi-progreso',
                'tags' => ['Módulo: Cotizador', 'métricas', 'volumen'],
                'que_es' => 'La pantalla “Mi Progreso”: tarjetas de volumen y totales por contenedor y fecha (no es el calendario de actividades).',
                'para_que' => 'Revisar Volumen China, Volumen Vendido, Volumen Pendiente, Total Fob, Total Impuestos y Total Logística del periodo.',
                'quien' => 'Rol {rol} (Cotizador). Consulta; no crea registros.',
                'cuando' => 'Para ver tu avance comercial de contenedores en un rango de fechas.',
                'flows' => [
                    [
                        'titulo' => 'Filtrar métricas',
                        'steps' => [
                            $this->itemFlujo(
                                'Aplicar el periodo',
                                'Entra a Mi progreso. En Filtros elige Contenedor, Fecha Inicio y Fecha Fin. Pulsa Aplicar Filtros (elegir el contenedor aplica solo). Resetear limpia. Revisa las seis tarjetas y lo de debajo (tablas o gráficos). Si todo queda en cero, amplía fechas o cambia de contenedor: aquí no se crean registros.',
                                'Recorta Filtros (contenedor y fechas) y las seis tarjetas. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Contenedor', 'Filtro', '#101'],
                    ['Fecha Inicio / Fecha Fin', 'Filtro', '01-08-2026 · 31-08-2026'],
                    ['Volumen China / Vendido / Pendiente', 'Calculado', '30 / 22 / 8 CBM'],
                    ['Total Fob / Impuestos / Logística', 'Calculado', 'S/. 12 000 / 2 100 / 800'],
                ],
                'consideraciones' => "No confundir con Calendario → Progreso (actividades del equipo).\n\nLos porcentajes de tendencia en las tarjetas son informativos de la vista.",
                'errores' => [
                    ['Las tarjetas en cero', 'Rango sin datos o contenedor sin movimiento', 'Amplía fechas o elige otro contenedor / Resetear'],
                ],
                'ejemplo' => 'Contenedor #101, agosto 2026. Volumen Vendido 22 CBM, FOB S/. 12 000. El cotizador usa eso en el cierre de la campaña.',
                'resultado' => 'ves los totales del filtro aplicado (contenedor y fechas).',
                'ver_tambien' => 'Cotizador · Carga consolidada · Calendario.',
            ],
            'copiloto' => [
                'modulo_key' => 'copiloto',
                'titulo' => 'Copiloto',
                'descripcion' => '{rol} → Copiloto',
                'articulo_titulo' => 'Copiloto',
                'articulo_clave' => '/copiloto',
                'tags' => ['Módulo: Copiloto', 'WhatsApp', 'pipeline'],
                'que_es' => '“Copiloto IA”: cola de WhatsApp con sugerencias de respuesta, ficha del lead y tablero Pipeline (etapas).',
                'para_que' => 'Atender leads, enviar o programar mensajes y mover tarjetas de etapa (contacto, cotización, negociación, cierre).',
                'quien' => 'Rol {rol}. Cotizador usa Pipeline y Mi cola. El jefe de ventas entra a Equipo (supervisión). Si no tienes acceso: “No tienes acceso a Copiloto IA”.',
                'cuando' => 'Cuando trabajas la cola de cotización por WhatsApp.',
                'flows' => [
                    [
                        'titulo' => 'Atender la cola',
                        'steps' => [
                            $this->itemFlujo(
                                'Responder en Mi cola',
                                'Entra a Copiloto. Arriba: Pipeline | Mi cola. En Mi cola busca el chat. Escribe, usa sugerencias del último mensaje, plantillas o adjuntos; Enviar o Programar. Si la ventana de 24 h está cerrada, el envío libre puede pedir plantilla. Nuevo contacto y Sincronizar directorio si hace falta. Asignar o Renombrar el chat. Si ves No tienes acceso a Copiloto IA, esta vista no te toca.',
                                'Recorta Mi cola con el compositor, plantillas o Enviar. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Mover el Pipeline',
                                'En Pipeline arrastra la tarjeta de etapa (contacto, cotización, negociación, cierre). Revisa la ficha: Señales, Hist., Aduana. Si ves Equipo, es supervisión (cola en consulta, sí puedes asignar). Si no aparece Equipo, trabaja solo tu cola.',
                                'Recorta el tablero Pipeline con una tarjeta en una etapa. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Chat / lead', 'Cola WhatsApp', 'Juan Pérez'],
                    ['Mensaje / plantilla', 'Compositor', 'Hola, te envío la cotización…'],
                    ['Etapa', 'Pipeline', 'Negociación'],
                    ['Ficha Aduana', 'Búsqueda en la ficha', 'partida 6404…'],
                ],
                'consideraciones' => "Si la ventana de 24 h de WhatsApp está cerrada, el envío libre puede pedir plantilla (igual que el inbox de Coordinación).\n\nEquipo (jefe): cola en solo lectura con filtro por asesor; sí puede asignar y reordenar etapas.",
                'errores' => [
                    ['No tienes acceso a Copiloto IA', 'Tu cargo no es Cotizador ni jefe de ventas', 'Pulsa Ir al inicio; pide el menú si te corresponde'],
                    ['No envía', 'Ventana cerrada o falta plantilla', 'Usa Plantillas'],
                ],
                'ejemplo' => 'Lead ficticio “Juan Pérez” en etapa Contacto. El cotizador responde con plantilla, arrastra a Cotización y deja nota en la ficha.',
                'resultado' => 'el chat queda atendido y la tarjeta en la etapa correcta.',
                'ver_tambien' => 'Cotizador · Chat WhatsApp (Coordinación).',
            ],
            'verificacion' => [
                'modulo_key' => 'verificacion',
                'titulo' => 'Verificación',
                'descripcion' => '{rol} → Verificación',
                'articulo_titulo' => 'Verificación',
                'articulo_clave' => '/verificacion',
                'tags' => ['Módulo: Verificación', 'pagos', 'permisos', 'boletín'],
                'que_es' => 'La pantalla “Verificación”: bandeja para confirmar pagos. Pestañas Consolidado, Cursos, Delivery, Boletín Químico y (solo Administración) Permisos.',
                'para_que' => 'Validar vouchers (Pendiente / Confirmado / Observado) antes de seguir la carga, el curso, el delivery o el trámite.',
                'quien' => 'Rol {rol}. Administración confirma y ve Permisos. Contabilidad y otros con menú consultan las pestañas que les aparecen; no ven Permisos si no son Administración.',
                'cuando' => 'Cuando hay un adelanto o pago por revisar.',
                'flows' => [
                    [
                        'titulo' => 'Consultar la bandeja',
                        'steps' => [
                            $this->itemFlujo(
                                'Elegir pestaña y filtrar',
                                'Entra a Verificación. Elige la pestaña: Consolidado, Cursos, Delivery, Boletín Químico o Permisos (si la ves). Busca o filtra. Estado, Carga/Campaña y Tipo de Entrega son listas de filtro: no confirman el pago. Si no ves Permisos, no eres Administración: usa las otras pestañas.',
                                'Recorta las pestañas y los filtros de la bandeja. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Confirmar u observar un voucher',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir el detalle',
                                'Pulsa el ojo de la fila. Entras al detalle de ese cliente: resumen Importe / Pagado y las tarjetas de cada adelanto. Abre el comprobante (nombre del archivo) para ver el voucher. Si el ojo no cambia nada, esta vista solo lista.',
                                'Recorta el detalle con Importe / Pagado y una tarjeta de adelanto. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Marcar Confirmado u Observado',
                                'En cada tarjeta, Estado es una lista: PENDIENTE, CONFIRMADO u OBSERVADO. Elige CONFIRMADO si el voucher está bien, OBSERVADO si hay problema. Se guarda al elegir. La tarjeta se pinta de verde o rojo. Escribe una Nota si hace falta y pulsa Guardar (candado). Regresar vuelve a la bandeja. Si el estado no cambia, tu rol no confirma: consulta y pide a Administración.',
                                'Recorta la lista Estado de una tarjeta (Confirmado u Observado). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Permisos (si ves la pestaña)',
                                'En Permisos el ojo abre el trámite: ahí confirmas derecho y tramitador y pulsas Guardar todo. Si sales con cambios: Cambios sin guardar. Crear el boletín o el trámite no se hace aquí.',
                                'Recorta el detalle de Permisos con derecho/tramitador y Guardar todo. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Pestaña', 'Consolidado, Cursos, Delivery, Permisos, Boletín', 'Consolidado'],
                    ['Contacto / Cliente / Carga', 'De la fila', 'María Pérez · #101'],
                    ['Estado / Importe / Pagado / Adelantos', 'Listado. El estado de la fila puede verse como lista bloqueada.', 'Adelanto · S/. 500 · S/. 200'],
                    ['Estado del voucher', 'Lista desplegable en el detalle (Administración): Pendiente, Confirmado u Observado. Se guarda al elegir.', 'Confirmado'],
                    ['Permisos: Derecho / Tramitador', 'Detalle, solo Administración', 'S/. 150 · voucher.jpg'],
                ],
                'consideraciones' => "Crear el boletín o el trámite de permiso no se hace aquí (Aduanas → Boletín / Permisos).\n\nEn Boletín el estado de la fila es solo lectura; se confirma cada adelanto.\n\nSi sales del detalle de Permisos con cambios: “Cambios sin guardar”.",
                'errores' => [
                    ['No se encontraron registros', 'Filtro de estado, carga o fechas', 'Pon Estado más amplio y limpia fechas'],
                    ['No veo Permisos', 'Solo Administración tiene esa pestaña', 'Usa Consolidado o Boletín, o pide a Administración'],
                    ['No cambia el voucher', 'Tu rol no confirma', 'Consulta; Administración marca Confirmado u Observado'],
                ],
                'ejemplo' => 'Consolidado, cliente “María Pérez”, carga #101, Adelanto S/. 200. Administración abre el ojo, pone Confirmado. En Permisos confirma el derecho DIGESA.',
                'resultado' => 'el pago queda Confirmado u Observado y la bandeja refleja el nuevo estado.',
                'ver_tambien' => 'Boletín químico · Permisos · Carga consolidada · Inspeccionados.',
            ],
            'inspeccionados' => [
                'modulo_key' => 'inspeccionados',
                'titulo' => 'Inspecciones — Inspeccionados',
                'descripcion' => '{rol} → Inspeccionados',
                'articulo_titulo' => 'Inspeccionados',
                'articulo_clave' => '/inspeccionados',
                'tags' => ['Módulo: Inspecciones', 'logística', 'pagos'],
                'que_es' => 'El listado “Inspeccionados”: clientes con carga inspeccionada y el cobro de logística (importe, pagado, diferencia, adelantos).',
                'para_que' => 'Seguir el cobro post-inspección, registrar adelantos y enviar recordatorio de pago.',
                'quien' => 'Rol {rol} (Contabilidad). Inspección y Estado de pago en la tabla están bloqueados (consulta). Sí se registran o eliminan adelantos y se envía recordatorio.',
                'cuando' => 'Cuando la carga ya fue inspeccionada y hay que cobrar o recordar el pago.',
                'flows' => [
                    [
                        'titulo' => 'Consultar y cobrar',
                        'steps' => [
                            $this->itemFlujo(
                                'Filtrar la lista',
                                'Entra a Inspeccionados. Filtra F. Inspección, Estado Inspección y Estado de pago: son listas de filtro (no cambian la fila). Estado Inspección: Todos / Inspeccionado / Completado. Estado de pago: PENDIENTE, ADELANTO, PAGADO, SOBREPAGO. Inspección y Estado de pago en la tabla están bloqueados: no los edites a mano.',
                                'Recorta Inspeccionados con filtros y una fila (importe, pagado, diferencia). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Adelanto y recordatorio',
                                'En Adelantos registra o elimina un pago (completa monto y archivo). El ojo abre las cotizaciones del contenedor (otra pantalla). El recordatorio se envía desde la fila, no desde Ver: pulsa enviar recordatorio y confirma. Si falla el envío, reintenta con voucher completo.',
                                'Recorta la grilla Adelantos o el envío del recordatorio. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Contacto / T. Cliente / Campaña', 'De la carga', 'María Pérez · #101'],
                    ['F. Inspección', 'De la operación', '10-08-2026'],
                    ['Estado inspección', 'Lista desplegable en la tabla, bloqueada (solo lectura).', 'Inspeccionado'],
                    ['Concepto / Importe / Pagado / Diferencia', 'Solo lectura en la fila. El cobro se registra en la grilla de adelantos.', 'Inspección · S/. 80 · S/. 40 · S/. 40'],
                    ['Estado de pago', 'Lista desplegable en la tabla, bloqueada: PENDIENTE, ADELANTO, PAGADO, SOBREPAGO. El avance va por los adelantos.', 'ADELANTO'],
                ],
                'consideraciones' => 'No edites Inspección ni Estado de pago en la fila: están deshabilitados. El avance va por los adelantos.',
                'errores' => [
                    ['No se encontraron clientes con carga inspeccionada', 'Filtro de fechas o estado', 'Amplía F. Inspección y pon Estados en Todos'],
                    ['Falla el recordatorio o el pago', 'Red o voucher incompleto', 'Reintenta; completa monto y archivo'],
                ],
                'ejemplo' => 'Cliente ficticio “María Pérez”, campaña #101, inspeccionada el 10-08, importe S/. 80, adelanto S/. 40. Contabilidad registra el saldo y envía recordatorio.',
                'resultado' => 'ves la fila inspeccionada y el adelanto o el recordatorio quedan registrados.',
                'ver_tambien' => 'Verificación · Carga consolidada · Facturación.',
            ],
            'datos-facturacion' => [
                'modulo_key' => 'datos-facturacion',
                'titulo' => 'Facturación',
                'descripcion' => '{rol} → Facturación',
                'articulo_titulo' => 'Facturación',
                'articulo_clave' => '/datos-facturacion',
                'tags' => ['Módulo: Facturación', 'Excel', 'clientes'],
                'que_es' => 'La pantalla “Importar Datos de Facturacion”: historial de Excels para crear o vincular datos de facturación (correo, celular, documento).',
                'para_que' => 'Cargar un lote de datos fiscales y, si un lote salió mal, deshacerlo con Rollback.',
                'quien' => 'Rol {rol} (Contabilidad). Pensado también para Administración (avisos en tiempo real a esos cargos).',
                'cuando' => 'Cuando llega un Excel de facturación para actualizar clientes.',
                'flows' => [
                    [
                        'titulo' => 'Importar o deshacer',
                        'steps' => [
                            $this->itemFlujo(
                                'Importar Excel',
                                'Entra a Facturación. Pulsa Importar Excel y sube .xlsx o CSV con las columnas: nombre, celular, dni, correo, documento, titular, domicilio_fiscal, destino_entrega. Revisa la tabla: Archivo, Fecha, Filas Creadas, Estado, Accion. Si no acepta el archivo, no es Excel ni CSV. Vacío: No hay importaciones registradas. Regresar vuelve a Clientes.',
                                'Recorta Importar Excel y el historial (archivo, filas, estado). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Rollback',
                                'Si el lote está mal y no está en ROLLBACK, pulsa Rollback en esa fila: es irreversible en ese archivo. Si Rollback no aparece, ese lote ya está deshecho: no se puede otra vez.',
                                'Recorta la fila con Accion Rollback (antes de confirmar). Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Archivo', 'El Excel o CSV subido', 'facturacion_agosto.xlsx'],
                    ['Fecha / Filas creadas / Estado', 'Del lote', '17-08-2026 · 40 · OK'],
                    ['nombre / celular / dni / correo', 'Columnas del Excel', 'María Pérez · 999111222'],
                    ['documento / titular / domicilio_fiscal / destino_entrega', 'Columnas del Excel', '20123456789 · María Pérez · Av. Ejemplo 123'],
                ],
                'consideraciones' => "Rollback es irreversible en esa fila.\n\nNo hay filtros en esta pantalla. Vacío: “No hay importaciones registradas”.",
                'errores' => [
                    ['No acepta el archivo', 'No es Excel ni CSV', 'Usa .xlsx o .csv con esas columnas'],
                    ['Importación con errores', 'Filas incompletas o mal vinculadas', 'Corrige el archivo y vuelve a Importar; Rollback si el lote ya entró mal'],
                    ['Rollback no aparece', 'Ese lote ya está en ROLLBACK', 'No se puede deshacer otra vez'],
                ],
                'ejemplo' => 'Excel ficticio de 40 filas, cliente “María Pérez”, RUC 20123456789. Contabilidad importa, ve Filas Creadas 40. Si era el archivo viejo, Rollback.',
                'resultado' => 'el lote queda en el historial con sus filas, o el Rollback deja ese archivo deshecho.',
                'ver_tambien' => 'Clientes · Inspeccionados · Verificación.',
            ],
            'coordinacion/whatsapp-inbox' => [
                'modulo_key' => 'coordinacion/whatsapp-inbox',
                'titulo' => 'Chat — WhatsApp',
                'descripcion' => '{rol} → Chat WhatsApp',
                'articulo_titulo' => 'Chat WhatsApp',
                'articulo_clave' => '/coordinacion/whatsapp-inbox',
                'tags' => ['Módulo: Coordinación', 'WhatsApp', 'inbox'],
                'que_es' => 'La bandeja “PROBUSINESS / WhatsApp Inbox”: chats del número de sesión de Coordinación (asignar, plantillas, enviar).',
                'para_que' => 'Escribirle a un cliente o proveedor por el canal de coordinación, no por el celular personal.',
                'quien' => 'Rol {rol}. Coordinación usa esta bandeja. Si el menú te deja entrar, envías y asignas; si no, pantalla no autorizada.',
                'cuando' => 'Cuando hay que coordinar una carga o responder un chat del número de la empresa.',
                'flows' => [
                    [
                        'titulo' => 'Atender un chat',
                        'steps' => [
                            $this->itemFlujo(
                                'Encontrar la conversación',
                                'Entra a Chat WhatsApp. Filtra: Todas, Sin asignar, Mis chats, Cerradas. Busca el cliente. Pulsa Actualizar si no ves el último mensaje. Nuevo contacto si aún no está. Si ves No autorizado, esta bandeja no te toca. Si el navegador pide notificaciones, acéptalas para no perder chats.',
                                'Recorta la bandeja con filtros (Todas / Sin asignar / Mis chats) y un chat. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Enviar o plantilla',
                                'Asignar abre la lista de responsables; Renombrar abre un formulario y Guardar cambia el nombre del contacto. Escribe y pulsa Enviar, o Programar para elegir fecha y hora. Adjuntar permite Fotos y videos, Documento o Audio. Si pasaron 24 h sin respuesta, usa Plantillas: elige una, completa sus variables y confirma el envío.',
                                'Recorta el compositor con Enviar/Programar y el modal de plantilla con variables ficticias.'
                            ),
                            $this->itemFlujo(
                                'Acciones de un mensaje',
                                'Abre el menú del mensaje. Info muestra estado y hora de entrega/lectura. Responder cita ese mensaje en el compositor. Reenviar plantilla vuelve a preparar la plantilla para otro envío. Reacción agrega el emoji elegido. Estas acciones no editan ni eliminan el mensaje ya enviado.',
                                'Recorta el menú de un mensaje con Info, Responder, Reenviar plantilla y Reacción. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Cerrar o reabrir la conversación',
                                'Cuando el caso termine, usa Cerrar conversación y confirma; pasa a Cerradas. Desde el filtro Cerradas puedes abrirla y reanudarla si llega trabajo nuevo. Actualizar sincroniza la lista, no envía mensajes.',
                                'Recorta la confirmación de cierre y el filtro Cerradas. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Filtro de lista', 'Todas / Sin asignar / Mis chats / Cerradas', 'Sin asignar'],
                    ['Cliente', 'Búsqueda', 'María Pérez'],
                    ['Mensaje / plantilla / adjunto', 'Compositor', 'Tu carga #101 cierra el 15'],
                    ['Asignado a', 'Asignar', 'Luis (Coordinación)'],
                ],
                'consideraciones' => "Si el navegador pide permiso de notificaciones, acéptalo para no perder chats.\n\nVentana cerrada = solo plantillas hasta que el cliente escriba de nuevo.",
                'errores' => [
                    ['No autorizado', 'Tu cargo no tiene esta bandeja', 'Pide el menú a Subgerencia'],
                    ['No hay conversaciones', 'Filtro Mis chats / Cerradas', 'Pon Todas o Sin asignar'],
                    ['No deja escribir', 'Pasaron 24 h sin respuesta del cliente', 'Pulsa Plantillas'],
                ],
                'ejemplo' => 'Chat ficticio “María Pérez”, sin asignar. Coordinación asigna a Luis, envía plantilla de cierre de carga #101.',
                'resultado' => 'el chat queda asignado y el mensaje o la plantilla enviados.',
                'ver_tambien' => 'Carga consolidada · Copiloto · Clientes.',
            ],
            'calendar/subpantallas' => [
                'modulo_key' => 'calendar/subpantallas',
                'titulo' => 'Calendario — progreso y configuración',
                'descripcion' => '{rol} → Calendario → Subpantallas',
                'articulo_titulo' => 'Progreso y configuración',
                'articulo_clave' => '/calendar/progreso',
                'tags' => ['Módulo: Calendario', 'progreso', 'actividades', 'configuración'],
                'que_es' => 'Las pantallas auxiliares del Calendario: Progreso, Registro de actividades, Catálogo, Colores, Colores por usuario, Configuración y Grupos por rol.',
                'para_que' => 'Consultar avance y, si eres jefe del grupo, mantener las opciones que usa el calendario.',
                'quien' => 'Rol {rol}. Los miembros consultan Progreso y sus actividades. Los botones de configuración dependen de ser jefe del grupo.',
                'cuando' => 'Cuando necesitas una tabla de avance o ajustar catálogos, colores y grupos del calendario.',
                'flows' => [
                    [
                        'titulo' => 'Progreso y registro',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir Progreso',
                                'Desde Calendario pulsa Progreso. La página destino muestra tarjetas Completadas, En progreso y Pendientes, filtros y el listado de actividades. Allí puedes actualizar estados, desplegar subtareas y abrir Notas cuando ese control esté disponible. Regresar vuelve al mes.',
                                'Recorta Progreso con tarjetas, filtros, estado y subtareas de una actividad ficticia.'
                            ),
                            $this->itemFlujo(
                                'Actividades',
                                'Desde Configuración abre Registro de Actividades. Nueva actividad abre el formulario; completa los datos solicitados y guarda. El lápiz abre la edición. La papelera pide confirmación antes de eliminar. Usa los filtros de fecha para encontrar un registro.',
                                'Recorta Registro de Actividades con Nueva actividad, filtros, lápiz y papelera. Datos ficticios.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Configurar (jefe del grupo)',
                        'steps' => [
                            $this->itemFlujo(
                                'Catálogo de actividades',
                                'En Catálogo escribe el nombre y pulsa Agregar. El lápiz permite cambiar el nombre en la misma fila; confirma con el check o cancela con la X. La papelera pide confirmación. También puedes aplicar un color, marcar sábado o domingo, elegir prioridad, reordenar y usar Guardar cambios o Guardar orden.',
                                'Recorta Catálogo con Agregar, edición en fila, color, opciones y papelera. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Colores y colores por usuario',
                                'En Colores selecciona un color para cada consolidado y pulsa Guardar cambios. En Colores por usuario busca la fila del responsable y elige un color predefinido o personalizado; ese cambio se guarda al seleccionarlo. Comprueba el resultado al volver al mes.',
                                'Recorta Colores con Guardar cambios o una fila de Colores por usuario. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Configuración y grupos por rol',
                                'Configuración reúne accesos a Registro, Progreso, Colores, Colores por usuario, Grupos de Roles y Catálogo. En Grupos de Roles puedes crear, editar o eliminar un grupo; asignar o quitar miembros y su rol; ordenar la prioridad de colores para jefe y miembros; y pulsar Guardar configuración.',
                                'Recorta Configuración y un Grupo de Roles con miembros y Guardar configuración. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Actividad / prioridad / fechas', 'Formulario Crear o Editar', 'Enviar packing list · Alta · 18-08-2026'],
                    ['Responsables / grupo', 'Formulario o Grupo de Roles', 'Ana Torres · Importaciones'],
                    ['Color', 'Catálogo, consolidado o responsable', 'Naranja'],
                    ['Miembro / rol en el grupo', 'Grupo de Roles', 'Ana Torres · Jefe'],
                ],
                'consideraciones' => "Los controles dependen del rol que ocupas dentro del grupo.\n\nColores por usuario guarda al elegir. Colores por consolidado requiere Guardar cambios. Las opciones y el orden del Catálogo tienen botones de guardado separados.",
                'errores' => [
                    ['No aparece Configuración', 'No eres jefe del grupo', 'Usa Progreso o pide al jefe que haga el ajuste'],
                    ['El cambio no se ve en el mes', 'La vista conserva datos anteriores', 'Vuelve al Calendario y recarga'],
                    ['No guarda una actividad', 'Faltan fechas, nombre o responsables', 'Completa los obligatorios del formulario'],
                ],
                'ejemplo' => 'El jefe agrega “Revisar BL” al Catálogo, define prioridad alta y color naranja. Ana abre Progreso, despliega la actividad y actualiza su seguimiento.',
                'resultado' => 'el avance queda visible y la configuración guardada se refleja en el calendario.',
                'ver_tambien' => 'Calendario · Carga consolidada.',
            ],
            'soporte-ti/configuracion' => [
                'modulo_key' => 'soporte-ti/configuracion',
                'titulo' => 'Soporte — configuración de horas SLA',
                'descripcion' => '{rol} → Soporte TI → Configuración',
                'articulo_titulo' => 'Horas SLA',
                'articulo_clave' => '/soporte-ti/configuracion/horas-tipo-a',
                'tags' => ['Módulo: Soporte', 'SLA', 'horas', 'PM'],
                'que_es' => 'Las matrices de horas para tickets Tipo A (proyectos) y Tipo B (requerimientos).',
                'para_que' => 'Definir las horas por complejidad y fase que usa el seguimiento de soporte.',
                'quien' => 'Rol {rol}. PM edita la matriz de fases de Tipo A. El analista de Soporte edita configuración y las horas de Tipo B. El resto solo lee o vuelve a Soporte.',
                'cuando' => 'Cuando cambian los tiempos acordados de una complejidad.',
                'flows' => [
                    [
                        'titulo' => 'Actualizar horas',
                        'steps' => [
                            $this->itemFlujo(
                                'Tipo A',
                                'Abre Horas Tipo A. En Fases PM ubica la complejidad, cambia las horas de cada fase y pulsa Guardar en esa fila; el total es referencial. En Configuración, el analista cambia Horas config. y pulsa Guardar. Cada valor debe estar entre 1 y 9999.',
                                'Recorta una fila de Fases PM y una de Configuración con Guardar. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Tipo B',
                                'Abre Horas Tipo B. Ubica B1 o B2 y su complejidad, cambia las horas permitidas y pulsa Guardar. Espera el aviso antes de salir. Volver regresa a Soporte TI.',
                                'Recorta Horas Tipo B con B1/B2, complejidad y Guardar. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Complejidad', 'Fila de la matriz', 'M3'],
                    ['Fase', 'Columna Tipo A', 'Desarrollo'],
                    ['Horas', 'Número entre 1 y 9999', '16'],
                ],
                'consideraciones' => 'PM y analista editan secciones distintas. No cambies una matriz sin acuerdo del área.',
                'errores' => [
                    ['Guardar está deshabilitado', 'Hay una hora vacía, menor que 1 o mayor que 9999', 'Corrige toda la fila'],
                    ['Solo lectura', 'Tu cargo no edita esa sección', 'Pide el cambio al PM o al analista'],
                ],
                'ejemplo' => 'PM cambia M3, fase Desarrollo, de 12 a 16 horas y pulsa Guardar. El total de la fila se actualiza.',
                'resultado' => 'la matriz queda guardada y los nuevos tickets usan las horas configuradas.',
                'ver_tambien' => 'Soporte TI — tickets.',
            ],
            'panel-acceso/administracion-avanzada' => [
                'modulo_key' => 'panel-acceso/administracion-avanzada',
                'titulo' => 'Panel acceso — menús y externos',
                'descripcion' => '{rol} → Panel acceso → Menús y externos',
                'articulo_titulo' => 'Menús y usuarios externos',
                'articulo_clave' => '/panel-acceso/menus',
                'tags' => ['Módulo: Panel acceso', 'menús', 'externos', 'permisos por usuario'],
                'que_es' => 'Las pantallas para mantener el catálogo de menús, usuarios externos, menús externos y permisos individuales.',
                'para_que' => 'Crear opciones de navegación y controlar el acceso de personas externas sin cambiar el cargo completo.',
                'quien' => 'Rol {rol} (Subgerencia).',
                'cuando' => 'Cuando aparece una pantalla nueva, se habilita un usuario externo o una persona necesita una excepción de menú.',
                'flows' => [
                    [
                        'titulo' => 'Catálogos de menús',
                        'steps' => [
                            $this->itemFlujo(
                                'Menús internos',
                                'En Menús pulsa Agregar Menú. Elige si es principal o submenú y completa nombre, orden, ruta Nuxt, ícono, URL de video y estado; si es submenú, elige también el padre. Guardar crea. El lápiz abre el mismo formulario para editar. La papelera abre la confirmación de Eliminar.',
                                'Recorta Menús con Agregar Menú, lápiz, papelera y el formulario. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Menús externos',
                                'En Menús externos repite el mantenimiento para la navegación externa. Usa una URL válida. Guardar crea o actualiza; Eliminar pide confirmación. Después asigna el menú en Permisos por usuario.',
                                'Recorta Menús externos con un formulario ficticio y Guardar.'
                            ),
                        ],
                    ],
                    [
                        'titulo' => 'Usuarios y permisos externos',
                        'steps' => [
                            $this->itemFlujo(
                                'Usuarios externos',
                                'Pulsa Agregar Usuario. Completa nombre, apellido, email, contraseña, WhatsApp y DNI. Guardar crea. El lápiz abre el mismo formulario; deja la nueva contraseña vacía si no deseas cambiarla. La papelera abre la confirmación de Eliminar. No uses contraseñas reales en capturas.',
                                'Recorta Usuarios externos con Agregar Usuario, lápiz y formulario. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Permisos por usuario',
                                'En Permisos de Menú por Usuario elige Usuario Externo. Marca cada menú o usa el check general. Pulsa Guardar en la cabecera. Recargar descarta la vista actual y vuelve a traer permisos guardados; la persona debe recargar su sesión.',
                                'Recorta el selector de usuario, grupos de menú, check general y Guardar. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Nombre / URL / orden', 'Formulario de menú', 'Portal proveedor · /portal · 3'],
                    ['Usuario externo / email', 'Formulario de usuario', 'Proveedor Demo · proveedor@ejemplo.com'],
                    ['Acceso', 'Check por menú', 'Documentos: sí'],
                ],
                'consideraciones' => "Permisos por usuario son para usuarios externos y no sustituyen Permisos menú por cargo.\n\nEliminar un menú puede dejar permisos sin destino; confirma su uso antes.",
                'errores' => [
                    ['No hay menús disponibles', 'No se eligió usuario o no existe catálogo externo', 'Elige el usuario y revisa Menús externos'],
                    ['No ve el cambio', 'No se pulsó Guardar o la sesión sigue abierta', 'Guarda y pide que recargue'],
                    ['Email no guarda', 'Ya existe o el formato es inválido', 'Usa un correo externo único'],
                ],
                'ejemplo' => 'Subgerencia crea el menú externo “Documentos”, registra a Proveedor Demo y marca ese acceso en Permisos por usuario.',
                'resultado' => 'el catálogo queda actualizado y el usuario externo ve solo los menús guardados.',
                'ver_tambien' => 'Cargos · Usuarios · Permisos menú.',
            ],
            'panel-acceso/cargos' => [
                'modulo_key' => 'panel-acceso/cargos',
                'titulo' => 'Panel acceso — Cargos',
                'descripcion' => '{rol} → Cargos',
                'articulo_titulo' => 'Cargos',
                'articulo_clave' => '/panel-acceso/cargos',
                'tags' => ['Módulo: Panel acceso', 'cargos'],
                'que_es' => '“Gestión de Cargos”: alta y edición de cargos (grupos) de la intranet (privilegio, nombre, descripción, notificación, estado).',
                'para_que' => 'Crear o ajustar el cargo al que luego se asignan usuarios y menús.',
                'quien' => 'Rol {rol} (Subgerencia). Quien tiene este menú puede agregar, editar y eliminar.',
                'cuando' => 'Cuando hay un cargo nuevo o hay que desactivar uno.',
                'flows' => [
                    [
                        'titulo' => 'Mantener cargos',
                        'steps' => [
                            $this->itemFlujo(
                                'Crear un cargo',
                                'Entra a Cargos. Busca por nombre. Pulsa Agregar Cargo. Completa Privilegio, Cargo y Descripción (root: también Empresa y Organización). Guarda. Sin Cargo u otro obligatorio no se crea. Los menús se marcan en Permisos menú, no aquí.',
                                'Recorta Agregar Cargo (Privilegio, Cargo, Descripción). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Editar o eliminar',
                                'En la fila: editar o eliminar. Notificación Activa/Inactiva y Estado se ven en la tabla. Eliminar un cargo afecta a los usuarios de ese grupo: confirma antes.',
                                'Recorta una fila de cargo con editar/eliminar y Notificación. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Privilegio', 'Al crear', 'Operaciones'],
                    ['Cargo', 'Nombre del grupo', 'Coordinación'],
                    ['Descripción', 'Texto libre', 'Seguimiento de cargas'],
                    ['Notificación / Estado', 'Activa-Inactiva / vigente', 'Activa'],
                ],
                'consideraciones' => 'Eliminar un cargo afecta a los usuarios de ese grupo: confirma antes. Los menús se marcan en Permisos menú, no aquí.',
                'errores' => [
                    ['No guarda', 'Falta Cargo u otro obligatorio', 'Completa Privilegio y Cargo'],
                    ['No hay registros', 'Búsqueda sin coincidencia', 'Limpia el buscador'],
                ],
                'ejemplo' => 'Cargo ficticio “Analista aduana”, privilegio Operaciones, notificación Activa. Subgerencia guarda y luego marca menús en Permisos.',
                'resultado' => 'el cargo queda en la lista y se puede asignar a usuarios.',
                'ver_tambien' => 'Usuarios · Permisos menú.',
            ],
            'panel-acceso/usuarios' => [
                'modulo_key' => 'panel-acceso/usuarios',
                'titulo' => 'Panel acceso — Usuarios',
                'descripcion' => '{rol} → Usuarios',
                'articulo_titulo' => 'Usuarios',
                'articulo_clave' => '/panel-acceso/usuarios',
                'tags' => ['Módulo: Panel acceso', 'usuarios'],
                'que_es' => '“Gestión de Usuarios”: alta y edición de personas (email, nombres, cargo, estado, contraseña).',
                'para_que' => 'Dar acceso a la intranet o cambiar el cargo de alguien.',
                'quien' => 'Rol {rol} (Subgerencia).',
                'cuando' => 'Ingreso de personal, cese (estado) o cambio de rol.',
                'flows' => [
                    [
                        'titulo' => 'Crear o corregir usuario',
                        'steps' => [
                            $this->itemFlujo(
                                'Alta',
                                'Entra a Usuarios. Busca por nombre o correo. Pulsa Agregar Usuario. Completa Cargo/Grupo, Email, Nombres y Contraseña. Guarda. Email repetido o campos vacíos no se guardan. No documentes contraseñas reales.',
                                'Recorta Agregar Usuario (cargo, email, nombres). Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Estado y menú',
                                'En la fila edita o cambia Estado. Si la persona entra pero no ve pantallas, el cargo no tiene menús: marca Consultar en Permisos menú. La columna de contraseña es de uso interno de esta pantalla.',
                                'Recorta la tabla de usuarios con Estado de una fila. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Cargo / Grupo', 'De Gestión de Cargos', 'Coordinación'],
                    ['Email (Usuario)', 'Login', 'ana.torres@ejemplo.com'],
                    ['Nombres y Apellidos', 'Alta', 'Ana Torres'],
                    ['Contraseña', 'Al crear o al resetear', 'pendiente de definir política'],
                    ['Estado', 'Activo / inactivo', 'Activo'],
                ],
                'consideraciones' => 'Sin cargo correcto la persona no verá el menú esperado: revisa también Permisos menú.\n\nNo documentes contraseñas reales en el manual.',
                'errores' => [
                    ['No guarda', 'Email repetido o campos vacíos', 'Usa un correo único y completa nombres y cargo'],
                    ['Entra pero no ve pantallas', 'El cargo no tiene menús marcados', 'Ve a Permisos menú y marca Consultar'],
                ],
                'ejemplo' => 'Usuario ficticio Ana Torres, correo ana.torres@ejemplo.com, cargo Coordinación, Activo. Ya puede entrar con esa clave.',
                'resultado' => 'la persona queda en la lista con su cargo y puede iniciar sesión (si está Activo).',
                'ver_tambien' => 'Cargos · Permisos menú.',
            ],
            'panel-acceso/permisos' => [
                'modulo_key' => 'panel-acceso/permisos',
                'titulo' => 'Panel acceso — Permisos menú',
                'descripcion' => '{rol} → Permisos menú',
                'articulo_titulo' => 'Permisos menú',
                'articulo_clave' => '/panel-acceso/permisos',
                'tags' => ['Módulo: Panel acceso', 'menú'],
                'que_es' => '“Gestión de Permisos de Menú”: qué opciones ve cada cargo (Consultar, Agregar, Editar, Eliminar).',
                'para_que' => 'Mostrar u ocultar pantallas de un rol sin tocar el código.',
                'quien' => 'Rol {rol} (Subgerencia).',
                'cuando' => 'Cuando un cargo debe ver (o dejar de ver) una pantalla.',
                'flows' => [
                    [
                        'titulo' => 'Marcar el menú de un cargo',
                        'steps' => [
                            $this->itemFlujo(
                                'Elegir cargo y marcar',
                                'Entra a Permisos menú. Elige el Cargo (root: también Empresa y Organización). En la tabla, marca Consultar / Agregar / Editar / Eliminar por ítem. Puedes usar el check-all. Esto no es el trámite de permisos de carga: aquí solo se muestra u oculta el menú.',
                                'Recorta el selector de Cargo y varias filas con checks Consultar/Agregar. Datos ficticios.'
                            ),
                            $this->itemFlujo(
                                'Guardar',
                                'Pulsa Guardar. La persona de ese cargo debe volver a entrar o recargar para ver el menú nuevo. Si no ve el menú, faltó Consultar o no recargó.',
                                'Recorta el botón Guardar y una fila ya marcada. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['Cargo', 'Selector', 'Coordinación'],
                    ['Menú', 'Fila del árbol', 'Aduanas → Permisos'],
                    ['Consultar / Agregar / Editar / Eliminar', 'Checks por fila', 'Consultar sí · Agregar no'],
                ],
                'consideraciones' => "Esto no es Aduanas → Permisos (trámites de carga).\n\nHay pantallas hermanas (permisos por usuario, menús, externos) si aparecen en tu menú; este artículo cubre el permiso por cargo.",
                'errores' => [
                    ['No ve el menú tras Guardar', 'No recargó sesión o faltó Consultar', 'Marca Consultar, Guardar, y que recargue'],
                    ['Ningún cargo tiene el menú', 'El ítem no está asignado a nadie', 'Elige el cargo y marca al menos Consultar'],
                ],
                'ejemplo' => 'Cargo Coordinación, fila “Boletín químico”: Consultar sí, Agregar sí. Guardar. Luis recarga y ya ve Aduanas → Boletín químico.',
                'resultado' => 'el cargo queda con las marcas guardadas y el menú de esas personas cambia al recargar.',
                'ver_tambien' => 'Cargos · Usuarios.',
            ],
            'agente-compra' => [
                'modulo_key' => 'agente-compra',
                'titulo' => 'Agente de compra',
                'descripcion' => '{rol} → Agente de compra',
                'articulo_titulo' => 'Agente de compra',
                'articulo_clave' => null,
                'tags' => ['Módulo: Agente de compra'],
                'que_es' => 'Opción del menú (Orden de compra / Cotización garantizada) que en esta intranet no tiene pantalla propia: la ruta está vacía.',
                'para_que' => 'pendiente de definir. Puede abrir el módulo de la intranet anterior o no hacer nada en v3.',
                'quien' => 'Rol {rol} (Asistente Comercial). El ítem aparece porque el cargo lo tiene en el menú.',
                'cuando' => 'Cuando el menú muestra Agente de compra. El flujo operativo en v3 está pendiente de definir.',
                'flows' => [
                    [
                        'titulo' => 'Qué hacer hoy',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir el menú',
                                'Pulsa Agente de compra. Si abre otra intranet, trabaja ahí Orden de compra o Cotización garantizada. Si no abre nada o vuelve al inicio, no hay formulario en esta versión: avisa a soporte. No esperes un listado aquí hasta que exista la pantalla.',
                                'Recorta el ítem de menú Agente de compra o la pantalla vacía/redirección. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['pendiente de definir', 'Módulo no migrado a esta intranet', 'pendiente de definir'],
                ],
                'consideraciones' => 'Documentado para no dejar el menú sin artículo. Cuando exista la vista v3, se reemplaza este texto.',
                'errores' => [
                    ['El menú no abre una pantalla', 'No hay ruta en esta versión', 'Usa la intranet anterior si te la habilitaron, o pide a TI el módulo'],
                ],
                'ejemplo' => 'Asistente Comercial pulsa Agente de compra. Si no carga un formulario, el caso se escala a soporte (dato ficticio: orden OC-0).',
                'resultado' => 'sabes que el módulo puede no estar en esta intranet y no esperas un listado v3.',
                'ver_tambien' => 'Cotizador · Clientes.',
            ],
            'agente-compra-trading' => [
                'modulo_key' => 'agente-compra-trading',
                'titulo' => 'Agente de compra — Trading',
                'descripcion' => '{rol} → Trading',
                'articulo_titulo' => 'Trading',
                'articulo_clave' => null,
                'tags' => ['Módulo: Agente de compra', 'China'],
                'que_es' => 'Opción del menú de Almacén China sin ruta en esta intranet (url vacía).',
                'para_que' => 'pendiente de definir con el equipo de China.',
                'quien' => 'Rol {rol} (Almacén China).',
                'cuando' => 'Cuando el menú muestra Trading. El trabajo en v3 está pendiente de definir.',
                'flows' => [
                    [
                        'titulo' => 'Qué hacer hoy',
                        'steps' => [
                            $this->itemFlujo(
                                'Abrir Trading',
                                'Pulsa Trading. Si abre la intranet anterior, opera allá. Si no abre, avisa a soporte: no hay pantalla en esta versión. No esperes un módulo aquí hasta que el menú apunte a una vista real.',
                                'Recorta el ítem Trading del menú o la pantalla vacía/redirección. Datos ficticios.'
                            ),
                        ],
                    ],
                ],
                'campos' => [
                    ['pendiente de definir', 'Módulo no migrado', 'pendiente de definir'],
                ],
                'consideraciones' => 'Artículo placeholder hasta que exista la vista. No describes botones que no están en v3.',
                'errores' => [
                    ['No carga Trading', 'Sin ruta v3', 'Confirma con China / TI si se usa otro sistema'],
                ],
                'ejemplo' => 'Almacén China pulsa Trading. Si no hay formulario, el seguimiento sigue en el sistema que indique el área (dato ficticio: lote T-0).',
                'resultado' => 'no esperas un módulo v3 hasta que el menú apunte a una pantalla real.',
                'ver_tambien' => 'Carga consolidada · Noticias.',
            ],
        ];
    }
}
