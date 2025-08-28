# Calculadora de Importación - Documentación

## Descripción
Este módulo permite almacenar y gestionar cálculos de importación con información de clientes, proveedores y productos. Está diseñado para mapear el payload JSON que viene del frontend.

## Estructura de la Base de Datos

### Tabla: `calculadora_importacion`
- **id**: Identificador único
- **id_cliente**: Referencia al cliente (opcional)
- **nombre_cliente**: Nombre del cliente
- **dni_cliente**: DNI del cliente
- **correo_cliente**: Correo electrónico del cliente
- **whatsapp_cliente**: Número de WhatsApp del cliente
- **tipo_cliente**: Tipo de cliente (NUEVO, RECURRENTE, PREMIUM, INACTIVO)
- **qty_proveedores**: Cantidad de proveedores
- **tarifa_total_extra_proveedor**: Tarifa extra por proveedor
- **tarifa_total_extra_item**: Tarifa extra por ítem
- **created_at**: Fecha de creación
- **updated_at**: Fecha de actualización

### Tabla: `calculadora_importacion_proveedores`
- **id**: Identificador único
- **id_calculadora_importacion**: Referencia a la calculadora
- **cbm**: Metros cúbicos
- **peso**: Peso en kilogramos
- **qty_caja**: Cantidad de cajas
- **created_at**: Fecha de creación
- **updated_at**: Fecha de actualización

### Tabla: `calculadora_importacion_productos`
- **id**: Identificador único
- **id_proveedor**: Referencia al proveedor
- **nombre**: Nombre del producto
- **precio**: Precio unitario
- **valoracion**: Valoración del producto
- **cantidad**: Cantidad de productos
- **antidumping_cu**: Antidumping por unidad
- **ad_valorem_p**: Ad valorem porcentual
- **created_at**: Fecha de creación
- **updated_at**: Fecha de actualización

## Modelos

### CalculadoraImportacion
Modelo principal que maneja la información del cliente y las relaciones con proveedores.

**Relaciones:**
- `cliente()`: Relación con el modelo Cliente
- `proveedores()`: Relación con los proveedores

**Atributos calculados:**
- `total_cbm`: Total de metros cúbicos
- `total_peso`: Total de peso
- `total_productos`: Total de productos

### CalculadoraImportacionProveedor
Modelo para manejar la información de los proveedores.

**Relaciones:**
- `calculadoraImportacion()`: Relación con la calculadora
- `productos()`: Relación con los productos

**Atributos calculados:**
- `total_productos`: Total de productos del proveedor
- `valor_total_productos`: Valor total de productos

### CalculadoraImportacionProducto
Modelo para manejar la información de los productos.

**Relaciones:**
- `proveedor()`: Relación con el proveedor

**Atributos calculados:**
- `valor_total`: Valor total del producto
- `total_antidumping`: Total de antidumping
- `total_ad_valorem`: Total de ad valorem

## Servicio

### CalculadoraImportacionService
Servicio que maneja toda la lógica de negocio.

**Métodos principales:**
- `guardarCalculo($data)`: Guarda un cálculo completo
- `obtenerCalculo($id)`: Obtiene un cálculo por ID
- `obtenerCalculosPorCliente($dni)`: Obtiene cálculos por DNI del cliente
- `calcularTotales($calculadora)`: Calcula totales del cálculo
- `eliminarCalculo($id)`: Elimina un cálculo

## Controlador

### CalculadoraImportacionController
Controlador que expone los endpoints de la API.

**Endpoints disponibles:**
- `POST /api/calculadora-importacion`: Guardar nuevo cálculo
- `GET /api/calculadora-importacion/{id}`: Obtener cálculo por ID
- `GET /api/calculadora-importacion/cliente`: Obtener cálculos por cliente
- `DELETE /api/calculadora-importacion/{id}`: Eliminar cálculo

## Ejemplo de Uso

### Payload de entrada (POST /api/calculadora-importacion)
```json
{
    "clienteInfo": {
        "nombre": "JENDY ROKY GONZALES MATIAS",
        "dni": "47456666",
        "whatsapp": {
            "id": 2,
            "value": "51902843298",
            "nombre": "JENDY ROKY GONZALES MATIAS",
            "documento": "47456666",
            "correo": null,
            "label": "51902843298",
            "ruc": null,
            "empresa": null,
            "fecha": "11/06/2025",
            "categoria": "NUEVO",
            "total_servicios": 0,
            "primer_servicio": null,
            "servicios": []
        },
        "correo": "",
        "qtyProveedores": 6,
        "tipoCliente": "NUEVO"
    },
    "proveedores": [
        {
            "cbm": 1.2,
            "peso": 100,
            "qtyCaja": 10,
            "productos": [
                {
                    "nombre": "Producto 1",
                    "precio": 10,
                    "valoracion": 0,
                    "cantidad": 100,
                    "antidumpingCU": 0,
                    "adValoremP": 0
                }
            ]
        }
    ],
    "tarifaTotalExtraProveedor": 150,
    "tarifaTotalExtraItem": 0
}
```

### Respuesta exitosa
```json
{
    "success": true,
    "message": "Cálculo guardado exitosamente",
    "data": {
        "calculadora": {
            "id": 1,
            "nombre_cliente": "JENDY ROKY GONZALES MATIAS",
            "dni_cliente": "47456666",
            "proveedores": [...]
        },
        "totales": {
            "total_cbm": 7.2,
            "total_peso": 600,
            "total_productos": 600,
            "valor_total_productos": 6000,
            "total_antidumping": 0,
            "total_ad_valorem": 0,
            "tarifa_total_extra_proveedor": 150,
            "tarifa_total_extra_item": 0
        }
    }
}
```

## Migraciones

Para crear las tablas, ejecuta:
```bash
php artisan migrate
```

## Seeders

Para poblar con datos de prueba, ejecuta:
```bash
php artisan db:seed --class=CalculadoraImportacionSeeder
```

## Validaciones

El sistema incluye validaciones para:
- Campos obligatorios del cliente
- Formato de correo electrónico
- Valores numéricos positivos
- Arrays no vacíos para proveedores y productos
- Cantidades mínimas válidas

## Características

- **Transacciones**: Todas las operaciones de guardado usan transacciones de base de datos
- **Relaciones en cascada**: Al eliminar una calculadora, se eliminan automáticamente proveedores y productos
- **Cálculos automáticos**: Total de CBM, peso, productos y valores
- **Búsqueda por cliente**: Permite encontrar todos los cálculos de un cliente específico
- **Logging**: Registra errores para debugging
- **Validación robusta**: Valida todos los campos de entrada
