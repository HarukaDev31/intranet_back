<?php

use App\Models\CalculadoraImportacion;
use App\Services\CalculadoraImportacion\CalculadoraTarifaService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calculadora_importacion', 'calculadora_tarifa_consolidado_id')) {
            return;
        }

        /** @var CalculadoraTarifaService $tarifaService */
        $tarifaService = app(CalculadoraTarifaService::class);

        $updated = 0;
        $skipped = 0;

        CalculadoraImportacion::query()
            ->whereNull('calculadora_tarifa_consolidado_id')
            ->with('proveedores')
            ->orderBy('id')
            ->chunkById(200, function ($calculadoras) use ($tarifaService, &$updated, &$skipped) {
                foreach ($calculadoras as $calculadora) {
                    $row = $tarifaService->findTarifaAtCalculadoraCreation($calculadora);
                    if (! $row) {
                        $skipped++;

                        continue;
                    }

                    $updates = [
                        'calculadora_tarifa_consolidado_id' => (int) $row->id,
                    ];

                    if (empty($calculadora->tarifa_type)) {
                        $updates['tarifa_type'] = strtoupper(trim((string) $row->type)) ?: 'PLAIN';
                    }

                    $calculadora->forceFill($updates)->save();
                    $updated++;
                }
            });

        Log::info('Backfill calculadora_tarifa_consolidado_id', [
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function down(): void
    {
        // No revertir: no sabemos qué filas tenían null antes del backfill.
    }
};
