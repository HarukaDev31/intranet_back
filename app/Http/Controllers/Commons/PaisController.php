<?php

namespace App\Http\Controllers\Commons;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pais;
class PaisController extends Controller
{
    public function getPaisDropdown()
    {
        $paises = Pais::all();
        $data = [];
        foreach ($paises as $pais) {
            $data[] = ['value' => $pais->ID_Pais, 'label' => $pais->No_Pais];
        }
        return response()->json(['data' => $data, 'success' => true]);
    }
}
