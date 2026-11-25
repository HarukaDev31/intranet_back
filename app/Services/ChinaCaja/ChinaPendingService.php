<?php

namespace App\Services\ChinaCaja;

use App\Services\ChinaBilling\ChinaBillingCalculator;
use App\Services\ChinaBilling\ChinaBillingReader;
use App\Services\ChinaBilling\ChinaDraftService;
use App\Services\PagoProveedor\PagoProveedorYuanService;
use Illuminate\Support\Facades\DB;

/**
 * Bandeja de pendientes del Inicio del Encargado China (flujos 13 a 19).
 */
class ChinaPendingService
{
    public function __construct(
        private readonly ChinaContainerService $containers,
        private readonly ChinaCashService $cash,
        private readonly ChinaBillingReader $billing,
        private readonly ChinaDraftService $drafts,
        private readonly PagoProveedorYuanService $yuan,
    ) {
    }

    /**
     * @param  array<int, int>  $organizationIds
     * @return list<array{section: string, kind: string, urgent: bool, tag: string, title: string, detail: string, to: string, count: int}>
     */
    public function list(array $organizationIds): array
    {
        $today = ChinaCashRules::today();
        $rows = $this->containers->rows($organizationIds);
        $names = $this->billing->organizationNames($organizationIds);
        $out = [];
        $add = function (string $section, string $kind, bool $urgent, string $tag, string $title, string $detail, string $to, int $count = 1) use (&$out) {
            $out[] = compact('section', 'kind', 'urgent', 'tag', 'title', 'detail', 'to', 'count');
        };

        $nc = DB::table('contenedor_consolidado_cotizacion_proveedores as p')
            ->join('contenedor_consolidado_cotizacion as c', 'c.id', '=', 'p.id_cotizacion')
            ->whereIn('p.id_contenedor', $rows->where('closed', false)->pluck('id')->all())
            ->whereNull('c.deleted_at')->where('c.estado_cotizador', 'CONFIRMADO')->where('p.estados_proveedor', 'NC')->pluck('c.nombre');
        if ($nc->isNotEmpty()) {
            $add('Seguimiento', 'en NC', false, $nc->count().' en NC', $nc->count().($nc->count() === 1 ? ' proveedor en estado NC' : ' proveedores en estado NC'),
                $nc->unique()->take(3)->implode(' · ').($nc->unique()->count() > 3 ? ' …' : ''), '/seguimiento-china?estado=NC', $nc->count());
        }

        foreach ($rows as $row) {
            $label = $row['label'].' · '.$row['country'];
            foreach ($row['progress']['stages'] as $stage) {
                if (!$stage['locked'] && $stage['done'] < $stage['total']) {
                    $add('Carga consolidada', 'Etapa '.$stage['number'], false, 'Etapa '.$stage['number'], $stage['title'].' pendiente',
                        $label.' · '.$stage['done'].' de '.$stage['total'].match ($stage['number']) {
                            1 => ' datos',
                            2 => ' · llenado '.($row['data']['filling_on'] ? date('d/m/Y', strtotime($row['data']['filling_on'])) : '—'),
                            default => '',
                        },
                        '/cargaconsolidada/'.($row['closed'] ? 'completados' : 'abiertos').'?etapas='.$row['id'].'&etapa='.$stage['number']);
                }
            }
        }

        foreach ($this->cash->weeksToClose($today) as $week) {
            $overdue = $week['days'] > 0;
            $add('Flujo de caja', 'Semana sin cerrar', $overdue, $overdue ? 'Vencido '.$week['days'].'d' : 'Hoy',
                'Cerrar semana '.date('d', strtotime($week['starts_on'])).'–'.date('d/m', strtotime($week['ends_on'])),
                'Tocaba cerrar el '.(date('N', strtotime($week['due_on'])) === '1' ? 'lunes ' : '').date('d/m', strtotime($week['due_on'])).' · pantallazo + saldo', '/flujo-caja?mes='.$week['month'].'&semana='.$week['starts_on']);
        }
        foreach ($this->cash->late($organizationIds, $today) as $late) {
            $to = match ($late['link']) {
                'operation' => '/operaciones-china?operacion='.$late['link_id'],
                'container' => '/estado-contenedores-china?contenedor='.$late['link_id'],
                default => '/flujo-caja',
            };
            $add('Flujo de caja', 'Atrasados', true, abs($late['days']).'d atrasado', ($late['type'] === 'out' ? 'Por pagar · ' : '').$late['title'],
                trim($late['detail'].' · '.($late['type'] === 'in' ? '+' : '−').'¥'.number_format($late['amount'], 0).' · venció '.date('d/m', strtotime($late['due_on'])), ' ·'), $to);
        }

        foreach ($this->drafts->list($organizationIds) as $draft) {
            $add('Operaciones', 'Por aprobar', false, 'Por aprobar', 'Aprobar operación · '.($names[$draft['organization_id']]['name'] ?? ''),
                $draft['description'].' · '.$draft['cbm'].' CBM', '/operaciones-china?borrador='.$draft['container_id']);
        }
        foreach ($this->billing->payments($organizationIds, null, null) as $payment) {
            $status = ChinaBillingCalculator::paymentStatus($payment);
            $client = $names[(int) $payment->organization_id]['name'] ?? '';
            if ($status === ChinaBillingCalculator::STATUS_TO_CONFIRM) {
                $add('Operaciones', 'Por confirmar', false, 'Por confirmar', 'Confirmar ingreso '.$payment->code.' · '.$client,
                    $payment->allocations->map(fn ($a) => $a->operation->code ?? '')->filter()->implode(', ').' · $'.number_format((float) $payment->amount_sent, 0), '/operaciones-china?cobro='.$payment->id);
            } elseif ($status === ChinaBillingCalculator::STATUS_CONFIRMED) {
                $add('Operaciones', 'Cambiar a RMB', false, 'Cambiar a RMB', 'Cambio a RMB '.$payment->code.' · '.$client,
                    'Confirmado $'.number_format((float) $payment->amount_received, 0).' · falta el cambio', '/operaciones-china?cobro='.$payment->id);
            }
        }

        foreach ($this->yuan->list(['bucket' => 'open', 'per_page' => 100000])->items() as $group) {
            $statuses = $group['providers']->map(fn ($provider) => $this->yuan->statusOf($provider));
            $pending = $statuses->filter(fn (string $status) => $status === 'pendiente')->count();
            $observed = $statuses->filter(fn (string $status) => $status === 'observado')->count();
            if ($pending + $observed === 0) {
                continue;
            }
            $add('Pago proveedores', $observed ? 'Observado' : 'Pendiente', $observed > 0, $observed ? 'Observado' : 'Pendiente',
                $group['providers']->pluck('request_code')->filter()->unique()->implode(' / ').' · '.($group['request']->client_name ?: $group['request']->partner),
                implode(' · ', array_filter([$pending ? $pending.($pending === 1 ? ' pendiente' : ' pendientes') : '', $observed ? $observed.($observed === 1 ? ' observado' : ' observados') : ''])),
                '/pago-proveedor/pagos-yuanes?solicitud='.$group['request']->id);
        }

        foreach ($this->containers->shipping($organizationIds) as $row) {
            $status = $row['status'];
            $days = $status['days_to_arrival'];
            $arrived = $days !== null && $days < 0;
            $when = match (true) {
                $arrived => 'ya arribó',
                $days === 0 => 'arriba hoy',
                default => 'arriba en '.$days.($days === 1 ? ' día' : ' días'),
            };
            $label = $row['label'].' · '.$row['country'];
            $to = '/estado-contenedores-china?contenedor='.$row['id'];
            if ($row['data']['bl_official_path'] === null && $status['bl_due']) {
                $add('Contenedores', 'BL oficial', false, 'BL oficial', 'Subir BL oficial', $label.' · 3 semanas tras zarpe', $to);
            }
            if (!$row['data']['telex'] && $status['arrival_due']) {
                $add('Contenedores', 'Telex', $arrived, 'Telex', 'Confirmar telex', $label.' · '.$when, $to);
            }
            if (!$row['freight']['paid'] && (float) $row['data']['freight_usd'] > 0 && $status['arrival_due']) {
                $add('Contenedores', 'Flete', $arrived, 'Flete', 'Pagar flete · $'.number_format((float) $row['data']['freight_usd'], 2), $label.' · '.$when, $to);
            }
        }

        usort($out, fn (array $a, array $b) => $b['urgent'] <=> $a['urgent']);

        return $out;
    }
}
