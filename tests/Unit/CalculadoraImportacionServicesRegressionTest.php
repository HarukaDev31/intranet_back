<?php

namespace Tests\Unit;

use App\Services\CalculadoraImportacion\CalculadoraImportacionExcelService;
use App\Services\CalculadoraImportacion\CalculadoraImportacionWhatsappService;
use App\Services\CalculadoraImportacion\ClienteWhatsappLookupService;
use App\Services\CalculadoraImportacionService;
use Tests\TestCase;

class CalculadoraImportacionServicesRegressionTest extends TestCase
{
    public function test_increment_column_regression(): void
    {
        $excel = new CalculadoraImportacionExcelService(app(CalculadoraImportacionService::class));

        $this->assertSame('D', $excel->incrementColumn('C'));
        $this->assertSame('Z', $excel->incrementColumn('Y'));
        $this->assertSame('AA', $excel->incrementColumn('Z'));
        $this->assertSame('AB', $excel->incrementColumn('AA'));
        $this->assertSame('BA', $excel->incrementColumn('AZ'));
    }

    public function test_generate_code_supplier_regression(): void
    {
        $excel = new CalculadoraImportacionExcelService(app(CalculadoraImportacionService::class));

        $code = $excel->generateCodeSupplier('Juan Perez', '05', '05', 1);
        $this->assertSame('JUPE5-1', $code);

        // Palabras cortas/no estándar: debe generar lo que pueda y concatenar igual.
        $code2 = $excel->generateCodeSupplier('A Bc', 'X', 'X', 2);
        $this->assertSame('BCX-2', $code2);
    }

    public function test_siguiente_indice_code_supplier_respecto_al_maximo_existente(): void
    {
        $calc = app(CalculadoraImportacionService::class);
        $excel = new CalculadoraImportacionExcelService($calc);

        $this->assertSame('JUPE5', $calc->codeSupplierBasePrefix('Juan Perez', '5'));
        $max = $calc->maxCodeSupplierSuffixForBase('JUPE5', ['JUPE5-1', 'JUPE5-4', 'JUPE5-7', 'OTRO9-1']);
        $this->assertSame(7, $max);

        $siguiente = $max + 1;
        $nuevo = $excel->generateCodeSupplier('Juan Perez', '5', '', $siguiente);
        $this->assertSame('JUPE5-8', $nuevo);
    }

    public function test_format_whatsapp_number_regression(): void
    {
        $wa = new CalculadoraImportacionWhatsappService();

        $this->assertSame('51987654321@c.us', $wa->formatWhatsAppNumber('987654321'));
        $this->assertSame('51987654321@c.us', $wa->formatWhatsAppNumber('+51 987-654-321'));
        $this->assertSame('51987654321@c.us', $wa->formatWhatsAppNumber('0987654321'));
    }

    public function test_determinar_categoria_cliente_regression(): void
    {
        $svc = new ClienteWhatsappLookupService();

        $this->assertSame('NUEVO', $svc->determinarCategoriaCliente([]));

        $this->assertSame('RECURRENTE', $svc->determinarCategoriaCliente([
            ['servicio' => 'Consolidado', 'fecha' => now()->subDays(1)->toDateString()],
        ]));

        $this->assertSame('INACTIVO', $svc->determinarCategoriaCliente([
            ['servicio' => 'Consolidado', 'fecha' => now()->subMonths(10)->toDateString()],
            ['servicio' => 'Curso', 'fecha' => now()->subMonths(8)->toDateString()],
        ]));
    }
}

