<?php

namespace App\Http\Controllers\Perfil;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\UsuarioMetodoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CFG-26-0056: el usuario elige en su perfil la billetera digital y el número donde quiere
 * recibir sus reintegros (por defecto Yape con el celular de su cuenta).
 */
class MetodoPagoPreferidoController extends Controller
{
    public function show(): JsonResponse
    {
        $usuario = auth()->user();
        if (!$usuario instanceof Usuario) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => UsuarioMetodoPago::preferidoDe($usuario),
            'metodos' => $this->opcionesMetodos(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $usuario = auth()->user();
        if (!$usuario instanceof Usuario) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $datos = $request->validate([
            'metodo' => ['required', 'string', Rule::in(array_keys(UsuarioMetodoPago::METODOS))],
            'numero' => ['required', 'string', 'regex:/^9\d{8}$/'],
        ], [
            'metodo.in' => 'Elige un método de pago válido.',
            'numero.required' => 'Ingresa el número de celular.',
            'numero.regex' => 'El número debe tener 9 dígitos y empezar con 9.',
        ]);

        $registro = UsuarioMetodoPago::firstOrNew(['ID_Usuario' => $usuario->ID_Usuario]);
        $registro->metodo = (string) $datos['metodo'];
        $registro->numero = (string) $datos['numero'];
        $registro->save();

        return response()->json([
            'success' => true,
            'message' => 'Método de pago actualizado',
            'data' => UsuarioMetodoPago::preferidoDe($usuario),
            'metodos' => $this->opcionesMetodos(),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function opcionesMetodos(): array
    {
        $opciones = [];
        foreach (UsuarioMetodoPago::METODOS as $value => $label) {
            $opciones[] = ['value' => $value, 'label' => $label];
        }

        return $opciones;
    }
}
