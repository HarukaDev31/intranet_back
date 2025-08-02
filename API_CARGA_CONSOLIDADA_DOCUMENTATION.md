# API Carga Consolidada - Documentación

## Autenticación
Todas las rutas requieren autenticación JWT. Incluye el token en el header:
```
Authorization: Bearer {token}
```

---

## Rutas de Clientes

### 1. Obtener Lista de Clientes
**GET** `/api/base-datos/clientes`

#### Parámetros de Query:
- `limit` (opcional): Número de registros por página (default: 10)
- `page` (opcional): Número de página (default: 1)
- `search` (opcional): Término de búsqueda
- `servicio` (opcional): Filtrar por servicio
- `categoria` (opcional): Filtrar por categoría
- `Recurrente` (opcional): Filtrar solo clientes recurrentes (con más de un servicio)
- `fecha_inicio` (opcional): Fecha de inicio en formato dd/mm/yyyy
- `fecha_fin` (opcional): Fecha de fin en formato dd/mm/yyyy

#### Ejemplo de Request:
```
GET /api/base-datos/clientes?limit=15&page=1&search=Juan&categoria=Recurrente&fecha_inicio=12/08/2025&fecha_fin=18/08/2025
```

#### Respuesta Exitosa (200):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nombre": "Juan Pérez",
            "documento": "12345678",
            "correo": "juan@email.com",
            "telefono": "123456789",
            "fecha": "15/01/2024",
            "primer_servicio": {
                "servicio": "Importación",
                "fecha": "15/01/2024",
                "categoria": "Comercial"
            },
            "total_servicios": 3,
            "servicios": [
                {
                    "servicio": "Importación",
                    "fecha": "15/01/2024",
                    "categoria": "Comercial"
                },
                {
                    "servicio": "Exportación",
                    "fecha": "20/02/2024",
                    "categoria": "Industrial"
                }
            ]
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75,
        "from": 1,
        "to": 15
    }
}
```

#### Respuesta de Error (500):
```json
{
    "status": "error",
    "message": "Error al obtener clientes: Mensaje del error"
}
```

### 2. Obtener Cliente Específico
**GET** `/api/base-datos/clientes/{id}`

#### Respuesta Exitosa (200):
```json
{
    "success": true,
    "data": {
        "id": 1,
        "nombre": "Juan Pérez",
        "documento": "12345678",
        "correo": "juan@email.com",
        "telefono": "123456789",
        "fecha": "15/01/2024",
        "primer_servicio": {
            "servicio": "Importación",
            "fecha": "15/01/2024",
            "categoria": "Comercial"
        },
        "total_servicios": 3,
        "servicios": [...]
    }
}
```

### 3. Crear Cliente
**POST** `/api/base-datos/clientes`

#### Body:
```json
{
    "nombre": "Nuevo Cliente",
    "documento": "87654321",
    "correo": "nuevo@email.com",
    "telefono": "987654321"
}
```

### 4. Actualizar Cliente
**PUT** `/api/base-datos/clientes/{id}`

### 5. Eliminar Cliente
**DELETE** `/api/base-datos/clientes/{id}`

### 6. Estadísticas de Clientes
**GET** `/api/base-datos/clientes/buscar/estadisticas`

#### Respuesta:
```json
{
    "success": true,
    "data": {
        "total_clientes": 150,
        "clientes_este_mes": 25,
        "servicios_totales": 450,
        "categorias": {
            "Comercial": 80,
            "Industrial": 45,
            "Otros": 25
        }
    }
}
```

### 7. Clientes por Servicio
**GET** `/api/base-datos/clientes/por-servicio?servicio=Importación`

---

## Rutas de Carga Consolidada

### Contenedores

#### 1. Obtener Lista de Contenedores
**GET** `/api/carga-consolidada/contenedores`

#### Parámetros de Query:
- `limit` (opcional): Número de registros por página (default: 10)
- `page` (opcional): Número de página (default: 1)
- `search` (opcional): Término de búsqueda
- `mes` (opcional): Filtrar por mes
- `estado` (opcional): Filtrar por estado
- `tipo_carga` (opcional): Filtrar por tipo de carga
- `id_pais` (opcional): Filtrar por país

#### Respuesta Exitosa (200):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "mes": "ENERO",
            "id_pais": 1,
            "carga": "Carga 2024-01",
            "f_puerto": "2024-01-15T10:30:00",
            "f_entrega": "2024-01-20T14:00:00",
            "empresa": "Empresa ABC",
            "estado": "PENDIENTE",
            "f_cierre": "2024-01-25",
            "lista_embarque_url": "https://example.com/lista.pdf",
            "bl_file_url": "https://example.com/bl.pdf",
            "factura_general_url": "https://example.com/factura.pdf",
            "estado_china": "PENDIENTE",
            "estado_documentacion": "PENDIENTE",
            "tipo_carga": "CARGA CONSOLIDADA",
            "naviera": "Naviera XYZ",
            "tipo_contenedor": "40HC",
            "canal_control": "Verde",
            "numero_dua": "DUA123456",
            "fecha_zarpe": "2024-01-10",
            "fecha_arribo": "2024-01-15",
            "fecha_declaracion": "2024-01-18",
            "fecha_levante": "2024-01-22",
            "valor_fob": 50000.00,
            "valor_flete": 5000.00,
            "costo_destino": 2000.00,
            "ajuste_valor": 0.00,
            "multa": 0.00,
            "observaciones": "Observaciones del contenedor",
            "pais": {
                "ID_Pais": 1,
                "Nombre": "China"
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 25,
        "from": 1,
        "to": 10
    }
}
```

#### 2. Obtener Contenedor Específico
**GET** `/api/carga-consolidada/contenedores/{id}`

#### 3. Crear Contenedor
**POST** `/api/carga-consolidada/contenedores`

#### Body:
```json
{
    "mes": "ENERO",
    "id_pais": 1,
    "carga": "Carga 2024-01",
    "f_puerto": "2024-01-15 10:30:00",
    "f_entrega": "2024-01-20 14:00:00",
    "empresa": "Empresa ABC",
    "estado": "PENDIENTE",
    "tipo_carga": "CARGA CONSOLIDADA",
    "naviera": "Naviera XYZ",
    "tipo_contenedor": "40HC",
    "canal_control": "Verde",
    "numero_dua": "DUA123456",
    "valor_fob": 50000.00,
    "valor_flete": 5000.00,
    "costo_destino": 2000.00
}
```

#### 4. Actualizar Contenedor
**PUT** `/api/carga-consolidada/contenedores/{id}`

#### 5. Eliminar Contenedor
**DELETE** `/api/carga-consolidada/contenedores/{id}`

#### 6. Opciones de Filtro
**GET** `/api/carga-consolidada/contenedores/filters/options`

#### Respuesta:
```json
{
    "success": true,
    "data": {
        "meses": [
            {"ENERO": "Enero"},
            {"FEBRERO": "Febrero"},
            {"MARZO": "Marzo"}
        ],
        "estados": [
            {"PENDIENTE": "Pendiente"},
            {"RECIBIENDO": "Recibiendo"},
            {"COMPLETADO": "Completado"}
        ],
        "tipos_carga": [
            {"G. IMPORTACION": "G. Importación"},
            {"CARGA CONSOLIDADA": "Carga Consolidada"}
        ],
        "paises": [
            {"id": 1, "nombre": "China"},
            {"id": 2, "nombre": "Estados Unidos"}
        ]
    }
}
```

#### 7. Exportar Contenedores
**GET** `/api/carga-consolidada/contenedores/export`

---

### Tipos de Cliente

#### 1. Obtener Lista de Tipos de Cliente
**GET** `/api/carga-consolidada/tipos-cliente`

#### Respuesta:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Cliente Regular"
        },
        {
            "id": 2,
            "name": "Cliente VIP"
        }
    ]
}
```

#### 2. Crear Tipo de Cliente
**POST** `/api/carga-consolidada/tipos-cliente`

#### Body:
```json
{
    "name": "Nuevo Tipo de Cliente"
}
```

#### 3. Actualizar Tipo de Cliente
**PUT** `/api/carga-consolidada/tipos-cliente/{id}`

#### 4. Eliminar Tipo de Cliente
**DELETE** `/api/carga-consolidada/tipos-cliente/{id}`

---

### Cotizaciones

#### 1. Obtener Lista de Cotizaciones
**GET** `/api/carga-consolidada/cotizaciones`

#### Parámetros de Query:
- `limit` (opcional): Número de registros por página (default: 10)
- `page` (opcional): Número de página (default: 1)
- `search` (opcional): Búsqueda por nombre, documento o correo
- `estado` (opcional): Filtrar por estado
- `estado_cliente` (opcional): Filtrar por estado del cliente
- `id_contenedor` (opcional): Filtrar por contenedor
- `id_tipo_cliente` (opcional): Filtrar por tipo de cliente

#### Respuesta Exitosa (200):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "id_contenedor": 1,
            "id_tipo_cliente": 1,
            "fecha": "2024-01-15T10:30:00",
            "nombre": "Cliente Ejemplo",
            "documento": "12345678",
            "correo": "cliente@email.com",
            "telefono": "123456789",
            "volumen": 10.50,
            "cotizacion_file_url": "https://example.com/cotizacion.pdf",
            "cotizacion_final_file_url": "https://example.com/cotizacion_final.pdf",
            "estado": "PENDIENTE",
            "volumen_doc": "10.5 m³",
            "valor_doc": 5000.00,
            "valor_cot": 5500.00,
            "volumen_china": "10.5 m³",
            "factura_comercial": "FAC-001",
            "id_usuario": 1,
            "monto": 5500.00,
            "fob": 5000.00,
            "impuestos": 500.00,
            "tarifa": 100.00,
            "estado_cliente": "RESERVADO",
            "peso": 1000.00,
            "tarifa_final": 110.00,
            "monto_final": 5610.00,
            "volumen_final": 10.50,
            "guia_remision_url": "https://example.com/guia.pdf",
            "factura_general_url": "https://example.com/factura.pdf",
            "cotizacion_final_url": "https://example.com/cotizacion_final.pdf",
            "estado_cotizador": "PENDIENTE",
            "fecha_confirmacion": "2024-01-16T15:00:00",
            "estado_pagos_coordinacion": "PENDIENTE",
            "estado_cotizacion_final": "PENDIENTE",
            "impuestos_final": 500.00,
            "fob_final": 5000.00,
            "note_administracion": "Notas de administración",
            "status_cliente_doc": "Pendiente",
            "logistica_final": 100.00,
            "qty_item": 5,
            "contenedor": {
                "id": 1,
                "carga": "Carga 2024-01"
            },
            "tipo_cliente": {
                "id": 1,
                "name": "Cliente Regular"
            },
            "usuario": {
                "ID_Usuario": 1,
                "Nombre": "Usuario Ejemplo"
            }
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 10,
        "total": 50,
        "from": 1,
        "to": 10
    }
}
```

#### 2. Crear Cotización
**POST** `/api/carga-consolidada/cotizaciones`

#### Body:
```json
{
    "id_contenedor": 1,
    "id_tipo_cliente": 1,
    "nombre": "Nuevo Cliente",
    "documento": "87654321",
    "correo": "nuevo@email.com",
    "telefono": "987654321",
    "volumen": 10.50,
    "valor_cot": 5500.00,
    "estado": "PENDIENTE"
}
```

#### 3. Cotizaciones por Contenedor
**GET** `/api/carga-consolidada/cotizaciones/por-contenedor/{contenedorId}`

#### 4. Estadísticas de Cotizaciones
**GET** `/api/carga-consolidada/cotizaciones/estadisticas`

#### Respuesta:
```json
{
    "success": true,
    "data": {
        "total_cotizaciones": 150,
        "cotizaciones_pendientes": 45,
        "cotizaciones_confirmadas": 80,
        "cotizaciones_declinadas": 25,
        "valor_total": 750000.00,
        "promedio_por_cotizacion": 5000.00,
        "estados_distribucion": {
            "PENDIENTE": 45,
            "CONFIRMADO": 80,
            "DECLINADO": 25
        }
    }
}
```

---

## Códigos de Estado HTTP

- **200**: Operación exitosa
- **201**: Recurso creado exitosamente
- **400**: Error en la solicitud (datos inválidos)
- **401**: No autorizado (token inválido o expirado)
- **404**: Recurso no encontrado
- **422**: Error de validación
- **500**: Error interno del servidor

---

## Notas Importantes

1. **Autenticación**: Todas las rutas requieren un token JWT válido
2. **Paginación**: Las listas están paginadas por defecto
3. **Filtros**: Los filtros son opcionales y se pueden combinar
4. **Fechas**: Las fechas se devuelven en formato ISO 8601
5. **Archivos**: Las URLs de archivos apuntan a recursos almacenados
6. **Relaciones**: Los datos incluyen información de relaciones cuando es relevante 