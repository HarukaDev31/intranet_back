# CI/CD — GitHub Actions

Pipeline para **QA (Docker)** y **PROD (host, manual)**.

## Workflows

| Archivo | Disparador | Qué hace |
|---------|------------|----------|
| `ci.yml` | PR a `qa`/`main`, push `main`/`upgrade/**` | Composer, smoke, PHPUnit Unit (+ Feature sin bloquear) |
| `deploy-qa.yml` | Push a `qa` o manual | CI reutilizado → SSH → `deploy.sh` |
| `deploy-prod.yml` | Manual (`deploy`) | SSH → PROD (classic por defecto en host) |

## Configuración en GitHub (una sola vez)

Repositorio → **Settings** → **Secrets and variables** → **Actions**.

### Secrets (obligatorios para deploy)

| Secret | Valor ejemplo | Notas |
|--------|---------------|-------|
| `DEPLOY_HOST` | `3.18.243.245` | IP pública del EC2 |
| `DEPLOY_USER` | **`ubuntu`** | Usuario SSH (no root) |
| `DEPLOY_SSH_KEY` | Contenido de tu **.pem** (clave privada) | La misma que usas en `ssh -i clave.pem ubuntu@IP` |
| `QA_DEPLOY_PATH` | `/var/www/html/intranet_back_qa` | Ruta del clone QA |
| `PROD_DEPLOY_PATH` | `/var/www/html/intranet_back` | Ruta del clone PROD |

Opcional:

| Secret | Default |
|--------|---------|
| `DEPLOY_SSH_PORT` | `22` |

### Variables (opcionales)

| Variable | Valor recomendado QA | Descripción |
|----------|----------------------|-------------|
| `QA_DEPLOY_MODE` | `docker` | `docker` o `classic` |
| `QA_COMPOSER_CLEAN` | `false` | Pon `true` durante upgrades Laravel (borra `vendor/`) |
| `PROD_DEPLOY_MODE` | `classic` | PROD sigue en PHP host + Supervisor |

### Environment `qa`

En **Settings → Environments → qa** puedes activar *Required reviewers* para aprobar deploys manuales (opcional).

## EC2: ubuntu + clave SSH + sudo

Flujo manual: `ssh -i tu.pem ubuntu@IP` → `sudo su` → root.

GitHub Actions:

1. SSH como **`ubuntu`** con **`DEPLOY_SSH_KEY`** (tu .pem)
2. `deploy.sh` se **re-ejecuta con `sudo`** (sin password interactivo)

### Paso A — sudo sin password para deploy (una vez en el servidor)

```bash
sudo visudo -f /etc/sudoers.d/intranet-deploy
```

```text
ubuntu ALL=(ALL) NOPASSWD: /var/www/html/intranet_back_qa/scripts/deploy.sh
ubuntu ALL=(ALL) NOPASSWD: /var/www/html/intranet_back/scripts/deploy.sh
```

Prueba:

```bash
sudo -n /var/www/html/intranet_back_qa/scripts/deploy.sh
```

### Paso B — Secret `DEPLOY_SSH_KEY` en GitHub

Usa la **misma clave privada** con la que entras hoy:

```cmd
notepad C:\ruta\a\tu-clave.pem
```

Copia **todo** el archivo (desde `-----BEGIN` hasta `-----END`) → GitHub → Secret **`DEPLOY_SSH_KEY`**.

| Secret | Valor |
|--------|--------|
| `DEPLOY_USER` | `ubuntu` |
| `DEPLOY_SSH_KEY` | contenido completo del .pem |
| `DEPLOY_HOST` | `3.18.243.245` |
| `QA_DEPLOY_PATH` | `/var/www/html/intranet_back_qa` |

### Paso C — Probar desde CMD

```cmd
ssh -i C:\ruta\a\tu-clave.pem ubuntu@3.18.243.245
```

Si entras sin password, GitHub también podrá con el mismo contenido en `DEPLOY_SSH_KEY`.

### Clave dedicada solo para GitHub (opcional)

Si no quieres poner tu .pem personal en GitHub, genera otra y añade la `.pub` a `~/.ssh/authorized_keys` de ubuntu:

```cmd
ssh-keygen -t ed25519 -f %USERPROFILE%\github_actions_intranet -N ""
type %USERPROFILE%\github_actions_intranet.pub
```

En el servidor (como ubuntu), pega la línea en `~/.ssh/authorized_keys`. En GitHub usa la **privada** en `DEPLOY_SSH_KEY`.

## Troubleshooting SSH

```text
push a qa  →  solo Deploy QA (incluye CI + deploy)
PR a qa    →  solo CI (validación antes del merge)
push main  →  CI
```

El servidor ejecuta `scripts/deploy.sh` (optimizado: build/composer/migrate solo si cambió algo relevante).

```bash
git fetch origin qa && git reset --hard origin/qa
# docker build     → solo si cambió Dockerfile/compose/docker/*
# composer install → solo si cambió composer.lock
# migrate          → solo si hay migraciones nuevas
docker compose up -d
config:cache + restart workers (si cambió app/config/routes)
```

### Tiempos esperados

| Tipo de deploy | Antes | Ahora (aprox.) |
|----------------|-------|----------------|
| Solo PHP/código | ~6 min | **~2–3 min** |
| composer.lock cambió | ~6 min | ~4 min |
| Dockerfile cambió | ~6 min | ~5 min (sin `--pull` por defecto) |

Variables opcionales en deploy manual / servidor:

| Variable | Default | Uso |
|----------|---------|-----|
| `DOCKER_REBUILD` | `auto` | `true` fuerza build |
| `DOCKER_BUILD_PULL` | `false` | `true` = `build --pull` (1x/semana) |
| `COMPOSER_INSTALL` | `auto` | `true` fuerza composer install |

CI en push a `qa`: **smoke only** (sin PHPUnit). Tests completos en **PR a qa**.

## Deploy manual (GitHub UI)

**Actions → Deploy QA → Run workflow**

| Input | Cuándo usarlo |
|-------|----------------|
| `git_branch` | Desplegar otra rama (ej. `upgrade/laravel-10`) sin mergear a `qa` |
| `composer_clean` | `true` en saltos de versión Laravel (L9→L10, etc.) |
| `run_migrations` | `false` si solo cambias config/código sin migraciones |

## Flujo PROD (manual)

PROD usa PHP 7.4/8 en **host** (sin Docker por ahora):

1. Merge validado en `main`
2. **Actions → Deploy PROD → Run workflow**
3. Escribir `deploy` en el campo de confirmación

## Upgrade QA → Laravel 13

Estrategia: subir versiones en rama `qa` (o `upgrade/laravel-*` + deploy manual) **sin tocar PROD** hasta PHP 8.3+ en prod.

| Laravel | PHP (Dockerfile) | Notas |
|---------|------------------|-------|
| 9–12 | `php:8.2-fpm` | Rama actual |
| 13 | `php:8.3-fpm` | Cambiar `Dockerfile` + CI `php-version: 8.3` |
| 11+ | Reverb | Reemplazar `beyondcode/laravel-websockets` |

Durante upgrades en QA:

1. Merge cambios a `qa` (o deploy manual con `git_branch`)
2. Variable `QA_COMPOSER_CLEAN=true` **o** checkbox en deploy manual
3. Validar Horizon, JWT, WebSockets, S3
4. Repetir salto (10 → 11 → 12 → 13)

## Troubleshooting CI

| Error | Solución |
|-------|----------|
| `route:list --columns` / `--compact` | L9 usa `route:list --except-vendor --json` |
| Feature tests fallan | No bloquean deploy; tests `@group integration` / `requires-db` se excluyen en CI |
| Unit tests MySQL | Tests con BD real van con `@group requires-db`; correr en local: `phpunit --group requires-db` |
| SSH permission denied | `DEPLOY_USER=ubuntu`, .pem completo en `DEPLOY_SSH_KEY`, probar `ssh -i clave.pem ubuntu@IP` |
| `ssh: no key found` | `DEPLOY_SSH_KEY` vacío o mal pegado; debe incluir `BEGIN`/`END` |
| sudo pide password en CI | Configurar `/etc/sudoers.d/intranet-deploy` (NOPASSWD) |
| `vendor/` corrupto en servidor | Deploy con `composer_clean=true` |
