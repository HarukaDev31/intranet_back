# Contrato front — pantalla `/manual-usuario`

El ítem **Manual de usuario** llega del sidebar (`GET /api/menu/listar`) con `url_intranet_v2 = manual-usuario`.

## Implementado en front (intranet v3)

- Lector: `pages/manual-usuario/index.vue` → `/manual-usuario`
- Mantenedor: `pages/manual-usuario/admin/index.vue` + `admin/[id].vue`
- Contenido: solo CMS en BD (`pages` + árbol `blocks`/`children`) con `ManualBlockRenderer`
- Servicio: `services/manualUsuarioService.ts`

## Mantenedor CMS (root)

- Ruta listado: `/manual-usuario/admin`
- Ruta edición: `/manual-usuario/admin/{id}`
- Desde el lector (root): botón **Mantenedor CMS**
- Bloque raíz = **grupo** (`titulo` + `clave`/ruta); widgets solo como subbloques
- Importar snapshot de page Vue **dentro de un grupo** (`parent_id`)
- Orden de bloques/subbloques con drag & drop (icono ☰)
- Rol se define al crear la página (no se edita en el detalle)
- Upload de capturas en widgets `media` → `POST /api/manual-usuario/admin/media`
- Widget `tabla` usa el componente `DataTable` (mismos estilos)
- Al **importar** o **guardar** un widget con `live_api` (tabla, filtros, modal, …), el back llama la misma ruta del front y guarda el snapshot hidratado
- Tipos importables desde page Vue: `tabla`, `filtros`, `tabs`, `modal`, `toolbar` (bloque), `accion` (botón suelto), `card`
- Widget `timeline`: línea de tiempo horizontal; dentro se agregan/importan widgets como pasos (izq → der)
- El rol de la página queda en `source.role_slug` (referencia del contexto al crear la página)

Tras desplegar la migración del menú en el back, **vuelve a iniciar sesión** para ver el ítem.

## Catálogo de widgets (scanner → prod)

El scanner lee el **repo Vue** (`probusiness_intranetv3`), no Netlify.

1. Local o Actions: regenerar `config/manual_usuario_page_widgets.php`
2. Commit / merge en el back
3. Deploy back → el mantenedor importa desde ese catálogo

```bash
# Windows
.\scripts\manual-scan-front-widgets.ps1
# o
composer manual:scan-write
```

GitHub → Actions → **Scan manual widgets** → Run (abre PR si hubo cambios).

## Flujo UI

1. Al montar, llamar `GET /api/manual-usuario` con JWT.
2. Si `is_root === false`:
   - Cargar `GET /api/manual-usuario/me`.
   - Mostrar `data.pages` (bloques UI).
   - Botón PDF → `GET /api/manual-usuario/me/pdf` (blob download).
3. Si `is_root === true`:
   - Selector de roles desde `data.roles`.
   - PDF global → `GET /api/manual-usuario/pdf`.
   - Al elegir rol → `GET /api/manual-usuario/roles/{slug}` y PDF `.../roles/{slug}/pdf`.

## Render del contenido

- Solo `pages[].blocks` vía `ManualBlockRenderer` (recursivo: `grupo` → `children`; widgets hoja).
- `tabla` → `DataTable` del front.
- No hay fallback Markdown.

## Respuestas clave

### `GET /api/manual-usuario`

```json
{
  "status": "success",
  "data": {
    "title": "Manual de usuario — Intranet Probusiness",
    "is_root": false,
    "can_download_global_pdf": false,
    "my_role": { "slug": "cotizador", "id_grupo": 1210, "nombre": "Cotizador" },
    "roles": [{ "slug": "cotizador", "id_grupo": 1210, "nombre": "Cotizador" }]
  }
}
```

### `GET /api/manual-usuario/me` (o `/roles/{slug}`)

```json
{
  "status": "success",
  "data": {
    "source": "db",
    "role": { "slug": "cotizador", "id_grupo": 1210, "nombre": "Cotizador", "meta": {} },
    "pages": [
      {
        "id": 1,
        "modulo_key": "cargaconsolidada/abiertos",
        "titulo": "Carga consolidada — Abiertos",
        "descripcion": "...",
        "orden": 1,
        "blocks": [{
          "id": 10,
          "tipo": "grupo",
          "titulo": "Abiertos",
          "clave": "cargaconsolidada/abiertos",
          "payload": { "subtitulo": null, "snapshot": {} },
          "orden": 1,
          "children": [
            { "id": 11, "tipo": "tabla", "titulo": "Listado", "payload": { "snapshot": { "columns": [], "rows": [] } }, "orden": 1, "children": [] }
          ]
        }]
      }
    ],
    "pdf_url": "https://.../api/manual-usuario/roles/cotizador/pdf"
  }
}
```

## Errores

- `404` si el usuario no tiene rol con manual.
- `403` si pide un rol ajeno y no es root.
- Si el rol no tiene páginas publicadas en BD, `pages` viene `[]` y el front muestra “No hay contenido…”.
