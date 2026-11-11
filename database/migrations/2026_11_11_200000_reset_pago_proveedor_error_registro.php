<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRY-26-0003: Aprobación de pagos ya no usa «Error de registro» (si Ventas registró mal,
 * Administración edita el depósito en Verificación). Los pagos que quedaron en ese estado
 * vuelven a Pendiente para que Administración los apruebe o los marque Por cobrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pago_proveedor_solicitud_proveedores')) {
            return;
        }

        DB::table('pago_proveedor_solicitud_proveedores')
            ->where('approval_status', 'error_registro')
            ->where('status', '!=', 'anulado')
            ->update([
                'approval_status' => 'pendiente',
                'status' => 'en_proceso',
                'collect_until' => null,
                'registration_error_note' => null,
            ]);
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se sabe qué pagos estaban en «Error de registro».
    }
};
