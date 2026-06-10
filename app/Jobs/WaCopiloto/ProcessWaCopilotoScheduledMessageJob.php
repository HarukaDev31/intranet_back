<?php

namespace App\Jobs\WaCopiloto;

use App\Jobs\WaCopiloto\SendWaCopilotoOutboundJob;
use App\Models\WaCopiloto\WaCopilotoScheduledMessage;
use App\Services\WaCopiloto\WaCopilotoMessageService;
use App\Services\WaCopiloto\WaCopilotoWindowService;
use App\Support\WhatsApp\WaCopilotoJobContext;
use App\Support\WhatsApp\WaCopilotoLog;
use App\Traits\DatabaseConnectionTrait;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWaCopilotoScheduledMessageJob implements ShouldQueue
{
    use DatabaseConnectionTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $scheduledMessageId;

    /** @var string|null */
    public $domain;

    public function __construct($scheduledMessageId, ?string $domain = null)
    {
        $this->scheduledMessageId = (int) $scheduledMessageId;
        $this->domain = $domain;
        $this->onQueue((string) config('meta_whatsapp_copiloto.queue', 'notificaciones'));
    }

    public function displayName(): string
    {
        return 'Copiloto · mensaje programado #' . $this->scheduledMessageId;
    }

    public function handle(WaCopilotoMessageService $messageService, WaCopilotoWindowService $windowService)
    {
        $this->setDatabaseConnection(
            WaCopilotoJobContext::resolveJobDomain($this->domain)
        );

        $scheduled = WaCopilotoScheduledMessage::query()->find($this->scheduledMessageId);
        if (!$scheduled || $scheduled->status !== 'pending') {
            return;
        }

        $conversation = $scheduled->conversation;
        if (!$conversation) {
            $scheduled->status = 'failed';
            $scheduled->failed_reason = 'Conversación no encontrada';
            $scheduled->save();
            return;
        }

        $window = $windowService->computeWindowState($conversation);
        $now = Carbon::now();
        $expiresAt = !empty($window['expires_at']) ? Carbon::parse($window['expires_at']) : null;

        if (empty($window['can_send_text']) || ($expiresAt && $now->greaterThanOrEqualTo($expiresAt))) {
            $scheduled->status = 'failed';
            $scheduled->failed_reason = 'La ventana de 24 h cerró. Envía una plantilla para reactivar.';
            $scheduled->save();
            WaCopilotoLog::warning('job.ProcessWaCopilotoScheduled.window_closed', [
                'scheduled_message_id' => $this->scheduledMessageId,
                'conversation_id' => (int) $conversation->id,
            ]);
            return;
        }

        if ($expiresAt && $scheduled->scheduled_at && $scheduled->scheduled_at->greaterThanOrEqualTo($expiresAt)) {
            $scheduled->status = 'failed';
            $scheduled->failed_reason = 'El horario programado quedó fuera de la ventana. Usa una plantilla.';
            $scheduled->save();
            return;
        }

        try {
            $message = $messageService->createOutboundPending(
                $conversation,
                (string) $scheduled->body,
                (int) $scheduled->created_by_user_id,
                (string) $scheduled->message_type,
                null,
                is_array($scheduled->template_params) ? $scheduled->template_params : null
            );

            SendWaCopilotoOutboundJob::dispatch(
                (int) $message->id,
                WaCopilotoJobContext::resolveJobDomain($this->domain)
            );

            $scheduled->status = 'sent';
            $scheduled->message_id = (int) $message->id;
            $scheduled->save();

            WaCopilotoLog::info('job.ProcessWaCopilotoScheduled.sent', [
                'scheduled_message_id' => $this->scheduledMessageId,
                'message_id' => (int) $message->id,
                'conversation_id' => (int) $conversation->id,
            ]);
        } catch (\Exception $e) {
            $scheduled->status = 'failed';
            $scheduled->failed_reason = mb_substr($e->getMessage(), 0, 480);
            $scheduled->save();

            WaCopilotoLog::error('job.ProcessWaCopilotoScheduled.exception', [
                'scheduled_message_id' => $this->scheduledMessageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
