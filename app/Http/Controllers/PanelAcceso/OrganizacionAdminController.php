<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
use App\Models\OrganizacionPortal;
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
            $query = Organizacion::with(['empresa', 'portal'])->orderBy('No_Organizacion');

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
            ]);

            $organizacion = Organizacion::create([
                'ID_Empresa'       => $request->id_empresa,
                'No_Organizacion'  => trim($request->no_organizacion),
                'Txt_Organizacion' => $request->txt_organizacion,
                'Nu_Estado'        => $request->input('estado', 1),
            ]);

            $this->syncPortal($organizacion, $this->requestPayload($request));

            return response()->json([
                'success' => true,
                'message' => 'Organización creada exitosamente',
                'data' => $this->serializar($organizacion->fresh(['empresa', 'portal'])),
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
            $organizacion->save();
            $this->syncPortal($organizacion, $input);

            return response()->json([
                'success' => true,
                'message' => 'Organización actualizada exitosamente',
                'data' => $this->serializar($organizacion->fresh(['empresa', 'portal'])),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('OrganizacionAdminController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar organización'], 500);
        }
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

        return [
            'id'                     => $o->getAttribute('ID_Organizacion'),
            'id_empresa'             => $o->getAttribute('ID_Empresa'),
            'empresa'                => $empresa ? $empresa->getAttribute('No_Empresa') : null,
            'no_organizacion'        => $o->getAttribute('No_Organizacion'),
            'txt_organizacion'       => $o->getAttribute('Txt_Organizacion'),
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
