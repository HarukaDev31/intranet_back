# Guía de capturas — Manual de usuario

## Objetivo

Una captura clara por vista principal, tab relevante y modal/acción crítica, en lenguaje de usuario (sin depurar UI de desarrollo).

## Naming

Coloca archivos en `resources/manual/screenshots/{slug-rol}/`.

Patrón sugerido:

```text
{modulo}-{vista}-{detalle}.png
```

Ejemplos:

- `carga-consolidada-abiertos.png`
- `contenedor-tab-clientes.png`
- `modal-agregar-cliente.png`
- `cotizaciones-listado.png`

En el Markdown:

```markdown
![Listado de contenedores abiertos](screenshots/cotizador/carga-consolidada-abiertos.png)
```

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

## Futuro (fuera de v1)

Script Playwright que recorra `url_intranet_v2` del menú por rol y deje PNG en `screenshots/`.
