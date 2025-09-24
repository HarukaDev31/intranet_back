<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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