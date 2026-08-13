<?php

/**
 * Generado por: php artisan manual:scan-front-widgets --write
 * No editar a mano si vas a regenerar; preferí ajustar el scanner o merge manual.
 */
return array (
  0 => 
  array (
    'key' => 'admin.news',
    'label' => 'Admin → News',
    'page_path' => 'pages/admin/news/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-editingnews-editar-noticia-nueva-noticia',
        'label' => 'Modal — {{ editingNews ? \'Editar Noticia\' : \'Nueva Noticia\' }}',
        'tipo' => 'modal',
        'component' => 'pages/admin/news/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingNews ? \'Editar Noticia\' : \'Nueva Noticia\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'titulo',
              'label' => 'Título',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'resumen-opcional',
              'label' => 'Resumen (opcional)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'contenido',
              'label' => 'Contenido',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'tipo',
              'label' => 'Tipo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'solicitada-por',
              'label' => 'Solicitada por',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'publicar',
              'label' => 'Publicar',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'fecha-de-publicacion-opcional',
              'label' => 'Fecha de publicación (opcional)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'key' => 'url-de-redireccion-opcional',
              'label' => 'URL de redirección (opcional)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  1 => 
  array (
    'key' => 'basedatos.boletin.quimico',
    'label' => 'Basedatos → Boletin Quimico',
    'page_path' => 'pages/basedatos/boletin-quimico/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-boletin-quimico',
        'label' => 'Tabla — Boletín Químico',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/boletin-quimico/index',
        'api_hint' => 'data:data · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-filtered',
        'label' => 'Tabla — Filtered',
        'tipo' => 'tabla',
        'component' => 'components/DataTable',
        'api_hint' => 'data:filteredData · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-nuevo-boletin-quimico',
        'label' => 'Modal — Nuevo Boletín Químico',
        'tipo' => 'modal',
        'component' => 'components/basedatos/BoletinQuimicoModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Nuevo Boletín Químico',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'consolidado',
              'label' => 'Consolidado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'cliente',
              'label' => 'Cliente',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'items-de-la-cotizacion-del-cliente',
              'label' => 'Items (de la cotización del cliente)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  2 => 
  array (
    'key' => 'basedatos.clientes',
    'label' => 'Basedatos → Clientes',
    'page_path' => 'pages/basedatos/clientes/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-base-de-datos-de-clientes',
        'label' => 'Tabla — Base de datos de clientes',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/clientes/index',
        'api_hint' => 'data:clientes · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            3 => 
            array (
              'accessorKey' => 'provincia',
              'header' => 'Provincia',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'primer_servicio',
              'header' => 'Servicio',
            ),
            6 => 
            array (
              'accessorKey' => 'categoria',
              'header' => 'Categoría',
            ),
            7 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acción',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Servicio',
              'key' => 'servicio',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Curso',
                  'value' => 'Curso',
                ),
                2 => 
                array (
                  'label' => 'Consolidado',
                  'value' => 'Consolidado',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Categoría',
              'key' => 'categoria',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Cliente',
                  'value' => 'Cliente',
                ),
                2 => 
                array (
                  'label' => 'Recurrente',
                  'value' => 'Recurrente',
                ),
                3 => 
                array (
                  'label' => 'Premium',
                  'value' => 'Premium',
                ),
                4 => 
                array (
                  'label' => 'Inactivo',
                  'value' => 'Inactivo',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/basedatos/clientes/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Servicio',
              'key' => 'servicio',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Curso',
                  'value' => 'Curso',
                ),
                2 => 
                array (
                  'label' => 'Consolidado',
                  'value' => 'Consolidado',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Categoría',
              'key' => 'categoria',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Cliente',
                  'value' => 'Cliente',
                ),
                2 => 
                array (
                  'label' => 'Recurrente',
                  'value' => 'Recurrente',
                ),
                3 => 
                array (
                  'label' => 'Premium',
                  'value' => 'Premium',
                ),
                4 => 
                array (
                  'label' => 'Inactivo',
                  'value' => 'Inactivo',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  3 => 
  array (
    'key' => 'basedatos.clientes.archivos',
    'label' => 'Basedatos → Clientes → Archivos',
    'page_path' => 'pages/basedatos/clientes/archivos.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-archivos',
        'label' => 'Tabla — Archivos',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/clientes/archivos',
        'api_hint' => 'data:archivos · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/base-datos/clientes',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre_archivo',
              'header' => 'Nombre del archivo',
            ),
            2 => 
            array (
              'accessorKey' => 'created_at',
              'header' => 'Fecha de importación',
            ),
            3 => 
            array (
              'accessorKey' => 'cantidad_rows',
              'header' => 'Registros importados',
            ),
            4 => 
            array (
              'accessorKey' => 'excel',
              'header' => 'Descargar',
            ),
            5 => 
            array (
              'accessorKey' => 'accion',
              'header' => 'Acción',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/base-datos/clientes',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'modal-importar-base-de-datos-de-clientes',
        'label' => 'Modal — Importar Base de Datos de Clientes',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/clientes/archivos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Importar Base de Datos de Clientes',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Importar Excel de Clientes',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  4 => 
  array (
    'key' => 'basedatos.clientes.id',
    'label' => 'Basedatos → Clientes → Id',
    'page_path' => 'pages/basedatos/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-historial',
        'label' => 'Tabla — Historial',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/clientes/[id]',
        'api_hint' => 'data:historialComprasPaginado · columns:historialColumns',
        'live_api' => 
        array (
          'path' => 'api/base-datos/clientes',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'numero',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio',
            ),
            3 => 
            array (
              'accessorKey' => 'monto',
              'header' => 'Monto',
            ),
            4 => 
            array (
              'accessorKey' => 'is_imported',
              'header' => 'Ver',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/base-datos/clientes',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-filtered',
        'label' => 'Tabla — Filtered',
        'tipo' => 'tabla',
        'component' => 'components/DataTable',
        'api_hint' => 'data:filteredData · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  5 => 
  array (
    'key' => 'basedatos.permisos',
    'label' => 'Basedatos → Permisos',
    'page_path' => 'pages/basedatos/permisos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-permisos',
        'label' => 'Tabla — Permisos',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/permisos/index',
        'api_hint' => 'data:tramites · columns:tableColumns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'entidad',
              'header' => 'Entidad',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_permiso',
              'header' => 'T. Permiso',
            ),
            4 => 
            array (
              'accessorKey' => 'derecho_entidad',
              'header' => 'Derecho tramite',
            ),
            5 => 
            array (
              'accessorKey' => 'tramitador',
              'header' => 'Tramitador',
            ),
            6 => 
            array (
              'accessorKey' => 'total_pago_servicio',
              'header' => 'Servicio',
            ),
            7 => 
            array (
              'accessorKey' => 'f_inicio',
              'header' => 'F. Inicio',
            ),
            8 => 
            array (
              'accessorKey' => 'f_termino',
              'header' => 'F. Termino',
            ),
            9 => 
            array (
              'accessorKey' => 'f_caducidad',
              'header' => 'F. Caducidad',
            ),
            10 => 
            array (
              'accessorKey' => 'dias',
              'header' => 'Días',
            ),
            11 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'ALL',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'ALL',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/basedatos/permisos/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'ALL',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'ALL',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-filtered',
        'label' => 'Tabla — Filtered',
        'tipo' => 'tabla',
        'component' => 'components/DataTable',
        'api_hint' => 'data:filteredData · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      3 => 
      array (
        'key' => 'modal-readonly-ver-permiso-isedit-editar-permiso-crear-tramite',
        'label' => 'Modal — {{ readOnly ? \'Ver permiso\' : (isEdit ? \'Editar permiso\' : \'Crear trámite\') }}',
        'tipo' => 'modal',
        'component' => 'components/basedatos/PermisoTramiteModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ readOnly ? \'Ver permiso\' : (isEdit ? \'Editar permiso\' : \'Crear trámite\') }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'selecciona-el-consolidado',
              'label' => 'Selecciona el consolidado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'selecciona-el-cliente',
              'label' => 'Selecciona el cliente',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'selecciona-la-entidad',
              'label' => 'Selecciona la entidad',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'tramitador-s',
              'label' => 'Tramitador (S/.)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'precio-s',
              'label' => 'Precio (S/.)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Agregar tipo de permiso',
            1 => 'Cancelar',
            2 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'modal-editar-tipo-de-permiso',
        'label' => 'Modal — Editar tipo de permiso',
        'tipo' => 'modal',
        'component' => 'components/basedatos/PermisoTramiteModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Editar tipo de permiso',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'modal-editar-entidad',
        'label' => 'Modal — Editar entidad',
        'tipo' => 'modal',
        'component' => 'components/basedatos/PermisoTramiteModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Editar entidad',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  6 => 
  array (
    'key' => 'basedatos.permisos.documentos.id',
    'label' => 'Basedatos → Permisos → Documentos → Id',
    'page_path' => 'pages/basedatos/permisos/documentos/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-nuevo-documento',
        'label' => 'Modal — Nuevo documento',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/permisos/documentos/[id]',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Nuevo documento',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'nombre-del-documento',
              'label' => 'Nombre del documento',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'archivo',
              'label' => 'Archivo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Agregar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-registrar-pago',
        'label' => 'Modal — Registrar Pago ',
        'tipo' => 'modal',
        'component' => 'components/commons/CreatePagoModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Registrar Pago ',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'monto',
              'label' => 'Monto',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'banco',
              'label' => 'Banco',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-cierre',
              'label' => 'Fecha Cierre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'editcomprobante-comprobante-puedes-cambiar-el-archivo-solocomprobante-comprobante-voucher',
              'label' => 'editComprobante ? \'Comprobante (puedes cambiar el archivo)\' : (soloComprobante ? \'Comprobante\' : \'Voucher\')',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'soloComprobante ? \'Aceptar\' : \'Guardar\'',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  7 => 
  array (
    'key' => 'basedatos.productos',
    'label' => 'Basedatos → Productos',
    'page_path' => 'pages/basedatos/productos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-historial-de-productos-importados',
        'label' => 'Tabla — \'Historial de productos importados\'',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/productos/index',
        'api_hint' => 'data:tableRows · columns:tableColumns',
        'live_api' => 
        array (
          'path' => 'api/base-datos/productos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'Foto',
            ),
            1 => 
            array (
              'accessorKey' => 'nombreComercial',
              'header' => 'Rubro',
            ),
            2 => 
            array (
              'accessorKey' => 'rubro',
              'header' => 'T. Producto',
            ),
            3 => 
            array (
              'accessorKey' => 'tipoProducto',
              'header' => 'Unidad Com.',
            ),
            4 => 
            array (
              'accessorKey' => 'unidadComercial',
              'header' => 'Subpartida',
            ),
            5 => 
            array (
              'accessorKey' => 'subpartida',
              'header' => 'Campaña',
            ),
            6 => 
            array (
              'accessorKey' => 'cargaContenedor',
              'header' => 'Año',
            ),
            7 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Rubro',
              'key' => 'rubro',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Tipo Producto',
              'key' => 'tipoProducto',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            2 => 
            array (
              'label' => 'Campaña',
              'key' => 'campana',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/base-datos/productos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/basedatos/productos/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Rubro',
              'key' => 'rubro',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Tipo Producto',
              'key' => 'tipoProducto',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            2 => 
            array (
              'label' => 'Campaña',
              'key' => 'campana',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-filtered',
        'label' => 'Tabla — Filtered',
        'tipo' => 'tabla',
        'component' => 'components/DataTable',
        'api_hint' => 'data:filteredData · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  8 => 
  array (
    'key' => 'basedatos.productos.archivos',
    'label' => 'Basedatos → Productos → Archivos',
    'page_path' => 'pages/basedatos/productos/archivos.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-archivos',
        'label' => 'Tabla — Archivos',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/productos/archivos',
        'api_hint' => 'data:archivos · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre_archivo',
              'header' => 'Nombre del archivo',
            ),
            2 => 
            array (
              'accessorKey' => 'created_at',
              'header' => 'Fecha de importación',
            ),
            3 => 
            array (
              'accessorKey' => 'cantidad_rows',
              'header' => 'Registros importados',
            ),
            4 => 
            array (
              'accessorKey' => 'excel',
              'header' => 'Descargar',
            ),
            5 => 
            array (
              'accessorKey' => 'accion',
              'header' => 'Acción',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-importar-base-de-datos-de-productos',
        'label' => 'Modal — Importar Base de Datos de Productos',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/productos/archivos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Importar Base de Datos de Productos',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Importar Excel de Productos',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  9 => 
  array (
    'key' => 'basedatos.regulaciones',
    'label' => 'Basedatos → Regulaciones',
    'page_path' => 'pages/basedatos/regulaciones/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-selected-rubroregulaciones',
        'label' => 'Tabla — Selected Rubro.regulaciones',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/regulaciones/index',
        'api_hint' => 'data:selectedRubro.regulaciones · columns:[
                                    { accessorKey:',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'descripcion',
              'header' => 'Descripción',
            ),
            2 => 
            array (
              'accessorKey' => 'partida',
              'header' => 'Partida',
            ),
            3 => 
            array (
              'accessorKey' => 'precio_declarado',
              'header' => 'P. Declaración',
            ),
            4 => 
            array (
              'accessorKey' => 'antidumping',
              'header' => 'Antidumping',
            ),
            5 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            6 => 
            array (
              'accessorKey' => 'nombre',
              'header' => 'Nombre',
            ),
            7 => 
            array (
              'accessorKey' => 'c_permiso',
              'header' => 'C. Permiso',
            ),
            8 => 
            array (
              'accessorKey' => 'c_tramitador',
              'header' => 'C. Tramitador',
            ),
            9 => 
            array (
              'accessorKey' => 'imagenes',
              'header' => 'Imágenes',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-selected-entidadregulaciones',
        'label' => 'Tabla — Selected Entidad.regulaciones',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/regulaciones/index',
        'api_hint' => 'data:selectedEntidad.regulaciones · columns:[
                                { accessorKey:',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'descripcion',
              'header' => 'Descripción',
            ),
            2 => 
            array (
              'accessorKey' => 'partida',
              'header' => 'Partida',
            ),
            3 => 
            array (
              'accessorKey' => 'precio_declarado',
              'header' => 'P. Declaración',
            ),
            4 => 
            array (
              'accessorKey' => 'antidumping',
              'header' => 'Antidumping',
            ),
            5 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            6 => 
            array (
              'accessorKey' => 'nombre',
              'header' => 'Nombre',
            ),
            7 => 
            array (
              'accessorKey' => 'c_permiso',
              'header' => 'C. Permiso',
            ),
            8 => 
            array (
              'accessorKey' => 'c_tramitador',
              'header' => 'C. Tramitador',
            ),
            9 => 
            array (
              'accessorKey' => 'imagenes',
              'header' => 'Imágenes',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-selected-etiquetadoregulaciones',
        'label' => 'Tabla — Selected Etiquetado.regulaciones',
        'tipo' => 'tabla',
        'component' => 'pages/basedatos/regulaciones/index',
        'api_hint' => 'data:selectedEtiquetado.regulaciones · columns:[
                                        { accessorKey:',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'descripcion',
              'header' => 'Descripción',
            ),
            2 => 
            array (
              'accessorKey' => 'partida',
              'header' => 'Partida',
            ),
            3 => 
            array (
              'accessorKey' => 'precio_declarado',
              'header' => 'P. Declaración',
            ),
            4 => 
            array (
              'accessorKey' => 'antidumping',
              'header' => 'Antidumping',
            ),
            5 => 
            array (
              'accessorKey' => 'id',
              'header' => 'N°',
            ),
            6 => 
            array (
              'accessorKey' => 'nombre',
              'header' => 'Nombre',
            ),
            7 => 
            array (
              'accessorKey' => 'c_permiso',
              'header' => 'C. Permiso',
            ),
            8 => 
            array (
              'accessorKey' => 'c_tramitador',
              'header' => 'C. Tramitador',
            ),
            9 => 
            array (
              'accessorKey' => 'imagenes',
              'header' => 'Imágenes',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      3 => 
      array (
        'key' => 'modal-crear-producto-antidumping',
        'label' => 'Modal — Crear Producto Antidumping',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear Producto Antidumping',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Regulación',
            1 => 'Cancelar',
            2 => 'getCreateButtonLabel(activeTab)',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  10 => 
  array (
    'key' => 'basedatos.regulaciones.antidumping.crear',
    'label' => 'Basedatos → Regulaciones → Antidumping → Crear',
    'page_path' => 'pages/basedatos/regulaciones/antidumping/crear.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/antidumping/crear',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Crear Producto',
            1 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  11 => 
  array (
    'key' => 'basedatos.regulaciones.antidumping.editar.id',
    'label' => 'Basedatos → Regulaciones → Antidumping → Editar → Id',
    'page_path' => 'pages/basedatos/regulaciones/antidumping/editar/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/antidumping/editar/[id]',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Crear Producto',
            1 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  12 => 
  array (
    'key' => 'basedatos.regulaciones.documentos.crear',
    'label' => 'Basedatos → Regulaciones → Documentos → Crear',
    'page_path' => 'pages/basedatos/regulaciones/documentos/crear.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/documentos/crear',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Crear Producto',
            1 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  13 => 
  array (
    'key' => 'basedatos.regulaciones.documentos.editar.id',
    'label' => 'Basedatos → Regulaciones → Documentos → Editar → Id',
    'page_path' => 'pages/basedatos/regulaciones/documentos/editar/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/documentos/editar/[id]',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Crear Producto',
            1 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  14 => 
  array (
    'key' => 'basedatos.regulaciones.etiquetado.crear',
    'label' => 'Basedatos → Regulaciones → Etiquetado → Crear',
    'page_path' => 'pages/basedatos/regulaciones/etiquetado/crear.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/basedatos/regulaciones/etiquetado/crear',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Crear Producto',
            1 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  15 => 
  array (
    'key' => 'calendar',
    'label' => 'Calendar',
    'page_path' => 'pages/calendar/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar eliminación',
        'tipo' => 'modal',
        'component' => 'pages/calendar/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-crear-evento',
        'label' => 'Modal — Crear Evento',
        'tipo' => 'modal',
        'component' => 'components/calendar/EventModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear Evento',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'titulo',
              'label' => 'Título',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'descripcion',
              'label' => 'Descripción',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-de-inicio',
              'label' => 'Fecha de inicio',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'fecha-de-fin',
              'label' => 'Fecha de fin',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'campo',
              'label' => 'Campo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'hora-de-inicio',
              'label' => 'Hora de inicio',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'hora-de-fin',
              'label' => 'Hora de fin',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'key' => 'campo',
              'label' => 'Campo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'key' => 'campo',
              'label' => 'Campo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'key' => 'campo',
              'label' => 'Campo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Evento',
            1 => 'Tarea',
            2 => 'Cancelar',
            3 => 'isEdit ? \'Actualizar\' : \'Crear\'',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-crear-nuevo',
        'label' => 'Modal — Crear nuevo',
        'tipo' => 'modal',
        'component' => 'components/calendar/QuickCreateModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear nuevo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Continuar',
          ),
          'live_api' => NULL,
        ),
      ),
      3 => 
      array (
        'key' => 'modal-isedit-editar-actividad-nueva-actividad',
        'label' => 'Modal — {{ isEdit ? \'Editar Actividad\' : \'Nueva Actividad\' }}',
        'tipo' => 'modal',
        'component' => 'components/calendar/ActivityModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ isEdit ? \'Editar Actividad\' : \'Nueva Actividad\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'consolidado',
              'label' => 'Consolidado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'actividad',
              'label' => 'Actividad',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-de-inicio',
              'label' => 'Fecha de inicio',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'fecha-de-fin',
              'label' => 'Fecha de fin',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'prioridad',
              'label' => 'Prioridad',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'responsables',
              'label' => 'Responsables',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'notas',
              'label' => 'Notas',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'option.label',
            1 => 'Eliminar',
            2 => 'Cancelar',
            3 => 'isEdit ? \'Guardar cambios\' : \'Crear actividad\'',
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'modal-notas-de-la-actividad',
        'label' => 'Modal — Notas de la actividad',
        'tipo' => 'modal',
        'component' => 'components/calendar/NotesModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Notas de la actividad',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  16 => 
  array (
    'key' => 'calendar.actividades',
    'label' => 'Calendar → Actividades',
    'page_path' => 'pages/calendar/actividades.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-isedit-editar-actividad-nueva-actividad',
        'label' => 'Modal — {{ isEdit ? \'Editar Actividad\' : \'Nueva Actividad\' }}',
        'tipo' => 'modal',
        'component' => 'components/calendar/ActivityModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ isEdit ? \'Editar Actividad\' : \'Nueva Actividad\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'consolidado',
              'label' => 'Consolidado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'actividad',
              'label' => 'Actividad',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-de-inicio',
              'label' => 'Fecha de inicio',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'fecha-de-fin',
              'label' => 'Fecha de fin',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'prioridad',
              'label' => 'Prioridad',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'responsables',
              'label' => 'Responsables',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'notas',
              'label' => 'Notas',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'option.label',
            1 => 'Eliminar',
            2 => 'Cancelar',
            3 => 'isEdit ? \'Guardar cambios\' : \'Crear actividad\'',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  17 => 
  array (
    'key' => 'calendar.progreso',
    'label' => 'Calendar → Progreso',
    'page_path' => 'pages/calendar/progreso.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-selectedcharge-mis-notas-notas-de-la-actividad',
        'label' => 'Modal — {{ selectedCharge ? \'Mis notas\' : \'Notas de la actividad\' }}',
        'tipo' => 'modal',
        'component' => 'pages/calendar/progreso',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ selectedCharge ? \'Mis notas\' : \'Notas de la actividad\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'selectedcharge-mis-notas-notas',
              'label' => 'selectedCharge ? \'Mis notas\' : \'Notas\'',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Borrar',
            1 => 'Cancelar',
            2 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-activityname-sin-titulo',
        'label' => 'Modal — {{ activity?.name || \'Sin título\' }}',
        'tipo' => 'modal',
        'component' => 'components/calendar/ActivityTrackingModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ activity?.name || \'Sin título\' }}',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-crear-subtareas',
        'label' => 'Modal — Crear Subtareas',
        'tipo' => 'modal',
        'component' => 'components/calendar/CreateSubtasksModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear Subtareas',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'duracion-horas',
              'label' => 'Duración (horas)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-fin',
              'label' => 'Fecha fin',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Agregar otra subtarea',
            1 => 'Cancelar',
            2 => 'Crear todas',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  18 => 
  array (
    'key' => 'campanas',
    'label' => 'Campanas',
    'page_path' => 'pages/campanas/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-campanas',
        'label' => 'Tabla — Campañas',
        'tipo' => 'tabla',
        'component' => 'pages/campanas/index',
        'api_hint' => 'data:campaigns · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/campaigns',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha_creacion',
              'header' => 'Fecha de Creación',
            ),
            2 => 
            array (
              'accessorKey' => 'nombre_campana',
              'header' => 'Nombre de Campaña',
            ),
            3 => 
            array (
              'accessorKey' => 'fecha_inicio',
              'header' => 'Fecha de Inicio',
            ),
            4 => 
            array (
              'accessorKey' => 'fecha_fin',
              'header' => 'Fecha Fin',
            ),
            5 => 
            array (
              'accessorKey' => 'cantidad_personas',
              'header' => 'Cantidad de Personas',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Mes',
              'key' => 'mes',
              'type' => 'select',
              'value' => 'enero',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'enero',
                ),
                1 => 
                array (
                  'label' => 'Febrero',
                  'value' => 'febrero',
                ),
                2 => 
                array (
                  'label' => 'Marzo',
                  'value' => 'marzo',
                ),
                3 => 
                array (
                  'label' => 'Abril',
                  'value' => 'abril',
                ),
                4 => 
                array (
                  'label' => 'Mayo',
                  'value' => 'mayo',
                ),
                5 => 
                array (
                  'label' => 'Junio',
                  'value' => 'junio',
                ),
                6 => 
                array (
                  'label' => 'Julio',
                  'value' => 'julio',
                ),
                7 => 
                array (
                  'label' => 'Agosto',
                  'value' => 'agosto',
                ),
                8 => 
                array (
                  'label' => 'Septiembre',
                  'value' => 'septiembre',
                ),
                9 => 
                array (
                  'label' => 'Octubre',
                  'value' => 'octubre',
                ),
                10 => 
                array (
                  'label' => 'Noviembre',
                  'value' => 'noviembre',
                ),
                11 => 
                array (
                  'label' => 'Diciembre',
                  'value' => 'diciembre',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'activa',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'activa',
                ),
                1 => 
                array (
                  'label' => 'Finalizada',
                  'value' => 'finalizada',
                ),
                2 => 
                array (
                  'label' => 'Programada',
                  'value' => 'programada',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/campaigns',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/campanas/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Mes',
              'key' => 'mes',
              'type' => 'select',
              'value' => 'enero',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'enero',
                ),
                1 => 
                array (
                  'label' => 'Febrero',
                  'value' => 'febrero',
                ),
                2 => 
                array (
                  'label' => 'Marzo',
                  'value' => 'marzo',
                ),
                3 => 
                array (
                  'label' => 'Abril',
                  'value' => 'abril',
                ),
                4 => 
                array (
                  'label' => 'Mayo',
                  'value' => 'mayo',
                ),
                5 => 
                array (
                  'label' => 'Junio',
                  'value' => 'junio',
                ),
                6 => 
                array (
                  'label' => 'Julio',
                  'value' => 'julio',
                ),
                7 => 
                array (
                  'label' => 'Agosto',
                  'value' => 'agosto',
                ),
                8 => 
                array (
                  'label' => 'Septiembre',
                  'value' => 'septiembre',
                ),
                9 => 
                array (
                  'label' => 'Octubre',
                  'value' => 'octubre',
                ),
                10 => 
                array (
                  'label' => 'Noviembre',
                  'value' => 'noviembre',
                ),
                11 => 
                array (
                  'label' => 'Diciembre',
                  'value' => 'diciembre',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'activa',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'activa',
                ),
                1 => 
                array (
                  'label' => 'Finalizada',
                  'value' => 'finalizada',
                ),
                2 => 
                array (
                  'label' => 'Programada',
                  'value' => 'programada',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-crear-campana',
        'label' => 'Modal — Crear Campaña',
        'tipo' => 'modal',
        'component' => 'components/campanas/CreateCampaignModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear Campaña',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'mes.label',
            1 => 'Cancelar',
            2 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  19 => 
  array (
    'key' => 'cargaconsolidada.abiertos',
    'label' => 'Cargaconsolidada → Abiertos',
    'page_path' => 'pages/cargaconsolidada/abiertos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-abierta',
        'label' => 'Tabla — Carga Consolidada Abierta',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaAbiertaView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  20 => 
  array (
    'key' => 'cargaconsolidada.abiertos.clientes.id',
    'label' => 'Cargaconsolidada → Abiertos → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/abiertos/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  21 => 
  array (
    'key' => 'cargaconsolidada.abiertos.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Abiertos → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/abiertos/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  22 => 
  array (
    'key' => 'cargaconsolidada.abiertos.cotizaciones.id',
    'label' => 'Cargaconsolidada → Abiertos → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/abiertos/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  23 => 
  array (
    'key' => 'cargaconsolidada.abiertos.entrega.id',
    'label' => 'Cargaconsolidada → Abiertos → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/abiertos/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  24 => 
  array (
    'key' => 'cargaconsolidada.abiertos.factura.guia.id',
    'label' => 'Cargaconsolidada → Abiertos → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/abiertos/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  25 => 
  array (
    'key' => 'cargaconsolidada.completados',
    'label' => 'Cargaconsolidada → Completados',
    'page_path' => 'pages/cargaconsolidada/completados/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-completada',
        'label' => 'Tabla — Carga Consolidada Completada',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaCompletadosView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  26 => 
  array (
    'key' => 'cargaconsolidada.completados.clientes.id',
    'label' => 'Cargaconsolidada → Completados → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/completados/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  27 => 
  array (
    'key' => 'cargaconsolidada.completados.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Completados → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/completados/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  28 => 
  array (
    'key' => 'cargaconsolidada.completados.cotizaciones.id',
    'label' => 'Cargaconsolidada → Completados → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/completados/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  29 => 
  array (
    'key' => 'cargaconsolidada.completados.entrega.id',
    'label' => 'Cargaconsolidada → Completados → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/completados/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  30 => 
  array (
    'key' => 'cargaconsolidada.completados.factura.guia.id',
    'label' => 'Cargaconsolidada → Completados → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/completados/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  31 => 
  array (
    'key' => 'cargaconsolidada.completados.plantillas.finales.id',
    'label' => 'Cargaconsolidada → Completados → Plantillas Finales → Id',
    'page_path' => 'pages/cargaconsolidada/completados/plantillas-finales/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-historial-de-generaciones',
        'label' => 'Tabla — Historial de generaciones',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/PlantillasFinalesView/index',
        'api_hint' => 'data:batches · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre_plantilla',
              'header' => 'Plantilla',
            ),
            2 => 
            array (
              'accessorKey' => 'zip_path',
              'header' => 'ZIP generado',
            ),
            3 => 
            array (
              'accessorKey' => 'detalle',
              'header' => 'Detalle',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_inicio',
              'header' => 'Inicio',
            ),
            6 => 
            array (
              'accessorKey' => 'fecha_fin',
              'header' => 'Fin',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  32 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-abierta',
        'label' => 'Tabla — Carga Consolidada Abierta',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaAbiertaView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  33 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.clientes.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  34 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  35 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.cotizaciones.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  36 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.entrega.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  37 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.factura.guia.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  38 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.abiertos.plantillas.finales.id',
    'label' => 'Cargaconsolidada → Coordinacion → Abiertos → Plantillas Finales → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/abiertos/plantillas-finales/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-historial-de-generaciones',
        'label' => 'Tabla — Historial de generaciones',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/PlantillasFinalesView/index',
        'api_hint' => 'data:batches · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre_plantilla',
              'header' => 'Plantilla',
            ),
            2 => 
            array (
              'accessorKey' => 'zip_path',
              'header' => 'ZIP generado',
            ),
            3 => 
            array (
              'accessorKey' => 'detalle',
              'header' => 'Detalle',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_inicio',
              'header' => 'Inicio',
            ),
            6 => 
            array (
              'accessorKey' => 'fecha_fin',
              'header' => 'Fin',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  39 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados',
    'label' => 'Cargaconsolidada → Coordinacion → Completados',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-completada',
        'label' => 'Tabla — Carga Consolidada Completada',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaCompletadosView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  40 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.clientes.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  41 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  42 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.cotizaciones.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  43 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.entrega.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  44 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.factura.guia.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  45 => 
  array (
    'key' => 'cargaconsolidada.coordinacion.completados.plantillas.finales.id',
    'label' => 'Cargaconsolidada → Coordinacion → Completados → Plantillas Finales → Id',
    'page_path' => 'pages/cargaconsolidada/coordinacion/completados/plantillas-finales/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-historial-de-generaciones',
        'label' => 'Tabla — Historial de generaciones',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/PlantillasFinalesView/index',
        'api_hint' => 'data:batches · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre_plantilla',
              'header' => 'Plantilla',
            ),
            2 => 
            array (
              'accessorKey' => 'zip_path',
              'header' => 'ZIP generado',
            ),
            3 => 
            array (
              'accessorKey' => 'detalle',
              'header' => 'Detalle',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_inicio',
              'header' => 'Inicio',
            ),
            6 => 
            array (
              'accessorKey' => 'fecha_fin',
              'header' => 'Fin',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  46 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-abierta',
        'label' => 'Tabla — Carga Consolidada Abierta',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaAbiertaView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  47 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos.clientes.id',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  48 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  49 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos.cotizaciones.id',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  50 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos.entrega.id',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  51 => 
  array (
    'key' => 'cargaconsolidada.documentacion.abiertos.factura.guia.id',
    'label' => 'Cargaconsolidada → Documentacion → Abiertos → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/abiertos/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  52 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados',
    'label' => 'Cargaconsolidada → Documentacion → Completados',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-carga-consolidada-completada',
        'label' => 'Tabla — Carga Consolidada Completada',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/consolidado/CargaConsolidadaCompletadosView/index',
        'api_hint' => 'data:consolidadoData · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
            1 => 
            array (
              'accessorKey' => 'mes',
              'header' => 'Mes',
            ),
            2 => 
            array (
              'accessorKey' => 'anio',
              'header' => 'Año',
            ),
            3 => 
            array (
              'accessorKey' => 'pais',
              'header' => 'País',
            ),
            4 => 
            array (
              'accessorKey' => 'f_cierre',
              'header' => 'F. Cierre',
            ),
            5 => 
            array (
              'accessorKey' => 'fecha_arribo',
              'header' => 'F. Arribo',
            ),
            6 => 
            array (
              'accessorKey' => 'f_entrega',
              'header' => 'F. Entrega',
            ),
            7 => 
            array (
              'accessorKey' => 'empresa',
              'header' => 'Empresa',
            ),
            8 => 
            array (
              'accessorKey' => 'estado_china',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'cbm_total_peru',
              'header' => 'CBM Perú',
            ),
            10 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM China',
            ),
            11 => 
            array (
              'accessorKey' => 'limite_cbm_imo',
              'header' => 'Límite CBM IMO',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  53 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados.clientes.id',
    'label' => 'Cargaconsolidada → Documentacion → Completados → Clientes → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/clientes/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientes · columns:getColumnsGeneral() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarcados',
        'label' => 'Tabla — Embarcados',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesEmbarcados · columns:getColumnsEmbarcados() · tab:embarcados',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/embarcados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-variacion',
        'label' => 'Tabla — Variacion',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesVariacion · columns:columnsVariacion · tab:variacion',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            5 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            6 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            7 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            8 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/variacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => 'data:clientesPagos · columns:getColumnsPagos() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'products',
              'header' => 'Productos',
            ),
            4 => 
            array (
              'accessorKey' => 'supplier',
              'header' => 'Supplier',
            ),
            5 => 
            array (
              'accessorKey' => 'code_supplier',
              'header' => 'Code Supplier',
            ),
            6 => 
            array (
              'accessorKey' => 'volumen_peru',
              'header' => 'Vol. Perú',
            ),
            7 => 
            array (
              'accessorKey' => 'volumen_china',
              'header' => 'Vol. China',
            ),
            8 => 
            array (
              'accessorKey' => 'factura_comercial',
              'header' => 'Factura Comercial',
            ),
            9 => 
            array (
              'accessorKey' => 'packing_list',
              'header' => 'Packing List',
            ),
            10 => 
            array (
              'accessorKey' => 'excel_confirmacion',
              'header' => 'Excel Confirmación',
            ),
            11 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            12 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            13 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/clientes/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabs-documentacion-documentacion',
        'label' => 'Tabs — Documentación / Documentacion',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/clientes/ClientesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'documentacion',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentación',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'documentacion',
              'label' => 'Documentacion',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  54 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados.cotizacion.final.id',
    'label' => 'Cargaconsolidada → Documentacion → Completados → Cotizacion Final → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/cotizacion-final/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:general · columns:getGeneralColumns() · tab:general',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_entrega',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'volumen_final',
              'header' => 'Volumen',
            ),
            5 => 
            array (
              'accessorKey' => 'fob_final',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica_final',
              'header' => 'Logística',
            ),
            7 => 
            array (
              'accessorKey' => 'impuestos_final',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa_final',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estados',
            ),
            10 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C Final',
            ),
            11 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            12 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            13 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:pagos · columns:getPagosColumns() · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            6 => 
            array (
              'accessorKey' => 'total_logistica_impuestos',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            8 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            9 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            10 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            12 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            13 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-cargos-extra',
        'label' => 'Tabla — Cargos Extra',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => 'data:cargosExtra · columns:getCargosExtraColumns() · tab:cargos-extra',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'entrega',
              'header' => 'Entrega',
            ),
            3 => 
            array (
              'accessorKey' => 'qty_box_china',
              'header' => 'QTY Box',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_pallet_china',
              'header' => 'QTY Pallet',
            ),
            5 => 
            array (
              'accessorKey' => 'qty_total',
              'header' => 'QTY Total',
            ),
            6 => 
            array (
              'accessorKey' => 'cbm_total_china',
              'header' => 'CBM Total',
            ),
            7 => 
            array (
              'accessorKey' => 'peso_total',
              'header' => 'Peso total',
            ),
            8 => 
            array (
              'accessorKey' => 'servicio',
              'header' => 'Servicio / Importe',
            ),
            9 => 
            array (
              'accessorKey' => 'total_importe_servicios',
              'header' => 'Total Servicios',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/cotizacion-final',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabs-pagos-general',
        'label' => 'Tabs — Pagos / General',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizacion-final/CotizacionFinalView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  55 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados.cotizaciones.id',
    'label' => 'Cargaconsolidada → Documentacion → Completados → Cotizaciones → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/cotizaciones/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-prospectos',
        'label' => 'Tabla — Prospectos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizaciones · columns:getProespectosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-embarque',
        'label' => 'Tabla — Embarque',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionProveedor · columns:getEmbarqueColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'data:cotizacionPagos · columns:getPagosColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'NÂ°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            4 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'InspecciÃ³n',
            ),
            5 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            6 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            7 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            8 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            9 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            10 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            11 => 
            array (
              'accessorKey' => 'asesor',
              'header' => 'Asesor',
            ),
            12 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Status',
            ),
            13 => 
            array (
              'accessorKey' => 'n',
              'header' => 'N.',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'filtros-filterconfigprospectoscoordinacion',
        'label' => 'Filtros — Prospectos Coordinacion',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosCoordinacion',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            9 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            11 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'filtros-filterconfigprospectosalmacen',
        'label' => 'Filtros — Prospectos Almacen',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectosAlmacen',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'NS',
                  'value' => 'NS',
                ),
                6 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                7 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                8 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                9 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            7 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            8 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigprospectos',
        'label' => 'Filtros — Prospectos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigProspectos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Cotizador',
              'key' => 'estado_cotizador',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'CONTACTADO',
                  'value' => 'CONTACTADO',
                ),
                3 => 
                array (
                  'label' => 'CONFIRMADO',
                  'value' => 'CONFIRMADO',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado Proveedor',
              'key' => 'estado_china',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'WAIT',
                  'value' => 'WAIT',
                ),
                2 => 
                array (
                  'label' => 'NC',
                  'value' => 'NC',
                ),
                3 => 
                array (
                  'label' => 'NP',
                  'value' => 'NP',
                ),
                4 => 
                array (
                  'label' => 'C',
                  'value' => 'C',
                ),
                5 => 
                array (
                  'label' => 'R',
                  'value' => 'R',
                ),
                6 => 
                array (
                  'label' => 'INSPECTION',
                  'value' => 'INSPECTION',
                ),
                7 => 
                array (
                  'label' => 'LOADED',
                  'value' => 'LOADED',
                ),
                8 => 
                array (
                  'label' => 'NO LOADED',
                  'value' => 'NO LOADED',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_coordinacion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'ROTULADO',
                  'value' => 'ROTULADO',
                ),
                2 => 
                array (
                  'label' => 'DATOS PROVEEDOR',
                  'value' => 'DATOS PROVEEDOR',
                ),
                3 => 
                array (
                  'label' => 'INSPECCIONADO',
                  'value' => 'INSPECCIONADO',
                ),
                4 => 
                array (
                  'label' => 'RESERVADO',
                  'value' => 'RESERVADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'InspecciÃ³n',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'Pendiente',
                ),
                2 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                3 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                3 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'tabs-pagos-prospectos-por-embarcar',
        'label' => 'Tabs — Pagos / Prospectos / Por Embarcar',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/cotizaciones/CotizacionesView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'pagos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'prospectos',
              'label' => 'Prospectos',
              'content' => '',
            ),
            2 => 
            array (
              'key' => 'por-embarcar',
              'label' => 'Por Embarcar',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  56 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados.entrega.id',
    'label' => 'Cargaconsolidada → Documentacion → Completados → Entrega → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/entrega/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-clientes',
        'label' => 'Tabla — Clientes',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:clientes · columns:clientesColumns · tab:clientes',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-entregas',
        'label' => 'Tabla — Entregas',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:entregas · columns:entregasColumns · tab:entregas',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/entrega/EntregaView/index',
        'api_hint' => 'data:delivery · columns:deliveryColumns · tab:delivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'name',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'type_form',
              'header' => 'T. Entrega',
            ),
            4 => 
            array (
              'accessorKey' => 'origen',
              'header' => 'Origen',
            ),
            5 => 
            array (
              'accessorKey' => 'registrado',
              'header' => 'Registrado',
            ),
            6 => 
            array (
              'accessorKey' => 'entregado',
              'header' => 'Entregado',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_cotizacion_final',
              'header' => 'Cotizacion Final',
            ),
            8 => 
            array (
              'accessorKey' => 'delivery',
              'header' => 'Delivery',
            ),
            9 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N',
            ),
            10 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            11 => 
            array (
              'accessorKey' => 'cbm',
              'header' => 'Cbm',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  57 => 
  array (
    'key' => 'cargaconsolidada.documentacion.completados.factura.guia.id',
    'label' => 'Cargaconsolidada → Documentacion → Completados → Factura Guia → Id',
    'page_path' => 'pages/cargaconsolidada/documentacion/completados/factura-guia/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-general',
        'label' => 'Tabla — General',
        'tipo' => 'tabla',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => 'data:general · columns:generalColumnsByRole',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
            4 => 
            array (
              'accessorKey' => 'c_final',
              'header' => 'C. Final',
            ),
            5 => 
            array (
              'accessorKey' => 'factura_c_',
              'header' => 'Factura C. ',
            ),
            6 => 
            array (
              'accessorKey' => 'guia_r_',
              'header' => 'Guia R. ',
            ),
            7 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'Acciones',
            ),
            8 => 
            array (
              'accessorKey' => 'nro',
              'header' => 'N°',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            11 => 
            array (
              'accessorKey' => 'ajuste',
              'header' => 'Ajuste',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor/factura-guia/general',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabs-general-pagos',
        'label' => 'Tabs — General / Pagos',
        'tipo' => 'tabs',
        'component' => 'components/cargaconsolidada/factura-guia/FacturaGuiaView/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'general',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'general',
              'label' => 'General',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
    ),
  ),
  58 => 
  array (
    'key' => 'cotizaciones',
    'label' => 'Cotizaciones',
    'page_path' => 'pages/cotizaciones/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-cotizaciones',
        'label' => 'Tabla — Cotizaciones',
        'tipo' => 'tabla',
        'component' => 'pages/cotizaciones/index',
        'api_hint' => 'data:cotizaciones · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/contenedor',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            3 => 
            array (
              'accessorKey' => 'volumen',
              'header' => 'Vol',
            ),
            4 => 
            array (
              'accessorKey' => 'qty_item',
              'header' => 'Item',
            ),
            5 => 
            array (
              'accessorKey' => 'fob',
              'header' => 'Fob',
            ),
            6 => 
            array (
              'accessorKey' => 'logistica',
              'header' => 'Logistica',
            ),
            7 => 
            array (
              'accessorKey' => 'impuesto',
              'header' => 'Impuesto',
            ),
            8 => 
            array (
              'accessorKey' => 'tarifa',
              'header' => 'Tarifa',
            ),
            9 => 
            array (
              'accessorKey' => 'descuento',
              'header' => 'Desct.',
            ),
            10 => 
            array (
              'accessorKey' => 'campania',
              'header' => 'Campaña',
            ),
            11 => 
            array (
              'accessorKey' => 'cotizador',
              'header' => 'Cotizador',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/contenedor',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  59 => 
  array (
    'key' => 'curso',
    'label' => 'Curso',
    'page_path' => 'pages/curso/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-alumnos',
        'label' => 'Tabla — Alumnos',
        'tipo' => 'tabla',
        'component' => 'pages/curso/index',
        'api_hint' => 'data:cursosData · columns:getAlumnosColumns() · tab:alumnos',
        'live_api' => 
        array (
          'path' => 'api/cursos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            3 => 
            array (
              'accessorKey' => 'precio',
              'header' => 'Precio',
            ),
            4 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelanto',
              'header' => 'Adelanto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-pagos',
        'label' => 'Tabla — Pagos',
        'tipo' => 'tabla',
        'component' => 'pages/curso/index',
        'api_hint' => 'data:pagosData · columns:columnsPagos · tab:pagos',
        'live_api' => 
        array (
          'path' => 'api/cursos/pagos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'Fe_Registro',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_curso',
              'header' => 'Curso',
            ),
            4 => 
            array (
              'accessorKey' => 'campana',
              'header' => 'Campaña',
            ),
            5 => 
            array (
              'accessorKey' => 'usuario',
              'header' => 'Usuario',
            ),
            6 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            8 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            9 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            10 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            11 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/pagos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabs-alumnos-pagos',
        'label' => 'Tabs — Alumnos / Pagos',
        'tipo' => 'tabs',
        'component' => 'pages/curso/index',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'active' => 'alumnos',
          'tabs' => 
          array (
            0 => 
            array (
              'key' => 'alumnos',
              'label' => 'Alumnos',
              'content' => '',
            ),
            1 => 
            array (
              'key' => 'pagos',
              'label' => 'Pagos',
              'content' => '',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'toolbar-acciones',
        'label' => 'Toolbar — Acciones',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/index',
        'api_hint' => '#actions',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Planes landing web',
              'icon' => 'i-heroicons-globe-alt',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
            1 => 
            array (
              'label' => 'Ver Campañas',
              'icon' => 'i-heroicons-eye',
              'color' => 'primary',
              'variant' => 'solid',
            ),
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'accion-planes-landing-web',
        'label' => 'Acción — Planes landing web',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => '#actions',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Planes landing web',
          'icon' => 'i-heroicons-globe-alt',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
      5 => 
      array (
        'key' => 'accion-ver-campanas',
        'label' => 'Acción — Ver Campañas',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => '#actions',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Ver Campañas',
          'icon' => 'i-heroicons-eye',
          'color' => 'primary',
          'variant' => 'solid',
        ),
      ),
      6 => 
      array (
        'key' => 'toolbar-acciones-2',
        'label' => 'Toolbar — Acciones 2',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/index',
        'api_hint' => '#actions',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Planes landing web',
              'icon' => 'i-heroicons-globe-alt',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
          ),
        ),
      ),
      7 => 
      array (
        'key' => 'toolbar-acciones-fila',
        'label' => 'Toolbar — Acciones de fila',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Ver',
              'icon' => 'i-heroicons-eye',
              'color' => 'primary',
              'variant' => 'solid',
            ),
            1 => 
            array (
              'label' => 'Eliminar',
              'icon' => 'i-heroicons-trash',
              'color' => 'primary',
              'variant' => 'outline',
            ),
            2 => 
            array (
              'label' => 'Guardar',
              'icon' => 'ic:outline-save',
              'color' => 'primary',
              'variant' => 'outline',
            ),
            3 => 
            array (
              'label' => 'Mensaje',
              'icon' => 'i-heroicons-chat-bubble-left-right',
              'color' => 'primary',
              'variant' => 'outline',
            ),
          ),
        ),
      ),
      8 => 
      array (
        'key' => 'accion-ver',
        'label' => 'Acción — Ver',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Ver',
          'icon' => 'i-heroicons-eye',
          'color' => 'primary',
          'variant' => 'solid',
        ),
      ),
      9 => 
      array (
        'key' => 'accion-eliminar',
        'label' => 'Acción — Eliminar',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Eliminar',
          'icon' => 'i-heroicons-trash',
          'color' => 'primary',
          'variant' => 'outline',
        ),
      ),
      10 => 
      array (
        'key' => 'accion-guardar',
        'label' => 'Acción — Guardar',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Guardar',
          'icon' => 'ic:outline-save',
          'color' => 'primary',
          'variant' => 'outline',
        ),
      ),
      11 => 
      array (
        'key' => 'accion-mensaje',
        'label' => 'Acción — Mensaje',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Mensaje',
          'icon' => 'i-heroicons-chat-bubble-left-right',
          'color' => 'primary',
          'variant' => 'outline',
        ),
      ),
      12 => 
      array (
        'key' => 'toolbar-datatable',
        'label' => 'Toolbar — Controles de tabla',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Exportar',
              'icon' => 'i-heroicons-arrow-up-tray',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
            1 => 
            array (
              'label' => 'Filtros',
              'icon' => 'i-heroicons-funnel',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
            2 => 
            array (
              'label' => 'Buscar',
              'icon' => 'i-heroicons-magnifying-glass',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
          ),
        ),
      ),
      13 => 
      array (
        'key' => 'accion-exportar',
        'label' => 'Acción — Exportar',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Exportar',
          'icon' => 'i-heroicons-arrow-up-tray',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
      14 => 
      array (
        'key' => 'accion-filtros',
        'label' => 'Acción — Filtros',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Filtros',
          'icon' => 'i-heroicons-funnel',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
      15 => 
      array (
        'key' => 'accion-buscar',
        'label' => 'Acción — Buscar',
        'tipo' => 'accion',
        'component' => 'pages/curso/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Buscar',
          'icon' => 'i-heroicons-magnifying-glass',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
      16 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'composables/useCursos.ts',
        'api_hint' => 'filterConfig',
        'live_api' => 
        array (
          'path' => 'api/cursos/filters/options',
          'method' => 'GET',
          'params' => 
          array (
          ),
          'data_key' => 'data',
          'kind' => 'filter_options',
        ),
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estados_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'Adelanto',
                  'value' => 'ADELANTO',
                ),
                3 => 
                array (
                  'label' => 'Pagado',
                  'value' => 'PAGADO',
                ),
                4 => 
                array (
                  'label' => 'Sobrepago',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha de inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha de fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Tipo de curso',
              'key' => 'tipo_curso',
              'type' => 'select',
              'value' => '0',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Virtual',
                  'value' => '0',
                ),
                1 => 
                array (
                  'label' => 'En vivo',
                  'value' => '1',
                ),
              ),
            ),
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/filters/options',
            'method' => 'GET',
            'params' => 
            array (
            ),
            'data_key' => 'data',
            'kind' => 'filter_options',
          ),
        ),
      ),
      17 => 
      array (
        'key' => 'filtros-filterconfigpagos',
        'label' => 'Filtros — Pagos',
        'tipo' => 'filtros',
        'component' => 'composables/curso/usePagos.ts',
        'api_hint' => 'filterConfigPagos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estados_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'Adelanto',
                  'value' => 'ADELANTO',
                ),
                3 => 
                array (
                  'label' => 'Pagado',
                  'value' => 'PAGADO',
                ),
                4 => 
                array (
                  'label' => 'Sobrepago',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha de inicio',
              'key' => 'fecha_inicio',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha de fin',
              'key' => 'fecha_fin',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Tipo de curso',
              'key' => 'tipo_curso',
              'type' => 'select',
              'value' => '0',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Virtual',
                  'value' => '0',
                ),
                1 => 
                array (
                  'label' => 'En vivo',
                  'value' => '1',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  60 => 
  array (
    'key' => 'curso.campanas',
    'label' => 'Curso → Campanas',
    'page_path' => 'pages/curso/campanas/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-campanas',
        'label' => 'Tabla — Campañas',
        'tipo' => 'tabla',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'data:campaigns · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/campaigns',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'fecha_creacion',
              'header' => 'Fecha de Creación',
            ),
            2 => 
            array (
              'accessorKey' => 'nombre_campana',
              'header' => 'Nombre de Campaña',
            ),
            3 => 
            array (
              'accessorKey' => 'fecha_inicio',
              'header' => 'Fecha de Inicio',
            ),
            4 => 
            array (
              'accessorKey' => 'fecha_fin',
              'header' => 'Fecha Fin',
            ),
            5 => 
            array (
              'accessorKey' => 'cantidad_personas',
              'header' => 'Cantidad de Personas',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Mes',
              'key' => 'mes',
              'type' => 'select',
              'value' => 'enero',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'enero',
                ),
                1 => 
                array (
                  'label' => 'Febrero',
                  'value' => 'febrero',
                ),
                2 => 
                array (
                  'label' => 'Marzo',
                  'value' => 'marzo',
                ),
                3 => 
                array (
                  'label' => 'Abril',
                  'value' => 'abril',
                ),
                4 => 
                array (
                  'label' => 'Mayo',
                  'value' => 'mayo',
                ),
                5 => 
                array (
                  'label' => 'Junio',
                  'value' => 'junio',
                ),
                6 => 
                array (
                  'label' => 'Julio',
                  'value' => 'julio',
                ),
                7 => 
                array (
                  'label' => 'Agosto',
                  'value' => 'agosto',
                ),
                8 => 
                array (
                  'label' => 'Septiembre',
                  'value' => 'septiembre',
                ),
                9 => 
                array (
                  'label' => 'Octubre',
                  'value' => 'octubre',
                ),
                10 => 
                array (
                  'label' => 'Noviembre',
                  'value' => 'noviembre',
                ),
                11 => 
                array (
                  'label' => 'Diciembre',
                  'value' => 'diciembre',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'activa',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'activa',
                ),
                1 => 
                array (
                  'label' => 'Finalizada',
                  'value' => 'finalizada',
                ),
                2 => 
                array (
                  'label' => 'Programada',
                  'value' => 'programada',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/campaigns',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Mes',
              'key' => 'mes',
              'type' => 'select',
              'value' => 'enero',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'enero',
                ),
                1 => 
                array (
                  'label' => 'Febrero',
                  'value' => 'febrero',
                ),
                2 => 
                array (
                  'label' => 'Marzo',
                  'value' => 'marzo',
                ),
                3 => 
                array (
                  'label' => 'Abril',
                  'value' => 'abril',
                ),
                4 => 
                array (
                  'label' => 'Mayo',
                  'value' => 'mayo',
                ),
                5 => 
                array (
                  'label' => 'Junio',
                  'value' => 'junio',
                ),
                6 => 
                array (
                  'label' => 'Julio',
                  'value' => 'julio',
                ),
                7 => 
                array (
                  'label' => 'Agosto',
                  'value' => 'agosto',
                ),
                8 => 
                array (
                  'label' => 'Septiembre',
                  'value' => 'septiembre',
                ),
                9 => 
                array (
                  'label' => 'Octubre',
                  'value' => 'octubre',
                ),
                10 => 
                array (
                  'label' => 'Noviembre',
                  'value' => 'noviembre',
                ),
                11 => 
                array (
                  'label' => 'Diciembre',
                  'value' => 'diciembre',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'activa',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'activa',
                ),
                1 => 
                array (
                  'label' => 'Finalizada',
                  'value' => 'finalizada',
                ),
                2 => 
                array (
                  'label' => 'Programada',
                  'value' => 'programada',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'toolbar-acciones-fila',
        'label' => 'Toolbar — Acciones de fila',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Eliminar',
              'icon' => 'i-heroicons-trash',
              'color' => 'error',
              'variant' => 'ghost',
            ),
            1 => 
            array (
              'label' => 'Ver',
              'icon' => 'i-heroicons-eye',
              'color' => 'primary',
              'variant' => 'ghost',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'accion-eliminar',
        'label' => 'Acción — Eliminar',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Eliminar',
          'icon' => 'i-heroicons-trash',
          'color' => 'error',
          'variant' => 'ghost',
        ),
      ),
      4 => 
      array (
        'key' => 'accion-ver',
        'label' => 'Acción — Ver',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Ver',
          'icon' => 'i-heroicons-eye',
          'color' => 'primary',
          'variant' => 'ghost',
        ),
      ),
      5 => 
      array (
        'key' => 'toolbar-datatable',
        'label' => 'Toolbar — Controles de tabla',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Nuevo',
              'icon' => 'i-heroicons-plus',
              'color' => 'primary',
              'variant' => 'solid',
            ),
          ),
        ),
      ),
      6 => 
      array (
        'key' => 'accion-nuevo',
        'label' => 'Acción — Nuevo',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/index',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Nuevo',
          'icon' => 'i-heroicons-plus',
          'color' => 'primary',
          'variant' => 'solid',
        ),
      ),
      7 => 
      array (
        'key' => 'modal-crear-campana',
        'label' => 'Modal — Crear Campaña',
        'tipo' => 'modal',
        'component' => 'components/campanas/CreateCampaignModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Crear Campaña',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'mes.label',
            1 => 'Cancelar',
            2 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  61 => 
  array (
    'key' => 'curso.campanas.id',
    'label' => 'Curso → Campanas → Id',
    'page_path' => 'pages/curso/campanas/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-estudiantes-de-la-campana',
        'label' => 'Tabla — Estudiantes de la Campaña',
        'tipo' => 'tabla',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'data:students · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/campaigns',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'numero',
              'header' => 'N.',
            ),
            1 => 
            array (
              'accessorKey' => 'Fe_Registro',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'tipo_curso',
              'header' => 'Curso',
            ),
            4 => 
            array (
              'accessorKey' => 'campana',
              'header' => 'Campaña',
            ),
            5 => 
            array (
              'accessorKey' => 'usuario',
              'header' => 'Usuario',
            ),
            6 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            7 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'pagado',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'pagado',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'pendiente',
                ),
                2 => 
                array (
                  'label' => 'Cancelado',
                  'value' => 'cancelado',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/campaigns',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'filterConfig',
        'live_api' => 
        array (
          'path' => 'api/cursos/filters/options',
          'method' => 'GET',
          'params' => 
          array (
          ),
          'data_key' => 'data',
          'kind' => 'filter_options',
        ),
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'pagado',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'pagado',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'pendiente',
                ),
                2 => 
                array (
                  'label' => 'Cancelado',
                  'value' => 'cancelado',
                ),
              ),
            ),
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/filters/options',
            'method' => 'GET',
            'params' => 
            array (
            ),
            'data_key' => 'data',
            'kind' => 'filter_options',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'filtros-filterconfiglist',
        'label' => 'Filtros — List',
        'tipo' => 'filtros',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'filterConfigList',
        'live_api' => 
        array (
          'path' => 'api/cursos/filters/options',
          'method' => 'GET',
          'params' => 
          array (
          ),
          'data_key' => 'data',
          'kind' => 'filter_options',
        ),
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'pagado',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'pagado',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'pendiente',
                ),
                2 => 
                array (
                  'label' => 'Cancelado',
                  'value' => 'cancelado',
                ),
              ),
            ),
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/filters/options',
            'method' => 'GET',
            'params' => 
            array (
            ),
            'data_key' => 'data',
            'kind' => 'filter_options',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'toolbar-acciones-fila',
        'label' => 'Toolbar — Acciones de fila',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Ver',
              'icon' => 'i-heroicons-eye',
              'color' => 'warning',
              'variant' => 'solid',
            ),
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'accion-ver',
        'label' => 'Acción — Ver',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Ver',
          'icon' => 'i-heroicons-eye',
          'color' => 'warning',
          'variant' => 'solid',
        ),
      ),
      5 => 
      array (
        'key' => 'toolbar-datatable',
        'label' => 'Toolbar — Controles de tabla',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Exportar',
              'icon' => 'i-heroicons-arrow-up-tray',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
            1 => 
            array (
              'label' => 'Buscar',
              'icon' => 'i-heroicons-magnifying-glass',
              'color' => 'neutral',
              'variant' => 'outline',
            ),
          ),
        ),
      ),
      6 => 
      array (
        'key' => 'accion-exportar',
        'label' => 'Acción — Exportar',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Exportar',
          'icon' => 'i-heroicons-arrow-up-tray',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
      7 => 
      array (
        'key' => 'accion-buscar',
        'label' => 'Acción — Buscar',
        'tipo' => 'accion',
        'component' => 'pages/curso/campanas/[id]',
        'api_hint' => 'DataTable chrome',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Buscar',
          'icon' => 'i-heroicons-magnifying-glass',
          'color' => 'neutral',
          'variant' => 'outline',
        ),
      ),
    ),
  ),
  62 => 
  array (
    'key' => 'curso.id',
    'label' => 'Curso → Id',
    'page_path' => 'pages/curso/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'composables/useCursos.ts',
        'api_hint' => 'filterConfig',
        'live_api' => 
        array (
          'path' => 'api/cursos/filters/options',
          'method' => 'GET',
          'params' => 
          array (
          ),
          'data_key' => 'data',
          'kind' => 'filter_options',
        ),
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estados_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'Adelanto',
                  'value' => 'ADELANTO',
                ),
                3 => 
                array (
                  'label' => 'Pagado',
                  'value' => 'PAGADO',
                ),
                4 => 
                array (
                  'label' => 'Sobrepago',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha de inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha de fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
              ),
            ),
            4 => 
            array (
              'label' => 'Tipo de curso',
              'key' => 'tipo_curso',
              'type' => 'select',
              'value' => '0',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Virtual',
                  'value' => '0',
                ),
                1 => 
                array (
                  'label' => 'En vivo',
                  'value' => '1',
                ),
              ),
            ),
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/filters/options',
            'method' => 'GET',
            'params' => 
            array (
            ),
            'data_key' => 'data',
            'kind' => 'filter_options',
          ),
        ),
      ),
    ),
  ),
  63 => 
  array (
    'key' => 'curso.planes.web',
    'label' => 'Curso → Planes Web',
    'page_path' => 'pages/curso/planes-web.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-planes',
        'label' => 'Tabla — Planes',
        'tipo' => 'tabla',
        'component' => 'pages/curso/planes-web',
        'api_hint' => 'data:planes · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/cursos/web-planes-membresia',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'sort_order',
              'header' => 'Orden',
            ),
            1 => 
            array (
              'accessorKey' => 'title',
              'header' => 'Título',
            ),
            2 => 
            array (
              'accessorKey' => 'subtitle',
              'header' => 'Subtítulo',
            ),
            3 => 
            array (
              'accessorKey' => 'price_amount',
              'header' => 'Monto',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/cursos/web-planes-membresia',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'modal-isediting-editar-plan-nuevo-plan',
        'label' => 'Modal — {{ isEditing ? \'Editar plan\' : \'Nuevo plan\' }}',
        'tipo' => 'modal',
        'component' => 'pages/curso/planes-web',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ isEditing ? \'Editar plan\' : \'Nuevo plan\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'orden',
              'label' => 'Orden',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'titulo',
              'label' => 'Título',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'subtitulo',
              'label' => 'Subtítulo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'monto-principal-pen',
              'label' => 'Monto principal (PEN)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'precio-de-lista-pen',
              'label' => 'Precio de lista (PEN)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'beneficios',
              'label' => 'Beneficios',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'toolbar-acciones-fila',
        'label' => 'Toolbar — Acciones de fila',
        'tipo' => 'toolbar',
        'component' => 'pages/curso/planes-web',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'buttons' => 
          array (
            0 => 
            array (
              'label' => 'Editar',
              'icon' => 'i-heroicons-pencil-square',
              'color' => 'primary',
              'variant' => 'ghost',
            ),
            1 => 
            array (
              'label' => 'Eliminar',
              'icon' => 'i-heroicons-trash',
              'color' => 'error',
              'variant' => 'ghost',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'accion-editar',
        'label' => 'Acción — Editar',
        'tipo' => 'accion',
        'component' => 'pages/curso/planes-web',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Editar',
          'icon' => 'i-heroicons-pencil-square',
          'color' => 'primary',
          'variant' => 'ghost',
        ),
      ),
      4 => 
      array (
        'key' => 'accion-eliminar',
        'label' => 'Acción — Eliminar',
        'tipo' => 'accion',
        'component' => 'pages/curso/planes-web',
        'api_hint' => 'column:acciones',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'label' => 'Eliminar',
          'icon' => 'i-heroicons-trash',
          'color' => 'error',
          'variant' => 'ghost',
        ),
      ),
      5 => 
      array (
        'key' => 'card-tabla',
        'label' => 'Card — Tabla',
        'tipo' => 'card',
        'component' => 'pages/curso/planes-web',
        'api_hint' => 'contiene tabla',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Tabla',
          'icon' => NULL,
          'body' => 'No hay planes. Usa «Nuevo plan» para añadir el primero.',
          'fields' => 
          array (
          ),
          'buttons' => 
          array (
          ),
        ),
      ),
    ),
  ),
  64 => 
  array (
    'key' => 'dashboard',
    'label' => 'Dashboard',
    'page_path' => 'pages/dashboard/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-detalle-de-transacciones',
        'label' => 'Tabla — Detalle de Transacciones',
        'tipo' => 'tabla',
        'component' => 'pages/dashboard/index',
        'api_hint' => 'data:resumenData · columns:transactionColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/dashboard-ventas',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'fecha_zarpe',
              'header' => 'Fecha',
            ),
            1 => 
            array (
              'accessorKey' => 'vendedor',
              'header' => 'Vendedor',
            ),
            2 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Contenedor',
            ),
            3 => 
            array (
              'accessorKey' => 'volumenes',
              'header' => 'Vol. Vendido (m³)',
            ),
            4 => 
            array (
              'accessorKey' => 'volumenes',
              'header' => 'Vol. Pendiente (m³)',
            ),
            5 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Total ($)',
            ),
            6 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Impuestos ($)',
            ),
            7 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Logística ($)',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/dashboard-ventas',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  65 => 
  array (
    'key' => 'datos.facturacion',
    'label' => 'Datos Facturacion',
    'page_path' => 'pages/datos-facturacion.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-imports',
        'label' => 'Tabla — Imports',
        'tipo' => 'tabla',
        'component' => 'pages/datos-facturacion',
        'api_hint' => 'data:imports · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/base-datos/clientes/facturacion',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nombre_archivo',
              'header' => 'Archivo',
            ),
            1 => 
            array (
              'accessorKey' => 'created_at',
              'header' => 'Fecha',
            ),
            2 => 
            array (
              'accessorKey' => 'cantidad_rows',
              'header' => 'Filas Creadas',
            ),
            3 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            4 => 
            array (
              'accessorKey' => 'accion',
              'header' => 'Accion',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/base-datos/clientes/facturacion',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  66 => 
  array (
    'key' => 'inspeccionados',
    'label' => 'Inspeccionados',
    'page_path' => 'pages/inspeccionados/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-inspeccionados',
        'label' => 'Tabla — Inspeccionados',
        'tipo' => 'tabla',
        'component' => 'pages/inspeccionados/index',
        'api_hint' => 'data:inspeccionados · columns:getColumns()',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/inspeccionados',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N°',
            ),
            1 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            2 => 
            array (
              'accessorKey' => 'tipo_cliente',
              'header' => 'T. Cliente',
            ),
            3 => 
            array (
              'accessorKey' => 'campana',
              'header' => 'Campaña',
            ),
            4 => 
            array (
              'accessorKey' => 'f_inspeccion',
              'header' => 'F. Inspección',
            ),
            5 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            6 => 
            array (
              'accessorKey' => 'estado_inspeccion',
              'header' => 'Inspección',
            ),
            7 => 
            array (
              'accessorKey' => 'estado_pago',
              'header' => 'Estado',
            ),
            8 => 
            array (
              'accessorKey' => 'concepto',
              'header' => 'Concepto',
            ),
            9 => 
            array (
              'accessorKey' => 'importe',
              'header' => 'Importe',
            ),
            10 => 
            array (
              'accessorKey' => 'pagado',
              'header' => 'Pagado',
            ),
            11 => 
            array (
              'accessorKey' => 'diferencia',
              'header' => 'Diferencia',
            ),
            12 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'F. Inspección Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'F. Inspección Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Inspección',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                2 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                3 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/inspeccionados',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'filtros-filterconfig',
        'label' => 'Filtros — General',
        'tipo' => 'filtros',
        'component' => 'pages/inspeccionados/index',
        'api_hint' => 'filterConfig',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'F. Inspección Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'F. Inspección Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Estado Inspección',
              'key' => 'estado_inspeccion',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'Inspeccionado',
                  'value' => 'Inspeccionado',
                ),
                2 => 
                array (
                  'label' => 'Completado',
                  'value' => 'Completado',
                ),
              ),
            ),
            3 => 
            array (
              'label' => 'Estado',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos',
                  'value' => 'todos',
                ),
                1 => 
                array (
                  'label' => 'PENDIENTE',
                  'value' => 'PENDIENTE',
                ),
                2 => 
                array (
                  'label' => 'ADELANTO',
                  'value' => 'ADELANTO',
                ),
                3 => 
                array (
                  'label' => 'PAGADO',
                  'value' => 'PAGADO',
                ),
                4 => 
                array (
                  'label' => 'SOBREPAGO',
                  'value' => 'SOBREPAGO',
                ),
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  67 => 
  array (
    'key' => 'landing.leads',
    'label' => 'Landing → Leads',
    'page_path' => 'pages/landing/leads.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-tabletitle',
        'label' => 'Tabla — tableTitle',
        'tipo' => 'tabla',
        'component' => 'pages/landing/leads',
        'api_hint' => 'data:tableData · columns:tableColumns',
        'live_api' => 
        array (
          'path' => 'api/landing-leads',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'nombre',
              'header' => 'Nombre',
            ),
            2 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'WhatsApp',
            ),
            3 => 
            array (
              'accessorKey' => 'proveedor',
              'header' => 'Proveedor',
            ),
            4 => 
            array (
              'accessorKey' => 'codigo_campana',
              'header' => 'Campaña',
            ),
            5 => 
            array (
              'accessorKey' => 'ip_address',
              'header' => 'IP',
            ),
            6 => 
            array (
              'accessorKey' => 'created_at',
              'header' => 'Fecha',
            ),
            7 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            8 => 
            array (
              'accessorKey' => 'nombre',
              'header' => 'Nombre',
            ),
            9 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'WhatsApp',
            ),
            10 => 
            array (
              'accessorKey' => 'email',
              'header' => 'Email',
            ),
            11 => 
            array (
              'accessorKey' => 'experiencia_importando',
              'header' => 'Experiencia',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/landing-leads',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  68 => 
  array (
    'key' => 'manual.usuario',
    'label' => 'Manual Usuario',
    'page_path' => 'pages/manual-usuario/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-blocktitulo',
        'label' => 'Tabla — block.titulo || \'\'',
        'tipo' => 'tabla',
        'component' => 'components/manual/ManualBlockRenderer',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  69 => 
  array (
    'key' => 'mi.progreso',
    'label' => 'Mi Progreso',
    'page_path' => 'pages/mi-progreso.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-detalle-de-transacciones',
        'label' => 'Tabla — Detalle de Transacciones',
        'tipo' => 'tabla',
        'component' => 'pages/mi-progreso',
        'api_hint' => 'data:resumenData · columns:transactionColumns',
        'live_api' => 
        array (
          'path' => 'api/dashboard-usuario/ventas',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'fecha_zarpe',
              'header' => 'Fecha',
            ),
            1 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Contenedor',
            ),
            2 => 
            array (
              'accessorKey' => 'volumenes',
              'header' => 'Vol. Vendido (m³)',
            ),
            3 => 
            array (
              'accessorKey' => 'volumenes',
              'header' => 'Vol. Pendiente (m³)',
            ),
            4 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Total ($)',
            ),
            5 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Impuestos ($)',
            ),
            6 => 
            array (
              'accessorKey' => 'totales',
              'header' => 'Logística ($)',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/dashboard-usuario/ventas',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
    ),
  ),
  70 => 
  array (
    'key' => 'panel.acceso.cargos',
    'label' => 'Panel Acceso → Cargos',
    'page_path' => 'pages/panel-acceso/cargos.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-editinggrupo-editar-cargo-nuevo-cargo',
        'label' => 'Modal — {{ editingGrupo ? \'Editar Cargo\' : \'Nuevo Cargo\' }}',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/cargos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingGrupo ? \'Editar Cargo\' : \'Nuevo Cargo\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'empresa',
              'label' => 'Empresa',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'organizacion',
              'label' => 'Organización',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'privilegio',
              'label' => 'Privilegio',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'cargo',
              'label' => 'Cargo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'descripcion',
              'label' => 'Descripción',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'estado',
              'label' => 'Estado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar Eliminación',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/cargos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar Eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  71 => 
  array (
    'key' => 'panel.acceso.menus',
    'label' => 'Panel Acceso → Menus',
    'page_path' => 'pages/panel-acceso/menus.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-gestion-de-menus',
        'label' => 'Tabla — Gestión de Menús',
        'tipo' => 'tabla',
        'component' => 'pages/panel-acceso/menus',
        'api_hint' => 'data:sortedMenus · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'ruta',
              'header' => 'Estado',
            ),
            1 => 
            array (
              'accessorKey' => 'activo',
              'header' => 'Roles asignados',
            ),
            2 => 
            array (
              'accessorKey' => 'total_roles',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-editingmenu-editar-menu-nuevo-menu',
        'label' => 'Modal — {{ editingMenu ? \'Editar Menú\' : \'Nuevo Menú\' }}',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingMenu ? \'Editar Menú\' : \'Nuevo Menú\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'tipo-de-menu',
              'label' => 'Tipo de menú',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'menu-padre',
              'label' => 'Menú Padre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'orden',
              'label' => 'Orden',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'ruta-nuxt',
              'label' => 'Ruta Nuxt',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'icono',
              'label' => 'Ícono',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'url-de-video',
              'label' => 'URL de Video',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'key' => 'estado',
              'label' => 'Estado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
      3 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar Eliminación',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar Eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'modal-seleccionar-icono',
        'label' => 'Modal — Seleccionar Ícono',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Seleccionar Ícono',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  72 => 
  array (
    'key' => 'panel.acceso.menus.externos',
    'label' => 'Panel Acceso → Menus Externos',
    'page_path' => 'pages/panel-acceso/menus-externos.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-menus-externos',
        'label' => 'Tabla — Menús Externos',
        'tipo' => 'tabla',
        'component' => 'pages/panel-acceso/menus-externos',
        'api_hint' => 'data:sortedMenus · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'icono',
              'header' => 'Ruta',
            ),
            1 => 
            array (
              'accessorKey' => 'ruta',
              'header' => 'Estado',
            ),
            2 => 
            array (
              'accessorKey' => 'activo',
              'header' => 'Usuarios',
            ),
            3 => 
            array (
              'accessorKey' => 'total_usuarios',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-editingmenu-editar-menu-externo-nuevo-menu-externo',
        'label' => 'Modal — {{ editingMenu ? \'Editar Menú Externo\' : \'Nuevo Menú Externo\' }}',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingMenu ? \'Editar Menú Externo\' : \'Nuevo Menú Externo\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'tipo-de-menu',
              'label' => 'Tipo de menú',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'menu-padre',
              'label' => 'Menú Padre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'orden',
              'label' => 'Orden',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'ruta',
              'label' => 'Ruta',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'icono',
              'label' => 'Ícono',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'key' => 'url-de-video',
              'label' => 'URL de Video',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'key' => 'estado',
              'label' => 'Estado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-modal',
        'label' => 'Modal — Modal',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Modal',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
      3 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar Eliminación',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar Eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
      4 => 
      array (
        'key' => 'modal-seleccionar-icono',
        'label' => 'Modal — Seleccionar Ícono',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/menus-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Seleccionar Ícono',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  73 => 
  array (
    'key' => 'panel.acceso.usuarios',
    'label' => 'Panel Acceso → Usuarios',
    'page_path' => 'pages/panel-acceso/usuarios.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-gestion-de-usuarios',
        'label' => 'Tabla — Gestión de Usuarios',
        'tipo' => 'tabla',
        'component' => 'pages/panel-acceso/usuarios',
        'api_hint' => 'data:usuarios · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cargo',
              'header' => 'Cargo',
            ),
            1 => 
            array (
              'accessorKey' => 'usuario',
              'header' => 'Usuario (Email)',
            ),
            2 => 
            array (
              'accessorKey' => 'password_sin_encriptar',
              'header' => 'Contraseña actual',
            ),
            3 => 
            array (
              'accessorKey' => 'nombres_apellidos',
              'header' => 'Nombres y Apellidos',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-editingusuario-editar-usuario-nuevo-usuario',
        'label' => 'Modal — {{ editingUsuario ? \'Editar Usuario\' : \'Nuevo Usuario\' }}',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/usuarios',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingUsuario ? \'Editar Usuario\' : \'Nuevo Usuario\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'cargo-grupo',
              'label' => 'Cargo / Grupo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'email-usuario',
              'label' => 'Email (usuario)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'nombres-y-apellidos',
              'label' => 'Nombres y Apellidos',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'editingusuario-nueva-contrasena-opcional-contrasena',
              'label' => 'editingUsuario ? \'Nueva contraseña (opcional)\' : \'Contraseña\'',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'celular',
              'label' => 'Celular',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'estado',
              'label' => 'Estado',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar Eliminación',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/usuarios',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar Eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  74 => 
  array (
    'key' => 'panel.acceso.usuarios.externos',
    'label' => 'Panel Acceso → Usuarios Externos',
    'page_path' => 'pages/panel-acceso/usuarios-externos.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-usuarios-externos',
        'label' => 'Tabla — Usuarios Externos',
        'tipo' => 'tabla',
        'component' => 'pages/panel-acceso/usuarios-externos',
        'api_hint' => 'data:usuarios · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'nombre_completo',
              'header' => 'Nombre',
            ),
            1 => 
            array (
              'accessorKey' => 'email',
              'header' => 'Email',
            ),
            2 => 
            array (
              'accessorKey' => 'whatsapp',
              'header' => 'WhatsApp',
            ),
            3 => 
            array (
              'accessorKey' => 'dni',
              'header' => 'DNI',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-editingusuario-editar-usuario-externo-nuevo-usuario-externo',
        'label' => 'Modal — {{ editingUsuario ? \'Editar Usuario Externo\' : \'Nuevo Usuario Externo\' }}',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/usuarios-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => '{{ editingUsuario ? \'Editar Usuario Externo\' : \'Nuevo Usuario Externo\' }}',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'nombre',
              'label' => 'Nombre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'apellido',
              'label' => 'Apellido',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'email',
              'label' => 'Email',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'editingusuario-nueva-contrasena-dejar-vacio-no-cambiar-contrasena',
              'label' => 'editingUsuario ? \'Nueva Contraseña (dejar vacío = no cambiar)\' : \'Contraseña\'',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'whatsapp',
              'label' => 'WhatsApp',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'dni',
              'label' => 'DNI',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-confirmar-eliminacion',
        'label' => 'Modal — Confirmar Eliminación',
        'tipo' => 'modal',
        'component' => 'pages/panel-acceso/usuarios-externos',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Confirmar Eliminación',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Eliminar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  75 => 
  array (
    'key' => 'soporte.ti',
    'label' => 'Soporte Ti',
    'page_path' => 'pages/soporte-ti/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-filas-tabla',
        'label' => 'Tabla — Filas Tabla',
        'tipo' => 'tabla',
        'component' => 'pages/soporte-ti/index',
        'api_hint' => 'data:filasTabla · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'titulo',
              'header' => 'Estado',
            ),
            1 => 
            array (
              'accessorKey' => 'codigo',
              'header' => 'Acciones',
            ),
            2 => 
            array (
              'accessorKey' => 'codigo',
              'header' => 'Código',
            ),
            3 => 
            array (
              'accessorKey' => 'tipoSolicitud',
              'header' => 'Tipo solicitud',
            ),
            4 => 
            array (
              'accessorKey' => 'fechaRegistroCompleta',
              'header' => 'Fecha de registro',
            ),
            5 => 
            array (
              'accessorKey' => 'fechaFinEstimadoFmt',
              'header' => 'Término estimado',
            ),
            6 => 
            array (
              'accessorKey' => 'codigo',
              'header' => 'Evidencia',
            ),
            7 => 
            array (
              'accessorKey' => 'area',
              'header' => 'Área',
            ),
            8 => 
            array (
              'accessorKey' => 'prioridad',
              'header' => 'Prioridad',
            ),
            9 => 
            array (
              'accessorKey' => 'complejidadPm',
              'header' => 'Compl. PM',
            ),
            10 => 
            array (
              'accessorKey' => 'criticidad',
              'header' => 'Compl. analista',
            ),
            11 => 
            array (
              'accessorKey' => 'codigo',
              'header' => 'Código',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-filtered',
        'label' => 'Tabla — Filtered',
        'tipo' => 'tabla',
        'component' => 'components/DataTable',
        'api_hint' => 'data:filteredData · columns:columns',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'c0',
              'header' => 'Columna 1',
            ),
            1 => 
            array (
              'accessorKey' => 'c1',
              'header' => 'Columna 2',
            ),
            2 => 
            array (
              'accessorKey' => 'c2',
              'header' => 'Columna 3',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => NULL,
        ),
      ),
      2 => 
      array (
        'key' => 'modal-nueva-solicitud',
        'label' => 'Modal — Nueva solicitud',
        'tipo' => 'modal',
        'component' => 'components/soporte-ti/SoporteTiModalCreate',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Nueva solicitud',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'subtipo',
              'label' => 'Subtipo',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'area-solicitante',
              'label' => 'Área solicitante',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'url',
              'label' => 'URL',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'titulo',
              'label' => 'Título',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'key' => 'tipo-a-objetivo-alcance-descripcion-del-problema',
              'label' => 'tipo === \'A\' ? \'Objetivo / alcance\' : \'Descripción del problema\'',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'key' => 'evidencias-pantallazos',
              'label' => 'Evidencias (pantallazos)',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'Guardar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  76 => 
  array (
    'key' => 'verificacion',
    'label' => 'Verificacion',
    'page_path' => 'pages/verificacion/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-true',
        'label' => 'Tabla — true',
        'tipo' => 'tabla',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'data:consolidadoData · columns:consolidadoColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto por ítem',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            6 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            7 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            8 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo',
              'header' => 'Servicio',
            ),
            11 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            11 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'tabla-cursos',
        'label' => 'Tabla — Cursos',
        'tipo' => 'tabla',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'data:cursosData · columns:cursosColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto por ítem',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            6 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            7 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            8 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo',
              'header' => 'Servicio',
            ),
            11 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      2 => 
      array (
        'key' => 'tabla-permisos',
        'label' => 'Tabla — Permisos',
        'tipo' => 'tabla',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'data:permisosData · columns:permisosColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto por ítem',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            6 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            7 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            8 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo',
              'header' => 'Servicio',
            ),
            11 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            11 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      3 => 
      array (
        'key' => 'tabla-delivery',
        'label' => 'Tabla — Delivery',
        'tipo' => 'tabla',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'data:deliveryData · columns:deliveryColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto por ítem',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            6 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            7 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            8 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo',
              'header' => 'Servicio',
            ),
            11 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            2 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      4 => 
      array (
        'key' => 'tabla-verificacion',
        'label' => 'Tabla — Verificación',
        'tipo' => 'tabla',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'data:boletinData · columns:boletinColumns',
        'live_api' => 
        array (
          'path' => 'api/carga-consolidada/boletin-quimico',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'cliente',
              'header' => 'Cliente',
            ),
            1 => 
            array (
              'accessorKey' => 'consolidado',
              'header' => 'Consolidado',
            ),
            2 => 
            array (
              'accessorKey' => 'items',
              'header' => 'Items',
            ),
            3 => 
            array (
              'accessorKey' => 'monto_boletin',
              'header' => 'Monto por ítem',
            ),
            4 => 
            array (
              'accessorKey' => 'estado',
              'header' => 'Estado',
            ),
            5 => 
            array (
              'accessorKey' => 'adelantos',
              'header' => 'Adelantos',
            ),
            6 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
            7 => 
            array (
              'accessorKey' => 'index',
              'header' => 'N.',
            ),
            8 => 
            array (
              'accessorKey' => 'fecha',
              'header' => 'Fecha',
            ),
            9 => 
            array (
              'accessorKey' => 'contacto',
              'header' => 'Contacto',
            ),
            10 => 
            array (
              'accessorKey' => 'tipo',
              'header' => 'Servicio',
            ),
            11 => 
            array (
              'accessorKey' => 'carga',
              'header' => 'Carga',
            ),
          ),
          'filters' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            11 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/carga-consolidada/boletin-quimico',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      5 => 
      array (
        'key' => 'filtros-filterconfigconsolidado',
        'label' => 'Filtros — Consolidado',
        'tipo' => 'filtros',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'filterConfigConsolidado',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            5 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            6 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            9 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            10 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            11 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      6 => 
      array (
        'key' => 'filtros-filterconfigcursos',
        'label' => 'Filtros — Cursos',
        'tipo' => 'filtros',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'filterConfigCursos',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado de pago',
              'key' => 'estado_pago',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'label' => 'Campaña',
              'key' => 'campanas',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'Filtro_Fe_Inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'Filtro_Fe_Fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            5 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            6 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            7 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            8 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
      7 => 
      array (
        'key' => 'filtros-filterconfigdelivery',
        'label' => 'Filtros — Delivery',
        'tipo' => 'filtros',
        'component' => 'pages/verificacion/index',
        'api_hint' => 'filterConfigDelivery',
        'live_api' => NULL,
        'snapshot' => 
        array (
          'fields' => 
          array (
            0 => 
            array (
              'label' => 'Estado',
              'key' => 'estado',
              'type' => 'select',
              'value' => 'PENDIENTE',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los estados',
                  'value' => 'PENDIENTE',
                ),
                1 => 
                array (
                  'label' => 'Pendiente',
                  'value' => 'PAGADO',
                ),
              ),
            ),
            1 => 
            array (
              'label' => 'Tipo de Entrega',
              'key' => 'entrega',
              'type' => 'select',
              'value' => 'LIMA',
              'options' => 
              array (
                0 => 
                array (
                  'label' => 'Todos los tipos',
                  'value' => 'LIMA',
                ),
                1 => 
                array (
                  'label' => 'Lima',
                  'value' => 'PROVINCIA',
                ),
              ),
            ),
            2 => 
            array (
              'label' => 'Carga',
              'key' => 'carga',
              'type' => 'select',
              'value' => 'todos',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'label' => 'Fecha Inicio',
              'key' => 'fecha_inicio',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            4 => 
            array (
              'label' => 'Fecha Fin',
              'key' => 'fecha_fin',
              'type' => 'date',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  77 => 
  array (
    'key' => 'verificacion.permisos.id',
    'label' => 'Verificacion → Permisos → Id',
    'page_path' => 'pages/verificacion/permisos/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-registrar-pago',
        'label' => 'Modal — Registrar Pago ',
        'tipo' => 'modal',
        'component' => 'components/commons/CreatePagoModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Registrar Pago ',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'monto',
              'label' => 'Monto',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'banco',
              'label' => 'Banco',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-cierre',
              'label' => 'Fecha Cierre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'editcomprobante-comprobante-puedes-cambiar-el-archivo-solocomprobante-comprobante-voucher',
              'label' => 'editComprobante ? \'Comprobante (puedes cambiar el archivo)\' : (soloComprobante ? \'Comprobante\' : \'Voucher\')',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'soloComprobante ? \'Aceptar\' : \'Guardar\'',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  78 => 
  array (
    'key' => 'viaticos',
    'label' => 'Viaticos',
    'page_path' => 'pages/viaticos/index.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-mis-viaticos-y-reintegros',
        'label' => 'Tabla — Mis viáticos y reintegros',
        'tipo' => 'tabla',
        'component' => 'pages/viaticos/index',
        'api_hint' => 'data:viaticos · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/viaticos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'codigo_confirmado',
              'header' => 'Código',
            ),
            2 => 
            array (
              'accessorKey' => 'subject',
              'header' => 'Asunto',
            ),
            3 => 
            array (
              'accessorKey' => 'reimbursement_date',
              'header' => 'Fecha Reintegro',
            ),
            4 => 
            array (
              'accessorKey' => 'return_date',
              'header' => 'Fecha de devolución',
            ),
            5 => 
            array (
              'accessorKey' => 'requesting_area',
              'header' => 'Área Solicitante',
            ),
            6 => 
            array (
              'accessorKey' => 'total_amount',
              'header' => 'Monto',
            ),
            7 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Estado',
            ),
            8 => 
            array (
              'accessorKey' => 'receipt_file',
              'header' => 'Evidencia',
            ),
            9 => 
            array (
              'accessorKey' => 'payment_receipt_file',
              'header' => 'Retribución',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/viaticos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  79 => 
  array (
    'key' => 'viaticos.completados',
    'label' => 'Viaticos → Completados',
    'page_path' => 'pages/viaticos/completados.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-completados',
        'label' => 'Tabla — Completados',
        'tipo' => 'tabla',
        'component' => 'pages/viaticos/completados',
        'api_hint' => 'data:viaticos · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/viaticos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'codigo_confirmado',
              'header' => 'Código',
            ),
            2 => 
            array (
              'accessorKey' => 'subject',
              'header' => 'Asunto',
            ),
            3 => 
            array (
              'accessorKey' => 'reimbursement_date',
              'header' => 'Fecha Reintegro',
            ),
            4 => 
            array (
              'accessorKey' => 'requesting_area',
              'header' => 'Área Solicitante',
            ),
            5 => 
            array (
              'accessorKey' => 'nombre_usuario',
              'header' => 'Solicitante',
            ),
            6 => 
            array (
              'accessorKey' => 'total_amount',
              'header' => 'Monto',
            ),
            7 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Estado',
            ),
            8 => 
            array (
              'accessorKey' => 'receipt_file',
              'header' => 'Comprobante',
            ),
            9 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/viaticos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  80 => 
  array (
    'key' => 'viaticos.id',
    'label' => 'Viaticos → Id',
    'page_path' => 'pages/viaticos/[id].vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'modal-registrar-pago',
        'label' => 'Modal — Registrar Pago ',
        'tipo' => 'modal',
        'component' => 'components/commons/CreatePagoModal',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Registrar Pago ',
          'fields' => 
          array (
            0 => 
            array (
              'key' => 'monto',
              'label' => 'Monto',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            1 => 
            array (
              'key' => 'banco',
              'label' => 'Banco',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            2 => 
            array (
              'key' => 'fecha-cierre',
              'label' => 'Fecha Cierre',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
            3 => 
            array (
              'key' => 'editcomprobante-comprobante-puedes-cambiar-el-archivo-solocomprobante-comprobante-voucher',
              'label' => 'editComprobante ? \'Comprobante (puedes cambiar el archivo)\' : (soloComprobante ? \'Comprobante\' : \'Voucher\')',
              'type' => 'text',
              'value' => '',
              'options' => 
              array (
              ),
            ),
          ),
          'actions' => 
          array (
            0 => 'Cancelar',
            1 => 'soloComprobante ? \'Aceptar\' : \'Guardar\'',
          ),
          'live_api' => NULL,
        ),
      ),
      1 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
  81 => 
  array (
    'key' => 'viaticos.pendientes',
    'label' => 'Viaticos → Pendientes',
    'page_path' => 'pages/viaticos/pendientes.vue',
    'widgets' => 
    array (
      0 => 
      array (
        'key' => 'tabla-pendientes',
        'label' => 'Tabla — Pendientes',
        'tipo' => 'tabla',
        'component' => 'pages/viaticos/pendientes',
        'api_hint' => 'data:viaticos · columns:columns',
        'live_api' => 
        array (
          'path' => 'api/viaticos',
          'method' => 'GET',
          'params' => 
          array (
            'page' => 1,
            'limit' => 15,
          ),
          'data_key' => 'data',
          'kind' => 'list',
        ),
        'snapshot' => 
        array (
          'columns' => 
          array (
            0 => 
            array (
              'accessorKey' => 'id',
              'header' => 'ID',
            ),
            1 => 
            array (
              'accessorKey' => 'codigo_confirmado',
              'header' => 'Código',
            ),
            2 => 
            array (
              'accessorKey' => 'subject',
              'header' => 'Asunto',
            ),
            3 => 
            array (
              'accessorKey' => 'reimbursement_date',
              'header' => 'Fecha Reintegro',
            ),
            4 => 
            array (
              'accessorKey' => 'return_date',
              'header' => 'Fecha de devolución',
            ),
            5 => 
            array (
              'accessorKey' => 'requesting_area',
              'header' => 'Área Solicitante',
            ),
            6 => 
            array (
              'accessorKey' => 'nombre_usuario',
              'header' => 'Solicitante',
            ),
            7 => 
            array (
              'accessorKey' => 'total_amount',
              'header' => 'Monto',
            ),
            8 => 
            array (
              'accessorKey' => 'status',
              'header' => 'Estado',
            ),
            9 => 
            array (
              'accessorKey' => 'receipt_file',
              'header' => 'Comprobante',
            ),
            10 => 
            array (
              'accessorKey' => 'acciones',
              'header' => 'Acciones',
            ),
          ),
          'filters' => 
          array (
          ),
          'rows' => 
          array (
          ),
          'live_api' => 
          array (
            'path' => 'api/viaticos',
            'method' => 'GET',
            'params' => 
            array (
              'page' => 1,
              'limit' => 15,
            ),
            'data_key' => 'data',
            'kind' => 'list',
          ),
        ),
      ),
      1 => 
      array (
        'key' => 'modal-vista-previa-del-archivo',
        'label' => 'Modal — Vista previa del archivo',
        'tipo' => 'modal',
        'component' => 'components/commons/ModalPreview',
        'api_hint' => NULL,
        'live_api' => NULL,
        'snapshot' => 
        array (
          'title' => 'Vista previa del archivo',
          'fields' => 
          array (
          ),
          'actions' => 
          array (
            0 => 'Abrir en pestaña',
            1 => 'Descargar',
            2 => '`${speed}x`',
            3 => 'Abrir en nueva pestaña',
            4 => 'Cerrar',
          ),
          'live_api' => NULL,
        ),
      ),
    ),
  ),
);
