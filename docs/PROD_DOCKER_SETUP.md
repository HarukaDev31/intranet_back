# PROD — Migrar de PHP host a Docker

Guía para dockerizar **producción** en `/var/www/html/intranet_back` (`intranetback.probusiness.pe`), igual que QA.

## Resumen

| | Antes (classic) | Después (docker) |
|---|-----------------|------------------|
| PHP | 7.4/8 FPM en host | 8.3 en contenedor |
| API | Nginx → php-fpm socket | Nginx host → `:8082` → compose |
| Horizon / cron | Supervisor en host | contenedores `horizon` + `scheduler` |
| WebSockets | puerto 6001 host | contenedor `websockets` (Reverb) `:6001` |
| Deploy | manual / classic | GitHub Actions → `deploy-prod.yml` |

## 1. Variables Docker en `.env` (añadir al `.env` existente)

**No borres** credenciales actuales (AWS, JWT, Bitrix, Meta, etc.). Solo agrega/ajusta:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://intranetback.probusiness.pe

APP_PORT=8082
COMPOSE_PROJECT_NAME=intranet_prod

# MySQL en el mismo host (socket Unix — no TCP público)
DB_HOST=localhost
DB_SOCKET=/var/run/mysqld/mysqld.sock

# Redis del contenedor compose (no el Redis del host)
DOCKER_REDIS_HOST=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_CLIENT=phpredis
REDIS_PREFIX=intranet_back_
FORWARD_REDIS_PORT=6381
# Redis Docker no tiene password — no reutilices la de Redis del host
REDIS_PASSWORD=null

# WebSockets / Reverb (puerto 6001 en prod)
LARAVEL_WEBSOCKETS_PORT=6001
PUSHER_PORT=6001
REVERB_PORT=6001
REVERB_HOST=websockets
BROADCAST_CONNECTION=reverb

HORIZON_PREFIX=intranet_horizon:
# Acceso dashboard /horizon en PROD (obligatorio una de las dos):
HORIZON_DASHBOARD_TOKEN=
HORIZON_ALLOWED_IPS=
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=file
```

Verifica la ruta del socket MySQL:

```bash
mysql -e "SHOW VARIABLES LIKE 'socket';"
```

Si difiere, define `MYSQL_SOCKET_HOST=/ruta/real/mysqld.sock` en `.env`.

## 2. Nginx del host

Actualiza el vhost de prod para proxy al contenedor (puerto **8082**). **No** configures CORS en Nginx host: Laravel ya lo envía (`HandleCors` + `config/cors.php`); duplicarlo rompe el front con `multiple values` en `Access-Control-Allow-Origin`.

```bash
cd /var/www/html/intranet_back
sudo cp docker/nginx/host-reverse-proxy.probusiness.example.conf \
  /etc/nginx/conf.d/intranetback.probusiness.pe.conf
sudo nginx -t && sudo systemctl reload nginx
```

Comprueba CORS (un solo header, desde Laravel):

```bash
curl -I -X OPTIONS "https://intranetback.probusiness.pe/api/calendar/my-role-groups" \
  -H "Origin: https://intranetv2.probusiness.pe" \
  -H "Access-Control-Request-Method: GET"
# Debe haber UNA sola línea Access-Control-Allow-Origin
```

Limpia config cache de Laravel en Docker:

```bash
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:cache
```

## 3. Detener Supervisor del host (solo este proyecto)

**No dejes dos Horizon** (host + Docker) sobre el mismo Redis:

```bash
sudo supervisorctl status
sudo supervisorctl stop intranet-horizon intranet-scheduler   # nombres reales
# Comenta o elimina los programas en /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
```

## 4. Primer deploy Docker en el servidor

```bash
cd /var/www/html/intranet_back
git fetch origin main && git reset --hard origin/main
chmod +x scripts/deploy.sh

# Primera vez (build + composer + migrate)
DEPLOY_PATH=/var/www/html/intranet_back GIT_BRANCH=main DEPLOY_MODE=docker \
  DOCKER_REBUILD=true bash scripts/deploy.sh
```

Comprobar:

```bash
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml ps
curl -I http://127.0.0.1:8082
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan migrate:status
docker compose logs -f horizon
```

## 5. GitHub Actions (deploy automático manual)

En **Settings → Actions → Variables**:

| Variable | Valor |
|----------|-------|
| `PROD_DEPLOY_MODE` | `docker` |
| `PROD_COMPOSER_CLEAN` | `false` (pon `true` solo en upgrades Laravel) |

Secrets (mismos que QA):

- `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`
- `PROD_DEPLOY_PATH` = `/var/www/html/intranet_back`

Deploy: **Actions → Deploy PROD → Run workflow** → escribir `deploy`.

## Acceso a `/horizon` en PROD

En `APP_ENV=production` el dashboard **no es público** (403 Forbidden sin configurar). Opciones en `.env`:

**A) Token en URL** (recomendado):

```env
HORIZON_DASHBOARD_TOKEN=tu-token-largo-aleatorio
```

```bash
openssl rand -hex 32
```

Abrir: `https://intranetback.probusiness.pe/horizon?token=tu-token-largo-aleatorio`

**B) IPs permitidas** (oficina/VPN):

```env
HORIZON_ALLOWED_IPS=203.0.113.10,198.51.100.5
```

Tras cambiar `.env`:

```bash
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec -u www-data app php artisan config:cache
```

## 6. Rollback rápido (si algo falla)

```bash
# Volver a modo classic temporal
DEPLOY_MODE=classic RUN_MIGRATIONS=false bash scripts/deploy.sh
sudo supervisorctl start intranet-horizon intranet-scheduler
# Restaurar nginx antiguo (php-fpm) desde backup del vhost
```

## Checklist post-migración

- [ ] `https://intranetback.probusiness.pe` responde 200
- [ ] Login JWT funciona
- [ ] Horizon procesa jobs (`/horizon` — ver acceso abajo)
- [ ] Uploads S3 / CDN
- [ ] Webhooks WhatsApp / Bitrix
- [ ] WebSockets (Reverb en `:6001`)

## Notas

- **QA y PROD** son clones distintos: `/var/www/html/intranet_back_qa` (8085) y `/var/www/html/intranet_back` (8082).
- El workflow `deploy-prod.yml` usa `DEPLOY_MODE=docker` por defecto (variable `PROD_DEPLOY_MODE`).
- Durante upgrades Laravel en prod: deploy manual con **composer_clean=true**.
