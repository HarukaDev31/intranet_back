# Guía de capturas — Manual de usuario

## Objetivo

Una captura clara por vista principal, tab relevante y modal/acción crítica, en lenguaje de usuario (sin depurar UI de desarrollo).

## Naming

El runner escribe bajo `resources/manual/capturas/`:

```text
{rol}/{pantalla}/{capture_key}.png
{rol}/{pantalla}/{capture_key}--2560x1440.png
```

`ManualUsuarioArticuloWriter` y `ManualUsuarioCursoAlumnosSeeder` persisten en
el snapshot la ruta tomada de `screen.articulo_clave`, clave, rol,
módulo/pantalla, flujo, paso, hint, output y configuración Playwright.

`capture_key` es semántica y **no incluye el rol**. El PNG canónico es:

```text
{capture_key}.png
```

Ejemplo: `news__leer-avisos__paso-01-tarjetas-y-detalle.png`. Todos los roles que documentan la misma UI (Noticias, Viáticos, Calendario, Soporte, Clientes, Verificación, etc.) comparten esa clave y el mismo `media_id`.

El rol solo se usa para autenticar el runner. La ruta histórica `{rol}/{pantalla}/{capture_key}.png` sigue resolviéndose al adjuntar (basename), pero el snapshot queda en `{capture_key}.png`.

Un bloque puede declarar `capture_alias_of` apuntando a la canónica. Al adjuntar un PNG una vez, todos los bloques de esa identidad reciben el mismo `media_id`.

```bash
php artisan manual:normalize-capturas-keys --dry-run
php artisan manual:normalize-capturas-keys
```

Ese comando no borra copy: solo unifica `capture_output` (y alias si aplica).

`manual:export-capturas-manifest` deja una captura canónica por clave; el resto sale como alias (`enabled: false`).

En el mantenedor, el catálogo de imágenes lista clave, vista previa y cuántas hojas la usan. En cada bloque media puedes elegir esa imagen; al guardar se aplica a todas las hojas con la misma clave.

Un paso puede fijar la clave y configuración del runner:

```php
$this->itemFlujo(
    'Confirmar pedido',
    'Revisa los datos y confirma.',
    'Recorta el botón y el resumen.',
    [
        'capture_key' => 'pedidos__confirmar__paso-01-resumen',
        'type' => 'modal',
        'actions' => [
            ['type' => 'click', 'target' => ['role' => 'button', 'name' => 'Confirmar']],
        ],
        'target' => ['role' => 'dialog'],
        'expectedText' => 'Confirmar pedido',
    ]
);
```

También se admiten `padding`, `masks`, `piiAllow`, `expectedHash`, `enabled` y
`url`. Sin configuración se exporta como captura `page`.

Las imágenes Markdown históricas bajo `resources/manual/screenshots/{slug-rol}`
siguen siendo independientes de este inventario CMS.

## Manifiesto versionable

```bash
php artisan manual:export-capturas-manifest
php artisan manual:export-capturas-manifest --strict
php artisan manual:export-capturas-manifest --output=resources/manual/capturas/manifest.json
```

El formato canónico coordinado con el runner es el plano
`{schema_version, screens, captures[]}`. `screens[screen].url` viene de
`screen.articulo_clave`; cada captura incluye `capture_key`, roles, screen,
módulo, flow, step, hint y output. Cuando existe configuración por paso se
exportan además `type`, `target`, `actions`, `expected_text`, `padding`,
`masks`, `pii_allow`, `expected_hash` y `enabled`.

El loader frontend normaliza este formato al modelo de ejecución
`{version, roles[].screens[].shots[]}`. La variante 1920×1200 usa `output`
exactamente; 2560×1440 añade el sufijo `--2560x1440`.

`--strict` no escribe si falta ruta/clave, hay shots duplicados o una captura
que no sea `page` carece de `target`.

## Enlace de capturas

Primero valida sin escribir:

```bash
php artisan manual:attach-capturas --dry-run --strict
```

Después enlaza por coincidencia exacta:

```bash
php artisan manual:attach-capturas --strict
```

No hay fallback heurístico silencioso. La transición anterior solo se habilita
de forma explícita con `--legacy`; úsala para diagnosticar contenido aún no
resembrado, no para generar el manifiesto definitivo.

## Auditoría

```bash
php artisan manual:audit-capturas
php artisan manual:audit-capturas --strict
php artisan manual:audit-capturas --strict --report=storage/app/manual/capturas-audit.json
php artisan manual:audit-capturas --manifest=resources/manual/capturas/manifest.json --strict
```

La auditoría comprueba cobertura, claves repetidas, PNG faltantes y huérfanos,
hashes exactos repetidos sin alias, dimensiones mínimas, archivos PNG válidos y
`media_id`. Puede ejecutarse contra el inventario vivo de BD o contra un
manifiesto versionado.

Las migraciones históricas de attach son deliberadamente no-op: ninguna
migración sube archivos ni llama storage. El enlace se ejecuta únicamente con
el comando explícito, después del dry-run estricto.

## Qué capturar

1. Vista listado (con filtros visibles si son importantes).
2. Cada tab del detalle que cambie el flujo.
3. Cada modal de alta/edición crítico.
4. Mensaje de éxito o error frecuente (opcional).

## Entorno

Preferir **QA** con datos de ejemplo (sin datos sensibles reales). Evita números de documento reales de clientes.

## Resolución

- Ancho útil ~1280–1440 px.
- Recorta barras del navegador si no aportan.
- No uses blur excesivo que oculte botones.

## Checklist por capítulo

- [ ] Título de pantalla
- [ ] Para qué sirve
- [ ] Captura principal
- [ ] Paso a paso con nombres de botones reales
- [ ] Qué pasa después
- [ ] Errores frecuentes
