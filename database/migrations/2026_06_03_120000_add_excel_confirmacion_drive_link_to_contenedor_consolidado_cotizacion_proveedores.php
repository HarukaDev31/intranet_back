<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExcelConfirmacionDriveLinkToContenedorConsolidadoCotizacionProveedores extends Migration
{
    private $table = 'contenedor_consolidado_cotizacion_proveedores';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if (!Schema::hasColumn($this->table, 'excel_confirmacion_drive_link')) {
                $table->string('excel_confirmacion_drive_link', 1024)->nullable()->after('excel_confirmacion');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if (Schema::hasColumn($this->table, 'excel_confirmacion_drive_link')) {
                $table->dropColumn('excel_confirmacion_drive_link');
            }
        });
    }
}
