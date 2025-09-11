<?php

namespace App\Traits;

use Exception;

/**
 * CodeIgniter Encryption Compatibility Trait for Laravel (PHP 7.3 Compatible)
 * 
 * This trait allows you to decrypt data that was encrypted using CodeIgniter's encryption library
 * 
 * Usage:
 * use App\Traits\CodeIgniterEncryption;
 * 
 * class YourClass {
 *     use CodeIgniterEncryption;
 *     
 *     public function someMethod() {
 *         $decrypted = $this->ciDecrypt($encryptedData);
 *         $encrypted = $this->ciEncrypt($plainData);
 *     }
 * }
 */
trait CodeIgniterEncryption
{
    /**
     * Encryption cipher
     *
     * @var string
     */
    protected $ciCipher = 'aes-128';

    /**
     * Cipher mode
     *
     * @var string
     */
    protected $ciMode = 'cbc';

    /**
     * Cipher handle
     *
     * @var mixed
     */
    protected $ciHandle;

    /**
     * Encryption key
     *
     * @var string|null
     */
    protected $ciKey;

    /**
     * PHP extension to be used
     *
     * @var string
     */
    protected $ciDriver = 'openssl';

    /**
     * List of available modes
     *
     * @var array
     */
    protected $ciModes = [
        'openssl' => [
            'cbc' => 'cbc',
            'ecb' => 'ecb',
            'ofb' => 'ofb',
            'cfb' => 'cfb',
            'cfb8' => 'cfb8',
            'ctr' => 'ctr',
            'stream' => '',
            'xts' => 'xts'
        ]
    ];

    /**
     * List of supported HMAC algorithms
     *
     * @var array
     */
    protected $ciDigests = [
        'sha224' => 28,
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64
    ];

    /**
     * mbstring.func_overload flag
     *
     * @var bool|null
     */
    protected static $ciFuncOverload;

    /**
     * Initialize CodeIgniter encryption
     *
     * @param array $params
     * @throws Exception
     */
    protected function initializeCiEncryption(array $params = [])
    {
        if (!extension_loaded('openssl')) {
            throw new Exception('Encryption: OpenSSL extension is required.');
        }

        if (self::$ciFuncOverload === null) {
            self::$ciFuncOverload = (extension_loaded('mbstring') && ini_get('mbstring.func_overload'));
        }
        
        // Get key from Laravel's .env file
        if (!isset($this->ciKey)) {
            $key = env('ENCRYPTION_KEY', config('app.ci_encryption_key'));
            if ($key) {
                $this->ciKey = $key;
            }
        }

        if (!isset($this->ciKey)) {
            throw new Exception('Encryption: No encryption key provided. Set CI_ENCRYPTION_KEY in your .env file.');
        }

        $this->ciDriver = 'openssl';
        
        if (empty($params['cipher'])) {
            $params['cipher'] = $this->ciCipher;
        }
        
        if (!empty($params['key'])) {
            $this->ciKey = $params['key'];
        }
        
        $this->ciOpenSslInitialize($params);
    }

    /**
     * Initialize OpenSSL
     *
     * @param array $params
     * @throws Exception
     */
    protected function ciOpenSslInitialize(array $params)
    {
        if (!empty($params['cipher'])) {
            $params['cipher'] = strtolower($params['cipher']);
            $this->ciCipherAlias($params['cipher']);
            $this->ciCipher = $params['cipher'];
        }

        if (!empty($params['mode'])) {
            $params['mode'] = strtolower($params['mode']);
            if (!isset($this->ciModes['openssl'][$params['mode']])) {
                throw new Exception('Encryption: OpenSSL mode ' . strtoupper($params['mode']) . ' is not available.');
            } else {
                $this->ciMode = $this->ciModes['openssl'][$params['mode']];
            }
        }

        if (isset($this->ciCipher, $this->ciMode)) {
            $handle = empty($this->ciMode) ? $this->ciCipher : $this->ciCipher . '-' . $this->ciMode;

            if (!in_array($handle, openssl_get_cipher_methods(), true)) {
                $this->ciHandle = null;
                throw new Exception('Encryption: Unable to initialize OpenSSL with method ' . strtoupper($handle) . '.');
            } else {
                $this->ciHandle = $handle;
            }
        }
    }

    /**
     * Create a random key
     *
     * @param int $length
     * @return string|false
     */
    protected function ciCreateKey($length)
    {
        try {
            return random_bytes($length);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Main decrypt method - Compatible with CodeIgniter encrypted data
     *
     * @param string $data
     * @param array|null $params
     * @return string|false
     */
    public function ciDecrypt($data, array $params = null)
    {
        // Auto-initialize if not done
        if (!isset($this->ciHandle)) {
            $this->initializeCiEncryption();
        }

        $params = $this->ciGetParams($params);
        if ($params === false) {
            return false;
        }

        if (isset($params['hmac_digest'])) {
            $digest_size = $params['base64'] 
                ? $this->ciDigests[$params['hmac_digest']] * 2
                : $this->ciDigests[$params['hmac_digest']];

            if ($this->ciStrlen($data) <= $digest_size) {
                return false;
            }

            $hmac_input = $this->ciSubstr($data, 0, $digest_size);
            $data = $this->ciSubstr($data, $digest_size);

            if (!isset($params['hmac_key'])) {
                $params['hmac_key'] = $this->ciHkdf($this->ciKey, 'sha512', null, null, 'authentication');
            }
            
            $hmac_check = hash_hmac($params['hmac_digest'], $data, $params['hmac_key'], !$params['base64']);

            // Time-attack-safe comparison
            $diff = 0;
            for ($i = 0; $i < $digest_size; $i++) {
                $diff |= ord($hmac_input[$i]) ^ ord($hmac_check[$i]);
            }

            if ($diff !== 0) {
                return false;
            }
        }

        if ($params['base64']) {
            $data = base64_decode($data);
        }

        if (!isset($params['key'])) {
            $params['key'] = $this->ciHkdf($this->ciKey, 'sha512', null, $this->ciStrlen($this->ciKey), 'encryption');
        }

        return $this->ciOpenSslDecrypt($data, $params);
    }

    /**
     * Main encrypt method - Compatible with CodeIgniter encryption
     *
     * @param string $data
     * @param array|null $params
     * @return string|false
     */
    public function ciEncrypt($data, array $params = null)
    {
        // Auto-initialize if not done
        if (!isset($this->ciHandle)) {
            $this->initializeCiEncryption();
        }

        $params = $this->ciGetParams($params);
        if ($params === false) {
            return false;
        }

        if (!isset($params['key'])) {
            $params['key'] = $this->ciHkdf($this->ciKey, 'sha512', null, $this->ciStrlen($this->ciKey), 'encryption');
        }

        $data = $this->ciOpenSslEncrypt($data, $params);
        if ($data === false) {
            return false;
        }

        if ($params['base64']) {
            $data = base64_encode($data);
        }

        if (isset($params['hmac_digest'])) {
            if (!isset($params['hmac_key'])) {
                $params['hmac_key'] = $this->ciHkdf($this->ciKey, 'sha512', null, null, 'authentication');
            }
            return hash_hmac($params['hmac_digest'], $data, $params['hmac_key'], !$params['base64']) . $data;
        }

        return $data;
    }

    /**
     * Encrypt via OpenSSL
     *
     * @param string $data
     * @param array $params
     * @return string|false
     */
    protected function ciOpenSslEncrypt($data, array $params)
    {
        if (empty($params['handle'])) {
            return false;
        }

        $iv_size = openssl_cipher_iv_length($params['handle']);
        $iv = $iv_size ? $this->ciCreateKey($iv_size) : null;

        $data = openssl_encrypt(
            $data,
            $params['handle'],
            $params['key'],
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($data === false) {
            return false;
        }

        return $iv . $data;
    }

    /**
     * Decrypt via OpenSSL
     *
     * @param string $data
     * @param array $params
     * @return string|false
     */
    protected function ciOpenSslDecrypt($data, array $params)
    {
        $iv_size = openssl_cipher_iv_length($params['handle']);
        
        if ($iv_size) {
            $iv = $this->ciSubstr($data, 0, $iv_size);
            $data = $this->ciSubstr($data, $iv_size);
        } else {
            $iv = null;
        }

        return empty($params['handle']) 
            ? false 
            : openssl_decrypt(
                $data,
                $params['handle'],
                $params['key'],
                OPENSSL_RAW_DATA,
                $iv
            );
    }

    /**
     * Get params
     *
     * @param array|null $params
     * @return array|false
     */
    protected function ciGetParams($params)
    {
        if (empty($params)) {
            return isset($this->ciCipher, $this->ciMode, $this->ciKey, $this->ciHandle)
                ? [
                    'handle' => $this->ciHandle,
                    'cipher' => $this->ciCipher,
                    'mode' => $this->ciMode,
                    'key' => null,
                    'base64' => true,
                    'hmac_digest' => 'sha512',
                    'hmac_key' => null
                ]
                : false;
        }
        
        if (!isset($params['cipher'], $params['mode'], $params['key'])) {
            return false;
        }

        if (isset($params['mode'])) {
            $params['mode'] = strtolower($params['mode']);
            if (!isset($this->ciModes[$this->ciDriver][$params['mode']])) {
                return false;
            }
            $params['mode'] = $this->ciModes[$this->ciDriver][$params['mode']];
        }

        if (isset($params['hmac']) && $params['hmac'] === false) {
            $params['hmac_digest'] = null;
            $params['hmac_key'] = null;
        } else {
            if (!isset($params['hmac_key'])) {
                return false;
            }
            
            if (isset($params['hmac_digest'])) {
                $params['hmac_digest'] = strtolower($params['hmac_digest']);
                if (!isset($this->ciDigests[$params['hmac_digest']])) {
                    return false;
                }
            } else {
                $params['hmac_digest'] = 'sha512';
            }
        }

        $params = [
            'handle' => null,
            'cipher' => $params['cipher'],
            'mode' => $params['mode'],
            'key' => $params['key'],
            'base64' => isset($params['raw_data']) ? !$params['raw_data'] : false,
            'hmac_digest' => $params['hmac_digest'],
            'hmac_key' => $params['hmac_key']
        ];

        $this->ciCipherAlias($params['cipher']);
        
        if ($params['cipher'] !== $this->ciCipher || $params['mode'] !== $this->ciMode) {
            $params['handle'] = $this->ciOpenSslGetHandle($params['cipher'], $params['mode']);
        } else {
            $params['handle'] = $this->ciHandle;
        }

        return $params;
    }

    /**
     * Get OpenSSL handle
     *
     * @param string $cipher
     * @param string $mode
     * @return string
     */
    protected function ciOpenSslGetHandle($cipher, $mode)
    {
        return ($mode === 'stream') ? $cipher : $cipher . '-' . $mode;
    }

    /**
     * Cipher alias
     *
     * @param string $cipher
     */
    protected function ciCipherAlias(&$cipher)
    {
        static $dictionary = [
            'openssl' => [
                'rijndael-128' => 'aes-128',
                'tripledes' => 'des-ede3',
                'blowfish' => 'bf',
                'cast-128' => 'cast5',
                'arcfour' => 'rc4-40',
                'rc4' => 'rc4-40'
            ]
        ];

        if (isset($dictionary[$this->ciDriver][$cipher])) {
            $cipher = $dictionary[$this->ciDriver][$cipher];
        }
    }

    /**
     * HKDF key derivation function
     *
     * @param string $key
     * @param string $digest
     * @param string|null $salt
     * @param int|null $length
     * @param string $info
     * @return string|false
     */
    protected function ciHkdf($key, $digest = 'sha512', $salt = null, $length = null, $info = '')
    {
        if (!isset($this->ciDigests[$digest])) {
            return false;
        }

        if (empty($length) || !is_int($length)) {
            $length = $this->ciDigests[$digest];
        } elseif ($length > (255 * $this->ciDigests[$digest])) {
            return false;
        }

        if (!$this->ciStrlen($salt)) {
            $salt = str_repeat("\0", $this->ciDigests[$digest]);
        }

        $prk = hash_hmac($digest, $key, $salt, true);
        $key = '';
        $key_block = '';
        
        for ($block_index = 1; $this->ciStrlen($key) < $length; $block_index++) {
            $key_block = hash_hmac($digest, $key_block . $info . chr($block_index), $prk, true);
            $key .= $key_block;
        }

        return $this->ciSubstr($key, 0, $length);
    }

    /**
     * Byte-safe strlen()
     *
     * @param string $str
     * @return int
     */
    protected function ciStrlen($str)
    {
        return (self::$ciFuncOverload) ? mb_strlen($str, '8bit') : strlen($str);
    }

    /**
     * Byte-safe substr()
     *
     * @param string $str
     * @param int $start
     * @param int|null $length
     * @return string
     */
    protected function ciSubstr($str, $start, $length = null)
    {
        if (self::$ciFuncOverload) {
            if ($length === null) {
                $length = ($start >= 0 ? $this->ciStrlen($str) - $start : -$start);
            }
            return mb_substr($str, $start, $length, '8bit');
        }
        
        return $length !== null ? substr($str, $start, $length) : substr($str, $start);
    }

    /**
     * Set custom encryption key
     *
     * @param string $key
     */
    public function setCiEncryptionKey($key)
    {
        $this->ciKey = $key;
        // Re-initialize with new key
        $this->initializeCiEncryption();
    }

    /**
     * Get current encryption key
     *
     * @return string|null
     */
    public function getCiEncryptionKey()
    {
        return $this->ciKey;
    }
}