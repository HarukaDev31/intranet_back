<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * correo era TEXT (índice solo con prefijo). documento ya era VARCHAR(255).
 * Unifica ambos a VARCHAR(510) = 255*2 para índices completos y lookups Copiloto.
 */
class ConvertCotizacionCorreoDocumentoToVarchar extends Migration
{
    const TABLE = 'contenedor_consolidado_cotizacion';

    /** @var int */
    const VARCHAR_LEN = 510;

    /** @var array<int, string> */
    const INDEX_NAMES = [
        'cc_cot_correo_idx',
        'cc_cot_documento_idx',
        'idx_cotizacion_correo',
    ];

    public function up()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->dropRelatedIndexes();

        if (Schema::hasColumn(self::TABLE, 'correo')) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `correo` VARCHAR(%d) NULL',
                self::TABLE,
                self::VARCHAR_LEN
            ));
        }

        if (Schema::hasColumn(self::TABLE, 'documento')) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `documento` VARCHAR(%d) NULL',
                self::TABLE,
                self::VARCHAR_LEN
            ));
        }

        $this->addRelatedIndexes();
    }

    public function down()
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->dropRelatedIndexes();

        if (Schema::hasColumn(self::TABLE, 'correo')) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` MODIFY `correo` TEXT NULL');
        }

        if (Schema::hasColumn(self::TABLE, 'documento')) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` MODIFY `documento` VARCHAR(255) NULL');
        }

        if (Schema::hasColumn(self::TABLE, 'correo') && !$this->indexExists('idx_cotizacion_correo')) {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ADD INDEX `idx_cotizacion_correo` (`correo`(255))');
        }
    }

    private function addRelatedIndexes()
    {
        if (Schema::hasColumn(self::TABLE, 'correo')
            && !$this->indexExists('cc_cot_correo_idx')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['correo'], 'cc_cot_correo_idx');
            });
        }

        if (Schema::hasColumn(self::TABLE, 'documento')
            && !$this->indexExists('cc_cot_documento_idx')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['documento'], 'cc_cot_documento_idx');
            });
        }
    }

    private function dropRelatedIndexes()
    {
        foreach (self::INDEX_NAMES as $indexName) {
            if (!$this->indexExists($indexName)) {
                continue;
            }

            Schema::table(self::TABLE, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    /**
     * @param  string  $indexName
     * @return bool
     */
    private function indexExists($indexName)
    {
        $database = Schema::getConnection()->getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, self::TABLE, $indexName]
        );

        return count($rows) > 0;
    }
}
