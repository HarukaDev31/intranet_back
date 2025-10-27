#!/bin/bash

# Script de configuración Nginx para ProBusiness Backend
# Uso: sudo bash setup-nginx.sh

set -e

echo "🚀 Iniciando configuración de Nginx para ProBusiness..."

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Verificar si es root
if [[ $EUID -ne 0 ]]; then
   print_error "Este script debe ejecutarse como root (use: sudo bash setup-nginx.sh)"
   exit 1
fi

# Variables a configurar
read -p "Ingresa tu dominio principal (ej: probusiness.com.pe): " DOMAIN
read -p "Ingresa el PRIMER dominio frontend (ej: app.probusiness.com.pe): " FRONTEND_DOMAIN_1
read -p "Ingresa el SEGUNDO dominio frontend (ej: panel.probusiness.com.pe): " FRONTEND_DOMAIN_2
read -p "¿Deseas agregar más dominios? (s/n): " AGREGAR_MAS

FRONTEND_DOMAINS="$FRONTEND_DOMAIN_1|$FRONTEND_DOMAIN_2"

while [ "$AGREGAR_MAS" = "s" ] || [ "$AGREGAR_MAS" = "S" ]; do
    read -p "Ingresa otro dominio frontend: " OTRO_DOMINIO
    if [ -n "$OTRO_DOMINIO" ]; then
        FRONTEND_DOMAINS="$FRONTEND_DOMAINS|$OTRO_DOMINIO"
    fi
    read -p "¿Agregar más dominios? (s/n): " AGREGAR_MAS
done

read -p "Ingresa la ruta del proyecto Laravel (ej: /var/www/probusiness): " PROJECT_PATH
read -p "Versión de PHP (ej: 8.2): " PHP_VERSION

# Validar que el proyecto existe
if [ ! -d "$PROJECT_PATH" ]; then
    print_error "La ruta del proyecto no existe: $PROJECT_PATH"
    exit 1
fi

print_info "Configurando Nginx..."

# Crear archivo de configuración nginx
NGINX_CONFIG="/etc/nginx/sites-available/probusiness"

cat > "$NGINX_CONFIG" << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name DOMAIN www.DOMAIN;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name DOMAIN www.DOMAIN;

    ssl_certificate /etc/letsencrypt/live/DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/DOMAIN/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    root PROJECT_PATH/public;
    index index.php index.html index.htm;

    client_max_body_size 100M;

    access_log /var/log/nginx/probusiness-access.log;
    error_log /var/log/nginx/probusiness-error.log;

    add_header 'Access-Control-Allow-Origin' 'https://FRONTEND_DOMAIN' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;

    if ($request_method = 'OPTIONS') {
        add_header 'Access-Control-Allow-Origin' 'https://FRONTEND_DOMAIN' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-Requested-With' always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
        add_header 'Access-Control-Max-Age' '86400' always;
        add_header 'Content-Type' 'text/plain charset=UTF-8' always;
        add_header 'Content-Length' '0' always;
        return 204;
    }

    location ~ ^/storage/ {
        try_files $uri =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
        add_header 'Access-Control-Allow-Origin' 'https://FRONTEND_DOMAIN' always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    location ~ ^/files/ {
        try_files $uri /index.php?$query_string;
        add_header 'Access-Control-Allow-Origin' 'https://FRONTEND_DOMAIN' always;
        add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    location ~ ^/(css|js|images|fonts|assets)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location ~ ^/(favicon.ico|robots.txt)$ {
        try_files $uri =404;
        expires 30d;
    }

    location ~ /\. {
        deny all;
    }

    location ~ ~$ {
        deny all;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php_VERSION-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        fastcgi_param HTTPS on;
        include fastcgi_params;
        fastcgi_read_timeout 300s;
        fastcgi_connect_timeout 75s;
    }

    location ~ /\.php$ {
        deny all;
    }
}
EOF

# Reemplazar variables
sed -i "s|DOMAIN|$DOMAIN|g" "$NGINX_CONFIG"
sed -i "s|FRONTEND_DOMAIN|$FRONTEND_DOMAINS|g" "$NGINX_CONFIG"
sed -i "s|PROJECT_PATH|$PROJECT_PATH|g" "$NGINX_CONFIG"
sed -i "s|php_VERSION|php$PHP_VERSION|g" "$NGINX_CONFIG"

print_info "Archivo nginx creado: $NGINX_CONFIG"

# Crear enlace simbólico
if [ ! -L /etc/nginx/sites-enabled/probusiness ]; then
    ln -s "$NGINX_CONFIG" /etc/nginx/sites-enabled/probusiness
    print_info "Enlace simbólico creado"
else
    print_warning "Enlace simbólico ya existe"
fi

# Desactivar configuración default si existe
if [ -L /etc/nginx/sites-enabled/default ]; then
    rm /etc/nginx/sites-enabled/default
    print_info "Configuración default deshabilitada"
fi

# Probar sintaxis nginx
print_info "Probando sintaxis de nginx..."
if nginx -t; then
    print_info "✓ Sintaxis correcta"
else
    print_error "Sintaxis incorrecta. Revisa el archivo de configuración"
    exit 1
fi

# Recargar nginx
print_info "Recargando nginx..."
systemctl reload nginx
print_info "✓ Nginx recargado"

# Configurar permisos
print_info "Configurando permisos de carpetas..."
chown -R www-data:www-data "$PROJECT_PATH/storage"
chmod -R 775 "$PROJECT_PATH/storage"
chown -R www-data:www-data "$PROJECT_PATH/bootstrap/cache"
chmod -R 775 "$PROJECT_PATH/bootstrap/cache"
chown -R www-data:www-data "$PROJECT_PATH/public"
chmod -R 755 "$PROJECT_PATH/public"
print_info "✓ Permisos configurados"

# Resumen
echo ""
echo "=========================================="
echo -e "${GREEN}✓ Configuración completada!${NC}"
echo "=========================================="
echo ""
echo "📝 Próximos pasos:"
echo ""
echo "1. Configurar SSL (si aún no lo tienes):"
echo "   sudo certbot certonly --nginx -d $DOMAIN -d www.$DOMAIN"
echo ""
echo "2. Actualizar .env en el servidor:"
echo "   APP_URL=https://$DOMAIN"
echo "   APP_URL_CLIENTES=https://$FRONTEND_DOMAINS"
echo "   FRONTEND_URL=https://$FRONTEND_DOMAINS"
echo ""
echo "3. Ejecutar migraciones:"
echo "   cd $PROJECT_PATH && php artisan migrate --force"
echo ""
echo "4. Optimizar configuración:"
echo "   cd $PROJECT_PATH && php artisan config:cache"
echo "   cd $PROJECT_PATH && php artisan route:cache"
echo ""
echo "5. Revisar logs:"
echo "   tail -f /var/log/nginx/probusiness-error.log"
echo "   tail -f $PROJECT_PATH/storage/logs/laravel.log"
echo ""
echo "=========================================="
