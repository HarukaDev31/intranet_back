<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'ID_Entidad';
    
    protected $fillable = [
        'Fe_Nacimiento',
        'Nu_Como_Entero_Empresa',
        'No_Otros_Como_Entero_Empresa',
        'ID_Distrito',
        'ID_Provincia',
        'ID_Departamento',
        'ID_Tipo_Documento_Identidad',
        'ID_Pais',
        'Nu_Tipo_Sexo',
        'No_Entidad',
        'Nu_Documento_Identidad',
        'Nu_Celular_Entidad',
        'Txt_Email_Entidad',
        'Nu_Edad'
    ];

    protected $casts = [
        'Fe_Nacimiento' => 'date',
        'Nu_Edad' => 'integer'
    ];

    /**
     * Relación con Distrito
     */
    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class, 'ID_Distrito', 'ID_Distrito');
    }

    /**
     * Relación con Provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'ID_Provincia', 'ID_Provincia');
    }

    /**
     * Relación con Departamento
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'ID_Departamento', 'ID_Departamento');
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
} 