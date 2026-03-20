<?php

namespace App\Http\Controllers\Curso;

use App\Http\Controllers\Controller;
use App\Models\WebCursoPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de planes para la landing curso membresía (intranet Vue + API pública de solo lectura).
 */
class WebCursoPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pageKey = $request->query('page_key', WebCursoPlan::PAGE_CURSO_MEMBRESIA);

        $planes = WebCursoPlan::query()
            ->where('page_key', $pageKey)
            ->ordered()
            ->get();

        return response()->json([
            'data' => $planes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $plan = WebCursoPlan::query()->create($data);

        return response()->json([
            'message' => 'Plan creado.',
            'data' => $plan,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = WebCursoPlan::query()->findOrFail($id);
        $data = $this->validatedPayload($request, $plan->id);

        $plan->update($data);

        return response()->json([
            'message' => 'Plan actualizado.',
            'data' => $plan->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $plan = WebCursoPlan::query()->findOrFail($id);
        $plan->delete();

        return response()->json([
            'message' => 'Plan eliminado.',
        ]);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        $pageKey = (string) $request->input('page_key', WebCursoPlan::PAGE_CURSO_MEMBRESIA);

        $sortRules = [
            'required',
            'integer',
            'min:1',
            'max:255',
            Rule::unique('web_curso_planes', 'sort_order')
                ->where('page_key', $pageKey)
                ->ignore($ignoreId),
        ];

        $validated = $request->validate(
            [
                'page_key' => 'required|string|max:64',
                'title' => 'required|string|max:255',
                'subtitle' => 'nullable|string|max:255',
                'price_current' => 'required|string|max:64',
                'price_original' => 'nullable|string|max:64',
                'price_amount' => 'nullable|integer|min:1|max:999999',
                'benefits' => 'required|array|min:1',
                'benefits.*' => 'required|string|max:2000',
                'button_label' => 'required|string|max:120',
                'button_css_classes' => 'nullable|string',
                'card_css_classes' => 'nullable|string',
                'is_visible' => 'sometimes|boolean',
                'sort_order' => $sortRules,
            ],
            [
                'sort_order.unique' => 'Ese orden ya está en uso. Usa otro número (al crear un plan nuevo te sugerimos el siguiente libre).',
            ]
        );

        $sortOrder = (int) $validated['sort_order'];

        return [
            'page_key' => $validated['page_key'],
            /* Misma clave que usa la landing (pagar N) e Izipay por posición de plan */
            'tipo_pago' => $sortOrder,
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'price_current' => $validated['price_current'],
            'price_original' => $validated['price_original'] ?? null,
            'price_amount' => $validated['price_amount'] ?? null,
            'benefits' => array_values(array_filter(
                array_map('trim', $validated['benefits']),
                static fn ($l) => $l !== ''
            )),
            'button_label' => $validated['button_label'],
            'button_css_classes' => $validated['button_css_classes'] ?? null,
            'card_css_classes' => $validated['card_css_classes'] ?? null,
            'is_visible' => $request->boolean('is_visible', true),
            'sort_order' => $sortOrder,
        ];
    }
}
