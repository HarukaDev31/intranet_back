<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaBankAccount;

class ChinaBankAccountService
{
    /**
     * Cuenta vigente. Si aún no se cargó ninguna, devuelve los campos vacíos.
     *
     * @return array<string, string>
     */
    public function active(): array
    {
        $account = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first();

        $result = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $result[$field] = (string) ($account?->getAttribute($field) ?? '');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function update(array $data): array
    {
        $account = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first() ?? new ChinaBankAccount(['is_active' => true]);

        $values = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        $account->fill($values)->save();

        return $this->active();
    }
}
