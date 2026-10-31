<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->renames() as $table => $columns) {
            foreach ($columns as $from => [$to, $comment]) {
                $this->rename($table, $from, $to, $comment);
            }
        }

        foreach ($this->comments() as $table => $columns) {
            foreach ($columns as $column => $comment) {
                $this->comment($table, $column, $comment);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->renames() as $table => $columns) {
            foreach ($columns as $from => [$to]) {
                $this->rename($table, $to, $from, '');
            }
        }
    }

    /**
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    private function renames(): array
    {
        return [
            'pago_proveedor_configuracion' => [
                'clave' => ['setting_key', 'Clave única de configuración: tarifa, plazo, tolerancia o correlativo'],
                'valor' => ['setting_value', 'Valor JSON de esa clave'],
            ],
            'pago_proveedor_cotizaciones' => [
                'codigo' => ['code', 'Código interno de la cotización de pago a proveedor'],
                'id_cotizacion_cc' => ['consolidated_quote_id', 'Id de la cotización de carga consolidada. Referencia lógica, sin clave foránea'],
                'cod_cotizacion_cc' => ['consolidated_quote_code', 'Código de la cotización consolidada, formato CO y 8 dígitos'],
                'estado' => ['status', 'Estado guardado: pendiente o confirmada. Vencida se calcula y no se guarda'],
                'vigencia_fin' => ['valid_until', 'Fecha y hora UTC en que vence la cotización'],
                'tipo_cambio' => ['exchange_rate', 'Tipo de cambio USD usado en la cotización'],
                'tipo_cambio_confirmado' => ['exchange_rate_confirmed', 'Indica si Ventas confirmó el tipo de cambio'],
                'cliente_nombre' => ['client_name', 'Nombre del cliente copiado de la cotización consolidada'],
                'cliente_documento' => ['client_document', 'DNI o RUC del cliente'],
                'cliente_whatsapp' => ['client_whatsapp', 'WhatsApp del cliente'],
                'cliente_correo' => ['client_email', 'Correo del cliente'],
                'consolidado' => ['consolidation', 'Etiqueta del consolidado, por ejemplo 21 - 2026'],
                'usuario_id' => ['user_id', 'Usuario de Ventas que creó la cotización. Sin clave foránea'],
            ],
            'pago_proveedor_cotizacion_proveedores' => [
                'cotizacion_id' => ['quote_id', 'Cotización de pago a la que pertenece esta línea'],
                'orden' => ['position', 'Orden de la línea dentro de la cotización'],
                'id_proveedor_cc' => ['consolidated_provider_id', 'Id de contenedor_consolidado_cotizacion_proveedores. Sin clave foránea'],
                'nombre_proveedor' => ['supplier_name', 'Nombre del proveedor al momento de cotizar'],
                'producto' => ['product', 'Producto de la línea'],
                'moneda' => ['currency', 'Moneda del pago: USD o RMB'],
                'tipo_pago' => ['payment_type', 'Forma de pago: transferencia, tt o tarjeta'],
                'valor_carga_usd' => ['cargo_value_usd', 'Valor de la carga en dólares'],
                'modalidad_abono' => ['client_payment_mode', 'Cómo abona el cliente: total o fraccionado'],
                'modalidad_pago' => ['supplier_payment_mode', 'Cómo se paga al proveedor: total o fraccionado'],
                'porcentaje_1' => ['first_share_percent', 'Porcentaje de la primera cuota'],
                'porcentaje_2' => ['second_share_percent', 'Porcentaje de la segunda cuota'],
                'fecha_cuota_2' => ['second_installment_on', 'Fecha pactada de la segunda cuota'],
                'comision_bancaria' => ['charges_bank_fee', 'Si esta línea cobra comisión bancaria'],
                'comision_administrativa' => ['charges_admin_fee', 'Si esta línea cobra comisión administrativa'],
                'proforma_nombre' => ['proforma_name', 'Nombre original del archivo de proforma'],
            ],
            'pago_proveedor_solicitudes' => [
                'numero_orden' => ['order_number', 'Número de orden visible, único y global'],
                'cotizacion_id' => ['quote_id', 'Cotización de pago que originó la solicitud. Una sola por cotización'],
                'cod_cotizacion_cc' => ['consolidated_quote_code', 'Código de la cotización consolidada, formato CO y 8 dígitos'],
                'id_cotizacion_cc' => ['consolidated_quote_id', 'Id de la cotización de carga consolidada. Referencia lógica, sin clave foránea'],
                'tipo_cambio' => ['exchange_rate', 'Tipo de cambio USD heredado de la cotización'],
                'tipo_cambio_confirmado' => ['exchange_rate_confirmed', 'Indica si Ventas confirmó el tipo de cambio'],
                'cliente_nombre' => ['client_name', 'Nombre del cliente'],
                'cliente_documento' => ['client_document', 'DNI o RUC del cliente'],
                'cliente_whatsapp' => ['client_whatsapp', 'WhatsApp del cliente'],
                'cliente_correo' => ['client_email', 'Correo del cliente'],
                'consolidado' => ['consolidation', 'Etiqueta del consolidado, por ejemplo 21 - 2026'],
                'usuario_id' => ['user_id', 'Usuario de Ventas que creó la solicitud. Sin clave foránea'],
            ],
            'pago_proveedor_solicitud_proveedores' => [
                'solicitud_id' => ['request_id', 'Solicitud a la que pertenece este proveedor'],
                'orden' => ['position', 'Orden del proveedor dentro de la solicitud'],
                'id_proveedor_cc' => ['consolidated_provider_id', 'Id de contenedor_consolidado_cotizacion_proveedores. Sin clave foránea'],
                'nombre_proveedor' => ['supplier_name', 'Nombre del proveedor copiado al crear la solicitud'],
                'producto' => ['product', 'Producto a pagar'],
                'moneda' => ['currency', 'Moneda del pago: USD o RMB'],
                'tipo_pago' => ['payment_type', 'Forma de pago: transferencia, tt o tarjeta'],
                'valor_carga_usd' => ['cargo_value_usd', 'Valor de la carga en dólares'],
                'modalidad_abono' => ['client_payment_mode', 'Cómo abona el cliente: total o fraccionado'],
                'modalidad_pago' => ['supplier_payment_mode', 'Cómo se paga al proveedor: total o fraccionado'],
                'porcentaje_1' => ['first_share_percent', 'Porcentaje de la primera cuota'],
                'porcentaje_2' => ['second_share_percent', 'Porcentaje de la segunda cuota'],
                'fecha_cuota_2' => ['second_installment_on', 'Fecha pactada de la segunda cuota'],
                'comision_bancaria' => ['charges_bank_fee', 'Si esta línea cobra comisión bancaria'],
                'comision_administrativa' => ['charges_admin_fee', 'Si esta línea cobra comisión administrativa'],
                'bancaria_monto' => ['bank_fee_amount', 'Monto de la comisión bancaria de esta línea'],
                'administrativa_monto' => ['admin_fee_amount', 'Monto de la comisión administrativa de esta línea'],
                'total_monto' => ['total_amount', 'Total a cobrar: carga más comisiones'],
                'bancaria_incluida_en_orden' => ['bank_fee_on_position', 'Posición de la línea que concentra la comisión bancaria agrupada'],
                'resumen_json' => ['summary_json', 'Desglose calculado de comisiones y cuotas'],
                'proforma_nombre' => ['proforma_name', 'Nombre original del archivo de proforma'],
                'codigo_solicitud' => ['request_code', 'Código CN o PE del proveedor. Nulo hasta salir de la etapa 1'],
                'etapa' => ['stage', 'Etapa del proveedor: 1 datos, 2 validación, 3 ejecución, 4 confirmación'],
                'estado' => ['status', 'Estado del proveedor: en proceso, observado, anulado o completado'],
                'cuota_actual' => ['current_installment', 'Cuota del cliente que está en curso'],
                'pago_actual' => ['current_payment', 'Pago al proveedor que está en curso'],
            ],
            'pago_proveedor_historial' => [
                'entidad_tipo' => ['entity_type', 'Tipo de entidad: cotizacion o solicitud'],
                'entidad_id' => ['entity_id', 'Id de esa entidad. Sin clave foránea'],
                'accion' => ['action', 'Acción registrada'],
                'detalle' => ['detail', 'Texto libre de la acción'],
                'usuario_id' => ['user_id', 'Usuario que hizo la acción. Sin clave foránea'],
                'usuario_nombre' => ['user_name', 'Nombre del usuario al momento de la acción'],
            ],
            'pago_proveedor_mensajes' => [
                'cotizacion_id' => ['quote_id', 'Cotización relacionada. Sin clave foránea'],
                'solicitud_id' => ['request_id', 'Solicitud relacionada. Sin clave foránea'],
                'tipo' => ['message_type', 'Tipo de mensaje: WhatsApp, formulario u otro'],
                'destinatario' => ['recipient', 'Destinatario del mensaje, normalmente el WhatsApp del cliente'],
                'cuerpo' => ['body', 'Texto del mensaje'],
                'usuario_id' => ['user_id', 'Usuario que generó el mensaje. Sin clave foránea'],
            ],
            'pago_proveedor_datos_proveedor' => [
                'solicitud_proveedor_id' => ['request_provider_id', 'Proveedor de la solicitud dueño de estos datos bancarios'],
                'vendedor_nombre' => ['seller_name', 'Nombre del vendedor del proveedor'],
                'estado' => ['status', 'Estado de los datos: pendiente, enviado, por autorizar o recibido'],
                'origen' => ['source', 'Quién cargó los datos: cliente o ventas'],
                'correccion_solicitada' => ['correction_requested', 'Si Ventas pidió que el cliente revise los datos'],
                'beneficiario_nombre' => ['beneficiary_name', 'Nombre del beneficiario del pago'],
                'beneficiario_cuenta' => ['beneficiary_account', 'Cuenta o IBAN del beneficiario'],
                'beneficiario_direccion' => ['beneficiary_address', 'Dirección del beneficiario'],
                'beneficiario_pais' => ['beneficiary_country', 'País del beneficiario'],
                'beneficiario_ciudad' => ['beneficiary_city', 'Ciudad del beneficiario'],
                'banco_tipo_codigo' => ['bank_code_type', 'Tipo de código del banco destino: SWIFT, ABA, CHIPS, Sort Code u otro'],
                'banco_codigo' => ['bank_code', 'Código del banco destino'],
                'banco_nombre' => ['bank_name', 'Nombre del banco destino'],
                'banco_direccion' => ['bank_address', 'Dirección del banco destino'],
                'banco_pais' => ['bank_country', 'País del banco destino'],
                'banco_ciudad' => ['bank_city', 'Ciudad del banco destino'],
                'usa_intermediario' => ['uses_intermediary', 'Si el pago usa banco intermediario'],
                'inter_tipo_codigo' => ['intermediary_code_type', 'Tipo de código del banco intermediario'],
                'inter_codigo' => ['intermediary_code', 'Código del banco intermediario'],
                'inter_nombre' => ['intermediary_name', 'Nombre del banco intermediario'],
                'inter_direccion' => ['intermediary_address', 'Dirección del banco intermediario'],
                'inter_pais' => ['intermediary_country', 'País del banco intermediario'],
                'inter_ciudad' => ['intermediary_city', 'Ciudad del banco intermediario'],
                'autorizado_at' => ['authorized_at', 'Momento en que el cliente autorizó los datos'],
            ],
            'pago_proveedor_abonos' => [
                'solicitud_id' => ['request_id', 'Solicitud a la que pertenece este abono'],
                'importe' => ['amount', 'Importe que registró Ventas. Si el banco difiere, pasa a ser el importe del banco'],
                'fecha_pago' => ['paid_on', 'Fecha del depósito'],
                'numero_operacion' => ['operation_number', 'Número de operación bancaria'],
                'banco' => ['bank', 'Banco del depósito: bcp o interbank'],
                'interbancaria' => ['interbank', 'Si el depósito fue interbancario o por cajero'],
                'voucher_nombre' => ['voucher_name', 'Nombre original del comprobante'],
                'estado' => ['status', 'Estado del abono: en validación, validado, observado o anulado'],
                'excedente' => ['surplus', 'Monto del abono que sobra después de aplicarlo'],
                'excedente_decision' => ['surplus_decision', 'Qué hacer con el excedente: consultar, aplicar a cuota, reembolso o no devolver'],
                'operacion_repetida' => ['repeated_operation', 'Si el número de operación ya se había usado'],
                'usuario_id' => ['user_id', 'Usuario de Ventas que registró el abono. Sin clave foránea'],
            ],
            'pago_proveedor_abono_aplicaciones' => [
                'abono_id' => ['deposit_id', 'Abono al que se aplica este monto'],
                'solicitud_proveedor_id' => ['request_provider_id', 'Proveedor de la solicitud cubierto por este monto'],
                'monto' => ['amount', 'Monto del abono aplicado a ese proveedor'],
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function comments(): array
    {
        return [
            'pago_proveedor_cotizaciones' => [
                'id' => 'Identificador de la cotización de pago',
                'calculadora_importacion_id' => 'Id de calculadora_importacion. Referencia lógica, sin clave foránea',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_cotizacion_proveedores' => [
                'id' => 'Identificador de la línea de proveedor',
                'code_supplier' => 'Código de proveedor de carga consolidada',
                'proforma_path' => 'Ruta del archivo de proforma en el almacenamiento',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_solicitudes' => [
                'id' => 'Identificador de la solicitud',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_solicitud_proveedores' => [
                'id' => 'Identificador del proveedor dentro de la solicitud',
                'code_supplier' => 'Código de proveedor de carga consolidada',
                'proforma_path' => 'Ruta del archivo de proforma en el almacenamiento',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_historial' => [
                'id' => 'Identificador del evento',
                'area' => 'Área del usuario: Ventas, Administración u otra',
                'created_at' => 'Fecha del evento',
            ],
            'pago_proveedor_mensajes' => [
                'id' => 'Identificador del mensaje',
                'created_at' => 'Fecha en que se armó el mensaje',
            ],
            'pago_proveedor_datos_proveedor' => [
                'id' => 'Identificador de los datos bancarios',
                'wechat' => 'WeChat del vendedor, solo dígitos',
                'token' => 'Token público del formulario T-T',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_abonos' => [
                'id' => 'Identificador del abono',
                'voucher_path' => 'Ruta del comprobante en el almacenamiento',
                'created_at' => 'Fecha en que Ventas registró el abono. Inicia el plazo de verificación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_abono_aplicaciones' => [
                'id' => 'Identificador de la aplicación del abono',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
            'pago_proveedor_configuracion' => [
                'id' => 'Identificador de la fila de configuración',
                'created_at' => 'Fecha de creación',
                'updated_at' => 'Fecha de la última actualización',
            ],
        ];
    }

    private function rename(string $table, string $from, string $to, string $comment): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $from)) {
            return;
        }

        $definition = $this->definition($table, $from);
        $sqlComment = $comment === '' ? '' : " COMMENT '".$this->escape($comment)."'";
        DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` {$definition}{$sqlComment}");
    }

    private function comment(string $table, string $column, string $comment): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $definition = $this->definition($table, $column);
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition} COMMENT '".$this->escape($comment)."'");
    }

    private function definition(string $table, string $column): string
    {
        $meta = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        $nullable = $meta->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';
        $extra = strtolower((string) $meta->EXTRA);
        $suffix = '';
        if (str_contains($extra, 'auto_increment')) {
            $suffix .= ' AUTO_INCREMENT';
        }
        if (str_contains($extra, 'on update')) {
            $suffix .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        return trim("{$meta->COLUMN_TYPE} {$nullable} {$this->defaultClause($meta)}{$suffix}");
    }

    private function defaultClause(object $meta): string
    {
        if ($meta->COLUMN_DEFAULT === null) {
            return $meta->IS_NULLABLE === 'YES' ? 'DEFAULT NULL' : '';
        }

        $default = (string) $meta->COLUMN_DEFAULT;
        if (preg_match('/^current_timestamp(?:\(\d*\))?$/i', $default)) {
            return 'DEFAULT CURRENT_TIMESTAMP';
        }
        if (is_numeric($default)) {
            return 'DEFAULT '.$default;
        }

        return "DEFAULT '".$this->escape($default)."'";
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
};
