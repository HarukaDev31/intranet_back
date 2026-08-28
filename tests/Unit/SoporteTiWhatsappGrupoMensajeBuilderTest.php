<?php

namespace Tests\Unit;

use App\Models\SoporteTi\SoporteTiSolicitud;
use App\Support\SoporteTi\SoporteTiWhatsappGrupoMensajeBuilder;
use Tests\TestCase;

class SoporteTiWhatsappGrupoMensajeBuilderTest extends TestCase
{
    private function proyecto(array $attrs = []): SoporteTiSolicitud
    {
        $s = new SoporteTiSolicitud();
        $s->tipo_solicitud = 'A';
        $s->codigo = 'PRY-26-0004';
        $s->titulo = 'Módulo de reportes';
        $s->solicitante = 'Danitza López';
        $s->area = 'Importaciones';
        $s->complejidad_pm = 'Baja';
        foreach ($attrs as $k => $v) {
            $s->{$k} = $v;
        }

        return $s;
    }

    private function ticket(array $attrs = []): SoporteTiSolicitud
    {
        $s = new SoporteTiSolicitud();
        $s->tipo_solicitud = 'B';
        $s->subtipo_b = 'B1';
        $s->codigo = 'INC-26-0003';
        $s->titulo = 'Error al exportar';
        $s->solicitante = 'Danitza';
        $s->area = 'Importaciones';
        $s->complejidad_analista = 'Baja';
        foreach ($attrs as $k => $v) {
            $s->{$k} = $v;
        }

        return $s;
    }

    public function test_proyecto_creado(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('creado', $this->proyecto());
        $this->assertStringContainsString('*Soporte TI — Proyecto creado*', $msg);
        $this->assertStringContainsString('PRY-26-0004', $msg);
        $this->assertStringContainsString('Solicitante: Danitza López', $msg);
        $this->assertStringNotContainsString('Ticket creado', $msg);
    }

    public function test_proyecto_en_maqueta(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('en_maqueta', $this->proyecto());
        $this->assertStringContainsString('*Soporte TI — En maqueta*', $msg);
        $this->assertStringContainsString('pasó a etapa de maqueta', $msg);
        $this->assertStringContainsString('Hola Danitza', $msg);
    }

    public function test_proyecto_en_progreso(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('en_progreso', $this->proyecto());
        $this->assertStringContainsString('*Soporte TI — En progreso*', $msg);
        $this->assertStringContainsString('Complejidad: Baja', $msg);
        $this->assertStringContainsString('ya está configurando tu proyecto', $msg);
    }

    public function test_proyecto_desplegado(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('desplegado', $this->proyecto());
        $this->assertStringContainsString('subido al sistema QA', $msg);
        $this->assertStringNotContainsString('incidencia reportada', $msg);
    }

    public function test_proyecto_observado(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('observado', $this->proyecto());
        $this->assertStringContainsString('correcciones pendientes en tu proyecto', $msg);
    }

    public function test_ticket_no_tiene_en_maqueta(): void
    {
        $this->assertNull(SoporteTiWhatsappGrupoMensajeBuilder::build('en_maqueta', $this->ticket()));
    }

    public function test_ticket_creado_sigue_siendo_ticket(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('creado', $this->ticket());
        $this->assertStringContainsString('*Soporte TI — Ticket creado*', $msg);
        $this->assertStringNotContainsString('Proyecto creado', $msg);
    }

    public function test_etiqueta_solicitante_si_hay_telefono(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('creado', $this->proyecto(), '51987654321');
        $this->assertStringContainsString('Solicitante: Danitza López @51987654321', $msg);

        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('en_maqueta', $this->proyecto(), '51987654321');
        $this->assertStringContainsString('Hola @51987654321,', $msg);
        $this->assertStringNotContainsString('Hola Danitza,', $msg);
    }

    public function test_sin_telefono_no_cambia_el_saludo(): void
    {
        $msg = SoporteTiWhatsappGrupoMensajeBuilder::build('en_maqueta', $this->proyecto(), null);
        $this->assertStringContainsString('Hola Danitza,', $msg);
        $this->assertStringNotContainsString('@51', $msg);
    }
}
