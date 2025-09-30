<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryAgency extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'delivery_agencies';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'ruc'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope para buscar agencias por nombre.
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('name', 'LIKE', "%{$termino}%")
                ->orWhere('ruc', 'LIKE', "%{$termino}%");
        });
    }

    /**
     * Scope para ordenar por nombre.
     */
    public function scopeOrdenarPorNombre($query)
    {
        return $query->orderBy('name');
    }

    /**
     * Verifica si la agencia tiene RUC válido.
     */
    public function getTieneRucValidoAttribute()
    {
        return !empty($this->ruc) && strlen($this->ruc) >= 8;
    }

    /**
     * Obtiene el nombre formateado de la agencia.
     */
    public function getNombreFormateadoAttribute()
    {
        return $this->name . ($this->ruc ? " (RUC: {$this->ruc})" : '');
    }

    /**
     * Relación con ConsolidadoDeliveryFormProvince
     */
    public function formulariosProvincia()
    {
        return $this->hasMany(\App\Models\CargaConsolidada\ConsolidadoDeliveryFormProvince::class, 'id_agency');
    }

    /**
     * Relación con ConsolidadoDeliveryFormLima
     */
    public function formulariosLima()
    {
        return $this->hasMany(\App\Models\CargaConsolidada\ConsolidadoDeliveryFormLima::class, 'id_agency');
    }
}
