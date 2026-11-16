<?php

namespace App\Services\Organizacion;

use App\Exceptions\OrganizacionClienteException;
use App\Models\Grupo;
use App\Models\Organizacion;
use App\Models\OrganizacionPortal;
use App\Models\Usuario;
use App\Services\Auth\AuthMenuCacheService;
use App\Support\Organizacion\OrganizacionPaisesHabilitados;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Alta y edición de una organización como cliente de Almacén China (PRY-26-0003).
 * Lo usan Clientes China y Panel de acceso › Organizaciones.
 *
 * - La organización se crea bajo GRUPO PRO BUSINESS (o la empresa que se indique).
 * - Socio: tarifas 1 y 2 (con historial) y países para consolidar (por defecto, su país de origen).
 *   Importador: sin tarifas ni países para consolidar.
 * - Crea (o reutiliza) el rol «Socio» o «Importador» de la organización con el menú de Pagos del socio.
 * - Si viene correo, crea el usuario de la plataforma (usuario y contraseña = correo, bcrypt) en ese rol.
 * Todo es idempotente: al editar no se duplican rol, menú ni usuario.
 */
class OrganizacionClienteService
{
    /** Nombre del rol del socio: el resto del sistema compara contra Usuario::ROL_SOCIO. */
    public const GRUPO_SOCIO = Usuario::ROL_SOCIO;

    public const GRUPO_IMPORTADOR = 'Importador';

    /** url_intranet_v2 del menú «Pagos» del socio (rutas china-billing/partner/*). */
    public const MENU_PAGOS_URL = 'pagos-socio';

    public const EMPRESA_PRINCIPAL = 'GRUPO PRO BUSINESS';

    /**
     * @param  array<string, mixed>  $data  id_empresa, no_organizacion, txt_organizacion, estado, id_pais, tipo_cliente,
     *                                      pais_origen_id, paises_habilitados, tarifa_1, tarifa_2, whatsapp, contacto, correo.
     *                                      Al editar, las claves que no vienen conservan su valor.
     */
    public function guardar(array $data, ?int $organizacionId = null, ?int $userId = null): Organizacion
    {
        $org = null;
        if ($organizacionId) {
            $org = Organizacion::query()->find($organizacionId);
            if (!$org) {
                throw new OrganizacionClienteException('La organización no existe.', 404);
            }
        }
        $has = fn (string $key): bool => array_key_exists($key, $data);
        $esAdmin = $org !== null && (int) $org->getKey() === Usuario::ID_ORGANIZACION_ADMIN;

        $nombre = $has('no_organizacion')
            ? trim((string) preg_replace('/\s+/', ' ', (string) $data['no_organizacion']))
            : trim((string) $org?->getAttribute('No_Organizacion'));
        if ($nombre === '') {
            throw new OrganizacionClienteException('Ingresa el nombre de la organización.');
        }
        if (mb_strlen($nombre) > 100) {
            throw new OrganizacionClienteException('El nombre de la organización no puede tener más de 100 caracteres.');
        }
        $this->validarNombreUnico($nombre, $org ? (int) $org->getKey() : null);

        $tipo = self::normalizarTipo($has('tipo_cliente') ? $data['tipo_cliente'] : $org?->getAttribute('tipo_cliente'));
        $paisOrigen = (int) ($has('pais_origen_id') ? $data['pais_origen_id'] : $org?->getAttribute('pais_origen_id'));
        $paisOrigen = $paisOrigen > 0 ? $paisOrigen : null;
        if ($paisOrigen && !DB::table('pais')->where('ID_Pais', $paisOrigen)->exists()) {
            throw new OrganizacionClienteException('El país de origen no existe.');
        }

        $correo = mb_strtolower(trim((string) ($has('correo') ? $data['correo'] : $org?->getAttribute('correo'))));
        if ($correo !== '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100)) {
            throw new OrganizacionClienteException('El correo no es válido.');
        }
        if ($correo !== '' && !$esAdmin) {
            $this->validarCorreoDisponible($correo, $org ? (int) $org->getKey() : null);
        }

        $esSocio = $tipo === Organizacion::TIPO_SOCIO;
        $tarifa1 = $esSocio ? self::normalizarTarifa($has('tarifa_1') ? $data['tarifa_1'] : $org?->getAttribute('tarifa_1'), 'Tarifa 1') : null;
        $tarifa2 = $esSocio ? self::normalizarTarifa($has('tarifa_2') ? $data['tarifa_2'] : $org?->getAttribute('tarifa_2'), 'Tarifa 2') : null;

        $empresaId = $has('id_empresa') && (int) $data['id_empresa'] > 0
            ? (int) $data['id_empresa']
            : ($org ? (int) $org->getAttribute('ID_Empresa') : $this->empresaPrincipalId());
        if (!DB::table('empresa')->where('ID_Empresa', $empresaId)->exists()) {
            throw new OrganizacionClienteException('La empresa no existe.');
        }

        $texto = fn (string $key, int $max): ?string => mb_substr(trim((string) ($has($key) ? $data[$key] : $org?->getAttribute($key))), 0, $max) ?: null;

        $guardada = DB::transaction(function () use ($org, $data, $has, $esAdmin, $nombre, $tipo, $paisOrigen, $correo, $esSocio, $tarifa1, $tarifa2, $empresaId, $texto, $userId) {
            $nueva = $org === null;
            $org ??= new Organizacion();
            $correoAnterior = $nueva ? '' : mb_strtolower(trim((string) $org->getAttribute('correo')));
            $tarifasAntes = [$org->getAttribute('tarifa_1'), $org->getAttribute('tarifa_2')];

            $org->fill([
                'ID_Empresa' => $empresaId,
                'No_Organizacion' => $nombre,
                'tipo_cliente' => $tipo,
                'pais_origen_id' => $paisOrigen,
                'tarifa_1' => $tarifa1,
                'tarifa_2' => $tarifa2,
                'whatsapp' => $texto('whatsapp', 40),
                'contacto' => $texto('contacto', 150),
                'correo' => $correo !== '' ? $correo : null,
            ]);
            if ($nueva) {
                $org->fill(['Txt_Organizacion' => null, 'Nu_Estado' => 1, 'fecha_registro' => now()]);
            }
            if ($has('txt_organizacion')) {
                $org->setAttribute('Txt_Organizacion', trim((string) $data['txt_organizacion']) !== '' ? (string) $data['txt_organizacion'] : null);
            }
            if ($has('estado') && $data['estado'] !== null && $data['estado'] !== '') {
                $org->setAttribute('Nu_Estado', (int) $data['estado'] === 0 ? 0 : 1);
            }
            if ($has('id_pais')) {
                $org->setAttribute('id_pais', (int) $data['id_pais'] > 0 ? (int) $data['id_pais'] : null);
            }
            if (!$org->getAttribute('id_pais') && $paisOrigen) {
                // El país de la organización (prefijo de WhatsApp) toma el de origen si no tiene uno.
                $org->setAttribute('id_pais', $paisOrigen);
            }
            if (!$org->getAttribute('fecha_registro')) {
                $org->setAttribute('fecha_registro', now());
            }
            $org->save();
            $organizacionId = (int) $org->getKey();

            if ($nueva) {
                OrganizacionPortal::firstOrCreateForOrganizacion($organizacionId);
            }
            if ($esAdmin) {
                return $org;
            }

            $actuales = Schema::hasTable('organizacion_paises_habilitados')
                ? DB::table('organizacion_paises_habilitados')->where('organizacion_id', $organizacionId)->pluck('id_pais')->all()
                : [];
            $paises = self::paisesParaConsolidar($tipo, $paisOrigen, $has('paises_habilitados') ? $data['paises_habilitados'] : $actuales);
            OrganizacionPaisesHabilitados::sync($org, $paises);

            if ($esSocio && ($tarifa1 !== null || $tarifa2 !== null) && self::tarifasCambiaron($tarifasAntes[0], $tarifasAntes[1], $tarifa1, $tarifa2)) {
                DB::table('organizacion_tarifas_historial')->insert([
                    'organizacion_id' => $organizacionId,
                    'tarifa_1' => $tarifa1,
                    'tarifa_2' => $tarifa2,
                    'vigente_desde' => now(),
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $grupoId = $this->asegurarGrupo($org, $tipo);
            if ($correo !== '') {
                $this->asegurarUsuario($org, $grupoId, $correo, $correoAnterior);
            }
            $this->asignarMenuPagos($organizacionId, $grupoId);

            return $org;
        });

        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }

        return $guardada->fresh() ?? $guardada;
    }

    /**
     * Datos de cliente de la organización para los formularios.
     *
     * @return array<string, mixed>
     */
    public static function serializar(Organizacion $org): array
    {
        $paises = Schema::hasTable('organizacion_paises_habilitados')
            ? DB::table('organizacion_paises_habilitados')->where('organizacion_id', $org->getKey())->orderBy('id')->pluck('id_pais')->map(fn ($id) => (int) $id)->all()
            : [];
        $origen = $org->getAttribute('pais_origen_id');

        return [
            'tipo_cliente' => self::normalizarTipo($org->getAttribute('tipo_cliente')),
            'pais_origen_id' => $origen ? (int) $origen : null,
            'pais_origen' => $origen ? DB::table('pais')->where('ID_Pais', $origen)->value('No_Pais') : null,
            'paises_consolidar' => $paises,
            'tarifa_1' => $org->getAttribute('tarifa_1') !== null ? (float) $org->getAttribute('tarifa_1') : null,
            'tarifa_2' => $org->getAttribute('tarifa_2') !== null ? (float) $org->getAttribute('tarifa_2') : null,
            'whatsapp' => $org->getAttribute('whatsapp'),
            'contacto' => $org->getAttribute('contacto'),
            'correo' => $org->getAttribute('correo'),
        ];
    }

    /** socio | importador (acepta también partner/importer). */
    public static function normalizarTipo(mixed $tipo): string
    {
        $tipo = mb_strtolower(trim((string) $tipo));

        return in_array($tipo, [Organizacion::TIPO_IMPORTADOR, 'importer'], true) ? Organizacion::TIPO_IMPORTADOR : Organizacion::TIPO_SOCIO;
    }

    public static function nombreGrupo(string $tipo): string
    {
        return self::normalizarTipo($tipo) === Organizacion::TIPO_IMPORTADOR ? self::GRUPO_IMPORTADOR : self::GRUPO_SOCIO;
    }

    /**
     * Socio: los países que se eligieron o, si no hay, su país de origen. Importador: ninguno.
     *
     * @return array<int, int>
     */
    public static function paisesParaConsolidar(string $tipo, ?int $paisOrigen, mixed $paises): array
    {
        if (self::normalizarTipo($tipo) === Organizacion::TIPO_IMPORTADOR) {
            return [];
        }
        $ids = OrganizacionPaisesHabilitados::normalizarIds(is_array($paises) ? $paises : []);
        if (!$ids && $paisOrigen) {
            $ids = [$paisOrigen];
        }

        return array_values($ids);
    }

    /** Tarifa en USD/CBM: vacío o 0 = sin tarifa; negativa = error. */
    public static function normalizarTarifa(mixed $valor, string $nombre): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_numeric($valor)) {
            throw new OrganizacionClienteException("La {$nombre} debe ser un número.");
        }
        $valor = round((float) $valor, 2);
        if ($valor < 0) {
            throw new OrganizacionClienteException("La {$nombre} no puede ser negativa.");
        }

        return $valor > 0 ? $valor : null;
    }

    public static function tarifasCambiaron(mixed $antes1, mixed $antes2, ?float $nueva1, ?float $nueva2): bool
    {
        $igual = fn (mixed $a, ?float $b): bool => ($a === null || $a === '') ? $b === null : ($b !== null && abs((float) $a - $b) < 0.005);

        return !$igual($antes1, $nueva1) || !$igual($antes2, $nueva2);
    }

    public function empresaPrincipalId(): int
    {
        $id = DB::table('empresa')->where('No_Empresa', 'like', '%'.self::EMPRESA_PRINCIPAL.'%')->orderBy('ID_Empresa')->value('ID_Empresa');

        return $id ? (int) $id : 1;
    }

    private function validarNombreUnico(string $nombre, ?int $ignorarId): void
    {
        $existe = Organizacion::query()
            ->whereRaw('LOWER(TRIM(No_Organizacion)) = ?', [mb_strtolower($nombre)])
            ->when($ignorarId, fn ($q) => $q->where('ID_Organizacion', '!=', $ignorarId))
            ->exists();
        if ($existe) {
            throw new OrganizacionClienteException("Ya existe una organización con el nombre «{$nombre}».");
        }
    }

    /**
     * El login busca el usuario solo por No_Usuario: el correo no puede ser usuario de otra organización.
     */
    private function validarCorreoDisponible(string $correo, ?int $organizacionId): void
    {
        $usuarioOrg = DB::table('usuario')->whereRaw('LOWER(No_Usuario) = ?', [$correo])->value('ID_Organizacion');
        if ($usuarioOrg !== null && (int) $usuarioOrg !== (int) $organizacionId) {
            throw new OrganizacionClienteException("El correo {$correo} ya es usuario de otra organización. Usa otro correo.");
        }
        $otraOrg = Organizacion::query()
            ->whereRaw('LOWER(correo) = ?', [$correo])
            ->when($organizacionId, fn ($q) => $q->where('ID_Organizacion', '!=', $organizacionId))
            ->value('No_Organizacion');
        if ($otraOrg !== null) {
            throw new OrganizacionClienteException("El correo {$correo} ya está registrado en la organización «{$otraOrg}».");
        }
    }

    /**
     * Rol «Socio» o «Importador» de la organización. Si cambió el tipo, el rol del otro tipo se renombra
     * (así sus usuarios y permisos se conservan y no se duplica).
     */
    private function asegurarGrupo(Organizacion $org, string $tipo): int
    {
        $organizacionId = (int) $org->getKey();
        $nombre = self::nombreGrupo($tipo);
        $otro = $nombre === self::GRUPO_SOCIO ? self::GRUPO_IMPORTADOR : self::GRUPO_SOCIO;
        $descripcion = $nombre === self::GRUPO_SOCIO ? 'Rol del socio (Almacén China)' : 'Rol del importador (Almacén China)';
        $buscar = fn (string $grupo) => Grupo::query()
            ->where('ID_Organizacion', $organizacionId)
            ->whereRaw('LOWER(TRIM(No_Grupo)) = ?', [mb_strtolower($grupo)])
            ->orderBy('ID_Grupo')
            ->first();

        $grupo = $buscar($nombre);
        if (!$grupo) {
            $grupo = $buscar($otro);
            if ($grupo) {
                $grupo->fill(['No_Grupo' => $nombre, 'No_Grupo_Descripcion' => $descripcion])->save();
            }
        }
        if (!$grupo) {
            $grupo = Grupo::query()->create([
                'ID_Empresa' => (int) $org->getAttribute('ID_Empresa'),
                'ID_Organizacion' => $organizacionId,
                'No_Grupo' => $nombre,
                'No_Grupo_Descripcion' => $descripcion,
                'Nu_Tipo_Privilegio_Acceso' => 1,
                'Nu_Notificacion' => 0,
                'Nu_Estado' => 1,
            ]);
        }

        return (int) $grupo->getKey();
    }

    /**
     * Usuario de la plataforma con usuario = correo y contraseña = correo (bcrypt). Si ya existe en la
     * organización solo se mueve al rol; si cambió el correo, se renombra el usuario del correo anterior.
     * No se envían avisos.
     */
    private function asegurarUsuario(Organizacion $org, int $grupoId, string $correo, string $correoAnterior): void
    {
        $organizacionId = (int) $org->getKey();
        $empresaId = (int) $org->getAttribute('ID_Empresa');
        $porLogin = fn (string $login) => DB::table('usuario')->whereRaw('LOWER(No_Usuario) = ?', [$login])->orderBy('ID_Usuario')->first();

        $usuario = $porLogin($correo);
        if (!$usuario && $correoAnterior !== '' && $correoAnterior !== $correo) {
            $anterior = $porLogin($correoAnterior);
            if ($anterior && (int) $anterior->ID_Organizacion === $organizacionId) {
                DB::table('usuario')->where('ID_Usuario', $anterior->ID_Usuario)->update([
                    'No_Usuario' => $correo,
                    'Txt_Email' => $correo,
                    'No_Password' => Hash::make($correo),
                    'No_Password_Sin_Encriptar' => null,
                ]);
                $usuario = $porLogin($correo);
            }
        }

        if ($usuario) {
            if ((int) $usuario->ID_Organizacion !== $organizacionId) {
                throw new OrganizacionClienteException("El correo {$correo} ya es usuario de otra organización. Usa otro correo.");
            }
            $usuarioId = (int) $usuario->ID_Usuario;
            DB::table('usuario')->where('ID_Usuario', $usuarioId)->update(['ID_Grupo' => $grupoId]);
        } else {
            $usuarioId = (int) DB::table('usuario')->insertGetId([
                'ID_Empresa' => $empresaId,
                'ID_Organizacion' => $organizacionId,
                'ID_Grupo' => $grupoId,
                'Nu_Codigo_Pais' => '1',
                'No_Usuario' => $correo,
                'No_Nombres_Apellidos' => mb_substr(trim((string) ($org->getAttribute('contacto') ?: $org->getAttribute('No_Organizacion'))), 0, 100),
                'No_Password' => Hash::make($correo),
                'No_Password_Sin_Encriptar' => null,
                'Txt_Email' => $correo,
                'Nu_Estado' => 1,
                'Nu_Setting_Panel_Menu_Izquierdo' => 0,
            ]);
        }

        $filas = DB::table('grupo_usuario')->where('ID_Usuario', $usuarioId)->where('ID_Organizacion', $organizacionId)->orderBy('ID_Grupo_Usuario')->get();
        if ($filas->contains(fn ($fila) => (int) $fila->ID_Grupo === $grupoId)) {
            return;
        }
        if ($filas->isNotEmpty()) {
            DB::table('grupo_usuario')->where('ID_Grupo_Usuario', $filas->first()->ID_Grupo_Usuario)->update(['ID_Grupo' => $grupoId]);

            return;
        }
        DB::table('grupo_usuario')->insert([
            'ID_Empresa' => $empresaId,
            'ID_Organizacion' => $organizacionId,
            'ID_Grupo' => $grupoId,
            'ID_Usuario' => $usuarioId,
        ]);
    }

    /**
     * Menú «Pagos» del socio (y sus padres) para cada usuario del rol. Los permisos de menú cuelgan de
     * grupo_usuario: sin usuarios en el rol todavía no hay a quién asignarlo.
     */
    private function asignarMenuPagos(int $organizacionId, int $grupoId): void
    {
        $menuId = DB::table('menu')->where('url_intranet_v2', self::MENU_PAGOS_URL)
            ->when(Schema::hasColumn('menu', 'ID_Organizacion'), fn ($q) => $q->orderByRaw('CASE WHEN ID_Organizacion = ? THEN 0 ELSE 1 END', [$organizacionId]))
            ->orderBy('ID_Menu')
            ->value('ID_Menu');
        if (!$menuId) {
            return;
        }

        $menus = [];
        for ($id = (int) $menuId, $guard = 0; $id > 0 && $guard < 5; $guard++) {
            $menus[] = $id;
            $id = (int) DB::table('menu')->where('ID_Menu', $id)->value('ID_Padre');
        }

        foreach (DB::table('grupo_usuario')->where('ID_Grupo', $grupoId)->get(['ID_Grupo_Usuario', 'ID_Empresa']) as $fila) {
            foreach ($menus as $id) {
                $existe = DB::table('menu_acceso')->where('ID_Menu', $id)->where('ID_Grupo_Usuario', $fila->ID_Grupo_Usuario)->exists();
                if ($existe) {
                    continue;
                }
                DB::table('menu_acceso')->insert([
                    'ID_Empresa' => $fila->ID_Empresa ?: 1,
                    'ID_Menu' => $id,
                    'ID_Grupo_Usuario' => $fila->ID_Grupo_Usuario,
                    'Nu_Consultar' => 1,
                    'Nu_Agregar' => 1,
                    'Nu_Editar' => 1,
                    'Nu_Eliminar' => 1,
                ]);
            }
        }
    }
}
