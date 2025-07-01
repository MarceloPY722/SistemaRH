-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 30-06-2025 a las 18:45:29
-- Versión del servidor: 8.4.3
-- Versión de PHP: 8.3.16

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
CREATE DEFINER=`root`@`localhost` PROCEDURE `InicializarColaFIFO` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `InicializarColaFIFOPorLugar` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `ObtenerProximoPoliciaDisponible` (IN `lugar_id_param` INT, IN `region_requerida` VARCHAR(50))   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaGuardias` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaGuardiasFIFO` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `ReorganizarListaPorLegajo` ()   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardia` (IN `policia_id_param` INT)   BEGIN
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardiaFIFO` (IN `policia_id_param` INT)   BEGIN
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
    
    -- Reordenar posiciones usando una tabla temporal
    CREATE TEMPORARY TABLE temp_reorder AS
    SELECT lg.id, 
           ROW_NUMBER() OVER (
               ORDER BY CASE WHEN lg.policia_id = policia_id_param THEN 999999 ELSE lg.posicion END
           ) as nueva_posicion
    FROM lista_guardias lg
    JOIN policias p ON lg.policia_id = p.id
    WHERE p.lugar_guardia_id = lugar_guardia_id;
    
    -- Actualizar las posiciones usando la tabla temporal
    UPDATE lista_guardias lg
    JOIN temp_reorder tr ON lg.id = tr.id
    SET lg.posicion = tr.nueva_posicion;
    
    -- Limpiar tabla temporal
    DROP TEMPORARY TABLE temp_reorder;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_servicios`
--

CREATE TABLE `asignaciones_servicios` (
  `id` int NOT NULL,
  `servicio_id` int NOT NULL,
  `policia_id` int NOT NULL,
  `puesto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `lugar` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `telefono_contacto` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ausencias`
--

CREATE TABLE `ausencias` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `tipo_ausencia_id` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `justificacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `documento_adjunto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `aprobado_por` int DEFAULT NULL,
  `estado` enum('PENDIENTE','APROBADA','RECHAZADA','COMPLETADA') COLLATE utf8mb4_spanish2_ci DEFAULT 'PENDIENTE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `ausencias`
--

INSERT INTO `ausencias` (`id`, `policia_id`, `tipo_ausencia_id`, `fecha_inicio`, `fecha_fin`, `descripcion`, `justificacion`, `documento_adjunto`, `aprobado_por`, `estado`, `created_at`, `updated_at`) VALUES
(6, 1, 5, '2025-06-29', '2025-07-29', 'Capacitacion', '', NULL, 1, 'APROBADA', '2025-06-29 18:08:30', '2025-06-29 18:08:30'),
(7, 6, 5, '2025-06-29', '2025-07-05', 'capacitacion', '', NULL, 1, 'APROBADA', '2025-06-29 18:26:36', '2025-06-29 18:29:32'),
(9, 598, 5, '2025-06-30', '2026-04-16', 'capac', '', NULL, 1, 'APROBADA', '2025-06-30 18:42:01', '2025-06-30 18:42:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `especialidades`
--

CREATE TABLE `especialidades` (
  `id` int NOT NULL,
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
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
  `id` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nivel_jerarquia` int NOT NULL,
  `abreviatura` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
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
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `lugar_guardia_id` int NOT NULL,
  `puesto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_semanales`
--

CREATE TABLE `guardias_semanales` (
  `id` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `usuario_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lista_guardias`
--

CREATE TABLE `lista_guardias` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `posicion` int NOT NULL,
  `ultima_guardia_fecha` date DEFAULT NULL,
  `fecha_disponible` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `lista_guardias`
--

INSERT INTO `lista_guardias` (`id`, `policia_id`, `posicion`, `ultima_guardia_fecha`, `fecha_disponible`, `created_at`, `updated_at`) VALUES
(3371, 1, 1, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3372, 2, 2, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3373, 3, 3, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3374, 4, 4, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3375, 5, 5, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3376, 6, 6, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3377, 7, 7, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3378, 8, 8, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3379, 9, 9, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3380, 10, 10, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3381, 11, 11, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3382, 104, 12, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3383, 12, 13, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3384, 13, 14, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3385, 20, 15, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3386, 24, 16, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3387, 28, 17, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3388, 32, 18, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3389, 36, 19, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3390, 40, 20, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3391, 44, 21, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3392, 48, 22, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3393, 52, 23, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3394, 56, 24, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3395, 60, 25, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3396, 64, 26, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3397, 68, 27, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3398, 72, 28, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3399, 76, 29, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3400, 80, 30, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3401, 84, 31, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3402, 88, 32, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3403, 92, 33, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3404, 96, 34, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3405, 100, 35, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3406, 245, 36, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3407, 248, 37, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3408, 251, 38, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3409, 254, 39, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3410, 257, 40, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3411, 260, 41, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3412, 263, 42, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3413, 265, 43, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3414, 268, 44, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3415, 271, 45, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3416, 274, 46, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3417, 277, 47, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3418, 280, 48, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3419, 283, 49, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3420, 285, 50, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3421, 288, 51, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3422, 291, 52, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3423, 294, 53, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3424, 297, 54, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3425, 300, 55, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3426, 303, 56, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3427, 485, 57, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3428, 488, 58, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3429, 491, 59, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3430, 494, 60, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3431, 497, 61, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3432, 500, 62, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3433, 503, 63, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3434, 505, 64, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3435, 508, 65, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3436, 511, 66, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3437, 514, 67, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3438, 517, 68, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3439, 520, 69, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3440, 523, 70, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3441, 585, 71, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3442, 588, 72, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3443, 591, 73, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3444, 594, 74, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3445, 597, 75, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3446, 600, 76, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3447, 603, 77, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3448, 625, 78, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3449, 628, 79, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3450, 631, 80, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3451, 634, 81, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3452, 637, 82, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3453, 640, 83, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3454, 643, 84, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3455, 14, 85, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3456, 15, 86, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3457, 21, 87, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3458, 25, 88, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3459, 29, 89, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3460, 33, 90, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3461, 37, 91, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3462, 41, 92, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3463, 45, 93, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3464, 49, 94, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3465, 53, 95, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3466, 57, 96, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3467, 61, 97, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3468, 65, 98, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3469, 69, 99, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3470, 73, 100, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3471, 77, 101, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3472, 81, 102, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3473, 85, 103, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3474, 89, 104, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3475, 93, 105, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3476, 97, 106, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3477, 101, 107, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3478, 246, 108, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3479, 249, 109, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3480, 252, 110, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3481, 255, 111, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3482, 258, 112, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3483, 261, 113, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3484, 264, 114, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3485, 266, 115, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3486, 269, 116, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3487, 272, 117, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3488, 275, 118, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3489, 278, 119, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3490, 281, 120, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3491, 284, 121, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3492, 286, 122, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3493, 289, 123, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3494, 292, 124, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3495, 295, 125, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3496, 298, 126, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3497, 301, 127, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3498, 304, 128, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3499, 486, 129, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3500, 489, 130, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3501, 492, 131, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3502, 495, 132, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3503, 498, 133, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3504, 501, 134, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3505, 504, 135, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3506, 506, 136, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3507, 509, 137, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3508, 512, 138, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3509, 515, 139, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3510, 518, 140, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3511, 521, 141, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3512, 524, 142, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3513, 586, 143, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3514, 589, 144, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3515, 592, 145, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3516, 595, 146, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3517, 598, 147, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3518, 601, 148, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3519, 604, 149, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3520, 626, 150, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3521, 629, 151, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3522, 632, 152, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3523, 635, 153, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3524, 638, 154, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3525, 641, 155, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3526, 644, 156, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3527, 16, 157, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3528, 17, 158, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3529, 22, 159, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3530, 26, 160, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3531, 30, 161, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3532, 34, 162, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3533, 38, 163, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3534, 42, 164, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3535, 46, 165, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3536, 50, 166, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3537, 54, 167, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3538, 58, 168, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3539, 62, 169, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3540, 66, 170, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3541, 70, 171, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3542, 74, 172, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3543, 78, 173, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3544, 82, 174, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3545, 86, 175, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3546, 90, 176, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3547, 94, 177, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3548, 98, 178, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3549, 247, 179, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3550, 250, 180, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3551, 253, 181, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3552, 256, 182, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3553, 259, 183, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3554, 262, 184, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3555, 267, 185, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3556, 270, 186, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3557, 273, 187, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3558, 276, 188, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3559, 279, 189, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3560, 282, 190, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3561, 287, 191, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3562, 290, 192, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3563, 293, 193, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3564, 296, 194, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3565, 299, 195, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3566, 302, 196, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3567, 487, 197, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3568, 490, 198, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3569, 493, 199, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3570, 496, 200, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3571, 499, 201, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3572, 502, 202, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3573, 507, 203, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3574, 510, 204, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3575, 513, 205, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3576, 516, 206, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3577, 519, 207, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3578, 522, 208, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3579, 587, 209, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3580, 590, 210, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3581, 593, 211, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3582, 596, 212, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3583, 599, 213, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3584, 602, 214, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3585, 627, 215, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3586, 630, 216, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3587, 633, 217, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3588, 636, 218, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3589, 639, 219, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3590, 642, 220, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3591, 18, 221, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3592, 19, 222, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3593, 23, 223, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3594, 27, 224, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3595, 31, 225, NULL, NULL, '2025-06-30 18:43:40', '2025-06-30 18:43:40'),
(3596, 35, 226, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3597, 39, 227, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3598, 43, 228, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3599, 47, 229, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3600, 51, 230, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3601, 55, 231, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3602, 59, 232, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3603, 63, 233, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3604, 67, 234, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3605, 71, 235, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3606, 75, 236, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3607, 79, 237, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3608, 83, 238, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3609, 87, 239, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3610, 91, 240, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3611, 95, 241, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41'),
(3612, 99, 242, NULL, NULL, '2025-06-30 18:43:41', '2025-06-30 18:43:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lugares_guardias`
--

CREATE TABLE `lugares_guardias` (
  `id` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `direccion` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `zona` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
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
  `id` int NOT NULL,
  `legajo` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `cin` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `genero` enum('MASCULINO','FEMENINO') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `grado_id` int NOT NULL,
  `especialidad_id` int DEFAULT NULL,
  `cargo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comisionamiento` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `region_id` int DEFAULT '1',
  `lugar_guardia_id` int DEFAULT NULL,
  `observaciones` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `policias`
--

INSERT INTO `policias` (`id`, `legajo`, `nombre`, `apellido`, `cin`, `genero`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region_id`, `lugar_guardia_id`, `observaciones`, `activo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Julio Alberto', 'Ramirez', '4324324', 'MASCULINO', 1, 1, 'JEFE DE DEPARTAMENTO', '', '0987767363', 1, 5, '0', 1, '2025-06-25 17:29:51', '2025-06-28 18:50:46'),
(2, 2, 'Carlos', 'González', '1234567', 'MASCULINO', 2, 1, 'Jefe de División', 'Comisionado en Central', '0981122334', 1, 1, '0', 1, '2025-06-25 18:32:48', '2025-06-25 18:41:59'),
(3, 3, 'Ana', 'Martínez', '2345678', 'FEMENINO', 3, 2, 'Subjefa de Departamento', NULL, '0982233445', 1, 2, 'Especialista en logística', 1, '2025-06-25 18:32:48', '2025-06-28 17:01:38'),
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
(95, 95, 'Inés', 'Bustamante', '7576777', 'FEMENINO', 15, 1, 'Funcionaria', '', '0987576778', 2, 3, '0', 1, '2025-06-25 18:32:48', '2025-06-29 17:30:06'),
(96, 96, 'Joaquín', 'Araya', '7677787', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0987677788', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(97, 97, 'Katia', 'Zúñiga', '7778797', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0987778798', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(98, 98, 'Lautaro', 'Yáñez', '7878808', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987878809', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(99, 99, 'Mireya', 'Xicará', '7980818', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987980819', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(100, 101, 'Norberto', 'Wagner', '8081828', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988081829', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-28 15:48:19'),
(101, 100, 'Ofelia', 'Valdés', '8182838', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988182839', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-28 15:48:19'),
(102, 102, 'Lionel', 'Messi', '2345333', 'MASCULINO', 1, 1, 'JEFE DE DEPARTAMENTO', '', '09725463554', 1, 2, '', 0, '2025-06-28 18:01:25', '2025-06-28 18:53:21'),
(104, 243, 'Julio Alberto', 'Ugarte', '9876366', 'MASCULINO', 11, 1, 'JEFE DE DEPARTAMENTO', '', '0976554554', 2, 4, '', 1, '2025-06-29 17:25:07', '2025-06-29 17:56:52'),
(245, 103, 'Carlos', 'Mendoza', '8283848', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988283849', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(246, 104, 'Ana', 'Vargas', '8384858', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988384859', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(247, 105, 'Eduardo', 'Ramírez', '8485868', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0988485869', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(248, 106, 'María', 'Jiménez', '8586878', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0988586879', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(249, 107, 'Roberto', 'Silva', '8687888', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0988687889', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(250, 108, 'Carmen', 'López', '8788898', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0988788899', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(251, 109, 'Fernando', 'García', '8889908', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988889909', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(252, 110, 'Patricia', 'Morales', '8990918', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988990919', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(253, 111, 'Diego', 'Herrera', '9091928', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989091929', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(254, 112, 'Lucía', 'Castro', '9192938', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0989192939', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(255, 113, 'Andrés', 'Ruiz', '9293948', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0989293949', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(256, 114, 'Valeria', 'Vega', '9394958', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989394959', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(257, 115, 'Javier', 'Peña', '9495968', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0989495969', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(258, 116, 'Mónica', 'Soto', '9596978', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0989596979', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(259, 117, 'Raúl', 'Ortega', '9697988', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989697989', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(260, 118, 'Sandra', 'Flores', '9798998', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0989798999', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(261, 119, 'Gustavo', 'Ramos', '9899008', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0989899009', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(262, 120, 'Elena', 'Guerrero', '9900018', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989900019', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(263, 121, 'Sergio', 'Medina', '0001028', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980001029', 1, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(264, 122, 'Cristina', 'Aguilar', '0102038', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980102039', 2, 1, NULL, 1, '2025-06-29 17:58:08', '2025-06-29 17:58:08'),
(265, 143, 'Arturo', 'Maldonado', '2223248', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982223249', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(266, 144, 'Dolores', 'Acosta', '2324258', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982324259', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(267, 145, 'Mauricio', 'Sandoval', '2425268', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0982425269', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(268, 146, 'Rocío', 'Cabrera', '2526278', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0982526279', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(269, 147, 'Esteban', 'Palacios', '2627288', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0982627289', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(270, 148, 'Margarita', 'Solano', '2728298', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0982728299', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(271, 149, 'Germán', 'Arévalo', '2829308', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982829309', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(272, 150, 'Consuelo', 'Barrera', '2930318', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982930319', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(273, 151, 'Fabián', 'Contreras', '3031328', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983031329', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(274, 152, 'Alicia', 'Figueroa', '3132338', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0983132339', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(275, 153, 'Leandro', 'Galindo', '3233348', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0983233349', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(276, 154, 'Remedios', 'Henríquez', '3334358', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983334359', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(277, 155, 'Patricio', 'Ibarra', '3435368', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0983435369', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(278, 156, 'Soledad', 'Jaramillo', '3536378', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0983536379', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(279, 157, 'Gonzalo', 'Lara', '3637388', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983637389', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(280, 158, 'Clemencia', 'Márquez', '3738398', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0983738399', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(281, 159, 'Aurelio', 'Nieto', '3839408', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0983839409', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(282, 160, 'Encarnación', 'Orozco', '3940418', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0983940419', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(283, 161, 'Maximiliano', 'Pedraza', '4041428', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984041429', 1, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(284, 162, 'Purificación', 'Quiroga', '4142438', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984142439', 2, 3, NULL, 1, '2025-06-29 17:59:30', '2025-06-29 17:59:30'),
(285, 163, 'Sebastián', 'Rincón', '4243448', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984243449', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(286, 164, 'Asunción', 'Suárez', '4344458', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984344459', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(287, 165, 'Valentín', 'Téllez', '4445468', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0984445469', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(288, 166, 'Visitación', 'Urrutia', '4546478', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0984546479', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(289, 167, 'Wenceslao', 'Vásquez', '4647488', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0984647489', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(290, 168, 'Xenia', 'Yáñez', '4748498', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0984748499', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(291, 169, 'Zacarías', 'Zapata', '4849508', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0984849509', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(292, 170, 'Azucena', 'Aguayo', '4950518', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0984950519', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(293, 171, 'Bartolomé', 'Bravo', '5051528', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985051529', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(294, 172, 'Candelaria', 'Calvo', '5152538', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0985152539', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(295, 173, 'Demetrio', 'Duarte', '5253548', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0985253549', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(296, 174, 'Epifanía', 'Estrada', '5354558', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985354559', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(297, 175, 'Florencio', 'Franco', '5455568', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0985455569', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(298, 176, 'Genoveva', 'Giraldo', '5556578', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0985556579', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(299, 177, 'Hermenegildo', 'Hurtado', '5657588', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985657589', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(300, 178, 'Inmaculada', 'Iglesias', '5758598', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0985758599', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(301, 179, 'Jeremías', 'Jiménez', '5859608', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0985859609', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(302, 180, 'Karina', 'Keller', '5960618', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0985960619', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(303, 181, 'Leopoldo', 'Luna', '6061628', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986061629', 1, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(304, 182, 'Milagros', 'Moreno', '6162638', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986162639', 2, 4, NULL, 1, '2025-06-29 18:01:54', '2025-06-29 18:01:54'),
(485, 203, 'Higinio', 'Herrera', '8280848', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988283849', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(486, 204, 'Inocencia', 'Ibáñez', '8084858', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988384859', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(487, 205, 'Jacinto', 'Jaimes', '8085868', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0988485869', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(488, 206, 'Librada', 'Leal', '8086878', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0988586879', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(489, 207, 'Melquíades', 'Mesa', '8187888', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0988687889', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(490, 208, 'Nicomedes', 'Nava', '8088898', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0988788899', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(491, 209, 'Onésimo', 'Olaya', '1389908', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988889909', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(492, 210, 'Primitiva', 'Pineda', '2990918', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988990919', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(493, 211, 'Quintiliano', 'Quiñones', '3091928', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989091929', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(494, 212, 'Remedios', 'Rendón', '7192938', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0989192939', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(495, 213, 'Saturnino', 'Salcedo', '2293948', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0989293949', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(496, 214, 'Tranquilina', 'Toro', '9594958', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989394959', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(497, 215, 'Ubaldo', 'Ulloa', '9495908', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0989495969', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(498, 216, 'Vicenta', 'Velasco', '9546978', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0989596979', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(499, 217, 'Wilibaldo', 'Wilches', '9397988', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989697989', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(500, 218, 'Ximena', 'Xalambrí', '9768998', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0989798999', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(501, 219, 'Yesid', 'Yepes', '9899058', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0989899009', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(502, 220, 'Zoila', 'Zamora', '9900318', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0989900019', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(503, 221, 'Apolinar', 'Ariza', '1001028', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980001029', 1, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(504, 222, 'Basilisa', 'Bernal', '1102038', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980102039', 2, 6, NULL, 1, '2025-06-29 18:06:28', '2025-06-29 18:06:28'),
(505, 223, 'Crescencio', 'Cruz', '0203048', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980203049', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(506, 224, 'Domitila', 'Díez', '0304058', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980304059', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(507, 225, 'Evaristo', 'Echeverri', '0405068', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0980405069', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(508, 226, 'Filomena', 'Flórez', '0506078', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0980506079', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(509, 227, 'Gaudencio', 'Gil', '0607088', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0980607089', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(510, 228, 'Herminia', 'Henao', '0708098', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0980708099', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(511, 229, 'Isidoro', 'Isaza', '0809108', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980809109', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(512, 230, 'Jacinta', 'Jiménez', '0910118', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980910119', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(513, 231, 'Lisandro', 'López', '1011128', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981011129', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(514, 232, 'Macaria', 'Martín', '1112138', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0981112139', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(515, 233, 'Nemesio', 'Naranjo', '1213148', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0981213149', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(516, 234, 'Otilia', 'Osorio', '1314158', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981314159', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(517, 235, 'Policarpo', 'Palacio', '1415168', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0981415169', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(518, 236, 'Querubina', 'Quintero', '1516178', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0981516179', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(519, 237, 'Rigoberto', 'Restrepo', '1617188', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981617189', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(520, 238, 'Sinforosa', 'Sánchez', '1718198', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0981718199', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(521, 239, 'Tiburcio', 'Tabares', '1819208', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0981819209', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(522, 240, 'Urbana', 'Upegui', '1920218', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981920219', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(523, 241, 'Valerio', 'Vargas', '2021228', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982021229', 1, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(524, 242, 'Zenobia', 'Zuluaga', '2122238', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982122239', 2, 7, NULL, 1, '2025-06-29 18:12:55', '2025-06-29 18:12:55'),
(585, 183, 'Napoleón', 'Núñez', '62636486', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986263649', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(586, 184, 'Obdulia', 'Ochoa', '63646586', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986364659', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(587, 185, 'Plácido', 'Prado', '6265666', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0986465669', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(588, 186, 'Quiteria', 'Quevedo', '6556678', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0986566679', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(589, 187, 'Remigio', 'Rivera', '6667658', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0986667689', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(590, 188, 'Serafina', 'Serrano', '6748698', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0986768699', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(591, 189, 'Teófilo', 'Trujillo', '6269708', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0986869709', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(592, 190, 'Úrsula', 'Uribe', '6970218', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0986970719', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(593, 191, 'Venancio', 'Villegas', '1071728', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987071729', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(594, 192, 'Walkiria', 'Wagner', '7572738', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0987172739', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(595, 193, 'Xerxes', 'Ximénez', '7253748', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0987273749', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(596, 194, 'Yolanda', 'Yépez', '7377758', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987374759', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(597, 195, 'Zenón', 'Zúñiga', '7475668', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0987475769', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(598, 196, 'Abundia', 'Arango', '7556778', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0987576779', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(599, 197, 'Bautista', 'Bedoya', '7277788', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987677789', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(600, 198, 'Casimira', 'Cano', '7774798', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0987778799', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(601, 199, 'Delfín', 'Daza', '7879828', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0987879809', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(602, 200, 'Escolástica', 'Escobar', '1222818', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987980819', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(603, 201, 'Fulgencio', 'Fajardo', '1081828', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988081829', 1, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(604, 202, 'Gumersinda', 'Galeano', '2182838', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988182839', 2, 5, NULL, 1, '2025-06-29 18:16:25', '2025-06-29 18:16:25'),
(625, 123, 'Miguel', 'Torres', '1203048', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980203049', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(626, 124, 'Rosa', 'Navarro', '9304058', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980304059', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(627, 125, 'Alejandro', 'Campos', '9405068', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0980405069', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(628, 126, 'Isabel', 'Rojas', '9506078', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0980506079', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(629, 127, 'Francisco', 'Delgado', '9607088', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0980607089', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(630, 128, 'Gloria', 'Paredes', '9708098', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0980708099', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(631, 129, 'Héctor', 'Salinas', '9809108', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0980809109', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(632, 130, 'Adriana', 'Mendez', '9910118', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0980910119', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(633, 131, 'Óscar', 'Fuentes', '9011128', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981011129', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(634, 132, 'Beatriz', 'Córdoba', '9112138', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0981112139', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(635, 133, 'Iván', 'Espinoza', '9213148', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0981213149', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(636, 134, 'Silvia', 'Restrepo', '9314158', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981314159', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(637, 135, 'Nicolás', 'Varela', '9415168', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0981415169', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(638, 136, 'Pilar', 'Montoya', '9516178', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0981516179', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(639, 137, 'Emilio', 'Cárdenas', '9617188', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981617189', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(640, 138, 'Nora', 'Bermúdez', '9718198', 'FEMENINO', 12, NULL, 'Suboficial Primero', NULL, '0981718199', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(641, 139, 'Tomás', 'Villareal', '9819208', 'MASCULINO', 13, NULL, 'Suboficial Segundo', NULL, '0981819209', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(642, 140, 'Amparo', 'Quintana', '9920218', 'FEMENINO', 14, NULL, 'Suboficial Ayudante', NULL, '0981920219', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(643, 141, 'Rodrigo', 'Pacheco', '9021228', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0982021229', 1, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29'),
(644, 142, 'Esperanza', 'Lozano', '9122238', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0982122239', 2, 2, NULL, 1, '2025-06-29 18:18:29', '2025-06-29 18:18:29');

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
  `id` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
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
  `id` int NOT NULL,
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_servicio` date NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `orden_del_dia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `jefe_servicio_id` int DEFAULT NULL,
  `estado` enum('PROGRAMADO','EN_CURSO','COMPLETADO','CANCELADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'PROGRAMADO',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_ausencias`
--

CREATE TABLE `tipos_ausencias` (
  `id` int NOT NULL,
  `nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
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
  `id` int NOT NULL,
  `nombre_usuario` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `contraseña` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nombre_completo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `rol` enum('ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'ADMIN',
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
`apellido` varchar(100)
,`cargo` varchar(150)
,`cin` varchar(20)
,`disponibilidad` varchar(13)
,`especialidad` varchar(150)
,`genero` enum('MASCULINO','FEMENINO')
,`grado` varchar(100)
,`id` int
,`legajo` int
,`lugar_guardia` varchar(100)
,`nivel_jerarquia` int
,`nombre` varchar(100)
,`observaciones` varchar(500)
,`region` varchar(50)
,`telefono` varchar(20)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_lista_guardias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_lista_guardias` (
`apellido` varchar(100)
,`cin` varchar(20)
,`disponibilidad` varchar(13)
,`genero` enum('MASCULINO','FEMENINO')
,`grado` varchar(100)
,`legajo` int
,`lugar_guardia` varchar(100)
,`nivel_jerarquia` int
,`nombre` varchar(100)
,`policia_id` int
,`posicion` int
,`ultima_guardia_fecha` date
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
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ausencias`
--
ALTER TABLE `ausencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `grados`
--
ALTER TABLE `grados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2189;

--
-- AUTO_INCREMENT de la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3613;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=645;

--
-- AUTO_INCREMENT de la tabla `regiones`
--
ALTER TABLE `regiones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_disponibilidad_policias`
--
DROP TABLE IF EXISTS `vista_disponibilidad_policias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_disponibilidad_policias`  AS SELECT `p`.`id` AS `id`, `p`.`legajo` AS `legajo`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `p`.`genero` AS `genero`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `e`.`nombre` AS `especialidad`, `p`.`cargo` AS `cargo`, `p`.`telefono` AS `telefono`, `lg`.`nombre` AS `lugar_guardia`, `r`.`nombre` AS `region`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad`, `p`.`observaciones` AS `observaciones` FROM ((((`policias` `p` left join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `especialidades` `e` on((`p`.`especialidad_id` = `e`.`id`))) left join `lugares_guardias` `lg` on((`p`.`lugar_guardia_id` = `lg`.`id`))) left join `regiones` `r` on((`p`.`region_id` = `r`.`id`))) WHERE (`p`.`activo` = true) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_lista_guardias`
--
DROP TABLE IF EXISTS `vista_lista_guardias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_lista_guardias`  AS SELECT `lg`.`posicion` AS `posicion`, `p`.`id` AS `policia_id`, `p`.`legajo` AS `legajo`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `p`.`genero` AS `genero`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `lguar`.`nombre` AS `lugar_guardia`, `lg`.`ultima_guardia_fecha` AS `ultima_guardia_fecha`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad` FROM (((`lista_guardias` `lg` join `policias` `p` on((`lg`.`policia_id` = `p`.`id`))) join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `lugares_guardias` `lguar` on((`p`.`lugar_guardia_id` = `lguar`.`id`))) WHERE (`p`.`activo` = true) ORDER BY `lg`.`posicion` ASC ;

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
