# API de Importación de Excel - Documentación

## Descripción General

Esta API permite importar datos de pagos desde archivos Excel, manejando tanto consolidados como cursos. El sistema detecta automáticamente el tipo de registro y lo inserta en las tablas correspondientes.

## Características Principales

- ✅ **Detección automática** de tipo (CONSOLIDADO o CURSO)
- ✅ **Creación automática** de contenedores si no existen
- ✅ **Creación automática** de entidades si no existen
- ✅ **Validación de datos** en tiempo real
- ✅ **Plantilla descargable** con ejemplos y validaciones
- ✅ **Procesamiento desde fila 3** (filas 1-2 para encabezados)
- ✅ **Transacciones seguras** con rollback en caso de error
- ✅ **Estadísticas detalladas** del proceso

## Autenticación

Todos los endpoints requieren autenticación JWT. Incluye el token en el header de autorización:

```
Authorization: Bearer {token}
```

## Endpoints Disponibles

### 1. Importar Archivo Excel

**Endpoint:** `POST /api/carga-consolidada/import/excel`

**Descripción:** Sube y procesa un archivo Excel con datos de pagos.

#### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `excel_file` | file | Sí | Archivo Excel (.xlsx, .xls) máximo 10MB |

#### Ejemplo de Request

```bash
curl -X POST "https://api.ejemplo.com/api/carga-consolidada/import/excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@datos_pagos.xlsx"
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "message": "Importación completada exitosamente",
  "output": "=== RESULTADOS DE LA IMPORTACIÓN ===\nConsolidados:\n  - Creados: 15\n  - Actualizados: 0\n  - Errores: 0\nCursos:\n  - Creados: 8\n  - Actualizados: 0\n  - Errores: 0\nContenedores:\n  - Creados: 3\n  - Errores: 0\n\nTotal de registros procesados: 23"
}
```

#### Respuesta de Error (500)

```json
{
  "success": false,
  "message": "Error durante la importación: Detalle del error específico"
}
```

### 2. Descargar Plantilla

**Endpoint:** `GET /api/carga-consolidada/import/template`

**Descripción:** Descarga una plantilla Excel con la estructura correcta y ejemplos.

#### Ejemplo de Request

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/import/template" \
  -H "Authorization: Bearer {token}" \
  --output plantilla_importacion_pagos.xlsx
```

#### Respuesta

Retorna el archivo Excel directamente para descarga.

### 3. Obtener Estadísticas

**Endpoint:** `GET /api/carga-consolidada/import/stats`

**Descripción:** Obtiene estadísticas generales de los datos importados.

#### Ejemplo de Request

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/import/stats" \
  -H "Authorization: Bearer {token}"
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "data": {
    "consolidados": 150,
    "cursos": 75,
    "contenedores": 25,
    "entidades": 200
  }
}
```

## Estructura del Excel

### Columnas Requeridas

| Columna | Posición | Descripción | Ejemplo |
|---------|----------|-------------|---------|
| ID | A | Identificador único | 1 |
| NOMBRE | B | Nombre del cliente | JESUS QUESQUEN CONDORI |
| TELÉFONO | C | Número de teléfono | 981 466 498 |
| TIPO | D | Tipo de registro | CONSOLIDADO o CURSO |
| FECHA | E | Fecha del registro | 1/01/2024 |
| CONTENEDOR | F | Nombre del contenedor | CONSOLIDADO #1 |
| DOCUMENTO | G | Número de documento | 10452681418 |
| NOMBRE COMPLETO | H | Nombre completo | JESUS ANTONIO QUESQUEN CONDORI |

### Ejemplo de Datos

| ID | NOMBRE | TELÉFONO | TIPO | FECHA | CONTENEDOR | DOCUMENTO | NOMBRE COMPLETO |
|----|--------|----------|------|-------|------------|-----------|-----------------|
| 1 | JESUS QUESQUEN CONDORI | 981 466 498 | CONSOLIDADO | 1/01/2024 | CONSOLIDADO #1 | 10452681418 | JESUS ANTONIO QUESQUEN CONDORI |
| 2 | MARIA GONZALEZ | 999 888 777 | CURSO | 15/01/2024 | | 12345678 | MARIA ELENA GONZALEZ LOPEZ |

## Lógica de Procesamiento

### Para Consolidados

1. **Crear/Encontrar Contenedor**
   - Si el contenedor no existe, se crea automáticamente
   - Si no se especifica contenedor, se usa "Contenedor Default"

2. **Crear Cotización**
   - Se busca por documento y contenedor
   - Si no existe, se crea una nueva cotización
   - Se asigna estado "PENDIENTE"

### Para Cursos

1. **Crear/Encontrar Entidad**
   - Se busca por número de documento
   - Si no existe, se crea una nueva entidad
   - Se asignan valores por defecto (país, tipo documento, etc.)

2. **Crear Pedido de Curso**
   - Se busca por entidad
   - Si no existe, se crea un nuevo pedido
   - Se asignan valores por defecto (moneda, campaña, etc.)

## Validaciones

### Validaciones del Archivo

- ✅ Formato: .xlsx o .xls
- ✅ Tamaño máximo: 10MB
- ✅ Datos desde fila 3
- ✅ Columnas requeridas presentes

### Validaciones de Datos

- ✅ Tipo debe ser "CONSOLIDADO" o "CURSO"
- ✅ Documento no puede estar vacío
- ✅ Nombre no puede estar vacío
- ✅ Fecha en formato válido (d/m/Y, d-m-Y, Y-m-d)

### Validaciones de Negocio

- ✅ Empresa del usuario autenticado
- ✅ Contenedores únicos por empresa
- ✅ Entidades únicas por documento y empresa
- ✅ Cotizaciones únicas por documento y contenedor

## Manejo de Errores

### Tipos de Errores

1. **Errores de Archivo**
   - Archivo no encontrado
   - Formato no válido
   - Tamaño excesivo

2. **Errores de Datos**
   - Columnas faltantes
   - Datos inválidos
   - Tipos incorrectos

3. **Errores de Base de Datos**
   - Restricciones de integridad
   - Errores de conexión
   - Transacciones fallidas

### Recuperación de Errores

- ✅ **Rollback automático** en caso de error
- ✅ **Logging detallado** de errores
- ✅ **Continuación** desde el último registro válido
- ✅ **Reporte de estadísticas** de errores

## Comando Artisan

También puedes usar el comando directamente desde la línea de comandos:

```bash
php artisan import:pagos-excel /ruta/al/archivo.xlsx
```

### Parámetros del Comando

| Parámetro | Descripción |
|-----------|-------------|
| `file` | Ruta completa al archivo Excel |

### Ejemplo de Uso

```bash
# Importar archivo
php artisan import:pagos-excel /home/user/datos_pagos.xlsx

# Ver ayuda
php artisan import:pagos-excel --help
```

## Formatos de Fecha Soportados

El sistema acepta los siguientes formatos de fecha:

- `d/m/Y` (01/01/2024)
- `d-m-Y` (01-01-2024)
- `Y-m-d` (2024-01-01)
- `d/m/y` (01/01/24)
- `d-m-y` (01-01-24)

## Valores por Defecto

### Para Entidades Nuevas

- **Tipo de Entidad**: 1 (Cliente)
- **Tipo de Documento**: 1 (DNI)
- **País**: Perú
- **Estado**: 1 (Activo)

### Para Pedidos de Curso Nuevos

- **Moneda**: PEN (Soles)
- **Campaña**: Primera campaña activa o nueva campaña
- **Estado**: 1 (Activo)

### Para Contenedores Nuevos

- **Estado**: ACTIVO
- **Fecha de Creación**: Fecha actual

## Estadísticas de Procesamiento

### Contadores Disponibles

- **Consolidados**: Creados, actualizados, errores
- **Cursos**: Creados, actualizados, errores
- **Contenedores**: Creados, errores
- **Total**: Registros procesados

### Ejemplo de Reporte

```
=== RESULTADOS DE LA IMPORTACIÓN ===
Consolidados:
  - Creados: 15
  - Actualizados: 0
  - Errores: 0
Cursos:
  - Creados: 8
  - Actualizados: 0
  - Errores: 0
Contenedores:
  - Creados: 3
  - Errores: 0

Total de registros procesados: 23
```

## Códigos de Estado HTTP

| Código | Descripción |
|--------|-------------|
| 200 | OK - Importación exitosa |
| 400 | Bad Request - Datos inválidos |
| 401 | Unauthorized - Token inválido |
| 413 | Payload Too Large - Archivo muy grande |
| 500 | Internal Server Error - Error del servidor |

## Notas Importantes

1. **Autenticación**: Todos los endpoints requieren JWT válido
2. **Empresa**: Los datos se filtran por la empresa del usuario autenticado
3. **Transacciones**: Todo el proceso se ejecuta en una transacción
4. **Duplicados**: El sistema evita duplicados basándose en documentos únicos
5. **Plantilla**: Se recomienda usar la plantilla oficial para evitar errores
6. **Logs**: Todos los errores se registran en los logs del sistema

## Dependencias Requeridas

- **PhpSpreadsheet**: Para lectura de archivos Excel
- **Carbon**: Para manejo de fechas
- **Laravel Excel**: Para procesamiento avanzado (opcional)

## Versión de la API

Esta documentación corresponde a la versión 1.0 de la API de Importación de Excel. 