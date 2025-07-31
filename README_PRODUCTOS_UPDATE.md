# Documentación del Endpoint PUT de Productos

## Endpoint
```
PUT /api/base-datos/productos/{id}
```

## Descripción
Este endpoint permite actualizar la información de un producto específico en la base de datos. Todos los campos son opcionales y solo se actualizan los campos enviados en la request.

## Autenticación
Requiere autenticación JWT. Incluir el token en el header:
```
Authorization: Bearer {token}
```

## Parámetros de URL
- `{id}`: ID del producto a actualizar

## Estructura del Request Body

### Campos Opcionales

```json
{
  "link": "string | null",
  "arancel_sunat": "string | null",
  "arancel_tlc": "string | null", 
  "correlativo": "string | null",
  "antidumping": "string | null",
  "antidumping_value": "string | null",
  "tipo_producto": "string | null",
  "entidad_id": "number | null",
  "etiquetado": "string | null",
  "tipo_etiquetado_id": "number | null",
  "doc_especial": "string | null",
  "tiene_observaciones": "boolean | null",
  "observaciones": "string | null"
}
```

## Descripción de Campos

### Campos de Tributos Aduaneros
- **`link`**: URL del producto (ej: "https://www.alibaba.com/product-detail/123456")
- **`arancel_sunat`**: Porcentaje de arancel SUNAT (ej: "0%", "10%", "15%")
- **`arancel_tlc`**: Porcentaje de arancel TLC (ej: "0%", "5%", "10%")
- **`correlativo`**: Código correlativo (ej: "NO", "SI", "ABC123")
- **`antidumping`**: Indica si aplica antidumping ("SI" | "NO")
- **`antidumping_value`**: Valor específico del antidumping (solo si `antidumping` = "SI")

### Campos de Requisitos Aduaneros
- **`tipo_producto`**: Tipo de producto ("LIBRE" | "RESTRINGIDO")
- **`entidad_id`**: ID de la entidad reguladora (solo si `tipo_producto` = "RESTRINGIDO")
- **`etiquetado`**: Tipo de etiquetado ("NORMAL" | "ESPECIAL")
- **`tipo_etiquetado_id`**: ID del tipo específico de etiquetado (solo si `etiquetado` = "ESPECIAL")
- **`doc_especial`**: Indica si requiere documento especial ("SI" | "NO")

### Campos de Observaciones
- **`tiene_observaciones`**: Indica si tiene observaciones (true | false)
- **`observaciones`**: Texto de las observaciones (solo si `tiene_observaciones` = true)

## Validaciones

### Validaciones de Campos Condicionales
1. **`antidumping_value`**: Solo debe enviarse si `antidumping` = "SI"
2. **`entidad_id`**: Solo debe enviarse si `tipo_producto` = "RESTRINGIDO"
3. **`tipo_etiquetado_id`**: Solo debe enviarse si `etiquetado` = "ESPECIAL"
4. **`observaciones`**: Solo debe enviarse si `tiene_observaciones` = true

### Validaciones de Valores Permitidos
1. **`antidumping`**: Solo "SI" o "NO"
2. **`tipo_producto`**: Solo "LIBRE" o "RESTRINGIDO"
3. **`etiquetado`**: Solo "NORMAL" o "ESPECIAL"
4. **`doc_especial`**: Solo "SI" o "NO"

## Ejemplos de Request

### Request Completo
```json
{
  "link": "https://www.alibaba.com/product-detail/123456",
  "arancel_sunat": "10%",
  "arancel_tlc": "0%",
  "correlativo": "NO",
  "antidumping": "SI",
  "antidumping_value": "25.5%",
  "tipo_producto": "RESTRINGIDO",
  "entidad_id": 5,
  "etiquetado": "ESPECIAL",
  "tipo_etiquetado_id": 12,
  "doc_especial": "SI",
  "tiene_observaciones": true,
  "observaciones": "El vista de aduanas observó la medida del producto."
}
```

### Request Mínimo (solo algunos campos)
```json
{
  "arancel_sunat": "5%",
  "tipo_producto": "LIBRE",
  "etiquetado": "NORMAL"
}
```

### Request con Campos Condicionales
```json
{
  "arancel_sunat": "15%",
  "antidumping": "SI",
  "antidumping_value": "30%",
  "tipo_producto": "RESTRINGIDO",
  "entidad_id": 3,
  "etiquetado": "ESPECIAL",
  "tipo_etiquetado_id": 8,
  "tiene_observaciones": true,
  "observaciones": "Producto requiere certificación adicional."
}
```

## Respuestas

### Respuesta de Éxito (200)
```json
{
  "success": true,
  "data": {
    "id": 123,
    "nombre_comercial": "VIDEO MONITOR PARA BEBES",
    "link": "https://www.alibaba.com/product-detail/123456",
    "arancel_sunat": "10%",
    "arancel_tlc": "0%",
    "correlativo": "NO",
    "antidumping": "SI",
    "antidumping_value": "25.5%",
    "tipo_producto": "RESTRINGIDO",
    "entidad_id": 5,
    "etiquetado": "ESPECIAL",
    "tipo_etiquetado_id": 12,
    "doc_especial": "SI",
    "tiene_observaciones": true,
    "observaciones": "El vista de aduanas observó la medida del producto.",
    "entidad": {
      "id": 5,
      "nombre": "MINSA",
      "descripcion": "Ministerio de Salud"
    },
    "tipo_etiquetado": {
      "id": 12,
      "nombre": "Etiquetado Especial"
    },
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "message": "Producto actualizado exitosamente"
}
```

### Respuesta de Error - Producto no encontrado (404)
```json
{
  "success": false,
  "message": "Producto no encontrado"
}
```

### Respuesta de Error - Validación (422)
```json
{
  "success": false,
  "error": "Errores de validación",
  "data": {
    "antidumping_value": "El valor de antidumping es requerido cuando antidumping es \"SI\"",
    "entidad_id": "La entidad es requerida cuando el tipo de producto es \"RESTRINGIDO\""
  }
}
```

### Respuesta de Error - Servidor (500)
```json
{
  "success": false,
  "error": "Error al actualizar producto: Mensaje de error específico",
  "data": null
}
```

## Códigos de Estado HTTP

- **200**: Actualización exitosa
- **404**: Producto no encontrado
- **422**: Errores de validación
- **500**: Error interno del servidor

## Notas Importantes

1. **Campos Opcionales**: Todos los campos son opcionales. Solo se actualizan los campos enviados.
2. **Actualización Parcial**: Los campos no enviados mantienen sus valores existentes.
3. **Campos Condicionales**: Los campos que aparecen solo cuando se selecciona una opción específica son opcionales.
4. **Relaciones**: La respuesta incluye las relaciones `entidad` y `tipo_etiquetado` si están presentes.
5. **Validación**: El backend valida que los campos condicionales solo se envíen cuando corresponda.

## Campos Agregados a la Base de Datos

Se han agregado los siguientes campos a la tabla `productos_importados_excel`:

- `antidumping_value` (varchar(50), nullable): Valor específico del antidumping
- `entidad_id` (unsigned bigint, nullable): ID de la entidad reguladora
- `tipo_etiquetado_id` (unsigned bigint, nullable): ID del tipo de etiquetado
- `tiene_observaciones` (boolean, default false): Indica si tiene observaciones
- `observaciones` (text, nullable): Texto de las observaciones

## Relaciones

El modelo `ProductoImportadoExcel` ahora incluye las siguientes relaciones:

- `entidad()`: Relación con `EntidadReguladora`
- `tipoEtiquetado()`: Relación con `ProductoRubro` (para tipos de etiquetado)

## Pruebas

Para probar la funcionalidad, ejecutar:
```bash
php artisan test:productos-update
```

Este comando realizará pruebas automáticas de todos los tipos de actualización posibles. 