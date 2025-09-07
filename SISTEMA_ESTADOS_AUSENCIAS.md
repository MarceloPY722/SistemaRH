# Sistema de Estados y Ausencias - Documentación

## Descripción General

Este sistema gestiona automáticamente la disponibilidad de los policías basándose en sus ausencias, asegurando que durante el período de ausencia no puedan ser asignados a guardias.

## Funcionamiento del Sistema

### Estados de Policías

Cada policía tiene un campo `estado` que puede tener los siguientes valores:
- **DISPONIBLE**: El policía está disponible para ser asignado a guardias
- **NO DISPONIBLE**: El policía no está disponible (por ausencia u otros motivos)

### Flujo de Ausencias

#### 1. Creación de Ausencia

**Archivo**: `admin/ausencias/agregar_ausencia.php`

- Cuando se crea una ausencia que **NO** es "Junta Médica":
  - El estado del policía cambia automáticamente a `'NO DISPONIBLE'`
  - El policía queda excluido de las listas de guardias

- Para ausencias de "Junta Médica":
  - Se mantiene el comportamiento especial existente (cambio a TELEFONISTA)
  - No se modifica el estado general

#### 2. Finalización de Ausencia

**Archivo**: `admin/index.php`

- Cuando se marca una ausencia como "COMPLETADA":
  - Se verifica si el policía tiene otras ausencias activas
  - Si NO tiene otras ausencias activas, su estado vuelve a `'DISPONIBLE'`
  - Si tiene otras ausencias activas, mantiene el estado `'NO DISPONIBLE'`

#### 3. Verificación Automática

**Archivo**: `verificar_ausencias_vencidas.php`

Este script debe ejecutarse diariamente y realiza:

1. **Marca ausencias vencidas como COMPLETADAS**
   - Busca ausencias con `fecha_fin < CURDATE()`
   - Las marca como `estado = 'COMPLETADA'`

2. **Restaura estados de policías**
   - Policías sin ausencias activas → `'DISPONIBLE'`
   - Policías con ausencias activas → `'NO DISPONIBLE'`

3. **Genera reportes**
   - Resumen de estados actuales
   - Lista de ausencias activas
   - Estadísticas de cambios realizados

### Integración con Sistema de Guardias

Todos los archivos que manejan la asignación de guardias han sido actualizados para considerar el campo `estado`:

- `admin/guardias/api/buscar_policias.php`
- `admin/guardias/api/obtener_todos_policias.php`
- `admin/guardias/generar_guardia.php`
- `admin/guardias/index.php`

**Condición agregada**: `AND p.estado = 'DISPONIBLE'`

Esto asegura que solo los policías disponibles aparezcan en las listas de selección para guardias.

## Archivos Modificados

### Archivos Principales
1. `actualizar.php` - Script de migración inicial
2. `verificar_ausencias_vencidas.php` - Verificación automática diaria
3. `admin/ausencias/agregar_ausencia.php` - Creación de ausencias
4. `admin/index.php` - Finalización de ausencias

### Archivos de Guardias
1. `admin/guardias/api/buscar_policias.php`
2. `admin/guardias/api/obtener_todos_policias.php`
3. `admin/guardias/generar_guardia.php`
4. `admin/guardias/index.php`

## Configuración y Mantenimiento

### Instalación Inicial
1. Ejecutar `http://localhost/SistemaRH/actualizar.php` para crear la columna `estado`
2. Verificar que todos los estados se hayan inicializado correctamente

### Mantenimiento Diario
1. Configurar tarea programada para ejecutar `verificar_ausencias_vencidas.php`
2. Revisar logs de ejecución para detectar posibles problemas

### Monitoreo
- El script de verificación genera reportes detallados
- Se pueden revisar manualmente los estados en la base de datos
- Los mensajes de éxito/error proporcionan información de depuración

## Beneficios del Sistema

1. **Automatización**: No requiere intervención manual para gestionar disponibilidad
2. **Consistencia**: Garantiza que policías en ausencia no sean asignados a guardias
3. **Flexibilidad**: Maneja múltiples ausencias simultáneas por policía
4. **Trazabilidad**: Registra todos los cambios de estado
5. **Robustez**: Incluye verificaciones automáticas y recuperación de errores

## Casos de Uso

### Caso 1: Vacaciones
- Policía solicita vacaciones del 1 al 15 de enero
- Al aprobar la ausencia, su estado cambia a 'NO DISPONIBLE'
- Durante esas fechas no aparece en listas de guardias
- El 16 de enero, el script automático lo marca como 'DISPONIBLE'

### Caso 2: Múltiples Ausencias
- Policía tiene ausencia médica del 1 al 10 de enero
- Luego tiene capacitación del 8 al 20 de enero (se superponen)
- Permanece 'NO DISPONIBLE' hasta el 20 de enero
- Solo vuelve a 'DISPONIBLE' cuando terminan todas las ausencias

### Caso 3: Junta Médica
- Mantiene el comportamiento especial existente
- Cambio automático de lugar de guardia a TELEFONISTA
- No afecta el sistema general de estados

## Troubleshooting

### Problema: Policía no aparece en guardias
**Solución**: Verificar su estado en la tabla `policias.estado`

### Problema: Estado incorrecto después de ausencia
**Solución**: Ejecutar manualmente `verificar_ausencias_vencidas.php`

### Problema: Ausencias no se completan automáticamente
**Solución**: Verificar que la tarea programada esté funcionando correctamente