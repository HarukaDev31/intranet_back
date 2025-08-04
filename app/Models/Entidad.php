<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entidad extends Model
{
    protected $table = 'entidad';
    protected $primaryKey = 'ID_Entidad';
    
    protected $fillable = [
        'ID_Empresa',
        'ID_Organizacion',
        'ID_Almacen',
        'Nu_Tipo_Entidad',
        'ID_Tipo_Documento_Identidad',
        'Nu_Documento_Identidad',
        'No_Entidad',
        'Txt_Direccion_Entidad',
        'Fe_Nacimiento',
        'Nu_Tipo_Sexo',
        'Nu_Telefono_Entidad',
        'Nu_Celular_Entidad',
        'Txt_Email_Entidad',
        'ID_Pais',
        'ID_Departamento',
        'ID_Provincia',
        'ID_Distrito',
        'Nu_Dias_Credito',
        'No_Contacto',
        'Nu_Celular_Contacto',
        'Txt_Email_Contacto',
        'Txt_Descripcion',
        'Nu_Pin_Caja',
        'Nu_Estado',
        'ID_Tipo_Cliente_1',
        'Fe_Registro',
        'Nu_Edad',
        'Nu_Codigo_Pais',
        'Txt_Perfil_Compra',
        'Txt_Rubro_Importacion',
        'Fe_Viaje',
        'Nu_Agente_Compra',
        'Nu_Carga_Consolidada',
        'Nu_Importacion_Grupal',
        'Nu_Curso',
        'Nu_Viaje_Negocios',
        'ID_Tipo_Documento_Identidad_Externo',
        'Nu_Documento_Identidad_Externo',
        'Txt_Url_Imagen_Proveedor',
        'No_Wechat',
        'No_Titular_Cuenta_Bancaria',
        'Txt_Url_Imagen_Proveedor_Pay_Qr',
        'Nu_Tipo_Pay_Proveedor_China',
        'No_Banco_China',
        'Nu_Como_Entero_Empresa',
        'No_Otros_Como_Entero_Empresa'
    ];

    protected $casts = [
        'Fe_Nacimiento' => 'date',
        'Fe_Registro' => 'datetime',
        'Fe_Viaje' => 'date',
        'Nu_Tipo_Entidad' => 'integer',
        'Nu_Tipo_Sexo' => 'integer',
        'Nu_Dias_Credito' => 'integer',
        'Nu_Estado' => 'integer',
        'Nu_Edad' => 'integer',
        'Nu_Agente_Compra' => 'integer',
        'Nu_Carga_Consolidada' => 'integer',
        'Nu_Importacion_Grupal' => 'integer',
        'Nu_Curso' => 'integer',
        'Nu_Viaje_Negocios' => 'integer',
        'Nu_Tipo_Pay_Proveedor_China' => 'integer',
        'Nu_Como_Entero_Empresa' => 'integer'
    ];

    /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Organizacion
     */
    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * Relación con Almacen
     */
    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'ID_Almacen', 'ID_Almacen');
    }

    /**
     * Relación con Tipo Documento Identidad
     */
    public function tipoDocumentoIdentidad(): BelongsTo
    {
        return $this->belongsTo(TipoDocumentoIdentidad::class, 'ID_Tipo_Documento_Identidad', 'ID_Tipo_Documento_Identidad');
    }

    /**
     * Relación con Pais
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'ID_Pais', 'ID_Pais');
    }

    /**
     * Relación con Departamento
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'ID_Departamento', 'ID_Departamento');
    }

    /**
     * Relación con Provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'ID_Provincia', 'ID_Provincia');
    }

    /**
     * Relación con Distrito
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'ID_Distrito', 'ID_Distrito');
    }

    /**
     * Relación con Usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'ID_Entidad', 'ID_Entidad');
    }

    /**
     * Relación con PedidoCurso
     */
    public function pedidosCurso(): HasMany
    {
        return $this->hasMany(PedidoCurso::class, 'ID_Entidad', 'ID_Entidad');
    }
} 