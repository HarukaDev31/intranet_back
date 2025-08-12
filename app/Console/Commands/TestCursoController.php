<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Curso\CursoController;
use Illuminate\Http\Request;

class TestCursoController extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:curso-controller {--limit=5} {--page=1} {--search=} {--campana=} {--estado-pago=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Probar la funcionalidad del CursoController';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Probando CursoController...');

        // Crear una instancia del controlador
        $controller = new CursoController();

        // Crear una request simulada
        $request = new Request();
        $request->merge([
            'limit' => $this->option('limit'),
            'page' => $this->option('page'),
            'search' => $this->option('search'),
            'campana' => $this->option('campana'),
            'estado_pago' => $this->option('estado-pago')
        ]);

        try {
            // Probar el método index
            $this->info('Probando método index...');
            $response = $controller->index($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success']) {
                $this->info('✅ Método index funcionando correctamente');
                $this->info("Total de registros: {$data['pagination']['total']}");
                $this->info("Página actual: {$data['pagination']['current_page']}");
                $this->info("Registros por página: {$data['pagination']['per_page']}");
                
                if (!empty($data['data'])) {
                    $this->info("\nPrimer registro:");
                    $primerRegistro = $data['data'][0];
                    $this->table(
                        ['Campo', 'Valor'],
                        [
                            ['ID_Pedido_Curso', $primerRegistro['ID_Pedido_Curso'] ?? 'N/A'],
                            ['Fe_Registro', $primerRegistro['Fe_Registro'] ?? 'N/A'],
                            ['No_Entidad', $primerRegistro['No_Entidad'] ?? 'N/A'],
                            ['Estado Pago', $primerRegistro['estado_pago'] ?? 'N/A'],
                            ['Total Pagos', $primerRegistro['total_pagos'] ?? 'N/A']
                        ]
                    );
                }
            } else {
                $this->error('❌ Error en método index: ' . ($data['message'] ?? 'Error desconocido'));
                if (isset($data['error'])) {
                    $this->error('Detalle del error: ' . $data['error']);
                }
            }

            // Probar el método filterOptions
            $this->info('\nProbando método filterOptions...');
            $response = $controller->filterOptions();
            $data = json_decode($response->getContent(), true);

            if ($data['success']) {
                $this->info('✅ Método filterOptions funcionando correctamente');
                $this->info("Campañas disponibles: " . count($data['data']['campanas']));
                $this->info("Estados de pago: " . count($data['data']['estados_pago']));
                $this->info("Tipos de curso: " . count($data['data']['tipos_curso']));
            } else {
                $this->error('❌ Error en método filterOptions: ' . ($data['message'] ?? 'Error desconocido'));
            }

        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            $this->error('Archivo: ' . $e->getFile());
            $this->error('Línea: ' . $e->getLine());
        }
    }
} 