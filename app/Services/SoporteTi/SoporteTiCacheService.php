<?php

namespace App\Services\SoporteTi;

use App\Models\SoporteTi\SoporteTiChatMiembro;
use App\Models\SoporteTi\SoporteTiChatSala;
use App\Models\SoporteTi\SoporteTiSolicitud;
use Carbon\Carbon;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

/**
 * Cache de Soporte TI (driver por defecto de Laravel: file, redis, etc.).
 * Invalidación por epoch de usuario, chat y solicitud + tag global del módulo.
 */
class SoporteTiCacheService
{
    const VERSION = 'v1';

    const TAG = 'soporte-ti';

    /**
     * @param int    $viewerUserId 0 si no autenticado
     * @param bool   $isStaff
     * @param array  $filters
     * @param callable $resolver debe devolver array con solicitudes + resumen
     * @return array
     */
    public function rememberListado($viewerUserId, $isStaff, array $filters, callable $resolver)
    {
        $viewerUserId = (int) $viewerUserId;
        $epoch = $isStaff ? $this->staffEpoch() : $this->userEpoch($viewerUserId);
        $role = $isStaff ? 'staff' : 'user';
        $key = $this->key(sprintf(
            'list:%s:%d:%s:%s',
            $role,
            $viewerUserId,
            $epoch,
            md5(json_encode($this->stableParams($filters)))
        ));

        return $this->rememberTagged($key, $this->ttlList(), $resolver);
    }

    /**
     * @param int|string $solicitudId
     * @param int        $viewerUserId
     * @param callable   $resolver
     * @return array
     */
    public function rememberSolicitudShow($solicitudId, $viewerUserId, callable $resolver)
    {
        $sid = (int) $solicitudId;
        $viewerUserId = (int) $viewerUserId;
        $key = $this->key(sprintf(
            'show:%d:u:%d:%s',
            $sid,
            $viewerUserId,
            $this->solicitudEpoch($sid)
        ));

        return $this->rememberTagged($key, $this->ttlShow(), $resolver);
    }

    /**
     * @param string   $chatUuid
     * @param int      $viewerUserId
     * @param int      $limit
     * @param int|null $beforeId
     * @param callable $resolver
     * @return array
     */
    public function rememberMensajesPagina($chatUuid, $viewerUserId, $limit, $beforeId, callable $resolver)
    {
        $chatUuid = trim((string) $chatUuid);
        $viewerUserId = (int) $viewerUserId;
        $limit = (int) $limit;
        $before = $beforeId !== null ? (int) $beforeId : 0;
        $key = $this->key(sprintf(
            'msgs:%s:u:%d:l:%d:b:%d:%s',
            $chatUuid,
            $viewerUserId,
            $limit,
            $before,
            $this->chatEpoch($chatUuid)
        ));

        return $this->rememberTagged($key, $this->ttlMensajes(), $resolver);
    }

    /**
     * @param int      $solicitudId
     * @param callable $resolver
     * @return array
     */
    public function rememberHistorialEstados($solicitudId, callable $resolver)
    {
        $sid = (int) $solicitudId;
        $key = $this->key(sprintf('hist:%d:%s', $sid, $this->solicitudEpoch($sid)));

        return $this->rememberTagged($key, $this->ttlShow(), $resolver);
    }

    /**
     * @param callable $resolver
     * @return array
     */
    public function rememberEstados(callable $resolver)
    {
        $key = $this->key('estados:' . $this->catalogEpoch('estados'));

        return $this->remember($key, $this->ttlCatalog(), $resolver);
    }

    /**
     * @param string   $tipoSolicitud A|B
     * @param callable $resolver
     * @return array
     */
    public function rememberSlaHoras($tipoSolicitud, callable $resolver)
    {
        $tipo = $tipoSolicitud === 'A' ? 'A' : 'B';
        $key = $this->key('sla:' . $tipo . ':' . $this->catalogEpoch('sla'));

        return $this->remember($key, $this->ttlCatalog(), $resolver);
    }

    /**
     * @param int $userId
     */
    public function invalidateUsuario($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }
        $this->bumpUserEpoch($userId);
    }

    /**
     * @param int[] $userIds
     */
    public function invalidateUsuarios(array $userIds)
    {
        foreach ($userIds as $uid) {
            $this->invalidateUsuario($uid);
        }
    }

    public function invalidateStaff()
    {
        $this->bumpStaffEpoch();
    }

    /**
     * @param int $solicitudId
     */
    public function invalidateSolicitud($solicitudId)
    {
        $sid = (int) $solicitudId;
        if ($sid <= 0) {
            return;
        }
        $this->bumpSolicitudEpoch($sid);
    }

    /**
     * @param string $chatUuid
     */
    public function invalidateChat($chatUuid)
    {
        $chatUuid = trim((string) $chatUuid);
        if ($chatUuid === '') {
            return;
        }
        $this->bumpChatEpoch($chatUuid);
    }

    /**
     * @param string|null $tipoSolicitud A|B|null = ambos
     */
    public function invalidateSlaHoras($tipoSolicitud = null)
    {
        if ($tipoSolicitud === null || $tipoSolicitud === 'A' || $tipoSolicitud === 'B') {
            $this->bumpCatalogEpoch('sla');
        }
    }

    public function invalidateEstados()
    {
        $this->bumpCatalogEpoch('estados');
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param int[]              $extraUserIds
     */
    public function invalidateAfterSolicitudWrite(SoporteTiSolicitud $solicitud, array $extraUserIds = array())
    {
        $sid = (int) $solicitud->id;
        if ($sid > 0) {
            Cache::forget($this->key('show:' . $sid));
        }

        $this->invalidateSolicitud($sid);
        $this->invalidateStaff();

        $userIds = array_merge($this->userIdsFromSolicitud($solicitud), $extraUserIds);
        $this->invalidateUsuarios($userIds);

        $chatUuid = $this->resolveChatUuid($solicitud);
        if ($chatUuid !== '') {
            $this->invalidateChat($chatUuid);
        }

        $this->flushTag();
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @param string|null        $chatUuid
     * @param int[]              $extraUserIds
     */
    public function invalidateAfterMensajeWrite(
        SoporteTiSolicitud $solicitud,
        $chatUuid = null,
        array $extraUserIds = array()
    ) {
        $this->invalidateSolicitud((int) $solicitud->id);
        $this->invalidateStaff();

        $userIds = array_merge(
            $this->userIdsFromSolicitud($solicitud),
            $this->userIdsMiembrosSala($solicitud),
            $extraUserIds
        );
        $this->invalidateUsuarios($userIds);

        $uuid = $chatUuid !== null && $chatUuid !== ''
            ? trim((string) $chatUuid)
            : $this->resolveChatUuid($solicitud);
        if ($uuid !== '') {
            $this->invalidateChat($uuid);
        }

        $this->flushTag();
    }

    /**
     * @param string $chatUuid
     * @param int[]  $userIdsAfectados
     */
    public function invalidateAfterLecturasWrite($chatUuid, array $userIdsAfectados = array())
    {
        $this->invalidateChat($chatUuid);
        $this->invalidateUsuarios($userIdsAfectados);
        $this->flushTag();
    }

    /**
     * @param int $solicitudId
     * @return string
     */
    public function chatUuidPorSolicitudId($solicitudId)
    {
        $sala = SoporteTiChatSala::where('solicitud_id', (int) $solicitudId)->first();

        return $sala && $sala->chat_uuid ? (string) $sala->chat_uuid : '';
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return int[]
     */
    public function userIdsFromSolicitud(SoporteTiSolicitud $solicitud)
    {
        $ids = array();
        foreach (array('solicitante_user_id', 'pm_user_id', 'analista_user_id') as $col) {
            if (!empty($solicitud->{$col})) {
                $ids[] = (int) $solicitud->{$col};
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param SoporteTiSolicitud $solicitud
     * @return int[]
     */
    public function userIdsMiembrosSala(SoporteTiSolicitud $solicitud)
    {
        $salaId = null;
        if ($solicitud->relationLoaded('salaChat') && $solicitud->salaChat) {
            $salaId = (int) $solicitud->salaChat->id;
        } else {
            $sala = SoporteTiChatSala::where('solicitud_id', (int) $solicitud->id)->first();
            $salaId = $sala ? (int) $sala->id : null;
        }

        if (!$salaId) {
            return array();
        }

        return SoporteTiChatMiembro::where('sala_id', $salaId)
            ->pluck('usuario_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values()
            ->all();
    }

    public function bumpUserEpoch($userId)
    {
        Cache::forever($this->epochKey('user', (int) $userId), (string) microtime(true));
    }

    public function bumpStaffEpoch()
    {
        Cache::forever($this->epochKey('staff', 0), (string) microtime(true));
    }

    public function bumpSolicitudEpoch($solicitudId)
    {
        Cache::forever($this->epochKey('sol', (int) $solicitudId), (string) microtime(true));
    }

    public function bumpChatEpoch($chatUuid)
    {
        Cache::forever($this->epochKey('chat', (string) $chatUuid), (string) microtime(true));
    }

    public function bumpCatalogEpoch($name)
    {
        Cache::forever($this->epochKey('cat', (string) $name), (string) microtime(true));
    }

    public function userEpoch($userId)
    {
        return $this->readEpoch($this->epochKey('user', (int) $userId));
    }

    public function staffEpoch()
    {
        return $this->readEpoch($this->epochKey('staff', 0));
    }

    public function solicitudEpoch($solicitudId)
    {
        return $this->readEpoch($this->epochKey('sol', (int) $solicitudId));
    }

    public function chatEpoch($chatUuid)
    {
        return $this->readEpoch($this->epochKey('chat', (string) $chatUuid));
    }

    public function catalogEpoch($name)
    {
        return $this->readEpoch($this->epochKey('cat', (string) $name));
    }

    protected function resolveChatUuid(SoporteTiSolicitud $solicitud)
    {
        if ($solicitud->relationLoaded('salaChat') && $solicitud->salaChat && $solicitud->salaChat->chat_uuid) {
            return (string) $solicitud->salaChat->chat_uuid;
        }

        return $this->chatUuidPorSolicitudId((int) $solicitud->id);
    }

    protected function key($suffix)
    {
        return 'soporte-ti:' . self::VERSION . ':' . $suffix;
    }

    /**
     * @param int|string $scopeId
     */
    protected function epochKey($scope, $scopeId)
    {
        if ($scope === 'chat') {
            return $this->key('epoch:chat:' . (string) $scopeId);
        }
        if ($scope === 'cat') {
            return $this->key('epoch:cat:' . (string) $scopeId);
        }

        return $this->key('epoch:' . $scope . ':' . (int) $scopeId);
    }

    protected function readEpoch($key)
    {
        $epoch = Cache::get($key);
        if (!is_string($epoch) || $epoch === '') {
            return '0';
        }

        return $epoch;
    }

    protected function remember($key, $ttl, callable $resolver)
    {
        return Cache::remember($key, $ttl, function () use ($resolver) {
            $value = $resolver();

            return is_array($value) ? $value : (array) $value;
        });
    }

    protected function rememberTagged($key, $ttl, callable $resolver)
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            return Cache::tags(array(self::TAG))->remember($key, $ttl, function () use ($resolver) {
                $value = $resolver();

                return is_array($value) ? $value : (array) $value;
            });
        }

        return $this->remember($key, $ttl, $resolver);
    }

    protected function flushTag()
    {
        $store = Cache::getStore();
        if ($store instanceof TaggableStore) {
            Cache::tags(array(self::TAG))->flush();
        }
    }

    protected function stableParams(array $params)
    {
        ksort($params);
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $params[$k] = $this->stableParams($v);
            }
        }

        return $params;
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlList()
    {
        return Carbon::now()->addSeconds((int) config('soporte-ti.cache_ttl_list_seconds', 120));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlShow()
    {
        return Carbon::now()->addSeconds((int) config('soporte-ti.cache_ttl_show_seconds', 180));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlMensajes()
    {
        return Carbon::now()->addSeconds((int) config('soporte-ti.cache_ttl_mensajes_seconds', 60));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlCatalog()
    {
        return Carbon::now()->addSeconds((int) config('soporte-ti.cache_ttl_catalog_seconds', 3600));
    }
}
