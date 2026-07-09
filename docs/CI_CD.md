# CI/CD — GitHub Actions

Pipeline para **QA (Docker)** y **PROD (Docker, manual confirmado)**.

## Workflows

| Archivo | Disparador | Qué hace |
|---------|------------|----------|
| `ci.yml` | PR a `qa`/`main`, push `main`/`upgrade/**` | Composer, smoke, PHPUnit Unit (+ Feature sin bloquear) |
| `deploy-qa.yml` | Push a `qa` o manual | CI reutilizado → SSH → `deploy.sh` |
| `deploy-prod.yml` | Push a `main` o manual (`deploy`) | CI reutilizado → SSH → PROD (docker) |
| `deploy-tag.yml` | Llamado por deploy QA/PROD | Tag anotado en GitHub tras deploy exitoso |

### Tags automáticos por deploy

Tras un deploy exitoso, GitHub Actions crea y publica un tag anotado en el commit del workflow (`github.sha`):

```
deploy/qa/20260709-021506-a1b2c3d
deploy/prod/20260709-021506-a1b2c3d
```

Formato: `deploy/{entorno}/{YYYYMMDD-HHMMSS}-{sha7}`

- Visible en **Releases / Tags** del repositorio.
- Útil para saber qué commit estaba en servidor en un momento dado (`git checkout deploy/prod/...`).
- En deploy manual con otra `git_branch`, el tag sigue apuntando al SHA del workflow, no necesariamente al commit que el servidor haya hecho `git pull` (salvo que coincida).

Requiere permiso `contents: write` del workflow (ya configurado en el job `tag`).

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
| `PROD_DEPLOY_MODE` | `docker` | `docker` o `classic` (rollback) |
| `PROD_COMPOSER_CLEAN` | `false` | Pon `true` durante upgrades Laravel en prod |

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
push a qa   →  Deploy QA (CI smoke + deploy + tag)
push main   →  Deploy PROD (CI smoke + deploy + tag)
PR a qa/main →  solo CI (validación antes del merge)
```

El servidor ejecuta `scripts/deploy.sh` (optimizado: build/composer solo si cambió algo relevante).

**Primer deploy** (sin `.deploy/initialized` o stack caído): build si aplica → `up -d` → composer si aplica → `clear:all` → migrate → reinicia horizon + scheduler + websockets.

**Deploy rutinario** (código PHP/config): `git pull` → `up -d` (sin recrear contenedores si no cambió la imagen) → composer solo si cambió lock → **`clear:all`** → **migrate** → solo **`horizon:terminate`** (scheduler y websockets siguen corriendo).

Reinicio completo de workers solo si cambió Docker/compose o hubo docker build en ese deploy.

### Tiempos esperados

| Tipo de deploy | Antes | Ahora (aprox.) |
|----------------|-------|----------------|
| Solo PHP/código | ~6 min | **~1–2 min** (sin restart de scheduler/ws) |
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

## Flujo PROD (push a `main` o manual)

PROD usa **Docker** (mismo `deploy.sh` que QA). Guía de migración: **[PROD_DOCKER_SETUP.md](PROD_DOCKER_SETUP.md)**.

**Automático:** cada `push` a `main` ejecuta CI smoke + deploy (igual que QA con `qa`).

**Manual:** **Actions → Deploy PROD → Run workflow** → escribir `deploy` (útil para re-deploy sin commit o otra rama).

1. Validar en QA
2. Merge a `main` → deploy automático (o manual con confirmación)
3. Opcional: `composer_clean=true` en upgrades Laravel (variable `PROD_COMPOSER_CLEAN` o checkbox manual)

## Upgrade QA → Laravel 13

Estrategia: subir versiones en rama `qa` (o `upgrade/laravel-*` + deploy manual) **sin tocar PROD** hasta PHP 8.3+ en prod.

| Laravel | PHP (Dockerfile) | Notas |
|---------|------------------|-------|
| 9–12 | `php:8.2-fpm` | L9–L11 en QA |
| **13** | **`php:8.3-fpm`** | **Actual en QA (Jul 2026)** |
| 11+ | Reverb | Reemplazar `beyondcode/laravel-websockets` |

Durante upgrades en QA (L9→L10, etc.):

- GitHub variable **`QA_COMPOSER_CLEAN=true`** o deploy manual con checkbox *composer_clean*
- Tras el deploy exitoso, volver a `false`

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
