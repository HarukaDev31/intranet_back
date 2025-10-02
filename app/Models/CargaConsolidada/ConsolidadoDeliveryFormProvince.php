<?php

namespace App\Models\CargaConsolidada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Departamento;
use App\Models\Provincia;
use App\Models\Distrito;
use App\Models\DeliveryAgency;

class ConsolidadoDeliveryFormProvince extends Model
{
    use HasFactory;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'consolidado_delivery_form_province';

    /**
     * Los atributos que son asignables masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'id_contenedor',
        'id_user',
        'id_cotizacion',
        'importer_nmae',
        'productos',
        'voucher_doc',
        'voucher_doc_type',
        'voucher_name',
        'voucher_email',
        'id_agency',
        'agency_ruc',
        'agency_name',
        'r_type',
        'r_doc',
        'r_name',
        'r_phone',
        'id_department',
        'id_province',
        'id_district',
        'agency_address_initial_delivery',
        'agency_address_final_delivery',
        'home_adress_delivery'
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
        'id_agency' => 'integer',
        'id_department' => 'integer',
        'id_province' => 'integer',
        'id_district' => 'integer',
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
     * Los valores posibles para el campo r_type.
     *
     * @var array
     */
    public const R_TYPES = [
        'PERSONA NATURAL' => 'Persona Natural',
        'EMPRESA' => 'Empresa'
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
     * Relación con DeliveryAgency
     */
    public function agency()
    {
        return $this->belongsTo(DeliveryAgency::class, 'id_agency');
    }

    /**
     * Relación con Departamento
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'id_department', 'ID_Departamento');
    }

    /**
     * Relación con Provincia
     */
    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'id_province', 'ID_Provincia');
    }

    /**
     * Relación con Distrito
     */
    public function distrito()
    {
        return $this->belongsTo(Distrito::class, 'id_district', 'ID_Distrito');
    }

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
     * Scope para filtrar por agencia.
     */
    public function scopePorAgencia($query, $idAgency)
    {
        return $query->where('id_agency', $idAgency);
    }

    /**
     * Scope para filtrar por departamento.
     */
    public function scopePorDepartamento($query, $idDepartment)
    {
        return $query->where('id_department', $idDepartment);
    }

    /**
     * Scope para filtrar por provincia.
     */
    public function scopePorProvincia($query, $idProvincia)
    {
        return $query->where('id_province', $idProvincia);
    }

    /**
     * Scope para filtrar por distrito.
     */
    public function scopePorDistrito($query, $idDistrito)
    {
        return $query->where('id_district', $idDistrito);
    }

    /**
     * Scope para filtrar por tipo de voucher.
     */
    public function scopePorTipoVoucher($query, $tipo)
    {
        return $query->where('voucher_doc_type', $tipo);
    }

    /**
     * Scope para filtrar por tipo de receptor.
     */
    public function scopePorTipoReceptor($query, $tipo)
    {
        return $query->where('r_type', $tipo);
    }

    /**
     * Scope para buscar por nombre del importador.
     */
    public function scopeBuscarImportador($query, $termino)
    {
        return $query->where('importer_nmae', 'LIKE', "%{$termino}%");
    }

    /**
     * Scope para buscar por nombre del receptor.
     */
    public function scopeBuscarReceptor($query, $termino)
    {
        return $query->where('r_name', 'LIKE', "%{$termino}%");
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
     * Verifica si el receptor es persona natural.
     */
    public function getEsPersonaNaturalAttribute()
    {
        return $this->r_type === 'PERSONA NATURAL';
    }

    /**
     * Verifica si el receptor es empresa.
     */
    public function getEsEmpresaAttribute()
    {
        return $this->r_type === 'EMPRESA';
    }

    /**
     * Obtiene el tipo de voucher en formato legible.
     */
    public function getTipoVoucherLegibleAttribute()
    {
        return self::VOUCHER_DOC_TYPES[$this->voucher_doc_type] ?? $this->voucher_doc_type;
    }

    /**
     * Obtiene el tipo de receptor en formato legible.
     */
    public function getTipoReceptorLegibleAttribute()
    {
        return self::R_TYPES[$this->r_type] ?? $this->r_type;
    }

    /**
     * Obtiene la dirección completa de entrega.
     */
    public function getDireccionCompletaAttribute()
    {
        return $this->home_adress_delivery;
    }

    /**
     * Obtiene la información completa de la ubicación.
     */
    public function getUbicacionCompletaAttribute()
    {
        $ubicacion = [];
        
        if ($this->departamento) {
            $ubicacion[] = $this->departamento->No_Departamento;
        }
        
        if ($this->provincia) {
            $ubicacion[] = $this->provincia->No_Provincia;
        }
        
        if ($this->distrito) {
            $ubicacion[] = $this->distrito->No_Distrito;
        }
        
        return implode(' - ', $ubicacion);
    }
}
