-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 01-09-2025 a las 01:09:46
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
  `puesto` enum('JEFE_SERVICIO','JEFE_CUARTEL','OFICIAL_GUARDIA','ATENCIÓN TELEFÓNICA EXCLUSIVA','NUMERO_GUARDIA','DATA_CENTER','TENIDA_REGLAMENTO','SANIDAD_GUARDIA') COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Puesto asignado',
  `numero_puesto` int DEFAULT NULL COMMENT 'Número del puesto (para NUMERO_GUARDIA 1-4, SANIDAD_GUARDIA 1-3)',
  `observaciones` text COLLATE utf8mb4_spanish2_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Asignaciones detalladas de personal por guardia';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guardias_generadas`
--

CREATE TABLE `guardias_generadas` (
  `id` int NOT NULL,
  `fecha_guardia` date NOT NULL COMMENT 'Fecha de la guardia',
  `orden_dia` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden del día (ej: 27/2025)',
  `region` enum('CENTRAL','REGIONAL') COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Región asignada según día de semana',
  `estado` enum('PROGRAMADA','ACTIVA','COMPLETADA','CANCELADA') COLLATE utf8mb4_spanish2_ci DEFAULT 'PROGRAMADA',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Guardias generadas por día con orden único';

--
-- Volcado de datos para la tabla `guardias_generadas`
--

INSERT INTO `guardias_generadas` (`id`, `fecha_guardia`, `orden_dia`, `region`, `estado`, `created_at`, `updated_at`) VALUES
(1, '2025-08-27', '28/2025', 'CENTRAL', 'PROGRAMADA', '2025-08-27 01:26:45', '2025-08-27 01:26:45'),
(2, '2025-08-28', '29/2025', 'CENTRAL', 'PROGRAMADA', '2025-08-27 01:35:34', '2025-08-27 01:35:34'),
(3, '2025-09-01', '30/2025', 'CENTRAL', 'PROGRAMADA', '2025-08-31 23:51:12', '2025-08-31 23:51:12'),
(6, '2025-09-02', '31/2025', 'CENTRAL', 'PROGRAMADA', '2025-09-01 00:03:21', '2025-09-01 00:03:21'),
(7, '2025-09-03', '35/2025', 'CENTRAL', 'PROGRAMADA', '2025-09-01 00:05:40', '2025-09-01 00:05:40'),
(8, '2025-09-04', '36/2025', 'CENTRAL', 'PROGRAMADA', '2025-09-01 00:23:14', '2025-09-01 00:23:14');

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
  `puesto` varchar(100) COLLATE utf8mb4_spanish2_ci NOT NULL,
  `orden_dia` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL,
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
(8, 436, 21, 7, 6, '2025-08-31 22:07:58', NULL, 1, 1, '2025-09-01 01:07:58');

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
(743, 414, 1, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(744, 415, 2, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(745, 416, 3, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(746, 417, 4, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(747, 418, 5, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(748, 419, 6, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(749, 420, 7, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(750, 421, 8, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(751, 422, 9, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(752, 423, 10, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(753, 424, 1, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(754, 425, 2, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(755, 426, 3, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(756, 427, 4, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(757, 428, 5, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(758, 429, 6, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(759, 430, 7, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(760, 431, 8, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(761, 432, 9, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(762, 433, 10, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(763, 434, 1, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(764, 435, 2, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(765, 436, 3, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(766, 437, 4, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(767, 438, 5, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(768, 439, 6, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(769, 440, 7, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(770, 441, 8, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(771, 442, 9, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(772, 443, 10, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(773, 444, 1, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(774, 445, 2, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(775, 446, 3, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(776, 447, 4, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(777, 448, 5, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(778, 449, 6, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(779, 450, 7, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(780, 451, 8, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(781, 452, 9, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(782, 453, 10, NULL, NULL, '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(783, 454, 1, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(784, 455, 2, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(785, 456, 3, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(786, 457, 4, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(787, 458, 5, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(788, 459, 6, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(789, 460, 7, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(790, 461, 8, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(791, 462, 9, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(792, 463, 10, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(793, 464, 1, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(794, 465, 2, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(795, 466, 3, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(796, 467, 4, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(797, 468, 5, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(798, 469, 6, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(799, 470, 7, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(800, 471, 8, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(801, 472, 9, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(802, 473, 10, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(803, 474, 1, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(804, 475, 2, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(805, 476, 3, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(806, 477, 4, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(807, 478, 5, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(808, 479, 6, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(809, 480, 7, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(810, 481, 8, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(811, 482, 9, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(812, 483, 10, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(813, 484, 1, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(814, 485, 2, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(815, 486, 3, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(816, 487, 4, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(817, 488, 5, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(818, 489, 6, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(819, 490, 7, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(820, 491, 8, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(821, 492, 9, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(822, 493, 10, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(823, 494, 1, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(824, 495, 2, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(825, 496, 3, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(826, 497, 4, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(827, 498, 5, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(828, 499, 6, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(829, 500, 7, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(830, 501, 8, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(831, 502, 9, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(832, 503, 10, NULL, NULL, '2025-09-01 01:04:03', '2025-09-01 01:04:03');

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
(1, 'JEFE DE SERVICIO', '', '', 'CENTRAL', 1, '2025-09-01 00:57:02', '2025-09-01 00:58:09'),
(2, 'JEFE DE CUARTEL', '', '', 'CENTRAL', 1, '2025-09-01 00:57:18', '2025-09-01 00:58:15'),
(3, 'OFICIAL DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:57:27', '2025-09-01 00:58:25'),
(4, 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '', '', 'CENTRAL', 1, '2025-09-01 00:57:53', '2025-09-01 00:58:31'),
(5, 'NUMERO DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:58:47', '2025-09-01 01:00:38'),
(6, 'CONDUCTOR DE GUARDIA', '', '', 'CENTRAL', 1, '2025-09-01 00:59:28', '2025-09-01 01:00:46'),
(7, 'DE  06:30 HORAS A 22:00 HS GUARDIA Y 22:00 HS AL LLAMADO HASTA 07:00 HS DEL DÍA SIGUIENTE', '', '', 'CENTRAL', 1, '2025-09-01 01:00:06', '2025-09-01 01:00:53'),
(8, 'TENIDA: DE REGLAMENTO CON PLACA IDENTIFICATORIA', '', '', 'CENTRAL', 1, '2025-09-01 01:00:12', '2025-09-01 01:01:00'),
(9, 'SANIDAD DE GUARDIA CON UNIFORME CORRESPONDIENTE', '', '', 'CENTRAL', 1, '2025-09-01 01:00:20', '2025-09-01 01:01:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_dia`
--

CREATE TABLE `orden_dia` (
  `id` int NOT NULL,
  `numero_orden` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden (ej: 27/2025)',
  `año` int NOT NULL COMMENT 'Año del orden',
  `numero` int NOT NULL COMMENT 'Número secuencial del año',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Gestión de números de orden del día únicos';

--
-- Volcado de datos para la tabla `orden_dia`
--

INSERT INTO `orden_dia` (`id`, `numero_orden`, `año`, `numero`, `fecha_creacion`, `activo`) VALUES
(7, '31/2025', 2025, 31, '2025-09-01 00:03:21', 1),
(8, '35/2025', 2025, 35, '2025-09-01 00:05:40', 1),
(9, '36/2025', 2025, 36, '2025-09-01 00:23:14', 1);

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
(7, 436, 21, 7, 1, '2025-09-01 01:07:58', 1);

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
  `estado` enum('DISPONIBLE','NO DISPONIBLE') COLLATE utf8mb4_spanish2_ci DEFAULT 'DISPONIBLE',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `policias`
--

INSERT INTO `policias` (`id`, `legajo`, `nombre`, `apellido`, `cin`, `genero`, `grado_id`, `especialidad_id`, `cargo`, `comisionamiento`, `telefono`, `region_id`, `lugar_guardia_id`, `lugar_guardia_reserva_id`, `observaciones`, `activo`, `estado`, `created_at`, `updated_at`) VALUES
(414, 1, 'María', 'Castro Martínez', '35019969', 'FEMENINO', 2, 2, 'Policía', 'VENTANILLA', '099560596', 2, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(415, 2, 'Carmen', 'Ospina García', '45440994', 'FEMENINO', 5, 2, 'Policía', 'VENTANILLA', '099230259', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(416, 3, 'Andrés', 'Martínez García', '48409468', 'MASCULINO', 5, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099455499', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(417, 4, 'Diego', 'Cardona Ospina', '77751045', 'FEMENINO', 4, NULL, 'Policía', NULL, '099588875', 2, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(418, 5, 'Diego', 'Álvarez Ospina', '13880433', 'FEMENINO', 1, 1, 'Policía', NULL, '099419396', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(419, 6, 'Mauricio', 'Restrepo Fernández', '81653587', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099783131', 2, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(420, 7, 'Ricardo', 'Castro López', '13364993', 'FEMENINO', 1, NULL, 'Policía', NULL, '099572753', 2, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(421, 8, 'Laura', 'Díaz Sánchez', '94530449', 'FEMENINO', 3, 1, 'Policía', NULL, '099400870', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(422, 9, 'Carmen', 'Rodríguez Restrepo', '42823971', 'FEMENINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099461595', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(423, 10, 'Laura', 'Castro Gutiérrez', '18580543', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099643836', 1, 4, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(424, 11, 'Mauricio', 'Vargas Sánchez', '66961706', 'FEMENINO', 5, NULL, 'Policía', NULL, '099282537', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(425, 12, 'Juan', 'Peña Peña', '59718559', 'FEMENINO', 3, 2, 'Policía', NULL, '099546534', 1, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(426, 13, 'Andrés', 'Restrepo Fernández', '28539913', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099856426', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(427, 14, 'Mónica', 'Fernández González', '33167405', 'FEMENINO', 1, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099788594', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(428, 15, 'Silvia', 'Sánchez López', '55286667', 'MASCULINO', 5, NULL, 'Policía', NULL, '099571548', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(429, 16, 'Juan', 'Rodríguez Álvarez', '92119510', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099832525', 1, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(430, 17, 'Laura', 'Gutiérrez Álvarez', '54134802', 'FEMENINO', 3, 1, 'Policía', NULL, '099454215', 1, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(431, 18, 'Pedro', 'Herrera Cardona', '52331141', 'MASCULINO', 2, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099309173', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(432, 19, 'Claudia', 'Díaz Álvarez', '11554263', 'MASCULINO', 3, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099959071', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(433, 20, 'Mónica', 'Ospina Jiménez', '23814847', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099996862', 2, 6, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(434, 21, 'Andrés', 'Jiménez Martínez', '83032719', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099441758', 1, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(435, 22, 'Ana', 'García Jiménez', '47921220', 'FEMENINO', 4, 1, 'Policía', 'VENTANILLA', '099812588', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(436, 23, 'Juan', 'Díaz Castro', '26730484', 'FEMENINO', 5, NULL, 'Policía', NULL, '099207460', 1, 6, 7, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:07:58'),
(437, 24, 'María', 'García López', '95775545', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099137651', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(438, 25, 'Adriana', 'Castro Castro', '25527092', 'MASCULINO', 5, NULL, 'Policía', NULL, '099556847', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(439, 26, 'Fernando', 'Castro Gutiérrez', '23246952', 'FEMENINO', 1, NULL, 'Policía', NULL, '099976259', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(440, 27, 'Silvia', 'López Vargas', '34103159', 'FEMENINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099615441', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(441, 28, 'Mauricio', 'Herrera Castro', '76806507', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099460175', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(442, 29, 'María', 'Herrera Díaz', '49882734', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099215770', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(443, 30, 'Javier', 'Sánchez Moreno', '50341335', 'MASCULINO', 4, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099673137', 2, 7, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(444, 31, 'Javier', 'López Cardona', '13072633', 'MASCULINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099608260', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(445, 32, 'Mauricio', 'Martínez Rodríguez', '23118897', 'MASCULINO', 4, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099185190', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(446, 33, 'Mauricio', 'Restrepo Peña', '57899210', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099920295', 2, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(447, 34, 'Andrés', 'Restrepo Moreno', '25042366', 'MASCULINO', 3, 1, 'Policía', NULL, '099593342', 2, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(448, 35, 'Ana', 'Gutiérrez González', '10941791', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099669960', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(449, 36, 'Fernando', 'Vargas Martínez', '76828144', 'MASCULINO', 4, 1, 'Policía', NULL, '099121753', 2, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(450, 37, 'Fernando', 'González Gutiérrez', '21107948', 'MASCULINO', 1, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099900686', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(451, 38, 'Claudia', 'Herrera Álvarez', '21293108', 'MASCULINO', 3, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099370114', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(452, 39, 'Fernando', 'García Gutiérrez', '40907181', 'MASCULINO', 4, 1, 'Policía', NULL, '099252235', 1, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(453, 40, 'Carlos', 'González Jiménez', '85610107', 'MASCULINO', 4, NULL, 'Policía', NULL, '099722211', 2, 2, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:02', '2025-09-01 01:04:02'),
(454, 41, 'Ana', 'Álvarez Jiménez', '92310944', 'MASCULINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099688717', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(455, 42, 'María', 'Díaz Restrepo', '12654407', 'FEMENINO', 3, NULL, 'Policía', 'VENTANILLA', '099531026', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(456, 43, 'Ricardo', 'Ospina García', '14973483', 'MASCULINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099269083', 1, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(457, 44, 'Patricia', 'Jiménez Vargas', '87483216', 'MASCULINO', 1, 1, 'Policía', NULL, '099612905', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(458, 45, 'Silvia', 'Peña Gutiérrez', '86620966', 'FEMENINO', 2, NULL, 'Policía', 'VENTANILLA', '099410157', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(459, 46, 'Pedro', 'Ramírez Peña', '94369580', 'MASCULINO', 4, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099516158', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(460, 47, 'Mauricio', 'Peña Díaz', '13274603', 'MASCULINO', 2, 2, 'Policía', NULL, '099698184', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(461, 48, 'Andrés', 'González Peña', '54648584', 'FEMENINO', 2, 1, 'Policía', NULL, '099605820', 1, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(462, 49, 'Diego', 'Ospina Rodríguez', '75358549', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099817640', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(463, 50, 'Silvia', 'Cardona Moreno', '59563096', 'FEMENINO', 2, NULL, 'Policía', NULL, '099705040', 2, 1, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),(464, 51, 'Roberto', 'Gutiérrez González', '92298747', 'MASCULINO', 1, 1, 'Policía', 'VENTANILLA', '099909554', 1, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(465, 52, 'Claudia', 'Herrera Gutiérrez', '12631439', 'MASCULINO', 5, 1, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099181569', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(466, 53, 'Diego', 'Sánchez Rodríguez', '89606707', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099906408', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(467, 54, 'Carmen', 'Díaz González', '88792335', 'MASCULINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099437798', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(468, 55, 'Andrés', 'Jiménez López', '11342837', 'FEMENINO', 1, 1, 'Policía', 'VENTANILLA', '099251327', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(469, 56, 'Diego', 'Ospina Sánchez', '31089685', 'MASCULINO', 1, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099734707', 1, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(470, 57, 'Esperanza', 'López Rodríguez', '56916868', 'FEMENINO', 1, 2, 'Policía', NULL, '099602770', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(471, 58, 'Claudia', 'Peña Ospina', '46636952', 'MASCULINO', 3, 2, 'Policía', 'VENTANILLA', '099520512', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(472, 59, 'Ana', 'Martínez Castro', '93083597', 'MASCULINO', 2, NULL, 'Policía', 'VENTANILLA', '099832516', 2, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(473, 60, 'Pedro', 'Cardona Cardona', '47990064', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099484998', 1, 5, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(474, 61, 'Ricardo', 'Díaz Sánchez', '87782216', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099348165', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(475, 62, 'Diego', 'Castro Díaz', '34568012', 'MASCULINO', 2, NULL, 'Policía', NULL, '099128575', 1, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(476, 63, 'Mauricio', 'López Díaz', '69875238', 'MASCULINO', 2, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099831051', 1, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(477, 64, 'Silvia', 'Peña Martínez', '32436859', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099925285', 1, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(478, 65, 'Mauricio', 'Moreno Ospina', '51614652', 'MASCULINO', 3, NULL, 'Policía', 'VENTANILLA', '099444109', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(479, 66, 'Diego', 'Rodríguez Vargas', '38836374', 'MASCULINO', 5, NULL, 'Policía', NULL, '099285259', 1, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(480, 67, 'Carlos', 'González Moreno', '30598517', 'FEMENINO', 1, NULL, 'Policía', NULL, '099756799', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(481, 68, 'Andrés', 'Herrera Martínez', '62291411', 'FEMENINO', 5, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099841581', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(482, 69, 'Adriana', 'Cardona Gutiérrez', '73847653', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099801722', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(483, 70, 'Ana', 'Díaz Sánchez', '29904359', 'FEMENINO', 3, NULL, 'Policía', NULL, '099410287', 2, 3, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(484, 71, 'Pedro', 'Cardona Ramírez', '24955689', 'MASCULINO', 2, 1, 'Policía', NULL, '099485015', 2, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(485, 72, 'Roberto', 'González Moreno', '11726485', 'FEMENINO', 1, NULL, 'Policía', NULL, '099154777', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(486, 73, 'Adriana', 'Restrepo Gutiérrez', '47212341', 'MASCULINO', 4, NULL, 'Policía', 'VENTANILLA', '099802220', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(487, 74, 'Mónica', 'Fernández Cardona', '89776304', 'MASCULINO', 5, 2, 'Policía', 'VENTANILLA', '099672250', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(488, 75, 'Adriana', 'Álvarez Moreno', '57576368', 'MASCULINO', 3, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099468570', 2, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(489, 76, 'Andrés', 'Gutiérrez Restrepo', '29946174', 'MASCULINO', 2, 1, 'Policía', 'VENTANILLA', '099907026', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(490, 77, 'Patricia', 'Sánchez López', '55853903', 'MASCULINO', 1, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099320074', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(491, 78, 'Claudia', 'González García', '57679695', 'MASCULINO', 3, 2, 'Policía', NULL, '099958265', 1, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(492, 79, 'Claudia', 'Jiménez Fernández', '63533760', 'MASCULINO', 4, NULL, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099429817', 2, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(493, 80, 'Mónica', 'Rodríguez Fernández', '95681991', 'MASCULINO', 2, NULL, 'Policía', NULL, '099814353', 2, 9, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(494, 81, 'Diego', 'Díaz López', '70386145', 'FEMENINO', 5, 1, 'Policía', NULL, '099672484', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(495, 82, 'Diego', 'González Jiménez', '28546767', 'MASCULINO', 5, NULL, 'Policía', NULL, '099782107', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(496, 83, 'Adriana', 'Castro Jiménez', '69003366', 'MASCULINO', 5, 2, 'Policía', 'VENTANILLA', '099485919', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(497, 84, 'Roberto', 'Moreno Castro', '35319345', 'MASCULINO', 2, NULL, 'Policía', NULL, '099479625', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(498, 85, 'Carlos', 'Fernández Sánchez', '36023863', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099144641', 1, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(499, 86, 'Fernando', 'Ospina Herrera', '35516464', 'FEMENINO', 1, 2, 'Policía', 'VENTANILLA', '099487637', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(500, 87, 'Mónica', 'Fernández Ramírez', '93854428', 'MASCULINO', 1, 2, 'Policía', 'ATENCIÓN TELEFÓNICA EXCLUSIVA', '099585971', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(501, 88, 'Ricardo', 'Restrepo Castro', '33074608', 'MASCULINO', 5, NULL, 'Policía', 'VENTANILLA', '099907985', 1, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(502, 89, 'Javier', 'García Ospina', '42060270', 'FEMENINO', 1, NULL, 'Policía', 'VENTANILLA', '099812061', 2, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03'),
(503, 90, 'Juan', 'Restrepo Ospina', '11604517', 'FEMENINO', 4, NULL, 'Policía', 'VENTANILLA', '099947730', 1, 8, 6, 'Usuario generado automáticamente para lugar específico', 1, 'DISPONIBLE', '2025-09-01 01:04:03', '2025-09-01 01:04:03');

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
  `rol` enum('ADMIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish2_ci DEFAULT 'ADMIN',
  `activo` tinyint(1) DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre_usuario`, `contraseña`, `nombre_completo`, `email`, `rol`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'Admin', '$2y$10$9g5OUkPYc66Pf0q0nFATi.zmI3Af0vFCBfTRttYuvqlYZf4l9EaXe', 'Admin Marcelo', 'admin@gmail.com', 'ADMIN', 1, '2025-06-25 15:54:58', '2025-06-25 15:54:58'),
(2, 'Marcelo', '$2y$10$hqrt6SIRRav3jh5OqwblDuSGaR4HjCSti3m3fwfhcJZ7xsRKPR49y', 'marcelo', 'mark@gmail.com', 'ADMIN', 1, '2025-08-21 02:54:46', '2025-08-21 02:54:46');

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
  ADD UNIQUE KEY `nombre` (`nombre`);

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `guardias_generadas_detalle`
--
ALTER TABLE `guardias_generadas_detalle`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=833;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `orden_dia`
--
ALTER TABLE `orden_dia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `orden_junta_medica_telefonista`
--
ALTER TABLE `orden_junta_medica_telefonista`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=504;

--
-- AUTO_INCREMENT de la tabla `regiones`
--
ALTER TABLE `regiones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_temporales_guardia`
--

CREATE TABLE `asignaciones_temporales_guardia` (
  `id` int NOT NULL,
  `fecha_guardia` date NOT NULL COMMENT 'Fecha de la guardia',
  `orden_dia` varchar(50) COLLATE utf8mb4_spanish2_ci NOT NULL COMMENT 'Número de orden del día',
  `sector_id` int NOT NULL COMMENT 'ID del sector (lugar_guardia_id)',
  `policia_id` int NOT NULL COMMENT 'ID del policía asignado',
  `posicion_sector` int NOT NULL COMMENT 'Posición dentro del sector (1=primero, 2=segundo, etc.)',
  `posicion_original_lista` int NOT NULL COMMENT 'Posición original en lista_guardias',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish2_ci COMMENT='Tabla temporal para almacenar asignaciones antes de generar PDF';

-- --------------------------------------------------------

--
-- Filtros para la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha_orden` (`fecha_guardia`, `orden_dia`),
  ADD KEY `idx_sector_posicion` (`sector_id`, `posicion_sector`),
  ADD KEY `fk_asignaciones_temp_policia` (`policia_id`),
  ADD KEY `fk_asignaciones_temp_sector` (`sector_id`);

--
-- AUTO_INCREMENT de la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Filtros para la tabla `asignaciones_temporales_guardia`
--
ALTER TABLE `asignaciones_temporales_guardia`
  ADD CONSTRAINT `fk_asignaciones_temp_policia` FOREIGN KEY (`policia_id`) REFERENCES `policias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_asignaciones_temp_sector` FOREIGN KEY (`sector_id`) REFERENCES `lugares_guardias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
