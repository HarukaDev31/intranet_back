<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organizacion_id
 * @property string $public_key
 * @property string|null $url_clientes
 * @property string|null $url_excel_confirmacion
 * @property string|null $url_datos_proveedor
 * @property string|null $drive_folder_id
 * @property string|null $logo_url
 * @property string|null $nombre_publico
 */
class OrganizacionPortal extends Model
{
    protected $table = 'organizacion_portales';

    protected $fillable = [
        'organizacion_id',
        'public_key',
        'url_clientes',
        'url_excel_confirmacion',
        'url_datos_proveedor',
        'drive_folder_id',
        'logo_url',
        'nombre_publico',
    ];

    protected $casts = [
        'organizacion_id' => 'integer',
    ];

    public function organizacion(): BelongsTo
    {
        return $this->belongsTo(Organizacion::class, 'organizacion_id', 'ID_Organizacion');
    }

    /**
     * @param  int  $organizacionId
     * @return self
     */
    public static function firstOrCreateForOrganizacion($organizacionId)
    {
        $organizacionId = (int) $organizacionId;
        $portal = static::where('organizacion_id', $organizacionId)->first();
        if ($portal) {
            return $portal;
        }

        return static::create([
            'organizacion_id' => $organizacionId,
            'public_key' => (string) Str::uuid(),
        ]);
    }
}
