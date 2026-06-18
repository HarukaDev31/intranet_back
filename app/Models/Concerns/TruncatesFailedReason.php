<?php

namespace App\Models\Concerns;

trait TruncatesFailedReason
{
    public function setFailedReasonAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['failed_reason'] = null;

            return;
        }

        $this->attributes['failed_reason'] = mb_substr((string) $value, 0, 65000);
    }
}
