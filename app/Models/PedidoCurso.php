<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Campana;
use App\Models\Entidad;

class PedidoCurso extends Model
{
    protected $table = 'pedido_curso';
    protected $primaryKey = 'ID_Pedido_Curso';
    public $timestamps = false;
    
    protected $fillable = [
        'ID_Empresa',
        'ID_Entidad',
        'ID_Pais',
        'ID_Moneda',
        'ID_Campana',
        'Fe_Emision',
        'Fe_Registro',
        'Ss_Total',
        'logistica_final',
        'impuestos_final',
        'note_administracion',
        'Nu_Estado',
        'id_cliente_importacion',
        'from_intranet'
    ];

    protected $casts = [
        'Fe_Emision' => 'datetime',
        'Fe_Registro' => 'datetime',
        'Ss_Total' => 'decimal:2',
        'logistica_final' => 'decimal:2',
        'impuestos_final' => 'decimal:2'
    ];

    /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Cliente (Entidad)
     */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'ID_Entidad', 'ID_Entidad');
    }

    /**
     * Relación con Pais
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'ID_Pais', 'ID_Pais');
    }

    /**
     * Relación con Moneda
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'ID_Moneda', 'ID_Moneda');
    }

    /**
     * Relación con Usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ID_Entidad', 'ID_Entidad');
    }

    /**
     * Relación con Campana
     */
    public function campana(): BelongsTo
    {
        return $this->belongsTo(Campana::class, 'ID_Campana', 'ID_Campana');
    }

    /**
     * Relación con pagos de curso
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PedidoCursoPago::class, 'id_pedido_curso', 'ID_Pedido_Curso');
    }

    /**
     * Relación con ImportCliente
     */
    public function importCliente()
    {
        return $this->belongsTo(ImportCliente::class, 'id_cliente_importacion');
    }
} 