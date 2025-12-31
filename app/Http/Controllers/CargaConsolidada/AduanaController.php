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
    /**
     * @OA\Get(
     *     path="/carga-consolidada/contenedores/{idContenedor}/aduana",
     *     tags={"Aduana"},
     *     summary="Ver formulario de aduana",
     *     description="Obtiene el formulario de aduana y archivos asociados a un contenedor",
     *     operationId="viewFormularioAduana",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="idContenedor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Formulario obtenido exitosamente"),
     *     @OA\Response(response=404, description="Formulario no encontrado")
     * )
     */
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
        
        // Codificar toda la ruta incluyendo el nombre del archivo para caracteres especiales como #
        $rutaEncoded = rawurlencode($ruta);
        
        return $baseUrl . '/' . $storagePath . '/' . $rutaEncoded;
    }
    /**
     * @OA\Post(
     *     path="/carga-consolidada/contenedor/aduana",
     *     tags={"Aduana"},
     *     summary="Guardar formulario de aduana",
     *     description="Guarda el formulario de aduana y los archivos asociados",
     *     operationId="saveFormularioAduana",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="idContainer", type="integer"),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="impuestos_pagados", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Formulario guardado exitosamente"),
     *     @OA\Response(response=500, description="Error interno")
     * )
     */
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
    /**
     * @OA\Delete(
     *     path="/carga-consolidada/contenedor/aduana/file/{idFile}",
     *     tags={"Aduana"},
     *     summary="Eliminar archivo de aduana",
     *     description="Elimina un archivo asociado al formulario de aduana",
     *     operationId="deleteFileAduana",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="idFile",
     *         in="path",
     *         required=true,
     *         description="ID del archivo a eliminar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Archivo eliminado correctamente"),
     *     @OA\Response(response=404, description="Archivo no encontrado")
     * )
     */
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
