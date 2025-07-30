# Sistema de Menús - Laravel API

Este sistema implementa la funcionalidad de menús jerárquicos basada en grupos de usuario, similar al sistema original de CodeIgniter.

## Características

- **Menús jerárquicos**: Soporte para menús padre, hijos y sub-hijos
- **Control por grupos**: Los menús se filtran según el `ID_Grupo` del usuario
- **Usuario root**: Acceso completo a todos los menús
- **Integración con login**: Los menús se incluyen automáticamente en la respuesta del login

## Estructura de Base de Datos

### Tabla `menu`
```sql
CREATE TABLE menu (
    ID_Menu INT PRIMARY KEY,
    No_Menu VARCHAR(255),
    ID_Padre INT DEFAULT 0,
    Nu_Orden INT,
    Nu_Activo TINYINT DEFAULT 0,
    -- otros campos...
);
```

### Tabla `menu_acceso`
```sql
CREATE TABLE menu_acceso (
    ID_Menu_Acceso INT PRIMARY KEY,
    ID_Menu INT,
    ID_Grupo_Usuario INT,
    -- otros campos...
);
```

### Tabla `grupo_usuario`
```sql
CREATE TABLE grupo_usuario (
    ID_Grupo_Usuario INT PRIMARY KEY,
    ID_Usuario INT,
    ID_Grupo INT,
    -- otros campos...
);
```

## API Endpoints

### 1. Login con Menús
**POST** `/api/auth/login`

Incluye automáticamente los menús del usuario en la respuesta:

```json
{
    "status": "success",
    "message": "Iniciando sesión",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "user": {
        "ID_Usuario": 1,
        "No_Usuario": "admin",
        "ID_Grupo": 1,
        // ... otros campos del usuario
    },
    "menus": [
        {
            "ID_Menu": 1,
            "No_Menu": "Administración",
            "ID_Padre": 0,
            "Nu_Orden": 1,
            "Nu_Cantidad_Menu_Padre": 2,
            "Hijos": [
                {
                    "ID_Menu": 2,
                    "No_Menu": "Usuarios",
                    "ID_Padre": 1,
                    "Nu_Orden": 1,
                    "Nu_Cantidad_Menu_Hijos": 0,
                    "SubHijos": []
                }
            ]
        }
    ]
}
```

### 2. Obtener Menús
**GET** `/api/menu/listar`

Requiere autenticación JWT. Devuelve los menús del usuario autenticado:

```json
{
    "status": "success",
    "message": "Menús obtenidos exitosamente",
    "data": [
        // ... estructura de menús
    ]
}
```

### 3. Obtener Menús (Alias)
**GET** `/api/menu/get`

Alias para `/api/menu/listar` para compatibilidad.

## Lógica de Filtrado

### Usuario Normal
- Los menús se filtran por `ID_Grupo` del usuario
- Solo se muestran menús a los que el grupo tiene acceso

### Usuario Root
- Acceso completo a todos los menús
- No se aplica filtro por grupo
- Se usa `DISTINCT` para evitar duplicados

## Estructura de Respuesta

### Menú Padre
```json
{
    "ID_Menu": 1,
    "No_Menu": "Administración",
    "ID_Padre": 0,
    "Nu_Orden": 1,
    "Nu_Cantidad_Menu_Padre": 2,
    "Hijos": [...]
}
```

### Menú Hijo
```json
{
    "ID_Menu": 2,
    "No_Menu": "Usuarios",
    "ID_Padre": 1,
    "Nu_Orden": 1,
    "Nu_Cantidad_Menu_Hijos": 1,
    "SubHijos": [...]
}
```

### Menú Sub-Hijo
```json
{
    "ID_Menu": 3,
    "No_Menu": "Crear Usuario",
    "ID_Padre": 2,
    "Nu_Orden": 1
}
```

## Comandos de Prueba

### Probar Menús
```bash
# Probar con usuario por defecto
php artisan test:menus

# Probar con usuario específico
php artisan test:menus --user=admin --grupo=1

# Probar con usuario root
php artisan test:menus --user=root --grupo=1
```

## Uso en el Frontend

### Ejemplo con JavaScript
```javascript
// Login
const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        No_Usuario: 'admin',
        No_Password: 'password'
    })
});

const data = await response.json();

if (data.status === 'success') {
    // Guardar token
    localStorage.setItem('token', data.token);
    
    // Guardar menús
    localStorage.setItem('menus', JSON.stringify(data.menus));
    
    // Renderizar menús
    renderMenus(data.menus);
}

// Función para renderizar menús
function renderMenus(menus) {
    const menuContainer = document.getElementById('menu');
    
    menus.forEach(menu => {
        const menuItem = document.createElement('div');
        menuItem.className = 'menu-item';
        menuItem.innerHTML = `
            <span>${menu.No_Menu}</span>
            ${menu.Hijos && menu.Hijos.length > 0 ? 
                `<div class="submenu">${renderSubMenus(menu.Hijos)}</div>` : 
                ''
            }
        `;
        menuContainer.appendChild(menuItem);
    });
}

function renderSubMenus(hijos) {
    return hijos.map(hijo => `
        <div class="submenu-item">
            <span>${hijo.No_Menu}</span>
            ${hijo.SubHijos && hijo.SubHijos.length > 0 ? 
                `<div class="sub-submenu">${renderSubSubMenus(hijo.SubHijos)}</div>` : 
                ''
            }
        </div>
    `).join('');
}

function renderSubSubMenus(subHijos) {
    return subHijos.map(subHijo => `
        <div class="sub-submenu-item">
            <span>${subHijo.No_Menu}</span>
        </div>
    `).join('');
}
```

## Notas Importantes

1. **Seguridad**: Los menús se filtran automáticamente según los permisos del grupo del usuario.

2. **Performance**: Las consultas están optimizadas para obtener toda la estructura de menús en una sola llamada.

3. **Compatibilidad**: La estructura es compatible con el sistema original de CodeIgniter.

4. **Extensibilidad**: Fácil de extender para agregar más niveles de menús o campos adicionales.

5. **Caché**: Considera implementar caché para los menús si son estáticos y se consultan frecuentemente.

## Migración desde CodeIgniter

Si estás migrando desde CodeIgniter, la estructura de respuesta es compatible:

```php
// CodeIgniter
$menus = $this->listarMenu();

// Laravel
$response = $this->obtenerMenusUsuario($user);
// $response tiene la misma estructura que en CodeIgniter
``` 