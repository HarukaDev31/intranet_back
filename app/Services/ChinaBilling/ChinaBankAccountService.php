<?php

namespace App\Services\ChinaBilling;

use App\Models\ChinaBilling\ChinaBankAccount;

class ChinaBankAccountService
{
    /** Cuenta activa leída una sola vez por request (listados con muchas facturas). */
    private ?ChinaBankAccount $activeAccount = null;

    private bool $loaded = false;

    /**
     * Cuenta vigente. Si aún no se cargó ninguna, devuelve los campos vacíos.
     *
     * @return array<string, string>
     */
    public function active(): array
    {
        $account = $this->activeAccount();

        $result = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $result[$field] = (string) ($account?->getAttribute($field) ?? '');
        }

        return $result;
    }

    /**
     * Huella de unos datos bancarios: si la de la factura no coincide con la de la cuenta activa, la factura quedó desactualizada.
     *
     * @param  array<string, mixed>|null  $bank
     */
    public static function fingerprint(?array $bank): string
    {
        $values = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $values[$field] = trim((string) ($bank[$field] ?? ''));
        }

        return sha1((string) json_encode($values));
    }

    public function activeFingerprint(): string
    {
        return self::fingerprint($this->active());
    }

    /**
     * Cuándo y quién cambió la cuenta activa por última vez.
     *
     * @return array{at: string|null, by: string|null}
     */
    public function lastChange(): array
    {
        $account = $this->activeAccount();

        return ['at' => $account?->updated_at?->toIso8601String(), 'by' => $account?->getAttribute('updated_by_name')];
    }

    private function activeAccount(): ?ChinaBankAccount
    {
        if (!$this->loaded) {
            $this->activeAccount = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first();
            $this->loaded = true;
        }

        return $this->activeAccount;
    }

    /**
     * Al cambiar la cuenta no se toca ninguna factura: el listado compara huellas y marca «Actualizar factura».
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function update(array $data, ?string $actorName = null): array
    {
        $account = ChinaBankAccount::query()->where('is_active', true)->orderByDesc('id')->first() ?? new ChinaBankAccount(['is_active' => true]);

        $values = [];
        foreach (ChinaBankAccount::FIELDS as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        $account->fill($values);
        $account->setAttribute('updated_by_name', $actorName);
        $account->save();
        $this->loaded = false;

        return $this->active();
    }
}
