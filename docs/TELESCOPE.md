# Laravel Telescope (intranet back)

## Por qué sale 404 en `/telescope`

1. **`TELESCOPE_ENABLED=false`** (por defecto) → Laravel no registra las rutas.
2. **`composer install --no-dev`** con Telescope solo en `require-dev` → el paquete no existe en el servidor.
3. Falta **`php artisan migrate`** (tablas `telescope_*`).

## Acceso (producción)

Login con usuarios internos (`tabla usuario`), igual que el visor de logs. La sesión queda en **cookie**; no hace falta `?token=` en la URL.

1. Abrir: `https://intranetback.probusiness.pe/telescope`
2. Si no hay sesión → redirige a `/telescope-login`
3. Credenciales: `No_Usuario` / password (CodeIgniter)
4. Logout: `/telescope-logout`

Opcional — fallbacks sin formulario (token / IP):

```env
TELESCOPE_DASHBOARD_TOKEN=secreto   # ?token= o header X-Telescope-Token
TELESCOPE_ALLOWED_IPS=1.2.3.4
```

## Despliegue en producción

```bash
composer install --no-dev
php artisan migrate
php artisan config:clear
php artisan route:clear
```

En `.env` del servidor:

```env
TELESCOPE_ENABLED=true
```

Con Telescope activo se graba todo (requests, queries, jobs). El prune semanal limita el tamaño de la BD.

## Limpieza de BD (prod)

Con Telescope activo, el schedule borra entries con más de **7 días**:

```text
telescope:prune --hours=168   # domingo 04:00 America/Lima (solo si TELESCOPE_ENABLED=true)
```

Manual:

```bash
php artisan telescope:prune --hours=168
```

## Nginx

El `root` debe ser la carpeta `public/` y las rutas no-API deben pasar por `index.php` (igual que Horizon).

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
