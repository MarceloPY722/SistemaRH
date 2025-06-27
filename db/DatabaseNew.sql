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

--
-- Procedimientos
--

--
-- Procedure InicializarColaFIFO
--
DELIMITER $$
DROP PROCEDURE IF EXISTS `InicializarColaFIFO`$$
CREATE PROCEDURE `InicializarColaFIFO`()
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

--
-- Procedure InicializarColaFIFOPorLugar
--
DELIMITER $$
DROP PROCEDURE IF EXISTS `InicializarColaFIFOPorLugar`$$
CREATE PROCEDURE `InicializarColaFIFOPorLugar`()
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

--
-- Procedure ObtenerProximoPoliciaDisponible
--
DELIMITER $$
DROP PROCEDURE IF EXISTS `ObtenerProximoPoliciaDisponible`$$
CREATE PROCEDURE `ObtenerProximoPoliciaDisponible`(IN `lugar_id_param` INT, IN `region_requerida` VARCHAR(50))
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

--
-- Procedure ReorganizarListaGuardias
--
DELIMITER $$
DROP PROCEDURE IF EXISTS `ReorganizarListaGuardias`$$
CREATE PROCEDURE `ReorganizarListaGuardias`()
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

DELIMITER $$
DROP PROCEDURE IF EXISTS `ReorganizarListaGuardiasFIFO`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaGuardiasFIFO`()
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

--
-- Procedure ReorganizarListaPorLegajo
--
DELIMITER $$
DROP PROCEDURE IF EXISTS `ReorganizarListaPorLegajo`$$
CREATE PROCEDURE `ReorganizarListaPorLegajo`()
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

DELIMITER $$
DROP PROCEDURE IF EXISTS `RotarGuardia`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardia`(IN `policia_id_param` INT)
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
END$$

DELIMITER $$
DROP PROCEDURE IF EXISTS `RotarGuardiaFIFO`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardiaFIFO`(IN `policia_id_param` INT)
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

--
-- Volcado de datos para la tabla `guardias_realizadas`
--

INSERT INTO `guardias_realizadas` (`id`, `policia_id`, `fecha_inicio`, `fecha_fin`, `lugar_guardia_id`, `puesto`, `observaciones`, `created_at`) VALUES
(1, 96, '2025-06-25 15:57:12', '2025-06-26 15:57:12', 4, NULL, 'Guardia asignada automáticamente', '2025-06-25 18:57:12'),
(2, 1, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(3, 6, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(4, 13, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(5, 13, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(6, 13, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(7, 20, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(8, 20, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(9, 5, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(10, 12, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(11, 12, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(12, 12, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(13, 12, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(14, 26, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(15, 26, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(16, 3, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(17, 10, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(18, 10, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(19, 10, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(20, 10, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(21, 24, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(22, 24, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(23, 2, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(24, 9, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(25, 9, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(26, 9, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(27, 9, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(28, 30, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(29, 30, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(30, 4, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(31, 11, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(32, 11, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(33, 11, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(34, 11, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(35, 25, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(36, 25, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(37, 7, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(38, 56, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(39, 56, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(40, 56, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(41, 56, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(42, 28, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(43, 28, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(44, 8, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(45, 8, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(46, 8, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(47, 8, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(48, 8, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(49, 29, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(50, 29, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:09:49'),
(51, 13, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(52, 13, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(53, 13, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(54, 13, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(55, 13, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(56, 20, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(57, 20, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(58, 12, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(59, 12, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(60, 12, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(61, 12, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(62, 12, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(63, 26, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(64, 26, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(65, 10, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(66, 10, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(67, 10, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(68, 10, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(69, 10, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(70, 24, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(71, 24, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(72, 9, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(73, 9, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(74, 9, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(75, 9, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(76, 9, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(77, 30, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(78, 30, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(79, 11, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(80, 11, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(81, 11, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(82, 11, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(83, 11, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(84, 25, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(85, 25, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(86, 56, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(87, 56, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(88, 56, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(89, 56, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(90, 56, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(91, 28, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(92, 28, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(93, 8, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(94, 8, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(95, 8, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(96, 8, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(97, 8, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(98, 29, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(99, 29, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:10:23'),
(100, 1, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(101, 6, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(102, 13, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(103, 13, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(104, 13, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(105, 20, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(106, 20, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(107, 5, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(108, 12, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(109, 12, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(110, 12, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(111, 12, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(112, 26, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(113, 26, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(114, 3, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(115, 10, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(116, 10, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(117, 10, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(118, 10, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(119, 24, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(120, 24, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(121, 2, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(122, 9, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(123, 9, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(124, 9, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(125, 9, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(126, 30, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(127, 30, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(128, 4, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(129, 11, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(130, 11, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(131, 11, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(132, 11, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(133, 25, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(134, 25, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(135, 7, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(136, 56, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(137, 56, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(138, 56, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(139, 56, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(140, 28, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(141, 28, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(142, 8, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(143, 8, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(144, 8, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(145, 8, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(146, 8, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(147, 29, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(148, 29, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:09'),
(149, 13, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(150, 13, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(151, 13, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(152, 13, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(153, 13, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(154, 20, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(155, 20, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(156, 12, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(157, 12, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(158, 12, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(159, 12, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(160, 12, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(161, 26, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(162, 26, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(163, 10, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(164, 10, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(165, 10, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(166, 10, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(167, 10, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(168, 24, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(169, 24, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(170, 9, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(171, 9, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(172, 9, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(173, 9, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(174, 9, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(175, 30, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(176, 30, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(177, 11, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(178, 11, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(179, 11, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(180, 11, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(181, 11, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(182, 25, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(183, 25, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(184, 56, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(185, 56, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(186, 56, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(187, 56, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(188, 56, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(189, 28, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(190, 28, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(191, 8, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(192, 8, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(193, 8, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(194, 8, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(195, 8, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(196, 29, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(197, 29, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:44:42'),
(198, 1, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(199, 6, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(200, 13, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(201, 48, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(202, 76, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(203, 20, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(204, 27, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 5, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(205, 5, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(206, 12, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(207, 40, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(208, 68, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(209, 96, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(210, 26, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 4, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(211, 3, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(212, 10, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(213, 52, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(214, 80, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(215, 45, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(216, 24, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(217, 31, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 2, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(218, 2, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(219, 9, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(220, 44, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(221, 72, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(222, 100, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(223, 30, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(224, 23, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 1, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(225, 4, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(226, 11, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(227, 32, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(228, 60, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(229, 88, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(230, 25, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 3, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(231, 7, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(232, 56, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(233, 84, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(234, 14, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(235, 49, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(236, 28, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(237, 21, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 6, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(238, 8, '2025-06-29 06:00:00', '2025-06-30 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(239, 36, '2025-06-30 06:00:00', '2025-07-01 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(240, 64, '2025-07-01 06:00:00', '2025-07-02 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(241, 92, '2025-07-02 06:00:00', '2025-07-03 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(242, 15, '2025-07-03 06:00:00', '2025-07-04 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(243, 29, '2025-07-04 06:00:00', '2025-07-05 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22'),
(244, 22, '2025-07-05 06:00:00', '2025-07-06 06:00:00', 7, NULL, 'Guardia semanal generada automáticamente', '2025-06-26 17:54:22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_semanales`
--

CREATE TABLE `guardias_semanales` (
  `id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `guardias_semanales`
--

INSERT INTO `guardias_semanales` (`id`, `fecha_inicio`, `fecha_fin`, `usuario_id`, `created_at`) VALUES
(1, '2025-06-29', '2025-07-05', 1, '2025-06-26 17:09:49');

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

--
-- Volcado de datos para la tabla `lista_guardias`
--

INSERT INTO `lista_guardias` (`id`, `policia_id`, `posicion`, `ultima_guardia_fecha`, `fecha_disponible`, `created_at`, `updated_at`) VALUES
(405, 2, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(406, 9, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(407, 44, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(408, 72, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(409, 100, 20, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(410, 37, 6, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(411, 65, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(412, 93, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(413, 16, 9, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(414, 30, 21, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(415, 58, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(416, 86, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(417, 23, 22, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(418, 51, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(419, 79, 15, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(420, 3, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(421, 10, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(422, 24, 21, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(423, 52, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(424, 80, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(425, 45, 20, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(426, 73, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(427, 101, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(428, 17, 9, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(429, 38, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(430, 66, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(431, 94, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(432, 31, 22, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(433, 59, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(434, 87, 15, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(435, 4, 15, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(436, 11, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(437, 32, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(438, 60, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(439, 88, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(440, 25, 20, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(441, 53, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(442, 81, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(443, 46, 9, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(444, 74, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(445, 18, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(446, 39, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(447, 67, 13, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(448, 95, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(449, 5, 15, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(450, 12, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(451, 40, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(452, 68, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(453, 96, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(454, 33, 6, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(455, 61, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(456, 89, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(457, 26, 20, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(458, 54, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(459, 82, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(460, 19, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(461, 47, 13, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(462, 75, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(463, 1, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(464, 6, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(465, 13, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(466, 20, 21, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(467, 48, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(468, 76, 20, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(469, 41, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(470, 69, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(471, 97, 9, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(472, 34, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(473, 62, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(474, 90, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(475, 27, 22, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(476, 55, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(477, 83, 15, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(478, 7, 15, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(479, 28, 20, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(480, 56, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(481, 84, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(482, 14, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(483, 21, 21, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(484, 49, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(485, 77, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(486, 42, 9, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(487, 70, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(488, 98, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(489, 35, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(490, 63, 13, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(491, 91, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(492, 8, 15, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(493, 36, 16, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(494, 64, 17, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(495, 92, 18, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(496, 15, 19, '2025-06-26', '2025-07-11', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(497, 29, 20, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(498, 57, 7, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(499, 85, 8, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(500, 22, 21, '2025-06-26', '2025-07-26', '2025-06-26 17:53:59', '2025-06-26 17:54:22'),
(501, 50, 10, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(502, 78, 11, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(503, 43, 12, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(504, 71, 13, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59'),
(505, 99, 14, NULL, NULL, '2025-06-26 17:53:59', '2025-06-26 17:53:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugares_guardias`
--

CREATE TABLE `lugares_guardias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `direccion` varchar(200) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `zona` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `lugares_guardias`
--

INSERT INTO `lugares_guardias` (`id`, `nombre`, `descripcion`, `direccion`, `zona`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'JEFE DE SERVICIO', '', '', 'Luque', 1, '2025-06-17 04:44:27', '2025-06-18 19:43:52'),
(2, 'JEFE DE CUARTEL', '', '', 'Luque', 1, '2025-06-18 19:44:20', '2025-06-18 19:44:20'),
(3, 'OFICIAL DE GUARDIA', '', '', 'Luque', 1, '2025-06-18 19:44:39', '2025-06-18 19:44:39'),
(4, 'GRUPO DOMINGO', '', '', 'Luque', 1, '2025-06-18 19:45:07', '2025-06-18 19:45:07'),
(5, 'CONDUCTOR DE GUARDIA', '', '', 'Luque', 1, '2025-06-18 19:45:25', '2025-06-18 19:45:25'),
(6, 'TELEFONISTA', '', '', 'Luque', 1, '2025-06-18 19:45:38', '2025-06-18 19:45:38'),
(7, 'TIKETEROS', '', '', 'Luque', 1, '2025-06-18 19:45:52', '2025-06-18 19:45:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `policias`
--

CREATE TABLE `policias` (
  `id` int(11) NOT NULL,
  `legajo` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `cin` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `genero` enum('MASCULINO','FEMENINO') COLLATE utf8mb4_spanish2_ci NOT NULL,
  `grado_id` int(11) NOT NULL,
  `especialidad_id` int(11) DEFAULT NULL,
  `cargo` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comisionamiento` varchar(200) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `region_id` int(11) DEFAULT '1',
  `lugar_guardia_id` int(11) DEFAULT NULL,
  `observaciones` varchar(500) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `policias`
--

INSERT INTO `policias` (`id`, `legajo`, `nombre`, `apellido`, `cin`, `genero`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region_id`, `lugar_guardia_id`, `observaciones`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Julio Alberto', 'Ramirez', '4324324', 'MASCULINO', 1, 1, 'JEFE DE DEPARTAMENTO', '', '09877673636', 1, 5, '0', 1, '2025-06-25 17:29:51', '2025-06-25 18:23:16'),
(2, 2, 'Carlos', 'González', '1234567', 'MASCULINO', 2, 1, 'Jefe de División', 'Comisionado en Central', '0981122334', 1, 1, '0', 1, '2025-06-25 18:32:48', '2025-06-25 18:41:59'),
(3, 3, 'Ana', 'Martínez', '2345678', 'FEMENINO', 3, 2, 'Subjefa de Departamento', NULL, '0982233445', 1, 2, 'Especialista en logística', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(4, 4, 'Luis', 'Rodríguez', '3456789', 'MASCULINO', 4, NULL, 'Oficial Inspector', NULL, '0983344556', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(5, 5, 'María', 'Pérez', '4567890', 'FEMENINO', 5, NULL, 'Oficial Primero', NULL, '0984455667', 1, 4, 'A cargo de grupo Domingo', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(6, 6, 'Juan', 'Gómez', '5678901', 'MASCULINO', 6, NULL, 'Oficial Segundo', NULL, '0985566778', 1, 5, 'Conductor principal', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(7, 7, 'Laura', 'Fernández', '6789012', 'FEMENINO', 7, NULL, 'Oficial Ayudante', NULL, '0986677889', 1, 6, 'Telefonista principal', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(8, 8, 'Pedro', 'López', '7890123', 'MASCULINO', 8, NULL, 'Suboficial Superior', NULL, '0987788990', 1, 7, 'Encargado de tiqueteros', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(9, 9, 'Sofía', 'Díaz', '8901234', 'FEMENINO', 9, NULL, 'Suboficial Principal', NULL, '0988899001', 1, 1, 'Asistente jefe de servicio', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(10, 10, 'Miguel', 'Hernández', '9012345', 'MASCULINO', 10, NULL, 'Suboficial Mayor', NULL, '0989900112', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(11, 11, 'Elena', 'Sánchez', '0123456', 'FEMENINO', 11, NULL, 'Suboficial Inspector', NULL, '0980011223', 1, 3, 'Encargado turno noche', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(12, 12, 'Jorge', 'Ramírez', '1122334', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0981122335', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(13, 13, 'Carmen', 'Torres', '2233445', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0982233446', 1, 5, 'Conductor secundario', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(14, 14, 'Ricardo', 'Jiménez', '3344556', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0983344557', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(15, 15, 'Patricia', 'Ruiz', '4455667', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984455668', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(16, 16, 'Fernando', 'Vargas', '5566778', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985566779', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(17, 17, 'Isabel', 'Castro', '6677889', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0986677880', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(18, 18, 'Roberto', 'Mendoza', '7788990', 'MASCULINO', 15, NULL, 'Funcionario Administrativo', NULL, '0987788991', 1, 3, 'Encargado de archivo', 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(19, 19, 'Lucía', 'Guerrero', '8899001', 'FEMENINO', 15, NULL, 'Funcionaria de Soporte', NULL, '0988899002', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(20, 20, 'Oscar', 'Silva', '9900112', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0989900113', 2, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(21, 21, 'Adriana', 'Rojas', '0011223', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980011224', 2, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(22, 22, 'Hugo', 'Mora', '1020304', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981020305', 2, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(23, 23, 'Gloria', 'Navarro', '2030405', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0982030406', 2, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(24, 24, 'Raúl', 'Cordero', '3040506', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0983040507', 2, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(25, 25, 'Silvia', 'Paredes', '4050607', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984050608', 2, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(26, 26, 'Mario', 'Quintero', '5060708', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985060709', 2, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(27, 27, 'Natalia', 'Salazar', '6070809', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0986070800', 2, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(28, 28, 'Felipe', 'Aguirre', '7080901', 'MASCULINO', 12, 1, 'Suboficial Primero', '', '0987080902', 2, 6, '0', 1, '2025-06-25 18:32:48', '2025-06-25 18:42:24'),
(29, 29, 'Verónica', 'Peña', '8090102', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988090103', 2, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(30, 30, 'Arturo', 'Delgado', '9101112', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989101113', 2, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(31, 31, 'Diana', 'Campos', '0111213', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0980111214', 2, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(32, 32, 'Gerardo', 'Ríos', '1213141', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0981213142', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(33, 33, 'Rosa', 'Valdez', '1314151', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0981314152', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(34, 34, 'Samuel', 'Carrillo', '1415161', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981415162', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(35, 35, 'Teresa', 'Miranda', '1516171', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0981516172', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(36, 36, 'Alberto', 'Mejía', '1617181', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0981617182', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(37, 37, 'Beatriz', 'Vega', '1718191', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0981718192', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(38, 38, 'César', 'Fuentes', '1819202', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981819203', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(39, 39, 'Daniela', 'Orellana', '1920212', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0981920213', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(40, 40, 'Ernesto', 'Ponce', '2021222', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982021223', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(41, 41, 'Flor', 'Santana', '2122232', 'FEMENINO', 13, NULL, 'Suboficial Segundo', '', '0982122233', 1, 5, '0', 1, '2025-06-25 18:32:48', '2025-06-25 19:15:07'),
(42, 42, 'Gustavo', 'Tapia', '2223242', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0982223243', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(43, 43, 'Hilda', 'Uribe', '2324252', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0982324253', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(44, 44, 'Iván', 'Zambrano', '2425262', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982425263', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(45, 45, 'Julia', 'Yáñez', '2526272', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982526273', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(46, 46, 'Kevin', 'Xicará', '2627282', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0982627283', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(47, 47, 'Lorena', 'Wong', '2728292', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0982728293', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(48, 48, 'Manuel', 'Villalba', '2829303', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982829304', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(49, 49, 'Nora', 'Urbina', '2930313', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982930314', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(50, 50, 'Octavio', 'Toledo', '3031323', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983031324', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(51, 51, 'Pamela', 'Soria', '3132333', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0983132334', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(52, 52, 'Quirino', 'Reyes', '3233343', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0983233344', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(53, 53, 'Rebeca', 'Quintana', '3334353', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0983334354', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(54, 54, 'Santiago', 'Paz', '3435363', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983435364', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(55, 55, 'Úrsula', 'Orozco', '3536373', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0983536374', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(56, 56, 'Víctor', 'Núñez', '3637383', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0983637384', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(57, 57, 'Wendy', 'Molina', '3738393', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0983738394', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(58, 58, 'Xavier', 'Llanos', '3839404', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983839405', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(59, 59, 'Yolanda', 'Katz', '3940414', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0983940415', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(60, 60, 'Zacarías', 'Jaramillo', '4041424', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984041425', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(61, 61, 'Alicia', 'Ibarra', '4142434', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984142435', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(62, 62, 'Bernardo', 'Herrera', '4243444', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0984243445', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(63, 63, 'Claudia', 'Guzmán', '4344454', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0984344455', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(64, 64, 'Darío', 'Flores', '4445464', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984445465', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(65, 65, 'Estela', 'Espinoza', '4546474', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984546475', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(66, 66, 'Fabián', 'Duarte', '4647484', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0984647485', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(67, 67, 'Gabriela', 'Cortés', '4748494', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0984748495', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(68, 68, 'Héctor', 'Bustos', '4849505', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984849506', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(69, 69, 'Iris', 'Aravena', '4950515', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984950516', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(70, 70, 'Jacobo', 'Barrios', '5051525', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985051526', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(71, 71, 'Karina', 'Acosta', '5152535', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0985152536', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(72, 72, 'Leonardo', 'Zelaya', '5253545', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0985253546', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(73, 73, 'Mónica', 'Yánez', '5354555', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0985354556', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(74, 74, 'Néstor', 'Xol', '5455565', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985455566', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(75, 75, 'Olga', 'Wagner', '5556575', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0985556576', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(76, 76, 'Pablo', 'Vallejo', '5657585', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0985657586', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(77, 77, 'Queca', 'Ulloa', '5758595', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0985758596', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(78, 78, 'Ramiro', 'Trelles', '5859606', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985859607', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(79, 79, 'Sara', 'Solís', '5960616', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0985960617', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(80, 80, 'Tomás', 'Rocha', '6061626', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986061627', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(81, 81, 'Úrsula', 'Pinto', '6162636', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986162637', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(82, 82, 'Vicente', 'Ojeda', '6263646', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0986263647', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(83, 83, 'Wanda', 'Nieto', '6364656', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0986364657', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(84, 84, 'Xavier', 'Méndez', '6465666', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986465667', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(85, 85, 'Yanina', 'López', '6566676', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986566677', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(86, 86, 'Zacarías', 'Katz', '6667686', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0986667687', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(87, 87, 'Amalia', 'Jara', '6768696', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0986768697', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(88, 88, 'Benito', 'Ibarra', '6869707', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986869708', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(89, 89, 'Celeste', 'Hernández', '6970717', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986970718', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(90, 90, 'Damián', 'Gómez', '7071727', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987071728', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(91, 91, 'Elisa', 'Fuentes', '7172737', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987172738', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(92, 92, 'Froilán', 'Espinoza', '7273747', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0987273748', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(93, 93, 'Gisela', 'Díaz', '7374757', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0987374758', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(94, 94, 'Horacio', 'Castro', '7475767', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987475768', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(95, 95, 'Inés', 'Bustamante', '7576777', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987576778', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(96, 96, 'Joaquín', 'Araya', '7677787', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0987677788', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(97, 97, 'Katia', 'Zúñiga', '7778797', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0987778798', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(98, 98, 'Lautaro', 'Yáñez', '7878808', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987878809', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(99, 99, 'Mireya', 'Xicará', '7980818', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987980819', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(100, 100, 'Norberto', 'Wagner', '8081828', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988081829', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(101, 101, 'Ofelia', 'Valdés', '8182838', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988182839', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48');

--
-- Disparadores `policias`
--
DELIMITER $$
CREATE TRIGGER `trg_insert_lista_guardias` AFTER INSERT ON `policias` FOR EACH ROW BEGIN
    DECLARE max_pos INT DEFAULT 0;
    
    SELECT COALESCE(MAX(posicion), 0) INTO max_pos FROM lista_guardias;
    
    INSERT INTO lista_guardias (policia_id, posicion)
    VALUES (NEW.id, max_pos + 1);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `regiones`
--

CREATE TABLE `regiones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `regiones`
--

INSERT INTO `regiones` (`id`, `nombre`, `descripcion`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'CENTRAL', 'Región Central', 1, '2025-06-25 18:09:29', '2025-06-25 18:09:29'),
(2, 'REGIONAL', 'Región Regional', 1, '2025-06-25 18:09:29', '2025-06-25 18:09:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicios`
--

CREATE TABLE `servicios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_servicio` date NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `orden_del_dia` varchar(50) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `jefe_servicio_id` int(11) DEFAULT NULL,
  `estado` enum('PROGRAMADO','EN_CURSO','COMPLETADO','CANCELADO') COLLATE utf8mb4_spanish2_ci DEFAULT 'PROGRAMADO',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_ausencias`
--

CREATE TABLE `tipos_ausencias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_spanish2_ci,
  `requiere_justificacion` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `tipos_ausencias`
--

INSERT INTO `tipos_ausencias` (`id`, `nombre`, `descripcion`, `requiere_justificacion`, `created_at`) VALUES
(1, 'Médico', 'Ausencia por motivos médicos', 1, '2025-06-25 15:54:11'),
(2, 'Embarazo', 'Licencia por embarazo', 1, '2025-06-25 15:54:11'),
(3, 'Personal', 'Ausencia por motivos personales', 0, '2025-06-25 15:54:11'),
(4, 'Vacaciones', 'Período vacacional', 0, '2025-06-25 15:54:11'),
(5, 'Capacitación', 'Ausencia por capacitación o entrenamiento', 0, '2025-06-25 15:54:11'),
(6, 'Suspensión', 'Suspensión disciplinaria', 1, '2025-06-25 15:54:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre_usuario` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `contraseña` varchar(255) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nombre_completo` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rol` enum('ADMIN') COLLATE utf8mb4_spanish2_ci DEFAULT 'ADMIN',
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_usuario`, `contraseña`, `nombre_completo`, `email`, `rol`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'Admin', '$2y$10$9g5OUkPYc66Pf0q0nFATi.zmI3Af0vFCBfTRttYuvqlYZf4l9EaXe', 'Admin Marcelo', 'admin@gmail.com', 'ADMIN', 1, '2025-06-25 15:54:58', '2025-06-25 15:54:58');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_disponibilidad_policias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_disponibilidad_policias` (
`id` int(11)
,`legajo` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`genero` enum('MASCULINO','FEMENINO')
,`grado` varchar(100)
,`nivel_jerarquia` int(11)
,`especialidad` varchar(150)
,`cargo` varchar(150)
,`telefono` varchar(20)
,`lugar_guardia` varchar(100)
,`region` varchar(50)
,`disponibilidad` varchar(13)
,`observaciones` varchar(500)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_lista_guardias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_lista_guardias` (
`posicion` int(11)
,`policia_id` int(11)
,`legajo` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`genero` enum('MASCULINO','FEMENINO')
,`grado` varchar(100)
,`nivel_jerarquia` int(11)
,`lugar_guardia` varchar(100)
,`ultima_guardia_fecha` date
,`disponibilidad` varchar(13)
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_servicio` (`servicio_id`),
  ADD KEY `idx_policia` (`policia_id`),
  ADD KEY `idx_puesto` (`puesto`);

--
-- Indices de la tabla `ausencias`
--
ALTER TABLE `ausencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tipo_ausencia_id` (`tipo_ausencia_id`),
  ADD KEY `aprobado_por` (`aprobado_por`),
  ADD KEY `idx_policia` (`policia_id`),
  ADD KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_ausencias_vigentes` (`policia_id`,`fecha_inicio`,`fecha_fin`,`estado`);

--
-- Indices de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `grados`
--
ALTER TABLE `grados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_policia` (`policia_id`),
  ADD KEY `idx_fecha` (`fecha_inicio`),
  ADD KEY `idx_lugar` (`lugar_guardia_id`),
  ADD KEY `idx_guardias_realizadas_fecha` (`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_semana` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policia_id` (`policia_id`),
  ADD KEY `idx_posicion` (`posicion`),
  ADD KEY `idx_policia` (`policia_id`);

--
-- Indices de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `policias`
--
ALTER TABLE `policias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `legajo` (`legajo`),
  ADD UNIQUE KEY `cin` (`cin`),
  ADD KEY `especialidad_id` (`especialidad_id`),
  ADD KEY `idx_legajo` (`legajo`),
  ADD KEY `idx_cin` (`cin`),
  ADD KEY `idx_grado` (`grado_id`),
  ADD KEY `idx_lugar_guardia` (`lugar_guardia_id`),
  ADD KEY `idx_region` (`region_id`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_policias_activos` (`activo`,`grado_id`);

--
-- Indices de la tabla `regiones`
--
ALTER TABLE `regiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jefe_servicio_id` (`jefe_servicio_id`),
  ADD KEY `idx_fecha` (`fecha_servicio`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_servicios_fecha` (`fecha_servicio`,`estado`);

--
-- Indices de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT de la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=506;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

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
-- AUTO_INCREMENT de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_disponibilidad_policias`
--
DROP TABLE IF EXISTS `vista_disponibilidad_policias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_disponibilidad_policias`  AS SELECT `p`.`id` AS `id`, `p`.`legajo` AS `legajo`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `p`.`genero` AS `genero`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `e`.`nombre` AS `especialidad`, `p`.`cargo` AS `cargo`, `p`.`telefono` AS `telefono`, `lg`.`nombre` AS `lugar_guardia`, `r`.`nombre` AS `region`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad`, `p`.`observaciones` AS `observaciones` FROM ((((`policias` `p` left join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `especialidades` `e` on((`p`.`especialidad_id` = `e`.`id`))) left join `lugares_guardias` `lg` on((`p`.`lugar_guardia_id` = `lg`.`id`))) left join `regiones` `r` on((`p`.`region_id` = `r`.`id`))) WHERE (`p`.`activo` = TRUE) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_lista_guardias`
--
DROP TABLE IF EXISTS `vista_lista_guardias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_lista_guardias`  AS SELECT `lg`.`posicion` AS `posicion`, `p`.`id` AS `policia_id`, `p`.`legajo` AS `legajo`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `p`.`genero` AS `genero`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `lguar`.`nombre` AS `lugar_guardia`, `lg`.`ultima_guardia_fecha` AS `ultima_guardia_fecha`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad` FROM (((`lista_guardias` `lg` join `policias` `p` on((`lg`.`policia_id` = `p`.`id`))) join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `lugares_guardias` `lguar` on((`p`.`lugar_guardia_id` = `lguar`.`id`))) WHERE (`p`.`activo` = TRUE) ORDER BY `lg`.`posicion` ASC ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  ADD CONSTRAINT `asignaciones_servicios_ibfk_1` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_servicios_ibfk_2` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`);

--
-- Filtros para la tabla `ausencias`
--
ALTER TABLE `ausencias`
  ADD CONSTRAINT `ausencias_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`),
  ADD CONSTRAINT `ausencias_ibfk_2` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencias` (`id`),
  ADD CONSTRAINT `ausencias_ibfk_3` FOREIGN KEY (`aprobado_por`) REFERENCES `policias` (`id`);

--
-- Filtros para la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  ADD CONSTRAINT `guardias_realizadas_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`),
  ADD CONSTRAINT `guardias_realizadas_ibfk_2` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`);

--
-- Filtros para la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD CONSTRAINT `lista_guardias_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`);

--
-- Filtros para la tabla `policias`
--
ALTER TABLE `policias`
  ADD CONSTRAINT `policias_ibfk_1` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`),
  ADD CONSTRAINT `policias_ibfk_2` FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`),
  ADD CONSTRAINT `policias_ibfk_3` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`),
  ADD CONSTRAINT `policias_ibfk_4` FOREIGN KEY (`region_id`) REFERENCES `regiones` (`id`);

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`jefe_servicio_id`) REFERENCES `policias` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
