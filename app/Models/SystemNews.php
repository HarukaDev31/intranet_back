<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class SystemNews extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'system_news';

    protected $fillable = [
        'title',
        'content',
        'summary',
        'type',
        'solicitada_por',
        'is_published',
        'published_at',
        'redirect',
        'created_by',
        'created_by_name'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Constantes para tipos
    const TYPE_UPDATE = 'update';
    const TYPE_FEATURE = 'feature';
    const TYPE_FIX = 'fix';
    const TYPE_ANNOUNCEMENT = 'announcement';

    // Constantes para solicitada_por
    const SOLICITADA_POR_CEO = 'CEO';
    const SOLICITADA_POR_COORDINACION = 'EQUIPO_DE_COORDINACION';
    const SOLICITADA_POR_VENTAS = 'EQUIPO_DE_VENTAS';
    const SOLICITADA_POR_CURSO = 'EQUIPO_DE_CURSO';
    const SOLICITADA_POR_DOCUMENTACION = 'EQUIPO_DE_DOCUMENTACION';
    const SOLICITADA_POR_ADMINISTRACION = 'ADMINISTRACION';
    const SOLICITADA_POR_TI = 'EQUIPO_DE_TI';
    const SOLICITADA_POR_MARKETING = 'EQUIPO_DE_MARKETING';

    /**
     * Relación con el usuario que creó la noticia
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by', 'ID_Usuario');
    }

    /**
     * Scope para noticias publicadas
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now()->toDateString());
            });
    }

    /**
     * Scope para noticias por tipo
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para noticias por solicitada_por
     */
    public function scopeBySolicitadaPor(Builder $query, string $solicitadaPor): Builder
    {
        return $query->where('solicitada_por', $solicitadaPor);
    }

    /**
     * Scope para ordenar por fecha de publicación
     */
    public function scopeOrderByPublished(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('published_at', $direction)
            ->orderBy('created_at', $direction);
    }

    /**
     * Verificar si la noticia está publicada
     */
    public function isPublished(): bool
    {
        if (!$this->is_published) {
            return false;
        }

        if ($this->published_at && $this->published_at->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Obtener el color según el tipo
     */
    public function getTypeColor(): string
    {
        switch ($this->type) {
            case self::TYPE_UPDATE:
                return 'blue';
            case self::TYPE_FEATURE:
                return 'green';
            case self::TYPE_FIX:
                return 'yellow';
            case self::TYPE_ANNOUNCEMENT:
                return 'purple';
            default:
                return 'gray';
        }
    }

    /**
     * Obtener el label del tipo
     */
    public function getTypeLabel(): string
    {
        switch ($this->type) {
            case self::TYPE_UPDATE:
                return 'Actualización';
            case self::TYPE_FEATURE:
                return 'Nueva Funcionalidad';
            case self::TYPE_FIX:
                return 'Corrección';
            case self::TYPE_ANNOUNCEMENT:
                return 'Anuncio';
            default:
                return 'Noticia';
        }
    }
}

