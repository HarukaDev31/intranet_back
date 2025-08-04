<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campana extends Model
{
    protected $table = 'campana_curso';
    protected $primaryKey = 'ID_Campana';
    
    protected $fillable = [
        'Fe_Creacion',
        'Fe_Inicio',
        'Fe_Fin',
        'Fe_Borrado'
    ];

    protected $casts = [
        'Fe_Creacion' => 'datetime',
        'Fe_Inicio' => 'date',
        'Fe_Fin' => 'date',
        'Fe_Borrado' => 'date'
    ];

    /**
     * Relación con PedidoCurso
     */
    public function pedidosCurso(): HasMany
    {
        return $this->hasMany(PedidoCurso::class, 'ID_Campana', 'ID_Campana');
    }

    /**
     * Scope para campañas activas (no borradas)
     */
    public function scopeActivas($query)
    {
        return $query->whereNull('Fe_Borrado');
    }

    /**
     * Scope para campañas vigentes (dentro del rango de fechas)
     */
    public function scopeVigentes($query)
    {
        $now = now()->toDateString();
        return $query->whereNull('Fe_Borrado')
                    ->where('Fe_Inicio', '<=', $now)
                    ->where('Fe_Fin', '>=', $now);
    }
} 