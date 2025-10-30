<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pais;

class Contenedor extends Model
{
    use HasFactory;
    const CONTEDOR_CERRADO="COMPLETADO";
    const CONTEDOR_PENDIENTE="PENDIENTE";
    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'carga_consolidada_contenedor';
    protected $primaryKey = 'id';
    public $timestamps = false;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'mes',
        'id_pais',
        'carga',
        'f_puerto',
        'f_entrega',
        'empresa',
        'estado',
        'f_cierre',
        'lista_embarque_url',
        'lista_embarque_uploaded_at',
        'bl_file_url',
        'factura_general_url',
        'estado_china',
        'estado_documentacion',
        'tipo_carga',
        'naviera',
        'tipo_contenedor',
        'canal_control',
        'numero_dua',
        'fecha_zarpe',
        'fecha_arribo',
        'fecha_declaracion',
        'fecha_levante',
        'valor_fob',
        'valor_flete',
        'costo_destino',
        'ajuste_valor',
        'multa',
        'observaciones',
        'fecha_documentacion_max'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'f_puerto' => 'datetime',
        'f_entrega' => 'datetime',
        'f_cierre' => 'date',
        'lista_embarque_uploaded_at' => 'datetime',
        'fecha_zarpe' => 'date',
        'fecha_arribo' => 'date',
        'fecha_declaracion' => 'date',
        'fecha_levante' => 'date',
        'valor_fob' => 'decimal:2',
        'valor_flete' => 'decimal:2',
        'costo_destino' => 'decimal:2',
        'ajuste_valor' => 'decimal:2',
        'multa' => 'decimal:2',
    ];

    /**
     * Los valores posibles para el campo mes.
     *
     * @var array
     */
    public const MESES = [
        'ENERO' => 'Enero',
        'FEBRERO' => 'Febrero',
        'MARZO' => 'Marzo',
        'ABRIL' => 'Abril',
        'MAYO' => 'Mayo',
        'JUNIO' => 'Junio',
        'JULIO' => 'Julio',
        'AGOSTO' => 'Agosto',
        'SETIEMBRE' => 'Setiembre',
        'OCTUBRE' => 'Octubre',
        'NOVIEMBRE' => 'Noviembre',
        'DICIEMBRE' => 'Diciembre'
    ];

    /**
     * Los valores posibles para el campo estado.
     *
     * @var array
     */
    public const ESTADOS = [
        'PENDIENTE' => 'Pendiente',
        'RECIBIENDO' => 'Recibiendo',
        'COMPLETADO' => 'Completado'
    ];

    /**
     * Los valores posibles para el campo estado_china.
     *
     * @var array
     */
    public const ESTADOS_CHINA = [
        'PENDIENTE' => 'Pendiente',
        'RECIBIENDO' => 'Recibiendo',
        'COMPLETADO' => 'Completado'
    ];

    /**
     * Los valores posibles para el campo estado_documentacion.
     *
     * @var array
     */
    public const ESTADOS_DOCUMENTACION = [
        'PENDIENTE' => 'Pendiente',
        'DOCUMENTACION' => 'Documentación',
        'COMPLETADO' => 'Completado'
    ];

    /**
     * Los valores posibles para el campo tipo_carga.
     *
     * @var array
     */
    public const TIPOS_CARGA = [
        'G. IMPORTACION' => 'G. Importación',
        'CARGA CONSOLIDADA' => 'Carga Consolidada'
    ];

    /**
     * Obtiene el país asociado al contenedor.
     */
    public function pais()
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'ID_Pais');
    }

    /**
     * Scope para filtrar por mes.
     */
    public function scopePorMes($query, $mes)
    {
        return $query->where('mes', $mes);
    }

    /**
     * Scope para filtrar por estado.
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para filtrar por estado de China.
     */
    public function scopePorEstadoChina($query, $estado)
    {
        return $query->where('estado_china', $estado);
    }

    /**
     * Scope para filtrar por estado de documentación.
     */
    public function scopePorEstadoDocumentacion($query, $estado)
    {
        return $query->where('estado_documentacion', $estado);
    }

    /**
     * Scope para filtrar por tipo de carga.
     */
    public function scopePorTipoCarga($query, $tipo)
    {
        return $query->where('tipo_carga', $tipo);
    }

    /**
     * Scope para filtrar por país.
     */
    public function scopePorPais($query, $idPais)
    {
        return $query->where('id_pais', $idPais);
    }

    /**
     * Scope para contenedores pendientes.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    /**
     * Scope para contenedores completados.
     */
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'COMPLETADO');
    }

    /**
     * Scope para contenedores en proceso de recepción.
     */
    public function scopeRecibiendo($query)
    {
        return $query->where('estado', 'RECIBIENDO');
    }

    /**
     * Calcula el valor total del contenedor.
     */
    public function getValorTotalAttribute()
    {
        return $this->valor_fob + $this->valor_flete + $this->costo_destino + $this->ajuste_valor + $this->multa;
    }

    /**
     * Verifica si el contenedor tiene documentación completa.
     */
    public function getTieneDocumentacionCompletaAttribute()
    {
        return !empty($this->lista_embarque_url) && 
               !empty($this->bl_file_url) && 
               !empty($this->factura_general_url);
    }

    /**
     * Verifica si el contenedor está completamente procesado.
     */
    public function getEstaCompletadoAttribute()
    {
        return $this->estado === 'COMPLETADO' && 
               $this->estado_china === 'COMPLETADO' && 
               $this->estado_documentacion === 'COMPLETADO';
    }

    /**
     * Obtener todas las cargas únicas para filtros
     */
    public static function getCargasUnicas()
    {
        return self::select('carga')
            ->whereNotNull('carga')
            ->where('carga', '!=', '')
            ->distinct()
            ->orderByRaw('CAST(carga AS UNSIGNED)')
            ->pluck('carga')
            ->toArray();
    }
    //get cotizaciones en paso clientes, deben tewer estado_cotizacion CONFIRMADO y estado_cliente !=null
    
}
