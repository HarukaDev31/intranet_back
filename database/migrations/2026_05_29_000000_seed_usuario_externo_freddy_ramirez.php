<?php

use App\Models\User;
use App\Models\UsuarioDatosFacturacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tymon\JWTAuth\Facades\JWTAuth;

return new class extends Migration
{
    private const EMAIL = 'Freddy23rf@gmail.com';

    private const PASSWORD = 'Freddy1996';

    public function up(): void
    {
        $user = $this->resolveUser();

        $token = JWTAuth::fromUser($user);
        $user->api_token = $token;
        $user->save();

        $this->asignarMenusUsuario($user->id);
        $this->ensureUsuarioDatosFacturacion($user);
    }

    public function down(): void
    {
        $userId = DB::table('users')->where('email', self::EMAIL)->value('id');

        if (!$userId) {
            return;
        }

        if (Schema::hasTable('usuario_datos_facturacion')) {
            DB::table('usuario_datos_facturacion')->where('id_user', $userId)->delete();
        }

        DB::table('menu_user_access')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->delete();
    }

    private function resolveUser(): User
    {
        $existing = User::where('email', self::EMAIL)->first();
        if ($existing) {
            return $existing;
        }

        $paisId = DB::table('pais')
            ->where('No_Pais', 'like', '%PERU%')
            ->value('ID_Pais');

        $departamentoId = DB::table('departamento')
            ->where('No_Departamento', 'Ucayali')
            ->value('ID_Departamento');

        $provinciaId = $departamentoId
            ? DB::table('provincia')
                ->where('ID_Departamento', $departamentoId)
                ->where('No_Provincia', 'Coronel Portillo')
                ->value('ID_Provincia')
            : null;

        $distritoId = $provinciaId
            ? DB::table('distrito')
                ->where('ID_Provincia', $provinciaId)
                ->where('No_Distrito', 'Yarinacocha')
                ->value('ID_Distrito')
            : null;

        return User::create([
            'name' => 'Freddy',
            'lastname' => 'Ramirez Flores',
            'email' => self::EMAIL,
            'whatsapp' => '977151514',
            'password' => Hash::make(self::PASSWORD),
            'dni' => '75433593',
            'birth_date' => '1996-11-17',
            'pais_id' => $paisId,
            'departamento_id' => $departamentoId,
            'provincia_id' => $provinciaId,
            'distrito_id' => $distritoId,
            'no_como_entero' => 2, // Facebook
        ]);
    }

    /**
     * Misma lógica que AuthController::asignarMenusUsuario().
     */
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

        if (!empty($menuAccess)) {
            DB::table('menu_user_access')->insert($menuAccess);
        }
    }

    /**
     * Registro inicial en usuario_datos_facturacion (boleta / provincia).
     */
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
};
