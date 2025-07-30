<?php

namespace App\Helpers;

class CodeIgniterEncryption
{
    /**
     * Clave de encriptación
     */
    private $encryption_key = '2016$%&LAE_SYSTEMS@!¡?¿|{}[]';

    /**
     * Cipher de encriptación
     */
    protected $cipher = 'aes-128';

    /**
     * Modo de encriptación
     */
    protected $mode = 'cbc';

    /**
     * Driver de encriptación
     */
    protected $driver = 'openssl';

    /**
     * Handle de encriptación
     */
    protected $handle;

    /**
     * Modos disponibles
     */
    protected $modes = [
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
     * Algoritmos de digest soportados
     */
    protected $digests = [
        'sha224' => 28,
        'sha256' => 32,
        'sha384' => 48,
        'sha512' => 64
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        if (!extension_loaded('openssl')) {
            throw new \Exception('La extensión OpenSSL no está disponible.');
        }

        $this->initialize();
    }

    /**
     * Inicializar la encriptación
     */
    protected function initialize()
    {
        $this->cipher = 'aes-128';
        $this->mode = 'cbc';
        $this->driver = 'openssl';
        
        $this->_openssl_initialize([
            'cipher' => $this->cipher,
            'mode' => $this->mode
        ]);
    }

    /**
     * Inicializar OpenSSL
     */
    protected function _openssl_initialize($params)
    {
        if (!empty($params['cipher'])) {
            $params['cipher'] = strtolower($params['cipher']);
            $this->_cipher_alias($params['cipher']);
            $this->cipher = $params['cipher'];
        }

        if (!empty($params['mode'])) {
            $params['mode'] = strtolower($params['mode']);
            if (!isset($this->modes['openssl'][$params['mode']])) {
                throw new \Exception('Modo OpenSSL ' . strtoupper($params['mode']) . ' no está disponible.');
            }
            $this->mode = $this->modes['openssl'][$params['mode']];
        }

        if (isset($this->cipher, $this->mode)) {
            $handle = empty($this->mode)
                ? $this->cipher
                : $this->cipher . '-' . $this->mode;

            if (!in_array($handle, openssl_get_cipher_methods(), true)) {
                $this->handle = null;
                throw new \Exception('No se puede inicializar OpenSSL con el método ' . strtoupper($handle) . '.');
            } else {
                $this->handle = $handle;
            }
        }
    }

    /**
     * Encriptar
     */
    public function encrypt($data, array $params = null)
    {
        if (($params = $this->_get_params($params)) === false) {
            return false;
        }

        if (!isset($params['key'])) {
            $params['key'] = $this->hkdf($this->encryption_key, 'sha512', null, $this->strlen($this->encryption_key), 'encryption');
        }

        if (($data = $this->_openssl_encrypt($data, $params)) === false) {
            return false;
        }

        if ($params['base64']) {
            $data = base64_encode($data);
        }

        if (isset($params['hmac_digest'])) {
            if (!isset($params['hmac_key'])) {
                $params['hmac_key'] = $this->hkdf($this->encryption_key, 'sha512', null, null, 'authentication');
            }
            return hash_hmac($params['hmac_digest'], $data, $params['hmac_key'], !$params['base64']) . $data;
        }

        return $data;
    }

    /**
     * Desencriptar
     */
    public function decrypt($data, array $params = null)
    {
        if (($params = $this->_get_params($params)) === false) {
            return false;
        }

        if (isset($params['hmac_digest'])) {
            $digest_size = ($params['base64'])
                ? $this->digests[$params['hmac_digest']] * 2
                : $this->digests[$params['hmac_digest']];

            if ($this->strlen($data) <= $digest_size) {
                return false;
            }

            $hmac_input = $this->substr($data, 0, $digest_size);
            $data = $this->substr($data, $digest_size);

            if (!isset($params['hmac_key'])) {
                $params['hmac_key'] = $this->hkdf($this->encryption_key, 'sha512', null, null, 'authentication');
            }
            $hmac_check = hash_hmac($params['hmac_digest'], $data, $params['hmac_key'], !$params['base64']);

            // Comparación segura contra ataques de tiempo
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
            $params['key'] = $this->hkdf($this->encryption_key, 'sha512', null, $this->strlen($this->encryption_key), 'encryption');
        }

        return $this->_openssl_decrypt($data, $params);
    }

    /**
     * Encriptar via OpenSSL
     */
    protected function _openssl_encrypt($data, $params)
    {
        if (empty($params['handle'])) {
            return false;
        }

        $iv = ($iv_size = openssl_cipher_iv_length($params['handle']))
            ? $this->create_key($iv_size)
            : null;

        $data = openssl_encrypt(
            $data,
            $params['handle'],
            $params['key'],
            1, // DO NOT TOUCH!
            $iv
        );

        if ($data === false) {
            return false;
        }

        return $iv . $data;
    }

    /**
     * Desencriptar via OpenSSL
     */
    protected function _openssl_decrypt($data, $params)
    {
        if ($iv_size = openssl_cipher_iv_length($params['handle'])) {
            $iv = $this->substr($data, 0, $iv_size);
            $data = $this->substr($data, $iv_size);
        } else {
            $iv = null;
        }

        return empty($params['handle'])
            ? false
            : openssl_decrypt(
                $data,
                $params['handle'],
                $params['key'],
                1, // DO NOT TOUCH!
                $iv
            );
    }

    /**
     * Obtener parámetros
     */
    protected function _get_params($params)
    {
        if (empty($params)) {
            return isset($this->cipher, $this->mode, $this->encryption_key, $this->handle)
                ? [
                    'handle' => $this->handle,
                    'cipher' => $this->cipher,
                    'mode' => $this->mode,
                    'key' => null,
                    'base64' => true,
                    'hmac_digest' => 'sha512',
                    'hmac_key' => null
                ]
                : false;
        } elseif (!isset($params['cipher'], $params['mode'], $params['key'])) {
            return false;
        }

        if (isset($params['mode'])) {
            $params['mode'] = strtolower($params['mode']);
            if (!isset($this->modes[$this->driver][$params['mode']])) {
                return false;
            }
            $params['mode'] = $this->modes[$this->driver][$params['mode']];
        }

        if (isset($params['hmac']) && $params['hmac'] === false) {
            $params['hmac_digest'] = $params['hmac_key'] = null;
        } else {
            if (!isset($params['hmac_key'])) {
                // Si no se especifica hmac_key, usar la clave por defecto
                $params['hmac_key'] = null;
            }
            if (isset($params['hmac_digest'])) {
                $params['hmac_digest'] = strtolower($params['hmac_digest']);
                if (!isset($this->digests[$params['hmac_digest']])) {
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

        $this->_cipher_alias($params['cipher']);
        $params['handle'] = ($params['cipher'] !== $this->cipher || $params['mode'] !== $this->mode)
            ? $this->_openssl_get_handle($params['cipher'], $params['mode'])
            : $this->handle;

        return $params;
    }

    /**
     * Obtener handle OpenSSL
     */
    protected function _openssl_get_handle($cipher, $mode)
    {
        return ($mode === 'stream')
            ? $cipher
            : $cipher . '-' . $mode;
    }

    /**
     * Alias de cipher
     */
    protected function _cipher_alias(&$cipher)
    {
        static $dictionary;

        if (empty($dictionary)) {
            $dictionary = [
                'openssl' => [
                    'rijndael-128' => 'aes-128',
                    'tripledes' => 'des-ede3',
                    'blowfish' => 'bf',
                    'cast-128' => 'cast5',
                    'arcfour' => 'rc4-40',
                    'rc4' => 'rc4-40'
                ]
            ];
        }

        if (isset($dictionary[$this->driver][$cipher])) {
            $cipher = $dictionary[$this->driver][$cipher];
        }
    }

    /**
     * Crear clave aleatoria
     */
    public function create_key($length)
    {
        if (function_exists('random_bytes')) {
            try {
                return random_bytes((int) $length);
            } catch (\Exception $e) {
                return false;
            }
        }

        $is_secure = null;
        $key = openssl_random_pseudo_bytes($length, $is_secure);
        return ($is_secure === true)
            ? $key
            : false;
    }

    /**
     * HKDF
     */
    public function hkdf($key, $digest = 'sha512', $salt = null, $length = null, $info = '')
    {
        if (!isset($this->digests[$digest])) {
            return false;
        }

        if (empty($length) || !is_int($length)) {
            $length = $this->digests[$digest];
        } elseif ($length > (255 * $this->digests[$digest])) {
            return false;
        }

        $this->strlen($salt) or $salt = str_repeat("\0", $this->digests[$digest]);

        $prk = hash_hmac($digest, $key, $salt, true);
        $key = '';
        for ($key_block = '', $block_index = 1; $this->strlen($key) < $length; $block_index++) {
            $key_block = hash_hmac($digest, $key_block . $info . chr($block_index), $prk, true);
            $key .= $key_block;
        }

        return $this->substr($key, 0, $length);
    }

    /**
     * Strlen seguro para bytes
     */
    protected function strlen($str)
    {
        return defined('MB_OVERLOAD_STRING')
            ? mb_strlen($str, '8bit')
            : strlen($str);
    }

    /**
     * Substr seguro para bytes
     */
    protected function substr($str, $start, $length = null)
    {
        if (defined('MB_OVERLOAD_STRING')) {
            isset($length) or $length = ($start >= 0 ? $this->strlen($str) - $start : -$start);
            return mb_substr($str, $start, $length, '8bit');
        }

        return isset($length)
            ? substr($str, $start, $length)
            : substr($str, $start);
    }

    /**
     * Métodos de compatibilidad con la implementación anterior
     */

    /**
     * Encriptar string (alias para compatibilidad)
     */
    public function encode($string, $key = '')
    {
        return $this->encrypt($string);
    }

    /**
     * Desencriptar string (alias para compatibilidad)
     */
    public function decode($string, $key = '')
    {
        return $this->decrypt($string);
    }

    /**
     * Verificar contraseña encriptada
     */
    public function verifyPassword($plainPassword, $encryptedPassword)
    {
        $decrypted = $this->decrypt($encryptedPassword);
        return $decrypted === $plainPassword;
    }

    /**
     * Encriptar contraseña
     */
    public function encryptPassword($password)
    {
        return $this->encrypt($password);
    }
} 