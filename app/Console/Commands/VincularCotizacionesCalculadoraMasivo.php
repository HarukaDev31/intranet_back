<?php

namespace App\Console\Commands;

use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionController;
use App\Models\CalculadoraImportacion;
use Illuminate\Console\Command;

class VincularCotizacionesCalculadoraMasivo extends Command
{
    /**
     * Ejemplos:
     * php artisan calculadora:vincular-masivo
     * php artisan calculadora:vincular-masivo --ids=101,205,300
     * php artisan calculadora:vincular-masivo --limit=50
     * php artisan calculadora:vincular-masivo --force
     */
    protected $signature = 'calculadora:vincular-masivo
                            {--ids= : IDs específicos separados por coma}
                            {--limit=0 : Máximo de cotizaciones a procesar}
                            {--force : Ejecuta sin confirmaciones interactivas}';

    protected $description = 'Vincula masivamente cotizaciones de calculadora en carga consolidada con vista previa, confirmaciones y resumen detallado.';

    public function handle()
    {
        $idsOption = trim((string) $this->option('ids'));
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $query = CalculadoraImportacion::query()
            ->with(['proveedores:id,id_calculadora_importacion,code_supplier,id_proveedor'])
            ->whereNotNull('url_cotizacion')
            ->whereNotNull('id_carga_consolidada_contenedor');

        if ($idsOption !== '') {
            $ids = collect(explode(',', $idsOption))
                ->map(function ($id) {
                    return (int) trim($id);
                })
                ->filter(function ($id) {
                    return $id > 0;
                })
                ->unique()
                ->values()
                ->all();

            if (empty($ids)) {
                $this->error('La opción --ids no contiene IDs válidos.');
                return 1;
            }

            $query->whereIn('id', $ids);
        }

        $candidatas = $query->orderBy('id')->get();

        $pendientes = $candidatas->filter(function ($calc) {
            if (!$calc->relationLoaded('proveedores')) {
                return false;
            }

            return $calc->proveedores->contains(function ($p) {
                $codeSupplierOk = $p->code_supplier !== null && trim((string) $p->code_supplier) !== '';
                $idProveedorOk = $p->id_proveedor !== null && (string) $p->id_proveedor !== '';
                return !$codeSupplierOk || !$idProveedorOk;
            });
        })->values();

        if ($limit > 0) {
            $pendientes = $pendientes->take($limit)->values();
        }

        if ($pendientes->isEmpty()) {
            $this->info('No hay cotizaciones pendientes por vincular con los criterios actuales.');
            return 0;
        }

        $this->warn('Se encontraron cotizaciones pendientes de vinculación.');
        $this->table(
            ['ID', 'Cliente', 'Contenedor', 'Estado', 'Total proveedores', 'Pendientes'],
            $pendientes->map(function ($calc) {
                $totalProv = $calc->proveedores->count();
                $pendProv = $calc->proveedores->filter(function ($p) {
                    $codeSupplierOk = $p->code_supplier !== null && trim((string) $p->code_supplier) !== '';
                    $idProveedorOk = $p->id_proveedor !== null && (string) $p->id_proveedor !== '';
                    return !$codeSupplierOk || !$idProveedorOk;
                })->count();

                return [
                    $calc->id,
                    $calc->nombre_cliente ?: '-',
                    $calc->id_carga_consolidada_contenedor ?: '-',
                    $calc->estado ?: '-',
                    $totalProv,
                    $pendProv,
                ];
            })->all()
        );

        if (!$force) {
            $this->line('');
            $this->warn('Confirmación 1/3: esta operación puede crear/vincular cotizaciones y sincronizar proveedores.');
            if (!$this->confirm('¿Deseas continuar con este lote?', false)) {
                $this->info('Operación cancelada por el usuario.');
                return 0;
            }

            $this->warn('Confirmación 2/3: se ejecutará la misma lógica de "vincular" usada por el botón individual.');
            if (!$this->confirm('¿Confirmas aplicar cambios en base de datos?', false)) {
                $this->info('Operación cancelada por el usuario.');
                return 0;
            }

            $idsTexto = $pendientes->pluck('id')->implode(',');
            $this->warn('Confirmación 3/3: IDs a procesar => ' . $idsTexto);
            if (!$this->confirm('¿Ejecutar ahora?', false)) {
                $this->info('Operación cancelada por el usuario.');
                return 0;
            }
        }

        $controller = app(CalculadoraImportacionController::class);
        $procesadas = 0;
        $ok = 0;
        $fail = 0;
        $errores = [];

        $bar = $this->output->createProgressBar($pendientes->count());
        $bar->start();

        foreach ($pendientes as $calc) {
            $procesadas++;
            try {
                $response = $controller->vincularCotizacionDesdeCalculadora($calc->id);
                $payload = method_exists($response, 'getData') ? (array) $response->getData(true) : [];

                if (!empty($payload['success'])) {
                    $ok++;
                } else {
                    $fail++;
                    $errores[] = [
                        'id' => $calc->id,
                        'mensaje' => isset($payload['message']) ? (string) $payload['message'] : 'Sin detalle',
                    ];
                }
            } catch (\Throwable $e) {
                $fail++;
                $errores[] = [
                    'id' => $calc->id,
                    'mensaje' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info('Proceso finalizado.');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total candidatos', $candidatas->count()],
                ['Total pendientes evaluados', $pendientes->count()],
                ['Procesadas', $procesadas],
                ['Exitosas', $ok],
                ['Fallidas', $fail],
            ]
        );

        if (!empty($errores)) {
            $this->warn('Detalle de fallos:');
            $this->table(
                ['ID', 'Error'],
                collect($errores)->map(function ($item) {
                    return [$item['id'], $item['mensaje']];
                })->all()
            );
        }

        return $fail > 0 ? 1 : 0;
    }
}

