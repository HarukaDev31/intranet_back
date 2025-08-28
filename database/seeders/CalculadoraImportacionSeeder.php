<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionProveedor;
use App\Models\CalculadoraImportacionProducto;

class CalculadoraImportacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear cálculo de ejemplo
        $calculadora = CalculadoraImportacion::create([
            'nombre_cliente' => 'JENDY ROKY GONZALES MATIAS',
            'dni_cliente' => '47456666',
            'correo_cliente' => 'jendy@example.com',
            'whatsapp_cliente' => '51902843298',
            'tipo_cliente' => 'NUEVO',
            'qty_proveedores' => 6,
            'tarifa_total_extra_proveedor' => 150.00,
            'tarifa_total_extra_item' => 0.00
        ]);

        // Crear proveedores de ejemplo
        for ($i = 1; $i <= 6; $i++) {
            $proveedor = $calculadora->proveedores()->create([
                'cbm' => 1.2,
                'peso' => 100.0,
                'qty_caja' => 10
            ]);

            // Crear productos para cada proveedor
            $proveedor->productos()->create([
                'nombre' => "Producto {$i}",
                'precio' => 10.00,
                'valoracion' => 0,
                'cantidad' => 100,
                'antidumping_cu' => 0.00,
                'ad_valorem_p' => 0.00
            ]);
        }

        // Crear otro cálculo de ejemplo
        $calculadora2 = CalculadoraImportacion::create([
            'nombre_cliente' => 'MARIA GARCIA LOPEZ',
            'dni_cliente' => '12345678',
            'correo_cliente' => 'maria@example.com',
            'whatsapp_cliente' => '51987654321',
            'tipo_cliente' => 'RECURRENTE',
            'qty_proveedores' => 3,
            'tarifa_total_extra_proveedor' => 75.00,
            'tarifa_total_extra_item' => 25.00
        ]);

        // Crear proveedores para el segundo cálculo
        for ($i = 1; $i <= 3; $i++) {
            $proveedor = $calculadora2->proveedores()->create([
                'cbm' => 0.8,
                'peso' => 75.0,
                'qty_caja' => 5
            ]);

            // Crear productos para cada proveedor
            $proveedor->productos()->create([
                'nombre' => "Artículo {$i}",
                'precio' => 25.00,
                'valoracion' => 5,
                'cantidad' => 50,
                'antidumping_cu' => 2.50,
                'ad_valorem_p' => 5.00
            ]);
        }
    }
}
