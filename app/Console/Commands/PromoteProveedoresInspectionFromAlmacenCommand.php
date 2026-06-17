<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromoteProveedoresInspectionFromAlmacenCommand extends Command
{
    protected $signature = 'proveedores:promote-inspection-from-almacen
                            {--contenedor= : Solo proveedores de este consolidado}
                            {--dry-run : Simular sin actualizar}
                            {--limit=0 : Máximo de proveedores a procesar (0 = sin límite)}';

    protected $description = 'Pasa a INSPECTION los proveedores en R/NC/WAIT que ya tienen archivos en contenedor_consolidado_almacen_inspection';

    /** @var array<int, string> */
    private const ESTADOS_ORIGEN = ['R', 'NC', 'WAIT'];

    public function handle(): int
    {
        if (!Schema::hasTable('contenedor_consolidado_almacen_inspection')) {
            $this->error('La tabla contenedor_consolidado_almacen_inspection no existe.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $idContenedor = $this->option('contenedor');
        $idContenedor = ($idContenedor !== null && $idContenedor !== '') ? (int) $idContenedor : null;

        if ($dryRun) {
            $this->warn('Modo dry-run: no se aplicarán cambios.');
        }

        $query = DB::table('contenedor_consolidado_cotizacion_proveedores as p')
            ->whereIn('p.estados_proveedor', self::ESTADOS_ORIGEN)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('contenedor_consolidado_almacen_inspection as i')
                    ->whereColumn('i.id_proveedor', 'p.id');
            })
            ->select('p.id', 'p.id_cotizacion', 'p.id_contenedor', 'p.estados_proveedor', 'p.estados', 'p.code_supplier')
            ->orderBy('p.id');

        if ($idContenedor !== null) {
            $query->where('p.id_contenedor', $idContenedor);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $proveedores = $query->get();

        if ($proveedores->isEmpty()) {
            $this->info('No hay proveedores en R/NC/WAIT con archivos de inspección en almacén.');

            return self::SUCCESS;
        }

        $this->info('Candidatos: ' . $proveedores->count());

        $actualizados = 0;

        foreach ($proveedores as $proveedor) {
            $line = sprintf(
                'id=%d cont=%s cot=%s | %s → INSPECTION | estados %s → INSPECCIONADO | inspecciones en almacén',
                $proveedor->id,
                $proveedor->id_contenedor ?? '-',
                $proveedor->id_cotizacion ?? '-',
                $proveedor->estados_proveedor,
                $proveedor->estados ?? 'null'
            );

            if ($dryRun) {
                $this->line('[dry-run] ' . $line);
                $actualizados++;

                continue;
            }

            try {
                DB::table('contenedor_consolidado_cotizacion_proveedores')
                    ->where('id', $proveedor->id)
                    ->whereIn('estados_proveedor', self::ESTADOS_ORIGEN)
                    ->update([
                        'estados_proveedor' => 'INSPECTION',
                        'estados' => 'INSPECCIONADO',
                    ]);

                $actualizados++;
                $this->line($line);
            } catch (\Throwable $e) {
                $this->error("Error id={$proveedor->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Simulados' : 'Actualizados') . ": {$actualizados}");

        return self::SUCCESS;
    }
}
