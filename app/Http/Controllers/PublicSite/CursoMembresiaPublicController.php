<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\WebCursoPlan;
use Illuminate\Http\JsonResponse;

class CursoMembresiaPublicController extends Controller
{
    /**
     * API pública: planes mostrados en probusiness.pe (curso membresía).
     */
    public function planes(): JsonResponse
    {
        $planes = WebCursoPlan::query()
            ->forPage(WebCursoPlan::PAGE_CURSO_MEMBRESIA)
            ->visiblePublic()
            ->ordered()
            ->get()
            ->map(function (WebCursoPlan $p) {
                return [
                    'tipo_pago' => $p->tipo_pago,
                    'title' => $p->title,
                    'subtitle' => $p->subtitle,
                    'price_current' => $p->price_current,
                    'price_original' => $p->price_original,
                    'price_amount' => $p->price_amount,
                    'benefits' => $p->benefits ?? [],
                    'button_label' => $p->button_label,
                    'button_css_classes' => $p->button_css_classes,
                    'card_css_classes' => $p->card_css_classes,
                    'sort_order' => $p->sort_order,
                ];
            });

        return response()->json([
            'page_key' => WebCursoPlan::PAGE_CURSO_MEMBRESIA,
            'planes' => $planes,
        ]);
    }
}
