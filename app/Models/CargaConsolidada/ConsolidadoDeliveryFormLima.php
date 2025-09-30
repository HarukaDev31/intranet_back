<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ConsolidadoDeliveryFormLima extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'consolidado_delivery_form_lima';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_contenedor',
        'id_user',
        'id_cotizacion',
        'id_range_date',
        'pick_name',
        'pick_doc',
        'import_name',
        'productos',
        'voucher_doc',
        'voucher_doc_type',
        'voucher_name',
        'voucher_email',
        'drver_name',
        'driver_doc_type',
        'driver_doc',
        'driver_license',
        'driver_plate',
        'final_destination_place',
        'final_destination_district'
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'id_contenedor' => 'integer',
        'id_user' => 'integer',
        'id_cotizacion' => 'integer',
        'id_range_date' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Los valores posibles para el campo voucher_doc_type.
     *
     * @var array
     */
    public const VOUCHER_DOC_TYPES = [
        'BOLETA' => 'Boleta',
        'FACTURA' => 'Factura'
    ];

    /**
     * Los valores posibles para el campo driver_doc_type.
     *
     * @var array
     */
    public const DRIVER_DOC_TYPES = [
        'DNI' => 'DNI',
        'PASAPORTE' => 'Pasaporte'
    ];

    /**
     * Relación con Contenedor
     */
    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'id_contenedor');
    }

    /**
     * Relación con User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Relación con Cotizacion
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    /**
     * Relación con ConsolidadoDeliveryRangeDate (se agregará cuando se cree el modelo)
     */
    // public function rangeDate()
    // {
    //     return $this->belongsTo(\App\Models\CargaConsolidada\ConsolidadoDeliveryRangeDate::class, 'id_range_date');
    // }

    /**
     * Scope para filtrar por contenedor.
     */
    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('id_contenedor', $idContenedor);
    }

    /**
     * Scope para filtrar por usuario.
     */
    public function scopePorUsuario($query, $idUser)
    {
        return $query->where('id_user', $idUser);
    }

    /**
     * Scope para filtrar por cotización.
     */
    public function scopePorCotizacion($query, $idCotizacion)
    {
        return $query->where('id_cotizacion', $idCotizacion);
    }

    /**
     * Scope para filtrar por rango de fecha.
     */
    public function scopePorRangoFecha($query, $idRangeDate)
    {
        return $query->where('id_range_date', $idRangeDate);
    }

    /**
     * Scope para filtrar por tipo de voucher.
     */
    public function scopePorTipoVoucher($query, $tipo)
    {
        return $query->where('voucher_doc_type', $tipo);
    }

    /**
     * Scope para filtrar por tipo de documento del conductor.
     */
    public function scopePorTipoDocumentoConductor($query, $tipo)
    {
        return $query->where('driver_doc_type', $tipo);
    }

    /**
     * Scope para buscar por nombre del importador.
     */
    public function scopeBuscarImportador($query, $termino)
    {
        return $query->where('import_name', 'LIKE', "%{$termino}%");
    }

    /**
     * Scope para buscar por nombre del conductor.
     */
    public function scopeBuscarConductor($query, $termino)
    {
        return $query->where('drver_name', 'LIKE', "%{$termino}%");
    }

    /**
     * Scope para buscar por placa del vehículo.
     */
    public function scopeBuscarPorPlaca($query, $placa)
    {
        return $query->where('driver_plate', 'LIKE', "%{$placa}%");
    }

    /**
     * Scope para buscar por distrito de destino.
     */
    public function scopeBuscarPorDistritoDestino($query, $distrito)
    {
        return $query->where('final_destination_district', 'LIKE', "%{$distrito}%");
    }

    /**
     * Verifica si es una boleta.
     */
    public function getEsBoletaAttribute()
    {
        return $this->voucher_doc_type === 'BOLETA';
    }

    /**
     * Verifica si es una factura.
     */
    public function getEsFacturaAttribute()
    {
        return $this->voucher_doc_type === 'FACTURA';
    }

    /**
     * Verifica si el conductor tiene DNI.
     */
    public function getConductorTieneDniAttribute()
    {
        return $this->driver_doc_type === 'DNI';
    }

    /**
     * Verifica si el conductor tiene pasaporte.
     */
    public function getConductorTienePasaporteAttribute()
    {
        return $this->driver_doc_type === 'PASAPORTE';
    }

    /**
     * Obtiene el tipo de voucher en formato legible.
     */
    public function getTipoVoucherLegibleAttribute()
    {
        return self::VOUCHER_DOC_TYPES[$this->voucher_doc_type] ?? $this->voucher_doc_type;
    }

    /**
     * Obtiene el tipo de documento del conductor en formato legible.
     */
    public function getTipoDocumentoConductorLegibleAttribute()
    {
        return self::DRIVER_DOC_TYPES[$this->driver_doc_type] ?? $this->driver_doc_type;
    }

    /**
     * Obtiene la información completa del conductor.
     */
    public function getInfoConductorCompletaAttribute()
    {
        return "{$this->drver_name} - {$this->getTipoDocumentoConductorLegibleAttribute()}: {$this->driver_doc}";
    }

    /**
     * Obtiene la información completa del vehículo.
     */
    public function getInfoVehiculoCompletaAttribute()
    {
        return "Placa: {$this->driver_plate} - Licencia: {$this->driver_license}";
    }

    /**
     * Obtiene la información completa del destino.
     */
    public function getDestinoCompletoAttribute()
    {
        return "{$this->final_destination_place} - {$this->final_destination_district}";
    }

    /**
     * Obtiene la información completa del importador.
     */
    public function getInfoImportadorCompletaAttribute()
    {
        return "{$this->import_name} - Productos: {$this->productos}";
    }

    /**
     * Obtiene la información completa del picker.
     */
    public function getInfoPickerCompletaAttribute()
    {
        return "{$this->pick_name} - Doc: {$this->pick_doc}";
    }
}
