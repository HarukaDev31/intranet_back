<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorAbono;
use App\Models\PagoProveedor\PagoProveedorAbonoAplicacion;
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
        $approvals = app(PagoProveedorAprobacionService::class);
        foreach ($solicitud->proveedores as $provider) {
            $approvals->expireCollect($provider);
        }
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
            // Por autorizar: el borrador de Ventas lo revisa el cliente. Recibido: se le pide validarlo de nuevo.
            if (!in_array($datos->status, [PagoProveedorDatoProveedor::STATUS_RECEIVED, PagoProveedorDatoProveedor::STATUS_AWAITING_AUTHORIZATION], true)) {
                throw new PagoProveedorException('Solo se pide validación de datos ya llenados.');
            }
            $datos->correction_requested = $datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED;
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
    public function anularProveedor(int $solicitudId, int $proveedorId, string $motivo, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $motivo, $actor) {
            $proveedor = $this->proveedorEtapa1($solicitudId, $proveedorId);
            $proveedor->status = PagoProveedorSolicitudProveedor::STATUS_CANCELLED;
            $proveedor->cancellation_reason = $motivo;
            $proveedor->sales_observation = null;
            $proveedor->sales_observed_at = null;
            $proveedor->save();
            $this->recalculateYuanBankFee($this->solicitudDe($proveedor));
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Proveedor anulado', $actor, $proveedor->code_supplier . ' · ' . $motivo);

            return $this->recargar($this->solicitudDe($proveedor));
        });
    }

    /**
     * Sales pauses a supplier until the incident is solved. Validation belongs to Administration.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function observarProveedor(int $solicitudId, int $proveedorId, string $motivo, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $motivo, $actor) {
            $solicitud = $this->solicitud($solicitudId, true);
            $proveedor = $solicitud->proveedores->firstWhere('id', $proveedorId);
            if (!$proveedor) {
                throw new PagoProveedorException('El proveedor no pertenece a esta solicitud.', 404);
            }
            if ($proveedor->status !== PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS) {
                throw new PagoProveedorException('Solo se observa un proveedor en proceso.');
            }
            if ((int) $proveedor->stage === PagoProveedorSolicitudProveedor::STAGE_VALIDATION) {
                throw new PagoProveedorException('En Validación de pago observa Administración.');
            }
            $proveedor->status = PagoProveedorSolicitudProveedor::STATUS_OBSERVED;
            $proveedor->sales_observation = $motivo;
            $proveedor->sales_observed_at = now();
            $proveedor->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Proveedor observado', $actor, $proveedor->code_supplier . ' · ' . $motivo);

            return $this->recargar($solicitud);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function levantarObservacionVentas(int $solicitudId, int $proveedorId, string $motivo, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($solicitudId, $proveedorId, $motivo, $actor) {
            $solicitud = $this->solicitud($solicitudId, true);
            $proveedor = $solicitud->proveedores->firstWhere('id', $proveedorId);
            if (!$proveedor) {
                throw new PagoProveedorException('El proveedor no pertenece a esta solicitud.', 404);
            }
            $registrationError = $proveedor->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR;
            if (!$proveedor->sales_observation && !$registrationError) {
                throw new PagoProveedorException('Este proveedor no tiene una observación que puedas levantar.');
            }
            $proveedor->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            $proveedor->sales_observation = null;
            $proveedor->sales_observed_at = null;
            $proveedor->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Observación levantada', $actor, $proveedor->code_supplier . ' · ' . $motivo);
            if ($registrationError) {
                app(PagoProveedorAprobacionService::class)->liftRegistrationError($proveedor, $actor);
            }
            $this->intentarAvanzar($solicitud, $actor);

            return $this->recargar($solicitud);
        });
    }

    /**
     * The yuan bank fee moves to the next supplier when its carrier is cancelled,
     * and stays as it is once one of them advanced (RN-35).
     */
    private function recalculateYuanBankFee(PagoProveedorSolicitud $request): void
    {
        $providers = $request->proveedores()->get()->filter(function (PagoProveedorSolicitudProveedor $provider) {
            return $provider->status !== PagoProveedorSolicitudProveedor::STATUS_CANCELLED
                && $provider->currency === PagoProveedorCotizacionProveedor::CURRENCY_RMB
                && (bool) $provider->charges_bank_fee;
        })->sortBy('position')->values();
        if ($providers->isEmpty() || $providers->contains(fn (PagoProveedorSolicitudProveedor $provider) => (int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA)) {
            return;
        }

        $summary = app(PagoProveedorComisionService::class)->calcular(
            $providers->map(fn (PagoProveedorSolicitudProveedor $provider) => [
                'orden' => (int) $provider->position,
                'moneda' => $provider->currency,
                'modalidad_abono' => $provider->client_payment_mode,
                'modalidad_pago' => $provider->supplier_payment_mode,
                'valor_carga_usd' => (float) $provider->cargo_value_usd,
                'porcentaje_1' => $provider->first_share_percent,
                'porcentaje_2' => $provider->second_share_percent,
                'comision_bancaria' => (bool) $provider->charges_bank_fee,
                'comision_administrativa' => (bool) $provider->charges_admin_fee,
            ])->all(),
            app(PagoProveedorConfiguracionService::class)->tariff()
        );

        foreach ($providers as $index => $provider) {
            $line = $summary['proveedores'][$index];
            $provider->bank_fee_amount = $line['bancaria'];
            $provider->total_amount = $line['total'];
            $provider->bank_fee_on_position = $line['bancaria_incluida_en'];
            $provider->summary_json = $line;
            $provider->save();
        }
    }

    /**
     * @return array<int, array{id: int, titulo: string, moneda: string, incompleto: bool}>
     */
    public function candidatosPedirDatos(int $solicitudId): array
    {
        $solicitud = $this->solicitud($solicitudId);
        $rows = [];
        foreach ($solicitud->proveedores as $proveedor) {
            if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED || $proveedor->sales_observation || (int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA) {
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
     * Vista previa de «Pedir datos del proveedor». Con $soloVista no cambia nada (se usa mientras
     * Ventas elige proveedores en la misma ventana); sin él queda registrado el pedido (flujo anterior).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @param  array<int, int>|null  $providerIds
     * @return array<string, mixed>
     */
    public function vistaPreviaPedirDatos(int $solicitudId, array $actor, ?array $providerIds = null, bool $soloVista = false): array
    {
        if ($soloVista) {
            $pedido = $this->armarPedidoDatos($this->solicitud($solicitudId), $providerIds, false);

            return [
                'texto' => $pedido['texto'],
                'destinatario' => $pedido['destinatario'],
                'puede_enviar' => $pedido['destinatario'] !== null,
                'nota' => $pedido['destinatario'] !== null
                    ? 'Revisa el mensaje y pulsa Enviar. Se enviará al WhatsApp del cliente desde el número de ventas.'
                    : 'Falta el WhatsApp del cliente para poder enviar.',
            ];
        }

        return DB::transaction(function () use ($solicitudId, $actor, $providerIds) {
            $solicitud = $this->solicitud($solicitudId, true);
            $pedido = $this->armarPedidoDatos($solicitud, $providerIds, true);
            $this->guardarMensaje($pedido['primero'], 'pedir_datos', $pedido['texto'], $actor);
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitudId, 'Pedido de datos', $actor, null);

            return [
                'texto' => $pedido['texto'],
                'nota' => 'Quedó la vista previa. El envío real por WhatsApp se habilita con la plantilla de Meta.',
                'solicitud' => $this->recargar($solicitud),
            ];
        });
    }

    /**
     * Envía por WhatsApp (número de ventas) el pedido de datos de los proveedores elegidos y lo registra.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @param  array<int, int>|null  $providerIds
     * @return array<string, mixed>
     */
    public function enviarPedirDatos(int $solicitudId, array $actor, ?array $providerIds = null): array
    {
        return DB::transaction(function () use ($solicitudId, $actor, $providerIds) {
            $solicitud = $this->solicitud($solicitudId, true);
            $pedido = $this->armarPedidoDatos($solicitud, $providerIds, true);
            if ($pedido['destinatario'] === null) {
                throw new PagoProveedorException('Falta el WhatsApp del cliente.');
            }
            app(PagoProveedorCotizacionWhatsappService::class)
                ->enviarTextoCliente($pedido['destinatario'], $pedido['texto'], 'pedir_datos solicitud ' . $solicitudId);
            $this->guardarMensaje($pedido['primero'], 'pedir_datos', $pedido['texto'], $actor);
            $this->historial->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $solicitudId,
                'Pedido de datos enviado por WhatsApp',
                $actor,
                'Enviado desde el número de ventas a ' . $pedido['destinatario']
            );

            return [
                'texto' => $pedido['texto'],
                'enviado' => true,
                'nota' => 'Pedido de datos enviado por WhatsApp desde el número de ventas.',
                'solicitud' => $this->recargar($solicitud),
            ];
        });
    }

    /**
     * @param  array<int, int>|null  $providerIds
     * @return array{texto: string, primero: PagoProveedorSolicitudProveedor, destinatario: string|null}
     */
    private function armarPedidoDatos(PagoProveedorSolicitud $solicitud, ?array $providerIds, bool $marcarEnviado): array
    {
        $selected = $providerIds === null ? null : array_map('intval', $providerIds);
        $hasYuan = false;
        $hasBankForm = false;
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
                $bloques[] = "✅ Proveedor: *{$proveedor->code_supplier} | {$proveedor->product}*\n📝 Datos:\n▪️ *Nombre del vendedor:*\n▪️ *N.° de WeChat:*";
                $hasYuan = true;
                continue;
            }
            $datos = $this->datosDe($proveedor);
            if ($datos->status === PagoProveedorDatoProveedor::STATUS_RECEIVED && !$datos->correction_requested) {
                continue;
            }
            if ($marcarEnviado && $datos->status === PagoProveedorDatoProveedor::STATUS_PENDING) {
                $datos->status = PagoProveedorDatoProveedor::STATUS_SENT;
                $datos->save();
            }
            $bloques[] = "✅ Proveedor: *{$proveedor->code_supplier} | {$proveedor->product}*\n📝 Datos:\n▪️ *Formulario bancario:* {$this->enlace($datos)}";
            $hasBankForm = true;
        }
        if ($bloques === [] || !$primero) {
            throw new PagoProveedorException('Los proveedores de esta solicitud ya tienen los datos.');
        }
        $whatsapp = trim((string) $solicitud->client_whatsapp);

        return [
            'texto' => $this->dataRequestText($this->mensajes()->firstName($solicitud->client_name), $bloques, $hasYuan, $hasBankForm),
            'primero' => $primero,
            'destinatario' => $whatsapp !== '' ? $whatsapp : null,
        ];
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
            $texto = implode("\n", [
                'Hola, ' . $this->mensajes()->firstName($solicitud->client_name) . ' 🙋🏻‍♀️',
                'Te compartimos nuestras cuentas bancarias para que puedas realizar el depósito de tu pago. 👆🏻',
                '',
                '*Concepto:* Pago a proveedor',
                '',
                count($lineas) > 1 ? '📦 Detalle de proveedores:' : '📦 Detalle del proveedor:',
                ...array_map(fn (string $line) => '➡️ ' . $line, $lineas),
                '',
                '💰 *Total a depositar: $' . number_format($total, 2, '.', ',') . '*',
                '',
                '🧾 Una vez realizado el depósito, envíanos tu comprobante de pago por este medio para registrarlo.',
                '¡Gracias! 😊',
            ]);
            $this->guardarMensaje($primero, 'cuentas', $texto, $actor);

            return [
                'tipo' => 'cuentas',
                'texto' => $texto,
                'imagen' => '/images/pago-proveedor/cuentas-bancarias.jpg',
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
                $abono = $this->abonoEditable($solicitud, $abonoId, true);
            }

            $editandoEnValidacion = $abono
                && $abono->status === PagoProveedorAbono::STATUS_IN_VALIDATION
                && !$this->tieneErrorDeRegistro($solicitud, $abono);
            $correctedProviderIds = $abono && ($abono->status === PagoProveedorAbono::STATUS_OBSERVED || $editandoEnValidacion || $this->tieneErrorDeRegistro($solicitud, $abono))
                ? $abono->aplicaciones->pluck('request_provider_id')->map(fn ($id) => (int) $id)->all()
                : [];
            $saldos = [];
            $aprobacion = app(PagoProveedorAprobacionService::class);
            foreach ($solicitud->proveedores as $proveedor) {
                if ($proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED) {
                    continue;
                }
                $porCobrar = $proveedor->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_TO_COLLECT;
                $correcting = in_array((int) $proveedor->id, $correctedProviderIds, true);
                if ((int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_DATA && !$porCobrar && !$correcting) {
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
                'surplus_decision' => $this->evaluador->decisionExcedente(
                    $evaluacion['excedente'],
                    $hayCuota2,
                    (float) app(PagoProveedorConfiguracionService::class)->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1)
                ),
                'repeated_operation' => $repetida,
                'user_id' => $actor['id'],
                'observation_reason' => null,
                'observation_detail' => null,
                'observe_until' => null,
                'observation_escalated_at' => null,
                'verified_at' => null,
                'verified_by_user_id' => null,
                'bank_amount' => null,
                'recorded_amount' => null,
                'admin_corrections' => null,
            ];

            if ($abono) {
                $wasObserved = $abono->status === PagoProveedorAbono::STATUS_OBSERVED;
                $abono->fill($datosAbono);
                $abono->save();
                if ($wasObserved) {
                    app(PagoProveedorVerificacionService::class)->releaseObservedProviders($abono);
                }
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
            if ($editandoEnValidacion) {
                $this->regresarSinCobertura($this->recargar($solicitud, ['proveedores.datos', 'proveedores.aplicaciones.abono']), $correctedProviderIds, $actor);
            }
            $this->intentarAvanzar($this->recargar($solicitud, ['proveedores.datos', 'proveedores.aplicaciones.abono']), $actor);

            $presentado = $this->recargar($solicitud);

            \App\Services\Verificacion\VerificacionContadoresBroadcaster::emit(['supplier_payment']);

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
                app(PagoProveedorReembolsoService::class)->ensureSurplus($abono, $actor, true);
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
            $abonado += $this->creditedAmount($aplicacion, $abono);
        }

        return round(max(0, $this->requiredAmount($proveedor) - $abonado), 2);
    }

    /**
     * The bank rules over what Sales recorded (RN-62). A shared voucher keeps its split:
     * Sales assigns that difference when choosing who continues (RN-66).
     */
    private function creditedAmount(PagoProveedorAbonoAplicacion $application, PagoProveedorAbono $deposit): float
    {
        $applied = (float) $application->amount;
        if ($deposit->status !== PagoProveedorAbono::STATUS_VALIDATED || $deposit->bank_amount === null) {
            return $applied;
        }
        $deposit->loadMissing('aplicaciones');
        if ($deposit->aplicaciones->count() > 1) {
            return $applied;
        }

        return min($applied, (float) $deposit->bank_amount);
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
                $proveedor->request_code = $this->codes->deSolicitud($solicitud, $proveedor->currency);
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

    private function abonoEditable(PagoProveedorSolicitud $solicitud, int $abonoId, bool $correcting = false): PagoProveedorAbono
    {
        $abono = $solicitud->abonos->firstWhere('id', $abonoId);
        if (!$abono) {
            throw new PagoProveedorException('El abono no existe.', 404);
        }
        // Con un error de registro Ventas corrige su voucher aunque ya esté validado (ES-02).
        if ($correcting && $this->tieneErrorDeRegistro($solicitud, $abono)) {
            return $abono;
        }
        if (!in_array($abono->status, [PagoProveedorAbono::STATUS_IN_VALIDATION, PagoProveedorAbono::STATUS_OBSERVED], true)) {
            throw new PagoProveedorException('Solo se edita o anula un abono pendiente o observado.');
        }
        // Sales lifts a deposit observation in the same stage (RN-56, RN-111).
        if ($correcting && $abono->status === PagoProveedorAbono::STATUS_OBSERVED) {
            return $abono;
        }
        // Mientras Administración no lo valide, Ventas corrige su abono aunque el proveedor ya esté en Validación de pago.
        if ($correcting && $abono->status === PagoProveedorAbono::STATUS_IN_VALIDATION && self::abonoCorregibleEnValidacion($solicitud, $abono)) {
            return $abono;
        }
        $avanzados =$abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->request_provider_id);

            return $proveedor && (int) $proveedor->stage > 1;
        });
        if ($avanzados) {
            throw new PagoProveedorException('Un proveedor de este abono ya pasó a validación.');
        }

        return $abono;
    }

    /**
     * Un abono en validación se corrige si ninguno de sus proveedores pasó de Validación de pago ni tiene el pago aprobado.
     */
    public static function abonoCorregibleEnValidacion(?PagoProveedorSolicitud $solicitud, PagoProveedorAbono $abono): bool
    {
        if ($abono->status !== PagoProveedorAbono::STATUS_IN_VALIDATION) {
            return false;
        }

        return !$abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud?->proveedores?->firstWhere('id', $aplicacion->request_provider_id);

            return !$proveedor
                || $proveedor->status === PagoProveedorSolicitudProveedor::STATUS_CANCELLED
                || (int) $proveedor->stage > PagoProveedorSolicitudProveedor::STAGE_VALIDATION
                || $proveedor->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_APPROVED;
        });
    }

    /**
     * Si al corregir un abono en validación un proveedor deja de estar cubierto, vuelve a la etapa 1.
     *
     * @param  array<int, int>  $providerIds
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    private function regresarSinCobertura(PagoProveedorSolicitud $solicitud, array $providerIds, array $actor): void
    {
        foreach ($solicitud->proveedores as $proveedor) {
            if (!in_array((int) $proveedor->id, $providerIds, true)
                || (int) $proveedor->stage !== PagoProveedorSolicitudProveedor::STAGE_VALIDATION
                || $this->balance($proveedor) <= 0.009) {
                continue;
            }
            $proveedor->stage = PagoProveedorSolicitudProveedor::STAGE_DATA;
            $proveedor->save();
            $this->historial->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $solicitud->id, 'Volvió a etapa 1', $actor, $proveedor->code_supplier . ': el abono corregido ya no cubre el total.');
        }
    }

    private function tieneErrorDeRegistro(PagoProveedorSolicitud $solicitud, PagoProveedorAbono $abono): bool
    {
        return $abono->status !== PagoProveedorAbono::STATUS_CANCELLED && $abono->aplicaciones->contains(function ($aplicacion) use ($solicitud) {
            $proveedor = $solicitud->proveedores->firstWhere('id', $aplicacion->request_provider_id);

            return $proveedor && $proveedor->approval_status === PagoProveedorSolicitudProveedor::APPROVAL_REGISTRATION_ERROR;
        });
    }

    private function enlace(PagoProveedorDatoProveedor $datos): string
    {
        return rtrim((string) config('pago_proveedor.clientes_url'), '/') . '/pago-proveedor/formulario-tt/' . $datos->token;
    }

    private function textoFormulario(PagoProveedorSolicitudProveedor $proveedor, string $enlace, bool $porAutorizar): string
    {
        $nombre = $this->mensajes()->firstName($this->solicitudDe($proveedor)->client_name);
        $pedido = $porAutorizar
            ? 'Revisa y autoriza los datos bancarios que registramos para el pago.'
            : 'Para pagar a tu proveedor por transferencia internacional (T-T) necesitamos sus datos bancarios.';
        $bloque = "✅ Proveedor: *{$proveedor->code_supplier} | {$proveedor->product}*\n📝 Datos:\n▪️ *Formulario bancario:* {$enlace}";

        return "Hola, {$nombre} 🙋🏻‍♀️\n{$pedido}\n\n{$bloque}\n\n👉 Apenas lo envíes, lo recibimos y continuamos con el proceso.";
    }

    private function mensajes(): PagoProveedorMensajeService
    {
        return app(PagoProveedorMensajeService::class);
    }

    /**
     * @param  array<int, string>  $blocks
     */
    private function dataRequestText(string $name, array $blocks, bool $hasYuan, bool $hasBankForm): string
    {
        if (count($blocks) === 1) {
            $intro = $hasBankForm
                ? 'Para pagar a tu proveedor por transferencia internacional (T-T) necesitamos sus datos bancarios.'
                : 'Para pagar a tu proveedor necesitamos 2 datos de su vendedor.';
            $closing = $hasBankForm
                ? 'Apenas lo envíes, lo recibimos y continuamos con el proceso.'
                : 'Apenas los tengamos, continuamos con el proceso.';
        } elseif ($hasBankForm && $hasYuan) {
            $intro = 'Para pagar a tus proveedores necesitamos lo siguiente de cada uno:';
            $closing = 'Los datos del vendedor compártelos por aquí; los datos bancarios, en cada formulario. Apenas los tengamos, continuamos con el proceso.';
        } elseif ($hasBankForm) {
            $intro = 'Para pagar a tus proveedores por transferencia internacional (T-T) necesitamos sus datos bancarios:';
            $closing = 'Completa un formulario por cada proveedor y continuamos con el proceso.';
        } else {
            $intro = 'Para pagar a tus proveedores necesitamos 2 datos del vendedor de cada uno:';
            $closing = 'Compártelos por aquí y continuamos con el proceso.';
        }

        return "Hola, {$name} 🙋🏻‍♀️\n{$intro}\n\n" . implode("\n\n", $blocks) . "\n\n👉 {$closing}";
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
