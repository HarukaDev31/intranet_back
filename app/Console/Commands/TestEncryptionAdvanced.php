<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\CodeIgniterEncryption;

class TestEncryptionAdvanced extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:encryption-advanced';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pruebas avanzadas de la encriptación de CodeIgniter';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $ciEncryption = new CodeIgniterEncryption();
            
            $this->info('=== Pruebas Avanzadas de Encriptación CodeIgniter 3.x ===');
            $this->line('');
            
            // Prueba 1: Encriptación básica
            $this->info('1. Prueba de encriptación básica:');
            $texto = "Hola mundo desde CodeIgniter 3.x";
            $encriptado = $ciEncryption->encrypt($texto);
            $desencriptado = $ciEncryption->decrypt($encriptado);
            $this->line("   Original: " . $texto);
            $this->line("   Encriptado: " . substr($encriptado, 0, 50) . "...");
            $this->line("   Desencriptado: " . $desencriptado);
            $this->line("   Resultado: " . ($texto === $desencriptado ? "✅ Correcto" : "❌ Error"));
            $this->line('');
            
            // Prueba 2: Métodos de compatibilidad
            $this->info('2. Prueba de métodos de compatibilidad:');
            $encriptado2 = $ciEncryption->encode($texto);
            $desencriptado2 = $ciEncryption->decode($encriptado2);
            $this->line("   Resultado: " . ($texto === $desencriptado2 ? "✅ Correcto" : "❌ Error"));
            $this->line('');
            
            // Prueba 3: Verificación de contraseñas
            $this->info('3. Prueba de verificación de contraseñas:');
            $password = "MiContraseña123!";
            $passwordEncriptado = $ciEncryption->encryptPassword($password);
            $esValido = $ciEncryption->verifyPassword($password, $passwordEncriptado);
            $esInvalido = $ciEncryption->verifyPassword("ContraseñaIncorrecta", $passwordEncriptado);
            $this->line("   Contraseña correcta: " . ($esValido ? "✅ Correcto" : "❌ Error"));
            $this->line("   Contraseña incorrecta: " . (!$esInvalido ? "✅ Correcto" : "❌ Error"));
            $this->line('');
            
            // Prueba 4: Diferentes tipos de datos
            $this->info('4. Prueba con diferentes tipos de datos:');
            $datos = [
                "Texto simple" => "Hola",
                "Texto con espacios" => "Hola mundo con espacios",
                "Texto con caracteres especiales" => "¡Hola! ¿Cómo estás? @#$%^&*()",
                "Texto largo" => str_repeat("Este es un texto muy largo para probar la encriptación. ", 10),
                "Números" => "1234567890",
                "Texto vacío" => ""
            ];
            
            foreach ($datos as $descripcion => $valor) {
                $encriptado = $ciEncryption->encrypt($valor);
                $desencriptado = $ciEncryption->decrypt($encriptado);
                $resultado = ($valor === $desencriptado) ? "✅" : "❌";
                $this->line("   {$descripcion}: {$resultado}");
            }
            $this->line('');
            
            // Prueba 5: Creación de claves aleatorias
            $this->info('5. Prueba de creación de claves aleatorias:');
            $clave16 = $ciEncryption->create_key(16);
            $clave32 = $ciEncryption->create_key(32);
            $clave64 = $ciEncryption->create_key(64);
            $this->line("   Clave 16 bytes: " . ($clave16 ? "✅ " . bin2hex($clave16) : "❌ Error"));
            $this->line("   Clave 32 bytes: " . ($clave32 ? "✅ " . bin2hex($clave32) : "❌ Error"));
            $this->line("   Clave 64 bytes: " . ($clave64 ? "✅ " . bin2hex($clave64) : "❌ Error"));
            $this->line('');
            
            // Prueba 6: HKDF
            $this->info('6. Prueba de HKDF (Key Derivation Function):');
            $masterKey = "clave_maestra_secreta";
            $salt = "salt_aleatorio";
            $derivedKey = $ciEncryption->hkdf($masterKey, 'sha512', $salt, 32, 'contexto');
            $this->line("   Clave derivada: " . ($derivedKey ? "✅ " . bin2hex($derivedKey) : "❌ Error"));
            $this->line('');
            
            // Prueba 7: Parámetros personalizados
            $this->info('7. Prueba con parámetros personalizados:');
            $texto = "Texto con parámetros personalizados";
            
            // Sin HMAC
            $encriptadoSinHmac = $ciEncryption->encrypt($texto, ['hmac' => false]);
            $desencriptadoSinHmac = $ciEncryption->decrypt($encriptadoSinHmac, ['hmac' => false]);
            $this->line("   Sin HMAC: " . ($texto === $desencriptadoSinHmac ? "✅ Correcto" : "❌ Error"));
            
            // Con datos raw (sin base64)
            $encriptadoRaw = $ciEncryption->encrypt($texto, ['raw_data' => true]);
            $desencriptadoRaw = $ciEncryption->decrypt($encriptadoRaw, ['raw_data' => true]);
            $this->line("   Datos raw: " . ($texto === $desencriptadoRaw ? "✅ Correcto" : "❌ Error"));
            $this->line('');
            
            $this->info('✅ Todas las pruebas completadas exitosamente');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
} 