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
- Rama `soporte/PRY-26-0003-2` (en `qa`), PR #41 abierto sin merge, va junto al front #166.
- Migraciones nuevas (orden): `2026_11_14_090000_*`, `2026_11_16_100000_*` (campos de cliente en organizacion), `110000` (historial tarifas),
  `120000` (`rate_tier`), `130000` (mueve y **elimina `china_clients`**; respaldar antes de prod), `2026_11_17_100000_*` (país de origen de Pro Business).
