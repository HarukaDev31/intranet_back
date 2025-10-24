<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\BaseDatos\Clientes\Cliente;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned to the "api" middleware group. Enjoy building your API!
|
*/

// Test route for phone search
Route::get('/test-phone-search/{termino}', function ($termino) {
    try {
        $clientes = Cliente::buscar($termino)->limit(5)->get();
        
        return response()->json([
            'termino_busqueda' => $termino,
            'termino_normalizado' => preg_replace('/[\s\-\(\)\.\+]/', '', $termino),
            'total_encontrados' => $clientes->count(),
            'clientes' => $clientes->map(function($cliente) {
                return [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'telefono' => $cliente->telefono,
                    'documento' => $cliente->documento
                ];
            })
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

/*
|--------------------------------------------------------------------------
| Módulos de Rutas
|--------------------------------------------------------------------------
|
| Las rutas están organizadas en módulos separados para mejor mantenimiento
| y organización del código.
|
*/

// Módulo de Autenticación (usuarios internos y externos)
require __DIR__.'/modules/auth.php';

// Módulo de Menús
require __DIR__.'/modules/menu.php';

// Módulo de Base de Datos
require __DIR__.'/modules/base-datos.php';

// Módulo de Cursos
require __DIR__.'/modules/cursos.php';

// Módulo de Carga Consolidada
require __DIR__.'/modules/carga-consolidada.php';

// Módulo de Calculadora de Importación
require __DIR__.'/modules/calculadora-importacion.php';

// Módulo de Campañas
require __DIR__.'/modules/campaigns.php';

// Módulo de Notificaciones
require __DIR__.'/modules/notificaciones.php';

// Módulo de Opciones Generales
require __DIR__.'/modules/options.php';

//Clientes
// Módulo de Delivery
require __DIR__.'/modules/external/delivery.php';
// Módulo de Importaciones
require __DIR__.'/modules/external/importaciones.php';
// Módulo de Contenedores
require __DIR__.'/modules/external/containers.php';
// Módulo de Location
require __DIR__.'/modules/external/commons/location.php';
// Módulo de Contenedores
require __DIR__.'/modules/external/commons/container.php';

// Google Sheets API Routes
Route::prefix('google-sheets')->group(function () {
    Route::get('/test-connection', [SheetController::class, 'testConnection']);
    Route::get('/values', [SheetController::class, 'getGoogleSheetValues']);
    Route::get('/merged-ranges', [SheetController::class, 'getMergedRanges']);
    Route::get('/merged-ranges-column', [SheetController::class, 'getMergedRangesInColumn']);
    Route::get('/range-values', [SheetController::class, 'getRangeValues']);
    Route::post('/insert-value', [SheetController::class, 'insertValue']);
    Route::post('/merge-cells', [SheetController::class, 'mergeCells']);
});