<?php

namespace App\Services\Copiloto;

use App\Models\BaseDatos\ProductoImportadoExcel;
use App\Models\BaseDatos\ProductoRegulacionAntidumping;
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecial;
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;
use App\Models\BaseDatos\ProductoRegulacionPermiso;
use App\Models\BaseDatos\Regulaciones\ProductoRubro;
use App\Support\WhatsApp\WaJsonUtf8;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Búsqueda en base de datos de productos y regulaciones para Copiloto (UI + contexto IA).
 */
class CopilotoAduanaKnowledgeService
{
  /**
   * @param  string  $queryText
   * @param  int  $limit
   * @return array<string, mixed>
   */
  public function searchForAdvisor($queryText, $limit = 18)
  {
    $terms = $this->extractTerms($queryText);
    if (empty($terms)) {
      return [
        'terms' => [],
        'items' => [],
        'knowledge_block' => '',
      ];
    }

    $limit = max(1, min(30, (int) $limit));
    $ttl = max(300, (int) config('meta_whatsapp_copiloto.analysis_aduana_context_cache_ttl', 1800));
    $cacheKey = 'wa_copiloto_aduana_search_v1_' . md5(implode('|', $terms) . '|' . $limit);

    return Cache::remember($cacheKey, $ttl, function () use ($terms, $limit) {
      $items = [];
      $this->safeSearch('productos', function () use ($terms, &$items) {
        $this->searchProductosImportados($terms, $items);
      });
      $this->safeSearch('rubros', function () use ($terms, &$items) {
        $this->searchRubros($terms, $items);
      });
      $this->safeSearch('permisos', function () use ($terms, &$items) {
        $this->searchPermisos($terms, $items);
      });
      $this->safeSearch('antidumping', function () use ($terms, &$items) {
        $this->searchAntidumping($terms, $items);
      });
      $this->safeSearch('etiquetado', function () use ($terms, &$items) {
        $this->searchEtiquetado($terms, $items);
      });
      $this->safeSearch('documentos_especiales', function () use ($terms, &$items) {
        $this->searchDocumentosEspeciales($terms, $items);
      });

      $items = $this->dedupeItems($items);
      $items = array_slice($items, 0, $limit);

      return [
        'terms' => $terms,
        'items' => $items,
        'knowledge_block' => $this->formatKnowledgeBlock($items),
      ];
    });
  }

  /**
   * Bloque compacto para el prompt de Gemini a partir del hilo y último mensaje.
   *
   * @param  string  $latestBody
   * @param  string  $threadText
   * @return string
   */
  public function buildKnowledgeBlockForMessage($latestBody, $threadText = '')
  {
    if (!config('meta_whatsapp_copiloto.analysis_aduana_context_enabled', true)) {
      return '';
    }

    $combined = trim($latestBody . "\n" . $threadText);
    $result = $this->searchForAdvisor(
      $combined,
      (int) config('meta_whatsapp_copiloto.analysis_aduana_context_max_items', 12)
    );

    $block = isset($result['knowledge_block']) ? (string) $result['knowledge_block'] : '';
    $maxChars = max(800, (int) config('meta_whatsapp_copiloto.analysis_aduana_context_max_chars', 4500));
    if (mb_strlen($block) > $maxChars) {
      $block = mb_substr($block, 0, $maxChars - 1) . '…';
    }

    return $block;
  }

  /**
   * @param  string  $text
   * @return array<int, string>
   */
  protected function extractTerms($text)
  {
    $text = mb_strtolower(WaJsonUtf8::sanitizeString((string) $text));
    if ($text === '') {
      return [];
    }

    $stopwords = [
      'hola', 'buenos', 'dias', 'tardes', 'noches', 'gracias', 'por', 'para', 'que', 'con',
      'una', 'uno', 'unos', 'unas', 'los', 'las', 'del', 'de', 'la', 'el', 'en', 'es', 'son',
      'como', 'esta', 'este', 'esto', 'ese', 'esa', 'eso', 'muy', 'mas', 'menos', 'solo',
      'quiero', 'necesito', 'puede', 'pueden', 'hola', 'ola', 'si', 'no', 'ya', 'hay', 'les',
      'nos', 'les', 'sus', 'mis', 'tus', 'the', 'and', 'you', 'your', 'our', 'pero', 'porque',
      'cuando', 'donde', 'cual', 'cuanto', 'cuanta', 'cuantos', 'cuantas', 'sobre', 'algo',
      'bien', 'todo', 'toda', 'todos', 'todas', 'ese', 'esa', 'aqui', 'ahi', 'asi', 'tan',
    ];

    preg_match_all('/\p{L}[\p{L}\d]{2,}/u', $text, $matches);
    $terms = [];
    foreach ($matches[0] as $word) {
      $word = trim($word);
      if ($word === '' || in_array($word, $stopwords, true)) {
        continue;
      }
      if (mb_strlen($word) < 3) {
        continue;
      }
      $terms[] = $word;
      if (count($terms) >= 8) {
        break;
      }
    }

    return array_values(array_unique($terms));
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchProductosImportados(array $terms, array &$items)
  {
    $query = ProductoImportadoExcel::query()->whereNull('deleted_at');
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $q->orWhere('nombre_comercial', 'like', $like)
          ->orWhere('rubro', 'like', $like)
          ->orWhere('subpartida', 'like', $like)
          ->orWhere('tipo_producto', 'like', $like)
          ->orWhere('observaciones', 'like', $like);
      }
    });

    foreach ($query->orderByDesc('id')->limit(8)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'producto',
        'titulo' => (string) $row->nombre_comercial,
        'rubro' => $row->rubro ? (string) $row->rubro : null,
        'subpartida' => $row->subpartida ? (string) $row->subpartida : null,
        'restriccion' => $row->tipo ? (string) $row->tipo : null,
        'detalle' => $this->joinParts([
          $row->subpartida ? 'Partida ' . $row->subpartida : null,
          $row->arancel_sunat ? 'Arancel ' . $row->arancel_sunat : null,
          $row->antidumping ? 'Antidumping: ' . $row->antidumping : null,
          $row->etiquetado ? 'Etiquetado: ' . $row->etiquetado : null,
          $row->doc_especial ? 'Doc. especial: ' . $row->doc_especial : null,
        ]),
        'observaciones' => $row->observaciones ? (string) $row->observaciones : null,
      ];
    }
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchRubros(array $terms, array &$items)
  {
    $query = ProductoRubro::query();
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $q->orWhere('nombre', 'like', '%' . $term . '%');
      }
    });

    foreach ($query->orderBy('nombre')->limit(6)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'rubro',
        'titulo' => (string) $row->nombre,
        'rubro' => (string) $row->nombre,
        'restriccion' => $row->tipo ? (string) $row->tipo : null,
        'detalle' => 'Rubro regulado en base de datos',
        'observaciones' => null,
      ];
    }
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchPermisos(array $terms, array &$items)
  {
    // bd_productos_regulaciones_permiso no tiene id_rubro; se vincula por entidad reguladora.
    $query = ProductoRegulacionPermiso::query()->with(['entidadReguladora']);
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $q->orWhere('nombre', 'like', $like)
          ->orWhere('observaciones', 'like', $like)
          ->orWhereHas('entidadReguladora', function ($entidad) use ($like) {
            $entidad->where('nombre', 'like', $like);
          });
      }
    });

    foreach ($query->orderByDesc('id')->limit(8)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'permiso',
        'titulo' => (string) $row->nombre,
        'rubro' => null,
        'entidad' => $row->entidadReguladora ? (string) $row->entidadReguladora->nombre : null,
        'restriccion' => 'PERMISO',
        'detalle' => $this->joinParts([
          $row->c_permiso ? 'Costo permiso S/ ' . $row->c_permiso : null,
          $row->c_tramitador ? 'Tramitador S/ ' . $row->c_tramitador : null,
        ]),
        'observaciones' => $row->observaciones ? (string) $row->observaciones : null,
      ];
    }
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchAntidumping(array $terms, array &$items)
  {
    $query = ProductoRegulacionAntidumping::query()->with('rubro');
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $q->orWhere('descripcion_producto', 'like', $like)
          ->orWhere('partida', 'like', $like)
          ->orWhere('observaciones', 'like', $like)
          ->orWhereHas('rubro', function ($rubro) use ($like) {
            $rubro->where('nombre', 'like', $like);
          });
      }
    });

    foreach ($query->orderByDesc('id')->limit(6)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'antidumping',
        'titulo' => (string) ($row->descripcion_producto ?: 'Antidumping'),
        'rubro' => $row->rubro ? (string) $row->rubro->nombre : null,
        'subpartida' => $row->partida ? (string) $row->partida : null,
        'restriccion' => 'ANTIDUMPING',
        'detalle' => $this->joinParts([
          $row->antidumping ? 'Cuota: ' . $row->antidumping : null,
          $row->precio_declarado ? 'Precio declarado: ' . $row->precio_declarado : null,
        ]),
        'observaciones' => $row->observaciones ? (string) $row->observaciones : null,
      ];
    }
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchEtiquetado(array $terms, array &$items)
  {
    $query = ProductoRegulacionEtiquetado::query()->with(['rubro', 'entidadReguladora']);
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $q->orWhere('observaciones', 'like', $like)
          ->orWhereHas('rubro', function ($rubro) use ($like) {
            $rubro->where('nombre', 'like', $like);
          });
      }
    });

    foreach ($query->orderByDesc('id')->limit(5)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'etiquetado',
        'titulo' => $row->rubro ? (string) $row->rubro->nombre : 'Etiquetado',
        'rubro' => $row->rubro ? (string) $row->rubro->nombre : null,
        'entidad' => $row->entidadReguladora ? (string) $row->entidadReguladora->nombre : null,
        'restriccion' => 'ETIQUETADO',
        'detalle' => 'Requiere regulación de etiquetado',
        'observaciones' => $row->observaciones ? (string) $row->observaciones : null,
      ];
    }
  }

  /**
   * @param  array<int, string>  $terms
   * @param  array<int, array<string, mixed>>  $items
   */
  protected function searchDocumentosEspeciales(array $terms, array &$items)
  {
    $query = ProductoRegulacionDocumentoEspecial::query()->with('rubro');
    $query->where(function ($q) use ($terms) {
      foreach ($terms as $term) {
        $like = '%' . $term . '%';
        $q->orWhere('observaciones', 'like', $like)
          ->orWhereHas('rubro', function ($rubro) use ($like) {
            $rubro->where('nombre', 'like', $like);
          });
      }
    });

    foreach ($query->orderByDesc('id')->limit(5)->get() as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'tipo' => 'documento_especial',
        'titulo' => $row->rubro ? (string) $row->rubro->nombre : 'Documento especial',
        'rubro' => $row->rubro ? (string) $row->rubro->nombre : null,
        'restriccion' => 'DOC. ESPECIAL',
        'detalle' => 'Requiere documentación especial',
        'observaciones' => $row->observaciones ? (string) $row->observaciones : null,
      ];
    }
  }

  /**
   * @param  string  $source
   * @param  callable  $callback
   */
  protected function safeSearch($source, callable $callback)
  {
    try {
      $callback();
    } catch (\Throwable $e) {
      Log::warning('[CopilotoAduana] search_failed', [
        'source' => $source,
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * @param  array<int, array<string, mixed>>  $items
   * @return array<int, array<string, mixed>>
   */
  protected function dedupeItems(array $items)
  {
    $seen = [];
    $out = [];
    foreach ($items as $item) {
      $key = ($item['tipo'] ?? '') . ':' . ($item['id'] ?? 0) . ':' . mb_strtolower((string) ($item['titulo'] ?? ''));
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = true;
      $out[] = $item;
    }

    return $out;
  }

  /**
   * @param  array<int, array<string, mixed>>  $items
   * @return string
   */
  protected function formatKnowledgeBlock(array $items)
  {
    if (empty($items)) {
      return 'Sin coincidencias en base de datos de productos/regulaciones para los términos del mensaje.';
    }

    $lines = ['Base de datos Probusiness — productos y regulaciones relevantes al mensaje:'];
    foreach ($items as $item) {
      $parts = [];
      $parts[] = '[' . strtoupper((string) ($item['tipo'] ?? 'item')) . '] ' . (string) ($item['titulo'] ?? '');
      if (!empty($item['rubro'])) {
        $parts[] = 'Rubro: ' . $item['rubro'];
      }
      if (!empty($item['subpartida'])) {
        $parts[] = 'Partida: ' . $item['subpartida'];
      }
      if (!empty($item['entidad'])) {
        $parts[] = 'Entidad: ' . $item['entidad'];
      }
      if (!empty($item['restriccion'])) {
        $parts[] = 'Tipo: ' . $item['restriccion'];
      }
      if (!empty($item['detalle'])) {
        $parts[] = (string) $item['detalle'];
      }
      if (!empty($item['observaciones'])) {
        $parts[] = 'Obs: ' . mb_substr((string) $item['observaciones'], 0, 220);
      }
      $lines[] = '- ' . implode(' | ', $parts);
    }

    $lines[] = 'Si el producto del cliente coincide con una regulación anterior, menciónalo en la sugerencia y alerta.';

    return implode("\n", $lines);
  }

  /**
   * @param  array<int, string|null>  $parts
   * @return string|null
   */
  protected function joinParts(array $parts)
  {
    $clean = [];
    foreach ($parts as $part) {
      $t = trim((string) $part);
      if ($t !== '') {
        $clean[] = $t;
      }
    }

    return count($clean) ? implode(' · ', $clean) : null;
  }
}
