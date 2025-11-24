# Implementación Frontend - Carga de Formulario de Delivery

## Archivos a Modificar

### 1. Servicio: `services/clientes/delivery/deliveryService.ts`

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

### 2. Composable: `composables/clientes/delivery/useDelivery.ts`

Agregar las siguientes variables y método:

```typescript
// Agregar en las variables reactivas
const formularioGuardado = ref<any>(null)
const loadingFormulario = ref(false)

// Agregar el método
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

// En el return del composable, agregar:
return {
  // ... otros métodos existentes
  getFormularioLimaByCotizacion,
  formularioGuardado,
  loadingFormulario
}
```

## Funcionalidad Implementada

### En el Componente Vue

Ya se ha agregado un watcher que:

1. **Se activa cuando se selecciona un importador** (`formData.importador`)
2. **Solo carga el formulario si NO tiene horario seleccionado** (`!formData.horarioSeleccionado`)
3. **Llena los campos del formulario** con los datos guardados (sin sobrescribir datos existentes)
4. **Navega al paso 4** si el formulario guardado estaba en ese paso
5. **Carga los horarios disponibles** cuando navega al paso 4
6. **Guarda el estado** después de cargar

### Lógica de Carga

- Solo se carga si el importador tiene un `value` válido
- Solo se carga si el formulario actual NO tiene `horarioSeleccionado`
- Los campos se llenan solo si están vacíos (no sobrescribe datos existentes)
- NO se incluyen `fechaEntrega` ni `horarioSeleccionado` del formulario guardado
- Si hay error, se registra pero no se bloquea el flujo

## Flujo de Usuario

1. Usuario selecciona un importador en el paso 1
2. Si ese importador tiene un formulario guardado sin horario:
   - Se cargan automáticamente los datos del formulario
   - Se navega al paso 4 (selección de fecha)
   - Se cargan los horarios disponibles
3. Usuario puede seleccionar un horario y completar el formulario
4. Si el formulario ya tiene horario, no se carga automáticamente

