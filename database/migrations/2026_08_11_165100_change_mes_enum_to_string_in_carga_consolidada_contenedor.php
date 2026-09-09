<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('carga_consolidada_contenedor') || !Schema::hasColumn('carga_consolidada_contenedor', 'mes')) {
            return;
        }

        // De ENUM (SETIEMBRE) a VARCHAR libre para aceptar SEPTIEMBRE y variantes del front.
        DB::statement('ALTER TABLE `carga_consolidada_contenedor` MODIFY `mes` VARCHAR(32) NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('carga_consolidada_contenedor') || !Schema::hasColumn('carga_consolidada_contenedor', 'mes')) {
            return;
        }

        // Volver a ENUM histórico (ortografía peruana SETIEMBRE).
        DB::statement("UPDATE `carga_consolidada_contenedor` SET `mes` = 'SETIEMBRE' WHERE UPPER(`mes`) IN ('SEPTIEMBRE', 'SEPTEMBER')");

        DB::statement("ALTER TABLE `carga_consolidada_contenedor` MODIFY `mes` ENUM(
            'ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SETIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'
        ) NULL");
    }
};
