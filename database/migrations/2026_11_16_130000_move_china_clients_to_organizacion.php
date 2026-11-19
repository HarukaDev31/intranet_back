<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Los clientes de Almacén China pasan a ser organizaciones (PRY-26-0003) y se elimina china_clients.
 * - Socios: se copian WhatsApp, contacto, correo y tarifas (consol → tarifa_1, consol_2 → tarifa_2) a su organización.
 * - Importadores (sin organización): se les crea una organización tipo importador bajo GRUPO PRO BUSINESS,
 *   con país de origen según su país; si ya hay una organización con el mismo nombre se usa esa.
 * - china_operations.client_id (importador facturado) pasa a organization_id y la columna se elimina.
 * El down vuelve a crear china_clients a partir de las organizaciones (aproximado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('china_clients')) {
            return;
        }

        $companyId = $this->companyId();
        $countries = DB::table('pais')->get(['ID_Pais', 'No_Pais']);
        $map = [];

        foreach (DB::table('china_clients')->orderBy('id')->get() as $client) {
            $prices = json_decode((string) ($client->prices ?? ''), true);
            $prices = is_array($prices) ? $prices : [];
            $rate1 = isset($prices['consol']) && (float) $prices['consol'] > 0 ? (float) $prices['consol'] : null;
            $rate2 = isset($prices['consol_2']) && (float) $prices['consol_2'] > 0 ? (float) $prices['consol_2'] : null;
            $isPartner = $client->type === 'partner';

            $organizationId = $client->organization_id ? (int) $client->organization_id : null;
            if ($organizationId && !DB::table('organizacion')->where('ID_Organizacion', $organizationId)->exists()) {
                $organizationId = null;
            }
            if (!$organizationId) {
                $organizationId = $this->findOrCreateOrganization($client, $isPartner, $companyId, $countries);
            }
            $map[(int) $client->id] = $organizationId;

            $org = DB::table('organizacion')->where('ID_Organizacion', $organizationId)->first();
            $update = [
                'tipo_cliente' => $isPartner ? 'socio' : 'importador',
                'whatsapp' => $org->whatsapp ?: ($client->whatsapp ?: null),
                'contacto' => $org->contacto ?: ($client->contact_name ?: null),
                'correo' => $org->correo ?: ($client->email ? mb_strtolower(trim((string) $client->email)) : null),
                'fecha_registro' => $org->fecha_registro ?: ($client->created_at ?: now()),
            ];
            if ($isPartner) {
                $update['tarifa_1'] = $rate1;
                $update['tarifa_2'] = $rate2;
            }
            DB::table('organizacion')->where('ID_Organizacion', $organizationId)->update($update);

            if ($isPartner && ($rate1 !== null || $rate2 !== null)) {
                DB::table('organizacion_tarifas_historial')->insert([
                    'organizacion_id' => $organizationId,
                    'tarifa_1' => $rate1,
                    'tarifa_2' => $rate2,
                    'vigente_desde' => $client->updated_at ?: ($client->created_at ?: now()),
                    'created_by' => $client->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasColumn('china_operations', 'client_id')) {
            foreach (DB::table('china_operations')->whereNotNull('client_id')->get(['id', 'client_id', 'organization_id']) as $operation) {
                $target = $map[(int) $operation->client_id] ?? null;
                $hasPayments = Schema::hasTable('china_payment_allocations')
                    && DB::table('china_payment_allocations')->where('operation_id', $operation->id)->exists();
                if ($target && !$hasPayments) {
                    DB::table('china_operations')->where('id', $operation->id)->update(['organization_id' => $target]);
                } else {
                    Log::warning('PRY-26-0003: operación China con client_id que no se movió a la organización del importador', [
                        'operation_id' => $operation->id, 'client_id' => $operation->client_id, 'target' => $target,
                    ]);
                }
            }
            Schema::table('china_operations', function (Blueprint $table) {
                $table->dropColumn('client_id');
            });
        }

        Schema::dropIfExists('china_clients');
    }

    public function down(): void
    {
        if (!Schema::hasColumn('china_operations', 'client_id')) {
            Schema::table('china_operations', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->after('organization_id')->comment('Cliente importador (cuando no se factura al socio)');
            });
        }
        if (Schema::hasTable('china_clients')) {
            return;
        }

        Schema::create('china_clients', function (Blueprint $table) {
            $table->id()->comment('Identificador del cliente de China');
            $table->unsignedInteger('organization_id')->nullable()->unique()->comment('Organización del socio (vacío en los importadores)');
            $table->string('type', 20)->comment('partner (socio) o importer (importador)');
            $table->string('name', 150)->comment('Nombre o empresa');
            $table->string('country', 80)->nullable()->comment('País del cliente');
            $table->string('whatsapp', 40)->nullable()->comment('WhatsApp de contacto');
            $table->string('contact_name', 150)->nullable()->comment('Persona de contacto');
            $table->string('email', 150)->nullable()->comment('Correo de contacto');
            $table->json('prices')->nullable()->comment('Tarifas del socio por servicio (consol, consol_2)');
            $table->unsignedBigInteger('created_by')->nullable()->comment('Usuario que lo registró');
            $table->timestamps();

            $table->foreign('organization_id', 'china_clients_org_fk')
                ->references('ID_Organizacion')->on('organizacion')->nullOnDelete();
            $table->index(['type', 'country'], 'china_clients_type_country_idx');
        });

        // Aproximado: solo las organizaciones que tienen datos de cliente; los importadores quedan ligados a su organización.
        $rows = DB::table('organizacion as o')
            ->leftJoin('pais as p', 'p.ID_Pais', '=', 'o.pais_origen_id')
            ->where(fn ($q) => $q->whereNotNull('o.whatsapp')->orWhereNotNull('o.tarifa_1')->orWhere('o.tipo_cliente', 'importador'))
            ->get(['o.ID_Organizacion', 'o.No_Organizacion', 'o.tipo_cliente', 'o.whatsapp', 'o.contacto', 'o.correo', 'o.tarifa_1', 'o.tarifa_2', 'o.fecha_registro', 'p.No_Pais']);
        foreach ($rows as $row) {
            $isPartner = $row->tipo_cliente !== 'importador';
            DB::table('china_clients')->insert([
                'organization_id' => (int) $row->ID_Organizacion,
                'type' => $isPartner ? 'partner' : 'importer',
                'name' => mb_substr((string) $row->No_Organizacion, 0, 150),
                'country' => $row->No_Pais,
                'whatsapp' => $row->whatsapp,
                'contact_name' => $row->contacto,
                'email' => $row->correo,
                'prices' => $isPartner ? json_encode(['consol' => $row->tarifa_1 !== null ? (float) $row->tarifa_1 : null, 'consol_2' => $row->tarifa_2 !== null ? (float) $row->tarifa_2 : null]) : null,
                'created_at' => $row->fecha_registro ?: now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function companyId(): int
    {
        $id = DB::table('empresa')->where('No_Empresa', 'like', '%GRUPO PRO BUSINESS%')->orderBy('ID_Empresa')->value('ID_Empresa');

        return $id ? (int) $id : 1;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $countries
     */
    private function findOrCreateOrganization(object $client, bool $isPartner, int $companyId, $countries): int
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $client->name) ?? '');
        $name = mb_substr($name !== '' ? $name : 'Cliente China '.$client->id, 0, 100);
        $existing = DB::table('organizacion')->whereRaw('LOWER(TRIM(No_Organizacion)) = ?', [mb_strtolower($name)])->value('ID_Organizacion');
        if ($existing) {
            return (int) $existing;
        }

        $key = fn ($value) => mb_strtolower(trim(Str::ascii((string) $value)));
        $wanted = $key($client->country ?? '');
        $country = $wanted !== '' ? $countries->first(fn ($row) => $key($row->No_Pais) === $wanted) : null;
        $countryId = $country ? (int) $country->ID_Pais : null;

        $row = [
            'ID_Empresa' => $companyId,
            'No_Organizacion' => $name,
            'Txt_Organizacion' => null,
            'Nu_Estado' => 1,
            'id_pais' => $countryId,
            'pais_origen_id' => $countryId,
            'tipo_cliente' => $isPartner ? 'socio' : 'importador',
        ];
        if (Schema::hasColumn('organizacion', 'Nu_Estado_Sistema')) {
            $row['Nu_Estado_Sistema'] = 0;
        }
        $organizationId = (int) DB::table('organizacion')->insertGetId($row);

        if ($isPartner && $countryId && Schema::hasTable('organizacion_paises_habilitados')) {
            DB::table('organizacion_paises_habilitados')->insertOrIgnore(['organizacion_id' => $organizationId, 'id_pais' => $countryId]);
        }
        if (Schema::hasTable('organizacion_portales')) {
            DB::table('organizacion_portales')->insertOrIgnore(['organizacion_id' => $organizationId, 'public_key' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $organizationId;
    }
};
