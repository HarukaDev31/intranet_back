<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubdominioTiendaVirtual extends Model
{
    protected $table = 'subdominio_tienda_virtual';
    protected $primaryKey = 'ID_Subdominio_Tienda_Virtual';
    
    protected $fillable = [
        'ID_Empresa',
        'No_Dominio_Tienda_Virtual',
        'No_Subdominio_Tienda_Virtual',
        'Nu_Estado'
    ];

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }
} 