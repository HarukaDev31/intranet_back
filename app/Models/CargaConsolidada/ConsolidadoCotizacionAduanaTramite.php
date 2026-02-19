<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'precio',
        'f_inicio',
        'f_termino',
        'f_caducidad',
        'dias',
        'estado',
        'tramitador',
    ];

    protected $casts = [
        'precio' => 'decimal:4',
        'tramitador' => 'decimal:2',
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

    public function tiposPermiso(): BelongsToMany
    {
        return $this->belongsToMany(
            TramiteAduanaTipoPermiso::class,
            'tramite_aduana_tramite_tipo_permiso',
            'id_tramite',
            'id_tipo_permiso'
        )->withPivot('derecho_entidad', 'estado', 'f_inicio', 'f_termino', 'f_caducidad', 'dias')->withTimestamps();
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

    public function pagos(): HasMany
    {
        return $this->hasMany(TramiteAduanaPago::class, 'id_tramite');
    }
}
