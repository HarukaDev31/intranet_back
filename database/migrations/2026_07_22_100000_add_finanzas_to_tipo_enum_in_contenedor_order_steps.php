<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Añade FINANZAS al ENUM tipo de contenedor_consolidado_order_steps.
 * generateSteps() inserta pasos con tipo=FINANZAS; sin este valor MySQL trunca (1265).
 */
return new class extends Migration
{
    private string $table = 'contenedor_consolidado_order_steps';

    private string $column = 'tipo';

    /** @var list<string> */
    private array $valuesToEnsure = [
        'FINANZAS',
    ];

    public function up(): void
    {
        $enumValues = $this->getEnumValues($this->table, $this->column);
        if ($enumValues === []) {
            return;
        }

        $changed = false;
        foreach ($this->valuesToEnsure as $value) {
            if (!in_array($value, $enumValues, true)) {
                $enumValues[] = $value;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $this->rebuildEnumColumn($this->table, $this->column, $enumValues);
    }

    public function down(): void
    {
        $enumValues = $this->getEnumValues($this->table, $this->column);
        if ($enumValues === []) {
            return;
        }

        foreach ($this->valuesToEnsure as $value) {
            if (!in_array($value, $enumValues, true)) {
                continue;
            }

            DB::table($this->table)
                ->where($this->column, $value)
                ->update([$this->column => 'COTIZADOR']);

            $enumValues = array_values(array_filter(
                $enumValues,
                static fn ($existing) => $existing !== $value
            ));
        }

        $this->rebuildEnumColumn($this->table, $this->column, $enumValues);
    }

    private function rebuildEnumColumn(string $table, string $column, array $enumValues): void
    {
        $tmpColumn = $column . '_2';
        $enumSql = $this->enumListSql($enumValues);

        DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` ENUM({$enumSql}) NULL AFTER `{$column}`");
        DB::statement("UPDATE `{$table}` SET `{$tmpColumn}` = `{$column}`");
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        DB::statement("ALTER TABLE `{$table}` CHANGE COLUMN `{$tmpColumn}` `{$column}` ENUM({$enumSql}) NOT NULL");
    }

    /**
     * @return list<string>
     */
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
