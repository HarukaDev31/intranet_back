<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private string $table = 'contenedor_consolidado_order_steps';
    private string $column = 'tipo';
    private string $newValue = 'JEFE MARKETING';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $enumValues = $this->getEnumValues($this->table, $this->column);
        if (empty($enumValues)) {
            return;
        }

        if (!in_array($this->newValue, $enumValues, true)) {
            $enumValues[] = $this->newValue;
        }

        $this->rebuildEnumColumn($this->table, $this->column, $enumValues);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $enumValues = $this->getEnumValues($this->table, $this->column);
        if (empty($enumValues) || !in_array($this->newValue, $enumValues, true)) {
            return;
        }

        // Reasignar registros que usan el valor agregado antes de quitarlo del enum.
        DB::table($this->table)
            ->where($this->column, $this->newValue)
            ->update([$this->column => 'JEFE IMPORTACION']);

        $enumValues = array_values(array_filter(
            $enumValues,
            fn ($value) => $value !== $this->newValue
        ));

        $this->rebuildEnumColumn($this->table, $this->column, $enumValues);
    }

    /**
     * Proceso seguro: crear columna nueva enum, copiar datos, borrar antigua y renombrar.
     */
    private function rebuildEnumColumn(string $table, string $column, array $enumValues): void
    {
        $tmpColumn = $column . '_2';
        $enumSql = $this->enumListSql($enumValues);

        DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` ENUM({$enumSql}) NULL AFTER `{$column}`");
        DB::statement("UPDATE `{$table}` SET `{$tmpColumn}` = `{$column}`");
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        DB::statement("ALTER TABLE `{$table}` CHANGE COLUMN `{$tmpColumn}` `{$column}` ENUM({$enumSql}) NOT NULL");
    }

    private function getEnumValues(string $table, string $column): array
    {
        $result = DB::selectOne("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        $type = $result->Type ?? null;
        if (!$type || !Str::startsWith($type, 'enum(')) {
            return [];
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);
        return array_map(static fn ($value) => str_replace("\\'", "'", $value), $matches[1] ?? []);
    }

    private function enumListSql(array $values): string
    {
        return implode(', ', array_map(
            static fn ($value) => "'" . str_replace("'", "\\'", $value) . "'",
            $values
        ));
    }
};

