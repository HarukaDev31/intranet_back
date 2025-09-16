# Documentación del Controlador de Notificaciones

## 📋 Información General

El `NotificacionController` maneja todas las operaciones relacionadas con las notificaciones del sistema. Proporciona funcionalidades para crear, leer, actualizar y gestionar notificaciones por usuario, rol y módulo.

**Archivo:** `app/Http/Controllers/NotificacionController.php`  
**Middleware:** `auth:api` (requiere autenticación JWT)  
**Namespace:** `App\Http\Controllers`

---

## 🔗 Rutas API

Todas las rutas están bajo el prefijo `/api/notificaciones/`:

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/api/notificaciones` | Obtener notificaciones paginadas |
| `GET` | `/api/notificaciones/conteo-no-leidas` | Obtener conteo de notificaciones no leídas |
| `GET` | `/api/notificaciones/{id}` | Obtener una notificación específica |
| `POST` | `/api/notificaciones` | Crear una nueva notificación |
| `PUT` | `/api/notificaciones/{id}/marcar-leida` | Marcar notificación como leída |
| `POST` | `/api/notificaciones/marcar-multiples-leidas` | Marcar múltiples notificaciones como leídas |
| `PUT` | `/api/notificaciones/{id}/archivar` | Archivar una notificación |

---

## 📊 Modelo de Datos

### Estructura de la Notificación

```json
{
  "id": 1,
  "titulo": "Nueva Cotización Creada",
  "mensaje": "Se ha creado una nueva cotización para el contenedor #123",
  "descripcion": "Detalles adicionales sobre la cotización...",
  "modulo": "cotizaciones",
  "navigate_to": "cargaconsolidada/abiertos/cotizaciones/123",
  "navigate_params": {
    "tab": "prospectos",
    "idCotizacion": 456
  },
  "tipo": "info",
  "icono": "fas fa-file-invoice",
  "prioridad": 3,
  "referencia_tipo": "cotizacion",
  "referencia_id": 456,
  "fecha_creacion": "2025-01-16T10:30:00Z",
  "fecha_expiracion": "2025-01-23T10:30:00Z",
  "creador": {
    "id": 1,
    "nombre": "Juan Pérez"
  },
  "estado_usuario": {
    "leida": false,
    "fecha_lectura": null,
    "archivada": false,
    "fecha_archivado": null
  }
}
```

### Tipos de Notificación

| Tipo | Descripción | Color |
|------|-------------|-------|
| `info` | Información general | Azul |
| `success` | Operación exitosa | Verde |
| `warning` | Advertencia | Amarillo |
| `error` | Error | Rojo |

### Prioridades

| Prioridad | Descripción |
|-----------|-------------|
| 1 | Crítica |
| 2 | Alta |
| 3 | Media |
| 4 | Baja |
| 5 | Informativa |

---

## 🔧 Métodos del Controlador

### 1. `index(Request $request): JsonResponse`

**Descripción:** Obtiene las notificaciones paginadas para el usuario autenticado.

**Parámetros de consulta:**
- `modulo` (opcional): Filtrar por módulo específico
- `tipo` (opcional): Filtrar por tipo de notificación
- `prioridad_minima` (opcional): Filtrar por prioridad mínima
- `no_leidas` (opcional): Solo notificaciones no leídas (boolean)
- `per_page` (opcional): Número de elementos por página (default: 15)

**Ejemplo de solicitud:**
```http
GET /api/notificaciones?modulo=cotizaciones&no_leidas=true&per_page=20
Authorization: Bearer {jwt_token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "titulo": "Nueva Cotización Creada",
        "mensaje": "Se ha creado una nueva cotización...",
        "descripcion": "Detalles adicionales...",
        "modulo": "cotizaciones",
        "navigate_to": "cargaconsolidada/abiertos/cotizaciones/123",
        "navigate_params": {
          "tab": "prospectos",
          "idCotizacion": 456
        },
        "tipo": "info",
        "icono": "fas fa-file-invoice",
        "prioridad": 3,
        "referencia_tipo": "cotizacion",
        "referencia_id": 456,
        "fecha_creacion": "2025-01-16T10:30:00Z",
        "fecha_expiracion": "2025-01-23T10:30:00Z",
        "creador": {
          "id": 1,
          "nombre": "Juan Pérez"
        },
        "estado_usuario": {
          "leida": false,
          "fecha_lectura": null,
          "archivada": false,
          "fecha_archivado": null
        }
      }
    ],
    "first_page_url": "http://api.example.com/notificaciones?page=1",
    "from": 1,
    "last_page": 3,
    "last_page_url": "http://api.example.com/notificaciones?page=3",
    "links": [...],
    "next_page_url": "http://api.example.com/notificaciones?page=2",
    "path": "http://api.example.com/notificaciones",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 42
  },
  "message": "Notificaciones obtenidas exitosamente"
}
```

---

### 2. `conteoNoLeidas(): JsonResponse`

**Descripción:** Obtiene el número total de notificaciones no leídas para el usuario autenticado.

**Ejemplo de solicitud:**
```http
GET /api/notificaciones/conteo-no-leidas
Authorization: Bearer {jwt_token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "total_no_leidas": 5
  }
}
```

---

### 3. `show(int $id): JsonResponse`

**Descripción:** Obtiene una notificación específica por su ID.

**Parámetros:**
- `id` (requerido): ID de la notificación

**Ejemplo de solicitud:**
```http
GET /api/notificaciones/123
Authorization: Bearer {jwt_token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "titulo": "Nueva Cotización Creada",
    "mensaje": "Se ha creado una nueva cotización...",
    "descripcion": "Detalles adicionales...",
    "modulo": "cotizaciones",
    "navigate_to": "cargaconsolidada/abiertos/cotizaciones/123",
    "navigate_params": {
      "tab": "prospectos",
      "idCotizacion": 456
    },
    "tipo": "info",
    "icono": "fas fa-file-invoice",
    "prioridad": 3,
    "referencia_tipo": "cotizacion",
    "referencia_id": 456,
    "fecha_creacion": "2025-01-16T10:30:00Z",
    "fecha_expiracion": "2025-01-23T10:30:00Z",
    "creador": {
      "id": 1,
      "nombre": "Juan Pérez"
    },
    "estado_usuario": {
      "leida": false,
      "fecha_lectura": null,
      "archivada": false,
      "fecha_archivado": null
    }
  }
}
```

**Error de permisos (403):**
```json
{
  "success": false,
  "message": "No tienes permisos para ver esta notificación"
}
```

---

### 4. `store(Request $request): JsonResponse`

**Descripción:** Crea una nueva notificación (solo para administradores).

**Datos requeridos:**
```json
{
  "titulo": "string (max: 255)",
  "mensaje": "string",
  "modulo": "string (max: 100)",
  "tipo": "info|success|warning|error",
  "prioridad": "integer (1-5)"
}
```

**Datos opcionales:**
```json
{
  "descripcion": "string",
  "configuracion_roles": "array",
  "rol_destinatario": "string (max: 100)",
  "usuario_destinatario": "integer (ID_Usuario existente)",
  "navigate_to": "string (max: 500)",
  "navigate_params": "array",
  "icono": "string (max: 100)",
  "referencia_tipo": "string (max: 100)",
  "referencia_id": "integer",
  "fecha_expiracion": "date (después de ahora)"
}
```

**Ejemplo de solicitud:**
```http
POST /api/notificaciones
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "titulo": "Nueva Cotización Creada",
  "mensaje": "Se ha creado una nueva cotización para el contenedor #123",
  "descripcion": "La cotización incluye 25 items con un valor total de $50,000",
  "modulo": "cotizaciones",
  "rol_destinatario": "Coordinacion",
  "navigate_to": "cargaconsolidada/abiertos/cotizaciones/123",
  "navigate_params": {
    "tab": "prospectos",
    "idCotizacion": 456
  },
  "tipo": "info",
  "icono": "fas fa-file-invoice",
  "prioridad": 3,
  "referencia_tipo": "cotizacion",
  "referencia_id": 456,
  "fecha_expiracion": "2025-01-23T10:30:00"
}
```

**Respuesta exitosa (201):**
```json
{
  "success": true,
  "data": {
    "id": 124,
    "titulo": "Nueva Cotización Creada",
    "mensaje": "Se ha creado una nueva cotización para el contenedor #123",
    "descripcion": "La cotización incluye 25 items con un valor total de $50,000",
    "modulo": "cotizaciones",
    "rol_destinatario": "Coordinacion",
    "navigate_to": "cargaconsolidada/abiertos/cotizaciones/123",
    "navigate_params": {
      "tab": "prospectos",
      "idCotizacion": 456
    },
    "tipo": "info",
    "icono": "fas fa-file-invoice",
    "prioridad": 3,
    "referencia_tipo": "cotizacion",
    "referencia_id": 456,
    "activa": true,
    "fecha_expiracion": "2025-01-23T10:30:00",
    "creado_por": 1,
    "created_at": "2025-01-16T10:30:00Z",
    "updated_at": "2025-01-16T10:30:00Z"
  },
  "message": "Notificación creada exitosamente"
}
```

**Error de validación (422):**
```json
{
  "success": false,
  "message": "Datos inválidos",
  "errors": {
    "titulo": ["El campo titulo es obligatorio."],
    "tipo": ["El tipo seleccionado no es válido."]
  }
}
```

---

### 5. `marcarComoLeida(Request $request, int $id): JsonResponse`

**Descripción:** Marca una notificación específica como leída para el usuario autenticado.

**Parámetros:**
- `id` (requerido): ID de la notificación

**Ejemplo de solicitud:**
```http
PUT /api/notificaciones/123/marcar-leida
Authorization: Bearer {jwt_token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Notificación marcada como leída"
}
```

---

### 6. `marcarMultiplesComoLeidas(Request $request): JsonResponse`

**Descripción:** Marca múltiples notificaciones como leídas para el usuario autenticado.

**Datos requeridos:**
```json
{
  "notificacion_ids": [1, 2, 3, 4, 5]
}
```

**Ejemplo de solicitud:**
```http
POST /api/notificaciones/marcar-multiples-leidas
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "notificacion_ids": [123, 124, 125, 126]
}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Notificaciones marcadas como leídas"
}
```

**Error de validación (422):**
```json
{
  "success": false,
  "message": "Datos inválidos",
  "errors": {
    "notificacion_ids": ["El campo notificacion ids es obligatorio."],
    "notificacion_ids.0": ["El campo notificacion_ids.0 debe ser un número entero."]
  }
}
```

---

### 7. `archivar(Request $request, int $id): JsonResponse`

**Descripción:** Archiva una notificación específica para el usuario autenticado.

**Parámetros:**
- `id` (requerido): ID de la notificación

**Ejemplo de solicitud:**
```http
PUT /api/notificaciones/123/archivar
Authorization: Bearer {jwt_token}
```

**Respuesta exitosa (200):**
```json
{
  "success": true,
  "message": "Notificación archivada"
}
```

---

## 🔐 Autenticación y Autorización

### Requisitos de Autenticación
- Todas las rutas requieren un token JWT válido en el header `Authorization`
- Formato: `Bearer {jwt_token}`

### Permisos por Rol

| Operación | Administrador | Coordinación | Usuario Regular |
|-----------|---------------|--------------|-----------------|
| Ver notificaciones | ✅ Todas | ✅ Propias + Rol | ✅ Propias |
| Crear notificaciones | ✅ | ❌ | ❌ |
| Marcar como leída | ✅ | ✅ | ✅ |
| Archivar | ✅ | ✅ | ✅ |

### Lógica de Visibilidad
Las notificaciones son visibles para un usuario si:
- Es el destinatario específico (`usuario_destinatario`)
- Pertenece al rol destinatario (`rol_destinatario`)
- No tiene destinatario específico (notificación general)

---

## 📱 Ejemplos de Uso Frontend

### Obtener Notificaciones con Filtros
```javascript
// Obtener notificaciones no leídas del módulo cotizaciones
const response = await fetch('/api/notificaciones?modulo=cotizaciones&no_leidas=true', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
if (data.success) {
  const notificaciones = data.data.data;
  const totalNoLeidas = data.data.total;
}
```

### Marcar como Leída y Navegar
```javascript
// Marcar como leída y navegar
async function handleNotificationClick(notificacion) {
  // Marcar como leída
  await fetch(`/api/notificaciones/${notificacion.id}/marcar-leida`, {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  // Navegar si tiene destino
  if (notificacion.navigate_to) {
    let url = notificacion.navigate_to;
    
    // Agregar parámetros si existen
    if (notificacion.navigate_params) {
      const params = new URLSearchParams(notificacion.navigate_params);
      url += `?${params.toString()}`;
    }
    
    window.location.href = url;
  }
}
```

### Crear Notificación (Admin)
```javascript
async function crearNotificacion(datos) {
  const response = await fetch('/api/notificaciones', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      titulo: "Nueva Cotización",
      mensaje: "Se ha creado una nueva cotización",
      modulo: "cotizaciones",
      tipo: "info",
      prioridad: 3,
      rol_destinatario: "Coordinacion",
      navigate_to: "cargaconsolidada/abiertos/cotizaciones/123",
      navigate_params: {
        tab: "prospectos",
        idCotizacion: 456
      }
    })
  });
  
  const result = await response.json();
  if (result.success) {
    console.log('Notificación creada:', result.data);
  }
}
```

### Contador de Notificaciones No Leídas
```javascript
// Obtener conteo para mostrar en badge
async function actualizarContadorNotificaciones() {
  const response = await fetch('/api/notificaciones/conteo-no-leidas', {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  });
  
  const data = await response.json();
  if (data.success) {
    const badge = document.getElementById('notification-badge');
    const count = data.data.total_no_leidas;
    
    if (count > 0) {
      badge.textContent = count;
      badge.style.display = 'inline';
    } else {
      badge.style.display = 'none';
    }
  }
}

// Actualizar cada 30 segundos
setInterval(actualizarContadorNotificaciones, 30000);
```

---

## 🚨 Manejo de Errores

### Códigos de Estado HTTP

| Código | Descripción | Ejemplo |
|--------|-------------|---------|
| 200 | Operación exitosa | Notificación obtenida |
| 201 | Recurso creado | Notificación creada |
| 403 | Sin permisos | No puede ver la notificación |
| 404 | No encontrado | Notificación no existe |
| 422 | Error de validación | Datos inválidos |
| 500 | Error del servidor | Error interno |

### Formato de Respuesta de Error
```json
{
  "success": false,
  "message": "Descripción del error",
  "errors": {
    "campo": ["Mensaje de error específico"]
  }
}
```

---

## 📝 Notas Técnicas

### Características del Sistema
- **Paginación automática** en listados
- **Filtros flexibles** por módulo, tipo, prioridad y estado
- **Textos personalizados** por rol usando `configuracion_roles`
- **Navegación integrada** con parámetros dinámicos
- **Estados de usuario** independientes (leída, archivada)
- **Expiración automática** de notificaciones
- **Relaciones optimizadas** con eager loading

### Optimizaciones
- Uso de `paraUsuario()` scope para filtrar automáticamente
- Eager loading de relaciones (`creador`, `usuarioDestinatario`)
- Validación robusta con mensajes específicos
- Manejo de excepciones centralizado

### Consideraciones de Rendimiento
- Las consultas incluyen índices en campos de filtrado
- Paginación para evitar cargar demasiadas notificaciones
- Lazy loading de estados de usuario específicos
- Cache de conteo de notificaciones no leídas (recomendado)

---

## 🔄 Flujo de Trabajo Típico

1. **Usuario autenticado** accede al sistema
2. **Frontend** obtiene conteo de notificaciones no leídas
3. **Usuario** ve lista de notificaciones (paginated)
4. **Usuario** hace clic en notificación → se marca como leída
5. **Sistema** navega a la URL especificada en `navigate_to`
6. **Usuario** puede archivar notificaciones antiguas

---

*Documentación generada para el sistema de notificaciones de ProBusiness Intranet v2*
