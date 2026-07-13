<?php

namespace App\Services;

use App\Models\Viatico;
use App\Models\ViaticoPago;
use App\Models\ViaticoRetribucion;
use App\Support\MonetarioDosDecimales;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Events\ViaticoCreado;
use App\Traits\UsesObjectStorage;

class ViaticoService
{
    use UsesObjectStorage;
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
            unset($data['total_amount']);

            if ($archivo) {
                $data['receipt_file'] = $this->guardarArchivo($archivo);
            }

            $viatico = Viatico::create(array_merge($data, ['total_amount' => '0.00']));

            foreach ($items as $index => $item) {
                $pagoData = [
                    'viatico_id' => $viatico->id,
                    'concepto' => $item['concepto'],
                    'monto' => $item['monto'],
                ];
                $file = $itemFiles[$index] ?? null;
                $pagoData = array_merge($pagoData, $this->resolverArchivoPagoItem($item, $file));
                ViaticoPago::create($pagoData);
            }

            $this->sincronizarTotalAmountViaticoDesdePagos($viatico);

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
                // Recalcular estado según la suma restante (sum por filas + BCMath; evitar float del aggregate SUM)
                $sumaRetribuciones = $this->sumaMontosRetribucionesViatico($viatico->id);
                $totalAmount = $viatico->total_amount;
                Log::info('sumaRetribuciones: ' . $sumaRetribuciones);
                Log::info('totalAmount: ' . $totalAmount);
                Log::info('diferencia (saldo pendiente sobre total): ' . $this->diferenciaMontoMoneda($totalAmount, $sumaRetribuciones));
                if ($this->retribucionesCubrenTotalViatico($sumaRetribuciones, $totalAmount)) {
                    $data['status'] = Viatico::STATUS_CONFIRMED;
                } else {
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
                    'monto' => isset($data['payment_receipt_monto']) ? $data['payment_receipt_monto'] : null,
                    'fecha_cierre' => $fechaCierre,
                    'orden' => $maxOrden + 1,
                ]);
                $data['payment_receipt_file'] = $rutaNuevaGuardada;
                // Pasar a CONFIRMED si la suma de todas las retribuciones >= total del viático
                $sumaRetribuciones = $this->sumaMontosRetribucionesViatico($viatico->id);
                $totalAmount = $viatico->total_amount;
                Log::info('sumaRetribuciones: ' . $sumaRetribuciones);
                Log::info('totalAmount: ' . $totalAmount);
                Log::info('diferencia (saldo pendiente sobre total): ' . $this->diferenciaMontoMoneda($totalAmount, $sumaRetribuciones));
                if ($this->retribucionesCubrenTotalViatico($sumaRetribuciones, $totalAmount)) {
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
            unset($data['total_amount']);

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
                            } else {
                                $updateData = array_merge($updateData, $this->resolverArchivoPagoItem($item, null));
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
                        } else {
                            $pagoData = array_merge($pagoData, $this->resolverArchivoPagoItem($item, null));
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

            $this->sincronizarTotalAmountViaticoDesdePagos($viatico);
            $viatico->refresh();
            $this->reconciliarEstadoConfirmacionSegunRetribuciones($viatico);

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
            unset($data['total_amount']);
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
                            } else {
                                $updateData = array_merge($updateData, $this->resolverArchivoPagoItem($item, null));
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
                        } else {
                            $pagoData = array_merge($pagoData, $this->resolverArchivoPagoItem($item, null));
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

            $this->sincronizarTotalAmountViaticoDesdePagos($viatico);

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

        if (isset($filtros['solicitante']) && trim($filtros['solicitante']) !== '') {
            $solicitante = trim($filtros['solicitante']);
            $query->whereHas('usuario', function ($q) use ($solicitante) {
                $q->where('No_Nombres_Apellidos', 'like', "%{$solicitante}%");
            });
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
     * Resolver ruta/archivo de un ítem de pago: subida nueva o URL/ruta existente.
     *
     * @return array<string, mixed>
     */
    private function resolverArchivoPagoItem(array $item, ?UploadedFile $file): array
    {
        $existingUrl = trim((string) ($item['existing_file_url'] ?? $item['pago_url'] ?? ''));
        if ($existingUrl !== '') {
            return ['file_path' => $this->normalizarRutaPagoDesdeUrl($existingUrl)];
        }

        if ($file instanceof UploadedFile) {
            return $this->guardarArchivoPagoItem($file);
        }

        return [];
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
        return $this->storageStoreUpload($archivo, 'viaticos', $nombreArchivo);
    }

    /**
     * Guardar archivo de item en viaticos_pagos y devolver datos para la tabla
     */
    private function guardarArchivoPagoItem(UploadedFile $archivo): array
    {
        $nombreArchivo = time() . '_' . uniqid() . '.' . $archivo->getClientOriginalExtension();
        $ruta = $this->storageStoreUpload($archivo, 'viaticos_pagos', $nombreArchivo);
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
     * Suma montos de ítems (viaticos_pagos) leyendo por filas (evitar SUM agregado como float en PDO).
     */
    private function sumaMontosPagosViatico(int $viaticoId): string
    {
        $montos = DB::table('viaticos_pagos')
            ->where('viatico_id', $viaticoId)
            ->orderBy('id')
            ->pluck('monto');

        return MonetarioDosDecimales::sumarMontosColumnaBd($montos);
    }

    private function sincronizarTotalAmountViaticoDesdePagos(Viatico $viatico): void
    {
        $sum = $this->sumaMontosPagosViatico($viatico->id);

        $viatico->total_amount = $sum;
        Viatico::withoutEvents(function () use ($viatico) {
            $viatico->save();
        });
    }

    /**
     * Si hay retribuciones con monto, el total del viático debe alinearse después de editar ítems.
     */
    private function reconciliarEstadoConfirmacionSegunRetribuciones(Viatico $viatico): void
    {
        if ($viatico->status === Viatico::STATUS_REJECTED) {
            return;
        }

        if (!ViaticoRetribucion::where('viatico_id', $viatico->id)->exists()) {
            return;
        }

        $sumaRetribuciones = $this->sumaMontosRetribucionesViatico($viatico->id);
        $nuevo = $this->retribucionesCubrenTotalViatico($sumaRetribuciones, $viatico->total_amount)
            ? Viatico::STATUS_CONFIRMED
            : Viatico::STATUS_PENDING;

        if ($viatico->status === $nuevo) {
            return;
        }

        $viatico->status = $nuevo;
        $viatico->save();
    }

    private function sumaMontosRetribucionesViatico(int $viaticoId): string
    {
        $montos = DB::table('viaticos_retribuciones')
            ->where('viatico_id', $viaticoId)
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('monto');

        return MonetarioDosDecimales::sumarMontosColumnaBd($montos);
    }

    /**
     * True si la suma cubre o iguala el total usando 2 decimales (sin depender de errores IEEE 754).
     */
    private function retribucionesCubrenTotalViatico($sumaRetribuciones, $totalAmount): bool
    {
        $sumStr = $this->montoDosDecimalesString($sumaRetribuciones);
        $totStr = $this->montoDosDecimalesString($totalAmount);
        if (function_exists('bccomp')) {
            return bccomp($sumStr, $totStr, 2) >= 0;
        }

        return (float) $sumStr >= (float) $totStr;
    }

    /**
     * Saldo pendiente: total menos suma retribuciones (2 decimales), usando BCMath cuando exista.
     */
    private function diferenciaMontoMoneda($totalAmount, $sumaRetribuciones): string
    {
        $totStr = $this->montoDosDecimalesString($totalAmount);
        $sumStr = $this->montoDosDecimalesString($sumaRetribuciones);

        return function_exists('bcsub')
            ? bcsub($totStr, $sumStr, 2)
            : sprintf('%.2F', round((float) $totStr - (float) $sumStr, 2));
    }

    private function montoDosDecimalesString($valor): string
    {
        if ($valor === null || $valor === '') {
            return '0.00';
        }

        $s = preg_replace('#\s+#', '', str_replace(',', '.', (string) $valor));
        if ($s === '' || !is_numeric($s)) {
            return '0.00';
        }

        if (function_exists('bcadd')) {
            return bcadd($s, '0', 2);
        }

        return sprintf('%.2F', round((float) $s, 2));
    }

    /**
     * Eliminar archivo del storage
     */
    private function eliminarArchivo(string $ruta): bool
    {
        try {
            return $this->objectStorage()->delete($ruta);
        } catch (\Exception $e) {
            Log::error('Error al eliminar archivo: ' . $e->getMessage());
            return false;
        }
    }
}
