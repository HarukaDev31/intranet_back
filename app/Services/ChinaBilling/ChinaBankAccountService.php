<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaBankAccount;

class ChinaBankAccountService
{
    /**
     * Cuenta vigente. Si aún no se cargó ninguna, devuelve los campos vacíos.
     *
     * @return array{company: string, beneficiary: string, intermediary_bank: string, payer_bank: string, account_number: string}
     */
    public function active(): array
    {
        $account = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first();

        return [
            'company' => (string) ($account?->company ?? ''),
            'beneficiary' => (string) ($account?->beneficiary ?? ''),
            'intermediary_bank' => (string) ($account?->intermediary_bank ?? ''),
            'payer_bank' => (string) ($account?->payer_bank ?? ''),
            'account_number' => (string) ($account?->account_number ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{company: string, beneficiary: string, intermediary_bank: string, payer_bank: string, account_number: string}
     */
    public function update(array $data): array
    {
        $account = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first() ?? new ChinaBankAccount(['is_active' => true]);
        $account->fill([
            'company' => $data['company'],
            'beneficiary' => $data['beneficiary'] ?? null,
            'intermediary_bank' => $data['intermediary_bank'] ?? null,
            'payer_bank' => $data['payer_bank'] ?? null,
            'account_number' => $data['account_number'] ?? null,
        ])->save();

        return $this->active();
    }
}
