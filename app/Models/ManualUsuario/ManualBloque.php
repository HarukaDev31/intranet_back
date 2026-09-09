<?php

namespace App\Models\ManualUsuario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualBloque extends Model
{
    /** Contenedor: siempre título + clave (ruta); puede tener hijos. */
    public const TIPO_GRUPO = 'grupo';

    /** Widgets (solo como subbloques / hojas). */
    public const TIPO_TEXTO = 'texto';
    public const TIPO_CALLOUT = 'callout';
    public const TIPO_MEDIA = 'media';
    public const TIPO_FLOW = 'flow';
    public const TIPO_EMBED = 'embed';
    public const TIPO_TABLA = 'tabla';
    public const TIPO_FILTROS = 'filtros';
    public const TIPO_TABS = 'tabs';
    public const TIPO_TOOLBAR = 'toolbar';
    public const TIPO_MODAL = 'modal';
    public const TIPO_CARD = 'card';
    public const TIPO_ACCION = 'accion';
    /** Contenedor horizontal: encadena widgets como pasos de un flujo. */
    public const TIPO_TIMELINE = 'timeline';

    private const ALIASES = [
        'ui_callout' => self::TIPO_CALLOUT,
        'media_shot' => self::TIPO_MEDIA,
        'ui_flow' => self::TIPO_FLOW,
        'ui_embed' => self::TIPO_EMBED,
        'ui_table' => self::TIPO_TABLA,
        'ui_filters' => self::TIPO_FILTROS,
        'ui_tabs' => self::TIPO_TABS,
        'ui_toolbar' => self::TIPO_TOOLBAR,
        'ui_modal' => self::TIPO_MODAL,
        'ui_card' => self::TIPO_CARD,
        'ucard' => self::TIPO_CARD,
        'ui_action' => self::TIPO_ACCION,
        'action' => self::TIPO_ACCION,
        'ui_timeline' => self::TIPO_TIMELINE,
        'linea' => self::TIPO_TIMELINE,
        'section' => self::TIPO_GRUPO,
        'group' => self::TIPO_GRUPO,
    ];

    protected $table = 'manual_bloques';

    protected $fillable = [
        'pagina_id',
        'parent_id',
        'tipo',
        'titulo',
        'clave',
        'payload',
        'orden',
    ];

    protected $casts = [
        'payload' => 'array',
        'orden' => 'integer',
        'parent_id' => 'integer',
    ];

    public function pagina(): BelongsTo
    {
        return $this->belongsTo(ManualPagina::class, 'pagina_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('orden')->orderBy('id');
    }

    public static function tiposGrupo(): array
    {
        return [self::TIPO_GRUPO];
    }

    public static function tiposWidget(): array
    {
        return [
            self::TIPO_TEXTO,
            self::TIPO_CALLOUT,
            self::TIPO_MEDIA,
            self::TIPO_FLOW,
            self::TIPO_EMBED,
            self::TIPO_TABLA,
            self::TIPO_FILTROS,
            self::TIPO_TABS,
            self::TIPO_TOOLBAR,
            self::TIPO_MODAL,
            self::TIPO_CARD,
            self::TIPO_ACCION,
            self::TIPO_TIMELINE,
        ];
    }

    public static function tiposValidos(): array
    {
        return array_merge(self::tiposGrupo(), self::tiposWidget());
    }

    public static function normalizeTipo(string $tipo): string
    {
        return self::ALIASES[$tipo] ?? $tipo;
    }

    public static function isValidTipo(string $tipo): bool
    {
        return in_array(self::normalizeTipo($tipo), self::tiposValidos(), true);
    }

    public static function isGrupo(string $tipo): bool
    {
        return self::normalizeTipo($tipo) === self::TIPO_GRUPO;
    }

    public static function isTimeline(string $tipo): bool
    {
        return self::normalizeTipo($tipo) === self::TIPO_TIMELINE;
    }

    /** Puede tener subbloques (grupo o línea de tiempo). */
    public static function isContainer(string $tipo): bool
    {
        $t = self::normalizeTipo($tipo);

        return $t === self::TIPO_GRUPO || $t === self::TIPO_TIMELINE;
    }

    public static function isWidget(string $tipo): bool
    {
        return in_array(self::normalizeTipo($tipo), self::tiposWidget(), true);
    }
}
