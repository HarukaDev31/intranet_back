# Sistema de Autenticación - Probusiness Intranet

## Descripción
Sistema de autenticación completo para Probusiness Intranet utilizando Laravel 8 y JWT (JSON Web Tokens).

## Características
- ✅ Autenticación con JWT
- ✅ Validación de usuarios, empresas y organizaciones
- ✅ Verificación de estados activos
- ✅ Manejo de contraseñas hasheadas
- ✅ Endpoints protegidos con middleware
- ✅ Refresh tokens
- ✅ Documentación completa

## Instalación

### 1. Instalar dependencias
```bash
composer install
```

### 2. Configurar variables de entorno
Copia el archivo `.env.example` a `.env` y configura tu base de datos:
```bash
cp .env.example .env
```

Edita el archivo `.env` con tu configuración de base de datos:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=probusiness_intranet
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

### 3. Generar clave de aplicación
```bash
php artisan key:generate
```

### 4. Ejecutar migraciones
```bash
php artisan migrate
```

### 5. Ejecutar seeders (datos de prueba)
```bash
php artisan db:seed
```

### 6. Configurar JWT
La clave secreta de JWT ya fue generada automáticamente durante la instalación.

## Uso

### Credenciales de prueba
- **Usuario**: `admin`
- **Contraseña**: `password123`

### Endpoints disponibles

#### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
    "No_Usuario": "admin",
    "No_Password": "password123"
}
```

#### Obtener usuario actual
```bash
GET /api/auth/me
Authorization: Bearer {token}
```

#### Refresh token
```bash
POST /api/auth/refresh
Authorization: Bearer {token}
```

#### Logout
```bash
POST /api/auth/logout
Authorization: Bearer {token}
```

## Estructura de la base de datos

### Tablas principales:
- `pais` - Países
- `empresa` - Empresas
- `organizacion` - Organizaciones
- `usuario` - Usuarios del sistema
- `grupo` - Grupos de usuarios
- `grupo_usuario` - Relación usuarios-grupos
- `moneda` - Monedas por empresa
- `almacen` - Almacenes por organización
- `subdominio_tienda_virtual` - Configuración de tiendas virtuales

### Estados importantes:
- `Nu_Estado = 1` - Activo
- `Nu_Estado = 0` - Inactivo

## Configuración adicional

### Tiempo de vida de tokens
Edita `config/jwt.php` para modificar:
- `ttl` - Tiempo de vida del token (minutos)
- `refresh_ttl` - Tiempo de vida del refresh token (minutos)

### Middleware personalizado
El middleware `jwt.auth` está disponible para proteger rutas:
```php
Route::middleware('jwt.auth')->group(function () {
    // Rutas protegidas
});
```

## Seguridad

### Contraseñas
- Las contraseñas se hashean automáticamente con bcrypt
- Para usuarios existentes, asegúrate de que las contraseñas estén hasheadas

### Validaciones
- Verificación de estado de usuario (activo/inactivo)
- Verificación de estado de empresa (activa/inactiva)
- Verificación de estado de organización (activa/inactiva)
- Validación de credenciales

## Solución de problemas

### Error de token expirado
```json
{
    "status": "error",
    "message": "Token expirado"
}
```
Solución: Usar el endpoint `/api/auth/refresh` para obtener un nuevo token.

### Error de usuario no encontrado
```json
{
    "status": "danger",
    "message": "No existe usuario"
}
```
Solución: Verificar que el usuario existe y está activo en la base de datos.

### Error de contraseña incorrecta
```json
{
    "status": "warning",
    "message": "Contraseña incorrecta"
}
```
Solución: Verificar que la contraseña esté hasheada correctamente en la base de datos.

## Desarrollo

### Agregar nuevos campos
1. Crear migración: `php artisan make:migration add_field_to_table`
2. Actualizar modelo correspondiente
3. Actualizar controlador si es necesario

### Agregar nuevas validaciones
1. Modificar método `verificarAccesoLogin` en `AuthController`
2. Agregar validaciones en el método `login`

## Soporte
Para soporte técnico, contacta al equipo de desarrollo de Probusiness. 