-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 29-06-2025 a las 15:31:02
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
  `estado` enum('PENDIENTE','APROBADA','RECHAZADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'PENDIENTE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `ausencias`
--

INSERT INTO `ausencias` (`id`, `policia_id`, `tipo_ausencia_id`, `fecha_inicio`, `fecha_fin`, `descripcion`, `justificacion`, `documento_adjunto`, `aprobado_por`, `estado`, `created_at`, `updated_at`) VALUES
(5, 41, 5, '2025-06-28', '2025-07-12', 'Capacitacion', '', NULL, 1, 'APROBADA', '2025-06-28 17:33:59', '2025-06-28 17:43:22');

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
(1920, 1, 1, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1921, 2, 2, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1922, 3, 3, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1923, 4, 4, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1924, 5, 5, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1925, 6, 6, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1926, 7, 7, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1927, 8, 8, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1928, 9, 9, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1929, 10, 10, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1930, 11, 11, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1931, 12, 12, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1932, 13, 13, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1933, 20, 14, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1934, 24, 15, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1935, 28, 16, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1936, 32, 17, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1937, 36, 18, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1938, 40, 19, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1939, 44, 20, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1940, 48, 21, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1941, 52, 22, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1942, 56, 23, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1943, 60, 24, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1944, 64, 25, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1945, 68, 26, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1946, 72, 27, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1947, 76, 28, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1948, 80, 29, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1949, 84, 30, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1950, 88, 31, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1951, 92, 32, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1952, 96, 33, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1953, 100, 34, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1954, 14, 35, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1955, 15, 36, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1956, 21, 37, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1957, 25, 38, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1958, 29, 39, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1959, 33, 40, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1960, 37, 41, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1961, 41, 42, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1962, 45, 43, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1963, 49, 44, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1964, 53, 45, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1965, 57, 46, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1966, 61, 47, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1967, 65, 48, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1968, 69, 49, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1969, 73, 50, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1970, 77, 51, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1971, 81, 52, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1972, 85, 53, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1973, 89, 54, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1974, 93, 55, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1975, 97, 56, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1976, 101, 57, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1977, 16, 58, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1978, 17, 59, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1979, 22, 60, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1980, 26, 61, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1981, 30, 62, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1982, 34, 63, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1983, 38, 64, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1984, 42, 65, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1985, 46, 66, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1986, 50, 67, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1987, 54, 68, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1988, 58, 69, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1989, 62, 70, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1990, 66, 71, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1991, 70, 72, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1992, 74, 73, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1993, 78, 74, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1994, 82, 75, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1995, 86, 76, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1996, 90, 77, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1997, 94, 78, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1998, 98, 79, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(1999, 18, 80, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2000, 19, 81, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2001, 23, 82, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2002, 27, 83, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2003, 31, 84, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2004, 35, 85, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2005, 39, 86, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2006, 43, 87, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2007, 47, 88, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2008, 51, 89, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2009, 55, 90, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2010, 59, 91, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2011, 63, 92, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2012, 67, 93, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2013, 71, 94, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2014, 75, 95, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2015, 79, 96, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2016, 83, 97, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2017, 87, 98, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2018, 91, 99, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2019, 95, 100, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59'),
(2020, 99, 101, NULL, NULL, '2025-06-28 15:27:59', '2025-06-28 15:27:59');

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
(95, 95, 'Inés', 'Bustamante', '7576777', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987576778', 1, 3, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(96, 96, 'Joaquín', 'Araya', '7677787', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0987677788', 1, 4, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(97, 97, 'Katia', 'Zúñiga', '7778797', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0987778798', 1, 5, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(98, 98, 'Lautaro', 'Yáñez', '7878808', 'MASCULINO', 14, NULL, 'Suboficial Ayudante', NULL, '0987878809', 1, 6, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(99, 99, 'Mireya', 'Xicará', '7980818', 'FEMENINO', 15, NULL, 'Funcionaria', NULL, '0987980819', 1, 7, NULL, 1, '2025-06-25 18:32:48', '2025-06-25 18:32:48'),
(100, 101, 'Norberto', 'Wagner', '8081828', 'MASCULINO', 12, NULL, 'Suboficial Primero', NULL, '0988081829', 1, 1, NULL, 1, '2025-06-25 18:32:48', '2025-06-28 15:48:19'),
(101, 100, 'Ofelia', 'Valdés', '8182838', 'FEMENINO', 13, NULL, 'Suboficial Segundo', NULL, '0988182839', 1, 2, NULL, 1, '2025-06-25 18:32:48', '2025-06-28 15:48:19'),
(102, 102, 'Lionel', 'Messi', '2345333', 'MASCULINO', 1, 1, 'JEFE DE DEPARTAMENTO', '', '09725463554', 1, 2, '', 0, '2025-06-28 18:01:25', '2025-06-28 18:53:21');

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1332;

--
-- AUTO_INCREMENT de la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2022;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

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
