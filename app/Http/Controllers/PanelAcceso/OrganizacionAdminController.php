<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use App\Models\OrganizacionPortal;
use App\Models\Pais;
use App\Models\PaisFlag;
use App\Support\Organizacion\OrganizacionPortalUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mantenedor de organizaciones (crear/editar/desactivar organizaciones,
 * p. ej. al sumar un pais nuevo). Solo lo puede usar la organizacion 1
 * (admin) -- el resto gestiona usuarios dentro de la suya, ya existente.
 */
class OrganizacionAdminController extends Controller
{
    private const ID_ORGANIZACION_ADMIN = 1;

    private function autorizarAdmin(): ?\Illuminate\Http\JsonResponse
    {
        $authUser = auth()->user();
        if (!$authUser || (int) $authUser->getAttribute('ID_Organizacion') !== self::ID_ORGANIZACION_ADMIN) {
            return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        }

        return null;
    }

    /**
     * GET /api/panel-acceso/organizaciones
     */
    public function index(Request $request)
    {
        if ($denegado = $this->autorizarAdmin()) {
            return $denegado;
        }

        try {
            $query = Organizacion::with(['empresa', 'portal', 'pais', 'paisFlag'])->orderBy('No_Organizacion');

            if ($request->filled('empresa_id')) {
                $query->where('ID_Empresa', $request->empresa_id);
            }

            $organizaciones = $query->get()->map(fn (Organizacion $o) => $this->serializar($o));

            return response()->json(['success' => true, 'data' => $organizaciones]);
        } catch (\Exception $e) {
            Log::error('OrganizacionAdminController@index: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al listar organizaciones'], 500);
        }
    }

    /**
     * POST /api/panel-acceso/organizaciones
     */
    public function store(Request $request)
    {
        if ($denegado = $this->autorizarAdmin()) {
            return $denegado;
        }

        try {
            $this->normalizarIdPaisRequest($request);
            $request->validate([
                'id_empresa'             => 'required|integer|exists:empresa,ID_Empresa',
                'no_organizacion'        => 'required|string|max:100',
                'txt_organizacion'       => 'nullable|string',
                'estado'                 => 'nullable|integer|in:0,1',
                'url_clientes'           => 'nullable|string|max:255',
                'url_excel_confirmacion' => 'nullable|string|max:255',
                'url_datos_proveedor'    => 'nullable|string|max:255',
                'nombre_publico'         => 'nullable|string|max:120',
                'drive_folder_id'        => 'nullable|string|max:128',
                'logo_url'               => 'nullable|string|max:255',
                'id_pais'                => 'nullable|integer|exists:pais,ID_Pais',
            ]);

            $organizacion = Organizacion::create([
                'ID_Empresa'       => $request->id_empresa,
                'No_Organizacion'  => trim($request->no_organizacion),
                'Txt_Organizacion' => $request->txt_organizacion,
                'Nu_Estado'        => $request->input('estado', 1),
                'id_pais'          => $request->filled('id_pais') ? (int) $request->input('id_pais') : null,
            ]);

            $this->syncPortal($organizacion, $this->requestPayload($request));

            return response()->json([
                'success' => true,
                'message' => 'Organización creada exitosamente',
                'data' => $this->serializar($organizacion->fresh(['empresa', 'portal', 'pais', 'paisFlag'])),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('OrganizacionAdminController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al crear organización'], 500);
        }
    }

    /**
     * PUT /api/panel-acceso/organizaciones/{id}
     */
    public function update(Request $request, $id)
    {
        if ($denegado = $this->autorizarAdmin()) {
            return $denegado;
        }

        try {
            $organizacion = Organizacion::find($id);
            if (!$organizacion) {
                return response()->json(['success' => false, 'message' => 'Organización no encontrada'], 404);
            }

            $this->normalizarIdPaisRequest($request);
            $request->validate([
                'id_empresa'             => 'sometimes|required|integer|exists:empresa,ID_Empresa',
                'no_organizacion'        => 'sometimes|required|string|max:100',
                'txt_organizacion'       => 'nullable|string',
                'estado'                 => 'nullable|integer|in:0,1',
                'url_clientes'           => 'nullable|string|max:255',
                'url_excel_confirmacion' => 'nullable|string|max:255',
                'url_datos_proveedor'    => 'nullable|string|max:255',
                'nombre_publico'         => 'nullable|string|max:120',
                'drive_folder_id'        => 'nullable|string|max:128',
                'logo_url'               => 'nullable|string|max:255',
                'regenerar_public_key'   => 'nullable|boolean',
                'id_pais'                => 'nullable|integer|exists:pais,ID_Pais',
            ]);

            $input = $this->requestPayload($request);

            if ($this->inputFilled($input, 'id_empresa')) {
                $organizacion->setAttribute('ID_Empresa', $input['id_empresa']);
            }
            if ($this->inputFilled($input, 'no_organizacion')) {
                $organizacion->setAttribute('No_Organizacion', trim((string) $input['no_organizacion']));
            }
            if (array_key_exists('txt_organizacion', $input)) {
                $organizacion->setAttribute('Txt_Organizacion', $input['txt_organizacion']);
            }
            if ($this->inputFilled($input, 'estado')) {
                $organizacion->setAttribute('Nu_Estado', $input['estado']);
            }
            if (array_key_exists('id_pais', $input)) {
                $idPais = $input['id_pais'];
                $organizacion->setAttribute(
                    'id_pais',
                    $idPais !== null && $idPais !== '' && (int) $idPais > 0 ? (int) $idPais : null
                );
            }
            $organizacion->save();
            $this->syncPortal($organizacion, $input);

            return response()->json([
                'success' => true,
                'message' => 'Organización actualizada exitosamente',
                'data' => $this->serializar($organizacion->fresh(['empresa', 'portal', 'pais', 'paisFlag'])),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('OrganizacionAdminController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar organización'], 500);
        }
    }

    /**
     * GET /api/panel-acceso/paises
     */
    public function paises()
    {
        if ($denegado = $this->autorizarAdmin()) {
            return $denegado;
        }

        $paises = Pais::query()->orderBy('No_Pais')->get();
        $flags = PaisFlag::query()
            ->whereIn('id_pais', $paises->map(function (Pais $pais) {
                return (int) $pais->getAttribute('ID_Pais');
            })->all())
            ->get()
            ->keyBy('id_pais');

        return response()->json([
            'success' => true,
            'data' => $paises->map(function (Pais $p) use ($flags) {
                $idPais = (int) $p->getAttribute('ID_Pais');
                $flag = $flags->get($idPais);
                $iso = $flag ? strtoupper(trim((string) $flag->getAttribute('iso2'))) : '';
                $phone = $flag ? preg_replace('/[^0-9]/', '', (string) $flag->getAttribute('phone_code')) : '';

                return [
                    'value'      => $idPais,
                    'label'      => $p->getAttribute('No_Pais'),
                    'iso2'       => $iso !== '' ? $iso : null,
                    'phone_code' => $phone !== '' ? $phone : null,
                ];
            })->values(),
        ]);
    }

    /**
     * DELETE /api/panel-acceso/organizaciones/{id}
     * No se borra fisicamente (hay contenedores/usuarios con FK restrict hacia
     * esta fila): se desactiva, igual que Empresa/Grupo en el resto del panel.
     */
    public function destroy($id)
    {
        if ($denegado = $this->autorizarAdmin()) {
            return $denegado;
        }

        try {
            $organizacion = Organizacion::find($id);
            if (!$organizacion) {
                return response()->json(['success' => false, 'message' => 'Organización no encontrada'], 404);
            }

            $organizacion->setAttribute('Nu_Estado', 0);
            $organizacion->save();

            return response()->json(['success' => true, 'message' => 'Organización desactivada exitosamente']);
        } catch (\Exception $e) {
            Log::error('OrganizacionAdminController@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al desactivar organización'], 500);
        }
    }

    private function serializar(Organizacion $o): array
    {
        $empresa = $o->relationLoaded('empresa') ? $o->getRelation('empresa') : $o->empresa;
        $portal = $o->relationLoaded('portal') ? $o->getRelation('portal') : $o->portal;

        $pais = $o->relationLoaded('pais') ? $o->getRelation('pais') : $o->pais;
        $flag = $o->relationLoaded('paisFlag') ? $o->getRelation('paisFlag') : $o->paisFlag;
        $phoneCode = $flag ? preg_replace('/[^0-9]/', '', (string) $flag->getAttribute('phone_code')) : '';

        return [
            'id'                     => $o->getAttribute('ID_Organizacion'),
            'id_empresa'             => $o->getAttribute('ID_Empresa'),
            'empresa'                => $empresa ? $empresa->getAttribute('No_Empresa') : null,
            'no_organizacion'        => $o->getAttribute('No_Organizacion'),
            'txt_organizacion'       => $o->getAttribute('Txt_Organizacion'),
            'id_pais'                => $o->getAttribute('id_pais') ? (int) $o->getAttribute('id_pais') : null,
            'pais'                   => $pais ? $pais->getAttribute('No_Pais') : null,
            'prefijo'                => $phoneCode !== '' ? $phoneCode : null,
            'estado'                 => $o->getAttribute('Nu_Estado'),
            'url_clientes'           => $portal ? $portal->url_clientes : null,
            'url_excel_confirmacion' => $portal ? $portal->url_excel_confirmacion : null,
            'url_datos_proveedor'    => $portal ? $portal->url_datos_proveedor : null,
            'nombre_publico'         => $portal ? $portal->nombre_publico : null,
            'drive_folder_id'        => $portal ? $portal->drive_folder_id : null,
            'logo_url'               => $portal ? $portal->logo_url : null,
            'public_key'             => $portal ? $portal->public_key : null,
        ];
    }

    private function normalizarIdPaisRequest(Request $request): void
    {
        $idPais = $request->input('id_pais');
        if ($idPais === '' || $idPais === null || (int) $idPais <= 0) {
            $request->merge(['id_pais' => null]);
        }
    }

    /**
     * PUT en algunos PHP/nginx no llena $_POST; mezclamos query + body + JSON.
     *
     * @return array<string, mixed>
     */
    private function requestPayload(Request $request): array
    {
        $json = $request->json() ? $request->json()->all() : [];
        if (!is_array($json)) {
            $json = [];
        }

        return array_merge($request->query->all(), $request->request->all(), $request->all(), $json);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function inputFilled(array $input, string $key): bool
    {
        if (!array_key_exists($key, $input)) {
            return false;
        }

        $value = $input[$key];

        return $value !== null && $value !== '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function syncPortal(Organizacion $organizacion, array $input): void
    {
        $orgId = (int) $organizacion->getAttribute('ID_Organizacion');
        $portal = OrganizacionPortal::firstOrCreateForOrganizacion($orgId);

        $campos = ['url_clientes', 'url_excel_confirmacion', 'url_datos_proveedor', 'nombre_publico', 'drive_folder_id', 'logo_url'];
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $input)) {
                continue;
            }
            $valor = trim((string) ($input[$campo] ?? ''));
            $portal->setAttribute($campo, $valor !== '' ? $valor : null);
        }
        if (!empty($input['regenerar_public_key'])) {
            $portal->setAttribute('public_key', (string) \Illuminate\Support\Str::uuid());
        }
        $portal->save();
        OrganizacionPortalUrls::forgetCache($orgId);
    }
}
