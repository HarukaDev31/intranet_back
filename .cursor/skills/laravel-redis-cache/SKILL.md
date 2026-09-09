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
- **Nunca guardar modelos Eloquent ni Collections en caché** (ver sección obligatoria abajo).

### ⚠️ OBLIGATORIO: no cachear modelos Eloquent (evitar `__PHP_Incomplete_Class`)

En este proyecto **Redis serializa con PHP `serialize()`**. Si guardas un `Model`, `Collection` o `Paginator` dentro del payload:

- Al leer la caché puede aparecer `{ __PHP_Incomplete_Class_Name: "App\\Models\\..." }` en JSON.
- Métodos como `relationLoaded()`, `setRelation()` o acceso a relaciones **fallan** en runtime.
- Caso real: `CalculadoraImportacionController::show()` rompía en `ordenarProveedoresPorId()` al servir desde caché.

**Regla:** todo lo que entre/salga de caché debe ser **array escalar** (arrays, strings, ints, floats, bool, null).

#### Usar el normalizador del repo

Clase: `App\Support\Cache\CachePayloadNormalizer`

```php
use App\Support\Cache\CachePayloadNormalizer;

// Al escribir en caché (dentro del resolver del *CacheService):
$payload = CachePayloadNormalizer::resolveArray($resolver);

// Al leer caché existente: rechazar payloads viejos con objetos PHP
$cached = Cache::get($key);
if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
    return $cached;
}
$payload = CachePayloadNormalizer::resolveArray($resolver);
Cache::put($key, $payload, $ttl);
```

`resolveArray()` convierte recursivamente:
- `Model` / `Arrayable` → `toArray()`
- `Collection` → array
- `AbstractPaginator` → array con `items` + metadatos de paginación

`containsUnsafeCachedValue()` detecta:
- `__PHP_Incomplete_Class`
- instancias de `Model`, `Collection`, `Paginator`
- cualquier objeto que no sea `stdClass`

#### Patrón en servicios `*CacheService` de este repo

Referencia: `CalculadoraImportacionCacheService`, `ClienteCacheService`, `CargaConsolidadaCacheService` (HTTP cache guarda **string JSON**, no modelos).

```php
private function remember(string $key, $ttl, callable $resolver): array
{
    $cached = Cache::get($key);
    if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
        return $cached;
    }

    $payload = CachePayloadNormalizer::resolveArray($resolver);
    Cache::put($key, $payload, $ttl);

    return $payload;
}
```

Con tags:

```php
$tags = Cache::tags([self::TAG]);
$cached = $tags->get($key);
if (is_array($cached) && ! CachePayloadNormalizer::containsUnsafeCachedValue($cached)) {
    return $cached;
}
$payload = CachePayloadNormalizer::resolveArray($resolver);
$tags->put($key, $payload, $ttl);
```

#### Qué NO hacer

```php
// ❌ MAL: modelos dentro del array cacheado
return [
    'success' => true,
    'data' => $calculadora,              // Model
    'items' => $query->paginate(10),     // Paginator con Models
];

// ❌ MAL: confiar en que is_array() basta
return is_array($value) ? $value : (array) $value; // sigue conteniendo objetos anidados

// ❌ MAL: post-procesar modelos después de leer caché
$this->ordenarProveedoresPorId($payload['data']['calculadora']); // falla si vino deserializado mal
```

```php
// ✅ BIEN: normalizar antes de persistir; ordenar/transformar ANTES de cachear
$this->ordenarProveedoresPorId($calculadora);
return CachePayloadNormalizer::normalizePayloadArray([
    'success' => true,
    'data' => ['calculadora' => $calculadora, 'totales' => $totales],
]);
```

#### Invalidación al cambiar forma del payload

1. **Bump de versión** en la key (`calcimp:v2:...`, `clientes:v3:...`) — obligatorio si cambias estructura.
2. Opcional: `Cache::tags([...])->flush()` o epoch global (como `CargaConsolidadaCacheService`).
3. Tras deploy con fix de serialización: la primera lectura regenera; `php artisan cache:clear` solo si hace falta limpiar todo.

#### Checklist antes de mergear cache nuevo

- [ ] El resolver devuelve solo arrays/escalares (usar `CachePayloadNormalizer::resolveArray`).
- [ ] El `*CacheService` valida lecturas con `containsUnsafeCachedValue`.
- [ ] No hay lógica post-cache que asuma `Model` (ej. `relationLoaded`, `setRelation`).
- [ ] Key versionada (`vN`) si el payload cambió.
- [ ] Invalidación en writes/observers del módulo.

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
5. **Serialización**: objetos no serializables → cachear arrays/DTOs. Si ves `__PHP_Incomplete_Class`, el payload tiene modelos PHP serializados: usar `CachePayloadNormalizer` y bump de versión de key (ver sección obligatoria arriba).
## Convenciones de naming de keys (recomendado)

- Formato: `<feature>:v<ver>:<scope>:<id>:<hash-opcional>`
- Ejemplos:
  - `delivery:v1:cotizacion:123:resumen`
  - `imports:v2:user:55:filters:md5(...)`

## Salida esperada al implementar

Cuando te pidan “cachear con Redis” en este proyecto, responde y ejecuta así:

1. Identifica el punto caro (query/endpoint) y sus parámetros.
2. Define key(s) y TTL (con versión).
3. Implementa `*CacheService` con `CachePayloadNormalizer::resolveArray` al escribir y `containsUnsafeCachedValue` al leer.
4. Añade invalidación en los flows de escritura.
5. Verifica con logs/tiempos que hay hit rate y que la respuesta JSON no contiene `__PHP_Incomplete_Class`.
## Recursos internos (opcional)

Si necesitas ampliar con material específico del repo (nombres de tablas/flows), crea un `reference.md` en esta misma skill y enlázalo desde aquí.

