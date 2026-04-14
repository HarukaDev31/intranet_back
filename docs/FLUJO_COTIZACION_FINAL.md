# Flujo de Cotizacion Final (Actualizado)

Este documento resume el flujo actual para generar archivos de cotizacion final en Carga Consolidada, incluyendo los ajustes recientes de columnas para ISC y ANTIDUMPING.

## 1) Endpoints principales involucrados

- `POST /api/carga-consolidada/contenedor/cotizacion-final/general/upload-plantilla-final`
  - Controlador: `CotizacionFinalController::generateMassiveExcelPayrolls`
  - Genera cotizaciones finales masivas (Excel) y empaqueta resultados.

- `GET /api/carga-consolidada/contenedor/cotizacion-final/general/download-plantilla-general/{idContenedor}`
  - Controlador: `CotizacionFinalController::downloadPlantillaGeneral`
  - Lee la factura general subida/procesada y construye un archivo "plantilla general" normalizado.

- `GET /api/carga-consolidada/contenedor/documentacion/download-factura-comercial/{idContenedor}`
  - Controlador: `DocumentacionController::downloadFacturaComercial`
  - Toma Factura Comercial + Packing List + Lista de Partidas y devuelve una factura comercial procesada.

## 2) Flujo general de cotizacion final

1. Se sube/consume la plantilla de entrada desde el flujo de cotizacion final.
2. `generateMassiveExcelPayrolls` procesa clientes/productos y genera el Excel final por cotizacion.
3. Se actualiza estado y URL de archivo en BD (`contenedor_consolidado_cotizacion`).
4. Para boleta PDF:
   - Se arma HTML con `buildCotizacionFinalBoletaFilledHtml`.
   - DomPDF renderiza el PDF usando la plantilla HTML.
   - Se publica en `storage` para descarga/envio.
5. Para la "plantilla general" de soporte:
   - `downloadPlantillaGeneral` toma `factura_general_url`.
   - Lee filas/item-cliente y genera un nuevo Excel estandarizado.

## 3) Flujo de `downloadFacturaComercial` (DocumentacionController)

### Entrada

- Busca y valida 3 archivos del contenedor:
  - Factura Comercial
  - Packing List
  - Lista de Partidas
- Carga los 3 Excel y datos del sistema (`contenedor_consolidado_cotizacion`).

### Procesamiento

- Mapea item -> cliente desde Packing List.
- Recorre filas de Factura Comercial (hoja principal y hojas adicionales).
- Para cada item busca datos aduaneros en Lista de Partidas y datos del sistema.

### Salida (layout actualizado)

En la factura procesada descargada se usan estas columnas:

- `R`: ADVALOREM
- `S`: ISC
- `T`: ANTIDUMPING
- `U`: VOL. SISTEMA

### Regla de lectura desde Lista de Partidas (actualizada)

Se considera que se agrego una nueva columna antes de ANTIDUMPING:

- `I`: ISC
- `J`: ANTIDUMPING

Luego se escribe:

- `R <- ADVALOREM`
- `S <- ISC`
- `T <- ANTIDUMPING`
- `U <- VOLUMEN SISTEMA`

## 4) Flujo de `downloadPlantillaGeneral` (CotizacionFinalController)

Este endpoint lee la factura general subida/procesada y arma un Excel "plantilla general" para descarga.

### Encabezados de salida (actualizados)

- `R`: AD VALOREM
- `S`: ISC
- `T`: PERCEPCION
- `U`: PESO
- `V`: VOLUMEN SISTEMA

### Mapeo de entrada -> salida usado en `processSingleRow`

Asumiendo layout fuente actual de factura general:

- Fuente `R` (AD VALOREM) -> Salida `R`
- Fuente `S` (ISC) -> Salida `S`
- Fuente `T` (ANTIDUMPING) -> Salida `P`
- Fuente `U` (VOLUMEN SISTEMA) -> Salida `V`
- PERCEPCION se fija en `T` (0.035)
- PESO viene de datos del sistema -> `U`

### Estilos y merges

- Rangos de estilo ampliados de `A:U` a `A:V`.
- Merges de cliente incluyen columnas finales `U` y `V`.
- Formato porcentaje aplicado en `R`, `S` y `T`.

## 5) Notas de mantenimiento

- Si se vuelve a mover columnas en plantillas, actualizar:
  - Encabezados
  - `processSingleRow`
  - Formatos (`applyRowStyles`, `applyFinalStyles`)
  - Merges (`applyClientMerges`)
  - Mapeo de aduanas (`processCustomsInfo` en DocumentacionController)
- Mantener consistencia entre:
  - Layout de entrada (archivos de documentacion)
  - Layout de salida (descargas)
  - Tests de integracion que validan columnas/valores.

## 6) Paso final (re-subida para cotizacion final masiva)

Despues de descargar la plantilla general/factura procesada, ese archivo se vuelve a subir al endpoint:

- `POST /api/carga-consolidada/contenedor/cotizacion-final/general/upload-plantilla-final`
- Metodo: `CotizacionFinalController::generateMassiveExcelPayrolls`

### Mapeo esperado al re-subir (parser `getMassiveExcelData`)

Layout actual de entrada:

- `P`: ANTIDUMPING
- `Q`: VALORACION
- `R`: AD VALOREM (%)
- `S`: ISC (%)
- `T`: PERCEPCION (%)
- `U`: PESO
- `V`: VOLUMEN

Al parsear:

- `ad_valorem` se toma como porcentaje.
- `isc_percent` se toma de `S` y es el porcentaje fuente para calcular ISC valor.
- `percepcion` se toma de `T`.
- Se mantiene compatibilidad basica con layout antiguo (sin columna ISC, donde `S` era percepcion).

### Regla clave de negocio (ISC)

- El archivo re-subido trae **ISC %** (`isc_percent`).
- El **ISC valor** se calcula en formulas de Excel/boleta con la logica vigente.
- No se debe hardcodear ISC valor manualmente: debe derivarse del porcentaje de entrada.

