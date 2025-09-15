<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CargaConsolidada\Contenedor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AduanaController extends Controller
{
    public function viewFormularioAduana($idContenedor)
    {


        // Migración del query original a Eloquent/Query Builder puro en Laravel

        $contenedor = DB::table('carga_consolidada_contenedor')
            ->where('id', $idContenedor)
            ->first();

        if ($contenedor) {
            // Obtener los archivos relacionados al contenedor
            $archivos = DB::table('carga_consolidada_aduana_files')
                ->where('id_contenedor', $idContenedor)
                ->select([
                    'id',
                    'file_name',
                    'file_path as file_url',
                    'file_type as file_ext',
                    'file_size',
                    'tipo'
                ])
                ->get();

            // Convertir a array simple
            $contenedor->files = $archivos->toArray();
            //for each file, get the file url
            foreach ($contenedor->files as $file) {
                $file->file_url = $this->generateImageUrl($file->file_url);
            }
        } else {
            $contenedor = null;
        }
        $query = $contenedor;
        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Formulario de aduana no encontrado'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => $query,
            'message' => 'Formulario de aduana encontrado correctamente'
        ]);
    }
    private function generateImageUrl($ruta)
    {
        if (empty($ruta)) {
            return null;
        }

        // Si ya es una URL completa, devolverla tal como está
        if (filter_var($ruta, FILTER_VALIDATE_URL)) {
            return $ruta;
        }

        // Limpiar la ruta de barras iniciales para evitar doble slash
        $ruta = ltrim($ruta, '/');

        // Construir URL manualmente para evitar problemas con Storage::url()
        $baseUrl = config('app.url');
        $storagePath = '/storage/';

        // Asegurar que no haya doble slash
        $baseUrl = rtrim($baseUrl, '/');
        $storagePath = ltrim($storagePath, '/');
        $ruta = ltrim($ruta, '/');
        return $baseUrl . '/' . $storagePath . '/' . $ruta;
    }
    public function saveFormularioAduana(Request $request)
    {
        DB::beginTransaction();
        try {
            $idContenedor = $request->idContainer;
            $data = $request->all();

            $files = $request->file('files');
            $impuestos_pagados = $request->file('impuestos_pagados');

            if ($files) {
                foreach ($files as $file) {
                    $filePath = $file->storeAs('cargaconsolidada/aduana/' . $idContenedor, $file->getClientOriginalName(),'public');
                    $fileName = $file->getClientOriginalName();
                    //use query builder
                    DB::table('carga_consolidada_aduana_files')->insert([
                        'id_contenedor' => $idContenedor,
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_type' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }
            if ($impuestos_pagados) {
                foreach ($impuestos_pagados as $impuestos_pagado) {
                    $filePath = $impuestos_pagado->storeAs('cargaconsolidada/aduana/' . $idContenedor, $impuestos_pagado->getClientOriginalName(),'public');
                    $fileName = $impuestos_pagado->getClientOriginalName();
                    DB::table('carga_consolidada_aduana_files')->insert([
                        'id_contenedor' => $idContenedor,
                        'file_path' => $filePath,
                        'file_name' => $fileName,
                        'file_type' => $impuestos_pagado->getClientOriginalExtension(),
                        'file_size' => $impuestos_pagado->getSize(),
                        'tipo' => 'impuestos'
                    ]);
                }
            }
            $contenedor = Contenedor::find($idContenedor);
            $contenedor->fill($data);
            $contenedor->save();
            return response()->json([
                'success' => true,
                'message' => 'Formulario de aduana guardado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar formulario de aduana: ' . $e->getMessage()
            ], 500);
        } finally {
            DB::commit();
        }
    }
    public function deleteFileAduana($idFile)
    {
        $file = DB::table('carga_consolidada_aduana_files')->where('id', $idFile)->first();
        DB::table('carga_consolidada_aduana_files')->where('id', $idFile)->delete();
        Storage::disk('public')->delete($file->file_path);
        return response()->json([
            'success' => true,
            'message' => 'Archivo eliminado correctamente'
        ]);
    }
}
