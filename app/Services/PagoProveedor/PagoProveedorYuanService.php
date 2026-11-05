<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\PagoProveedor\PagoProveedorConfiguracion;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoProveedorYuanService
{
    use UsesObjectStorage;

    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuration,
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PagoProveedorSolicitudProveedor>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $bucket = (string) ($filters['bucket'] ?? PagoProveedorEjecucion::BUCKET_PENDING);
        $rows = $this->rows($bucket, $this->searchOf($filters), $this->partnerOf($filters));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    public function pendingCount(): int
    {
        return $this->rows(PagoProveedorEjecucion::BUCKET_PENDING, '')->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{usd_received: float, yuan_obtained: float, yuan_paid: float, to_collect: float, to_collect_yuan: float}
     */
    public function indicators(array $filters = []): array
    {
        $rows = $this->rows('all', $this->searchOf($filters), $this->partnerOf($filters));
        $usd = 0.0;
        $obtained = 0.0;
        $paid = 0.0;
        $collectUsd = 0.0;
        $collectYuan = 0.0;
        foreach ($rows as $provider) {
            $usd += $this->quote($provider)['usd'];
            foreach ($provider->ejecuciones as $execution) {
                $obtained += (float) $execution->yuan_obtained;
                $paid += (float) $execution->yuan_paid;
                if ($execution->exchange_result !== PagoProveedorEjecucion::RESULT_COLLECT) {
                    continue;
                }
                $collectUsd += abs((float) $execution->exchange_difference_usd);
                $collectYuan += abs((float) $execution->yuan_obtained - (float) $execution->yuan_paid);
            }
        }

        return [
            'usd_received' => round($usd, 2),
            'yuan_obtained' => round($obtained, 2),
            'yuan_paid' => round($paid, 2),
            'to_collect' => round($collectUsd, 2),
            'to_collect_yuan' => round($collectYuan, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{all: int, pending: int, observed: int, paid: int}
     */
    public function counts(array $filters): array
    {
        $search = $this->searchOf($filters);
        $partner = $this->partnerOf($filters);

        return [
            'all' => $this->rows('all', $search, $partner)->count(),
            'pending' => $this->rows(PagoProveedorEjecucion::BUCKET_PENDING, $search, $partner)->count(),
            'observed' => $this->rows('observed', $search, $partner)->count(),
            'paid' => $this->rows(PagoProveedorEjecucion::BUCKET_PAID, $search, $partner)->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function partners(): array
    {
        return $this->rows('all', '', '')
            ->map(fn (PagoProveedorSolicitudProveedor $provider) => PagoProveedorSolicitud::partnerLabel($provider->solicitud?->partner))
            ->filter(fn (string $partner) => $partner !== '')
            ->merge(PagoProveedorSolicitud::PARTNERS)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function confirmNotice(int $providerId, float $yuanPaid, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $yuanPaid, $actor) {
            $provider = $this->lock($providerId);
            $this->requireReady($provider);
            if ($provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL) {
                throw new PagoProveedorException('Las solicitudes manuales no avisan al cliente.');
            }
            if ($yuanPaid <= 0) {
                throw new PagoProveedorException('Ingresa primero los yuanes pagados.');
            }
            app(PagoProveedorMensajeService::class)->confirmRmbNotice($provider, $yuanPaid, $actor);

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    public function noticeText(int $providerId): string
    {
        $provider = PagoProveedorSolicitudProveedor::query()->with('solicitud')->find($providerId);
        if (!$provider) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }

        return app(PagoProveedorMensajeService::class)->rmbNoticeText($provider);
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function reasons(): array
    {
        $reasons = $this->defaultReasons();
        $saved = $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_CHINA_REASONS, []);
        if (!is_array($saved)) {
            return $reasons;
        }
        foreach ($saved as $row) {
            if (!is_array($row) || empty($row['code']) || empty($row['label'])) {
                continue;
            }
            $code = (string) $row['code'];
            if (collect($reasons)->contains(fn (array $item) => $item['code'] === $code)) {
                continue;
            }
            $reasons[] = ['code' => $code, 'label' => (string) $row['label']];
        }

        return $reasons;
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function register(int $providerId, float $realRate, float $yuanPaid, UploadedFile $receipt, array $actor): PagoProveedorEjecucion
    {
        return DB::transaction(function () use ($providerId, $realRate, $yuanPaid, $receipt, $actor) {
            $provider = $this->lock($providerId);
            $this->requireReady($provider);
            if ($realRate <= 0 || $yuanPaid <= 0) {
                throw new PagoProveedorException('El tipo de cambio real y los yuanes pagados tienen que ser mayores que cero.');
            }

            $quote = $this->quote($provider);
            $manual = $provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL;
            if (!$manual && !app(PagoProveedorMensajeService::class)->rmbNoticeMatches($provider, $yuanPaid)) {
                throw new PagoProveedorException('Primero envía el aviso al cliente por WhatsApp. Si cambias los yuanes pagados, vuelve a enviarlo.');
            }
            $calculo = PagoProveedorYuanCalculo::calcular($quote['usd'], $realRate, $yuanPaid, $this->tolerance());
            $obtained = $calculo['obtained'];
            $differenceUsd = $calculo['difference_usd'];
            $result = $calculo['result'];
            $extension = strtolower($receipt->getClientOriginalExtension() ?: 'pdf');
            $path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                $receipt,
                'pago-proveedor/constancias',
                'constancia-rmb-'.$provider->id.'-'.now()->format('YmdHis').'.'.$extension
            ));

            $execution = PagoProveedorEjecucion::query()->create([
                'request_provider_id' => $provider->id,
                'installment' => (int) $provider->current_installment,
                'supplier_payment' => (int) $provider->current_payment,
                'cargo_amount' => $quote['usd'],
                'charged_bank_fee' => 0,
                'real_bank_fee' => 0,
                'charged_admin_fee' => 0,
                'bank_difference' => 0,
                'profit' => 0,
                'real_exchange_rate' => round($realRate, 4),
                'yuan_obtained' => $obtained,
                'yuan_paid' => round($yuanPaid, 2),
                'exchange_difference_usd' => $differenceUsd,
                'exchange_result' => $result,
                'receipt_path' => $path,
                'receipt_name' => $receipt->getClientOriginalName(),
                'executed_on' => now(PagoProveedorVigenciaService::ZONA)->toDateString(),
                'user_id' => $actor['id'],
            ]);

            if (!$manual) {
                $provider->exchange_result = $result;
            }
            $provider->china_observation_reason = null;
            $provider->china_observation_detail = null;
            $provider->china_observed_at = null;
            $provider->china_observe_until = null;
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            $provider->stage = PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION;
            $provider->save();
            $provider->load('ejecuciones');
            if (!$manual && $result === PagoProveedorEjecucion::RESULT_REFUND) {
                app(PagoProveedorReembolsoService::class)->ensureExchange($provider, $actor);
            }

            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Pago en yuanes',
                $actor,
                $provider->code_supplier.' · '.$this->resultLabel($result)
            );
            if (!$manual) {
                app(PagoProveedorConfirmacionService::class)->open($provider);
            }
            $execution->load(['proveedor.solicitud', 'proveedor.datos']);

            return $execution;
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function observe(int $providerId, string $reason, ?string $detail, ?string $newLabel, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $reason, $detail, $newLabel, $actor) {
            $provider = $this->lock($providerId);
            $this->requireReady($provider);
            $code = $this->resolveReason($reason, $newLabel);
            $hours = max(1, (int) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_CHINA_HOURS, 48));
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_OBSERVED;
            $provider->china_observation_reason = $code;
            $provider->china_observation_detail = $detail !== null && trim($detail) !== '' ? trim($detail) : null;
            $provider->china_observed_at = now();
            $provider->china_observe_until = now()->addHours($hours);
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Observado por China',
                $actor,
                trim($this->reasonLabel($code).' '.($provider->china_observation_detail ?? ''))
            );

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function lift(int $requestId, int $providerId, string $seller, string $wechat, array $actor): \App\Models\PagoProveedor\PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $providerId, $seller, $wechat, $actor) {
            $provider = PagoProveedorSolicitudProveedor::query()
                ->with('datos')
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->find($providerId);
            if (!$provider || $provider->china_observation_reason === null) {
                throw new PagoProveedorException('Este pago no tiene una observación de China.', 404);
            }
            $data = $provider->datos ?: $provider->datos()->create([
                'status' => PagoProveedorDatoProveedor::STATUS_RECEIVED,
                'source' => 'ventas',
            ]);
            $data->seller_name = trim($seller);
            $data->wechat = PagoProveedorDatosReglas::wechat($wechat);
            if ($data->seller_name === '') {
                throw new PagoProveedorException('Falta el nombre del vendedor.');
            }
            $data->status = PagoProveedorDatoProveedor::STATUS_RECEIVED;
            $data->save();
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            $provider->china_observation_reason = null;
            $provider->china_observation_detail = null;
            $provider->china_observed_at = null;
            $provider->china_observe_until = null;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $requestId,
                'Observación de China levantada',
                $actor,
                $provider->code_supplier
            );

            return app(PagoProveedorEtapa1Service::class)->detalle($requestId);
        });
    }

    /**
     * Las solicitudes manuales las levanta China (RN-96). Las de Perú las levanta Ventas.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function liftManual(int $providerId, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $actor) {
            $provider = $this->lock($providerId);
            $request = $provider->solicitud;
            if ($request?->origin !== PagoProveedorSolicitud::ORIGIN_MANUAL) {
                throw new PagoProveedorException('Esta observación la levanta Ventas.');
            }
            if ($provider->china_observation_reason === null) {
                throw new PagoProveedorException('Este pago no está observado.');
            }
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            $provider->stage = PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
            $provider->china_observation_reason = null;
            $provider->china_observation_detail = null;
            $provider->china_observed_at = null;
            $provider->china_observe_until = null;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Observación manual levantada',
                $actor,
                $provider->code_supplier
            );

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function deleteManual(int $providerId, array $actor): void
    {
        DB::transaction(function () use ($providerId, $actor) {
            $provider = $this->lock($providerId);
            $request = $provider->solicitud;
            if ($request?->origin !== PagoProveedorSolicitud::ORIGIN_MANUAL) {
                throw new PagoProveedorException('Solo se eliminan las solicitudes creadas aquí.');
            }
            if ($provider->ejecuciones()->exists()) {
                throw new PagoProveedorException('Este pago ya tiene constancia. No se puede eliminar.');
            }
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Solicitud manual eliminada',
                $actor,
                $provider->code_supplier
            );
            $provider->datos()->delete();
            $provider->delete();
            $request->delete();
        });
    }

    /**
     * @param  array{partner: string, client_name: string, consolidation: string, product: string, supplier_code: ?string, supplier_name: ?string, seller_name: string, wechat: string, usd_amount: float, yuan_amount: float}  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function createManual(array $payload, ?UploadedFile $proforma, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($payload, $proforma, $actor) {
            $code = trim((string) ($payload['supplier_code'] ?? ''));
            $name = trim((string) ($payload['supplier_name'] ?? ''));
            if ($code === '' && $name === '') {
                throw new PagoProveedorException('Indica el código o el nombre del proveedor.');
            }
            $partner = trim((string) $payload['partner']);
            if (stripos($partner, 'pro business') !== false) {
                throw new PagoProveedorException('Las solicitudes de Pro Business llegan desde Aprobación. Aquí solo van las de otros socios.');
            }
            $wechat = preg_replace('/\s+/', '', (string) $payload['wechat']) ?? '';
            if (!preg_match('/^\d{5,20}$/', $wechat)) {
                throw new PagoProveedorException('El N.° de WeChat es un número: solo dígitos, entre 5 y 20.');
            }
            $usd = round((float) $payload['usd_amount'], 2);
            $yuan = round((float) $payload['yuan_amount'], 2);
            if ($usd <= 0 || $yuan <= 0) {
                throw new PagoProveedorException('Los montos en dólares y yuanes tienen que ser mayores que cero.');
            }
            $rate = round($yuan / $usd, 4);
            $order = ((int) PagoProveedorSolicitud::query()->max('order_number')) + 1;
            $quote = PagoProveedorCotizacion::query()->create([
                'code' => 'MAN'.$order,
                'consolidated_quote_code' => trim($payload['consolidation']),
                'status' => PagoProveedorCotizacion::STATUS_CONFIRMED,
                'valid_until' => now()->addDay(),
                'exchange_rate' => $rate,
                'exchange_rate_confirmed' => true,
                'client_name' => trim($payload['client_name']),
                'consolidation' => trim($payload['consolidation']),
                'user_id' => $actor['id'],
            ]);
            $path = null;
            $fileName = null;
            if ($proforma) {
                $extension = strtolower($proforma->getClientOriginalExtension() ?: 'pdf');
                $path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                    $proforma,
                    'pago-proveedor/proformas',
                    'manual-'.$order.'-'.now()->format('YmdHis').'.'.$extension
                ));
                $fileName = $proforma->getClientOriginalName();
            }
            $request = PagoProveedorSolicitud::query()->create([
                'order_number' => $order,
                'quote_id' => $quote->id,
                'consolidated_quote_code' => trim($payload['consolidation']),
                'exchange_rate' => $rate,
                'exchange_rate_confirmed' => true,
                'client_name' => trim($payload['client_name']),
                'consolidation' => trim($payload['consolidation']),
                'partner' => $partner,
                'origin' => PagoProveedorSolicitud::ORIGIN_MANUAL,
                'user_id' => $actor['id'],
            ]);
            $provider = $request->proveedores()->create([
                'position' => 1,
                'code_supplier' => $code !== '' ? $code : 'Sin código',
                'supplier_name' => $name !== '' ? $name : null,
                'product' => trim($payload['product']),
                'currency' => PagoProveedorCotizacionProveedor::CURRENCY_RMB,
                'payment_type' => 'transferencia',
                'cargo_value_usd' => $usd,
                'client_payment_mode' => 'total',
                'supplier_payment_mode' => 'total',
                'first_share_percent' => 100,
                'total_amount' => $usd,
                'summary_json' => ['cuotas' => [['carga' => $usd, 'total' => $usd]], 'pagos_proveedor' => [['carga' => $usd]]],
                'proforma_path' => $path,
                'proforma_name' => $fileName,
                'request_code' => app(PagoProveedorCodigoSolicitud::class)->armar(PagoProveedorCotizacionProveedor::CURRENCY_RMB, $this->nextCorrelative()),
                'stage' => PagoProveedorSolicitudProveedor::STAGE_EXECUTION,
                'status' => PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS,
                'current_installment' => 1,
                'current_payment' => 1,
                'approval_status' => PagoProveedorSolicitudProveedor::APPROVAL_APPROVED,
                'approval_mode' => PagoProveedorSolicitudProveedor::APPROVAL_MANUAL,
            ]);
            $provider->datos()->create([
                'seller_name' => trim($payload['seller_name']),
                'wechat' => PagoProveedorDatosReglas::wechat($wechat),
                'status' => PagoProveedorDatoProveedor::STATUS_RECEIVED,
                'source' => 'china',
                'token' => Str::random(40),
            ]);
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $request->id, 'Solicitud manual', $actor, $provider->code_supplier.' · '.$request->partner);

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @param  array{partner: string, client_name: string, consolidation: string, product: string, supplier_code: ?string, supplier_name: ?string, seller_name: string, wechat: string, usd_amount: float, yuan_amount: float}  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function updateManual(int $providerId, array $payload, ?UploadedFile $proforma, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $payload, $proforma, $actor) {
            $provider = $this->lock($providerId);
            $request = $provider->solicitud;
            if ($request?->origin !== PagoProveedorSolicitud::ORIGIN_MANUAL) {
                throw new PagoProveedorException('Solo se editan las solicitudes creadas aquí.');
            }
            if ($provider->ejecuciones()->exists()) {
                throw new PagoProveedorException('Este pago ya tiene constancia. No se puede editar.');
            }
            $code = trim((string) ($payload['supplier_code'] ?? ''));
            $name = trim((string) ($payload['supplier_name'] ?? ''));
            if ($code === '' && $name === '') {
                throw new PagoProveedorException('Indica el código o el nombre del proveedor.');
            }
            $partner = trim((string) $payload['partner']);
            if (stripos($partner, 'pro business') !== false) {
                throw new PagoProveedorException('Las solicitudes de Pro Business llegan desde Aprobación. Aquí solo van las de otros socios.');
            }
            $wechat = preg_replace('/\s+/', '', (string) $payload['wechat']) ?? '';
            if (!preg_match('/^\d{5,20}$/', $wechat)) {
                throw new PagoProveedorException('El N.° de WeChat es un número: solo dígitos, entre 5 y 20.');
            }
            $usd = round((float) $payload['usd_amount'], 2);
            $yuan = round((float) $payload['yuan_amount'], 2);
            if ($usd <= 0 || $yuan <= 0) {
                throw new PagoProveedorException('Los montos en dólares y yuanes tienen que ser mayores que cero.');
            }
            $rate = round($yuan / $usd, 4);
            $request->exchange_rate = $rate;
            $request->client_name = trim($payload['client_name']);
            $request->consolidation = trim($payload['consolidation']);
            $request->consolidated_quote_code = trim($payload['consolidation']);
            $request->partner = $partner;
            $request->save();
            $quote = $request->cotizacion;
            if ($quote) {
                $quote->exchange_rate = $rate;
                $quote->client_name = trim($payload['client_name']);
                $quote->consolidation = trim($payload['consolidation']);
                $quote->consolidated_quote_code = trim($payload['consolidation']);
                $quote->save();
            }
            if ($proforma) {
                $extension = strtolower($proforma->getClientOriginalExtension() ?: 'pdf');
                $provider->proforma_path = $this->storageFinalizeCdnPath($this->storageStoreUpload(
                    $proforma,
                    'pago-proveedor/proformas',
                    'manual-'.$request->order_number.'-'.now()->format('YmdHis').'.'.$extension
                ));
                $provider->proforma_name = $proforma->getClientOriginalName();
            }
            $provider->code_supplier = $code !== '' ? $code : 'Sin código';
            $provider->supplier_name = $name !== '' ? $name : null;
            $provider->product = trim($payload['product']);
            $provider->cargo_value_usd = $usd;
            $provider->total_amount = $usd;
            $provider->summary_json = ['cuotas' => [['carga' => $usd, 'total' => $usd]], 'pagos_proveedor' => [['carga' => $usd]]];
            $provider->save();
            $datos = $provider->datos;
            if ($datos) {
                $datos->seller_name = trim($payload['seller_name']);
                $datos->wechat = PagoProveedorDatosReglas::wechat($wechat);
                $datos->save();
            }
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $request->id, 'Solicitud manual editada', $actor, $provider->code_supplier.' · '.$partner);

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @return array{usd: float, quoted_rate: float, quoted_yuan: float}
     */
    public function quote(PagoProveedorSolicitudProveedor $provider): array
    {
        $provider->loadMissing('solicitud');
        $summary = is_array($provider->summary_json) ? $provider->summary_json : [];
        $installments = is_array($summary['cuotas'] ?? null) ? $summary['cuotas'] : [];
        $payments = is_array($summary['pagos_proveedor'] ?? null) ? $summary['pagos_proveedor'] : [];
        $installment = $installments[max(0, (int) $provider->current_installment - 1)] ?? [];
        $payment = $payments[max(0, (int) $provider->current_payment - 1)] ?? [];
        $usd = isset($payment['carga']) ? (float) $payment['carga'] : (float) ($installment['carga'] ?? $provider->cargo_value_usd);
        $solicitud = $provider->solicitud;
        $rate = $solicitud === null ? 0.0 : (float) ($solicitud->exchange_rate ?? 0);

        return [
            'usd' => round($usd, 2),
            'quoted_rate' => round($rate, 4),
            'quoted_yuan' => round($usd * $rate, 2),
        ];
    }

    public function classify(float $differenceUsd): string
    {
        if (abs($differenceUsd) <= $this->tolerance() + 0.001) {
            return PagoProveedorEjecucion::RESULT_WITHIN;
        }

        return $differenceUsd < 0 ? PagoProveedorEjecucion::RESULT_COLLECT : PagoProveedorEjecucion::RESULT_REFUND;
    }

    public function tolerance(): float
    {
        return (float) $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_TOLERANCE, 1);
    }

    public function resultLabel(string $result): string
    {
        return match ($result) {
            PagoProveedorEjecucion::RESULT_COLLECT => 'Diferencia por cobrar',
            PagoProveedorEjecucion::RESULT_REFUND => 'Diferencia a favor del cliente',
            default => 'Diferencia en tolerancia',
        };
    }

    public function reasonLabel(string $code): string
    {
        $match = collect($this->reasons())->firstWhere('code', $code);

        return $match['label'] ?? $code;
    }

    private function nextCorrelative(): int
    {
        $row = PagoProveedorConfiguracion::query()->where('setting_key', 'correlativo_solicitud')->lockForUpdate()->first();
        if (!$row) {
            $row = PagoProveedorConfiguracion::query()->create([
                'setting_key' => 'correlativo_solicitud',
                'setting_value' => ['n' => 0],
            ]);
            $row = PagoProveedorConfiguracion::query()->whereKey($row->id)->lockForUpdate()->first();
        }
        if (!$row) {
            throw new PagoProveedorException('No se pudo reservar el correlativo de la solicitud.');
        }
        $next = ((int) ($row->setting_value['n'] ?? 0)) + 1;
        $row->setting_value = ['n' => $next];
        $row->save();

        return $next;
    }

    /**
     * @return Collection<int, PagoProveedorSolicitudProveedor>
     */
    /**
     * @param  array<string, mixed>  $filters
     */
    private function searchOf(array $filters): string
    {
        return mb_strtolower(trim((string) ($filters['search'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function partnerOf(array $filters): string
    {
        return trim((string) ($filters['partner'] ?? ''));
    }

    /**
     * @return Collection<int, PagoProveedorSolicitudProveedor>
     */
    private function rows(string $bucket, string $search, string $partner = ''): Collection
    {
        $providers = PagoProveedorSolicitudProveedor::query()
            ->with(['solicitud', 'datos', 'ejecuciones'])
            ->where('currency', PagoProveedorCotizacionProveedor::CURRENCY_RMB)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PagoProveedorSolicitudProveedor $provider) => $this->inBucket($provider, $bucket));

        if ($partner !== '') {
            $providers = $providers->filter(function (PagoProveedorSolicitudProveedor $provider) use ($partner) {
                return PagoProveedorSolicitud::partnerLabel($provider->solicitud?->partner) === $partner;
            });
        }

        if ($search === '') {
            return $providers->values();
        }

        return $providers->filter(function (PagoProveedorSolicitudProveedor $provider) use ($search) {
            $request = $provider->solicitud;
            $haystack = mb_strtolower(implode(' ', array_filter([
                $provider->code_supplier,
                $provider->supplier_name,
                $provider->product,
                $provider->request_code,
                $provider->datos?->wechat,
                $request?->client_name,
                $request?->consolidation,
                (string) $request?->order_number,
            ])));

            return str_contains($haystack, $search);
        })->values();
    }

    private function inBucket(PagoProveedorSolicitudProveedor $provider, string $bucket): bool
    {
        $pending = $this->isPending($provider);
        $observed = $provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED
            && $provider->china_observation_reason !== null
            && (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
        $paid = $provider->ejecuciones->contains(
            fn (PagoProveedorEjecucion $execution) => (int) $execution->supplier_payment === (int) $provider->current_payment
                && (int) $execution->installment === (int) $provider->current_installment
        ) || ((int) $provider->stage >= PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION && $provider->ejecuciones->isNotEmpty());

        return match ($bucket) {
            PagoProveedorEjecucion::BUCKET_PAID => $paid && !$pending,
            'observed' => $observed,
            'all' => $pending || $observed || $paid,
            default => $pending,
        };
    }

    private function isPending(PagoProveedorSolicitudProveedor $provider): bool
    {
        if ((int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_EXECUTION) {
            return false;
        }
        if ($provider->approval_status !== PagoProveedorSolicitudProveedor::APPROVAL_APPROVED) {
            return false;
        }
        if ($provider->status !== PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS) {
            return false;
        }
        $manual = $provider->solicitud?->origin === \App\Models\PagoProveedor\PagoProveedorSolicitud::ORIGIN_MANUAL;
        if (!$manual && trim((string) $provider->proforma_path) === '') {
            return false;
        }
        $data = $provider->datos;
        if (!$data || trim((string) $data->seller_name) === '' || trim((string) $data->wechat) === '') {
            return false;
        }

        return !$provider->ejecuciones->contains(
            fn (PagoProveedorEjecucion $execution) => (int) $execution->installment === (int) $provider->current_installment
                && (int) $execution->supplier_payment === (int) $provider->current_payment
        );
    }

    private function requireReady(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->sales_observation) {
            throw new PagoProveedorException('Ventas observó este proveedor: el pago sigue cuando levante la observación.');
        }
        if (!$this->isPending($provider)) {
            throw new PagoProveedorException('Este pago en yuanes no está listo para registrar.');
        }
        if ($this->quote($provider)['quoted_rate'] <= 0) {
            throw new PagoProveedorException('Falta el tipo de cambio de la cotización.');
        }
    }

    private function lock(int $providerId): PagoProveedorSolicitudProveedor
    {
        $provider = PagoProveedorSolicitudProveedor::query()
            ->with(['solicitud', 'datos', 'ejecuciones'])
            ->lockForUpdate()
            ->find($providerId);
        if (!$provider) {
            throw new PagoProveedorException('El pago no existe.', 404);
        }

        return $provider;
    }

    private function resolveReason(string $reason, ?string $newLabel): string
    {
        $reason = trim($reason);
        $known = collect($this->reasons())->keyBy('code');
        if ($reason !== '' && $known->has($reason)) {
            return $reason;
        }
        $label = trim((string) $newLabel);
        if ($label === '') {
            throw new PagoProveedorException('Elige un motivo de observación.');
        }
        $code = Str::slug($label, '_');
        if ($code === '') {
            throw new PagoProveedorException('El motivo no es válido.');
        }
        if ($known->has($code)) {
            return $code;
        }
        $saved = $this->configuration->value(PagoProveedorSolicitudProveedor::SETTING_CHINA_REASONS, []);
        $saved = is_array($saved) ? $saved : [];
        $saved[] = ['code' => $code, 'label' => $label];
        $this->configuration->save(PagoProveedorSolicitudProveedor::SETTING_CHINA_REASONS, array_values($saved));

        return $code;
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function defaultReasons(): array
    {
        return [
            ['code' => PagoProveedorSolicitudProveedor::CHINA_REASON_WECHAT, 'label' => 'WeChat ID incorrecto'],
            ['code' => PagoProveedorSolicitudProveedor::CHINA_REASON_NO_REPLY, 'label' => 'Proveedor no responde'],
            ['code' => PagoProveedorSolicitudProveedor::CHINA_REASON_MISMATCH, 'label' => 'Datos no coinciden'],
        ];
    }
}
