# Encriptación de CodeIgniter 3.x en Laravel

Este proyecto implementa la encriptación moderna de CodeIgniter 3.x en Laravel para mantener compatibilidad con sistemas existentes que usan esta encriptación.

## Clase CodeIgniterEncryption

La clase `App\Helpers\CodeIgniterEncryption` proporciona métodos para encriptar y desencriptar datos usando el algoritmo moderno de CodeIgniter 3.x con la clave: `2016$%&LAE_SYSTEMS@!¡?¿|{}[]`

### Características de la implementación

- **Algoritmo**: AES-128-CBC con OpenSSL
- **HMAC**: SHA-512 para autenticación
- **HKDF**: Deriva claves de forma segura
- **Compatibilidad**: Total con CodeIgniter 3.x

### Métodos disponibles

#### `encrypt($data, array $params = null)`
Encripta datos usando el algoritmo moderno de CodeIgniter 3.x.

```php
$ciEncryption = new CodeIgniterEncryption();
$encrypted = $ciEncryption->encrypt('mi_contraseña');
```

#### `decrypt($data, array $params = null)`
Desencripta datos previamente encriptados.

```php
$ciEncryption = new CodeIgniterEncryption();
$decrypted = $ciEncryption->decrypt($encryptedString);
```

#### `encode($string, $key = '')` (Compatibilidad)
Alias para `encrypt()` - mantiene compatibilidad con código anterior.

```php
$ciEncryption = new CodeIgniterEncryption();
$encrypted = $ciEncryption->encode('mi_contraseña');
```

#### `decode($string, $key = '')` (Compatibilidad)
Alias para `decrypt()` - mantiene compatibilidad con código anterior.

```php
$ciEncryption = new CodeIgniterEncryption();
$decrypted = $ciEncryption->decode($encryptedString);
```

#### `verifyPassword($plainPassword, $encryptedPassword)`
Verifica si una contraseña en texto plano coincide con su versión encriptada.

```php
$ciEncryption = new CodeIgniterEncryption();
$isValid = $ciEncryption->verifyPassword('mi_contraseña', $encryptedPassword);
```

#### `encryptPassword($password)`
Encripta una contraseña para almacenamiento.

```php
$ciEncryption = new CodeIgniterEncryption();
$encryptedPassword = $ciEncryption->encryptPassword('mi_contraseña');
```

## Uso en AuthController

El `AuthController` ya está configurado para usar esta encriptación en el método `verificarAccesoLogin()`. La verificación de contraseña se realiza automáticamente usando la clase `CodeIgniterEncryption`.

### Ejemplo de uso en el login:

```php
// En el método verificarAccesoLogin
$ciEncryption = new CodeIgniterEncryption();
if (!$ciEncryption->verifyPassword($No_Password, $usuario->No_Password)) {
    return [
        'sStatus' => 'warning',
        'sMessage' => 'Contraseña incorrecta'
    ];
}
```

## Configuración avanzada

### Parámetros personalizados

Puedes personalizar la encriptación pasando parámetros:

```php
$ciEncryption = new CodeIgniterEncryption();

// Encriptar con parámetros personalizados
$encrypted = $ciEncryption->encrypt('datos', [
    'cipher' => 'aes-256',
    'mode' => 'cbc',
    'hmac' => false, // Deshabilitar HMAC
    'raw_data' => true // No usar base64
]);

// Desencriptar con parámetros personalizados
$decrypted = $ciEncryption->decrypt($encrypted, [
    'cipher' => 'aes-256',
    'mode' => 'cbc',
    'hmac' => false,
    'raw_data' => true
]);
```

### Crear claves aleatorias

```php
$ciEncryption = new CodeIgniterEncryption();
$randomKey = $ciEncryption->create_key(32); // 32 bytes
```

### HKDF (Key Derivation Function)

```php
$ciEncryption = new CodeIgniterEncryption();
$derivedKey = $ciEncryption->hkdf(
    $masterKey,
    'sha512',
    $salt,
    32,
    'contexto'
);
```

## Compatibilidad

- **PHP**: 7.3+ (usa OpenSSL)
- **Laravel**: 8.x+
- **CodeIgniter**: 3.x compatible
- **Algoritmo**: AES-128-CBC con HMAC-SHA512

## Notas importantes

1. **Seguridad**: Esta implementación usa OpenSSL y algoritmos modernos de CodeIgniter 3.x.

2. **Clave**: La clave de encriptación está hardcodeada en la clase. En producción, considera moverla a variables de entorno.

3. **Compatibilidad**: Esta implementación es compatible con datos encriptados por CodeIgniter 3.x usando la misma clave.

4. **HMAC**: Por defecto se usa HMAC-SHA512 para autenticación. Puedes deshabilitarlo pasando `'hmac' => false`.

5. **Base64**: Por defecto los datos se codifican en base64. Puedes usar datos raw pasando `'raw_data' => true`.

## Ejemplo completo

```php
<?php

use App\Helpers\CodeIgniterEncryption;

// Crear instancia
$ciEncryption = new CodeIgniterEncryption();

// Encriptar
$texto = "Hola mundo";
$encriptado = $ciEncryption->encrypt($texto);
echo "Encriptado: " . $encriptado . "\n";

// Desencriptar
$desencriptado = $ciEncryption->decrypt($encriptado);
echo "Desencriptado: " . $desencriptado . "\n";

// Verificar contraseña
$password = "mi_contraseña";
$passwordEncriptado = $ciEncryption->encryptPassword($password);
$esValido = $ciEncryption->verifyPassword($password, $passwordEncriptado);
echo "Contraseña válida: " . ($esValido ? "Sí" : "No") . "\n";

// Usar métodos de compatibilidad
$encriptado2 = $ciEncryption->encode($texto);
$desencriptado2 = $ciEncryption->decode($encriptado2);
echo "Compatibilidad: " . ($texto === $desencriptado2 ? "✅" : "❌") . "\n";
```

## Comando de prueba

Puedes probar la funcionalidad usando el comando Artisan:

```bash
php artisan test:encryption
php artisan test:encryption "mi_texto_personalizado"
``` 