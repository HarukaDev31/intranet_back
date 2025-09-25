<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;

class FixEntregaCotizadorSteps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contenedores:fix-entrega-step {--only= : Lista de IDs separados por coma o rangos (10-20,25) } {--dry-run : Solo mostrar cambios sin aplicarlos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inserta el paso "ENTREGA" en pasos COTIZADOR de contenedores que aún no lo tienen, reordenando para mantener FACTURA Y GUIA al final.';

    /**
     * Icon relative path
     */
    private const ENTREGA_ICON = 'assets/icons/entrega.png';

    public function handle(): int
    {
        $only = $this->option('only');
        $dry = (bool)$this->option('dry-run');

        $contenedoresQuery = Contenedor::query();
        if ($only) {
            $ids = $this->parseOnlyOption($only);
            if (empty($ids)) {
                $this->error('Formato inválido en --only. Usa ejemplos: 5,10-15,22');
                return 1;
            }
            $contenedoresQuery->whereIn('id', $ids);
        }

        $contenedores = $contenedoresQuery->pluck('id');
        if ($contenedores->isEmpty()) {
            $this->info('No hay contenedores para procesar.');
            return 0;
        }

        $this->info('Procesando '.$contenedores->count().' contenedores'.($dry ? ' (dry-run)' : '').'...');
        $insertados = 0; $sinCambios = 0; $errores = 0;

        foreach ($contenedores as $idContenedor) {
            try {
                DB::beginTransaction();

                $steps = ContenedorPasos::where('id_pedido', $idContenedor)
                    ->where('tipo', 'COTIZADOR')
                    ->orderBy('id_order')
                    ->get();

                // Ya tiene ENTREGA
                if ($steps->first(fn($s) => strtoupper($s->name) === 'ENTREGA')) {
                    DB::rollBack(); // No se modificó
                    $sinCambios++;
                    $this->line("#{$idContenedor}: ya tiene ENTREGA");
                    continue;
                }

                if ($steps->isEmpty()) {
                    // Contenedor sin pasos COTIZADOR: se omite (no creamos ENTREGA aislada)
                    DB::rollBack();
                    $sinCambios++;
                    $this->line("#{$idContenedor}: sin pasos COTIZADOR iniciales, omitido");
                    continue;
                } else {
                    // Buscar posición después de COTIZACION FINAL si existe
                    $cotFin = $steps->first(fn($s) => strtoupper($s->name) === 'COTIZACION FINAL');
                    if ($cotFin) {
                        $targetOrder = $cotFin->id_order + 1; // insertar después de COTIZACION FINAL
                    } else {
                        // Insertar antes de FACTURA Y GUIA si existe
                        $factura = $steps->first(fn($s) => strtoupper($s->name) === 'FACTURA Y GUIA');
                        if ($factura) {
                            $targetOrder = $factura->id_order; // vamos a desplazar factura hacia adelante
                        } else {
                            // Al final
                            $targetOrder = ($steps->max('id_order') ?? 0) + 1;
                        }
                    }
                }

                // Desplazar los existentes >= targetOrder una posición arriba si insertamos en medio.
                // IMPORTANTE: se hace en orden DESC para evitar colisiones temporales si hay índice único sobre (id_pedido,tipo,id_order).
                $needsShift = $steps->where('id_order', '>=', $targetOrder)->sortByDesc('id_order');
                foreach ($needsShift as $st) {
                    ContenedorPasos::where('id', $st->id)->update(['id_order' => $st->id_order + 1]);
                }

                // Insertar ENTREGA
                $row = [
                    'id_pedido' => $idContenedor,
                    'id_order' => $targetOrder,
                    'tipo' => 'COTIZADOR',
                    'name' => 'ENTREGA',
                    'iconURL' => self::ENTREGA_ICON,
                    'status' => 'PENDING',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($dry) {
                    DB::rollBack();
                    $insertados++; // contamos como potencial
                    $this->line("#{$idContenedor}: (dry-run) INSERT ENTREGA en order {$targetOrder} (shift ".$needsShift->count()." filas)");
                } else {
                    ContenedorPasos::insert($row);
                    DB::commit();
                    $insertados++;
                    $this->line("#{$idContenedor}: ENTREGA insertada en order {$targetOrder}");
                }
            } catch (\Throwable $e) {
                $errores++;
                DB::rollBack();
                $this->error("#{$idContenedor}: error -> ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Resumen:");
        $this->line("  Insertados: {$insertados}");
        $this->line("  Ya existentes: {$sinCambios}");
        $this->line("  Errores: {$errores}");
        if ($dry) {
            $this->comment('Modo dry-run: no se aplicaron cambios reales.');
        }

        return $errores > 0 ? 1 : 0;
    }

    private function parseOnlyOption(string $only): array
    {
        $ids = [];
        foreach (explode(',', $only) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            if (preg_match('/^(\d+)-(\d+)$/', $chunk, $m)) {
                $start = (int)$m[1]; $end = (int)$m[2];
                if ($end >= $start) {
                    $ids = array_merge($ids, range($start, $end));
                }
            } elseif (ctype_digit($chunk)) {
                $ids[] = (int)$chunk;
            }
        }
        return array_values(array_unique($ids));
    }
}
