<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Obtiene cumpleaños (Fe_Nacimiento) de la tabla usuario por una lista de números (Nu_Celular).
 * Los números pueden venir del Excel Reporte_Numeros_CON_UN_SOLO_NOMBRE_FINAL.xlsx.
 *
 * Uso:
 *   php artisan cumpleanos:por-numeros 987654321 912345678 997351212
 * o con archivo de texto (un número por línea):
 *   php artisan cumpleanos:por-numeros --file=numeros.txt
 */
class CumpleanosPorNumeros extends Command
{
    protected $signature = 'cumpleanos:por-numeros
                            {numeros?* : Números de teléfono separados por espacio}
                            {--file= : Ruta a archivo con un número por línea}';

    protected $description = 'Lista cumpleaños de usuarios por números de teléfono (Nu_Celular)';

    public function handle(): int
    {
        $numeros = $this->argument('numeros');
        $file = $this->option('file');

        if ($file) {
            if (!is_readable($file)) {
                $this->error("No se puede leer el archivo: {$file}");
                return 1;
            }
            $numeros = array_filter(array_map('trim', file($file)));
        }

        if (empty($numeros)) {
            $this->info('Uso: php artisan cumpleanos:por-numeros 987654321 912345678');
            $this->info('     php artisan cumpleanos:por-numeros --file=numeros.txt');
            return 0;
        }

        $numerosLimpios = array_map(function ($n) {
            return preg_replace('/[^0-9]/', '', $n);
        }, $numeros);
        $numerosSin51 = array_map(function ($n) {
            return preg_replace('/^51/', '', $n);
        }, $numerosLimpios);

        $usuarios = DB::table('usuario')
            ->select('ID_Usuario', 'No_Nombres_Apellidos', 'Nu_Celular', 'Fe_Nacimiento', 'Txt_Email')
            ->whereNotNull('Fe_Nacimiento')
            ->where(function ($q) use ($numerosLimpios, $numerosSin51) {
                foreach ($numerosLimpios as $i => $n) {
                    if ($n === '') {
                        continue;
                    }
                    $s = $numerosSin51[$i];
                    $q->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nu_Celular, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?',
                        ["%{$n}%"]
                    );
                    if ($s !== $n) {
                        $q->orWhereRaw(
                            'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nu_Celular, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?',
                            ["%{$s}%"]
                        );
                    }
                }
            })
            ->orderBy('No_Nombres_Apellidos')
            ->get();

        if ($usuarios->isEmpty()) {
            $this->warn('No se encontraron usuarios con Fe_Nacimiento para esos números.');
            return 0;
        }

        $this->table(
            ['ID_Usuario', 'Nombres', 'Nu_Celular', 'Fe_Nacimiento', 'Email'],
            $usuarios->map(fn ($u) => [$u->ID_Usuario, $u->No_Nombres_Apellidos, $u->Nu_Celular, $u->Fe_Nacimiento, $u->Txt_Email ?? ''])
        );

        $this->info('Total: ' . $usuarios->count() . ' usuario(s).');
        return 0;
    }
}
