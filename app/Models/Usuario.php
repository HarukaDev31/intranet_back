<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read Grupo|null $grupo
 */
class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey = 'ID_Usuario';
    const ROL_COTIZADOR = 'Cotizador';
    const ROL_COORDINACION = 'Coordinación';
    const ROL_ALMACEN_CHINA = 'ContenedorAlmacen';
    const ROL_ADMINISTRACION = 'Administración';
    const ROL_DOCUMENTACION = 'Documentacion';
    const ROL_CATALOGO_CHINA = 'CatalogoChina';
    const ROL_JEFE_IMPORTACION = 'Jefe Importacion';
    const ROL_COORDINADOR_GENERAL = 'Coordinador General';
    const ROL_CONTABILIDAD = 'Contabilidad';
    const ROL_GERENCIA = 'GERENCIA';
    const JEFE_MARKETING = 'Jefe Marketing';
    const ROL_SOPORTE = 'Soporte';
    const ROL_PM = 'PM';
    const ROL_FINANZAS = 'Finanzas';
    const ROL_RRHH = 'RRHH';
    const ROL_SOCIO = 'Socio';
    const ROL_GERENTE_GENERAL = 'GERENTE GENERAL';
    const ID_ORGANIZACION_ADMIN = 1;

    /**
     * Roles que operan fisicamente para todas las organizaciones a la vez
     * (ej. el almacen de China recibe carga de cualquier organizacion) y por
     * eso necesitan ver y editar datos cross-org, sin depender de filas en
     * grupo_usuario por cada organizacion nueva que se cree.
     */
    const ROLES_VISIBILIDAD_GLOBAL = [
        self::ROL_ALMACEN_CHINA,
    ];
    protected $fillable = [
        'No_Usuario',
        'No_Password',
        'No_Password_Sin_Encriptar',
        'Nu_Estado',
        'ID_Empresa',
        'ID_Organizacion',
        'ID_Grupo',
        'Fe_Creacion',
        'ID_Pais',
        'ID_Departamento',
        'ID_Provincia',
        'ID_Distrito',
        'Fe_Nacimiento',
        'Nu_Documento',
        'Txt_Objetivos',
        'Txt_Foto',
        'Txt_Email',
        'Nu_Celular',
        'No_Nombres_Apellidos'
    ];

    protected $hidden = [
        'No_Password',
    ];
    const ID_JEFE_VENTAS = 28791;

    /** @var array<int, int>|null Memo en memoria de organizacionesPermitidas() para este request. */
    private $organizacionesPermitidasMemo = null;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'ID_Empresa', 'ID_Empresa');
    }

    /**
     * Relación con Organizacion
     */
    public function organizacion()
    {
        return $this->belongsTo(Organizacion::class, 'ID_Organizacion', 'ID_Organizacion');
    }

    /**
     * IDs de organización a las que el usuario tiene acceso, resueltos en vivo
     * desde grupo_usuario (un usuario puede tener una fila por organización).
     * No se cachea en el token: si le quitan acceso a una organización, se
     * corta en la siguiente request. Se memoiza solo en memoria de este
     * request para no repetir la query dentro del mismo ciclo.
     *
     * @return array<int, int>
     */
    public function organizacionesPermitidas(): array
    {
        if ($this->organizacionesPermitidasMemo !== null) {
            return $this->organizacionesPermitidasMemo;
        }

        if (in_array($this->getNombreGrupo(), self::ROLES_VISIBILIDAD_GLOBAL, true)
            || $this->puedeVerContenedoresDeOtrasOrgs()
        ) {
            return $this->organizacionesPermitidasMemo = Organizacion::query()
                ->where('Nu_Estado', 1)
                ->pluck('ID_Organizacion')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        $ids = GrupoUsuario::query()
            ->where('ID_Usuario', $this->getKey())
            ->whereNotNull('ID_Organizacion')
            ->distinct()
            ->pluck('ID_Organizacion')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $idOrganizacionPropia = $this->getAttribute('ID_Organizacion');
        if ($ids === [] && $idOrganizacionPropia !== null) {
            $ids = [(int) $idOrganizacionPropia];
        }

        return $this->organizacionesPermitidasMemo = $ids;
    }

    /**
     * Coordinación / Documentación / Jefe de org 1 ven contenedores de socios.
     * El mismo rol en org ≠ 1 solo ve la suya.
     */
    public function puedeVerContenedoresDeOtrasOrgs(): bool
    {
        if ((int) $this->getAttribute('ID_Organizacion') !== self::ID_ORGANIZACION_ADMIN) {
            return false;
        }

        $rol = $this->getNombreGrupo();
        if (self::rolEquivaleJefeImportacion($rol)) {
            return true;
        }

        return in_array($rol, [
            self::ROL_COORDINACION,
            self::ROL_DOCUMENTACION,
        ], true);
    }

    /**
     * Relación directa con Grupo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'ID_Grupo', 'ID_Grupo');
    }

    /**
     * Relación con GrupoUsuario
     */
    public function gruposUsuario()
    {
        return $this->hasMany(GrupoUsuario::class, 'ID_Usuario', 'ID_Usuario');
    }

    /**
     * Relación con Almacen
     */
    public function almacenes()
    {
        return $this->hasManyThrough(
            Almacen::class,
            Organizacion::class,
            'ID_Organizacion',
            'ID_Organizacion',
            'ID_Organizacion'
        );
    }

    /**
     * Obtener todos los grupos del usuario (incluyendo la relación many-to-many)
     */
    public function getAllGrupos()
    {
        $grupos = collect();

        // Agregar el grupo directo si existe
        if ($this->grupo) {
            $grupos->push($this->grupo);
        }

        // Agregar grupos de la relación many-to-many
        $gruposManyToMany = $this->gruposUsuario()->with('grupo')->get()->pluck('grupo');
        $grupos = $grupos->merge($gruposManyToMany);

        return $grupos->unique('ID_Grupo');
    }

    /**
     * Verificar si el usuario pertenece a un grupo específico
     */
    public function perteneceAGrupo($grupoId)
    {
        // Verificar grupo directo
        if ($this->ID_Grupo == $grupoId) {
            return true;
        }

        // Verificar grupos many-to-many
        return $this->gruposUsuario()->where('ID_Grupo', $grupoId)->exists();
    }

    /**
     * Obtener el nombre del grupo principal del usuario
     */
    public function getNombreGrupo()
    {
        return $this->grupo ? $this->grupo->No_Grupo : 'Sin grupo';
    }

    /**
     * Obtener el id del usuario
     */
    public function getIdUsuario()
    {
        return $this->ID_Usuario;
    }

    /**
     * Obtener la descripción del grupo principal del usuario
     */
    public function getDescripcionGrupoPrincipalAttribute()
    {
        return $this->grupo ? $this->grupo->No_Grupo_Descripcion : 'Sin descripción';
    }

    /**
     * Obtener el tipo de privilegio de acceso del grupo principal
     */
    public function getTipoPrivilegioAccesoAttribute()
    {
        return $this->grupo ? $this->grupo->Nu_Tipo_Privilegio_Acceso : null;
    }

    /**
     * Roles con acceso al módulo WhatsApp Inbox (API + canal Pusher).
     *
     * @return string[]
     */
    /**
     * Roles con los mismos permisos operativos que Jefe de Importaciones en carga consolidada.
     *
     * @return string[]
     */
    public static function rolesEquivalentesJefeImportacion()
    {
        return [
            self::ROL_JEFE_IMPORTACION,
            self::ROL_COORDINADOR_GENERAL,
        ];
    }

    public static function rolEquivaleJefeImportacion($rol)
    {
        if ($rol === null || $rol === '') {
            return false;
        }

        return in_array(trim((string) $rol), self::rolesEquivalentesJefeImportacion(), true);
    }

    public function usuarioEquivaleJefeImportacion()
    {
        return self::rolEquivaleJefeImportacion($this->getNombreGrupo());
    }

    /**
     * Verifica si el usuario tiene los mismos accesos que el Jefe de Ventas
     * (GINO, identificado por ID_JEFE_VENTAS) dentro del flujo de carga consolidada:
     * es el propio GINO o pertenece al rol RRHH.
     *
     * @return bool
     */
    public function esJefeVentasOEquivalente()
    {
        if ($this->getIdUsuario() === self::ID_JEFE_VENTAS) {
            return true;
        }

        return trim((string) $this->getNombreGrupo()) === self::ROL_RRHH;
    }

    /**
     * El inbox se abre por menú en la intranet. API y canal WS solo exigen sesión.
     *
     * @return bool
     */
    public function puedeAccederWhatsappInbox()
    {
        return $this->getKey() !== null;
    }

    /**
     * Org 1: Gerencia General / GERENCIA (root). Resto: Socio.
     *
     * @return bool
     */
    public function puedeConfigurarWhatsappInbox()
    {
        if ($this->getKey() === null) {
            return false;
        }

        $orgId = (int) $this->getAttribute('ID_Organizacion');
        $grupo = trim((string) $this->getNombreGrupo());
        $usuario = strtolower(trim((string) $this->No_Usuario));

        if ($orgId === self::ID_ORGANIZACION_ADMIN) {
            if ($usuario === 'root') {
                return true;
            }

            return in_array($grupo, [self::ROL_GERENCIA, self::ROL_GERENTE_GENERAL], true);
        }

        return $grupo === self::ROL_SOCIO;
    }
}
