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
            $user = $request->user();
            $esChina = \App\Models\Usuario::esRolAlmacenChina(trim((string) $user->getNombreGrupo()));
            if (!$esChina) {
                try {
                    $this->authorization->requirePartner($user);
                } catch (ChinaBillingException) {
                    throw new ChinaBillingException('No tienes permiso para ver la cuenta de China.', 403);
                }
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
