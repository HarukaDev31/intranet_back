# Configuración Final de CORS en Nginx

## 📋 Resumen de la Solución

Has pasado por varios problemas de CORS que hemos resuelto paso a paso:

1. ✅ Error de `add_header` dentro de bloques `if`
2. ✅ Headers CORS duplicados (Nginx + Laravel)
3. ✅ Headers CORS duplicados en múltiples locations
4. ✅ Falta de CORS en `/api/`

La solución final es tener **4 bloques `location` separados** con CORS solo donde se necesita.

---

## 🔧 Configuración Final Correcta

```nginx
server {
    listen 443 ssl;
    server_name intranetback.probusiness.pe;

    client_max_body_size 700M;
    client_body_timeout 3000s;
    client_header_timeout 300s;
    root /var/www/html/intranet_back/public;
    index index.php index.html index.htm;

    error_log /var/log/nginx/intranetback_error.log;
    access_log /var/log/nginx/intranetback_access.log;
    ssl_certificate /etc/letsencrypt/live/intranetback.probusiness.pe/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/intranetback.probusiness.pe/privkey.pem;

    # ============================================
    # CORS - Variable para múltiples dominios
    # ============================================
    set $cors_origin "";
    
    # Permite CUALQUIER subdominio de probusiness.pe
    if ($http_origin ~* ^https?://(.*\.)?probusiness\.pe$) {
        set $cors_origin $http_origin;
    }
    
    # También permite localhost para desarrollo local
    if ($http_origin ~* ^http://localhost) {
        set $cors_origin $http_origin;
    }

    # ============================================
    # LOCATIONS CON CORS
    # ============================================

    # 1. API - Con CORS (preferencia sobre location /)
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
        
        add_header 'Access-Control-Allow-Origin' $cors_origin always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'DNT, User-Agent, X-Requested-With, If-Modified-Since, Cache-Control, Content-Type, Range, Authorization' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
    }

    # 2. Storage - Con CORS
    location /storage/ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
        
        add_header 'Access-Control-Allow-Origin' $cors_origin always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    # 3. Files - Con CORS
    location ~ ^/files/ {
        try_files $uri /index.php?$query_string;

        add_header 'Access-Control-Allow-Origin' $cors_origin always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    # ============================================
    # LOCATIONS SIN CORS (Genéricas)
    # ============================================

    # 4. Raíz - Sin CORS (no necesario)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # 5. PHP - Sin CORS (Laravel manejará si es necesario)
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        proxy_connect_timeout 1800s;
        proxy_send_timeout 1800s;
        proxy_read_timeout 1800s;
    }

    # 6. WebSockets - Sin CORS (proxy a puerto 6001)
    location /app/ {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 86400;
    }

    location /laravel-websockets {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🎯 Cómo Funciona

### Orden de Matching en Nginx

Nginx intenta matchear en este orden:

1. ✅ `location /api/` → Ruta exacta `/api/`, tiene CORS
2. ✅ `location /storage/` → Ruta exacta `/storage/`, tiene CORS  
3. ✅ `location ~ ^/files/` → Regex, tiene CORS
4. `location ~ \.php$` → Regex para PHP
5. `location /app/` → Proxy a websockets
6. `location /laravel-websockets` → Proxy a websockets
7. `location /` → Raíz, sin CORS

### Peticiones Específicas

```
GET /api/contenedor/external/cotizacion/...
  → Matchea location /api/ ✅ (CORS)

GET /files/contratos/...
  → Matchea location ~ ^/files/ ✅ (CORS)

GET /storage/images/...
  → Matchea location /storage/ ✅ (CORS)

GET / (o cualquier otra ruta)
  → Matchea location / (sin CORS)
```

---

## 🚀 Aplicar en Servidor

```bash
# 1. Editar archivo
sudo nano /etc/nginx/conf.d/intranetback.probusiness.pe.conf

# 2. Agregar el bloque location /api/ ANTES de location /
#    (Ver configuración arriba)

# 3. Verificar sintaxis
sudo nginx -t
# Debe mostrar: nginx: configuration file test is successful

# 4. Recargar
sudo systemctl reload nginx

# 5. Verificar estado
sudo systemctl status nginx
```

---

## 🧪 Pruebas Completas

### Test 1: API con CORS

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -H "Access-Control-Request-Method: POST" \
  -X OPTIONS https://intranetback.probusiness.pe/api/contenedor/external/cotizacion/get-service-contract/ad088f6f-aaa0-47c2-9e47-11ff6ae30c0d \
  -v 2>&1 | grep -i "access-control-allow-origin"

# Debe mostrar:
# < access-control-allow-origin: https://clientes.probusiness.pe
```

### Test 2: Files con CORS

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/files/contratos/test.pdf \
  -v 2>&1 | grep -i "access-control-allow-origin"

# Debe mostrar:
# < access-control-allow-origin: https://clientes.probusiness.pe
```

### Test 3: Storage con CORS

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/storage/images/test.jpg \
  -v 2>&1 | grep -i "access-control-allow-origin"

# Debe mostrar:
# < access-control-allow-origin: https://clientes.probusiness.pe
```

### Test 4: Verificar sin duplicación

```bash
# Todos los tests deben mostrar EXACTAMENTE 1 línea
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/api/test \
  -i 2>&1 | grep -c "access-control-allow-origin"

# Resultado: 1 ✅
```

### Test 5: Desde navegador

```javascript
// Desde https://clientes.probusiness.pe

// Test API
fetch('https://intranetback.probusiness.pe/api/contenedor/external/cotizacion/get-service-contract/ad088f6f-aaa0-47c2-9e47-11ff6ae30c0d')
  .then(r => {
    console.log('✅ API CORS OK:', r.headers.get('Access-Control-Allow-Origin'));
    return r.json();
  })
  .then(d => console.log('✅ Datos:', d))
  .catch(e => console.error('❌ Error API:', e));

// Test Files
fetch('https://intranetback.probusiness.pe/files/contratos/test.pdf')
  .then(r => {
    console.log('✅ Files CORS OK:', r.headers.get('Access-Control-Allow-Origin'));
    return r.blob();
  })
  .then(b => console.log('✅ Archivo:', b.size, 'bytes'))
  .catch(e => console.error('❌ Error Files:', e));
```

---

## 📊 Resumen Final

| Ruta | CORS | Método |
|------|------|--------|
| `/api/*` | ✅ Sí | POST, PUT, DELETE, OPTIONS |
| `/storage/*` | ✅ Sí | GET, OPTIONS |
| `/files/*` | ✅ Sí | GET, OPTIONS |
| `/` (raíz) | ❌ No | - |
| `/*.php` | ❌ No | - |
| `/app/*` | ❌ No | WebSocket Proxy |

---

## ✨ Checklist Final

- [ ] Agregado bloque `location /api/` antes de `location /`
- [ ] Verificado sintaxis: `sudo nginx -t`
- [ ] Recargado Nginx: `sudo systemctl reload nginx`
- [ ] Probado API con curl (CORS OK)
- [ ] Probado Files con curl (CORS OK)
- [ ] Probado Storage con curl (CORS OK)
- [ ] Verificado sin duplicación (1 solo header)
- [ ] Probado desde navegador (sin errores)
- [ ] Probado con múltiples subdominios (app.*, clientes.*, etc.)

✅ **CORS debe funcionar correctamente en todas las rutas que lo necesitan.**

---

## 🔒 Ventajas de Esta Configuración

✅ **Específica** - Cada ruta tiene su propia configuración
✅ **Sin duplicación** - Cada petición matchea UNA sola location
✅ **Eficiente** - No procesa headers innecesarios
✅ **Mantenible** - Fácil agregar nuevas rutas con CORS
✅ **Segura** - Solo subdominios de probusiness.pe + localhost
✅ **Flexible** - Soporta cualquier subdominio nuevo automáticamente

