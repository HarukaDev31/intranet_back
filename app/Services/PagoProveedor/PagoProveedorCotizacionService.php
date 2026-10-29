<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        private readonly PagoProveedorCotizacionPresenter $presenter,
        private readonly PagoProveedorMensajeService $mensajes,
    ) {
    }

    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = PagoProveedorCotizacion::query()->with('proveedores')->orderByDesc('id');

        if (!empty($filtros['search'])) {
            $texto = '%' . trim((string) $filtros['search']) . '%';
            $query->where(function ($q) use ($texto) {
                $q->where('codigo', 'like', $texto)
                    ->orWhere('cod_cotizacion_cc', 'like', $texto)
                    ->orWhere('cliente_nombre', 'like', $texto)
                    ->orWhere('consolidado', 'like', $texto);
            });
        }

        if (!empty($filtros['estado']) && $filtros['estado'] !== 'todos') {
            if ($filtros['estado'] === 'vencida') {
                $query->where('estado', 'pendiente')->where('vigencia_fin', '<=', now());
            } elseif ($filtros['estado'] === 'pendiente') {
                $query->where('estado', 'pendiente')->where('vigencia_fin', '>', now());
            } else {
                $query->where('estado', $filtros['estado']);
            }
        }

        $tarifa = $this->configuracion->tarifa();
        $pagina = $query->paginate((int) ($filtros['per_page'] ?? 15));
        $pagina->getCollection()->transform(function (PagoProveedorCotizacion $cotizacion) use ($tarifa) {
            return $this->presenter->cotizacion($cotizacion, $tarifa);
        });

        return $pagina;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function crear(string $codigoCc, array $actor): array
    {
        $origen = $this->lookup->buscar($codigoCc);

        return DB::transaction(function () use ($origen, $actor) {
            $cotizacion = PagoProveedorCotizacion::query()->create([
                'codigo' => $this->siguienteCodigo(),
                'calculadora_importacion_id' => $origen['calculadora_importacion_id'],
                'id_cotizacion_cc' => $origen['id_cotizacion_cc'],
                'cod_cotizacion_cc' => $origen['cod_cotizacion_cc'],
                'estado' => 'pendiente',
                'vigencia_fin' => $this->vigencia->sumarHorasHabiles(now(), $this->configuracion->horasVigencia()),
                'cliente_nombre' => $origen['cliente']['nombre'],
                'cliente_documento' => $origen['cliente']['documento'],
                'cliente_whatsapp' => $origen['cliente']['whatsapp'],
                'cliente_correo' => $origen['cliente']['correo'],
                'consolidado' => $origen['consolidado'],
                'usuario_id' => $actor['id'],
            ]);

            $cotizacion->proveedores()->create($this->proveedorVacio(1));
            $this->historial->registrar('cotizacion', $cotizacion->id, 'Creada', $actor, $origen['cod_cotizacion_cc']);

            return $this->ver($cotizacion->id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function ver(int $id): array
    {
        $cotizacion = $this->encontrar($id);
        $codigos = [];
        try {
            $codigos = $this->lookup->buscar($cotizacion->cod_cotizacion_cc)['proveedores'];
        } catch (PagoProveedorException) {
            $codigos = [];
        }

        return $this->presenter->cotizacion($cotizacion, $this->configuracion->tarifa(), true, $codigos);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $datos, array $actor): array
    {
        return DB::transaction(function () use ($id, $datos, $actor) {
            $cotizacion = $this->encontrar($id, true);
            $this->exigirPendiente($cotizacion);

            $codigos = $this->lookup->buscar($cotizacion->cod_cotizacion_cc)['proveedores'];
            $permitidos = collect($codigos)->keyBy('code_supplier');
            if ($permitidos->isEmpty()) {
                throw new PagoProveedorException('El consolidado todavía no tiene códigos de proveedor.');
            }

            $cotizacion->tipo_cambio = isset($datos['tipo_cambio']) ? (float) $datos['tipo_cambio'] : null;
            $cotizacion->tipo_cambio_confirmado = (bool) $datos['tipo_cambio_confirmado'] && (float) $cotizacion->tipo_cambio > 0;
            $cotizacion->save();

            $conservar = [];
            foreach (array_values($datos['proveedores']) as $indice => $fila) {
                $normalizada = $this->normalizarProveedor($fila, $indice + 1, $permitidos);
                $existente = null;
                if (!empty($fila['id'])) {
                    $existente = $cotizacion->proveedores()->where('id', $fila['id'])->first();
                }

                if ($existente) {
                    $existente->fill($normalizada);
                    $existente->save();
                    $conservar[] = $existente->id;
                } else {
                    $nuevo = $cotizacion->proveedores()->create($normalizada);
                    $conservar[] = $nuevo->id;
                }
            }

            $cotizacion->proveedores()->whereNotIn('id', $conservar)->delete();
            $this->historial->registrar('cotizacion', $cotizacion->id, 'Editada', $actor);

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
            PagoProveedorHistorial::query()
                ->where('entidad_tipo', 'cotizacion')
                ->where('entidad_id', $cotizacion->id)
                ->delete();
            PagoProveedorMensaje::query()->where('cotizacion_id', $cotizacion->id)->delete();
            $cotizacion->delete();
        });
        unset($actor);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function renovar(int $id, ?float $tipoCambio, array $actor): array
    {
        return DB::transaction(function () use ($id, $tipoCambio, $actor) {
            $cotizacion = $this->encontrar($id, true);
            $this->exigirPendiente($cotizacion);
            if (!$this->vigencia->estaVencida($cotizacion->vigencia_fin)) {
                throw new PagoProveedorException('Solo se renueva una cotización vencida.');
            }

            if ($tipoCambio !== null && $tipoCambio > 0) {
                $cotizacion->tipo_cambio = $tipoCambio;
            }
            $cotizacion->tipo_cambio_confirmado = false;
            $cotizacion->vigencia_fin = $this->vigencia->sumarHorasHabiles(now(), $this->configuracion->horasVigencia());
            $cotizacion->save();
            $this->historial->registrar('cotizacion', $cotizacion->id, 'Renovada', $actor, 'Se reinició la vigencia. Hay que reenviar el resumen.');

            return $this->ver($cotizacion->id);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function subirProforma(int $cotizacionId, int $proveedorId, UploadedFile $archivo, array $actor): array
    {
        $cotizacion = $this->encontrar($cotizacionId);
        $this->exigirPendiente($cotizacion);
        $proveedor = $cotizacion->proveedores()->where('id', $proveedorId)->first();
        if (!$proveedor) {
            throw new PagoProveedorException('El proveedor no pertenece a esta cotización.', 404);
        }

        $nombre = $archivo->getClientOriginalName();
        $extension = strtolower($archivo->getClientOriginalExtension() ?: 'pdf');
        $ruta = $this->storageStoreUpload(
            $archivo,
            'pago-proveedor/proformas',
            'proforma-' . $proveedor->id . '-' . now()->format('YmdHis') . '.' . $extension
        );
        $proveedor->proforma_path = $this->storageFinalizeCdnPath($ruta);
        $proveedor->proforma_nombre = $nombre;
        $proveedor->save();
        $this->historial->registrar('cotizacion', $cotizacion->id, 'Proforma cargada', $actor, $nombre);

        return $this->ver($cotizacion->id);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function vistaPreviaWhatsapp(int $id, array $actor): array
    {
        $cotizacion = $this->encontrar($id);
        $presentado = $this->presenter->cotizacion($cotizacion, $this->configuracion->tarifa());
        if (!$presentado['puede_enviar']) {
            throw new PagoProveedorException(
                $presentado['vencida']
                    ? 'La cotización está vencida. Renuévala para enviar el resumen.'
                    : implode(' ', $presentado['faltantes_pdf'])
            );
        }

        $detalle = $cotizacion->proveedores->map(fn ($proveedor) => [
            'code_supplier' => $proveedor->code_supplier,
            'nombre_proveedor' => $proveedor->nombre_proveedor,
            'producto' => $proveedor->producto,
        ])->all();

        $texto = $this->mensajes->resumen(
            (string) $cotizacion->cliente_nombre,
            $presentado['resumen'],
            $detalle
        );

        PagoProveedorMensaje::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'tipo' => 'resumen',
            'destinatario' => $cotizacion->cliente_whatsapp,
            'cuerpo' => $texto,
            'usuario_id' => $actor['id'],
            'created_at' => now(),
        ]);
        $this->historial->registrar('cotizacion', $cotizacion->id, 'Resumen preparado', $actor, 'Vista previa de WhatsApp. El envío real queda pendiente de plantilla Meta.');

        return [
            'texto' => $texto,
            'destinatario' => $cotizacion->cliente_whatsapp,
            'puede_enviar' => false,
            'nota' => 'Quedó registrada la vista previa. El envío por WhatsApp se habilita cuando exista la plantilla de Meta.',
        ];
    }

    public function encontrar(int $id, bool $bloquear = false): PagoProveedorCotizacion
    {
        $query = PagoProveedorCotizacion::query()->with('proveedores');
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
        if ($cotizacion->estado !== 'pendiente') {
            throw new PagoProveedorException('Esta cotización ya está confirmada y no se puede modificar.');
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
        if ($tipo === 'tarjeta') {
            throw new PagoProveedorException('USD / Tarjeta está fuera de alcance en esta versión.');
        }
        if ($moneda === 'USD') {
            $tipo = 'tt';
        }
        if ($moneda === 'RMB') {
            $tipo = 'transferencia';
        }

        $abono = $fila['modalidad_abono'] === 'fraccionado' ? 'fraccionado' : 'total';
        $pago = $fila['modalidad_pago'] === 'fraccionado' ? 'fraccionado' : 'total';
        if ($moneda === 'USD') {
            $pago = $abono;
        }

        $codigo = trim((string) ($fila['code_supplier'] ?? ''));
        $idProveedor = null;
        if ($codigo !== '') {
            $catalogo = $permitidos->get($codigo);
            if (!$catalogo) {
                throw new PagoProveedorException("El código {$codigo} no pertenece a esta cotización de Consolidada.");
            }
            $idProveedor = $catalogo['id_proveedor_cc'] ?? null;
        }

        $p1 = $abono === 'total' && $pago === 'total' ? 100 : (float) ($fila['porcentaje_1'] ?? 50);
        $p2 = $abono === 'total' && $pago === 'total' ? null : (float) ($fila['porcentaje_2'] ?? (100 - $p1));
        if ($abono === 'fraccionado' || $pago === 'fraccionado') {
            if (round($p1 + (float) $p2, 2) !== 100.0) {
                throw new PagoProveedorException("Proveedor {$orden}: los porcentajes deben sumar 100.");
            }
        }

        return [
            'orden' => $orden,
            'code_supplier' => $codigo !== '' ? $codigo : null,
            'id_proveedor_cc' => $idProveedor,
            'nombre_proveedor' => $this->vacio($fila['nombre_proveedor'] ?? null),
            'producto' => $this->vacio($fila['producto'] ?? null),
            'moneda' => $moneda,
            'tipo_pago' => $tipo,
            'valor_carga_usd' => round((float) ($fila['valor_carga_usd'] ?? 0), 2),
            'modalidad_abono' => $abono,
            'modalidad_pago' => $pago,
            'porcentaje_1' => $p1,
            'porcentaje_2' => $p2,
            'fecha_cuota_2' => $abono === 'fraccionado' ? (($fila['fecha_cuota_2'] ?? null) ?: null) : null,
            'comision_bancaria' => (bool) $fila['comision_bancaria'],
            'comision_administrativa' => (bool) $fila['comision_administrativa'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proveedorVacio(int $orden): array
    {
        return [
            'orden' => $orden,
            'moneda' => 'RMB',
            'tipo_pago' => 'transferencia',
            'valor_carga_usd' => 0,
            'modalidad_abono' => 'total',
            'modalidad_pago' => 'total',
            'porcentaje_1' => 100,
            'comision_bancaria' => true,
            'comision_administrativa' => false,
        ];
    }

    private function siguienteCodigo(): string
    {
        $maximo = (int) PagoProveedorCotizacion::query()
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) as n')
            ->value('n');

        return 'COT-' . str_pad((string) ($maximo + 1), 2, '0', STR_PAD_LEFT);
    }

    private function vacio(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
