# Deploy, Docker y migración por fases

Guía para **QA** y **PROD** con dos clones en servidor, todo dockerizado (PHP 8.2 solo dentro del contenedor).

En el servidor solo necesitas: **Docker**, **Docker Compose**, **Git** y **Nginx** (reverse proxy al puerto del contenedor). No instales PHP, Composer ni extensiones en el host.

---

## Arquitectura en servidor

```text
Internet
   │
   ▼
Nginx (host)  :443 / :80
   │  proxy_pass → 127.0.0.1:8085 (QA)  o  :8081 (PROD)
   ▼
docker compose (intranet_back_qa / intranet_back)
   ├── nginx (contenedor)
   ├── app (PHP 8.2-FPM)
   ├── redis
   ├── horizon
   └── scheduler

MySQL → externo (RDS, servidor DB, o host.docker.internal si MySQL está en el mismo host)
```

---

## Fase 1 — Docker local

```bash
cp .env.docker.example .env
bash scripts/docker-init.sh
```

Usa `docker-compose.yml` + `docker-compose.local.yml` (incluye MySQL en contenedor).

| Servicio    | Rol              | Puerto local |
|------------|------------------|--------------|
| `nginx`    | API HTTP         | `8080`       |
| `app`      | PHP 8.2 FPM      | interno      |
| `mysql`    | Solo en local    | `3306`       |
| `redis`    | Colas + cache    | `6379`       |
| `horizon`  | Worker           | —            |
| `scheduler`| Cron Laravel     | —            |

---

## Fase 2 — Servidor dockerizado (QA + PROD)

### Requisitos en el servidor (una vez)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y docker.io docker-compose-plugin git nginx
sudo usermod -aG docker $USER
# cerrar sesión y volver a entrar
```

### Setup QA

```bash
sudo mkdir -p /var/www/html/intranet_back_qa
sudo chown -R $USER:$USER /var/www/html/intranet_back_qa
git clone -b qa git@github.com:HarukaDev31/intranet_back.git /var/www/html/intranet_back_qa
cd /var/www/html/intranet_back_qa

cp .env.example .env
```

En `.env` de QA:

```env
APP_ENV=qa
APP_PORT=8085
COMPOSE_PROJECT_NAME=intranet_qa

# MySQL en el mismo host (bind-address=127.0.0.1) — socket Unix, sin abrir 3306 a internet:
DB_HOST=localhost
DB_SOCKET=/var/run/mysqld/mysqld.sock
# deploy.sh usa COMPOSE_HOST_MYSQL=true por defecto (docker-compose.host-mysql.yml)
# RDS u otro host TCP:
# DB_HOST=mi-rds.amazonaws.com

DB_DATABASE=intranet_qa
REDIS_HOST=redis
REDIS_PREFIX=qa_
HORIZON_PREFIX=qa_horizon:
QUEUE_CONNECTION=redis
```

```bash
chmod +x scripts/deploy.sh
DEPLOY_PATH=/var/www/html/intranet_back_qa GIT_BRANCH=qa bash scripts/deploy.sh
```

Nginx del host → ver `docker/nginx/host-reverse-proxy.probusiness.example.conf` (PROD) y `host-reverse-proxy.probusiness-qa.example.conf` (QA).

Resumen puertos en el mismo servidor:

| Ambiente | Dominio | `APP_PORT` | WebSockets (host) |
|----------|---------|------------|-------------------|
| QA | `qa.intranetback.probusiness.pe` | `8085` | `6002` |
| PROD | `intranetback.probusiness.pe` | `8081` | `6001` |

### Setup PROD (segundo clone)

Misma estructura en `/var/www/html/intranet_back`, rama `main`, `APP_PORT=8081`, `COMPOSE_PROJECT_NAME=intranet_prod`, credenciales de prod.

### MySQL en el host (bind-address=127.0.0.1) + Docker

Mantén `bind-address = 127.0.0.1` en `mysqld.cnf`. La IP elástica de EC2 **no importa**: MySQL no escucha en interfaces públicas y el Security Group no debe abrir el puerto 3306 desde internet.

**Problema:** desde un contenedor, `DB_HOST=127.0.0.1` apunta al loopback del contenedor, no al del host. `host.docker.internal` llega por la red Docker (`docker0`, p. ej. `172.17.0.1`), pero con `bind-address=127.0.0.1` MySQL rechaza esas conexiones TCP.

**Solución recomendada — socket Unix montado** (sin cambiar bind-address):

1. En `.env` del servidor:

```env
DB_HOST=localhost
DB_SOCKET=/var/run/mysqld/mysqld.sock
```

2. Levantar con el overlay (o `deploy.sh`, que usa `COMPOSE_HOST_MYSQL=true` por defecto):

```bash
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml up -d
```

3. Usuario MySQL para socket (autentica como `localhost`):

```sql
CREATE USER IF NOT EXISTS 'tu_usuario'@'localhost' IDENTIFIED BY 'tu_password';
GRANT ALL PRIVILEGES ON intranet_probusiness2.* TO 'tu_usuario'@'localhost';
FLUSH PRIVILEGES;
```

4. Verifica la ruta del socket en el host:

```bash
mysql -e "SHOW VARIABLES LIKE 'socket';"
```

Si no es `/var/run/mysqld/mysqld.sock`, define `MYSQL_SOCKET_HOST` en `.env` con la ruta real.

**Alternativa (solo si no puedes usar socket):** enlazar MySQL solo a la IP de `docker0` (`172.17.0.1`), no a `0.0.0.0`. Sigue sin exponer la IP elástica, pero el cliente local debe usar socket o `mysql -h 172.17.0.1`. Menos limpio que el socket.

**No hagas:** `bind-address=0.0.0.0` “porque el SG protege” — cualquier error de firewall abre la BD a internet.

#### Si ves `Connection timed out`

Eso es **TCP** a un host incorrecto (p. ej. `host.docker.internal` o IP privada), no el socket. Revisa en orden:

```bash
cd /var/www/html/intranet_back_qa
grep -E '^DB_|^DATABASE_URL=' .env
```

Debe quedar así (sin `DATABASE_URL` apuntando a otro host):

```env
DB_HOST=localhost
DB_SOCKET=/var/run/mysqld/mysqld.sock
```

Recrear contenedores **con** el overlay (solo `exec` no monta el socket):

```bash
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml up -d --force-recreate app horizon scheduler
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app ls -la /var/run/mysqld/mysqld.sock
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan migrate --force
```

Diagnóstico automático: `bash scripts/docker-db-check.sh`

#### Si `curl` devuelve HTTP 500

Nginx y PHP responden; el error es de Laravel. En el servidor:

```bash
cd /var/www/html/intranet_back_qa
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan route:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan config:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app php artisan view:clear
docker compose -f docker-compose.yml -f docker-compose.host-mysql.yml exec app tail -50 storage/logs/laravel.log
curl -I http://127.0.0.1:8085
```

Causas frecuentes:

| Causa | Solución |
|-------|----------|
| `route:cache` con closures en `web.php` | `route:clear` (deploy ya no hace `route:cache`) |
| `config:cache` con `.env` viejo | `config:clear` y luego `config:cache` |
| `APP_KEY` vacía | `php artisan key:generate` |
| Permisos `storage/` | `chown -R www-data storage bootstrap/cache` |

Para ver el error en pantalla (solo QA): `APP_DEBUG=true` temporal + `config:clear`.

---

## Horizon y Scheduler: de Supervisor (host) a Docker

### Cómo funciona hoy (host)

En el servidor suele haber algo así en Supervisor:

```ini
[program:horizon]
command=php /var/www/html/intranet_back/artisan horizon
autostart=true
autorestart=true

[program:laravel-scheduler]
command=php /var/www/html/intranet_back/artisan schedule:run
# o un cron * * * * * schedule:run
```

Supervisor del **host** levanta PHP del host y ejecuta Horizon + el cron.

### Cómo queda con Docker

**Supervisor del host ya no corre Horizon ni el schedule** para esta app. Lo reemplazan dos servicios en `docker-compose.yml`:

| Antes (host) | Ahora (contenedor) | Comando |
|--------------|-------------------|---------|
| Supervisor `horizon` | servicio `horizon` | `php artisan horizon` |
| Cron / Supervisor scheduler | servicio `scheduler` | `schedule:run` cada 60 s en loop |

Docker se encarga del **autostart** con `restart: unless-stopped` (equivalente a `autorestart=true`).

Cada clone (QA / PROD) tiene **su propio** Horizon y scheduler:

```text
intranet_back_qa/     →  contenedores intranet_qa-horizon-1, intranet_qa-scheduler-1
intranet_back/        →  contenedores intranet_prod-horizon-1, intranet_prod-scheduler-1
```

Separa colas/métricas con prefijos distintos en `.env`:

```env
COMPOSE_PROJECT_NAME=intranet_qa
HORIZON_PREFIX=qa_horizon:
REDIS_PREFIX=qa_
```

### Migración (paso a paso)

1. Levantar Docker con `deploy.sh` y verificar que `horizon` y `scheduler` están `Up`:
   ```bash
   docker compose ps
   docker compose logs -f horizon
   ```
2. Confirmar en `/horizon` que los supervisores internos de Horizon (importaciones, emails, notificaciones…) procesan jobs.
3. **Detener** los programas viejos del Supervisor del host (solo para este proyecto):
   ```bash
   sudo supervisorctl stop intranet-horizon intranet-scheduler   # nombres reales en tu servidor
   sudo supervisorctl remove intranet-horizon intranet-scheduler  # o comentar en /etc/supervisor/conf.d/
   ```
4. No dejes **dos** Horizon corriendo (host + Docker) sobre el mismo Redis: consumirían jobs duplicados o pelearían por locks.

### Comandos operativos (equivalencias)

| Supervisor (antes) | Docker (ahora) |
|--------------------|----------------|
| `supervisorctl status` | `docker compose ps` |
| `supervisorctl restart horizon` | `docker compose restart horizon` |
| `supervisorctl restart laravel-scheduler` | `docker compose restart scheduler` |
| `tail -f storage/logs/horizon.log` | `docker compose logs -f horizon` |
| Deploy con `horizon:terminate` | Ya lo hace `scripts/deploy.sh` |

### Redis: contenedor vs host

- **Redis en Docker** (compose actual): `.env` → `REDIS_HOST=redis`
- **Redis que ya tienes en el host** (ej. puerto `6380`): no levantes el servicio `redis` del compose o usa override; en `.env`:
  ```env
  REDIS_HOST=host.docker.internal
  REDIS_PORT=6380
  ```
  Cada ambiente (QA/PROD) debe usar **prefijos distintos** (`REDIS_PREFIX`, `HORIZON_PREFIX`) aunque compartan el mismo Redis.

---

## Fase 3 — CI/CD en GitHub

Guía detallada: **[docs/CI_CD.md](CI_CD.md)**.

| Workflow          | Cuándo              | Qué hace                    |
|-------------------|---------------------|-----------------------------|
| `ci.yml`          | PR/push `qa`/`main`/`upgrade/**` | Unit tests + smoke Laravel |
| `deploy-qa.yml`   | Push a `qa` (+ manual) | CI OK → SSH → `deploy.sh` (docker) |
| `deploy-prod.yml` | Manual              | SSH → prod (classic host)   |

### Secrets (Settings → Actions → Secrets)

| Secret             | Ejemplo                            |
|----------------------|------------------------------------|
| `DEPLOY_HOST`        | IP del servidor EC2                |
| `DEPLOY_USER`        | `root` / `ubuntu`                  |
| `DEPLOY_SSH_KEY`     | clave privada PEM (ed25519)        |
| `QA_DEPLOY_PATH`     | `/var/www/html/intranet_back_qa`   |
| `PROD_DEPLOY_PATH`   | `/var/www/html/intranet_back`      |

Opcional: `DEPLOY_SSH_PORT` (default 22).

### Variables (Settings → Actions → Variables)

| Variable             | QA        | PROD      |
|----------------------|-----------|-----------|
| `QA_DEPLOY_MODE`     | `docker`  | —         |
| `QA_COMPOSER_CLEAN`  | `false`   | —         |
| `PROD_DEPLOY_MODE`   | —         | `classic` |

Pon `QA_COMPOSER_CLEAN=true` durante upgrades Laravel (borra `vendor/` en deploy).

### Flujo diario

```text
feature → PR → qa → merge → CI + deploy auto
validar QA → (PROD sigue L8 hasta PHP 8.3+) → Deploy PROD manual
```

Deploy manual otra rama: Actions → Deploy QA → `git_branch=upgrade/laravel-10`.

---

## Fase 4 — Sin BD por dominio (hecho)

Cada clone tiene su `.env`. No hay middleware que cambie la BD según el dominio del front.

---

## Fase 5 — Migración Laravel (QA directo hasta L13)

**PROD** permanece en L8 + PHP 7.4 hasta terminar la cadena en QA y subir PHP en prod.

PHP sube **dentro del Dockerfile** (8.2 para L9–L12 → **8.3** para L13).

```text
qa (Docker) — upgrades en cadena, sin merge a main hasta el final:
  L9  ✓
  L10 ✓
  L11 ✓ (actual — Reverb)
  L12 → L13 (Dockerfile php:8.3-fpm + CI php 8.3)
```

Cada salto en QA:

1. Merge a `qa` (o deploy manual con `git_branch`)
2. GitHub variable `QA_COMPOSER_CLEAN=true` o deploy manual con checkbox
3. Checklist: Horizon, JWT, S3, Excel, WebSockets/Reverb, WhatsApp webhooks
4. PROD no se toca

### WebSockets: Reverb (L11+)

| Versión | WebSockets |
|---------|------------|
| **L11+** | **[Laravel Reverb](https://laravel.com/docs/reverb)** — contenedor `websockets` ejecuta `reverb:start`. |
| L8–L10 | `beyondcode/laravel-websockets` (retirado en L11). |

En el `.env` del servidor QA, añadir (mismos valores que `PUSHER_*`):

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=websockets
REVERB_PORT=6002
REVERB_SCHEME=http
REVERB_SERVER_PORT=6002
```

El front sigue usando `pusher-js` / Echo con `PUSHER_APP_KEY` (alias de `REVERB_APP_KEY`).

Checklist por salto:

- [ ] `docker compose build` OK
- [ ] Horizon procesa colas
- [ ] JWT, S3, Excel/PDF, webhooks WhatsApp

---

## Script de deploy

```bash
# QA (default docker)
DEPLOY_PATH=/var/www/html/intranet_back_qa GIT_BRANCH=qa bash scripts/deploy.sh

# Sin migraciones
RUN_MIGRATIONS=false DEPLOY_PATH=... GIT_BRANCH=qa bash scripts/deploy.sh

# Legacy (solo si no usas Docker)
DEPLOY_MODE=classic DEPLOY_PATH=... bash scripts/deploy.sh
```

El deploy dockerizado ejecuta: `git pull` → `docker compose build` → `up -d` → `composer install` **dentro del contenedor** → `migrate` → cache → `horizon:terminate`.
