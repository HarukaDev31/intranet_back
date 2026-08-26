<?php

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
        $result = $tarifaService->runBackfillTarifaIds();

        Log::info('Backfill v3 calculadora_tarifa_consolidado_id', [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function down(): void
    {
        //
    }
};
