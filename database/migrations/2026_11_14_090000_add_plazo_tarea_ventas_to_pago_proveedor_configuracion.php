<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\PagoProveedor\PagoProveedorCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRY-26-0003 (#1742): las tareas de Ventas sin plazo propio (registrar datos, comprobante,
 * diferencia de cambio…) vencen por defecto a las 48 h. Solo se llena si la clave no existe
 * o quedó vacía: no pisa un valor que Ventas ya haya configurado.
 */
return new class extends Migration
{
    private const KEY = 'plazo_tarea_ventas_horas';

    private const DEFAULT_HOURS = 48;

    public function up(): void
    {
        if (!Schema::hasTable('pago_proveedor_configuracion')) {
            return;
        }

        $row = DB::table('pago_proveedor_configuracion')->where('setting_key', self::KEY)->first();
        $value = $row !== null ? json_decode((string) $row->setting_value, true) : null;
        if ($row !== null && is_numeric($value) && (int) $value > 0) {
            return;
        }

        DB::table('pago_proveedor_configuracion')->updateOrInsert(
            ['setting_key' => self::KEY],
            ['setting_value' => json_encode(self::DEFAULT_HOURS), 'created_at' => now(), 'updated_at' => now()]
        );
        app(PagoProveedorCacheService::class)->forgetConfiguration();
    }

    public function down(): void
    {
        if (!Schema::hasTable('pago_proveedor_configuracion')) {
            return;
        }

        DB::table('pago_proveedor_configuracion')
            ->where('setting_key', self::KEY)
            ->where('setting_value', json_encode(self::DEFAULT_HOURS))
            ->delete();
    }
};
