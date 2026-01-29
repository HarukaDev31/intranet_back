<?php

namespace App\Jobs;

use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendViaticoWhatsappNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    public $message;
    public $userId;
    public $paymentReceiptPath;
    public $queue = 'notificaciones';

    /**
     * Create a new job instance.
     */
    public function __construct(string $message, int $userId, ?string $paymentReceiptPath = null)
    {
        $this->message = $message;
        $this->userId = $userId;
        $this->paymentReceiptPath = $paymentReceiptPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->sendMessage($this->message, $this->userId, 0, 'administracion');

            if ($this->paymentReceiptPath) {
                $fullPath = Storage::disk('public')->path($this->paymentReceiptPath);
                if (file_exists($fullPath) && is_readable($fullPath)) {
                    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
                    $this->sendMedia($fullPath, $mime, $this->message, $this->userId, 0, 'administracion');
                } else {
                    Log::warning('SendViaticoWhatsappNotificationJob: archivo no encontrado o no legible', [
                        'path' => $this->paymentReceiptPath,
                        'fullPath' => $fullPath
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('SendViaticoWhatsappNotificationJob: ' . $e->getMessage(), [
                'userId' => $this->userId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
