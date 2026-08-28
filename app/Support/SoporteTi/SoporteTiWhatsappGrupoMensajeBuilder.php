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
     */
    public static function build($evento, SoporteTiSolicitud $solicitud)
    {
        if (self::esProyecto($solicitud)) {
            return self::buildProyecto($evento, $solicitud);
        }

        return self::buildRequerimiento($evento, $solicitud);
    }

    /**
     * @param string $evento
     * @return string|null
     */
    private static function buildProyecto($evento, SoporteTiSolicitud $solicitud)
    {
        switch ($evento) {
            case 'creado':
                return self::proyectoCreado($solicitud);
            case 'en_maqueta':
                return self::proyectoEnMaqueta($solicitud);
            case 'en_progreso':
                return self::proyectoEnProgreso($solicitud);
            case 'desplegado':
                return self::proyectoDesplegado($solicitud);
            case 'observado':
                return self::proyectoObservado($solicitud);
            default:
                return null;
        }
    }

    /**
     * @param string $evento
     * @return string|null
     */
    private static function buildRequerimiento($evento, SoporteTiSolicitud $solicitud)
    {
        switch ($evento) {
            case 'creado':
                return self::ticketCreado($solicitud);
            case 'en_progreso':
                return self::enProceso($solicitud);
            case 'desplegado':
                return self::desplegado($solicitud);
            case 'observado':
                return self::observado($solicitud);
            default:
                return null;
        }
    }

    /**
     * @return string
     */
    private static function proyectoCreado(SoporteTiSolicitud $solicitud)
    {
        return "🎫 *Soporte TI — Proyecto creado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Solicitante: ' . self::solicitante($solicitud) . "\n"
            . '➡️Área: ' . self::area($solicitud);
    }

    /**
     * @return string
     */
    private static function proyectoEnMaqueta(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

        return "🎨 *Soporte TI — En maqueta*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu proyecto pasó a etapa de maqueta y el equipo de TI la está revisando. Ante cualquier consulta, nos estaremos comunicando contigo.';
    }

    /**
     * @return string
     */
    private static function proyectoEnProgreso(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

        return "🔧 *Soporte TI — En progreso*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Complejidad: ' . self::complejidadEtiqueta($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', el equipo de TI ya está configurando tu proyecto.';
    }

    /**
     * @return string
     */
    private static function proyectoDesplegado(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

        return "🚀 *Soporte TI — Desplegado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu proyecto ya fue subido al sistema QA. Ingresa al intranet, valida y marca Observado u Operativo.';
    }

    /**
     * @return string
     */
    private static function proyectoObservado(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

        return "🚀 *Soporte TI — Observado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', se identificaron correcciones pendientes en tu proyecto, se está revisando.';
    }

    /**
     * @return string
     */
    private static function ticketCreado(SoporteTiSolicitud $solicitud)
    {
        return "🎫 *Soporte TI — Ticket creado*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Solicitante: ' . self::solicitante($solicitud) . "\n"
            . '➡️Área: ' . self::area($solicitud);
    }

    /**
     * @return string
     */
    private static function enProceso(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

        return "🔧 *Soporte TI — En proceso*\n"
            . '➡️Código: ' . self::codigo($solicitud) . "\n"
            . '➡️Título: ' . self::titulo($solicitud) . "\n"
            . '➡️Complejidad: ' . self::complejidadEtiqueta($solicitud) . "\n"
            . '🤖 Hola ' . $nombre . ', tu ticket ya está siendo atendido por el equipo de TI.';
    }

    /**
     * @return string
     */
    private static function desplegado(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);
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
     * @return string
     */
    private static function observado(SoporteTiSolicitud $solicitud)
    {
        $nombre = self::primerNombre($solicitud);

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
     * @return string
     */
    private static function complejidadEtiqueta(SoporteTiSolicitud $solicitud)
    {
        $valor = strtoupper(trim((string) $solicitud->tipo_solicitud)) === 'A'
            ? trim((string) $solicitud->complejidad_pm)
            : trim((string) $solicitud->complejidad_analista);

        if ($valor === '' || stripos($valor, 'definir') !== false) {
            return 'Por definir';
        }

        return $valor;
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
