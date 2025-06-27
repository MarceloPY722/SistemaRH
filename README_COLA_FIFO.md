# Sistema de Cola FIFO para Guardias

## Descripción
Este sistema implementa una cola FIFO (First In, First Out) para la asignación de guardias policiales. Los primeros 7 policías en la lista están siempre disponibles para ser asignados a guardias.

## Funcionamiento

### Estructura de la Cola
- **Posiciones 1-7**: Cola activa de guardias (policías disponibles inmediatamente)
- **Posiciones 8+**: Cola de espera (policías que esperan su turno)

### Proceso de Rotación
1. Cuando un policía de las posiciones 1-7 es asignado a una guardia:
   - Se mueve al final de toda la cola
   - Los policías en posiciones 2-7 suben una posición (2→1, 3→2, etc.)
   - El primer policía disponible de la posición 8+ pasa a la posición 7
   - Se establece su fecha de disponibilidad según su región:
     - **Central**: 15 días
     - **Regional**: 30 días

### Procedimientos Disponibles

#### `InicializarColaFIFO()`
- Reinicia completamente la cola de guardias
- Ordena a todos los policías activos por jerarquía y legajo
- Coloca a los primeros 7 en la cola activa

#### `RotarGuardiaFIFO(policia_id)`
- Rota la cola cuando un policía es asignado a guardia
- Solo afecta a policías en posiciones 1-7
- Implementa la lógica FIFO correcta

#### `ReorganizarListaGuardiasFIFO()`
- Reorganiza la lista excluyendo policías con ausencias aprobadas
- Mantiene el orden por jerarquía y legajo

## Instalación y Uso

### 1. Ejecutar la Base de Datos
```sql
-- Cargar la base de datos principal
source DatabaseNew.sql;
```

### 2. Inicializar la Cola FIFO
```sql
-- Ejecutar el script de inicialización
source inicializar_cola_fifo.sql;
```

### 3. Verificar el Estado
El script de inicialización mostrará:
- Estado completo de la cola (primeros 20)
- Cola activa (primeros 7 policías)

## Consultas Útiles

### Ver Cola Activa
```sql
SELECT 
    lg.posicion,
    p.legajo,
    CONCAT(p.apellido, ', ', p.nombre) as nombre_completo,
    g.nombre as grado
FROM lista_guardias lg
JOIN policias p ON lg.policia_id = p.id
JOIN grados g ON p.grado_id = g.id
WHERE lg.posicion <= 7
ORDER BY lg.posicion;
```

### Ver Próximos en Espera
```sql
SELECT 
    lg.posicion,
    p.legajo,
    CONCAT(p.apellido, ', ', p.nombre) as nombre_completo,
    lg.fecha_disponible
FROM lista_guardias lg
JOIN policias p ON lg.policia_id = p.id
WHERE lg.posicion > 7 AND lg.posicion <= 15
ORDER BY lg.posicion;
```

## Notas Importantes

1. **Orden de Prioridad**: Los policías se ordenan por:
   - Nivel de jerarquía (ascendente)
   - Número de legajo (ascendente)

2. **Disponibilidad**: Solo se consideran policías:
   - Activos en el sistema
   - Sin ausencias aprobadas en la fecha actual

3. **Fechas de Disponibilidad**: 
   - Se calculan automáticamente al asignar guardia
   - Región Central: 15 días de espera
   - Región Regional: 30 días de espera

4. **Mantenimiento**: Se recomienda ejecutar `InicializarColaFIFO()` periódicamente para:
   - Incluir nuevos policías
   - Excluir policías inactivos
   - Reordenar según cambios de grado o legajo