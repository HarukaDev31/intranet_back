<?php

namespace App\Services;

use App\Models\CalculadoraImportacion;
use App\Models\CalculadoraImportacionProveedor;
use App\Models\CalculadoraImportacionProducto;
use App\Models\BaseDatos\Clientes\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CargaConsolidada\Cotizacion;
use App\Models\CargaConsolidada\CotizacionProveedor;
use App\Models\CargaConsolidada\CotizacionProveedorItem;
use App\Models\CargaConsolidada\FacturaComercial;
use App\Models\CalculadoraTipoCliente;
use App\Models\CalculadoraTarifasConsolidado;
use Carbon\Carbon;

class CalculadoraImportacionService
{
    public $TCAMBIO = 3.75;

    /** Número de proveedores incluidos sin cargo extra */
    const MAX_PROVEEDORES = 3;
    /** USD por cada proveedor que exceda MAX_PROVEEDORES */
    const TARIFA_EXTRA_PROVEEDOR = 50;

    /** Tarifas extra por ítem según rango de CBM (reglas de negocio calculadora) */
    const TARIFAS_EXTRA_ITEM_PER_CBM = [
        ['limit_inf' => 0.1,   'limit_sup' => 1.0,   'item_base' => 6,  'item_extra' => 4,  'tarifa' => 20],
        ['limit_inf' => 1.01,  'limit_sup' => 2.0,   'item_base' => 8,  'item_extra' => 7,  'tarifa' => 10],
        ['limit_inf' => 2.1,   'limit_sup' => 3.0,   'item_base' => 10, 'item_extra' => 5,  'tarifa' => 10],
        ['limit_inf' => 3.1,   'limit_sup' => 6.0,   'item_base' => 13, 'item_extra' => 7,  'tarifa' => 10],
        ['limit_inf' => 6.1,   'limit_sup' => 9.0,   'item_base' => 15, 'item_extra' => 5,  'tarifa' => 10],
        ['limit_inf' => 9.1,   'limit_sup' => 12.0,  'item_base' => 17, 'item_extra' => 8,  'tarifa' => 10],
        ['limit_inf' => 12.1,  'limit_sup' => 15.0,  'item_base' => 19, 'item_extra' => 6,  'tarifa' => 10],
        ['limit_inf' => 15.1,  'limit_sup' => 9999,  'item_base' => 20, 'item_extra' => 10, 'tarifa' => 10],
    ];
    public $formatoDollar = '"$"#,##0.00_-';
    public $formatoSoles = '"S/." #,##0.00_-';
    public $formatoTexto = 'General';
    /**
     * Guardar cálculo de importación completo
     */
    public function guardarCalculo(array $data): CalculadoraImportacion
    {
        try {
            DB::beginTransaction();

            // Crear o actualizar cliente si existe
            $cliente = $this->buscarOcrearCliente($data['clienteInfo']);

            // Determinar campos según tipo de documento
            $tipoDocumento = $data['clienteInfo']['tipoDocumento'] ?? 'DNI';

            // Obtener tipo_cambio del request, si es null o 0 usar 3.75 por defecto
            $tipoCambio = (!empty($data['tipo_cambio']) && $data['tipo_cambio'] > 0) ? $data['tipo_cambio'] : 3.75;

            // Crear registro principal
            $calculadora = CalculadoraImportacion::create([
                'id_cliente' => $cliente ? $cliente->id : null,
                'id_usuario' => $data['id_usuario'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'nombre_cliente' => $data['clienteInfo']['nombre'],
                'tipo_documento' => $tipoDocumento,
                'id_carga_consolidada_contenedor' => $data['id_carga_consolidada_contenedor'] ?? null,
                'dni_cliente' => $tipoDocumento === 'DNI' ? ($data['clienteInfo']['dni'] ?? null) : null,
                'ruc_cliente' => $tipoDocumento === 'RUC' ? ($data['clienteInfo']['ruc'] ?? null) : null,
                'razon_social' => $tipoDocumento === 'RUC' ? ($data['clienteInfo']['empresa'] ?? $data['clienteInfo']['razonSocial'] ?? null) : null,
                'correo_cliente' => $data['clienteInfo']['correo'] ?: null,
                'whatsapp_cliente' => is_array($data['clienteInfo']['whatsapp']) ? ($data['clienteInfo']['whatsapp']['value'] ?? null) : ($data['clienteInfo']['whatsapp'] ?? null),
                'tipo_cliente' => $data['clienteInfo']['tipoCliente'],
                'qty_proveedores' => $data['clienteInfo']['qtyProveedores'],
                'tarifa_total_extra_proveedor' => $data['tarifaTotalExtraProveedor'] ?? 0,
                'tarifa_total_extra_item' => $data['tarifaTotalExtraItem'] ?? 0,
                'tarifa' => $data['tarifa']['tarifa'] ?? 0,
                'tarifa_descuento' => $data['tarifaDescuento'] ?? 0,
                'tc' => $tipoCambio,
                'estado' => CalculadoraImportacion::ESTADO_PENDIENTE,

            ]);
            $totalProductos = 0;
            // Crear proveedores (sin códigos, se generarán solo al pasar a COTIZADO)
            foreach ($data['proveedores'] as $index => $proveedorData) {
                $proveedor = $calculadora->proveedores()->create([
                    'cbm' => $proveedorData['cbm'],
                    'peso' => $proveedorData['peso'],
                    'qty_caja' => $proveedorData['qtyCaja'],
                    'code_supplier' => null // Los códigos se generan solo al pasar a COTIZADO
                ]);

                // Crear productos del proveedor
                foreach ($proveedorData['productos'] as $productoData) {
                    $proveedor->productos()->create([
                        'nombre' => $productoData['nombre'],
                        'precio' => $productoData['precio'],
                        'valoracion' => $productoData['valoracion'] ?? 0,
                        'cantidad' => $productoData['cantidad'],
                        'antidumping_cu' => $productoData['antidumpingCU'] ?? 0,
                        'ad_valorem_p' => $productoData['adValoremP'] ?? 0
                    ]);
                    $totalProductos += 1;
                }
            }
            $data['totalProductos'] = $totalProductos;
            $data['totalExtraProveedor'] = $data['tarifaTotalExtraProveedor'];
            $data['totalExtraItem'] = $data['tarifaTotalExtraItem'];
            $data['totalDescuento'] = $data['tarifaDescuento'];

            Log::info('[CREAR COTIZACIÓN] Iniciando creación de Excel para calculadora ID: ' . $calculadora->id);
            Log::info('[CREAR COTIZACIÓN] Total productos: ' . $totalProductos);
            Log::info('[CREAR COTIZACIÓN] Datos preparados: ' . json_encode([
                'totalProductos' => $data['totalProductos'],
                'totalExtraProveedor' => $data['totalExtraProveedor'],
                'totalExtraItem' => $data['totalExtraItem'],
                'totalDescuento' => $data['totalDescuento']
            ]));

            $result = $this->crearCotizacionInicial($data);

            Log::info('[CREAR COTIZACIÓN] Resultado de crearCotizacionInicial: ' . json_encode($result));

            if (!$result || !isset($result['url'])) {
                Log::error('[CREAR COTIZACIÓN] Error: No se generó URL del Excel. Result: ' . json_encode($result));
                throw new \Exception('No se pudo generar el archivo Excel de la cotización');
            }

            $url = $result['url'];
            $totalFob = $result['totalfob'];
            $totalImpuestos = $result['totalimpuestos'];
            $logistica = $result['logistica'];
            $boletaInfo = $result['boleta'];

            Log::info('[CREAR COTIZACIÓN] Excel generado exitosamente');
            Log::info('[CREAR COTIZACIÓN] URL Excel: ' . $url);
            Log::info('[CREAR COTIZACIÓN] Total FOB: ' . $totalFob);
            Log::info('[CREAR COTIZACIÓN] Total Impuestos: ' . $totalImpuestos);
            Log::info('[CREAR COTIZACIÓN] Logística: ' . $logistica);

            if ($boletaInfo) {
                Log::info('[CREAR COTIZACIÓN] Boleta generada: ' . $boletaInfo['filename']);
            }

            $calculadora->url_cotizacion = $url;
            $calculadora->total_fob = $totalFob;
            $calculadora->total_impuestos = $totalImpuestos;
            $calculadora->logistica = $logistica;

            // Guardar URL del PDF si se generó la boleta
            if ($boletaInfo) {
                $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                Log::info('[CREAR COTIZACIÓN] Boleta PDF guardada en: ' . $boletaInfo['path']);
                Log::info('[CREAR COTIZACIÓN] URL pública del PDF: ' . $boletaInfo['url']);
            }

            Log::info('[CREAR COTIZACIÓN] Guardando calculadora con URL: ' . $url);
            $calculadora->save();
            Log::info('[CREAR COTIZACIÓN] Calculadora guardada exitosamente. ID: ' . $calculadora->id . ', URL: ' . $calculadora->url_cotizacion);

            // Guardar información de la boleta si se generó
            if ($boletaInfo) {
                // Aquí podrías guardar la información de la boleta en la base de datos
                // Por ejemplo, crear un registro en una tabla de boletas
                Log::info('Boleta PDF guardada en: ' . $boletaInfo['path']);
            }

            DB::commit();
            return $calculadora->load(['proveedores.productos']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar cálculo de importación: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar cálculo de importación existente
     */
    public function actualizarCalculo(CalculadoraImportacion $calculadora, array $data): CalculadoraImportacion
    {
        try {
            DB::beginTransaction();

            // Cargar relación de contenedor si existe
            if ($calculadora->id_carga_consolidada_contenedor) {
                $calculadora->load('contenedor');
            }

            // Actualizar cliente si existe
            $cliente = $this->buscarOcrearCliente($data['clienteInfo']);

            // Determinar campos según tipo de documento
            $tipoDocumento = $data['clienteInfo']['tipoDocumento'] ?? 'DNI';

            // Obtener tipo_cambio del request, si es null o 0 usar el valor existente o 3.75 por defecto
            $tipoCambio = (!empty($data['tipo_cambio']) && $data['tipo_cambio'] > 0) ? $data['tipo_cambio'] : ($calculadora->tc ?? 3.75);

            // Actualizar registro principal
            $calculadora->update([
                'id_cliente' => $cliente ? $cliente->id : $calculadora->id_cliente,
                'nombre_cliente' => $data['clienteInfo']['nombre'],
                'tipo_documento' => $tipoDocumento,
                'id_carga_consolidada_contenedor' => $data['id_carga_consolidada_contenedor'] ?? $calculadora->id_carga_consolidada_contenedor,
                'dni_cliente' => $tipoDocumento === 'DNI' ? ($data['clienteInfo']['dni'] ?? null) : null,
                'ruc_cliente' => $tipoDocumento === 'RUC' ? ($data['clienteInfo']['ruc'] ?? null) : null,
                'razon_social' => $tipoDocumento === 'RUC' ? ($data['clienteInfo']['empresa'] ?? $data['clienteInfo']['razonSocial'] ?? null) : null,
                'correo_cliente' => $data['clienteInfo']['correo'] ?: null,
                'whatsapp_cliente' => is_array($data['clienteInfo']['whatsapp']) ? ($data['clienteInfo']['whatsapp']['value'] ?? null) : ($data['clienteInfo']['whatsapp'] ?? null),
                'tipo_cliente' => $data['clienteInfo']['tipoCliente'],
                'qty_proveedores' => $data['clienteInfo']['qtyProveedores'],
                'tarifa_total_extra_proveedor' => $data['tarifaTotalExtraProveedor'] ?? 0,
                'tarifa_total_extra_item' => $data['tarifaTotalExtraItem'] ?? 0,
                'tarifa' => $data['tarifa']['tarifa'] ?? $calculadora->tarifa,
                'tarifa_descuento' => $data['tarifaDescuento'] ?? $calculadora->tarifa_descuento,
                'tc' => $tipoCambio,
            ]);

            // Obtener proveedores existentes con sus códigos antes de eliminarlos
            // Mapear por código para preservarlos correctamente
            $proveedoresExistentes = $calculadora->proveedores()->with('productos')->orderBy('id')->get();
            $mapaCodigosPorProveedor = [];
            $codigosPorIndice = []; // Mapear códigos por índice de orden
            foreach ($proveedoresExistentes as $index => $prov) {
                if ($prov->code_supplier) {
                    // Usar el código como clave para mapear
                    $mapaCodigosPorProveedor[$prov->code_supplier] = $prov;
                    // También mapear por índice para preservar orden
                    $codigosPorIndice[$index] = $prov->code_supplier;
                }
            }

            // Obtener códigos de proveedores que se van a mantener (si vienen en los datos)
            // Si no vienen en los datos, usar los códigos existentes por orden
            $codigosAMantener = [];
            foreach ($data['proveedores'] as $index => $proveedorData) {
                if (isset($proveedorData['code_supplier']) && !empty($proveedorData['code_supplier'])) {
                    // Si viene en los datos, usarlo
                    $codigosAMantener[] = $proveedorData['code_supplier'];
                } elseif (isset($codigosPorIndice[$index])) {
                    // Si no viene pero existe un código en esa posición, preservarlo
                    $codigosAMantener[] = $codigosPorIndice[$index];
                    // Agregar al array de datos para que se use
                    $data['proveedores'][$index]['code_supplier'] = $codigosPorIndice[$index];
                }
            }

            // Si hay cotización relacionada, eliminar proveedores de cotización que ya no existen
            if ($calculadora->id_cotizacion && !empty($mapaCodigosPorProveedor)) {
                $codigosExistentes = array_keys($mapaCodigosPorProveedor);
                $codigosAEliminar = array_diff($codigosExistentes, $codigosAMantener);

                if (!empty($codigosAEliminar)) {
                    // Eliminar proveedores de cotización que ya no están en la calculadora
                    $proveedoresCotizacion = \App\Models\CargaConsolidada\CotizacionProveedor::where('id_cotizacion', $calculadora->id_cotizacion)
                        ->whereIn('code_supplier', $codigosAEliminar)
                        ->get();

                    foreach ($proveedoresCotizacion as $provCotizacion) {
                        // Eliminar items primero
                        \App\Models\CargaConsolidada\CotizacionProveedorItem::where('id_proveedor', $provCotizacion->id)->delete();
                        // Eliminar proveedor
                        $provCotizacion->delete();
                    }

                    Log::info('Proveedores eliminados de cotización', [
                        'calculadora_id' => $calculadora->id,
                        'cotizacion_id' => $calculadora->id_cotizacion,
                        'codigos_eliminados' => $codigosAEliminar
                    ]);
                }
            }

            // Eliminar proveedores y productos existentes de la calculadora
            foreach ($calculadora->proveedores as $proveedor) {
                $proveedor->productos()->delete();
            }
            $calculadora->proveedores()->delete();

            // Crear nuevos proveedores y productos (preservar códigos existentes si vienen en los datos)
            foreach ($data['proveedores'] as $index => $proveedorData) {
                // Preservar código existente si viene en los datos (no regenerar)
                $codeSupplier = null;
                if (isset($proveedorData['code_supplier']) && !empty($proveedorData['code_supplier'])) {
                    // Si viene en los datos, preservarlo (ya fue generado al pasar a COTIZADO)
                    $codeSupplier = $proveedorData['code_supplier'];
                }
                // Si no viene código, dejarlo null (solo se genera al pasar a COTIZADO)

                $proveedor = $calculadora->proveedores()->create([
                    'cbm' => $proveedorData['cbm'] ?? 0,
                    'peso' => $proveedorData['peso'] ?? 0,
                    'qty_caja' => $proveedorData['qtyCaja'] ?? 0,
                    'code_supplier' => $codeSupplier
                ]);

                // Agregar código al array de datos para usar en Excel (si existe)
                if ($codeSupplier) {
                    $data['proveedores'][$index]['code_supplier'] = $codeSupplier;
                }

                foreach ($proveedorData['productos'] as $productoData) {
                    $proveedor->productos()->create([
                        'nombre' => $productoData['nombre'],
                        'precio' => $productoData['precio'],
                        'valoracion' => $productoData['valoracion'] ?? 0,
                        'cantidad' => $productoData['cantidad'],
                        'antidumping_cu' => $productoData['antidumpingCU'] ?? 0,
                        'ad_valorem_p' => $productoData['adValoremP'] ?? 0,
                    ]);
                }
            }

            // Recalcular totales
            $totales = $this->calcularTotales($calculadora);

            // Actualizar totales en la calculadora
            $calculadora->update([
                'total_fob' => $totales['totalFOB'] ?? 0,
                'total_impuestos' => $totales['totalImpuestos'] ?? 0,
                //extra proveedor + extra item
                'logistica' => $totales['logistica'] ?? 0,
            ]);

            // Preparar datos para regenerar Excel y boleta
            // Calcular totalProductos desde los datos recibidos, no desde la BD (porque puede no estar recargada)
            $totalProductos = 0;
            foreach ($data['proveedores'] as $proveedorData) {
                $totalProductos += count($proveedorData['productos'] ?? []);
            }
            $data['totalProductos'] = $totalProductos;
            $data['totalExtraProveedor'] = $data['tarifaTotalExtraProveedor'];
            $data['totalExtraItem'] = $data['tarifaTotalExtraItem'];
            $data['totalDescuento'] = $data['tarifaDescuento'];
            $data['id_usuario'] = $data['id_usuario'];
            $data['id_carga_consolidada_contenedor'] = $calculadora->id_carga_consolidada_contenedor;

            Log::info('[EDITAR COTIZACIÓN] Iniciando edición de Excel para calculadora ID: ' . $calculadora->id);
            Log::info('[EDITAR COTIZACIÓN] URL Excel anterior: ' . $calculadora->url_cotizacion);
            Log::info('[EDITAR COTIZACIÓN] Total productos: ' . $data['totalProductos']);
            Log::info('[EDITAR COTIZACIÓN] Datos preparados: ' . json_encode([
                'totalProductos' => $data['totalProductos'],
                'totalExtraProveedor' => $data['totalExtraProveedor'],
                'totalExtraItem' => $data['totalExtraItem'],
                'totalDescuento' => $data['totalDescuento'],
                'id_usuario' => $data['id_usuario'],
                'id_carga_consolidada_contenedor' => $data['id_carga_consolidada_contenedor']
            ]));

            // Regenerar Excel completo con las nuevas filas del resumen
            $result = $this->crearCotizacionInicial($data);

            Log::info('[EDITAR COTIZACIÓN] Resultado de crearCotizacionInicial: ' . json_encode($result));

            if (!$result || !isset($result['url'])) {
                Log::error('[EDITAR COTIZACIÓN] ERROR: No se generó URL del Excel. Result: ' . json_encode($result));
                Log::error('[EDITAR COTIZACIÓN] La calculadora mantendrá la URL anterior: ' . $calculadora->url_cotizacion);
                // No lanzar excepción, solo loggear el error para no perder los datos
            } else {
                $urlAnterior = $calculadora->url_cotizacion;
                $calculadora->url_cotizacion = $result['url'];
                $calculadora->total_fob = $result['totalfob'] ?? $calculadora->total_fob;
                $calculadora->total_impuestos = $result['totalimpuestos'] ?? $calculadora->total_impuestos;
                $calculadora->logistica = $result['logistica'] ?? $calculadora->logistica;

                Log::info('[EDITAR COTIZACIÓN] Excel regenerado exitosamente');
                Log::info('[EDITAR COTIZACIÓN] URL Excel anterior: ' . $urlAnterior);
                Log::info('[EDITAR COTIZACIÓN] URL Excel nueva: ' . $result['url']);
                Log::info('[EDITAR COTIZACIÓN] Total FOB: ' . ($result['totalfob'] ?? 'N/A'));
                Log::info('[EDITAR COTIZACIÓN] Total Impuestos: ' . ($result['totalimpuestos'] ?? 'N/A'));
                Log::info('[EDITAR COTIZACIÓN] Logística: ' . ($result['logistica'] ?? 'N/A'));

                // Regenerar PDF con el Excel actualizado
                if (isset($result['boleta'])) {
                    $boletaInfo = $result['boleta'];
                    Log::info('[EDITAR COTIZACIÓN] Boleta incluida en resultado: ' . json_encode($boletaInfo));
                } else {
                    Log::info('[EDITAR COTIZACIÓN] Generando boleta por separado...');
                    $boletaInfo = $this->generateBoleta($calculadora->url_cotizacion, $data['clienteInfo']);
                    Log::info('[EDITAR COTIZACIÓN] Resultado de generateBoleta: ' . json_encode($boletaInfo));
                }

                if ($boletaInfo && isset($boletaInfo['path'])) {
                    $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                    Log::info('[EDITAR COTIZACIÓN] Boleta PDF actualizada: ' . $boletaInfo['url']);
                }

                Log::info('[EDITAR COTIZACIÓN] Guardando calculadora con nueva URL: ' . $calculadora->url_cotizacion);
                $calculadora->save();

                if ($calculadora->id_cotizacion && $calculadora->url_cotizacion) {
                    Cotizacion::where('id', $calculadora->id_cotizacion)->update([
                        'cotizacion_file_url' => $calculadora->url_cotizacion,
                    ]);
                    Log::info('[EDITAR COTIZACIÓN] cotizacion_file_url actualizado en cotización ID: ' . $calculadora->id_cotizacion);
                }

                Log::info('[EDITAR COTIZACIÓN] Calculadora guardada exitosamente. ID: ' . $calculadora->id . ', URL: ' . $calculadora->url_cotizacion);
            }

            DB::commit();
            return $calculadora->load(['proveedores.productos']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar cálculo de importación: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar o crear cliente basado en la información proporcionada
     */
    private function buscarOcrearCliente(array $clienteInfo): ?Cliente
    {
        if (empty($clienteInfo['dni'])) {
            return null;
        }

        $cliente = Cliente::where('documento', $clienteInfo['dni'])->first();

        if (!$cliente) {
            $cliente = Cliente::create([
                'nombre' => $clienteInfo['nombre'],
                'documento' => $clienteInfo['dni'],
                'correo' => $clienteInfo['correo'] ?: null,
                'telefono' => $clienteInfo['whatsapp']['value'] ?? null,
                'tipo_cliente' => $clienteInfo['tipoCliente'] ?? 'NUEVO'
            ]);
        }

        return $cliente;
    }

    /**
     * Obtener cálculo por ID
     */
    public function obtenerCalculo(int $id): ?CalculadoraImportacion
    {
        return CalculadoraImportacion::with(['proveedores.productos', 'cliente'])->find($id);
    }

    /**
     * Obtener cálculos por cliente
     */
    public function obtenerCalculosPorCliente(string $dni): \Illuminate\Database\Eloquent\Collection
    {
        return CalculadoraImportacion::with(['proveedores.productos'])
            ->where('dni_cliente', $dni)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calcular totales del cálculo
     */
    public function calcularTotales(CalculadoraImportacion $calculadora): array
    {
        $totales = [
            'total_cbm' => 0,
            'total_peso' => 0,
            'total_productos' => 0,
            'valor_total_productos' => 0,
            'total_antidumping' => 0,
            'total_ad_valorem' => 0,
            'tarifa_total_extra_proveedor' => $calculadora->tarifa_total_extra_proveedor,
            'tarifa_total_extra_item' => $calculadora->tarifa_total_extra_item,
            'tarifa_descuento' => $calculadora->tarifa_descuento
        ];

        foreach ($calculadora->proveedores as $proveedor) {
            $totales['total_cbm'] += round($proveedor->cbm, 2);
            $totales['total_peso'] += $proveedor->peso;

            foreach ($proveedor->productos as $producto) {
                $totales['total_productos'] += 1;
                $totales['valor_total_productos'] += $producto->valor_total;
                $totales['total_antidumping'] += $producto->total_antidumping;
                $totales['total_ad_valorem'] += $producto->total_ad_valorem;
            }
        }
        $totales['total_cbm'] = round($totales['total_cbm'], 2);
        $totales['total_peso'] = round($totales['total_peso'], 2);
        return $totales;
    }

    /**
     * Eliminar cálculo y todos sus datos relacionados
     */
    public function eliminarCalculo(int $id): bool
    {
        DB::beginTransaction();
        try {
            $calculadora = CalculadoraImportacion::findOrFail($id);

            // Si la calculadora tiene cotización, eliminar primero los registros relacionados
            if ($calculadora->id_cotizacion) {
                $cotizacionId = $calculadora->id_cotizacion;

                // Obtener los proveedores de la cotización
                $proveedores = CotizacionProveedor::where('id_cotizacion', $cotizacionId)->get();

                // Eliminar items de proveedores primero
                foreach ($proveedores as $proveedor) {
                    CotizacionProveedorItem::where('id_proveedor', $proveedor->id)->delete();
                }

                // Eliminar proveedores
                CotizacionProveedor::where('id_cotizacion', $cotizacionId)->delete();

                // Eliminar facturas comerciales
                FacturaComercial::where('quotation_id', $cotizacionId)->delete();

                // Eliminar la cotización
                Cotizacion::where('id', $cotizacionId)->delete();
            }

            // Eliminar la calculadora (esto eliminará en cascada proveedores y productos)
            $calculadora->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar cálculo de importación: ' . $e->getMessage(), [
                'calculadora_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }


    /**
     * Función alternativa usando PhpSpreadsheet para obtener columna +n
     * @param string $column Columna inicial (ej: 'A', 'C', 'Z')
     * @param int $increment Cantidad a incrementar
     * @return string Nueva columna
     */
    private function getColumnByIndex(string $column, int $increment = 1): string
    {
        // Convertir columna a índice numérico
        $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);

        // Sumar el incremento
        $newColumnIndex = $columnIndex + $increment;

        // Convertir de vuelta a letra de columna
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($newColumnIndex);
    }

    /**
     * Resolver tipo de cliente por WhatsApp: busca en BD de clientes y determina categoría por servicios.
     * Si no encuentra cliente o no hay servicios, devuelve NUEVO.
     */
    public function getTipoClientePorWhatsapp(string $whatsapp): string
    {
        $telefonoNormalizado = preg_replace('/[\s\-\(\)\.\+]/', '', $whatsapp);
        if (preg_match('/^51(\d{9})$/', $telefonoNormalizado, $m)) {
            $telefonoNormalizado = $m[1];
        }
        $clientes = Cliente::where('telefono', '!=', null)
            ->where('telefono', '!=', '')
            ->where(function ($q) use ($whatsapp, $telefonoNormalizado) {
                $q->where('telefono', 'like', '%' . $whatsapp . '%');
                if (!empty($telefonoNormalizado)) {
                    $q->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefono, " ", ""), "-", ""), "(", ""), ")", ""), "+", "") LIKE ?', ["%{$telefonoNormalizado}%"]);
                }
            })
            ->limit(5)
            ->get();
        if ($clientes->isEmpty()) {
            return 'NUEVO';
        }
        $clienteIds = $clientes->pluck('id')->toArray();
        $serviciosPorCliente = $this->obtenerServiciosEnLoteParaExport($clienteIds);
        foreach ($clienteIds as $cid) {
            $servicios = $serviciosPorCliente[$cid] ?? [];
            $categoria = $this->determinarCategoriaClienteExport($servicios);
            if ($categoria !== 'NUEVO' || count($servicios) > 0) {
                return $categoria;
            }
        }
        return 'NUEVO';
    }

    private function obtenerServiciosEnLoteParaExport(array $clienteIds): array
    {
        if (empty($clienteIds)) {
            return [];
        }
        $serviciosPorCliente = [];
        $pedidosCurso = DB::table('pedido_curso as pc')
            ->join('entidad as e', 'pc.ID_Entidad', '=', 'e.ID_Entidad')
            ->where('pc.Nu_Estado', 2)
            ->whereIn('pc.id_cliente', $clienteIds)
            ->select('pc.id_cliente', 'e.Fe_Registro as fecha', DB::raw("'Curso' as servicio"), DB::raw('NULL as monto'))
            ->get();
        $cotizaciones = DB::table('contenedor_consolidado_cotizacion')
            ->where('estado_cotizador', 'CONFIRMADO')
            ->whereIn('id_cliente', $clienteIds)
            ->select('id_cliente', 'fecha', DB::raw("'Consolidado' as servicio"), 'monto')
            ->get();
        foreach ($pedidosCurso as $p) {
            $serviciosPorCliente[$p->id_cliente][] = ['servicio' => $p->servicio, 'fecha' => $p->fecha, 'monto' => $p->monto];
        }
        foreach ($cotizaciones as $c) {
            $serviciosPorCliente[$c->id_cliente][] = ['servicio' => $c->servicio, 'fecha' => $c->fecha, 'monto' => $c->monto];
        }
        foreach ($serviciosPorCliente as &$s) {
            usort($s, function ($a, $b) { return strtotime($a['fecha']) - strtotime($b['fecha']); });
        }
        return $serviciosPorCliente;
    }

    private function determinarCategoriaClienteExport(array $servicios): string
    {
        $n = count($servicios);
        if ($n === 0) {
            return 'NUEVO';
        }
        if ($n === 1) {
            return 'RECURRENTE';
        }
        $ultimo = end($servicios);
        $fechaUltimo = Carbon::parse($ultimo['fecha']);
        $meses = $fechaUltimo->diffInMonths(Carbon::now());
        if ($meses > 6) {
            return 'INACTIVO';
        }
        if ($n >= 2) {
            $primero = $servicios[0];
            $frecuencia = Carbon::parse($primero['fecha'])->diffInMonths($fechaUltimo) / ($n - 1);
            if ($frecuencia <= 2 && $meses <= 2) {
                return 'PREMIUM';
            }
            if ($meses <= 6) {
                return 'RECURRENTE';
            }
        }
        return 'INACTIVO';
    }

    /**
     * Tarifa principal por tipo de cliente y CBM total (desde calculadora_tarifas_consolidado).
     */
    public function getTarifaPrincipalPorTipoYCbm(string $tipoCliente, float $cbmTotal): float
    {
        $tipo = CalculadoraTipoCliente::where('nombre', $tipoCliente)->first();
        if (!$tipo) {
            $tipo = CalculadoraTipoCliente::where('nombre', 'NUEVO')->first();
        }
        if (!$tipo) {
            return 375.0;
        }
        $tarifa = CalculadoraTarifasConsolidado::where('calculadora_tipo_cliente_id', $tipo->id)
            ->where('limit_inf', '<=', $cbmTotal)
            ->where('limit_sup', '>=', $cbmTotal)
            ->first();
        if ($tarifa) {
            return (float) $tarifa->value;
        }
        $tarifa = CalculadoraTarifasConsolidado::where('calculadora_tipo_cliente_id', $tipo->id)
            ->orderBy('limit_sup', 'desc')
            ->first();
        return $tarifa ? (float) $tarifa->value : 375.0;
    }

    /** Extra USD por proveedores que excedan MAX_PROVEEDORES (50 USD c/u). */
    public function getExtraProveedor(int $cantidadProveedores): float
    {
        $extra = max(0, $cantidadProveedores - self::MAX_PROVEEDORES);
        return $extra * self::TARIFA_EXTRA_PROVEEDOR;
    }

    /** Extra USD por ítems según CBM total y tabla TARIFAS_EXTRA_ITEM_PER_CBM. */
    public function getExtraItemPorCbm(float $cbmTotal, int $totalItems): float
    {
        foreach (self::TARIFAS_EXTRA_ITEM_PER_CBM as $row) {
            if ($cbmTotal >= $row['limit_inf'] && $cbmTotal <= $row['limit_sup']) {
                $itemsExtraCobrar = min(max(0, $totalItems - $row['item_base']), $row['item_extra']);
                return $itemsExtraCobrar * $row['tarifa'];
            }
        }
        return 0;
    }

    /**
     * Generar solo Excel + PDF de cotización sin guardar en calculadora_importacion.
     * Para exportación vía n8n/WhatsApp (user_cotizacion_exports).
     * Si el payload no trae tarifa/tarifaTotalExtraProveedor/tarifaTotalExtraItem, se calculan en backend
     * por tipo de cliente (resuelto por whatsapp), CBM total e ítems.
     *
     * @param array $data clienteInfo (whatsapp obligatorio para resolver tipo), proveedores; opcional tarifa, tarifaTotalExtraProveedor, tarifaTotalExtraItem
     * @return array|null ['url' => ..., 'boleta' => ..., 'totalfob' => ..., ...] o null si falla
     */
    public function generarCotizacionParaExport(array $data)
    {
        $totalProductos = 0;
        $totalCbm = 0;
        foreach ($data['proveedores'] as $proveedor) {
            $totalProductos += isset($proveedor['productos']) ? count($proveedor['productos']) : 0;
            $totalCbm += (float) ($proveedor['cbm'] ?? 0);
        }
        $data['totalProductos'] = $totalProductos;

        $calcularEnBackend = !isset($data['tarifa']) || empty($data['tarifa']) || !isset($data['tarifaTotalExtraProveedor']) || !isset($data['tarifaTotalExtraItem']);
        if ($calcularEnBackend) {
            $whatsapp = is_array($data['clienteInfo']['whatsapp'] ?? null)
                ? ($data['clienteInfo']['whatsapp']['value'] ?? '')
                : ($data['clienteInfo']['whatsapp'] ?? '');
            $tipoCliente = !empty($whatsapp) ? $this->getTipoClientePorWhatsapp($whatsapp) : ($data['clienteInfo']['tipoCliente'] ?? 'NUEVO');
            $data['clienteInfo']['tipoCliente'] = $tipoCliente;
            $tarifaVal = $this->getTarifaPrincipalPorTipoYCbm($tipoCliente, $totalCbm);
            $data['tarifa'] = ['tarifa' => $tarifaVal, 'type' => 'CBM', 'value' => $tarifaVal];
            $data['tarifaTotalExtraProveedor'] = $this->getExtraProveedor(count($data['proveedores']));
            $data['tarifaTotalExtraItem'] = $this->getExtraItemPorCbm($totalCbm, $totalProductos);
            $data['tarifaDescuento'] = 0;
        }

        $data['totalExtraProveedor'] = $data['tarifaTotalExtraProveedor'] ?? 0;
        $data['totalExtraItem'] = $data['tarifaTotalExtraItem'] ?? 0;
        $data['totalDescuento'] = $data['tarifaDescuento'] ?? 0;
        if (empty($data['tarifa']) || !is_array($data['tarifa'])) {
            $tarifaVal = is_array($data['tarifa']) ? (float) ($data['tarifa']['tarifa'] ?? 0) : (float) ($data['tarifa'] ?? 0);
            $data['tarifa'] = ['tarifa' => $tarifaVal, 'type' => 'CBM', 'value' => $tarifaVal];
        }
        if (empty($data['tipo_cambio']) || $data['tipo_cambio'] <= 0) {
            $data['tipo_cambio'] = 3.75;
        }
        foreach ($data['proveedores'] as $i => $proveedor) {
            if (!isset($data['proveedores'][$i]['code_supplier'])) {
                $data['proveedores'][$i]['code_supplier'] = null;
            }
        }
        if (empty($data['clienteInfo']['qtyProveedores'])) {
            $data['clienteInfo']['qtyProveedores'] = count($data['proveedores']);
        }
        $result = $this->crearCotizacionInicial($data);
        if (!is_array($result) || empty($result['url'])) {
            return null;
        }
        return $result;
    }

    /**
     * Lee la plantilla de cotización inicial desde assets/templates
     * @param \Illuminate\Http\Request $request
     * @return array|string Datos de la plantilla o mensaje de error
     */
    public function crearCotizacionInicial(array $data)
    {
        try {
            // Obtener tipo_cambio del request, si es null o 0 usar 3.75 por defecto
            $tipoCambio = (!empty($data['tipo_cambio']) && $data['tipo_cambio'] > 0) ? $data['tipo_cambio'] : 3.75;
            $plantillaPath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL_CALCULADORA.xlsx');
    
            if (!file_exists($plantillaPath)) {
                return 'Plantilla de cotización inicial no encontrada: ' . $plantillaPath;
            }
    
            try {
                $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($plantillaPath);
    
                if (!$objPHPExcel) {
                    return 'Error: La plantilla Excel no se cargó correctamente';
                }
    
                if ($objPHPExcel->getSheetCount() === 0) {
                    return 'Error: La plantilla Excel no tiene hojas';
                }
            } catch (\Exception $e) {
                return 'Error al cargar plantilla Excel: ' . $e->getMessage();
            }
    
            if ($objPHPExcel->getSheetCount() < 2) {
                return 'Error: La plantilla no tiene suficientes hojas';
            }
    
            $sheetCalculos = $objPHPExcel->getSheet(1);
    
            if (!$sheetCalculos) {
                return 'Error: No se pudo obtener la hoja de cálculos';
            }
    
            $totalProductos = $data['totalProductos'];
            
            if ($totalProductos <= 0) {
                return [
                    'url' => null,
                    'totalfob' => null,
                    'totalimpuestos' => null,
                    'logistica' => null,
                    'boleta' => null
                ];
            }
            
            $rowCodeSupplier = 3;
            $rowNProveedor = 4;
            $rowNCaja = 5;
            $rowPeso = 6;
            $rowMedida = 7;
            $rowVolProveedor = 8;
            $rowHeaderNProveedor = 10;
            $rowProducto = 11;
            $rowValorUnitario = 15;
            $rowValoracion = 16;
            $rowCantidad = 17;
            $rowValorFob = 18;
            $rowValorAjustado = 19;
            $rowDistribucion = 20;
            $rowFlete = 21;
            $rowValorCFR = 22;
            $rowValorCFRAjustado = 23;
            $rowSeguro = 24;
            $rowValorCIF = 25;
            $rowValorCIFAdjustado = 26;
            $rowAntidumpingCU = 30;
            $rowAntidumpingValor = 31;
            $rowAdValoremP = 33;
            $rowAdValoremValor = 34;
            $rowIGV = 35;
            $rowIPM = 36;
            $rowPercepcion = 37;
            $rowTotalTributos = 38;
            $rowDistribucionItemDestino = 44;
            $rowItemDestino = 45;
            $rowItemCostos = 50;
            $rowCostoTotal = 51;
            $rowCostoCantidad = 52;
            $rowCostoUnitarioUSD = 53;
            $rowCostoUnitarioPEN = 54;
            $initialColumnIndex = 3;
            $totalColumnas = 0;
            $formatoTexto = 'General';
            $sheetResumen = $objPHPExcel->getSheet(0);
            Log::info('data: ' . json_encode($data));
            foreach ($data['proveedores'] as $proveedor) {
                $totalColumnas += count($proveedor['productos']);
            }
    
            if ($totalColumnas > 1) {
                $columnasAInsertar = $totalColumnas - 1;
                $sheetCalculos->insertNewColumnBefore('D', $columnasAInsertar);
            }
    
            $columnIndex = 3;
            $indexProveedor = 1;
    
            $getColumnLetter = function ($columnNumber) {
                $letter = '';
                while ($columnNumber > 0) {
                    $columnNumber--;
                    $letter = chr(65 + ($columnNumber % 26)) . $letter;
                    $columnNumber = intval($columnNumber / 26);
                }
                return $letter;
            };
            $initialColumn = $getColumnLetter($initialColumnIndex);
            
            $totalColumn = $getColumnLetter($initialColumnIndex + $totalProductos);
            $sumColumn = $getColumnLetter($initialColumnIndex + $totalProductos - 1);
    
            $indexProducto = 1;
            $startRowProducto = 38;
            $currentRowProducto = $startRowProducto;
            
            // ✅ SOLUCIÓN CRÍTICA: Eliminar TODOS los comentarios de AMBAS hojas
            foreach ([$sheetResumen, $sheetCalculos] as $sheet) {
                $comments = $sheet->getComments();
                foreach ($comments as $coordinate => $comment) {
                    try {
                        $sheet->getComment($coordinate)->getText()->createText('');
                        unset($sheet->getComments()[$coordinate]);
                    } catch (\Exception $e) {
                        // Ignorar
                    }
                }
            }
            
            // ✅ Función helper para merge seguro
            $safeMergeCells = function($sheet, $range) {
                try {
                    $sheet->unmergeCells($range);
                } catch (\Exception $e) {
                    // No importa
                }
                
                try {
                    $sheet->mergeCells($range);
                } catch (\Exception $e) {
                    // Ignorar
                }
            };
            
            foreach ($data['proveedores'] as $proveedor) {
                $numProductos = count($proveedor['productos']);
    
                $startColumn = $getColumnLetter($columnIndex);
                $endColumn = $getColumnLetter($columnIndex + $numProductos - 1);
    
                if ($numProductos > 1) {
                    $safeMergeCells($sheetCalculos, $startColumn . $rowCodeSupplier . ':' . $endColumn . $rowCodeSupplier);
                    $safeMergeCells($sheetCalculos, $startColumn . $rowNProveedor . ':' . $endColumn . $rowNProveedor);
                    $safeMergeCells($sheetCalculos, $startColumn . $rowNCaja . ':' . $endColumn . $rowNCaja);
                    $safeMergeCells($sheetCalculos, $startColumn . $rowPeso . ':' . $endColumn . $rowPeso);
                    $safeMergeCells($sheetCalculos, $startColumn . $rowVolProveedor . ':' . $endColumn . $rowVolProveedor);
                    $safeMergeCells($sheetCalculos, $startColumn . $rowHeaderNProveedor . ':' . $endColumn . $rowHeaderNProveedor);
    
                    if (isset($proveedor['medidas'])) {
                        $safeMergeCells($sheetCalculos, $startColumn . $rowMedida . ':' . $endColumn . $rowMedida);
                    }
                }
    
                $codeSupplier = $proveedor['code_supplier'] ?? null;
                if ($codeSupplier) {
                    $sheetCalculos->setCellValue($startColumn . $rowCodeSupplier, $codeSupplier);
                }
    
                $sheetCalculos->setCellValue($startColumn . $rowNProveedor, $indexProveedor);
                $sheetCalculos->setCellValue($startColumn . $rowNCaja, $proveedor['qtyCaja']);
                $sheetCalculos->setCellValue($startColumn . $rowPeso, $proveedor['peso']);
                $sheetCalculos->setCellValue($startColumn . $rowVolProveedor, $proveedor['cbm']);
                $sheetCalculos->setCellValue($startColumn . $rowHeaderNProveedor, $indexProveedor);
    
                if (isset($proveedor['medidas'])) {
                    $sheetCalculos->setCellValue($startColumn . $rowMedida, $proveedor['medidas']);
                }
    
                $productColumnIndex = $columnIndex;
                $indexP=0;
                Log::info('currentRowProducto: ' . $currentRowProducto);
                $sheetResumen->insertNewRowBefore($currentRowProducto, count($proveedor['productos']));
                
                foreach ($proveedor['productos'] as $productoIndex => $producto) {
                    $productColumn = $getColumnLetter($productColumnIndex);
                    Log::info('productColumn: ' . $productColumn);
                    // ✅ Limpiar merges heredados de la fila
                    $allMergedCells = $sheetResumen->getMergeCells();
                    foreach ($allMergedCells as $mergedRange) {
                        if (preg_match('/:([A-Z]+)(\d+)$/', $mergedRange, $matches)) {
                            $rangeRow = (int)$matches[2];
                            if ($rangeRow == $currentRowProducto) {
                                try {
                                    $sheetResumen->unmergeCells($mergedRange);
                                } catch (\Exception $e) {
                                    // Ignorar
                                }
                            }
                        }
                    }
    
                    $sheetCalculos->setCellValue($productColumn . $rowProducto, $producto['nombre']);
                    $sheetCalculos->setCellValue($productColumn . $rowValorUnitario, $producto['precio']);
                    $sheetCalculos->setCellValue($productColumn . $rowValoracion, $producto['valoracion']);
                    $sheetCalculos->setCellValue($productColumn . $rowCantidad, $producto['cantidad']);
                    $sheetCalculos->setCellValue($productColumn . $rowValorFob, '=' . $productColumn . $rowValorUnitario . '*' . $productColumn . $rowCantidad);
                    $sheetCalculos->setCellValue($productColumn . $rowValorAjustado, '=' . ($productColumn . $rowValoracion) . '*' . ($productColumn . $rowCantidad));
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucion, '=ROUND(' . ($productColumn . $rowValorFob) . '/' . ($totalColumn . $rowValorFob) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowFlete, '=ROUND(' . ($productColumn . $rowDistribucion) . '*' . ($totalColumn . $rowFlete) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFR, '=ROUND(' . ($productColumn . $rowValorFob) . '+' . ($productColumn . $rowFlete) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFRAjustado, '=ROUND(' . ($productColumn . $rowValorAjustado) . '+' . ($productColumn . $rowFlete) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowSeguro, '=ROUND(' . ($totalColumn . $rowSeguro) . '*' . ($productColumn . $rowDistribucion) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIF, '=ROUND(' . ($productColumn . $rowValorCFR) . '+' . ($productColumn . $rowSeguro) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIFAdjustado, '=ROUND(' . ($productColumn . $rowValorCFRAjustado) . '+' . ($productColumn . $rowSeguro) . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingCU, $producto['antidumpingCU'] ?? 0);
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingValor, '=ROUND(' . $productColumn . $rowCantidad . '*' . $productColumn . $rowAntidumpingCU . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremP, round(($producto['adValoremP'] ?? 0) / 100, 4));
                    $formADVALOREM = "=ROUND(MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")*" . $productColumn . $rowAdValoremP . ",10)";
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremValor, $formADVALOREM);
                    $formIGV = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.16,10)";
                    $sheetCalculos->setCellValue($productColumn . $rowIGV, $formIGV);
                    $formIPM = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.02,10)";
                    $sheetCalculos->setCellValue($productColumn . $rowIPM, $formIPM);
                    $formPercepcion = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . ")*0.035,10)";
                    $sheetCalculos->setCellValue($productColumn . $rowPercepcion, $formPercepcion);
    
                    $formTotalTributos = "=ROUND(" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . "+" . $productColumn . $rowPercepcion . ",10)";
                    $sheetCalculos->setCellValue($productColumn . $rowTotalTributos, $formTotalTributos);
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucionItemDestino, '=ROUND(' . $productColumn . $rowDistribucion . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowItemDestino, '=ROUND(' . $totalColumn . $rowItemDestino . '*(' . $productColumn . $rowDistribucionItemDestino . '),10)');
                    $sheetCalculos->setCellValue($productColumn . $rowItemCostos, '=(' . $productColumn . $rowProducto . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoTotal, '=ROUND(MAX(' . $productColumn . $rowValorCFR . ',' . $productColumn . $rowValorCFRAjustado . ')+' . $productColumn . $rowAntidumpingValor . '+' . $productColumn . $rowTotalTributos . '+' . $productColumn . $rowItemDestino . ',10)');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoCantidad, '=(' . $productColumn . $rowCantidad . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioUSD, '=ROUND((' . $productColumn . $rowCostoTotal . ')/(' . $productColumn . $rowCostoCantidad . '),10)');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioPEN, '=ROUND((' . $productColumn . $rowCostoUnitarioUSD . ')*' . $tipoCambio . ',10)');
                    
                    $sheetResumen->setCellValue('A' . $currentRowProducto, $indexProducto);
                    $sheetResumen->setCellValue('B' . $currentRowProducto, "='2'!" . $productColumn . $rowProducto);
                    
                    $safeMergeCells($sheetResumen, 'B' . $currentRowProducto . ':D' . $currentRowProducto);
                    
                    $sheetResumen->setCellValue('E' . $currentRowProducto, "='2'!" . $productColumn . $rowCantidad);
                    $sheetResumen->setCellValue('F' . $currentRowProducto, "='2'!" . $productColumn . $rowValorUnitario);
                    
                    $safeMergeCells($sheetResumen, 'F' . $currentRowProducto . ':G' . $currentRowProducto);
                    
                    $sheetResumen->setCellValue('H' . $currentRowProducto, "='2'!" . $productColumn . $rowCostoUnitarioUSD);
                    $sheetResumen->setCellValue('I' . $currentRowProducto, "=E" . $currentRowProducto . "*H" . $currentRowProducto);
                    $sheetResumen->setCellValue('J' . $currentRowProducto, '=H' . $currentRowProducto . '*' . $tipoCambio);
    
                    $safeMergeCells($sheetResumen, 'J' . $currentRowProducto . ':K' . $currentRowProducto);
                    
                    // ✅ CAMBIO CRÍTICO: Copiar estilos celda por celda en lugar de duplicateStyle
                    $templateRow = $startRowProducto;
                    $styleArray = [
                        'font' => $sheetResumen->getStyle('A' . $templateRow)->getFont()->exportArray(),
                        'borders' => $sheetResumen->getStyle('A' . $templateRow)->getBorders()->exportArray(),
                        'alignment' => $sheetResumen->getStyle('A' . $templateRow)->getAlignment()->exportArray(),
                        'fill' => $sheetResumen->getStyle('A' . $templateRow)->getFill()->exportArray(),
                    ];
                    
                    // Aplicar estilos individualmente a cada celda del rango
                    foreach (range('A', 'K') as $col) {
                        $cellCoord = $col . $currentRowProducto;
                        $sheetResumen->getStyle($cellCoord)->applyFromArray($styleArray);
                    }
                    
                    $sheetResumen->getStyle('F' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('H' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('J' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoSoles);
                    
                    $sheetResumen->setCellValue('M' . $currentRowProducto, "=A" . $currentRowProducto);
                    $sheetResumen->setCellValue('N' . $currentRowProducto, "=B" . $currentRowProducto);
                    $sheetResumen->setCellValue('O' . $currentRowProducto, "=F" . $currentRowProducto);
                    $sheetResumen->setCellValue('P' . $currentRowProducto, "=H" . $currentRowProducto);
                    $sheetResumen->setCellValue('Q' . $currentRowProducto, "=J" . $currentRowProducto);
                    
                    $sheetResumen->getStyle('M' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoTexto);
                    $sheetResumen->getStyle('N' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoTexto);
                    $sheetResumen->getStyle('O' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('P' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('Q' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoSoles);
                    
                    $rangeMQ = 'M' . $currentRowProducto . ':Q' . $currentRowProducto;
                    $styleMQ = $sheetResumen->getStyle($rangeMQ);
                    $styleMQ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $styleMQ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $styleMQ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $styleMQ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $styleMQ->getFill()->getStartColor()->setARGB('FFFFFF');
                    $styleMQ->getFont()->getColor()->setARGB('000000');
    
                    $indexProducto++;
                    $currentRowProducto++;
                    $productColumnIndex++;
                    $indexP++;
                }
    
                $columnIndex += $numProductos;
                $indexProveedor++;
            }
            
            $sheetResumen->setCellValue('A' . $currentRowProducto, 'TOTAL');
            $sheetResumen->setCellValue('E' . $currentRowProducto, '=SUM(E' . $startRowProducto . ':E' . ($currentRowProducto - 1) . ')');
            $sheetResumen->setCellValue('I' . $currentRowProducto, '=SUM(I' . $startRowProducto . ':I' . ($currentRowProducto - 1) . ')');
            $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
    
            $currentRowProducto++;
            
            $sheetCalculos->setCellValue($totalColumn . $rowNProveedor, '=SUM(' . ($initialColumn . $rowNProveedor) . ':' . ($sumColumn . $rowNProveedor) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowNCaja, '=SUM(' . ($initialColumn . $rowNCaja) . ':' . ($sumColumn . $rowNCaja) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowPeso, '=SUM(' . ($initialColumn . $rowPeso) . ':' . ($sumColumn . $rowPeso) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowVolProveedor, '=SUM(' . ($initialColumn . $rowVolProveedor) . ':' . ($sumColumn . $rowVolProveedor) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCantidad, '=SUM(' . ($initialColumn . $rowCantidad) . ':' . ($sumColumn . $rowCantidad) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorFob, '=SUM(' . ($initialColumn . $rowValorFob) . ':' . ($sumColumn . $rowValorFob) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorAjustado, '=SUM(' . ($initialColumn . $rowValorAjustado) . ':' . ($sumColumn . $rowValorAjustado) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowDistribucion, '=SUM(' . ($initialColumn . $rowDistribucion) . ':' . ($sumColumn . $rowDistribucion) . ')');
            
            $totalExtras = ($data['totalExtraProveedor'] ?? 0) + ($data['totalExtraItem'] ?? 0);
            $tarifaConExtras = $data['tarifa']['tarifa'];
            
            if ($data['tarifa']['type'] == 'PLAIN') {
                $sheetCalculos->setCellValue($totalColumn . $rowFlete, '=' . ($data['tarifa']['tarifa']) . '*0.6');
                $sheetCalculos->setCellValue($totalColumn . $rowItemDestino, '=(' . ($data['tarifa']['tarifa']) . '*0.4)-(' . ($data['totalDescuento']??00) . ')+(' . ($totalExtras??00) . ')');
            } else {
                $sheetCalculos->setCellValue($totalColumn . $rowFlete, '=' . ($data['tarifa']['tarifa']) . '*0.6*(' . ($totalColumn . $rowVolProveedor) . ')');
                $sheetCalculos->setCellValue($totalColumn . $rowItemDestino, '=' . ($tarifaConExtras) . '*0.4*(' . ($totalColumn . $rowVolProveedor) . ')-(' . ($data['totalDescuento']??00) . ')+(' . ($totalExtras??00) . ')');
            }
    
            $sheetCalculos->setCellValue($totalColumn . $rowValorCFR, '=ROUND(SUM(' . $initialColumn . $rowValorCFR . ':' . $sumColumn . $rowValorCFR . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCFRAjustado, '=ROUND(SUM(' . $initialColumn . $rowValorCFRAjustado . ':' . $sumColumn . $rowValorCFRAjustado . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowSeguro, '=IF(' . $totalColumn . $rowValorFob . '>=5000,100,50)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIF, '=ROUND(SUM(' . $initialColumn . $rowValorCIF . ':' . $sumColumn . $rowValorCIF . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIFAdjustado, '=ROUND(SUM(' . $initialColumn . $rowValorCIFAdjustado . ':' . $sumColumn . $rowValorCIFAdjustado . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowAntidumpingValor, '=ROUND(SUM(' . $initialColumn . $rowAntidumpingValor . ':' . $sumColumn . $rowAntidumpingValor . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowAdValoremValor, '=ROUND(SUM(' . $initialColumn . $rowAdValoremValor . ':' . $sumColumn . $rowAdValoremValor . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowIGV, '=ROUND(SUM(' . $initialColumn . $rowIGV . ':' . $sumColumn . $rowIGV . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowIPM, '=ROUND(SUM(' . $initialColumn . $rowIPM . ':' . $sumColumn . $rowIPM . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowPercepcion, '=ROUND(SUM(' . $initialColumn . $rowPercepcion . ':' . $sumColumn . $rowPercepcion . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowTotalTributos, '=ROUND(SUM(' . $initialColumn . $rowTotalTributos . ':' . $sumColumn . $rowTotalTributos . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoTotal, '=ROUND(MAX(' . $totalColumn . $rowValorCFR . ',' . $totalColumn . $rowValorCFRAjustado . ')+' . $totalColumn . $rowAntidumpingValor . '+' . $totalColumn . $rowTotalTributos . '+' . $totalColumn . $rowItemDestino . ',10)');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoCantidad, '=(' . $totalColumn . $rowCantidad . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioUSD, '=ROUND(SUM(' . $initialColumn . $rowCostoUnitarioUSD . ':' . $sumColumn . $rowCostoUnitarioUSD . '),10)');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioPEN, '=ROUND(SUM(' . $initialColumn . $rowCostoUnitarioPEN . ':' . $sumColumn . $rowCostoUnitarioPEN . '),10)');
            
            $sumColumnIndex = $initialColumnIndex + $totalProductos - 1;
            $totalColumnIndex = $initialColumnIndex + $totalProductos;
            
            for ($colIdx = $sumColumnIndex + 1; $colIdx < $totalColumnIndex + 3; $colIdx++) {
                $colLetter = $getColumnLetter($colIdx);
                if ($colIdx == $totalColumnIndex) {
                    continue;
                }
                try {
                    $lastRow = max($rowCostoUnitarioPEN, $rowTotalTributos, $rowItemDestino, $rowPercepcion, $rowCostoTotal);
                    for ($row = 1; $row <= $lastRow; $row++) {
                        $cell = $colLetter . $row;
                        try {
                            $cellValue = $sheetCalculos->getCell($cell)->getValue();
                            if ($cellValue === null || $cellValue === '') {
                                $sheetCalculos->setCellValue($cell, '');
                            }
                        } catch (\Exception $e) {
                            // Ignorar
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar
                }
            }
            
            $sheetResumen->setCellValue('E11', $data['tarifa']['value']);
            $tipoDocumento = $data['clienteInfo']['tipoDocumento'] ?? 'DNI';
            $nombreMostrar = $tipoDocumento === 'RUC' ? ($data['clienteInfo']['empresa'] ?? $data['clienteInfo']['razonSocial'] ?? '') : $data['clienteInfo']['nombre'];
            $documentoMostrar = $tipoDocumento === 'RUC' ? ($data['clienteInfo']['ruc'] ?? '') : $data['clienteInfo']['dni'];
            $whatsappValue = is_array($data['clienteInfo']['whatsapp']) ? ($data['clienteInfo']['whatsapp']['value'] ?? '') : ($data['clienteInfo']['whatsapp'] ?? '');
            
            $sheetResumen->setCellValue('B8', $nombreMostrar);
            $sheetResumen->setCellValue('B9', $documentoMostrar);
            $sheetResumen->setCellValue('B10', $data['clienteInfo']['correo']);
            $sheetResumen->setCellValue('B11', $whatsappValue);
            $sheetResumen->setCellValue('I9', "='2'!" . ($totalColumn . $rowPeso));
            $sheetResumen->setCellValue('I10', "='2'!" . ($totalColumn . $rowNCaja));
            $sheetResumen->setCellValue('I11', "='2'!" . ($totalColumn . $rowVolProveedor));
            $sheetResumen->setCellValue('J11', "='2'!" . ($totalColumn . $rowVolProveedor));
            $sheetResumen->setCellValue('J14', "='2'!" . ($totalColumn . $rowValorFob));
            $sheetResumen->setCellValue('J15', "='2'!" . ($totalColumn . $rowFlete . "+('2'!" . $totalColumn . $rowSeguro . ")"));
            
            $finalColumnAdValorem = $getColumnLetter($initialColumnIndex + $totalColumnas - 1);
            $sheetResumen->setCellValue('I20', "=MAX('2'!C" . $rowAdValoremP . ":" . $finalColumnAdValorem . $rowAdValoremP . ")");
            $sheetResumen->setCellValue('J20', "='2'!" . ($totalColumn . $rowAdValoremValor));
            $sheetResumen->setCellValue('J21', "='2'!" . ($totalColumn . $rowIGV));
            $sheetResumen->setCellValue('J22', "='2'!" . ($totalColumn . $rowIPM));
            $sheetResumen->setCellValue('I23', "=MAX('2'!C" . $rowAntidumpingCU . ":" . $finalColumnAdValorem . $rowAntidumpingCU . ")");
            $sheetResumen->setCellValue('J23', "='2'!" . ($totalColumn . $rowAntidumpingValor));
            $sheetResumen->setCellValue('J26', "='2'!" . ($totalColumn . $rowPercepcion));
            
            if ($data['tarifa']['type'] == 'PLAIN') {
                $sheetResumen->setCellValue('J30', '=' . $data['tarifa']['tarifa']);
            } else {
                $sheetResumen->setCellValue('J30', '=I11*(' . $data['tarifa']['tarifa'] . ')');
            }
    
            $sheetResumen->setCellValue('J31', $data['totalExtraProveedor']+$data['totalExtraItem']);
            $sheetResumen->setCellValue('J32', $data['totalDescuento']);
            $sheetResumen->setCellValue('J33', '=J27');
            $sheetResumen->setCellValue('J34', '=J30+J31-J32+J33');
            
            $timestamp = now()->format('Y_m_d_H_i_s');
            $fileName = "COTIZACION_INICIAL_{$data['clienteInfo']['nombre']}_{$timestamp}.xlsx";
            $filePath = storage_path('app/public/templates/' . $fileName);
    
            try {
                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xlsx');
                $writer->save($filePath);
    
                if (!file_exists($filePath)) {
                    return [
                        'url' => null,
                        'totalfob' => null,
                        'totalimpuestos' => null,
                        'logistica' => null,
                        'boleta' => null
                    ];
                }
    
                $fileSize = filesize($filePath);
                if ($fileSize === 0) {
                    return [
                        'url' => null,
                        'totalfob' => null,
                        'totalimpuestos' => null,
                        'logistica' => null,
                        'boleta' => null
                    ];
                }
    
                $boletaInfo = null;
                try {
                    $boletaInfo = $this->generateBoleta($objPHPExcel, $data['clienteInfo']);
                } catch (\Exception $e) {
                    // Continuar sin la boleta
                }
    
                $publicUrl = Storage::url('templates/' . $fileName);
                
                $totalFob = $sheetCalculos->getCell($totalColumn . $rowValorFob)->getCalculatedValue();
                $totalImpuestos = $sheetCalculos->getCell($totalColumn . $rowTotalTributos)->getCalculatedValue();
                $j30 = $sheetResumen->getCell('J30')->getCalculatedValue();
                $j31 = $sheetResumen->getCell('J31')->getCalculatedValue();
                $j32 = $sheetResumen->getCell('J32')->getCalculatedValue();
                $logistica = (is_numeric($j30) && is_numeric($j31) && is_numeric($j32)) ? ($j30 + $j31 - $j32) : 0;
                
                return [
                    'url' => $publicUrl,
                    'totalfob' => $totalFob,
                    'totalimpuestos' => $totalImpuestos,
                    'logistica' => $logistica,
                    'boleta' => $boletaInfo
                ];
            } catch (\Exception $e) {
                Log::error('Error al guardar Excel: ' . $e->getMessage());
                return [
                    'url' => null,
                    'totalfob' => null,
                    'totalimpuestos' => null,
                    'logistica' => null,
                    'boleta' => null
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error al crear cotización inicial: ' . $e->getMessage());
    
            return [
                'url' => null,
                'totalfob' => null,
                'totalimpuestos' => null,
                'logistica' => null
            ];
        }
    }

    /**
     * Actualizar estado de una calculadora
     */
    public function actualizarEstado(int $id, string $estado): bool
    {
        if (!CalculadoraImportacion::esEstadoValido($estado)) {
            throw new \InvalidArgumentException('Estado no válido: ' . $estado);
        }

        $calculadora = CalculadoraImportacion::find($id);
        if (!$calculadora) {
            throw new \Exception('Calculadora no encontrada');
        }

        return $calculadora->update(['estado' => $estado]);
    }

    /**
     * Marcar calculadora como cotizada
     */
    public function marcarComoCotizada(int $id): bool
    {
        return $this->actualizarEstado($id, CalculadoraImportacion::ESTADO_COTIZADO);
    }

    /**
     * Marcar calculadora como confirmada
     */
    public function marcarComoConfirmada(int $id): bool
    {
        return $this->actualizarEstado($id, CalculadoraImportacion::ESTADO_CONFIRMADO);
    }

    /**
     * Marcar calculadora como pendiente
     */
    public function marcarComoPendiente(int $id): bool
    {
        return $this->actualizarEstado($id, CalculadoraImportacion::ESTADO_PENDIENTE);
    }

    /**
     * Asociar calculadora con un contenedor
     */
    public function asociarContenedor(int $idCalculadora, int $idContenedor): bool
    {
        $calculadora = CalculadoraImportacion::find($idCalculadora);
        if (!$calculadora) {
            throw new \Exception('Calculadora no encontrada');
        }

        // Verificar que el contenedor existe
        $contenedor = \App\Models\CargaConsolidada\Contenedor::find($idContenedor);
        if (!$contenedor) {
            throw new \Exception('Contenedor no encontrado');
        }

        return $calculadora->update(['id_carga_consolidada_contenedor' => $idContenedor]);
    }

    /**
     * Desasociar contenedor de una calculadora
     */
    public function desasociarContenedor(int $idCalculadora): bool
    {
        $calculadora = CalculadoraImportacion::find($idCalculadora);
        if (!$calculadora) {
            throw new \Exception('Calculadora no encontrada');
        }

        return $calculadora->update(['id_carga_consolidada_contenedor' => null]);
    }

    /**
     * Regenerar Excel desde la calculadora cuando el archivo no existe.
     * Usado en modificarExcelConFechas y actualizarCotizacionDesdeCalculadora.
     *
     * @return array|null ['url' => ..., 'totalfob' => ..., etc] o null si falla
     */
    public function regenerarExcelDesdeCalculadora(CalculadoraImportacion $calculadora): ?array
    {
        $calculadora->load(['proveedores.productos', 'contenedor']);

        $clienteInfo = [
            'nombre' => $calculadora->nombre_cliente,
            'dni' => $calculadora->dni_cliente ?? '',
            'ruc' => $calculadora->ruc_cliente ?? '',
            'empresa' => $calculadora->razon_social ?? '',
            'razonSocial' => $calculadora->razon_social ?? '',
            'correo' => $calculadora->correo_cliente ?? '',
            'whatsapp' => is_array($calculadora->whatsapp_cliente ?? null)
                ? $calculadora->whatsapp_cliente
                : ['value' => $calculadora->whatsapp_cliente ?? ''],
            'tipoCliente' => $calculadora->tipo_cliente ?? 'NUEVO',
            'qtyProveedores' => $calculadora->qty_proveedores ?? 1,
            'tipoDocumento' => $calculadora->tipo_documento ?? 'DNI',
        ];

        $proveedores = [];
        foreach ($calculadora->proveedores as $prov) {
            $productos = [];
            foreach ($prov->productos as $p) {
                $productos[] = [
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio,
                    'valoracion' => (float) ($p->valoracion ?? 0),
                    'cantidad' => (int) $p->cantidad,
                    'antidumpingCU' => (float) ($p->antidumping_cu ?? 0),
                    'adValoremP' => (float) ($p->ad_valorem_p ?? 0),
                ];
            }
            $proveedores[] = [
                'cbm' => (float) $prov->cbm,
                'peso' => (float) $prov->peso,
                'qtyCaja' => (int) $prov->qty_caja,
                'code_supplier' => $prov->code_supplier,
                'productos' => $productos,
            ];
        }

        $totalProductos = collect($proveedores)->sum(fn ($p) => count($p['productos']));
        $tarifaVal = (float) ($calculadora->tarifa ?? 0);

        $totalExtraProveedor = (float) ($calculadora->tarifa_total_extra_proveedor ?? 0);
        $totalExtraItem = (float) ($calculadora->tarifa_total_extra_item ?? 0);
        $totalDescuento = (float) ($calculadora->tarifa_descuento ?? 0);

        $data = [
            'clienteInfo' => $clienteInfo,
            'proveedores' => $proveedores,
            'totalProductos' => $totalProductos,
            'tarifaTotalExtraProveedor' => $totalExtraProveedor,
            'tarifaTotalExtraItem' => $totalExtraItem,
            'tarifaDescuento' => $totalDescuento,
            'totalExtraProveedor' => $totalExtraProveedor,
            'totalExtraItem' => $totalExtraItem,
            'totalDescuento' => $totalDescuento,
            'tarifa' => [
                'tarifa' => $tarifaVal,
                'type' => 'CBM',
                'value' => $tarifaVal,
            ],
            'tipo_cambio' => (float) ($calculadora->tc ?? 3.75),
            'id_carga_consolidada_contenedor' => $calculadora->id_carga_consolidada_contenedor,
        ];

        $result = $this->crearCotizacionInicial($data);
        if (!$result || !is_array($result) || empty($result['url'])) {
            Log::error('[REGENERAR EXCEL] No se pudo crear el Excel', ['calculadora_id' => $calculadora->id]);
            return null;
        }

        $calculadora->url_cotizacion = $result['url'];
        $calculadora->total_fob = $result['totalfob'] ?? $calculadora->total_fob;
        $calculadora->total_impuestos = $result['totalimpuestos'] ?? $calculadora->total_impuestos;
        $calculadora->logistica = $result['logistica'] ?? $calculadora->logistica;
        if (!empty($result['boleta']['url'])) {
            $calculadora->url_cotizacion_pdf = $result['boleta']['url'];
        }
        $calculadora->save();

        Log::info('[REGENERAR EXCEL] Excel recreado exitosamente', ['calculadora_id' => $calculadora->id, 'url' => $result['url']]);
        return $result;
    }

    /**
     * Regenerar boleta PDF a partir del Excel actual (ej. cuando se actualiza con cod_cotizacion).
     * Usado cuando la cotización pasa a COTIZADO y el Excel ya tiene el código en D7.
     *
     * @return array|null ['path' => ..., 'filename' => ..., 'url' => ...] o null si falla
     */
    public function regenerarBoletaPdf(CalculadoraImportacion $calculadora): ?array
    {
        if (!$calculadora->url_cotizacion) {
            Log::warning('[REGENERAR BOLETA] Calculadora sin url_cotizacion', ['id' => $calculadora->id]);
            return null;
        }

        $clienteInfo = [
            'nombre' => $calculadora->nombre_cliente,
            'dni' => $calculadora->dni_cliente ?? $calculadora->ruc_cliente,
            'ruc' => $calculadora->ruc_cliente,
            'correo' => $calculadora->correo_cliente ?? '',
            'whatsapp' => is_array($calculadora->whatsapp_cliente ?? null)
                ? $calculadora->whatsapp_cliente
                : ['value' => $calculadora->whatsapp_cliente ?? ''],
            'tipoCliente' => $calculadora->tipo_cliente ?? 'NUEVO',
            'qtyProveedores' => $calculadora->qty_proveedores ?? 1,
        ];

        try {
            return $this->generateBoleta($calculadora->url_cotizacion, $clienteInfo);
        } catch (\Exception $e) {
            Log::error('[REGENERAR BOLETA] Error al regenerar boleta: ' . $e->getMessage(), [
                'calculadora_id' => $calculadora->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generar boleta PDF a partir del Excel
     */
    private function generateBoleta($objPHPExcelOrUrl, $clienteInfo)
    {
        try {
            // Aumentar memoria para procesar archivos Excel grandes
            ini_set('memory_limit', '2G');
            ini_set('max_execution_time', 300);

            // Si recibe una URL, cargar el archivo Excel
            if (is_string($objPHPExcelOrUrl)) {
                $fileUrl = $objPHPExcelOrUrl;
                $filePath = null;
                // Convertir URL a ruta de archivo
                if (strpos($fileUrl, 'http') === 0) {
                    $parsedUrl = parse_url($fileUrl);
                    $path = $parsedUrl['path'] ?? '';
                    if (strpos($path, '/storage/') === 0) {
                        $path = substr($path, 9); // Remover '/storage/'
                    }
                    $path = ltrim($path, '/');
                    $filePath = storage_path('app/public/' . $path);
                } else {
                    // url_cotizacion puede ser "storage/templates/..." o "/storage/templates/..."
                    $pathRel = preg_replace('#^/?(storage/)?#', '', $fileUrl);
                    $pathStorage = storage_path('app/public/' . $pathRel);
                    if (file_exists($pathStorage)) {
                        $filePath = $pathStorage;
                    } else {
                        $filePath = public_path($fileUrl);
                    }
                }

                if (!$filePath || !file_exists($filePath)) {
                    throw new \Exception('Archivo Excel no encontrado: ' . ($filePath ?? $fileUrl));
                }

                $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            } else {
                $objPHPExcel = $objPHPExcelOrUrl;
            }

            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet();

            $antidumping = $sheet->getCell('A23')->getValue(); // B23 -> A23

            // Código de cotización (D7: "COTIZACION N° CO02260001") para la boleta PDF
            $codigoCotizacion = '';
            try {
                $d7 = $sheet->getCell('D7')->getValue();
                if (is_string($d7) && preg_match('/COTIZACION\s+N[°º]?\s*(.+)/u', trim($d7), $m)) {
                    $codigoCotizacion = trim($m[1]);
                }
            } catch (\Throwable $e) {
                // Ignorar
            }

            $data = [
                "cod_contract" => $codigoCotizacion,
                "name" => $clienteInfo['nombre'] ?? $sheet->getCell('B8')->getValue(), // C8 -> B8
                "lastname" => "", // No hay apellido separado en el nuevo formato
                "ID" => $clienteInfo['dni'] ?? $sheet->getCell('B10')->getValue(), // C10 -> B10
                "phone" => $clienteInfo['whatsapp']['value'] ?? $sheet->getCell('B11')->getValue(), // C11 -> B11
                "date" => date('d/m/Y'),
                "tipocliente" => $clienteInfo['tipoCliente'] ?? $sheet->getCell('E11')->getValue(), // F11 -> E11
                "peso" => number_format($this->getCellValueAsFloat($sheet, 'I9'), 2, '.', ','), // Peso formateado (kg)
                "qtysuppliers" => $clienteInfo['qtyProveedores'] ?? $sheet->getCell('E11')->getValue(), // Cantidad de proveedores
                "qtycajas" => intval($this->getCellValueAsFloat($sheet, 'I10')), // Número de cajas total
                "cbm" => number_format($this->getCellValueAsFloat($sheet, 'I11'), 2, '.', ','), // Volumen formateado (m³)
                "valorcarga" => round($this->getCellValueAsFloat($sheet, 'J14'), 2), // K14 -> J14
                "fleteseguro" => round($this->getCellValueAsFloat($sheet, 'J15'), 2), // K15 -> J15
                "valorcif" => round($this->getCellValueAsFloat($sheet, 'J16'), 2), // K16 -> J16
                "advalorempercent" => intval($this->getCellValueAsFloat($sheet, 'I20') * 100), // J20 -> I20
                "advalorem" => round($this->getCellValueAsFloat($sheet, 'J20'), 2), // K20 -> J20
                "antidumping" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J23'), 2) : 0.0, // K23 -> J23
                "igv" => round($this->getCellValueAsFloat($sheet, 'J21'), 2), // K21 -> J21
                "ipm" => round($this->getCellValueAsFloat($sheet, 'J22'), 2), // K22 -> J22
                "subtotal" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J24'), 2) : round($this->getCellValueAsFloat($sheet, 'J23'), 2), // K24/K23 -> J24/J23
                "percepcion" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J26'), 2) : round($this->getCellValueAsFloat($sheet, 'J25'), 2), // K26/K25 -> J26/J25
                "total" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J27'), 2) : round($this->getCellValueAsFloat($sheet, 'J26'), 2), // K27/K26 -> J27/J26
                // Resumen de cotización - ahora son 4 conceptos (filas 30-33) + total (fila 34)
                // Fila 30: SERVICIO DE IMPORTACIÓN (ORIGEN -FLETE - DESTINO)
                "servicioimportacion" => round($this->getCellValueAsFloat($sheet, 'J30'), 2),
                // Fila 31: CARGOS EXTRAS (QTY PROVEEDORES O QTY ITEMS)
                "cargosextras" => round($this->getCellValueAsFloat($sheet, 'J31'), 2),
                // Fila 32: DESCUENTO APLICABLE
                "descuento" => round($this->getCellValueAsFloat($sheet, 'J32'), 2),
                // Fila 33: IMPUESTOS
                "impuestos" => round($this->getCellValueAsFloat($sheet, 'J33'), 2),
                // Fila 34: MONTO TOTAL
                "montototal" => round($this->getCellValueAsFloat($sheet, 'J34'), 2),
            ];
            Log::info(json_encode($data));
            $i = $antidumping == "ANTIDUMPING" ? 38 : 37;
            $items = [];

            while ($sheet->getCell('A' . $i)->getValue() != 'TOTAL') {
                //remove format to string
                $item = [
                    "index" => $sheet->getCell('A' . $i)->getCalculatedValue(), // B -> A
                    "name" => $sheet->getCell('B' . $i)->getCalculatedValue(), // C -> B
                    "qty" => $this->getCellValueAsFloat($sheet, 'E' . $i), // F -> E
                    "costounit" => number_format(round($this->getCellValueAsFloat($sheet, 'F' . $i), 2), 2, '.', ','), // G -> F
                    "preciounit" => number_format(round($this->getCellValueAsFloat($sheet, 'H' . $i), 2), 2, '.', ','), // I -> H
                    "total" => round($this->getCellValueAsFloat($sheet, 'I' . $i), 2), // J -> I
                    "preciounitpen" => number_format(round($this->getCellValueAsFloat($sheet, 'J' . $i), 2), 2, '.', ','), // K -> J
                ];
                Log::info(json_encode($item));
                $items[] = $item;
                $i++;
            }

            $itemsCount = count($items);
            $data["br"] = $itemsCount - 18 < 0 ? str_repeat("<br>", 18 - $itemsCount) : "";
            $data['items'] = $items;

            // Cargar logo y pagos
            $logoPath = public_path('assets/images/probusiness.png');
            $logoContent = file_exists($logoPath) ? file_get_contents($logoPath) : '';
            $logoData = base64_encode($logoContent);
            $data["logo"] = 'data:image/jpg;base64,' . $logoData;

            // Cargar template HTML
            $htmlFilePath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL_CALCULADORA.html');
            if (!file_exists($htmlFilePath)) {
                throw new \Exception('Template HTML no encontrado: ' . $htmlFilePath);
            }
            $htmlContent = file_get_contents($htmlFilePath);

            // Cargar imagen de pagos
            $pagosPath = public_path('assets/images/pagos-full.jpg');
            $pagosContent = file_exists($pagosPath) ? file_get_contents($pagosPath) : '';
            $pagosData = base64_encode($pagosContent);
            $data["pagos"] = 'data:image/jpg;base64,' . $pagosData;

            // Reemplazar variables en el template
            foreach ($data as $key => $value) {
                if (is_numeric($value)) {
                    if ($value == 0) {
                        $value = '-';
                    } else if ($key != "ID" && $key != "phone" && $key != "qtysuppliers" && $key != "advalorempercent") {
                        $value = number_format($value, 2, '.', ',');
                    }
                }

                if ($key == "antidumping") {
                    if ($antidumping == "ANTIDUMPING" && $data['antidumping'] > 0) {
                        $antidumpingHtml = '<tr style="background:#FFFF33">
                        <td style="border-top:none!important;border-bottom:none!important" colspan="3">ANTIDUMPING</td>
                        <td style="border-top:none!important;border-bottom:none!important" ></td>
                        <td style="border-top:none!important;border-bottom:none!important" >$' . number_format($data['antidumping'], 2, '.', ',') . '</td>
                        <td style="border-top:none!important;border-bottom:none!important" >USD</td>
                        </tr>';
                        $htmlContent = str_replace('{{antidumping}}', $antidumpingHtml, $htmlContent);
                    } else {
                        // Si no hay antidumping, reemplazar con string vacío
                        $htmlContent = str_replace('{{antidumping}}', '', $htmlContent);
                    }
                } else if ($key == "items") {
                    $itemsHtml = "";
                    $total = 0;
                    $cantidad = 0;
                    foreach ($value as $item) {
                        $total += $item['total'];
                        $cantidad += $item['qty'];
                        $itemsHtml .= '<tr>
                        <td colspan="1">' . $item['index'] . '</td>
                        <td colspan="5">' . $item['name'] . '</td>
                        <td colspan="1">' . $item['qty'] . '</td>
                        <td colspan="2">$ ' . $item['costounit'] . '</td>
                        <td colspan="1">$ ' . $item['preciounit'] . '</td>
                        <td colspan="1">$ ' . number_format($item['total'], 2, '.', ',') . '</td>
                        <td colspan="1">S/. ' . $item['preciounitpen'] . '</td>
                    </tr>';
                    }
                    $itemsHtml .= '<tr>
                    <td colspan="6" >TOTAL</td>
                    <td >' . $cantidad . '</td>
                    <td colspan="2" style="border:none!important"></td>
                    <td style="border:none!important"></td>
                    <td >$ ' . number_format($total, 2, '.', ',') . '</td>
                    <td style="border:none!important"></td>
                </tr>';
                    $htmlContent = str_replace('{{' . $key . '}}', $itemsHtml, $htmlContent);
                } else {
                    $htmlContent = str_replace('{{' . $key . '}}', $value, $htmlContent);
                }
            }

            // Generar PDF usando DomPDF
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($htmlContent);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // Guardar PDF en storage
            $timestamp = now()->format('Y_m_d_H_i_s');
            $pdfFileName = "BOLETA_{$clienteInfo['nombre']}_{$timestamp}.pdf";
            $pdfPath = storage_path('app/public/boletas/' . $pdfFileName);

            // Crear directorio si no existe
            if (!is_dir(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }

            // Guardar PDF
            file_put_contents($pdfPath, $dompdf->output());

            return [
                'path' => $pdfPath,
                'filename' => $pdfFileName,
                'url' => Storage::url('boletas/' . $pdfFileName)
            ];
        } catch (\Exception $e) {
            Log::error('Error al generar boleta: ' . $e->getMessage());
            throw new \Exception('Error al generar boleta: ' . $e->getMessage());
        }
    }

    /**
     * Genera código de proveedor basado en nombre del cliente y índice
     * Misma estructura que CotizacionController.generateCodeSupplier
     */
    private function generateCodeSupplier($string, $carga, $rowCount, $index)
    {
        $words = explode(" ", trim($string));
        $code = "";

        // Primeras 2 letras de las primeras 2 palabras (protegido)
        foreach ($words as $word) {
            if (strlen($code) >= 4) break; // Ya tenemos 4 caracteres (2 palabras)
            if (strlen($word) >= 2) { // Solo si la palabra tiene 2+ caracteres
                $code .= strtoupper(substr($word, 0, 2));
            }
        }

        // Completar con ceros y retornar
        return $code . $carga . "-" . $index;
    }

    /**
     * Helper para obtener el valor de una celda como float, manejando errores
     */
    private function getCellValueAsFloat($sheet, $cellReference)
    {
        try {
            $cell = $sheet->getCell($cellReference);
            $value = $cell->getCalculatedValue();
            Log::info("valor de la celda: " . $value);

            // Si la celda está vacía o es null
            if ($value === null || $value === '') {
                return 0.0;
            }

            // Si es un string, intentar convertirlo a float
            if (is_string($value)) {
                // Remover caracteres no numéricos excepto punto y coma
                $cleanValue = preg_replace('/[^0-9.-]/', '', $value);
                if ($cleanValue === '' || $cleanValue === '-') {
                    return 0.0;
                }
                return floatval($cleanValue);
            }

            // Si ya es un número, convertirlo a float
            if (is_numeric($value)) {
                return floatval($value);
            }

            // Si no se puede convertir, retornar 0
            return 0.0;
        } catch (\Exception $e) {
            Log::warning("Error al obtener valor de celda {$cellReference}: " . $e->getMessage());
            return 0.0;
        }
    }
}
