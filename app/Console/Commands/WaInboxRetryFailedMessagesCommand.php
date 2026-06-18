<?php

namespace App\Console\Commands;

use App\Jobs\WhatsappInbox\SendWaInboxOutboundJob;
use App\Models\WhatsappInbox\WaInboxMessage;
use App\Services\WhatsappInbox\WhatsappInboxSendService;
use App\Support\WhatsApp\WaInboxJobContext;
use App\Traits\DatabaseConnectionTrait;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class WaInboxRetryFailedMessagesCommand extends Command
{
    use DatabaseConnectionTrait;

    protected $signature = 'wa-inbox:retry-failed-messages
                            {--reason=131042 : Filtrar failed_reason (código Meta o texto parcial)}
                            {--ids= : IDs de wa_inbox_messages separados por coma}
                            {--since= : Fecha mínima sent_at (Y-m-d)}
                            {--domain= : Dominio BD/cola (ej. intranetback.probusiness.pe)}
                            {--limit=100 : Máximo de mensajes a reencolar}
                            {--delay=2 : Segundos entre jobs encolados}
                            {--sync : Enviar en este proceso (sin cola)}
                            {--dry-run : Solo listar, no reencolar}
                            {--force : Sin confirmación interactiva}';

    protected $description = 'Reencola mensajes outbound del inbox que fallaron (p. ej. error #131042 de pago Meta)';

    /** @var WhatsappInboxSendService */
    protected $sendService;

    public function __construct(WhatsappInboxSendService $sendService)
    {
        parent::__construct();
        $this->sendService = $sendService;
    }

    public function handle(): int
    {
        $domain = $this->resolveDomain();
        $connection = $this->setDatabaseConnection($domain);

        $this->info('Conexión BD: ' . $connection . ' (dominio: ' . $domain . ')');

        $messages = $this->buildQuery()->get();

        if ($messages->isEmpty()) {
            $this->warn('No hay mensajes outbound fallidos que coincidan con el filtro.');

            return 0;
        }

        $rows = [];
        foreach ($messages as $message) {
            $rows[] = [
                $message->id,
                $message->conversation_id,
                mb_substr((string) $message->body, 0, 40),
                mb_substr((string) $message->failed_reason, 0, 60),
                optional($message->sent_at)->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['ID', 'Conv.', 'Texto', 'failed_reason', 'sent_at'],
            $rows
        );

        $this->line('Total: ' . $messages->count());

        if ($this->option('dry-run')) {
            $this->comment('Dry-run: no se modificó nada.');

            return 0;
        }

        if (!$this->option('force') && !$this->confirm('¿Reencolar estos mensajes?', true)) {
            $this->comment('Cancelado.');

            return 0;
        }

        if ($this->option('sync')) {
            return $this->retrySynchronously($messages, $domain);
        }

        return $this->retryViaQueue($messages, $domain);
    }

    /**
     * @return Builder<WaInboxMessage>
     */
    private function buildQuery(): Builder
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $reason = trim((string) $this->option('reason'));

        $query = WaInboxMessage::query()
            ->where('direction', 'out')
            ->where('delivery_status', 'failed')
            ->orderBy('id');

        $ids = $this->parseIdsOption();
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($reason !== '') {
            $query->where(function (Builder $q) use ($reason) {
                $q->where('failed_reason', 'like', '%' . $reason . '%');

                if ($reason === '131042') {
                    $q->orWhere('failed_reason', 'like', '%unsettled payments%')
                        ->orWhere('failed_reason', 'like', '%Business eligibility payment%');
                }
            });
        }

        $since = trim((string) $this->option('since'));
        if ($since !== '') {
            $query->whereDate('sent_at', '>=', $since);
        }

        return $query->limit($limit);
    }

    /**
     * @return array<int, int>
     */
    private function parseIdsOption(): array
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

    private function resolveDomain(): string
    {
        $domain = trim((string) $this->option('domain'));
        if ($domain !== '') {
            return WaInboxJobContext::resolveJobDomain($domain);
        }

        return WaInboxJobContext::resolveJobDomain();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WaInboxMessage>  $messages
     */
    private function retryViaQueue($messages, string $domain): int
    {
        $delayStep = max(0, min(30, (int) $this->option('delay')));
        $queued = 0;

        foreach ($messages as $index => $message) {
            $this->resetMessageForRetry($message);

            $job = SendWaInboxOutboundJob::dispatch(
                (int) $message->id,
                $domain,
                'Reintento #' . $message->id
            );

            if ($index > 0 && $delayStep > 0) {
                $job->delay(now()->addSeconds($index * $delayStep));
            }

            $queued++;
        }

        $this->info("Encolados {$queued} job(s) SendWaInboxOutboundJob.");

        return 0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, WaInboxMessage>  $messages
     */
    private function retrySynchronously($messages, string $domain): int
    {
        $delayStep = max(0, min(30, (int) $this->option('delay')));
        $ok = 0;
        $fail = 0;

        foreach ($messages as $index => $message) {
            if ($index > 0 && $delayStep > 0) {
                sleep($delayStep);
            }

            $this->resetMessageForRetry($message);
            $result = $this->sendService->sendOutboundMessage((int) $message->id);

            if (!empty($result['success'])) {
                $ok++;
                $this->line("OK  #{$message->id}");
            } else {
                $fail++;
                $error = isset($result['error']) ? (string) $result['error'] : 'Error desconocido';
                $this->error("FAIL #{$message->id}: {$error}");
            }
        }

        $this->info("Sync terminado: {$ok} enviados, {$fail} fallidos.");

        return $fail > 0 ? 1 : 0;
    }

    private function resetMessageForRetry(WaInboxMessage $message): void
    {
        $message->delivery_status = 'pending';
        $message->failed_reason = null;
        $message->meta_message_id = null;
        $message->save();
    }
}
