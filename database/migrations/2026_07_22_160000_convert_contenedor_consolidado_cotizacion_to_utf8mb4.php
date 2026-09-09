<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla estaba en latin1_swedish_ci y el store de cotizaciones falla (MySQL 3988)
 * con nombres Unicode (p. ej. "JESTİN" con İ turca) enviados como utf8mb4.
 */
return new class extends Migration
{
    private string $table = 'contenedor_consolidado_cotizacion';

    private string $charset = 'utf8mb4';

    private string $collation = 'utf8mb4_unicode_ci';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        $status = DB::selectOne("SHOW TABLE STATUS LIKE '{$this->table}'");
        $current = strtolower((string) ($status->Collation ?? ''));
        if ($current === strtolower($this->collation) || str_starts_with($current, 'utf8mb4_')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `{$this->table}` CONVERT TO CHARACTER SET {$this->charset} COLLATE {$this->collation}"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        // Revertir solo si hace falta operar en entornos legacy; no recomendado en prod.
        DB::statement(
            "ALTER TABLE `{$this->table}` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci"
        );
    }
};
