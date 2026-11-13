<?php

namespace App\Services\PagoProveedor;

use App\Exceptions\PagoProveedorException;
use App\Models\Organizacion;
use App\Models\Pais;
use App\Models\PagoProveedor\PagoProveedorCotizacion;
use App\Models\PagoProveedor\PagoProveedorCotizacionProveedor;
use App\Models\PagoProveedor\PagoProveedorDatoProveedor;
use App\Models\PagoProveedor\PagoProveedorEjecucion;
use App\Models\PagoProveedor\PagoProveedorHistorial;
use App\Models\PagoProveedor\PagoProveedorIngreso;
use App\Models\PagoProveedor\PagoProveedorReembolso;
use App\Models\PagoProveedor\PagoProveedorSolicitud;
use App\Models\PagoProveedor\PagoProveedorSolicitudProveedor;
use App\Traits\UsesObjectStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PagoProveedorYuanService
{
    use UsesObjectStorage;

    /** La organización 1 es Pro Business: sus pagos nacen en Ventas, no aquí. */
    private const OWN_ORGANIZATION = 1;

    /** Una petición de la lista pide las filas varias veces: lista, indicadores, conteos y sugerencias. */
    private ?Collection $rowsMemo = null;

    private ?Collection $groupsMemo = null;

    public function __construct(
        private readonly PagoProveedorConfiguracionService $configuration,
        private readonly PagoProveedorHistorialService $history,
    ) {
    }

    /**
     * Solicitudes con los proveedores que China puede ver.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{request: PagoProveedorSolicitud, providers: Collection<int, PagoProveedorSolicitudProveedor>, number: int}>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $groups = $this->groups()->filter(fn (array $group) => $this->matches($group, $filters))->values();
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));
        $total = $groups->count();
        $offset = ($page - 1) * $perPage;
        $items = $groups->forPage($page, $perPage)->values()->map(function (array $group, int $index) use ($total, $offset) {
            $group['number'] = $total - $offset - $index;

            return $group;
        });

        return new Paginator($items, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    public function pendingCount(): int
    {
        return $this->rows()->filter(fn (PagoProveedorSolicitudProveedor $provider) => $this->isPending($provider))->count();
    }

    /**
     * Los indicadores no dependen de los filtros de la lista (RN-94).
     *
     * @return array{usd_received: float, yuan_obtained: float, yuan_paid: float, to_collect: float, to_collect_yuan: float, to_collect_count: int}
     */
    public function indicators(): array
    {
        $totals = ['usd_received' => 0.0, 'yuan_obtained' => 0.0, 'yuan_paid' => 0.0, 'to_collect' => 0.0, 'to_collect_yuan' => 0.0, 'to_collect_count' => 0];
        foreach ($this->groups() as $group) {
            $summary = $this->summary($group);
            $totals['usd_received'] += $summary['usd_received'];
            $totals['yuan_obtained'] += $summary['yuan_obtained'];
            $totals['yuan_paid'] += $summary['yuan_paid'];
            if ($summary['difference_status'] === 'collect') {
                $totals['to_collect'] += $summary['difference_usd'];
                $totals['to_collect_yuan'] += abs($summary['difference']);
                $totals['to_collect_count']++;
            }
        }

        return [
            'usd_received' => round($totals['usd_received'], 2),
            'yuan_obtained' => round($totals['yuan_obtained'], 2),
            'yuan_paid' => round($totals['yuan_paid'], 2),
            'to_collect' => round($totals['to_collect'], 2),
            'to_collect_yuan' => round($totals['to_collect_yuan'], 2),
            'to_collect_count' => $totals['to_collect_count'],
        ];
    }

    /**
     * Conteos por solicitud (fila de la lista China).
     * Completados: todos los proveedores pagados. Abiertos: falta al menos uno.
     *
     * @return array{open: int, completed: int, all: int, pending: int, observed: int, paid: int}
     */
    public function counts(): array
    {
        $statuses = $this->groups()->map(fn (array $group) => $this->summary($group)['status']);
        $completed = $statuses->filter(fn (string $status) => $status === 'pagado')->count();
        $open = $statuses->count() - $completed;
        $observed = $statuses->filter(fn (string $status) => $status === 'observado')->count();

        return [
            'open' => $open,
            'completed' => $completed,
            'all' => $statuses->count(),
            'pending' => $open,
            'observed' => $observed,
            'paid' => $completed,
        ];
    }

    /**
     * Socios a los que China puede crearles pagos: toda organización activa menos Pro Business.
     *
     * @return array<int, array{id: int, name: string, country: string}>
     */
    public function organizations(): array
    {
        return Organizacion::query()
            ->with(['pais', 'paisesHabilitados'])
            ->where('Nu_Estado', 1)
            ->where('ID_Organizacion', '!=', self::OWN_ORGANIZATION)
            ->orderBy('No_Organizacion')
            ->get()
            ->map(fn (Organizacion $organization) => $this->organizationItem($organization))
            ->values()
            ->all();
    }

    /**
     * Solo crea la fila de la organización. Darle usuarios y menús es del comando organizacion:crear.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     * @return array{id: int, name: string, country: string}
     */
    public function createOrganization(string $name, int $countryId, array $actor): array
    {
        return DB::transaction(function () use ($name, $countryId, $actor) {
            $name = trim($name);
            $exists = Organizacion::query()->whereRaw('LOWER(No_Organizacion) = ?', [mb_strtolower($name)])->lockForUpdate()->exists();
            if ($exists) {
                throw new PagoProveedorException('Ya existe un socio con ese nombre.');
            }
            $own = Organizacion::query()->find(self::OWN_ORGANIZATION);
            $organization = Organizacion::query()->create([
                'ID_Empresa' => $own?->ID_Empresa,
                'No_Organizacion' => $name,
                'id_pais' => $countryId,
                'Nu_Estado' => 1,
            ]);
            Log::info('Pago a proveedor: socio creado desde China', ['organizacion_id' => $organization->ID_Organizacion, 'nombre' => $name, 'usuario' => $actor['id']]);

            return $this->organizationItem($organization->load(['pais', 'paisesHabilitados']));
        });
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function countryCatalog(): array
    {
        return Pais::query()
            ->where('Nu_Estado', 1)
            ->orderBy('ID_Pais')
            ->get(['ID_Pais', 'No_Pais'])
            ->map(fn (Pais $country) => ['id' => (int) $country->ID_Pais, 'name' => $this->countryName((string) $country->No_Pais)])
            ->unique('name')
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Países que hoy aparecen en la lista, para filtrarla.
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        return $this->groups()
            ->map(fn (array $group) => $this->countryOf($group['request']))
            ->filter(fn (string $country) => $country !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Clientes y clientes del socio ya usados por cada organización, para sugerirlos al escribir.
     *
     * @return array{partners: array<int, array<int, string>>, clients: array<int, array<int, string>>}
     */
    public function suggestions(): array
    {
        $partners = [];
        $clients = [];
        foreach ($this->groups() as $group) {
            $organizationId = $group['request']->organizacion_id;
            if (!$organizationId) {
                continue;
            }
            $partners[$organizationId][(string) $group['request']->partner] = (string) $group['request']->partner;
            foreach ($group['providers'] as $provider) {
                $name = trim((string) $provider->client_name);
                if ($name !== '') {
                    $clients[$organizationId][$name] = $name;
                }
            }
        }

        return [
            'partners' => array_map(fn (array $names) => array_values($names), $partners),
            'clients' => array_map(fn (array $names) => array_values($names), $clients),
        ];
    }

    /**
     * Balance de la solicitud: yuanes obtenidos menos pagados y por pagar.
     *
     * @param  array{request: PagoProveedorSolicitud, providers: Collection<int, PagoProveedorSolicitudProveedor>}  $group
     * @return array{exchange_rate: float, usd_total: float, yuan_total: float, usd_received: float, yuan_obtained: float, income_rates: array<int, float>, yuan_paid: float, yuan_pending: float, difference: float, difference_usd: float, difference_status: string|null, last_rate: float, status: string, status_label: string, paid_count: int, provider_count: int}
     */
    public function summary(array $group): array
    {
        $request = $group['request'];
        $providers = $group['providers'];
        $rate = (float) ($request->exchange_rate ?? 0);
        $incomes = $request->ingresos;
        $paid = 0.0;
        $pending = 0.0;
        $usdTotal = 0.0;
        $yuanTotal = 0.0;
        foreach ($providers as $provider) {
            $paid += (float) $provider->ejecuciones->sum('yuan_paid');
            $pending += $this->pendingYuan($provider);
            $usdTotal += (float) $provider->cargo_value_usd;
            $yuanTotal += $this->totalYuan($provider);
        }
        $obtained = round((float) $incomes->sum('yuan_amount'), 2);
        $difference = round($obtained - $paid - $pending, 2);
        $lastRate = (float) ($incomes->last()?->exchange_rate ?: $rate);
        $statuses = $providers->map(fn (PagoProveedorSolicitudProveedor $provider) => $this->statusOf($provider));
        $paidCount = $statuses->filter(fn (string $status) => $status === 'pagado')->count();
        $status = $statuses->contains('observado') ? 'observado' : ($paidCount === $providers->count() ? 'pagado' : 'pendiente');

        return [
            'exchange_rate' => round($rate, 4),
            'usd_total' => round($usdTotal, 2),
            'yuan_total' => round($yuanTotal, 2),
            'usd_received' => round((float) $incomes->sum('usd_amount'), 2),
            'yuan_obtained' => $obtained,
            'income_rates' => $incomes->pluck('exchange_rate')->map(fn ($value) => (float) $value)->unique()->values()->all(),
            'yuan_paid' => round($paid, 2),
            'yuan_pending' => round($pending, 2),
            'difference' => $difference,
            'difference_usd' => $lastRate > 0 ? round(abs($difference) / $lastRate, 2) : 0.0,
            'difference_status' => $this->differenceStatus($difference, $status),
            'last_rate' => round($lastRate, 4),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'paid_count' => $paidCount,
            'provider_count' => $providers->count(),
        ];
    }

    public function statusOf(PagoProveedorSolicitudProveedor $provider): string
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED && $provider->china_observation_reason !== null) {
            return 'observado';
        }
        if ($this->isPending($provider) || $provider->ejecuciones->isEmpty()) {
            return 'pendiente';
        }

        return 'pagado';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'observado' => 'Observado',
            'pagado' => 'Pagado',
            default => 'Pendiente',
        };
    }

    public function dateOf(PagoProveedorSolicitud $request): string
    {
        if ($request->request_date) {
            return $request->request_date->toDateString();
        }

        return ($request->created_at ?: now())->copy()->timezone(PagoProveedorVigenciaService::ZONA)->toDateString();
    }

    public function countryOf(PagoProveedorSolicitud $request): string
    {
        if ($request->organizacion) {
            return $this->countryOfOrganization($request->organizacion);
        }

        return $request->country ?: $this->countryOfPartner((string) $request->partner);
    }

    /**
     * @param  Collection<int, PagoProveedorSolicitudProveedor>  $providers
     */
    public function canRemoveIncome(PagoProveedorSolicitud $request, Collection $providers): bool
    {
        return $request->origin === PagoProveedorSolicitud::ORIGIN_MANUAL
            || !$providers->contains(fn (PagoProveedorSolicitudProveedor $provider) => $provider->ejecuciones->isNotEmpty());
    }

    /**
     * Se puede deshacer mientras Ventas no haya avanzado con la confirmación.
     */
    public function canUndo(PagoProveedorSolicitudProveedor $provider): bool
    {
        if ($this->statusOf($provider) !== 'pagado' || (int) $provider->stage !== PagoProveedorSolicitudProveedor::STAGE_CONFIRMATION) {
            return false;
        }
        if ($provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL) {
            return true;
        }
        if ($provider->second_installment_choice || $provider->second_payment_choice || $provider->exchangeDeposits->isNotEmpty()) {
            return false;
        }
        $refundAdvanced = $provider->refunds->contains(fn (PagoProveedorReembolso $refund) => $refund->origin !== PagoProveedorReembolso::ORIGIN_SURPLUS
            && $refund->status !== PagoProveedorReembolso::STATUS_CONSULT);

        return !$refundAdvanced && !app(PagoProveedorMensajeService::class)->balanceStored($provider);
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
     * El tipo de cambio real sale de lo que China recibió del socio (RN-92).
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function register(int $providerId, float $yuanPaid, ?string $paidOn, ?UploadedFile $receipt, array $actor): PagoProveedorEjecucion
    {
        return DB::transaction(function () use ($providerId, $yuanPaid, $paidOn, $receipt, $actor) {
            $provider = $this->lock($providerId);
            $this->requireReady($provider);
            if ($yuanPaid <= 0) {
                throw new PagoProveedorException('Los yuanes pagados tienen que ser mayores que cero.');
            }

            $quote = $this->quote($provider);
            $manual = $provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL;
            $realRate = $this->realRate($provider->solicitud);
            if (!$manual) {
                if ($realRate === null) {
                    throw new PagoProveedorException('Primero registra el dinero recibido: el tipo de cambio real sale de ese ingreso.');
                }
                if (!$receipt) {
                    throw new PagoProveedorException('Adjunta la constancia del pago: Ventas la necesita como respaldo.');
                }
                if (!app(PagoProveedorMensajeService::class)->rmbNoticeMatches($provider, $yuanPaid)) {
                    throw new PagoProveedorException('Primero envía el aviso al cliente por WhatsApp. Si cambias los yuanes pagados, vuelve a enviarlo.');
                }
            }
            $realRate = $realRate ?? $quote['quoted_rate'];
            $calculo = PagoProveedorYuanCalculo::calcular($quote['usd'], $realRate, $yuanPaid, $this->tolerance());
            $result = $calculo['result'];

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
                'yuan_obtained' => $calculo['obtained'],
                'yuan_paid' => round($yuanPaid, 2),
                'exchange_difference_usd' => $calculo['difference_usd'],
                'exchange_result' => $result,
                'receipt_path' => $receipt ? $this->storeFile($receipt, 'pago-proveedor/constancias', 'constancia-rmb-'.$provider->id) : null,
                'receipt_name' => $receipt?->getClientOriginalName(),
                'executed_on' => $paidOn ?: now(PagoProveedorVigenciaService::ZONA)->toDateString(),
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
    public function undoPayment(int $providerId, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $actor) {
            $provider = $this->lock($providerId);
            if (!$this->canUndo($provider)) {
                throw new PagoProveedorException('Este pago ya no se puede deshacer: Ventas avanzó con la confirmación.');
            }
            $execution = $provider->ejecuciones->sortByDesc('id')->first();
            $execution?->delete();
            $provider->refunds()
                ->where('origin', PagoProveedorReembolso::ORIGIN_EXCHANGE)
                ->where('status', PagoProveedorReembolso::STATUS_CONSULT)
                ->delete();
            $previous = $provider->ejecuciones()->orderByDesc('id')->first();
            $provider->exchange_result = $provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL ? null : $previous?->exchange_result;
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
            $provider->stage = PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
            $provider->decision_due_at = null;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Pago en yuanes deshecho',
                $actor,
                $provider->code_supplier
            );

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function addIncome(int $requestId, float $usd, float $rate, ?UploadedFile $receipt, array $actor): PagoProveedorIngreso
    {
        return DB::transaction(function () use ($requestId, $usd, $rate, $receipt, $actor) {
            $request = PagoProveedorSolicitud::query()->lockForUpdate()->find($requestId);
            if (!$request) {
                throw new PagoProveedorException('La solicitud no existe.', 404);
            }
            if ($usd <= 0 || $rate <= 0) {
                throw new PagoProveedorException('Los dólares y el tipo de cambio tienen que ser mayores que cero.');
            }
            $income = $request->ingresos()->create([
                'usd_amount' => round($usd, 2),
                'exchange_rate' => round($rate, 4),
                'yuan_amount' => round($usd * $rate, 2),
                'received_on' => now(PagoProveedorVigenciaService::ZONA)->toDateString(),
                'receipt_path' => $receipt ? $this->storeFile($receipt, 'pago-proveedor/ingresos', 'ingreso-'.$request->id) : null,
                'receipt_name' => $receipt?->getClientOriginalName(),
                'user_id' => $actor['id'],
            ]);
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $request->id,
                'Dinero recibido',
                $actor,
                '$'.number_format($income->usd_amount, 2).' × '.$income->exchange_rate
            );

            return $income;
        });
    }

    /**
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function removeIncome(int $incomeId, array $actor): void
    {
        DB::transaction(function () use ($incomeId, $actor) {
            $income = PagoProveedorIngreso::query()->with('solicitud')->lockForUpdate()->find($incomeId);
            if (!$income || !$income->solicitud) {
                throw new PagoProveedorException('El ingreso no existe.', 404);
            }
            $request = $income->solicitud;
            $providers = $request->proveedores()->with('ejecuciones')->get();
            if (!$this->canRemoveIncome($request, $providers->toBase())) {
                throw new PagoProveedorException('Ya hay pagos calculados con este ingreso. Deshaz primero el pago.');
            }
            $income->delete();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                $request->id,
                'Dinero recibido quitado',
                $actor,
                '$'.number_format($income->usd_amount, 2).' × '.$income->exchange_rate
            );
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
            $code = trim($reason) === '' && trim((string) $newLabel) === '' && trim((string) $detail) !== ''
                ? PagoProveedorSolicitudProveedor::CHINA_REASON_FREE_TEXT
                : $this->resolveReason($reason, $newLabel);
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
     * China puede quitar su propia observación. Ventas la levanta corrigiendo los datos.
     *
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function clearObservation(int $providerId, array $actor): PagoProveedorSolicitudProveedor
    {
        return DB::transaction(function () use ($providerId, $actor) {
            $provider = $this->lock($providerId);
            if ($provider->china_observation_reason === null) {
                throw new PagoProveedorException('Este pago no está observado.');
            }
            $this->clearChinaObservation($provider);
            $provider->stage = PagoProveedorSolicitudProveedor::STAGE_EXECUTION;
            $provider->save();
            $this->history->registrar(
                PagoProveedorHistorial::ENTITY_REQUEST,
                (int) $provider->request_id,
                'Observación quitada por China',
                $actor,
                $provider->code_supplier
            );

            return $provider->load(['solicitud', 'datos', 'ejecuciones']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function createManual(array $payload, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($payload, $actor) {
            $fields = $this->manualFields($payload);
            $order = ((int) PagoProveedorSolicitud::query()->max('order_number')) + 1;
            $quote = PagoProveedorCotizacion::query()->create([
                'code' => 'MAN'.$order,
                'consolidated_quote_code' => 'MAN'.$order,
                'status' => PagoProveedorCotizacion::STATUS_CONFIRMED,
                'valid_until' => now()->addDay(),
                'exchange_rate' => $fields['rate'],
                'exchange_rate_confirmed' => true,
                'client_name' => $fields['partner'],
                'user_id' => $actor['id'],
            ]);
            $request = PagoProveedorSolicitud::query()->create([
                'order_number' => $order,
                'quote_id' => $quote->id,
                'consolidated_quote_code' => 'MAN'.$order,
                'exchange_rate' => $fields['rate'],
                'exchange_rate_confirmed' => true,
                'client_name' => $fields['partner'],
                'partner' => $fields['partner'],
                'organizacion_id' => $fields['organization']->ID_Organizacion,
                'country' => $this->countryOfOrganization($fields['organization']),
                'request_date' => $fields['date'],
                'origin' => PagoProveedorSolicitud::ORIGIN_MANUAL,
                'user_id' => $actor['id'],
            ]);
            $code = app(PagoProveedorCodigoSolicitud::class)->deSolicitud($request);
            foreach ($fields['providers'] as $index => $row) {
                $this->saveManualProvider($request, $row, $fields['rate'], $code, $index + 1, null);
            }
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $request->id, 'Solicitud manual', $actor, $fields['partner']);

            return $request;
        });
    }

    /**
     * Al guardar, los proveedores observados vuelven a Pendiente. Lo ya pagado conserva sus montos.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function updateManual(int $requestId, array $payload, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $payload, $actor) {
            $request = PagoProveedorSolicitud::query()->with(['proveedores.ejecuciones', 'proveedores.datos'])->lockForUpdate()->find($requestId);
            if (!$request) {
                throw new PagoProveedorException('La solicitud no existe.', 404);
            }
            if ($request->origin !== PagoProveedorSolicitud::ORIGIN_MANUAL) {
                throw new PagoProveedorException('Solo se editan las solicitudes creadas aquí. Los datos de Perú los corrige Ventas.');
            }
            $fields = $this->manualFields($payload);
            $existing = $request->proveedores->keyBy('id');
            $kept = collect($fields['providers'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            foreach ($existing as $id => $provider) {
                if ($kept->contains((int) $id)) {
                    continue;
                }
                if ($provider->ejecuciones->isNotEmpty()) {
                    throw new PagoProveedorException('Un proveedor ya pagado no se puede quitar. Deshaz primero el pago.');
                }
                $provider->datos()->delete();
                $provider->delete();
            }
            $request->fill([
                'exchange_rate' => $fields['rate'],
                'client_name' => $fields['partner'],
                'partner' => $fields['partner'],
                'organizacion_id' => $fields['organization']->ID_Organizacion,
                'country' => $this->countryOfOrganization($fields['organization']),
                'request_date' => $fields['date'],
            ])->save();
            if ($request->cotizacion) {
                $request->cotizacion->fill(['exchange_rate' => $fields['rate'], 'client_name' => $fields['partner']])->save();
            }
            $code = (string) $request->proveedores->pluck('request_code')->filter()->first();
            $position = (int) $existing->max('position');
            foreach ($fields['providers'] as $row) {
                $current = isset($row['id']) ? $existing->get((int) $row['id']) : null;
                if (isset($row['id']) && !$current) {
                    throw new PagoProveedorException('Uno de los proveedores ya no pertenece a esta solicitud.');
                }
                $this->saveManualProvider($request, $row, $fields['rate'], $code, $current ? (int) $current->position : ++$position, $current);
            }
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $request->id, 'Solicitud manual editada', $actor, $fields['partner']);

            return $request;
        });
    }

    public function isManual(int $requestId): bool
    {
        $request = PagoProveedorSolicitud::query()->find($requestId, ['id', 'origin']);
        if (!$request) {
            throw new PagoProveedorException('La solicitud no existe.', 404);
        }

        return $request->origin === PagoProveedorSolicitud::ORIGIN_MANUAL;
    }

    /**
     * Solicitud que llegó de Ventas: China corrige solo nombre del proveedor, WeChat ID y vendedor.
     * Cliente, montos, modalidad y proforma los corrige Ventas.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{id: int|null, nombre: string, area: string}  $actor
     */
    public function updateSupplierData(int $requestId, array $payload, array $actor): PagoProveedorSolicitud
    {
        return DB::transaction(function () use ($requestId, $payload, $actor) {
            $request = PagoProveedorSolicitud::query()->with('proveedores.datos')->lockForUpdate()->find($requestId);
            if (!$request) {
                throw new PagoProveedorException('La solicitud no existe.', 404);
            }
            $existing = $request->proveedores->keyBy('id');
            /** @var array<int, array<string, mixed>> $rows */
            $rows = array_values($payload['providers']);
            foreach ($rows as $row) {
                $provider = $existing->get((int) $row['id']);
                if (!$provider) {
                    throw new PagoProveedorException('Uno de los proveedores ya no pertenece a esta solicitud.');
                }
                $provider->supplier_name = trim((string) ($row['supplier_name'] ?? '')) ?: null;
                $provider->save();
                $wechat = trim((string) ($row['wechat'] ?? ''));
                $seller = trim((string) ($row['seller_name'] ?? ''));
                $data = $provider->datos;
                if (!$data && $wechat === '' && $seller === '') {
                    continue;
                }
                $data ??= $provider->datos()->make([
                    'status' => PagoProveedorDatoProveedor::STATUS_RECEIVED,
                    'source' => 'china',
                    'token' => Str::random(40),
                ]);
                $data->wechat = $wechat === '' ? null : PagoProveedorDatosReglas::wechat($wechat);
                $data->seller_name = $seller === '' ? null : $seller;
                $data->save();
            }
            $this->history->registrar(PagoProveedorHistorial::ENTITY_REQUEST, $request->id, 'Datos del proveedor editados por China', $actor, (string) $request->client_name);

            return $request;
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
            'quoted_yuan' => isset($summary['rmb']) ? round((float) $summary['rmb'], 2) : round($usd * $rate, 2),
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
        if ($code === PagoProveedorSolicitudProveedor::CHINA_REASON_FREE_TEXT) {
            return 'Observación de China';
        }
        $match = collect($this->reasons())->firstWhere('code', $code);

        return $match['label'] ?? $code;
    }

    /**
     * @return Collection<int, array{request: PagoProveedorSolicitud, providers: Collection<int, PagoProveedorSolicitudProveedor>}>
     */
    private function groups(): Collection
    {
        return $this->groupsMemo ??= $this->rows()
            ->groupBy('request_id')
            ->map(fn (Collection $providers) => [
                'request' => $providers->first()->solicitud,
                'providers' => $providers->sortBy('position')->values(),
            ])
            ->filter(fn (array $group) => $group['request'] !== null)
            ->sort(function (array $left, array $right) {
                return [$this->dateOf($right['request']), $right['request']->id] <=> [$this->dateOf($left['request']), $left['request']->id];
            })
            ->values();
    }

    /**
     * @param  array{request: PagoProveedorSolicitud, providers: Collection<int, PagoProveedorSolicitudProveedor>}  $group
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $group, array $filters): bool
    {
        $request = $group['request'];
        // Abiertos/Completados miran la solicitud entera: solo Completados si TODOS los proveedores están pagados.
        $requestStatus = $this->summary($group)['status'];
        $bucket = (string) ($filters['bucket'] ?? 'open');
        if ($bucket === 'completed' || $bucket === PagoProveedorEjecucion::BUCKET_PAID) {
            if ($requestStatus !== 'pagado') {
                return false;
            }
        } elseif ($bucket === 'open' || $bucket === PagoProveedorEjecucion::BUCKET_PENDING) {
            if ($requestStatus === 'pagado') {
                return false;
            }
        } elseif ($bucket === 'observed') {
            if ($requestStatus !== 'observado') {
                return false;
            }
        } elseif ($bucket !== 'all' && $bucket !== '') {
            return false;
        }
        $country = trim((string) ($filters['country'] ?? ''));
        if ($country !== '' && $this->countryOf($request) !== $country) {
            return false;
        }
        $date = $this->dateOf($request);
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if (($from !== '' && $date < $from) || ($to !== '' && $date > $to)) {
            return false;
        }
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search === '') {
            return true;
        }
        $haystack = [$request->client_name, $request->partner, $request->consolidation, (string) $request->order_number];
        foreach ($group['providers'] as $provider) {
            array_push($haystack, $provider->code_supplier, $provider->supplier_name, $provider->client_name, $provider->product, $provider->request_code, $provider->datos?->wechat);
        }

        return str_contains(mb_strtolower(implode(' ', array_filter($haystack))), $search);
    }

    /**
     * @return Collection<int, PagoProveedorSolicitudProveedor>
     */
    private function rows(): Collection
    {
        return $this->rowsMemo ??= PagoProveedorSolicitudProveedor::query()
            ->with(['solicitud.ingresos', 'solicitud.organizacion.pais', 'solicitud.organizacion.paisesHabilitados', 'datos', 'ejecuciones', 'exchangeDeposits', 'refunds'])
            ->where('currency', PagoProveedorCotizacionProveedor::CURRENCY_RMB)
            ->orderByDesc('id')
            ->get()
            ->filter(function (PagoProveedorSolicitudProveedor $provider) {
                return $this->isPending($provider)
                    || $provider->ejecuciones->isNotEmpty()
                    || ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED
                        && $provider->china_observation_reason !== null
                        && (int) $provider->stage === PagoProveedorSolicitudProveedor::STAGE_EXECUTION);
            })
            ->values();
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
        $manual = $provider->solicitud?->origin === PagoProveedorSolicitud::ORIGIN_MANUAL;
        if (!$manual && trim((string) $provider->proforma_path) === '') {
            return false;
        }
        $data = $provider->datos;
        if (!$data || trim((string) $data->wechat) === '' || (!$manual && trim((string) $data->seller_name) === '')) {
            return false;
        }

        return !$provider->ejecuciones->contains(
            fn (PagoProveedorEjecucion $execution) => (int) $execution->installment === (int) $provider->current_installment
                && (int) $execution->supplier_payment === (int) $provider->current_payment
        );
    }

    private function totalYuan(PagoProveedorSolicitudProveedor $provider): float
    {
        $summary = is_array($provider->summary_json) ? $provider->summary_json : [];
        if (isset($summary['rmb'])) {
            return round((float) $summary['rmb'], 2);
        }

        return round((float) $provider->cargo_value_usd * (float) ($provider->solicitud->exchange_rate ?? 0), 2);
    }

    /**
     * Lo que falta pagar de este proveedor, contando las cuotas o pagos que aún no se ejecutan.
     */
    private function pendingYuan(PagoProveedorSolicitudProveedor $provider): float
    {
        if (in_array($provider->status, [PagoProveedorSolicitudProveedor::STATUS_COMPLETED, PagoProveedorSolicitudProveedor::STATUS_CANCELLED], true)) {
            return 0.0;
        }
        if ($provider->ejecuciones->isEmpty()) {
            return $this->totalYuan($provider);
        }
        $remaining = (float) $provider->cargo_value_usd - (float) $provider->ejecuciones->sum('cargo_amount');
        if ($remaining < 0.005) {
            return 0.0;
        }

        return round($remaining * (float) ($provider->solicitud->exchange_rate ?? 0), 2);
    }

    private function differenceStatus(float $difference, string $status): ?string
    {
        if ($difference < 0) {
            return $status === 'pagado' ? 'collect' : 'missing';
        }

        return $difference > 0 ? 'favor' : null;
    }

    /**
     * El promedio de lo recibido: dos ingresos pueden tener distinto tipo de cambio.
     */
    private function realRate(?PagoProveedorSolicitud $request): ?float
    {
        $incomes = $request?->ingresos()->get();
        $usd = $incomes ? (float) $incomes->sum('usd_amount') : 0.0;
        if ($usd <= 0) {
            return null;
        }

        return round((float) $incomes->sum('yuan_amount') / $usd, 4);
    }

    private function countryOfPartner(string $partner): string
    {
        return match (true) {
            stripos($partner, 'BOG') !== false => PagoProveedorSolicitud::COUNTRY_BOLIVIA,
            stripos($partner, 'ECU') !== false => PagoProveedorSolicitud::COUNTRY_ECUADOR,
            default => PagoProveedorSolicitud::COUNTRY_PERU,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{organization: Organizacion, partner: string, date: string, rate: float, providers: array<int, array<string, mixed>>}
     */
    private function manualFields(array $payload): array
    {
        $organizationId = (int) $payload['organizacion_id'];
        if ($organizationId === self::OWN_ORGANIZATION) {
            throw new PagoProveedorException('Los pagos de Pro Business llegan desde Ventas, ya aprobados. Aquí van los de otros socios.');
        }
        $organization = Organizacion::query()->with(['pais', 'paisesHabilitados'])->where('Nu_Estado', 1)->find($organizationId);
        if (!$organization) {
            throw new PagoProveedorException('El socio no existe o no está activo.');
        }
        $rate = round((float) $payload['exchange_rate'], 4);
        if ($rate <= 0) {
            throw new PagoProveedorException('El tipo de cambio tiene que ser mayor que cero.');
        }

        return [
            'organization' => $organization,
            'partner' => trim((string) $payload['partner']),
            'date' => (string) $payload['request_date'],
            'rate' => $rate,
            'providers' => array_values($payload['providers']),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function saveManualProvider(PagoProveedorSolicitud $request, array $row, float $rate, string $code, int $position, ?PagoProveedorSolicitudProveedor $provider): void
    {
        $client = trim((string) $row['client_name']);
        $name = trim((string) $row['supplier_name']);
        $wechat = preg_replace('/\s+/', '', (string) $row['wechat']) ?? '';
        if ($client === '' || $name === '' || $wechat === '') {
            throw new PagoProveedorException('Cada proveedor necesita cliente, nombre y WeChat ID.');
        }
        $yuan = round((float) $row['yuan_amount'], 2);
        if ($yuan <= 0) {
            throw new PagoProveedorException('El monto en yuanes tiene que ser mayor que cero.');
        }
        $usd = round($yuan / $rate, 2);
        $values = [
            'position' => $position,
            'supplier_name' => $name,
            'client_name' => $client,
            'product' => trim((string) ($row['product'] ?? '')),
        ];
        $proforma = $row['proforma'] ?? null;
        if ($proforma instanceof UploadedFile) {
            $values['proforma_path'] = $this->storeFile($proforma, 'pago-proveedor/proformas', 'manual-'.$request->order_number.'-'.$position);
            $values['proforma_name'] = $proforma->getClientOriginalName();
        }
        if (!$provider || $provider->ejecuciones->isEmpty()) {
            $values += [
                'cargo_value_usd' => $usd,
                'total_amount' => $usd,
                'summary_json' => ['cuotas' => [['carga' => $usd, 'total' => $usd]], 'pagos_proveedor' => [['carga' => $usd]], 'rmb' => $yuan],
            ];
        }
        if ($provider) {
            $provider->fill($values);
            $this->clearChinaObservation($provider);
            $provider->save();
            $data = $provider->datos;
        } else {
            $provider = $request->proveedores()->create($values + [
                'code_supplier' => $this->manualSupplierCode($client),
                'currency' => PagoProveedorCotizacionProveedor::CURRENCY_RMB,
                'payment_type' => 'transferencia',
                'client_payment_mode' => 'total',
                'supplier_payment_mode' => 'total',
                'first_share_percent' => 100,
                'request_code' => $code,
                'stage' => PagoProveedorSolicitudProveedor::STAGE_EXECUTION,
                'status' => PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS,
                'current_installment' => 1,
                'current_payment' => 1,
                'approval_status' => PagoProveedorSolicitudProveedor::APPROVAL_APPROVED,
                'approval_mode' => PagoProveedorSolicitudProveedor::APPROVAL_MANUAL,
            ]);
            $data = null;
        }
        $seller = trim((string) ($row['seller_name'] ?? ''));
        if ($data) {
            $data->fill(['seller_name' => $seller, 'wechat' => $wechat])->save();

            return;
        }
        $provider->datos()->create([
            'seller_name' => $seller,
            'wechat' => $wechat,
            'status' => PagoProveedorDatoProveedor::STATUS_RECEIVED,
            'source' => 'china',
            'token' => Str::random(40),
        ]);
    }

    private function clearChinaObservation(PagoProveedorSolicitudProveedor $provider): void
    {
        if ($provider->status === PagoProveedorSolicitudProveedor::STATUS_OBSERVED) {
            $provider->status = PagoProveedorSolicitudProveedor::STATUS_IN_PROGRESS;
        }
        $provider->china_observation_reason = null;
        $provider->china_observation_detail = null;
        $provider->china_observed_at = null;
        $provider->china_observe_until = null;
    }

    /**
     * Mismo formato del código de Perú: letras del cliente, su número y el orden del proveedor (MAYS19-1).
     */
    private function manualSupplierCode(string $client): string
    {
        $providers = PagoProveedorSolicitudProveedor::query()
            ->whereHas('solicitud', fn ($query) => $query->where('origin', PagoProveedorSolicitud::ORIGIN_MANUAL))
            ->whereNotNull('client_name')
            ->get(['client_name', 'code_supplier']);
        $key = mb_strtolower($client);
        $same = $providers->filter(fn (PagoProveedorSolicitudProveedor $row) => mb_strtolower((string) $row->client_name) === $key);
        $number = 20 + $providers->pluck('client_name')->map(fn ($name) => mb_strtolower((string) $name))->unique()->count();
        $sequence = 1;
        foreach ($same as $row) {
            if (preg_match('/(\d+)-(\d+)$/', (string) $row->code_supplier, $match)) {
                $number = (int) $match[1];
                $sequence = max($sequence, (int) $match[2] + 1);
            }
        }
        $words = preg_split('/\s+/', trim((string) preg_replace('/[^A-Z ]/', '', strtoupper(Str::ascii($client))))) ?: [];
        $letters = substr((string) ($words[0] ?? ''), 0, 2).substr((string) ($words[1] ?? $words[0] ?? ''), 0, 2);

        return str_pad($letters, 4, 'X').$number.'-'.$sequence;
    }

    /**
     * @return array{id: int, name: string, country: string}
     */
    private function organizationItem(Organizacion $organization): array
    {
        return [
            'id' => (int) $organization->ID_Organizacion,
            'name' => (string) $organization->No_Organizacion,
            'country' => $this->countryOfOrganization($organization),
        ];
    }

    private function countryOfOrganization(Organizacion $organization): string
    {
        $name = $organization->pais->No_Pais ?? $organization->paisesHabilitados->first()->No_Pais ?? '';

        return $this->countryName((string) $name);
    }

    private function countryName(string $name): string
    {
        return mb_strtoupper($name) === 'PERU' ? PagoProveedorSolicitud::COUNTRY_PERU : mb_convert_case($name, MB_CASE_TITLE);
    }

    private function storeFile(UploadedFile $file, string $folder, string $name): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');

        return $this->storageFinalizeCdnPath($this->storageStoreUpload($file, $folder, $name.'-'.now()->format('YmdHis').'.'.$extension));
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
