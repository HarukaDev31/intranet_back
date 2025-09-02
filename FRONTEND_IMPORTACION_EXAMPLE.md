# Ejemplo de Frontend para Importación de Excel

## Descripción

Este documento muestra cómo implementar el polling para verificar el estado de la importación de Excel en el frontend.

## Implementación

### 1. Función de Importación

```javascript
async function importarExcel(file, idContenedor) {
    try {
        const formData = new FormData();
        formData.append('excel_file', file);
        formData.append('idContenedor', idContenedor);

        const response = await fetch('/api/productos/import', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            console.log('Importación iniciada:', data.data.import_id);
            
            // Iniciar polling para verificar el estado
            iniciarPolling(data.data.import_id);
            
            return data;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error al importar:', error);
        throw error;
    }
}
```

### 2. Función de Polling

```javascript
function iniciarPolling(importId) {
    const interval = setInterval(async () => {
        try {
            const response = await fetch(`/api/productos/import/status/${importId}`);
            const data = await response.json();

            if (data.success) {
                const stats = data.data.estadisticas;
                
                // Actualizar UI con el progreso
                actualizarProgreso(stats);
                
                // Verificar si la importación terminó
                if (stats.status === 'completed') {
                    clearInterval(interval);
                    mostrarCompletado(stats);
                } else if (stats.status === 'failed') {
                    clearInterval(interval);
                    mostrarError(stats.error);
                }
            }
        } catch (error) {
            console.error('Error al verificar estado:', error);
            clearInterval(interval);
        }
    }, 2000); // Verificar cada 2 segundos
}
```

### 3. Funciones de UI

```javascript
function actualizarProgreso(stats) {
    const progressElement = document.getElementById('import-progress');
    if (progressElement) {
        const porcentaje = stats.total_productos > 0 
            ? (stats.productos_importados / stats.total_productos) * 100 
            : 0;
        
        progressElement.innerHTML = `
            <div class="progress">
                <div class="progress-bar" style="width: ${porcentaje}%"></div>
            </div>
            <p>Procesando: ${stats.productos_importados} / ${stats.total_productos} productos</p>
        `;
    }
}

function mostrarCompletado(stats) {
    const messageElement = document.getElementById('import-message');
    if (messageElement) {
        messageElement.innerHTML = `
            <div class="alert alert-success">
                <h4>✅ Importación Completada</h4>
                <p>${stats.productos_importados} productos importados exitosamente</p>
                <p>Errores: ${stats.errores || 0}</p>
            </div>
        `;
    }
}

function mostrarError(error) {
    const messageElement = document.getElementById('import-message');
    if (messageElement) {
        messageElement.innerHTML = `
            <div class="alert alert-danger">
                <h4>❌ Error en la Importación</h4>
                <p>${error}</p>
            </div>
        `;
    }
}
```

### 4. Uso Completo

```javascript
// Ejemplo de uso
document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fileInput = document.getElementById('excel-file');
    const contenedorSelect = document.getElementById('contenedor');
    
    if (fileInput.files.length === 0) {
        alert('Por favor selecciona un archivo');
        return;
    }
    
    try {
        // Mostrar loading
        document.getElementById('import-status').innerHTML = `
            <div class="alert alert-info">
                <h4>🔄 Iniciando Importación...</h4>
                <p>Por favor espera mientras se procesa el archivo</p>
            </div>
        `;
        
        // Iniciar importación
        await importarExcel(fileInput.files[0], contenedorSelect.value);
        
    } catch (error) {
        document.getElementById('import-status').innerHTML = `
            <div class="alert alert-danger">
                <h4>❌ Error</h4>
                <p>${error.message}</p>
            </div>
        `;
    }
});
```

### 5. HTML de Ejemplo

```html
<div class="import-container">
    <form id="import-form">
        <div class="form-group">
            <label for="excel-file">Archivo Excel:</label>
            <input type="file" id="excel-file" accept=".xlsx,.xls,.xlsm" required>
        </div>
        
        <div class="form-group">
            <label for="contenedor">Contenedor:</label>
            <select id="contenedor" required>
                <option value="">Seleccionar contenedor</option>
                <!-- Opciones dinámicas -->
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">
            Importar Productos
        </button>
    </form>
    
    <div id="import-status"></div>
    <div id="import-progress"></div>
    <div id="import-message"></div>
</div>
```

## Notificaciones en Tiempo Real

### Escuchar Eventos de Broadcasting

```javascript
// Configurar Laravel Echo
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: false,
    wsHost: window.location.hostname,
    wsPort: 6001,
    disableStats: true,
});

// Escuchar eventos de importación completada
Echo.private('Documentacion-notifications')
    .listen('ImportacionExcelCompleted', (e) => {
        console.log('Evento de importación recibido:', e);
        
        if (e.status === 'completed') {
            mostrarNotificacion('Importación completada', e.message, 'success');
        } else if (e.status === 'failed') {
            mostrarNotificacion('Error en importación', e.message, 'error');
        }
    });

function mostrarNotificacion(titulo, mensaje, tipo) {
    // Implementar notificación (Toast, Alert, etc.)
    console.log(`${titulo}: ${mensaje}`);
}
```

## Consideraciones

1. **Polling vs Broadcasting**: Usa polling para archivos pequeños y broadcasting para archivos grandes
2. **Intervalo de Polling**: 2-5 segundos es un buen intervalo
3. **Timeout**: Considera implementar un timeout para evitar polling infinito
4. **UX**: Muestra progreso visual para mejorar la experiencia del usuario
5. **Error Handling**: Maneja errores de red y timeouts apropiadamente
