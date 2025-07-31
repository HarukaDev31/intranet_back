# Documentación de Relación Usuario-Grupo

## Descripción
Se ha implementado la relación directa entre usuarios y grupos, permitiendo acceder fácilmente a la información del grupo desde el usuario y viceversa.

## Estructura de la Base de Datos

### Tabla `usuario`
- `ID_Grupo` (int unsigned, NOT NULL): ID del grupo principal del usuario
- Foreign key a `grupo.ID_Grupo`

### Tabla `grupo`
- `ID_Grupo` (int unsigned, NOT NULL): ID del grupo
- `ID_Empresa` (int unsigned, NOT NULL): ID de la empresa
- `ID_Organizacion` (int unsigned, NOT NULL): ID de la organización
- `No_Grupo` (varchar(30), NOT NULL): Nombre del grupo
- `No_Grupo_Descripcion` (varchar(100), NULL): Descripción del grupo
- `Nu_Estado` (tinyint(1), NOT NULL): Estado del grupo (1=activo, 0=inactivo)
- `Nu_Tipo_Privilegio_Acceso` (tinyint, NULL): Tipo de privilegio de acceso
- `Fe_Registro_Hora` (timestamp, NOT NULL): Fecha de registro
- `Nu_Notificacion` (tinyint(1), NULL): Configuración de notificaciones

### Tabla `grupo_usuario` (Relación many-to-many)
- `ID_Grupo_Usuario` (int unsigned, NOT NULL): ID de la relación
- `ID_Usuario` (int unsigned, NOT NULL): ID del usuario
- `ID_Grupo` (int unsigned, NOT NULL): ID del grupo

## Modelos y Relaciones

### Modelo Usuario
```php
class Usuario extends Authenticatable implements JWTSubject
{
    // Relación directa con Grupo
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'ID_Grupo', 'ID_Grupo');
    }

    // Relación many-to-many con Grupo
    public function gruposUsuario()
    {
        return $this->hasMany(GrupoUsuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    // Métodos útiles
    public function getAllGrupos()
    {
        // Retorna todos los grupos del usuario (directo + many-to-many)
    }

    public function perteneceAGrupo($grupoId)
    {
        // Verifica si el usuario pertenece a un grupo específico
    }

    // Atributos calculados
    public function getNombreGrupoPrincipalAttribute()
    public function getDescripcionGrupoPrincipalAttribute()
    public function getTipoPrivilegioAccesoAttribute()
}
```

### Modelo Grupo
```php
class Grupo extends Model
{
    // Relación directa con Usuarios
    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'ID_Grupo', 'ID_Grupo');
    }

    // Relación many-to-many con Usuarios
    public function gruposUsuario()
    {
        return $this->hasMany(GrupoUsuario::class, 'ID_Grupo', 'ID_Grupo');
    }
}
```

## Login con Información de Grupo

### Endpoint
```
POST /api/auth/login
```

### Request Body
```json
{
    "No_Usuario": "usuario",
    "No_Password": "password",
    "ID_Empresa": 1,
    "ID_Organizacion": 1
}
```

### Response con Información de Grupo
```json
{
    "status": "success",
    "message": "Iniciando sesión",
    "token": "jwt_token_here",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
        "id": 1,
        "nombre": "usuario",
        "nombres_apellidos": "Nombre Apellido",
        "email": "usuario@email.com",
        "estado": 1,
        "empresa": {
            "id": 1,
            "nombre": "Empresa S.A.C."
        },
        "organizacion": {
            "id": 1,
            "nombre": "Organización"
        },
        "grupo": {
            "id": 3,
            "nombre": "GERENCIA",
            "descripcion": "Grupo de gerencia",
            "tipo_privilegio": 1,
            "estado": 1,
            "notificacion": 1
        }
    },
    "iCantidadAcessoUsuario": 1,
    "iIdEmpresa": 1,
    "menus": []
}
```

## Endpoints de Usuario-Grupo

### 1. Obtener Usuario con Grupos
```
GET /api/usuarios-grupos/usuario/{id}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "usuario": {
            "id": 1,
            "nombre": "usuario",
            "grupo_principal": {
                "id": 3,
                "nombre": "GERENCIA",
                "descripcion": "Grupo de gerencia",
                "tipo_privilegio": 1
            }
        },
        "grupos": [
            {
                "id": 3,
                "nombre": "GERENCIA",
                "descripcion": "Grupo de gerencia",
                "tipo_privilegio": 1,
                "estado": 1,
                "notificacion": 1
            }
        ]
    }
}
```

### 2. Obtener Usuarios por Grupo
```
GET /api/usuarios-grupos/grupo/{grupoId}
```

### 3. Verificar Pertenencia
```
POST /api/usuarios-grupos/verificar-pertenencia
```

**Request Body:**
```json
{
    "usuario_id": 1,
    "grupo_id": 3
}
```

### 4. Obtener Grupos Disponibles
```
GET /api/usuarios-grupos/grupos-disponibles/{usuarioId}
```

### 5. Obtener Estadísticas
```
GET /api/usuarios-grupos/estadisticas
```

## Comandos de Prueba

### Probar Relaciones Usuario-Grupo
```bash
php artisan test:usuario-grupo
```

### Probar Login con Grupo
```bash
php artisan test:login-grupo
```

### Verificar Estructura de Tablas
```bash
php artisan check:usuario-table
```

## Características Principales

### 1. Relación Dual
- **Relación directa**: `usuario.ID_Grupo` → `grupo.ID_Grupo`
- **Relación many-to-many**: `usuario` ↔ `grupo_usuario` ↔ `grupo`

### 2. Métodos Útiles
- `getAllGrupos()`: Obtiene todos los grupos del usuario
- `perteneceAGrupo($grupoId)`: Verifica pertenencia a un grupo
- Atributos calculados para información del grupo principal

### 3. Login Mejorado
- Incluye información completa del grupo en la respuesta
- Carga automática de relaciones (empresa, organización, grupo)
- Estructura de respuesta organizada y clara

### 4. Endpoints Completos
- CRUD para usuarios y grupos
- Verificación de pertenencias
- Estadísticas y reportes
- Grupos disponibles por usuario

## Migraciones Realizadas

### 1. Foreign Key para ID_Grupo
```php
// database/migrations/2025_07_31_152358_add_foreign_key_id_grupo_to_usuario_table.php
Schema::table('usuario', function (Blueprint $table) {
    $table->foreign('ID_Grupo')
          ->references('ID_Grupo')
          ->on('grupo')
          ->onDelete('restrict')
          ->onUpdate('cascade');
});
```

## Notas Importantes

1. **Compatibilidad**: La implementación mantiene compatibilidad con el sistema existente
2. **Flexibilidad**: Permite tanto relación directa como many-to-many
3. **Seguridad**: Foreign key constraints para integridad referencial
4. **Rendimiento**: Índices agregados para mejorar consultas
5. **Escalabilidad**: Estructura preparada para futuras funcionalidades

## Uso en el Frontend

### Ejemplo de Login
```javascript
const loginResponse = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        No_Usuario: 'usuario',
        No_Password: 'password'
    })
});

const data = await loginResponse.json();

// Acceder a información del grupo
const grupoUsuario = data.user.grupo;
console.log('Grupo:', grupoUsuario.nombre);
console.log('Tipo Privilegio:', grupoUsuario.tipo_privilegio);
```

### Verificar Pertenencia
```javascript
const verificarPertenencia = async (usuarioId, grupoId) => {
    const response = await fetch('/api/usuarios-grupos/verificar-pertenencia', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario_id: usuarioId, grupo_id: grupoId })
    });
    
    const data = await response.json();
    return data.data.pertenece;
};
``` 