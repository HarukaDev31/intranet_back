<?php

namespace Tests\Feature;

use App\Exceptions\ChinaBillingException;
use App\Services\ChinaCaja\ChinaCashService;
use App\Services\ChinaCaja\ChinaContainerService;
use App\Services\ChinaCaja\ChinaPendingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Recorrido completo del Encargado China: etapas → contenedores → caja → Inicio. */
class ChinaCajaTest extends TestCase
{
    private const ACTOR = ['id' => 9, 'name' => 'Armando', 'organization_ids' => [1]];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'china_caja',
            'database.connections.china_caja' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'china_billing.borradores_desde' => '2026-09-01',
        ]);
        Carbon::setTestNow('2026-11-16 04:00:00');
        CarbonImmutable::setTestNow('2026-11-16 04:00:00');
        $this->tables();
        DB::table('organizacion')->insert(['ID_Organizacion' => 1, 'No_Organizacion' => 'Pro Business', 'id_pais' => 1]);
        DB::table('pais')->insert(['ID_Pais' => 1, 'No_Pais' => 'PERU']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_la_reserva_espera_60_cbm_y_luego_alimenta_el_contenedor(): void
    {
        $id = $this->container('RECIBIENDO');
        $this->supplier($id, 40);
        $this->assertFails(fn () => $this->containers()->update($id, ['freight_usd' => 4500], [], self::ACTOR), 'Bloqueada hasta 60 CBM');

        $this->supplier($id, 20);
        $row = $this->containers()->update($id, ['freight_usd' => 4500, 'filling_on' => '2026-11-20', 'shipping_line' => 'nueva línea', 'arrival_on' => '2026-12-01'], [], self::ACTOR);

        $this->assertSame([4500.0, 'NUEVA LÍNEA'], [$row['data']['freight_usd'], $row['data']['shipping_line']]);
        $this->assertContains('NUEVA LÍNEA', $this->containers()->options()['lines']);
        $this->assertCount(1, $this->containers()->shipping([1]));
        $this->assertNull(DB::table('carga_consolidada_contenedor')->value('naviera'));
    }

    public function test_el_flete_vence_7_dias_antes_se_paga_una_vez_y_sale_de_pendientes(): void
    {
        $id = $this->container('COMPLETADO');
        $this->containers()->update($id, ['freight_usd' => 1000, 'arrival_on' => '2026-11-20', 'sailing_on' => '2026-10-20'], [], self::ACTOR);
        $this->assertFails(fn () => app(ChinaCashService::class)->rateFor('2026-11-16'), 'TC del mes');
        $this->cash()->setRate('2026-11', 7.1);

        $month = $this->cash()->month([1], '2026-11');
        $this->assertSame([7100.0, -3], [$month['late'][0]['amount'], $month['late'][0]['days']]);
        $this->assertContains('Atrasados', $this->kinds());
        $this->assertContains('Flete', $this->kinds());
        $this->assertContains('BL oficial', $this->kinds());

        $this->assertFails(fn () => $this->containers()->payFreight([$id], '2026-11-16', null, false, 7.1, self::ACTOR), 'voucher');
        $payment = $this->containers()->payFreight([$id], '2026-11-16', null, true, 7.1, self::ACTOR);
        $this->assertFails(fn () => $this->containers()->payFreight([$id], '2026-11-16', null, true, 7.1, self::ACTOR), 'ya está pagado');
        $this->assertFails(fn () => $this->containers()->update($id, ['freight_usd' => 900], [], self::ACTOR), 'anula el pago');

        $month = $this->cash()->month([1], '2026-11');
        $this->assertSame([], $month['late']);
        $this->assertSame(7100.0, $month['totals']['expense']);
        $this->assertNotContains('Flete', $this->kinds());

        $this->containers()->cancelFreight($payment, self::ACTOR);
        $this->assertContains('Flete', $this->kinds());
    }

    public function test_cobro_pendiente_confirmado_y_en_caja_recorren_el_flujo(): void
    {
        $id = $this->container('COMPLETADO');
        $this->assertContains('Por aprobar', $this->kinds());
        $this->containers()->update($id, ['arrival_on' => '2026-12-01'], [], self::ACTOR);
        $this->cash()->setRate('2026-11', 7);
        $op = DB::table('china_operations')->insertGetId(['code' => 'OP-0001', 'organization_id' => 1, 'service_name' => 'Consolidado', 'billing_type' => 'fixed',
            'container_id' => $id, 'issued_on' => '2026-11-10', 'description' => 'C', 'amount' => 500]);
        $row = $this->containers()->find($id, [1]);
        $this->assertSame(['pending', 1], [$row['collection'], $row['progress']['stages'][2]['done']]);
        $pending = $this->cash()->month([1], '2026-11')['pending'];
        $this->assertSame([3500.0, '2026-11-24', 8], [$pending[0]['amount'], $pending[0]['due_on'], $pending[0]['days']]);

        $pay = DB::table('china_payments')->insertGetId(['code' => 'PG-0001', 'organization_id' => 1, 'paid_on' => '2026-11-12', 'amount_sent' => 500,
            'amount_received' => 500, 'confirmed_at' => '2026-11-13 02:00:00', 'created_at' => now()]);
        DB::table('china_payment_allocations')->insert(['payment_id' => $pay, 'operation_id' => $op, 'amount' => 500]);
        $month = $this->cash()->month([1], '2026-11');
        $this->assertSame([], $month['pending']);
        $this->assertSame(500.0, $month['weeks'][1]['usd_to_exchange'][0]['usd']);
        $this->assertSame([], $month['weeks'][0]['usd_to_exchange']);
        $this->assertContains('Cambiar a RMB', $this->kinds());
        $this->assertSame('paid', $this->containers()->find($id, [1])['collection']);

        // 20:00 UTC del 15 ya es 16 en China.
        DB::table('china_payments')->where('id', $pay)->update(['exchange_rate' => 7.2, 'yuan_amount' => 3600, 'cashed_at' => '2026-11-15 20:00:00']);
        $month = $this->cash()->month([1], '2026-11');
        $this->assertSame(['2026-11-16', 3600.0], [$month['real'][0]['date'], $month['totals']['income']]);
        $this->assertNotContains('Cambiar a RMB', $this->kinds());
    }

    public function test_cierres_de_semana_y_mes_y_saldo_que_pasa_al_mes_siguiente(): void
    {
        Storage::fake('public');
        $this->fakeUpload();
        $this->cash()->setRate('2026-11', 7);
        $this->cash()->setOpening('2026-11', 1000);
        $this->assertContains('Semana sin cerrar', $this->kinds());
        $this->assertFails(fn () => $this->cash()->closeWeek('2026-11', '2026-11-16', 1, $this->file(), self::ACTOR), 'se cierra desde');
        $this->cash()->closeWeek('2026-11', '2026-11-01', 900, $this->file(), self::ACTOR);
        $this->assertSame(900.0, $this->cash()->month([1], '2026-11')['balance']);
        $this->assertFails(fn () => $this->cash()->closeWeek('2026-11', '2026-11-01', 1, $this->file(), self::ACTOR), 'ya está cerrada');
        $this->cash()->closeWeek('2026-11', '2026-11-09', 950, $this->file(), self::ACTOR);
        $this->assertNotContains('Semana sin cerrar', $this->kinds());
        $this->assertFails(fn () => $this->cash()->closeMonth('2026-11', 1, self::ACTOR), 'último día');

        $this->cash()->saveFixedCost(null, ['name' => 'Alquiler', 'amount' => 300]);
        $this->cash()->saveFixedCost(null, ['name' => 'Pausado', 'amount' => 50]);
        $this->cash()->saveFixedCost(2, ['active' => false]);
        Carbon::setTestNow('2026-11-30 04:00:00');
        CarbonImmutable::setTestNow('2026-11-30 04:00:00');
        $this->assertFails(fn () => $this->cash()->closeMonth('2026-11', 1, self::ACTOR), 'todas las semanas');
        $this->cash()->closeWeek('2026-11', '2026-11-16', 800, $this->file(), self::ACTOR);
        $this->cash()->closeWeek('2026-11', '2026-11-23', 700, $this->file(), self::ACTOR);
        $this->cash()->closeMonth('2026-11', 690, self::ACTOR);

        $this->assertFails(fn () => $this->cash()->setRate('2026-11', 8), 'cerrado');
        $history = $this->cash()->history([1], 2026);
        $this->assertSame([300.0, 700.0, 690.0, -10.0], [$history[0]['expense'], $history[0]['calculated'], $history[0]['bank'], $history[0]['difference']]);
        // Eliminar el costo no cambia lo ya descontado.
        $this->cash()->saveFixedCost(1, ['deleted_at' => now()]);
        $this->assertSame(300.0, $this->cash()->month([1], '2026-11')['totals']['expense']);
        Carbon::setTestNow('2026-12-01 04:00:00');
        CarbonImmutable::setTestNow('2026-12-01 04:00:00');
        $december = $this->cash()->month([1], '2026-12');
        $this->assertSame([690.0, false], [$december['opening_balance'], $december['opening_editable']]);
    }

    public function test_inversion_pagada_puede_repetirse(): void
    {
        $this->cash()->saveInvestment(['name' => 'Licencias', 'amount' => 100, 'due_on' => '2026-11-18']);
        $this->cash()->payInvestment(1, '2026-11-16', ['due_on' => '2027-11-18', 'amount' => 120]);
        $this->assertSame([100.0, '2027-11-18'], [$this->cash()->month([1], '2026-11')['totals']['expense'], DB::table('china_investments')->where('id', 2)->value('due_on')]);
        $this->assertFails(fn () => $this->cash()->payInvestment(1, '2026-11-16', null), 'ya está pagada');
    }

    public function test_proveedores_nc_llevan_a_seguimiento(): void
    {
        $this->supplier($this->container('RECIBIENDO'), 0, 'NC');
        $row = collect(app(ChinaPendingService::class)->list([1]))->firstWhere('section', 'Seguimiento');
        $this->assertSame(['1 en NC', '/seguimiento-china?estado=NC'], [$row['tag'], $row['to']]);
    }

    private function cash(): ChinaCashService
    {
        return app(ChinaCashService::class);
    }

    private function containers(): ChinaContainerService
    {
        return app(ChinaContainerService::class);
    }

    /** @return array<int, string> */
    private function kinds(): array
    {
        return array_column(app(ChinaPendingService::class)->list([1]), 'kind');
    }

    private function assertFails(callable $action, string $message): void
    {
        try {
            $action();
        } catch (ChinaBillingException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());

            return;
        }
        $this->fail('Se esperaba: '.$message);
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->create('banco.pdf', 5, 'application/pdf');
    }

    private function fakeUpload(): void
    {
        $this->app->bind(ChinaContainerService::class, fn ($app) => new class($app->make(\App\Services\ChinaBilling\ChinaBillingReader::class), $app->make(\App\Services\ChinaBilling\ChinaDraftService::class)) extends ChinaContainerService {
            public function storeFile(UploadedFile $file, string $directory, string $prefix): string
            {
                return $directory.'/'.$prefix;
            }
        });
    }

    private function container(string $status): int
    {
        return (int) DB::table('carga_consolidada_contenedor')->insertGetId(['carga' => '21', 'id_pais' => 1, 'organizacion_id' => 1, 'empresa' => 'PRO MUNDO',
            'estado_china' => $status, 'f_cierre' => '2026-11-01', 'f_inicio' => '2026-10-01']);
    }

    private function supplier(int $containerId, float $cbm, string $status = 'R'): void
    {
        $quote = DB::table('contenedor_consolidado_cotizacion')->insertGetId(['id_contenedor' => $containerId, 'estado_cotizador' => 'CONFIRMADO', 'nombre' => 'Ana']);
        DB::table('contenedor_consolidado_cotizacion_proveedores')->insert(['id_cotizacion' => $quote, 'id_contenedor' => $containerId, 'cbm_total_china' => $cbm, 'estados_proveedor' => $status]);
    }

    private function tables(): void
    {
        $make = function (string $name, array $columns, string $key = 'id') {
            Schema::create($name, function (Blueprint $table) use ($columns, $key) {
                $table->increments($key);
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        };
        $make('organizacion', ['No_Organizacion', 'id_pais'], 'ID_Organizacion');
        Schema::table('organizacion', fn (Blueprint $table) => $table->boolean('facturable_china')->default(true));
        $make('pais', ['No_Pais'], 'ID_Pais');
        $make('contenedor_consolidado_cotizacion', ['id_contenedor', 'estado_cotizador', 'nombre', 'deleted_at']);
        Schema::create('contenedor_consolidado_cotizacion_proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_cotizacion');
            $table->unsignedInteger('id_contenedor');
            $table->decimal('cbm_total_china', 10, 3)->default(0);
            $table->string('estados_proveedor')->nullable();
        });
        Schema::create('carga_consolidada_contenedor', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_pais');
            $table->unsignedInteger('organizacion_id');
            foreach (['carga', 'parte', 'empresa', 'estado_china', 'f_cierre', 'f_inicio', 'lista_embarque_uploaded_at', 'fecha_zarpe', 'fecha_arribo', 'naviera', 'deleted_at'] as $column) {
                $table->string($column)->nullable();
            }
        });
        $make('pago_proveedor_solicitud_proveedores', ['request_id', 'currency']);
        $make('pago_proveedor_ingresos', ['request_id', 'usd_amount', 'exchange_rate', 'yuan_amount', 'received_on']);
        $make('pago_proveedor_ejecuciones', ['request_provider_id', 'yuan_paid', 'executed_on']);
        (require database_path('migrations/2026_11_10_100000_create_china_billing_tables.php'))->up();
        (require database_path('migrations/2026_11_24_100000_create_china_cash_and_container_tables.php'))->up();
    }
}
