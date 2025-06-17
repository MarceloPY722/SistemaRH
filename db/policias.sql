-- ========================================
-- SISTEMA DE RECURSOS HUMANOS - POLICÍA NACIONAL DE PARAGUAY
-- Base de Datos para Automatización de Guardias y Servicios
-- ========================================

-- Tabla de Grados Policiales
CREATE TABLE grados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    nivel_jerarquia INT NOT NULL, -- 1 = más alto, números mayores = menor jerarquía
    abreviatura VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de Especialidades
CREATE TABLE especialidades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de Lugares de Guardias
CREATE TABLE lugares_guardias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    direccion VARCHAR(200),
    zona VARCHAR(50), -- Central, Regional, etc.
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla principal de Policías
CREATE TABLE policias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    cin VARCHAR(20) UNIQUE NOT NULL, -- Cédula de Identidad Nacional
    grado_id INT NOT NULL,
    especialidad_id INT,
    cargo VARCHAR(150),
    comisionamiento VARCHAR(200),
    telefono VARCHAR(20),
    region ENUM('CENTRAL', 'REGIONAL') DEFAULT 'CENTRAL',
    lugar_guardia_id INT, -- Lugar de guardia asignado
    fecha_ingreso DATE,
    antiguedad_dias INT DEFAULT 0, -- Calculado automáticamente
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (grado_id) REFERENCES grados(id),
    FOREIGN KEY (especialidad_id) REFERENCES especialidades(id),
    FOREIGN KEY (lugar_guardia_id) REFERENCES lugares_guardias(id),
    
    INDEX idx_cin (cin),
    INDEX idx_grado (grado_id),
    INDEX idx_lugar_guardia (lugar_guardia_id),
    INDEX idx_activo (activo)
);

-- Tabla de Tipos de Ausencias
CREATE TABLE tipos_ausencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE, -- 'Médico', 'Embarazo', 'Personal', etc.
    descripcion TEXT,
    requiere_justificacion BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Ausencias
CREATE TABLE ausencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policia_id INT NOT NULL,
    tipo_ausencia_id INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE, -- NULL para ausencias indefinidas
    descripcion TEXT,
    justificacion TEXT,
    documento_adjunto VARCHAR(255), -- Ruta del archivo de justificación
    aprobado_por INT, -- ID del superior que aprobó
    estado ENUM('PENDIENTE', 'APROBADA', 'RECHAZADA') DEFAULT 'PENDIENTE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (policia_id) REFERENCES policias(id),
    FOREIGN KEY (tipo_ausencia_id) REFERENCES tipos_ausencias(id),
    FOREIGN KEY (aprobado_por) REFERENCES policias(id),
    
    INDEX idx_policia (policia_id),
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_estado (estado)
);

-- Tabla de Lista de Guardias (FIFO por jerarquía y antigüedad)
CREATE TABLE lista_guardias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policia_id INT NOT NULL UNIQUE,
    posicion INT NOT NULL, -- Posición en la lista FIFO
    ultima_guardia_fecha DATE, -- Última vez que hizo guardia
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (policia_id) REFERENCES policias(id),
    
    INDEX idx_posicion (posicion),
    INDEX idx_policia (policia_id)
);

-- Tabla principal de Servicios (Nota de Servicio)
CREATE TABLE servicios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(200) NOT NULL,
    fecha_servicio DATE NOT NULL,
    descripcion TEXT,
    orden_del_dia VARCHAR(50), -- Ej: "322/2025"
    jefe_servicio_id INT,
    estado ENUM('PROGRAMADO', 'EN_CURSO', 'COMPLETADO', 'CANCELADO') DEFAULT 'PROGRAMADO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (jefe_servicio_id) REFERENCES policias(id),
    
    INDEX idx_fecha (fecha_servicio),
    INDEX idx_estado (estado)
);

-- Tabla de Asignaciones de Servicios (Detalle de cada servicio)
CREATE TABLE asignaciones_servicios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    servicio_id INT NOT NULL,
    policia_id INT NOT NULL,
    puesto VARCHAR(100) NOT NULL, -- 'JEFE DE SERVICIO', 'JEFE DE CUARTEL', etc.
    lugar VARCHAR(150),
    hora_inicio TIME,
    hora_fin TIME,
    telefono_contacto VARCHAR(20),
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE,
    FOREIGN KEY (policia_id) REFERENCES policias(id),
    
    INDEX idx_servicio (servicio_id),
    INDEX idx_policia (policia_id),
    INDEX idx_puesto (puesto)
);

-- Tabla de Guardias Realizadas (Historial)
CREATE TABLE guardias_realizadas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policia_id INT NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    lugar_guardia_id INT NOT NULL,
    puesto VARCHAR(100), -- 'JEFE DE SERVICIO', 'OFICIAL DE GUARDIA', etc.
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (policia_id) REFERENCES policias(id),
    FOREIGN KEY (lugar_guardia_id) REFERENCES lugares_guardias(id),
    
    INDEX idx_policia (policia_id),
    INDEX idx_fecha (fecha_inicio),
    INDEX idx_lugar (lugar_guardia_id)
);

-- ========================================
-- VISTAS ÚTILES
-- ========================================

-- Vista de Disponibilidad de Policías
CREATE VIEW vista_disponibilidad_policias AS
SELECT 
    p.id,
    p.nombre,
    p.apellido,
    p.cin,
    g.nombre as grado,
    g.nivel_jerarquia,
    e.nombre as especialidad,
    p.cargo,
    p.telefono,
    lg.nombre as lugar_guardia,
    p.region,
    p.antiguedad_dias,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM ausencias a 
            WHERE a.policia_id = p.id 
            AND a.estado = 'APROBADA'
            AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
        ) THEN 'NO DISPONIBLE'
        ELSE 'DISPONIBLE'
    END as disponibilidad,
    p.observaciones
FROM policias p
LEFT JOIN grados g ON p.grado_id = g.id
LEFT JOIN especialidades e ON p.especialidad_id = e.id
LEFT JOIN lugares_guardias lg ON p.lugar_guardia_id = lg.id
WHERE p.activo = TRUE;

-- Vista de Lista de Guardias Ordenada
CREATE VIEW vista_lista_guardias AS
SELECT 
    lg.posicion,
    p.id as policia_id,
    p.nombre,
    p.apellido,
    p.cin,
    g.nombre as grado,
    g.nivel_jerarquia,
    p.antiguedad_dias,
    lguar.nombre as lugar_guardia,
    lg.ultima_guardia_fecha,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM ausencias a 
            WHERE a.policia_id = p.id 
            AND a.estado = 'APROBADA'
            AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
        ) THEN 'NO DISPONIBLE'
        ELSE 'DISPONIBLE'
    END as disponibilidad
FROM lista_guardias lg
JOIN policias p ON lg.policia_id = p.id
JOIN grados g ON p.grado_id = g.id
LEFT JOIN lugares_guardias lguar ON p.lugar_guardia_id = lguar.id
WHERE p.activo = TRUE
ORDER BY lg.posicion;

-- ========================================
-- PROCEDIMIENTOS ALMACENADOS
-- ========================================

DELIMITER //

-- Procedimiento para calcular antigüedad
CREATE PROCEDURE ActualizarAntiguedad()
BEGIN
    UPDATE policias 
    SET antiguedad_dias = DATEDIFF(CURDATE(), fecha_ingreso)
    WHERE fecha_ingreso IS NOT NULL AND activo = TRUE;
END //

-- Procedimiento para reorganizar lista de guardias por jerarquía y antigüedad
CREATE PROCEDURE ReorganizarListaGuardias()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE policia_id_var INT;
    DECLARE posicion_counter INT DEFAULT 1;
    
    DECLARE cur CURSOR FOR 
        SELECT p.id
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = TRUE
        ORDER BY g.nivel_jerarquia ASC, p.antiguedad_dias DESC, p.id ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Limpiar lista actual
    DELETE FROM lista_guardias;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO policia_id_var;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO lista_guardias (policia_id, posicion) 
        VALUES (policia_id_var, posicion_counter);
        
        SET posicion_counter = posicion_counter + 1;
    END LOOP;
    CLOSE cur;
END //

-- Procedimiento para rotar guardia (mover al final de la lista)
CREATE PROCEDURE RotarGuardia(IN policia_id_param INT)
BEGIN
    DECLARE max_posicion INT;
    
    -- Obtener la máxima posición
    SELECT MAX(posicion) INTO max_posicion FROM lista_guardias;
    
    -- Mover el policía al final
    UPDATE lista_guardias 
    SET posicion = max_posicion + 1,
        ultima_guardia_fecha = CURDATE()
    WHERE policia_id = policia_id_param;
    
    -- Reordenar posiciones
    SET @new_pos = 0;
    UPDATE lista_guardias 
    SET posicion = (@new_pos := @new_pos + 1)
    ORDER BY 
        CASE WHEN policia_id = policia_id_param THEN 1 ELSE 0 END,
        posicion;
END //

DELIMITER ;

-- Insertar Tipos de Ausencias
INSERT INTO tipos_ausencias (nombre, descripcion, requiere_justificacion) VALUES
('Médico', 'Ausencia por motivos médicos', TRUE),
('Embarazo', 'Licencia por embarazo', TRUE),
('Personal', 'Ausencia por motivos personales', FALSE),
('Vacaciones', 'Período vacacional', FALSE),
('Capacitación', 'Ausencia por capacitación o entrenamiento', FALSE),
('Suspensión', 'Suspensión disciplinaria', TRUE);

-- ========================================
-- TRIGGERS
-- ========================================

DELIMITER //

-- Trigger para actualizar automáticamente la antigüedad
CREATE TRIGGER trg_update_antiguedad 
BEFORE UPDATE ON policias
FOR EACH ROW
BEGIN
    IF NEW.fecha_ingreso IS NOT NULL THEN
        SET NEW.antiguedad_dias = DATEDIFF(CURDATE(), NEW.fecha_ingreso);
    END IF;
END //

-- Trigger para insertar en lista_guardias cuando se crea un nuevo policía
CREATE TRIGGER trg_insert_lista_guardias
AFTER INSERT ON policias
FOR EACH ROW
BEGIN
    DECLARE max_pos INT DEFAULT 0;
    
    SELECT COALESCE(MAX(posicion), 0) INTO max_pos FROM lista_guardias;
    
    INSERT INTO lista_guardias (policia_id, posicion)
    VALUES (NEW.id, max_pos + 1);
END //

DELIMITER ;

-- ========================================
-- ÍNDICES ADICIONALES PARA RENDIMIENTO
-- ========================================

CREATE INDEX idx_ausencias_vigentes ON ausencias (policia_id, fecha_inicio, fecha_fin, estado);
CREATE INDEX idx_servicios_fecha ON servicios (fecha_servicio, estado);
CREATE INDEX idx_guardias_realizadas_fecha ON guardias_realizadas (fecha_inicio, fecha_fin);
CREATE INDEX idx_policias_activos ON policias (activo, grado_id, antiguedad_dias);

-- ========================================
-- Tabla de Usuarios del Sistema (Administradores)
-- ========================================
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_usuario VARCHAR(50) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100),
    email VARCHAR(100),
    rol ENUM('ADMIN') DEFAULT 'ADMIN',
    activo BOOLEAN DEFAULT TRUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
