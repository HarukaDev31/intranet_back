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

class CalculadoraImportacionService
{
    public $TCAMBIO = 3.75;
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
            // Crear proveedores
            foreach ($data['proveedores'] as $proveedorData) {
                $proveedor = $calculadora->proveedores()->create([
                    'cbm' => $proveedorData['cbm'],
                    'peso' => $proveedorData['peso'],
                    'qty_caja' => $proveedorData['qtyCaja']
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

            // Eliminar proveedores y productos existentes
            foreach ($calculadora->proveedores as $proveedor) {
                $proveedor->productos()->delete();
            }
            $calculadora->proveedores()->delete();

            // Crear nuevos proveedores y productos
            foreach ($data['proveedores'] as $index => $proveedorData) {
                $proveedor = $calculadora->proveedores()->create([
                    'cbm' => $proveedorData['cbm'] ?? 0,
                    'peso' => $proveedorData['peso'] ?? 0,
                    'qty_caja' => $proveedorData['qtyCaja'] ?? 0,
                ]);

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
            $data['totalExtraProveedor'] = $calculadora->tarifa_total_extra_proveedor;
            $data['totalExtraItem'] = $calculadora->tarifa_total_extra_item;
            $data['totalDescuento'] = $calculadora->tarifa_descuento;
            $data['id_usuario'] = $calculadora->id_usuario;
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
            foreach ($data['proveedores'] as $proveedor) {
                $totalColumnas += count($proveedor['productos']);
            }

            // Solo insertar columnas si necesitamos más de 1 (ya que C está disponible)
            if ($totalColumnas > 1) {
                $columnasAInsertar = $totalColumnas - 1; // Restamos 1 porque ya tenemos la columna C
                $sheetCalculos->insertNewColumnBefore('D', $columnasAInsertar);
            }

            // Ahora empezar desde columna C (índice 3)
            $columnIndex = 3; // C = 3
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
            
            // Calcular totalColumn y sumColumn una sola vez antes del bucle
            $totalColumn = $getColumnLetter($initialColumnIndex + $totalProductos);
            $sumColumn = $getColumnLetter($initialColumnIndex + $totalProductos - 1);

            $indexProducto = 1;
            $startRowProducto = 38; // Fila de inicio de productos en la hoja de resumen
            $currentRowProducto = $startRowProducto;
            foreach ($data['proveedores'] as $proveedor) {
                $numProductos = count($proveedor['productos']);

                // Obtener columna inicial y final para el merge
                $startColumn = $getColumnLetter($columnIndex);
                $endColumn = $getColumnLetter($columnIndex + $numProductos - 1);

                // Si el proveedor tiene más de 1 producto, hacer merge de las celdas del proveedor
                if ($numProductos > 1) {
                    // Merge para las filas del proveedor
                    $sheetCalculos->mergeCells($startColumn . $rowNProveedor . ':' . $endColumn . $rowNProveedor);
                    $sheetCalculos->mergeCells($startColumn . $rowNCaja . ':' . $endColumn . $rowNCaja);
                    $sheetCalculos->mergeCells($startColumn . $rowPeso . ':' . $endColumn . $rowPeso);
                    $sheetCalculos->mergeCells($startColumn . $rowVolProveedor . ':' . $endColumn . $rowVolProveedor);
                    $sheetCalculos->mergeCells($startColumn . $rowHeaderNProveedor . ':' . $endColumn . $rowHeaderNProveedor);

                    // Si hay medidas, también hacer merge
                    if (isset($proveedor['medidas'])) {
                        $sheetCalculos->mergeCells($startColumn . $rowMedida . ':' . $endColumn . $rowMedida);
                    }
                }

                // Establecer valores del proveedor (solo en la primera columna del merge)
                $sheetCalculos->setCellValue($startColumn . $rowNProveedor, $indexProveedor);
                $sheetCalculos->setCellValue($startColumn . $rowNCaja, $proveedor['qtyCaja']);
                $sheetCalculos->setCellValue($startColumn . $rowPeso, $proveedor['peso']);
                $sheetCalculos->setCellValue($startColumn . $rowVolProveedor, $proveedor['cbm']);
                $sheetCalculos->setCellValue($startColumn . $rowHeaderNProveedor, $indexProveedor);

                if (isset($proveedor['medidas'])) {
                    $sheetCalculos->setCellValue($startColumn . $rowMedida, $proveedor['medidas']);
                }

                // Ahora procesar cada producto en su propia columna
                $productColumnIndex = $columnIndex;
                $indexP=0;
                //insert count total productos rows before current row
                $sheetResumen->insertNewRowBefore($currentRowProducto, count($proveedor['productos']));
                foreach ($proveedor['productos'] as $productoIndex => $producto) {
                    $productColumn = $getColumnLetter($productColumnIndex);

                    $sheetCalculos->setCellValue($productColumn . $rowProducto, $producto['nombre']);
                    $sheetCalculos->setCellValue($productColumn . $rowValorUnitario, $producto['precio']);
                    $sheetCalculos->setCellValue($productColumn . $rowValoracion, $producto['valoracion']);
                    $sheetCalculos->setCellValue($productColumn . $rowCantidad, $producto['cantidad']);
                    // Calcular valores
                    $sheetCalculos->setCellValue($productColumn . $rowValorFob, '=' . $productColumn . $rowValorUnitario . '*' . $productColumn . $rowCantidad);
                    $sheetCalculos->setCellValue($productColumn . $rowValorAjustado, '=' . ($productColumn . $rowValoracion) . '*' . ($productColumn . $rowCantidad));
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucion, '=ROUND(' . ($productColumn . $rowValorFob) . '/' . ($totalColumn . $rowValorFob) . ',4)');
                    $sheetCalculos->setCellValue($productColumn . $rowFlete, '=ROUND(' . ($productColumn . $rowDistribucion) . '*' . ($totalColumn . $rowFlete) . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFR, '=ROUND(' . ($productColumn . $rowValorFob) . '+' . ($productColumn . $rowFlete) . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFRAjustado, '=ROUND(' . ($productColumn . $rowValorAjustado) . '+' . ($productColumn . $rowFlete) . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowSeguro, '=ROUND(' . ($totalColumn . $rowSeguro) . '*' . ($productColumn . $rowDistribucion) . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIF, '=ROUND(' . ($productColumn . $rowValorCFR) . '+' . ($productColumn . $rowSeguro) . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIFAdjustado, '=ROUND(' . ($productColumn . $rowValorCFRAjustado) . '+' . ($productColumn . $rowSeguro) . ',2)');
                    //tributos
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingCU, $producto['antidumpingCU'] ?? 0);
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingValor, '=ROUND(' . $productColumn . $rowCantidad . '*' . $productColumn . $rowAntidumpingCU . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremP, round(($producto['adValoremP'] ?? 0) / 100, 4));
                    $formADVALOREM = "=ROUND(MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")*" . $productColumn . $rowAdValoremP . ",2)";
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremValor, $formADVALOREM);
                    $formIGV = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.16,2)";
                    $sheetCalculos->setCellValue($productColumn . $rowIGV, $formIGV);
                    $formIPM = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.02,2)";
                    $sheetCalculos->setCellValue($productColumn . $rowIPM, $formIPM);
                    //form percepcion (max + advalorem + igv+ ipm)*0.035
                    $formPercepcion = "=ROUND((MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . ")*0.035,2)";
                    $sheetCalculos->setCellValue($productColumn . $rowPercepcion, $formPercepcion);

                    $formTotalTributos = "=ROUND(" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . "+" . $productColumn . $rowPercepcion . ",2)";
                    $sheetCalculos->setCellValue($productColumn . $rowTotalTributos, $formTotalTributos);
                    //Costos Destino
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucionItemDestino, '=ROUND(' . $productColumn . $rowDistribucion . ',4)');
                    $sheetCalculos->setCellValue($productColumn . $rowItemDestino, '=ROUND(' . $totalColumn . $rowItemDestino . '*(' . $productColumn . $rowDistribucionItemDestino . '),2)');
                    $sheetCalculos->setCellValue($productColumn . $rowItemCostos, '=(' . $productColumn . $rowProducto . ')');
                    //Totales - Costo Total = MAX(CIF, CIF Ajustado) + Antidumping + Total Tributos + Item Destino
                    $sheetCalculos->setCellValue($productColumn . $rowCostoTotal, '=ROUND(MAX(' . $productColumn . $rowValorCIF . ',' . $productColumn . $rowValorCIFAdjustado . ')+' . $productColumn . $rowAntidumpingValor . '+' . $productColumn . $rowTotalTributos . '+' . $productColumn . $rowItemDestino . ',2)');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoCantidad, '=(' . $productColumn . $rowCantidad . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioUSD, '=ROUND((' . $productColumn . $rowCostoTotal . ')/(' . $productColumn . $rowCostoCantidad . '),2)');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioPEN, '=ROUND((' . $productColumn . $rowCostoUnitarioUSD . ')*' . $tipoCambio . ',2)');
                    //IF PRODUCTINDEX >1 THEN INSERT ROW before NEXT ROW (to duplicate current row structure)
                    
                    $sheetResumen->setCellValue('A' . $currentRowProducto, $indexProducto);

                    $sheetResumen->setCellValue('B' . $currentRowProducto, "='2'!" . $productColumn . $rowProducto);
                    //merge b to c
                    $sheetResumen->mergeCells('B' . $currentRowProducto . ':D' . $currentRowProducto);
                    $sheetResumen->setCellValue('E' . $currentRowProducto, "='2'!" . $productColumn . $rowCantidad);
                    $sheetResumen->setCellValue('F' . $currentRowProducto, "='2'!" . $productColumn . $rowValorUnitario);
                    //merge f to g
                    $sheetResumen->mergeCells('F' . $currentRowProducto . ':G' . $currentRowProducto);
                    $sheetResumen->setCellValue('H' . $currentRowProducto, "='2'!" . $productColumn . $rowCostoUnitarioUSD);
                    $sheetResumen->setCellValue('I' . $currentRowProducto, "=E" . $currentRowProducto . "*H" . $currentRowProducto);
                    $sheetResumen->setCellValue('J' . $currentRowProducto, '=H' . $currentRowProducto . '*' . $tipoCambio);

                    //MERGE J TO K
                    $sheetResumen->mergeCells('J' . $currentRowProducto . ':K' . $currentRowProducto);
                    $sheetResumen->duplicateStyle($sheetResumen->getStyle('A'.$startRowProducto), 'A' . $currentRowProducto . ':K' . $currentRowProducto);
                    //APPLY F H I CONTAINS DOLLAR FORMAT
                    $sheetResumen->getStyle('F' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('H' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    //APPLY J S/. format
                    $sheetResumen->getStyle('J' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoSoles);
                    
                    // Tabla paralela en columnas M-Q con fórmulas de cada celda por producto
                    // Los datos se toman de la hoja 1 (resumen) sección de productos que empieza en A28
                    // $currentRowProducto es la fila actual donde se está escribiendo el producto en la hoja de resumen
                    $sheetResumen->setCellValue('M' . $currentRowProducto, "=A" . $currentRowProducto);
                    $sheetResumen->setCellValue('N' . $currentRowProducto, "=B" . $currentRowProducto);
                    $sheetResumen->setCellValue('O' . $currentRowProducto, "=F" . $currentRowProducto);
                    $sheetResumen->setCellValue('P' . $currentRowProducto, "=H" . $currentRowProducto);
                    $sheetResumen->setCellValue('Q' . $currentRowProducto, "=J" . $currentRowProducto);
                    //m and n column not are currency format
                    $sheetResumen->getStyle('M' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoTexto);
                    $sheetResumen->getStyle('N' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoTexto);
                    // Aplicar formatos a la tabla M-Q
                    $sheetResumen->getStyle('O' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('P' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('Q' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoSoles);
                    
                    // Aplicar centrado y bordes a la tabla M-Q
                    $rangeMQ = 'M' . $currentRowProducto . ':Q' . $currentRowProducto;
                    $styleMQ = $sheetResumen->getStyle($rangeMQ);
                    $styleMQ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $styleMQ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $styleMQ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    //fonfo blanco 
                    $styleMQ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                    $styleMQ->getFill()->getStartColor()->setARGB('FFFFFF');
                    //set font color black
                    $styleMQ->getFont()->getColor()->setARGB('000000');

                    $indexProducto++;
                    $currentRowProducto++;
                    $productColumnIndex++; // Incrementar el índice de columna para el siguiente producto
                    $indexP++;
                }

                // Actualizar el índice de columna para el siguiente proveedor
                $columnIndex += $numProductos;
                $indexProveedor++;
            }
            
            // Crear UN SOLO TOTAL al final de todos los productos (fuera del bucle de proveedores)
            //totales acurrentrow =TOTAL , E= SUM(e.$startRowProducto:e.$currentRowProducto-1),i= SUM(i.$startRowProducto:i.$currentRowProducto-1),j= SUM(j.$startRowProducto:j.$currentRowProducto-1)
            $sheetResumen->setCellValue('A' . $currentRowProducto, 'TOTAL');
            $sheetResumen->setCellValue('E' . $currentRowProducto, '=SUM(E' . $startRowProducto . ':E' . ($currentRowProducto - 1) . ')');
            $sheetResumen->setCellValue('I' . $currentRowProducto, '=SUM(I' . $startRowProducto . ':I' . ($currentRowProducto - 1) . ')');
            //format i to dollar
            $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
            


            //copy style from row 36 to more rows
            
            // Agregar una fila en blanco después del TOTAL final (para separar del resumen de pagos)
            $currentRowProducto++;
            //set row indexProducto
            $sheetCalculos->setCellValue($totalColumn . $rowNProveedor, '=SUM(' . ($initialColumn . $rowNProveedor) . ':' . ($sumColumn . $rowNProveedor) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowNCaja, '=SUM(' . ($initialColumn . $rowNCaja) . ':' . ($sumColumn . $rowNCaja) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowPeso, '=SUM(' . ($initialColumn . $rowPeso) . ':' . ($sumColumn . $rowPeso) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowVolProveedor, '=SUM(' . ($initialColumn . $rowVolProveedor) . ':' . ($sumColumn . $rowVolProveedor) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCantidad, '=SUM(' . ($initialColumn . $rowCantidad) . ':' . ($sumColumn . $rowCantidad) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorFob, '=SUM(' . ($initialColumn . $rowValorFob) . ':' . ($sumColumn . $rowValorFob) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorAjustado, '=SUM(' . ($initialColumn . $rowValorAjustado) . ':' . ($sumColumn . $rowValorAjustado) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowDistribucion, '=SUM(' . ($initialColumn . $rowDistribucion) . ':' . ($sumColumn . $rowDistribucion) . ')');
            if ($data['tarifa']['type'] == 'PLAIN') {
                $sheetCalculos->setCellValue($totalColumn . $rowFlete, '=' . ($data['tarifa']['tarifa']) . '*0.6');
                $sheetCalculos->setCellValue($totalColumn . $rowItemDestino, '=' . ($data['tarifa']['tarifa']) . '*0.4');
            } else {
                $sheetCalculos->setCellValue($totalColumn . $rowFlete, '=' . ($data['tarifa']['tarifa']) . '*0.6*(' . ($totalColumn . $rowVolProveedor) . ')');
                $sheetCalculos->setCellValue($totalColumn . $rowItemDestino, '=' . ($data['tarifa']['tarifa']) . '*0.4*(' . ($totalColumn . $rowVolProveedor) . ')');
            }

            $sheetCalculos->setCellValue($totalColumn . $rowValorCFR, '=ROUND(SUM(' . $initialColumn . $rowValorCFR . ':' . $sumColumn . $rowValorCFR . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCFRAjustado, '=ROUND(SUM(' . $initialColumn . $rowValorCFRAjustado . ':' . $sumColumn . $rowValorCFRAjustado . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowSeguro, '=IF(' . $totalColumn . $rowValorFob . '>=5000,100,50)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIF, '=ROUND(SUM(' . $initialColumn . $rowValorCIF . ':' . $sumColumn . $rowValorCIF . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIFAdjustado, '=ROUND(SUM(' . $initialColumn . $rowValorCIFAdjustado . ':' . $sumColumn . $rowValorCIFAdjustado . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowAntidumpingValor, '=ROUND(SUM(' . $initialColumn . $rowAntidumpingValor . ':' . $sumColumn . $rowAntidumpingValor . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowAdValoremValor, '=ROUND(SUM(' . $initialColumn . $rowAdValoremValor . ':' . $sumColumn . $rowAdValoremValor . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowIGV, '=ROUND(SUM(' . $initialColumn . $rowIGV . ':' . $sumColumn . $rowIGV . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowIPM, '=ROUND(SUM(' . $initialColumn . $rowIPM . ':' . $sumColumn . $rowIPM . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowPercepcion, '=ROUND(SUM(' . $initialColumn . $rowPercepcion . ':' . $sumColumn . $rowPercepcion . '),2)');
            $sheetCalculos->setCellValue($totalColumn . $rowTotalTributos, '=ROUND(SUM(' . $initialColumn . $rowTotalTributos . ':' . $sumColumn . $rowTotalTributos . '),2)');
            // El costo total es: MAX(ValorCIF, ValorCIF Ajustado) + Antidumping + Total Tributos + Costos Destino
            $sheetCalculos->setCellValue($totalColumn . $rowCostoTotal, '=ROUND(MAX(' . $totalColumn . $rowValorCIF . ',' . $totalColumn . $rowValorCIFAdjustado . ')+' . $totalColumn . $rowAntidumpingValor . '+' . $totalColumn . $rowTotalTributos . '+' . $totalColumn . $rowItemDestino . ',2)');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoCantidad, '=(' . $totalColumn . $rowCantidad . ')');
            // Sumar los costos unitarios USD de todos los items individuales
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioUSD, '=ROUND(SUM(' . $initialColumn . $rowCostoUnitarioUSD . ':' . $sumColumn . $rowCostoUnitarioUSD . '),2)');
            // Sumar los costos unitarios PEN de todos los items individuales
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioPEN, '=ROUND(SUM(' . $initialColumn . $rowCostoUnitarioPEN . ':' . $sumColumn . $rowCostoUnitarioPEN . '),2)');
            
            // Limpiar celdas vacías en la hoja 2 después de la última columna de productos
            $sumColumnIndex = $initialColumnIndex + $totalProductos - 1;
            $totalColumnIndex = $initialColumnIndex + $totalProductos;
            
            // Limpiar celdas vacías en columnas después de la última columna de productos
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
                            // Ignorar errores
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar errores
                }
            }
            
            //Resumen
            $sheetResumen->setCellValue('E11', $data['tarifa']['value']);
            // Determinar qué datos mostrar según tipo de documento
            $tipoDocumento = $data['clienteInfo']['tipoDocumento'] ?? 'DNI';
            $nombreMostrar = $tipoDocumento === 'RUC' ? ($data['clienteInfo']['empresa'] ?? $data['clienteInfo']['razonSocial'] ?? '') : $data['clienteInfo']['nombre'];
            $documentoMostrar = $tipoDocumento === 'RUC' ? ($data['clienteInfo']['ruc'] ?? '') : $data['clienteInfo']['dni'];
            $whatsappValue = is_array($data['clienteInfo']['whatsapp']) ? ($data['clienteInfo']['whatsapp']['value'] ?? '') : ($data['clienteInfo']['whatsapp'] ?? '');
            
            $sheetResumen->setCellValue('B8', $nombreMostrar);
            $sheetResumen->setCellValue('B9', $documentoMostrar);
            $sheetResumen->setCellValue('B10', $data['clienteInfo']['correo']);
            $sheetResumen->setCellValue('B11', $whatsappValue);
            // Agregar número de cajas, peso y volumen en la hoja de resumen
            $sheetResumen->setCellValue('I9', "='2'!" . ($totalColumn . $rowPeso)); // Peso total
            $sheetResumen->setCellValue('I10', "='2'!" . ($totalColumn . $rowNCaja)); // Número de cajas total
            $sheetResumen->setCellValue('I11', "='2'!" . ($totalColumn . $rowVolProveedor)); // Volumen total
            $sheetResumen->setCellValue('J11', "='2'!" . ($totalColumn . $rowVolProveedor));
            $sheetResumen->setCellValue('J14', "='2'!" . ($totalColumn . $rowValorFob));
            $sheetResumen->setCellValue('J15', "='2'!" . ($totalColumn . $rowFlete . "+('2'!" . $totalColumn . $rowSeguro . ")"));
            //i20 is percentage of highter percentage of advalorem between all products on page 2 ROW 33 COLUNM START FROM C COLUMN
            $finalColumnAdValorem = $getColumnLetter($initialColumnIndex + $totalColumnas - 1);
            $sheetResumen->setCellValue('I20', "=MAX('2'!C" . $rowAdValoremP . ":" . $finalColumnAdValorem . $rowAdValoremP . ")");
            //set i20 total advalorem
            $sheetResumen->setCellValue('J20', "='2'!" . ($totalColumn . $rowAdValoremValor));
            //set i21 total igv
            $sheetResumen->setCellValue('J21', "='2'!" . ($totalColumn . $rowIGV));
            //set i22 total ipm
            $sheetResumen->setCellValue('J22', "='2'!" . ($totalColumn . $rowIPM));
            $sheetResumen->setCellValue('I23', "=MAX('2'!C" . $rowAntidumpingCU . ":" . $finalColumnAdValorem . $rowAntidumpingCU . ")");

            //set i23 total antidumping
            $sheetResumen->setCellValue('J23', "='2'!" . ($totalColumn . $rowAntidumpingValor));
            //set i26 total percepcion
            $sheetResumen->setCellValue('J26', "='2'!" . ($totalColumn . $rowPercepcion));
            //set i30 i11
            if ($data['tarifa']['type'] == 'PLAIN') {
                $sheetResumen->setCellValue('J30', '=' . $data['tarifa']['tarifa']);
            } else {
                $sheetResumen->setCellValue('J30', '=I11*(' . $data['tarifa']['tarifa'] . ')');
            }

            $sheetResumen->setCellValue('J31', $data['totalExtraProveedor']+$data['totalExtraItem']);
            $sheetResumen->setCellValue('J32', $data['totalDescuento']);
            $sheetResumen->setCellValue('J33', '=J27'); // IMPUESTOS
            // Fila 34: MONTO TOTAL = SERVICIO + CARGOS EXTRAS - DESCUENTO + IMPUESTOS
            $sheetResumen->setCellValue('J34', '=J30+J31-J32+J33');
            $timestamp = now()->format('Y_m_d_H_i_s');
            $fileName = "COTIZACION_INICIAL_{$data['clienteInfo']['nombre']}_{$timestamp}.xlsx";
            $filePath = storage_path('app/public/templates/' . $fileName);

            try {
                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xlsx');
                $writer->save($filePath);

                // Verificar que el archivo se creó correctamente
                if (!file_exists($filePath)) {
                    return [
                        'url' => null,
                        'totalfob' => null,
                        'totalimpuestos' => null,
                        'logistica' => null,
                        'boleta' => null
                    ];
                }

                // Verificar que el archivo no esté vacío
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

                // Generar boleta PDF
                $boletaInfo = null;
                try {
                    $boletaInfo = $this->generateBoleta($objPHPExcel, $data['clienteInfo']);
                } catch (\Exception $e) {
                    // Continuar sin la boleta
                }

                $publicUrl = Storage::url('templates/' . $fileName);
                
                $totalFob = $sheetCalculos->getCell($totalColumn . $rowValorFob)->getCalculatedValue();
                $totalImpuestos = $sheetCalculos->getCell($totalColumn . $rowTotalTributos)->getCalculatedValue();
                //j30 + j31 if is number else 0
                $logistica = is_numeric($sheetResumen->getCell('J30')->getCalculatedValue()) && is_numeric($sheetResumen->getCell('J31')->getCalculatedValue()) ? $sheetResumen->getCell('J30')->getCalculatedValue() + $sheetResumen->getCell('J31')->getCalculatedValue() : 0;
                
                return [
                    'url' => $publicUrl,
                    'totalfob' => $totalFob,
                    'totalimpuestos' => $totalImpuestos,
                    'logistica' => $logistica,
                    'boleta' => $boletaInfo
                ];
            } catch (\Exception $e) {
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
                // Convertir URL a ruta de archivo
                if (strpos($fileUrl, 'http') === 0) {
                    $parsedUrl = parse_url($fileUrl);
                    $path = $parsedUrl['path'] ?? '';
                    if (strpos($path, '/storage/') === 0) {
                        $path = substr($path, 9); // Remover '/storage/'
                    }
                    $filePath = storage_path('app/public/' . $path);
                } else {
                    $filePath = public_path($fileUrl);
                }
                
                if (!file_exists($filePath)) {
                    throw new \Exception('Archivo Excel no encontrado: ' . $filePath);
                }
                
                $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            } else {
                $objPHPExcel = $objPHPExcelOrUrl;
            }
            
            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet();
            
            $antidumping = $sheet->getCell('A23')->getValue(); // B23 -> A23
            
            $data = [
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
