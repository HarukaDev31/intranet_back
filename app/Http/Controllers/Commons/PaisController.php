<?php

namespace App\Http\Controllers\Commons;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use App\Models\Pais;
use App\Models\PaisFlag;
use App\Support\Organizacion\OrganizacionPortalUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaisController extends Controller
{
    /** Catálogo de países con phone_code: casi no cambia. */
    private const CACHE_KEY = 'options:paises:phone_flags:v1';
    private const CACHE_TTL_SECONDS = 604800; // 7 días

    /**
     * @OA\Get(
     *     path="/api/options/paises",
     *     tags={"Commons"},
     *     summary="Obtener países para dropdown (con bandera y phone_code)",
     *     operationId="getPaisDropdown",
     *     @OA\Response(response=200, description="Países obtenidos exitosamente")
     * )
     */
    public function getPaisDropdown(Request $request)
    {
        $orgId = (int) $request->attributes->get(
            'organizacion_id',
            OrganizacionPortalUrls::tryOrgIdFromPublicRequest($request) ?? 0
        );

        $data = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return self::buildPaisesPayload();
        });

        $defaultPaisId = self::resolveDefaultPaisId($orgId, $data);

        return response()->json([
            'success' => true,
            'data' => $data,
            'default_pais_id' => $defaultPaisId,
        ]);
    }

    /**
     * Invalidar caché si se actualizan pais_flags / países.
     */
    public static function forgetPaisesCache()
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildPaisesPayload()
    {
        $flags = PaisFlag::query()
            ->whereNotNull('id_pais')
            ->where('id_pais', '>', 0)
            ->whereNotNull('phone_code')
            ->where('phone_code', '!=', '')
            ->get()
            ->keyBy('id_pais');

        $ids = $flags->keys()->map(function ($id) {
            return (int) $id;
        })->all();

        $paises = Pais::query()
            ->whereIn('ID_Pais', $ids)
            ->orderBy('No_Pais')
            ->get();

        return $paises->map(function (Pais $pais) use ($flags) {
            $idPais = (int) $pais->getAttribute('ID_Pais');
            $flag = $flags->get($idPais);
            $iso = $flag ? strtolower(trim((string) $flag->getAttribute('iso2'))) : '';
            $phone = $flag ? preg_replace('/[^0-9]/', '', (string) $flag->getAttribute('phone_code')) : '';
            $flagUrl = $flag ? trim((string) $flag->getAttribute('flag_url')) : '';
            if ($flagUrl === '' && $iso !== '') {
                $flagUrl = (string) PaisFlag::flagCdnUrl($iso);
            }

            return [
                'value' => $idPais,
                'label' => $pais->getAttribute('No_Pais'),
                'iso2' => $iso !== '' ? $iso : null,
                'phone_code' => $phone !== '' ? $phone : null,
                'flag_url' => $flagUrl !== '' ? $flagUrl : null,
            ];
        })->values()->all();
    }

    /**
     * @param  int  $orgId
     * @param  array<int, array<string, mixed>>  $data
     * @return int|null
     */
    private static function resolveDefaultPaisId($orgId, array $data)
    {
        $defaultPaisId = null;
        if ($orgId > 0) {
            $defaultPaisId = Organizacion::query()
                ->where('ID_Organizacion', $orgId)
                ->value('id_pais');
            $defaultPaisId = $defaultPaisId ? (int) $defaultPaisId : null;
            if ($defaultPaisId) {
                $exists = false;
                foreach ($data as $row) {
                    if ((int) ($row['value'] ?? 0) === $defaultPaisId) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $defaultPaisId = null;
                }
            }
        }

        if ($defaultPaisId) {
            return $defaultPaisId;
        }

        foreach ($data as $row) {
            if (($row['iso2'] ?? '') === 'pe' || ($row['phone_code'] ?? '') === '51') {
                return (int) $row['value'];
            }
        }

        return isset($data[0]['value']) ? (int) $data[0]['value'] : null;
    }
}
