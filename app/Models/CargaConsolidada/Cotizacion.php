<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class Cotizacion extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'contenedor_consolidado_cotizacion';
    public $timestamps = false;

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_contenedor_pago',
        'id_contenedor',
        'uuid',
        'id_tipo_cliente',
        'id_cliente',
        'fecha',
        'nombre',
        'documento',
        'correo',
        'telefono',
        'volumen',
        'cotizacion_file_url',
        'cotizacion_contrato_url',
        'cotizacion_contrato_firmado_url',
        'cotizacion_final_file_url',
        'estado',
        'volumen_doc',
        'valor_doc',
        'valor_cot',
        'volumen_china',
        'factura_comercial',
        'id_usuario',
        'monto',
        'fob',
        'impuestos',
        'tarifa',
        'excel_comercial',
        'excel_confirmacion',
        'vol_selected',
        'estado_cliente',
        'peso',
        'peso_final',
        'tarifa_final',
        'monto_final',
        'volumen_final',
        'guia_remision_url',
        'factura_general_url',
        'cotizacion_final_url',
        'estado_cotizador',
        'fecha_confirmacion',
        'estado_pagos_coordinacion',
        'estado_cotizacion_final',
        'impuestos_final',
        'fob_final',
        'note_administracion',
        'status_cliente_doc',
        'logistica_final',
        'qty_item',
        'id_cliente_importacion',
        'delivery_form_registered_at',
        'total_pago_delivery',
        'tipo_servicio',
        'send_alert_difference_cbm_status',
        'cod_contract',
        'autosigned_contract_at',
        'cotizacion_contrato_autosigned_url',
        'from_calculator',
        'id_contenedor_destino'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'fecha' => 'datetime',
        'fecha_confirmacion' => 'datetime',
        'autosigned_contract_at' => 'datetime',
        'volumen' => 'decimal:2',
        'valor_doc' => 'decimal:2',
        'valor_cot' => 'decimal:2',
        'monto' => 'decimal:2',
        'fob' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'tarifa' => 'decimal:2',
        'peso' => 'decimal:2',
        'peso_final' => 'decimal:2',
        'tarifa_final' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'volumen_final' => 'decimal:2',
        'impuestos_final' => 'decimal:2',
        'fob_final' => 'decimal:2',
        'logistica_final' => 'decimal:2',
        'qty_item' => 'integer',
        'delivery_form_registered_at' => 'date',
        'total_pago_delivery' => 'decimal:2',
        'from_calculator' => 'boolean',
    ];

    /**
     * Los valores posibles para el campo estado.
     *
     * @var array
     */
    public const ESTADOS = [
        'PENDIENTE' => 'Pendiente',
        'CONFIRMADO' => 'Confirmado',
        'DECLINADO' => 'Declinado'
    ];

    /**
     * Los valores posibles para el campo estado_cliente.
     *
     * @var array
     */
    public const ESTADOS_CLIENTE = [
        'RESERVADO' => 'Reservado',
        'NO RESERVADO' => 'No Reservado',
        'DOCUMENTACION' => 'Documentación',
        'C FINAL' => 'C. Final',
        'FACTURADO' => 'Facturado'
    ];

    /**
     * Los valores posibles para el campo estado_cotizador.
     *
     * @var array
     */
    public const ESTADOS_COTIZADOR = [
        'PENDIENTE' => 'Pendiente',
        'CONFIRMADO' => 'Confirmado',
        'INTERESADO' => 'Interesado',
        'CONTACTADO' => 'Contactado'
    ];

    /**
     * Los valores posibles para el campo estado_pagos_coordinacion.
     *
     * @var array
     */
    public const ESTADOS_PAGOS_COORDINACION = [
        'PENDIENTE' => 'Pendiente',
        'ADELANTO' => 'Adelanto',
        'PAGADO' => 'Pagado',
        'SOBREPAGO' => 'Sobrepago'
    ];

    /**
     * Los valores posibles para el campo estado_cotizacion_final.
     *
     * @var array
     */
    public const ESTADOS_COTIZACION_FINAL = [
        'PENDIENTE' => 'Pendiente',
        'C.FINAL' => 'C. Final',
        'AJUSTADO' => 'Ajustado',
        'COTIZADO' => 'Cotizado',
        'COBRANDO' => 'Cobrando',
        'PAGADO' => 'Pagado',
        'SOBREPAGO' => 'Sobrepago',
    ];

    /**
     * Los valores posibles para el campo status_cliente_doc.
     *
     * @var array
     */
    public const STATUS_CLIENTE_DOC = [
        'Pendiente' => 'Pendiente',
        'Incompleto' => 'Incompleto',
        'Completado' => 'Completado'
    ];

    /**
     * Estados para el envío de alerta de diferencia CBM
     *
     * @var array
     */
    public const SEND_ALERT_DIFFERENCE_CBM_STATUS = [
        'PENDING' => 'PENDING',
        'SENDED' => 'SENDED'
    ];

    /**
     * Obtiene el contenedor asociado a la cotización.
     */
    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    /**
     * Obtiene el tipo de cliente asociado a la cotización.
     */
    public function tipoCliente()
    {
        return $this->belongsTo(TipoCliente::class, 'id_tipo_cliente');
    }

    /**
     * Obtiene el usuario asociado a la cotización.
     */
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'ID_Usuario');
    }

    /**
     * Relación con Pagos
     */
    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_cotizacion');
    }

    /**
     * Relación con Facturas Comerciales
     */
    public function facturasComerciales()
        {
            return $this->hasMany(FacturaComercial::class, 'quotation_id', 'id');
    }

    /**
     * Scope para filtrar por estado.
     */
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope para filtrar por estado de cliente.
     */
    public function scopePorEstadoCliente($query, $estado)
    {
        return $query->where('estado_cliente', $estado);
    }

    /**
     * Scope para filtrar por estado de cotizador.
     */
    public function scopePorEstadoCotizador($query, $estado)
    {
        return $query->where('estado_cotizador', $estado);
    }

    /**
     * Scope para filtrar por estado de pagos coordinación.
     */
    public function scopePorEstadoPagosCoordinacion($query, $estado)
    {
        return $query->where('estado_pagos_coordinacion', $estado);
    }

    /**
     * Scope para filtrar por estado de cotización final.
     */
    public function scopePorEstadoCotizacionFinal($query, $estado)
    {
        return $query->where('estado_cotizacion_final', $estado);
    }

    /**
     * Scope para filtrar por contenedor.
     */
    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('id_contenedor', $idContenedor);
    }

    /**
     * Scope para filtrar por tipo de cliente.
     */
    public function scopePorTipoCliente($query, $idTipoCliente)
    {
        return $query->where('id_tipo_cliente', $idTipoCliente);
    }

    /**
     * Scope para filtrar por usuario.
     */
    public function scopePorUsuario($query, $idUsuario)
    {
        return $query->where('id_usuario', $idUsuario);
    }

    /**
     * Scope para cotizaciones pendientes.
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    /**
     * Scope para cotizaciones confirmadas.
     */
    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'CONFIRMADO');
    }

    /**
     * Scope para cotizaciones declinadas.
     */
    public function scopeDeclinadas($query)
    {
        return $query->where('estado', 'DECLINADO');
    }

    /**
     * Scope para buscar por nombre o documento.
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'LIKE', "%{$termino}%")
                ->orWhere('documento', 'LIKE', "%{$termino}%")
                ->orWhere('correo', 'LIKE', "%{$termino}%");
        });
    }

    /**
     * Calcula el valor total de la cotización.
     */
    public function getValorTotalAttribute()
    {
        return $this->valor_cot ?? 0;
    }

    /**
     * Calcula el valor total final de la cotización.
     */
    public function getValorTotalFinalAttribute()
    {
        return ($this->fob_final ?? 0) + ($this->impuestos_final ?? 0) + ($this->logistica_final ?? 0);
    }

    /**
     * Verifica si la cotización tiene documentación completa.
     */
    public function getTieneDocumentacionCompletaAttribute()
    {
        return !empty($this->cotizacion_file_url) &&
            !empty($this->cotizacion_final_file_url) &&
            !empty($this->guia_remision_url) &&
            !empty($this->factura_general_url);
    }

    /**
     * Verifica si la cotización está confirmada.
     */
    public function getEstaConfirmadaAttribute()
    {
        return $this->estado === 'CONFIRMADO';
    }

    /**
     * Verifica si la cotización está pagada.
     */
    public function getEstaPagadaAttribute()
    {
        return $this->estado_pagos_coordinacion === 'PAGADO' ||
            $this->estado_pagos_coordinacion === 'SOBREPAGO';
    }

    /**
     * Obtiene el estado de la cotización en formato legible.
     */
    public function getEstadoLegibleAttribute()
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Obtiene el estado del cliente en formato legible.
     */
    public function getEstadoClienteLegibleAttribute()
    {
        return self::ESTADOS_CLIENTE[$this->estado_cliente] ?? $this->estado_cliente;
    }

    /**
     * Relación con ImportCliente
     */
    public function importCliente()
    {
        return $this->belongsTo(\App\Models\ImportCliente::class, 'id_cliente_importacion');
    }

    /**
     * Relación con CotizacionDocumentacion
     */
    public function documentacion()
    {
        return $this->hasMany(CotizacionDocumentacion::class, 'id_cotizacion');
    }

    /**
     * Relación con CotizacionProveedor
     */
    public function proveedores()
    {
        return $this->hasMany(CotizacionProveedor::class, 'id_cotizacion');
    }

    /**
     * Relación con AlmacenDocumentacion
     */
    public function documentacionAlmacen()
    {
        return $this->hasMany(AlmacenDocumentacion::class, 'id_cotizacion');
    }

    /**
     * Relación con AlmacenInspection
     */
    public function inspeccionAlmacen()
    {
        return $this->hasMany(AlmacenInspection::class, 'id_cotizacion');
    }
    //function get sum of cbm_total of proveedores whwere estados_proveedor is 'LOADED' and estado_cotizador is 'CONFIRMADO'
    public function getSumCbmTotalChinaAttribute()
    {
        return $this->proveedores->where('estados_proveedor', 'LOADED')->sum('cbm_total_china');
    }
    //function get sum of qty_box of proveedores whwere estados_proveedor is 'LOADED' and estado_cotizador is 'CONFIRMADO'
    public function getSumQtyBoxChinaAttribute()
    {
        return $this->proveedores->where('estados_proveedor', 'LOADED')->sum('qty_box_china');
    }
    public function getSumValorDocAttribute()
    {
        return $this->proveedores->where('estados_proveedor', 'LOADED')->sum('valor_doc');
    }
    //sum volume_doc of proveedores whwere estados_proveedor is 'LOADED' and estado_cotizador is 'CONFIRMADO'
    public function getSumVolumeDocAttribute()
    {
        return $this->proveedores->where('estados_proveedor', 'LOADED')->sum('volume_doc');
    }
    public function getSumVolumeFinalAttribute(){
        //IF COTIZACION HAS VOLUME FINAL, RETURN VOLUME FINAL, ELSE RETURN VOLUME DOC
        return $this->volumen_final==0 || $this->volumen_final==null ? $this->volumen : $this->volumen_final;
    }
    //return cotizaciones en paso clientes, deben tewer estado_cotizacion CONFIRMADO y estado_cliente !=null
    public function scopeCotizacionesEnPasoClientes($query){
            return $query->where('estado_cotizador', 'CONFIRMADO')
            ->whereNotNull('estado_cliente');
    }

    /**
     * Verifica si el formulario de delivery fue registrado.
     */
    public function getFormularioDeliveryRegistradoAttribute()
    {
        return !is_null($this->delivery_form_registered_at);
    }

    /**
     * Scope para filtrar cotizaciones con formulario de delivery registrado.
     */
    public function scopeConFormularioDelivery($query)
    {
        return $query->whereNotNull('delivery_form_registered_at');
    }

    /**
     * Scope para filtrar cotizaciones sin formulario de delivery registrado.
     */
    public function scopeSinFormularioDelivery($query)
    {
        return $query->whereNull('delivery_form_registered_at');
    }
}
