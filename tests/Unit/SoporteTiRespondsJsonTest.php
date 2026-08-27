<?php

namespace Tests\Unit;

use App\Support\SoporteTi\RespondsSoporteTiJson;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class SoporteTiRespondsJsonTest extends TestCase
{
    private function subject()
    {
        return new class {
            use RespondsSoporteTiJson;

            public function ok($data = null, $message = null, $status = 200): JsonResponse
            {
                return $this->soporteTiOk($data, $message, $status);
            }

            public function fail(\Throwable $e): JsonResponse
            {
                return $this->soporteTiFail($e);
            }
        };
    }

    public function test_ok_incluye_data_y_success(): void
    {
        $res = $this->subject()->ok(array('id' => 1), 'listo');
        $this->assertSame(200, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('listo', $payload['message']);
        $this->assertSame(1, $payload['data']['id']);
    }

    public function test_fail_mapea_authorization_a_403(): void
    {
        $res = $this->subject()->fail(new AuthorizationException('No autorizado'));
        $this->assertSame(403, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('No autorizado', $payload['message']);
    }

    public function test_fail_mapea_not_found_a_404(): void
    {
        $res = $this->subject()->fail(new ModelNotFoundException());
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_fail_mapea_invalid_argument_a_422(): void
    {
        $res = $this->subject()->fail(new \InvalidArgumentException('dato inválido'));
        $this->assertSame(422, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertSame('dato inválido', $payload['message']);
    }

    public function test_fail_generico_no_expone_mensaje_interno(): void
    {
        $res = $this->subject()->fail(new \RuntimeException('stack secret'));
        $this->assertSame(500, $res->getStatusCode());
        $payload = $res->getData(true);
        $this->assertStringNotContainsString('stack secret', $payload['message']);
    }
}
