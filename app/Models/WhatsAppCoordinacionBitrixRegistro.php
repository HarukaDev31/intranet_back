<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppCoordinacionBitrixRegistro extends Model
{
    public const MAX_ATTEMPTS = 3;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'whatsapp_coordinacion_bitrix_registros';

    protected $fillable = [
        'phone_e164',
        'bitrix_contact_id',
        'bitrix_chat_id',
        'template_name',
        'bitrix_message',
        'meta_ok',
        'meta_error',
        'include_timeline',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'completed_at',
        'failed_at',
        'payload_extra',
    ];

    protected $casts = [
        'meta_ok' => 'boolean',
        'include_timeline' => 'boolean',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'payload_extra' => 'array',
    ];

    public function isProcessable(): bool
    {
        if ($this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_FAILED) {
            return false;
        }

        return $this->attempts < ($this->max_attempts ?: self::MAX_ATTEMPTS);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function recordAttemptFailure(string $message): bool
    {
        $attempts = $this->attempts + 1;
        $max = $this->max_attempts ?: self::MAX_ATTEMPTS;
        $permanentlyFailed = $attempts >= $max;

        $payload = [
            'attempts' => $attempts,
            'last_error' => mb_substr($message, 0, 500),
        ];

        if ($permanentlyFailed) {
            $payload['status'] = self::STATUS_FAILED;
            $payload['failed_at'] = now();
        }

        $this->update($payload);

        return $permanentlyFailed;
    }
}
