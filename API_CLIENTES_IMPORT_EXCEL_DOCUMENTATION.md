# API de Importación Excel - ClientesController - Documentación

## Descripción General

Esta API permite importar datos desde archivos Excel directamente desde el `ClientesController`, especificando si se debe insertar en **cursos** o **cotizaciones**. El sistema procesa los datos desde la fila 3 y crea automáticamente las entidades y contenedores necesarios.

## Características Principales

- ✅ **Detección automática** del tipo (cursos o cotizaciones) por columna SERVICIO
- ✅ **Procesamiento desde fila 3** (filas 1-2 para encabezados)
- ✅ **Creación automática** de entidades si no existen
- ✅ **Creación automática** de contenedores si no existen
- ✅ **Registro de importación** en tabla `imports_clientes`
- ✅ **Cascade delete** automático al borrar importación
- ✅ **Validación de datos** en tiempo real
- ✅ **Plantillas descargables** específicas por tipo
- ✅ **Transacciones seguras** con rollback en caso de error
- ✅ **Estadísticas detalladas** del proceso

## Autenticación

Todos los endpoints requieren autenticación JWT. Incluye el token en el header de autorización:

```
Authorization: Bearer {token}
```

## Endpoints Disponibles

### 1. Importar Archivo Excel

**Endpoint:** `POST /api/base-datos/clientes/import-excel`

**Descripción:** Sube y procesa un archivo Excel detectando automáticamente si se debe insertar en cursos o cotizaciones según la columna SERVICIO.

#### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `excel_file` | file | Sí | Archivo Excel (.xlsx, .xls) máximo 10MB |

#### Ejemplo de Request

```bash
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/import-excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@datos_clientes.xlsx"
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "message": "Importación completada exitosamente",
  "data": {
    "creados": 15,
    "actualizados": 0,
    "errores": 0,
    "tipo_detectado": "cursos",
    "import_id": 123,
    "detalles": [
      "Fila 3: Curso creado para JESUS QUESQUEN CONDORI",
      "Fila 4: Curso creado para MARIA GONZALEZ",
      "Fila 5: Curso ya existe para JUAN PEREZ"
    ]
  }
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

**Endpoint:** `POST /api/base-datos/clientes/descargar-plantilla`

**Descripción:** Crea y proporciona una plantilla Excel específica para el tipo de importación.

#### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|-----------|-------------|
| `tipo` | string | Sí | "cursos" o "cotizaciones" |

#### Ejemplo de Request

```bash
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/descargar-plantilla" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"tipo": "cursos"}'
```

#### Respuesta Exitosa (200)

```json
{
  "success": true,
  "message": "Plantilla creada exitosamente",
  "download_url": "https://api.ejemplo.com/api/base-datos/clientes/descargar-plantilla/cursos"
}
```

## Estructura del Excel

### Estructura General (Aplicable para Cursos y Cotizaciones)

| Columna | Posición | Descripción | Ejemplo |
|---------|----------|-------------|---------|
| N | A | Número de registro | 1 |
| CLIENTE | B | Nombre del cliente | JESUS QUESQUEN CONDORI |
| DNI | C | Número de DNI | 12345678 |
| CORREO | D | Correo electrónico | jesus@email.com |
| WHATSAPP | E | Número de WhatsApp | 981 466 498 |
| SERVICIO | F | Tipo de servicio | CONSOLIDADO o CURSO |
| FECHA | G | Fecha del servicio | 1/01/2024 |
| SERVICIO | H | Detalle del servicio | CONSOLIDADO #1 |
| RUC | I | Número de RUC | 10452681418 |
| RAZON SOCIAL O NOMBRE | J | Razón social completa | JESUS ANTONIO QUESQUEN CONDORI |

### Ejemplo de Datos

#### Para Cursos:
```
| N | CLIENTE | DNI | CORREO | WHATSAPP | SERVICIO | FECHA | SERVICIO | RUC | RAZON SOCIAL O NOMBRE |
|----|---------|-----|--------|----------|----------|-------|----------|-----|----------------------|
| 1 | JESUS QUESQUEN CONDORI | | | 981 466 498 | CURSO | 1/01/2024 | CURSO #1 | | JESUS ANTONIO QUESQUEN CONDORI |
| 2 | MARIA GONZALEZ | 12345678 | maria@email.com | 999 888 777 | CURSO | 15/01/2024 | CURSO #2 | | MARIA ELENA GONZALEZ LOPEZ |
```

#### Para Cotizaciones:
```
| N | CLIENTE | DNI | CORREO | WHATSAPP | SERVICIO | FECHA | SERVICIO | RUC | RAZON SOCIAL O NOMBRE |
|----|---------|-----|--------|----------|----------|-------|----------|-----|----------------------|
| 1 | JESUS QUESQUEN CONDORI | | | 981 466 498 | CONSOLIDADO | 1/01/2024 | CONSOLIDADO #1 | 10452681418 | JESUS ANTONIO QUESQUEN CONDORI |
| 2 | MARIA GONZALEZ | 12345678 | maria@email.com | 999 888 777 | CONSOLIDADO | 15/01/2024 | CONSOLIDADO #2 | 20123456789 | MARIA ELENA GONZALEZ LOPEZ |
```

## Lógica de Procesamiento

### 1. Detección Automática del Tipo
- Se lee la **columna F (SERVICIO)** de la **fila 3**
- Si contiene "CONSOLIDADO" → se procesa como **cotizaciones**
- Si contiene "CURSO" → se procesa como **cursos**
- Si no se puede detectar → error

### 2. Registro de Importación
- Se crea un registro en `imports_clientes` con:
  - Nombre del archivo
  - Ruta del archivo
  - Cantidad de filas procesadas
  - Tipo de importación detectado
  - Empresa y usuario
  - Estadísticas del proceso

### 3. Para Cursos

1. **Crear/Encontrar Entidad**
   - Se busca por número de documento y empresa
   - Si no existe, se crea una nueva entidad
   - Se asignan valores por defecto (país, tipo documento, etc.)

2. **Crear Pedido de Curso**
   - Se busca por entidad y empresa
   - Si no existe, se crea un nuevo pedido
   - Se asigna `id_cliente_importacion` para relación con la importación

### 4. Para Cotizaciones

1. **Crear/Encontrar Contenedor**
   - Si el contenedor no existe, se crea automáticamente
   - Si no se especifica contenedor, se usa "Contenedor Default"

2. **Crear Cotización**
   - Se busca por documento y contenedor
   - Si no existe, se crea una nueva cotización
   - Se asigna estado "PENDIENTE"
   - Se asigna `id_cliente_importacion` para relación con la importación

### 5. Cascade Delete
- Al borrar un registro de `imports_clientes`
- Se eliminan automáticamente todos los registros relacionados
- En `pedido_curso` y `cotizaciones` con el mismo `id_cliente_importacion`

## Validaciones

### Validaciones del Archivo

- ✅ Formato: .xlsx o .xls
- ✅ Tamaño máximo: 10MB
- ✅ Datos desde fila 3
- ✅ Columnas requeridas presentes

### Validaciones de Datos

- ✅ Columna SERVICIO debe contener "CONSOLIDADO" o "CURSO"
- ✅ Cliente no puede estar vacío
- ✅ Al menos DNI o RUC debe estar presente
- ✅ Fecha en formato válido (d/m/Y, d-m-Y, Y-m-d)
- ✅ Usuario autenticado válido

### Validaciones de Negocio

- ✅ Empresa del usuario autenticado (ID = 1)
- ✅ Entidades únicas por documento (DNI o RUC) y empresa
- ✅ Contenedores únicos por empresa
- ✅ Cotizaciones únicas por documento y contenedor
- ✅ Registro de importación único por archivo

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

### Para Cotizaciones Nuevas

- **Estado**: PENDIENTE
- **Estado Cotizador**: PENDIENTE
- **Logística Final**: 0
- **Impuestos Final**: 0

## Estadísticas de Procesamiento

### Contadores Disponibles

- **Creados**: Registros nuevos insertados
- **Actualizados**: Registros que ya existían
- **Errores**: Filas con errores de procesamiento
- **Detalles**: Lista detallada de cada operación

### Ejemplo de Reporte

```json
{
  "creados": 15,
  "actualizados": 3,
  "errores": 0,
  "detalles": [
    "Fila 3: Curso creado para JESUS QUESQUEN CONDORI",
    "Fila 4: Curso creado para MARIA GONZALEZ",
    "Fila 5: Curso ya existe para JUAN PEREZ"
  ]
}
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
2. **Empresa**: Los datos se filtran por empresa ID = 1 (fijo)
3. **Detección Automática**: El tipo se detecta por la columna SERVICIO de la fila 3
4. **Transacciones**: Todo el proceso se ejecuta en una transacción
5. **Cascade Delete**: Al borrar una importación se eliminan todos los registros relacionados
6. **Duplicados**: El sistema evita duplicados basándose en documentos únicos
7. **Plantillas**: Se recomienda usar las plantillas oficiales para evitar errores
8. **Logs**: Todos los errores se registran en los logs del sistema

## Tabla de Importaciones

### Estructura de `imports_clientes`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint | ID único de la importación |
| `nombre_archivo` | string | Nombre original del archivo Excel |
| `ruta_archivo` | string | Ruta donde se guardó el archivo |
| `cantidad_rows` | integer | Número de filas procesadas |
| `tipo_importacion` | string | "cursos" o "cotizaciones" |
| `empresa_id` | integer | ID de la empresa (siempre 1) |
| `usuario_id` | integer | ID del usuario que realizó la importación |
| `estadisticas` | json | Estadísticas del proceso de importación |
| `created_at` | timestamp | Fecha de creación |
| `updated_at` | timestamp | Fecha de última actualización |

### Relaciones

- **Cascade Delete**: Al eliminar un registro de `imports_clientes`, se eliminan automáticamente:
  - Todos los registros en `pedido_curso` con `id_cliente_importacion` correspondiente
  - Todos los registros en `cotizaciones` con `id_cliente_importacion` correspondiente

## Dependencias Requeridas

- **PhpSpreadsheet**: Para lectura de archivos Excel
- **Carbon**: Para manejo de fechas
- **Laravel Excel**: Para procesamiento avanzado (opcional)

## Ejemplos de Uso

### Ejemplo 1: Importar Cursos

```bash
# 1. Descargar plantilla de cursos
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/descargar-plantilla" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"tipo": "cursos"}'

# 2. Llenar datos en la plantilla

# 3. Importar archivo
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/import-excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@cursos.xlsx"
```

### Ejemplo 2: Importar Cotizaciones

```bash
# 1. Descargar plantilla de cotizaciones
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/descargar-plantilla" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"tipo": "cotizaciones"}'

# 2. Llenar datos en la plantilla

# 3. Importar archivo
curl -X POST "https://api.ejemplo.com/api/base-datos/clientes/import-excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@cotizaciones.xlsx"
```

## Versión de la API

Esta documentación corresponde a la versión 1.0 de la API de Importación Excel del ClientesController. 