<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCoordinacionBatch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $table = 'whatsapp_coordinacion_batches';

    protected $fillable = [
        'tipo',
        'id_cotizacion',
        'phone_e164',
        'cliente',
        'carga',
        'status',
        'total_items',
        'completed_items',
        'failed_items',
        'job_domain',
        'laravel_batch_id',
        'outbound_laravel_batch_id',
        'dispatched_at',
        'finished_at',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'completed_items' => 'integer',
        'failed_items' => 'integer',
        'dispatched_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WhatsAppCoordinacionBatchItem::class, 'batch_id')->orderBy('sort_order');
    }

    public function progressPercent(): int
    {
        if ($this->total_items <= 0) {
            return 0;
        }

        return (int) round((($this->completed_items + $this->failed_items) / $this->total_items) * 100);
    }
}
