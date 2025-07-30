# 📸 Manejo de Imágenes en Regulaciones Antidumping

## 🎯 **Problema Resuelto**
- ✅ **Imágenes nuevas** - Se pueden agregar
- ✅ **Imágenes existentes** - Se pueden eliminar selectivamente
- ✅ **Reemplazo completo** - Se pueden reemplazar todas las imágenes
- ✅ **Mantener existentes** - Se pueden conservar las imágenes actuales

## 🔧 **Estrategias de Manejo**

### 1️⃣ **Eliminar Imágenes Específicas**
```javascript
const formData = new FormData()
formData.append('id_regulacion', '2') // ID del registro a actualizar
formData.append('producto_id', '1')
formData.append('descripcion', 'Producto actualizado')
formData.append('partida', '6403.91.00.00')
formData.append('precio_declarado', '30.00')
formData.append('antidumping', 'true')
formData.append('observaciones', 'Observaciones')

// Especificar qué imágenes eliminar (IDs de la base de datos)
formData.append('imagenes_eliminar[]', '5')  // Eliminar imagen con ID 5
formData.append('imagenes_eliminar[]', '8')  // Eliminar imagen con ID 8

// Agregar nuevas imágenes
formData.append('imagenes[0]', newFile1)
formData.append('imagenes[1]', newFile2)

fetch('/api/base-datos/regulaciones/antidumping', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
})
```

### 2️⃣ **Reemplazar Todas las Imágenes**
```javascript
const formData = new FormData()
formData.append('id_regulacion', '2')
formData.append('producto_id', '1')
formData.append('descripcion', 'Producto actualizado')
formData.append('partida', '6403.91.00.00')
formData.append('precio_declarado', '30.00')
formData.append('antidumping', 'true')
formData.append('observaciones', 'Observaciones')

// Reemplazar todas las imágenes existentes
formData.append('reemplazar_imagenes', 'true')

// Nuevas imágenes (reemplazarán todas las existentes)
formData.append('imagenes[0]', newFile1)
formData.append('imagenes[1]', newFile2)

fetch('/api/base-datos/regulaciones/antidumping', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
})
```

### 3️⃣ **Solo Agregar Nuevas Imágenes**
```javascript
const formData = new FormData()
formData.append('id_regulacion', '2')
formData.append('producto_id', '1')
formData.append('descripcion', 'Producto actualizado')
formData.append('partida', '6403.91.00.00')
formData.append('precio_declarado', '30.00')
formData.append('antidumping', 'true')
formData.append('observaciones', 'Observaciones')

// Solo agregar nuevas imágenes (mantener las existentes)
formData.append('imagenes[0]', newFile1)
formData.append('imagenes[1]', newFile2)

fetch('/api/base-datos/regulaciones/antidumping', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
})
```

### 4️⃣ **Solo Eliminar Imágenes (sin agregar nuevas)**
```javascript
const formData = new FormData()
formData.append('id_regulacion', '2')
formData.append('producto_id', '1')
formData.append('descripcion', 'Producto actualizado')
formData.append('partida', '6403.91.00.00')
formData.append('precio_declarado', '30.00')
formData.append('antidumping', 'true')
formData.append('observaciones', 'Observaciones')

// Solo eliminar imágenes específicas
formData.append('imagenes_eliminar[]', '5')
formData.append('imagenes_eliminar[]', '8')

fetch('/api/base-datos/regulaciones/antidumping', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
})
```

## 📊 **Respuesta del Servidor**

### ✅ **Actualización Exitosa**
```json
{
  "success": true,
  "message": "Regulación antidumping actualizada exitosamente",
  "data": {
    "id": 2,
    "id_rubro": 1,
    "descripcion_producto": "Producto actualizado",
    "partida": "6403.91.00.00",
    "antidumping": "30.00",
    "observaciones": "Observaciones",
    "created_at": "2025-07-30T02:30:00.000000Z",
    "updated_at": "2025-07-30T02:35:00.000000Z",
    "rubro": { ... },
    "media": [
      {
        "id": 10,
        "id_regulacion": 2,
        "extension": "jpg",
        "peso": 1024000,
        "nombre_original": "imagen1.jpg",
        "ruta": "regulaciones/antidumping/1732845000_abc123.jpg",
        "created_at": "2025-07-30T02:35:00.000000Z",
        "updated_at": "2025-07-30T02:35:00.000000Z"
      }
    ],
    "media_urls": [
      {
        "id": 10,
        "extension": "jpg",
        "peso": 1024000,
        "nombre_original": "imagen1.jpg",
        "ruta": "regulaciones/antidumping/1732845000_abc123.jpg",
        "url": "http://localhost:8000/storage/regulaciones/antidumping/1732845000_abc123.jpg",
        "created_at": "2025-07-30T02:35:00.000000Z",
        "updated_at": "2025-07-30T02:35:00.000000Z"
      }
    ]
  }
}
```

## 🔍 **Logs del Servidor**

### 📝 **Eliminación de Imágenes**
```php
[2025-07-30 02:35:00] local.INFO: Eliminando imágenes especificadas: [5, 8]
[2025-07-30 02:35:00] local.INFO: Archivo eliminado del storage: {"ruta":"regulaciones/antidumping/1732844000_old1.jpg"}
[2025-07-30 02:35:00] local.INFO: Registro de media eliminado: {"id":5}
[2025-07-30 02:35:00] local.INFO: Archivo eliminado del storage: {"ruta":"regulaciones/antidumping/1732844000_old2.jpg"}
[2025-07-30 02:35:00] local.INFO: Registro de media eliminado: {"id":8}
```

### 📝 **Agregar Nuevas Imágenes**
```php
[2025-07-30 02:35:00] local.INFO: Procesando nuevas imágenes: {"cantidad":2}
[2025-07-30 02:35:00] local.INFO: Nueva imagen agregada: {"filename":"1732845000_abc123.jpg","original_name":"imagen1.jpg","size":1024000}
[2025-07-30 02:35:00] local.INFO: Nueva imagen agregada: {"filename":"1732845000_def456.jpg","original_name":"imagen2.jpg","size":2048000}
```

### 📝 **Reemplazo Completo**
```php
[2025-07-30 02:35:00] local.INFO: Reemplazando todas las imágenes existentes
[2025-07-30 02:35:00] local.INFO: Imágenes existentes eliminadas: {"cantidad":3}
[2025-07-30 02:35:00] local.INFO: Nueva imagen agregada: {"filename":"1732845000_new1.jpg","original_name":"nueva1.jpg","size":1024000}
[2025-07-30 02:35:00] local.INFO: Nueva imagen agregada: {"filename":"1732845000_new2.jpg","original_name":"nueva2.jpg","size":2048000}
```

## 🎨 **Ejemplo Frontend Completo**

```javascript
// Función para actualizar regulación con manejo de imágenes
async function actualizarRegulacionAntidumping(id, datos, imagenesNuevas = [], imagenesEliminar = []) {
  const formData = new FormData()
  
  // Datos básicos
  formData.append('id_regulacion', id.toString())
  formData.append('producto_id', datos.producto_id.toString())
  formData.append('descripcion', datos.descripcion)
  formData.append('partida', datos.partida)
  formData.append('precio_declarado', datos.precio_declarado.toString())
  formData.append('antidumping', datos.antidumping.toString())
  
  if (datos.observaciones) {
    formData.append('observaciones', datos.observaciones)
  }
  
  // Imágenes a eliminar
  if (imagenesEliminar.length > 0) {
    imagenesEliminar.forEach(imageId => {
      formData.append('imagenes_eliminar[]', imageId.toString())
    })
  }
  
  // Nuevas imágenes
  if (imagenesNuevas.length > 0) {
    imagenesNuevas.forEach((imagen, index) => {
      formData.append(`imagenes[${index}]`, imagen)
    })
  }
  
  try {
    const response = await fetch('/api/base-datos/regulaciones/antidumping', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    })
    
    const result = await response.json()
    
    if (result.success) {
      console.log('✅ Regulación actualizada:', result.data)
      return result.data
    } else {
      console.error('❌ Error:', result.message)
      throw new Error(result.message)
    }
  } catch (error) {
    console.error('❌ Error en la petición:', error)
    throw error
  }
}

// Uso:
const datos = {
  producto_id: 1,
  descripcion: 'Producto actualizado',
  partida: '6403.91.00.00',
  precio_declarado: 30.00,
  antidumping: true,
  observaciones: 'Observaciones'
}

// Eliminar imágenes con IDs 5 y 8, agregar 2 nuevas
await actualizarRegulacionAntidumping(
  2, 
  datos, 
  [file1, file2], // nuevas imágenes
  [5, 8]          // IDs de imágenes a eliminar
)
```

## 🚀 **Ventajas de esta Implementación**

1. **✅ Flexibilidad total** - Puedes eliminar, agregar o reemplazar imágenes
2. **✅ Seguridad** - Solo elimina archivos asociados al registro
3. **✅ Logs detallados** - Fácil debugging y auditoría
4. **✅ URLs automáticas** - Genera URLs completas para las imágenes
5. **✅ Validación robusta** - Valida archivos antes de procesarlos
6. **✅ Limpieza automática** - Elimina archivos físicos y registros de BD

¡Ahora tienes control total sobre las imágenes en tus regulaciones! 🎉 