<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copia el comprobante único (payment_receipt_file) a la tabla retribuciones para viáticos existentes.
     */
    public function up(): void
    {
        $rows = DB::table('viaticos')
            ->whereNotNull('payment_receipt_file')
            ->where('payment_receipt_file', '!=', '')
            ->get(['id', 'payment_receipt_file']);

        foreach ($rows as $row) {
            $exists = DB::table('viaticos_retribuciones')
                ->where('viatico_id', $row->id)
                ->exists();
            if (!$exists) {
                DB::table('viaticos_retribuciones')->insert([
                    'viatico_id' => $row->id,
                    'file_path' => $row->payment_receipt_file,
                    'file_original_name' => null,
                    'orden' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No borrar datos; la migración anterior drop de la tabla si se hace down.
    }
};
