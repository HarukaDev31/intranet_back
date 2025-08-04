# Sistema de Importación de Excel - Guía de Uso

## 📋 Descripción

Este sistema permite importar datos de pagos desde archivos Excel, manejando automáticamente tanto **consolidados** como **cursos**. El sistema detecta el tipo de registro y lo inserta en las tablas correspondientes.

## 🚀 Características

- ✅ **Detección automática** de tipo (CONSOLIDADO o CURSO)
- ✅ **Creación automática** de contenedores si no existen
- ✅ **Creación automática** de entidades si no existen
- ✅ **Validación de datos** en tiempo real
- ✅ **Plantilla descargable** con ejemplos y validaciones
- ✅ **Procesamiento desde fila 3** (filas 1-2 para encabezados)
- ✅ **Transacciones seguras** con rollback en caso de error
- ✅ **Estadísticas detalladas** del proceso

## 📊 Estructura del Excel

### Columnas Requeridas

| Columna | Posición | Descripción | Ejemplo |
|---------|----------|-------------|---------|
| ID | A | Identificador único | 1 |
| NOMBRE | B | Nombre del cliente | JESUS QUESQUEN CONDORI |
| TELÉFONO | C | Número de teléfono | 981 466 498 |
| TIPO | D | Tipo de registro | CONSOLIDADO o CURSO |
| FECHA | E | Fecha del registro | 1/01/2024 |
| CONTENEDOR | F | Nombre del contenedor | CONSOLIDADO #1 |
| DOCUMENTO | G | Número de documento | 10452681418 |
| NOMBRE COMPLETO | H | Nombre completo | JESUS ANTONIO QUESQUEN CONDORI |

### Ejemplo de Datos

```
| ID | NOMBRE | TELÉFONO | TIPO | FECHA | CONTENEDOR | DOCUMENTO | NOMBRE COMPLETO |
|----|--------|----------|------|-------|------------|-----------|-----------------|
| 1 | JESUS QUESQUEN CONDORI | 981 466 498 | CONSOLIDADO | 1/01/2024 | CONSOLIDADO #1 | 10452681418 | JESUS ANTONIO QUESQUEN CONDORI |
| 2 | MARIA GONZALEZ | 999 888 777 | CURSO | 15/01/2024 | | 12345678 | MARIA ELENA GONZALEZ LOPEZ |
```

## 🔧 Instalación y Configuración

### 1. Dependencias Requeridas

```bash
# Instalar PhpSpreadsheet para manejo de Excel
composer require phpoffice/phpspreadsheet

# Instalar Carbon para manejo de fechas (ya incluido en Laravel)
composer require nesbot/carbon
```

### 2. Configuración de Almacenamiento

```bash
# Crear directorio para archivos temporales
mkdir -p storage/app/temp
mkdir -p storage/app/templates

# Dar permisos de escritura
chmod -R 775 storage/app/temp
chmod -R 775 storage/app/templates
```

### 3. Configuración de Base de Datos

Asegúrate de que las siguientes tablas existan:
- `carga_consolidada_contenedor`
- `contenedor_consolidado_cotizacion`
- `pedido_curso`
- `entidad`
- `pais`
- `moneda`
- `campana_curso`

## 📥 Uso de la API

### 1. Descargar Plantilla

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/import/template" \
  -H "Authorization: Bearer {token}" \
  --output plantilla_importacion_pagos.xlsx
```

### 2. Importar Archivo

```bash
curl -X POST "https://api.ejemplo.com/api/carga-consolidada/import/excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@datos_pagos.xlsx"
```

### 3. Obtener Estadísticas

```bash
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/import/stats" \
  -H "Authorization: Bearer {token}"
```

## 💻 Uso del Comando Artisan

### Comando Básico

```bash
php artisan import:pagos-excel /ruta/al/archivo.xlsx
```

### Ejemplos de Uso

```bash
# Importar archivo desde directorio actual
php artisan import:pagos-excel datos_pagos.xlsx

# Importar archivo con ruta completa
php artisan import:pagos-excel /home/user/datos_pagos.xlsx

# Ver ayuda del comando
php artisan import:pagos-excel --help
```

## 🔄 Lógica de Procesamiento

### Para Consolidados

1. **Crear/Encontrar Contenedor**
   - Si el contenedor no existe, se crea automáticamente
   - Si no se especifica contenedor, se usa "Contenedor Default"

2. **Crear Cotización**
   - Se busca por documento y contenedor
   - Si no existe, se crea una nueva cotización
   - Se asigna estado "PENDIENTE"

### Para Cursos

1. **Crear/Encontrar Entidad**
   - Se busca por número de documento
   - Si no existe, se crea una nueva entidad
   - Se asignan valores por defecto (país, tipo documento, etc.)

2. **Crear Pedido de Curso**
   - Se busca por entidad
   - Si no existe, se crea un nuevo pedido
   - Se asignan valores por defecto (moneda, campaña, etc.)

## ✅ Validaciones

### Validaciones del Archivo

- ✅ Formato: .xlsx o .xls
- ✅ Tamaño máximo: 10MB
- ✅ Datos desde fila 3
- ✅ Columnas requeridas presentes

### Validaciones de Datos

- ✅ Tipo debe ser "CONSOLIDADO" o "CURSO"
- ✅ Documento no puede estar vacío
- ✅ Nombre no puede estar vacío
- ✅ Fecha en formato válido (d/m/Y, d-m-Y, Y-m-d)

### Validaciones de Negocio

- ✅ Empresa del usuario autenticado
- ✅ Contenedores únicos por empresa
- ✅ Entidades únicas por documento y empresa
- ✅ Cotizaciones únicas por documento y contenedor

## 📅 Formatos de Fecha Soportados

El sistema acepta los siguientes formatos de fecha:

- `d/m/Y` (01/01/2024)
- `d-m-Y` (01-01-2024)
- `Y-m-d` (2024-01-01)
- `d/m/y` (01/01/24)
- `d-m-y` (01-01-24)

## 🎯 Valores por Defecto

### Para Entidades Nuevas

- **Tipo de Entidad**: 1 (Cliente)
- **Tipo de Documento**: 1 (DNI)
- **País**: Perú
- **Estado**: 1 (Activo)

### Para Pedidos de Curso Nuevos

- **Moneda**: PEN (Soles)
- **Campaña**: Primera campaña activa o nueva campaña
- **Estado**: 1 (Activo)

### Para Contenedores Nuevos

- **Estado**: ACTIVO
- **Fecha de Creación**: Fecha actual

## 📊 Estadísticas de Procesamiento

### Contadores Disponibles

- **Consolidados**: Creados, actualizados, errores
- **Cursos**: Creados, actualizados, errores
- **Contenedores**: Creados, errores
- **Total**: Registros procesados

### Ejemplo de Reporte

```
=== RESULTADOS DE LA IMPORTACIÓN ===
Consolidados:
  - Creados: 15
  - Actualizados: 0
  - Errores: 0
Cursos:
  - Creados: 8
  - Actualizados: 0
  - Errores: 0
Contenedores:
  - Creados: 3
  - Errores: 0

Total de registros procesados: 23
```

## 🚨 Manejo de Errores

### Tipos de Errores

1. **Errores de Archivo**
   - Archivo no encontrado
   - Formato no válido
   - Tamaño excesivo

2. **Errores de Datos**
   - Columnas faltantes
   - Datos inválidos
   - Tipos incorrectos

3. **Errores de Base de Datos**
   - Restricciones de integridad
   - Errores de conexión
   - Transacciones fallidas

### Recuperación de Errores

- ✅ **Rollback automático** en caso de error
- ✅ **Logging detallado** de errores
- ✅ **Continuación** desde el último registro válido
- ✅ **Reporte de estadísticas** de errores

## 🔍 Troubleshooting

### Problemas Comunes

1. **Error: "El archivo no existe"**
   - Verificar que la ruta del archivo sea correcta
   - Asegurar que el archivo tenga permisos de lectura

2. **Error: "Formato no válido"**
   - Verificar que el archivo sea .xlsx o .xls
   - Asegurar que el archivo no esté corrupto

3. **Error: "Columnas faltantes"**
   - Verificar que el Excel tenga las 8 columnas requeridas
   - Asegurar que los datos empiecen desde la fila 3

4. **Error: "Tipo desconocido"**
   - Verificar que la columna TIPO contenga "CONSOLIDADO" o "CURSO"
   - Asegurar que no haya espacios extra

### Logs

Los errores se registran en:
```
storage/logs/laravel.log
```

## 📝 Ejemplos de Uso

### Ejemplo 1: Importación Básica

```bash
# 1. Descargar plantilla
curl -X GET "https://api.ejemplo.com/api/carga-consolidada/import/template" \
  -H "Authorization: Bearer {token}" \
  --output plantilla.xlsx

# 2. Llenar datos en la plantilla

# 3. Importar archivo
curl -X POST "https://api.ejemplo.com/api/carga-consolidada/import/excel" \
  -H "Authorization: Bearer {token}" \
  -F "excel_file=@plantilla.xlsx"
```

### Ejemplo 2: Uso del Comando

```bash
# Crear archivo de ejemplo
echo "ID,NOMBRE,TELÉFONO,TIPO,FECHA,CONTENEDOR,DOCUMENTO,NOMBRE COMPLETO" > datos.csv
echo "1,JESUS QUESQUEN CONDORI,981 466 498,CONSOLIDADO,1/01/2024,CONSOLIDADO #1,10452681418,JESUS ANTONIO QUESQUEN CONDORI" >> datos.csv

# Convertir a Excel (usar Excel o LibreOffice)
# Luego importar
php artisan import:pagos-excel datos_pagos.xlsx
```

## 🔧 Configuración Avanzada

### Personalizar Valores por Defecto

Editar el archivo `app/Console/Commands/ImportPagosExcel.php`:

```php
// Cambiar país por defecto
$pais = Pais::first() ?? Pais::create(['No_Pais' => 'México']);

// Cambiar moneda por defecto
$moneda = Moneda::first() ?? Moneda::create(['No_Moneda' => 'USD']);

// Cambiar tipo de documento por defecto
'ID_Tipo_Documento_Identidad' => 2, // CE en lugar de DNI
```

### Personalizar Validaciones

```php
// Agregar validaciones personalizadas en el método processRow()
if (strlen($data['documento']) < 8) {
    $this->error("Fila {$row}: Documento muy corto");
    return;
}
```

## 📚 Archivos del Sistema

### Comandos
- `app/Console/Commands/ImportPagosExcel.php` - Comando principal

### Controladores
- `app/Http/Controllers/CargaConsolidada/ImportController.php` - API de importación

### Rutas
- `routes/api.php` - Rutas de la API

### Documentación
- `API_IMPORT_EXCEL_DOCUMENTATION.md` - Documentación completa de la API
- `README_IMPORT_EXCEL.md` - Esta guía

## 🤝 Contribución

Para contribuir al sistema:

1. Crear una rama para tu feature
2. Implementar los cambios
3. Agregar tests si es necesario
4. Actualizar la documentación
5. Crear un pull request

## 📞 Soporte

Para soporte técnico:
- Revisar los logs en `storage/logs/laravel.log`
- Verificar la documentación de la API
- Contactar al equipo de desarrollo

---

**Versión**: 1.0  
**Última actualización**: Enero 2024  
**Autor**: Sistema de Importación Excel 