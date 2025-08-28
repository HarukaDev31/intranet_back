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

class CalculadoraImportacionService
{
    public $TCAMBIO = 3.75;
    public $formatoDollar = '"$"#,##0.00_-';
    public $formatoSoles = '"S/." #,##0.00_-';
    /**
     * Guardar cálculo de importación completo
     */
    public function guardarCalculo(array $data): CalculadoraImportacion
    {
        try {
            DB::beginTransaction();

            // Crear o actualizar cliente si existe
            $cliente = $this->buscarOcrearCliente($data['clienteInfo']);

            // Crear registro principal
            $calculadora = CalculadoraImportacion::create([
                'id_cliente' => $cliente ? $cliente->id : null,
                'nombre_cliente' => $data['clienteInfo']['nombre'],
                'dni_cliente' => $data['clienteInfo']['dni'],
                'correo_cliente' => $data['clienteInfo']['correo'] ?: null,
                'whatsapp_cliente' => $data['clienteInfo']['whatsapp']['value'] ?? null,
                'tipo_cliente' => $data['clienteInfo']['tipoCliente'],
                'qty_proveedores' => $data['clienteInfo']['qtyProveedores'],
                'tarifa_total_extra_proveedor' => $data['tarifaTotalExtraProveedor'] ?? 0,
                'tarifa_total_extra_item' => $data['tarifaTotalExtraItem'] ?? 0,
                'tarifa' => $data['tarifa']['tarifa'] ?? 0,
                'estado' => CalculadoraImportacion::ESTADO_PENDIENTE
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
                        'valoracion' => $productoData['valoracion'],
                        'cantidad' => $productoData['cantidad'],
                        'antidumping_cu' => $productoData['antidumpingCU'],
                        'ad_valorem_p' => $productoData['adValoremP']
                    ]);
                    $totalProductos += 1;
                }
            }
            $data['totalProductos'] = $totalProductos;
            $result = $this->crearCotizacionInicial($data);
            $url = $result['url'];
            $totalFob = $result['totalfob'];
            $totalImpuestos = $result['totalimpuestos'];
            $logistica = $result['logistica'];
            $boletaInfo = $result['boleta'];
            
            Log::info('url: ' . $url);
            Log::info('totalFob: ' . $totalFob);
            Log::info('totalImpuestos: ' . $totalImpuestos);
            Log::info('logistica: ' . $logistica);
            if ($boletaInfo) {
                Log::info('Boleta generada: ' . $boletaInfo['filename']);
            }
            
            $calculadora->url_cotizacion = $url;
            $calculadora->total_fob = $totalFob;
            $calculadora->total_impuestos = $totalImpuestos;
            $calculadora->logistica = $logistica;
            
            // Guardar URL del PDF si se generó la boleta
            if ($boletaInfo) {
                $calculadora->url_cotizacion_pdf = $boletaInfo['url'];
                Log::info('Boleta PDF guardada en: ' . $boletaInfo['path']);
                Log::info('URL pública del PDF: ' . $boletaInfo['url']);
            }
            
            $calculadora->save();
            
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
            'tarifa_total_extra_item' => $calculadora->tarifa_total_extra_item
        ];

        foreach ($calculadora->proveedores as $proveedor) {
            $totales['total_cbm'] += $proveedor->cbm;
            $totales['total_peso'] += $proveedor->peso;

            foreach ($proveedor->productos as $producto) {
                $totales['total_productos'] += 1;
                $totales['valor_total_productos'] += $producto->valor_total;
                $totales['total_antidumping'] += $producto->total_antidumping;
                $totales['total_ad_valorem'] += $producto->total_ad_valorem;
            }
        }

        return $totales;
    }

    /**
     * Eliminar cálculo y todos sus datos relacionados
     */
    public function eliminarCalculo(int $id): bool
    {
        try {
            $calculadora = CalculadoraImportacion::findOrFail($id);
            $calculadora->delete(); // Esto eliminará en cascada proveedores y productos
            return true;
        } catch (\Exception $e) {
            Log::error('Error al eliminar cálculo de importación: ' . $e->getMessage());
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
        // Aumentar memoria para procesar archivos Excel grandes
     
        try {

            Log::info('Datos recibidos en crearCotizacionInicial:', [
                'data' => $data
            ]);
            $plantillaPath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL.xlsx');

            if (!file_exists($plantillaPath)) {
                Log::error('Plantilla de cotización inicial no encontrada en: ' . $plantillaPath);
                return 'Plantilla de cotización inicial no encontrada: ' . $plantillaPath;
            }


            try {
                $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($plantillaPath);

                // Verificar que la plantilla se cargó correctamente
                if (!$objPHPExcel) {
                    Log::error('La plantilla Excel no se cargó correctamente');
                    return 'Error: La plantilla Excel no se cargó correctamente';
                }

                // Verificar que tiene al menos una hoja
                if ($objPHPExcel->getSheetCount() === 0) {
                    Log::error('La plantilla Excel no tiene hojas');
                    return 'Error: La plantilla Excel no tiene hojas';
                }

                Log::info('Plantilla Excel cargada correctamente. Hojas disponibles: ' . $objPHPExcel->getSheetCount());
            } catch (\Exception $e) {
                Log::error('Error al cargar plantilla Excel: ' . $e->getMessage());
                return 'Error al cargar plantilla Excel: ' . $e->getMessage();
            }

            // Verificar que existe la hoja 1 (índice 1)
            if ($objPHPExcel->getSheetCount() < 2) {
                Log::error('La plantilla no tiene suficientes hojas. Se requiere al menos la hoja 1');
                return 'Error: La plantilla no tiene suficientes hojas';
            }

            $sheetCalculos = $objPHPExcel->getSheet(1);

            // Verificar que la hoja se obtuvo correctamente
            if (!$sheetCalculos) {
                Log::error('No se pudo obtener la hoja de cálculos');
                return 'Error: No se pudo obtener la hoja de cálculos';
            }


            $totalProductos = $data['totalProductos'];
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
            $sheetResumen = $objPHPExcel->getSheet(0);
            foreach ($data['proveedores'] as $proveedor) {
                $totalColumnas += count($proveedor['productos']);
            }

            Log::info("Total de columnas necesarias: $totalColumnas");

            // Solo insertar columnas si necesitamos más de 1 (ya que C está disponible)
            if ($totalColumnas > 1) {
                $columnasAInsertar = $totalColumnas - 1; // Restamos 1 porque ya tenemos la columna C
                $sheetCalculos->insertNewColumnBefore('D', $columnasAInsertar);
                Log::info("Se insertaron $columnasAInsertar columnas adicionales después de C");
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

            $indexProducto = 1;
            $startRowProducto = 37;
            $currentRowProducto = $startRowProducto;
            foreach ($data['proveedores'] as $proveedor) {
                $numProductos = count($proveedor['productos']);

                Log::info("Procesando proveedor $indexProveedor con $numProductos productos");

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
                $totalColumn = $getColumnLetter($initialColumnIndex + $totalProductos);

                $sumColumn = $getColumnLetter($initialColumnIndex + $totalProductos - 1);

                // Ahora procesar cada producto en su propia columna
                $productColumnIndex = $columnIndex;
                foreach ($proveedor['productos'] as $productoIndex => $producto) {
                    $productColumn = $getColumnLetter($productColumnIndex);

                    $sheetCalculos->setCellValue($productColumn . $rowProducto, $producto['nombre']);
                    $sheetCalculos->setCellValue($productColumn . $rowValorUnitario, $producto['precio']);
                    $sheetCalculos->setCellValue($productColumn . $rowValoracion, $producto['valoracion']);
                    $sheetCalculos->setCellValue($productColumn . $rowCantidad, $producto['cantidad']);
                    // Calcular valores
                    $sheetCalculos->setCellValue($productColumn . $rowValorFob, '=' . $productColumn . $rowValorUnitario . '*' . $productColumn . $rowCantidad);
                    $sheetCalculos->setCellValue($productColumn . $rowValorAjustado, '=' . ($productColumn . $rowValoracion) . '*' . ($productColumn . $rowCantidad));
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucion, '=' . ($productColumn . $rowValorFob) . '/' . ($totalColumn . $rowValorFob));
                    $sheetCalculos->setCellValue($productColumn . $rowFlete, '=' . ($productColumn . $rowDistribucion) . '*' . ($totalColumn . $rowFlete));
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFR, '=' . ($productColumn . $rowValorFob) . '+' . ($productColumn . $rowFlete));
                    $sheetCalculos->setCellValue($productColumn . $rowValorCFRAjustado, '=' . ($productColumn . $rowValorAjustado) . '+' . ($productColumn . $rowFlete));
                    $sheetCalculos->setCellValue($productColumn . $rowSeguro, '=' . ($totalColumn . $rowSeguro) . '*' . ($productColumn . $rowDistribucion));
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIF, '=' . ($productColumn . $rowValorCFR) . '+' . ($productColumn . $rowSeguro));
                    $sheetCalculos->setCellValue($productColumn . $rowValorCIFAdjustado, '=' . ($productColumn . $rowValorCFRAjustado) . '+' . ($productColumn . $rowSeguro));
                    //tributos
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingCU, '=' . $producto['antidumpingCU']);
                    $sheetCalculos->setCellValue($productColumn . $rowAntidumpingValor, '=' . $productColumn . $rowCantidad . '*' . $productColumn . $rowAntidumpingCU);
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremP, '=' . $producto['adValoremP'] / 100);
                    $formADVALOREM = "=MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")*" . $productColumn . $rowAdValoremP;
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremValor, $formADVALOREM);
                    $formIGV = "=(MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.16";
                    $sheetCalculos->setCellValue($productColumn . $rowIGV, $formIGV);
                    $formIPM = "=(MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . ")*0.02";
                    $sheetCalculos->setCellValue($productColumn . $rowIPM, $formIPM);
                    //form percepcion (max + advalorem + igv+ ipm)*0.035
                    $formPercepcion = "=(MAX(" . $productColumn . $rowValorCIF . ":" . $productColumn . $rowValorCIFAdjustado . ")+" . $productColumn . $rowAdValoremP . "+" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . ")*0.035";
                    $sheetCalculos->setCellValue($productColumn . $rowPercepcion, $formPercepcion);

                    $formTotalTributos = "=" . $productColumn . $rowAdValoremValor . "+" . $productColumn . $rowIGV . "+" . $productColumn . $rowIPM . "+" . $productColumn . $rowPercepcion;
                    $sheetCalculos->setCellValue($productColumn . $rowTotalTributos, $formTotalTributos);
                    //Costos Destino
                    $sheetCalculos->setCellValue($productColumn . $rowDistribucionItemDestino, '=(' . $productColumn . $rowDistribucion . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowItemDestino, '=(' . $totalColumn . $rowItemDestino . ')*(' . $productColumn . $rowDistribucionItemDestino . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowItemCostos, '=(' . $productColumn . $rowProducto . ')');
                    //Totales
                    $sheetCalculos->setCellValue($productColumn . $rowCostoTotal, '=(' . $totalColumn . $rowItemDestino . ')*(' . $productColumn . $rowDistribucionItemDestino . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoCantidad, '=(' . $productColumn . $rowCantidad . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioUSD, '=(' . $productColumn . $rowCostoTotal . ')/(' . $productColumn . $rowCostoCantidad . ')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioPEN, '=(' . $productColumn . $rowCostoUnitarioUSD . ')*' . $this->TCAMBIO);
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
                    $sheetResumen->setCellValue('J' . $currentRowProducto, '=H' . $currentRowProducto . '*' . $this->TCAMBIO);

                    //MERGE J TO K
                    $sheetResumen->mergeCells('J' . $currentRowProducto . ':K' . $currentRowProducto);
                    $sheetResumen->duplicateStyle($sheetResumen->getStyle('A36'), 'A' . $currentRowProducto . ':K' . $currentRowProducto);
                    //APPLY F H I CONTAINS DOLLAR FORMAT
                    $sheetResumen->getStyle('F' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('H' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                    //APPLY J S/. format
                    $sheetResumen->getStyle('J' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoSoles);
                    $indexProducto++;
                    $currentRowProducto++;
                }

                //totales acurrentrow =TOTAL , E= SUM(e.$startRowProducto:e.$currentRowProducto-1),i= SUM(i.$startRowProducto:i.$currentRowProducto-1),j= SUM(j.$startRowProducto:j.$currentRowProducto-1)
                $sheetResumen->setCellValue('A' . $currentRowProducto, 'TOTAL');
                $sheetResumen->setCellValue('E' . $currentRowProducto, '=SUM(E36:E' . ($currentRowProducto - 1) . ')');
                $sheetResumen->setCellValue('I' . $currentRowProducto, '=SUM(I36:I' . ($currentRowProducto - 1) . ')');
                //format i to dollar
                $sheetResumen->getStyle('I' . $currentRowProducto)->getNumberFormat()->setFormatCode($this->formatoDollar);
                //copy style from row 36 to more rows

                // Actualizar el índice de columna para el siguiente proveedor
                $columnIndex += $numProductos;
                $indexProveedor++;
            }
            //set row indexProducto
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

            $sheetCalculos->setCellValue($totalColumn . $rowValorCFR, '=SUM(' . $initialColumn . $rowValorCFR . ':' . $sumColumn . $rowValorCFR . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCFRAjustado, '=SUM(' . $initialColumn . $rowValorCFRAjustado . ':' . $sumColumn . $rowValorCFRAjustado . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowSeguro, '=IF(' . $totalColumn . $rowValorFob . '>=5000,100,50)');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIF, '=SUM(' . $initialColumn . $rowValorCIF . ':' . $sumColumn . $rowValorCIF . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorCIFAdjustado, '=SUM(' . $initialColumn . $rowValorCIFAdjustado . ':' . $sumColumn . $rowValorCIFAdjustado . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowAntidumpingValor, '=SUM(' . $initialColumn . $rowAntidumpingValor . ':' . $sumColumn . $rowAntidumpingValor . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowAdValoremValor, '=SUM(' . $initialColumn . $rowAdValoremValor . ':' . $sumColumn . $rowAdValoremValor . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowIGV, '=SUM(' . $initialColumn . $rowIGV . ':' . $sumColumn . $rowIGV . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowIPM, '=SUM(' . $initialColumn . $rowIPM . ':' . $sumColumn . $rowIPM . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowPercepcion, '=SUM(' . $initialColumn . $rowPercepcion . ':' . $sumColumn . $rowPercepcion . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowTotalTributos, '=SUM(' . $initialColumn . $rowTotalTributos . ':' . $sumColumn . $rowTotalTributos . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoTotal, '=' . $totalColumn . $rowValorFob . '+' . $totalColumn . $rowAntidumpingValor . '+' . $totalColumn . $rowTotalTributos . '+' . $totalColumn . $rowItemDestino);
            $sheetCalculos->setCellValue($totalColumn . $rowCostoCantidad, '=(' . $totalColumn . $rowCantidad . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioUSD, '=(' . $totalColumn . $rowCostoTotal . ')/(' . $totalColumn . $rowCostoCantidad . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioPEN, '=(' . $totalColumn . $rowCostoUnitarioUSD . ')*' . $this->TCAMBIO);
            //Resumen
            $sheetResumen->setCellValue('E11', $data['tarifa']['value']);
            $sheetResumen->setCellValue('B8', $data['clienteInfo']['nombre']);
            $sheetResumen->setCellValue('B9', $data['clienteInfo']['dni']);
            $sheetResumen->setCellValue('B10', $data['clienteInfo']['correo']);
            $sheetResumen->setCellValue('B11', $data['clienteInfo']['whatsapp']['value']);
            $sheetResumen->setCellValue('I11', "='2'!" . ($totalColumn . $rowVolProveedor));
            $sheetResumen->setCellValue('J11', "='2'!" . ($totalColumn . $rowVolProveedor));
            $sheetResumen->setCellValue('J14', "='2'!" . ($totalColumn . $rowValorFob));
            $sheetResumen->setCellValue('J15', "='2'!" . ($totalColumn . $rowFlete . "+('2'!" . $totalColumn . $rowSeguro . ")"));

            //set i20 total advalorem
            $sheetResumen->setCellValue('J20', "='2'!" . ($totalColumn . $rowAdValoremValor));
            //set i21 total igv
            $sheetResumen->setCellValue('J21', "='2'!" . ($totalColumn . $rowIGV));
            //set i22 total ipm
            $sheetResumen->setCellValue('J22', "='2'!" . ($totalColumn . $rowIPM));

            //set i23 total antidumping
            $sheetResumen->setCellValue('J23', "='2'!" . ($totalColumn . $rowAntidumpingValor));
            //set i26 total percepcion
            $sheetResumen->setCellValue('J26', "='2'!" . ($totalColumn . $rowPercepcion));
            //set i30 i11
            $sheetResumen->setCellValue('J30', '=J14');
            if ($data['tarifa']['type'] == 'PLAIN') {
                $sheetResumen->setCellValue('J31', '=' . $data['tarifa']['tarifa']);
            } else {
                $sheetResumen->setCellValue('J31', '=I11*(' . $data['tarifa']['tarifa'] . ')');
            }

            $sheetResumen->setCellValue('J32', '=J27');
            $timestamp = now()->format('Y_m_d_H_i_s');
            $fileName = "COTIZACION_INICIAL_{$data['clienteInfo']['nombre']}_{$timestamp}.xlsx";
            $filePath = storage_path('app/public/templates/' . $fileName);


            try {
                // Guardar el archivo con nombre único
                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPHPExcel, 'Xlsx');
                $writer->save($filePath);

                // Verificar que el archivo se creó correctamente
                if (!file_exists($filePath)) {
                    Log::error('El archivo no se creó correctamente en: ' . $filePath);
                    return null;
                }

                // Verificar que el archivo no esté vacío
                if (filesize($filePath) === 0) {
                    Log::error('El archivo se creó pero está vacío: ' . $filePath);
                    return null;
                }

                // Generar boleta PDF
                $boletaInfo = null;
                try {
                    $boletaInfo = $this->generateBoleta($objPHPExcel, $data['clienteInfo']);
                    Log::info('Boleta PDF generada exitosamente: ' . $boletaInfo['filename']);
                } catch (\Exception $e) {
                    Log::warning('No se pudo generar la boleta PDF: ' . $e->getMessage());
                    // Continuar sin la boleta
                }

                $publicUrl = Storage::url('templates/' . $fileName);
                $totalFob = $sheetCalculos->getCell($totalColumn . $rowValorFob)->getCalculatedValue();
                $totalImpuestos = $sheetCalculos->getCell($totalColumn . $rowTotalTributos)->getCalculatedValue();
                $logistica = $sheetResumen->getCell('J31')->getCalculatedValue();
                
                return [
                    'url' => $publicUrl,
                    'totalfob' => $totalFob,
                    'totalimpuestos' => $totalImpuestos,
                    'logistica' => $logistica,
                    'boleta' => $boletaInfo
                ];
            } catch (\Exception $e) {
                Log::error('Error al guardar el archivo: ' . $e->getMessage());
                return [
                    'url' => null,
                    'totalfob' => null,
                    'totalimpuestos' => null,
                    'logistica' => null,
                    'boleta' => null
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error al leer plantilla de cotización inicial: ' . $e->getMessage());
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
    private function generateBoleta($objPHPExcel, $clienteInfo)
    {
        try {
            // Aumentar memoria para procesar archivos Excel grandes
            ini_set('memory_limit', '2G');
            ini_set('max_execution_time', 300);
            
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
                "peso" => $this->getCellValueAsFloat($sheet, 'I9'), // J9 -> I9
                "qtysuppliers" => $sheet->getCell('I10')->getValue(), // J10 -> I10
                "cbm" => $this->getCellValueAsFloat($sheet, 'I11'), // J11 -> I11
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
                "valorcargaproveedor" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J30'), 2) : round($this->getCellValueAsFloat($sheet, 'J29'), 2), // K30/K29 -> J30/J29
                "servicioimportacion" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J31'), 2) : round($this->getCellValueAsFloat($sheet, 'J30'), 2), // K31/K30 -> J31/J30
                "impuestos" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J32'), 2) : round($this->getCellValueAsFloat($sheet, 'J31'), 2), // K32/K31 -> J32/J31
                "montototal" => $antidumping == "ANTIDUMPING" ? round($this->getCellValueAsFloat($sheet, 'J33'), 2) : round($this->getCellValueAsFloat($sheet, 'J32'), 2), // K33/K32 -> J33/J32
            ];
            Log::info(json_encode($data));
            $i = $antidumping == "ANTIDUMPING" ? 37 : 36;
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
            $htmlFilePath = public_path('assets/templates/PLANTILLA_COTIZACION_INICIAL.html');
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
