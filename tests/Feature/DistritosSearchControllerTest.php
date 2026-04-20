<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DistritosSearchControllerTest extends TestCase
{
    /**
     * Respuesta paginada lista para consumo desde autocomplete (Nuxt UI).
     *
     * @return void
     */
    public function test_distritos_search_returns_pagination_meta(): void
    {
        if (! Schema::hasTable('distrito')) {
            $this->markTestSkipped('Tabla distrito no disponible en este entorno.');
        }

        $response = $this->getJson('/api/clientes/ubicacion/distritos/search?page=1&per_page=5');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);

        $this->assertIsArray($response->json('data'));
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    /**
     * @return void
     */
    public function test_public_distritos_search_same_shape(): void
    {
        if (! Schema::hasTable('distrito')) {
            $this->markTestSkipped('Tabla distrito no disponible en este entorno.');
        }

        $response = $this->getJson('/api/public/ubicacion/distritos/search?q=a&per_page=3');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }
}
