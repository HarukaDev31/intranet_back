<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BaseDatos\ProductoRubro;
use App\Models\BaseDatos\EntidadReguladora;
use App\Models\BaseDatos\ProductoRegulacionAntidumping;
use App\Models\BaseDatos\ProductoRegulacionPermiso;
use App\Models\BaseDatos\ProductoRegulacionEtiquetado;
use App\Models\BaseDatos\ProductoRegulacionDocumentoEspecial;

class TestRegulaciones extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:regulaciones';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prueba los modelos de regulaciones de productos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== Prueba de Modelos de Regulaciones de Productos ===');
        $this->line('');

        try {
            // Probar ProductoRubro
            $this->info('1. Probando ProductoRubro:');
            $rubros = ProductoRubro::all();
            $this->line("   Total de rubros: " . $rubros->count());
            
            if ($rubros->count() > 0) {
                $rubro = $rubros->first();
                $this->line("   Primer rubro: " . $rubro->nombre);
                $this->line("   ID: " . $rubro->id);
                $this->line("   Creado: " . $rubro->created_at->format('Y-m-d H:i:s'));
            }
            $this->line('');

            // Probar EntidadReguladora
            $this->info('2. Probando EntidadReguladora:');
            $entidades = EntidadReguladora::all();
            $this->line("   Total de entidades: " . $entidades->count());
            
            if ($entidades->count() > 0) {
                $entidad = $entidades->first();
                $this->line("   Primera entidad: " . $entidad->nombre);
                $this->line("   Descripción: " . ($entidad->descripcion ?: 'Sin descripción'));
            }
            $this->line('');

            // Probar ProductoRegulacionAntidumping
            $this->info('3. Probando ProductoRegulacionAntidumping:');
            $antidumping = ProductoRegulacionAntidumping::with('rubro')->get();
            $this->line("   Total de regulaciones antidumping: " . $antidumping->count());
            
            if ($antidumping->count() > 0) {
                $reg = $antidumping->first();
                $this->line("   Primera regulación:");
                $this->line("   - Descripción: " . $reg->descripcion_producto);
                $this->line("   - Partida: " . $reg->partida);
                $this->line("   - Antidumping: " . ($reg->antidumping ?: 'No especificado'));
                $this->line("   - Rubro: " . ($reg->rubro ? $reg->rubro->nombre : 'Sin rubro'));
            }
            $this->line('');

            // Probar ProductoRegulacionPermiso
            $this->info('4. Probando ProductoRegulacionPermiso:');
            $permisos = ProductoRegulacionPermiso::with(['rubro', 'entidadReguladora'])->get();
            $this->line("   Total de regulaciones de permisos: " . $permisos->count());
            
            if ($permisos->count() > 0) {
                $permiso = $permisos->first();
                $this->line("   Primer permiso:");
                $this->line("   - Nombre: " . $permiso->nombre);
                $this->line("   - Código permiso: " . ($permiso->c_permiso ?: 'No especificado'));
                $this->line("   - Rubro: " . ($permiso->rubro ? $permiso->rubro->nombre : 'Sin rubro'));
                $this->line("   - Entidad: " . ($permiso->entidadReguladora ? $permiso->entidadReguladora->nombre : 'Sin entidad'));
            }
            $this->line('');

            // Probar ProductoRegulacionEtiquetado
            $this->info('5. Probando ProductoRegulacionEtiquetado:');
            $etiquetado = ProductoRegulacionEtiquetado::with('rubro')->get();
            $this->line("   Total de regulaciones de etiquetado: " . $etiquetado->count());
            
            if ($etiquetado->count() > 0) {
                $etiq = $etiquetado->first();
                $this->line("   Primera regulación de etiquetado:");
                $this->line("   - Observaciones: " . ($etiq->observaciones ?: 'Sin observaciones'));
                $this->line("   - Rubro: " . ($etiq->rubro ? $etiq->rubro->nombre : 'Sin rubro'));
            }
            $this->line('');

            // Probar ProductoRegulacionDocumentoEspecial
            $this->info('6. Probando ProductoRegulacionDocumentoEspecial:');
            $documentos = ProductoRegulacionDocumentoEspecial::with('rubro')->get();
            $this->line("   Total de regulaciones de documentos especiales: " . $documentos->count());
            
            if ($documentos->count() > 0) {
                $doc = $documentos->first();
                $this->line("   Primera regulación de documentos especiales:");
                $this->line("   - Observaciones: " . ($doc->observaciones ?: 'Sin observaciones'));
                $this->line("   - Rubro: " . ($doc->rubro ? $doc->rubro->nombre : 'Sin rubro'));
            }
            $this->line('');

            // Probar relaciones
            if ($rubros->count() > 0) {
                $this->info('7. Probando relaciones:');
                $rubro = $rubros->first();
                
                $this->line("   Rubro: " . $rubro->nombre);
                $this->line("   - Regulaciones antidumping: " . $rubro->regulacionesAntidumping->count());
                $this->line("   - Regulaciones de permisos: " . $rubro->regulacionesPermiso->count());
                $this->line("   - Regulaciones de etiquetado: " . $rubro->regulacionesEtiquetado->count());
                $this->line("   - Regulaciones de documentos especiales: " . $rubro->regulacionesDocumentosEspeciales->count());
            }
            $this->line('');

            $this->info('✅ Prueba completada exitosamente');

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 