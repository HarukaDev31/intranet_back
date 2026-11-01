<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\CalculadoraImportacion;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Support\CargaConsolidada\CargaLabel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class PagoProveedorCotizacionCcLookup
{
    public function __construct(
        private readonly PagoProveedorCacheService $cache,
    ) {
    }

    public function forget(string $code): void
    {
        $this->cache->forgetConsolidatedQuote($code);
    }

    /**
     * @return array<string, mixed>
     */
    public function findByCode(string $code): array
    {
        $code = strtoupper(trim($code));

        return $this->cache->rememberConsolidatedQuote($code, fn () => $this->load($code));
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $code): array
    {
        if (!preg_match('/^CO\d{8}$/', $code)) {
            throw new PagoProveedorException('El número de cotización de Consolidada debe tener el formato CO y 8 dígitos.');
        }

        $calculator = CalculadoraImportacion::query()
            ->with(['cotizacion.contenedor', 'cotizacion.proveedores', 'proveedores', 'contenedor'])
            ->where('cod_cotizacion', $code)
            ->first();

        if (!$calculator instanceof CalculadoraImportacion) {
            throw new PagoProveedorException('Ese número no existe en Carga Consolidada.', 404);
        }

        $quote = $calculator->cotizacion;
        $container = $calculator->contenedor;
        if ($quote instanceof Cotizacion) {
            $relation = $quote->contenedor();
            $loaded = $relation instanceof Relation ? $relation->getResults() : null;
            if ($loaded !== null) {
                $container = $loaded;
            }
        }
        $name = $calculator->razon_social ?: $calculator->nombre_cliente;
        $document = $calculator->dni_cliente ?: $calculator->ruc_cliente;
        $whatsapp = $calculator->whatsapp_cliente;
        $email = $calculator->correo_cliente;
        $quoteId = null;

        if ($quote instanceof Cotizacion) {
            $name = $quote->nombre ?: $name;
            $document = $quote->documento ?: $document;
            $whatsapp = $quote->telefono ?: $whatsapp;
            $email = $quote->correo ?: $email;
            $quoteId = $quote->id;
        }

        $alreadyUsed = PagoProveedorCotizacion::query()
            ->where('consolidated_quote_code', $code)
            ->exists();

        return [
            'cod_cotizacion_cc' => $code,
            'calculadora_importacion_id' => $calculator->id,
            'id_cotizacion_cc' => $quoteId,
            'cliente' => [
                'nombre' => $name,
                'documento' => $document,
                'whatsapp' => $whatsapp,
                'correo' => $email,
            ],
            'consolidado' => $this->consolidationLabel($container),
            'proveedores' => $this->existingProviders($calculator, $quote instanceof Cotizacion ? $quote : null),
            'ya_usada' => $alreadyUsed,
            'aviso' => $alreadyUsed
                ? 'Ya existe una cotización de pago a proveedor con este número. Puedes continuar.'
                : null,
        ];
    }

    /**
     * Suppliers already created in Carga Consolidada. This module does not invent providers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function existingProviders(CalculadoraImportacion $calculator, ?Cotizacion $quote): array
    {
        if ($quote instanceof Cotizacion) {
            $relation = $quote->proveedores();
            $providers = $relation instanceof Relation ? $relation->getResults() : null;
            if ($providers instanceof Collection && $providers->isNotEmpty()) {
                return $providers
                    ->filter(fn ($provider) => $provider instanceof CotizacionProveedor && trim((string) $provider->code_supplier) !== '')
                    ->map(fn ($provider) => $provider instanceof CotizacionProveedor ? $this->providerRow($provider) : [])
                    ->values()
                    ->all();
            }
        }

        $linkedIds = $calculator->proveedores
            ->pluck('id_proveedor')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($linkedIds === []) {
            return [];
        }

        return CotizacionProveedor::query()
            ->whereIn('id', $linkedIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (CotizacionProveedor $provider) => trim((string) $provider->code_supplier) !== '')
            ->map(fn (CotizacionProveedor $provider) => $this->providerRow($provider))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function providerRow(CotizacionProveedor $provider): array
    {
        return [
            'id_proveedor_cc' => $provider->id,
            'code_supplier' => $provider->code_supplier,
            'producto' => $provider->products,
            'nombre' => $provider->supplier,
        ];
    }

    private function consolidationLabel(mixed $container): ?string
    {
        if (!$container instanceof \Illuminate\Database\Eloquent\Model) {
            return null;
        }

        $label = CargaLabel::format($container->getAttribute('carga') ?? '', $container->getAttribute('f_inicio') ?? null, $container->getAttribute('parte') ?? null);
        if (preg_match('/^(.+)-(\d{4})$/', $label, $matches)) {
            return $matches[1] . ' - ' . $matches[2];
        }

        return $label !== '' ? $label : null;
    }
}
