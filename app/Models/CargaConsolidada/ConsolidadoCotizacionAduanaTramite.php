<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\BaseDatos\Clientes\Cliente;

class ConsolidadoCotizacionAduanaTramite extends Model
{
    protected $table = 'consolidado_cotizacion_aduana_tramites';

    protected $fillable = [
        'id_cotizacion',
        'id_consolidado',
        'id_cliente',
        'id_entidad',
        'id_tipo_permiso',
        'derecho_entidad',
        'precio',
        'f_inicio',
        'f_termino',
        'f_caducidad',
        'dias',
        'estado',
    ];

    protected $casts = [
        'derecho_entidad' => 'decimal:4',
        'precio' => 'decimal:4',
        'f_inicio' => 'date',
        'f_termino' => 'date',
        'f_caducidad' => 'date',
        'dias' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const ESTADOS = [
        'PENDIENTE',
        'SD',
        'PAGADO',
        'EN_TRAMITE',
        'RECHAZADO',
        'COMPLETADO',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function consolidado(): BelongsTo
    {
        return $this->belongsTo(Contenedor::class, 'id_consolidado');
    }

    public function entidad(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaEntidad::class, 'id_entidad', 'id');
    }

    public function tipoPermiso(): BelongsTo
    {
        return $this->belongsTo(TramiteAduanaTipoPermiso::class, 'id_tipo_permiso', 'id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id');
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(TramiteAduanaCategoria::class, 'id_tramite');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteAduanaDocumento::class, 'id_tramite');
    }
}
