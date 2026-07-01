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
   │  proxy_pass → 127.0.0.1:8080 (QA)  o  :8081 (PROD)
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
APP_PORT=8080
COMPOSE_PROJECT_NAME=intranet_qa

DB_HOST=host.docker.internal   # MySQL en el mismo host, fuera de Docker
# o DB_HOST=mi-rds.amazonaws.com

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
| QA | `intranetback-qa.probusiness.pe` | `8080` | `6002` |
| PROD | `intranetback.probusiness.pe` | `8081` | `6001` |

### Setup PROD (segundo clone)

Misma estructura en `/var/www/html/intranet_back`, rama `main`, `APP_PORT=8081`, `COMPOSE_PROJECT_NAME=intranet_prod`, credenciales de prod.

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

| Workflow          | Cuándo              | Qué hace                    |
|-------------------|---------------------|-----------------------------|
| `ci.yml`          | PR/push `qa`/`main` | Tests + `route:list`        |
| `deploy-qa.yml`   | Push a `qa`         | SSH → `deploy.sh` (docker)  |
| `deploy-prod.yml` | Manual              | SSH → prod (escribir deploy)|

### Secrets

| Secret             | Ejemplo                            |
|----------------------|------------------------------------|
| `DEPLOY_HOST`        | IP del servidor                    |
| `DEPLOY_USER`        | usuario SSH                        |
| `DEPLOY_SSH_KEY`     | clave privada                      |
| `QA_DEPLOY_PATH`     | `/var/www/html/intranet_back_qa`   |
| `PROD_DEPLOY_PATH`   | `/var/www/html/intranet_back`      |

Por defecto el deploy usa **Docker** (`DEPLOY_MODE=docker`). Solo si algún día vuelves a PHP en el host: variable `QA_DEPLOY_MODE=classic`.

### Flujo diario

```text
feature → PR → qa → merge → deploy auto (docker build + up + migrate)
validar QA → PR qa → main → Deploy PROD manual
```

---

## Fase 4 — Sin BD por dominio (hecho)

Cada clone tiene su `.env`. No hay middleware que cambie la BD según el dominio del front.

---

## Fase 5 — Migración Laravel (por ramas, en QA primero)

PHP sube **dentro del Dockerfile** al migrar versiones (8.2 hoy → 8.3 cuando vayas a Laravel 13). No tocas el host.

```text
upgrade/laravel-9  → qa (docker) → validar → main
upgrade/laravel-10 → qa → validar → main
...
Laravel 13         → cambiar base image a php:8.3-fpm en Dockerfile
```

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
