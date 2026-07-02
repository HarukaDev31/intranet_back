# CI/CD — GitHub Actions

Pipeline para **QA (Docker)** y **PROD (host, manual)**.

## Workflows

| Archivo | Disparador | Qué hace |
|---------|------------|----------|
| `ci.yml` | Push/PR en `qa`, `main`, `upgrade/**` | Composer, smoke (`about`, `route:list`), PHPUnit Unit (+ Feature sin bloquear) |
| `deploy-qa.yml` | Push a `qa` o manual | CI → SSH → `scripts/deploy.sh` en el servidor QA |
| `deploy-prod.yml` | Manual (`deploy`) | SSH → PROD (classic por defecto en host) |

## Configuración en GitHub (una sola vez)

Repositorio → **Settings** → **Secrets and variables** → **Actions**.

### Secrets (obligatorios para deploy)

| Secret | Valor ejemplo | Notas |
|--------|---------------|-------|
| `DEPLOY_HOST` | `172.31.2.196` o IP pública | Mismo servidor QA/PROD si comparten EC2 |
| `DEPLOY_USER` | `root` o `ubuntu` | Usuario SSH |
| `DEPLOY_SSH_KEY` | Contenido de la clave **privada** PEM | La pública debe estar en `~/.ssh/authorized_keys` del servidor |
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

## Clave SSH para GitHub Actions

En tu PC o en el servidor:

```bash
ssh-keygen -t ed25519 -C "github-actions-intranet" -f github_actions_intranet -N ""
```

- `github_actions_intranet.pub` → pegar en el servidor: `~/.ssh/authorized_keys`
- `github_actions_intranet` (privada) → secret `DEPLOY_SSH_KEY` en GitHub

Probar desde tu máquina:

```bash
ssh -i github_actions_intranet DEPLOY_USER@DEPLOY_HOST
```

## Flujo QA (automático)

```text
push/merge a qa  →  CI (tests)  →  deploy-qa (SSH + deploy.sh)
```

El servidor ejecuta:

```bash
git fetch origin qa && git reset --hard origin/qa
docker compose build && up -d
composer install (dentro del contenedor)
migrate, config:cache, restart horizon/scheduler/websockets
```

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
| Feature tests fallan | No bloquean deploy (`continue-on-error`); arreglar gradualmente |
| SSH permission denied | Revisar `DEPLOY_SSH_KEY` y `authorized_keys` |
| `vendor/` corrupto en servidor | Deploy con `composer_clean=true` |
