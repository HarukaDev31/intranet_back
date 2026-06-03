<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCoordinacionBatchItem extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'whatsapp_coordinacion_batch_items';

    protected $fillable = [
        'batch_id',
        'sort_order',
        'step_key',
        'label',
        'template_name',
        'payload_type',
        'status',
        'last_error',
        'bitrix_registro_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCoordinacionBatch::class, 'batch_id');
    }
}
