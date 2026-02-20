<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Para no perder data: viáticos CONFIRMED que no tienen ningún registro en
     * viaticos_retribuciones y sí tienen payment_receipt_file obtienen un registro.
     * La URL del pago será la de payment_receipt_file (file_path = payment_receipt_file).
     * Campos: fecha_cierre = updated_at, banco = YAPE, monto = total_amount.
     */
    public function up(): void
    {
        $viaticos = DB::table('viaticos')
            ->where('status', 'CONFIRMED')
            ->whereNotNull('payment_receipt_file')
            ->where('payment_receipt_file', '!=', '')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('viaticos_retribuciones')
                    ->whereColumn('viaticos_retribuciones.viatico_id', 'viaticos.id');
            })
            ->get(['id', 'total_amount', 'updated_at', 'payment_receipt_file']);

        foreach ($viaticos as $v) {
            $fechaCierre = $v->updated_at ? date('Y-m-d', strtotime($v->updated_at)) : null;

            DB::table('viaticos_retribuciones')->insert([
                'viatico_id' => $v->id,
                'file_path' => $v->payment_receipt_file,
                'file_original_name' => null,
                'banco' => 'YAPE',
                'monto' => $v->total_amount,
                'fecha_cierre' => $fechaCierre,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar registros creados por esta migración (banco YAPE y sin file_original_name)
        DB::table('viaticos_retribuciones')
            ->where('banco', 'YAPE')
            ->whereNull('file_original_name')
            ->delete();
    }
};
