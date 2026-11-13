<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #1724: al validar, el abono que registró Ventas toma el importe del banco. La aplicación guarda lo que aplicó
 * Ventas para mostrarlo («Ventas registró $X») y para deshacer la validación. Los abonos ya validados de un solo
 * proveedor en los que llegó menos al banco se ajustan igual.
 */
return new class extends Migration
{
    private const TABLA = 'pago_proveedor_abono_aplicaciones';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLA) || Schema::hasColumn(self::TABLA, 'recorded_amount')) {
            return;
        }
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->decimal('recorded_amount', 12, 2)->nullable()->after('amount')
                ->comment('Monto que aplicó Ventas cuando Administración lo ajustó al importe del banco');
        });

        $filas = DB::table(self::TABLA.' as a')
            ->join('pago_proveedor_abonos as d', 'd.id', '=', 'a.deposit_id')
            ->where('d.status', 'validado')
            ->whereNotNull('d.bank_amount')
            ->whereRaw('a.amount - d.bank_amount >= 0.005')
            ->whereRaw('(SELECT COUNT(*) FROM '.self::TABLA.' x WHERE x.deposit_id = a.deposit_id) = 1')
            ->get(['a.id', 'a.amount', 'd.bank_amount']);
        foreach ($filas as $fila) {
            DB::table(self::TABLA)->where('id', $fila->id)->update([
                'recorded_amount' => $fila->amount,
                'amount' => $fila->bank_amount,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLA) || !Schema::hasColumn(self::TABLA, 'recorded_amount')) {
            return;
        }
        DB::table(self::TABLA)->whereNotNull('recorded_amount')->update(['amount' => DB::raw('recorded_amount')]);
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropColumn('recorded_amount');
        });
    }
};
