<?php

/**
 * Catálogo de UI real de la intranet (snapshots HTML+CSS) para el mantenedor del manual.
 * Al elegir un ítem se copia html/css al payload del bloque `ui_embed` en BD.
 */
return [
    [
        'key' => 'cc.tabs.prospectos-embarcar',
        'label' => 'Tabs — Prospectos / Por Embarcar',
        'category' => 'tabs',
        'module' => 'Carga consolidada',
        'source' => 'components/cargaconsolidada/cotizaciones/CotizacionesView',
        'description' => 'Pestañas del detalle de contenedor (vista Cotizador).',
        'html' => <<<'HTML'
<div class="mui-tabs">
  <div class="mui-tabs__list" role="tablist">
    <button type="button" class="mui-tabs__tab is-active" role="tab">Prospectos</button>
    <button type="button" class="mui-tabs__tab" role="tab">Por Embarcar</button>
  </div>
  <div class="mui-tabs__panel" role="tabpanel">
    <p class="mui-tabs__hint">Listado de prospectos del contenedor (demo visual del manual).</p>
  </div>
</div>
HTML,
        'css' => <<<'CSS'
.mui-tabs{font-family:ui-sans-serif,system-ui,sans-serif;color:#111827}
.mui-tabs__list{display:flex;gap:4px;border-bottom:1px solid #e5e7eb;margin-bottom:12px}
.mui-tabs__tab{appearance:none;background:transparent;border:0;border-bottom:2px solid transparent;padding:10px 14px;font-size:14px;font-weight:600;color:#6b7280;cursor:default}
.mui-tabs__tab.is-active{color:#0f766e;border-bottom-color:#0d9488}
.mui-tabs__panel{padding:4px 2px 8px}
.mui-tabs__hint{margin:0;font-size:13px;color:#6b7280}
CSS,
    ],
    [
        'key' => 'cc.filters.listado-anio-estado',
        'label' => 'Selects — Filtros Año / Estado',
        'category' => 'selects',
        'module' => 'Carga consolidada',
        'source' => 'components/cargaconsolidada/consolidado/CargaConsolidadaAbiertaView',
        'description' => 'Filtros típicos del listado de contenedores abiertos.',
        'html' => <<<'HTML'
<div class="mui-filters">
  <div class="mui-field">
    <label class="mui-label">Año</label>
    <div class="mui-select"><span>2026</span><span class="mui-caret">▾</span></div>
  </div>
  <div class="mui-field">
    <label class="mui-label">Estado</label>
    <div class="mui-select"><span>Todos</span><span class="mui-caret">▾</span></div>
  </div>
  <div class="mui-field mui-field--grow">
    <label class="mui-label">Buscar</label>
    <div class="mui-input">Contenedor, cliente…</div>
  </div>
</div>
HTML,
        'css' => <<<'CSS'
.mui-filters{display:grid;grid-template-columns:160px 180px 1fr;gap:12px;font-family:ui-sans-serif,system-ui,sans-serif}
@media(max-width:720px){.mui-filters{grid-template-columns:1fr}}
.mui-label{display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:6px}
.mui-select,.mui-input{display:flex;align-items:center;justify-content:space-between;min-height:36px;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:14px;color:#111827}
.mui-input{color:#9ca3af}
.mui-caret{color:#9ca3af;font-size:12px}
CSS,
    ],
    [
        'key' => 'cc.toolbar.listado',
        'label' => 'Toolbar — Exportar / Actualizar',
        'category' => 'toolbars',
        'module' => 'Carga consolidada',
        'source' => 'components/DataTable + acciones listado',
        'description' => 'Barra de acciones superior del listado.',
        'html' => <<<'HTML'
<div class="mui-toolbar">
  <button type="button" class="mui-btn mui-btn--ghost">Exportar</button>
  <button type="button" class="mui-btn mui-btn--ghost">Actualizar</button>
  <button type="button" class="mui-btn mui-btn--primary">Nueva acción</button>
</div>
HTML,
        'css' => <<<'CSS'
.mui-toolbar{display:flex;flex-wrap:wrap;gap:8px;font-family:ui-sans-serif,system-ui,sans-serif}
.mui-btn{appearance:none;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;cursor:default;border:1px solid transparent}
.mui-btn--ghost{background:#fff;border-color:#d1d5db;color:#374151}
.mui-btn--primary{background:#0d9488;color:#fff}
CSS,
    ],
    [
        'key' => 'cc.modal.nueva-cotizacion',
        'label' => 'Modal — Nueva cotización',
        'category' => 'modales',
        'module' => 'Cotizaciones',
        'source' => 'pages/cotizaciones/crear + modales de alta',
        'description' => 'Estructura visual del modal de alta (campos demo).',
        'html' => <<<'HTML'
<div class="mui-modal" role="dialog" aria-modal="true">
  <div class="mui-modal__card">
    <div class="mui-modal__header">
      <strong>Nueva cotización</strong>
      <span class="mui-modal__x">×</span>
    </div>
    <div class="mui-modal__body">
      <div class="mui-field">
        <label class="mui-label">Cliente</label>
        <div class="mui-input">Seleccionar cliente</div>
      </div>
      <div class="mui-field">
        <label class="mui-label">Contenedor</label>
        <div class="mui-select"><span>Seleccionar</span><span class="mui-caret">▾</span></div>
      </div>
      <div class="mui-field">
        <label class="mui-label">WhatsApp</label>
        <div class="mui-input">+51 …</div>
      </div>
    </div>
    <div class="mui-modal__footer">
      <button type="button" class="mui-btn mui-btn--ghost">Cancelar</button>
      <button type="button" class="mui-btn mui-btn--primary">Guardar</button>
    </div>
  </div>
</div>
HTML,
        'css' => <<<'CSS'
.mui-modal{font-family:ui-sans-serif,system-ui,sans-serif;background:rgba(15,23,42,.35);border-radius:12px;padding:18px}
.mui-modal__card{background:#fff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;max-width:480px;margin:0 auto;box-shadow:0 10px 30px rgba(0,0,0,.12)}
.mui-modal__header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid #e5e7eb;font-size:15px;color:#111827}
.mui-modal__x{color:#9ca3af;font-size:20px;line-height:1}
.mui-modal__body{padding:16px;display:grid;gap:12px}
.mui-modal__footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid #e5e7eb;background:#f9fafb}
.mui-label{display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:6px}
.mui-select,.mui-input{display:flex;align-items:center;justify-content:space-between;min-height:36px;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:14px;color:#9ca3af}
.mui-caret{color:#9ca3af}
.mui-btn{appearance:none;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;cursor:default;border:1px solid transparent}
.mui-btn--ghost{background:#fff;border-color:#d1d5db;color:#374151}
.mui-btn--primary{background:#0d9488;color:#fff}
CSS,
    ],
    [
        'key' => 'cc.select.estado-cotizador',
        'label' => 'Select — Estado cotizador',
        'category' => 'selects',
        'module' => 'Carga consolidada',
        'source' => 'composables/cargaconsolidada/useCotizacion filters',
        'description' => 'Select de estado usado en filtros de prospectos.',
        'html' => <<<'HTML'
<div class="mui-field" style="max-width:240px">
  <label class="mui-label">Estado cotizador</label>
  <div class="mui-select is-open">
    <span class="mui-select__value">Cotizado</span>
    <span class="mui-caret">▾</span>
  </div>
  <div class="mui-menu">
    <div class="mui-menu__item">Todos</div>
    <div class="mui-menu__item is-active">Cotizado</div>
    <div class="mui-menu__item">Confirmado</div>
    <div class="mui-menu__item">Pendiente</div>
  </div>
</div>
HTML,
        'css' => <<<'CSS'
.mui-field{position:relative;font-family:ui-sans-serif,system-ui,sans-serif}
.mui-label{display:block;font-size:12px;font-weight:600;color:#4b5563;margin-bottom:6px}
.mui-select{display:flex;align-items:center;justify-content:space-between;min-height:36px;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font-size:14px;color:#111827}
.mui-select.is-open{border-color:#0d9488;box-shadow:0 0 0 3px rgba(13,148,136,.15)}
.mui-select__value{color:#111827}
.mui-caret{color:#9ca3af}
.mui-menu{margin-top:6px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,.08);overflow:hidden}
.mui-menu__item{padding:8px 12px;font-size:13px;color:#374151}
.mui-menu__item.is-active{background:#f0fdfa;color:#0f766e;font-weight:600}
CSS,
    ],
    [
        'key' => 'cc.table.preview-prospectos',
        'label' => 'Tabla — Preview prospectos',
        'category' => 'tablas',
        'module' => 'Carga consolidada',
        'source' => 'components/DataTable (prospectos)',
        'description' => 'Cabecera y filas demo del listado de prospectos.',
        'html' => <<<'HTML'
<div class="mui-table-wrap">
  <table class="mui-table">
    <thead>
      <tr>
        <th>Cliente</th>
        <th>Estado</th>
        <th>CBM</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Cliente Demo SAC</td>
        <td><span class="mui-badge">Cotizado</span></td>
        <td>2.40</td>
        <td>Ver</td>
      </tr>
      <tr>
        <td>Importadora Ejemplo</td>
        <td><span class="mui-badge mui-badge--muted">Pendiente</span></td>
        <td>1.10</td>
        <td>Ver</td>
      </tr>
    </tbody>
  </table>
</div>
HTML,
        'css' => <<<'CSS'
.mui-table-wrap{overflow:auto;border:1px solid #e5e7eb;border-radius:12px;background:#fff;font-family:ui-sans-serif,system-ui,sans-serif}
.mui-table{width:100%;border-collapse:collapse;font-size:13px}
.mui-table th{text-align:left;padding:10px 12px;background:#f9fafb;color:#6b7280;font-size:11px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #e5e7eb}
.mui-table td{padding:10px 12px;border-bottom:1px solid #f3f4f6;color:#111827}
.mui-badge{display:inline-flex;padding:2px 8px;border-radius:999px;background:#ccfbf1;color:#0f766e;font-size:11px;font-weight:700}
.mui-badge--muted{background:#f3f4f6;color:#4b5563}
CSS,
    ],
];
