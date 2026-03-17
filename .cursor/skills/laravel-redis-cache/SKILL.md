---
name: laravel-redis-cache
description: Cachear con Redis en Laravel (configuración, patrones Cache::remember, invalidación, tags, locks y troubleshooting). Usar cuando el usuario mencione Redis, cache, Cache::remember, optimización de performance, endpoints lentos, colas, sesiones, rate limiting o cuando se quiera reducir queries/latencia en este proyecto Laravel.
---

# Laravel + Redis Cache (proyecto)

## Objetivo

Aplicar caching con Redis de forma segura y consistente en este repositorio Laravel:
- Reducir latencia de endpoints/consultas repetidas.
- Evitar thundering herd con locks.
- Invalidar cache de forma predecible (tags/keys).
- Poder verificar rápidamente si realmente se está usando Redis.

## Quick start (checklist)

1. **Confirmar driver**: `CACHE_STORE=redis` (o `CACHE_DRIVER=redis` si el proyecto usa esa env legacy).
2. **Confirmar conexión**: `REDIS_CLIENT=phpredis` (preferido) o `predis`.
3. **Elegir estrategia**:
   - **Cache de lectura**: `Cache::remember(...)` / `rememberForever`.
   - **Invalidación**: `Cache::forget(key)` o **tags** si necesitas invalidar por grupo.
   - **Concurrencia**: `Cache::lock(...)` para cálculos caros.
4. **Medir**: registrar tiempo/queries antes y después; nunca “cachear a ciegas”.

## Configuración Redis (Laravel)

### Variables de entorno típicas

- `CACHE_STORE=redis` (Laravel 10+) o `CACHE_DRIVER=redis` (proyectos antiguos).
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` (si aplica), `REDIS_DB` (opcional).
- `REDIS_CLIENT=phpredis` recomendado.

### Verificación rápida en runtime

En un punto controlado (por ejemplo un comando artisan o un endpoint interno), comprobar:

```php
use Illuminate\Support\Facades\Cache;

Cache::put('cache:healthcheck', 'ok', 10);
$value = Cache::get('cache:healthcheck'); // debe ser "ok"
```

Si falla:
- Revisar `.env` y `config/cache.php`, `config/database.php`.
- Asegurar que Redis esté levantado y accesible desde el entorno.

## Patrones recomendados

### 1) Cache de lectura simple (por key)

Usar claves **versionadas** y **namespaced** por feature:

```php
use Illuminate\Support\Facades\Cache;

$key = "delivery:v1:cotizacion:{$idCotizacion}:resumen";

$data = Cache::remember($key, now()->addMinutes(10), function () use ($idCotizacion) {
    // consulta/armado de data caro
    return $this->buildResumen($idCotizacion);
});
```

Reglas:
- Incluir `v1` para poder “romper” cache con un bump de versión.
- Incluir parámetros relevantes (id, filtros, fechas).
- TTL corto por defecto; aumentar con evidencia.

### 2) Cache por usuario / permisos

Si la data depende del usuario/rol, incluirlo en la key:

```php
$key = "dashboard:v1:user:{$userId}:widgets";
```

### 3) Tags para invalidación por grupo (si el store lo soporta)

Redis soporta tags.

```php
use Illuminate\Support\Facades\Cache;

$tags = ["cotizacion:{$idCotizacion}", "delivery"];
$key  = "v1:summary";

$summary = Cache::tags($tags)->remember($key, now()->addMinutes(10), fn () => $this->summary($idCotizacion));

// Invalidate todo lo relacionado a una cotización:
Cache::tags(["cotizacion:{$idCotizacion}"])->flush();
```

Cuándo usar tags:
- Cuando múltiples keys dependen del mismo agregado y necesitas invalidar en lote.
Cuándo NO:
- Si el store no soporta tags (file, database).

### 4) Cache stampede protection (locks)

Para cálculos pesados con alta concurrencia:

```php
use Illuminate\Support\Facades\Cache;

$key = "report:v1:cotizacion:{$idCotizacion}";
$ttl = now()->addMinutes(10);

$lock = Cache::lock("lock:{$key}", 15);

return $lock->block(5, function () use ($key, $ttl, $idCotizacion) {
    return Cache::remember($key, $ttl, fn () => $this->buildReport($idCotizacion));
});
```

Reglas:
- `lock TTL` debe cubrir el peor caso razonable de cómputo.
- `block()` con timeout pequeño para no colgar requests.

### 5) “Cache aside” + invalidación al escribir

Si hay endpoints que **actualizan** data, invalidar en el mismo flujo (o en un listener/job):

```php
Cache::forget("delivery:v1:cotizacion:{$idCotizacion}:resumen");
Cache::tags(["cotizacion:{$idCotizacion}", "delivery"])->flush();
```

Preferir invalidación **específica** (keys) antes que `flush()` global.

## Dónde aplicar caching (heurística)

- Listados/consultas repetidas por filtros.
- Subqueries agregadas (SUM, JSON_ARRAYAGG) y joins caros.
- Datos “semi-estáticos” (catálogos, configuraciones).

Evitar cachear:
- Respuestas altamente dinámicas (estado en tiempo real) sin estrategia clara de invalidación.
- Data sensible si la key no incluye el scope correcto (usuario/tenant/permisos).

## Troubleshooting (cuando “no cachea”)

1. **Driver real**: verificar `config('cache.default')` y `Cache::getStore()` (si lo expones en un entorno seguro).
2. **Prefijo**: revisar `CACHE_PREFIX` (puede colisionar con otros entornos).
3. **Tags**: si falla `Cache::tags()`, probablemente el store no lo soporta o no es Redis.
4. **TTL**: revisar si el TTL se setea correctamente (uso de `now()->addMinutes()`).
5. **Serialización**: objetos no serializables → cachear arrays/DTOs.

## Convenciones de naming de keys (recomendado)

- Formato: `<feature>:v<ver>:<scope>:<id>:<hash-opcional>`
- Ejemplos:
  - `delivery:v1:cotizacion:123:resumen`
  - `imports:v2:user:55:filters:md5(...)`

## Salida esperada al implementar

Cuando te pidan “cachear con Redis” en este proyecto, responde y ejecuta así:

1. Identifica el punto caro (query/endpoint) y sus parámetros.
2. Define key(s) y TTL (con versión).
3. Implementa `Cache::remember` (y `Cache::lock` si aplica).
4. Añade invalidación en los flows de escritura.
5. Verifica con logs/tiempos que hay hit rate.

## Recursos internos (opcional)

Si necesitas ampliar con material específico del repo (nombres de tablas/flows), crea un `reference.md` en esta misma skill y enlázalo desde aquí.

