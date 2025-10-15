<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateEstadosProveedor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proveedores:update-estados';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualizar estados de proveedores según la nueva lógica';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando actualización de estados de proveedores...');

        // Obtener todos los registros con estado 'R'
        $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->whereIn('estados_proveedor', ['R', 'NC',  'WAIT'])
            ->where('id_contenedor', '>=', 130)
            ->get();

        $this->info("Encontrados {$proveedores->count()} proveedores con estado 'R'");

        $actualizados = 0;

        foreach ($proveedores as $proveedor) {
            $nuevoEstado = $this->determinarNuevoEstado($proveedor);
            
            if ($nuevoEstado !== 'R') {
                try {
                    DB::table('contenedor_consolidado_cotizacion_proveedores')
                        ->where('id', $proveedor->id)
                        ->update(['estados_proveedor' => $nuevoEstado]);
                    
                    $actualizados++;
                    
                    if ($actualizados % 10 == 0) {
                        $this->info("Actualizados {$actualizados} registros...");
                    }
                } catch (\Exception $e) {
                    $this->error("Error actualizando proveedor ID {$proveedor->id}: " . $e->getMessage());
                }
            }
        }

        $this->info("Actualización completada. {$actualizados} registros actualizados.");
    }

    /**
     * Determinar el nuevo estado según la lógica especificada
     */
    private function determinarNuevoEstado($proveedor)
    {
        // Validar qty_box_china - considerar nulo como 0
        $qtyBoxChina = 0;
        if ($proveedor->qty_box_china !== null && trim($proveedor->qty_box_china) !== '') {
            $qtyBoxChina = (int)$proveedor->qty_box_china;
        }

        // Validar cbm_total_china - considerar nulo como 0
        $cbmTotalChina = 0;
        if ($proveedor->cbm_total_china !== null && trim($proveedor->cbm_total_china) !== '') {
            $cbmTotalChina = (float)$proveedor->cbm_total_china;
        }

        // Validar supplier - debe ser no nulo y no vacío después de trim
        $supplier = false;
        if ($proveedor->supplier !== null && trim($proveedor->supplier) !== '') {
            $supplier = true;
        }

        // Validar supplier_phone - debe ser no nulo y no vacío después de trim
        $supplierPhone = false;
        if ($proveedor->supplier_phone !== null && trim($proveedor->supplier_phone) !== '') {
            $supplierPhone = true;
        }
        
        // Verificar arrive_date_china de forma segura (manejar fechas inválidas)
        $arriveDateChina = false;
        if ($proveedor->arrive_date_china !== null && 
            trim($proveedor->arrive_date_china) !== '' &&
            $proveedor->arrive_date_china !== '0000-00-00' && 
            $proveedor->arrive_date_china !== '0000-00-00 00:00:00') {
            try {
                $date = new \DateTime($proveedor->arrive_date_china);
                $arriveDateChina = true;
            } catch (\Exception $e) {
                $arriveDateChina = false;
            }
        }

        // Si tiene qty_box_china o cbm_total_china, se queda en R
        if ($qtyBoxChina > 0 || $cbmTotalChina > 0) {
            return 'R';
        }

        // Si no tiene qty_box_china ni cbm_total_china (o están en 0)
        // Verificar si tiene supplier y supplier_phone
        if ($supplier && $supplierPhone) {
            // Si tiene arrive_date_china, poner en C
            if ($arriveDateChina) {
                return 'C';
            }
            // Si no tiene arrive_date_china, poner en NC
            return 'NC';
        }

        // Si no tiene supplier ni supplier_phone, poner en WAIT
        return 'WAIT';
    }
}