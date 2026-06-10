<?php

namespace App\Services\CargaConsolidada;

use App\Events\CargaConsolidada\DocumentacionExpedienteObservacionCreated;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\DocumentacionObservacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DocumentacionObservacionService
{
    const CATEGORIAS = [
        'factura_comercial',
        'packing_list',
        'excel_confirmacion',
        'general',
    ];

    /**
     * @param int $idProveedor
     * @return array<int, array<string, mixed>>
     */
    public function listarPorProveedor($idProveedor)
    {
        $this->assertProveedorExiste($idProveedor);

        return DocumentacionObservacion::query()
            ->where('id_proveedor', (int) $idProveedor)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (DocumentacionObservacion $obs) {
                return $this->serializar($obs);
            })
            ->values()
            ->all();
    }

    /**
     * @param int $idProveedor
     * @param array<string, mixed> $payload
     * @param Usuario $user
     * @return array<string, mixed>
     */
    public function crear($idProveedor, array $payload, Usuario $user)
    {
        $this->assertProveedorExiste($idProveedor);

        $validator = Validator::make($payload, [
            'categoria' => 'required|string|in:' . implode(',', self::CATEGORIAS),
            'mensaje' => 'required|string|min:1|max:5000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $mensaje = trim((string) $data['mensaje']);

        if ($mensaje === '') {
            throw ValidationException::withMessages([
                'mensaje' => ['El mensaje no puede estar vacío.'],
            ]);
        }

        $obs = DocumentacionObservacion::create([
            'id_proveedor' => (int) $idProveedor,
            'categoria' => (string) $data['categoria'],
            'mensaje' => $mensaje,
            'user_id' => (int) $user->ID_Usuario,
            'user_name' => trim((string) $user->No_Nombres_Apellidos),
        ]);

        $serializado = $this->serializar($obs);
        event(new DocumentacionExpedienteObservacionCreated((int) $idProveedor, $serializado));

        return $serializado;
    }

    /**
     * @param int $idProveedor
     * @return void
     */
    protected function assertProveedorExiste($idProveedor)
    {
        $exists = CotizacionProveedor::query()
            ->where('id', (int) $idProveedor)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'id_proveedor' => ['Proveedor no encontrado.'],
            ]);
        }
    }

    /**
     * @param DocumentacionObservacion $obs
     * @return array<string, mixed>
     */
    protected function serializar(DocumentacionObservacion $obs)
    {
        return [
            'id' => (int) $obs->id,
            'id_proveedor' => (int) $obs->id_proveedor,
            'categoria' => (string) $obs->categoria,
            'mensaje' => (string) $obs->mensaje,
            'user_id' => (int) $obs->user_id,
            'user_name' => (string) $obs->user_name,
            'created_at' => $obs->created_at ? $obs->created_at->toIso8601String() : null,
        ];
    }
}
