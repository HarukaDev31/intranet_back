# GoogleSheetsHelper Trait

Este trait proporciona métodos auxiliares para interactuar con Google Sheets de manera similar a PHP Excel.

## Características

- **Obtener rangos mergeados**: Encuentra todas las celdas mergeadas en un rango específico
- **Insertar valores**: Inserta valores en celdas específicas
- **Mergear celdas**: Combina celdas desde una posición inicial hasta una final
- **Obtener valores de rango**: Lee datos de un rango específico
- **Insertar múltiples valores**: Inserta arrays bidimensionales de datos

## Uso del Trait

### 1. En tu Controller

```php
use App\Traits\GoogleSheetsHelper;

class MiController extends Controller
{
    use GoogleSheetsHelper;
    
    public function miMetodo()
    {
        // Usar los métodos del trait
        $mergedRanges = $this->getMergedRangesInRange('A1:Z100');
        $this->insertValueInCell('A1', 'Mi valor');
        $this->mergeCells('A1', 'C3');
    }
}
```

### 2. Métodos Disponibles

#### `getMergedRangesInRange($range = null)`
Obtiene todos los rangos mergeados en un rango específico.

**Parámetros:**
- `$range` (string, opcional): Rango en notación A1 (ej: "A1:Z100")

**Retorna:** Array con información detallada de cada rango mergeado.

**Ejemplo:**
```php
$mergedRanges = $this->getMergedRangesInRange('A1:C10');
// Retorna:
// [
//     [
//         'start_row' => 0,
//         'end_row' => 2,
//         'start_column' => 0,
//         'end_column' => 1,
//         'range' => 'A1:B3',
//         'start_cell' => 'A1',
//         'end_cell' => 'B3',
//         'row_count' => 2,
//         'column_count' => 1
//     ]
// ]
```

#### `insertValueInCell($cell, $value)`
Inserta un valor en una celda específica.

**Parámetros:**
- `$cell` (string): Celda en notación A1 (ej: "A1")
- `$value` (mixed): Valor a insertar

**Ejemplo:**
```php
$this->insertValueInCell('A1', 'Hola Mundo');
```

#### `mergeCells($startCell, $endCell)`
Mergea celdas desde una posición inicial hasta una final.

**Parámetros:**
- `$startCell` (string): Celda inicial (ej: "A1")
- `$endCell` (string): Celda final (ej: "C3")

**Ejemplo:**
```php
$this->mergeCells('A1', 'C3'); // Mergea desde A1 hasta C3
```

#### `getRangeValues($range)`
Obtiene valores de un rango específico.

**Parámetros:**
- `$range` (string): Rango en notación A1 (ej: "A1:C10")

**Retorna:** Array bidimensional con los valores.

**Ejemplo:**
```php
$values = $this->getRangeValues('A1:C3');
// Retorna:
// [
//     ['Valor1', 'Valor2', 'Valor3'],
//     ['Valor4', 'Valor5', 'Valor6'],
//     ['Valor7', 'Valor8', 'Valor9']
// ]
```

#### `insertRangeValues($range, $values)`
Inserta múltiples valores en un rango.

**Parámetros:**
- `$range` (string): Rango en notación A1 (ej: "A1:C3")
- `$values` (array): Array bidimensional de valores

**Ejemplo:**
```php
$values = [
    ['Nombre', 'Edad', 'Ciudad'],
    ['Juan', 25, 'Lima'],
    ['María', 30, 'Arequipa']
];
$this->insertRangeValues('A1:C3', $values);
```

## API Endpoints

El trait se puede usar a través de endpoints REST:

### GET `/api/google-sheets/merged-ranges`
Obtiene rangos mergeados.

**Parámetros de query:**
- `range` (opcional): Filtrar por rango específico

**Ejemplo:**
```
GET /api/google-sheets/merged-ranges?range=A1:C10
```

### POST `/api/google-sheets/insert-value`
Inserta un valor en una celda.

**Body:**
```json
{
    "cell": "A1",
    "value": "Mi valor"
}
```

### POST `/api/google-sheets/merge-cells`
Mergea celdas.

**Body:**
```json
{
    "start_cell": "A1",
    "end_cell": "C3"
}
```

### GET `/api/google-sheets/range-values`
Obtiene valores de un rango.

**Parámetros de query:**
- `range`: Rango a leer (ej: "A1:C10")

**Ejemplo:**
```
GET /api/google-sheets/range-values?range=A1:C10
```

## Configuración

Asegúrate de tener configurado:

1. **Service Account** en Google Cloud Console
2. **Archivo de credenciales** en `storage/credentials.json`
3. **Variables de entorno** en `.env`:
   ```
   GOOGLE_SERVICE_ENABLED=true
   POST_SPREADSHEET_ID=tu_spreadsheet_id
   POST_SHEET_ID=tu_sheet_name
   ```

## Notas Importantes

- Google Sheets usa índices base 0, pero la notación A1 usa base 1
- El trait maneja automáticamente la conversión entre formatos
- Todos los métodos incluyen manejo de errores y logging
- El Service Account debe tener permisos de lectura/escritura en el spreadsheet
