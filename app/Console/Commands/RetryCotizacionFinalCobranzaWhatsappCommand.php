<?php

namespace App\Console\Commands;

use App\Services\CargaConsolidada\CotizacionFinal\CotizacionFinalCobranzaWhatsappService;
use App\Traits\DatabaseConnectionTrait;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RetryCotizacionFinalCobranzaWhatsappCommand extends Command
{
    use DatabaseConnectionTrait;

    protected $signature = 'whatsapp:retry-cotizacion-final-cobranza
                            {--date= : Fecha actividad inbox Y-m-d (default: ayer America/Lima)}
                            {--from= : Fecha inicio Y-m-d}
                            {--to= : Fecha fin Y-m-d}
                            {--ids= : IDs de cotización separados por coma}
                            {--id-contenedor= : Filtrar por contenedor}
                            {--all-missing : Ignora fecha; todos los COBRANDO sin plantilla OK}
                            {--limit=200 : Máximo a listar/enviar}
                            {--delay=3 : Segundos entre envíos}
                            {--dry-run : Solo listar}
                            {--force : Sin confirmación}
                            {--include-already-sent : Reenviar aunque ya exista envío OK}';

    protected $description = 'Reenvía pb_consolidado_cotizacion_final_v1 (con PDF) a COBRANDO sin envío OK en inbox';

    /** @var CotizacionFinalCobranzaWhatsappService */
    private $service;

    public function __construct(CotizacionFinalCobranzaWhatsappService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle(): int
    {
        $this->setDatabaseConnection();

        $ids = $this->parseIds();
        $includeAlreadySent = (bool) $this->option('include-already-sent');
        $idContenedor = (int) $this->option('id-contenedor');
        $idContenedor = $idContenedor > 0 ? $idContenedor : null;
        $allMissing = (bool) $this->option('all-missing');

        $from = null;
        $to = null;

        if ($ids !== []) {
            $this->info('Usando IDs explícitos: ' . implode(', ', $ids));
            $candidates = [];
            foreach ($ids as $id) {
                $candidates[] = (object) [
                    'id' => $id,
                    'nombre' => null,
                    'telefono' => null,
                    'needs_resend' => true,
                    'skip_reason' => null,
                ];
            }
        } else {
            if (!$allMissing) {
                [$from, $to] = $this->resolveDateRange();
                $this->info(sprintf(
                    'Buscando COBRANDO sin plantilla OK (actividad inbox %s → %s)…',
                    $from->toDateString(),
                    $to->toDateString()
                ));
                $this->comment('Nota: Cotizacion no usa updated_at; el filtro de fecha usa actividad del inbox.');
            } else {
                $this->info('Buscando TODOS los COBRANDO sin plantilla OK…');
            }

            if ($idContenedor !== null) {
                $this->line('Filtro id_contenedor=' . $idContenedor);
            }

            $candidates = $this->service->findMissingOrFailedSends(
                $allMissing ? null : $from,
                $allMissing ? null : $to,
                max(1, (int) $this->option('limit')),
                $idContenedor,
                false
            );

            $diag = $this->service->diagnostics($from, $to, $idContenedor);
            $this->line(sprintf(
                'Diagnóstico: COBRANDO=%d | sin plantilla OK=%d | con actividad en rango=%d',
                $diag['cobrando_total'],
                $diag['sin_plantilla_ok'],
                $diag['con_actividad']
            ));
        }

        $toSend = [];
        $skipped = [];
        foreach ($candidates as $row) {
            $forceSend = $ids !== [] || $includeAlreadySent;
            if ($forceSend || !empty($row->needs_resend)) {
                if (!empty($row->skip_reason) && $row->skip_reason === 'telefono_invalido' && $ids === []) {
                    $skipped[] = $row;
                    continue;
                }
                $toSend[] = $row;
            } else {
                $skipped[] = $row;
            }
        }

        $this->table(
            ['ID', 'Nombre', 'Teléfono', 'Contenedor', 'Acción', 'Motivo skip'],
            array_map(static function ($row) use ($toSend) {
                $willSend = false;
                foreach ($toSend as $s) {
                    if ((int) $s->id === (int) $row->id) {
                        $willSend = true;
                        break;
                    }
                }

                return [
                    $row->id ?? '',
                    mb_substr((string) ($row->nombre ?? ''), 0, 24),
                    (string) ($row->telefono ?? $row->phone_digits ?? ''),
                    $row->id_contenedor ?? '',
                    $willSend ? 'REENVIAR' : 'OK/skip',
                    (string) ($row->skip_reason ?? ''),
                ];
            }, $candidates)
        );

        $this->line('A reenviar: ' . count($toSend) . ' | Omitidos: ' . count($skipped));

        if ($toSend === []) {
            $this->warn('No hay nada que reenviar.');
            $this->comment('Prueba: --all-missing  o  --id-contenedor=163  o  --ids=1,2,3');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se envió nada.');

            return 0;
        }

        if (!$this->option('force') && !$this->confirm('¿Reenviar ' . count($toSend) . ' cotización(es)?', true)) {
            $this->comment('Cancelado.');

            return 0;
        }

        $delay = max(0, min(60, (int) $this->option('delay')));
        $ok = 0;
        $fail = 0;

        foreach (array_values($toSend) as $index => $row) {
            if ($index > 0 && $delay > 0) {
                sleep($delay);
            }

            $id = (int) $row->id;
            $this->line("Enviando cotización #{$id}…");

            try {
                $result = $this->service->sendForCotizacion($id);
            } catch (\Throwable $e) {
                $fail++;
                $this->error("FAIL #{$id}: " . $e->getMessage());
                continue;
            }

            if (!empty($result['status'])) {
                $ok++;
                $this->info("OK  #{$id}" . (!empty($result['queued']) ? ' (encolado)' : ''));
            } else {
                $fail++;
                $this->error('FAIL #' . $id . ': ' . ($result['error'] ?? 'error'));
            }
        }

        $this->info("Terminado: {$ok} ok, {$fail} fallidos.");

        return $fail > 0 ? 1 : 0;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(): array
    {
        $tz = 'America/Lima';
        $fromOpt = trim((string) $this->option('from'));
        $toOpt = trim((string) $this->option('to'));
        $dateOpt = trim((string) $this->option('date'));

        if ($fromOpt !== '' || $toOpt !== '') {
            $from = Carbon::parse($fromOpt !== '' ? $fromOpt : ($toOpt ?: 'yesterday'), $tz)->startOfDay();
            $to = Carbon::parse($toOpt !== '' ? $toOpt : ($fromOpt ?: 'yesterday'), $tz)->endOfDay();

            return [$from, $to];
        }

        $day = $dateOpt !== '' ? $dateOpt : 'yesterday';
        $from = Carbon::parse($day, $tz)->startOfDay();
        $to = $from->copy()->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array<int, int>
     */
    private function parseIds(): array
    {
        $raw = trim((string) $this->option('ids'));
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
