<?php

namespace App\Console\Commands;

use App\Http\Controllers\CalculadoraImportacion\CalculadoraImportacionController;
use App\Models\CalculadoraImportacion;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class PasarCalculadorasACotizadoPorFecha extends Command
{
    protected $signature = 'calculadora:pasar-cotizado-fecha
                            {fecha? : Fecha objetivo en formato YYYY-MM-DD}
                            {--campo-fecha=created_at : Campo de fecha para el filtro (created_at|updated_at)}
                            {--estado-origen= : Filtrar solo filas con este estado actual}
                            {--ids= : IDs especificos separados por coma}
                            {--limit=0 : Maximo de filas a procesar}
                            {--force : Ejecuta cambios. Sin --force solo previsualiza}';

    protected $description = 'Pasa calculadoras a COTIZADO por fecha reutilizando el flujo real de changeEstado (Request + regeneracion completa).';

    public function handle(): int
    {
        $fecha = trim((string) ($this->argument('fecha') ?? ''));
        $campoFecha = trim((string) $this->option('campo-fecha'));
        $estadoOrigen = trim((string) $this->option('estado-origen'));
        $idsOption = trim((string) $this->option('ids'));
        $limit = max((int) $this->option('limit'), 0);
        $force = (bool) $this->option('force');

        if (!in_array($campoFecha, ['created_at', 'updated_at'], true)) {
            $this->error('La opcion --campo-fecha solo permite: created_at, updated_at');
            return self::FAILURE;
        }

        if ($fecha === '' && $idsOption === '') {
            $this->error('Debes enviar una fecha (YYYY-MM-DD) o usar --ids.');
            return self::FAILURE;
        }

        if ($fecha !== '' && !$this->fechaValida($fecha)) {
            $this->error('La fecha no tiene formato valido. Usa YYYY-MM-DD.');
            return self::FAILURE;
        }

        $query = CalculadoraImportacion::query()->orderBy('id');

        if ($fecha !== '') {
            $query->whereDate($campoFecha, $fecha);
        }

        if ($estadoOrigen !== '') {
            $query->where('estado', $estadoOrigen);
        }

        if ($idsOption !== '') {
            $ids = collect(explode(',', $idsOption))
                ->map(fn($id) => (int) trim($id))
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($ids)) {
                $this->error('La opcion --ids no contiene valores validos.');
                return self::FAILURE;
            }

            $query->whereIn('id', $ids);
        }

        $rows = $query->get(['id', 'estado', 'nombre_cliente', 'id_cotizacion', 'id_carga_consolidada_contenedor', $campoFecha]);

        if ($limit > 0) {
            $rows = $rows->take($limit)->values();
        }

        if ($rows->isEmpty()) {
            $this->info('No se encontraron calculadoras con los filtros indicados.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Estado actual', 'Cliente', 'Cotizacion', 'Contenedor', 'Fecha'],
            $rows->map(function ($row) use ($campoFecha) {
                return [
                    (int) $row->id,
                    (string) ($row->estado ?? '-'),
                    (string) ($row->nombre_cliente ?? '-'),
                    (string) ($row->id_cotizacion ?? '-'),
                    (string) ($row->id_carga_consolidada_contenedor ?? '-'),
                    (string) ($row->{$campoFecha} ?? '-'),
                ];
            })->all()
        );

        if (!$force) {
            $this->warn('Modo preview activo: no se aplicaran cambios. Usa --force para ejecutar.');
            return self::SUCCESS;
        }

        $controller = app(CalculadoraImportacionController::class);
        $ok = 0;
        $fail = 0;
        $errores = [];

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $row) {
            try {
                $request = new Request([
                    'estado' => CalculadoraImportacion::ESTADO_COTIZADO,
                ]);

                $response = $controller->changeEstado($request, (int) $row->id);
                $payload = method_exists($response, 'getData')
                    ? (array) $response->getData(true)
                    : [];

                if (!empty($payload['success'])) {
                    $ok++;
                } else {
                    $fail++;
                    $errores[] = [
                        'id' => (int) $row->id,
                        'error' => isset($payload['message']) ? (string) $payload['message'] : 'Respuesta sin detalle',
                    ];
                }
            } catch (\Throwable $e) {
                $fail++;
                $errores[] = [
                    'id' => (int) $row->id,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Procesadas', $rows->count()],
                ['Exitosas', $ok],
                ['Fallidas', $fail],
            ]
        );

        if (!empty($errores)) {
            $this->warn('Detalle de errores:');
            $this->table(
                ['ID', 'Error'],
                collect($errores)->map(fn($e) => [$e['id'], $e['error']])->all()
            );
        }

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function fechaValida(string $fecha): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt && $dt->format('Y-m-d') === $fecha;
    }
}

