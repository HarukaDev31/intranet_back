<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\CalculadoraImportacion;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Support\CargaConsolidada\CargaLabel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * @return LengthAwarePaginator<int, CalculadoraImportacion>
     */
    public function search(string $term, int $perPage): LengthAwarePaginator
    {
        $perPage = min(20, max(1, $perPage));
        $term = trim($term);
        $query = CalculadoraImportacion::query()
            ->with(['cotizacion', 'contenedor'])
            ->whereNotNull('cod_cotizacion')
            ->where('cod_cotizacion', '!=', '');

        if (mb_strlen($term) >= 2) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('cod_cotizacion', 'like', $like)
                    ->orWhere('nombre_cliente', 'like', $like)
                    ->orWhere('razon_social', 'like', $like)
                    ->orWhere('dni_cliente', 'like', $like)
                    ->orWhere('ruc_cliente', 'like', $like)
                    ->orWhereHas('cotizacion', function ($quote) use ($like): void {
                        $quote->where('nombre', 'like', $like)
                            ->orWhere('documento', 'like', $like);
                    });
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    /**
     * @return array{cod_cotizacion_cc: string, cliente_nombre: ?string, cliente_documento: ?string, consolidado: ?string}
     */
    public function suggestion(CalculadoraImportacion $calculator): array
    {
        $quote = $calculator->cotizacion;
        $name = $calculator->razon_social ?: $calculator->nombre_cliente;
        $document = $calculator->dni_cliente ?: $calculator->ruc_cliente;
        if ($quote instanceof Cotizacion) {
            $name = $quote->nombre ?: $name;
            $document = $quote->documento ?: $document;
        }

        return [
            'cod_cotizacion_cc' => strtoupper((string) $calculator->cod_cotizacion),
            'cliente_nombre' => $name !== null && $name !== '' ? (string) $name : null,
            'cliente_documento' => $document !== null && $document !== '' ? (string) $document : null,
            'consolidado' => $this->consolidationLabel($calculator->contenedor),
        ];
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
        $product = trim((string) $provider->products);
        if ($product === '') {
            $provider->loadMissing('items');
            $product = $provider->items
                ->map(function ($item) {
                    $name = trim((string) ($item->final_name ?: $item->initial_name ?: $item->tipo_producto));

                    return $name;
                })
                ->filter()
                ->unique()
                ->implode(', ');
        }

        return [
            'id_proveedor_cc' => $provider->id,
            'code_supplier' => $provider->code_supplier,
            'producto' => $product !== '' ? $product : null,
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
