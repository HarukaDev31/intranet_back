<?php

use App\Services\Organizacion\OrganizacionClienteService;
use App\Support\Organizacion\SocioOplogistics;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta del socio OPLOGISTICS (Ecuador) con los menús y el rol Socio de la org 2.
 * La contraseña inicial la genera organizacion:crear. Idempotente: si ya existe solo completa sus datos.
 */
return new class extends Migration
{
    private const ORG_PLANTILLA = 2;

    private const CORREO = 'coordinacion@opl-ecuador.com';

    private const CONTACTO = 'Diego Wladimir Flores Armas';

    public function up(): void
    {
        if (!Schema::hasTable('organizacion') || !DB::table('organizacion')->where('ID_Organizacion', self::ORG_PLANTILLA)->exists()) {
            return;
        }

        DB::transaction(function () {
            $paisId = (int) DB::table('organizacion')->where('ID_Organizacion', self::ORG_PLANTILLA)->value('id_pais');
            if ($this->orgId() === 0) {
                $salida = Artisan::call('organizacion:crear', [
                    'nombre' => SocioOplogistics::NOMBRE,
                    '--pais' => (string) $paisId,
                    '--clonar-de' => (string) self::ORG_PLANTILLA,
                    '--socio-email' => self::CORREO,
                    '--socio-nombre' => SocioOplogistics::NOMBRE,
                    '--no-interaction' => true,
                ]);
                if ($salida !== 0 || $this->orgId() === 0) {
                    throw new RuntimeException('No se pudo crear el socio OPLOGISTICS: ' . Artisan::output());
                }
            }

            app(OrganizacionClienteService::class)->guardar([
                'tipo_cliente' => 'socio',
                'pais_origen_id' => $paisId,
                'contacto' => self::CONTACTO,
                'correo' => self::CORREO,
                'whatsapp' => '+593998477751',
                'txt_organizacion' => 'RUC / TAX ID: 1722058797001. Dirección: s33e, Ernesto Albán, 170708 Quito. Correo de gerencia: gerencia@opl-ecuador.com.',
            ], $this->orgId());
        });
    }

    public function down(): void
    {
        // No se borra: la organización puede tener consolidados y usuarios.
    }

    private function orgId(): int
    {
        return (int) DB::table('organizacion')->whereRaw('UPPER(TRIM(No_Organizacion)) = ?', [SocioOplogistics::NOMBRE])->value('ID_Organizacion');
    }
};
