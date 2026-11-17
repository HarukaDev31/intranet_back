<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** El historial ahora guarda frases completas en `action` («Abono #1 editado antes de validar: …»): 80 caracteres no alcanzan. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pago_proveedor_historial') || ! Schema::hasColumn('pago_proveedor_historial', 'action')) {
            return;
        }
        DB::statement('ALTER TABLE pago_proveedor_historial MODIFY action VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
    }
};
