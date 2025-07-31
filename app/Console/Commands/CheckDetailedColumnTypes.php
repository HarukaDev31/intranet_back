<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDetailedColumnTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:detailed-column-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar información detallada de tipos de datos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificando Información Detallada de Columnas ===');

        // Verificar información completa de las columnas
        $this->info("\n--- Información completa de entidad_id ---");
        $entidadIdInfo = DB::select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos_importados_excel' AND COLUMN_NAME = 'entidad_id'");
        foreach ($entidadIdInfo as $info) {
            $this->info("  COLUMN_NAME: {$info->COLUMN_NAME}");
            $this->info("  DATA_TYPE: {$info->DATA_TYPE}");
            $this->info("  IS_NULLABLE: {$info->IS_NULLABLE}");
            $this->info("  COLUMN_DEFAULT: {$info->COLUMN_DEFAULT}");
            $this->info("  EXTRA: {$info->EXTRA}");
            $this->info("  NUMERIC_PRECISION: {$info->NUMERIC_PRECISION}");
            $this->info("  NUMERIC_SCALE: {$info->NUMERIC_SCALE}");
        }

        $this->info("\n--- Información completa de id en bd_entidades_reguladoras ---");
        $idEntidadInfo = DB::select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bd_entidades_reguladoras' AND COLUMN_NAME = 'id'");
        foreach ($idEntidadInfo as $info) {
            $this->info("  COLUMN_NAME: {$info->COLUMN_NAME}");
            $this->info("  DATA_TYPE: {$info->DATA_TYPE}");
            $this->info("  IS_NULLABLE: {$info->IS_NULLABLE}");
            $this->info("  COLUMN_DEFAULT: {$info->COLUMN_DEFAULT}");
            $this->info("  EXTRA: {$info->EXTRA}");
            $this->info("  NUMERIC_PRECISION: {$info->NUMERIC_PRECISION}");
            $this->info("  NUMERIC_SCALE: {$info->NUMERIC_SCALE}");
        }

        $this->info("\n--- Información completa de tipo_etiquetado_id ---");
        $tipoEtiquetadoIdInfo = DB::select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos_importados_excel' AND COLUMN_NAME = 'tipo_etiquetado_id'");
        foreach ($tipoEtiquetadoIdInfo as $info) {
            $this->info("  COLUMN_NAME: {$info->COLUMN_NAME}");
            $this->info("  DATA_TYPE: {$info->DATA_TYPE}");
            $this->info("  IS_NULLABLE: {$info->IS_NULLABLE}");
            $this->info("  COLUMN_DEFAULT: {$info->COLUMN_DEFAULT}");
            $this->info("  EXTRA: {$info->EXTRA}");
            $this->info("  NUMERIC_PRECISION: {$info->NUMERIC_PRECISION}");
            $this->info("  NUMERIC_SCALE: {$info->NUMERIC_SCALE}");
        }

        $this->info("\n--- Información completa de id en bd_productos ---");
        $idProductoInfo = DB::select("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bd_productos' AND COLUMN_NAME = 'id'");
        foreach ($idProductoInfo as $info) {
            $this->info("  COLUMN_NAME: {$info->COLUMN_NAME}");
            $this->info("  DATA_TYPE: {$info->DATA_TYPE}");
            $this->info("  IS_NULLABLE: {$info->IS_NULLABLE}");
            $this->info("  COLUMN_DEFAULT: {$info->COLUMN_DEFAULT}");
            $this->info("  EXTRA: {$info->EXTRA}");
            $this->info("  NUMERIC_PRECISION: {$info->NUMERIC_PRECISION}");
            $this->info("  NUMERIC_SCALE: {$info->NUMERIC_SCALE}");
        }

        return 0;
    }
}
