# Laravel Telescope (intranet back)

## Por qué sale 404 en `/telescope`

1. **`TELESCOPE_ENABLED=false`** (por defecto) → Laravel no registra las rutas.
2. **`composer install --no-dev`** con Telescope solo en `require-dev` → el paquete no existe en el servidor.
3. Falta **`php artisan migrate`** (tablas `telescope_*`).

## Despliegue en producción

```bash
composer install --no-dev   # Telescope va en require (no solo dev)
php artisan migrate
php artisan config:clear
php artisan route:clear
```

En `.env` del servidor:

```env
TELESCOPE_ENABLED=true
TELESCOPE_DASHBOARD_TOKEN=pon-un-secreto-largo-aqui
```

Abrir:

`https://intranetback.probusiness.pe/telescope?token=pon-un-secreto-largo-aqui`

Sin token verás **403** (no 404). Con token correcto carga el panel.

## Nginx

El `root` debe ser la carpeta `public/` y las rutas no-API deben pasar por `index.php` (igual que Horizon).

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```
