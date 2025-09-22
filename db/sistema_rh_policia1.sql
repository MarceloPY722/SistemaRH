-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 17-09-2025 a las 01:09:56
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
CREATE DEFINER=`root`@`localhost` PROCEDURE `RotarGuardia` (IN `policia_id_param` INT)   BEGIN
    DECLARE max_posicion INT;
    DECLARE policia_estado VARCHAR(20);
    
    -- Verificar el estado del policía
    SELECT estado INTO policia_estado 
    FROM policias 
    WHERE id = policia_id_param;
    
    -- Solo proceder si el policía está DISPONIBLE
    IF policia_estado = 'DISPONIBLE' THEN
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
    END IF;
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
-- Estructura de tabla para la tabla `asignaciones_temporales_guardia`
--

CREATE TABLE `asignaciones_temporales_guardia` (
  `id` int NOT NULL,
  `fecha_guardia` date NOT NULL COMMENT 'Fecha de la guardia',
  `orden_dia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden del día',
  `sector_id` int NOT NULL COMMENT 'ID del sector (lugar_guardia_id)',
  `policia_id` int NOT NULL COMMENT 'ID del policía asignado',
  `posicion_sector` int NOT NULL COMMENT 'Posición dentro del sector (1=primero, 2=segundo, etc.)',
  `posicion_original_lista` int NOT NULL COMMENT 'Posición original en lista_guardias',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Tabla temporal para almacenar asignaciones antes de generar PDF';

--
-- Volcado de datos para la tabla `asignaciones_temporales_guardia`
--

INSERT INTO `asignaciones_temporales_guardia` (`id`, `fecha_guardia`, `orden_dia`, `sector_id`, `policia_id`, `posicion_sector`, `posicion_original_lista`, `created_at`) VALUES
(1, '2025-09-16', '2/2025', 7, 419, 1, 1, '2025-09-16 00:52:36'),
(2, '2025-09-16', '2/2025', 8, 420, 1, 2, '2025-09-16 00:52:41');

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
  `estado` enum('PENDIENTE','APROBADA','RECHAZADA','COMPLETADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'PENDIENTE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `ausencias`
--

INSERT INTO `ausencias` (`id`, `policia_id`, `tipo_ausencia_id`, `fecha_inicio`, `fecha_fin`, `descripcion`, `justificacion`, `documento_adjunto`, `aprobado_por`, `estado`, `created_at`, `updated_at`) VALUES
(21, 436, 1, '2025-09-01', NULL, '', '', NULL, NULL, 'APROBADA', '2025-09-01 01:07:58', '2025-09-01 01:07:58');

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
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `grados`
--

INSERT INTO `grados` (`id`, `nombre`, `nivel_jerarquia`, `abreviatura`, `descripcion`, `created_at`, `updated_at`) VALUES
(1, 'Oficial', 1, 'OF.', 'Categoría de oficiales superiores', '2025-07-27 23:48:07', '2025-07-27 23:48:07'),
(2, 'Oficial Subalterno', 2, 'OF. SUB.', 'Categoría de oficiales subalternos', '2025-07-27 23:48:07', '2025-07-27 23:48:07'),
(3, 'Suboficial', 3, 'SUBOF.', 'Categoría de suboficiales', '2025-07-27 23:48:07', '2025-07-27 23:48:07'),
(4, 'Funcionario', 4, 'FUNC.', 'Categoría de funcionarios civiles', '2025-07-27 23:48:07', '2025-07-27 23:48:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_asignaciones`
--

CREATE TABLE `guardias_asignaciones` (
  `id` int NOT NULL,
  `guardia_id` int NOT NULL COMMENT 'ID de la guardia generada',
  `policia_id` int NOT NULL COMMENT 'ID del policía asignado',
  `puesto` enum('JEFE_SERVICIO','JEFE_CUARTEL','OFICIAL_GUARDIA','ATENCIÓN TELEFÓNICA EXCLUSIVA','NUMERO_GUARDIA','DATA_CENTER','TENIDA_REGLAMENTO','SANIDAD_GUARDIA') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Puesto asignado',
  `numero_puesto` int DEFAULT NULL COMMENT 'Número del puesto (para NUMERO_GUARDIA 1-4, SANIDAD_GUARDIA 1-3)',
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Asignaciones detalladas de personal por guardia';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_generadas`
--

CREATE TABLE `guardias_generadas` (
  `id` int NOT NULL,
  `fecha_guardia` date NOT NULL COMMENT 'Fecha de la guardia',
  `orden_dia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden del día (ej: 27/2025)',
  `region` enum('CENTRAL','REGIONAL') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Región asignada según día de semana',
  `estado` enum('PROGRAMADA','ACTIVA','COMPLETADA','CANCELADA') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'PROGRAMADA',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Guardias generadas por día con orden único';

--
-- Volcado de datos para la tabla `guardias_generadas`
--

INSERT INTO `guardias_generadas` (`id`, `fecha_guardia`, `orden_dia`, `region`, `estado`, `created_at`, `updated_at`) VALUES
(36, '2025-09-16', '1/2025', 'CENTRAL', 'PROGRAMADA', '2025-09-15 22:30:42', '2025-09-15 22:30:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_generadas_detalle`
--

CREATE TABLE `guardias_generadas_detalle` (
  `id` int NOT NULL,
  `guardia_generada_id` int NOT NULL,
  `policia_id` int NOT NULL,
  `lugar_guardia_id` int NOT NULL,
  `posicion_asignacion` int NOT NULL COMMENT 'Orden de asignación dentro del lugar de guardia',
  `posicion_lista_original` int NOT NULL COMMENT 'Posición original en lista_guardias antes de la asignación',
  `es_retorno_ausencia` tinyint(1) DEFAULT '0' COMMENT 'Si el policía regresa de ausencia',
  `horario_inicio` time DEFAULT NULL,
  `horario_fin` time DEFAULT NULL,
  `observaciones_asignacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `guardias_generadas_detalle`
--

INSERT INTO `guardias_generadas_detalle` (`id`, `guardia_generada_id`, `policia_id`, `lugar_guardia_id`, `posicion_asignacion`, `posicion_lista_original`, `es_retorno_ausencia`, `horario_inicio`, `horario_fin`, `observaciones_asignacion`, `created_at`) VALUES
(358, 36, 546, 1, 1, 1, 0, NULL, NULL, 'JEFE_SERVICIO', '2025-09-15 22:30:42'),
(359, 36, 450, 2, 2, 2, 0, NULL, NULL, 'JEFE_CUARTEL', '2025-09-15 22:30:42'),
(360, 36, 657, 3, 3, 3, 0, NULL, NULL, 'OFICIAL_GUARDIA', '2025-09-15 22:30:42'),
(361, 36, 418, 4, 4, 4, 0, NULL, NULL, 'ATENCION_TELEFONICA_EXCLUSIVA', '2025-09-15 22:30:42'),
(362, 36, 690, 6, 5, 5, 0, NULL, NULL, 'CONDUCTOR_GUARDIA', '2025-09-15 22:30:42'),
(363, 36, 509, 7, 6, 6, 0, NULL, NULL, 'GUARDIA_06_30_22_00', '2025-09-15 22:30:42'),
(364, 36, 498, 8, 7, 7, 0, NULL, NULL, 'TENIDA_REGLAMENTO', '2025-09-15 22:30:42'),
(365, 36, 464, 5, 8, 8, 0, NULL, NULL, 'NUMERO_GUARDIA_1', '2025-09-15 22:30:42'),
(366, 36, 469, 5, 9, 9, 0, NULL, NULL, 'NUMERO_GUARDIA_2', '2025-09-15 22:30:42'),
(367, 36, 601, 5, 10, 10, 0, NULL, NULL, 'NUMERO_GUARDIA_3', '2025-09-15 22:30:42'),
(368, 36, 599, 5, 11, 11, 0, NULL, NULL, 'NUMERO_GUARDIA_4', '2025-09-15 22:30:42'),
(369, 36, 485, 9, 12, 12, 0, NULL, NULL, 'SANIDAD_GUARDIA_1', '2025-09-15 22:30:42'),
(370, 36, 491, 9, 13, 13, 0, NULL, NULL, 'SANIDAD_GUARDIA_2', '2025-09-15 22:30:42');

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
-- Estructura de tabla para la tabla `historial_guardias_policia`
--

CREATE TABLE `historial_guardias_policia` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `guardia_id` int NOT NULL,
  `fecha_guardia` date NOT NULL,
  `puesto` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `orden_dia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Historial de guardias realizadas por cada policía';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intercambios_guardias`
--

CREATE TABLE `intercambios_guardias` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `ausencia_id` int NOT NULL,
  `lugar_original_id` int NOT NULL,
  `lugar_intercambio_id` int NOT NULL,
  `fecha_intercambio` datetime NOT NULL,
  `fecha_restauracion` datetime DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `intercambios_guardias`
--

INSERT INTO `intercambios_guardias` (`id`, `policia_id`, `ausencia_id`, `lugar_original_id`, `lugar_intercambio_id`, `fecha_intercambio`, `fecha_restauracion`, `usuario_id`, `activo`, `created_at`) VALUES
(8, 436, 21, 13, 11, '2025-08-31 22:07:58', NULL, 1, 1, '2025-09-01 01:07:58');

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
(7934, 418, 336, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7935, 419, 1, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7936, 420, 2, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7937, 423, 3, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7938, 427, 4, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7939, 433, 5, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7940, 437, 6, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7941, 439, 7, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7942, 446, 8, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7943, 450, 338, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7944, 457, 9, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7945, 464, 332, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7946, 466, 10, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7947, 468, 11, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7948, 469, 331, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7949, 470, 12, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7950, 473, 13, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7951, 474, 14, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7952, 480, 15, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7953, 485, 328, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7954, 490, 16, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7955, 498, 333, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7956, 499, 17, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7957, 500, 18, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7958, 502, 19, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7959, 509, 334, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7960, 516, 20, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7961, 518, 21, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7962, 520, 22, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7963, 527, 23, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7964, 530, 24, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7965, 533, 25, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7966, 535, 26, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7967, 541, 27, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7968, 546, 339, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7969, 547, 28, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7970, 558, 29, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7971, 559, 30, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7972, 561, 31, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7973, 563, 32, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7974, 570, 33, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7975, 575, 34, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7976, 582, 35, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7977, 585, 36, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7978, 592, 37, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7979, 595, 38, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7980, 601, 330, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7981, 604, 39, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7982, 608, 40, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7983, 614, 41, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7984, 621, 42, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7985, 622, 43, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7986, 624, 44, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7987, 630, 45, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7988, 633, 46, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7989, 635, 47, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7990, 649, 48, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7991, 650, 49, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7992, 653, 50, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7993, 657, 337, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7994, 665, 51, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7995, 666, 52, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7996, 667, 53, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7997, 671, 54, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7998, 680, 55, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(7999, 683, 56, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8000, 684, 57, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8001, 690, 335, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8002, 700, 58, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8003, 703, 59, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8004, 704, 60, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8005, 707, 61, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8006, 708, 62, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8007, 712, 63, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8008, 714, 64, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8009, 718, 65, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8010, 720, 66, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8011, 721, 67, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8012, 722, 68, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8013, 724, 69, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8014, 726, 70, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8015, 728, 71, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8016, 732, 72, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8017, 734, 73, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8018, 737, 74, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8019, 742, 75, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8020, 414, 76, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8021, 422, 77, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8022, 426, 78, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8023, 429, 79, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8024, 431, 80, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8025, 434, 81, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8026, 442, 82, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8027, 458, 83, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8028, 460, 84, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8029, 461, 85, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8030, 463, 86, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8031, 467, 87, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8032, 472, 88, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8033, 475, 89, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8034, 476, 90, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8035, 484, 91, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8036, 489, 92, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8037, 493, 93, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8038, 497, 94, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8039, 504, 95, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8040, 505, 96, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8041, 510, 97, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8042, 521, 98, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8043, 529, 99, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8044, 538, 100, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8045, 539, 101, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8046, 542, 102, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8047, 543, 103, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8048, 549, 104, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8049, 550, 105, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8050, 556, 106, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8051, 562, 107, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8052, 566, 108, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8053, 569, 109, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8054, 572, 110, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8055, 573, 111, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8056, 574, 112, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8057, 579, 113, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8058, 581, 114, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8059, 596, 115, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8060, 598, 116, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8061, 599, 329, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8062, 600, 117, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8063, 602, 118, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8064, 606, 119, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8065, 625, 120, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8066, 626, 121, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8067, 632, 122, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8068, 645, 123, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8069, 648, 124, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8070, 654, 125, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8071, 655, 126, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8072, 658, 127, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8073, 660, 128, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8074, 661, 129, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8075, 662, 130, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8076, 672, 131, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8077, 674, 132, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8078, 676, 133, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8079, 679, 134, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8080, 685, 135, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8081, 687, 136, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8082, 691, 137, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8083, 692, 138, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8084, 695, 139, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8085, 696, 140, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8086, 702, 141, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8087, 706, 142, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8088, 717, 143, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8089, 723, 144, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8090, 727, 145, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8091, 729, 146, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8092, 733, 147, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8093, 735, 148, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8094, 738, 149, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8095, 741, 150, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8096, 744, 151, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8097, 745, 152, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8098, 746, 153, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8099, 751, 154, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8100, 421, 155, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8101, 425, 156, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8102, 430, 157, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8103, 432, 158, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8104, 440, 159, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8105, 444, 160, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8106, 447, 161, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8107, 451, 162, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8108, 455, 163, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8109, 471, 164, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8110, 478, 165, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8111, 483, 166, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8112, 488, 167, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8113, 491, 327, '2025-09-16', NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8114, 508, 168, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8115, 513, 169, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8116, 514, 170, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8117, 515, 171, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8118, 528, 172, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8119, 545, 173, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8120, 548, 174, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8121, 553, 175, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8122, 555, 176, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8123, 557, 177, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8124, 567, 178, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8125, 578, 179, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8126, 583, 180, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8127, 584, 181, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8128, 609, 182, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8129, 612, 183, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8130, 613, 184, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8131, 620, 185, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8132, 631, 186, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8133, 639, 187, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8134, 651, 188, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8135, 652, 189, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8136, 656, 190, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8137, 663, 191, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8138, 668, 192, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8139, 669, 193, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8140, 673, 194, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8141, 675, 195, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8142, 682, 196, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8143, 689, 197, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8144, 698, 198, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8145, 699, 199, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8146, 705, 200, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8147, 716, 201, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8148, 731, 202, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8149, 739, 203, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8150, 743, 204, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8151, 747, 205, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8152, 748, 206, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8153, 753, 207, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8154, 417, 208, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8155, 435, 209, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8156, 441, 210, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8157, 443, 211, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8158, 445, 212, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8159, 449, 213, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8160, 452, 214, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8161, 453, 215, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8162, 459, 216, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8163, 477, 217, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8164, 486, 218, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8165, 492, 219, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8166, 503, 220, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8167, 506, 221, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8168, 507, 222, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8169, 512, 223, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8170, 517, 224, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8171, 519, 225, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8172, 526, 226, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8173, 531, 227, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8174, 532, 228, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8175, 537, 229, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8176, 544, 230, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8177, 551, 231, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8178, 552, 232, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8179, 554, 233, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8180, 560, 234, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8181, 564, 235, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8182, 565, 236, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8183, 586, 237, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8184, 597, 238, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8185, 605, 239, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8186, 615, 240, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8187, 616, 241, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8188, 618, 242, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8189, 623, 243, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8190, 628, 244, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8191, 638, 245, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8192, 642, 246, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8193, 643, 247, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8194, 644, 248, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8195, 647, 249, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8196, 664, 250, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8197, 677, 251, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8198, 678, 252, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8199, 681, 253, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8200, 686, 254, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8201, 709, 255, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8202, 710, 256, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8203, 711, 257, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8204, 719, 258, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8205, 736, 259, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8206, 415, 260, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8207, 416, 261, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8208, 424, 262, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8209, 428, 263, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8210, 436, 264, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8211, 438, 265, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8212, 448, 266, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8213, 454, 267, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8214, 456, 268, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8215, 462, 269, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8216, 465, 270, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8217, 479, 271, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8218, 481, 272, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8219, 482, 273, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8220, 487, 274, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8221, 494, 275, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8222, 495, 276, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8223, 496, 277, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8224, 511, 278, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8225, 522, 279, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8226, 523, 280, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8227, 524, 281, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8228, 525, 282, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8229, 534, 283, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8230, 536, 284, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8231, 540, 285, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8232, 568, 286, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8233, 571, 287, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8234, 576, 288, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8235, 577, 289, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8236, 580, 290, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8237, 587, 291, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8238, 588, 292, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8239, 589, 293, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8240, 590, 294, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8241, 591, 295, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8242, 593, 296, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8243, 594, 297, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8244, 603, 298, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8245, 607, 299, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8246, 610, 300, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8247, 611, 301, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8248, 617, 302, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8249, 619, 303, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8250, 627, 304, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8251, 629, 305, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8252, 634, 306, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8253, 636, 307, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8254, 637, 308, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8255, 640, 309, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8256, 641, 310, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8257, 646, 311, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8258, 659, 312, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8259, 670, 313, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8260, 688, 314, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8261, 693, 315, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8262, 694, 316, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8263, 697, 317, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8264, 701, 318, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8265, 713, 319, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8266, 715, 320, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8267, 725, 321, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8268, 730, 322, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8269, 740, 323, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8270, 749, 324, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8271, 750, 325, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42'),
(8272, 752, 326, NULL, NULL, '2025-09-15 22:17:19', '2025-09-15 22:30:42');

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
(1, 'JEFE DE SERVICIO', '', '', 'CENTRAL', 1, '2025-09-01 00:57:02', '2025-09-17 00:32:13'),
(2, 'JEFE DE SERVICIO', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:32:35'),
(3, 'JEFE DE CUARTEL', '', '', 'CENTRAL', 1, '2025-09-01 00:57:18', '2025-09-17 00:32:51'),
(4, 'JEFE DE CUARTEL', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:33:05'),
(5, 'OFICIAL DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:57:27', '2025-09-17 00:33:50'),
(6, 'OFICIAL DE GUARDIA', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:34:19'),
(7, 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '', '', 'CENTRAL', 1, '2025-09-01 00:57:53', '2025-09-17 00:34:41'),
(8, 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:35:00'),
(9, 'NUMERO DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:58:47', '2025-09-17 00:35:49'),
(10, 'NUMERO DE GUARDIA', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:36:10'),
(11, 'CONDUCTOR DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:59:28', '2025-09-17 00:36:29'),
(12, 'CONDUCTOR DE GUARDIA', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:36:42'),
(13, 'DE  06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE', '', '', 'CENTRAL', 1, '2025-09-01 01:00:06', '2025-09-17 00:38:19'),
(14, 'DE 06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:38:35'),
(15, 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA', '', '', 'CENTRAL', 1, '2025-09-01 01:00:12', '2025-09-17 00:39:02'),
(16, 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:39:16'),
(17, 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE', '', '', 'CENTRAL', 1, '2025-09-01 01:00:20', '2025-09-17 00:39:29'),
(18, 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE', '', '', 'REGIONAL', 1, '2025-09-09 00:10:12', '2025-09-17 00:39:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_dia`
--

CREATE TABLE `orden_dia` (
  `id` int NOT NULL,
  `numero_orden` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden (ej: 27/2025)',
  `año` int NOT NULL COMMENT 'Año del orden',
  `numero` int NOT NULL COMMENT 'Número secuencial del año',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Gestión de números de orden del día únicos';

--
-- Volcado de datos para la tabla `orden_dia`
--

INSERT INTO `orden_dia` (`id`, `numero_orden`, `año`, `numero`, `fecha_creacion`, `activo`) VALUES
(36, '1/2025', 2025, 1, '2025-09-15 22:30:42', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_junta_medica_telefonista`
--

CREATE TABLE `orden_junta_medica_telefonista` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL COMMENT 'ID del policía',
  `ausencia_id` int NOT NULL COMMENT 'ID de la ausencia',
  `lugar_guardia_original_id` int NOT NULL COMMENT 'ID del lugar de guardia original',
  `orden_anotacion` int NOT NULL COMMENT 'Orden de anotación para ATENCIÓN TELEFÓNICA EXCLUSIVA',
  `fecha_anotacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de anotación',
  `activo` tinyint(1) DEFAULT '1' COMMENT 'Estado activo/inactivo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Tabla para gestionar el orden de policías con Junta Médica asignados a ATENCIÓN TELEFÓNICA EXCLUSIVA';

--
-- Volcado de datos para la tabla `orden_junta_medica_telefonista`
--

INSERT INTO `orden_junta_medica_telefonista` (`id`, `policia_id`, `ausencia_id`, `lugar_guardia_original_id`, `orden_anotacion`, `fecha_anotacion`, `activo`) VALUES
(7, 436, 21, 13, 1, '2025-09-01 01:07:58', 1);

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
  `grado_id` int NOT NULL COMMENT 'Referencia a tipo_grados.id',
  `especialidad_id` int DEFAULT NULL,
  `cargo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `comisionamiento` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `telefono` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `region_id` int DEFAULT '1',
  `lugar_guardia_id` int DEFAULT NULL,
  `lugar_guardia_reserva_id` int DEFAULT '6' COMMENT 'Siempre ATENCIÓN TELEFÓNICA EXCLUSIVA (ID 6)',
  `observaciones` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `estado` enum('DISPONIBLE','NO DISPONIBLE') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'DISPONIBLE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `policias`
--

INSERT INTO `policias` (`id`, `legajo`, `nombre`, `apellido`, `cin`, `genero`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region_id`, `lugar_guardia_id`, `lugar_guardia_reserva_id`, `observaciones`, `activo`, `estado`, `created_at`, `updated_at`) VALUES
(414, 1, 'María', 'Castro Martínez', '35019969', 'FEMENINO', 2, 2, 'Policía', 'VENTANILLA', '099560596', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(415, 2, 'Carmen', 'Ospina García', '45440994', 'FEMENINO', 5, 2, 'Policía', 'VENTANILLA', '099230259', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(416, 3, 'Andrés', 'Martínez García', '48409468', 'MASCULINO', 5, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099455499', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(417, 4, 'Diego', 'Cardona Ospina', '77751045', 'FEMENINO', 4, NULL, 'Policía', NULL, '099588875', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(418, 5, 'Diego', 'Álvarez Ospina', '13880433', 'FEMENINO', 1, 1, 'Policía', '', '099419396', 3, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-16 23:27:18'),
(419, 6, 'Mauricio', 'Restrepo Fernández', '81653587', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099783131', 3, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-16 23:27:52'),
(420, 7, 'Ricardo', 'Castro López', '13364993', 'FEMENINO', 1, NULL, 'Policía', '', '099572753', 3, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-16 23:28:09'),
(421, 8, 'Laura', 'Díaz Sánchez', '94530449', 'FEMENINO', 3, 1, 'Policía', NULL, '099400870', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(422, 9, 'Carmen', 'Rodríguez Restrepo', '42823971', 'FEMENINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099461595', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(423, 10, 'Laura', 'Castro Gutiérrez', '18580543', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099643836', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(424, 11, 'Mauricio', 'Vargas Sánchez', '66961706', 'FEMENINO', 5, NULL, 'Policía', NULL, '099282537', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(425, 12, 'Juan', 'Peña Peña', '59718559', 'FEMENINO', 3, 2, 'Policía', NULL, '099546534', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(426, 13, 'Andrés', 'Restrepo Fernández', '28539913', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099856426', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(427, 14, 'Mónica', 'Fernández González', '33167405', 'FEMENINO', 1, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099788594', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(428, 15, 'Silvia', 'Sánchez López', '55286667', 'MASCULINO', 5, NULL, 'Policía', NULL, '099571548', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(429, 16, 'Juan', 'Rodríguez Álvarez', '92119510', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099832525', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(430, 17, 'Laura', 'Gutiérrez Álvarez', '54134802', 'FEMENINO', 3, 1, 'Policía', NULL, '099454215', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(431, 18, 'Pedro', 'Herrera Cardona', '52331141', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099309173', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(432, 19, 'Claudia', 'Díaz Álvarez', '11554263', 'MASCULINO', 3, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099959071', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(433, 20, 'Mónica', 'Ospina Jiménez', '23814847', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099996862', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(434, 21, 'Andrés', 'Jiménez Martínez', '83032719', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099441758', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(435, 22, 'Ana', 'García Jiménez', '47921220', 'FEMENINO', 4, 1, 'Policía', 'VENTANILLA', '099812588', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(436, 23, 'Juan', 'Díaz Castro', '26730484', 'FEMENINO', 5, NULL, 'Policía', NULL, '099207460', 1, 11, 13, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:07:58'),
(437, 24, 'María', 'García López', '95775545', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099137651', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(438, 25, 'Adriana', 'Castro Castro', '25527092', 'MASCULINO', 5, NULL, 'Policía', NULL, '099556847', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(439, 26, 'Fernando', 'Castro Gutiérrez', '23246952', 'FEMENINO', 1, NULL, 'Policía', NULL, '099976259', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(440, 27, 'Silvia', 'López Vargas', '34103159', 'FEMENINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099615441', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(441, 28, 'Mauricio', 'Herrera Castro', '76806507', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099460175', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(442, 29, 'María', 'Herrera Díaz', '49882734', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099215770', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(443, 30, 'Javier', 'Sánchez Moreno', '50341335', 'MASCULINO', 4, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099673137', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(444, 31, 'Javier', 'López Cardona', '13072633', 'MASCULINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099608260', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(445, 32, 'Mauricio', 'Martínez Rodríguez', '23118897', 'MASCULINO', 4, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099185190', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(446, 33, 'Mauricio', 'Restrepo Peña', '57899210', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099920295', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:16'),
(447, 34, 'Andrés', 'Restrepo Moreno', '25042366', 'MASCULINO', 3, 1, 'Policía', NULL, '099593342', 2, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(448, 35, 'Ana', 'Gutiérrez González', '10941791', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099669960', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(449, 36, 'Fernando', 'Vargas Martínez', '76828144', 'MASCULINO', 4, 1, 'Policía', NULL, '099121753', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(450, 37, 'Fernando', 'González Gutiérrez', '21107948', 'MASCULINO', 1, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099900686', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(451, 38, 'Claudia', 'Herrera Álvarez', '21293108', 'MASCULINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099370114', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(452, 39, 'Fernando', 'García Gutiérrez', '40907181', 'MASCULINO', 4, 1, 'Policía', NULL, '099252235', 1, 3, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(453, 40, 'Carlos', 'González Jiménez', '85610107', 'MASCULINO', 4, NULL, 'Policía', NULL, '099722211', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-09 00:15:15'),
(454, 41, 'Ana', 'Álvarez Jiménez', '92310944', 'MASCULINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099688717', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(455, 42, 'María', 'Díaz Restrepo', '12654407', 'FEMENINO', 3, NULL, 'Policía', 'VENTANILLA', '099531026', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(456, 43, 'Ricardo', 'Ospina García', '14973483', 'MASCULINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099269083', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(457, 44, 'Patricia', 'Jiménez Vargas', '87483216', 'MASCULINO', 1, 1, 'Policía', NULL, '099612905', 2, 18, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(458, 45, 'Silvia', 'Peña Gutiérrez', '86620966', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099410157', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(459, 46, 'Pedro', 'Ramírez Peña', '94369580', 'MASCULINO', 4, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099516158', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(460, 47, 'Mauricio', 'Peña Díaz', '13274603', 'MASCULINO', 2, 2, 'Policía', NULL, '099698184', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(461, 48, 'Andrés', 'González Peña', '54648584', 'FEMENINO', 2, 1, 'Policía', NULL, '099605820', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(462, 49, 'Diego', 'Ospina Rodríguez', '75358549', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099817640', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(463, 50, 'Silvia', 'Cardona Moreno', '59563096', 'FEMENINO', 2, NULL, 'Policía', NULL, '099705040', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(464, 51, 'Roberto', 'Gutiérrez González', '92298747', 'MASCULINO', 1, 1, 'Policía', 'VENTANILLA', '099909554', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(465, 52, 'Claudia', 'Herrera Gutiérrez', '12631439', 'MASCULINO', 5, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099181569', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(466, 53, 'Diego', 'Sánchez Rodríguez', '89606707', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099906408', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(467, 54, 'Carmen', 'Díaz González', '88792335', 'MASCULINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099437798', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(468, 55, 'Andrés', 'Jiménez López', '11342837', 'FEMENINO', 1, 1, 'Policía', 'VENTANILLA', '099251327', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(469, 56, 'Diego', 'Ospina Sánchez', '31089685', 'MASCULINO', 1, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099734707', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(470, 57, 'Esperanza', 'López Rodríguez', '56916868', 'FEMENINO', 1, 2, 'Policía', NULL, '099602770', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(471, 58, 'Claudia', 'Peña Ospina', '46636952', 'MASCULINO', 3, 2, 'Policía', 'VENTANILLA', '099520512', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(472, 59, 'Ana', 'Martínez Castro', '93083597', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099832516', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(473, 60, 'Pedro', 'Cardona Cardona', '47990064', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099484998', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(474, 61, 'Ricardo', 'Díaz Sánchez', '87782216', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099348165', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(475, 62, 'Diego', 'Castro Díaz', '34568012', 'MASCULINO', 2, NULL, 'Policía', NULL, '099128575', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(476, 63, 'Mauricio', 'López Díaz', '69875238', 'MASCULINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099831051', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(477, 64, 'Silvia', 'Peña Martínez', '32436859', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099925285', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(478, 65, 'Mauricio', 'Moreno Ospina', '51614652', 'MASCULINO', 3, NULL, 'Policía', 'VENTANILLA', '099444109', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(479, 66, 'Diego', 'Rodríguez Vargas', '38836374', 'MASCULINO', 5, NULL, 'Policía', NULL, '099285259', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(480, 67, 'Carlos', 'González Moreno', '30598517', 'FEMENINO', 1, NULL, 'Policía', NULL, '099756799', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(481, 68, 'Andrés', 'Herrera Martínez', '62291411', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099841581', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(482, 69, 'Adriana', 'Cardona Gutiérrez', '73847653', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099801722', 2, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(483, 70, 'Ana', 'Díaz Sánchez', '29904359', 'FEMENINO', 3, NULL, 'Policía', NULL, '099410287', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(484, 71, 'Pedro', 'Cardona Ramírez', '24955689', 'MASCULINO', 2, 1, 'Policía', NULL, '099485015', 2, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(485, 72, 'Roberto', 'González Moreno', '11726485', 'FEMENINO', 1, NULL, 'Policía', NULL, '099154777', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(486, 73, 'Adriana', 'Restrepo Gutiérrez', '47212341', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099802220', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(487, 74, 'Mónica', 'Fernández Cardona', '89776304', 'MASCULINO', 5, 2, 'Policía', 'VENTANILLA', '099672250', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(488, 75, 'Adriana', 'Álvarez Moreno', '57576368', 'MASCULINO', 3, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099468570', 2, 4, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(489, 76, 'Andrés', 'Gutiérrez Restrepo', '29946174', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099907026', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(490, 77, 'Patricia', 'Sánchez López', '55853903', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099320074', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(491, 78, 'Claudia', 'González García', '57679695', 'MASCULINO', 3, 2, 'Policía', NULL, '099958265', 1, 17, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(492, 79, 'Claudia', 'Jiménez Fernández', '63533760', 'MASCULINO', 4, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099429817', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(493, 80, 'Mónica', 'Rodríguez Fernández', '95681991', 'MASCULINO', 2, NULL, 'Policía', NULL, '099814353', 2, 14, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(494, 81, 'Diego', 'Díaz López', '70386145', 'FEMENINO', 5, 1, 'Policía', NULL, '099672484', 2, 8, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(495, 82, 'Diego', 'González Jiménez', '28546767', 'MASCULINO', 5, NULL, 'Policía', NULL, '099782107', 2, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(496, 83, 'Adriana', 'Castro Jiménez', '69003366', 'MASCULINO', 5, 2, 'Policía', 'VENTANILLA', '099485919', 2, 2, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(497, 84, 'Roberto', 'Moreno Castro', '35319345', 'MASCULINO', 2, NULL, 'Policía', NULL, '099479625', 2, 12, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(498, 85, 'Carlos', 'Fernández Sánchez', '36023863', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099144641', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(499, 86, 'Fernando', 'Ospina Herrera', '35516464', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099487637', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:15'),
(500, 87, 'Mónica', 'Fernández Ramírez', '93854428', 'MASCULINO', 1, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099585971', 2, 16, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(502, 89, 'Javier', 'García Ospina', '42060270', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099812061', 2, 6, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-09 00:15:16'),
(503, 90, 'Juan', 'Restrepo Ospina', '11604517', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099947730', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(504, 91, 'Ana', 'Gutiérrez Moreno', '13052810', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099977822', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(505, 92, 'Ricardo', 'Ospina Jiménez', '25512847', 'MASCULINO', 2, NULL, 'Policía', NULL, '099776051', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(506, 93, 'Laura', 'Sánchez Álvarez', '82800330', 'MASCULINO', 4, NULL, 'Policía', 'TELEFONISTA', '099399028', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(507, 94, 'Adriana', 'Peña Ospina', '30455895', 'FEMENINO', 4, NULL, 'Policía', 'TELEFONISTA', '099442472', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(508, 95, 'Juan', 'Restrepo Rodríguez', '70973326', 'MASCULINO', 3, NULL, 'Policía', 'VENTANILLA', '099211700', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(509, 96, 'Carlos', 'Martínez Díaz', '53997046', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099463483', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(510, 97, 'Diego', 'López García', '66131447', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099265008', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(511, 98, 'Esperanza', 'Herrera Herrera', '59071040', 'MASCULINO', 5, 2, 'Policía', NULL, '099247730', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(512, 99, 'Mauricio', 'Gutiérrez Díaz', '79604327', 'FEMENINO', 4, NULL, 'Policía', NULL, '099186115', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(513, 100, 'María', 'Peña Ospina', '38154679', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099714229', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(514, 101, 'Pedro', 'Cardona Gutiérrez', '23552911', 'FEMENINO', 3, NULL, 'Policía', NULL, '099304887', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(515, 102, 'Claudia', 'Vargas Peña', '83503866', 'MASCULINO', 3, 1, 'Policía', NULL, '099826204', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(516, 103, 'Mauricio', 'García Fernández', '54830537', 'FEMENINO', 1, NULL, 'Policía', NULL, '099602963', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(517, 104, 'Mónica', 'Herrera Peña', '38409294', 'FEMENINO', 4, 2, 'Policía', NULL, '099461794', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(518, 105, 'Silvia', 'Martínez Cardona', '65288121', 'MASCULINO', 1, 2, 'Policía', NULL, '099496799', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(519, 106, 'Patricia', 'Martínez Cardona', '95496887', 'FEMENINO', 4, NULL, 'Policía', NULL, '099190454', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(520, 107, 'Esperanza', 'Restrepo Rodríguez', '10808115', 'FEMENINO', 1, NULL, 'Policía', NULL, '099307242', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(521, 108, 'Roberto', 'Herrera López', '60603791', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099816396', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(522, 109, 'Pedro', 'Fernández González', '61688002', 'MASCULINO', 5, NULL, 'Policía', NULL, '099227884', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(523, 110, 'Adriana', 'López López', '21636837', 'MASCULINO', 5, 2, 'Policía', NULL, '099145433', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(524, 111, 'Diego', 'Cardona Rodríguez', '64572494', 'FEMENINO', 5, 2, 'Policía', 'VENTANILLA', '099859382', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(525, 112, 'Ana', 'García Cardona', '14733101', 'FEMENINO', 5, NULL, 'Policía', NULL, '099191207', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(526, 113, 'Mónica', 'Moreno Sánchez', '16236914', 'FEMENINO', 4, 2, 'Policía', NULL, '099840294', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(527, 114, 'Silvia', 'García Jiménez', '98014934', 'FEMENINO', 1, 1, 'Policía', 'VENTANILLA', '099365321', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(528, 115, 'Diego', 'Restrepo Peña', '21168396', 'MASCULINO', 3, NULL, 'Policía', NULL, '099665632', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(529, 116, 'Mauricio', 'Herrera Vargas', '28393148', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099190333', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(530, 117, 'Ricardo', 'López Martínez', '22856319', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099797385', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(531, 118, 'Mauricio', 'López Díaz', '94728892', 'FEMENINO', 4, 2, 'Policía', NULL, '099466932', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(532, 119, 'Mónica', 'Fernández Cardona', '96187547', 'MASCULINO', 4, 1, 'Policía', NULL, '099687698', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(533, 120, 'Diego', 'Ospina Moreno', '59442089', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099730486', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(534, 121, 'Roberto', 'Herrera Gutiérrez', '86676031', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099882826', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(535, 122, 'Carlos', 'Díaz García', '34178414', 'MASCULINO', 1, 2, 'Policía', 'VENTANILLA', '099246054', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(536, 123, 'Javier', 'Castro Gutiérrez', '32607277', 'MASCULINO', 5, 1, 'Policía', NULL, '099301435', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(537, 124, 'Laura', 'Fernández Ospina', '12454868', 'MASCULINO', 4, 2, 'Policía', 'VENTANILLA', '099753970', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(538, 125, 'Roberto', 'López García', '25479694', 'FEMENINO', 2, 2, 'Policía', 'VENTANILLA', '099486496', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(539, 126, 'Adriana', 'Martínez Sánchez', '50362393', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099764119', 1, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(540, 127, 'Laura', 'Herrera Ramírez', '52493563', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099428350', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(541, 128, 'Patricia', 'Álvarez Castro', '49264751', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099541976', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(542, 129, 'Laura', 'González Martínez', '75200533', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099526200', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(543, 130, 'María', 'Ramírez López', '30079176', 'MASCULINO', 2, 1, 'Policía', NULL, '099491257', 2, 13, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:28:23', '2025-09-09 00:28:23'),
(544, 131, 'Andrés', 'Jiménez Rodríguez', '95342306', 'FEMENINO', 4, 2, 'Policía', NULL, '099560816', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(545, 132, 'Andrés', 'López Sánchez', '39060366', 'MASCULINO', 3, 1, 'Policía', 'TELEFONISTA', '099429340', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(546, 133, 'Ana', 'Cardona Fernández', '63962638', 'FEMENINO', 1, 2, 'Policía', 'TELEFONISTA', '099975801', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(547, 134, 'Ana', 'Jiménez Gutiérrez', '39298086', 'FEMENINO', 1, 1, 'Policía', 'VENTANILLA', '099596027', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(548, 135, 'Andrés', 'Ospina Martínez', '82815498', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099210308', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(549, 136, 'María', 'González González', '77651458', 'MASCULINO', 2, 2, 'Policía', 'VENTANILLA', '099394072', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(550, 137, 'Roberto', 'López García', '96988678', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099724020', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(551, 138, 'Silvia', 'Sánchez Ospina', '56640574', 'MASCULINO', 4, 2, 'Policía', 'VENTANILLA', '099872804', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(552, 139, 'Roberto', 'Vargas Fernández', '58529274', 'MASCULINO', 4, NULL, 'Policía', NULL, '099978254', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(553, 140, 'Juan', 'Sánchez Sánchez', '17661051', 'FEMENINO', 3, NULL, 'Policía', 'TELEFONISTA', '099158238', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(554, 141, 'Laura', 'Restrepo Cardona', '84907360', 'FEMENINO', 4, NULL, 'Policía', NULL, '099810036', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(555, 142, 'Laura', 'González Sánchez', '88241606', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099743417', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(556, 143, 'Juan', 'Sánchez Rodríguez', '94631374', 'FEMENINO', 2, NULL, 'Policía', NULL, '099651008', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(557, 144, 'Fernando', 'Peña López', '75242407', 'FEMENINO', 3, NULL, 'Policía', 'VENTANILLA', '099939454', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(558, 145, 'Fernando', 'Gutiérrez González', '74643534', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099836276', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(559, 146, 'Silvia', 'Vargas Cardona', '91649432', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099594886', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(560, 147, 'Patricia', 'Álvarez González', '75551191', 'FEMENINO', 4, 2, 'Policía', NULL, '099331525', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(561, 148, 'Silvia', 'García Rodríguez', '56961880', 'MASCULINO', 1, 1, 'Policía', 'TELEFONISTA', '099875661', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(562, 149, 'Claudia', 'Restrepo Díaz', '52014701', 'FEMENINO', 2, NULL, 'Policía', NULL, '099129135', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(563, 150, 'Roberto', 'Ospina Peña', '16933074', 'MASCULINO', 1, 2, 'Policía', 'VENTANILLA', '099326507', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(564, 151, 'Silvia', 'Gutiérrez Cardona', '58880272', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099968584', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(565, 152, 'Silvia', 'Martínez Herrera', '80278413', 'FEMENINO', 4, NULL, 'Policía', 'TELEFONISTA', '099766500', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:55', '2025-09-09 00:29:55'),
(566, 153, 'Ana', 'Castro González', '59431917', 'MASCULINO', 2, NULL, 'Policía', 'TELEFONISTA', '099673746', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(567, 154, 'Laura', 'Ramírez Rodríguez', '62663968', 'FEMENINO', 3, 2, 'Policía', 'TELEFONISTA', '099785577', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(568, 155, 'María', 'Restrepo González', '65665177', 'FEMENINO', 5, 1, 'Policía', 'TELEFONISTA', '099408658', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(569, 156, 'Fernando', 'Rodríguez Ospina', '84002339', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099479484', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(570, 157, 'Mauricio', 'González Moreno', '99183483', 'MASCULINO', 1, 1, 'Policía', NULL, '099221936', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(571, 158, 'Ana', 'Sánchez Díaz', '35264999', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099825564', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(572, 159, 'Juan', 'García López', '37573789', 'FEMENINO', 2, 1, 'Policía', 'VENTANILLA', '099597322', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(573, 160, 'Esperanza', 'Castro Restrepo', '48844852', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099409632', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(574, 161, 'Andrés', 'Rodríguez Martínez', '36432166', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099577485', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(575, 162, 'Juan', 'Ramírez Fernández', '87417999', 'MASCULINO', 1, NULL, 'Policía', NULL, '099162760', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(576, 163, 'Patricia', 'Sánchez Moreno', '77802088', 'MASCULINO', 5, 2, 'Policía', 'TELEFONISTA', '099358428', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(577, 164, 'Roberto', 'Martínez Peña', '49020674', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099686081', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(578, 165, 'Carmen', 'Castro Ospina', '86519637', 'MASCULINO', 3, 2, 'Policía', NULL, '099465946', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(579, 166, 'Ana', 'Ospina López', '30825347', 'FEMENINO', 2, 1, 'Policía', 'VENTANILLA', '099462187', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(580, 167, 'María', 'Díaz Vargas', '95096503', 'FEMENINO', 5, NULL, 'Policía', NULL, '099572887', 2, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(581, 168, 'María', 'Álvarez Vargas', '19198739', 'MASCULINO', 2, 2, 'Policía', 'VENTANILLA', '099316551', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(582, 169, 'Roberto', 'Ramírez García', '90777703', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099357684', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(583, 170, 'Fernando', 'Díaz Díaz', '99266212', 'MASCULINO', 3, NULL, 'Policía', NULL, '099851098', 1, 1, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:29:56', '2025-09-09 00:29:56'),
(584, 171, 'Ana', 'Moreno Ospina', '18636765', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099577070', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(585, 172, 'Diego', 'Moreno López', '45913015', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099516428', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(586, 173, 'Carmen', 'Ospina Fernández', '47157170', 'MASCULINO', 4, 2, 'Policía', NULL, '099905073', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(587, 174, 'Carmen', 'Álvarez Restrepo', '97625108', 'MASCULINO', 5, 2, 'Policía', NULL, '099140001', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(588, 175, 'Diego', 'González Jiménez', '96247764', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099422232', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(589, 176, 'Claudia', 'Cardona Ramírez', '55002988', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099475655', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(590, 177, 'Roberto', 'Peña Cardona', '46130801', 'FEMENINO', 5, 1, 'Policía', NULL, '099690200', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(591, 178, 'Pedro', 'Jiménez Castro', '27455126', 'MASCULINO', 5, NULL, 'Policía', 'TELEFONISTA', '099120492', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:58', '2025-09-09 00:31:58'),
(592, 179, 'Mónica', 'López Álvarez', '22531121', 'FEMENINO', 1, NULL, 'Policía', 'TELEFONISTA', '099637215', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(593, 180, 'Silvia', 'Álvarez Vargas', '71984203', 'FEMENINO', 5, 1, 'Policía', NULL, '099224338', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(594, 181, 'Patricia', 'Martínez Castro', '74140790', 'FEMENINO', 5, NULL, 'Policía', NULL, '099478953', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(595, 182, 'Silvia', 'Gutiérrez Álvarez', '34571865', 'FEMENINO', 1, 1, 'Policía', 'VENTANILLA', '099231936', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(596, 183, 'Roberto', 'Jiménez Cardona', '26414969', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099258501', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(597, 184, 'Mauricio', 'Vargas Moreno', '53155913', 'FEMENINO', 4, NULL, 'Policía', 'TELEFONISTA', '099769053', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(598, 185, 'Diego', 'Gutiérrez Rodríguez', '80582666', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099294850', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(599, 186, 'Adriana', 'López Jiménez', '10363988', 'FEMENINO', 2, 1, 'Policía', NULL, '099350510', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(600, 187, 'Laura', 'Díaz Castro', '86557070', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099679233', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(601, 188, 'Juan', 'López González', '47317268', 'MASCULINO', 1, NULL, 'Policía', NULL, '099260457', 1, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(602, 189, 'Ricardo', 'Peña Castro', '72142837', 'FEMENINO', 2, NULL, 'Policía', NULL, '099978272', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(603, 190, 'María', 'Jiménez Martínez', '39592141', 'FEMENINO', 5, 1, 'Policía', 'VENTANILLA', '099646672', 2, 9, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(604, 191, 'Roberto', 'Herrera Ospina', '46019651', 'FEMENINO', 1, 1, 'Policía', 'TELEFONISTA', '099792005', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(605, 192, 'Javier', 'Díaz Castro', '24192036', 'MASCULINO', 4, 1, 'Policía', NULL, '099272279', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(606, 193, 'Roberto', 'Díaz Cardona', '98794257', 'FEMENINO', 2, NULL, 'Policía', 'TELEFONISTA', '099679561', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(607, 194, 'Pedro', 'Gutiérrez Castro', '63940498', 'FEMENINO', 5, 1, 'Policía', 'TELEFONISTA', '099997258', 1, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(608, 195, 'Roberto', 'Cardona Castro', '61894692', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099188052', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(609, 196, 'Carmen', 'Herrera Ramírez', '97413821', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099639850', 1, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(610, 197, 'Carmen', 'González Cardona', '76130493', 'MASCULINO', 5, NULL, 'Policía', NULL, '099136350', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(611, 198, 'Roberto', 'Peña Martínez', '10490619', 'FEMENINO', 5, NULL, 'Policía', 'TELEFONISTA', '099681633', 1, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(612, 199, 'Roberto', 'Sánchez Cardona', '66998394', 'FEMENINO', 3, NULL, 'Policía', 'VENTANILLA', '099478382', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(613, 200, 'Carmen', 'Ramírez García', '69065039', 'MASCULINO', 3, 2, 'Policía', NULL, '099582950', 1, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(614, 201, 'Mónica', 'Restrepo Jiménez', '14213177', 'MASCULINO', 1, 2, 'Policía', 'TELEFONISTA', '099230554', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(615, 202, 'Andrés', 'Rodríguez López', '15015989', 'MASCULINO', 4, 2, 'Policía', NULL, '099818541', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(616, 203, 'Mauricio', 'González Restrepo', '30537205', 'MASCULINO', 4, 2, 'Policía', 'TELEFONISTA', '099927238', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(617, 204, 'María', 'González Cardona', '15263857', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099240855', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(618, 205, 'Ana', 'González Castro', '93009603', 'MASCULINO', 4, NULL, 'Policía', NULL, '099905535', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(619, 206, 'Pedro', 'Álvarez Ospina', '23333554', 'FEMENINO', 5, NULL, 'Policía', 'TELEFONISTA', '099856064', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(620, 207, 'Patricia', 'Moreno Jiménez', '40857062', 'MASCULINO', 3, NULL, 'Policía', 'TELEFONISTA', '099567879', 1, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(621, 208, 'Claudia', 'Vargas Cardona', '35159601', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099197244', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59');
INSERT INTO `policias` (`id`, `legajo`, `nombre`, `apellido`, `cin`, `genero`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region_id`, `lugar_guardia_id`, `lugar_guardia_reserva_id`, `observaciones`, `activo`, `estado`, `created_at`, `updated_at`) VALUES
(622, 209, 'Patricia', 'Ospina Castro', '35363459', 'FEMENINO', 1, NULL, 'Policía', NULL, '099792994', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(623, 210, 'Ana', 'Herrera Álvarez', '58319937', 'FEMENINO', 4, 2, 'Policía', 'TELEFONISTA', '099454271', 2, 10, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(624, 211, 'Juan', 'Gutiérrez Sánchez', '56604972', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099290988', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(625, 212, 'Esperanza', 'Gutiérrez Jiménez', '33569831', 'MASCULINO', 2, 2, 'Policía', 'VENTANILLA', '099868006', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(626, 213, 'Patricia', 'Herrera Castro', '36690574', 'MASCULINO', 2, 2, 'Policía', NULL, '099353280', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(627, 214, 'Andrés', 'Martínez Jiménez', '55933281', 'MASCULINO', 5, 1, 'Policía', 'VENTANILLA', '099431224', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(628, 215, 'Andrés', 'Martínez Restrepo', '25768388', 'FEMENINO', 4, 1, 'Policía', NULL, '099290713', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(629, 216, 'Adriana', 'Jiménez Rodríguez', '38007338', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099545083', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(630, 217, 'Patricia', 'Rodríguez García', '17668957', 'MASCULINO', 1, 1, 'Policía', 'VENTANILLA', '099213017', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(631, 218, 'Esperanza', 'Ramírez Álvarez', '31674791', 'MASCULINO', 3, NULL, 'Policía', NULL, '099408893', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(632, 219, 'Carlos', 'Castro Martínez', '24400037', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099376984', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(633, 220, 'Laura', 'López Gutiérrez', '32442857', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099466642', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(634, 221, 'Ana', 'Herrera Fernández', '31588424', 'MASCULINO', 5, 2, 'Policía', NULL, '099862698', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(635, 222, 'Carlos', 'Peña Ramírez', '38027334', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099637842', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(636, 223, 'Patricia', 'Restrepo Ospina', '59550246', 'FEMENINO', 5, 1, 'Policía', 'VENTANILLA', '099908919', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(637, 224, 'Esperanza', 'Sánchez Moreno', '86583035', 'FEMENINO', 5, 1, 'Policía', 'VENTANILLA', '099590934', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(638, 225, 'Andrés', 'Castro Herrera', '57654918', 'FEMENINO', 4, 1, 'Policía', 'VENTANILLA', '099905634', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(639, 226, 'Roberto', 'Cardona Jiménez', '86764004', 'MASCULINO', 3, 1, 'Policía', NULL, '099933038', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(640, 227, 'Adriana', 'Álvarez Sánchez', '36200929', 'MASCULINO', 5, 1, 'Policía', 'VENTANILLA', '099667332', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(641, 228, 'Laura', 'Herrera Jiménez', '95292217', 'FEMENINO', 5, 2, 'Policía', 'VENTANILLA', '099987192', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(642, 229, 'Carlos', 'López López', '66868700', 'MASCULINO', 4, NULL, 'Policía', 'TELEFONISTA', '099837591', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(643, 230, 'Patricia', 'García García', '95757128', 'FEMENINO', 4, NULL, 'Policía', 'TELEFONISTA', '099791493', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(644, 231, 'Carmen', 'Restrepo Herrera', '16978664', 'MASCULINO', 4, 1, 'Policía', 'TELEFONISTA', '099429632', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(645, 232, 'Patricia', 'Peña Jiménez', '33292078', 'FEMENINO', 2, 2, 'Policía', 'VENTANILLA', '099389376', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(646, 233, 'Mónica', 'Fernández Fernández', '81540265', 'FEMENINO', 5, 1, 'Policía', 'TELEFONISTA', '099904497', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(647, 234, 'Carlos', 'Díaz Martínez', '47117060', 'MASCULINO', 4, NULL, 'Policía', 'TELEFONISTA', '099552895', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(648, 235, 'Ana', 'Castro López', '43283853', 'FEMENINO', 2, 1, 'Policía', NULL, '099296181', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(649, 236, 'Laura', 'Álvarez González', '41762160', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099681936', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(650, 237, 'Silvia', 'García Herrera', '27360553', 'MASCULINO', 1, 1, 'Policía', NULL, '099777165', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(651, 238, 'Ana', 'Herrera Herrera', '69968192', 'MASCULINO', 3, 2, 'Policía', 'VENTANILLA', '099891976', 1, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(652, 239, 'Roberto', 'Castro García', '42320842', 'FEMENINO', 3, 2, 'Policía', 'TELEFONISTA', '099996200', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(653, 240, 'Carmen', 'Peña Jiménez', '99339357', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099267277', 2, 15, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:31:59', '2025-09-09 00:31:59'),
(654, 241, 'Pedro', 'Herrera Peña', '76285489', 'MASCULINO', 2, NULL, 'Policía', NULL, '099780883', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:08', '2025-09-09 00:33:08'),
(655, 242, 'Claudia', 'Cardona Moreno', '13359656', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099821567', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(656, 243, 'Ana', 'Vargas González', '67757427', 'FEMENINO', 3, NULL, 'Policía', NULL, '099350946', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(657, 244, 'Fernando', 'González Herrera', '19241802', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099190707', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(658, 245, 'María', 'López Díaz', '53339763', 'MASCULINO', 2, NULL, 'Policía', 'TELEFONISTA', '099286040', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(659, 246, 'Silvia', 'Álvarez López', '52794759', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099826463', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(660, 247, 'Esperanza', 'Herrera Fernández', '17675837', 'MASCULINO', 2, NULL, 'Policía', NULL, '099910732', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(661, 248, 'Ricardo', 'López Gutiérrez', '45010545', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099671508', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(662, 249, 'Carlos', 'Castro Peña', '42026083', 'MASCULINO', 2, NULL, 'Policía', NULL, '099524017', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(663, 250, 'Javier', 'Moreno Vargas', '33658458', 'MASCULINO', 3, 1, 'Policía', NULL, '099763327', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(664, 251, 'Pedro', 'Peña Jiménez', '40092335', 'MASCULINO', 4, NULL, 'Policía', 'TELEFONISTA', '099513802', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(665, 252, 'María', 'Castro Vargas', '64366519', 'FEMENINO', 1, NULL, 'Policía', NULL, '099106109', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(666, 253, 'Mauricio', 'Jiménez García', '14509244', 'FEMENINO', 1, NULL, 'Policía', NULL, '099743178', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(667, 254, 'Javier', 'Herrera López', '26793969', 'FEMENINO', 1, 1, 'Policía', 'TELEFONISTA', '099363237', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(668, 255, 'Ricardo', 'Díaz Rodríguez', '67701540', 'FEMENINO', 3, 1, 'Policía', 'VENTANILLA', '099932014', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(669, 256, 'Adriana', 'Herrera Herrera', '63101760', 'MASCULINO', 3, NULL, 'Policía', 'VENTANILLA', '099825363', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(670, 257, 'Javier', 'Jiménez López', '85440746', 'FEMENINO', 5, 1, 'Policía', 'VENTANILLA', '099373405', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(671, 258, 'Mónica', 'Ospina Cardona', '82644465', 'MASCULINO', 1, 2, 'Policía', NULL, '099889800', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(672, 259, 'Fernando', 'Ramírez Jiménez', '67903328', 'MASCULINO', 2, 1, 'Policía', NULL, '099247222', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(673, 260, 'Laura', 'Moreno Ospina', '39719766', 'FEMENINO', 3, 1, 'Policía', 'VENTANILLA', '099163588', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(674, 261, 'Roberto', 'Moreno González', '66334762', 'FEMENINO', 2, 2, 'Policía', NULL, '099300420', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(675, 262, 'Diego', 'López Restrepo', '20961161', 'FEMENINO', 3, NULL, 'Policía', NULL, '099933838', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(676, 263, 'Pedro', 'González Herrera', '87017629', 'FEMENINO', 2, NULL, 'Policía', 'TELEFONISTA', '099182815', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(677, 264, 'Ana', 'Ramírez Ospina', '65749149', 'FEMENINO', 4, 2, 'Policía', 'VENTANILLA', '099422843', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(678, 265, 'María', 'Herrera Díaz', '87025919', 'MASCULINO', 4, 2, 'Policía', 'TELEFONISTA', '099397348', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(679, 266, 'Carmen', 'Moreno Vargas', '91064371', 'FEMENINO', 2, 2, 'Policía', NULL, '099186716', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(680, 267, 'Juan', 'Herrera Ospina', '82753089', 'MASCULINO', 1, 1, 'Policía', NULL, '099585292', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(681, 268, 'Laura', 'Herrera Sánchez', '12597287', 'MASCULINO', 4, 1, 'Policía', 'VENTANILLA', '099725924', 2, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(682, 269, 'Carmen', 'Fernández Álvarez', '98104422', 'MASCULINO', 3, 2, 'Policía', NULL, '099528219', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(683, 270, 'Mónica', 'Sánchez Álvarez', '94332636', 'MASCULINO', 1, 2, 'Policía', NULL, '099447144', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(684, 271, 'Claudia', 'López Ospina', '63823058', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099418396', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(685, 272, 'Javier', 'López González', '68275808', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099998819', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(686, 273, 'Patricia', 'Cardona García', '64208163', 'MASCULINO', 4, NULL, 'Policía', NULL, '099410314', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(687, 274, 'Fernando', 'Fernández Gutiérrez', '55656324', 'FEMENINO', 2, NULL, 'Policía', 'TELEFONISTA', '099164011', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(688, 275, 'Roberto', 'Castro Rodríguez', '78237383', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099305381', 1, 5, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(689, 276, 'Mónica', 'López Vargas', '60234786', 'FEMENINO', 3, 1, 'Policía', NULL, '099685841', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(690, 277, 'Javier', 'Álvarez Fernández', '92825127', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099386583', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(691, 278, 'Carmen', 'Moreno Moreno', '35804607', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099251621', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(692, 279, 'Ana', 'Gutiérrez Fernández', '26249139', 'FEMENINO', 2, NULL, 'Policía', 'TELEFONISTA', '099962105', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(693, 280, 'Fernando', 'Restrepo Castro', '21134313', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099841616', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(694, 281, 'Claudia', 'Moreno Díaz', '14341395', 'MASCULINO', 5, 2, 'Policía', NULL, '099596126', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(695, 282, 'Ana', 'Ospina Vargas', '67017900', 'FEMENINO', 2, NULL, 'Policía', NULL, '099454219', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(696, 283, 'Carmen', 'López Peña', '42201913', 'FEMENINO', 2, 1, 'Policía', 'VENTANILLA', '099562109', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(697, 284, 'Patricia', 'Jiménez González', '20607932', 'MASCULINO', 5, NULL, 'Policía', 'TELEFONISTA', '099196793', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(698, 285, 'Claudia', 'Restrepo Díaz', '26404767', 'MASCULINO', 3, 2, 'Policía', 'VENTANILLA', '099876002', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(699, 286, 'Esperanza', 'Herrera Vargas', '96910602', 'MASCULINO', 3, NULL, 'Policía', 'TELEFONISTA', '099481679', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(700, 287, 'Javier', 'González Fernández', '64041024', 'MASCULINO', 1, 2, 'Policía', NULL, '099890166', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(701, 288, 'Carlos', 'García Gutiérrez', '80309146', 'MASCULINO', 5, 1, 'Policía', 'VENTANILLA', '099274883', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(702, 289, 'Claudia', 'Ramírez Gutiérrez', '49104418', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099234844', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(703, 290, 'Roberto', 'Martínez Moreno', '54302982', 'MASCULINO', 1, NULL, 'Policía', NULL, '099203054', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(704, 291, 'Fernando', 'García Herrera', '51977482', 'FEMENINO', 1, NULL, 'Policía', 'TELEFONISTA', '099815591', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(705, 292, 'Roberto', 'Jiménez Gutiérrez', '63324637', 'MASCULINO', 3, NULL, 'Policía', 'TELEFONISTA', '099167768', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(706, 293, 'Javier', 'López López', '90017641', 'MASCULINO', 2, 1, 'Policía', NULL, '099722895', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(707, 294, 'Diego', 'Restrepo Peña', '40788211', 'MASCULINO', 1, NULL, 'Policía', 'TELEFONISTA', '099661012', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(708, 295, 'Ricardo', 'Vargas Martínez', '16468861', 'FEMENINO', 1, 1, 'Policía', 'TELEFONISTA', '099813446', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(709, 296, 'Ricardo', 'Ramírez Cardona', '81667272', 'MASCULINO', 4, 1, 'Policía', 'TELEFONISTA', '099192716', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(710, 297, 'Diego', 'Vargas Herrera', '65986848', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099791156', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(711, 298, 'Juan', 'Ospina Rodríguez', '81784958', 'MASCULINO', 4, NULL, 'Policía', 'TELEFONISTA', '099756139', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(712, 299, 'Patricia', 'Gutiérrez Fernández', '52492864', 'MASCULINO', 1, NULL, 'Policía', 'VENTANILLA', '099240524', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(713, 300, 'Pedro', 'Sánchez Jiménez', '63319587', 'MASCULINO', 5, NULL, 'Policía', 'TELEFONISTA', '099146893', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(714, 301, 'María', 'González López', '35200748', 'FEMENINO', 1, NULL, 'Policía', NULL, '099100267', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(715, 302, 'Patricia', 'Restrepo Ospina', '29254994', 'MASCULINO', 5, 2, 'Policía', 'TELEFONISTA', '099253049', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(716, 303, 'María', 'López Jiménez', '26981342', 'MASCULINO', 3, NULL, 'Policía', NULL, '099132965', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(717, 304, 'Esperanza', 'Restrepo Fernández', '93370243', 'MASCULINO', 2, 1, 'Policía', 'TELEFONISTA', '099214283', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(718, 305, 'Silvia', 'Rodríguez Cardona', '77438642', 'MASCULINO', 1, NULL, 'Policía', NULL, '099294139', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(719, 306, 'Roberto', 'García Peña', '89965350', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099135747', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(720, 307, 'Mauricio', 'Peña Castro', '94406600', 'MASCULINO', 1, 1, 'Policía', 'TELEFONISTA', '099882185', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(721, 308, 'Mauricio', 'Sánchez Álvarez', '42431609', 'MASCULINO', 1, NULL, 'Policía', NULL, '099269407', 2, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(722, 309, 'Carmen', 'Sánchez Ospina', '85672529', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099932742', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(723, 310, 'Juan', 'Moreno Castro', '92873665', 'MASCULINO', 2, 2, 'Policía', 'TELEFONISTA', '099647810', 1, 11, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(724, 311, 'Adriana', 'Moreno Ospina', '47071080', 'MASCULINO', 1, NULL, 'Policía', NULL, '099149076', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(725, 312, 'Carlos', 'Restrepo López', '25098332', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099261942', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(726, 313, 'Ana', 'Moreno Sánchez', '89840580', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099196071', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(727, 314, 'Pedro', 'Álvarez Ramírez', '56284615', 'FEMENINO', 2, 1, 'Policía', 'TELEFONISTA', '099320854', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(728, 315, 'María', 'Ospina Díaz', '34551217', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099962655', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(729, 316, 'Diego', 'Moreno Díaz', '12569299', 'FEMENINO', 2, NULL, 'Policía', NULL, '099586702', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(730, 317, 'Andrés', 'Ospina González', '99533852', 'FEMENINO', 5, 2, 'Policía', 'VENTANILLA', '099915708', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(731, 318, 'Pedro', 'Jiménez Díaz', '91808627', 'FEMENINO', 3, 1, 'Policía', 'TELEFONISTA', '099412415', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(732, 319, 'Mauricio', 'Castro Álvarez', '53236728', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099396010', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(733, 320, 'Laura', 'Sánchez Martínez', '73933836', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099198156', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(734, 321, 'Andrés', 'Fernández Rodríguez', '50206881', 'FEMENINO', 1, 2, 'Policía', 'TELEFONISTA', '099689338', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(735, 322, 'Adriana', 'Castro Restrepo', '35018324', 'FEMENINO', 2, 2, 'Policía', 'VENTANILLA', '099276235', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(736, 323, 'María', 'Díaz Peña', '70597900', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099989648', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(737, 324, 'Andrés', 'Rodríguez Rodríguez', '10034498', 'MASCULINO', 1, 1, 'Policía', 'TELEFONISTA', '099439794', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(738, 325, 'Ricardo', 'Ospina Jiménez', '63444463', 'FEMENINO', 2, NULL, 'Policía', NULL, '099219938', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(739, 326, 'Patricia', 'Ramírez Restrepo', '59528630', 'FEMENINO', 3, NULL, 'Policía', 'TELEFONISTA', '099141719', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(740, 327, 'Mauricio', 'Castro Castro', '68521283', 'MASCULINO', 5, 2, 'Policía', 'VENTANILLA', '099340768', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(741, 328, 'Carmen', 'Jiménez Cardona', '31552192', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099873538', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(742, 329, 'Esperanza', 'Vargas Díaz', '77819071', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099400680', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(743, 330, 'Andrés', 'Ramírez Restrepo', '87410259', 'FEMENINO', 3, 2, 'Policía', 'TELEFONISTA', '099732655', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(744, 331, 'Juan', 'Martínez Díaz', '85149813', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099579217', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(745, 332, 'Carlos', 'González Restrepo', '54316055', 'MASCULINO', 2, NULL, 'Policía', NULL, '099210190', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(746, 333, 'Mónica', 'Díaz Fernández', '31652931', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099612130', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(747, 334, 'Roberto', 'Sánchez Rodríguez', '16843863', 'FEMENINO', 3, 2, 'Policía', 'VENTANILLA', '099376492', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(748, 335, 'Adriana', 'Martínez López', '56204777', 'FEMENINO', 3, NULL, 'Policía', 'TELEFONISTA', '099316783', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(749, 336, 'Adriana', 'Cardona Restrepo', '65748076', 'FEMENINO', 5, 1, 'Policía', 'VENTANILLA', '099948951', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(750, 337, 'Patricia', 'Fernández Sánchez', '75057800', 'MASCULINO', 5, 1, 'Policía', NULL, '099236002', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(751, 338, 'Laura', 'Peña Castro', '60189893', 'FEMENINO', 2, 1, 'Policía', NULL, '099698336', 1, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(752, 339, 'Adriana', 'Vargas González', '54549733', 'FEMENINO', 5, NULL, 'Policía', 'VENTANILLA', '099285617', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09'),
(753, 340, 'Carlos', 'Díaz Rodríguez', '59447499', 'FEMENINO', 3, NULL, 'Policía', 'VENTANILLA', '099777985', 2, 7, 11, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-09 00:33:09', '2025-09-09 00:33:09');

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
(2, 'REGIONAL', 'Región Regional', 1, '2025-06-25 18:09:29', '2025-06-25 18:09:29'),
(3, 'GRUPO DOMINGO', 'GRUPO DOMINGO', 1, '2025-09-16 23:25:52', '2025-09-16 23:26:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retornos_ausencias`
--

CREATE TABLE `retornos_ausencias` (
  `id` int NOT NULL,
  `policia_id` int NOT NULL,
  `ausencia_id` int NOT NULL,
  `fecha_retorno` date NOT NULL,
  `posicion_asignada` int NOT NULL COMMENT 'Posición asignada entre 1-5 por prioridad',
  `procesado` tinyint(1) DEFAULT '0' COMMENT 'Si ya fue procesado en una guardia',
  `guardia_asignada_id` int DEFAULT NULL COMMENT 'ID de la guardia donde fue asignado',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

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
(1, 'Junta Medica', 'Ausencia por motivos médicos', 0, '2025-06-25 15:54:11'),
(2, 'Vacaciones', '', 0, '2025-08-13 16:26:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_grados`
--

CREATE TABLE `tipo_grados` (
  `id` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci NOT NULL,
  `nivel_jerarquia` int NOT NULL,
  `abreviatura` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT NULL,
  `grado_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `tipo_grados`
--

INSERT INTO `tipo_grados` (`id`, `nombre`, `nivel_jerarquia`, `abreviatura`, `grado_id`, `created_at`, `updated_at`) VALUES
(1, 'Comisario Principal', 1, 'Crio Princ', 1, '2025-06-17 04:33:23', '2025-06-18 19:47:44'),
(2, 'Comisario', 2, 'Com.', 1, '2025-06-18 19:47:30', '2025-06-18 20:53:34'),
(3, 'Subcomisario', 3, 'SUBCOM.', 1, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(4, 'Oficial Inspector', 4, 'OF. INSP.', 2, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(5, 'Oficial Primero', 5, 'OF. 1°', 2, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(6, 'Oficial Segundo', 6, 'OF. 2°', 2, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(7, 'Oficial Ayudante', 7, 'OF. AYD.', 2, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(8, 'Suboficial Superior', 8, 'SUBOF. SUP.', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(9, 'Suboficial Principal', 9, 'SUBOF. PPAL.', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(10, 'Suboficial Mayor', 10, 'SUBOF. MY.', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(11, 'Suboficial Inspector', 11, 'SUBOF. INSP.', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(12, 'Suboficial Primero', 12, 'SUBOF. 1°', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(13, 'Suboficial Segundo', 13, 'SUBOF. 2°', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(14, 'Suboficial Ayudante', 14, 'SUBOF. AYD.', 3, '2025-06-18 20:56:35', '2025-06-18 20:56:35'),
(15, 'Funcionario/a', 15, 'FUNC.', 4, '2025-06-18 20:56:35', '2025-07-28 23:05:16');

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
  `rol` enum('ADMIN','SUPERADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'ADMIN',
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_usuario`, `contraseña`, `nombre_completo`, `email`, `rol`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'Admin', '$2y$10$9g5OUkPYc66Pf0q0nFATi.zmI3Af0vFCBfTRttYuvqlYZf4l9EaXe', 'Admin Marcelo', 'admin@gmail.com', 'ADMIN', 1, '2025-06-25 15:54:58', '2025-06-25 15:54:58'),
(2, 'Marcelo', '$2y$10$hqrt6SIRRav3jh5OqwblDuSGaR4HjCSti3m3fwfhcJZ7xsRKPR49y', 'marcelo', 'mark@gmail.com', 'ADMIN', 1, '2025-08-21 02:54:46', '2025-08-21 02:54:46'),
(4, 'superadmin', '$2y$12$KBi6t5DNcUErAhYFfvexTuf6BvsCap3NjiyrjMrxHugzzbqsS5v0S', 'Super Administrador', 'superadmin@sistemarh.com', 'SUPERADMIN', 1, '2025-09-08 23:11:04', '2025-09-08 23:11:04');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_disponibilidad_guardias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_disponibilidad_guardias` (
`id` int
,`legajo` int
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`grado` varchar(100)
,`nivel_jerarquia` int
,`especialidad` varchar(150)
,`cargo` varchar(150)
,`telefono` varchar(20)
,`lugar_guardia` varchar(100)
,`region` varchar(50)
,`comisionamiento` varchar(200)
,`fecha_ingreso` timestamp
,`antiguedad_dias` int
,`disponibilidad` varchar(12)
,`fecha_fin_ausencia` date
,`ultima_guardia` date
,`dias_desde_ultima_guardia` int
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_disponibilidad_policias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_disponibilidad_policias` (
`id` int
,`nombre` varchar(100)
,`apellido` varchar(100)
,`cin` varchar(20)
,`grado` varchar(100)
,`especialidad` varchar(150)
,`lugar_guardia` varchar(100)
,`lugar_guardia_id` int
,`zona` varchar(50)
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
-- Indices de la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha_orden` (`fecha_guardia`,`orden_dia`),
  ADD KEY `idx_sector_posicion` (`sector_id`,`posicion_sector`),
  ADD KEY `fk_asignaciones_temp_policia` (`policia_id`),
  ADD KEY `fk_asignaciones_temp_sector` (`sector_id`);

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
  ADD KEY `idx_ausencias_vigentes` (`policia_id`,`fecha_inicio`,`fecha_fin`,`estado`),
  ADD KEY `idx_fechas_estado_policia` (`fecha_inicio`,`fecha_fin`,`estado`,`policia_id`),
  ADD KEY `idx_tipo_estado` (`tipo_ausencia_id`,`estado`);

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
-- Indices de la tabla `guardias_asignaciones`
--
ALTER TABLE `guardias_asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guardia` (`guardia_id`),
  ADD KEY `idx_policia` (`policia_id`),
  ADD KEY `idx_puesto` (`puesto`);

--
-- Indices de la tabla `guardias_generadas`
--
ALTER TABLE `guardias_generadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fecha` (`fecha_guardia`),
  ADD UNIQUE KEY `unique_orden_dia` (`orden_dia`),
  ADD KEY `idx_fecha` (`fecha_guardia`),
  ADD KEY `idx_region` (`region`);

--
-- Indices de la tabla `guardias_generadas_detalle`
--
ALTER TABLE `guardias_generadas_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_guardia_lugar` (`guardia_generada_id`,`lugar_guardia_id`),
  ADD KEY `idx_policia_fecha` (`policia_id`,`guardia_generada_id`);

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
  ADD KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_usuario_fechas` (`usuario_id`,`fecha_inicio`,`fecha_fin`);

--
-- Indices de la tabla `historial_guardias_policia`
--
ALTER TABLE `historial_guardias_policia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `guardia_id` (`guardia_id`),
  ADD KEY `idx_policia_fecha` (`policia_id`,`fecha_guardia`),
  ADD KEY `idx_fecha` (`fecha_guardia`);

--
-- Indices de la tabla `intercambios_guardias`
--
ALTER TABLE `intercambios_guardias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activo_fecha` (`activo`,`fecha_intercambio`),
  ADD KEY `fk_intercambios_guardias_policias` (`policia_id`),
  ADD KEY `fk_intercambios_guardias_ausencias` (`ausencia_id`),
  ADD KEY `fk_intercambios_guardias_lugar_original` (`lugar_original_id`),
  ADD KEY `fk_intercambios_guardias_lugar_intercambio` (`lugar_intercambio_id`),
  ADD KEY `fk_intercambios_guardias_usuarios` (`usuario_id`);

--
-- Indices de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policia_posicion` (`policia_id`,`posicion`);

--
-- Indices de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nombre_zona` (`nombre`,`zona`);

--
-- Indices de la tabla `orden_dia`
--
ALTER TABLE `orden_dia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_numero_orden` (`numero_orden`),
  ADD KEY `idx_año` (`año`),
  ADD KEY `idx_numero` (`numero`);

--
-- Indices de la tabla `orden_junta_medica_telefonista`
--
ALTER TABLE `orden_junta_medica_telefonista`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_policia_ausencia` (`policia_id`,`ausencia_id`),
  ADD KEY `idx_orden_anotacion` (`orden_anotacion`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_fecha_anotacion` (`fecha_anotacion`),
  ADD KEY `idx_activo_orden` (`activo`,`orden_anotacion`),
  ADD KEY `fk_orden_junta_medica_ausencias` (`ausencia_id`),
  ADD KEY `fk_orden_junta_medica_lugares` (`lugar_guardia_original_id`);

--
-- Indices de la tabla `policias`
--
ALTER TABLE `policias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `legajo` (`legajo`),
  ADD UNIQUE KEY `cin` (`cin`),
  ADD KEY `idx_grado` (`grado_id`),
  ADD KEY `idx_especialidad` (`especialidad_id`),
  ADD KEY `idx_region` (`region_id`),
  ADD KEY `idx_lugar_guardia` (`lugar_guardia_id`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_nombre_completo` (`nombre`,`apellido`),
  ADD KEY `idx_grado_region_activo` (`grado_id`,`region_id`,`activo`),
  ADD KEY `idx_genero_grado` (`genero`,`grado_id`),
  ADD KEY `fk_policias_lugar_guardia_reserva` (`lugar_guardia_reserva_id`);

--
-- Indices de la tabla `regiones`
--
ALTER TABLE `regiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `retornos_ausencias`
--
ALTER TABLE `retornos_ausencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `policia_ausencia` (`policia_id`,`ausencia_id`),
  ADD KEY `idx_fecha_retorno` (`fecha_retorno`),
  ADD KEY `idx_procesado` (`procesado`),
  ADD KEY `idx_posicion_asignada` (`posicion_asignada`),
  ADD KEY `fk_retorno_ausencia` (`ausencia_id`),
  ADD KEY `fk_retorno_guardia_asignada` (`guardia_asignada_id`);

--
-- Indices de la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha_estado` (`fecha_servicio`,`estado`),
  ADD KEY `idx_jefe_servicio_fecha` (`jefe_servicio_id`,`fecha_servicio`);

--
-- Indices de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `tipo_grados`
--
ALTER TABLE `tipo_grados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tipo_grados_grados` (`grado_id`);

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
-- AUTO_INCREMENT de la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `ausencias`
--
ALTER TABLE `ausencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `grados`
--
ALTER TABLE `grados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `guardias_asignaciones`
--
ALTER TABLE `guardias_asignaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guardias_generadas`
--
ALTER TABLE `guardias_generadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `guardias_generadas_detalle`
--
ALTER TABLE `guardias_generadas_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=371;

--
-- AUTO_INCREMENT de la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_guardias_policia`
--
ALTER TABLE `historial_guardias_policia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `intercambios_guardias`
--
ALTER TABLE `intercambios_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8273;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000000;

--
-- AUTO_INCREMENT de la tabla `orden_dia`
--
ALTER TABLE `orden_dia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `orden_junta_medica_telefonista`
--
ALTER TABLE `orden_junta_medica_telefonista`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=754;

--
-- AUTO_INCREMENT de la tabla `regiones`
--
ALTER TABLE `regiones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `retornos_ausencias`
--
ALTER TABLE `retornos_ausencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servicios`
--
ALTER TABLE `servicios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_ausencias`
--
ALTER TABLE `tipos_ausencias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `tipo_grados`
--
ALTER TABLE `tipo_grados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_disponibilidad_guardias`
--
DROP TABLE IF EXISTS `vista_disponibilidad_guardias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_disponibilidad_guardias`  AS SELECT `p`.`id` AS `id`, `p`.`legajo` AS `legajo`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `g`.`nombre` AS `grado`, `g`.`nivel_jerarquia` AS `nivel_jerarquia`, `e`.`nombre` AS `especialidad`, `p`.`cargo` AS `cargo`, `p`.`telefono` AS `telefono`, `lg`.`nombre` AS `lugar_guardia`, `lg`.`zona` AS `region`, `p`.`comisionamiento` AS `comisionamiento`, `p`.`created_at` AS `fecha_ingreso`, (to_days(curdate()) - to_days(`p`.`created_at`)) AS `antiguedad_dias`, (case when ((`a`.`id` is not null) and (`a`.`fecha_fin` >= curdate())) then 'CON_AUSENCIA' else 'DISPONIBLE' end) AS `disponibilidad`, `a`.`fecha_fin` AS `fecha_fin_ausencia`, `hgp`.`fecha_guardia` AS `ultima_guardia`, coalesce((to_days(curdate()) - to_days(`hgp`.`fecha_guardia`)),999) AS `dias_desde_ultima_guardia` FROM (((((`policias` `p` left join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `especialidades` `e` on((`p`.`especialidad_id` = `e`.`id`))) left join `lugares_guardias` `lg` on((`p`.`lugar_guardia_id` = `lg`.`id`))) left join `ausencias` `a` on(((`p`.`id` = `a`.`policia_id`) and (`a`.`fecha_inicio` <= curdate()) and (`a`.`fecha_fin` >= curdate())))) left join (select `historial_guardias_policia`.`policia_id` AS `policia_id`,max(`historial_guardias_policia`.`fecha_guardia`) AS `fecha_guardia` from `historial_guardias_policia` group by `historial_guardias_policia`.`policia_id`) `hgp` on((`p`.`id` = `hgp`.`policia_id`))) WHERE (`p`.`activo` = 1) ORDER BY (case when (`a`.`id` is not null) then 1 else 0 end) ASC, `g`.`nivel_jerarquia` ASC, coalesce((to_days(curdate()) - to_days(`hgp`.`fecha_guardia`)),999) DESC, `p`.`legajo` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_disponibilidad_policias`
--
DROP TABLE IF EXISTS `vista_disponibilidad_policias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_disponibilidad_policias`  AS SELECT `p`.`id` AS `id`, `p`.`nombre` AS `nombre`, `p`.`apellido` AS `apellido`, `p`.`cin` AS `cin`, `g`.`nombre` AS `grado`, `e`.`nombre` AS `especialidad`, `lg`.`nombre` AS `lugar_guardia`, `lg`.`id` AS `lugar_guardia_id`, `lg`.`zona` AS `zona`, (case when exists(select 1 from `ausencias` `a` where ((`a`.`policia_id` = `p`.`id`) and (`a`.`estado` = 'APROBADA') and (curdate() between `a`.`fecha_inicio` and coalesce(`a`.`fecha_fin`,curdate())))) then 'NO DISPONIBLE' else 'DISPONIBLE' end) AS `disponibilidad` FROM (((`policias` `p` left join `grados` `g` on((`p`.`grado_id` = `g`.`id`))) left join `especialidades` `e` on((`p`.`especialidad_id` = `e`.`id`))) left join `lugares_guardias` `lg` on((`p`.`lugar_guardia_id` = `lg`.`id`))) WHERE (`p`.`activo` = 1) ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones_servicios`
--
ALTER TABLE `asignaciones_servicios`
  ADD CONSTRAINT `fk_asignaciones_servicios_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asignaciones_servicios_servicios` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  ADD CONSTRAINT `fk_asignaciones_temp_policia` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asignaciones_temp_sector` FOREIGN KEY (`sector_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ausencias`
--
ALTER TABLE `ausencias`
  ADD CONSTRAINT `fk_ausencias_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ausencias_tipos_ausencias` FOREIGN KEY (`tipo_ausencia_id`) REFERENCES `tipos_ausencias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ausencias_usuarios` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `guardias_asignaciones`
--
ALTER TABLE `guardias_asignaciones`
  ADD CONSTRAINT `guardias_asignaciones_ibfk_1` FOREIGN KEY (`guardia_id`) REFERENCES `guardias_generadas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guardias_asignaciones_ibfk_2` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`);

--
-- Filtros para la tabla `guardias_realizadas`
--
ALTER TABLE `guardias_realizadas`
  ADD CONSTRAINT `fk_guardias_realizadas_lugares` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_guardias_realizadas_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `guardias_semanales`
--
ALTER TABLE `guardias_semanales`
  ADD CONSTRAINT `fk_guardias_semanales_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_guardias_policia`
--
ALTER TABLE `historial_guardias_policia`
  ADD CONSTRAINT `historial_guardias_policia_ibfk_1` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`),
  ADD CONSTRAINT `historial_guardias_policia_ibfk_2` FOREIGN KEY (`guardia_id`) REFERENCES `guardias_generadas` (`id`);

--
-- Filtros para la tabla `intercambios_guardias`
--
ALTER TABLE `intercambios_guardias`
  ADD CONSTRAINT `fk_intercambios_guardias_ausencias` FOREIGN KEY (`ausencia_id`) REFERENCES `ausencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_intercambios_guardias_lugar_intercambio` FOREIGN KEY (`lugar_intercambio_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_intercambios_guardias_lugar_original` FOREIGN KEY (`lugar_original_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_intercambios_guardias_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_intercambios_guardias_usuarios` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `lista_guardias`
--
ALTER TABLE `lista_guardias`
  ADD CONSTRAINT `fk_lista_guardias_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `orden_junta_medica_telefonista`
--
ALTER TABLE `orden_junta_medica_telefonista`
  ADD CONSTRAINT `fk_orden_junta_medica_ausencias` FOREIGN KEY (`ausencia_id`) REFERENCES `ausencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orden_junta_medica_lugares` FOREIGN KEY (`lugar_guardia_original_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orden_junta_medica_policias` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `policias`
--
ALTER TABLE `policias`
  ADD CONSTRAINT `fk_policias_especialidades` FOREIGN KEY (`especialidad_id`) REFERENCES `especialidades` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_policias_lugar_guardia` FOREIGN KEY (`lugar_guardia_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_policias_lugar_guardia_reserva` FOREIGN KEY (`lugar_guardia_reserva_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_policias_regiones` FOREIGN KEY (`region_id`) REFERENCES `regiones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_policias_tipo_grados` FOREIGN KEY (`grado_id`) REFERENCES `tipo_grados` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `retornos_ausencias`
--
ALTER TABLE `retornos_ausencias`
  ADD CONSTRAINT `fk_retorno_ausencia` FOREIGN KEY (`ausencia_id`) REFERENCES `ausencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retorno_guardia_asignada` FOREIGN KEY (`guardia_asignada_id`) REFERENCES `guardias_generadas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_retorno_policia` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `servicios`
--
ALTER TABLE `servicios`
  ADD CONSTRAINT `fk_servicios_jefe_servicio` FOREIGN KEY (`jefe_servicio_id`) REFERENCES `policias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `tipo_grados`
--
ALTER TABLE `tipo_grados`
  ADD CONSTRAINT `fk_tipo_grados_grados` FOREIGN KEY (`grado_id`) REFERENCES `grados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
