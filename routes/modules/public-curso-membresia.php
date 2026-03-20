<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\CursoMembresiaPublicController;

/*
| API pública (sin JWT) para la web probusiness.pe
*/
Route::prefix('public')->group(function () {
    Route::get('curso-membresia/planes', [CursoMembresiaPublicController::class, 'planes'])
        ->name('public.curso-membresia.planes');
});
