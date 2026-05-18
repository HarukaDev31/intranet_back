<?php

namespace App\Models\SoporteTi;

use Illuminate\Database\Eloquent\Model;

class SoporteTiSolicitud extends Model
{
    protected $table = 'soporte_ti_solicitudes';

    protected $fillable = [
        'codigo',
        'tipo_solicitud',
        'subtipo_b',
        'titulo',
        'prioridad',
        'area',
        'solicitante',
        'solicitante_user_id',
        'pm',
        'pm_user_id',
        'analista',
        'analista_user_id',
        'criticidad',
        'complejidad_pm',
        'complejidad_analista',
        'estado_actual_id',
        'fase_index',
        'progreso',
        'sla_horas',
        'horas_transcurridas',
        'sla_segundos_acumulados',
        'sla_reanudado_en',
        'fecha_fin_estimado',
        'seccion_ruta',
        'descripcion',
        'ultima_actualizacion',
    ];

    protected $casts = [
        'prioridad' => 'integer',
        'fase_index' => 'integer',
        'progreso' => 'integer',
        'sla_horas' => 'integer',
        'horas_transcurridas' => 'float',
        'sla_segundos_acumulados' => 'integer',
        'sla_reanudado_en' => 'datetime',
        'fecha_fin_estimado' => 'date',
        'ultima_actualizacion' => 'datetime',
    ];

    public function estadoActual()
    {
        return $this->belongsTo(SoporteTiEstado::class, 'estado_actual_id');
    }

    public function historialEstados()
    {
        return $this->hasMany(SoporteTiSolicitudEstado::class, 'solicitud_id')->orderBy('id', 'desc');
    }

    public function salaChat()
    {
        return $this->hasOne(SoporteTiChatSala::class, 'solicitud_id');
    }

    public function maqueta()
    {
        return $this->hasOne(SoporteTiMaqueta::class, 'solicitud_id');
    }

    public function evidencias()
    {
        return $this->hasMany(SoporteTiSolicitudEvidencia::class, 'solicitud_id')->orderBy('orden')->orderBy('id');
    }
}
