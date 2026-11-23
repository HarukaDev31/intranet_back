<?php

namespace Tests\Feature;

use App\Http\Middleware\JWTExternalMiddleware;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\User;
use App\Services\Auth\ImpersonacionClienteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class VerComoClienteTest extends TestCase
{
    private ImpersonacionClienteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'ver_como',
            'database.connections.ver_como' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'auth.defaults.guard' => 'api-external',
            'cache.default' => 'array',
            'jwt.secret' => str_repeat('x', 64),
            'app.url_clientes' => 'https://clientes.example.test',
        ]);
        JWTAuth::factory()->setTTL(1440);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'email', 'dni', 'whatsapp', 'phone', 'password'] as $columna) {
                $table->string($columna)->nullable();
            }
            $table->integer('organizacion_id')->nullable();
            $table->timestamps();
        });
        Schema::create('organizacion_portales', function (Blueprint $table) {
            $table->id();
            $table->integer('organizacion_id');
            $table->string('url_clientes')->nullable();
        });
        $this->servicio = new ImpersonacionClienteService();
    }

    public function test_encuentra_la_cuenta_exacta_de_la_organizacion(): void
    {
        $this->usuario(['email' => 'ana@example.test', 'organizacion_id' => 2]);
        $ana = $this->usuario(['email' => 'ana@example.test', 'organizacion_id' => 1]);
        $beto = $this->usuario(['email' => 'b@example.test', 'whatsapp' => '+51 987 654 321']);

        $this->assertSame($ana, $this->cuenta(['correo' => 'ana@example.test'])['user']->id);
        $this->assertSame($beto, $this->cuenta(['telefono' => '987654321'])['user']->id);
    }

    public function test_no_elige_por_coincidencia_parcial_ni_ambigua(): void
    {
        $this->usuario(['whatsapp' => '551987654321']);
        $this->assertSame('SIN_CUENTA', $this->cuenta(['telefono' => '987654321'])['error']);
        $this->assertSame('SIN_CUENTA', $this->cuenta([])['error']);

        $this->usuario(['dni' => '40000001']);
        $this->usuario(['dni' => '40000001']);
        $this->assertSame('CUENTA_AMBIGUA', $this->cuenta(['documento' => '40000001'])['error']);
    }

    public function test_el_codigo_sirve_una_vez_y_solo_en_su_organizacion(): void
    {
        $id = $this->usuario(['email' => 'ana@example.test', 'organizacion_id' => 1]);
        $user = User::query()->findOrFail($id);

        $this->assertNull($this->servicio->canjear($this->codigo($user), 2, null));

        $codigo = $this->codigo($user);
        $sesion = $this->servicio->canjear($codigo, 1, null);
        $tokenNormal = JWTAuth::fromUser($user);
        $this->assertSame($id, $sesion['user']->id);
        $this->assertNull($this->servicio->canjear($codigo, 1, null));

        $payload = $this->claims($sesion['token']);
        $this->assertSame(77, $payload[ImpersonacionClienteService::CLAIM]);
        $this->assertSame(1800, $payload['exp'] - $payload['iat']);

        // El siguiente login normal no hereda ni el claim ni la duración corta.
        $normal = $this->claims($tokenNormal);
        $this->assertArrayNotHasKey(ImpersonacionClienteService::CLAIM, $normal);
        $this->assertSame(1440 * 60, $normal['exp'] - $normal['iat']);
    }

    public function test_la_sesion_lee_pero_no_escribe(): void
    {
        $user = User::query()->findOrFail($this->usuario(['email' => 'ana@example.test', 'organizacion_id' => 1]));
        $token = $this->servicio->canjear($this->codigo($user), 1, null)['token'];
        $tokenNormal = JWTAuth::fromUser($user);

        $this->assertSame(200, $this->pasar('GET', $token));
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $metodo) {
            $this->assertSame(403, $this->pasar($metodo, $token), $metodo);
        }
        $this->assertSame(200, $this->pasar('POST', $tokenNormal), 'el cliente real sí escribe');
    }

    private function pasar(string $metodo, string $token): int
    {
        $request = Request::create('/api/auth/clientes/profile', $metodo);
        $request->headers->set('Authorization', 'Bearer '.$token);
        $this->app->instance('request', $request);
        // En producción cada request arranca limpio: jwt-auth deja en el factory los claims del último token leído.
        JWTAuth::unsetToken();
        JWTAuth::factory()->customClaims([])->emptyClaims();
        JWTAuth::setRequest($request);

        return (new JWTExternalMiddleware())->handle($request, fn () => response('ok'))->getStatusCode();
    }

    /** Lee los claims sin pasar por jwt-auth, que los dejaría en su factory. */
    private function claims(string $token): array
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
    }

    private function codigo(User $user): string
    {
        $url = $this->servicio->crearLink($user, 77, 10, null);
        $this->assertStringStartsWith('https://clientes.example.test/ver-como-cliente#codigo=', $url);

        return explode('codigo=', $url)[1];
    }

    private function usuario(array $datos): int
    {
        return (int) DB::table('users')->insertGetId($datos);
    }

    private function cuenta(array $datos): array
    {
        $cotizacion = new Cotizacion();
        foreach ($datos + ['organizacion_id' => 1] as $campo => $valor) {
            $cotizacion->setAttribute($campo, $valor);
        }

        return $this->servicio->cuentaDeCotizacion($cotizacion);
    }
}
