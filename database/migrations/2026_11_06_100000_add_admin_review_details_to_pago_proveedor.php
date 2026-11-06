<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->json('admin_corrections')->nullable()->after('corrected_by_admin')
                ->comment('Datos corregidos por Administración: campo, antes y después');
        });
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->text('registration_error_note')->nullable()->after('collect_until')
                ->comment('Qué debe corregir Ventas por un error de registro');
        });
    }

    public function down(): void
    {
        Schema::table('pago_proveedor_abonos', function (Blueprint $table) {
            $table->dropColumn('admin_corrections');
        });
        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropColumn('registration_error_note');
        });
    }
};
