# Solución: Headers CORS Duplicados en Múltiples Locations

## ❌ Problema

```
The 'Access-Control-Allow-Origin' header contains multiple values 
'http://localhost:3001, https://clientes.probusiness.pe'
```

## 🔍 Causa

En Nginx, cuando múltiples bloques `location` matchean una petición, **cada uno agrega el header CORS**. Cuando la petición va a `/files/contratos/...`:

1. `location /` matchea (por defecto)
2. `location ~ ^/files/` matchea (más específico)

Ambos están agregando el header, causando duplicación.

---

## ✅ Solución

**Mantener los headers CORS solo en las locations que realmente los necesitan**, NO en todas.

### Configuración Correcta:

```nginx
# ❌ ELIMINAR headers de aquí
location / {
    try_files $uri $uri/ /index.php?$query_string;
    # Sin add_header CORS
}

# ❌ ELIMINAR headers de aquí
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    # ... config PHP ...
    # Sin add_header CORS
}

# ✅ MANTENER CORS aquí
location /storage/ {
    try_files $uri =404;
    add_header 'Access-Control-Allow-Origin' $cors_origin always;
    add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
}

# ✅ MANTENER CORS aquí
location ~ ^/files/ {
    try_files $uri /index.php?$query_string;
    add_header 'Access-Control-Allow-Origin' $cors_origin always;
    add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
}
```

---

## 🚀 APLICAR EN TU SERVIDOR

### Paso 1: Actualizar Nginx

```bash
sudo nano /etc/nginx/conf.d/intranetback.probusiness.pe.conf
# o
sudo nano /etc/nginx/sites-available/intranetback.probusiness.pe
```

### Paso 2: Copiar esta estructura

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
    
    if ($http_origin ~* ^https?://(.*\.)?probusiness\.pe$) {
        set $cors_origin $http_origin;
    }
    
    if ($http_origin ~* ^http://localhost) {
        set $cors_origin $http_origin;
    }

    # Sin CORS headers aquí
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Sin CORS headers aquí
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        proxy_connect_timeout 1800s;
        proxy_send_timeout 1800s;
        proxy_read_timeout 1800s;
    }

    # Con CORS headers
    location /storage/ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
        
        add_header 'Access-Control-Allow-Origin' $cors_origin always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    # Con CORS headers
    location ~ ^/files/ {
        try_files $uri /index.php?$query_string;
        add_header 'Access-Control-Allow-Origin' $cors_origin always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

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

### Paso 3: Verificar y recargar

```bash
# Verificar sintaxis
sudo nginx -t

# Recargar
sudo systemctl reload nginx
```

---

## 🧪 PRUEBAS

### Test 1: Con curl

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/files/contratos/test.pdf -i 2>&1 | grep -i "access-control-allow-origin"

# Debe mostrar UNA sola línea:
# < access-control-allow-origin: https://clientes.probusiness.pe
```

### Test 2: Verificar que es una sola línea

```bash
curl -H "Origin: https://clientes.probusiness.pe" \
  -X OPTIONS https://intranetback.probusiness.pe/files/contratos/test.pdf -i 2>&1 | grep -c "access-control-allow-origin"

# Debe mostrar: 1
# (Si muestra > 1, hay duplicación)
```

### Test 3: Desde navegador

```javascript
fetch('https://intranetback.probusiness.pe/files/contratos/contrato_cotizacion_8677_1761595447_MIGUEL_VILLEGAS.pdf')
  .then(response => {
    console.log('✅ CORS OK');
    console.log('Header:', response.headers.get('Access-Control-Allow-Origin'));
    return response.blob();
  })
  .then(blob => console.log('✅ Archivo descargado:', blob.size, 'bytes'))
  .catch(error => console.error('❌ Error:', error.message));
```

---

## 📋 CAMBIOS REALIZADOS

| Location | Antes | Después |
|----------|-------|---------|
| `location /` | Tenía CORS headers | Eliminados (no necesarios) |
| `location ~ \.php$` | Tenía CORS headers | Eliminados (PHP devuelve JSON, no archivos) |
| `location /storage/` | Tenía CORS headers | Mantenidos ✅ |
| `location ~ ^/files/` | Tenía CORS headers | Mantenidos ✅ |

---

## 🔒 POR QUÉ FUNCIONA AHORA

1. **Variables CORS** - Se definen una sola vez a nivel de server
2. **Headers únicos** - Se agregan solo donde se necesitan (`/storage/` y `/files/`)
3. **Sin duplicación** - Cada petición solo ve un header
4. **Eficiente** - Las outras locations no procesan headers innecesarios

---

## ✨ CHECKLIST FINAL

- [ ] Actualizado nginx con la nueva estructura
- [ ] Eliminados CORS headers de `location /`
- [ ] Eliminados CORS headers de `location ~ \.php$`
- [ ] Mantenidos CORS en `/storage/` y `//files/`
- [ ] Verificado sintaxis: `sudo nginx -t`
- [ ] Recargado: `sudo systemctl reload nginx`
- [ ] Probado con curl (una sola línea de header)
- [ ] Probado desde navegador (sin errores)

✅ **CORS debe funcionar ahora sin duplicación de headers.**

