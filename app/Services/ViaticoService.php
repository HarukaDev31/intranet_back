<?php

namespace App\Services;

use App\Models\Viatico;
use App\Models\ViaticoPago;
use App\Models\ViaticoRetribucion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
USE App\Events\ViaticoCreado;

class ViaticoService
{
    /**
     * Crear un nuevo viático (con items: concepto, monto, receipt_file por item)
     */
    public function crearViatico(array $data, ?UploadedFile $archivo = null, array $itemFiles = []): Viatico
    {
        try {
            DB::beginTransaction();

            $data['user_id'] = auth()->id();
            $data['status'] = Viatico::STATUS_PENDING;
            $items = $data['items'] ?? [];
            unset($data['items']);

            if ($archivo) {
                $data['receipt_file'] = $this->guardarArchivo($archivo);
            }

            $viatico = Viatico::create($data);

            foreach ($items as $index => $item) {
                $pagoData = [
                    'viatico_id' => $viatico->id,
                    'concepto' => $item['concepto'],
                    'monto' => $item['monto'],
                ];
                if (!empty($item['pago_url'])) {
                    // Ya viene con URL, no subir imagen; guardar solo la ruta
                    $pagoData['existing_file_url'] = $this->normalizarRutaPagoDesdeUrl($item['existing_file_url']);
                } else {
                    $file = $itemFiles[$index] ?? null;
                    if ($file instanceof UploadedFile) {
                        $fileInfo = $this->guardarArchivoPagoItem($file);
                        $pagoData = array_merge($pagoData, $fileInfo);
                    }
                }
                ViaticoPago::create($pagoData);
            }

            $user = $viatico->usuario;
            ViaticoCreado::dispatch($viatico, $user, 'Viático creado exitosamente');
            DB::commit();

            return $viatico->load(['usuario', 'pagos']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear viático: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un viático (items con id = actualizar, sin id = crear; no enviados = eliminar).
     * Un viático puede tener varias retribuciones. payment_receipt_file: agrega una más (tabla viaticos_retribuciones).
     * delete_retribucion_id: elimina una retribución por id.
     * delete_file: elimina todas las retribuciones y pone estado Pendiente.
     */
    public function actualizarViatico(Viatico $viatico, array $data, ?UploadedFile $archivo = null, array $itemFiles = []): Viatico
    {
        $rutaNuevaGuardada = null;
        $nuevaRetribucion = null;

        try {
            $archivosAEliminar = [];

            // Eliminar una retribución concreta (permitido aunque el viático esté CONFIRMED)
            $deleteRetribucionId = $data['delete_retribucion_id'] ?? null;
            if ($deleteRetribucionId) {
                $retribucion = ViaticoRetribucion::where('viatico_id', $viatico->id)->find($deleteRetribucionId);
                if ($retribucion && $retribucion->file_path) {
                    $archivosAEliminar[] = $retribucion->file_path;
                }
                ViaticoRetribucion::where('viatico_id', $viatico->id)->where('id', $deleteRetribucionId)->delete();
                unset($data['delete_retribucion_id']);
                // Actualizar payment_receipt_file legacy con la siguiente retribución o null
                $siguiente = ViaticoRetribucion::where('viatico_id', $viatico->id)->orderBy('orden')->orderBy('id')->first();
                $data['payment_receipt_file'] = $siguiente ? $siguiente->file_path : null;
                // Si la suma de las retribuciones restantes es menor que el total, pasar a PENDING
                $sumaRetribuciones = ViaticoRetribucion::where('viatico_id', $viatico->id)->sum('monto');
                $totalAmount = (float) $viatico->total_amount;
                if (abs($sumaRetribuciones - $totalAmount) >= 0.02) {
                    $data['status'] = Viatico::STATUS_PENDING;
                }
            }

            // Agregar una nueva retribución (archivo + banco, monto, fecha opcionales)
            $nuevaRetribucion = null;
            if ($archivo) {
                $rutaNuevaGuardada = $this->guardarArchivo($archivo);
                $maxOrden = ViaticoRetribucion::where('viatico_id', $viatico->id)->max('orden') ?? 0;
                $fechaCierre = isset($data['payment_receipt_fecha_cierre'])
                    ? (\Carbon\Carbon::parse($data['payment_receipt_fecha_cierre'])->format('Y-m-d'))
                    : null;
                $nuevaRetribucion = ViaticoRetribucion::create([
                    'viatico_id' => $viatico->id,
                    'file_path' => $rutaNuevaGuardada,
                    'file_original_name' => $archivo->getClientOriginalName(),
                    'banco' => $data['payment_receipt_banco'] ?? null,
                    'monto' => isset($data['payment_receipt_monto']) ? (float) $data['payment_receipt_monto'] : null,
                    'fecha_cierre' => $fechaCierre,
                    'orden' => $maxOrden + 1,
                ]);
                $data['payment_receipt_file'] = $rutaNuevaGuardada;
                // Pasar a CONFIRMED solo si la suma de todas las retribuciones = total del viático
                $sumaRetribuciones = ViaticoRetribucion::where('viatico_id', $viatico->id)->sum('monto');
                $totalAmount = (float) $viatico->total_amount;
                if (abs($sumaRetribuciones - $totalAmount) < 0.02) {
                    $data['status'] = Viatico::STATUS_CONFIRMED;
                } else {
                    $data['status'] = Viatico::STATUS_PENDING;
                }
            }

            if (isset($data['delete_file']) && $data['delete_file'] === true) {
                if ($viatico->receipt_file) {
                    $archivosAEliminar[] = $viatico->receipt_file;
                }
                foreach ($viatico->retribuciones as $r) {
                    if ($r->file_path) {
                        $archivosAEliminar[] = $r->file_path;
                    }
                }
                ViaticoRetribucion::where('viatico_id', $viatico->id)->delete();
                $data['payment_receipt_file'] = null;
                $data['status'] = Viatico::STATUS_PENDING;
                unset($data['delete_file']);
            }

            $items = $data['items'] ?? null;
            unset($data['items']);

            DB::beginTransaction();
            if (isset($data['status']) && $data['status'] === Viatico::STATUS_CONFIRMED && empty($viatico->codigo_confirmado)) {
                $year = date('Y');
                $nextIndex = Viatico::where('status', Viatico::STATUS_CONFIRMED)
                    ->whereNotNull('codigo_confirmado')
                    ->where('codigo_confirmado', 'like', "VI{$year}%")
                    ->lockForUpdate()
                    ->count() + 1;
                $data['codigo_confirmado'] = 'VI' . $year . str_pad((string) $nextIndex, 3, '0', STR_PAD_LEFT);
            }
            $viatico->update($data);

            if ($items !== null) {
                $idsPresentes = [];
                foreach ($items as $index => $item) {
                    $file = $itemFiles[$index] ?? null;
                    if (!empty($item['id'])) {
                        $pago = ViaticoPago::where('viatico_id', $viatico->id)->find($item['id']);
                        if ($pago) {
                            $idsPresentes[] = $pago->id;
                            $updateData = [
                                'concepto' => $item['concepto'],
                                'monto' => $item['monto'],
                            ];
                            if ($file instanceof UploadedFile) {
                                if ($pago->file_path) {
                                    $archivosAEliminar[] = $pago->file_path;
                                }
                                $updateData = array_merge($updateData, $this->guardarArchivoPagoItem($file));
                            }
                            $pago->update($updateData);
                        }
                    } else {
                        $pagoData = [
                            'viatico_id' => $viatico->id,
                            'concepto' => $item['concepto'],
                            'monto' => $item['monto'],
                        ];
                        if ($file instanceof UploadedFile) {
                            $pagoData = array_merge($pagoData, $this->guardarArchivoPagoItem($file));
                        }
                        $nuevo = ViaticoPago::create($pagoData);
                        $idsPresentes[] = $nuevo->id;
                    }
                }
                // Eliminar items que ya no vienen en el request
                $eliminados = ViaticoPago::where('viatico_id', $viatico->id)->whereNotIn('id', $idsPresentes)->get();
                foreach ($eliminados as $p) {
                    if ($p->file_path) {
                        $archivosAEliminar[] = $p->file_path;
                    }
                    $p->delete();
                }
            }

            DB::commit();

            foreach ($archivosAEliminar as $ruta) {
                $this->eliminarArchivo($ruta);
            }

            $viatico->load(['usuario', 'pagos', 'retribuciones']);
            if ($nuevaRetribucion !== null) {
                $viatico->nueva_retribucion = $nuevaRetribucion;
            }
            return $viatico;
        } catch (\Exception $e) {
            DB::rollBack();
            if ($rutaNuevaGuardada) {
                $this->eliminarArchivo($rutaNuevaGuardada);
            }
            Log::error('Error al actualizar viático: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Usuario actualiza su viático (con items: id para actualizar, sin id para crear)
     */
    public function usuarioActualizarViatico(Viatico $viatico, array $data, ?UploadedFile $archivo = null, array $itemFiles = []): Viatico
    {
        try {
            DB::beginTransaction();
            if ($archivo) {
                $data['receipt_file'] = $this->guardarArchivo($archivo);
            }
            $items = $data['items'] ?? null;
            unset($data['items']);
            $viatico->update($data);

            if ($items !== null) {
                $idsPresentes = [];
                foreach ($items as $index => $item) {
                    $file = $itemFiles[$index] ?? null;
                    if (!empty($item['id'])) {
                        $pago = ViaticoPago::where('viatico_id', $viatico->id)->find($item['id']);
                        if ($pago) {
                            $idsPresentes[] = $pago->id;
                            $updateData = ['concepto' => $item['concepto'], 'monto' => $item['monto']];
                            if ($file instanceof UploadedFile) {
                                if ($pago->file_path) {
                                    $this->eliminarArchivo($pago->file_path);
                                }
                                $updateData = array_merge($updateData, $this->guardarArchivoPagoItem($file));
                            }
                            $pago->update($updateData);
                        }
                    } else {
                        $pagoData = [
                            'viatico_id' => $viatico->id,
                            'concepto' => $item['concepto'],
                            'monto' => $item['monto'],
                        ];
                        if ($file instanceof UploadedFile) {
                            $pagoData = array_merge($pagoData, $this->guardarArchivoPagoItem($file));
                        }
                        $nuevo = ViaticoPago::create($pagoData);
                        $idsPresentes[] = $nuevo->id;
                    }
                }
                $eliminados = ViaticoPago::where('viatico_id', $viatico->id)->whereNotIn('id', $idsPresentes)->get();
                foreach ($eliminados as $p) {
                    if ($p->file_path) {
                        $this->eliminarArchivo($p->file_path);
                    }
                    $p->delete();
                }
            }
            DB::commit();
            return $viatico->load(['usuario', 'pagos']);
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
        $query = Viatico::with(['usuario', 'pagos', 'retribuciones']);

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

        if (isset($filtros['requesting_area'])) {
            $query->where('requesting_area', $filtros['requesting_area']);
        }

        // Búsqueda por asunto, descripción, código o monto
        if (isset($filtros['search']) && trim($filtros['search']) !== '') {
            $search = trim($filtros['search']);
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('expense_description', 'like', "%{$search}%")
                  ->orWhere('codigo_confirmado', 'like', "%{$search}%");
                if (is_numeric(str_replace([',', ' ', '.'], '', $search))) {
                    $amount = (float) str_replace(',', '.', $search);
                    $q->orWhereBetween('total_amount', [$amount - 0.02, $amount + 0.02]);
                }
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
        return Viatico::with(['usuario', 'pagos', 'retribuciones'])->find($id);
    }

    /**
     * Eliminar un viático
     */
    public function eliminarViatico(Viatico $viatico): bool
    {
        try {
            if ($viatico->receipt_file) {
                $this->eliminarArchivo($viatico->receipt_file);
            }
            if ($viatico->payment_receipt_file) {
                $this->eliminarArchivo($viatico->payment_receipt_file);
            }
            foreach ($viatico->pagos as $pago) {
                if ($pago->file_path) {
                    $this->eliminarArchivo($pago->file_path);
                }
            }
            foreach ($viatico->retribuciones as $r) {
                if ($r->file_path) {
                    $this->eliminarArchivo($r->file_path);
                }
            }
            return $viatico->delete();
        } catch (\Exception $e) {
            Log::error('Error al eliminar viático: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Normalizar pago_url (URL completa o ruta) a ruta relativa para file_path
     */
    private function normalizarRutaPagoDesdeUrl(string $pagoUrl): string
    {
        $path = $pagoUrl;
        if (preg_match('#^https?://#', $pagoUrl)) {
            $parsed = parse_url($pagoUrl);
            $path = isset($parsed['path']) ? ltrim($parsed['path'], '/') : $pagoUrl;
        }
        // Quitar prefijo "storage/" si viene en la ruta
        if (strpos($path, 'storage/') === 0) {
            $path = substr($path, 8);
        }
        return $path;
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
     * Guardar archivo de item en viaticos_pagos y devolver datos para la tabla
     */
    private function guardarArchivoPagoItem(UploadedFile $archivo): array
    {
        $nombreArchivo = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $ruta = $archivo->storeAs('viaticos_pagos', $nombreArchivo, 'public');
        return [
            'file_path' => $ruta,
            'file_url' => null,
            'file_size' => $archivo->getSize(),
            'file_original_name' => $archivo->getClientOriginalName(),
            'file_mime_type' => $archivo->getMimeType(),
            'file_extension' => $archivo->getClientOriginalExtension(),
        ];
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
