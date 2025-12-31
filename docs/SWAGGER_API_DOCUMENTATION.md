# Documentación API con Swagger/OpenAPI

## Descripción

Este proyecto utiliza [L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger) para generar documentación interactiva de la API REST basada en anotaciones OpenAPI 3.0.

## Acceso a la Documentación

### URL de la Documentación

Una vez que el servidor esté corriendo, puedes acceder a la documentación Swagger UI en:

```
http://localhost:8000/api/documentation
```

O en tu dominio de producción:

```
https://tu-dominio.com/api/documentation
```

## Generación de Documentación

### Regenerar Documentación

Cada vez que modifiques las anotaciones Swagger en los controladores, ejecuta:

```bash
php artisan l5-swagger:generate
```

### Regenerar en Desarrollo (automático)

En el archivo de configuración `config/l5-swagger.php`, puedes habilitar la regeneración automática:

```php
'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true),
```

## Estructura de Anotaciones

### Información Base

La configuración base de la API está definida en `app/Http/Controllers/Controller.php`:

- **Info**: Título, versión y descripción de la API
- **Server**: URL base del servidor
- **SecurityScheme**: Configuración de autenticación JWT
- **Tags**: Categorías de endpoints

### Anotaciones en Controladores

Cada endpoint documentado tiene anotaciones como:

```php
/**
 * @OA\Get(
 *     path="/ruta/endpoint",
 *     tags={"Categoría"},
 *     summary="Descripción corta",
 *     description="Descripción detallada",
 *     operationId="nombreOperacion",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(...),
 *     @OA\RequestBody(...),
 *     @OA\Response(...)
 * )
 */
```

## Autenticación

La API utiliza autenticación JWT (Bearer Token). Para usar endpoints protegidos:

1. Obtén un token usando `/api/auth/login` o `/api/auth/clientes/login`
2. En Swagger UI, haz clic en el botón "Authorize" 🔐
3. Ingresa el token (sin el prefijo "Bearer")
4. Todos los endpoints protegidos usarán automáticamente el token

## Tags/Categorías Disponibles

| Tag | Descripción |
|-----|-------------|
| Autenticación | Login/logout usuarios internos |
| Autenticación Clientes | Login/registro clientes externos |
| Menú | Gestión de menús del sistema |
| Clientes | CRUD de clientes |
| Productos | Gestión de productos |
| Carga Consolidada | Contenedores y cotizaciones |
| Calculadora Importación | Cálculos de importación |
| Cursos | Gestión de cursos |
| Notificaciones | Sistema de notificaciones |
| Calendario | Eventos y tareas |
| Campañas | Gestión de campañas |
| Noticias | Noticias del sistema |
| Perfil Usuario | Gestión de perfil |
| Empresa Usuario | Datos de empresa |
| Delivery | Sistema de entregas |
| Contenedores | Gestión de contenedores |
| Ubicaciones | Países, departamentos, etc. |
| Pagos | Gestión de pagos y transacciones |
| Factura y Guía | Gestión de facturas y guías |
| Tipos de Cliente | Clasificación de clientes |
| Dashboard Usuario | Dashboard personalizado |
| Dashboard Ventas | Métricas de ventas |
| Importación | Importación desde Excel |
| Documentación | Documentación de carga |
| Cotización Final | Cotizaciones finales |
| Clientes Carga Consolidada | Clientes en carga consolidada |
| Usuarios | Gestión de usuarios |
| Regulaciones | Regulaciones de importación |
| Commons | Utilidades comunes |
| Google Sheets | Integración Google Sheets |
| Cotizaciones Proveedor | Cotizaciones de proveedores |
| Aduana | Formularios de aduana |
| Entregas | Gestión de entregas |
| Archivos | Servicio de archivos |
| Broadcasting | WebSocket y notificaciones |

## Endpoints Documentados

### Autenticación

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/auth/login` | Login usuario interno |
| POST | `/auth/logout` | Cerrar sesión |
| POST | `/auth/refresh` | Refrescar token |
| GET | `/auth/me` | Usuario autenticado |
| POST | `/auth/profile` | Actualizar perfil |

### Autenticación Clientes

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/auth/clientes/register` | Registro de cliente |
| POST | `/auth/clientes/login` | Login de cliente |
| POST | `/auth/clientes/forgot-password` | Recuperar contraseña |
| POST | `/auth/clientes/reset-password` | Restablecer contraseña |
| GET | `/auth/clientes/profile` | Ver perfil |
| POST | `/auth/clientes/profile` | Actualizar perfil |
| GET | `/auth/clientes/business` | Ver empresa |
| POST | `/auth/clientes/business` | Actualizar empresa |

### Menú

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/menu/listar` | Listar menús del usuario |

### Clientes

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/clientes` | Listar clientes |
| GET | `/clientes/{id}` | Ver cliente |

### Productos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/productos` | Listar productos |
| GET | `/productos/filter-options` | Opciones de filtro |

### Carga Consolidada

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/carga-consolidada/contenedores` | Listar contenedores |
| GET | `/carga-consolidada/contenedores/{id}/cotizaciones` | Cotizaciones por contenedor |
| GET | `/carga-consolidada/contenedores/{id}/clientes-pagos` | Clientes con pagos |
| GET | `/carga-consolidada/contenedores/{id}/factura-guia` | Facturas y guías |
| GET | `/carga-consolidada/contenedores/{id}/documentacion/folders` | Carpetas documentación |
| GET | `/carga-consolidada/contenedores/{id}/cotizaciones-finales` | Cotizaciones finales |
| GET | `/carga-consolidada/contenedores/{id}/clientes/general` | Clientes general |
| GET | `/carga-consolidada/contenedores/{id}/clientes/embarcados` | Clientes embarcados |
| GET | `/carga-consolidada/contenedores/{id}/clientes/pagos` | Pagos de clientes |
| GET | `/carga-consolidada/contenedores/{id}/clientes/variacion` | Variaciones |
| GET | `/carga-consolidada/pagos` | Consolidado de pagos |
| GET | `/carga-consolidada/tipos-cliente` | Tipos de cliente |

### Dashboard

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/carga-consolidada/dashboard-ventas/contenedores` | Contenedores ventas |
| GET | `/carga-consolidada/dashboard-ventas/vendedores` | Vendedores ventas |
| GET | `/carga-consolidada/dashboard-usuario/contenedores` | Contenedores usuario |
| GET | `/carga-consolidada/dashboard-usuario/vendedores` | Vendedores usuario |

### Regulaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/regulaciones/antidumping` | Regulaciones antidumping |
| GET | `/regulaciones/permisos` | Regulaciones permisos |
| GET | `/regulaciones/etiquetado` | Regulaciones etiquetado |
| GET | `/regulaciones/documentos-especiales` | Documentos especiales |
| GET | `/regulaciones/entidades/dropdown` | Entidades dropdown |
| GET | `/regulaciones/rubros/dropdown` | Rubros dropdown |

### Campañas y Cursos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/campaigns` | Listar campañas |
| POST | `/campaigns` | Crear campaña |
| DELETE | `/campaigns/{id}` | Eliminar campaña |
| GET | `/campaigns/{id}/students` | Estudiantes de campaña |
| GET | `/cursos` | Listar cursos |

### Noticias

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/news` | Noticias públicas |
| GET | `/news/admin` | Noticias admin |

### Notificaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/notificaciones` | Listar notificaciones |

### Calendario

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/calendar/events` | Obtener eventos |

### Ubicaciones

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/external/location/paises` | Listar países |
| GET | `/external/location/departamentos` | Listar departamentos |
| GET | `/external/location/provincias/{id}` | Provincias por departamento |
| GET | `/external/location/distritos/{id}` | Distritos por provincia |
| GET | `/commons/paises/dropdown` | Países para dropdown |

### Usuarios

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/usuarios/{id}/grupos` | Usuario con grupos |

### Archivos e Importación

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/files/{path}` | Servir archivo |
| GET | `/carga-consolidada/import/form` | Formulario importación |
| POST | `/carga-consolidada/import/excel` | Importar Excel |

### Google Sheets

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/google/sheets/values` | Valores de Google Sheet |

### Broadcasting

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/broadcasting/auth` | Autenticar canal |

## Archivos de Configuración

- `config/l5-swagger.php` - Configuración de L5-Swagger
- `storage/api-docs/api-docs.json` - Documentación generada

## Agregar Nuevos Endpoints

Para documentar un nuevo endpoint:

1. Agrega las anotaciones OpenAPI al método del controlador
2. Ejecuta `php artisan l5-swagger:generate`
3. Verifica en Swagger UI

### Ejemplo Básico

```php
/**
 * @OA\Get(
 *     path="/mi-endpoint",
 *     tags={"MiCategoria"},
 *     summary="Mi resumen",
 *     @OA\Response(
 *         response=200,
 *         description="Éxito"
 *     )
 * )
 */
public function miMetodo()
{
    // ...
}
```

## Recursos Adicionales

- [Documentación L5-Swagger](https://github.com/DarkaOnLine/L5-Swagger)
- [Especificación OpenAPI 3.0](https://swagger.io/specification/)
- [Anotaciones swagger-php](https://zircote.github.io/swagger-php/)
