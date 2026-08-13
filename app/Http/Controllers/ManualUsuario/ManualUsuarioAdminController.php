<?php

namespace App\Http\Controllers\ManualUsuario;

use App\Http\Controllers\Controller;
use App\Services\ManualUsuario\ManualUsuarioAdminService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ManualUsuarioAdminController extends Controller
{
    public function __construct(
        private ManualUsuarioAdminService $admin
    ) {
        $this->middleware('jwt.auth');
    }

    public function meta()
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->admin->meta(),
        ]);
    }

    public function indexPages(Request $request)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $roleSlug = $request->query('role_slug');
        $publicado = $request->query('publicado');
        $publicadoBool = null;
        if ($publicado !== null && $publicado !== '') {
            $publicadoBool = filter_var($publicado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->admin->listPages(
                is_string($roleSlug) && $roleSlug !== '' ? $roleSlug : null,
                $publicadoBool
            ),
        ]);
    }

    public function storePage(Request $request)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'role_slug' => 'required|string|max:64',
            'id_grupo' => 'nullable|integer',
            'modulo_key' => [
                'required',
                'string',
                'max:120',
                Rule::unique('manual_paginas', 'modulo_key')->where(fn ($q) => $q->where('role_slug', $request->input('role_slug'))),
            ],
            'titulo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:500',
            'orden' => 'nullable|integer|min:1|max:9999',
            'publicado' => 'nullable|boolean',
        ]);

        try {
            $page = $this->admin->createPage($data);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $page], 201);
    }

    public function showPage(int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        try {
            return response()->json([
                'status' => 'success',
                'data' => $this->admin->getPage($id),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        }
    }

    public function updatePage(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        try {
            $current = $this->admin->getPage($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        }

        $roleSlug = $request->input('role_slug', $current['role_slug']);

        $data = $request->validate([
            'role_slug' => 'sometimes|string|max:64',
            'id_grupo' => 'nullable|integer',
            'modulo_key' => [
                'sometimes',
                'string',
                'max:120',
                Rule::unique('manual_paginas', 'modulo_key')
                    ->where(fn ($q) => $q->where('role_slug', $roleSlug))
                    ->ignore($id),
            ],
            'titulo' => 'sometimes|string|max:200',
            'descripcion' => 'nullable|string|max:500',
            'orden' => 'nullable|integer|min:1|max:9999',
            'publicado' => 'nullable|boolean',
        ]);

        try {
            $page = $this->admin->updatePage($id, $data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $page]);
    }

    public function copyPage(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'role_slug' => 'required|string|max:64',
            'titulo' => 'nullable|string|max:200',
            'modulo_key' => 'nullable|string|max:120',
            'descripcion' => 'nullable|string|max:500',
            'publicado' => 'nullable|boolean',
        ]);

        try {
            $page = $this->admin->copyPage($id, $data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $page], 201);
    }

    public function destroyPage(int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        try {
            $this->admin->deletePage($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Página eliminada.']);
    }

    public function storeBlockFromPageWidget(Request $request, int $paginaId)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'parent_id' => 'required|integer|min:1',
            'page_key' => 'required|string|max:120',
            'widget_key' => 'required|string|max:120',
            'titulo' => 'nullable|string|max:200',
            'orden' => 'nullable|integer|min:1|max:9999',
        ]);

        try {
            $page = \App\Models\ManualUsuario\ManualPagina::query()->findOrFail($paginaId);
            $resolved = $this->admin->resolvePageWidget(
                $data['page_key'],
                $data['widget_key'],
                $request->bearerToken(),
                $page->role_slug
            );
            $block = $this->admin->createBlock($paginaId, [
                'parent_id' => (int) $data['parent_id'],
                'tipo' => $resolved['tipo'],
                'titulo' => $data['titulo'] ?? $resolved['titulo'],
                'payload' => $resolved['payload'],
                'orden' => $data['orden'] ?? null,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $block], 201);
    }

    public function storeBlock(Request $request, int $paginaId)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'parent_id' => 'nullable|integer|min:1',
            'tipo' => 'nullable|string|max:40',
            'titulo' => 'nullable|string|max:200',
            'clave' => 'nullable|string|max:160',
            'payload' => 'nullable|array',
            'orden' => 'nullable|integer|min:1|max:9999',
        ]);

        try {
            $block = $this->admin->createBlock($paginaId, $data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Página no encontrada.'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $block], 201);
    }

    public function updateBlock(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'tipo' => 'sometimes|string|max:40',
            'titulo' => 'nullable|string|max:200',
            'clave' => 'nullable|string|max:160',
            'payload' => 'nullable|array',
            'orden' => 'nullable|integer|min:1|max:9999',
        ]);
        $data['bearer_token'] = $request->bearerToken();

        try {
            $block = $this->admin->updateBlock($id, $data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Bloque no encontrado.'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $block]);
    }

    public function destroyBlock(int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        try {
            $this->admin->deleteBlock($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Bloque no encontrado.'], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Bloque eliminado.']);
    }

    public function reorderBlocks(Request $request)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.orden' => 'required|integer|min:1|max:9999',
        ]);

        $this->admin->reorderBlocks($data['items']);

        return response()->json(['status' => 'success', 'message' => 'Orden actualizado.']);
    }

    public function indexMedia(Request $request)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $roleSlug = $request->query('role_slug');

        return response()->json([
            'status' => 'success',
            'data' => $this->admin->listMedia(is_string($roleSlug) && $roleSlug !== '' ? $roleSlug : null),
        ]);
    }

    public function storeMedia(Request $request)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'alt' => 'nullable|string|max:255',
            'role_slug' => 'nullable|string|max:64',
        ]);

        $user = Auth::user();
        $media = $this->admin->uploadMedia(
            $request->file('file'),
            $request->input('alt'),
            $request->input('role_slug'),
            $user ? (int) $user->ID_Usuario : null
        );

        return response()->json(['status' => 'success', 'data' => $media], 201);
    }

    public function destroyMedia(int $id)
    {
        if ($deny = $this->denyUnlessRoot()) {
            return $deny;
        }

        try {
            $this->admin->deleteMedia($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Media no encontrado.'], 404);
        }

        return response()->json(['status' => 'success', 'message' => 'Media eliminado.']);
    }

    /**
     * Sirve un archivo de manual_media.
     * Preferir redirect a CDN/S3; fallback a stream local autenticado.
     */
    public function showMedia(int $id): Response
    {
        $publicUrl = $this->admin->publicMediaUrl($id);
        if (is_string($publicUrl) && $publicUrl !== '' && !str_contains($publicUrl, '/api/manual-usuario/media/')) {
            return redirect()->away($publicUrl);
        }

        $absolute = $this->admin->absoluteMediaPath($id);
        if (!$absolute) {
            abort(404);
        }

        $media = $this->admin->findMedia($id);
        $mime = $media?->mime ?: (mime_content_type($absolute) ?: 'application/octet-stream');

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function denyUnlessRoot()
    {
        $user = Auth::user();
        $rootName = (string) config('manual_usuario.root_usuario', 'root');
        if ($user && (string) ($user->No_Usuario ?? '') === $rootName) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Solo root puede administrar el manual.',
        ], 403);
    }
}
