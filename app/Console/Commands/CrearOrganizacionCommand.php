<?php

namespace App\Console\Commands;

use App\Helpers\CodeIgniterEncryption;
use App\Models\OrganizacionPortal;
use App\Services\Organizacion\OrganizacionMensajeriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Crea una organización socio lista para usar:
 * organizacion (+ país), países habilitados, portal, mensajería, grupo "Socio"
 * y el catálogo de menús clonado de una org plantilla (por defecto la de Ecuador).
 *
 * Los permisos de menú (menu_acceso) cuelgan de grupo_usuario, así que solo se
 * asignan si se pide crear el usuario socio (--socio-email).
 */
class CrearOrganizacionCommand extends Command
{
    protected $signature = 'organizacion:crear
                            {nombre? : Nombre de la organización (único; se pregunta si se omite)}
                            {--pais= : País (ID o nombre, ej. 4 o Ecuador)}
                            {--empresa=1 : ID de la empresa}
                            {--clonar-de= : ID de la organización de la que se copian los menús (por defecto la de Ecuador)}
                            {--socio-email= : Crea el usuario socio con este email (login global único)}
                            {--socio-nombre= : Nombres y apellidos del usuario socio}
                            {--socio-password= : Contraseña del usuario socio (si se omite se genera una)}
                            {--dry-run : Valida y muestra el plan sin escribir en la BD}';

    protected $description = 'Crea una organización socio con país, menús de Ecuador y el rol Socio (interactivo si faltan datos)';

    private const ID_ORGANIZACION_ADMIN = 1;
    private const GRUPO_SOCIO = 'Socio';
    private const GRUPO_SOCIO_DESCRIPCION = 'Rol administrador del socio';

    public function handle(): int
    {
        foreach (['organizacion', 'pais', 'empresa', 'grupo', 'grupo_usuario', 'menu', 'menu_acceso', 'usuario'] as $tabla) {
            if (!Schema::hasTable($tabla)) {
                $this->error("Falta la tabla '{$tabla}'. ¿Migraciones sin correr?");

                return 1;
            }
        }
        if (!Schema::hasColumn('menu', 'ID_Organizacion') || !Schema::hasColumn('organizacion', 'id_pais')) {
            $this->error("Faltan columnas menu.ID_Organizacion u organizacion.id_pais. Corre las migraciones.");

            return 1;
        }

        $this->completarDatosInteractivo();

        $errores = [];

        // Nombre
        $nombre = trim(preg_replace('/\s+/', ' ', (string) $this->argument('nombre')));
        if ($nombre === '' || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
        } elseif (DB::table('organizacion')->whereRaw('LOWER(TRIM(No_Organizacion)) = ?', [mb_strtolower($nombre)])->exists()) {
            $errores[] = "Ya existe una organización llamada '{$nombre}'.";
        }

        // Empresa
        $idEmpresa = (int) $this->option('empresa');
        if ($idEmpresa <= 0 || !DB::table('empresa')->where('ID_Empresa', $idEmpresa)->exists()) {
            $errores[] = "La empresa '{$this->option('empresa')}' no existe.";
        }

        // País
        $pais = null;
        if (trim((string) $this->option('pais')) === '') {
            $errores[] = 'Debes indicar el país con --pais (ID o nombre).';
        } else {
            [$pais, $errorPais] = $this->resolverPais((string) $this->option('pais'));
            if ($errorPais !== null) {
                $errores[] = $errorPais;
            }
        }

        // Org plantilla de menús
        [$orgOrigen, $errorOrigen] = $this->resolverOrgOrigen();
        $menusOrigen = collect();
        if ($errorOrigen !== null) {
            $errores[] = $errorOrigen;
        } else {
            $menusOrigen = DB::table('menu')->where('ID_Organizacion', $orgOrigen->ID_Organizacion)->orderBy('ID_Padre')->orderBy('Nu_Orden')->get();
            if ($menusOrigen->isEmpty()) {
                $errores[] = "La organización plantilla {$orgOrigen->ID_Organizacion} ({$orgOrigen->No_Organizacion}) no tiene menús para clonar.";
            }
        }

        // Usuario socio (opcional)
        $socioEmail = strtolower(trim((string) $this->option('socio-email')));
        $socioPassword = (string) $this->option('socio-password');
        $socioPasswordGenerada = false;
        if ($socioEmail !== '') {
            if (!filter_var($socioEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($socioEmail) > 100) {
                $errores[] = "El email del socio '{$socioEmail}' no es válido (máx. 100 caracteres).";
            } elseif (DB::table('usuario')->whereRaw('LOWER(No_Usuario) = ?', [$socioEmail])->exists()) {
                // El login busca por No_Usuario sin filtrar organización: debe ser único global.
                $errores[] = "Ya existe un usuario con el login '{$socioEmail}' (el login es único entre todas las organizaciones).";
            }
            if (mb_strlen((string) $this->option('socio-nombre')) > 100) {
                $errores[] = 'El nombre del socio supera 100 caracteres.';
            }
            if ($socioPassword === '') {
                $socioPassword = Str::random(12);
                $socioPasswordGenerada = true;
            } elseif (strlen($socioPassword) < 6) {
                $errores[] = 'La contraseña del socio debe tener al menos 6 caracteres.';
            }
        } elseif ($this->option('socio-nombre') || $this->option('socio-password')) {
            $errores[] = '--socio-nombre y --socio-password requieren --socio-email.';
        }

        if (mb_strlen(self::GRUPO_SOCIO) > 30 || mb_strlen(self::GRUPO_SOCIO_DESCRIPCION) > 100) {
            $errores[] = 'Nombre/descripcion del grupo Socio exceden el tamaño de la columna.';
        }

        if ($errores) {
            foreach ($errores as $e) {
                $this->error($e);
            }

            return 1;
        }

        $this->info('Plan:');
        $this->table(['Campo', 'Valor'], [
            ['Organización', $nombre],
            ['Empresa', $idEmpresa],
            ['País', "{$pais->ID_Pais} - {$pais->No_Pais}"],
            ['Menús clonados de', "{$orgOrigen->ID_Organizacion} - {$orgOrigen->No_Organizacion} ({$menusOrigen->count()} menús)"],
            ['Rol', self::GRUPO_SOCIO],
            ['Usuario socio', $socioEmail !== '' ? $socioEmail : '(no se crea)'],
        ]);

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: no se escribió nada.');

            return 0;
        }

        if ($this->input->isInteractive() && !$this->confirm('¿Crear la organización con estos datos?', true)) {
            $this->warn('Cancelado, no se escribió nada.');

            return 0;
        }

        try {
            $resultado = DB::transaction(function () use ($nombre, $idEmpresa, $pais, $orgOrigen, $menusOrigen, $socioEmail, $socioPassword) {
                $orgId = (int) DB::table('organizacion')->insertGetId([
                    'ID_Empresa' => $idEmpresa,
                    'No_Organizacion' => $nombre,
                    'Txt_Organizacion' => null,
                    'id_pais' => (int) $pais->ID_Pais,
                    'Nu_Estado' => 1,
                    'Nu_Estado_Sistema' => 0,
                ]);

                if (Schema::hasTable('organizacion_paises_habilitados')) {
                    DB::table('organizacion_paises_habilitados')->insertOrIgnore([
                        'organizacion_id' => $orgId,
                        'id_pais' => (int) $pais->ID_Pais,
                    ]);
                }

                if (Schema::hasTable('organizacion_portales')) {
                    $portal = OrganizacionPortal::firstOrCreateForOrganizacion($orgId);
                    $portal->setAttribute('nombre_publico', mb_substr($nombre, 0, 120));
                    $portal->save();
                }

                if (Schema::hasTable('organizacion_mensajeria')) {
                    app(OrganizacionMensajeriaService::class)->firstOrCreateFor($orgId);
                }

                $grupoId = (int) DB::table('grupo')->insertGetId([
                    'ID_Empresa' => $idEmpresa,
                    'ID_Organizacion' => $orgId,
                    'No_Grupo' => self::GRUPO_SOCIO,
                    'No_Grupo_Descripcion' => self::GRUPO_SOCIO_DESCRIPCION,
                    'Nu_Estado' => 1,
                    'Nu_Tipo_Privilegio_Acceso' => 1,
                    'Nu_Notificacion' => 0,
                ]);

                $mapaMenus = $this->clonarMenus($menusOrigen, $orgId);

                $usuarioId = null;
                $accesos = 0;
                if ($socioEmail !== '') {
                    [$usuarioId, $accesos] = $this->crearUsuarioSocio($orgId, $idEmpresa, $grupoId, $orgOrigen, $mapaMenus, $socioEmail, $socioPassword);
                }

                return [
                    'org_id' => $orgId,
                    'grupo_id' => $grupoId,
                    'menus' => count($mapaMenus),
                    'usuario_id' => $usuarioId,
                    'accesos' => $accesos,
                ];
            });
        } catch (\Throwable $e) {
            Log::error('organizacion:crear falló: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->error('No se creó nada (rollback): ' . $e->getMessage());

            return 1;
        }

        $this->info("Organización creada: ID {$resultado['org_id']} - {$nombre}");
        $this->line("  Grupo Socio: {$resultado['grupo_id']}");
        $this->line("  Menús clonados: {$resultado['menus']}");
        if ($resultado['usuario_id']) {
            $this->line("  Usuario socio: {$socioEmail} (ID {$resultado['usuario_id']}, {$resultado['accesos']} permisos de menú)");
            if ($socioPasswordGenerada) {
                $this->warn("  Contraseña generada (guárdala, no se vuelve a mostrar): {$socioPassword}");
            }
        } else {
            $this->line('  Sin usuario socio: los permisos de menú se asignan al crear usuarios en el grupo Socio (Panel de Acceso > Permisos).');
        }

        return 0;
    }

    /**
     * Pregunta por terminal lo que no vino por argumento/opción. Con --no-interaction
     * no pregunta y la validación posterior reporta lo que falte.
     */
    private function completarDatosInteractivo(): void
    {
        if (!$this->input->isInteractive()) {
            return;
        }

        $this->info('Crear organización socio (Ctrl+C para cancelar)');

        if (trim((string) $this->argument('nombre')) === '') {
            $nombre = $this->preguntar('Nombre de la organización', function ($valor) {
                $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));
                if (mb_strlen($valor) < 3 || mb_strlen($valor) > 100) {
                    throw new \RuntimeException('Debe tener entre 3 y 100 caracteres.');
                }
                if (DB::table('organizacion')->whereRaw('LOWER(TRIM(No_Organizacion)) = ?', [mb_strtolower($valor)])->exists()) {
                    throw new \RuntimeException("Ya existe una organización llamada '{$valor}'.");
                }

                return $valor;
            });
            $this->input->setArgument('nombre', $nombre);
        }

        if (trim((string) $this->option('pais')) === '') {
            $pais = $this->preguntar('País (ID o nombre, ej. Ecuador, Colombia)', function ($valor) {
                [, $error] = $this->resolverPais((string) $valor);
                if ($error !== null) {
                    throw new \RuntimeException($error);
                }

                return $valor;
            });
            $this->input->setOption('pais', $pais);
        }

        $empresas = DB::table('empresa')->pluck('ID_Empresa')->all();
        if (count($empresas) > 1 && !$this->input->hasParameterOption('--empresa')) {
            $this->input->setOption('empresa', $this->choice('Empresa', array_map('strval', $empresas), '1'));
        }

        if (trim((string) $this->option('clonar-de')) === '') {
            [$orgDefault] = $this->resolverOrgOrigen();
            if ($orgDefault !== null) {
                if (!$this->confirm("¿Copiar los menús de '{$orgDefault->No_Organizacion}' (ID {$orgDefault->ID_Organizacion})?", true)) {
                    $this->input->setOption('clonar-de', $this->preguntar('ID de la organización de la que copiar los menús', function ($valor) {
                        if (!ctype_digit(trim((string) $valor)) || !DB::table('organizacion')->where('ID_Organizacion', (int) $valor)->exists()) {
                            throw new \RuntimeException('Esa organización no existe.');
                        }

                        return trim((string) $valor);
                    }));
                }
            }
        }

        if (trim((string) $this->option('socio-email')) === ''
            && $this->confirm('¿Crear también el usuario socio (con permisos de menú)?', true)) {
            $this->input->setOption('socio-email', $this->preguntar('Email del socio (será su usuario de login)', function ($valor) {
                $valor = strtolower(trim((string) $valor));
                if (!filter_var($valor, FILTER_VALIDATE_EMAIL) || mb_strlen($valor) > 100) {
                    throw new \RuntimeException('Email no válido.');
                }
                if (DB::table('usuario')->whereRaw('LOWER(No_Usuario) = ?', [$valor])->exists()) {
                    throw new \RuntimeException('Ya existe un usuario con ese login (es único entre todas las organizaciones).');
                }

                return $valor;
            }));
            if (trim((string) $this->option('socio-nombre')) === '') {
                $this->input->setOption('socio-nombre', (string) $this->ask('Nombres y apellidos del socio (opcional)', ''));
            }
            if ((string) $this->option('socio-password') === '') {
                $password = $this->secret('Contraseña del socio (Enter para generar una automática)');
                $this->input->setOption('socio-password', (string) $password);
            }
        }

        $this->newLine();
    }

    /**
     * ask() con validación y reintento (el ask() de Laravel no recibe validador).
     */
    private function preguntar(string $pregunta, callable $validar)
    {
        for ($intento = 1; $intento <= 5; $intento++) {
            $respuesta = $this->ask($pregunta);
            try {
                return $validar($respuesta);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
            }
        }

        throw new \RuntimeException("Demasiados intentos inválidos en: {$pregunta}");
    }

    /**
     * @return array{0: object|null, 1: string|null}
     */
    private function resolverPais(string $input): array
    {
        $input = trim($input);
        if (ctype_digit($input)) {
            $row = DB::table('pais')->where('ID_Pais', (int) $input)->first();

            return $row ? [$row, null] : [null, "No existe un país con ID {$input}."];
        }

        $buscado = $this->normalizar($input);
        $todos = DB::table('pais')->get(['ID_Pais', 'No_Pais']);
        $coinciden = $todos->filter(fn ($p) => $this->normalizar($p->No_Pais) === $buscado)->values();

        if ($coinciden->count() === 1) {
            return [$coinciden->first(), null];
        }

        if ($coinciden->count() > 1) {
            // Hay países duplicados en el catálogo (ej. ECUADOR 4 y Ecuador 66): preferir el que ya usan las organizaciones.
            $enUso = DB::table('organizacion')->whereNotNull('id_pais')->pluck('id_pais')->map(fn ($v) => (int) $v)->all();
            $preferidos = $coinciden->filter(fn ($p) => in_array((int) $p->ID_Pais, $enUso, true))->values();
            if ($preferidos->count() === 1) {
                return [$preferidos->first(), null];
            }
            $ids = $coinciden->map(fn ($p) => "{$p->ID_Pais} ({$p->No_Pais})")->implode(', ');

            return [null, "El país '{$input}' es ambiguo en el catálogo: {$ids}. Indica el ID."];
        }

        $parecidos = $todos->filter(fn ($p) => str_contains($this->normalizar($p->No_Pais), $buscado))->take(5)
            ->map(fn ($p) => "{$p->ID_Pais} ({$p->No_Pais})")->implode(', ');

        return [null, "No se encontró el país '{$input}'." . ($parecidos !== '' ? " ¿Quisiste decir: {$parecidos}?" : '')];
    }

    /**
     * @return array{0: object|null, 1: string|null}
     */
    private function resolverOrgOrigen(): array
    {
        $opcion = trim((string) $this->option('clonar-de'));
        if ($opcion !== '') {
            if (!ctype_digit($opcion)) {
                return [null, '--clonar-de debe ser el ID numérico de una organización.'];
            }
            $org = DB::table('organizacion')->where('ID_Organizacion', (int) $opcion)->first();

            return $org ? [$org, null] : [null, "La organización plantilla {$opcion} no existe."];
        }

        $idsEcuador = DB::table('pais')->get(['ID_Pais', 'No_Pais'])
            ->filter(fn ($p) => $this->normalizar($p->No_Pais) === 'ecuador')
            ->pluck('ID_Pais')->all();
        $org = $idsEcuador
            ? DB::table('organizacion')->whereIn('id_pais', $idsEcuador)->where('ID_Organizacion', '!=', self::ID_ORGANIZACION_ADMIN)->orderBy('ID_Organizacion')->first()
            : null;

        return $org
            ? [$org, null]
            : [null, 'No se encontró la organización de Ecuador para copiar sus menús. Usa --clonar-de=<ID>.'];
    }

    /**
     * Copia el catálogo de menús a la nueva org respetando la jerarquía (padres antes que hijos).
     *
     * @return array<int, int> ID_Menu origen => ID_Menu nuevo
     */
    private function clonarMenus($menusOrigen, int $orgId): array
    {
        $porId = $menusOrigen->keyBy('ID_Menu');
        $mapa = [];
        $pendientes = $porId->keys()->all();

        while ($pendientes) {
            $avance = false;
            foreach ($pendientes as $i => $idMenu) {
                $menu = $porId[$idMenu];
                $padre = (int) $menu->ID_Padre;
                // Padre fuera del catálogo origen (o raíz): se conserva tal cual.
                $padreListo = $padre === 0 || !$porId->has($padre) || isset($mapa[$padre]);
                if (!$padreListo) {
                    continue;
                }

                $datos = (array) $menu;
                unset($datos['ID_Menu']);
                $datos['ID_Organizacion'] = $orgId;
                $datos['ID_Padre'] = $padre !== 0 && isset($mapa[$padre]) ? $mapa[$padre] : $padre;

                $mapa[$idMenu] = (int) DB::table('menu')->insertGetId($datos);
                unset($pendientes[$i]);
                $avance = true;
            }
            if (!$avance) {
                throw new \RuntimeException('Jerarquía de menús inconsistente (ciclo entre ID_Padre): ' . implode(',', $pendientes));
            }
        }

        return $mapa;
    }

    /**
     * Crea usuario + grupo_usuario y le da los permisos de menú del Socio de la org plantilla.
     *
     * @param  array<int, int>  $mapaMenus
     * @return array{0: int, 1: int} [ID_Usuario, permisos creados]
     */
    private function crearUsuarioSocio(int $orgId, int $idEmpresa, int $grupoId, object $orgOrigen, array $mapaMenus, string $email, string $password): array
    {
        $cifrado = (new CodeIgniterEncryption())->encrypt($password);
        if (!$cifrado) {
            throw new \RuntimeException('No se pudo cifrar la contraseña del socio.');
        }

        $nombreSocio = trim((string) $this->option('socio-nombre')) ?: $email;

        $usuarioId = (int) DB::table('usuario')->insertGetId([
            'ID_Empresa' => $idEmpresa,
            'ID_Organizacion' => $orgId,
            'ID_Grupo' => $grupoId,
            'Nu_Codigo_Pais' => '1',
            'No_Usuario' => $email,
            'No_Nombres_Apellidos' => mb_substr($nombreSocio, 0, 100),
            'No_Password' => $cifrado,
            'No_Password_Sin_Encriptar' => $password,
            'Txt_Email' => $email,
            'Nu_Estado' => 1,
            'Nu_Setting_Panel_Menu_Izquierdo' => 0,
        ]);

        $grupoUsuarioId = (int) DB::table('grupo_usuario')->insertGetId([
            'ID_Empresa' => $idEmpresa,
            'ID_Organizacion' => $orgId,
            'ID_Grupo' => $grupoId,
            'ID_Usuario' => $usuarioId,
        ]);

        $plantilla = $this->permisosPlantillaSocio($orgOrigen);
        $filas = [];
        if ($plantilla) {
            foreach ($plantilla as $idMenuOrigen => $flags) {
                // Menús globales (ej. Inicio) no están en el mapa: se conserva el ID.
                $idMenu = $mapaMenus[$idMenuOrigen] ?? $idMenuOrigen;
                $filas[$idMenu] = $flags;
            }
        } else {
            $this->warn('La org plantilla no tiene un Socio con permisos; se da consulta total a los menús clonados.');
            foreach ($mapaMenus as $idMenuNuevo) {
                $filas[$idMenuNuevo] = ['Nu_Consultar' => 1, 'Nu_Agregar' => 1, 'Nu_Editar' => 1, 'Nu_Eliminar' => 1];
            }
        }

        foreach ($filas as $idMenu => $flags) {
            DB::table('menu_acceso')->insert(array_merge($flags, [
                'ID_Empresa' => $idEmpresa,
                'ID_Menu' => (int) $idMenu,
                'ID_Grupo_Usuario' => $grupoUsuarioId,
            ]));
        }

        return [$usuarioId, count($filas)];
    }

    /**
     * Permisos (máximo por menú) de los usuarios del grupo Socio de la org plantilla.
     *
     * @return array<int, array<string, int>>
     */
    private function permisosPlantillaSocio(object $orgOrigen): array
    {
        $grupoIds = DB::table('grupo')
            ->where('ID_Organizacion', $orgOrigen->ID_Organizacion)
            ->where('No_Grupo', self::GRUPO_SOCIO)
            ->pluck('ID_Grupo');
        if ($grupoIds->isEmpty()) {
            return [];
        }

        $guIds = DB::table('grupo_usuario')->whereIn('ID_Grupo', $grupoIds)->pluck('ID_Grupo_Usuario');
        if ($guIds->isEmpty()) {
            return [];
        }

        $merged = [];
        foreach (DB::table('menu_acceso')->whereIn('ID_Grupo_Usuario', $guIds)->get() as $fila) {
            $id = (int) $fila->ID_Menu;
            foreach (['Nu_Consultar', 'Nu_Agregar', 'Nu_Editar', 'Nu_Eliminar'] as $col) {
                $merged[$id][$col] = max($merged[$id][$col] ?? 0, (int) $fila->{$col});
            }
        }

        return $merged;
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim(Str::ascii($texto)));
    }
}
