-- Script para corregir la restricción UNIQUE en la tabla lugares_guardias
-- Permite nombres duplicados en diferentes zonas

USE sistema_rh_policia;

-- Eliminar la restricción UNIQUE existente en el campo 'nombre'
ALTER TABLE lugares_guardias DROP INDEX nombre;

-- Agregar una nueva restricción UNIQUE compuesta para 'nombre' y 'zona'
ALTER TABLE lugares_guardias ADD UNIQUE KEY unique_nombre_zona (nombre, zona);

-- Verificar la estructura de la tabla
SHOW INDEX FROM lugares_guardias;