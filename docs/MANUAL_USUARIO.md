# Manual de usuario (Intranet)

Documentación versionada en el backend (`resources/manual/`) consumida por la pantalla **Manual de usuario** del front y exportable a PDF.

## Estructura

```text
resources/manual/
  index.yaml
  global/*.md
  roles/{slug}/_meta.yaml
  roles/{slug}/*.md
  screenshots/{slug}/*.png
```

## API (JWT interno)

| Método | Ruta | Quién |
|--------|------|--------|
| GET | `/api/manual-usuario` | Todos: contexto + lista de roles visibles |
| GET | `/api/manual-usuario/me` | Manual del rol del usuario |
| GET | `/api/manual-usuario/roles/{slug}` | Owner del rol o `root` |
| GET | `/api/manual-usuario/me/pdf` | PDF del rol propio |
| GET | `/api/manual-usuario/roles/{slug}/pdf` | Owner o `root` |
| GET | `/api/manual-usuario/pdf` | Solo `No_Usuario === root` (PDF global) |
| GET | `/api/manual-usuario/assets/{path}` | Capturas (JWT) |

`root` ve todos los roles y puede descargar el PDF global. El resto solo ve/descarga el manual de su `ID_Grupo`.

## Seed de stubs desde menús

```bash
php artisan manual:seed-from-menus
php artisan manual:seed-from-menus --force          # regenera stubs
php artisan manual:seed-from-menus --write-index    # reescribe index.yaml
```

## CMS en BD (piloto)

Tablas: `manual_paginas`, `manual_bloques`, `manual_media`.

```bash
php artisan migrate
php artisan manual:seed-cms-pilot --fresh
```

La API de lectura del manual usa solo CMS en BD (`source: db`). El Markdown en `resources/manual/` queda como fuente para seeds/migración, no como fallback de lectura.

Bloques raíz: siempre **grupo** (`titulo` + `clave`/ruta). Dentro: subbloques anidados (otros grupos o widgets).
Widgets: `texto`, `callout`, `media`, `flow`, `embed`, `tabla`, `filtros`, `tabs`, `toolbar`.
El widget `tabla` se renderiza con el componente **DataTable** del front.

Cada widget: **título** + **subtítulo opcional** + **snapshot** (sin llamadas API en la vista del manual).

### Importar desde page Vue

Catálogo `config/manual_usuario_page_widgets.php`: pages → widgets (tablas/filtros/tabs) con snapshot.
En el mantenedor, **dentro de un grupo**: Page → widget → **Importar snapshot** (`POST .../bloques/from-page-widget` con `parent_id`).
Se guarda solo la config/valores; la lectura del manual es rápida.

Regenerar catálogo desde el front (build-time; **no en el server de prod ni contra Netlify**):

```bash
# Local (Windows): detecta ../probusiness_intranetv3
.\scripts\manual-scan-front-widgets.ps1
.\scripts\manual-scan-front-widgets.ps1 -Only pages/curso

# Local / CI (bash)
bash scripts/manual-scan-front-widgets.sh
bash scripts/manual-scan-front-widgets.sh --only=pages/curso

# Composer
composer manual:scan          # dry-run
composer manual:scan-write    # escribe config/

# Artisan directo
php artisan manual:scan-front-widgets --write
php artisan manual:scan-front-widgets --front="C:/path/probusiness_intranetv3" --write
```

GitHub Actions: workflow **Scan manual widgets** (manual) clona `probusiness_intranetv3`, regenera el config y abre PR.

Opcional en `.env` (solo local/CI): `MANUAL_USUARIO_FRONT_PATH=...`

**Prod:** desplegar el back con `config/manual_usuario_page_widgets.php` commiteado. El admin importa desde ese archivo; no escanea.

### `ui_embed` (HTML+CSS 1:1 del front)

Catálogo en `config/manual_usuario_ui_catalog.php` (tabs, modales, selects, toolbars, tablas).
En el mantenedor eliges un ítem y se **copia** `html` + `css` al payload del bloque en BD.
La vista del manual lo renderiza con CSS scoped.

## Mantenedor admin (root)

Front: `/manual-usuario/admin` (listado) y `/manual-usuario/admin/{id}` (edición). Rol fijo al crear; orden de bloques con drag & drop.

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/manual-usuario/admin/meta` | Roles + tipos de bloque |
| GET/POST | `/api/manual-usuario/admin/pages` | Listar / crear página |
| GET/PUT/DELETE | `/api/manual-usuario/admin/pages/{id}` | CRUD página |
| POST | `/api/manual-usuario/admin/pages/{id}/bloques` | Crear bloque |
| PUT/DELETE | `/api/manual-usuario/admin/bloques/{id}` | Editar / borrar bloque |
| POST | `/api/manual-usuario/admin/bloques/reorder` | Reordenar `{ items:[{id,orden}] }` |
| GET/POST/DELETE | `/api/manual-usuario/admin/media` | Listar / subir / borrar imagen |
| GET | `/api/manual-usuario/media/{id}` | Servir imagen (JWT) |

Uploads van a `storage/app/manual/{role}/...` (disco `local`).


Reescribe capítulos en lenguaje de usuario (sin `TODO captura` ni plantillas vacías). Respeta roles con `curated: true` en seed; Cotizador se puede preservar:

```bash
php artisan manual:curate-roles --keep-cotizador
php artisan manual:curate-roles --only=administracion
```

Las imágenes solo se exponen si el archivo existe en `resources/manual/screenshots/...`. Si no hay PNG, el HTML no incluye `<img>` (evita 404).

## Menú sidebar

Migración `insert_manual_usuario_menu_item`: crea ítem `Manual de usuario` con `url_intranet_v2 = manual-usuario` y `menu_acceso` por grupo interno (excluye Cliente `1205`).

## Cómo editar contenido

1. Edita o agrega Markdown en `resources/manual/roles/{slug}/`.
2. Coloca capturas en `resources/manual/screenshots/{slug}/`.
3. Referencia en MD: `![texto](screenshots/{slug}/archivo.png)`.
4. Haz PR; no hay CMS en BD en v1.

## Front

Ver contrato en [MANUAL_USUARIO_FRONT.md](MANUAL_USUARIO_FRONT.md) y guía de capturas en [MANUAL_USUARIO_CAPTURAS.md](MANUAL_USUARIO_CAPTURAS.md).
