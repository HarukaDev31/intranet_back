<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDocumento;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PagoProveedorCotizacionService
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuracion,
        private readonly PagoProveedorVigenciaService $vigencia,
        private readonly PagoProveedorHistorialService $historial,
        private readonly PagoProveedorCotizacionCcLookup $lookup,
    ) {
    }

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = PagoProveedorCotizacion::query()->with(['proveedores.consolidatedProvider', 'solicitud'])->orderByDesc('id');

        // A manual China request keeps an internal quote that Sales never works with (RN-97).
        $query->whereDoesntHave('solicitud', fn ($request) => $request->where('origin', PagoProveedorSolicitud::ORIGIN_MANUAL));

        if (!empty($filtros['search'])) {
            $texto = '%' . trim((string) $filtros['search']) . '%';
            $query->where(function ($q) use ($texto) {
                $q->where('code', 'like', $texto)
                    ->orWhere('consolidated_quote_code', 'like', $texto)
                    ->orWhere('client_name', 'like', $texto)
                    ->orWhere('consolidation', 'like', $texto);
            });
        }

        $consolidado = trim((string) ($filtros['consolidado'] ?? ''));
        if ($consolidado !== '' && $consolidado !== 'todos') {
            $query->where('consolidation', $consolidado);
        }

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
            if ($filtros['estado'] === PagoProveedorCotizacion::FILTER_EXPIRED) {
                $query->where('status', PagoProveedorCotizacion::STATUS_PENDING)->where('valid_until', '<=', now());
            } elseif ($filtros['estado'] === PagoProveedorCotizacion::STATUS_PENDING) {
                $query->where('status', PagoProveedorCotizacion::STATUS_PENDING)->where('valid_until', '>', now());
            } else {
                $query->where('status', $filtros['estado']);
            }
        }

        $resaltarDesde = max(0, (int) ($filtros['resaltar_desde'] ?? 0));
        if ($resaltarDesde > 0 && !empty($filtros['solo_resaltadas'])) {
            $limite = now()->subDays($resaltarDesde)->startOfDay();
            $query->where('status', PagoProveedorCotizacion::STATUS_PENDING)
                ->whereDate('created_at', '<=', $limite);
        }

        return $query->paginate((int) ($filtros['per_page'] ?? 15));
    }

    /**
     * Consolidados distintos para el filtro del listado.
     *
     * @return array<int, string>
     */
    public function consolidadosDisponibles(): array
    {
        return PagoProveedorCotizacion::query()
            ->whereNotNull('consolidation')
            ->where('consolidation', '!=', '')
            ->distinct()
            ->orderBy('consolidation')
            ->pluck('consolidation')
            ->values()
            ->all();
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function crear(string $codigoCc, array $actor): PagoProveedorCotizacion
    {
        $origen = $this->lookup->findByCode($codigoCc);

        return DB::transaction(function () use ($origen, $actor) {
            $cotizacion = PagoProveedorCotizacion::query()->create([
                'code' => $this->siguienteCodigo(),
                'calculadora_importacion_id' => $origen['calculadora_importacion_id'],
                'consolidated_quote_id' => $origen['id_cotizacion_cc'],
                'consolidated_quote_code' => $origen['cod_cotizacion_cc'],
                'status' => PagoProveedorCotizacion::STATUS_PENDING,
                'valid_until' => $this->vigencia->sumarHoras(
                    now(),
                    $this->configuracion->validityHours(),
                    $this->configuracion->validityType()
                ),
                'client_name' => $origen['cliente']['nombre'],
                'client_document' => $origen['cliente']['documento'],
                'client_whatsapp' => $origen['cliente']['whatsapp'],
                'client_email' => $origen['cliente']['correo'],
                'consolidation' => $origen['consolidado'],
                'user_id' => $actor['id'],
            ]);

            $cotizacion->proveedores()->create($this->proveedorVacio(1));
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_QUOTE, $cotizacion->id, 'Creada', $actor, $origen['cod_cotizacion_cc']);
            $this->lookup->forget($origen['cod_cotizacion_cc']);

            return $this->ver($cotizacion->id);
        });
    }

    /**
     * Crea la cotización con sus proveedores y proformas en una sola operación.
     *
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function crearConDatos(string $codigoCc, array $datos, array $actor): PagoProveedorCotizacion
    {
        try {
            return DB::transaction(function () use ($codigoCc, $datos, $actor) {
                $cotizacion = $this->crear($codigoCc, $actor);

                return $this->actualizar($cotizacion->id, $datos, $actor);
            });
        } catch (\Throwable $e) {
            // La caché pudo guardar "ya_usada" para una cotización que se revirtió.
            $this->lookup->forget(strtoupper(trim($codigoCc)));
            throw $e;
        }
    }

    /**
     * Cotización en memoria con los datos del formulario, para generar el resumen sin guardar nada.
     * El tipo de cambio cuenta como confirmado: la confirmación solo se exige para crear la solicitud.
     *
     * @param  array<string, mixed>  $datos
     */
    public function borrador(array $datos): PagoProveedorCotizacion
    {
        $guardada = !empty($datos['cotizacion_id']) ? $this->encontrar((int) $datos['cotizacion_id']) : null;
        if ($guardada) {
            $this->exigirPendiente($guardada);
        }

        $origen = $this->lookup->findByCode($guardada ? $guardada->consolidated_quote_code : $datos['cod_cotizacion_cc']);
        $permitidos = collect($origen['proveedores'])->keyBy('code_supplier');
        if ($permitidos->isEmpty()) {
            throw new PagoProveedorException('El consolidado todavía no tiene códigos de proveedor.');
        }

        if ($guardada) {
            $cotizacion = clone $guardada;
        } else {
            $cotizacion = new PagoProveedorCotizacion([
                'code' => $origen['cod_cotizacion_cc'],
                'consolidated_quote_code' => $origen['cod_cotizacion_cc'],
                'status' => PagoProveedorCotizacion::STATUS_PENDING,
                'valid_until' => $this->vigencia->sumarHoras(
                    now(),
                    $this->configuracion->validityHours(),
                    $this->configuracion->validityType()
                ),
                'client_name' => $origen['cliente']['nombre'],
                'client_document' => $origen['cliente']['documento'],
                'client_whatsapp' => $origen['cliente']['whatsapp'],
                'client_email' => $origen['cliente']['correo'],
                'consolidation' => $origen['consolidado'],
            ]);
            $cotizacion->setRelation('solicitud', null);
        }

        $cotizacion->exchange_rate = isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null;
        $cotizacion->exchange_rate_confirmed = (float) $cotizacion->exchange_rate > 0;

        $filas = [];
        foreach (array_values($datos['proveedores']) as $indice => $fila) {
            $proveedor = new PagoProveedorCotizacionProveedor($this->normalizarProveedor($fila, $indice + 1, $permitidos));
            $proveedor->setRelation('consolidatedProvider', null);
            $filas[] = $proveedor;
        }
        $cotizacion->setRelation('proveedores', new EloquentCollection($filas));

        return $cotizacion;
    }

    public function ver(int $id): PagoProveedorCotizacion
    {
        return $this->encontrar($id);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function actualizar(int $id, array $datos, array $actor): PagoProveedorCotizacion
    {
        return DB::transaction(function () use ($id, $datos, $actor) {
            $cotizacion = $this->encontrar($id, true);
            $this->exigirPendiente($cotizacion);

            $codigos = $this->lookup->findByCode($cotizacion->consolidated_quote_code)['proveedores'];
            $permitidos = collect($codigos)->keyBy('code_supplier');
            if ($permitidos->isEmpty()) {
                throw new PagoProveedorException('El consolidado todavía no tiene códigos de proveedor.');
            }

            $cotizacion->exchange_rate = isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null;
            $cotizacion->exchange_rate_confirmed = (bool) $datos['tipo_cambio_confirmado'] && (float) $cotizacion->exchange_rate > 0;
            $cotizacion->exchange_rate_confirmed_at = $cotizacion->exchange_rate_confirmed ? now() : null;
            $cotizacion->save();

            $conservar = [];
            $proformas = [];
            foreach (array_values($datos['proveedores']) as $indice => $fila) {
                $normalizada = $this->normalizarProveedor($fila, $indice + 1, $permitidos);
                $proveedor = null;
                if (!empty($fila['id'])) {
                    $proveedor = $cotizacion->proveedores()->where('id', $fila['id'])->first();
                }

                if ($proveedor) {
                    $proveedor->fill($normalizada);
                    $proveedor->save();
                } else {
                    $proveedor = $cotizacion->proveedores()->create($normalizada);
                }
                $conservar[] = $proveedor->id;

                if (($fila['proforma'] ?? null) instanceof UploadedFile) {
                    $proformas[] = [$proveedor, $fila['proforma']];
                }
            }

            $cotizacion->proveedores()->whereNotIn('id', $conservar)->delete();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_QUOTE, $cotizacion->id, 'Editada', $actor);

            // Se suben al final para no dejar archivos huérfanos si alguna fila no es válida.
            foreach ($proformas as [$proveedor, $archivo]) {
                $this->guardarProforma($cotizacion, $proveedor, $archivo, $actor);
            }

            return $this->ver($cotizacion->id);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function eliminar(int $id, array $actor): void
    {
        DB::transaction(function () use ($id) {
            $cotizacion = $this->encontrar($id, true);
            $this->exigirPendiente($cotizacion);
            if ($cotizacion->solicitud) {
                throw new PagoProveedorException('No se puede eliminar: ya tiene una solicitud asociada.');
            }
            PagoProveedorHistorial::query()
                ->where('entity_type', PagoProveedorHistorial::ENTITY_QUOTE)
                ->where('entity_id', $cotizacion->id)
                ->delete();
            PagoProveedorMensaje::query()->where('quote_id', $cotizacion->id)->delete();
            PagoProveedorDocumento::query()
                ->where('entity_type', PagoProveedorDocumento::ENTITY_QUOTE)
                ->where('entity_id', $cotizacion->id)
                ->delete();
            $code = (string) $cotizacion->consolidated_quote_code;
            $cotizacion->delete();
            $this->lookup->forget($code);
        });
        unset($actor);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function renovar(int $id, ?float $tipoCambio, array $actor): PagoProveedorCotizacion
    {
        return DB::transaction(function () use ($id, $tipoCambio, $actor) {
            $cotizacion = $this->encontrar($id, true);
            $this->exigirPendiente($cotizacion);
            if (!$this->vigencia->estaVencida($cotizacion->valid_until)) {
                throw new PagoProveedorException('Solo se renueva una cotización vencida.');
            }

            if ($tipoCambio !== null && $tipoCambio > 0) {
                $cotizacion->exchange_rate = $tipoCambio;
            }
            $cotizacion->exchange_rate_confirmed = false;
            $cotizacion->exchange_rate_confirmed_at = null;
            $cotizacion->valid_until = $this->vigencia->sumarHoras(
                now(),
                $this->configuracion->validityHours(),
                $this->configuracion->validityType()
            );
            $cotizacion->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_QUOTE, $cotizacion->id, 'Renovada', $actor, 'Se reinició la vigencia. Hay que reenviar el resumen.');

            return $this->ver($cotizacion->id);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function guardarProforma(PagoProveedorCotizacion $cotizacion, PagoProveedorCotizacionProveedor $proveedor, UploadedFile $archivo, array $actor): void
    {
        $nombre = $archivo->getClientOriginalName();
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'pdf');
        $ruta = $this->storageStoreUpload(
            $archivo,
            'pago-proveedor/proformas',
            'proforma-' . $proveedor->id . '-' . now()->format('YmdHis') . '.' . $extension
        );
        $proveedor->proforma_path = $this->storageFinalizeCdnPath($ruta);
        $proveedor->proforma_name = $nombre;
        $proveedor->save();
        $this->historial->registrar(PagoProveedorHistorial::ENTITY_QUOTE, $cotizacion->id, 'Proforma cargada', $actor, $nombre);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function vistaPreviaWhatsapp(int $id, array $actor): array
    {
        return app(PagoProveedorCotizacionWhatsappService::class)->vistaPrevia($id, $actor);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function enviarWhatsapp(int $id, array $actor): array
    {
        return app(PagoProveedorCotizacionWhatsappService::class)->enviar($id, $actor);
    }

    public function encontrar(int $id, bool $bloquear = false): PagoProveedorCotizacion
    {
        $query = PagoProveedorCotizacion::query()->with(['proveedores.consolidatedProvider', 'solicitud']);
        if ($bloquear) {
            $query->lockForUpdate();
        }
        $cotizacion = $query->find($id);
        if (!$cotizacion) {
            throw new PagoProveedorException('La cotización no existe.', 404);
        }

        return $cotizacion;
    }

    public function exigirPendiente(PagoProveedorCotizacion $cotizacion): void
    {
        if ($cotizacion->status !== PagoProveedorCotizacion::STATUS_PENDING) {
            throw new PagoProveedorException('Solo aplica a cotizaciones pendientes. Las confirmadas no se pueden editar ni eliminar.');
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $permitidos
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function normalizarProveedor(array $fila, int $orden, $permitidos): array
    {
        $moneda = strtoupper((string) $fila['moneda']);
        $tipo = (string) $fila['tipo_pago'];
        if ($tipo === PagoProveedorCotizacionProveedor::PAYMENT_CARD) {
            throw new PagoProveedorException('USD / Tarjeta está fuera de alcance en esta versión.');
        }
        if ($moneda === PagoProveedorCotizacionProveedor::CURRENCY_USD) {
            $tipo = PagoProveedorCotizacionProveedor::PAYMENT_TT;
        }
        if ($moneda === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
            $tipo = PagoProveedorCotizacionProveedor::PAYMENT_TRANSFER;
        }

        $abono = $fila['modalidad_abono'] === PagoProveedorCotizacionProveedor::MODE_SPLIT
            ? PagoProveedorCotizacionProveedor::MODE_SPLIT
            : PagoProveedorCotizacionProveedor::MODE_TOTAL;
        $pago = $fila['modalidad_pago'] === PagoProveedorCotizacionProveedor::MODE_SPLIT
            ? PagoProveedorCotizacionProveedor::MODE_SPLIT
            : PagoProveedorCotizacionProveedor::MODE_TOTAL;
        if ($moneda === PagoProveedorCotizacionProveedor::CURRENCY_USD) {
            $pago = $abono;
        }

        // El valor en yuanes se guarda tal cual lo digitó Ventas: reconvertirlo desde el USD
        // redondeado daba ¥9,999.99 en vez de ¥10,000 en el resumen.
        $valorRmb = $moneda === PagoProveedorCotizacionProveedor::CURRENCY_RMB && (float) ($fila['valor_carga_rmb'] ?? 0) > 0
            ? round((float) $fila['valor_carga_rmb'], 2)
            : null;

        $codigo = trim((string) ($fila['code_supplier'] ?? ''));
        $idProveedor = null;
        if ($codigo !== '') {
            $catalogo = $permitidos->get($codigo);
            if (!$catalogo) {
                throw new PagoProveedorException("El código {$codigo} no pertenece a esta cotización de Consolidada.");
            }
            $idProveedor = $catalogo['id_proveedor_cc'] ?? null;
        }

        $p1 = $abono === PagoProveedorCotizacionProveedor::MODE_TOTAL && $pago === PagoProveedorCotizacionProveedor::MODE_TOTAL
            ? PagoProveedorCotizacionProveedor::FULL_SHARE
            : (float) ($fila['porcentaje_1'] ?? 50);
        $p2 = $abono === PagoProveedorCotizacionProveedor::MODE_TOTAL && $pago === PagoProveedorCotizacionProveedor::MODE_TOTAL
            ? null
            : (float) ($fila['porcentaje_2'] ?? (PagoProveedorCotizacionProveedor::FULL_SHARE - $p1));
        if ($abono === PagoProveedorCotizacionProveedor::MODE_SPLIT || $pago === PagoProveedorCotizacionProveedor::MODE_SPLIT) {
            if (round($p1 + (float) $p2, 2) !== (float) PagoProveedorCotizacionProveedor::FULL_SHARE) {
                throw new PagoProveedorException("Proveedor {$orden}: los porcentajes deben sumar 100.");
            }
        }

        return [
            'position' => $orden,
            'code_supplier' => $codigo !== '' ? $codigo : null,
            'consolidated_provider_id' => $idProveedor,
            'supplier_name' => $this->vacio($fila['nombre_proveedor'] ?? null),
            'product' => $this->vacio($fila['producto'] ?? null),
            'currency' => $moneda,
            'payment_type' => $tipo,
            'cargo_value_usd' => round((float) ($fila['valor_carga_usd'] ?? 0), 2),
            'cargo_value_rmb' => $valorRmb,
            'client_payment_mode' => $abono,
            'supplier_payment_mode' => $pago,
            'first_share_percent' => $p1,
            'second_share_percent' => $p2,
            'second_installment_on' => $abono === PagoProveedorCotizacionProveedor::MODE_SPLIT ? (($fila['fecha_cuota_2'] ?? null) ?: null) : null,
            'charges_bank_fee' => (bool) $fila['comision_bancaria'],
            'charges_admin_fee' => (bool) $fila['comision_administrativa'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proveedorVacio(int $orden): array
    {
        return [
            'position' => $orden,
            'currency' => PagoProveedorCotizacionProveedor::CURRENCY_RMB,
            'payment_type' => PagoProveedorCotizacionProveedor::PAYMENT_TRANSFER,
            'cargo_value_usd' => 0,
            'client_payment_mode' => PagoProveedorCotizacionProveedor::MODE_TOTAL,
            'supplier_payment_mode' => PagoProveedorCotizacionProveedor::MODE_TOTAL,
            'first_share_percent' => PagoProveedorCotizacionProveedor::FULL_SHARE,
            'charges_bank_fee' => true,
            'charges_admin_fee' => false,
        ];
    }

    private function siguienteCodigo(): string
    {
        $maximo = (int) PagoProveedorCotizacion::query()
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as n')
            ->value('n');

        return 'COT-' . str_pad((string) ($maximo + 1), 2, '0', STR_PAD_LEFT);
    }

    private function vacio(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
