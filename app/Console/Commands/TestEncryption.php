<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\CodeIgniterEncryption;

class TestEncryption extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:encryption {text?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba la encriptación de CodeIgniter';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $text = $this->argument('text') ?? 'test123';
        
        try {
            $ciEncryption = new CodeIgniterEncryption();
            
            $this->info('=== Prueba de Encriptación CodeIgniter ===');
            $this->line('');
            
            // Encriptar
            $this->info('Texto original: ' . $text);
            $encrypted = $ciEncryption->encode($text);
            $this->info('Texto encriptado: ' . $encrypted);
            $this->line('');
            
            // Desencriptar
            $decrypted = $ciEncryption->decode($encrypted);
            $this->info('Texto desencriptado: ' . $decrypted);
            $this->line('');
            
            // Verificar
            $isValid = $ciEncryption->verifyPassword($text, $encrypted);
            $this->info('Verificación: ' . ($isValid ? '✅ Correcto' : '❌ Incorrecto'));
            $this->line('');
            
            // Probar con contraseña incorrecta
            $wrongPassword = 'wrong123';
            $isWrongValid = $ciEncryption->verifyPassword($wrongPassword, $encrypted);
            $this->info('Verificación con contraseña incorrecta: ' . ($isWrongValid ? '❌ Error' : '✅ Correcto'));
            $this->line('');
            
            $this->info('✅ Prueba completada exitosamente');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
} 