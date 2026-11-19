# intranet_back (Laravel)

Front: `HarukaDev31/probusiness_intranetv3` (Nuxt). Prod = `main` ("Deploy PROD", aprobación manual), QA = `qa`
(Actions "Deploy QA": CI con tests + PHPStan y luego migraciones; API QA https://qa-intranetback.probusiness.pe/api).

## Flujo de trabajo
- Rama `soporte/{ticket}` desde `main` → merge `--no-ff` a `qa` → PR a `main`. Nunca push a `main`.
- Local: `php -l` en lo tocado. `vendor` puede no instalarse (proxy) → el CI de "Deploy QA" corre tests y PHPStan; revisar el run si falla.
- PHPStan es estricto: no ampliar el arreglo que se pasa a `Model::update()` (usar `fill`/asignar atributo y `save`).

## Mapa de módulos
- **Pago a Proveedor**: `app/Services/PagoProveedor/*` (Etapa1, Verificacion, Aprobacion, Confirmacion, Yuan, Mensaje…),
  controladores en `app/Http/Controllers/PagoProveedor`, rutas `routes/modules/pago-proveedor.php`.
- **Almacén China**: `app/Services/ChinaBilling/*`, rutas `routes/modules/china-billing.php`.
  - Cálculos (diferencia contra lo enviado, saldo a favor, estados, `partner_status`): `ChinaBillingCalculator` (tests en `tests/Unit/ChinaBillingCalculatorTest.php`).
  - Lectura/serialización: `ChinaBillingReader`. Cobros: `ChinaPaymentService`. Operaciones: `ChinaOperationService`. PDFs: `ChinaDocumentService` + `resources/views/china-billing/*.blade.php`.
  - Permisos: `ChinaBillingAuthorization` (`requireWarehouse` incluye org 1; `requirePartner` = rol Socio/Importador o menú `pagos-socio`).
- **Clientes = organizaciones** (socio/importador, tarifas con historial, rol, menú Pagos y usuario = correo con bcrypt):
  `app/Services/Organizacion/OrganizacionClienteService.php` (usado por Clientes China y Panel de acceso). Al cambiar el tipo no se renombran roles: solo se mueve al usuario del cliente.
- Login acepta contraseñas CodeIgniter y bcrypt: `app/Helpers/CodeIgniterEncryption::verifyPassword`. Menús del login: `AuthController` (respuesta `menus`).
- Soporte TI (tickets): `routes/modules/soporte-ti.php`, `app/Services/SoporteTi/`.

## Cómo trabajar (ahorro de tokens)
- Usar este mapa antes de buscar; verificar lógica con tests unitarios del Calculator/servicios o `e2e/qa` del front.
- Reglas de negocio, migraciones y permisos → modelo principal; ajustes triviales → modelo menor.
- Resúmenes cortos al cerrar cada ronda (chat del ticket / PR), no por cada cambio.

## Estado PRY-26-0003
- Rama `soporte/PRY-26-0003-2` (mergeada a `qa`), PR #41 abierto sin merge, va junto al front #166. Estado completo y pendientes: `CLAUDE.md` del front (`probusiness_intranetv3`).
- Migraciones nuevas (todas viajan a prod con el PR): `2026_11_14_090000`, `2026_11_16_100000/110000/120000`, `130000` (**elimina `china_clients`**; respaldar), `2026_11_17_100000` (país Pro Business),
  `2026_11_17_110000` (dirección oficina en chino), `120000` (reordena menú Pagos: **+1 a los menús principales posteriores**), `130000` (historial `action` a 255),
  `140000` (cuenta bancaria China + tax id/uscc/email), `150000` (rol **Encargado China**, mueve a armando@probusiness.pe y quita módulos de China a ContenedorAlmacen), `160000` (menú Pago a Proveedor a todos los Cotizador), `170000` (menú de Encargado China).
  `2026_11_19_100000` (`rate_pending` yuanes), `110000` (menús placeholder de Encargado China), `120000` (`organizacion.facturable_china`: eliminar cliente de China = baja lógica).
- Clientes China: Pro Business (org 1) se lista (`is_house`), solo se editan sus tarifas (`updateHouseRates`), no se elimina.
- Roles: `Usuario::ROL_ENCARGADO_CHINA`; `Usuario::esRolAlmacenChina()` lo trata como almacén en Carga Consolidada; ChinaBilling y Pago a Proveedor (China) exigen Encargado China.
- Links al cliente (T-T): `config/pago_proveedor.php` → `clientes_url` (QA: beta de clientes en Netlify; prod: clientes.probusiness.pe). Token de 12 caracteres.
- Comando `qa:limpiar-pruebas [--keep=2|--operaciones=|--solicitudes=] [--dry-run]`: solo QA.
