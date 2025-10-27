# Solución Final: Conflicto de CORS Duplicado

## ❌ Problema

```
Access-Control-Allow-Origin: https://clientes.probusiness.pe, https://clientes.probusiness.pe
(El mismo header se envía DOS veces)
```

## 🔍 Causa

Había **TRES fuentes de CORS activas simultáneamente**:

1. ✅ **Nginx** - Configurado correctamente (Tabla de routing)
2. ❌ **Fruitcake\Cors\HandleCors** - Middleware de Fruitcake (en Kernel.php línea 19)
3. ❌ **App\Http\Middleware\CorsMiddleware** - Middleware personalizado (en Kernel.php línea 24)

Ambos middlewares estaban agregando el mismo header, causando conflicto.

---

## ✅ Solución

**Desactivar TODOS los middlewares CORS en Laravel y dejar que SOLO NGINX maneje CORS.**

### Paso 1: Desactivar middlewares en `app/Http/Kernel.php`

**En tu servidor:**

```bash
sudo nano /var/www/html/intranet_back/app/Http/Kernel.php
```

**Busca la sección `protected $middleware` y comenta estas dos líneas:**

```php
protected $middleware = [
    // \App\Http\Middleware\TrustHosts::class,
    \App\Http\Middleware\TrustProxies::class,
    // \Fruitcake\Cors\HandleCors::class,  // ❌ COMENTAR ESTA LÍNEA
    \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \App\Http\Middleware\TrimStrings::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    // \App\Http\Middleware\CorsMiddleware::class,  // ❌ COMENTAR ESTA LÍNEA
];
```

### Paso 2: Limpiar caché de Laravel

```bash
cd /var/www/html/intranet_back

# Limpiar
php artisan config:clear

# Regenerar
php artisan config:cache
```

### Paso 3: Recargar servicios

```bash
sudo systemctl reload nginx
sudo systemctl restart php7.4-fpm
```

---

## 🧪 PROBAR LA SOLUCIÓN

### Test 1: Con curl

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -H "Access-Control-Request-Method: GET" \
  -X OPTIONS https://intranetback.probusiness.pe/api/contenedor/external/cotizacion/get-service-contract/ad088f6f-aaa0-47c2-9e47-11ff6ae30c0d \
  -v 2>&1 | grep -i "access-control-allow-origin"

# Resultado ESPERADO (solo UNA línea):
# < access-control-allow-origin: https://clientes.probusiness.pe
```

Si ves la línea **una sola vez**, está correcto. Si la ves dos veces, hay un problema.

### Test 2: Desde navegador

Abre la consola en https://clientes.probusiness.pe (F12) y ejecuta:

```javascript
fetch('https://intranetback.probusiness.pe/api/contenedor/external/cotizacion/get-service-contract/ad088f6f-aaa0-47c2-9e47-11ff6ae30c0d')
  .then(response => {
    console.log('✅ SUCCESS - CORS funciona correctamente');
    console.log('Header:', response.headers.get('Access-Control-Allow-Origin'));
    return response.json();
  })
  .then(data => console.log('✅ Datos recibidos:', data))
  .catch(error => console.error('❌ Error CORS:', error.message));
```

**Resultado esperado:**
- ✅ `SUCCESS - CORS funciona correctamente`
- ✅ Header: `https://clientes.probusiness.pe`
- ✅ Los datos se cargan sin errores

---

## 📋 CONFIGURACIÓN FINAL

### En Nginx (único responsable de CORS):

```nginx
set $cors_origin "";

if ($http_origin ~* ^https?://(.*\.)?probusiness\.pe$) {
    set $cors_origin $http_origin;
}

if ($http_origin ~* ^http://localhost) {
    set $cors_origin $http_origin;
}

location / {
    add_header 'Access-Control-Allow-Origin' $cors_origin always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'DNT, User-Agent, X-Requested-With, If-Modified-Since, Cache-Control, Content-Type, Range, Authorization' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;
}
```

### En Laravel (desactivado):

```php
// En app/Http/Kernel.php - Ambos middlewares comentados
// \Fruitcake\Cors\HandleCors::class,
// \App\Http\Middleware\CorsMiddleware::class,

// En config/cors.php - Configurado solo para localhost (respaldo)
'allowed_origins_patterns' => [
    '#^https?://(.*\.)?probusiness\.pe(:\d+)?$#',
],
```

---

## 📊 COMPARACIÓN

| Aspecto | Antes | Después |
|---------|-------|---------|
| Fuentes CORS | 3 (Nginx + 2 middlewares) | 1 (solo Nginx) |
| Headers duplicados | ❌ Sí | ✅ No |
| Error CORS | ❌ Sí | ✅ No |
| Performance | ❌ Lento | ✅ Rápido |
| Mantenimiento | ❌ Complejo | ✅ Simple |

---

## 🔒 VENTAJAS DE ESTA SOLUCIÓN

✅ **Único punto de control** - Solo Nginx configura CORS
✅ **Sin duplicados** - El header se envía una sola vez
✅ **Más rápido** - Nginx procesa antes que PHP
✅ **Más seguro** - Menor complejidad, menos errores
✅ **Flexible** - Fácil agregar nuevos subdominios
✅ **Compatible** - Localhost sigue funcionando para desarrollo

---

## 🆘 SI TODAVÍA HAY PROBLEMAS

### Verificar que los middlewares están desactivados

```bash
cd /var/www/html/intranet_back

# Ver que están comentados
grep -n "CorsMiddleware\|HandleCors" app/Http/Kernel.php

# Debe mostrar ambas líneas comentadas con //
```

### Verificar que solo Nginx envía headers

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/api/test -i 2>&1 | grep -c "access-control-allow-origin"

# Debe mostrar: 1 (una sola vez)
# Si muestra más de 1, hay un problema
```

### Limpiar todo

```bash
cd /var/www/html/intranet_back

# Limpiar todo
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Regenerar
php artisan config:cache
php artisan route:cache

# Reiniciar servicios
sudo systemctl restart php7.4-fpm
sudo systemctl reload nginx
```

---

## ✨ CHECKLIST FINAL

- [ ] Comentadas las líneas 19 y 24 en `app/Http/Kernel.php`
- [ ] Ejecutado `php artisan config:cache`
- [ ] Recargado Nginx: `sudo systemctl reload nginx`
- [ ] Reiniciado PHP-FPM: `sudo systemctl restart php7.4-fpm`
- [ ] Probado con curl (una sola línea de `access-control-allow-origin`)
- [ ] Probado desde navegador (sin errores CORS)
- [ ] Probado desde múltiples subdominios (app.probusiness.pe, clientes.probusiness.pe, etc.)

✅ **Una vez completados todos los pasos, CORS debería funcionar sin conflictos.**

---

## 💡 RESUMEN

El problema era que **3 sistemas diferentes estaban intentando manejar CORS**. La solución fue dejar que **solo Nginx lo maneje**, que es:

- Más rápido (menos procesamiento PHP)
- Más seguro (menos complejidad)
- Más mantenible (un único punto de control)
- Más eficiente (se procesa antes que Laravel)

Ahora cada subdominio de `probusiness.pe` funciona automáticamente, y `localhost` sigue funcionando para desarrollo.
