# Validación de FormData para Regulaciones Antidumping

## Problemas Comunes con FormData

### 1. **Campos Booleanos**
FormData convierte automáticamente los valores a strings, por lo que `true` se convierte en `"true"`.

**❌ Incorrecto:**
```javascript
formData.append('antidumping', true) // Se envía como "true"
```

**✅ Correcto:**
```javascript
formData.append('antidumping', 'true') // Enviar como string
// O
formData.append('antidumping', antidumpingData.antidumping.toString())
```

### 2. **Campos Numéricos**
FormData también convierte números a strings.

**❌ Incorrecto:**
```javascript
formData.append('producto_id', 123) // Se envía como "123"
formData.append('precio_declarado', 100.50) // Se envía como "100.50"
```

**✅ Correcto:**
```javascript
formData.append('producto_id', producto_id.toString())
formData.append('precio_declarado', precio_declarado.toString())
```

## Ejemplo Completo de Implementación

### Frontend (JavaScript/Vue.js)

```javascript
// Función para crear regulación antidumping
async function crearAntidumping(antidumpingData) {
  try {
    // Crear FormData
    const formData = new FormData()
    
    // Agregar campos de texto (convertir a string)
    formData.append('producto_id', antidumpingData.producto_id.toString())
    formData.append('descripcion', antidumpingData.descripcion)
    formData.append('partida', antidumpingData.partida)
    formData.append('precio_declarado', antidumpingData.precio_declarado.toString())
    formData.append('antidumping', antidumpingData.antidumping.toString()) // Convertir boolean a string
    
    // Agregar observaciones si existen
    if (antidumpingData.observaciones) {
      formData.append('observaciones', antidumpingData.observaciones)
    }
    
    // Agregar imágenes si existen
    if (antidumpingData.imagenes && antidumpingData.imagenes.length > 0) {
      antidumpingData.imagenes.forEach((imagen, index) => {
        formData.append(`imagenes[${index}]`, imagen)
      })
    }

    // Realizar petición
    const response = await fetch('/api/base-datos/regulaciones/antidumping', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}` // Token JWT
      },
      body: formData // NO incluir Content-Type, se establece automáticamente
    })

    const result = await response.json()
    
    if (!response.ok) {
      throw new Error(result.message || 'Error al crear regulación')
    }

    return result

  } catch (error) {
    console.error('Error:', error)
    throw error
  }
}

// Ejemplo de uso
const antidumpingData = {
  producto_id: 123,
  descripcion: "Producto de ejemplo",
  partida: "123456",
  precio_declarado: 100.50,
  antidumping: true,
  observaciones: "Observaciones adicionales",
  imagenes: [] // Array de archivos File
}

// Llamar función
crearAntidumping(antidumpingData)
  .then(result => {
    console.log('Éxito:', result)
  })
  .catch(error => {
    console.error('Error:', error)
  })
```

### Frontend (Axios)

```javascript
import axios from 'axios'

// Configurar interceptor para manejar 401
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      // Mostrar modal de sesión expirada
      mostrarModalSesionExpirada()
      // Redirigir al login
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

// Función para crear regulación antidumping
async function crearAntidumping(antidumpingData) {
  try {
    const formData = new FormData()
    
    // Agregar campos (convertir a string)
    formData.append('producto_id', antidumpingData.producto_id.toString())
    formData.append('descripcion', antidumpingData.descripcion)
    formData.append('partida', antidumpingData.partida)
    formData.append('precio_declarado', antidumpingData.precio_declarado.toString())
    formData.append('antidumping', antidumpingData.antidumping.toString())
    
    if (antidumpingData.observaciones) {
      formData.append('observaciones', antidumpingData.observaciones)
    }
    
    if (antidumpingData.imagenes && antidumpingData.imagenes.length > 0) {
      antidumpingData.imagenes.forEach((imagen, index) => {
        formData.append(`imagenes[${index}]`, imagen)
      })
    }

    const response = await axios.post('/api/base-datos/regulaciones/antidumping', formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        // NO incluir Content-Type, Axios lo establece automáticamente para FormData
      }
    })

    return response.data

  } catch (error) {
    console.error('Error:', error.response?.data || error.message)
    throw error
  }
}
```

## Ruta de Prueba

Para probar las validaciones, puedes usar la ruta de prueba:

```javascript
// POST /api/base-datos/regulaciones/antidumping/test-validation
// Esta ruta te mostrará exactamente qué datos llegan al backend
```

## Validaciones del Backend

### Campos Requeridos:
- `producto_id`: Entero, debe existir en `bd_productos`
- `descripcion`: String, máximo 500 caracteres
- `partida`: String, máximo 50 caracteres
- `precio_declarado`: Numérico, mínimo 0
- `antidumping`: Booleano (se convierte automáticamente)

### Campos Opcionales:
- `observaciones`: String, máximo 1000 caracteres
- `imagenes`: Array de archivos de imagen (JPEG, PNG, JPG, GIF), máximo 2MB cada uno

## Respuestas de Error

### 422 - Validación Fallida
```json
{
  "status": "error",
  "message": "Datos de entrada inválidos",
  "errors": {
    "producto_id": ["El ID del producto es obligatorio"],
    "precio_declarado": ["El precio declarado debe ser un número"]
  }
}
```

### 500 - Error del Servidor
```json
{
  "status": "error",
  "message": "Error al crear regulación antidumping: [detalles del error]"
}
```

## Logs de Debug

El backend registra logs detallados en `storage/logs/laravel.log`:

- Datos recibidos
- Conversiones de tipos
- Errores de validación
- Errores de procesamiento

## Consejos Importantes

1. **Siempre convertir a string**: Todos los valores no-string deben convertirse a string antes de agregarlos al FormData
2. **No establecer Content-Type**: Deja que el navegador establezca automáticamente el Content-Type para FormData
3. **Manejar errores**: Siempre maneja las respuestas de error del backend
4. **Validar archivos**: Verifica que los archivos sean imágenes válidas antes de enviarlos
5. **Tamaño de archivos**: Respeta el límite de 2MB por archivo 