<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Pais;
use App\Models\Organizacion;
use App\Models\CargaConsolidada\Scopes\OrganizacionScope;

/**
 * @property int $id
 * @property string|null $mes
 * @property int|null $id_pais
 * @property int|null $organizacion_id
 * @property string|null $carga
 * @property string|null $parte
 * @property int|null $id_contenedor_origen
 * @property \Illuminate\Support\Carbon|null $f_puerto
 * @property \Illuminate\Support\Carbon|null $f_entrega
 * @property \Illuminate\Support\Carbon|null $f_cierre
 * @property \Illuminate\Support\Carbon|null $f_inicio
 * @property string|int|null $empresa
 * @property string|null $estado
 * @property string|null $estado_china
 * @property string|null $estado_documentacion
 * @property string|null $estado_finanzas
 * @property string|null $tipo_carga
 * @property string|null $naviera
 * @property string|null $tipo_contenedor
 * @property string|null $canal_control
 * @property string|null $numero_dua
 * @property \Illuminate\Support\Carbon|null $fecha_zarpe
 * @property \Illuminate\Support\Carbon|null $fecha_arribo
 * @property \Illuminate\Support\Carbon|null $fecha_declaracion
 * @property \Illuminate\Support\Carbon|null $fecha_levante
 * @property string|float|int|null $valor_fob
 * @property string|float|int|null $valor_flete
 * @property string|float|int|null $costo_destino
 * @property string|float|int|null $ajuste_valor
 * @property string|float|int|null $multa
 * @property string|float|int|null $limite_cbm_imo
 * @property string|null $observaciones
 * @property string|null $fecha_documentacion_max
 * @property string|null $lista_embarque_url
 * @property string|null $bl_file_url
 * @property string|null $factura_general_url
 * @property-read Pais|null $pais
 * @property-read Organizacion|null $organizacion
 * @property-read ContenedorTcYuan|null $tcYuan
 */
class Contenedor extends Model
{
    use HasFactory;
    use SoftDeletes;

    const CONTEDOR_CERRADO="COMPLETADO";
    const CONTEDOR_PENDIENTE="PENDIENTE";

    /** Letras disponibles al partir (máx. 10 subconsolidados). */
    const PARTES = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'carga_consolidada_contenedor';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected static function booted()
    {
        static::addGlobalScope(new OrganizacionScope());
    }

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'mes',
        'id_pais',
        'organizacion_id',
        'carga',
        'parte',
        'id_contenedor_origen',
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
        'estado_finanzas',
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
        'fecha_documentacion_max',
        'f_inicio',
        'limite_cbm_imo',
        'excel_seguimiento_drive_file_id',
        'excel_seguimiento_drive_link',
        'excel_seguimiento_vinculado_at',
        'excel_seguimiento_file_name',
        'excel_seguimiento_link_status',
        'excel_seguimiento_link_error',
    ];

    /** @see \App\Enums\CargaConsolidada\ExcelSeguimientoLinkStatus */

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
        'f_inicio' => 'date',
        'limite_cbm_imo' => 'decimal:2',
        'deleted_at' => 'datetime',
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
        'SEPTIEMBRE' => 'Septiembre',
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
     * Los valores posibles para el campo estado_finanzas.
     *
     * @var array
     */
    public const ESTADOS_FINANZAS = [
        'PENDIENTE' => 'Pendiente',
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
     * Label de carga para listados: "15 - 2026" o partido "15A-2026".
     *
     * @return string
     */
    public function formatCargaLabel()
    {
        $year = $this->f_inicio ? date('Y', strtotime($this->f_inicio)) : date('Y');
        if (!empty($this->parte)) {
            return $this->carga . $this->parte . '-' . $year;
        }

        return $this->carga . ' - ' . $year;
    }

    /**
     * Contenedor original del grupo partido (parte A).
     */
    public function contenedorOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_contenedor_origen');
    }

    /**
     * Obtiene el país asociado al contenedor.
     *
     * @return BelongsTo<Pais, $this>
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'id_pais', 'ID_Pais');
    }

    /**
     * Obtiene la organización asociada al contenedor.
     *
     * @return BelongsTo<Organizacion, $this>
     */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'ID_Organizacion');
    }

    /**
     * TC Yuan vigente del consolidado (periodo dado por created_at/updated_at).
     *
     * @return HasOne<ContenedorTcYuan, $this>
     */
    public function tcYuan(): HasOne
    {
        return $this->hasOne(ContenedorTcYuan::class, 'id_contenedor');
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
     * Scope para filtrar por estado de finanzas.
     */
    public function scopePorEstadoFinanzas($query, $estado)
    {
        return $query->where('estado_finanzas', $estado);
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
     * Scope para filtrar por organización.
     */
    public function scopePorOrganizacion($query, $idOrganizacion)
    {
        return $query->where('organizacion_id', $idOrganizacion);
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
               $this->estado_documentacion === 'COMPLETADO' &&
               $this->estado_finanzas === 'COMPLETADO';
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

    public function scopeCerradoPorDocumentacion($query)
    {
        return $query->where('estado_documentacion', self::CONTEDOR_CERRADO);
    }
}
