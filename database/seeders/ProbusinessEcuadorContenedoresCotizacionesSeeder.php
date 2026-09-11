<?php

namespace Database\Seeders;

use App\Http\Controllers\CargaConsolidada\ContenedorController;
use App\Models\CargaConsolidada\Contenedor;
use App\Models\CargaConsolidada\ContenedorPasos;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\Organizacion;
use App\Models\Usuario;
use App\Services\CalculadoraImportacion\CodeSupplierHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos de demo para org 2 (Probusiness Ecuador): consolidados abiertos
 * y cotizaciones resumen (COTIZADO + CONFIRMADO).
 *
 * Idempotente: re-ejecutar no duplica contenedores ni cotizaciones.
 *
 * php artisan db:seed --class=ProbusinessEcuadorContenedoresCotizacionesSeeder
 */
class ProbusinessEcuadorContenedoresCotizacionesSeeder extends Seeder
{
    private const ID_ORGANIZACION = 2;
    private const EMPRESA_SEED = 'SEED Probusiness Ecuador';
    private const TIPO_CLIENTE_NUEVO = 1;
    private const LOTE_DESDE = 3;
    private const LOTE_HASTA = 22;

    private const MESES = [
        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE',
    ];

    private const NOMBRES = [
        'Ana', 'Carlos', 'Diego', 'Elena', 'Fernando', 'Gabriela', 'Hugo', 'Isabel',
        'Javier', 'Karla', 'Luis', 'Monica', 'Nicolas', 'Olga', 'Pablo', 'Rosa',
        'Santiago', 'Tania', 'Victor', 'Wendy', 'Xavier', 'Yadira', 'Andres', 'Beatriz',
    ];

    private const APELLIDOS = [
        'Perez', 'Mendoza', 'Salazar', 'Rios', 'Vega', 'Leon', 'Castro', 'Morales',
        'Guerrero', 'Navarro', 'Ortiz', 'Paredes', 'Quintero', 'Reyes', 'Suarez', 'Torres',
        'Valencia', 'Zambrano', 'Aguilar', 'Bustos', 'Cevallos', 'Dominguez', 'Espinoza', 'Flores',
    ];

    private const PRODUCTOS = [
        'Lamparas LED y accesorios',
        'Baterias y cargadores',
        'Ropa deportiva y calzado',
        'Cosmeticos y cuidado personal',
        'Envases y packaging',
        'Herramientas y ferreteria',
        'Muebles plegables y hogar',
        'Juguetes y articulos infantiles',
        'Electrodomesticos pequenos',
        'Accesorios para celular',
        'Textiles para hogar',
        'Articulos de oficina',
    ];

    public function run(): void
    {
        $org = Organizacion::query()->find(self::ID_ORGANIZACION);
        if (!$org) {
            $this->command->error('No existe la organización 2 (Probusiness Ecuador).');
            return;
        }

        $vendedores = Usuario::query()
            ->where('ID_Organizacion', self::ID_ORGANIZACION)
            ->where('Nu_Estado', 1)
            ->orderBy('ID_Usuario')
            ->get();
        if ($vendedores->isEmpty()) {
            $this->command->error('No hay usuarios activos en la organización 2.');
            return;
        }

        $idPaisEcuador = (int) DB::table('pais')->where('No_Pais', 'ECUADOR')->value('ID_Pais');
        if ($idPaisEcuador <= 0) {
            $this->command->error('No se encontró el país ECUADOR.');
            return;
        }

        $vendedorPrincipal = $vendedores->firstWhere('No_Usuario', 'johanna@probusinessecuador.com')
            ?: $vendedores->first();
        $vendedorSecundario = $vendedores->firstWhere('ID_Usuario', '!=', $vendedorPrincipal->getKey())
            ?: $vendedorPrincipal;
        $vendedoresIds = [
            'principal' => (int) $vendedorPrincipal->getKey(),
            'secundario' => (int) $vendedorSecundario->getKey(),
        ];

        DB::disableQueryLog();

        foreach ($this->definicionesContenedoresDemo() as $def) {
            $contenedor = $this->obtenerOCrearContenedor($def, $idPaisEcuador);
            $this->asegurarPasos($contenedor);
            $this->sembrarLote($contenedor, $def['cotizaciones'], $org, $vendedoresIds);
        }

        for ($n = self::LOTE_DESDE; $n <= self::LOTE_HASTA; $n++) {
            $qty = 30 + (($n * 11) % 21);
            $def = $this->definicionLote($n);
            $contenedor = $this->obtenerOCrearContenedor($def, $idPaisEcuador);
            $this->asegurarPasos($contenedor);
            $cotizaciones = $this->cotizacionesLote($n, $qty);
            $creadas = $this->sembrarLote($contenedor, $cotizaciones, $org, $vendedoresIds);
            $this->command->info(
                $def['carga'] . ' (id ' . $contenedor->getKey() . '): '
                . $qty . ' cotizaciones objetivo, ' . $creadas . ' nuevas.'
            );
        }

        $this->command->info('Seeder Probusiness Ecuador: contenedores y cotizaciones listos.');
    }

    private function definicionLote(int $n): array
    {
        $mesIndex = ($n - 1) % 12;
        $year = 2026 + (int) floor(($n - 1) / 12);
        $month = $mesIndex + 1;

        return [
            'carga' => 'EC-SEED-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'mes' => self::MESES[$mesIndex],
            'f_inicio' => sprintf('%04d-%02d-01', $year, $month),
            'f_cierre' => sprintf('%04d-%02d-25', $year, $month),
            'f_puerto' => sprintf('%04d-%02d-15', $year, $month === 12 ? 1 : $month + 1),
            'f_entrega' => sprintf('%04d-%02d-22', $year, $month === 12 ? 1 : $month + 1),
        ];
    }

    private function cotizacionesLote(int $n, int $qty): array
    {
        $estadosCliente = ['RESERVADO', 'DOCUMENTACION'];
        $items = [];
        for ($i = 1; $i <= $qty; $i++) {
            $confirmada = ($i % 5) !== 0 && ($i % 3) !== 0 ? false : ($i % 2 === 0);
            if ($i % 5 === 0 || $i % 3 === 0) {
                $confirmada = $i % 2 === 0;
            } else {
                $confirmada = $i % 4 === 0;
            }
            $dosProveedores = $i % 5 === 0;
            $conImo = $i % 7 === 0;
            $proveedores = [
                $this->proveedorDesdeIndice($n, $i, 1, $conImo),
            ];
            if ($dosProveedores) {
                $proveedores[] = $this->proveedorDesdeIndice($n, $i, 2, false);
            }

            $items[] = [
                'estado' => $confirmada ? 'CONFIRMADO' : 'COTIZADO',
                'vendedor' => $i % 2 === 0 ? 'secundario' : 'principal',
                'estado_cliente' => $estadosCliente[$i % 2],
                'cliente' => $this->clienteLote($n, $i),
                'tarifa' => 70 + ($i % 25),
                'proveedores' => $proveedores,
            ];
        }

        return $items;
    }

    private function clienteLote(int $n, int $i): array
    {
        $nombre = self::NOMBRES[($n + $i) % count(self::NOMBRES)]
            . ' '
            . self::APELLIDOS[($n * 3 + $i) % count(self::APELLIDOS)]
            . ' EC'
            . str_pad((string) $n, 2, '0', STR_PAD_LEFT)
            . '-'
            . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

        return [
            'nombre' => $nombre,
            'documento' => '09' . sprintf('%02d', $n) . sprintf('%06d', $i),
            'correo' => 'seed.ec' . $n . '.' . $i . '@example.com',
            'telefono' => '59398' . sprintf('%02d', $n) . sprintf('%04d', $i),
        ];
    }

    private function proveedorDesdeIndice(int $n, int $i, int $slot, bool $conImo): array
    {
        $producto = self::PRODUCTOS[($n + $i + $slot) % count(self::PRODUCTOS)];
        $cbm = round(1.2 + (($n + $i + $slot) % 80) / 10, 2);
        $cbmImo = $conImo ? round(min(0.9, $cbm * 0.25), 2) : 0.0;
        $peso = 60 + (($n + $i + $slot) % 40) * 8;
        $cajas = 8 + (($i + $slot) % 30);
        $unidades = $cajas * (4 + ($slot % 5));
        $fob = 400 + (($n * 20) + ($i * 15) + ($slot * 80));
        $logistica = 80 + (($i + $slot) % 20) * 12;
        $impuesto = 60 + (($i + $slot) % 18) * 10;

        return $this->proveedor($producto, $cbm, $cbmImo, $peso, $cajas, $unidades, $fob, $logistica, $impuesto);
    }

    private function definicionesContenedoresDemo(): array
    {
        return [
            [
                'carga' => 'EC-SEED-1',
                'mes' => 'SEPTIEMBRE',
                'f_inicio' => '2026-09-01',
                'f_cierre' => '2026-09-25',
                'f_puerto' => '2026-10-15',
                'f_entrega' => '2026-10-22',
                'cotizaciones' => [
                    [
                        'estado' => 'COTIZADO',
                        'vendedor' => 'principal',
                        'cliente' => [
                            'nombre' => 'Ana Lucia Perez',
                            'documento' => '0912345678',
                            'correo' => 'ana.perez.seed@example.com',
                            'telefono' => '593987650001',
                        ],
                        'tarifa' => 85,
                        'proveedores' => [
                            $this->proveedor('Lámparas LED y accesorios', 2.40, 0, 180, 24, 120, 1850, 420, 310),
                        ],
                    ],
                    [
                        'estado' => 'COTIZADO',
                        'vendedor' => 'secundario',
                        'cliente' => [
                            'nombre' => 'Diego Salazar',
                            'documento' => '0923456789',
                            'correo' => 'diego.salazar.seed@example.com',
                            'telefono' => '593987650002',
                        ],
                        'tarifa' => 90,
                        'proveedores' => [
                            $this->proveedor('Baterías y cargadores IMO', 3.10, 0.80, 260, 18, 90, 2400, 510, 380),
                        ],
                    ],
                    [
                        'estado' => 'CONFIRMADO',
                        'vendedor' => 'principal',
                        'estado_cliente' => 'RESERVADO',
                        'cliente' => [
                            'nombre' => 'Carlos Mendoza',
                            'documento' => '0934567890',
                            'correo' => 'carlos.mendoza.seed@example.com',
                            'telefono' => '593987650003',
                        ],
                        'tarifa' => 80,
                        'proveedores' => [
                            $this->proveedor('Ropa deportiva y calzado', 4.50, 0, 320, 40, 200, 3600, 720, 540),
                        ],
                    ],
                    [
                        'estado' => 'CONFIRMADO',
                        'vendedor' => 'secundario',
                        'estado_cliente' => 'DOCUMENTACION',
                        'cliente' => [
                            'nombre' => 'Maria Fernanda Rios',
                            'documento' => '0945678901',
                            'correo' => 'maria.rios.seed@example.com',
                            'telefono' => '593987650004',
                        ],
                        'tarifa' => 75,
                        'proveedores' => [
                            $this->proveedor('Cosméticos y cuidado personal', 1.80, 0, 95, 16, 80, 980, 210, 160),
                            $this->proveedor('Envases y packaging', 1.20, 0, 70, 12, 60, 640, 150, 110),
                        ],
                    ],
                ],
            ],
            [
                'carga' => 'EC-SEED-2',
                'mes' => 'OCTUBRE',
                'f_inicio' => '2026-10-01',
                'f_cierre' => '2026-10-30',
                'f_puerto' => '2026-11-18',
                'f_entrega' => '2026-11-25',
                'cotizaciones' => [
                    [
                        'estado' => 'COTIZADO',
                        'vendedor' => 'principal',
                        'cliente' => [
                            'nombre' => 'Andres Vega',
                            'documento' => '0956789012',
                            'correo' => 'andres.vega.seed@example.com',
                            'telefono' => '593987650005',
                        ],
                        'tarifa' => 88,
                        'proveedores' => [
                            $this->proveedor('Herramientas y ferretería', 5.20, 0, 410, 32, 160, 4100, 830, 620),
                        ],
                    ],
                    [
                        'estado' => 'CONFIRMADO',
                        'vendedor' => 'principal',
                        'estado_cliente' => 'RESERVADO',
                        'cliente' => [
                            'nombre' => 'Patricia Leon',
                            'documento' => '0967890123',
                            'correo' => 'patricia.leon.seed@example.com',
                            'telefono' => '593987650006',
                        ],
                        'tarifa' => 82,
                        'proveedores' => [
                            $this->proveedor('Muebles plegables y hogar', 6.00, 0, 480, 20, 80, 5200, 980, 740),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function proveedor(
        string $productos,
        float $cbmTotal,
        float $cbmImo,
        float $peso,
        int $cajas,
        int $unidades,
        float $fob,
        float $logistica,
        float $impuesto
    ): array {
        return [
            'productos' => $productos,
            'cbm_total' => $cbmTotal,
            'cbm_imo' => $cbmImo,
            'peso' => $peso,
            'qty_box' => $cajas,
            'unidades' => $unidades,
            'incoterm' => 'FOB',
            'moneda' => 'USD',
            'costos' => [
                ['concepto' => 'Valor de Mercaderia', 'valor' => $fob],
                ['concepto' => 'Flete y Logistica', 'valor' => $logistica],
                ['concepto' => 'Tributos e Impuestos Aduaneros', 'valor' => $impuesto],
            ],
        ];
    }

    private function obtenerOCrearContenedor(array $def, int $idPais): Contenedor
    {
        $existente = Contenedor::query()
            ->where('organizacion_id', self::ID_ORGANIZACION)
            ->where('carga', $def['carga'])
            ->first();
        if ($existente) {
            return $existente;
        }

        return Contenedor::create([
            'mes' => $def['mes'],
            'id_pais' => $idPais,
            'organizacion_id' => self::ID_ORGANIZACION,
            'carga' => $def['carga'],
            'empresa' => self::EMPRESA_SEED,
            'estado' => 'PENDIENTE',
            'estado_china' => 'PENDIENTE',
            'estado_documentacion' => 'PENDIENTE',
            'estado_finanzas' => 'PENDIENTE',
            'tipo_carga' => 'CARGA CONSOLIDADA',
            'f_inicio' => $def['f_inicio'],
            'f_cierre' => $def['f_cierre'],
            'f_puerto' => $def['f_puerto'],
            'f_entrega' => $def['f_entrega'],
            'limite_cbm_imo' => 100,
        ]);
    }

    private function asegurarPasos(Contenedor $contenedor): void
    {
        $existe = ContenedorPasos::query()
            ->where('id_pedido', $contenedor->getKey())
            ->exists();
        if ($existe) {
            return;
        }

        app(ContenedorController::class)->generateSteps($contenedor->getKey());
    }

    /**
     * @return int Cantidad de cotizaciones nuevas insertadas
     */
    private function sembrarLote(
        Contenedor $contenedor,
        array $cotizaciones,
        Organizacion $org,
        array $vendedoresIds
    ): int {
        $idContenedor = (int) $contenedor->getKey();
        $existentes = Cotizacion::query()
            ->where('id_contenedor', $idContenedor)
            ->whereNull('deleted_at')
            ->pluck('nombre')
            ->flip();

        $pendientes = [];
        foreach ($cotizaciones as $def) {
            if (!isset($existentes[$def['cliente']['nombre']])) {
                $pendientes[] = $def;
            }
        }
        if ($pendientes === []) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $nombreOrg = (string) $org->getAttribute('No_Organizacion');
        $carga = (string) $contenedor->getAttribute('carga');

        $cotRows = [];
        foreach ($pendientes as $def) {
            $confirmada = $def['estado'] === 'CONFIRMADO';
            $totales = $this->sumarCostos($def['proveedores']);
            $cbmFull = 0.0;
            $cbmImo = 0.0;
            $pesoTotal = 0.0;
            $qtyItem = 0;
            foreach ($def['proveedores'] as $prov) {
                $cbmFull += (float) $prov['cbm_total'];
                $cbmImo += (float) $prov['cbm_imo'];
                $pesoTotal += (float) $prov['peso'];
                $qtyItem += (int) $prov['unidades'];
            }

            $cotRows[] = [
                'organizacion_id' => self::ID_ORGANIZACION,
                'uuid' => Str::uuid()->toString(),
                'id_contenedor' => $idContenedor,
                'id_usuario' => $vendedoresIds[$def['vendedor']] ?? $vendedoresIds['principal'],
                'fecha' => $now,
                'id_tipo_cliente' => self::TIPO_CLIENTE_NUEVO,
                'nombre' => $def['cliente']['nombre'],
                'documento' => $def['cliente']['documento'],
                'correo' => $def['cliente']['correo'],
                'telefono' => $def['cliente']['telefono'],
                'estado' => $confirmada ? 'CONFIRMADO' : 'PENDIENTE',
                'estado_cotizador' => $confirmada ? 'CONFIRMADO' : 'PENDIENTE',
                'estado_resumen' => $confirmada ? 'CONFIRMADO' : 'COTIZADO',
                'estado_cliente' => $confirmada ? ($def['estado_cliente'] ?? 'RESERVADO') : null,
                'fecha_confirmacion' => $confirmada ? $now : null,
                'from_calculator' => 0,
                'volumen' => $cbmFull,
                'es_imo' => $cbmImo > 0 ? 1 : 0,
                'fob' => $totales['fob'],
                'monto' => $totales['logistica'],
                'impuestos' => $totales['impuesto'],
                'tarifa' => $def['tarifa'] ?? 0,
                'peso' => $pesoTotal,
                'qty_item' => $qtyItem,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($cotRows, 100) as $chunk) {
            DB::table('contenedor_consolidado_cotizacion')->insert($chunk);
        }

        $nombres = array_map(function ($def) {
            return $def['cliente']['nombre'];
        }, $pendientes);
        $idsPorNombre = DB::table('contenedor_consolidado_cotizacion')
            ->where('id_contenedor', $idContenedor)
            ->whereIn('nombre', $nombres)
            ->whereNull('deleted_at')
            ->pluck('id', 'nombre');

        $provRows = [];
        foreach ($pendientes as $def) {
            $idCotizacion = (int) $idsPorNombre->get($def['cliente']['nombre']);
            if ($idCotizacion <= 0) {
                continue;
            }
            $confirmada = $def['estado'] === 'CONFIRMADO';
            $next = 1;
            foreach ($def['proveedores'] as $slot => $prov) {
                $cbmImoProv = (float) $prov['cbm_imo'];
                $row = [
                    'organizacion_id' => self::ID_ORGANIZACION,
                    'id_cotizacion' => $idCotizacion,
                    'id_contenedor' => $idContenedor,
                    'modo_cotizacion' => 'resumen',
                    'cbm_total' => max(0, (float) $prov['cbm_total'] - $cbmImoProv),
                    'cbm_imo' => $cbmImoProv,
                    'peso' => $prov['peso'],
                    'qty_box' => $prov['qty_box'],
                    'products' => $prov['productos'] . ' #' . $idCotizacion . '-' . ($slot + 1),
                    'estados_proveedor' => 'WAIT',
                    'tipo_rotulado' => 'pendiente',
                    'code_supplier' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($confirmada) {
                    $row['code_supplier'] = CodeSupplierHelper::generateWithOrgPrefix(
                        $nombreOrg,
                        $def['cliente']['nombre'],
                        $carga,
                        $next
                    );
                    $next++;
                }
                $provRows[] = $row;
            }
        }

        foreach (array_chunk($provRows, 100) as $chunk) {
            DB::table('contenedor_consolidado_cotizacion_proveedores')->insert($chunk);
        }

        $idsCotizacion = $idsPorNombre->values()->all();
        $proveedores = DB::table('contenedor_consolidado_cotizacion_proveedores')
            ->whereIn('id_cotizacion', $idsCotizacion)
            ->where('modo_cotizacion', 'resumen')
            ->get(['id', 'id_cotizacion', 'products']);

        $defsPorCotizacion = [];
        foreach ($pendientes as $def) {
            $idCotizacion = (int) $idsPorNombre->get($def['cliente']['nombre']);
            $defsPorCotizacion[$idCotizacion] = $def;
        }

        $resumenRows = [];
        foreach ($proveedores as $proveedor) {
            $def = $defsPorCotizacion[$proveedor->id_cotizacion] ?? null;
            if (!$def) {
                continue;
            }
            $slot = 0;
            if (preg_match('/#\d+-(\d+)$/', (string) $proveedor->products, $m)) {
                $slot = ((int) $m[1]) - 1;
            }
            $prov = $def['proveedores'][$slot] ?? $def['proveedores'][0];
            $inversion = 0.0;
            foreach ($prov['costos'] as $costo) {
                $inversion += (float) $costo['valor'];
            }
            $unidades = (int) $prov['unidades'];
            $resumenRows[] = [
                'organizacion_id' => self::ID_ORGANIZACION,
                'id_contenedor' => $idContenedor,
                'id_cotizacion' => $proveedor->id_cotizacion,
                'id_proveedor' => $proveedor->id,
                'producto' => $prov['productos'],
                'volumen_cbm' => $prov['cbm_total'],
                'unidades' => $unidades,
                'incoterm' => $prov['incoterm'],
                'costo_unitario_estimado' => $unidades > 0 ? round($inversion / $unidades, 4) : null,
                'inversion_total' => $inversion,
                'moneda' => $prov['moneda'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($resumenRows, 100) as $chunk) {
            DB::table('cotizacion_proveedor_resumen')->insert($chunk);
        }

        $resumenes = DB::table('cotizacion_proveedor_resumen')
            ->whereIn('id_cotizacion', $idsCotizacion)
            ->get(['id', 'id_cotizacion', 'id_proveedor']);

        $costoRows = [];
        foreach ($resumenes as $resumen) {
            $def = $defsPorCotizacion[$resumen->id_cotizacion] ?? null;
            if (!$def) {
                continue;
            }
            $prov = null;
            foreach ($proveedores as $proveedor) {
                if ((int) $proveedor->id === (int) $resumen->id_proveedor) {
                    $slot = 0;
                    if (preg_match('/#\d+-(\d+)$/', (string) $proveedor->products, $m)) {
                        $slot = ((int) $m[1]) - 1;
                    }
                    $prov = $def['proveedores'][$slot] ?? $def['proveedores'][0];
                    break;
                }
            }
            if (!$prov) {
                continue;
            }
            foreach ($prov['costos'] as $orden => $costo) {
                $costoRows[] = [
                    'organizacion_id' => self::ID_ORGANIZACION,
                    'id_cotizacion_proveedor_resumen' => $resumen->id,
                    'concepto' => $costo['concepto'],
                    'orden' => $orden,
                    'valor' => $costo['valor'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($costoRows, 200) as $chunk) {
            DB::table('cotizacion_proveedor_resumen_costo')->insert($chunk);
        }

        return count($pendientes);
    }

    private function sumarCostos(array $proveedores): array
    {
        $fob = 0.0;
        $logistica = 0.0;
        $impuesto = 0.0;
        foreach ($proveedores as $prov) {
            foreach ($prov['costos'] as $costo) {
                $concepto = mb_strtolower((string) $costo['concepto']);
                $valor = (float) $costo['valor'];
                if (strpos($concepto, 'mercader') !== false || strpos($concepto, 'fob') !== false) {
                    $fob += $valor;
                    continue;
                }
                if (strpos($concepto, 'impuest') !== false || strpos($concepto, 'tribut') !== false) {
                    $impuesto += $valor;
                    continue;
                }
                $logistica += $valor;
            }
        }

        return ['fob' => $fob, 'logistica' => $logistica, 'impuesto' => $impuesto];
    }
}
