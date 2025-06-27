-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 25-06-2025 a las 15:50:18
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
CREATE DEFINER=`root`@`localhost` PROCEDURE `ActualizarAntiguedad` ()   BEGIN
    UPDATE policias 
    SET antiguedad_dias = DATEDIFF(CURDATE(), fecha_ingreso)
    WHERE fecha_ingreso IS NOT NULL AND activo = TRUE;
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

--
-- Volcado de datos para la tabla `ausencias`
--

INSERT INTO `ausencias` (`id`, `policia_id`, `tipo_ausencia_id`, `fecha_inicio`, `fecha_fin`, `descripcion`, `justificacion`, `documento_adjunto`, `aprobado_por`, `estado`, `created_at`, `updated_at`) VALUES
(2, 58, 6, '2025-06-20', '2025-07-24', 'gfhgfhg', '', NULL, 1, 'APROBADA', '2025-06-20 16:55:24', '2025-06-20 16:55:27'),
(3, 282, 7, '2025-06-24', '2025-07-01', 'Descanso automático post-guardia del 2025-06-23 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(4, 279, 7, '2025-06-25', '2025-07-02', 'Descanso automático post-guardia del 2025-06-24 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(5, 290, 7, '2025-06-26', '2025-07-03', 'Descanso automático post-guardia del 2025-06-25 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(6, 276, 7, '2025-06-27', '2025-07-04', 'Descanso automático post-guardia del 2025-06-26 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(7, 287, 7, '2025-06-28', '2025-07-05', 'Descanso automático post-guardia del 2025-06-27 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(8, 43, 7, '2025-06-29', '2025-07-06', 'Descanso automático post-guardia del 2025-06-28 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(9, 51, 7, '2025-06-30', '2025-07-07', 'Descanso automático post-guardia del 2025-06-29 en CONDUCTOR DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(10, 262, 7, '2025-06-24', '2025-07-01', 'Descanso automático post-guardia del 2025-06-23 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(11, 259, 7, '2025-06-25', '2025-07-02', 'Descanso automático post-guardia del 2025-06-24 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(12, 270, 7, '2025-06-26', '2025-07-03', 'Descanso automático post-guardia del 2025-06-25 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(13, 256, 7, '2025-06-27', '2025-07-04', 'Descanso automático post-guardia del 2025-06-26 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(14, 267, 7, '2025-06-28', '2025-07-05', 'Descanso automático post-guardia del 2025-06-27 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(15, 35, 7, '2025-06-29', '2025-07-06', 'Descanso automático post-guardia del 2025-06-28 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(16, 252, 7, '2025-06-30', '2025-07-07', 'Descanso automático post-guardia del 2025-06-29 en GRUPO DOMINGO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(17, 12, 7, '2025-06-24', '2025-07-02', 'Descanso automático post-guardia del 2025-06-23 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(18, 17, 7, '2025-06-25', '2025-07-03', 'Descanso automático post-guardia del 2025-06-24 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(19, 18, 7, '2025-06-26', '2025-07-04', 'Descanso automático post-guardia del 2025-06-25 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(20, 19, 7, '2025-06-27', '2025-07-05', 'Descanso automático post-guardia del 2025-06-26 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(21, 20, 7, '2025-06-28', '2025-07-06', 'Descanso automático post-guardia del 2025-06-27 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(22, 13, 7, '2025-06-29', '2025-07-07', 'Descanso automático post-guardia del 2025-06-28 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(23, 21, 7, '2025-06-30', '2025-07-08', 'Descanso automático post-guardia del 2025-06-29 en JEFE DE CUARTEL', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(24, 1, 7, '2025-06-24', '2025-07-04', 'Descanso automático post-guardia del 2025-06-23 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(25, 2, 7, '2025-06-25', '2025-07-05', 'Descanso automático post-guardia del 2025-06-24 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(26, 3, 7, '2025-06-26', '2025-07-06', 'Descanso automático post-guardia del 2025-06-25 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(27, 4, 7, '2025-06-27', '2025-07-07', 'Descanso automático post-guardia del 2025-06-26 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(28, 5, 7, '2025-06-28', '2025-07-08', 'Descanso automático post-guardia del 2025-06-27 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(29, 222, 7, '2025-06-29', '2025-07-09', 'Descanso automático post-guardia del 2025-06-28 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(30, 6, 7, '2025-06-30', '2025-07-10', 'Descanso automático post-guardia del 2025-06-29 en JEFE DE SERVICIO', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(31, 22, 7, '2025-06-24', '2025-06-29', 'Descanso automático post-guardia del 2025-06-23 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(32, 23, 7, '2025-06-25', '2025-06-30', 'Descanso automático post-guardia del 2025-06-24 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(33, 24, 7, '2025-06-26', '2025-07-01', 'Descanso automático post-guardia del 2025-06-25 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(34, 242, 7, '2025-06-27', '2025-07-02', 'Descanso automático post-guardia del 2025-06-26 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(35, 25, 7, '2025-06-28', '2025-07-03', 'Descanso automático post-guardia del 2025-06-27 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(36, 239, 7, '2025-06-29', '2025-07-04', 'Descanso automático post-guardia del 2025-06-28 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(37, 250, 7, '2025-06-30', '2025-07-05', 'Descanso automático post-guardia del 2025-06-29 en OFICIAL DE GUARDIA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:47', '2025-06-23 16:05:47'),
(38, 362, 7, '2025-06-24', '2025-07-01', 'Descanso automático post-guardia del 2025-06-23 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(39, 359, 7, '2025-06-25', '2025-07-02', 'Descanso automático post-guardia del 2025-06-24 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(40, 370, 7, '2025-06-26', '2025-07-03', 'Descanso automático post-guardia del 2025-06-25 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(41, 356, 7, '2025-06-27', '2025-07-04', 'Descanso automático post-guardia del 2025-06-26 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(42, 367, 7, '2025-06-28', '2025-07-05', 'Descanso automático post-guardia del 2025-06-27 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(43, 59, 7, '2025-06-29', '2025-07-06', 'Descanso automático post-guardia del 2025-06-28 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(44, 352, 7, '2025-06-30', '2025-07-07', 'Descanso automático post-guardia del 2025-06-29 en TELEFONISTA', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(45, 382, 7, '2025-06-24', '2025-07-01', 'Descanso automático post-guardia del 2025-06-23 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(46, 379, 7, '2025-06-25', '2025-07-02', 'Descanso automático post-guardia del 2025-06-24 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(47, 390, 7, '2025-06-26', '2025-07-03', 'Descanso automático post-guardia del 2025-06-25 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(48, 376, 7, '2025-06-27', '2025-07-04', 'Descanso automático post-guardia del 2025-06-26 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(49, 387, 7, '2025-06-28', '2025-07-05', 'Descanso automático post-guardia del 2025-06-27 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(50, 67, 7, '2025-06-29', '2025-07-06', 'Descanso automático post-guardia del 2025-06-28 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48'),
(51, 372, 7, '2025-06-30', '2025-07-07', 'Descanso automático post-guardia del 2025-06-29 en TIKETEROS', NULL, NULL, 2, 'APROBADA', '2025-06-23 16:05:48', '2025-06-23 16:05:48');

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
(1, 'Magister en Ciencias policiales', '', '2025-06-17 01:29:03', '2025-06-17 01:29:03'),
(2, 'Magister en Gestión y Asesoramiento Policial', 'Magister en Gestión y Asesoramiento Policial', '2025-06-17 01:29:14', '2025-06-17 01:29:25');

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
(1, 'Comisario Principal', 1, 'Crio Princ', '2025-06-17 01:33:23', '2025-06-18 16:47:44'),
(2, 'Comisario', 2, 'Com.', '2025-06-18 16:47:30', '2025-06-18 17:53:34'),
(3, 'Subcomisario', 3, 'SUBCOM.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(4, 'Oficial Inspector', 4, 'OF. INSP.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(5, 'Oficial Primero', 5, 'OF. 1°', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(6, 'Oficial Segundo', 6, 'OF. 2°', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(7, 'Oficial Ayudante', 7, 'OF. AYD.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(8, 'Suboficial Superior', 8, 'SUBOF. SUP.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(9, 'Suboficial Principal', 9, 'SUBOF. PPAL.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(10, 'Suboficial Mayor', 10, 'SUBOF. MY.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(11, 'Suboficial Inspector', 11, 'SUBOF. INSP.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(12, 'Suboficial Primero', 12, 'SUBOF. 1°', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(13, 'Suboficial Segundo', 13, 'SUBOF. 2°', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(14, 'Suboficial Ayudante', 14, 'SUBOF. AYD.', '2025-06-18 17:56:35', '2025-06-18 17:56:35'),
(15, 'Funcionario/a', 15, 'FUNC.', '2025-06-18 17:56:35', '2025-06-18 17:56:35');

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
-- Estructura de tabla para la tabla `guardias_semanales_generadas`
--

CREATE TABLE `guardias_semanales_generadas` (
  `id` int(11) NOT NULL,
  `fecha_inicio_semana` date NOT NULL,
  `fecha_fin_semana` date NOT NULL,
  `tipo_guardia` enum('DIARIA','SEMANAL') COLLATE utf8mb4_spanish2_ci NOT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `lista_guardias`
--

INSERT INTO `lista_guardias` (`id`, `policia_id`, `posicion`, `ultima_guardia_fecha`, `created_at`, `updated_at`) VALUES
(7037, 1, 1, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7038, 12, 2, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7039, 2, 3, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7040, 3, 4, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7041, 22, 5, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7042, 4, 6, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7043, 23, 7, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7044, 5, 8, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7045, 24, 9, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7046, 222, 10, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7047, 242, 11, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7048, 262, 12, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7049, 282, 13, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7050, 362, 14, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7051, 382, 15, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7052, 6, 16, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7053, 25, 17, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7054, 219, 18, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7055, 230, 19, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7056, 239, 20, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7057, 250, 21, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7058, 259, 22, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7059, 270, 23, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7060, 279, 24, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7061, 290, 25, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7062, 359, 26, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7063, 370, 27, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7064, 379, 28, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7065, 390, 29, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7066, 7, 30, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7067, 26, 31, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7068, 216, 32, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7069, 227, 33, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7070, 236, 34, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7071, 247, 35, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7072, 256, 36, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7073, 267, 37, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7074, 276, 38, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7075, 287, 39, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7076, 356, 40, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7077, 367, 41, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7078, 376, 42, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7079, 387, 43, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7080, 8, 44, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7081, 17, 45, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7082, 27, 46, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7083, 35, 47, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7084, 43, 48, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7085, 51, 49, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7086, 59, 50, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7087, 67, 51, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7088, 212, 52, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7089, 223, 53, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7090, 232, 54, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7091, 243, 55, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7092, 252, 56, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7093, 263, 57, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7094, 272, 58, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7095, 283, 59, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7096, 352, 60, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7097, 363, 61, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7098, 372, 62, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7099, 383, 63, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7100, 9, 64, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7101, 18, 65, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7102, 28, 66, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7103, 36, 67, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7104, 44, 68, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7105, 52, 69, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7106, 60, 70, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7107, 68, 71, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7108, 214, 72, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7109, 225, 73, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7110, 234, 74, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7111, 245, 75, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7112, 254, 76, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7113, 265, 77, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7114, 274, 78, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7115, 285, 79, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7116, 354, 80, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7117, 365, 81, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7118, 374, 82, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7119, 385, 83, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7120, 10, 84, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7121, 19, 85, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7122, 29, 86, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7123, 37, 87, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7124, 45, 88, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7125, 53, 89, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7126, 61, 90, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7127, 69, 91, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7128, 220, 92, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7129, 231, 93, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7130, 240, 94, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7131, 251, 95, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7132, 260, 96, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7133, 271, 97, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7134, 280, 98, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7135, 291, 99, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7136, 360, 100, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7137, 371, 101, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7138, 380, 102, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7139, 391, 103, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7140, 11, 104, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7141, 20, 105, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7142, 30, 106, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7143, 38, 107, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7144, 46, 108, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7145, 54, 109, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7146, 62, 110, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7147, 70, 111, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7148, 217, 112, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7149, 228, 113, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7150, 237, 114, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7151, 248, 115, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7152, 257, 116, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7153, 268, 117, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7154, 277, 118, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7155, 288, 119, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7156, 357, 120, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7157, 368, 121, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7158, 377, 122, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7159, 388, 123, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7160, 13, 124, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7161, 21, 125, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7162, 31, 126, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7163, 39, 127, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7164, 47, 128, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7165, 55, 129, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7166, 63, 130, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7167, 71, 131, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7168, 213, 132, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7169, 224, 133, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7170, 233, 134, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7171, 244, 135, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7172, 253, 136, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7173, 264, 137, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7174, 273, 138, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7175, 284, 139, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7176, 353, 140, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7177, 364, 141, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7178, 373, 142, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7179, 384, 143, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7180, 14, 144, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7181, 32, 145, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7182, 40, 146, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7183, 48, 147, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7184, 56, 148, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7185, 64, 149, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7186, 218, 150, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7187, 229, 151, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7188, 238, 152, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7189, 249, 153, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7190, 258, 154, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7191, 269, 155, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7192, 278, 156, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7193, 289, 157, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7194, 358, 158, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7195, 369, 159, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7196, 378, 160, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7197, 389, 161, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7198, 15, 162, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7199, 33, 163, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7200, 41, 164, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7201, 49, 165, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7202, 57, 166, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7203, 65, 167, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7204, 215, 168, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7205, 226, 169, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7206, 241, 170, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7207, 261, 171, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7208, 281, 172, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7209, 361, 173, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7210, 381, 174, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7211, 16, 175, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7212, 34, 176, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7213, 42, 177, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7214, 50, 178, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7215, 58, 179, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7216, 66, 180, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7217, 221, 181, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7218, 235, 182, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7219, 246, 183, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7220, 255, 184, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7221, 266, 185, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7222, 275, 186, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7223, 286, 187, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7224, 355, 188, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7225, 366, 189, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7226, 375, 190, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07'),
(7227, 386, 191, NULL, '2025-06-25 14:21:07', '2025-06-25 14:21:07');

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
(1, 'JEFE DE SERVICIO', '', '', 'Luque', 1, '2025-06-17 01:44:27', '2025-06-18 16:43:52'),
(2, 'JEFE DE CUARTEL', '', '', 'Luque', 1, '2025-06-18 16:44:20', '2025-06-18 16:44:20'),
(3, 'OFICIAL DE GUARDIA', '', '', 'Luque', 1, '2025-06-18 16:44:39', '2025-06-18 16:44:39'),
(4, 'GRUPO DOMINGO', '', '', 'Luque', 1, '2025-06-18 16:45:07', '2025-06-18 16:45:07'),
(5, 'CONDUCTOR DE GUARDIA', '', '', 'Luque', 1, '2025-06-18 16:45:25', '2025-06-18 16:45:25'),
(6, 'TELEFONISTA', '', '', 'Luque', 1, '2025-06-18 16:45:38', '2025-06-18 16:45:38'),
(7, 'TIKETEROS', '', '', 'Luque', 1, '2025-06-18 16:45:52', '2025-06-18 16:45:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `policias`
--

CREATE TABLE `policias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `apellido` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `cin` varchar(20) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `grado_id` int(11) NOT NULL,
  `especialidad_id` int(11) DEFAULT NULL,
  `cargo` varchar(150) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comisionamiento` varchar(200) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `region` enum('CENTRAL','REGIONAL') COLLATE utf8mb4_spanish2_ci DEFAULT 'CENTRAL',
  `lugar_guardia_id` int(11) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `antiguedad_dias` int(11) DEFAULT '0',
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `policias`
--

INSERT INTO `policias` (`id`, `nombre`, `apellido`, `cin`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region`, `lugar_guardia_id`, `fecha_ingreso`, `antiguedad_dias`, `observaciones`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'Julio Alberto', 'Adorno Mujica', '1568485', 1, 1, 'JEFE DE DEPARTAMENTO', '0', '0974678901', 'CENTRAL', 1, '1997-01-18', 10378, '', 1, '2025-06-18 13:10:01', '2025-06-18 13:15:06'),
(2, 'Carlos Alberto', 'González Martínez', '2456789', 2, 1, 'JEFE DE GABINETE', 'Departamento Central', '0981234567', 'CENTRAL', 1, '2000-03-15', 0, 'Especialista en gestión policial', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(3, 'María Elena', 'Rodríguez Silva', '3567890', 3, 2, 'TESORERO-HABILITADO PAGADOR', 'División Administrativa', '0985678901', 'CENTRAL', 1, '2005-07-20', 0, 'Certificada en administración financiera', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(4, 'Roberto José', 'Fernández López', '4678911', 4, 1, 'JEFE DE LA DIVISION DE LEGALES', 'División Legal', '0976543210', 'CENTRAL', 1, '2010-01-10', 0, 'Abogado especializado', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(5, 'Ana Lucía', 'Benítez Torres', '5789012', 5, NULL, 'JEFE DE LA DIVISION MOVILES Y REGIONALES', 'División Operativa', '0987654321', 'CENTRAL', 1, '2012-09-05', 0, 'Coordinadora regional', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(6, 'Pedro Miguel', 'Acosta Ramírez', '6890123', 6, 2, 'JEFE DE LA OFICINA REGIONAL DE MARIA AUXIALIDORA', 'Regional María Auxiliadora', '0991234567', 'REGIONAL', 1, '2015-04-12', 0, 'Experiencia en atención al público', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(7, 'Carmen Rosa', 'Villalba Núñez', '7901234', 7, NULL, 'JEFE DE LA DIVISION DE VENTANILLA UNICA', 'División Ventanilla', '0984567890', 'CENTRAL', 1, '2018-11-08', 0, 'Especialista en procesos', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(8, 'Miguel Ángel', 'Ortega Cabrera', '8012345', 8, 1, 'JEFE DE LA DIVISION DE AUTORIZACION', 'División Autorización', '0977890123', 'CENTRAL', 1, '2006-06-30', 0, 'Supervisor de autorizaciones', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(9, 'Silvia Patricia', 'Morales Díaz', '9123456', 9, 2, 'JEFA DE LA DIVISIÓN DE DACTILOSCOPIA', 'División Dactiloscopia', '0988901234', 'CENTRAL', 1, '2008-02-14', 0, 'Experta en identificación', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(10, 'Luis Fernando', 'Cáceres Ayala', '1234567', 10, NULL, 'JEFE DE DATA CENTER - INFORMATICA', 'Data Center', '0995678012', 'CENTRAL', 1, '2016-10-25', 0, 'Ingeniero en sistemas', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(11, 'Gloria Beatriz', 'Sandoval Peña', '2345678', 11, 1, 'JEFA DE LA DIVISIÓN DE RECURSOS HUMANOS', 'División RRHH', '0982345678', 'CENTRAL', 1, '2013-08-18', 0, 'Licenciada en RRHH', 1, '2025-06-19 01:08:32', '2025-06-19 01:08:32'),
(12, 'Ramón Esteban', 'Paredes Gutierrez', '3456789', 1, 1, 'JEFE DE DEPARTAMENTO', 'Cuartel Central', '0986789012', 'CENTRAL', 2, '1995-05-22', 0, 'Comisario con amplia experiencia', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(13, 'Miriam Esther', 'Figueredo Sosa', '4567890', 12, NULL, 'SUBJEFA DE LA DIVISION DE CEDULACION A EXTRANJEROS', 'División Extranjeros', '0973456789', 'CENTRAL', 2, '2007-12-03', 0, 'Especialista en migración', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(14, 'Osvaldo Daniel', 'Ramos Vera', '5678901', 13, 2, 'SUBJEFE DE LA DIVISIÓN MOVILES Y REGIONALES', 'División Regional', '0989012345', 'REGIONAL', 2, '2011-03-28', 0, 'Coordinador de móviles', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(15, 'Teresa Isabel', 'Mendoza Flores', '6789012', 14, 1, 'JEFE DE LA SECCIÓN FISCALIZACIÓN', 'Sección Fiscalización', '0994567890', 'CENTRAL', 2, '2014-07-15', 0, 'Fiscalizadora certificada', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(16, 'Héctor Raúl', 'Giménez Castro', '7890123', 15, NULL, 'JEFE DE LA OFICINA REGIONAL DE ITÁ', 'Regional Itá', '0987123456', 'REGIONAL', 2, '2019-01-20', 0, 'Funcionario especializado', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(17, 'Lourdes María', 'Espínola Rojas', '8901234', 8, 2, 'SUBJEFA DE LA DIVISION DE PASAPORTE', 'División Pasaportes', '0978234567', 'CENTRAL', 2, '2009-09-10', 0, 'Experta en documentación', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(18, 'Ariel Sebastián', 'Coronel Vargas', '9012345', 9, 1, 'SUBJEFE D E LA OFICINA REGIONAL DE CAACUPÉ', 'Regional Caacupé', '0992345678', 'REGIONAL', 2, '2017-04-05', 0, 'Administrador regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(19, 'Nilda Concepción', 'Duarte Medina', '1345678', 10, NULL, 'JEFA DE LA SECCIÓN ENTREGA DE CEDULAS ELECTRÓNICAS (CDE)', 'Sección CDE', '0985456789', 'CENTRAL', 2, '2020-11-12', 0, 'Técnica en sistemas', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(20, 'Julio César', 'Riveros Ojeda', '2456780', 11, 2, 'JEFA DE SANIDAD (ODONTOLOGIA)', 'Sanidad', '0980567890', 'CENTRAL', 2, '2004-06-08', 0, 'Odontólogo policial', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(21, 'Rosa Elena', 'Báez Montiel', '3567801', 12, 1, 'JEFA DE L CENTRO DE PRODUCCIONES DE DOCUMENTOS ELECTRONICOS', 'Centro Producción', '0996678901', 'CENTRAL', 2, '2016-02-29', 0, 'Supervisora de producción', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(22, 'Fernando José', 'Maldonado Quiroz', '4678902', 3, NULL, 'SUBJEFA DE LA SECCION DE CONSULADO', 'Sección Consulado', '0971789012', 'CENTRAL', 3, '2003-10-17', 0, 'Especialista consular', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(23, 'Claudia Marlene', 'Lezcano Benítez', '5789013', 4, 1, 'JEFE DE LA SECCION DATA CENTER', 'Data Center', '0988890123', 'CENTRAL', 3, '2012-05-23', 0, 'Analista de sistemas', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(24, 'Gustavo Adolfo', 'Sánchez Peralta', '6890124', 5, 2, 'SUBJEFA DIVISION DE VENTANILLA UNICA', 'Ventanilla Única', '0983901234', 'CENTRAL', 3, '2018-12-01', 0, 'Supervisor de atención', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(25, 'Patricia Alejandra', 'Cardozo Jara', '7901235', 6, NULL, 'SUBJEFA DE LA DIVISIÓN DE DACTILOSCOPIA', 'Dactiloscopia', '0975012345', 'CENTRAL', 3, '2021-03-14', 0, 'Técnica en identificación', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(26, 'Raúl Enrique', 'Fleitas Romero', '8012346', 7, 1, 'SUBJEFA DE LA DIVISION FABRICA', 'División Fábrica', '0990123456', 'CENTRAL', 3, '2007-08-07', 0, 'Supervisor de producción', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(27, 'Mónica Graciela', 'Ayala Fretes', '9123457', 8, 2, 'JEFE DE TRASPORTE', 'División Transporte', '0986234567', 'CENTRAL', 3, '2015-01-19', 0, 'Coordinadora de flota', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(28, 'Diego Armando', 'Recalde Insfrán', '1234568', 9, NULL, 'SUBJEFA DE LA DIVISION DE LEGALES', 'División Legal', '0979345678', 'CENTRAL', 3, '2010-07-11', 0, 'Asistente legal', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(29, 'Estela Noemi', 'Torres Aguilar', '2345679', 10, 1, 'DIVISION DE RECLAMOS', 'División Reclamos', '0993456789', 'CENTRAL', 3, '2019-09-26', 0, 'Gestora de reclamos', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(30, 'Marcelo Augusto', 'Villasanti Rolon', '3456790', 11, 2, 'JEFE DE LA SECCIÓN DE ESTADISTICA', 'Sección Estadística', '0987567890', 'CENTRAL', 3, '2013-11-04', 0, 'Estadístico policial', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(31, 'Yolanda Mercedes', 'Gavilán Silva', '4567801', 12, NULL, 'JEFE DE LA SECCIÓN ENTREGA DE CEDULA (SNIC)', 'Sección SNIC', '0981678901', 'CENTRAL', 3, '2022-04-30', 0, 'Especialista en entrega', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(32, 'Blas Antonio', 'Cabañas Martínez', '5678912', 13, 1, 'AYUDANTIA DE LA JEFATURA', 'Jefatura', '0974789012', 'CENTRAL', 4, '2008-01-25', 0, 'Asistente administrativo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(33, 'Alba Cristina', 'Núñez Godoy', '6789023', 14, NULL, 'JEFA DE LA SECCION DE ESCANEO', 'Sección Escaneo', '0988901234', 'CENTRAL', 4, '2016-06-13', 0, 'Técnica en digitalización', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(34, 'Esteban Rafael', 'Paniagua Cáceres', '7890134', 15, 2, 'SUBJEFA ENTREGA DE PASAPORTE ELECTRONICO (CDE)', 'Entrega Pasaportes', '0985012345', 'CENTRAL', 4, '2020-02-08', 0, 'Funcionario especializado', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(35, 'Liliana Mabel', 'Ojeda Cantero', '8901245', 8, 1, 'SUBJEFA DE LA DIVISION DE MOVILES Y REGIONALES', 'Móviles y Regionales', '0976123456', 'REGIONAL', 4, '2005-12-20', 0, 'Coordinadora operativa', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(36, 'Víctor Hugo', 'Salinas Escobar', '9012356', 9, NULL, 'SUBJEFA DEL CENTRO DE PRODUCCION DE DOCUMENTOS', 'Centro Producción', '0991234567', 'CENTRAL', 4, '2017-10-15', 0, 'Supervisor técnico', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(37, 'Norma Beatriz', 'Velázquez Ibarra', '1234569', 10, 2, 'SUBJEFA DE LA DIVISION DE ARCHIVO', 'División Archivo', '0987345678', 'CENTRAL', 4, '2011-04-02', 0, 'Archivista especializada', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(38, 'Cristian David', 'Romero Delgado', '2345670', 11, 1, 'JEFA DE LA SECCIÓN ENTREGA DE ENTREA DE CEDULA SNIC', 'Entrega SNIC', '0982456789', 'CENTRAL', 4, '2023-07-18', 0, 'Técnico en entrega', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(39, 'Zunilda Carmen', 'Bogado Nuñez', '3456781', 12, NULL, 'JEFE DE LA OFICINA REGIONAL DE VILLARRICA', 'Regional Villarrica', '0978567890', 'REGIONAL', 4, '2014-09-09', 0, 'Jefe regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(40, 'Ignacio Rubén', 'Pereira Campos', '4567892', 13, 2, 'JEFE DE LA OFICINA REGIONAL DE CIUDAD DEL ESTE', 'Regional CDE', '0994678901', 'REGIONAL', 4, '2006-03-27', 0, 'Administrador regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(41, 'Celina Rosario', 'Mereles Ocampos', '5678903', 14, 1, 'JEFA DE LA REGIONAL DE SAN LORENZO', 'Regional San Lorenzo', '0989789012', 'REGIONAL', 4, '2018-05-14', 0, 'Coordinadora regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(42, 'Óscar Ramón', 'Bobadilla Espinoza', '6789014', 15, NULL, 'JEFA DE LA OFICINA REGIONAL DE PRESIDENTE FRANCO', 'Regional Pte. Franco', '0973890123', 'REGIONAL', 5, '2021-01-12', 0, 'Funcionario regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(43, 'Griselda Yolanda', 'Amarilla Domínguez', '7890125', 8, 2, 'TESORERIA', 'División Tesorería', '0988012345', 'CENTRAL', 5, '2009-08-30', 0, 'Tesorera especializada', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(44, 'Jorge Luis', 'Caballero Pintos', '8901236', 9, 1, 'JEFE DE LA REGIONAL DE JUAN E O\'LEARY', 'Regional Juan E. O\'Leary', '0984123456', 'REGIONAL', 5, '2015-12-05', 0, 'Jefe de operaciones', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(45, 'Sandra Elizabeth', 'Morinigo Gauto', '9012347', 10, NULL, 'JEFA DE LA REGIONAL DE SAN ALBERTO', 'Regional San Alberto', '0975234567', 'REGIONAL', 5, '2019-04-22', 0, 'Administradora regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(46, 'Elías Rodrigo', 'Benítez Cañete', '1234570', 11, 2, 'JEFE DE LA OFICINA REGIONAL DE PEDRO JUAN CABALLERO', 'Regional Pedro Juan Caballero', '0990345678', 'REGIONAL', 5, '2012-11-16', 0, 'Supervisor fronterizo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(47, 'Mirta Concepción', 'Duré González', '2345681', 12, 1, 'JEFE DE LA REGIONAL DE SANTA RITA', 'Regional Santa Rita', '0987456789', 'REGIONAL', 5, '2007-02-28', 0, 'Coordinadora zonal', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(48, 'Fabián Nicolás', 'Acuña Rojas', '3456792', 13, NULL, 'JEFE DE LA OFICINA REGIONAL DE REGISTRO CIVIL', 'Registro Civil', '0981567890', 'CENTRAL', 5, '2022-09-10', 0, 'Especialista en registro', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(49, 'Leticia Soledad', 'Valdez Ortiz', '4567803', 14, 2, 'JEFE DE LA REGIONAL DE CAAGUAZÚ', 'Regional Caaguazú', '0976678901', 'REGIONAL', 5, '2016-07-03', 0, 'Jefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(50, 'Néstor Gabriel', 'Franco Gamarra', '5678914', 15, 1, 'JEFA DE LA REGIONAL DE CAACUPE', 'Regional Caacupé', '0992789012', 'REGIONAL', 5, '2010-05-21', 0, 'Funcionario religioso', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(51, 'Elvira Francisca', 'Sosa Martínez', '6789025', 8, NULL, 'SUBJEFE DE LA REGIONAL DE SAN LORENZO', 'Regional San Lorenzo', '0988890123', 'REGIONAL', 5, '2024-01-07', 0, 'Subjefe operativo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(52, 'Derlis Maximiliano', 'Portillo Vera', '7890136', 9, 2, 'SUBJEFE DE LA OFICINA REGIONAL DE CIUDAD DEL ESTE', 'Regional CDE', '0985901234', 'REGIONAL', 6, '2013-03-19', 0, 'Subjefe fronterizo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(53, 'Gladys Petrona', 'Riquelme López', '8901247', 10, 1, 'JEFE DE LA REGIONAL DR. JUAN EULOGIO ESTIGARRIBIA', 'Regional Dr. Estigarribia', '0979012345', 'REGIONAL', 6, '2018-10-24', 0, 'Jefe de puesto', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(54, 'Alcides Bautista', 'Chamorro Silva', '9012358', 11, NULL, 'JEFE DE LA REGIONAL DE HERNANDADRIAS', 'Regional Hernandarias', '0993123456', 'REGIONAL', 6, '2005-01-15', 0, 'Coordinador hidroeléctrico', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(55, 'Dora Alicia', 'Laterza Rodríguez', '1234571', 12, 2, 'JEFE DE LA REGIONAL DE SALTOS DEL GUAIRA', 'Regional Saltos del Guairá', '0987234567', 'REGIONAL', 6, '2020-06-11', 0, 'Jefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(56, 'Wilson Enrique', 'Maciel Torres', '2345682', 13, 1, 'JEFA DE LA REGIONAL DE ITA', 'Regional Itá', '0982345678', 'REGIONAL', 6, '2011-09-28', 0, 'Administrador local', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(57, 'Zulma Raquel', 'Pistilli Benítez', '3456793', 14, NULL, 'A CARGO DE RRHH', 'División RRHH', '0978456789', 'CENTRAL', 6, '2017-12-13', 0, 'Encargada de personal', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(58, 'Rolando Ismael', 'Cabral Fernández', '4567804', 15, 2, 'TESORERIA', 'División Tesorería', '0995567890', 'CENTRAL', 6, '2023-04-06', 0, 'Funcionario financiero', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(59, 'Analía Griselda', 'Villanueva Morales', '5678915', 8, 1, 'SUBJEFE DE LA OFICINA REGIONAL DE PEDRO JUAN CABALLERO', 'Regional Pedro Juan Caballero', '0989678901', 'REGIONAL', 6, '2008-08-17', 0, 'Subjefe fronterizo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(60, 'Milciades Javier', 'Peralta Díaz', '6789026', 9, NULL, 'JEFE DE LA OFICINA REGIONAL DE ENCARNACION', 'Regional Encarnación', '0984789012', 'REGIONAL', 6, '2014-02-22', 0, 'Jefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(61, 'Emilia Beatriz', 'Cristaldo Cáceres', '7890137', 10, 2, 'JEFE DE LA REGIONAL DE CORONEL OVIEDO', 'Regional Coronel Oviedo', '0976890123', 'REGIONAL', 6, '2019-11-08', 0, 'Coordinadora regional', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(62, 'Atilio Ramiro', 'Quintana Ayala', '8901248', 11, 1, 'JEFA DE LA REGIONAL DE CARAPEGUA', 'Regional Carapeguá', '0991901234', 'REGIONAL', 7, '2006-05-14', 0, 'Jefe municipal', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(63, 'Silvana Lorena', 'Medina Rojas', '9012359', 12, NULL, 'JEFA DE LA REGIONAL DE PARAGUARI', 'Regional Paraguarí', '0987012345', 'REGIONAL', 7, '2021-07-29', 0, 'Coordinadora departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(64, 'Adrián Gustavo', 'Espinoza Vargas', '1234572', 13, 2, 'JEFE DE LA REGIONAL DE MINGA GUAZU', 'Regional Minga Guazú', '0983123456', 'REGIONAL', 7, '2012-12-01', 0, 'Administrador fronterizo', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(65, 'Marta Elena', 'Godoy Castro', '2345683', 14, 1, 'JEFA DE LA OFICINA DE CARAGUATAY', 'Oficina Caraguatay', '0978234567', 'REGIONAL', 7, '2016-04-18', 0, 'Jefe de oficina', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(66, 'Leonardo Abel', 'Escobar Mendoza', '3456794', 15, NULL, 'SUBJEFA DE LA REGIONAL DECORONEL OVIEDO', 'Regional Coronel Oviedo', '0994345678', 'REGIONAL', 7, '2024-02-12', 0, 'Funcionario subjefe', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(67, 'Cinthia Maribel', 'Ramírez Flores', '4567805', 8, 2, 'SUBJEFA DE LA REGIONAL DE CAAGUAZÚ', 'Regional Caaguazú', '0989456789', 'REGIONAL', 7, '2009-10-05', 0, 'Subjefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(68, 'Rubén Darío', 'Núñez Cabrera', '5678916', 9, 1, 'JEFE DE LA OFICINA REGIONAL DE CURUGUATY', 'Regional Curuguaty', '0985567890', 'REGIONAL', 7, '2015-01-27', 0, 'Jefe de frontera', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(69, 'Fátima Concepción', 'Jara Peña', '6789027', 10, NULL, 'JEFA DE LA REGIONAL DE SAN ESTANISLAO', 'Regional San Estanislao', '0980678901', 'REGIONAL', 7, '2018-08-14', 0, 'Jefe municipal', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(70, 'Teodoro Blas', 'Sandoval Ojeda', '7890138', 11, 2, 'JEFA DE LA REGIONAL DE YAGUARON', 'Regional Yaguarón', '0975789012', 'REGIONAL', 7, '2013-06-20', 0, 'Coordinador histórico', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(71, 'Celia Raquel', 'Aguilar Insfrán', '8901249', 12, 1, 'JEFA DE LA REGIONAL DE VILLARRICA', 'Regional Villarrica', '0990890123', 'REGIONAL', 7, '2007-03-03', 0, 'Jefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33'),
(212, 'Juan Carlos', 'Mendoza Pérez', '1000001', 8, 1, 'OFICIAL DE SERVICIO', 'Jefatura de Servicio', '0981000001', 'CENTRAL', 1, '2015-03-10', 0, 'Oficial especializado', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(213, 'María José', 'Ramírez Gómez', '1000002', 12, NULL, 'ASISTENTE DE JEFATURA', 'Jefatura de Servicio', '0981000002', 'CENTRAL', 1, '2018-07-15', 0, 'Asistente administrativo', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(214, 'Pedro Antonio', 'Silva Martínez', '1000003', 9, 2, 'COORDINADOR OPERATIVO', 'Jefatura de Servicio', '0981000003', 'CENTRAL', 1, '2012-11-20', 0, 'Coordinador de turno', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(215, 'Ana Lucía', 'Torres Benítez', '1000004', 14, 1, 'SECRETARIA EJECUTIVA', 'Jefatura de Servicio', '0981000004', 'CENTRAL', 1, '2020-02-28', 0, 'Secretaria especializada', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(216, 'Carlos Eduardo', 'Fernández López', '1000005', 7, NULL, 'SUPERVISOR DE GUARDIA', 'Jefatura de Servicio', '0981000005', 'CENTRAL', 1, '2016-09-12', 0, 'Supervisor nocturno', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(217, 'Rosa Elena', 'Cabrera Sosa', '1000006', 11, 2, 'JEFA DE TURNO', 'Jefatura de Servicio', '0981000006', 'CENTRAL', 1, '2014-05-08', 0, 'Jefa de turno diurno', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(218, 'Miguel Ángel', 'Rodríguez Castro', '1000007', 13, 1, 'OFICIAL DE ENLACE', 'Jefatura de Servicio', '0981000007', 'CENTRAL', 1, '2019-01-25', 0, 'Enlace institucional', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(219, 'Patricia Mónica', 'Villalba Núñez', '1000008', 6, NULL, 'COORDINADORA ADMINISTRATIVA', 'Jefatura de Servicio', '0981000008', 'CENTRAL', 1, '2017-06-30', 0, 'Coordinadora de área', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(220, 'Roberto José', 'Acosta Ramírez', '1000009', 10, 2, 'ANALISTA DE OPERACIONES', 'Jefatura de Servicio', '0981000009', 'CENTRAL', 1, '2013-10-14', 0, 'Analista especializado', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(221, 'Carmen Beatriz', 'Morales Díaz', '1000010', 15, 1, 'FUNCIONARIA DE APOYO', 'Jefatura de Servicio', '0981000010', 'CENTRAL', 1, '2021-04-18', 0, 'Apoyo administrativo', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(222, 'Luis Fernando', 'Espínola Rojas', '1000011', 5, NULL, 'OFICIAL SUPERIOR', 'Jefatura de Servicio', '0981000011', 'CENTRAL', 1, '2011-08-22', 0, 'Oficial de alto rango', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(223, 'Gloria Estela', 'Coronel Vargas', '1000012', 8, 2, 'SUPERVISORA DE PERSONAL', 'Jefatura de Servicio', '0981000012', 'CENTRAL', 1, '2016-12-05', 0, 'Supervisora de RRHH', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(224, 'Ariel Sebastián', 'Duarte Medina', '1000013', 12, 1, 'ASISTENTE OPERATIVO', 'Jefatura de Servicio', '0981000013', 'CENTRAL', 1, '2018-03-17', 0, 'Asistente de operaciones', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(225, 'Nilda Concepción', 'Riveros Ojeda', '1000014', 9, NULL, 'COORDINADORA DE TURNO', 'Jefatura de Servicio', '0981000014', 'CENTRAL', 1, '2015-07-09', 0, 'Coordinadora vespertina', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(226, 'Julio César', 'Báez Montiel', '1000015', 14, 2, 'SECRETARIO ADMINISTRATIVO', 'Jefatura de Servicio', '0981000015', 'CENTRAL', 1, '2020-11-23', 0, 'Secretario de área', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(227, 'Rosa María', 'Maldonado Quiroz', '1000016', 7, 1, 'OFICIAL DE GUARDIA', 'Jefatura de Servicio', '0981000016', 'CENTRAL', 1, '2017-02-14', 0, 'Oficial de turno', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(228, 'Fernando José', 'Lezcano Benítez', '1000017', 11, NULL, 'JEFE DE ÁREA', 'Jefatura de Servicio', '0981000017', 'CENTRAL', 1, '2014-09-28', 0, 'Jefe de área técnica', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(229, 'Claudia Marlene', 'Sánchez Peralta', '1000018', 13, 2, 'OFICIAL ESPECIALISTA', 'Jefatura de Servicio', '0981000018', 'CENTRAL', 1, '2019-05-11', 0, 'Especialista en procedimientos', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(230, 'Gustavo Adolfo', 'Cardozo Jara', '1000019', 6, 1, 'COORDINADOR DE SERVICIOS', 'Jefatura de Servicio', '0981000019', 'CENTRAL', 1, '2016-01-07', 0, 'Coordinador general', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(231, 'Patricia Alejandra', 'Fleitas Romero', '1000020', 10, NULL, 'ANALISTA SENIOR', 'Jefatura de Servicio', '0981000020', 'CENTRAL', 1, '2013-12-19', 0, 'Analista senior de operaciones', 1, '2025-06-20 16:07:01', '2025-06-20 16:07:01'),
(232, 'Ricardo Daniel', 'Fernández Silva', '3000001', 8, NULL, 'OFICIAL DE GUARDIA PRINCIPAL', 'Oficialía de Guardia', '0983000001', 'CENTRAL', 3, '2015-05-14', 0, 'Oficial principal de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(233, 'Carmen Liliana', 'Rodríguez Torres', '3000002', 12, 2, 'ASISTENTE DE GUARDIA', 'Oficialía de Guardia', '0983000002', 'CENTRAL', 3, '2018-09-28', 0, 'Asistente de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(234, 'Andrés Felipe', 'Benítez Cabrera', '3000003', 9, 1, 'COORDINADOR DE GUARDIA', 'Oficialía de Guardia', '0983000003', 'CENTRAL', 3, '2013-01-11', 0, 'Coordinador de turno de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(235, 'Miriam Estela', 'Sosa Villalba', '3000004', 15, NULL, 'FUNCIONARIA DE GUARDIA', 'Oficialía de Guardia', '0983000004', 'CENTRAL', 3, '2020-04-17', 0, 'Funcionaria de apoyo', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(236, 'Pablo Esteban', 'Castro Núñez', '3000005', 7, 2, 'OFICIAL DE TURNO', 'Oficialía de Guardia', '0983000005', 'CENTRAL', 3, '2016-11-23', 0, 'Oficial de turno diurno', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(237, 'Graciela Noemi', 'Ramírez Ortega', '3000006', 11, 1, 'JEFA DE GUARDIA NOCTURNA', 'Oficialía de Guardia', '0983000006', 'CENTRAL', 3, '2014-07-09', 0, 'Jefa de guardia nocturna', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(238, 'Héctor Raúl', 'Díaz Morales', '3000007', 13, NULL, 'OFICIAL DE CONTROL', 'Oficialía de Guardia', '0983000007', 'CENTRAL', 3, '2017-02-25', 0, 'Oficial de control de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(239, 'Silvia Patricia', 'Espínola Coronel', '3000008', 6, 2, 'COORDINADORA DE SERVICIOS', 'Oficialía de Guardia', '0983000008', 'CENTRAL', 3, '2015-12-12', 0, 'Coordinadora de servicios', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(240, 'Mario Augusto', 'Vargas Duarte', '3000009', 10, 1, 'ANALISTA DE GUARDIA', 'Oficialía de Guardia', '0983000009', 'CENTRAL', 3, '2013-10-30', 0, 'Analista de operaciones de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(241, 'Elena Rosa', 'Medina Riveros', '3000010', 14, NULL, 'SECRETARIA DE GUARDIA', 'Oficialía de Guardia', '0983000010', 'CENTRAL', 3, '2021-03-16', 0, 'Secretaria de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(242, 'Carlos Enrique', 'Ojeda Báez', '3000011', 5, 2, 'OFICIAL SUPERIOR DE GUARDIA', 'Oficialía de Guardia', '0983000011', 'CENTRAL', 3, '2011-06-22', 0, 'Oficial superior', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(243, 'Beatriz Alejandra', 'Montiel Maldonado', '3000012', 8, 1, 'SUPERVISORA DE GUARDIA', 'Oficialía de Guardia', '0983000012', 'CENTRAL', 3, '2018-01-08', 0, 'Supervisora de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(244, 'Roberto Carlos', 'Quiroz Lezcano', '3000013', 12, NULL, 'ASISTENTE NOCTURNO', 'Oficialía de Guardia', '0983000013', 'CENTRAL', 3, '2016-05-19', 0, 'Asistente de guardia nocturna', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(245, 'Lourdes María', 'Benítez Sánchez', '3000014', 9, 2, 'COORDINADORA VESPERTINA', 'Oficialía de Guardia', '0983000014', 'CENTRAL', 3, '2014-09-04', 0, 'Coordinadora de turno vespertino', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(246, 'Fernando Luis', 'Peralta Cardozo', '3000015', 15, 1, 'FUNCIONARIO DE APOYO', 'Oficialía de Guardia', '0983000015', 'CENTRAL', 3, '2020-12-01', 0, 'Funcionario de apoyo', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(247, 'Gloria Beatriz', 'Jara Fleitas', '3000016', 7, NULL, 'OFICIAL DE ENLACE', 'Oficialía de Guardia', '0983000016', 'CENTRAL', 3, '2017-04-27', 0, 'Oficial de enlace', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(248, 'Gustavo Ramón', 'Romero Ayala', '3000017', 11, 2, 'JEFE DE OPERACIONES', 'Oficialía de Guardia', '0983000017', 'CENTRAL', 3, '2015-08-13', 0, 'Jefe de operaciones de guardia', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(249, 'Patricia Elena', 'Fretes Recalde', '3000018', 13, 1, 'OFICIAL ESPECIALISTA', 'Oficialía de Guardia', '0983000018', 'CENTRAL', 3, '2019-02-18', 0, 'Oficial especialista', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(250, 'Diego Sebastián', 'Insfrán Torres', '3000019', 6, NULL, 'COORDINADOR DE ÁREA', 'Oficialía de Guardia', '0983000019', 'CENTRAL', 3, '2016-10-05', 0, 'Coordinador de área', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(251, 'Norma Concepción', 'Aguilar Villasanti', '3000020', 10, 2, 'ANALISTA SENIOR', 'Oficialía de Guardia', '0983000020', 'CENTRAL', 3, '2013-12-21', 0, 'Analista senior', 1, '2025-06-20 16:07:30', '2025-06-20 16:07:30'),
(252, 'Marcelo Raúl', 'Rolon Gavilán', '4000001', 8, 1, 'COORDINADOR GRUPO DOMINGO', 'Grupo Domingo', '0984000001', 'CENTRAL', 4, '2015-06-16', 0, 'Coordinador del grupo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(253, 'Yolanda Estela', 'Silva Cabañas', '4000002', 12, NULL, 'ASISTENTE GRUPO DOMINGO', 'Grupo Domingo', '0984000002', 'CENTRAL', 4, '2018-10-30', 0, 'Asistente administrativo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(254, 'Antonio Blas', 'Martínez Núñez', '4000003', 9, 2, 'SUPERVISOR DOMINGO', 'Grupo Domingo', '0984000003', 'CENTRAL', 4, '2013-02-13', 0, 'Supervisor de actividades dominicales', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(255, 'Cristina Alba', 'Godoy Paniagua', '4000004', 15, 1, 'FUNCIONARIA DOMINGO', 'Grupo Domingo', '0984000004', 'CENTRAL', 4, '2020-05-19', 0, 'Funcionaria de apoyo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(256, 'Rafael Esteban', 'Cáceres Ojeda', '4000005', 7, NULL, 'OFICIAL DOMINGO', 'Grupo Domingo', '0984000005', 'CENTRAL', 4, '2016-12-25', 0, 'Oficial de turno dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(257, 'Mabel Liliana', 'Cantero Salinas', '4000006', 11, 2, 'JEFA GRUPO DOMINGO', 'Grupo Domingo', '0984000006', 'CENTRAL', 4, '2014-08-11', 0, 'Jefa de grupo dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(258, 'Hugo Víctor', 'Escobar Velázquez', '4000007', 13, 1, 'OFICIAL DE CONTROL DOMINGO', 'Grupo Domingo', '0984000007', 'CENTRAL', 4, '2017-03-27', 0, 'Oficial de control dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(259, 'Beatriz Norma', 'Ibarra Romero', '4000008', 6, NULL, 'COORDINADORA DOMINGO', 'Grupo Domingo', '0984000008', 'CENTRAL', 4, '2015-01-14', 0, 'Coordinadora de servicios dominicales', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(260, 'David Cristian', 'Delgado Bogado', '4000009', 10, 2, 'ANALISTA DOMINGO', 'Grupo Domingo', '0984000009', 'CENTRAL', 4, '2013-11-01', 0, 'Analista de operaciones dominicales', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(261, 'Carmen Zunilda', 'Nuñez Pereira', '4000010', 14, 1, 'SECRETARIA DOMINGO', 'Grupo Domingo', '0984000010', 'CENTRAL', 4, '2021-04-18', 0, 'Secretaria del grupo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(262, 'Rubén Ignacio', 'Campos Mereles', '4000011', 5, NULL, 'OFICIAL SUPERIOR DOMINGO', 'Grupo Domingo', '0984000011', 'CENTRAL', 4, '2011-07-24', 0, 'Oficial superior del grupo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(263, 'Rosario Celina', 'Ocampos Bobadilla', '4000012', 8, 2, 'SUPERVISORA DOMINGO', 'Grupo Domingo', '0984000012', 'CENTRAL', 4, '2018-02-10', 0, 'Supervisora de grupo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(264, 'Ramón Óscar', 'Espinoza Amarilla', '4000013', 12, 1, 'ASISTENTE OPERATIVO DOMINGO', 'Grupo Domingo', '0984000013', 'CENTRAL', 4, '2016-06-21', 0, 'Asistente operativo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(265, 'Yolanda Griselda', 'Domínguez Caballero', '4000014', 9, NULL, 'COORDINADORA OPERATIVA DOMINGO', 'Grupo Domingo', '0984000014', 'CENTRAL', 4, '2014-10-06', 0, 'Coordinadora operativa', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(266, 'Luis Jorge', 'Pintos González', '4000015', 15, 2, 'FUNCIONARIO DOMINGO', 'Grupo Domingo', '0984000015', 'CENTRAL', 4, '2020-01-03', 0, 'Funcionario de apoyo dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(267, 'Beatriz Sandra', 'Vera López', '4000016', 7, 1, 'OFICIAL DE ENLACE DOMINGO', 'Grupo Domingo', '0984000016', 'CENTRAL', 4, '2017-05-29', 0, 'Oficial de enlace dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(268, 'Daniel Ricardo', 'Martínez Fernández', '4000017', 11, NULL, 'JEFE DE TURNO DOMINGO', 'Grupo Domingo', '0984000017', 'CENTRAL', 4, '2015-09-15', 0, 'Jefe de turno dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(269, 'Liliana Carmen', 'Silva Rodríguez', '4000018', 13, 2, 'OFICIAL ESPECIALISTA DOMINGO', 'Grupo Domingo', '0984000018', 'CENTRAL', 4, '2019-03-22', 0, 'Oficial especialista dominical', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(270, 'Felipe Andrés', 'Torres Benítez', '4000019', 6, 1, 'COORDINADOR DE SERVICIOS DOMINGO', 'Grupo Domingo', '0984000019', 'CENTRAL', 4, '2016-11-07', 0, 'Coordinador de servicios', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(271, 'Estela Miriam', 'Cabrera Sosa', '4000020', 10, NULL, 'ANALISTA SENIOR DOMINGO', 'Grupo Domingo', '0984000020', 'CENTRAL', 4, '2013-01-23', 0, 'Analista senior del grupo', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(272, 'Esteban Pablo', 'Castro Villalba', '5000001', 8, 2, 'CONDUCTOR PRINCIPAL', 'Conductores de Guardia', '0985000001', 'CENTRAL', 5, '2015-07-18', 0, 'Conductor principal de guardia', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(273, 'Noemi Graciela', 'Núñez Ramírez', '5000002', 12, 1, 'ASISTENTE DE CONDUCTORES', 'Conductores de Guardia', '0985000002', 'CENTRAL', 5, '2018-11-01', 0, 'Asistente de conductores', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(274, 'Raúl Héctor', 'Ortega Díaz', '5000003', 9, NULL, 'COORDINADOR DE CONDUCTORES', 'Conductores de Guardia', '0985000003', 'CENTRAL', 5, '2013-03-15', 0, 'Coordinador de conductores', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(275, 'Patricia Silvia', 'Morales Espínola', '5000004', 15, 2, 'FUNCIONARIA DE TRANSPORTE', 'Conductores de Guardia', '0985000004', 'CENTRAL', 5, '2020-06-21', 0, 'Funcionaria de transporte', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(276, 'Augusto Mario', 'Coronel Vargas', '5000005', 7, 1, 'OFICIAL CONDUCTOR', 'Conductores de Guardia', '0985000005', 'CENTRAL', 5, '2017-01-27', 0, 'Oficial conductor', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(277, 'Rosa Elena', 'Duarte Medina', '5000006', 11, NULL, 'JEFA DE CONDUCTORES', 'Conductores de Guardia', '0985000006', 'CENTRAL', 5, '2014-09-13', 0, 'Jefa de conductores', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(278, 'Enrique Carlos', 'Riveros Ojeda', '5000007', 13, 2, 'OFICIAL DE FLOTA', 'Conductores de Guardia', '0985000007', 'CENTRAL', 5, '2017-04-29', 0, 'Oficial de flota vehicular', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(279, 'Alejandra Beatriz', 'Báez Montiel', '5000008', 6, 1, 'COORDINADORA DE FLOTA', 'Conductores de Guardia', '0985000008', 'CENTRAL', 5, '2015-02-16', 0, 'Coordinadora de flota', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(280, 'Carlos Roberto', 'Montiel Maldonado', '5000009', 10, NULL, 'ANALISTA DE TRANSPORTE', 'Conductores de Guardia', '0985000009', 'CENTRAL', 5, '2013-12-03', 0, 'Analista de transporte', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(281, 'María Lourdes', 'Quiroz Lezcano', '5000010', 14, 2, 'SECRETARIA DE TRANSPORTE', 'Conductores de Guardia', '0985000010', 'CENTRAL', 5, '2021-05-20', 0, 'Secretaria de transporte', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(282, 'Luis Fernando', 'Benítez Sánchez', '5000011', 5, 1, 'OFICIAL SUPERIOR CONDUCTOR', 'Conductores de Guardia', '0985000011', 'CENTRAL', 5, '2011-08-26', 0, 'Oficial superior conductor', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(283, 'Beatriz Gloria', 'Peralta Cardozo', '5000012', 8, NULL, 'SUPERVISORA DE CONDUCTORES', 'Conductores de Guardia', '0985000012', 'CENTRAL', 5, '2018-03-12', 0, 'Supervisora de conductores', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(284, 'Ramón Gustavo', 'Jara Fleitas', '5000013', 12, 2, 'ASISTENTE DE FLOTA', 'Conductores de Guardia', '0985000013', 'CENTRAL', 5, '2016-07-23', 0, 'Asistente de flota', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(285, 'Elena Patricia', 'Romero Ayala', '5000014', 9, 1, 'COORDINADORA DE TURNOS', 'Conductores de Guardia', '0985000014', 'CENTRAL', 5, '2014-11-08', 0, 'Coordinadora de turnos', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(286, 'Sebastián Diego', 'Fretes Recalde', '5000015', 15, NULL, 'FUNCIONARIO CONDUCTOR', 'Conductores de Guardia', '0985000015', 'CENTRAL', 5, '2020-02-05', 0, 'Funcionario conductor', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(287, 'Concepción Norma', 'Insfrán Torres', '5000016', 7, 2, 'OFICIAL DE MANTENIMIENTO', 'Conductores de Guardia', '0985000016', 'CENTRAL', 5, '2017-06-01', 0, 'Oficial de mantenimiento vehicular', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(288, 'Raúl Marcelo', 'Aguilar Villasanti', '5000017', 11, 1, 'JEFE DE MANTENIMIENTO', 'Conductores de Guardia', '0985000017', 'CENTRAL', 5, '2015-10-17', 0, 'Jefe de mantenimiento', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(289, 'Estela Yolanda', 'Rolon Gavilán', '5000018', 13, NULL, 'OFICIAL DE CONTROL VEHICULAR', 'Conductores de Guardia', '0985000018', 'CENTRAL', 5, '2019-04-24', 0, 'Oficial de control vehicular', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(290, 'Blas Antonio', 'Silva Cabañas', '5000019', 6, 2, 'COORDINADOR DE SERVICIOS', 'Conductores de Guardia', '0985000019', 'CENTRAL', 5, '2016-12-09', 0, 'Coordinador de servicios', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(291, 'Alba Cristina', 'Martínez Núñez', '5000020', 10, 1, 'ANALISTA SENIOR TRANSPORTE', 'Conductores de Guardia', '0985000020', 'CENTRAL', 5, '2013-02-25', 0, 'Analista senior de transporte', 1, '2025-06-20 16:07:57', '2025-06-20 16:07:57'),
(352, 'Rafael Esteban', 'Godoy Paniagua', '6000001', 8, NULL, 'TELEFONISTA PRINCIPAL', 'Central Telefónica', '0986000001', 'CENTRAL', 6, '2015-08-20', 0, 'Telefonista principal', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(353, 'Mabel Liliana', 'Cáceres Ojeda', '6000002', 12, 2, 'ASISTENTE TELEFÓNICA', 'Central Telefónica', '0986000002', 'CENTRAL', 6, '2018-12-03', 0, 'Asistente de central telefónica', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(354, 'Hugo Víctor', 'Cantero Salinas', '6000003', 9, 1, 'COORDINADOR TELEFÓNICO', 'Central Telefónica', '0986000003', 'CENTRAL', 6, '2013-04-17', 0, 'Coordinador de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(355, 'Beatriz Norma', 'Escobar Velázquez', '6000004', 15, NULL, 'FUNCIONARIA TELEFÓNICA', 'Central Telefónica', '0986000004', 'CENTRAL', 6, '2020-07-23', 0, 'Funcionaria de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(356, 'David Cristian', 'Ibarra Romero', '6000005', 7, 2, 'OFICIAL COMUNICACIONES', 'Central Telefónica', '0986000005', 'CENTRAL', 6, '2017-02-28', 0, 'Oficial de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(357, 'Carmen Zunilda', 'Delgado Bogado', '6000006', 11, 1, 'JEFA DE COMUNICACIONES', 'Central Telefónica', '0986000006', 'CENTRAL', 6, '2014-10-15', 0, 'Jefa de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(358, 'Rubén Ignacio', 'Nuñez Pereira', '6000007', 13, NULL, 'OFICIAL TÉCNICO TELEFÓNICO', 'Central Telefónica', '0986000007', 'CENTRAL', 6, '2017-05-31', 0, 'Oficial técnico', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(359, 'Rosario Celina', 'Campos Mereles', '6000008', 6, 2, 'COORDINADORA TÉCNICA', 'Central Telefónica', '0986000008', 'CENTRAL', 6, '2015-03-18', 0, 'Coordinadora técnica', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(360, 'Ramón Óscar', 'Ocampos Bobadilla', '6000009', 10, 1, 'ANALISTA DE COMUNICACIONES', 'Central Telefónica', '0986000009', 'CENTRAL', 6, '2013-01-05', 0, 'Analista de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(361, 'Yolanda Griselda', 'Espinoza Amarilla', '6000010', 14, NULL, 'SECRETARIA TELEFÓNICA', 'Central Telefónica', '0986000010', 'CENTRAL', 6, '2021-06-22', 0, 'Secretaria de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(362, 'Luis Jorge', 'Domínguez Caballero', '6000011', 5, 2, 'OFICIAL SUPERIOR TELEFÓNICO', 'Central Telefónica', '0986000011', 'CENTRAL', 6, '2011-09-28', 0, 'Oficial superior de comunicaciones', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(363, 'Beatriz Sandra', 'Pintos González', '6000012', 8, 1, 'SUPERVISORA TELEFÓNICA', 'Central Telefónica', '0986000012', 'CENTRAL', 6, '2018-04-14', 0, 'Supervisora de central', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(364, 'Daniel Ricardo', 'Vera López', '6000013', 12, NULL, 'ASISTENTE DE TURNO', 'Central Telefónica', '0986000013', 'CENTRAL', 6, '2016-08-25', 0, 'Asistente de turno telefónico', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(365, 'Liliana Carmen', 'Martínez Fernández', '6000014', 9, 2, 'COORDINADORA DE TURNOS', 'Central Telefónica', '0986000014', 'CENTRAL', 6, '2014-12-10', 0, 'Coordinadora de turnos', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(366, 'Felipe Andrés', 'Silva Rodríguez', '6000015', 15, 1, 'FUNCIONARIO DE APOYO', 'Central Telefónica', '0986000015', 'CENTRAL', 6, '2020-03-07', 0, 'Funcionario de apoyo técnico', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(367, 'Estela Miriam', 'Torres Benítez', '6000016', 7, NULL, 'OFICIAL DE ENLACE TELEFÓNICO', 'Central Telefónica', '0986000016', 'CENTRAL', 6, '2017-07-03', 0, 'Oficial de enlace', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(368, 'Pablo Esteban', 'Cabrera Sosa', '6000017', 11, 2, 'JEFE DE TURNO TELEFÓNICO', 'Central Telefónica', '0986000017', 'CENTRAL', 6, '2015-11-19', 0, 'Jefe de turno', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(369, 'Noemi Graciela', 'Castro Villalba', '6000018', 13, 1, 'OFICIAL ESPECIALISTA TELEFÓNICO', 'Central Telefónica', '0986000018', 'CENTRAL', 6, '2019-05-26', 0, 'Oficial especialista', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(370, 'Raúl Héctor', 'Núñez Ramírez', '6000019', 6, NULL, 'COORDINADOR DE SERVICIOS', 'Central Telefónica', '0986000019', 'CENTRAL', 6, '2016-01-11', 0, 'Coordinador de servicios telefónicos', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(371, 'Patricia Silvia', 'Ortega Díaz', '6000020', 10, 2, 'ANALISTA SENIOR TELEFÓNICO', 'Central Telefónica', '0986000020', 'CENTRAL', 6, '2013-03-27', 0, 'Analista senior', 1, '2025-06-20 16:09:38', '2025-06-20 16:09:38'),
(372, 'Augusto Mario', 'Morales Espínola', '7000001', 8, 1, 'TIKETERO PRINCIPAL', 'Sección Tickets', '0987000001', 'CENTRAL', 7, '2015-09-22', 0, 'Tiketero principal', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(373, 'Rosa Elena', 'Coronel Vargas', '7000002', 12, NULL, 'ASISTENTE DE TICKETS', 'Sección Tickets', '0987000002', 'CENTRAL', 7, '2019-01-05', 0, 'Asistente de tickets', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(374, 'Enrique Carlos', 'Duarte Medina', '7000003', 9, 2, 'COORDINADOR DE TICKETS', 'Sección Tickets', '0987000003', 'CENTRAL', 7, '2013-05-19', 0, 'Coordinador de emisión', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(375, 'Alejandra Beatriz', 'Riveros Ojeda', '7000004', 15, 1, 'FUNCIONARIA DE TICKETS', 'Sección Tickets', '0987000004', 'CENTRAL', 7, '2020-08-25', 0, 'Funcionaria de tickets', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(376, 'Carlos Roberto', 'Báez Montiel', '7000005', 7, NULL, 'OFICIAL TIKETERO', 'Sección Tickets', '0987000005', 'CENTRAL', 7, '2017-04-01', 0, 'Oficial tiketero', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(377, 'María Lourdes', 'Montiel Maldonado', '7000006', 11, 2, 'JEFA DE TICKETS', 'Sección Tickets', '0987000006', 'CENTRAL', 7, '2014-11-17', 0, 'Jefa de sección', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(378, 'Luis Fernando', 'Quiroz Lezcano', '7000007', 13, 1, 'OFICIAL DE CONTROL TICKETS', 'Sección Tickets', '0987000007', 'CENTRAL', 7, '2017-07-02', 0, 'Oficial de control', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(379, 'Beatriz Gloria', 'Benítez Sánchez', '7000008', 6, NULL, 'COORDINADORA DE EMISIÓN', 'Sección Tickets', '0987000008', 'CENTRAL', 7, '2015-04-20', 0, 'Coordinadora de emisión', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(380, 'Ramón Gustavo', 'Peralta Cardozo', '7000009', 10, 2, 'ANALISTA DE TICKETS', 'Sección Tickets', '0987000009', 'CENTRAL', 7, '2013-02-07', 0, 'Analista de tickets', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(381, 'Elena Patricia', 'Jara Fleitas', '7000010', 14, 1, 'SECRETARIA DE TICKETS', 'Sección Tickets', '0987000010', 'CENTRAL', 7, '2021-07-24', 0, 'Secretaria de sección', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(382, 'Sebastián Diego', 'Romero Ayala', '7000011', 5, NULL, 'OFICIAL SUPERIOR TIKETERO', 'Sección Tickets', '0987000011', 'CENTRAL', 7, '2011-10-30', 0, 'Oficial superior', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(383, 'Concepción Norma', 'Fretes Recalde', '7000012', 8, 2, 'SUPERVISORA DE TICKETS', 'Sección Tickets', '0987000012', 'CENTRAL', 7, '2018-05-16', 0, 'Supervisora de tickets', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(384, 'Raúl Marcelo', 'Insfrán Torres', '7000013', 12, 1, 'ASISTENTE DE EMISIÓN', 'Sección Tickets', '0987000013', 'CENTRAL', 7, '2016-09-27', 0, 'Asistente de emisión', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(385, 'Estela Yolanda', 'Aguilar Villasanti', '7000014', 9, NULL, 'COORDINADORA DE TURNOS', 'Sección Tickets', '0987000014', 'CENTRAL', 7, '2015-01-12', 0, 'Coordinadora de turnos', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(386, 'Blas Antonio', 'Rolon Gavilán', '7000015', 15, 2, 'FUNCIONARIO DE APOYO', 'Sección Tickets', '0987000015', 'CENTRAL', 7, '2020-04-09', 0, 'Funcionario de apoyo', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(387, 'Alba Cristina', 'Silva Cabañas', '7000016', 7, 1, 'OFICIAL DE ENTREGA', 'Sección Tickets', '0987000016', 'CENTRAL', 7, '2017-08-05', 0, 'Oficial de entrega', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(388, 'Rafael Esteban', 'Martínez Núñez', '7000017', 11, NULL, 'JEFE DE ENTREGA', 'Sección Tickets', '0987000017', 'CENTRAL', 7, '2015-12-21', 0, 'Jefe de entrega', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(389, 'Mabel Liliana', 'Godoy Paniagua', '7000018', 13, 2, 'OFICIAL ESPECIALISTA TICKETS', 'Sección Tickets', '0987000018', 'CENTRAL', 7, '2019-06-28', 0, 'Oficial especialista', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(390, 'Hugo Víctor', 'Cáceres Ojeda', '7000019', 6, 1, 'COORDINADOR DE SERVICIOS', 'Sección Tickets', '0987000019', 'CENTRAL', 7, '2016-02-13', 0, 'Coordinador de servicios', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08'),
(391, 'Beatriz Norma', 'Cantero Salinas', '7000020', 10, NULL, 'ANALISTA SENIOR TICKETS', 'Sección Tickets', '0987000020', 'CENTRAL', 7, '2013-04-29', 0, 'Analista senior de tickets', 1, '2025-06-20 16:10:08', '2025-06-20 16:10:08');

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
DELIMITER $$
CREATE TRIGGER `trg_update_antiguedad` BEFORE UPDATE ON `policias` FOR EACH ROW BEGIN
    IF NEW.fecha_ingreso IS NOT NULL THEN
        SET NEW.antiguedad_dias = DATEDIFF(CURDATE(), NEW.fecha_ingreso);
    END IF;
END
$$
DELIMITER ;

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
(1, 'Médico', 'Ausencia por motivos médicos', 1, '2025-06-16 21:25:25'),
(2, 'Embarazo', 'Licencia por embarazo', 1, '2025-06-16 21:25:25'),
(3, 'Personal', 'Ausencia por motivos personales', 0, '2025-06-16 21:25:25'),
(4, 'Vacaciones', 'Período vacacional', 0, '2025-06-16 21:25:25'),
(5, 'Capacitación', 'Ausencia por capacitación o entrenamiento', 0, '2025-06-16 21:25:25'),
(6, 'Suspensión', 'Suspensión disciplinaria', 1, '2025-06-16 21:25:25'),
(7, 'Descanso Post-Guardia', 'Período de descanso obligatorio después de realizar guardia', 0, '2025-06-23 15:04:10');

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
(1, 'Admin', '$2y$10$HX1Lf2s93nawpw5LdWZVyeJA1xI0EMczmswQvCFaB3GPqQ7q6CRnO', 'Administrador', 'admin@gmail.com', 'ADMIN', 1, '2025-06-16 21:35:50', '2025-06-16 21:35:50'),
(2, 'Marcelo', '$2y$10$VoQiecIjRk6sk0TxuAtMuOK39aNomDZQmoCfcPHQEAEvHyw0tCipe', 'Marcelo Ariel Benitez', 'marceloariel722@gmail.com', 'ADMIN', 1, '2025-06-23 14:40:25', '2025-06-23 14:40:25');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_disponibilidad_policias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_disponibilidad_policias` (
`id` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`grado` varchar(100)
,`nivel_jerarquia` int(11)
,`especialidad` varchar(150)
,`cargo` varchar(150)
,`telefono` varchar(20)
,`lugar_guardia` varchar(100)
,`region` enum('CENTRAL','REGIONAL')
,`antiguedad_dias` int(11)
,`disponibilidad` varchar(13)
,`observaciones` text
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_lista_guardias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_lista_guardias` (
`posicion` int(11)
,`policia_id` int(11)
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`grado` varchar(100)
,`nivel_jerarquia` int(11)
,`antiguedad_dias` int(11)
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
-- Indices de la tabla `guardias_semanales_generadas`
--
ALTER TABLE `guardias_semanales_generadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_semana_tipo` (`fecha_inicio_semana`,`tipo_guardia`);

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
  ADD UNIQUE KEY `cin` (`cin`),
  ADD KEY `especialidad_id` (`especialidad_id`),
  ADD KEY `idx_cin` (`cin`),
  ADD KEY `idx_grado` (`grado_id`),
  ADD KEY `idx_lugar_guardia` (`lugar_guardia_id`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_policias_activos` (`activo`,`grado_id`,`antiguedad_dias`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

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
-- AUTO_INCREMENT de la tabla `guardias_semanales_generadas`
--
ALTER TABLE `guardias_semanales_generadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7228;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=392;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_disponibilidad_policias`
--
DROP TABLE IF EXISTS `vista_disponibilidad_policias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_disponibilidad_policias`  AS SELECT `p`.`id` AS `id`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `e`.`nombre` AS `especialidad`, `p`.`cargo` AS `cargo`, `p`.`telefono` AS `telefono`, `lg`.`nombre` AS `lugar_guardia`, `p`.`region` AS `region`, `p`.`antiguedad_dias` AS `antiguedad_dias`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad`, `p`.`observaciones` AS `observaciones` FROM (((`policias` `p` left join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `especialidades` `e` on((`p`.`especialidad_id` = `e`.`id`))) left join `lugares_guardias` `lg` on((`p`.`lugar_guardia_id` = `lg`.`id`))) WHERE (`p`.`activo` = TRUE) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_lista_guardias`
--
DROP TABLE IF EXISTS `vista_lista_guardias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_lista_guardias`  AS SELECT `lg`.`posicion` AS `posicion`, `p`.`id` AS `policia_id`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `p`.`antiguedad_dias` AS `antiguedad_dias`, `lguar`.`nombre` AS `lugar_guardia`, `lg`.`ultima_guardia_fecha` AS `ultima_guardia_fecha`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad` FROM (((`lista_guardias` `lg` join `policias` `p` on((`lg`.`policia_id` = `p`.`id`))) join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `lugares_guardias` `lguar` on((`p`.`lugar_guardia_id` = `lguar`.`id`))) WHERE (`p`.`activo` = TRUE) ORDER BY `lg`.`posicion` ASC ;

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
  ADD CONSTRAINT `policias_ibfk_3` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`);

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`jefe_servicio_id`) REFERENCES `policias` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
