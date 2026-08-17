<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCapturasAttacher;
use Illuminate\Console\Command;

class ManualAttachCapturasCommand extends Command
{
    protected $signature = 'manual:attach-capturas';

    protected $description = 'Sube capturas de resources/manual/capturas y las enlaza a bloques media del CMS';

    public function handle()
    {
        $result = (new ManualUsuarioCapturasAttacher())->attach();
        $this->info('Capturas: ' . $result['uploaded']
            . '. Enlazadas: ' . $result['linked']
            . '. Sin foto: ' . $result['skipped'] . '.');

        return 0;
    }
}
