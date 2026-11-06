<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vista de China: la solicitud agrupa proveedores y guarda el dinero que llega del socio.
 */
return new class extends Migration
{
    private const MENU_URL = 'pago-proveedor/pagos-yuanes';

    public function up(): void
    {
        Schema::create('pago_proveedor_ingresos', function (Blueprint $table) {
            $table->id()->comment('Identificador del dinero recibido del socio');
            $table->unsignedBigInteger('request_id')->comment('Solicitud a la que pertenece el ingreso');
            $table->decimal('usd_amount', 12, 2)->comment('Dólares recibidos del socio');
            $table->decimal('exchange_rate', 12, 4)->comment('Tipo de cambio del banco, en yuanes por dólar');
            $table->decimal('yuan_amount', 12, 2)->comment('Yuanes obtenidos: dólares por tipo de cambio');
            $table->date('received_on')->comment('Fecha de negocio en que se registró el ingreso');
            $table->string('receipt_path')->nullable()->comment('Ruta del comprobante del ingreso');
            $table->string('receipt_name')->nullable()->comment('Nombre original del comprobante del ingreso');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Usuario de China que lo registró. Sin clave foránea');
            $table->timestamps();

            $table->foreign('request_id', 'pp_ingreso_solicitud_fk')
                ->references('id')->on('pago_proveedor_solicitudes')->cascadeOnDelete();
        });

        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->string('country', 40)->nullable()->after('partner')->comment('País del socio. Vacío en las solicitudes de Ventas: es Perú');
            $table->date('request_date')->nullable()->after('country')->comment('Fecha de la solicitud manual de China. Vacía usa la de creación');
        });

        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->string('client_name')->nullable()->after('supplier_name')->comment('Cliente del socio al que corresponde este proveedor');
        });

        Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->change();
            $table->string('receipt_name')->nullable()->change();
        });

        $this->renameMenu('Pago a proveedores', 'Pago a proveedores');
    }

    public function down(): void
    {
        $this->renameMenu('Pagos en yuanes', 'Yuan payments');

        Schema::table('pago_proveedor_ejecuciones', function (Blueprint $table) {
            $table->string('receipt_path')->nullable(false)->change();
            $table->string('receipt_name')->nullable(false)->change();
        });

        Schema::table('pago_proveedor_solicitud_proveedores', function (Blueprint $table) {
            $table->dropColumn('client_name');
        });

        Schema::table('pago_proveedor_solicitudes', function (Blueprint $table) {
            $table->dropColumn(['country', 'request_date']);
        });

        Schema::dropIfExists('pago_proveedor_ingresos');
    }

    private function renameMenu(string $name, string $chinaName): void
    {
        DB::table('menu')->where('url_intranet_v2', self::MENU_URL)->update([
            'No_Menu' => $name,
            'No_Menu_China' => $chinaName,
        ]);

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }
};
