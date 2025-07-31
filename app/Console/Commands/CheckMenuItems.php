<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckMenuItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:menu-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar los menús de Base de Datos insertados';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Verificando Menús de Base de Datos ===');

        // Verificar estructura de la tabla menu
        $columns = DB::select("SHOW COLUMNS FROM menu");
        $hasUrlIntranetV2 = false;
        
        foreach ($columns as $column) {
            if ($column->Field === 'url_intranet_v2') {
                $hasUrlIntranetV2 = true;
                break;
            }
        }

        if ($hasUrlIntranetV2) {
            $this->info('✓ La columna url_intranet_v2 existe en la tabla menu');
        } else {
            $this->error('✗ La columna url_intranet_v2 NO existe en la tabla menu');
            return 1;
        }

        // Buscar el menú padre "Base de Datos"
        $baseDatos = DB::table('menu')->where('No_Menu', 'Base de Datos')->first();
        
        if ($baseDatos) {
            $this->info("✓ Menú padre 'Base de Datos' encontrado (ID: {$baseDatos->ID_Menu})");
            $this->info("  - Orden: {$baseDatos->Nu_Orden}");
            $this->info("  - URL: {$baseDatos->No_Menu_Url}");
            $this->info("  - Controller: {$baseDatos->No_Class_Controller}");
            $this->info("  - Icono: {$baseDatos->Txt_Css_Icons}");
            $this->info("  - url_intranet_v2: " . ($baseDatos->url_intranet_v2 ?? 'NULL'));
        } else {
            $this->error("✗ Menú padre 'Base de Datos' NO encontrado");
            return 1;
        }

        // Buscar los submenús
        $productos = DB::table('menu')->where('No_Menu', 'Productos')->where('ID_Padre', $baseDatos->ID_Menu)->first();
        $regulaciones = DB::table('menu')->where('No_Menu', 'Regulaciones')->where('ID_Padre', $baseDatos->ID_Menu)->first();

        if ($productos) {
            $this->info("\n✓ Submenú 'Productos' encontrado (ID: {$productos->ID_Menu})");
            $this->info("  - Orden: {$productos->Nu_Orden}");
            $this->info("  - URL: {$productos->No_Menu_Url}");
            $this->info("  - Controller: {$productos->No_Class_Controller}");
            $this->info("  - Icono: {$productos->Txt_Css_Icons}");
            $this->info("  - url_intranet_v2: " . ($productos->url_intranet_v2 ?? 'NULL'));
        } else {
            $this->error("\n✗ Submenú 'Productos' NO encontrado");
        }

        if ($regulaciones) {
            $this->info("\n✓ Submenú 'Regulaciones' encontrado (ID: {$regulaciones->ID_Menu})");
            $this->info("  - Orden: {$regulaciones->Nu_Orden}");
            $this->info("  - URL: {$regulaciones->No_Menu_Url}");
            $this->info("  - Controller: {$regulaciones->No_Class_Controller}");
            $this->info("  - Icono: {$regulaciones->Txt_Css_Icons}");
            $this->info("  - url_intranet_v2: " . ($regulaciones->url_intranet_v2 ?? 'NULL'));
        } else {
            $this->error("\n✗ Submenú 'Regulaciones' NO encontrado");
        }

        // Mostrar estructura jerárquica
        $this->info("\n=== Estructura Jerárquica ===");
        $this->info("Base de Datos (ID: {$baseDatos->ID_Menu})");
        if ($productos) {
            $this->info("  └── Productos (ID: {$productos->ID_Menu})");
        }
        if ($regulaciones) {
            $this->info("  └── Regulaciones (ID: {$regulaciones->ID_Menu})");
        }

        $this->info("\n=== Verificación completada ===");
        return 0;
    }
}
