<?php

namespace App\Console\Commands;

use App\Services\CalculadoraImportacion\CalculadoraTarifaService;
use Illuminate\Console\Command;

class BackfillCalculadoraTarifaIdsCommand extends Command
{
    protected $signature = 'calculadora:backfill-tarifa-ids
                            {--id= : Solo una calculadora (p. ej. 1340)}
                            {--dry-run : Simular sin escribir en BD}
                            {--force : Re-asignar aunque ya tengan calculadora_tarifa_consolidado_id}';

    protected $description = 'Asigna calculadora_tarifa_consolidado_id a filas legacy (tipo + CBM + monto guardado).';

    public function handle(CalculadoraTarifaService $tarifaService): int
    {
        $onlyId = $this->option('id') !== null && $this->option('id') !== ''
            ? (int) $this->option('id')
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá en la base de datos.');
        }

        $result = $tarifaService->runBackfillTarifaIds($onlyId, $dryRun, $force);

        $rows = collect($result['details'])->map(function (array $row) {
            return [
                $row['id'] ?? '-',
                $row['status'] ?? '-',
                $row['tarifa_row_id'] ?? '-',
                $row['tarifa_row_value'] ?? '-',
                $row['stored_tarifa'] ?? ($row['tarifa'] ?? '-'),
                $row['tipo'] ?? '-',
                $row['cbm'] ?? '-',
            ];
        })->all();

        if ($rows !== []) {
            $this->table(
                ['calc_id', 'status', 'tarifa_row_id', 'row_value', 'stored', 'tipo', 'cbm'],
                $rows
            );
        }

        $this->info(sprintf(
            'Listo. Actualizadas: %d | Omitidas: %d',
            $result['updated'],
            $result['skipped']
        ));

        return $result['skipped'] > 0 && $result['updated'] === 0 ? 1 : 0;
    }
}
