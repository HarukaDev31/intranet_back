<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inserta filas en usuario_datos_facturacion a partir de consolidado_delivery_form_province
 * y consolidado_delivery_form_lima, relacionando por id_user.
 *
 * Reglas de mapeo (voucher):
 * - dni: voucher_doc si voucher_doc_type = BOLETA; ruc: voucher_doc si voucher_doc_type = FACTURA.
 * - nombre_completo: voucher_name si BOLETA; razon_social: voucher_name si FACTURA.
 * - domicilio_fiscal: Provincia si hay home_adress_delivery; Lima desde final_destination_place (si hay texto).
 *
 * Ejecutar preferiblemente una sola vez por entorno: repetir migrate puede duplicar filas
 * si no se ajusta la lógica o no hay backup/previo vacío.
 */
class BackfillUsuarioDatosFacturacionFromDeliveryForms extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('usuario_datos_facturacion')) {
            return;
        }

        if (Schema::hasTable('consolidado_delivery_form_province')) {
            $this->backfillFromProvince();
        }

        if (Schema::hasTable('consolidado_delivery_form_lima')) {
            $this->backfillFromLima();
        }
    }

    /**
     * @return void
     */
    private function backfillFromProvince()
    {
        $sql = <<<'SQL'
INSERT INTO usuario_datos_facturacion (
    id_user,
    destino,
    nombre_completo,
    dni,
    ruc,
    razon_social,
    domicilio_fiscal,
    created_at,
    updated_at
)
SELECT
    p.id_user,
    'Provincia' AS destino,
    CASE
        WHEN UPPER(TRIM(COALESCE(p.voucher_doc_type, ''))) = 'BOLETA'
        THEN NULLIF(TRIM(p.voucher_name), '')
        ELSE NULL
    END AS nombre_completo,
    CASE
        WHEN UPPER(TRIM(COALESCE(p.voucher_doc_type, ''))) = 'BOLETA'
        THEN NULLIF(TRIM(p.voucher_doc), '')
        ELSE NULL
    END AS dni,
    CASE
        WHEN UPPER(TRIM(COALESCE(p.voucher_doc_type, ''))) = 'FACTURA'
        THEN NULLIF(TRIM(p.voucher_doc), '')
        ELSE NULL
    END AS ruc,
    CASE
        WHEN UPPER(TRIM(COALESCE(p.voucher_doc_type, ''))) = 'FACTURA'
        THEN NULLIF(TRIM(p.voucher_name), '')
        ELSE NULL
    END AS razon_social,
    CASE
        WHEN NULLIF(TRIM(p.home_adress_delivery), '') IS NOT NULL
        THEN NULLIF(TRIM(p.home_adress_delivery), '')
        ELSE NULL
    END AS domicilio_fiscal,
    COALESCE(p.created_at, NOW()) AS created_at,
    COALESCE(p.updated_at, NOW()) AS updated_at
FROM consolidado_delivery_form_province AS p
INNER JOIN users AS u ON u.id = p.id_user
SQL;

        DB::statement($sql);
    }

    /**
     * @return void
     */
    private function backfillFromLima()
    {
        $sql = <<<'SQL'
INSERT INTO usuario_datos_facturacion (
    id_user,
    destino,
    nombre_completo,
    dni,
    ruc,
    razon_social,
    domicilio_fiscal,
    created_at,
    updated_at
)
SELECT
    l.id_user,
    'Lima' AS destino,
    CASE
        WHEN UPPER(TRIM(COALESCE(l.voucher_doc_type, ''))) = 'BOLETA'
        THEN NULLIF(TRIM(l.voucher_name), '')
        ELSE NULL
    END AS nombre_completo,
    CASE
        WHEN UPPER(TRIM(COALESCE(l.voucher_doc_type, ''))) = 'BOLETA'
        THEN NULLIF(TRIM(l.voucher_doc), '')
        ELSE NULL
    END AS dni,
    CASE
        WHEN UPPER(TRIM(COALESCE(l.voucher_doc_type, ''))) = 'FACTURA'
        THEN NULLIF(TRIM(l.voucher_doc), '')
        ELSE NULL
    END AS ruc,
    CASE
        WHEN UPPER(TRIM(COALESCE(l.voucher_doc_type, ''))) = 'FACTURA'
        THEN NULLIF(TRIM(l.voucher_name), '')
        ELSE NULL
    END AS razon_social,
    NULLIF(TRIM(l.final_destination_place), '') AS domicilio_fiscal,
    COALESCE(l.created_at, NOW()) AS created_at,
    COALESCE(l.updated_at, NOW()) AS updated_at
FROM consolidado_delivery_form_lima AS l
INNER JOIN users AS u ON u.id = l.id_user
SQL;

        DB::statement($sql);
    }

    public function down()
    {
        // No se eliminan filas: podrían mezclarse con datos cargados por otros medios.
    }
}
