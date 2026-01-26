# Implementación de Carga de Formulario de Delivery

## Endpoint Backend

El endpoint ya está creado en `app/Http/Controllers/Clientes/DeliveryController.php`:

**Ruta:** `GET /api/clientes/delivery/formulario-lima/{cotizacionUuid}`

**Respuesta exitosa:**
```json
{
  "message": "Formulario obtenido correctamente",
  "success": true,
  "data": {
    "formData": {
      "nombreCompleto": "Juan Pérez",
      "dni": "12345678",
      "importador": {
        "label": "Importador ABC",
        "value": "123"
      },
      "tipoComprobante": {
        "label": "BOLETA",
        "value": "BOLETA"
      },
      "tiposProductos": "Juguetes, stickers, botellas",
      "clienteDni": "87654321",
      "clienteNombre": "María García",
      "clienteCorreo": "maria@example.com",
      "clienteRuc": "",
      "clienteRazonSocial": "",
      "choferNombre": "Carlos López",
      "choferDni": "11223344",
      "choferLicencia": "A123456",
      "choferPlaca": "ABC-123",
      "direccionDestino": "Av. Principal 123",
      "distritoDestino": "1"
    },
    "currentStep": 4,
    "timestamp": 1705320000000
  }
}
```

**Nota:** El endpoint solo devuelve el formulario si NO tiene horario asignado (`id_range_date` es null).

## Implementación en el Frontend

### 1. Servicio (services/clientes/delivery/deliveryService.ts)

Agregar el siguiente método:

```typescript
async getFormularioLimaByCotizacion(cotizacionUuid: string) {
  try {
    const response = await this.api.get(`/clientes/delivery/formulario-lima/${cotizacionUuid}`)
    return response.data
  } catch (error: any) {
    throw new Error(error?.response?.data?.message || 'Error al obtener el formulario')
  }
}
```

### 2. Composable (composables/clientes/delivery/useDelivery.ts)

Agregar el siguiente método:

```typescript
const formularioGuardado = ref<any>(null)
const loadingFormulario = ref(false)

const getFormularioLimaByCotizacion = async (cotizacionUuid: string) => {
  loadingFormulario.value = true
  try {
    const response = await deliveryService.getFormularioLimaByCotizacion(cotizacionUuid)
    if (response.success && response.data) {
      formularioGuardado.value = response.data
      return response.data
    }
    return null
  } catch (error) {
    console.error('Error al obtener formulario guardado:', error)
    return null
  } finally {
    loadingFormulario.value = false
  }
}

return {
  // ... otros métodos existentes
  getFormularioLimaByCotizacion,
  formularioGuardado,
  loadingFormulario
}
```

### 3. Componente Vue (pages/formulario-entrega/lima/[id].vue)

Modificar el watcher del `formData.importador` para cargar el formulario cuando se seleccione un cliente:

```typescript
// Agregar después de los otros watchers
watch(() => formData.importador, async (newValue) => {
  if (newValue && newValue.value) {
    // Solo cargar si el formulario no tiene horario
    if (!formData.horarioSeleccionado) {
      try {
        const formularioData = await getFormularioLimaByCotizacion(newValue.value)
        
        if (formularioData && formularioData.formData) {
          // Llenar el formulario con los datos guardados
          Object.assign(formData, {
            nombreCompleto: formularioData.formData.nombreCompleto || formData.nombreCompleto,
            dni: formularioData.formData.dni || formData.dni,
            tipoComprobante: formularioData.formData.tipoComprobante || formData.tipoComprobante,
            tiposProductos: formularioData.formData.tiposProductos || formData.tiposProductos,
            clienteDni: formularioData.formData.clienteDni || formData.clienteDni,
            clienteNombre: formularioData.formData.clienteNombre || formData.clienteNombre,
            clienteCorreo: formularioData.formData.clienteCorreo || formData.clienteCorreo,
            clienteRuc: formularioData.formData.clienteRuc || formData.clienteRuc,
            clienteRazonSocial: formularioData.formData.clienteRazonSocial || formData.clienteRazonSocial,
            choferNombre: formularioData.formData.choferNombre || formData.choferNombre,
            choferDni: formularioData.formData.choferDni || formData.choferDni,
            choferLicencia: formularioData.formData.choferLicencia || formData.choferLicencia,
            choferPlaca: formularioData.formData.choferPlaca || formData.choferPlaca,
            direccionDestino: formularioData.formData.direccionDestino || formData.direccionDestino,
            distritoDestino: formularioData.formData.distritoDestino || formData.distritoDestino,
            // NO incluir fechaEntrega ni horarioSeleccionado
          })
          
          // Ir al paso 4 si hay datos guardados
          if (formularioData.currentStep === 4) {
            currentStep.value = 4
            // Cargar horarios disponibles
            try {
              await getHorariosDisponibles(Number(consolidadoId))
            } catch (error) {
              console.error('Error al cargar horarios:', error)
            }
          }
          
          // Guardar estado después de cargar
          saveFormState(formData, currentStep.value)
        }
      } catch (error) {
        console.error('Error al cargar formulario guardado:', error)
        // Continuar sin error, simplemente no se carga el formulario
      }
    }
  }
}, { immediate: false })
```

## Lógica de Funcionamiento

1. **Cuando se selecciona un cliente (importador):**
   - Se verifica si el formulario ya tiene un horario seleccionado
   - Si NO tiene horario, se llama al endpoint para obtener el formulario guardado
   - Si el formulario existe y no tiene horario, se llenan los campos del formulario
   - Se navega al paso 4 (selección de fecha) si hay datos guardados
   - Se cargan los horarios disponibles

2. **Validaciones:**
   - Solo se carga el formulario si NO tiene `horarioSeleccionado`
   - El endpoint solo devuelve formularios sin horario asignado
   - Si el formulario ya tiene horario, no se carga automáticamente

3. **Manejo de errores:**
   - Si no se encuentra el formulario, se continúa normalmente
   - Si hay un error en la petición, se registra pero no se bloquea el flujo

