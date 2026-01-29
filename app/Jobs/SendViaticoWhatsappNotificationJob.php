<?php

namespace App\Jobs;

use App\Traits\WhatsappTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendViaticoWhatsappNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WhatsappTrait;

    public $message;
    public $userId;
    public $paymentReceiptPath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $message, int $userId, ?string $paymentReceiptPath = null)
    {
        $this->message = $message;
        $this->userId = $userId;
        $this->paymentReceiptPath = $paymentReceiptPath;
        $this->onQueue('notificaciones');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->sendMessage($this->message, $this->userId, 0, 'administracion');

            if ($this->paymentReceiptPath) {
                // Si viene URL (ej. http://localhost:8000/storage/viaticos/xxx.jpg), extraer path relativo "viaticos/xxx.jpg"
                $pathToUse = $this->paymentReceiptPath;
                if (preg_match('#/storage/(.+)$#', $this->paymentReceiptPath, $m)) {
                    $pathToUse = $m[1];
                }
                $pathNormalized = str_replace('\\', '/', trim($pathToUse));
                $fullPath = storage_path('app/public/' . $pathNormalized);

                if (!file_exists($fullPath)) {
                    $fullPath = public_path('storage/' . $pathNormalized);
                }
                if (!file_exists($fullPath) && method_exists(Storage::disk('public'), 'path')) {
                    $fullPath = Storage::disk('public')->path($pathToUse);
                }
                if (file_exists($fullPath) && is_readable($fullPath)) {
                    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
                    $this->sendMedia($fullPath, $mime, $this->message, $this->userId, 0, 'administracion');
                } else {
                    Log::warning('SendViaticoWhatsappNotificationJob: archivo no encontrado o no legible', [
                        'path' => $this->paymentReceiptPath,
                        'pathToUse' => $pathToUse,
                        'fullPath' => $fullPath,
                        'storage_app_public' => storage_path('app/public'),
                        'public_storage' => public_path('storage')
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
