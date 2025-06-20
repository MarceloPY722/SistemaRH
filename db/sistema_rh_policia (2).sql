-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 19-06-2025 a las 18:27:00
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
(711, 1, 1, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(712, 12, 2, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(713, 2, 3, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(714, 3, 4, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(715, 22, 5, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(716, 4, 6, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(717, 23, 7, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(718, 5, 8, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(719, 24, 9, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(720, 6, 10, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(721, 25, 11, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(722, 7, 12, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(723, 26, 13, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(724, 8, 14, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(725, 17, 15, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(726, 27, 16, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(727, 35, 17, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(728, 43, 18, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(729, 51, 19, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(730, 59, 20, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(731, 67, 21, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(732, 9, 22, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(733, 18, 23, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(734, 28, 24, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(735, 36, 25, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(736, 44, 26, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(737, 52, 27, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(738, 60, 28, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(739, 68, 29, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(740, 10, 30, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(741, 19, 31, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(742, 29, 32, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(743, 37, 33, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(744, 45, 34, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(745, 53, 35, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(746, 61, 36, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(747, 69, 37, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(748, 11, 38, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(749, 20, 39, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(750, 30, 40, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(751, 38, 41, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(752, 46, 42, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(753, 54, 43, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(754, 62, 44, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(755, 70, 45, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(756, 13, 46, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(757, 21, 47, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(758, 31, 48, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(759, 39, 49, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(760, 47, 50, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(761, 55, 51, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(762, 63, 52, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(763, 71, 53, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(764, 14, 54, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(765, 32, 55, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(766, 40, 56, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(767, 48, 57, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(768, 56, 58, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(769, 64, 59, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(770, 15, 60, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(771, 33, 61, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(772, 41, 62, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(773, 49, 63, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(774, 57, 64, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(775, 65, 65, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(776, 16, 66, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(777, 34, 67, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(778, 42, 68, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(779, 50, 69, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(780, 58, 70, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56'),
(781, 66, 71, NULL, '2025-06-19 18:18:56', '2025-06-19 18:18:56');

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
(71, 'Celia Raquel', 'Aguilar Insfrán', '8901249', 12, 1, 'JEFA DE LA REGIONAL DE VILLARRICA', 'Regional Villarrica', '0990890123', 'REGIONAL', 7, '2007-03-03', 0, 'Jefe departamental', 1, '2025-06-19 01:08:33', '2025-06-19 01:08:33');

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
(6, 'Suspensión', 'Suspensión disciplinaria', 1, '2025-06-16 21:25:25');

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
(1, 'Admin', '$2y$10$HX1Lf2s93nawpw5LdWZVyeJA1xI0EMczmswQvCFaB3GPqQ7q6CRnO', 'Administrador', 'admin@gmail.com', 'ADMIN', 1, '2025-06-16 21:35:50', '2025-06-16 21:35:50');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=782;

--
-- AUTO_INCREMENT de la tabla `lugares_guardias`
--
ALTER TABLE `lugares_guardias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `policias`
--
ALTER TABLE `policias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

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
