<?php

namespace App\Support\Organizacion;

use App\Models\OrganizacionPortal;
use Illuminate\Http\Request;

/**
 * URLs públicas (firma, inspección, formularios, reset) por organización.
 * La org nunca se toma del body: o es el padre en DB, o X-Org-Key / Host.
 */
class OrganizacionPortalUrls
{
    const ADMIN_ORG = 1;

    /** @var array<int, OrganizacionPortal|null> */
    private static $cache = [];

    /**
     * @param  mixed  $organizacionId
     * @return OrganizacionPortal|null
     */
    public static function portal($organizacionId)
    {
        $id = (int) $organizacionId;
        if ($id <= 0) {
            $id = self::ADMIN_ORG;
        }

        if (array_key_exists($id, self::$cache)) {
            return self::$cache[$id];
        }

        $portal = OrganizacionPortal::where('organizacion_id', $id)->first();
        self::$cache[$id] = $portal;

        return $portal;
    }

    /**
     * @param  mixed  $organizacionId
     */
    public static function forgetCache($organizacionId = null)
    {
        if ($organizacionId === null) {
            self::$cache = [];
            return;
        }

        unset(self::$cache[(int) $organizacionId]);
    }

    /**
     * @param  mixed  $organizacionId
     * @return string
     */
    public static function urlClientes($organizacionId)
    {
        $portal = self::portal($organizacionId);
        $url = $portal ? trim((string) $portal->url_clientes) : '';
        if ($url === '') {
            $url = trim((string) config('app.url_clientes', ''));
        }

        return rtrim($url, '/');
    }

    /**
     * @param  mixed  $organizacionId
     * @return string
     */
    public static function urlExcelConfirmacion($organizacionId)
    {
        $portal = self::portal($organizacionId);
        $url = $portal ? trim((string) $portal->url_excel_confirmacion) : '';
        if ($url === '') {
            $url = trim((string) config('app.url_excel_confirmacion', ''));
        }
        if ($url === '') {
            return self::urlClientes($organizacionId);
        }

        return rtrim($url, '/');
    }

    /**
     * @param  mixed  $organizacionId
     * @return string
     */
    public static function urlDatosProveedor($organizacionId)
    {
        $portal = self::portal($organizacionId);
        $url = $portal ? trim((string) $portal->url_datos_proveedor) : '';
        if ($url === '') {
            $url = trim((string) config('app.url_datos_proveedor', ''));
        }

        return rtrim($url, '/');
    }

    /**
     * @param  object|null  $model
     * @return int
     */
    public static function orgIdFromParent($model)
    {
        if (!$model) {
            return self::ADMIN_ORG;
        }

        $id = (int) ($model->organizacion_id ?? 0);

        return $id > 0 ? $id : self::ADMIN_ORG;
    }

    /**
     * Público: X-Org-Key → org. Nunca organizacion_id del body.
     * Sin key (portal principal / prod) → org 1. Key inválida → 403.
     *
     * @return int
     */
    public static function orgIdFromPublicRequest(Request $request)
    {
        $key = trim((string) $request->header('X-Org-Key', ''));
        if ($key !== '') {
            $portal = self::findPortalByPublicKey($key);
            if (!$portal) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'X-Org-Key inválido',
                    'code' => 'ORG_KEY_INVALID',
                ], 403));
            }

            return (int) $portal->organizacion_id;
        }

        $fromOrigin = self::tryOrgIdFromOrigin($request);

        return $fromOrigin ?: self::ADMIN_ORG;
    }

    /**
     * Resuelve org por key sin abortar (null si falta o es inválida).
     *
     * @return int|null
     */
    public static function tryOrgIdFromPublicRequest(Request $request)
    {
        $key = trim((string) $request->header('X-Org-Key', ''));
        if ($key === '') {
            return self::tryOrgIdFromOrigin($request);
        }

        $portal = self::findPortalByPublicKey($key);

        return $portal ? (int) $portal->organizacion_id : null;
    }

    /**
     * @return OrganizacionPortal|null
     */
    private static function findPortalByPublicKey(string $key)
    {
        try {
            return OrganizacionPortal::where('public_key', $key)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Front clientes: Origin/Referer (el Host de la request es el API).
     *
     * @return int|null
     */
    private static function tryOrgIdFromOrigin(Request $request)
    {
        $candidates = [
            $request->header('Origin'),
            $request->header('Referer'),
        ];
        foreach ($candidates as $url) {
            $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }
            try {
                $portales = OrganizacionPortal::query()
                    ->whereNotNull('url_clientes')
                    ->where('url_clientes', '!=', '')
                    ->get(['organizacion_id', 'url_clientes']);
            } catch (\Throwable $e) {
                return null;
            }
            foreach ($portales as $portal) {
                $portalHost = strtolower((string) parse_url((string) $portal->url_clientes, PHP_URL_HOST));
                if ($portalHost !== '' && $portalHost === $host) {
                    return (int) $portal->organizacion_id;
                }
            }
        }

        return null;
    }

    /**
     * @param  mixed  $organizacionId
     * @param  string  $uuid
     * @return string
     */
    public static function firmaAcuerdoServicio($organizacionId, $uuid)
    {
        return self::urlClientes($organizacionId) . '/firma-acuerdo-servicio/' . ltrim((string) $uuid, '/');
    }

    /**
     * @param  mixed  $organizacionId
     * @param  string  $uuid
     * @param  mixed  $idProveedor
     * @return string
     */
    public static function inspeccion($organizacionId, $uuid, $idProveedor)
    {
        return self::urlClientes($organizacionId)
            . '/inspeccion/' . ltrim((string) $uuid, '/')
            . '?id_proveedor=' . (int) $idProveedor;
    }

    /**
     * @param  mixed  $organizacionId
     * @param  mixed  $idContenedor
     * @param  mixed  $typeForm  1 lima, 0 provincia, null sin query
     * @return string
     */
    public static function formularioEntrega($organizacionId, $idContenedor, $typeForm = null)
    {
        $base = self::urlClientes($organizacionId) . '/formulario-entrega/' . (int) $idContenedor;
        if ($typeForm === 1 || $typeForm === '1') {
            return $base . '?destino=lima';
        }
        if ($typeForm === 0 || $typeForm === '0') {
            return $base . '?destino=provincia';
        }

        return $base;
    }

    /**
     * @param  mixed  $organizacionId
     * @param  mixed  $idContenedor
     * @return string
     */
    public static function formularioComprobante($organizacionId, $idContenedor)
    {
        return self::urlClientes($organizacionId) . '/formulario-comprobante/' . (int) $idContenedor;
    }

    /**
     * @param  mixed  $organizacionId
     * @return string
     */
    public static function recuperarContrasena($organizacionId)
    {
        return self::urlClientes($organizacionId) . '/recuperar-contrasena';
    }

    /**
     * @param  mixed  $organizacionId
     * @param  string  $token
     * @return string
     */
    public static function resetPassword($organizacionId, $token)
    {
        return self::urlClientes($organizacionId) . '/reset-password?token=' . $token;
    }

    /**
     * @param  mixed  $organizacionId
     * @param  bool  $incluirKey
     * @return array<string, mixed>
     */
    public static function toArray($organizacionId, $incluirKey = false)
    {
        $portal = self::portal($organizacionId);
        $payload = [
            'organizacion_id' => (int) ($organizacionId ?: self::ADMIN_ORG),
            'url_clientes' => self::urlClientes($organizacionId),
            'url_excel_confirmacion' => self::urlExcelConfirmacion($organizacionId),
            'url_datos_proveedor' => self::urlDatosProveedor($organizacionId),
            'nombre_publico' => $portal ? $portal->nombre_publico : null,
            'logo_url' => $portal ? $portal->logo_url : null,
            'drive_folder_id' => $portal ? $portal->drive_folder_id : null,
        ];
        if ($incluirKey) {
            $payload['public_key'] = $portal ? $portal->public_key : null;
        }

        return $payload;
    }
}
