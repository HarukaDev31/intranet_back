<?php

namespace App\Support\SoporteTi;

use App\Models\SoporteTi\SoporteTiSolicitud;

/**
 * Textos WhatsApp al grupo Soporte TI (plantilla MSJS.docx).
 * Tipo A = proyectos (PRY). Tipo B = requerimientos (INC / CFG / REQ).
 */
class SoporteTiWhatsappGrupoMensajeBuilder
{
    /**
     * @param string $evento creado|en_maqueta|en_progreso|desplegado|observado
     * @param string|null $mentionWa Número WhatsApp (51XXXXXXXXX) para etiquetar al solicitante
     */
    public static function build($evento, SoporteTiSolicitud $solicitud, $mentionWa = null)
    {
        $mentionWa = self::mentionWaNormalizado($mentionWa);

        if (self::esProyecto($solicitud)) {
            return self::buildProyecto($evento, $solicitud, $mentionWa);
        }

        return self::buildRequerimiento($evento, $solicitud, $mentionWa);
    }

    /**
     * @param string $evento
     * @param string|null $mentionWa
     * @return string|null
     */
    private static function buildProyecto($evento, SoporteTiSolicitud $solicitud, $mentionWa)
    {
        switch ($evento) {
            case 'creado':
                return self::proyectoCreado($solicitud, $mentionWa);
            case 'en_maqueta':
                return self::proyectoEnMaqueta($solicitud, $mentionWa);
            case 'en_progreso':
                return self::proyectoEnProgreso($solicitud, $mentionWa);
            case 'desplegado':
                return self::proyectoDesplegado($solicitud, $mentionWa);
            case 'observado':
                return self::proyectoObservado($solicitud, $mentionWa);
            default:
                return null;
        }
    }

    /**
     * @param string $evento
     * @param string|null $mentionWa
     * @return string|null
     */
    private static function buildRequerimiento($evento, SoporteTiSolicitud $solicitud, $mentionWa)
    {
        switch ($evento) {
            case 'creado':
                return self::ticketCreado($solicitud, $mentionWa);
            case 'en_progreso':
                return self::enProceso($solicitud, $mentionWa);
            case 'desplegado':
                return self::desplegado($solicitud, $mentionWa);
            case 'observado':
                return self::observado($solicitud, $mentionWa);
            default:
                return null;
        }
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function proyectoCreado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        return "🎫 *Soporte TI — Proyecto creado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Solicitante: ' . self::solicitanteConMencion($solicitud, $mentionWa) . "\n"
            . '➡️Área: ' . self::area($solicitud);
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function proyectoEnMaqueta(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🎨 *Soporte TI — En maqueta*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu proyecto pasó a etapa de maqueta y el equipo de TI la está revisando. Ante cualquier consulta, nos estaremos comunicando contigo.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function proyectoEnProgreso(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🔧 *Soporte TI — En progreso*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Complejidad: ' . self::complejidadEtiqueta($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', el equipo de TI ya está configurando tu proyecto.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function proyectoDesplegado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🚀 *Soporte TI — Desplegado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu proyecto ya fue subido al sistema QA. Ingresa al intranet, valida y marca Observado u Operativo.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function proyectoObservado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🚀 *Soporte TI — Observado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', se identificaron correcciones pendientes en tu proyecto, se está revisando.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function ticketCreado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        return "🎫 *Soporte TI — Ticket creado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Solicitante: ' . self::solicitanteConMencion($solicitud, $mentionWa) . "\n"
            . '➡️Área: ' . self::area($solicitud);
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function enProceso(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🔧 *Soporte TI — En proceso*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Complejidad: ' . self::complejidadEtiqueta($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu ticket ya está siendo atendido por el equipo de TI.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function desplegado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);
        $base = "🚀 *Soporte TI — Desplegado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n";

        if (self::esTicketConfiguracion($solicitud)) {
            return $base
                . '🤖 Hola ' . $nombre . ', ya se implementó la configuración solicitada. Ingresa al intranet, valida y marca Observado u Operativo.';
        }

        return $base
            . '🤖 Hola ' . $nombre . ', ya se solucionó la incidencia reportada. Ingresa al intranet, valida y marca Observado u Operativo.';
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function observado(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::saludo($solicitud, $mentionWa);

        return "🚀 *Soporte TI — Observado*\n"
            . '➡️ Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', has reportado una observación tras el despliegue, se está revisando.';
    }

    /**
     * Tipo A o código PRY- = proyecto.
     */
    private static function esProyecto(SoporteTiSolicitud $solicitud)
    {
        if (strtoupper((string) $solicitud->tipo_solicitud) === 'A') {
            return true;
        }

        $codigo = strtoupper(self::codigo($solicitud));

        return strpos($codigo, 'PRY-') === 0;
    }

    /**
     * B2 / CFG = configuración o requerimiento (REQ en plantilla).
     */
    private static function esTicketConfiguracion(SoporteTiSolicitud $solicitud)
    {
        if (strtoupper((string) $solicitud->tipo_solicitud) === 'B') {
            $sub = strtoupper(trim((string) $solicitud->subtipo_b));

            return $sub === 'B2' || $sub === 'CFG';
        }

        $codigo = strtoupper(self::codigo($solicitud));

        return strpos($codigo, 'CFG-') === 0 || strpos($codigo, 'REQ-') === 0;
    }

    /**
     * Complejidad mostrada en WhatsApp (En progreso / En proceso).
     * Tipo B persiste en criticidad; tipo A en complejidad_pm / complejidad_analista (+ criticidad espejo).
     *
     * @return string
     */
    private static function complejidadEtiqueta(SoporteTiSolicitud $solicitud)
    {
        $candidatos = strtoupper(trim((string) $solicitud->tipo_solicitud)) === 'A'
            ? array(
                trim((string) $solicitud->complejidad_pm),
                trim((string) $solicitud->complejidad_analista),
                trim((string) $solicitud->criticidad),
            )
            : array(
                trim((string) $solicitud->criticidad),
                trim((string) $solicitud->complejidad_analista),
            );

        foreach ($candidatos as $valor) {
            if ($valor !== '' && stripos($valor, 'definir') === false) {
                return $valor;
            }
        }

        return 'Por definir';
    }

    /**
     * @return string
     */
    private static function primerNombre(SoporteTiSolicitud $solicitud)
    {
        $solicitante = self::solicitante($solicitud);
        if ($solicitante === '') {
            return 'equipo';
        }

        $partes = preg_split('/\s+/u', $solicitante);

        return $partes[0] !== '' ? $partes[0] : $solicitante;
    }

    /**
     * @param mixed $mentionWa
     * @return string|null
     */
    private static function mentionWaNormalizado($mentionWa)
    {
        if (!is_string($mentionWa)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mentionWa);

        return $digits !== '' ? $digits : null;
    }

    /**
     * WhatsApp reemplaza @51XXXXXXXXX por el nombre del contacto si está en mentioned.
     *
     * @param string|null $mentionWa
     * @return string
     */
    private static function saludo(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        if ($mentionWa) {
            return '@' . $mentionWa;
        }

        return self::primerNombre($solicitud);
    }

    /**
     * @param string|null $mentionWa
     * @return string
     */
    private static function solicitanteConMencion(SoporteTiSolicitud $solicitud, $mentionWa)
    {
        $nombre = self::solicitante($solicitud);
        if (!$mentionWa) {
            return $nombre;
        }

        $tag = '@' . $mentionWa;

        return $nombre === '' ? $tag : $nombre . ' ' . $tag;
    }

    /**
     * @return string
     */
    private static function codigo(SoporteTiSolicitud $solicitud)
    {
        return trim((string) $solicitud->codigo);
    }

    /**
     * @return string
     */
    private static function titulo(SoporteTiSolicitud $solicitud)
    {
        return trim((string) $solicitud->titulo);
    }

    /**
     * @return string
     */
    private static function solicitante(SoporteTiSolicitud $solicitud)
    {
        return trim((string) $solicitud->solicitante);
    }

    /**
     * @return string
     */
    private static function area(SoporteTiSolicitud $solicitud)
    {
        return trim((string) $solicitud->area);
    }
}
