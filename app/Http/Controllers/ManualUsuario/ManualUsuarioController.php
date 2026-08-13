<?php

namespace App\Http\Controllers\ManualUsuario;

use App\Http\Controllers\Controller;
use App\Services\ManualUsuario\ManualUsuarioCatalogService;
use App\Services\ManualUsuario\ManualUsuarioDbService;
use App\Services\ManualUsuario\ManualUsuarioPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ManualUsuarioController extends Controller
{
    public function __construct(
        private ManualUsuarioCatalogService $catalog,
        private ManualUsuarioPdfService $pdf,
        private ManualUsuarioDbService $db
    ) {
        $this->middleware('jwt.auth');
    }

    /**
     * Contexto del usuario: rol propio + (si root) lista de roles.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isRoot = $this->isRoot($user);
        $myRole = $this->resolveUserRole($user);

        $roles = [];
        if ($isRoot) {
            foreach ($this->catalog->roles() as $role) {
                $roles[] = [
                    'slug' => $role['slug'],
                    'id_grupo' => (int) ($role['id_grupo'] ?? 0),
                    'nombre' => $role['nombre'] ?? $role['slug'],
                ];
            }
        } elseif ($myRole) {
            $roles[] = [
                'slug' => $myRole['slug'],
                'id_grupo' => (int) ($myRole['id_grupo'] ?? 0),
                'nombre' => $myRole['nombre'] ?? $myRole['slug'],
            ];
        }

        $index = $this->catalog->index();

        return response()->json([
            'status' => 'success',
            'data' => [
                'title' => $index['title'] ?? 'Manual de usuario',
                'description' => $index['description'] ?? '',
                'is_root' => $isRoot,
                'can_download_global_pdf' => $isRoot,
                'my_role' => $myRole ? [
                    'slug' => $myRole['slug'],
                    'id_grupo' => (int) ($myRole['id_grupo'] ?? 0),
                    'nombre' => $myRole['nombre'] ?? $myRole['slug'],
                ] : null,
                'roles' => $roles,
            ],
        ]);
    }

    /**
     * Manual del rol del usuario autenticado.
     */
    public function me()
    {
        $user = Auth::user();
        $myRole = $this->resolveUserRole($user);

        if (!$myRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tu usuario no tiene un rol con manual asignado.',
            ], 404);
        }

        return $this->roleResponse($myRole['slug']);
    }

    /**
     * Manual de un rol (owner o root).
     */
    public function showRole(string $slug)
    {
        if (!$this->canAccessRole($slug)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para ver este manual.',
            ], 403);
        }

        return $this->roleResponse($slug);
    }

    public function pdfMe()
    {
        $user = Auth::user();
        $myRole = $this->resolveUserRole($user);

        if (!$myRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tu usuario no tiene un rol con manual asignado.',
            ], 404);
        }

        return $this->pdfRole($myRole['slug']);
    }

    public function pdfRole(string $slug)
    {
        if (!$this->canAccessRole($slug)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tienes permiso para descargar este PDF.',
            ], 403);
        }

        if (!$this->catalog->findRoleBySlug($slug)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        try {
            $binary = $this->pdf->renderRolePdf($slug);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo generar el PDF: ' . $e->getMessage(),
            ], 500);
        }

        $filename = 'manual-' . $slug . '-' . now()->format('Ymd') . '.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function pdfGlobal()
    {
        $user = Auth::user();
        if (!$this->isRoot($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo root puede descargar el PDF global.',
            ], 403);
        }

        try {
            $binary = $this->pdf->renderGlobalPdf();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo generar el PDF global: ' . $e->getMessage(),
            ], 500);
        }

        $filename = 'manual-global-' . now()->format('Ymd') . '.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($binary),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Sirve capturas / assets del manual (JWT).
     * path: screenshots/cotizador/foo.png
     */
    public function asset(Request $request, string $path)
    {
        $absolute = $this->catalog->resolveAssetAbsolutePath($path);
        if (!$absolute) {
            abort(404);
        }

        $mime = mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function roleResponse(string $slug): Response
    {
        $role = $this->catalog->findRoleBySlug($slug);
        if (!$role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rol no encontrado.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'source' => 'db',
                'role' => [
                    'slug' => $role['slug'],
                    'id_grupo' => (int) ($role['id_grupo'] ?? 0),
                    'nombre' => $role['nombre'] ?? $slug,
                    'meta' => $this->catalog->roleMeta($slug),
                ],
                'pages' => $this->db->pagesForRole($slug),
                'pdf_url' => url('/api/manual-usuario/roles/' . $slug . '/pdf'),
            ],
        ]);
    }

    private function canAccessRole(string $slug): bool
    {
        $user = Auth::user();
        if ($this->isRoot($user)) {
            return true;
        }

        $myRole = $this->resolveUserRole($user);

        return $myRole && ($myRole['slug'] ?? null) === $slug;
    }

    private function isRoot($user): bool
    {
        $rootName = (string) config('manual_usuario.root_usuario', 'root');

        return $user && (string) ($user->No_Usuario ?? '') === $rootName;
    }

    private function resolveUserRole($user): ?array
    {
        if (!$user) {
            return null;
        }

        $idGrupo = (int) ($user->ID_Grupo ?? 0);
        if ($idGrupo <= 0) {
            return null;
        }

        return $this->catalog->findRoleByGrupoId($idGrupo);
    }
}
