# Migración de archivos a S3

Nuevas subidas usan el conector **`S3ObjectStorageConnector`** (`App\Contracts\ObjectStorageConnectorInterface`).  
Los archivos **ya guardados en disco local** siguen leyéndose (discos legacy `local` / `public`).

**Regla:** en base de datos solo **rutas relativas** (p. ej. `cotizacion_final/12/archivo.xlsx`). Las URLs absolutas se generan al responder API con `FileTrait::generateImageUrl()` o `objectStorage()->url()`.

## Configuración `.env`

```env
FILESYSTEM_UPLOAD_DISK=s3
FILESYSTEM_LEGACY_DISK=local
FILESYSTEM_LEGACY_PUBLIC_DISK=public

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket
AWS_UPLOAD_PREFIX=probusiness/production
AWS_URL=
AWS_SIGNED_URL_MINUTES=120
AWS_SERVE_VIA_SIGNED_REDIRECT=true
```

En local, hasta tener bucket: `FILESYSTEM_UPLOAD_DISK=local`.

## Uso en código

```php
use App\Traits\UsesObjectStorage;

class MiController extends Controller
{
    use UsesObjectStorage;

    public function subir(Request $request)
    {
        $path = $this->storageStoreUpload(
            $request->file('file'),
            'cargaconsolidada/comprobantes/' . $id,
            'documento.pdf'
        );
        // Guardar $path en BD (ruta relativa)
    }
}
```

`FileTrait::generateImageUrl()` ya usa el conector. Para WhatsApp/Gemini/PhpSpreadsheet: `$this->storageLocalPath($path)`.

## Estado de migración

| Estado | Módulo / archivos |
|--------|-------------------|
| ✅ | Conector S3, `UsesObjectStorage`, `FileTrait`, provider, config |
| ✅ | `FacturaGuiaController`, contabilidad jobs |
| ✅ | `EntregaController` (conformidad, cargo entrega, vouchers, WhatsApp) |
| ✅ | `CotizacionFinalController`, `DocumentacionController`, `CotizacionController` |
| ✅ | `CotizacionProveedorController` (inspección, documentación, contratos firmados) |
| ✅ | Calculadora importación (servicios + controladores documentos) |
| ✅ | `ContenedorController`, `EmbarcadosController`, `AduanaController` |
| ✅ | Regulaciones, `ProductosController`, import jobs/servicios |
| ✅ | `FileController`, `AuthController`, `UserProfileController` |
| ✅ | Viáticos, rotulado, solicitar documentos, pagos, cursos, boletín químico |
| ✅ | `MenuCatalogoController`, Soporte TI (jobs + API con `generateImageUrl`) |
| ✅ | `AutoSignContracts` (ruta relativa en BD) |
| — | Plantillas estáticas en `public/assets/` (no se suben a S3) |
| — | Temporales de proceso en `storage/app/temp` o `sys_get_temp_dir()` |
| Comando | `php artisan storage:migrate-local-to-s3` (ver abajo) |

## Orígenes legacy (antes de S3)

Las rutas en **BD** son relativas (ej. `cargaconsolidada/pagos/voucher.pdf`). Antes se guardaban en:

| Disco Laravel | Ruta física | URL pública (si aplicaba) |
|---------------|-------------|---------------------------|
| `local` (`FILESYSTEM_LEGACY_DISK`) | `storage/app/{ruta_bd}` | vía `APP_URL/storage/...` solo si el archivo estaba bajo `public/` |
| `public` (`FILESYSTEM_LEGACY_PUBLIC_DISK`) | `storage/app/public/{ruta_bd}` | `{APP_URL}/storage/{ruta_bd}` (symlink `public/storage`) |

El conector (`S3ObjectStorageConnector`) busca en **S3 → local → public** con la **misma ruta relativa**.

Carpetas habituales bajo `storage/app/public/` (y a veces también bajo `storage/app/`):

`cargaconsolidada`, `cotizacion_final`, `cotizaciones_finales`, `documentation`, `inspection`, `delivery_conformidad`, `delivery_cargo_firmado`, `entregas`, `profiles`, `productos`, `regulaciones`, `tramites`, `viaticos`, `viaticos_pagos`, `vouchers`, `imports`, `soporte-ti`, `assets/images/agentecompra`, `contratos`, `Cursos`, etc.

Plantillas de documentación (CONSIDERATIONS, Excel confirmación) suelen quedar en S3 como:

Bucket `probusiness-intranet`, claves en raíz del bucket:

`templates/CONSIDERATIONS.pdf`  
`templates/excel-confirmacion/EXCEL_DE_CONFIRMACION_GENERAL.xlsx`

```bash
php artisan storage:migrate-local-to-s3 --source=public --subdir=templates
```

`SolicitarDocumentosWhatsAppJob` lee plantillas solo desde S3 en `templates/` del bucket configurado en `AWS_BUCKET` (hardcodeado).

**No migrar** (por defecto el comando los excluye):

- `storage/app/temp/**`, `storage/app/public/temp/**`, `temp/whatsapp-meta/**`
- PDFs/ZIP sueltos de rotulado en raíz de `storage/app/` (`Rotulado*.pdf`, `Rotulado.zip`)
- `public/assets/` del proyecto (plantillas estáticas del repo, no uploads)

## Comando: copiar legacy → S3

Requisitos: `FILESYSTEM_UPLOAD_DISK=s3`, credenciales `AWS_*` y `AWS_BUCKET` en `.env`.

```bash
# Simular (recomendado primero)
php artisan storage:migrate-local-to-s3 --dry-run

# Subir todo lo legacy (public + local), omitiendo lo que ya existe en S3
php artisan storage:migrate-local-to-s3

# Solo un módulo
php artisan storage:migrate-local-to-s3 --subdir=cargaconsolidada/pagos

# Solo disco public (la mayoría de uploads antiguos con URL /storage/...)
php artisan storage:migrate-local-to-s3 --source=public

# Incluir temporales (no recomendado en producción)
php artisan storage:migrate-local-to-s3 --include-temp

# Sobrescribir objetos existentes en S3
php artisan storage:migrate-local-to-s3 --force

# Prueba con límite
php artisan storage:migrate-local-to-s3 --limit=100
```

Los objetos quedan en el bucket bajo `AWS_UPLOAD_PREFIX` + `{ruta_bd}` (ej. `probusiness/production/cargaconsolidada/pagos/archivo.pdf`). **No hay que cambiar rutas en BD.**

## CDN (`cdn.probusiness.pe`)

Cuando `FILESYSTEM_UPLOAD_DISK=s3`, las URLs públicas de la API dejan de ser `{APP_URL}/storage/...` y pasan al CDN:

```env
OBJECT_STORAGE_CDN_URL=https://cdn.probusiness.pe
OBJECT_STORAGE_CDN_INCLUDE_PREFIX=true
OBJECT_STORAGE_CDN_WHEN_S3=true
```

Ejemplo:

| En BD | URL API |
|-------|---------|
| `documentation/1779860347_6a16837b6376c.xlsx` | `https://cdn.probusiness.pe/probusiness/production/documentation/1779860347_6a16837b6376c.xlsx` |

También reescribe URLs legacy guardadas en BD como `http://localhost:8001/storage/documentation/...`.

Si el CDN sirve **sin** el prefijo S3 en la URL, usa `OBJECT_STORAGE_CDN_INCLUDE_PREFIX=false`.

En local sin CDN: dejar `OBJECT_STORAGE_CDN_URL` vacío → sigue `{APP_URL}/storage/...`.

## Composer

```bash
composer install
```

Paquete: `league/flysystem-aws-s3-v3`.
