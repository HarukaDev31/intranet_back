<?php

namespace App\Http\Controllers\PanelAcceso;

use App\Http\Controllers\Controller;
use App\Models\Organizacion;
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
            $query = Organizacion::with('empresa')->orderBy('No_Organizacion');

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
                'id_empresa'       => 'required|integer|exists:empresa,ID_Empresa',
                'no_organizacion'  => 'required|string|max:100',
                'txt_organizacion' => 'nullable|string',
                'estado'           => 'nullable|integer|in:0,1',
            ]);

            $organizacion = Organizacion::create([
                'ID_Empresa'       => $request->id_empresa,
                'No_Organizacion'  => trim($request->no_organizacion),
                'Txt_Organizacion' => $request->txt_organizacion,
                'Nu_Estado'        => $request->input('estado', 1),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organización creada exitosamente',
                'data' => $this->serializar($organizacion->fresh('empresa')),
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
                'id_empresa'       => 'sometimes|required|integer|exists:empresa,ID_Empresa',
                'no_organizacion'  => 'sometimes|required|string|max:100',
                'txt_organizacion' => 'nullable|string',
                'estado'           => 'nullable|integer|in:0,1',
            ]);

            if ($request->filled('id_empresa')) {
                $organizacion->setAttribute('ID_Empresa', $request->id_empresa);
            }
            if ($request->filled('no_organizacion')) {
                $organizacion->setAttribute('No_Organizacion', trim($request->no_organizacion));
            }
            if ($request->has('txt_organizacion')) {
                $organizacion->setAttribute('Txt_Organizacion', $request->txt_organizacion);
            }
            if ($request->filled('estado')) {
                $organizacion->setAttribute('Nu_Estado', $request->estado);
            }
            $organizacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Organización actualizada exitosamente',
                'data' => $this->serializar($organizacion->fresh('empresa')),
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
        $empresa = $o->getRelation('empresa');

        return [
            'id'               => $o->getAttribute('ID_Organizacion'),
            'id_empresa'       => $o->getAttribute('ID_Empresa'),
            'empresa'          => $empresa?->getAttribute('No_Empresa'),
            'no_organizacion'  => $o->getAttribute('No_Organizacion'),
            'txt_organizacion' => $o->getAttribute('Txt_Organizacion'),
            'estado'           => $o->getAttribute('Nu_Estado'),
        ];
    }
}
