<?php

namespace App\Services\WaCopiloto;

use App\Models\Usuario;
use App\Support\WhatsApp\WaJsonUtf8;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Caché Redis/file del módulo WaCopiloto con invalidación por epoch de sesión y catálogo.
 */
class WaCopilotoCacheService
{
    const VERSION = 'v1';

    /**
     * @param  int  $sessionId
     * @param  array<string, mixed>  $filters
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    public function rememberConversationList($sessionId, array $filters, callable $resolver)
    {
        $sessionId = (int) $sessionId;
        $key = $this->key(sprintf(
            'conv_list:%d:%s:%s',
            $sessionId,
            $this->sessionEpoch($sessionId),
            md5(json_encode($this->stableParams($filters)))
        ));

        return $this->remember($key, $this->ttlList(), $resolver);
    }

    /**
     * @param  int  $sessionId
     * @param  array<string, mixed>  $filters
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    public function rememberKanban($sessionId, array $filters, callable $resolver)
    {
        $sessionId = (int) $sessionId;
        $key = $this->key(sprintf(
            'kanban:%d:%s:%s',
            $sessionId,
            $this->sessionEpoch($sessionId),
            md5(json_encode($this->stableParams($filters)))
        ));

        return $this->remember($key, $this->ttlKanban(), $resolver);
    }

    /**
     * @param  int  $sessionId
     * @param  array<string, mixed>  $filters
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    public function rememberKpis($sessionId, array $filters, callable $resolver)
    {
        $sessionId = (int) $sessionId;
        $month = Carbon::now()->format('Y-m');
        $key = $this->key(sprintf(
            'kpis:%d:%s:%s:%s',
            $sessionId,
            $month,
            $this->sessionEpoch($sessionId),
            md5(json_encode($this->stableParams($filters)))
        ));

        return $this->remember($key, $this->ttlKpis(), $resolver);
    }

    /**
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    public function rememberStages(callable $resolver)
    {
        $key = $this->key('stages:' . $this->stagesEpoch());

        return $this->remember($key, $this->ttlStages(), $resolver);
    }

    /**
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    public function rememberAssignableUsers(callable $resolver)
    {
        $key = $this->key('assignable_users:' . $this->catalogEpoch('assignable'));

        return $this->remember($key, $this->ttlAssignableUsers(), $resolver);
    }

    /**
     * @param  int  $sessionId
     */
    public function invalidateSession($sessionId)
    {
        $sessionId = (int) $sessionId;
        if ($sessionId <= 0) {
            return;
        }
        $this->bumpSessionEpoch($sessionId);
    }

    public function invalidateStages()
    {
        $this->bumpStagesEpoch();
    }

    /**
     * @param  string  $phoneE164
     */
    public function invalidateClienteComercial($phoneE164)
    {
        $phone = trim((string) $phoneE164);
        if ($phone === '') {
            return;
        }
        Cache::forget('wa_copiloto_cliente_comercial_' . md5($phone));
        Cache::forget($this->key('ficha:' . md5($phone)));
    }

    /**
     * @param  string  $phoneE164
     * @param  int  $sessionId
     */
    public function invalidateAfterFichaWrite($phoneE164, $sessionId = 0)
    {
        $this->invalidateClienteComercial($phoneE164);
        if ((int) $sessionId > 0) {
            $this->invalidateSession((int) $sessionId);
        }
    }

    /**
     * @param  int[]  $userIds
     * @return array<int, string>
     */
    public function batchUserDisplayNames(array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }

        sort($userIds);
        $cacheKey = $this->key('usuario_names:' . md5(implode(',', $userIds)));
        $ttl = max(60, (int) config('meta_whatsapp_copiloto.cache_ttl_usuario_names_seconds', 300));

        return Cache::remember($cacheKey, $ttl, function () use ($userIds) {
            $map = [];
            $users = Usuario::query()
                ->whereIn('ID_Usuario', $userIds)
                ->get(['ID_Usuario', 'No_Nombres_Apellidos', 'No_Usuario']);

            foreach ($users as $u) {
                $map[(int) $u->ID_Usuario] = WaJsonUtf8::sanitizeString(
                    (string) ($u->No_Nombres_Apellidos ?: $u->No_Usuario)
                );
            }

            return $map;
        });
    }

    public function bumpSessionEpoch($sessionId)
    {
        Cache::forever($this->epochKey('session', (int) $sessionId), (string) microtime(true));
    }

    public function bumpStagesEpoch()
    {
        Cache::forever($this->epochKey('stages', 0), (string) microtime(true));
    }

    public function bumpCatalogEpoch($name)
    {
        Cache::forever($this->epochKey('cat', (string) $name), (string) microtime(true));
    }

    public function sessionEpoch($sessionId)
    {
        return $this->readEpoch($this->epochKey('session', (int) $sessionId));
    }

    public function stagesEpoch()
    {
        return $this->readEpoch($this->epochKey('stages', 0));
    }

    public function catalogEpoch($name)
    {
        return $this->readEpoch($this->epochKey('cat', (string) $name));
    }

    protected function key($suffix)
    {
        return 'wa_copiloto:' . self::VERSION . ':' . $suffix;
    }

    /**
     * @param  int|string  $scopeId
     */
    protected function epochKey($scope, $scopeId)
    {
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

    /**
     * @param  callable  $resolver
     * @return array<string, mixed>
     */
    protected function remember($key, $ttl, callable $resolver)
    {
        return Cache::remember($key, $ttl, function () use ($resolver) {
            $value = $resolver();

            return is_array($value) ? $value : (array) $value;
        });
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
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
        return Carbon::now()->addSeconds((int) config('meta_whatsapp_copiloto.cache_ttl_list_seconds', 45));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlKanban()
    {
        return Carbon::now()->addSeconds((int) config('meta_whatsapp_copiloto.cache_ttl_kanban_seconds', 45));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlKpis()
    {
        return Carbon::now()->addSeconds((int) config('meta_whatsapp_copiloto.cache_ttl_kpis_seconds', 60));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlStages()
    {
        return Carbon::now()->addSeconds((int) config('meta_whatsapp_copiloto.cache_ttl_stages_seconds', 3600));
    }

    /**
     * @return \DateTimeInterface|int
     */
    protected function ttlAssignableUsers()
    {
        return Carbon::now()->addSeconds((int) config('meta_whatsapp_copiloto.cache_ttl_assignable_users_seconds', 300));
    }
}
