<?php

namespace App\Http\Controllers\CargaConsolidada;

use App\Http\Controllers\Controller;
use App\Models\CargaConsolidada\ReasonDeleteCotizacion;
use Illuminate\Http\Request;

class ReasonDeleteCotizacionController extends Controller
{
    public function index()
    {
        $items = ReasonDeleteCotizacion::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $item = ReasonDeleteCotizacion::create([
            'name' => trim($validated['name']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Motivo creado correctamente',
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $item = ReasonDeleteCotizacion::findOrFail($id);
        $item->name = trim($validated['name']);
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Motivo actualizado correctamente',
            'data' => $item,
        ]);
    }

    public function destroy($id)
    {
        $item = ReasonDeleteCotizacion::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Motivo eliminado correctamente',
        ]);
    }
}

