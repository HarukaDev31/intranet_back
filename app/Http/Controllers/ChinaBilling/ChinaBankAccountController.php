<?php

namespace App\Http\Controllers\ChinaBilling;

use App\Exceptions\ChinaBillingException;
use App\Models\ChinaBilling\ChinaBankAccount;
use App\Http\Controllers\Controller;
use App\Services\ChinaBilling\ChinaBankAccountService;
use App\Services\ChinaBilling\ChinaBillingAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos bancarios de China: los ve Almacén China y el socio; solo Almacén China los edita.
 */
class ChinaBankAccountController extends Controller
{
    use RespondsChinaBilling;

    public function __construct(
        private readonly ChinaBillingAuthorization $authorization,
        private readonly ChinaBankAccountService $accounts,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            try {
                $this->authorization->requireWarehouse($request->user());
            } catch (ChinaBillingException) {
                $this->authorization->requirePartner($request->user());
            }

            return response()->json(['success' => true, 'data' => $this->accounts->active()]);
        });
    }

    public function update(Request $request): JsonResponse
    {
        return $this->responder(function () use ($request) {
            $this->authorization->requireWarehouse($request->user());
            $data = $request->validate($this->rules());

            return response()->json(['success' => true, 'data' => $this->accounts->update($data)]);
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $rules = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $rules[$field] = $field === 'company' ? ['required', 'string', 'max:150'] : ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
