<?php

namespace App\Support\WhatsApp;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Cifrado at-rest de secretos del inbox (APP_KEY).
 * Si el valor aún está en texto plano (seed/.env), se lee igual y se re-cifra al guardar.
 */
class WaInboxSecretCrypt
{
    /**
     * @param  mixed  $value
     * @return string|null
     */
    public static function encrypt($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (self::looksEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    public static function decrypt($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        if (!self::looksEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            return $value;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * @param  mixed  $value
     * @return bool
     */
    public static function looksEncrypted($value)
    {
        $raw = base64_decode((string) $value, true);
        if ($raw === false || $raw === '') {
            return false;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded)
            && isset($decoded['iv'])
            && isset($decoded['value'])
            && isset($decoded['mac']);
    }
}
