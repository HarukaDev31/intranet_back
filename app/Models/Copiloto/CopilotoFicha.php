<?php

namespace App\Models\Copiloto;

use Illuminate\Database\Eloquent\Model;

class CopilotoFicha extends Model
{
    protected $table = 'copiloto_fichas';

    protected $fillable = [
        'phone',
        'temperatura',
        'nivel',
        'senales',
        'objecion',
        'sugerencia',
        'sugerencia_corta',
        'bitrix_contact_id',
    ];

    protected $casts = [
        'senales' => 'array',
    ];
}

