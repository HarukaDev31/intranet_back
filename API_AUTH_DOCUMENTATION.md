# API de Autenticación - Probusiness Intranet

## Descripción
Esta API proporciona endpoints de autenticación usando JWT (JSON Web Tokens) para el sistema Probusiness Intranet.

## Endpoints

### 1. Login
**POST** `/api/auth/login`

Autentica un usuario y devuelve un token JWT.

#### Parámetros de entrada:
```json
{
    "No_Usuario": "string (requerido)",
    "No_Password": "string (requerido)",
    "ID_Empresa": "integer (opcional)",
    "ID_Organizacion": "integer (opcional)"
}
```

#### Respuesta exitosa (200):
```json
{
    "status": "success",
    "message": "Iniciando sesión",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
        "ID_Usuario": 1,
        "No_Usuario": "usuario123",
        "Nu_Estado": 1,
        "ID_Empresa": 1,
        "ID_Organizacion": 1
    },
    "iCantidadAcessoUsuario": 1,
    "iIdEmpresa": 1
}
```

#### Respuesta de error (401/422):
```json
{
    "status": "error",
    "message": "Mensaje de error específico"
}
```

### 2. Logout
**POST** `/api/auth/logout`

Cierra la sesión del usuario invalidando el token JWT.

#### Headers requeridos:
```
Authorization: Bearer {token}
```

#### Respuesta exitosa (200):
```json
{
    "status": "success",
    "message": "Sesión cerrada exitosamente"
}
```

### 3. Refresh Token
**POST** `/api/auth/refresh`

Refresca el token JWT actual.

#### Headers requeridos:
```
Authorization: Bearer {token}
```

#### Respuesta exitosa (200):
```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600
}
```

### 4. Obtener Usuario Actual
**GET** `/api/auth/me`

Obtiene la información del usuario autenticado.

#### Headers requeridos:
```
Authorization: Bearer {token}
```

#### Respuesta exitosa (200):
```json
{
    "ID_Usuario": 1,
    "No_Usuario": "usuario123",
    "Nu_Estado": 1,
    "ID_Empresa": 1,
    "ID_Organizacion": 1
}
```

## Códigos de Estado

- **200**: Operación exitosa
- **401**: No autorizado (token inválido, expirado o no proporcionado)
- **422**: Datos de entrada inválidos
- **500**: Error interno del servidor

## Estados de Respuesta

- **success**: Operación completada exitosamente
- **error**: Error en la operación
- **warning**: Advertencia (credenciales incorrectas, usuario suspendido)
- **danger**: Error crítico (usuario no existe, empresa desactivada)

## Autenticación

Para usar los endpoints protegidos, incluye el token JWT en el header de autorización:

```
Authorization: Bearer {tu_token_jwt}
```

## Ejemplo de Uso con cURL

### Login:
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "No_Usuario": "usuario123",
    "No_Password": "password123"
  }'
```

### Usar token para acceder a endpoint protegido:
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {tu_token_jwt}"
```

## Notas Importantes

1. **Contraseñas**: Las contraseñas deben estar hasheadas con bcrypt en la base de datos.
2. **Estados**: Los usuarios, empresas y organizaciones deben tener `Nu_Estado = 1` para estar activos.
3. **Tokens**: Los tokens JWT tienen un tiempo de expiración configurable en `config/jwt.php`.
4. **Seguridad**: Todos los endpoints protegidos requieren un token JWT válido.

## Configuración

La configuración de JWT se encuentra en `config/jwt.php`. Los parámetros principales son:

- `ttl`: Tiempo de vida del token (en minutos)
- `refresh_ttl`: Tiempo de vida del refresh token (en minutos)
- `secret`: Clave secreta para firmar los tokens 