<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los pagos en yuanes anteriores guardaban el tipo de cambio en la ejecución.
 * Sin su ingreso, el balance los mostraría como dinero por cobrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pago_proveedor_ejecuciones as e')
            ->join('pago_proveedor_solicitud_proveedores as p', 'p.id', '=', 'e.request_provider_id')
            ->whereNotNull('e.real_exchange_rate')
            ->whereNotNull('e.yuan_obtained')
            ->orderBy('e.id')
            ->get(['p.request_id', 'e.cargo_amount', 'e.real_exchange_rate', 'e.yuan_obtained', 'e.executed_on', 'e.user_id'])
            ->each(function ($row) {
                DB::table('pago_proveedor_ingresos')->insert([
                    'request_id' => $row->request_id,
                    'usd_amount' => $row->cargo_amount,
                    'exchange_rate' => $row->real_exchange_rate,
                    'yuan_amount' => $row->yuan_obtained,
                    'received_on' => $row->executed_on,
                    'user_id' => $row->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Los ingresos se eliminan con su tabla en la migración anterior.
    }
};
