<?php

namespace App\Models\Concerns;

use App\Support\MonetarioDosDecimales;

trait NormalizesMontosDosDecimales
{
    /**
     * Evita pérdida de precisión por float/point al persistir DECIMAL(?,2).
     */
    public function setMontoAttribute($value): void
    {
        $this->attributes['monto'] = MonetarioDosDecimales::paraBd($value);
    }
}
