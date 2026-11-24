<?php

use App\Services\Auth\AuthMenuCacheService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CFG-26-0058: la cuenta curso queda con el cargo Cotizador y los mismos menús
 * que cotizaciones6. No copia contraseña ni datos personales.
 * Solo actúa si la cuenta sigue en el cargo comercial, para no pisar un cambio posterior.
 */
return new class extends Migration
{
    private const USUARIO_ORIGEN = 'cotizaciones6@probusiness.pe';

    private const USUARIO_DESTINO = 'curso@probusiness.pe';

    public function up(): void
    {
        $origen = $this->usuario(self::USUARIO_ORIGEN);
        $destino = $this->usuario(self::USUARIO_DESTINO);
        if ($origen === null || $destino === null) {
            return;
        }

        if ((int) $origen->ID_Empresa !== (int) $destino->ID_Empresa
            || (int) $origen->ID_Organizacion !== (int) $destino->ID_Organizacion) {
            return;
        }

        $grupoOrigen = $this->nombreGrupo((int) $origen->ID_Grupo);
        $grupoDestino = $this->nombreGrupo((int) $destino->ID_Grupo);
        if ($grupoOrigen !== 'Cotizador' || $grupoDestino !== 'comercial') {
            return;
        }

        $grupoUsuarioOrigen = DB::table('grupo_usuario')
            ->where('ID_Usuario', (int) $origen->ID_Usuario)
            ->where('ID_Grupo', (int) $origen->ID_Grupo)
            ->first();
        $grupoUsuarioDestino = DB::table('grupo_usuario')
            ->where('ID_Usuario', (int) $destino->ID_Usuario)
            ->where('ID_Grupo', (int) $destino->ID_Grupo)
            ->first();
        if ($grupoUsuarioOrigen === null || $grupoUsuarioDestino === null) {
            return;
        }

        $origenGrupoId = (int) $origen->ID_Grupo;
        $desde = (int) $grupoUsuarioOrigen->ID_Grupo_Usuario;
        $hacia = (int) $grupoUsuarioDestino->ID_Grupo_Usuario;
        $destinoId = (int) $destino->ID_Usuario;

        DB::transaction(function () use ($origenGrupoId, $desde, $hacia, $destinoId): void {
            DB::table('usuario')
                ->where('ID_Usuario', $destinoId)
                ->update(['ID_Grupo' => $origenGrupoId]);

            DB::table('grupo_usuario')
                ->where('ID_Grupo_Usuario', $hacia)
                ->update(['ID_Grupo' => $origenGrupoId]);

            $this->copiarMenus($desde, $hacia);
        });

        $this->invalidarMenus();
    }

    public function down(): void
    {
        $destino = $this->usuario(self::USUARIO_DESTINO);
        if ($destino === null || $this->nombreGrupo((int) $destino->ID_Grupo) !== 'Cotizador') {
            return;
        }

        $comercialId = DB::table('grupo')
            ->where('No_Grupo', 'comercial')
            ->where('ID_Empresa', (int) $destino->ID_Empresa)
            ->where('ID_Organizacion', (int) $destino->ID_Organizacion)
            ->value('ID_Grupo');
        if (!$comercialId) {
            return;
        }

        $plantilla = DB::table('grupo_usuario')
            ->where('ID_Grupo', (int) $comercialId)
            ->where('ID_Usuario', '!=', (int) $destino->ID_Usuario)
            ->value('ID_Grupo_Usuario');

        $grupoUsuarioDestino = DB::table('grupo_usuario')
            ->where('ID_Usuario', (int) $destino->ID_Usuario)
            ->where('ID_Grupo', (int) $destino->ID_Grupo)
            ->first();

        DB::table('usuario')
            ->where('ID_Usuario', (int) $destino->ID_Usuario)
            ->update(['ID_Grupo' => (int) $comercialId]);

        if ($grupoUsuarioDestino !== null) {
            DB::table('grupo_usuario')
                ->where('ID_Grupo_Usuario', (int) $grupoUsuarioDestino->ID_Grupo_Usuario)
                ->update(['ID_Grupo' => (int) $comercialId]);

            if ($plantilla) {
                $this->copiarMenus((int) $plantilla, (int) $grupoUsuarioDestino->ID_Grupo_Usuario);
            }
        }

        $this->invalidarMenus();
    }

    private function usuario(string $noUsuario): ?object
    {
        $row = DB::table('usuario')->where('No_Usuario', $noUsuario)->first();

        return $row ?: null;
    }

    private function nombreGrupo(int $idGrupo): string
    {
        return (string) (DB::table('grupo')->where('ID_Grupo', $idGrupo)->value('No_Grupo') ?? '');
    }

    private function copiarMenus(int $desdeGrupoUsuarioId, int $haciaGrupoUsuarioId): void
    {
        $menus = DB::table('menu_acceso')
            ->where('ID_Grupo_Usuario', $desdeGrupoUsuarioId)
            ->get(['ID_Empresa', 'ID_Menu', 'Nu_Consultar', 'Nu_Agregar', 'Nu_Editar', 'Nu_Eliminar']);

        DB::table('menu_acceso')->where('ID_Grupo_Usuario', $haciaGrupoUsuarioId)->delete();

        foreach ($menus as $menu) {
            DB::table('menu_acceso')->insert([
                'ID_Empresa' => (int) $menu->ID_Empresa,
                'ID_Menu' => (int) $menu->ID_Menu,
                'ID_Grupo_Usuario' => $haciaGrupoUsuarioId,
                'Nu_Consultar' => (int) $menu->Nu_Consultar,
                'Nu_Agregar' => (int) $menu->Nu_Agregar,
                'Nu_Editar' => (int) $menu->Nu_Editar,
                'Nu_Eliminar' => (int) $menu->Nu_Eliminar,
            ]);
        }
    }

    private function invalidarMenus(): void
    {
        if (class_exists(AuthMenuCacheService::class)) {
            app(AuthMenuCacheService::class)->invalidateAll();
        }
    }
};
