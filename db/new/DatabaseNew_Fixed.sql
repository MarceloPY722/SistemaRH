-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 26-06-2025 a las 17:54:41
-- Versión del servidor: 5.7.33
-- Versión de PHP: 7.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_rh_policia`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `InicializarColaFIFO` ()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE policia_id_var INT;
    DECLARE posicion_counter INT DEFAULT 1;
    
    DECLARE cur CURSOR FOR 
        SELECT p.id
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = TRUE
        AND NOT EXISTS (
            SELECT 1 FROM ausencias a 
            WHERE a.policia_id = p.id 
            AND a.estado = 'APROBADA'
            AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
        )
        ORDER BY g.nivel_jerarquia ASC, p.legajo ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Limpiar lista actual
    DELETE FROM lista_guardias;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO policia_id_var;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Insertar policía en la posición correspondiente
        INSERT INTO lista_guardias (policia_id, posicion, fecha_disponible) 
        VALUES (policia_id_var, posicion_counter, NULL);
        
        SET posicion_counter = posicion_counter + 1;
    END LOOP;
    CLOSE cur;
    
    -- Asegurar que los primeros 7 estén disponibles inmediatamente
    UPDATE lista_guardias 
    SET fecha_disponible = NULL 
    WHERE posicion <= 7;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `InicializarColaFIFOPorLugar` ()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE lugar_id_var INT;
    
    DECLARE lugares_cursor CURSOR FOR 
        SELECT id FROM lugares_guardias WHERE activo = 1;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Limpiar lista actual
    DELETE FROM lista_guardias;
    
    OPEN lugares_cursor;
    lugares_loop: LOOP
        FETCH lugares_cursor INTO lugar_id_var;
        IF done THEN
            LEAVE lugares_loop;
        END IF;
        
        -- Inicializar cola para este lugar
        BEGIN
            DECLARE done_policias INT DEFAULT FALSE;
            DECLARE policia_id_var INT;
            DECLARE posicion_counter INT DEFAULT 1;
            
            DECLARE policias_cursor CURSOR FOR 
                SELECT p.id
                FROM policias p
                JOIN grados g ON p.grado_id = g.id
                WHERE p.activo = TRUE
                AND p.lugar_guardia_id = lugar_id_var
                ORDER BY g.nivel_jerarquia ASC, p.legajo ASC;
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done_policias = TRUE;
            
            OPEN policias_cursor;
            policias_loop: LOOP
                FETCH policias_cursor INTO policia_id_var;
                IF done_policias THEN
                    LEAVE policias_loop;
                END IF;
                
                INSERT INTO lista_guardias (policia_id, posicion, fecha_disponible) 
                VALUES (policia_id_var, posicion_counter, NULL);
                
                SET posicion_counter = posicion_counter + 1;
            END LOOP;
            CLOSE policias_cursor;
        END;
        
    END LOOP;
    CLOSE lugares_cursor;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ObtenerProximoPoliciaDisponible` (IN `lugar_id_param` INT, IN `region_requerida` VARCHAR(50))
BEGIN
    DECLARE dia_semana INT;
    
    -- Obtener día de la semana (1=Domingo, 2=Lunes, ..., 7=Sábado)
    SET dia_semana = DAYOFWEEK(CURDATE());
    
    -- Si no se especifica región, determinar según día de la semana
    IF region_requerida IS NULL THEN
        IF dia_semana IN (1, 2, 3, 4, 5) THEN -- Domingo a Jueves
            SET region_requerida = 'Central';
        ELSE -- Viernes y Sábado
            SET region_requerida = 'Regional';
        END IF;
    END IF;
    
    -- Devolver el próximo policía disponible como result set
    SELECT 
        p.id as policia_id,
        p.legajo,
        p.nombre,
        p.apellido,
        p.cin,
        p.telefono,
        g.nombre as grado,
        g.nivel_jerarquia,
        r.nombre as region,
        lg.posicion
    FROM lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    JOIN grados g ON p.grado_id = g.id
    JOIN regiones r ON p.region_id = r.id
    WHERE p.activo = TRUE 
    AND p.lugar_guardia_id = lugar_id_param
    AND r.nombre = region_requerida
    AND (lg.fecha_disponible IS NULL OR lg.fecha_disponible <= CURDATE())
    AND NOT EXISTS (
        SELECT 1 FROM ausencias a 
        WHERE a.policia_id = p.id 
        AND a.estado = 'APROBADA'
        AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
    )
    ORDER BY g.nivel_jerarquia ASC, p.legajo ASC, lg.posicion ASC
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaGuardias` ()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE policia_id_var INT;
    DECLARE posicion_counter INT DEFAULT 1;
    
    DECLARE cur CURSOR FOR 
        SELECT p.id
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = TRUE
        ORDER BY g.nivel_jerarquia ASC, p.id ASC;
    
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaGuardiasFIFO` ()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE policia_id_var INT;
    DECLARE posicion_counter INT DEFAULT 1;
    
    DECLARE cur CURSOR FOR 
        SELECT p.id
        FROM policias p
        JOIN grados g ON p.grado_id = g.id
        WHERE p.activo = TRUE
        AND NOT EXISTS (
            SELECT 1 FROM ausencias a 
            WHERE a.policia_id = p.id 
            AND a.estado = 'APROBADA'
            AND CURDATE() BETWEEN a.fecha_inicio AND COALESCE(a.fecha_fin, CURDATE())
        )
        ORDER BY g.nivel_jerarquia ASC, p.legajo ASC;
    
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaPorLegajo` ()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE policia_id_var INT;
    DECLARE lugar_id_var INT;
    DECLARE posicion_counter INT;
    
    -- Cursor para obtener lugares de guardia
    DECLARE lugares_cursor CURSOR FOR 
        SELECT id FROM lugares_guardias WHERE activo = 1;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN lugares_cursor;
    lugares_loop: LOOP
        FETCH lugares_cursor INTO lugar_id_var;
        IF done THEN
            LEAVE lugares_loop;
        END IF;
        
        -- Reorganizar por lugar de guardia
        SET posicion_counter = 1;
        
        -- Cursor para policías de este lugar ordenados por legajo descendente
        BEGIN
            DECLARE done_policias INT DEFAULT FALSE;
            DECLARE policias_cursor CURSOR FOR 
                SELECT p.id
                FROM policias p
                WHERE p.activo = TRUE 
                AND p.lugar_guardia_id = lugar_id_var
                ORDER BY p.legajo DESC; -- Mayor legajo primero
            
            DECLARE CONTINUE HANDLER FOR NOT FOUND SET done_policias = TRUE;
            
            OPEN policias_cursor;
            policias_loop: LOOP
                FETCH policias_cursor INTO policia_id_var;
                IF done_policias THEN
                    LEAVE policias_loop;
                END IF;
                
                -- Actualizar posición en lista_guardias
                UPDATE lista_guardias 
                SET posicion = posicion_counter
                WHERE policia_id = policia_id_var;
                
                SET posicion_counter = posicion_counter + 1;
            END LOOP;
            CLOSE policias_cursor;
        END;
        
    END LOOP;
    CLOSE lugares_cursor;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardia` (IN `policia_id_param` INT)
BEGIN
    DECLARE max_posicion INT;
    
    -- Obtener la máxima posición
    SELECT MAX(posicion) INTO max_posicion FROM lista_guardias;
    
    -- Mover el policía al final
    UPDATE lista_guardias 
    SET posicion = max_posicion + 1,
        ultima_guardia_fecha = CURDATE()
    WHERE policia_id = policia_id_param;
    
    -- Reordenar posiciones secuencialmente
    SET @row_number = 0;
    UPDATE lista_guardias 
    SET posicion = (@row_number := @row_number + 1)
    ORDER BY 
        CASE WHEN policia_id = policia_id_param THEN 999999 ELSE posicion END;
END$$

-- PROCEDIMIENTO CORREGIDO: RotarGuardiaFIFO
CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardiaFIFO` (IN `policia_id_param` INT)
BEGIN
    DECLARE region_policia VARCHAR(50);
    DECLARE fecha_disponible DATE;
    DECLARE lugar_guardia_id INT;
    DECLARE max_posicion_lugar INT;
    
    -- Obtener región y lugar del policía
    SELECT r.nombre, p.lugar_guardia_id INTO region_policia, lugar_guardia_id
    FROM policias p
    JOIN regiones r ON p.region_id = r.id
    WHERE p.id = policia_id_param;
    
    -- Calcular fecha de disponibilidad según región
    IF region_policia = 'Central' THEN
        SET fecha_disponible = DATE_ADD(CURDATE(), INTERVAL 15 DAY);
    ELSE
        SET fecha_disponible = DATE_ADD(CURDATE(), INTERVAL 30 DAY);
    END IF;
    
    -- Obtener la máxima posición para este lugar de guardia
    SELECT COALESCE(MAX(lg.posicion), 0) INTO max_posicion_lugar
    FROM lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    WHERE p.lugar_guardia_id = lugar_guardia_id;
    
    -- Actualizar el policía que hizo guardia: moverlo al final de su lugar
    UPDATE lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    SET lg.posicion = max_posicion_lugar + 1,
        lg.ultima_guardia_fecha = CURDATE(),
        lg.fecha_disponible = fecha_disponible
    WHERE lg.policia_id = policia_id_param;
    
    -- Reordenar posiciones para este lugar de guardia usando variables
    SET @row_number = 0;
    UPDATE lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    SET lg.posicion = (@row_number := @row_number + 1)
    WHERE p.lugar_guardia_id = lugar_guardia_id
    ORDER BY 
        CASE WHEN lg.policia_id = policia_id_param THEN 999999 ELSE lg.posicion END;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_servicios`
--

CREATE TABLE `asignaciones_servicios` (
  `id` int(11) NOT NULL,
  `servicio_id` int(11) NOT NULL,
  `policia_id` int(11) NOT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `lugar` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `telefono_contacto` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ausencias`
--

CREATE TABLE `ausencias` (
  `id` int(11) NOT NULL,
  `policia_id` int(11) NOT NULL,
  `tipo_ausencia_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `justificacion` text COLLATE utf8mb4_spanish2_ci,
  `documento_adjunto` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `estado` enum('PENDIENTE','APROBADA','RECHAZADA') COLLATE utf8mb4_spanish2_ci DEFAULT 'PENDIENTE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `especialidades`
--

INSERT INTO `especialidades` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'Magister en Ciencias policiales', '', '2025-06-17 04:29:03', '2025-06-17 04:29:03'),
(2, 'Magister en Gestión y Asesoramiento Policial', 'Magister en Gestión y Asesoramiento Policial', '2025-06-17 04:29:14', '2025-06-17 04:29:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grados`
--

CREATE TABLE `grados` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nivel_jerarquia` int(11) NOT NULL,
  `abreviatura` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `grados`
--

INSERT INTO `grados` (`id`, `nombre`, `nivel_jerarquia`, `abreviatura`, `created_at`, `updated_at`) VALUES
(1, 'Comisario Principal', 1, 'Crio Princ', '2025-06-17 04:33:23', '2025-06-18 19:47:44'),
(2, 'Comisario', 2, 'Com.', '2025-06-18 19:47:30', '2025-06-18 20:53:34'),
(3, 'Subcomisario', 3, 'SUBCOM.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(4, 'Oficial Inspector', 4, 'OF. INSP.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(5, 'Oficial Primero', 5, 'OF. 1°', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(6, 'Oficial Segundo', 6, 'OF. 2°', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(7, 'Oficial Ayudante', 7, 'OF. AYD.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(8, 'Suboficial Superior', 8, 'SUBOF. SUP.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(9, 'Suboficial Principal', 9, 'SUBOF. PPAL.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(10, 'Suboficial Mayor', 10, 'SUBOF. MY.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(11, 'Suboficial Inspector', 11, 'SUBOF. INSP.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(12, 'Suboficial Primero', 12, 'SUBOF. 1°', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(13, 'Suboficial Segundo', 13, 'SUBOF. 2°', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(14, 'Suboficial Ayudante', 14, 'SUBOF. AYD.', '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(15, 'Funcionario/a', 15, 'FUNC.', '2025-06-18 20:56:35', '2025-06-18 20:56:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_realizadas`
--

CREATE TABLE `guardias_realizadas` (
  `id` int(11) NOT NULL,
  `policia_id` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `lugar_guardia_id` int(11) NOT NULL,
  `puesto` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lista_guardias`
--

CREATE TABLE `lista_guardias` (
  `id` int(11) NOT NULL,
  `policia_id` int(11) NOT NULL,
  `posicion` int(11) NOT NULL,
  `ultima_guardia_fecha` date DEFAULT NULL,
  `fecha_disponible` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugares_guardias`
--

CREATE TABLE `lugares_guardias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `responsable` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `lugares_guardias`
--

INSERT INTO `lugares_guardias` (`id`, `nombre`, `direccion`, `telefono`, `responsable`, `observaciones`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Comisaría Central', 'Av. Principal 123', '555-0001', 'Comisario García', 'Guardia principal 24/7', 1, '2025-06-17 04:30:00', '2025-06-17 04:30:00'),
(2, 'Subcomisaría Norte', 'Calle Norte 456', '555-0002', 'Subcomisario López', 'Cobertura zona norte', 1, '2025-06-17 04:30:00', '2025-06-17 04:30:00'),
(3, 'Destacamento Sur', 'Av. Sur 789', '555-0003', 'Oficial Martínez', 'Zona residencial sur', 1, '2025-06-17 04:30:00', '2025-06-17 04:30:00'),
(4, 'Puesto Este', 'Ruta Este Km 5', '555-0004', 'Suboficial Rodríguez', 'Control de acceso este', 1, '2025-06-17 04:30:00', '2025-06-17 04:30:00'),
(5, 'Guardia Oeste', 'Av. Oeste 321', '555-0005', 'Oficial Fernández', 'Patrullaje zona oeste', 1, '2025-06-17 04:30:00', '2025-06-17 04:30:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `policias`
--

CREATE TABLE `policias` (
  `id` int(11) NOT NULL,
  `legajo` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `cin` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `direccion` text COLLATE utf8mb4_spanish2_ci,
  `fecha_ingreso` date DEFAULT NULL,
  `grado_id` int(11) NOT NULL,
  `especialidad_id` int(11) DEFAULT NULL,
  `region_id` int(11) NOT NULL,
  `lugar_guardia_id` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `regiones`
--

CREATE TABLE `regiones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `regiones`
--

INSERT INTO `regiones` (`id`, `nombre`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'Central', 'Región Central - Disponibilidad cada 15 días', '2025-06-17 04:31:00', '2025-06-17 04:31:00'),
(2, 'Regional', 'Región Regional - Disponibilidad cada 30 días', '2025-06-17 04:31:00', '2025-06-17 04:31:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `lugar` varchar(200) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `responsable` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `estado` enum('PLANIFICADO','EN_CURSO','COMPLETADO','CANCELADO') COLLATE utf8mb4_spanish2_ci DEFAULT 'PLANIFICADO',
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_ausencia`
--

CREATE TABLE `tipos_ausencia` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `requiere_justificacion` tinyint(1) DEFAULT '1',
  `dias_maximos` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `tipos_ausencia`
--

INSERT INTO `tipos_ausencia` (`id`, `nombre`, `descripcion`, `requiere_justificacion`, `dias_maximos`, `created_at`, `updated_at`) VALUES
(1, 'Licencia Médica', 'Ausencia por motivos de salud', 1, 30, '2025-06-17 04:32:00', '2025-06-17 04:32:00'),
(2, 'Vacaciones', 'Período de descanso anual', 0, 21, '2025-06-17 04:32:00', '2025-06-17 04:32:00'),
(3, 'Licencia Especial', 'Licencia por motivos personales', 1, 7, '2025-06-17 04:32:00', '2025-06-17 04:32:00'),
(4, 'Capacitación', 'Ausencia por entrenamiento o cursos', 0, 15, '2025-06-17 04:32:00', '2025-06-17 04:32:00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `servicio_id` (`servicio_id`),
  ADD KEY `policia_id` (`policia_id`);

--
-- Indices de la tabla `ausencias`
--
ALTER TABLE `ausencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `policia_id` (`policia_id`),
  ADD KEY `tipo_ausencia_id` (`tipo_ausencia_id`),
  ADD KEY `aprobado_por` (`aprobado_por`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `grados`
--
ALTER TABLE `grados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nivel_jerarquia` (`nivel_jerarquia`);

--
-- Indices de la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `policia_id` (`policia_id`),
  ADD KEY `lugar_guardia_id` (`lugar_guardia_id`);

--
-- Indices de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policia_id` (`policia_id`),
  ADD KEY `posicion` (`posicion`);

--
-- Indices de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `policias`
--
ALTER TABLE `policias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `legajo` (`legajo`),
  ADD UNIQUE KEY `cin` (`cin`),
  ADD KEY `grado_id` (`grado_id`),
  ADD KEY `especialidad_id` (`especialidad_id`),
  ADD KEY `region_id` (`region_id`),
  ADD KEY `lugar_guardia_id` (`lugar_guardia_id`);

--
-- Indices de la tabla `regiones`
--
ALTER TABLE `regiones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tipos_ausencia`
--
ALTER TABLE `tipos_ausencia`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ausencias`
--
ALTER TABLE `ausencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `grados`
--
ALTER TABLE `grados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `regiones`
--
ALTER TABLE `regiones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_ausencia`
--
ALTER TABLE `tipos_ausencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  ADD CONSTRAINT `asignaciones_servicios_ibfk_1` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_servicios_ibfk_2` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ausencias`
--
ALTER TABLE `ausencias`
  ADD CONSTRAINT `ausencias_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ausencias_ibfk_2` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencia` (`id`),
  ADD CONSTRAINT `ausencias_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `policias` (`id`);

--
-- Filtros para la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  ADD CONSTRAINT `guardias_realizadas_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guardias_realizadas_ibfk_2` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`);

--
-- Filtros para la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD CONSTRAINT `lista_guardias_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `policias`
--
ALTER TABLE `policias`
  ADD CONSTRAINT `policias_ibfk_1` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`),
  ADD CONSTRAINT `policias_ibfk_2` FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`),
  ADD CONSTRAINT `policias_ibfk_3` FOREIGN KEY (`region_id`) REFERENCES `regiones` (`id`),
  ADD CONSTRAINT `policias_ibfk_4` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;