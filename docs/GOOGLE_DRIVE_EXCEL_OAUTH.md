# Google Drive — Excel confirmación (OAuth)

Para cuentas **Gmail personal** (`@gmail.com`), la service account no puede subir archivos por API. Use **OAuth de usuario** con la cuenta dueña de la carpeta **Excel Confirmacion**.

## 1. Google Cloud Console

1. Mismo proyecto que la intranet (o uno nuevo).
2. **APIs y servicios → Biblioteca** → habilitar **Google Drive API**.
3. **Credenciales → Crear credenciales → ID de cliente de OAuth** → tipo **Aplicación web**.
4. **URI de redirección autorizados** (debe coincidir con `.env`):

   ```
   http://localhost:8001/api/google/drive/oauth/callback
   ```

   En producción use la URL real de la API, por ejemplo:

   ```
   https://api.probusiness.pe/api/google/drive/oauth/callback
   ```

5. Copie **Client ID** y **Client secret** al `.env`.

## 2. Variables `.env`

```env
GOOGLE_DRIVE_EXCEL_AUTH_MODE=oauth

GOOGLE_CLIENT_ID=tu-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu-secret

GOOGLE_DRIVE_EXCEL_CONFIRMACION_ROOT_FOLDER_ID=1ruktIZVCjZuX2IcfDvDB2NbRhL0Gya0S

# Opcional; por defecto APP_URL + /api/google/drive/oauth/callback
# GOOGLE_DRIVE_OAUTH_REDIRECT_URI=http://localhost:8001/api/google/drive/oauth/callback
```

No hace falta `GOOGLE_SERVICE_ENABLED=true` para el Excel si usa solo OAuth.

## 3. Autorizar (una vez)

**Opción A — navegador + callback**

```bash
php artisan google:drive-oauth
```

Abra la URL impresa, acepte permisos de Drive. Al terminar, el callback guarda `storage/app/google-drive-oauth-token.json`.

También puede abrir directamente:

```
GET http://localhost:8001/api/google/drive/oauth/authorize
```

**Opción B — código manual**

```bash
php artisan google:drive-oauth --code="4/0A..."
```

**Opción C — solo refresh token en servidor**

```env
GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN=1//0g...
```

(Útil en producción sin escribir archivo.)

## 4. Probar

```bash
php artisan config:clear
# Reiniciar queue:work / Horizon
```

Dispare **Solicitar documentos**. En `laravel.log` debe aparecer:

`SolicitarDocumentosWhatsAppJob: Excel subido a Drive` con `drive_link` `https://drive.google.com/file/d/...`

## 5. Seguridad

- El archivo `storage/app/google-drive-oauth-token.json` contiene secretos: **no commitear** (está en `.gitignore`).
- Las rutas `/api/google/drive/oauth/*` son solo para configuración inicial; en producción limite el acceso o revoque el client OAuth tras el primer uso si lo prefiere.

## Modo service account (Workspace)

Si más adelante usan **Unidad compartida** en Google Workspace:

```env
GOOGLE_DRIVE_EXCEL_AUTH_MODE=service_account
GOOGLE_SERVICE_ENABLED=true
GOOGLE_DRIVE_EXCEL_CONFIRMACION_SHARED_DRIVE_ID=...
```
