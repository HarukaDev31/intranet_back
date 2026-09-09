/**
 * Helper: list of modules to capture with role and URL.
 * Used as reference for manual screenshot batch (browser automation).
 */
module.exports = [
  { role: 'coordinacion@probusiness.pe', url: '/news', key: 'ops-news', pick: ['Ver más detalles'] },
  { role: 'coordinacion@probusiness.pe', url: '/viaticos', key: 'ops-viaticos', pick: ['Nuevo', 'Filtros'] },
  { role: 'coordinacion@probusiness.pe', url: '/soporte-ti', key: 'ops-soporte', pick: ['Nueva solicitud'] },
  { role: 'coordinacion@probusiness.pe', url: '/calendar', key: 'ops-calendar', pick: ['Nueva actividad', 'Filtros'] },
  { role: 'coordinacion@probusiness.pe', url: '/coordinacion/whatsapp-inbox', key: 'ops-whatsapp', pick: [] },
  { role: 'coordinacion@probusiness.pe', url: '/mi-progreso', key: 'ops-progreso', pick: [] },
  { role: 'documentacion@probusiness.pe', url: '/basedatos/productos', key: 'bd-productos', pick: ['Filtros', 'Importar'] },
  { role: 'documentacion@probusiness.pe', url: '/basedatos/regulaciones', key: 'bd-regulaciones', pick: ['Nuevo', 'Filtros'] },
  { role: 'documentacion@probusiness.pe', url: '/basedatos/permisos', key: 'bd-permisos', pick: ['Nuevo', 'Filtros'] },
  { role: 'cotizaciones@probusiness.pe', url: '/cotizaciones', key: 'cotizador-cotizaciones', pick: ['Nueva', 'Filtros'] },
  { role: 'mvillegas@probusiness.pe', url: '/panel-acceso/usuarios', key: 'panel-usuarios', pick: ['Nuevo', 'Crear'] },
  { role: 'mvillegas@probusiness.pe', url: '/panel-acceso/cargos', key: 'panel-cargos', pick: ['Nuevo', 'Crear'] },
  { role: 'mvillegas@probusiness.pe', url: '/panel-acceso/permisos', key: 'panel-permisos', pick: ['Guardar'] },
];
