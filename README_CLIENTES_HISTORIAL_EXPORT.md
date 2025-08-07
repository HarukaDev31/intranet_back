# Exportación de Clientes con Historial de Compras

## Descripción
Sistema de exportación de clientes que incluye información principal del cliente y su historial de compras en formato Excel.

## Características
- ✅ Exportación a Excel con formato profesional
- ✅ Información principal del cliente (N., NOMBRE, DNI, CORREO, WHATSAPP, FECHAS, SERVICIO, CATEGORIA)
- ✅ Historial de compras (hasta 3 compras más recientes)
- ✅ Filtros por empresa, organización y estado
- ✅ Vista previa de datos antes de exportar
- ✅ Estilos personalizados en Excel
- ✅ Comando de prueba para verificar funcionalidad

## Estructura de Columnas

### INFORMACIÓN PRINCIPAL
1. **N.** - ID del cliente
2. **NOMBRE** - Nombre completo del cliente
3. **DNI** - Número de documento de identidad
4. **CORREO** - Dirección de email
5. **WHATSAPP** - Número de celular
6. **FECHAS** - Fecha de registro del cliente
7. **SERVICIO** - Tipo de servicio principal (Curso, Carga Consolidada, etc.)
8. **CATEGORIA** - Categoría del cliente (Agente de Compra, Empresa, Cliente Individual)

### HISTORIAL DE COMPRA
Para cada compra (máximo 3):
- **FECHAS** - Fecha de la compra
- **SERVICIO** - Servicio comprado (campaña)
- **MONTO** - Monto total de la compra

## Instalación y Uso

### 1. Verificar Dependencias
Asegúrate de tener instalado el paquete `maatwebsite/excel`:
```bash
composer require maatwebsite/excel
```

### 2. Comando de Prueba
Para verificar que la funcionalidad funciona correctamente:
```bash
# Prueba básica
php artisan test:clientes-historial-export

# Con filtros específicos
php artisan test:clientes-historial-export --empresa=1 --organizacion=1 --estado=1
```

### 3. Endpoints de la API

#### Exportar Clientes con Historial
```bash
GET /api/base-datos/clientes-historial/export
```

**Parámetros de consulta:**
- `empresa` (opcional) - ID de la empresa
- `organizacion` (opcional) - ID de la organización
- `estado` (opcional) - Estado del cliente (1=Activo, 0=Inactivo)
- `fecha_desde` (opcional) - Fecha desde para filtrar
- `fecha_hasta` (opcional) - Fecha hasta para filtrar
- `tipo_servicio` (opcional) - Tipo de servicio
- `categoria` (opcional) - Categoría del cliente

**Ejemplo:**
```bash
GET /api/base-datos/clientes-historial/export?empresa=1&estado=1
```

#### Vista Previa de Datos
```bash
GET /api/base-datos/clientes-historial/preview
```

**Respuesta:**
```json
{
    "status": "success",
    "headers": ["N.", "NOMBRE", "DNI", ...],
    "data": [...],
    "total_records": 150,
    "preview_records": 10
}
```

#### Opciones de Filtros
```bash
GET /api/base-datos/clientes-historial/filter-options
```

**Respuesta:**
```json
{
    "status": "success",
    "options": {
        "empresas": [...],
        "organizaciones": [...],
        "tipos_servicio": [...],
        "categorias": [...],
        "estados": [...]
    }
}
```

## Archivos Creados

### 1. Export Class
- **Archivo:** `app/Exports/ClientesHistorialExport.php`
- **Función:** Maneja la lógica de exportación y mapeo de datos

### 2. Controller
- **Archivo:** `app/Http/Controllers/ClientesHistorialController.php`
- **Función:** Maneja las peticiones HTTP y respuestas de la API

### 3. Test Command
- **Archivo:** `app/Console/Commands/TestClientesHistorialExport.php`
- **Función:** Comando para probar la funcionalidad

### 4. Routes
- **Archivo:** `routes/api.php` (agregadas nuevas rutas)
- **Función:** Define los endpoints de la API

## Tipos de Servicio

Los clientes pueden tener los siguientes tipos de servicio:
- **Curso** - Cliente que ha tomado cursos
- **Carga Consolidada** - Cliente de carga consolidada
- **Importación Grupal** - Cliente de importación grupal
- **Viaje de Negocios** - Cliente de viajes de negocios
- **Cliente General** - Cliente sin servicio específico

## Categorías de Cliente

Los clientes se clasifican en:
- **Agente de Compra** - Cliente que actúa como agente
- **Empresa** - Cliente corporativo
- **Cliente Individual** - Cliente particular

## Estructura de la Base de Datos

### Tablas Principales:
- `entidad` - Información principal de clientes
- `pedido_curso` - Pedidos/cursos de los clientes
- `pedido_curso_pagos` - Pagos de los pedidos
- `campana` - Campañas/servicios

### Relaciones:
- Un cliente (entidad) puede tener múltiples pedidos
- Cada pedido puede tener múltiples pagos
- Los pedidos están asociados a campañas

## Ejemplo de Uso en Frontend

```javascript
// Exportar clientes
async function exportarClientes(filtros = {}) {
    const params = new URLSearchParams(filtros);
    const response = await fetch(`/api/base-datos/clientes-historial/export?${params}`);
    
    if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'clientes_historial.xlsx';
        a.click();
    }
}

// Obtener vista previa
async function obtenerVistaPrevia(filtros = {}) {
    const params = new URLSearchParams(filtros);
    const response = await fetch(`/api/base-datos/clientes-historial/preview?${params}`);
    return await response.json();
}

// Obtener opciones de filtros
async function obtenerOpcionesFiltros() {
    const response = await fetch('/api/base-datos/clientes-historial/filter-options');
    return await response.json();
}
```

## Notas Importantes

1. **Autenticación:** Todas las rutas requieren autenticación JWT
2. **Filtros:** Los filtros son opcionales y se pueden combinar
3. **Historial:** Solo se muestran las 3 compras más recientes por cliente
4. **Formato:** El archivo Excel se genera con estilos profesionales
5. **Rendimiento:** Para grandes volúmenes de datos, considera usar colas (queues)

## Troubleshooting

### Error: "No se encontraron clientes"
- Verifica que existan clientes en la base de datos
- Revisa los filtros aplicados
- Asegúrate de que los IDs de empresa/organización sean correctos

### Error: "Error al exportar"
- Verifica que el paquete `maatwebsite/excel` esté instalado
- Revisa los permisos de escritura en el directorio de storage
- Verifica la configuración de la base de datos

### Error: "Columnas vacías"
- Verifica que los clientes tengan pedidos asociados
- Revisa las relaciones entre tablas
- Asegúrate de que los campos requeridos no sean null 