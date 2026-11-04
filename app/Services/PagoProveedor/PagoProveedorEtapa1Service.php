<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorConfiguracion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorMensaje;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoProveedorEtapa1Service
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorHistorialService $historial,
        private readonly PagoProveedorAbonoEvaluador $evaluador,
        private readonly PagoProveedorCodigoSolicitud $codes,
    ) {
    }

    public function detalle(int $solicitudId): PagoProveedorSolicitud
    {
        $solicitud = $this->solicitud($solicitudId);
        app(PagoProveedorConfirmacionService::class)->refresh($solicitud);

        return $solicitud;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function guardarRmb(int $solicitudId, int $proveedorId, string $vendedor, string $wechat, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $vendedor, $wechat, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            if ($proveedor->currency !== PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
                throw new PagoProveedorException('El vendedor y el WeChat son solo para pagos en yuanes.');
            }
            $datos = $this->datosDe($proveedor);
            $datos->seller_name = trim($vendedor);
            $datos->wechat = PagoProveedorDatosReglas::wechat($wechat);
            if ($datos->seller_name === '') {
                throw new PagoProveedorException('Falta el nombre del vendedor.');
            }
            $datos->status = PagoProveedorDatoProveedor::STATUS_RECEIVED;
            $datos->source = 'ventas';
            $datos->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Datos del proveedor', $actor, $proveedor->code_supplier);
            $this->intentarAvanzar($this->solicitudDe($proveedor), $actor);

            return $this->recargar($this->solicitudDe($proveedor));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function enviarFormulario(int $solicitudId, int $proveedorId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $this->exigirTt($proveedor);
            $datos = $this->datosDe($proveedor);
            if ($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested) {
                throw new PagoProveedorException('Los datos ya fueron autorizados. Para corregirlos, solicita una nueva validación.');
            }
            if ($datos->status === PagoProveedorDatoProveedor::STATUS_PENDING) {
                $datos->status = PagoProveedorDatoProveedor::STATUS_SENT;
            }
            $datos->save();
            $enlace = $this->enlace($datos);
            $texto = $this->textoFormulario($proveedor, $enlace, $datos->status === PagoProveedorDatoProveedor::STATUS_AWAITING_AUTHORIZATION);
            $this->guardarMensaje($proveedor, 'pedir_datos', $texto, $actor);
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Formulario T-T enviado', $actor, $proveedor->code_supplier);

            return [
                'enlace' => $enlace,
                'texto' => $texto,
                'nota' => 'Quedó la vista previa. El envío real por WhatsApp se habilita con la plantilla de Meta.',
                'solicitud' => $this->recargar($this->solicitudDe($proveedor)),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function llenarPorVentas(int $solicitudId, int $proveedorId, array $payload, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $payload, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $this->exigirTt($proveedor);
            $datos = $this->datosDe($proveedor);
            if ($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED) {
                throw new PagoProveedorException('Los datos ya están autorizados. Solicita una validación para corregirlos.');
            }
            $this->aplicarDatosBancarios($datos, $payload);
            $datos->status = PagoProveedorDatoProveedor::STATUS_AWAITING_AUTHORIZATION;
            $datos->source = 'ventas';
            $datos->authorized_at = null;
            $datos->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'T-T llenado por Ventas', $actor, 'Quedó por autorizar. ' . $proveedor->code_supplier);

            return $this->recargar($this->solicitudDe($proveedor));
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function solicitarValidacion(int $solicitudId, int $proveedorId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $datos = $this->datosDe($proveedor);
            if ($datos->status !== PagoProveedorDatoProveedor::STATUS_RECEIVED) {
                throw new PagoProveedorException('Solo se pide una nueva validación cuando el cliente ya autorizó los datos.');
            }
            $datos->correction_requested = true;
            $datos->save();
            $enlace = $this->enlace($datos);
            $texto = "Hola, " . ($this->solicitudDe($proveedor)->client_name ?: 'cliente') . " 🙋🏻‍♀️\n|\nNecesitamos que revises los datos bancarios de " . $proveedor->code_supplier . ".\n" . $enlace;
            $this->guardarMensaje($proveedor, 'validacion_tt', $texto, $actor);
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Validación de datos T-T', $actor, $proveedor->code_supplier);

            return [
                'enlace' => $enlace,
                'texto' => $texto,
                'solicitud' => $this->recargar($this->solicitudDe($proveedor)),
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function anularProveedor(int $solicitudId, int $proveedorId, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $proveedor->status = PagoProveedorSolicitudProveedor::STATUS_CANCELLED;
            $proveedor->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Proveedor anulado', $actor, $proveedor->code_supplier);

            return $this->recargar($this->solicitudDe($proveedor));
        });
    }

    /**
     * @return array<int, array{id: int, titulo: string, moneda: string, incompleto: bool}>
     */
    public function candidatosPedirDatos(int $solicitudId): array
    {
        $solicitud = $this->solicitud($solicitudId);
        $rows = [];
        foreach ($solicitud->proveedores as $proveedor) {
            if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED || (int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA) {
                continue;
            }
            $incomplete = false;
            if ($proveedor->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
                $datos = $proveedor->datos;
                $incomplete = !($datos && trim((string) $datos->seller_name) !== '' && trim((string) $datos->wechat) !== '');
            } else {
                $datos = $this->datosDe($proveedor);
                $incomplete = !($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested);
            }
            if (!$incomplete) {
                continue;
            }
            $rows[] = [
                'id' => $proveedor->id,
                'titulo' => trim(($proveedor->code_supplier ?: 'Sin código').' · '.($proveedor->product ?: '')),
                'moneda' => $proveedor->currency,
                'incompleto' => true,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @param  array<int, int>|null  $providerIds
     * @return array<string, mixed>
     */
    public function vistaPreviaPedirDatos(int $solicitudId, array $actor, ?array $providerIds = null): array
    {
        return DB::transaction(function () use ($solicitudId, $actor, $providerIds) {
            $solicitud = $this->solicitud($solicitudId, true);
            $selected = $providerIds === null ? null : array_map('intval', $providerIds);
            $bloques = [];
            $primero = null;
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED || (int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA) {
                    continue;
                }
                if ($selected !== null && !in_array((int) $proveedor->id, $selected, true)) {
                    continue;
                }
                $primero ??= $proveedor;
                if ($proveedor->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
                    $datos = $proveedor->datos;
                    if ($datos && trim((string) $datos->seller_name) !== '' && trim((string) $datos->wechat) !== '') {
                        continue;
                    }
                    $bloques[] = "✅ Proveedor: {$proveedor->code_supplier} | {$proveedor->product}\n📝 Datos: nombre del vendedor y N.° de WeChat";
                    continue;
                }
                $datos = $this->datosDe($proveedor);
                if ($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested) {
                    continue;
                }
                if ($datos->status === PagoProveedorDatoProveedor::STATUS_PENDING) {
                    $datos->status = PagoProveedorDatoProveedor::STATUS_SENT;
                    $datos->save();
                }
                $bloques[] = "✅ Proveedor: {$proveedor->code_supplier} | {$proveedor->product}\n📝 Datos: {$this->enlace($datos)}";
            }
            if ($bloques === [] || !$primero) {
                throw new PagoProveedorException('Los proveedores de esta solicitud ya tienen los datos.');
            }
            $nombre = $solicitud->client_name ?: 'cliente';
            $texto = "Hola, {$nombre} 🙋🏻‍♀️\n|\n" . implode("\n", $bloques) . "\nCon estos datos continuamos con el proceso.";
            $this->guardarMensaje($primero, 'pedir_datos', $texto, $actor);
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Pedido de datos', $actor, null);

            return [
                'texto' => $texto,
                'nota' => 'Quedó la vista previa. El envío real por WhatsApp se habilita con la plantilla de Meta.',
                'solicitud' => $this->recargar($solicitud),
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function vistaPreviaCuentas(int $solicitudId, array $actor): array
    {
        return DB::transaction(function () use ($solicitudId, $actor) {
            $solicitud = $this->solicitud($solicitudId);
            $lineas = [];
            $total = 0.0;
            $primero = null;
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                    continue;
                }
                $primero ??= $proveedor;
                $saldo = $this->balance($proveedor);
                if ($saldo <= 0) {
                    continue;
                }
                $lineas[] = $proveedor->code_supplier . ' | ' . $proveedor->product;
                $total += $saldo;
            }
            if ($lineas === [] || !$primero) {
                throw new PagoProveedorException('Ya no hay saldo por depositar.');
            }
            $nombre = $solicitud->client_name ?: 'cliente';
            $texto = "Hola, {$nombre} 🙋🏻‍♀️\n|\n👆🏻\nConcepto: Pago a proveedor\n"
                . implode("\n", $lineas)
                . "\nTotal a depositar: $" . number_format($total, 2, '.', ',');
            $this->guardarMensaje($primero, 'cuentas', $texto, $actor);

            return [
                'texto' => $texto,
                'nota' => 'La imagen oficial de las cuentas va antes de este texto. Los números de cuenta no se escriben en el mensaje.',
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array<string, mixed>
     */
    public function registrarAbono(int $solicitudId, array $payload, ?UploadedFile $voucher, array $actor, ?int $abonoId = null): array
    {
        return DB::transaction(function () use ($solicitudId, $payload, $voucher, $actor, $abonoId) {
            $solicitud = $this->solicitud($solicitudId, true);
            $abono = null;
            if ($abonoId) {
                $abono = $this->abonoEditable($solicitud, $abonoId);
            }

            $saldos = [];
            $aprobacion = app(PagoProveedorAprobacionService::class);
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                    continue;
                }
                $porCobrar = $proveedor->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT;
                if ((int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA && !$porCobrar) {
                    continue;
                }
                $saldos[$proveedor->id] = $porCobrar
                    ? $aprobacion->outstanding($proveedor)
                    : $this->balance($proveedor, $abono?->id);
            }

            $aplicaciones = $this->mapaAplicaciones($payload['aplicaciones'] ?? []);
            foreach (array_keys($aplicaciones) as $proveedorId) {
                $elegido = $solicitud->proveedores->firstWhere('id', (int) $proveedorId);
                if ($elegido?->exchange_rate_pending) {
                    throw new PagoProveedorException('Confirma el tipo de cambio del día antes de registrar el abono de la cuota 2.');
                }
            }
            $evaluacion = $this->evaluador->evaluar((float) $payload['importe'], $aplicaciones, $saldos);
            $hayCuota2 = $solicitud->proveedores->contains(fn ($proveedor) => $proveedor->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT);
            $repetida = PagoProveedorAbono::query()
                ->where('operation_number', $payload['numero_operacion'])
                ->when($abono, fn ($q) => $q->where('id', '!=', $abono->id))
                ->where('status', '!=', PagoProveedorAbono::STATUS_CANCELLED)
                ->exists();

            if ($voucher) {
                $ruta = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                    $voucher,
                    'pago-proveedor/vouchers',
                    'voucher-' . $solicitudId . '-' . now()->format('YmdHis') . '.' . strtolower($voucher->getClientOriginalExtension() ?: 'pdf')
                ));
                $nombreVoucher = $voucher->getClientOriginalName();
            } elseif ($abono) {
                $ruta = $abono->voucher_path;
                $nombreVoucher = $abono->voucher_name;
            } else {
                throw new PagoProveedorException('Falta el comprobante.');
            }

            $datosAbono = [
                'amount' => round((float) $payload['importe'], 2),
                'paid_on' => $payload['fecha_pago'],
                'operation_number' => trim((string) $payload['numero_operacion']),
                'bank' => $payload['banco'],
                'interbank' => (bool) ($payload['interbancaria'] ?? false),
                'voucher_path' => $ruta,
                'voucher_name' => $nombreVoucher,
                'status' => PagoProveedorAbono::STATUS_IN_VALIDATION,
                'surplus' => $evaluacion['excedente'],
                'surplus_decision' => $this->evaluador->decisionExcedente($evaluacion['excedente'], $hayCuota2),
                'repeated_operation' => $repetida,
                'user_id' => $actor['id'],
                'observation_reason' => null,
                'observation_detail' => null,
                'observe_until' => null,
                'observation_escalated_at' => null,
                'verified_at' => null,
                'verified_by_user_id' => null,
                'bank_amount' => null,
            ];

            if ($abono) {
                $abono->fill($datosAbono);
                $abono->save();
                $abono->aplicaciones()->delete();
            } else {
                $abono = $solicitud->abonos()->create($datosAbono);
            }

            foreach ($evaluacion['aplicaciones'] as $proveedorId => $monto) {
                $abono->aplicaciones()->create([
                    'request_provider_id' => $proveedorId,
                    'amount' => $monto,
                ]);
            }

            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, $abonoId ? 'Abono editado' : 'Abono registrado', $actor, $abono->operation_number);
            $this->intentarAvanzar($this->recargar($solicitud, ['proveedores.datos', 'proveedores.aplicaciones.abono']), $actor);

            $presentado = $this->recargar($solicitud);

            return [
                'solicitud' => $presentado,
                'aviso_operacion_repetida' => $repetida
                    ? 'Ese número de operación ya está registrado. Puedes continuar.'
                    : null,
            ];
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function anularAbono(int $solicitudId, int $abonoId, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $abonoId, $actor) {
            $solicitud = $this->solicitud($solicitudId, true);
            $abono = $this->abonoEditable($solicitud, $abonoId);
            $abono->status = PagoProveedorAbono::STATUS_CANCELLED;
            $abono->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Abono anulado', $actor, $abono->operation_number);

            return $this->recargar($solicitud);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function decidirExcedente(int $solicitudId, int $abonoId, string $decision, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $abonoId, $decision, $actor) {
            $solicitud = $this->solicitud($solicitudId);
            $abono = $solicitud->abonos()->where('id', $abonoId)->first();
            if (!$abono || $abono->status === PagoProveedorAbono::STATUS_CANCELLED) {
                throw new PagoProveedorException('El abono no existe.', 404);
            }
            if ($abono->surplus_decision !== PagoProveedorAbono::EXCESS_TO_CONSULT) {
                throw new PagoProveedorException('Este excedente ya tiene una decisión.');
            }
            $hayCuota2 = $solicitud->proveedores->contains(fn ($proveedor) => $proveedor->client_payment_mode === PagoProveedorCotizacionProveedor::MODE_SPLIT);
            $permitidas = $hayCuota2
                ? [PagoProveedorAbono::EXCESS_APPLIED, PagoProveedorAbono::EXCESS_REFUND]
                : [PagoProveedorAbono::EXCESS_REFUND];
            if (!in_array($decision, $permitidas, true)) {
                throw new PagoProveedorException('Esa decisión no aplica a este excedente.');
            }
            $abono->surplus_decision = $decision;
            $abono->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Excedente', $actor, $decision);
            if ($decision === PagoProveedorAbono::EXCESS_REFUND) {
                app(PagoProveedorReembolsoService::class)->ensureSurplus($abono, $actor);
            }

            return $this->recargar($solicitud);
        });
    }

    /**
     * @param  array<int, int>  $providerIds
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function elegirContinuacion(int $solicitudId, int $abonoId, array $providerIds, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $abonoId, $providerIds, $actor) {
            $solicitud = $this->solicitud($solicitudId);
            $abono = $solicitud->abonos()->with('aplicaciones')->where('id', $abonoId)->first();
            if (!$abono || $abono->aplicaciones->count() < 2) {
                throw new PagoProveedorException('Este abono no es un voucher compartido.', 404);
            }
            $ids = array_values(array_unique(array_map('intval', $providerIds)));
            $known = $abono->aplicaciones->pluck('request_provider_id')->map(fn ($id) => (int) $id)->all();
            if ($ids === [] || array_diff($ids, $known) !== []) {
                throw new PagoProveedorException('Elige proveedores de este abono. Al menos uno tiene que continuar.');
            }
            foreach ($abono->aplicaciones as $aplicacion) {
                $aplicacion->continues = in_array((int) $aplicacion->request_provider_id, $ids, true);
                $aplicacion->save();
            }
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Proveedores que continúan', $actor, 'Abono '.$abono->operation_number);
            foreach ($abono->aplicaciones as $aplicacion) {
                $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->request_provider_id);
                if ($proveedor) {
                    app(PagoProveedorAprobacionService::class)->evaluate($proveedor, $actor);
                }
            }

            return $this->recargar($solicitud);
        });
    }

    public function verFormularioPublico(string $token): PagoProveedorDatoProveedor
    {
        return $this->datosPorToken($token);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function guardarFormularioPublico(string $token, array $payload): array
    {
        return DB::transaction(function () use ($token, $payload) {
            $datos = $this->datosPorToken($token);
            if ($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested) {
                throw new PagoProveedorException('Estos datos ya fueron autorizados.');
            }
            if (empty($payload['autorizo'])) {
                throw new PagoProveedorException('Falta la autorización para usar estos datos en el pago.');
            }
            $proveedor = $datos->proveedor;
            if ((int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA || $proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                throw new PagoProveedorException('Este formulario ya no admite cambios.');
            }
            $this->aplicarDatosBancarios($datos, $payload);
            $datos->status = PagoProveedorDatoProveedor::STATUS_RECEIVED;
            $datos->source = $datos->source === 'ventas' ? 'ventas' : 'cliente';
            $datos->correction_requested = false;
            $datos->authorized_at = now();
            $datos->save();
            $actor = ['id' => null, 'nombre' => 'Cliente', 'area' => 'Cliente'];
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $proveedor->request_id, 'T-T autorizado', $actor, $proveedor->code_supplier);
            $this->intentarAvanzar($this->solicitudDe($proveedor), $actor);

            return ['estado' => PagoProveedorDatoProveedor::STATUS_RECEIVED, 'mensaje' => 'Autorizamos los datos. Ventas ya puede continuar.'];
        });
    }

    /**
     * @param  array<int, string>  $with
     */
    private function recargar(PagoProveedorSolicitud $solicitud, array $with = ['proveedores.datos', 'proveedores.consolidatedProvider', 'proveedores.aplicaciones.abono', 'abonos.aplicaciones', 'cotizacion']): PagoProveedorSolicitud
    {
        $fresca = $solicitud->fresh($with);
        if (!$fresca instanceof PagoProveedorSolicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $fresca;
    }

    private function solicitudDe(PagoProveedorSolicitudProveedor $proveedor): PagoProveedorSolicitud
    {
        $solicitud = $proveedor->solicitud;
        if (!$solicitud instanceof PagoProveedorSolicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    private function solicitud(int $id, bool $bloquear = false): PagoProveedorSolicitud
    {
        $query = PagoProveedorSolicitud::query()->with(['proveedores.datos', 'proveedores.consolidatedProvider', 'proveedores.aplicaciones.abono', 'abonos.aplicaciones', 'cotizacion']);
        if ($bloquear) {
            $query->lockForUpdate();
        }
        $solicitud = $query->find($id);
        if (!$solicitud) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $solicitud;
    }

    private function proveedorEtapa1(int $solicitudId, int $proveedorId): PagoProveedorSolicitudProveedor
    {
        $solicitud = $this->solicitud($solicitudId, true);
        $proveedor = $solicitud->proveedores->firstWhere('id', $proveedorId);
        if (!$proveedor) {
            throw new PagoProveedorException('El proveedor no pertenece a esta solicitud.', 404);
        }
        if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
            throw new PagoProveedorException('Este proveedor está anulado.');
        }
        if ((int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA) {
            throw new PagoProveedorException('Los datos y los abonos de la etapa 1 ya no se editan.');
        }
        $proveedor->setRelation('solicitud', $solicitud);

        return $proveedor;
    }

    private function exigirTt(PagoProveedorSolicitudProveedor $proveedor): void
    {
        if ($proveedor->currency !== PagoProveedorCotizacionProveedor::CURRENCY_USD || $proveedor->payment_type !== PagoProveedorCotizacionProveedor::PAYMENT_TT) {
            throw new PagoProveedorException('El formulario T-T es solo para pagos en dólares.');
        }
    }

    private function datosDe(PagoProveedorSolicitudProveedor $proveedor): PagoProveedorDatoProveedor
    {
        $datos = $proveedor->datos;
        if ($datos) {
            return $datos;
        }

        return $proveedor->datos()->create([
            'status' => PagoProveedorDatoProveedor::STATUS_PENDING,
            'token' => Str::random(48),
        ]);
    }

    private function datosPorToken(string $token): PagoProveedorDatoProveedor
    {
        $datos = PagoProveedorDatoProveedor::query()->with('proveedor.solicitud')->where('token', $token)->first();
        if (!$datos || !$datos->proveedor) {
            throw new PagoProveedorException('El enlace no es válido.', 404);
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function aplicarDatosBancarios(PagoProveedorDatoProveedor $datos, array $payload): void
    {
        foreach (['beneficiario_nombre', 'beneficiario_cuenta', 'beneficiario_direccion', 'beneficiario_pais', 'beneficiario_ciudad', 'banco_nombre', 'banco_direccion', 'banco_pais', 'banco_ciudad'] as $campo) {
            if (trim((string) ($payload[$campo] ?? '')) === '') {
                throw new PagoProveedorException('Faltan datos del beneficiario o del banco de destino.');
            }
        }
        $tipo = (string) ($payload['banco_tipo_codigo'] ?? '');
        if (!in_array($tipo, ['SWIFT', 'ABA', 'CHIPS', 'Sort Code', 'Otro'], true)) {
            throw new PagoProveedorException('El tipo de código del banco no es válido.');
        }
        $datos->fill([
            'beneficiary_name' => trim((string) $payload['beneficiario_nombre']),
            'beneficiary_account' => trim((string) $payload['beneficiario_cuenta']),
            'beneficiary_address' => trim((string) $payload['beneficiario_direccion']),
            'beneficiary_country' => trim((string) $payload['beneficiario_pais']),
            'beneficiary_city' => trim((string) $payload['beneficiario_ciudad']),
            'bank_code_type' => $tipo,
            'bank_code' => PagoProveedorDatosReglas::codigoBanco($tipo, (string) ($payload['banco_codigo'] ?? '')),
            'bank_name' => trim((string) $payload['banco_nombre']),
            'bank_address' => trim((string) $payload['banco_direccion']),
            'bank_country' => trim((string) $payload['banco_pais']),
            'bank_city' => trim((string) $payload['banco_ciudad']),
            'uses_intermediary' => (bool) ($payload['usa_intermediario'] ?? false),
        ]);
        if ($datos->uses_intermediary) {
            $tipoInter = (string) ($payload['inter_tipo_codigo'] ?? '');
            if (!in_array($tipoInter, ['SWIFT', 'ABA', 'CHIPS', 'Sort Code', 'Otro'], true)) {
                throw new PagoProveedorException('El tipo de código del banco intermediario no es válido.');
            }
            foreach (['inter_nombre', 'inter_direccion', 'inter_pais', 'inter_ciudad'] as $campo) {
                if (trim((string) ($payload[$campo] ?? '')) === '') {
                    throw new PagoProveedorException('Faltan datos del banco intermediario.');
                }
            }
            $datos->intermediary_code_type = $tipoInter;
            $datos->intermediary_code = PagoProveedorDatosReglas::codigoBanco($tipoInter, (string) ($payload['inter_codigo'] ?? ''));
            $datos->intermediary_name = trim((string) $payload['inter_nombre']);
            $datos->intermediary_address = trim((string) $payload['inter_direccion']);
            $datos->intermediary_country = trim((string) $payload['inter_pais']);
            $datos->intermediary_city = trim((string) $payload['inter_ciudad']);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|string  $aplicaciones
     * @return array<int, float>
     */
    private function mapaAplicaciones(array|string $aplicaciones): array
    {
        if (is_string($aplicaciones)) {
            $aplicaciones = json_decode($aplicaciones, true) ?: [];
        }
        $mapa = [];
        foreach ($aplicaciones as $fila) {
            $mapa[(int) ($fila['solicitud_proveedor_id'] ?? 0)] = (float) ($fila['monto'] ?? 0);
        }

        return $mapa;
    }

    public function balance(PagoProveedorSolicitudProveedor $proveedor, ?int $exceptoAbonoId = null): float
    {
        $abonado = 0.0;
        foreach ($proveedor->aplicaciones as $aplicacion) {
            $abono = $aplicacion->abono;
            if (!$abono || $abono->status === PagoProveedorAbono::STATUS_CANCELLED) {
                continue;
            }
            if ($exceptoAbonoId && (int) $abono->id === $exceptoAbonoId) {
                continue;
            }
            $abonado += (float) $aplicacion->amount;
        }

        return round(max(0, $this->requiredAmount($proveedor) - $abonado), 2);
    }

    public function requiredAmount(PagoProveedorSolicitudProveedor $proveedor): float
    {
        $cuotas = $proveedor->summary_json['cuotas'] ?? [];
        $indice = max(0, ((int) $proveedor->current_installment) - 1);
        if (isset($cuotas[$indice]['total'])) {
            return round((float) $cuotas[$indice]['total'], 2);
        }

        return round((float) $proveedor->total_amount, 2);
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function intentarAvanzar(PagoProveedorSolicitud $solicitud, array $actor): void
    {
        $solicitud->load(['proveedores.datos', 'proveedores.aplicaciones.abono']);
        foreach ($solicitud->proveedores as $proveedor) {
            if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED || (int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA) {
                continue;
            }
            if (!$this->datosListos($proveedor) || $this->balance($proveedor) > 0.009) {
                continue;
            }
            if (!$proveedor->request_code) {
                $proveedor->request_code = $this->codes->armar($proveedor->currency, $this->siguienteCorrelativo());
            }
            $proveedor->stage = PagoProveedorSolicitudProveedor::STAGE_VALIDATION;
            $proveedor->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitud->id, 'Pasó a validación', $actor, $proveedor->code_supplier);
            app(PagoProveedorAprobacionService::class)->evaluate($proveedor, $actor);
        }
    }

    private function datosListos(PagoProveedorSolicitudProveedor $proveedor): bool
    {
        $datos = $proveedor->datos;
        if (!$datos) {
            return false;
        }
        if ($proveedor->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB) {
            return trim((string) $datos->seller_name) !== '' && trim((string) $datos->wechat) !== '';
        }

        return $datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED;
    }

    private function siguienteCorrelativo(): int
    {
        $fila = PagoProveedorConfiguracion::query()->where('setting_key', 'correlativo_solicitud')->lockForUpdate()->first();
        if (!$fila) {
            $fila = PagoProveedorConfiguracion::query()->create([
                'setting_key' => 'correlativo_solicitud',
                'setting_value' => ['n' => 0],
            ]);
            $fila = PagoProveedorConfiguracion::query()->whereKey($fila->id)->lockForUpdate()->first();
        }
        $siguiente = ((int) ($fila->setting_value['n'] ?? 0)) + 1;
        $fila->setting_value = ['n' => $siguiente];
        $fila->save();

        return $siguiente;
    }

    private function abonoEditable(PagoProveedorSolicitud $solicitud, int $abonoId): PagoProveedorAbono
    {
        $abono = $solicitud->abonos->firstWhere('id', $abonoId);
        if (!$abono) {
            throw new PagoProveedorException('El abono no existe.', 404);
        }
        if (!in_array($abono->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true)) {
            throw new PagoProveedorException('Solo se edita o anula un abono pendiente o observado.');
        }
        $avanzados = $abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->request_provider_id);

            return $proveedor && (int) $proveedor->stage > 1;
        });
        if ($avanzados) {
            throw new PagoProveedorException('Un proveedor de este abono ya pasó a validación.');
        }

        return $abono;
    }

    private function enlace(PagoProveedorDatoProveedor $datos): string
    {
        return rtrim((string) config('pago_proveedor.front_url'), '/') . '/pago-proveedor/formulario-tt/' . $datos->token;
    }

    private function textoFormulario(PagoProveedorSolicitudProveedor $proveedor, string $enlace, bool $porAutorizar): string
    {
        $nombre = $this->solicitudDe($proveedor)->client_name ?: 'cliente';
        $pedido = $porAutorizar
            ? 'Revisa y autoriza los datos bancarios que registramos para el pago.'
            : 'Completa el formulario bancario para pagarle a tu proveedor.';

        return "Hola, {$nombre} 🙋🏻‍♀️\n|\nProveedor: {$proveedor->code_supplier}\n{$proveedor->product}\n{$pedido}\n{$enlace}";
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function guardarMensaje(PagoProveedorSolicitudProveedor $proveedor, string $tipo, string $texto, array $actor): void
    {
        PagoProveedorMensaje::query()->create([
            'request_id' => $proveedor->request_id,
            'message_type' => $tipo,
            'recipient' => $this->solicitudDe($proveedor)->client_whatsapp,
            'body' => $texto,
            'user_id' => $actor['id'],
            'created_at' => now(),
        ]);
    }
}
