<?php

namespace App\Console\Commands;

use App\Services\ManualUsuario\ManualUsuarioCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Regenera capítulos de manual en lenguaje de usuario (sin stubs TODO / sin imágenes rotas).
 */
class ManualCurateRolesCommand extends Command
{
    protected $signature = 'manual:curate-roles
                            {--only= : Solo un slug (ej. administracion)}
                            {--keep-cotizador : No tocar el piloto Cotizador ya curado}';

    protected $description = 'Reescribe manuals por rol con texto de usuario final según menús reales';

    public function handle(ManualUsuarioCatalogService $catalog): int
    {
        $only = $this->option('only');
        $keepCotizador = (bool) $this->option('keep-cotizador');

        foreach ($catalog->roles() as $role) {
            $slug = $role['slug'] ?? null;
            $idGrupo = (int) ($role['id_grupo'] ?? 0);
            $nombre = (string) ($role['nombre'] ?? $slug);
            if (!$slug || $idGrupo <= 0) {
                continue;
            }
            if ($only && $only !== $slug) {
                continue;
            }
            if ($keepCotizador && $slug === 'cotizador') {
                $this->line("Skip cotizador (piloto)");
                continue;
            }

            $menus = $this->leafMenusForGrupo($idGrupo);
            $roleDir = $catalog->roleDir($slug);
            File::ensureDirectoryExists($roleDir);
            File::ensureDirectoryExists($catalog->screenshotsDir($slug));

            // Limpiar md previos
            foreach (File::files($roleDir) as $file) {
                if (str_ends_with(strtolower($file->getFilename()), '.md')) {
                    File::delete($file->getPathname());
                }
            }

            $meta = [
                'slug' => $slug,
                'id_grupo' => $idGrupo,
                'nombre' => $nombre,
                'descripcion' => 'Guía práctica del rol ' . $nombre . ' en la intranet.',
                'curated' => true,
            ];
            File::put($roleDir . DIRECTORY_SEPARATOR . '_meta.yaml', Yaml::dump($meta, 4, 2));

            $order = 1;
            $writtenKeys = [];
            foreach ($menus as $menu) {
                $url = trim((string) ($menu->url_intranet_v2 ?: $menu->No_Menu_Url ?: ''));
                if ($this->shouldSkip($url, (string) $menu->No_Menu)) {
                    continue;
                }

                $guide = $this->guideForUrl($url, (string) $menu->No_Menu);
                $dedupeKey = $guide['key'];
                if (isset($writtenKeys[$dedupeKey])) {
                    continue;
                }
                $writtenKeys[$dedupeKey] = true;

                $fileSlug = $catalog->slugifyGrupoNombre($dedupeKey);
                $filename = str_pad((string) $order, 2, '0', STR_PAD_LEFT) . '-' . $fileSlug . '.md';
                File::put($roleDir . DIRECTORY_SEPARATOR . $filename, $guide['markdown']);
                $order++;
            }

            // Capítulos transversales útiles
            if (!isset($writtenKeys['soporte-ti'])) {
                // ok si no tiene menú
            }

            $this->info("{$nombre} ({$slug}): " . ($order - 1) . ' capítulos');
        }

        return 0;
    }

    private function shouldSkip(string $url, string $titulo): bool
    {
        $u = mb_strtolower(trim($url, '/#'));
        $t = mb_strtolower($titulo);
        if ($u === '' || $u === '#') {
            return true;
        }
        if ($u === 'manual-usuario' || str_contains($t, 'manual de usuario')) {
            return true;
        }
        if ($u === 'iniciocontroller' || $u === '/') {
            return true; // cubierto en global
        }

        return false;
    }

    private function leafMenusForGrupo(int $idGrupo): array
    {
        $padres = DB::select(
            "SELECT DISTINCT MNU.ID_Menu, MNU.No_Menu, MNU.No_Menu_Url, MNU.url_intranet_v2, MNU.Nu_Orden
             FROM menu AS MNU
             JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
             JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
             WHERE MNU.ID_Padre = 0 AND MNU.Nu_Activo = 0 AND GRPUSR.ID_Grupo = ?
             ORDER BY MNU.Nu_Orden, MNU.ID_Menu",
            [$idGrupo]
        );

        $result = [];
        foreach ($padres as $padre) {
            $hijos = DB::select(
                "SELECT DISTINCT MNU.ID_Menu, MNU.No_Menu, MNU.No_Menu_Url, MNU.url_intranet_v2, MNU.Nu_Orden
                 FROM menu AS MNU
                 JOIN menu_acceso AS MNUACCESS ON (MNU.ID_Menu = MNUACCESS.ID_Menu)
                 JOIN grupo_usuario AS GRPUSR ON (GRPUSR.ID_Grupo_Usuario = MNUACCESS.ID_Grupo_Usuario)
                 WHERE MNU.ID_Padre = ? AND MNU.Nu_Activo = 0 AND GRPUSR.ID_Grupo = ?
                 ORDER BY MNU.Nu_Orden, MNU.ID_Menu",
                [$padre->ID_Menu, $idGrupo]
            );
            if (count($hijos) > 0) {
                foreach ($hijos as $hijo) {
                    $hijo->No_Menu = $padre->No_Menu . ' → ' . $hijo->No_Menu;
                    $result[] = $hijo;
                }
            } else {
                $result[] = $padre;
            }
        }

        return $result;
    }

    /**
     * @return array{key:string,markdown:string}
     */
    private function guideForUrl(string $url, string $tituloMenu): array
    {
        $u = mb_strtolower(trim(str_replace('\\', '/', $url), '/'));

        $guides = [
            'cargaconsolidada/abiertos' => [
                'key' => 'carga-abiertos',
                'title' => 'Carga consolidada — Abiertos',
                'para' => 'Ver y trabajar los contenedores de carga consolidada que siguen en curso.',
                'pasos' => [
                    'Entra a **Carga Consolidada → Abiertos**.',
                    'Filtra por año o estado si hay muchas cargas.',
                    'Abre una carga con el ícono del ojo para ver el detalle según tu rol (clientes, prospectos, documentación, etc.).',
                    'Usa los botones de la pantalla de detalle (guardar, crear, exportar) solo si aparecen para tu usuario.',
                ],
                'despues' => 'Los cambios quedan en esa carga y otros equipos los verán según su propio flujo.',
                'problemas' => [
                    'No ves una carga: revisa filtros o si está en Completados.',
                    'Falta un botón: puede depender del estado de la carga o de tus permisos.',
                ],
            ],
            'cargaconsolidada/completados' => [
                'key' => 'carga-completados',
                'title' => 'Carga consolidada — Completados',
                'para' => 'Consultar cargas ya cerradas y revisar su información histórica.',
                'pasos' => [
                    'Entra a **Carga Consolidada → Completados**.',
                    'Busca o filtra la carga que necesitas.',
                    'Ábrela para consultar el detalle (sin alterar el flujo de una carga abierta, salvo que el sistema te permita alguna acción puntual).',
                ],
                'despues' => 'Sirve para consultas, reportes y seguimiento de cargas terminadas.',
                'problemas' => ['Si no aparece, prueba otro año o limpia filtros.'],
            ],
            'cotizaciones' => [
                'key' => 'cotizaciones',
                'title' => 'Cotizaciones (Calculadora)',
                'para' => 'Crear y dar seguimiento a cotizaciones de importación para tus clientes.',
                'pasos' => [
                    'Entra a **Cotizador / Cotizaciones**.',
                    'Usa la búsqueda y filtros (fechas, estado, vendedor, campaña).',
                    'Pulsa **Crear Cotización** o edita una existente (si no está confirmada).',
                    'Completa los pasos de la calculadora y finaliza.',
                ],
                'despues' => 'La cotización queda con un estado (pendiente, cotizado, confirmado) y puede vincularse luego a una carga.',
                'problemas' => ['No puedes editar: suele estar confirmada.', 'Faltan datos del cliente o costos.'],
            ],
            'basedatos/clientes' => [
                'key' => 'clientes',
                'title' => 'Clientes',
                'para' => 'Consultar la ficha e historial de clientes.',
                'pasos' => [
                    'Entra a **Clientes**.',
                    'Busca por nombre, documento o usa filtros de servicio/categoría.',
                    'Abre el detalle para ver datos de contacto e historial.',
                ],
                'despues' => 'Usas esta información al cotizar, verificar pagos o dar seguimiento.',
                'problemas' => ['Sin resultados: amplía filtros.', 'Algunas acciones de carga masiva solo las ven otros roles.'],
            ],
            'basedatos/productos' => [
                'key' => 'productos',
                'title' => 'Aduanas — Productos',
                'para' => 'Consultar el historial de productos importados para orientar cotizaciones o trámites.',
                'pasos' => [
                    'Entra a **Aduanas → Productos**.',
                    'Busca y filtra el producto.',
                    'Revisa el detalle o exporta si el botón está disponible.',
                ],
                'despues' => 'Te ayuda a responder al cliente con información de productos ya trabajados.',
                'problemas' => ['Producto no encontrado: prueba otro término o pide apoyo a Documentación.'],
            ],
            'basedatos/regulaciones' => [
                'key' => 'regulaciones',
                'title' => 'Aduanas — Regulaciones',
                'para' => 'Consultar reglas aduaneras (antidumping, permisos, etiquetado, documentos especiales).',
                'pasos' => [
                    'Entra a **Aduanas → Regulaciones**.',
                    'Usa las pestañas del módulo.',
                    'Abre el detalle de la norma que necesitas.',
                ],
                'despues' => 'Usas la información para asesorar; la creación de normas suele ser de Documentación.',
                'problemas' => ['No puedes crear: es normal según tu rol.'],
            ],
            'basedatos/permisos' => [
                'key' => 'permisos',
                'title' => 'Aduanas — Permisos',
                'para' => 'Revisar trámites/permisos aduaneros asociados a productos o clientes.',
                'pasos' => [
                    'Entra a **Aduanas → Permisos**.',
                    'Busca y filtra por estado.',
                    'Abre el registro para consultar el detalle.',
                ],
                'despues' => 'Sirve para seguimiento y consulta; altas/ediciones pueden estar limitadas a otros roles.',
                'problemas' => ['Sin permiso para crear/editar: avisa a Documentación o Coordinación.'],
            ],
            'basedatos/boletin-quimico' => [
                'key' => 'boletin-quimico',
                'title' => 'Aduanas — Boletín químico',
                'para' => 'Registrar o consultar boletines químicos vinculados a clientes/consolidados.',
                'pasos' => [
                    'Entra a **Aduanas → Boletín Químico**.',
                    'Busca por cliente o consolidado.',
                    'Usa **Nuevo** si tu rol lo permite.',
                ],
                'despues' => 'El boletín queda disponible para el equipo que continúa el trámite.',
                'problemas' => ['No aparece **Nuevo**: tu rol solo consulta.'],
            ],
            'soporte-ti' => [
                'key' => 'soporte-ti',
                'title' => 'Soporte TI',
                'para' => 'Reportar incidencias o pedidos al área de sistemas y seguir tus tickets.',
                'pasos' => [
                    'Entra a **Soporte**.',
                    'Busca por código o título; filtra por tipo si aplica.',
                    'Pulsa **Nueva solicitud**, describe el problema con claridad y envía.',
                    'Abre el ticket para ver respuestas y adjuntar evidencias.',
                ],
                'despues' => 'TI toma el ticket y te avisa por el mismo módulo o notificaciones.',
                'problemas' => ['No ves tu ticket: revisa filtros.', 'Urgencias: indícalo en la descripción y avisa por el canal interno del equipo.'],
            ],
            'news' => [
                'key' => 'noticias',
                'title' => 'Noticias',
                'para' => 'Leer avisos y novedades publicadas en la intranet.',
                'pasos' => [
                    'Entra a **Noticias**.',
                    'Revisa el listado y abre la novedad que te interese.',
                    'Si hay botón “Ver más”, úsalo para ir al módulo relacionado.',
                ],
                'despues' => 'Te mantienes al día con cambios del sistema o del negocio.',
                'problemas' => ['Lista vacía: aún no hay publicaciones.'],
            ],
            'noticias' => [
                'key' => 'noticias',
                'title' => 'Noticias',
                'para' => 'Leer avisos y novedades publicadas en la intranet.',
                'pasos' => [
                    'Entra a **Noticias**.',
                    'Revisa el listado y abre la novedad que te interese.',
                ],
                'despues' => 'Te mantienes al día con cambios del sistema o del negocio.',
                'problemas' => ['Lista vacía: aún no hay publicaciones.'],
            ],
            'viaticos' => [
                'key' => 'viaticos',
                'title' => 'Mis reintegros / Viáticos',
                'para' => 'Registrar y dar seguimiento a tus reintegros o viáticos.',
                'pasos' => [
                    'Entra a **Viáticos / Mis Reintegros**.',
                    'Revisa pendientes o completados según el submenú.',
                    'Crea un nuevo registro si el botón está disponible y adjunta evidencias.',
                ],
                'despues' => 'El área responsable revisa y cambia el estado hasta completarlo.',
                'problemas' => ['Falta evidencia: el sistema o el revisor te lo pedirán.'],
            ],
            'viaticos/pendientes' => [
                'key' => 'viaticos-pendientes',
                'title' => 'Viáticos — Pendientes',
                'para' => 'Ver reintegros/viáticos que aún no están cerrados.',
                'pasos' => [
                    'Entra a **Viáticos → Pendientes**.',
                    'Revisa el listado y abre el detalle.',
                    'Completa lo que falte (datos o evidencias) si te lo solicitan.',
                ],
                'despues' => 'Cuando se aprueba o paga, el registro pasa a Completados.',
                'problemas' => ['No avanza: revisa observaciones del aprobador.'],
            ],
            'viaticos/completados' => [
                'key' => 'viaticos-completados',
                'title' => 'Viáticos — Completados',
                'para' => 'Consultar reintegros/viáticos ya finalizados.',
                'pasos' => [
                    'Entra a **Viáticos → Completados**.',
                    'Busca el registro y ábrelo si necesitas el detalle o evidencias.',
                ],
                'despues' => 'Sirve como historial de lo ya procesado.',
                'problemas' => ['No encuentras uno: verifica fechas o filtros.'],
            ],
            'copiloto' => [
                'key' => 'copiloto',
                'title' => 'Copiloto',
                'para' => 'Atender conversaciones comerciales con ayuda de contexto y un tablero de avance.',
                'pasos' => [
                    'Entra a **Copiloto**.',
                    'Usa **Mi cola** para chats y **Pipeline** para el tablero.',
                    'Busca el contacto, responde, usa plantillas o programa mensajes si aplica.',
                ],
                'despues' => 'El seguimiento comercial queda organizado; las cotizaciones formales siguen en Cotizador / Carga consolidada.',
                'problemas' => ['Sin conversaciones: sincroniza o revisa permisos.'],
            ],
            'mi-progreso' => [
                'key' => 'mi-progreso',
                'title' => 'Mi progreso',
                'para' => 'Ver el avance de tus actividades y dejar notas.',
                'pasos' => [
                    'Entra a **Mi Progreso**.',
                    'Filtra por fechas si hace falta.',
                    'Actualiza estados y guarda notas.',
                ],
                'despues' => 'Tu avance queda registrado para ti y tu equipo según permisos.',
                'problemas' => ['Tabla vacía: amplía el rango de fechas.'],
            ],
            'calendar' => [
                'key' => 'calendario',
                'title' => 'Calendario / Progreso',
                'para' => 'Organizar actividades y revisar progreso.',
                'pasos' => [
                    'Entra al **Calendario** o **Progreso** desde el menú.',
                    'Revisa actividades del período.',
                    'Actualiza lo que te corresponda (sin crear configuración de jefatura si no tienes ese permiso).',
                ],
                'despues' => 'El equipo ve el estado actualizado de las actividades.',
                'problemas' => ['No puedes crear actividades: puede ser limitación de rol.'],
            ],
            'verificacion' => [
                'key' => 'verificacion',
                'title' => 'Verificación',
                'para' => 'Revisar y validar pagos o comprobantes pendientes de verificación.',
                'pasos' => [
                    'Entra a **Verificación**.',
                    'Filtra los registros pendientes.',
                    'Abre el detalle, revisa evidencias y marca el estado que corresponda (aprobar / observar) si tienes permiso.',
                ],
                'despues' => 'El pago o trámite continúa según el resultado de la verificación.',
                'problemas' => ['Falta evidencia: solicita al área o cliente lo necesario antes de cerrar.'],
            ],
            'coordinacion/whatsapp-inbox' => [
                'key' => 'chat-whatsapp',
                'title' => 'Chat / WhatsApp (Coordinación)',
                'para' => 'Atender el buzón de WhatsApp de coordinación con clientes o contactos.',
                'pasos' => [
                    'Entra a **Chat** (bandeja WhatsApp).',
                    'Selecciona una conversación.',
                    'Responde, adjunta o usa las acciones disponibles en la bandeja.',
                ],
                'despues' => 'La conversación queda actualizada para el equipo de coordinación.',
                'problemas' => ['No ves chats: permisos o sincronización del canal.'],
            ],
            'inspeccionados' => [
                'key' => 'inspeccionados',
                'title' => 'Inspeccionados',
                'para' => 'Revisar proveedores o cargas en estado de inspección.',
                'pasos' => [
                    'Entra a **Inspeccionados**.',
                    'Filtra y abre el registro.',
                    'Completa o valida la información requerida por tu rol.',
                ],
                'despues' => 'El flujo avanza al siguiente estado cuando corresponde.',
                'problemas' => ['Sin datos de inspección: espera carga de archivos o aviso de almacén.'],
            ],
            'panel-acceso' => [
                'key' => 'panel-acceso',
                'title' => 'Panel de acceso',
                'para' => 'Administrar usuarios, cargos y permisos de menú (solo perfiles autorizados).',
                'pasos' => [
                    'Entra a **Panel Acceso**.',
                    'Elige usuarios, cargos o menús.',
                    'Edita con cuidado: los cambios afectan lo que cada persona ve en la intranet.',
                ],
                'despues' => 'El usuario verá el nuevo menú al volver a iniciar sesión o refrescar permisos.',
                'problemas' => ['No guardes cambios de prueba en producción sin confirmar.'],
            ],
        ];

        // match exact or prefix
        if (isset($guides[$u])) {
            return $this->renderGuide($guides[$u], $tituloMenu);
        }
        foreach ($guides as $pattern => $guide) {
            if (str_starts_with($u, $pattern)) {
                return $this->renderGuide($guide, $tituloMenu);
            }
        }
        // partial contains
        foreach (['cargaconsolidada/abiertos', 'cargaconsolidada/completados', 'cotizaciones', 'basedatos/clientes', 'basedatos/productos', 'basedatos/regulaciones', 'basedatos/permisos', 'basedatos/boletin', 'soporte-ti', 'viaticos/pendientes', 'viaticos/completados', 'viaticos', 'copiloto', 'mi-progreso', 'verificacion', 'whatsapp-inbox', 'news', 'noticias', 'calendar', 'inspeccionados', 'panel-acceso'] as $pattern) {
            if (str_contains($u, str_replace('basedatos/boletin', 'boletin', $pattern)) || str_contains($u, $pattern)) {
                $key = $pattern;
                if (isset($guides[$key])) {
                    return $this->renderGuide($guides[$key], $tituloMenu);
                }
            }
        }

        // generic but user-friendly (no stub language, no fake screenshots)
        $title = preg_replace('/\s*→\s*/u', ' — ', $tituloMenu) ?: $tituloMenu;
        $md = "# {$title}\n\n";
        $md .= "## Para qué sirve\n\n";
        $md .= "Usar el módulo **{$title}** desde tu menú para las tareas diarias de tu rol.\n\n";
        $md .= "## Cómo usarlo\n\n";
        $md .= "1. Entra desde el menú a **{$tituloMenu}**.\n";
        $md .= "2. Revisa el listado y usa la búsqueda o filtros si aparecen.\n";
        $md .= "3. Abre un registro (ojo o clic en la fila) para ver el detalle.\n";
        $md .= "4. Usa los botones visibles (**Guardar**, **Crear**, **Exportar**, etc.) solo cuando los necesites.\n\n";
        $md .= "## Qué ocurre después\n\n";
        $md .= "Los cambios o consultas quedan en ese módulo para ti y para los equipos que compartan el flujo.\n\n";
        $md .= "## Si algo no funciona\n\n";
        $md .= "- No ves un botón: puede ser por permisos o por el estado del registro.\n";
        $md .= "- No encuentras un dato: limpia filtros o amplía la búsqueda.\n";
        $md .= "- Sigue fallando: crea un ticket en **Soporte TI**.\n";

        return [
            'key' => 'mod-' . substr(md5($u ?: $tituloMenu), 0, 10),
            'markdown' => $md,
        ];
    }

    /**
     * @param array{key:string,title:string,para:string,pasos:array<int,string>,despues:string,problemas:array<int,string>} $guide
     * @return array{key:string,markdown:string}
     */
    private function renderGuide(array $guide, string $tituloMenu): array
    {
        $md = "# {$guide['title']}\n\n";
        $md .= "## Para qué sirve\n\n{$guide['para']}\n\n";
        $md .= "## Cómo usarlo\n\n";
        foreach ($guide['pasos'] as $i => $paso) {
            $md .= ($i + 1) . '. ' . $paso . "\n";
        }
        $md .= "\n## Qué ocurre después\n\n{$guide['despues']}\n\n";
        $md .= "## Si algo no funciona\n\n";
        foreach ($guide['problemas'] as $p) {
            $md .= '- ' . $p . "\n";
        }
        $md .= "\n> Menú: **{$tituloMenu}**\n";

        return ['key' => $guide['key'], 'markdown' => $md];
    }
}
