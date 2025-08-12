# CotizacionProveedorController - Documentación de Funcionalidad

## Descripción
El `CotizacionProveedorController` ha sido creado para replicar la funcionalidad del método `getContenedorCotizacionProveedores` del código original de CodeIgniter, implementando una consulta compleja con joins y subconsultas para obtener información detallada de cotizaciones con proveedores.

## Funcionalidades Implementadas

### 1. Método `getContenedorCotizacionProveedores()`
Obtiene cotizaciones con proveedores por contenedor, incluyendo filtros avanzados y lógica de permisos por grupo de usuario.

#### Parámetros de entrada:
- `idContenedor` (int): ID del contenedor
- `Filtro_State` (string): Filtro por estado (por defecto: '0')
- `Filtro_Status` (string): Filtro por estado de proveedor (por defecto: '0')
- `Filtro_Estado` (string): Filtro por estado general (por defecto: '0')

#### Filtros disponibles:
- **Estado**: ROTULADO, DATOS PROVEEDOR, COBRANDO, INSPECCIONADO, RESERVADO, EMBARCADO, NO EMBARCADO
- **Estado Proveedor**: NC, C, R, CONTACTED, NS, INSPECTION, LOADED, NO LOADED
- **Estado China**: PENDIENTE, INSPECTION, LOADED, NO LOADED
- **Estado Almacén**: PENDIENTE, INSPECTION, LOADED, NO LOADED

#### Lógica de permisos por grupo:
- **Cotizador**: Solo ve sus propias cotizaciones
- **Coordinación**: Ve todas las cotizaciones confirmadas
- **ContenedorAlmacen**: Ve cotizaciones confirmadas con filtro por estado_china
- **CatalogoChina**: Ve cotizaciones confirmadas con filtro por estado_china
- **Documentacion**: Ve cotizaciones confirmadas con filtro por estado
- **GERENCIA**: Acceso completo

#### Datos retornados:
- Información básica de la cotización
- Datos del usuario (nombre y apellidos)
- Array JSON de proveedores con toda la información
- Totales calculados (CBM China y Perú)
- Permisos de edición y eliminación
- Opciones de filtro disponibles

### 2. Método `getOpcionesFiltro()`
Proporciona opciones de filtro estructuradas para el frontend.

#### Estructura de respuesta:
```json
{
    "estados_almacen": {
        "key": "estado_almacen",
        "label": "Estado Almacén",
        "placeholder": "Selecciona estado de almacén",
        "options": [{"value": "PENDIENTE", "label": "PENDIENTE"}, ...]
    }
}
```

### 3. Métodos de Actualización
- **`updateEstadoProveedor`**: Actualiza estado del proveedor
- **`updateEstadoCotizacionProveedor`**: Actualiza estado de la cotización
- **`updateProveedorData`**: Actualiza datos del proveedor
- **`updateRotulado`**: Actualiza estado de rotulado
- **`deleteCotizacion`**: Elimina cotización (solo Coordinación)

### 4. Gestión de Archivos y Documentación
- **`getFilesAlmacenDocument`**: Obtiene archivos de documentación del proveedor
- **`deleteFileDocumentation`**: Elimina archivo de documentación del sistema
- **`getFilesAlmacenInspection`**: Obtiene archivos de inspección del proveedor
- **`validateToSendInspectionMessage`**: Valida y envía mensaje de inspección

### 5. Gestión de Notas
- **`getNotes`**: Obtiene notas del proveedor
- **`addNote`**: Agrega o actualiza nota del proveedor

## Estructura de la Consulta

La consulta principal incluye:

### Joins principales:
- `contenedor_consolidado_cotizacion` (main) - Tabla principal
- `contenedor_consolidado_tipo_cliente` (TC) - Tipo de cliente
- `usuario` (U) - Datos del usuario

### Subconsulta JSON:
```sql
SELECT JSON_ARRAYAGG(
    JSON_OBJECT(
        'id', proveedores.id,
        'qty_box', proveedores.qty_box,
        'peso', proveedores.peso,
        'cbm_total', proveedores.cbm_total,
        'supplier', proveedores.supplier,
        'code_supplier', proveedores.code_supplier,
        'estados_proveedor', proveedores.estados_proveedor,
        'estados', proveedores.estados,
        'supplier_phone', proveedores.supplier_phone,
        'cbm_total_china', proveedores.cbm_total_china,
        'qty_box_china', proveedores.qty_box_china,
        'id_proveedor', proveedores.id,
        'products', proveedores.products,
        'estado_china', proveedores.estado_china,
        'arrive_date_china', proveedores.arrive_date_china,
        'send_rotulado_status', proveedores.send_rotulado_status
    )
)
FROM contenedor_consolidado_cotizacion_proveedores proveedores
WHERE proveedores.id_cotizacion = main.id
```

## Modelos

### Modelo CotizacionProveedor

#### Campos principales:
- **Identificación**: id, id_cotizacion, id_contenedor
- **Datos del proveedor**: supplier, code_supplier, supplier_phone
- **Cantidades**: qty_box, qty_box_china
- **Medidas**: peso, cbm_total, cbm_total_china
- **Estados**: estados, estados_proveedor, estado_china, estado_almacen
- **Fechas**: arrive_date_china
- **Documentos**: products, packing_list, factura_comercial

#### Enums disponibles:
- **ESTADOS_ALMACEN**: ['PENDIENTE', 'INSPECTION', 'LOADED', 'NO LOADED']
- **ESTADOS_CHINA**: ['PENDIENTE', 'INSPECTION', 'LOADED', 'NO LOADED']
- **ESTADOS**: ['ROTULADO', 'DATOS PROVEEDOR', 'COBRANDO', 'INSPECCIONADO', 'RESERVADO', 'EMBARCADO', 'NO EMBARCADO']
- **ESTADOS_PROVEEDOR**: ['NC', 'C', 'R', 'CONTACTED', 'NS', 'INSPECTION', 'LOADED', 'NO LOADED']
- **SEND_ROTULADO_STATUS**: ['PENDING', 'SENDED']

### Modelo AlmacenDocumentacion

#### Campos principales:
- **Identificación**: id, id_proveedor
- **Archivo**: file_name, file_path, file_type, file_size, file_ext
- **Metadatos**: last_modified

#### Relaciones:
- **proveedor**: BelongsTo con CotizacionProveedor

### Modelo AlmacenInspection

#### Campos principales:
- **Identificación**: id, id_proveedor
- **Archivo**: file_name, file_path, file_type, file_size, file_ext
- **Estado**: send_status (PENDING, SENDED)

#### Enums disponibles:
- **SEND_STATUS**: ['PENDING', 'SENDED']

#### Relaciones:
- **proveedor**: BelongsTo con CotizacionProveedor

#### Scopes disponibles:
- **porEstadoEnvio**: Filtra por estado de envío
- **porTipoArchivo**: Filtra por tipo de archivo

## Rutas API

### GET `/api/carga-consolidada/cotizaciones-proveedores/contenedor/{idContenedor}`
- Obtiene cotizaciones con proveedores por contenedor
- Incluye filtros y lógica de permisos

### PUT `/api/carga-consolidada/cotizaciones-proveedores/{idCotizacion}/proveedor/{idProveedor}/estado`
- Actualiza estado del proveedor

### PUT `/api/carga-consolidada/cotizaciones-proveedores/{idCotizacion}/proveedor/{idProveedor}/estado-cotizacion`
- Actualiza estado de la cotización

### PUT `/api/carga-consolidada/cotizaciones-proveedores/{idCotizacion}/proveedor/{idProveedor}/datos`
- Actualiza datos del proveedor

### PUT `/api/carga-consolidada/cotizaciones-proveedores/{idCotizacion}/proveedor/{idProveedor}/rotulado`
- Actualiza estado de rotulado

### DELETE `/api/carga-consolidada/cotizaciones-proveedores/{idCotizacion}/proveedor/{idProveedor}`
- Elimina cotización (solo Coordinación)

### GET `/api/carga-consolidada/cotizaciones-proveedores/proveedor/{idProveedor}/documentos`
- Obtiene archivos de documentación del proveedor

### DELETE `/api/carga-consolidada/cotizaciones-proveedores/documento/{idFile}`
- Elimina archivo de documentación del sistema

### GET `/api/carga-consolidada/cotizaciones-proveedores/proveedor/{idProveedor}/inspeccion`
- Obtiene archivos de inspección del proveedor

### POST `/api/carga-consolidada/cotizaciones-proveedores/proveedor/{idProveedor}/inspeccion/enviar`
- Valida y envía mensaje de inspección

### GET `/api/carga-consolidada/cotizaciones-proveedores/proveedor/{idProveedor}/notas`
- Obtiene notas del proveedor

### POST `/api/carga-consolidada/cotizaciones-proveedores/proveedor/{idProveedor}/notas`
- Agrega o actualiza nota del proveedor

## Comando de Prueba

Se incluye un comando Artisan para probar la funcionalidad:

```bash
# Probar con parámetros por defecto
php artisan test:cotizacion-proveedor

# Probar con parámetros personalizados
php artisan test:cotizacion-proveedor --contenedor=1 --filtro-state=ROTULADO --filtro-status=NC
```

## Formato de Respuesta

### Respuesta exitosa:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "id_contenedor": 1,
            "nombre": "Cliente Ejemplo",
            "estado_cotizador": "CONFIRMADO",
            "proveedores": [...],
            "totales": {
                "cbm_total_china": 10.5,
                "cbm_total_peru": 8.2
            },
            "puede_editar": true,
            "puede_eliminar": false
        }
    ],
    "filters": {...},
    "user_group": "Coordinación",
    "user_id": 123
}
```

### Respuesta de error:
```json
{
    "success": false,
    "message": "Error al obtener cotizaciones con proveedores",
    "error": "Detalle del error"
}
```

## Dependencias

- **Modelos**: `CotizacionProveedor`, `Cotizacion`, `Usuario`, `TipoCliente`
- **Middleware**: JWT para autenticación
- **Base de datos**: MySQL con soporte para JSON_ARRAYAGG y JSON_OBJECT

## Notas de Implementación

1. **Autenticación**: El controlador requiere autenticación JWT
2. **Permisos**: Los permisos se basan en el grupo del usuario
3. **Filtros**: Los filtros son opcionales y se aplican condicionalmente
4. **JSON**: Se usa JSON_ARRAYAGG para agrupar proveedores por cotización
5. **Formato**: Los datos se retornan en formato JSON estructurado sin HTML
6. **Archivos**: Se usa Laravel Storage para operaciones de archivos seguras
7. **Logging**: Se registran todas las operaciones importantes para auditoría
8. **Validación**: Validación de entrada en todos los métodos POST/PUT
9. **Relaciones**: Uso de relaciones Eloquent para consultas eficientes
10. **Simulación**: La función de envío simula servicios externos (WhatsApp, etc.)

## Nuevas Funcionalidades Implementadas

### Gestión de Archivos de Documentación
- **getFilesAlmacenDocument**: Obtiene todos los archivos de documentación asociados a un proveedor
- **deleteFileDocumentation**: Elimina archivos del sistema de archivos y de la base de datos
- Manejo seguro de archivos usando Laravel Storage

### Gestión de Archivos de Inspección
- **getFilesAlmacenInspection**: Obtiene archivos de inspección (imágenes y videos)
- Filtrado por tipo de archivo (imágenes: jpeg, png, jpg; videos: mp4)
- Control de estado de envío (PENDING, SENDED)

### Proceso de Inspección Automatizado
- **validateToSendInspectionMessage**: Proceso completo de inspección
- Actualización automática de estados del proveedor
- Simulación de envío de mensajes y medios
- Formateo de fechas en español
- Logging detallado de todas las operaciones

### Sistema de Notas
- **getNotes**: Obtiene notas asociadas a un proveedor
- **addNote**: Agrega o actualiza notas con validación
- Almacenamiento en el campo `nota` del modelo CotizacionProveedor

## Diferencias con el Código Original

1. **Sin HTML**: No se genera HTML, solo datos estructurados
2. **Filtros estructurados**: Los filtros se envían en formato JSON para el frontend
3. **Permisos explícitos**: Se incluyen campos de permisos en la respuesta
4. **Validación**: Se incluye validación de entrada en los métodos de actualización
5. **Manejo de errores**: Manejo robusto de errores con respuestas estructuradas
6. **Gestión de archivos**: Uso de Laravel Storage para operaciones de archivos
7. **Logging estructurado**: Logs detallados para debugging y auditoría
8. **Simulación de servicios**: La función de envío de mensajes simula el servicio externo
9. **Modelos Eloquent**: Uso de relaciones y scopes de Eloquent para consultas eficientes
