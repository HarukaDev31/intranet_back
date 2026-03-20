<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebCursoPlan extends Model
{
    protected $table = 'web_curso_planes';

    protected $fillable = [
        'page_key',
        'tipo_pago',
        'title',
        'subtitle',
        'price_current',
        'price_original',
        'price_amount',
        'benefits',
        'button_label',
        'button_css_classes',
        'card_css_classes',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'benefits' => 'array',
        'is_visible' => 'boolean',
        'tipo_pago' => 'integer',
        'price_amount' => 'integer',
        'sort_order' => 'integer',
    ];

    public const PAGE_CURSO_MEMBRESIA = 'curso_membresia';

    public function scopeForPage($query, string $pageKey)
    {
        return $query->where('page_key', $pageKey);
    }

    public function scopeVisiblePublic($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
