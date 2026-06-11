<?php

namespace App\Console\Commands;

use App\Jobs\ProcesarCorteSeguimientoDatosProveedorJob;
use Illuminate\Console\Command;

class SeguimientoConsolidadoCorteDatosProveedorCommand extends Command
{
    protected $signature = 'segimiento-consolidado:corte-datos-proveedor {--contenedor=}';

    protected $description = 'Encola el corte diario (20:00 Perú) de clientes DATOS PROVEEDOR y sync Excel en Drive';

    /**
     * @return int
     */
    public function handle()
    {
        $id = $this->option('contenedor');
        ProcesarCorteSeguimientoDatosProveedorJob::dispatch($id !== null ? (int) $id : null);
        $this->info('Job de corte DATOS PROVEEDOR encolado.');

        return 0;
    }
}
