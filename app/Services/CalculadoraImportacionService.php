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
                'tarifa' => $data['tarifa']->tarifa ?? 0
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
            $url = $this->crearCotizacionInicial($data);
            $calculadora->url_cotizacion = $url;
            $calculadora->save();
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
                $totales['total_productos'] += $producto->cantidad;
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

            Log::info('Hoja de cálculos obtenida correctamente: ' . $sheetCalculos->getTitle());

            $totalProductos = $data['totalProductos'];
            Log::info("Total productos: $totalProductos");
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
            // Calcular el total de columnas necesarias
            $totalColumnas = 0;
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

            // Función para convertir número de columna a letra
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
                    $sheetCalculos->setCellValue($productColumn . $rowAdValoremP, '=' . $producto['adValoremP']/100);
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
                    $sheetCalculos->setCellValue($productColumn . $rowItemDestino, '=(' . $totalColumn.$rowItemDestino.')*('.$productColumn.$rowDistribucionItemDestino.')');
                    $sheetCalculos->setCellValue($productColumn . $rowItemCostos, '=(' . $productColumn.$rowProducto.')');
                    //Totales
                    $sheetCalculos->setCellValue($productColumn . $rowCostoTotal, '=(' . $totalColumn.$rowItemDestino.')*('.$productColumn.$rowDistribucionItemDestino.')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoCantidad, '=(' . $productColumn.$rowCantidad.')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioUSD, '=(' . $productColumn.$rowCostoTotal.')/('.$productColumn.$rowCostoCantidad.')');
                    $sheetCalculos->setCellValue($productColumn . $rowCostoUnitarioPEN, '=(' . $productColumn.$rowCostoUnitarioUSD.')*'.$this->TCAMBIO);

                    $productColumnIndex++;
                }

                //totales

                // Actualizar el índice de columna para el siguiente proveedor
                $columnIndex += $numProductos;
                $indexProveedor++;
            }
            //Totales  calculos
            $sheetCalculos->setCellValue($totalColumn . $rowVolProveedor, '=SUM(' . ($initialColumn . $rowVolProveedor) . ':' . ($sumColumn . $rowVolProveedor) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowCantidad, '=SUM(' . ($initialColumn . $rowCantidad) . ':' . ($sumColumn . $rowCantidad) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorFob, '=SUM(' . ($initialColumn . $rowValorFob) . ':' . ($sumColumn . $rowValorFob) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowValorAjustado, '=SUM(' . ($initialColumn . $rowValorAjustado) . ':' . ($sumColumn . $rowValorAjustado) . ')');
            $sheetCalculos->setCellValue($totalColumn . $rowDistribucion, '=SUM(' . ($initialColumn . $rowDistribucion) . ':' . ($sumColumn . $rowDistribucion) . ')');
            if($data['tarifa']['type']=='PLAIN'){
                $sheetCalculos->setCellValue($totalColumn . $rowFlete, '=' . ($data['tarifa']['tarifa']) . '*0.6');
                $sheetCalculos->setCellValue($totalColumn . $rowItemDestino, '=' . ($data['tarifa']['tarifa']) . '*0.4');
            }else{
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
            $sheetCalculos->setCellValue($totalColumn . $rowCostoTotal, '='.$totalColumn.$rowValorFob.'+'.$totalColumn.$rowAntidumpingValor.'+'.$totalColumn.$rowTotalTributos.'+'.$totalColumn.$rowItemDestino);
            $sheetCalculos->setCellValue($totalColumn . $rowCostoCantidad, '=('.$totalColumn.$rowCantidad.')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioUSD, '=('.$totalColumn.$rowCostoTotal.')/('.$totalColumn.$rowCostoCantidad.')');
            $sheetCalculos->setCellValue($totalColumn . $rowCostoUnitarioPEN, '=('.$totalColumn.$rowCostoUnitarioUSD.')*'.$this->TCAMBIO);
            //Resumen
            //set sheet 0, e11 tarifa value
            $sheetResumen = $objPHPExcel->getSheet(0);
            $sheetResumen->setCellValue('E11', $data['tarifa']['value']);
            //set b8cliente name
            $sheetResumen->setCellValue('B8', $data['clienteInfo']['nombre']);
            //set b9 cliente dni
            $sheetResumen->setCellValue('B9', $data['clienteInfo']['dni']);
            //set b10 cliente correo
            $sheetResumen->setCellValue('B10', $data['clienteInfo']['correo']);
            //set b11 cliente whatsapp
            $sheetResumen->setCellValue('B11', $data['clienteInfo']['whatsapp']['value']);
            //set b12 cliente correo
            //set i11 sheet 2 total cbm column usign relative reference
            $sheetResumen->setCellValue('I11', "='2'!".($totalColumn.$rowVolProveedor));
            $sheetResumen->setCellValue('J11', "='2'!".($totalColumn.$rowVolProveedor));
            //set j14 total valor fob

            $sheetResumen->setCellValue('J14', "='2'!".($totalColumn.$rowValorFob));
            //set j15 total flete  total seguro
            $sheetResumen->setCellValue('J15', "='2'!".($totalColumn.$rowFlete."+('2'!".$totalColumn.$rowSeguro.")"));
     
            //set i20 total advalorem
            $sheetResumen->setCellValue('J20', "='2'!".($totalColumn.$rowAdValoremValor));
            //set i21 total igv
            $sheetResumen->setCellValue('J21', "='2'!".($totalColumn.$rowIGV));
            //set i22 total ipm
            $sheetResumen->setCellValue('J22', "='2'!".($totalColumn.$rowIPM));

            //set i23 total antidumping
            $sheetResumen->setCellValue('J23', "='2'!".($totalColumn.$rowAntidumpingValor));
            //set i26 total percepcion
            $sheetResumen->setCellValue('J26', "='2'!".($totalColumn.$rowPercepcion));
            //set i30 i11
            $sheetResumen->setCellValue('J30', '=J14');
            if($data['tarifa']['type']=='PLAIN'){
                $sheetResumen->setCellValue('J31', '='.$data['tarifa']['tarifa']);
            }else{
                $sheetResumen->setCellValue('J31', '=I11*('.$data['tarifa']['tarifa'].')');
            }
            //j32 = j27
            $sheetResumen->setCellValue('J32', '=J27');
            $timestamp = now()->format('Y_m_d_H_i_s');
            $fileName = "COTIZACION_INICIAL_{$data['clienteInfo']['nombre']}_{$timestamp}.xlsx";
            $filePath = storage_path('app/public/templates/' . $fileName);

            Log::info('Guardando archivo en: ' . $filePath);

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

                Log::info('Archivo creado exitosamente en: ' . $filePath . ' - Tamaño: ' . filesize($filePath) . ' bytes');

                // Retornar la URL pública del archivo
                $publicUrl = Storage::url('templates/' . $fileName);
                Log::info('URL pública del archivo: ' . $publicUrl);
                return $publicUrl;
            } catch (\Exception $e) {
                Log::error('Error al guardar el archivo: ' . $e->getMessage());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Error al leer plantilla de cotización inicial: ' . $e->getMessage());
            return null;
        }
    }
}
