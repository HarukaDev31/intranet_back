<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UsuarioDatosFacturacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

class CrearUsuarioExternoCommand extends Command
{
    protected $signature = 'usuarios:crear-externo
                            {--name= : Nombre}
                            {--lastname= : Apellidos}
                            {--email= : Correo (único)}
                            {--whatsapp= : WhatsApp}
                            {--password= : Contraseña (mín. 6 caracteres)}
                            {--dni= : DNI (opcional)}
                            {--birth-date= : Fecha nacimiento YYYY-MM-DD}
                            {--departamento= : Nombre departamento}
                            {--provincia= : Nombre provincia}
                            {--distrito= : Nombre distrito}
                            {--no-como-entero= : Código medio (1=TikTok, 2=Facebook, 3=Instagram, 4=YouTube, 5=Familiares)}';

    protected $description = 'Crea un usuario externo (portal clientes) con menús y token JWT';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $password = (string) ($this->option('password') ?? '');

        if ($email === '' || $password === '') {
            $this->error('Requerido: --email y --password');

            return 1;
        }

        if (strlen($password) < 6) {
            $this->error('La contraseña debe tener al menos 6 caracteres');

            return 1;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $this->warn("El usuario ya existe (id {$existing->id}). Actualizando contraseña y token.");

            $existing->password = Hash::make($password);
            $token = JWTAuth::fromUser($existing);
            $existing->api_token = $token;
            $existing->save();
            $this->asignarMenusUsuario((int) $existing->id);

            $this->line("ID: {$existing->id}");
            $this->line("Email: {$existing->email}");
            $this->line("Password: {$password}");

            return 0;
        }

        $whatsapp = trim((string) ($this->option('whatsapp') ?? ''));
        if ($whatsapp !== '') {
            $dup = User::query()->where('whatsapp', $whatsapp)->exists();
            if ($dup) {
                $this->error("WhatsApp {$whatsapp} ya está registrado");

                return 1;
            }
        }

        $location = $this->resolveLocationIds(
            $this->option('departamento'),
            $this->option('provincia'),
            $this->option('distrito')
        );

        $user = User::create([
            'name' => trim((string) ($this->option('name') ?? 'Usuario')),
            'lastname' => $this->option('lastname') ? trim((string) $this->option('lastname')) : null,
            'email' => $email,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'password' => Hash::make($password),
            'dni' => $this->option('dni') ? trim((string) $this->option('dni')) : null,
            'birth_date' => $this->option('birth-date') ?: null,
            'pais_id' => DB::table('pais')->where('No_Pais', 'like', '%PERU%')->value('ID_Pais'),
            'departamento_id' => $location['departamento_id'],
            'provincia_id' => $location['provincia_id'],
            'distrito_id' => $location['distrito_id'],
            'no_como_entero' => $this->option('no-como-entero') !== null
                ? (int) $this->option('no-como-entero')
                : null,
        ]);

        $token = JWTAuth::fromUser($user);
        $user->api_token = $token;
        $user->save();

        $this->asignarMenusUsuario((int) $user->id);
        $this->ensureUsuarioDatosFacturacion($user);

        $this->info('Usuario externo creado correctamente.');
        $this->line("ID: {$user->id}");
        $this->line('Nombre: ' . trim($user->name . ' ' . ($user->lastname ?? '')));
        $this->line("Email: {$user->email}");
        $this->line("WhatsApp: {$user->whatsapp}");
        $this->line("Password: {$password}");

        return 0;
    }

    /**
     * @return array{departamento_id: int|null, provincia_id: int|null, distrito_id: int|null}
     */
    private function resolveLocationIds($departamento, $provincia, $distrito): array
    {
        $departamentoId = null;
        $provinciaId = null;
        $distritoId = null;

        if ($departamento) {
            $departamentoId = DB::table('departamento')
                ->where('No_Departamento', trim((string) $departamento))
                ->value('ID_Departamento');
        }

        if ($provincia && $departamentoId) {
            $provinciaId = DB::table('provincia')
                ->where('ID_Departamento', $departamentoId)
                ->where('No_Provincia', trim((string) $provincia))
                ->value('ID_Provincia');
        }

        if ($distrito && $provinciaId) {
            $distritoId = DB::table('distrito')
                ->where('ID_Provincia', $provinciaId)
                ->where('No_Distrito', trim((string) $distrito))
                ->value('ID_Distrito');
        }

        return [
            'departamento_id' => $departamentoId ? (int) $departamentoId : null,
            'provincia_id' => $provinciaId ? (int) $provinciaId : null,
            'distrito_id' => $distritoId ? (int) $distritoId : null,
        ];
    }

    private function asignarMenusUsuario(int $userId): void
    {
        $menus = DB::table('menu_user')->select('ID_Menu')->get();
        if ($menus->isEmpty()) {
            return;
        }

        $existingMenuIds = DB::table('menu_user_access')
            ->where('user_id', $userId)
            ->pluck('ID_Menu')
            ->all();

        $menuAccess = [];
        foreach ($menus as $menu) {
            if (in_array($menu->ID_Menu, $existingMenuIds, true)) {
                continue;
            }

            $menuAccess[] = [
                'ID_Menu' => $menu->ID_Menu,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($menuAccess !== []) {
            DB::table('menu_user_access')->insert($menuAccess);
        }
    }

    private function ensureUsuarioDatosFacturacion(User $user): void
    {
        if (!Schema::hasTable('usuario_datos_facturacion')) {
            return;
        }

        $exists = DB::table('usuario_datos_facturacion')
            ->where('id_user', $user->id)
            ->exists();

        if ($exists) {
            return;
        }

        UsuarioDatosFacturacion::create([
            'id_user' => $user->id,
            'destino' => 'Provincia',
            'nombre_completo' => trim($user->name . ' ' . ($user->lastname ?? '')),
            'dni' => $user->dni,
            'ruc' => null,
            'razon_social' => null,
            'domicilio_fiscal' => null,
            'updated_from' => 'register',
        ]);
    }
}
