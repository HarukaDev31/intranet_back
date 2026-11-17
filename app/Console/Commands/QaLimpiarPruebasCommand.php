<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia los datos de prueba de QA de Almacén China (operaciones y cobros) y de Pago a Proveedor
 * (cotizaciones y solicitudes), dejando los N registros más recientes (o los ids indicados) con todo lo relacionado.
 * Solo corre en QA: se niega en producción.
 */
class QaLimpiarPruebasCommand extends Command
{
    protected $signature = 'qa:limpiar-pruebas
        {--keep=2 : Cuántas operaciones y solicitudes (las más recientes) se conservan}
        {--operaciones= : Ids de operaciones de China a conservar, separados por coma (reemplaza a --keep)}
        {--solicitudes= : Ids de solicitudes de Pago a Proveedor a conservar, separados por coma (reemplaza a --keep)}
        {--dry-run : Solo muestra cuántos registros se borrarían}
        {--force : No pide confirmación}';

    protected $description = 'QA: borra los datos de prueba de Almacén China y Pago a Proveedor y deja unos pocos ejemplos';

    /** @var array<string, array<int, string>> columnas que el comando usa (se verifican antes de borrar nada) */
    private const COLUMNAS = [
        'china_operations' => ['id', 'transferred_to_operation_id'],
        'china_payments' => ['id', 'shortfall_operation_id'],
        'china_payment_allocations' => ['payment_id', 'operation_id'],
        'china_credit_applications' => ['payment_id', 'operation_id', 'from_operation_id'],
        'china_operation_costs' => ['operation_id', 'source_operation_id'],
        'china_documents' => ['operation_id'],
        'china_draft_dismissals' => ['id'],
        'pago_proveedor_cotizaciones' => ['id'],
        'pago_proveedor_cotizacion_proveedores' => ['quote_id'],
        'pago_proveedor_solicitudes' => ['id', 'quote_id'],
        'pago_proveedor_solicitud_proveedores' => ['id', 'request_id'],
        'pago_proveedor_datos_proveedor' => ['request_provider_id'],
        'pago_proveedor_abonos' => ['id', 'request_id'],
        'pago_proveedor_abono_aplicaciones' => ['deposit_id', 'request_provider_id'],
        'pago_proveedor_ejecuciones' => ['request_provider_id'],
        'pago_proveedor_ingresos' => ['request_id'],
        'pago_proveedor_reembolsos' => ['request_id'],
        'pago_proveedor_mensajes' => ['request_id', 'quote_id'],
        'pago_proveedor_historial' => ['entity_type', 'entity_id'],
        'pago_proveedor_documentos' => ['entity_type', 'entity_id'],
    ];

    public function handle(): int
    {
        $url = (string) config('app.url');
        if (app()->environment('production') || !(str_contains($url, 'qa-intranetback') || app()->environment(['local', 'testing']))) {
            $this->error('Este comando solo corre en QA (APP_URL con qa-intranetback). Entorno actual: ' . app()->environment() . ' · ' . $url);

            return self::FAILURE;
        }

        foreach (self::COLUMNAS as $tabla => $columnas) {
            foreach ($columnas as $columna) {
                if (!Schema::hasColumn($tabla, $columna)) {
                    $this->error("Falta la columna {$tabla}.{$columna}: no se borra nada.");

                    return self::FAILURE;
                }
            }
        }

        $keep = max(0, (int) $this->option('keep'));
        $ops = $this->ids((string) $this->option('operaciones')) ?: DB::table('china_operations')->orderByDesc('id')->limit($keep)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sols = $this->ids((string) $this->option('solicitudes')) ?: DB::table('pago_proveedor_solicitudes')->orderByDesc('id')->limit($keep)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $pagos = array_values(array_unique(array_merge(
            DB::table('china_payment_allocations')->whereIn('operation_id', $ops)->pluck('payment_id')->all(),
            DB::table('china_credit_applications')->whereIn('operation_id', $ops)->pluck('payment_id')->all()
        )));
        $provs = DB::table('pago_proveedor_solicitud_proveedores')->whereIn('request_id', $sols)->pluck('id')->all();
        $cotis = DB::table('pago_proveedor_solicitudes')->whereIn('id', $sols)->pluck('quote_id')->filter()->all();
        $abonos = DB::table('pago_proveedor_abonos')->whereIn('request_id', $sols)->pluck('id')->all();

        $this->info('Se conservan → operaciones China: ' . (implode(', ', $ops) ?: '—') . ' · cobros: ' . (implode(', ', $pagos) ?: '—') . ' · solicitudes: ' . (implode(', ', $sols) ?: '—'));

        $borrados = [
            'china_credit_applications' => DB::table('china_credit_applications')->where(fn ($q) => $q->whereNotIn('operation_id', $ops)->orWhere(fn ($w) => $w->whereNotNull('from_operation_id')->whereNotIn('from_operation_id', $ops))),
            'china_payment_allocations' => DB::table('china_payment_allocations')->whereNotIn('operation_id', $ops),
            'china_operation_costs' => DB::table('china_operation_costs')->whereNotIn('operation_id', $ops),
            'china_documents' => DB::table('china_documents')->where(fn ($q) => $q->whereNull('operation_id')->orWhereNotIn('operation_id', $ops)),
            'china_draft_dismissals' => DB::table('china_draft_dismissals'),
            'china_payments' => DB::table('china_payments')->whereNotIn('id', $pagos),
            'china_operations' => DB::table('china_operations')->whereNotIn('id', $ops),
            'pago_proveedor_abono_aplicaciones' => DB::table('pago_proveedor_abono_aplicaciones')->whereNotIn('deposit_id', $abonos),
            'pago_proveedor_ejecuciones' => DB::table('pago_proveedor_ejecuciones')->whereNotIn('request_provider_id', $provs),
            'pago_proveedor_ingresos' => DB::table('pago_proveedor_ingresos')->whereNotIn('request_id', $sols),
            'pago_proveedor_reembolsos' => DB::table('pago_proveedor_reembolsos')->whereNotIn('request_id', $sols),
            'pago_proveedor_datos_proveedor' => DB::table('pago_proveedor_datos_proveedor')->whereNotIn('request_provider_id', $provs),
            'pago_proveedor_abonos' => DB::table('pago_proveedor_abonos')->whereNotIn('request_id', $sols),
            'pago_proveedor_solicitud_proveedores' => DB::table('pago_proveedor_solicitud_proveedores')->whereNotIn('request_id', $sols),
            'pago_proveedor_mensajes' => DB::table('pago_proveedor_mensajes')->where(fn ($q) => $q->where(fn ($w) => $w->whereNull('request_id')->orWhereNotIn('request_id', $sols))->where(fn ($w) => $w->whereNull('quote_id')->orWhereNotIn('quote_id', $cotis))),
            'pago_proveedor_historial' => DB::table('pago_proveedor_historial')->where(fn ($q) => $q->where(fn ($w) => $w->where('entity_type', 'solicitud')->whereNotIn('entity_id', $sols))->orWhere(fn ($w) => $w->where('entity_type', 'cotizacion')->whereNotIn('entity_id', $cotis))->orWhereNotIn('entity_type', ['solicitud', 'cotizacion'])),
            'pago_proveedor_documentos' => DB::table('pago_proveedor_documentos')->where(fn ($q) => $q->where(fn ($w) => $w->where('entity_type', 'solicitud')->whereNotIn('entity_id', $sols))->orWhere(fn ($w) => $w->where('entity_type', 'cotizacion')->whereNotIn('entity_id', $cotis))->orWhereNotIn('entity_type', ['solicitud', 'cotizacion'])),
            'pago_proveedor_solicitudes' => DB::table('pago_proveedor_solicitudes')->whereNotIn('id', $sols),
            'pago_proveedor_cotizacion_proveedores' => DB::table('pago_proveedor_cotizacion_proveedores')->whereNotIn('quote_id', $cotis),
            'pago_proveedor_cotizaciones' => DB::table('pago_proveedor_cotizaciones')->whereNotIn('id', $cotis),
        ];

        $filas = [];
        foreach ($borrados as $tabla => $consulta) {
            $filas[] = [$tabla, (clone $consulta)->count()];
        }
        $this->table(['Tabla', 'Se borran'], $filas);

        if ($this->option('dry-run')) {
            $this->comment('--dry-run: no se borró nada.');

            return self::SUCCESS;
        }
        if (!$this->option('force') && !$this->confirm('¿Borrar estos registros de QA? No se puede deshacer.')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($borrados, $ops, $pagos): void {
            foreach ($borrados as $tabla => $consulta) {
                if ($tabla === 'china_operations') {
                    // Lo que se conserva no puede apuntar a lo que se borra.
                    DB::table('china_operations')->whereIn('id', $ops)->whereNotNull('transferred_to_operation_id')->whereNotIn('transferred_to_operation_id', $ops)->update(['transferred_to_operation_id' => null]);
                    DB::table('china_operation_costs')->whereIn('operation_id', $ops)->whereNotNull('source_operation_id')->whereNotIn('source_operation_id', $ops)->update(['source_operation_id' => null]);
                    DB::table('china_payments')->whereIn('id', $pagos)->whereNotNull('shortfall_operation_id')->whereNotIn('shortfall_operation_id', $ops)->update(['shortfall_operation_id' => null]);
                }
                $consulta->delete();
            }
        });

        $this->info('Listo: datos de prueba de QA limpiados.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function ids(string $lista): array
    {
        return array_values(array_filter(array_map('intval', array_map('trim', explode(',', $lista)))));
    }
}
