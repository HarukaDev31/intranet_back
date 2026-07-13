<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'contenedor_consolidado_cotizacion_proveedores';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if (!Schema::hasColumn($this->table, 'excel_conf_form_cerrado')) {
                $table->boolean('excel_conf_form_cerrado')->default(false)->after('excel_conf_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if (Schema::hasColumn($this->table, 'excel_conf_form_cerrado')) {
                $table->dropColumn('excel_conf_form_cerrado');
            }
        });
    }
};
