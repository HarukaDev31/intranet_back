<?php

namespace App\Services;

use App\Models\Viatico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
USE App\Events\ViaticoCreado;

class ViaticoService
{
    /**
     * Crear un nuevo viático
     */
    public function crearViatico(array $data, ?UploadedFile $archivo = null): Viatico
    {
        try {
            DB::beginTransaction();

            $data['user_id'] = auth()->id();
            $data['status'] = Viatico::STATUS_PENDING;

            // Si se sube un archivo, guardarlo y cambiar estado a CONFIRMED
            if ($archivo) {
                $data['receipt_file'] = $this->guardarArchivo($archivo);
            }

            $viatico = Viatico::create($data);
            //send viatico created notification to user
            $user = $viatico->usuario;
            ViaticoCreado::dispatch($viatico, $user, 'Viático creado exitosamente');
            DB::commit();

            return $viatico->load('usuario');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear viático: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un viático
     */
    public function actualizarViatico(Viatico $viatico, array $data, ?UploadedFile $archivo = null): Viatico
    {
        $rutaNuevaGuardada = null;

        try {
            // Guardar rutas de archivos a eliminar para hacerlo DESPUÉS del commit
            // (operaciones de disco lentas no deben mantener la transacción abierta)
            $archivosAEliminar = [];

            // Si se sube un archivo nuevo: guardar FUERA de la transacción (es la parte más lenta)
            if ($archivo) {
                if ($viatico->payment_receipt_file) {
                    $archivosAEliminar[] = $viatico->payment_receipt_file;
                }
                $rutaNuevaGuardada = $this->guardarArchivo($archivo);
                $data['payment_receipt_file'] = $rutaNuevaGuardada;
                $data['status'] = Viatico::STATUS_CONFIRMED;
            }

            // Si se elimina el archivo (se envía delete_file como true)
            if (isset($data['delete_file']) && $data['delete_file'] === true) {
                if ($viatico->receipt_file) {
                    $archivosAEliminar[] = $viatico->receipt_file;
                }
                $data['payment_receipt_file'] = null;
                $data['status'] = Viatico::STATUS_PENDING;
                unset($data['delete_file']);
            }

            DB::beginTransaction();
            $viatico->update($data);
            DB::commit();

            // Eliminar archivos antiguos después del commit para no bloquear la transacción
            foreach ($archivosAEliminar as $ruta) {
                $this->eliminarArchivo($ruta);
            }

            // Evitar fresh() (query extra): el modelo ya está actualizado; solo recargar relación si hace falta
            return $viatico->load('usuario');
        } catch (\Exception $e) {
            DB::rollBack();
            // Si se había guardado un archivo nuevo y falló el update, eliminar el archivo huérfano
            if ($rutaNuevaGuardada) {
                $this->eliminarArchivo($rutaNuevaGuardada);
            }
            Log::error('Error al actualizar viático: ' . $e->getMessage());
            throw $e;
        }
    }
    //usuario actualiza viatico
    public function usuarioActualizarViatico(Viatico $viatico, array $data, ?UploadedFile $archivo = null): Viatico
    {
        try {
            DB::beginTransaction();
            if ($archivo) {
                $data['receipt_file'] = $this->guardarArchivo($archivo);
            }
            $viatico->update($data);
            DB::commit();
            return $viatico->fresh()->load('usuario');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar viático: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener viáticos con filtros
     */
    public function obtenerViaticos(array $filtros = [])
    {
        $query = Viatico::with('usuario');

        // Filtrar por usuario si no es administración
        if (isset($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }

        // Filtrar por estado
        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        // Filtrar por fecha
        if (isset($filtros['fecha_inicio'])) {
            $query->where('reimbursement_date', '>=', $filtros['fecha_inicio']);
        }

        if (isset($filtros['fecha_fin'])) {
            $query->where('reimbursement_date', '<=', $filtros['fecha_fin']);
        }

        // Búsqueda por asunto o descripción
        if (isset($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('expense_description', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $filtros['sort_by'] ?? 'created_at';
        $sortOrder = $filtros['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query;
    }

    /**
     * Obtener un viático por ID
     */
    public function obtenerViaticoPorId(int $id): ?Viatico
    {
        return Viatico::with('usuario')->find($id);
    }

    /**
     * Eliminar un viático
     */
    public function eliminarViatico(Viatico $viatico): bool
    {
        try {
            // Eliminar archivo si existe
            if ($viatico->receipt_file) {
                $this->eliminarArchivo($viatico->receipt_file);
            }

            return $viatico->delete();
        } catch (\Exception $e) {
            Log::error('Error al eliminar viático: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Guardar archivo en storage
     */
    private function guardarArchivo(UploadedFile $archivo): string
    {
        $nombreArchivo = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs('viaticos', $nombreArchivo, 'public');
        
        return $ruta;
    }

    /**
     * Eliminar archivo del storage
     */
    private function eliminarArchivo(string $ruta): bool
    {
        try {
            if (Storage::disk('public')->exists($ruta)) {
                return Storage::disk('public')->delete($ruta);
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Error al eliminar archivo: ' . $e->getMessage());
            return false;
        }
    }
}
