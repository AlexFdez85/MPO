-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 18-04-2026 a las 16:00:16
-- Versión del servidor: 10.6.24-MariaDB-cll-lve-log
-- Versión de PHP: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `apaintsc_a4_mpo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesos_log`
--

CREATE TABLE `accesos_log` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `fecha_hora` datetime DEFAULT current_timestamp(),
  `tipo_acceso` enum('lectura','edicion') DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ajustes_insumos`
--

CREATE TABLE `ajustes_insumos` (
  `id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `comentario` text NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `estado` enum('pendiente','autorizada','rechazada') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `autorizador_id` int(11) DEFAULT NULL,
  `autorizado_en` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ajustes_mp`
--

CREATE TABLE `ajustes_mp` (
  `id` int(10) UNSIGNED NOT NULL,
  `mp_id` int(10) UNSIGNED NOT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT 'positivo=entrada, negativo=salida',
  `comentario` varchar(255) NOT NULL,
  `solicitante_id` int(10) UNSIGNED NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('pendiente','autorizado','rechazado') NOT NULL DEFAULT 'pendiente',
  `autorizado_por` int(10) UNSIGNED DEFAULT NULL,
  `fecha_autorizacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `autorizaciones_compra`
--

CREATE TABLE `autorizaciones_compra` (
  `id` int(10) UNSIGNED NOT NULL,
  `orden_compra_id` int(10) UNSIGNED NOT NULL,
  `autorizador_id` int(10) UNSIGNED NOT NULL,
  `fecha_autorizacion` datetime NOT NULL,
  `comentario` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `datos_envio` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `es_distribuidor` tinyint(1) DEFAULT 0,
  `distribuidor_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `contacto`, `direccion`, `datos_envio`, `activo`, `es_distribuidor`, `distribuidor_id`) VALUES
(1, 'ESSENZA', 'Mauricio , Rodrigo, Daniel y Mauricio Jr Labastida', 'Calle Pisa 872 Col. Italia Providencia, Guadalajara, Jalisco C.P. 44648', '', 1, 1, NULL),
(2, 'NOE', 'Juan Pérez', 'Calle Ejemplo 123', 'Datos de envío del cliente final', 1, 0, 1),
(3, 'PINTURAS ARTESANOS', 'Sr. Juan Carlos', 'AV. Artesanos N° 3498 Col. San Miguel de Huentitan , C.P. 44700 Guadalajara, Jalisco', '', 1, 0, 1),
(4, 'TOÑO DISTRIBUCIONES', 'Jose Antonio Cruz Andrade / 3316917881', 'Fraccionamiento Real del Parque, Calle Real del Nogal # 9', '', 1, 1, NULL),
(5, 'RAMIRO MARTINEZ CAMACHO', 'RAMIRO MTZ · ramiro@camachopaints.co / 33121233123', 'Av de los Arrayanes 13432, Hermosillo, Sonora C.P. 443232', '', 1, 1, NULL),
(6, 'JOSE MARTINEZ KARMIN', 'JOSE MARTINEZ GUERRERO · jose@pinturasmtz.com / 3321234431', 'AV. COLON 1413', '', 1, 0, NULL),
(7, 'JAVIER / HERMOSILLO', 'JAVIER ABOITE', 'Av. Perimetral Nte. 1285 Sahuaro, C.P. 83178 Hermosillo, Sonora', '', 1, 0, 1),
(8, 'PINTURAS J. JESUS ROMERO', 'SRA SILVIA PEREZ M.', 'Av. Mercedes Celis # 724 Col. Insurgentes Guadalajara, Jalisco', '', 1, 1, NULL),
(9, 'NOBO PINTURAS', 'Sr. Herbert Jaime', 'Av. Sierra de Tapalpa # 5200 Col. Las Águilas C.P. 45080 Zapopan, Jalisco', '', 1, 1, NULL),
(10, 'PINTURAS GRUPO FERRO', 'Sra. Andrea Romero', 'Av. 8 de Julio 3001-B Col. Lomas de Polanco C.P. 44960', '', 1, 1, NULL),
(11, 'PINTURAS EDWIN', 'ARACELI ROMERO', 'PAPALOAPAN SN COL. SAN PEDRITO TLAQUEPAQUE', '', 1, 1, NULL),
(12, 'FAVIAN ALARCON SLP', '', '', 'Envío por Autosag:\r\nJUAREZ ARRIAGA FATIMA ANAHI\r\nCARRETERA RIO VERDE 257\r\nSAN LUIS POTOSI C.P. 78438\r\nCEL. 4444214664', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_mayorista`
--

CREATE TABLE `clientes_mayorista` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `mayorista_user_id` int(11) NOT NULL,
  `status` enum('activo','en_revision','bloqueado') NOT NULL DEFAULT 'en_revision',
  `exclusivo` tinyint(1) NOT NULL DEFAULT 1,
  `zona` varchar(120) DEFAULT NULL,
  `proteccion_desde` date DEFAULT NULL,
  `proteccion_hasta` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes_mayorista`
--

INSERT INTO `clientes_mayorista` (`id`, `cliente_id`, `mayorista_user_id`, `status`, `exclusivo`, `zona`, `proteccion_desde`, `proteccion_hasta`, `created_at`, `updated_at`) VALUES
(1, 6, 2, 'activo', 1, NULL, NULL, NULL, '2025-09-03 08:17:07', '2025-09-03 08:17:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_mp`
--

CREATE TABLE `compras_mp` (
  `id` int(10) UNSIGNED NOT NULL,
  `mp_id` int(10) UNSIGNED DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos`
--

CREATE TABLE `contactos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(30) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `contactos`
--

INSERT INTO `contactos` (`id`, `cliente_id`, `nombre`, `telefono`) VALUES
(1, 1, 'Mauricio Labastida', '3319450323'),
(2, 1, 'Rodrigo Labastida', '3324610149'),
(3, 1, 'Mauricio Labastida (Jr)', '3322159344'),
(4, 5, 'RAMIRO MTZ', '33121233123'),
(5, 6, 'JOSE MARTINEZ GUERRERO', '3321234431');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costeo_mano_obra`
--

CREATE TABLE `costeo_mano_obra` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `empleado_id` int(11) DEFAULT NULL,
  `tiempo_asignado` decimal(10,2) DEFAULT NULL,
  `costo_prorrateado` decimal(10,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `horas` decimal(10,2) NOT NULL DEFAULT 0.00,
  `costo_hora` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `actividad` varchar(120) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `costeo_mano_obra`
--

INSERT INTO `costeo_mano_obra` (`id`, `orden_id`, `usuario_id`, `empleado_id`, `tiempo_asignado`, `costo_prorrateado`, `fecha`, `horas`, `costo_hora`, `actividad`) VALUES
(1, 1391, 4, NULL, NULL, 23.33, '2025-09-11', 0.27, 87.5000, 'Producción'),
(2, 1392, 4, NULL, NULL, 128.33, '2025-09-11', 1.47, 87.5000, 'Producción'),
(3, 2, 1, NULL, NULL, 450.83, '2025-10-13', 9.02, 50.0000, 'Producción'),
(4, 6, 1, NULL, NULL, 1550.83, '2025-10-15', 31.02, 50.0000, 'Producción'),
(5, 3, 1, NULL, NULL, 366.67, '2025-10-16', 7.33, 50.0000, 'Producción'),
(6, 4, 1, NULL, NULL, 150.00, '2025-10-16', 3.00, 50.0000, 'Producción'),
(7, 5, 1, NULL, NULL, 150.00, '2025-10-16', 3.00, 50.0000, 'Producción'),
(8, 13, 1, NULL, NULL, 250.00, '2025-10-16', 5.00, 50.0000, 'Producción'),
(9, 14, 1, NULL, NULL, 112.50, '2025-10-17', 2.25, 50.0000, 'Producción'),
(10, 16, 1, NULL, NULL, 300.00, '2025-10-17', 6.00, 50.0000, 'Producción'),
(11, 15, 1, NULL, NULL, 250.00, '2025-10-17', 5.00, 50.0000, 'Producción'),
(12, 17, 1, NULL, NULL, 300.00, '2025-10-17', 6.00, 50.0000, 'Producción'),
(13, 19, 1, NULL, NULL, 162.50, '2025-10-18', 3.25, 50.0000, 'Producción'),
(14, 20, 1, NULL, NULL, 54.17, '2025-10-21', 1.08, 50.0000, 'Producción'),
(15, 21, 1, NULL, NULL, 58.33, '2025-10-21', 1.17, 50.0000, 'Producción'),
(16, 22, 1, NULL, NULL, 50.00, '2025-10-21', 1.00, 50.0000, 'Producción'),
(17, 24, 1, NULL, NULL, 150.00, '2025-11-11', 3.00, 50.0000, 'Producción'),
(18, 28, 1, NULL, NULL, 637.50, '2026-01-05', 12.75, 50.0000, 'Producción'),
(19, 29, 1, NULL, NULL, 12.50, '2026-01-05', 0.25, 50.0000, 'Producción');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costos_indirectos_asignados`
--

CREATE TABLE `costos_indirectos_asignados` (
  `id` int(11) NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `orden_id` int(11) NOT NULL,
  `fuente` varchar(50) NOT NULL,
  `monto_mxn` decimal(12,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costos_indirectos_periodo`
--

CREATE TABLE `costos_indirectos_periodo` (
  `id` int(11) NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `fuente` varchar(50) NOT NULL,
  `monto_mxn` decimal(12,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costos_lote`
--

CREATE TABLE `costos_lote` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) DEFAULT NULL,
  `total_mp` decimal(10,2) DEFAULT NULL,
  `insumos` decimal(10,2) DEFAULT NULL,
  `mano_obra` decimal(10,2) DEFAULT NULL,
  `indirectos` decimal(10,2) DEFAULT NULL,
  `utilidad_neta` decimal(10,2) DEFAULT NULL,
  `fecha_costo` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `costos_lote`
--

INSERT INTO `costos_lote` (`id`, `orden_id`, `total_mp`, `insumos`, `mano_obra`, `indirectos`, `utilidad_neta`, `fecha_costo`) VALUES
(1, 1392, NULL, NULL, NULL, 3547.75, NULL, NULL),
(2, 12, NULL, NULL, NULL, 1500.00, NULL, NULL),
(3, 12, NULL, NULL, NULL, 1500.00, NULL, NULL),
(4, 12, NULL, NULL, NULL, 272.92, NULL, NULL),
(5, 13, NULL, NULL, NULL, 1227.08, NULL, NULL),
(6, 12, NULL, NULL, NULL, 272.92, NULL, NULL),
(7, 13, NULL, NULL, NULL, 1227.08, NULL, NULL),
(8, 14, NULL, NULL, NULL, 1500.00, NULL, NULL),
(9, 14, NULL, NULL, NULL, 47.86, NULL, NULL),
(10, 16, NULL, NULL, NULL, 1452.14, NULL, NULL),
(11, 14, NULL, NULL, NULL, 12.49, NULL, NULL),
(12, 15, NULL, NULL, NULL, 1108.45, NULL, NULL),
(13, 16, NULL, NULL, NULL, 379.05, NULL, NULL),
(14, 14, NULL, NULL, NULL, 12.49, NULL, NULL),
(15, 15, NULL, NULL, NULL, 1108.45, NULL, NULL),
(16, 16, NULL, NULL, NULL, 379.05, NULL, NULL),
(17, 19, NULL, NULL, NULL, 1500.00, NULL, NULL),
(18, 20, NULL, NULL, NULL, 1500.00, NULL, NULL),
(19, 20, NULL, NULL, NULL, 1093.75, NULL, NULL),
(20, 21, NULL, NULL, NULL, 406.25, NULL, NULL),
(21, 20, NULL, NULL, NULL, 295.52, NULL, NULL),
(22, 21, NULL, NULL, NULL, 109.76, NULL, NULL),
(23, 22, NULL, NULL, NULL, 1094.72, NULL, NULL),
(24, 24, NULL, NULL, NULL, 1500.00, NULL, NULL),
(25, 28, NULL, NULL, NULL, 1500.00, NULL, NULL),
(26, 28, NULL, NULL, NULL, 1153.85, NULL, NULL),
(27, 29, NULL, NULL, NULL, 346.15, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_canal_usuarios`
--

CREATE TABLE `C_canal_usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `rol` enum('distribuidor','mayorista') NOT NULL DEFAULT 'distribuidor',
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `C_canal_usuarios`
--

INSERT INTO `C_canal_usuarios` (`id`, `nombre`, `email`, `telefono`, `rol`, `password_hash`, `is_active`, `must_change_password`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'Distribuidor DEMO', 'demo@distribuidor.com', '3312345678', 'distribuidor', '$2y$10$5kgD9p1Y.hfgXCB7zuFiQeFQ/Pg9x9Jfiq1RTKQVIVmtE77RZs0pi', 1, 1, NULL, '2025-09-01 15:01:52', '2025-09-01 15:01:52'),
(2, 'Mayorista DEMO', 'demo@mayorista.com', '', 'mayorista', '$2y$10$5kgD9p1Y.hfgXCB7zuFiQeFQ/Pg9x9Jfiq1RTKQVIVmtE77RZs0pi', 1, 1, NULL, '2025-09-01 15:01:52', '2025-09-01 15:01:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_channels_prospectos`
--

CREATE TABLE `C_channels_prospectos` (
  `id` int(11) NOT NULL,
  `distribuidor_user_id` int(11) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `giro` varchar(120) DEFAULT NULL,
  `ubicacion` varchar(200) DEFAULT NULL,
  `contacto_nombre` varchar(120) NOT NULL,
  `contacto_email` varchar(160) NOT NULL,
  `contacto_tel` varchar(60) DEFAULT NULL,
  `linea_interes` varchar(120) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `valor_estimado` decimal(14,2) DEFAULT 0.00,
  `horizonte_compra` date DEFAULT NULL,
  `status` enum('pendiente','aprobado','rechazado','en_disputa') NOT NULL DEFAULT 'pendiente',
  `rechazo_motivo` text DEFAULT NULL,
  `validated_by` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_channels_prospecto_evidencias`
--

CREATE TABLE `C_channels_prospecto_evidencias` (
  `id` int(11) NOT NULL,
  `prospecto_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `filepath` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_distribuidores_registro`
--

CREATE TABLE `C_distribuidores_registro` (
  `id` int(11) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `representante` varchar(160) NOT NULL,
  `email` varchar(160) NOT NULL,
  `telefono` varchar(60) DEFAULT NULL,
  `sitio_web` varchar(200) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `ciudad` varchar(120) DEFAULT NULL,
  `estado` varchar(120) DEFAULT NULL,
  `pais` varchar(80) DEFAULT 'México',
  `cp` varchar(12) DEFAULT NULL,
  `zonas_interes` varchar(255) DEFAULT NULL,
  `lineas_interes` varchar(255) DEFAULT NULL,
  `experiencia` text DEFAULT NULL,
  `volumen_estimado` decimal(14,2) DEFAULT 0.00,
  `referencias` varchar(255) DEFAULT NULL,
  `origen` enum('publico','mayorista') NOT NULL DEFAULT 'publico',
  `mayorista_user_id` int(11) DEFAULT NULL,
  `status` enum('pendiente','en_revision','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `rechazo_motivo` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `linked_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `C_distribuidores_registro`
--

INSERT INTO `C_distribuidores_registro` (`id`, `razon_social`, `rfc`, `representante`, `email`, `telefono`, `sitio_web`, `domicilio`, `ciudad`, `estado`, `pais`, `cp`, `zonas_interes`, `lineas_interes`, `experiencia`, `volumen_estimado`, `referencias`, `origen`, `mayorista_user_id`, `status`, `rechazo_motivo`, `approved_by`, `approved_at`, `linked_user_id`, `created_at`, `updated_at`) VALUES
(4, 'dsadasd', '', 'asdas', 'd@fdf.com', '', '', '', '', '', 'México', '', '', '', '', 0.00, '', 'publico', NULL, 'pendiente', NULL, NULL, NULL, NULL, '2025-11-11 10:41:00', '2025-11-11 10:41:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_distribuidor_documentos`
--

CREATE TABLE `C_distribuidor_documentos` (
  `id` int(11) NOT NULL,
  `registro_id` int(11) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `filepath` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `C_encuestas_satisfaccion`
--

CREATE TABLE `C_encuestas_satisfaccion` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `mayorista_user_id` int(11) NOT NULL,
  `nps` tinyint(4) DEFAULT NULL,
  `tiempo_entrega` tinyint(4) DEFAULT NULL,
  `calidad_producto` tinyint(4) DEFAULT NULL,
  `comentarios` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `distribuidores_clientes`
--

CREATE TABLE `distribuidores_clientes` (
  `id` int(11) NOT NULL,
  `distribuidor_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `localidad` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `distribuidores_clientes`
--

INSERT INTO `distribuidores_clientes` (`id`, `distribuidor_id`, `cliente_id`, `localidad`) VALUES
(1, 1, 2, 'Guadalajara'),
(2, 1, 3, 'Monterrey'),
(3, 1, 4, 'Ciudad de México');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas_venta`
--

CREATE TABLE `entregas_venta` (
  `id` int(11) NOT NULL,
  `orden_venta_id` int(11) NOT NULL,
  `fecha_entrega` datetime NOT NULL DEFAULT current_timestamp(),
  `firma_cliente` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entregas_venta`
--

INSERT INTO `entregas_venta` (`id`, `orden_venta_id`, `fecha_entrega`, `firma_cliente`) VALUES
(1, 2, '2025-10-16 14:32:49', NULL),
(2, 4, '2025-10-17 09:03:21', NULL),
(3, 7, '2025-10-17 22:07:56', NULL),
(4, 6, '2025-10-20 18:39:49', NULL),
(5, 3, '2025-10-21 14:50:53', NULL),
(6, 16, '2026-01-06 18:25:10', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fichas_produccion`
--

CREATE TABLE `fichas_produccion` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `lote_minimo` decimal(10,2) DEFAULT NULL,
  `unidad_produccion` enum('kg','g') NOT NULL DEFAULT 'kg',
  `instrucciones` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `fichas_produccion`
--

INSERT INTO `fichas_produccion` (`id`, `producto_id`, `lote_minimo`, `unidad_produccion`, `instrucciones`, `creado_por`) VALUES
(21, 22, 12000.00, 'g', 'Procedimiento (operador):\r\n1.- Verificar que el tanque esté limpio y seco. Identificar lote y materia prima.\r\n2.- Cargar el AC 7524 50 y una pequeña parte del xilol al tanque y arrancar la Cowles a velocidad media para formar vórtice controlado.\r\n3.- Agregar el Sylysia SY 440 lentamente “en lluvia” sobre el vórtice, evitando grumos en paredes y eje. Mantener agitación constante.\r\n4.- Elevar gradualmente la cizalla (Cowles) hasta lograr buena dispersión y disolución. Mantener 10–20 min o hasta observar mezcla homogénea y sin partículas visibles.\r\n5.- Vertir el xilol conforme se vaya necesitando para lograr el espesor buscado.\r\n6.- Envasar, etiquetar: “Solución Matizante”, fecha, lote y peso neto y tapar bien.\r\n\r\nCriterios de aceptación: aspecto claro/ uniforme, sin grumos ni sedimento visible; viscosidad estable y repetible entre lotes.', 1),
(18, 19, 17610.00, 'g', 'Verter los ingredientes en peso y mezclar, esta mezcla alcanza para 20 litros... calcular la densidad para producir litros o mililitros exactos.\r\n\r\ncuidar siempre la limpieza de los recipientes donde se mezcla para no contaminar la mezcla.', 1),
(19, 20, 15000.00, 'g', '', 1),
(20, 21, 10500.00, 'g', 'Procedimiento (operador):\r\n\r\n1.- Verificar que el tanque esté limpio y seco. Identificar lote y materia prima.\r\n\r\n2.- Cargar el Acetato de Butilo (100%) al tanque y arrancar la Cowles a velocidad media para formar vórtice controlado.\r\n\r\n3.- Agregar el ACS 150 lentamente “en lluvia” sobre el vórtice, evitando grumos en paredes y eje. Mantener agitación constante.\r\n\r\n4.- Elevar gradualmente la cizalla (Cowles) hasta lograr buena dispersión y disolución. Mantener 10–20 min o hasta observar mezcla homogénea y sin partículas visibles.\r\n\r\n5.- Mezclar con pala 5–10 min para desairear.\r\n\r\n(Opcional) Filtrar por malla 100–200 µm al envase final.\r\n\r\nEnvasar y etiquetar: “Solución ACS 150 33.3% en Acetato de Butilo”, fecha, lote y peso neto.\r\n\r\nCriterios de aceptación: aspecto claro/ uniforme, sin grumos ni sedimento visible; viscosidad estable y repetible entre lotes.\r\n\r\nNotas:\r\n- Si aparece espuma, dejar reposar o usar antiespumante compatible.\r\n- Mantener temperatura ambiente controlada y lejos de fuentes de ignición.', 1),
(22, 23, 58424.00, 'g', '', 1),
(23, 24, 16653.00, 'g', '', 1),
(24, 25, 23605.00, 'g', '', 1),
(25, 26, 8135.00, 'g', '', 1),
(26, 27, 34918.00, 'g', '', 1),
(27, 28, 10000.00, 'g', '', 1),
(28, 29, 10000.00, 'g', '', 1),
(29, 30, 3635.00, 'g', '', 1),
(30, 31, 16800.00, 'g', 'Esta mezcla es para 4 galones, multiplicar para obtener la producción de los galones deseados.', 1),
(31, 32, 3635.00, 'g', 'Esta cantidad es para 1 galon.', 1),
(32, 33, 3695.00, 'g', 'Mezcla para 1 galon.', 1),
(33, 34, 13020.00, 'g', 'Ingresar ingredientes al molino de perlas.', 1),
(34, 35, 3678.00, 'g', '', 1),
(35, 36, 10164.59, 'g', '', 1),
(36, 37, 3575.00, 'g', '', 1),
(37, 38, 3566.00, 'g', '', 1),
(38, 39, 15645.00, 'g', '', 1),
(39, 40, 900.00, 'g', '', 1),
(40, 41, 3595.00, 'g', '', 1),
(41, 42, 15411.00, 'g', '', 1),
(42, 43, 12154.00, 'g', '', 1),
(43, 44, 3620.00, 'g', '', 1),
(44, 45, 1410.00, 'g', 'Cada fransco de 30ml contiene 28.2 gramos del resultado o producto de este proceso.', 1),
(45, 46, 3585.00, 'g', '', 1),
(46, 47, 13105.00, 'g', '', 1),
(47, 48, 3625.00, 'g', '', 1),
(48, 49, 27278.00, 'g', '', 1),
(49, 50, 24521.00, 'g', '', 1),
(50, 51, 25971.00, 'g', '', 1),
(51, 52, 17730.00, 'g', '', 1),
(52, 53, 15025.00, 'g', '', 1),
(53, 54, 900.00, 'g', '', 1),
(54, 55, 20180.00, 'g', '', 1),
(55, 56, 3552.00, 'g', '', 1),
(56, 57, 18.89, 'g', '', 1),
(57, 58, 2750.00, 'g', '', 1),
(58, 59, 22449.00, 'g', 'Agregar la mitad del Xilol y el Etilo en la dispersora.\r\nIncorporar la Resina AL-110-50XFB\r\n\r\nIncorporación del dispersante previamente disolverlo en solvente y agregarlo lentamente para posterior dejarlo mezclando por 10 minutos.\r\n\r\nAgregar los componentes en el siguiente orden:\r\nNegro de Humo R400\r\nÓxido de Zinc (ZnO)\r\nDióxido de Titanio (TiO?)\r\nSílice 306\r\nSylysia SY 440\r\nZeospheres G-400\r\nTalco TVX\r\n\r\nNota: Añadirlos poco a poco para evitar la formación de grumos.\r\n4. Incorporación de Aditivos Finales\r\n\r\nAgregar los siguientes aditivos en este orden:\r\nAntinata EXKIN N°2\r\nCMP-AP-004 (HALS UV Estabilizador)\r\nCMP-AP-012 (Benzotriazol UV Absorber)\r\nCobalto y Zinc', 1),
(59, 60, 10704.00, 'g', '', 1),
(60, 61, 1569.00, 'g', '', 1),
(61, 62, 900.00, 'g', '', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ficha_mp`
--

CREATE TABLE `ficha_mp` (
  `id` int(11) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `mp_id` int(11) DEFAULT NULL,
  `porcentaje_o_gramos` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `ficha_mp`
--

INSERT INTO `ficha_mp` (`id`, `ficha_id`, `mp_id`, `porcentaje_o_gramos`) VALUES
(163, 34, 11, 200.00),
(152, 29, 51, 190.00),
(151, 29, 48, 5.00),
(150, 29, 12, 220.00),
(149, 29, 11, 220.00),
(126, 28, 11, 2000.00),
(125, 28, 21, 3500.00),
(124, 28, 38, 4500.00),
(123, 27, 21, 3500.00),
(122, 27, 11, 1500.00),
(121, 27, 38, 5000.00),
(120, 26, 45, 7.00),
(119, 26, 19, 14.00),
(118, 26, 9, 14664.00),
(117, 26, 100026, 84.00),
(116, 26, 8, 2372.00),
(115, 26, 21, 3348.00),
(114, 26, 14, 3348.00),
(113, 26, 30, 253.00),
(112, 26, 29, 244.00),
(111, 26, 36, 10585.00),
(110, 25, 19, 5.00),
(109, 25, 22, 220.00),
(108, 25, 11, 4900.00),
(107, 25, 34, 1015.00),
(106, 25, 17, 2000.00),
(105, 24, 19, 5.00),
(104, 24, 11, 7000.00),
(103, 24, 22, 100.00),
(102, 24, 8, 12250.00),
(101, 24, 17, 4250.00),
(100, 23, 19, 10.00),
(99, 23, 100021, 300.00),
(98, 23, 46, 10.00),
(97, 23, 47, 10.00),
(96, 23, 48, 15.00),
(95, 23, 45, 8.00),
(94, 23, 12, 2000.00),
(93, 23, 17, 2000.00),
(92, 23, 26, 12000.00),
(91, 22, 13, 504.00),
(90, 22, 12, 3800.00),
(89, 22, 21, 1200.00),
(88, 22, 11, 2504.00),
(87, 22, 20, 48.00),
(86, 22, 19, 48.00),
(85, 22, 100021, 560.00),
(84, 22, 100020, 14000.00),
(83, 22, 18, 600.00),
(82, 22, 17, 35160.00),
(275, 21, 17, 3500.00),
(274, 21, 11, 3500.00),
(273, 21, 41, 5000.00),
(78, 20, 15, 3500.00),
(77, 20, 12, 7000.00),
(76, 19, 21, 6500.00),
(75, 19, 12, 6500.00),
(74, 19, 16, 2000.00),
(73, 18, 14, 1740.00),
(72, 18, 11, 8600.00),
(71, 18, 13, 1030.00),
(70, 18, 21, 3600.00),
(69, 18, 12, 2640.00),
(132, 30, 100025, 6000.00),
(133, 30, 100023, 10800.00),
(161, 34, 100023, 3278.00),
(160, 31, 48, 5.00),
(159, 31, 12, 220.00),
(158, 31, 11, 220.00),
(157, 31, 52, 190.00),
(162, 34, 12, 200.00),
(156, 32, 11, 220.00),
(155, 32, 48, 5.00),
(154, 32, 12, 220.00),
(153, 32, 53, 250.00),
(144, 33, 54, 1015.00),
(145, 33, 17, 3000.00),
(146, 33, 11, 8500.00),
(147, 33, 19, 5.00),
(148, 33, 22, 500.00),
(164, 35, 55, 1999.80),
(165, 35, 17, 799.92),
(166, 35, 11, 7000.00),
(167, 35, 22, 359.00),
(168, 35, 19, 5.00),
(169, 36, 100026, 700.00),
(170, 36, 100036, 20.00),
(171, 36, 100023, 2855.00),
(172, 37, 100023, 2781.00),
(173, 37, 100034, 750.00),
(174, 37, 100036, 35.00),
(175, 38, 36, 9730.00),
(176, 38, 17, 1650.00),
(177, 38, 35, 20.00),
(178, 38, 47, 45.00),
(179, 38, 46, 45.00),
(180, 38, 49, 8.00),
(181, 38, 45, 7.00),
(182, 38, 11, 1000.00),
(183, 38, 12, 3100.00),
(184, 38, 48, 35.00),
(185, 38, 19, 5.00),
(186, 39, 28, 450.00),
(187, 39, 12, 315.00),
(188, 39, 11, 135.00),
(189, 40, 100036, 600.00),
(190, 40, 100023, 2995.00),
(191, 41, 56, 2000.00),
(192, 41, 17, 1000.00),
(193, 41, 11, 12000.00),
(194, 41, 22, 406.00),
(195, 41, 19, 5.00),
(196, 42, 57, 2000.00),
(197, 42, 17, 3000.00),
(198, 42, 11, 6800.00),
(199, 42, 22, 349.00),
(200, 42, 19, 5.00),
(201, 43, 100043, 2920.00),
(202, 43, 100023, 700.00),
(206, 44, 11, 705.00),
(205, 44, 58, 705.00),
(207, 45, 100042, 450.00),
(208, 45, 100023, 3135.00),
(209, 46, 59, 3000.00),
(210, 46, 17, 1500.00),
(211, 46, 11, 8300.00),
(212, 46, 22, 300.00),
(213, 46, 19, 5.00),
(214, 47, 100047, 900.00),
(215, 47, 100023, 2725.00),
(216, 48, 36, 7588.00),
(217, 48, 29, 175.00),
(218, 48, 30, 188.00),
(219, 48, 14, 2400.00),
(220, 48, 21, 2400.00),
(221, 48, 8, 4000.00),
(222, 48, 9, 10512.00),
(223, 48, 19, 10.00),
(224, 48, 45, 5.00),
(225, 49, 36, 7588.00),
(226, 49, 29, 175.00),
(227, 49, 30, 188.00),
(228, 49, 14, 2400.00),
(229, 49, 21, 2400.00),
(230, 49, 100026, 1250.00),
(231, 49, 9, 10512.00),
(232, 49, 19, 10.00),
(233, 49, 45, 5.00),
(234, 50, 36, 7588.00),
(235, 50, 29, 175.00),
(236, 50, 30, 188.00),
(237, 50, 14, 2400.00),
(238, 50, 21, 2400.00),
(239, 50, 100047, 2700.00),
(240, 50, 9, 10512.00),
(241, 50, 19, 10.00),
(242, 50, 45, 5.00),
(243, 51, 12, 7920.00),
(244, 51, 13, 2060.00),
(245, 51, 11, 6880.00),
(246, 51, 14, 870.00),
(247, 52, 36, 5538.00),
(248, 52, 17, 1646.00),
(249, 52, 12, 6093.00),
(250, 52, 21, 762.00),
(251, 52, 14, 762.00),
(252, 52, 35, 20.00),
(253, 52, 48, 35.00),
(254, 52, 47, 45.00),
(255, 52, 46, 45.00),
(256, 52, 45, 28.00),
(257, 52, 49, 4.00),
(258, 52, 19, 5.00),
(259, 53, 28, 450.00),
(260, 53, 12, 315.00),
(261, 53, 11, 135.00),
(262, 54, 100024, 15080.00),
(263, 54, 100025, 5100.00),
(264, 55, 11, 1204.00),
(265, 55, 12, 880.00),
(266, 55, 21, 720.00),
(267, 55, 13, 360.00),
(268, 55, 14, 388.00),
(270, 56, 45, 20.00),
(271, 57, 24, 1375.00),
(272, 57, 23, 1375.00),
(276, 21, 49, 8.00),
(277, 58, 27, 5200.00),
(278, 58, 44, 1200.00),
(279, 58, 43, 500.00),
(280, 58, 34, 960.00),
(281, 58, 10, 1000.00),
(282, 58, 8, 640.00),
(283, 58, 41, 500.00),
(284, 58, 42, 1300.00),
(285, 58, 9, 800.00),
(286, 58, 11, 4882.00),
(287, 58, 21, 4882.00),
(288, 58, 22, 320.00),
(289, 58, 33, 160.00),
(290, 58, 47, 40.00),
(291, 58, 46, 30.00),
(292, 58, 32, 5.00),
(293, 58, 31, 30.00),
(294, 59, 36, 4500.00),
(295, 59, 17, 1000.00),
(296, 59, 11, 2500.00),
(297, 59, 12, 2500.00),
(298, 59, 48, 45.00),
(299, 59, 100021, 144.00),
(300, 59, 19, 3.00),
(301, 59, 45, 12.00),
(302, 60, 28, 1255.00),
(303, 60, 12, 314.00),
(304, 61, 28, 540.00),
(305, 61, 12, 225.00),
(306, 61, 11, 135.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos_compra`
--

CREATE TABLE `gastos_compra` (
  `id` int(11) NOT NULL,
  `oc_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('flete','aduana','maniobras','seguro','otros') NOT NULL,
  `monto` decimal(14,4) NOT NULL,
  `moneda` varchar(3) NOT NULL DEFAULT 'MXN',
  `tipo_cambio` decimal(14,6) NOT NULL DEFAULT 1.000000,
  `criterio_prorrateo` enum('valor','peso','volumen','unidades') NOT NULL DEFAULT 'valor',
  `creado_por` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos_fijos`
--

CREATE TABLE `gastos_fijos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `categoria` varchar(60) DEFAULT 'general',
  `periodicidad` enum('diario','semanal','mensual','anual') NOT NULL DEFAULT 'mensual',
  `monto_mxn` decimal(12,2) NOT NULL,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `centro_costo` enum('produccion','administracion','general') DEFAULT 'general',
  `activo` tinyint(1) DEFAULT 1
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_precios_cliente`
--

CREATE TABLE `historial_precios_cliente` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `lista_precio_id` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `vigencia_inicio` date DEFAULT NULL,
  `vigencia_fin` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos_comerciales`
--

CREATE TABLE `insumos_comerciales` (
  `id` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `stock` decimal(10,2) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT NULL,
  `precio_unitario` decimal(12,6) DEFAULT NULL,
  `stock_minimo` decimal(10,2) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `proveedor_id` int(11) UNSIGNED DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `insumos_comerciales`
--

INSERT INTO `insumos_comerciales` (`id`, `tipo`, `nombre`, `stock`, `unidad`, `precio_unitario`, `stock_minimo`, `proveedor`, `proveedor_id`, `activo`) VALUES
(4, 'Envase', 'Envase TTP 3.8 Lamina', 98.00, 'pza', 34.560000, 25.00, 'ECONOENVASES', 9, 1),
(5, 'Tapa', 'Tapa TTP 3.8 Lamina', 98.00, 'pza', 0.000000, 25.00, 'ECONOENVASES', 9, 1),
(6, 'Etiqueta - Diseño', 'Etiqueta Base Color Acrilica', 898.00, 'pza', 1.100000, 150.00, 'ESFERA DIGITAL', 10, 1),
(7, 'Etiqueta - Nombre y Lote', 'Etiqueta Chica al frente y parte superior', 547.00, 'rollo', 0.054000, 50.00, 'ECONOENVASES', 9, 1),
(8, 'Envase', 'Flex JR 4 Litros', 36.00, 'pza', 39.800000, 15.00, 'ECONOENVASES', 9, 1),
(9, 'Tapa', 'Tapa Flex JR', 36.00, 'pza', 0.000000, 15.00, 'ECONOENVASES', 9, 1),
(10, 'Envase', 'Flip Top 1 L Lamina', 69.00, 'pza', 17.000000, 50.00, 'ECONOENVASES', 9, 1),
(11, 'Tapa', 'Tapa Flip Top Universal', 345.00, 'pza', 0.000000, 50.00, 'ECONOENVASES', 9, 1),
(12, 'Etiqueta - Diseño', 'Etiqueta LT Cat Universal', 60.00, 'pza', 2.000000, 25.00, 'ESFERA DIGITAL', 10, 1),
(13, 'Etiqueta - Descripción', 'Etiqueta Descripcion 10x10', 765.00, 'rollo', 0.627500, 100.00, 'ESFERA DIGITAL', 10, 1),
(14, 'Envase', 'Envase TTP 0.956 L - Lamina', 66.00, 'pza', 14.500000, 30.00, 'ECONOENVASES', 9, 1),
(15, 'Tapa', 'Tapa TTP 0.956 L - Lamina', 66.00, 'pza', 0.000000, 30.00, 'ECONOENVASES', 9, 1),
(16, 'Etiqueta - Diseño', 'Etiqueta Fondos 0.946 (ttp)', 60.00, 'pza', 0.625000, 300.00, 'ESFERA DIGITAL', 10, 1),
(17, 'Etiqueta - Tiempo de secado', 'Etiqueta Amarilla - 60 a 120 min para Lijado', 150.00, 'pza', 0.070400, 15.00, 'ESFERA DIGITAL', 10, 1),
(18, 'Etiqueta - Diseño', 'Etiqueta Fondos Galon (ttp)', 200.00, 'pza', 0.074000, 100.00, 'ESFERA DIGITAL', 10, 1),
(19, 'Envase', 'Flip Top 0.125 LT Lamina', 25.00, 'pza', 8.000000, 35.00, 'ECONOENVASES', 9, 1),
(21, 'Envase', 'Flip Top 0.250 LT Lamina', 50.00, 'pza', 10.800000, 35.00, 'ECONOENVASES', 9, 1),
(22, 'Envase', 'Flip Top 0.500 LT Lamina', 85.00, 'pza', 15.500000, 35.00, 'ECONOENVASES', 9, 1),
(23, 'Etiqueta - Diseño', 'Etiqueta Catalizador 118ml Fondos 8:1', 25.00, 'pza', 1.300000, 50.00, 'ESFERA DIGITAL', 10, 1),
(24, 'Etiqueta - Diseño', 'Etiqueta Catalizador .236 ml Fondos 4:1', 15.00, 'pza', 1.620000, 30.00, 'ESFERA DIGITAL', 10, 1),
(25, 'Etiqueta - Diseño', 'Etiqueta Catalizador .473 ml Fondos 8:1', 25.00, 'pza', 2.160000, 25.00, 'ESFERA DIGITAL', 10, 1),
(26, 'Etiqueta - Diseño', 'Etiqueta Catalizador .946 ml Fondos 4:1', 25.00, 'pza', 3.250000, 20.00, 'ESFERA DIGITAL', 10, 1),
(27, 'Etiqueta - Diseño', 'Etiqueta PG211 0.946ml - Parte A', 80.00, 'pza', 2.250000, 45.00, 'ESFERA DIGITAL', 10, 1),
(28, 'Etiqueta - Diseño', 'Etiqueta PG211 3.785 L - Parte A', 800.00, 'pza', 1.250000, 300.00, 'ESFERA DIGITAL', 10, 1),
(29, 'Envase', 'Gotero Ambar Vidrio c/ Gotero de Vidrio 15 gotas', 30.00, 'pza', 10.632000, 15.00, 'ESFERA DIGITAL', 10, 1),
(30, 'Etiqueta - Diseño', 'Etiqueta AdheGrip CP50', 40.00, 'pza', 0.222000, 50.00, 'ESFERA DIGITAL', 10, 1),
(31, 'Envase', 'Cubeta 19L Cerrada Lamina c/Tapon', 45.00, 'pza', 184.580000, 10.00, 'ECONOENVASES', 9, 1),
(32, 'Envase', 'Cubeta 19L Abierta Lamina c/Tapa', 9.00, 'pza', 0.000000, 0.00, 'ECONOENVASES', 9, 1),
(33, 'Envase', 'Gotero Vidrio c/ tapa con Cintillo 20ml', 15.00, 'pza', 0.000000, 0.00, NULL, NULL, 1),
(34, 'Etiqueta - Diseño', 'Etiqueta Acelerador', 28.00, 'pza', 0.000000, 50.00, 'ESFERA DIGITAL', 10, 1),
(35, 'Etiqueta - Diseño', 'Etiqueta Esm Alta Temperatura', 4.00, 'pza', 4.000000, 10.00, 'ESFERA DIGITAL', 10, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos_proveedores`
--

CREATE TABLE `insumos_proveedores` (
  `insumo_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `insumos_proveedores`
--

INSERT INTO `insumos_proveedores` (`insumo_id`, `proveedor_id`) VALUES
(1, 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lineas_compra`
--

CREATE TABLE `lineas_compra` (
  `id` int(10) UNSIGNED NOT NULL,
  `orden_compra_id` int(10) UNSIGNED NOT NULL,
  `mp_id` int(10) UNSIGNED DEFAULT NULL,
  `ic_id` int(11) DEFAULT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `precio_unitario` decimal(14,6) NOT NULL,
  `subtotal` decimal(14,6) NOT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `tipo_cambio` decimal(14,6) DEFAULT NULL,
  `descuento_pct` decimal(6,3) NOT NULL DEFAULT 0.000,
  `notas_linea` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `lineas_compra`
--

INSERT INTO `lineas_compra` (`id`, `orden_compra_id`, `mp_id`, `ic_id`, `cantidad`, `precio_unitario`, `subtotal`, `moneda`, `tipo_cambio`, `descuento_pct`, `notas_linea`) VALUES
(1, 1, 16, NULL, 20000.00, 0.303316, 6066.320000, 'MXN', 1.000000, 0.000, NULL),
(2, 1, 51, NULL, 5000.00, 0.523826, 2619.130000, NULL, NULL, 0.000, NULL),
(3, 2, 11, NULL, 174000.00, 0.024713, 4300.062000, 'MXN', 1.000000, 0.000, NULL),
(4, 2, 14, NULL, 173400.00, 0.025375, 4400.025000, 'MXN', 1.000000, 0.000, NULL),
(5, 3, 58, NULL, 5000.00, 1.291600, 6458.000000, 'MXN', 1.000000, 0.000, NULL),
(6, 4, 28, NULL, 20000.00, 0.125050, 2501.000000, 'MXN', 1.000000, 0.000, NULL),
(7, 4, 28, NULL, 6000.00, 0.125050, 750.300000, 'MXN', 1.000000, 0.000, NULL),
(8, 5, 11, NULL, 174000.00, 0.024712, 4299.888000, 'MXN', 1.000000, 0.000, NULL),
(9, 5, 12, NULL, 176400.00, 0.031400, 5538.960000, 'MXN', 1.000000, 0.000, NULL),
(10, 6, NULL, 31, 20.00, 184.580000, 3691.600000, 'MXN', 1.000000, 0.000, NULL),
(11, 6, NULL, 4, 100.00, 34.560000, 3456.000000, 'MXN', 1.000000, 0.000, NULL),
(12, 6, NULL, 5, 100.00, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, NULL),
(13, 7, 8, NULL, 25000.00, 0.073137, 1828.425000, 'MXN', 1.000000, 0.000, NULL),
(14, 8, 24, NULL, 13180.00, 0.029620, 390.391600, 'MXN', 1.000000, 0.000, NULL),
(15, 8, 23, NULL, 14600.00, 0.026287, 383.790200, 'MXN', 1.000000, 0.000, NULL),
(16, 9, 36, NULL, 18000.00, 0.087319, 1571.742000, 'MXN', 1.000000, 0.000, NULL),
(20, 99998, 8, NULL, 0.00, 0.080000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(21, 99998, 9, NULL, 0.00, 0.020000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(22, 99998, 13, NULL, 0.00, 0.040000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(23, 99998, 15, NULL, 0.00, 0.430000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(24, 99998, 16, NULL, 0.00, 0.310000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(25, 99998, 17, NULL, 0.00, 0.090000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(26, 99998, 18, NULL, 0.00, 0.040000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(27, 99998, 19, NULL, 0.00, 0.260000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(28, 99998, 20, NULL, 0.00, 0.460000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(29, 99998, 21, NULL, 0.00, 0.030000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(30, 99998, 22, NULL, 0.00, 0.310000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(31, 99998, 25, NULL, 0.00, 0.010000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(32, 99998, 26, NULL, 0.00, 0.060000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(33, 99998, 27, NULL, 0.00, 0.060000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(34, 99998, 29, NULL, 0.00, 0.070000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(35, 99998, 35, NULL, 0.00, 0.880000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(36, 99998, 37, NULL, 0.00, 0.060000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(37, 99998, 38, NULL, 0.00, 0.100000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(38, 99998, 41, NULL, 0.00, 0.150000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(39, 99998, 42, NULL, 0.00, 0.050000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(40, 99998, 43, NULL, 0.00, 0.010000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(41, 99998, 45, NULL, 0.00, 0.510000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(42, 99998, 46, NULL, 0.00, 0.400000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(43, 99998, 47, NULL, 0.00, 0.330000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(44, 99998, 48, NULL, 0.00, 0.480000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(45, 99998, 49, NULL, 0.00, 0.770000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(46, 99998, 50, NULL, 0.00, 0.060000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(47, 99998, 52, NULL, 0.00, 0.660000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(48, 99998, 53, NULL, 0.00, 0.470000, 0.000000, 'MXN', 1.000000, 0.000, 'Backfill lotes EXISTENCIA'),
(51, 100000, 36, NULL, 18000.00, 0.087970, 1583.460000, 'MXN', 1.000000, 0.000, NULL),
(52, 100001, 36, NULL, 18000.00, 0.087400, 1573.200000, 'MXN', 1.000000, 0.000, NULL),
(53, 100002, NULL, 10, 104.00, 16.200000, 1684.800000, 'MXN', 1.000000, 0.000, NULL),
(54, 100002, NULL, 11, 104.00, 2.000000, 208.000000, 'MXN', 1.000000, 0.000, NULL),
(55, 100002, NULL, 32, 9.00, 156.600000, 1409.400000, 'MXN', 1.000000, 0.000, NULL),
(56, 100003, 57, NULL, 10000.00, 0.553570, 5535.700000, 'MXN', 1.000000, 0.000, NULL),
(57, 100004, 8, NULL, 25000.00, 0.073274, 1831.850000, 'MXN', 1.000000, 0.000, NULL),
(58, 900002, 38, NULL, 18500.00, 0.088330, 1634.105000, 'MXN', 1.000000, 0.000, NULL),
(59, 900002, 36, NULL, 18000.00, 0.087410, 1573.380000, 'MXN', 1.000000, 0.000, NULL),
(60, 900003, 28, NULL, 20000.00, 0.125669, 2513.380000, 'MXN', 18.480800, 0.000, NULL),
(61, 900004, 26, NULL, 200000.00, 0.055000, 11000.000000, 'MXN', 1.000000, 0.000, NULL),
(62, 900005, 17, NULL, 200000.00, 0.092000, 18400.000000, 'MXN', 1.000000, 0.000, NULL),
(63, 900006, 11, NULL, 348000.00, 0.025552, 8892.096000, 'MXN', 1.000000, 0.000, NULL),
(64, 900006, 21, NULL, 180400.00, 0.029090, 5247.836000, 'MXN', 1.000000, 0.000, NULL),
(65, 900006, 12, NULL, 176400.00, 0.030500, 5380.200000, 'MXN', 1.000000, 0.000, NULL),
(66, 900006, 13, NULL, 45000.00, 0.028422, 1278.990000, 'MXN', 1.000000, 0.000, NULL),
(67, 900007, 12, NULL, 5974.01, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, 'AJUSTE INVENTARIO desde inventario_mp'),
(68, 900008, 36, NULL, 3000.00, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, 'AJUSTE INVENTARIO desde inventario_mp'),
(69, 900009, 36, NULL, 3000.00, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, 'AJUSTE INV MP'),
(70, 900009, 36, NULL, 3000.00, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, 'AJUSTE INV MP'),
(71, 900009, 36, NULL, 3000.00, 0.000000, 0.000000, 'MXN', 1.000000, 0.000, 'AJUSTE INV MP'),
(72, 900010, 9, NULL, 40000.00, 0.014930, 597.200000, 'MXN', 1.000000, 0.000, NULL),
(73, 900011, 28, NULL, 20000.00, 0.123660, 2473.200000, 'MXN', 1.000000, 0.000, NULL),
(74, 900012, 26, NULL, 200000.00, 0.055000, 11000.000000, 'MXN', 1.000000, 0.000, NULL),
(75, 900013, 28, NULL, 20000.00, 0.123700, 2474.000000, 'MXN', 1.000000, 0.000, NULL),
(76, 900014, 36, NULL, 36000.00, 0.087400, 3146.400000, 'MXN', 1.000000, 0.000, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lineas_venta`
--

CREATE TABLE `lineas_venta` (
  `id` int(11) NOT NULL,
  `orden_venta_id` int(11) NOT NULL,
  `insumo_comercial_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `presentacion_id` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,4) NOT NULL,
  `subtotal` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `lineas_venta`
--

INSERT INTO `lineas_venta` (`id`, `orden_venta_id`, `insumo_comercial_id`, `producto_id`, `presentacion_id`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(3, 2, NULL, 54, 18, 20.000, 0.0000, 0.00),
(4, 2, NULL, 19, 22, 20.000, 0.0000, 0.00),
(5, 3, NULL, 52, 24, 2.000, 0.0000, 0.00),
(6, 4, NULL, 54, 18, 15.000, 0.0000, 0.00),
(7, 5, NULL, 52, 24, 1.000, 0.0000, 0.00),
(8, 5, NULL, 19, 24, 1.000, 0.0000, 0.00),
(9, 5, NULL, 31, 20, 3.000, 0.0000, 0.00),
(10, 6, NULL, 55, 22, 1.000, 0.0000, 0.00),
(11, 6, NULL, 56, 21, 1.000, 0.0000, 0.00),
(12, 7, NULL, 30, 20, 2.000, 0.0000, 0.00),
(13, 8, NULL, 39, 20, 12.000, 0.0000, 0.00),
(14, 8, NULL, 40, 18, 24.000, 0.0000, 0.00),
(15, 8, NULL, 27, 20, 15.000, 0.0000, 0.00),
(16, 8, NULL, 29, 18, 15.000, 0.0000, 0.00),
(17, 9, NULL, 31, 20, 6.000, 0.0000, 0.00),
(18, 9, NULL, 40, 18, 1.000, 0.0000, 0.00),
(19, 9, NULL, 40, 17, 4.000, 0.0000, 0.00),
(20, 9, NULL, 27, 18, 6.000, 0.0000, 0.00),
(21, 9, NULL, 28, 15, 6.000, 0.0000, 0.00),
(22, 9, NULL, 27, 20, 2.000, 0.0000, 0.00),
(23, 9, NULL, 28, 17, 2.000, 0.0000, 0.00),
(24, 9, NULL, 19, 24, 2.000, 0.0000, 0.00),
(25, 9, NULL, 52, 24, 2.000, 0.0000, 0.00),
(26, 10, NULL, 37, 20, 2.000, 0.0000, 0.00),
(27, 10, NULL, 31, 20, 2.000, 0.0000, 0.00),
(28, 10, NULL, 30, 20, 1.000, 0.0000, 0.00),
(29, 10, NULL, 32, 20, 1.000, 0.0000, 0.00),
(30, 10, NULL, 33, 20, 1.000, 0.0000, 0.00),
(31, 10, NULL, 48, 20, 1.000, 0.0000, 0.00),
(32, 10, NULL, 44, 20, 1.000, 0.0000, 0.00),
(33, 11, NULL, 31, 20, 3.000, 0.0000, 0.00),
(34, 11, NULL, 30, 20, 3.000, 0.0000, 0.00),
(35, 12, NULL, 33, 20, 1.000, 0.0000, 0.00),
(36, 12, NULL, 31, 20, 2.000, 0.0000, 0.00),
(37, 12, NULL, 30, 20, 2.000, 0.0000, 0.00),
(38, 13, NULL, 54, 18, 10.000, 0.0000, 0.00),
(39, 13, NULL, 55, 22, 1.000, 0.0000, 0.00),
(40, 13, NULL, 55, 20, 1.000, 0.0000, 0.00),
(41, 8, NULL, 57, 25, 15.000, 0.0000, 0.00),
(42, 5, NULL, 27, 20, 2.000, 0.0000, 0.00),
(43, 5, NULL, 28, 17, 2.000, 0.0000, 0.00),
(44, 5, NULL, 27, 18, 6.000, 0.0000, 0.00),
(45, 5, NULL, 28, 15, 6.000, 0.0000, 0.00),
(46, 14, NULL, 52, 21, 5.000, 0.0000, 0.00),
(47, 15, NULL, 27, 18, 20.000, 0.0000, 0.00),
(48, 15, NULL, 28, 15, 20.000, 0.0000, 0.00),
(49, 16, NULL, 35, 20, 10.000, 0.0000, 0.00),
(51, 18, NULL, 39, 20, 12.000, 0.0000, 0.00),
(52, 18, NULL, 40, 18, 24.000, 0.0000, 0.00),
(53, 18, NULL, 57, 25, 15.000, 0.0000, 0.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `listas_precios`
--

CREATE TABLE `listas_precios` (
  `id` int(11) NOT NULL,
  `nombre_lista` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias_primas`
--

CREATE TABLE `materias_primas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `precio_estimado` decimal(12,6) NOT NULL DEFAULT 0.000000,
  `proveedor` varchar(100) DEFAULT NULL,
  `stock_actual` decimal(10,2) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `densidad_kg_l` decimal(8,4) DEFAULT NULL,
  `unidad_compra` enum('g','kg','L','pza') NOT NULL DEFAULT 'g',
  `es_solvente` tinyint(1) NOT NULL DEFAULT 0,
  `existencia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad mínima de stock (en g) antes de generar alerta',
  `codigo_interno` varchar(50) DEFAULT NULL COMMENT 'Código interno de referencia',
  `unidad_base` varchar(16) NOT NULL DEFAULT 'g',
  `tipo_mp` varchar(32) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `materias_primas`
--

INSERT INTO `materias_primas` (`id`, `nombre`, `unidad`, `tipo`, `codigo`, `precio_estimado`, `proveedor`, `stock_actual`, `precio_unitario`, `densidad_kg_l`, `unidad_compra`, `es_solvente`, `existencia`, `stock_minimo`, `codigo_interno`, `unidad_base`, `tipo_mp`, `activo`) VALUES
(8, 'Dioxido de Titanio R706', 'g', 'pigmento', NULL, 0.080240, NULL, NULL, 0.08, NULL, 'g', 0, 30700.00, 15000.00, 'R706', 'g', NULL, 1),
(9, 'Talco TVX', 'g', 'otro', NULL, 0.017020, NULL, NULL, 0.02, NULL, 'g', 0, 34000.00, 15000.00, 'TALCOTV', 'g', NULL, 1),
(10, 'Oxido de Zinc', 'g', 'otro', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 5000.00, 'OXZCR', 'g', NULL, 1),
(11, 'Xilol', 'g', 'solvente', NULL, 0.027500, NULL, NULL, 0.03, 0.8700, 'g', 1, 476236.33, 100000.00, 'Xilol', 'g', NULL, 1),
(12, 'Acetato de Butilo', 'g', 'solvente', NULL, 0.035800, NULL, NULL, 0.04, 0.8820, 'g', 1, 246275.62, 100000.00, 'Butilo', 'g', NULL, 1),
(13, 'Butil Cellosolve', 'g', 'solvente', NULL, 0.035500, NULL, NULL, 0.04, 0.9000, 'g', 1, 84033.67, 50000.00, 'CELLOSOLVE', 'g', NULL, 1),
(14, 'Tolueno', 'g', 'solvente', NULL, 0.028000, NULL, NULL, 0.03, 0.8670, 'g', 1, 136552.56, 50000.00, 'TOLUENO', 'g', NULL, 1),
(15, 'Troythix 150 ACS ', 'g', 'aditivo', NULL, 0.433590, NULL, NULL, 0.43, NULL, 'g', 0, 2700.00, 3500.00, 'ACS150', 'g', NULL, 1),
(16, 'CAB 381-20.0 ', 'g', 'aditivo', NULL, 0.306780, NULL, NULL, 0.31, NULL, 'g', 0, 28000.00, 4000.00, 'CAB', 'g', NULL, 1),
(17, 'AC 7534 50 ', 'g', 'resina', NULL, 0.092000, NULL, NULL, 0.09, NULL, 'g', 0, 93302.52, 50000.00, 'RTP', 'g', NULL, 1),
(18, 'D.O.P.', 'g', 'aditivo', NULL, 0.039000, NULL, NULL, 0.04, 0.9860, 'g', 1, 77400.00, 20000.00, 'DOP', 'g', NULL, 1),
(19, 'Troysol AFL', 'g', 'aditivo', NULL, 0.260590, NULL, NULL, 0.26, NULL, 'g', 0, 928.82, 1000.00, 'AFL', 'g', NULL, 1),
(20, 'BIK 300', 'g', 'aditivo', NULL, 0.461560, NULL, NULL, 0.46, NULL, 'g', 0, 3952.00, 2000.00, 'BIK300', 'g', NULL, 1),
(21, 'Acetato de Etilo', 'g', 'solvente', NULL, 0.034200, NULL, NULL, 0.03, 0.9020, 'g', 1, 294670.52, 10000.00, 'Etilo', 'g', NULL, 1),
(22, 'Troysperse 98-C', 'g', 'aditivo', NULL, 0.309990, NULL, NULL, 0.31, NULL, 'g', 0, 7300.00, 5000.00, 'T98C', 'g', NULL, 1),
(23, 'Gas Nafta', 'g', 'solvente', NULL, 0.000000, NULL, NULL, 0.00, 0.7300, 'g', 1, 10466.00, 7000.00, 'GASNFTA', 'g', NULL, 1),
(24, 'Hexano', 'g', 'solvente', NULL, 0.000000, NULL, NULL, 0.00, 0.6590, 'g', 1, 9046.00, 10000.00, 'HEX', 'g', NULL, 1),
(25, 'Metanol', 'g', 'solvente', NULL, 0.012476, NULL, NULL, 0.01, 0.7920, 'g', 1, 12672.00, 5000.00, 'MTL', 'g', NULL, 1),
(26, 'AL 5301 50X', 'g', 'resina', NULL, 0.055000, NULL, NULL, 0.06, NULL, 'g', 0, 32779.86, 50000.00, 'AL5301', 'g', NULL, 1),
(27, 'AL 110 50XFB', 'g', 'resina', NULL, 0.057000, NULL, NULL, 0.06, NULL, 'g', 0, 20000.00, 20000.00, 'AL110', 'g', NULL, 1),
(28, 'Tolonate HDB 75-BX', 'g', 'resina', NULL, 0.136050, NULL, NULL, 0.14, NULL, 'g', 0, 26329.83, 20000.00, 'THDB', 'g', NULL, 1),
(29, 'Lecitina de Soya', 'g', 'aditivo', NULL, 0.068970, NULL, NULL, 0.07, NULL, 'g', 0, 8400.00, 3000.00, 'MP-LSOY', 'g', 'Aditivo', 1),
(30, 'Claytone HY BIK', 'g', 'aditivo', NULL, 0.180172, NULL, NULL, 0.18, NULL, 'g', 0, 0.00, 5000.00, 'MP-CLYHY', 'g', 'Aditivo', 1),
(31, 'Octoato de Zinc', 'g', 'aditivo', NULL, 0.279900, NULL, NULL, 0.28, NULL, 'g', 0, 0.00, 1000.00, 'MP-OCZC', 'g', 'Aditivo', 1),
(32, 'Octoato de Cobalto', 'g', 'aditivo', NULL, 0.119300, NULL, NULL, 0.12, NULL, 'g', 0, 0.00, 1000.00, 'MP-OCCOB', 'g', 'Aditivo', 1),
(33, 'Exkin N°2', 'g', 'aditivo', NULL, 0.099100, NULL, NULL, 0.10, NULL, 'g', 0, 0.00, 1000.00, 'MP-EXK', 'g', 'Aditivo', 1),
(34, 'Regal Carbon Black R 400 R', 'g', 'pigmento', NULL, 0.322230, NULL, NULL, 0.32, NULL, 'g', 0, 0.00, 4000.00, 'MP-PIG-CBR400', 'g', 'Pigmento', 1),
(35, 'BIK 331', 'g', 'aditivo', NULL, 0.883620, NULL, NULL, 0.88, NULL, 'g', 0, 5000.00, 2000.00, 'MP-BIK331', 'g', 'Aditivo', 1),
(36, 'Synthalat 077 HV', 'g', 'resina', NULL, 0.096320, NULL, NULL, 0.10, NULL, 'g', 0, 76496.47, 7500.00, 'MP-077HV', 'g', 'Resina', 1),
(37, 'Kpol 8211', 'g', 'resina', NULL, 0.057000, NULL, NULL, 0.06, NULL, 'g', 0, 13000.00, 4000.00, 'MP-K8211', 'g', 'Resina', 1),
(38, 'Desmordur L75', 'g', 'resina', NULL, 0.095680, NULL, NULL, 0.10, NULL, 'g', 0, 22500.00, 5000.00, 'MP-DL75', 'g', 'Resina', 1),
(39, 'Desmordur N3390', 'g', 'resina', NULL, 0.228100, NULL, NULL, 0.23, NULL, 'g', 0, 0.00, 2000.00, 'MP-D3390', 'g', 'Resina', 1),
(40, 'Synthalat A TS 3947', 'g', 'resina', NULL, 0.092980, NULL, NULL, 0.09, NULL, 'g', 0, 0.00, 2000.00, 'MP-STS3947', 'g', 'Resina', 1),
(41, 'Sylysia SY 440', 'g', 'cargas', NULL, 0.150210, NULL, NULL, 0.15, NULL, 'g', 0, 13500.00, 5000.00, 'MP-SSY440', 'g', 'Cargas', 1),
(42, 'Zeospheres G 400', 'g', 'cargas', NULL, 0.049870, NULL, NULL, 0.05, NULL, 'g', 0, 15000.00, 5000.00, 'MP-ZG400', 'g', 'Cargas', 1),
(43, 'Silice 306', 'g', 'cargas', NULL, 0.011900, NULL, NULL, 0.01, NULL, 'g', 0, 30000.00, 5000.00, 'MP-S306', 'g', 'Cargas', 1),
(44, 'Feldespato Potasico', 'g', 'cargas', NULL, 0.014260, NULL, NULL, 0.01, NULL, 'g', 0, 0.00, 5000.00, 'MP-FPT', 'g', 'Cargas', 1),
(45, 'DBTDL', 'g', 'cargas', NULL, 0.511210, NULL, NULL, 0.51, NULL, 'g', 0, 10725.44, 2000.00, 'MP-DBTDL', 'g', 'Cargas', 1),
(46, 'CMP-AP-012 (5530) Absorbedor UV', 'g', 'aditivo', NULL, 0.402670, NULL, NULL, 0.40, NULL, 'g', 0, 2989.82, 2000.00, 'MP-AP012', 'g', 'Aditivo', 1),
(47, 'CMP-AP-004 (353) Estabilizador de luz HALS', 'g', 'aditivo', NULL, 0.326160, NULL, NULL, 0.33, NULL, 'g', 0, 1989.82, 2000.00, 'MP-AP004', 'g', 'Aditivo', 1),
(48, 'Troysol S367', 'g', 'aditivo', NULL, 0.478300, NULL, NULL, 0.48, NULL, 'g', 0, 5825.24, 2000.00, 'MP-S367', 'g', 'Aditivo', 1),
(49, 'Rehobyk 410', 'g', 'aditivo', NULL, 0.772410, NULL, NULL, 0.77, NULL, 'g', 0, 25400.00, 2000.00, 'MP-BIK410', 'g', 'Aditivo', 1),
(50, 'PM Acetato', 'g', 'solvente', NULL, 0.056476, NULL, NULL, 0.06, 0.9650, 'g', 1, 15440.00, 20.00, 'PMAC', 'g', NULL, 1),
(51, 'ALU 3026 H', 'g', 'pigmento', NULL, 0.523825, NULL, NULL, 0.52, NULL, 'g', 0, 651.12, 2000.00, 'AL3026H', 'g', NULL, 1),
(52, 'ALU M4015', 'g', 'pigmento', NULL, 0.658766, NULL, NULL, 0.66, NULL, 'g', 0, 1700.00, 1900.00, 'ALUM4015', 'g', NULL, 1),
(53, 'ALU M2040', 'g', 'pigmento', NULL, 0.465510, NULL, NULL, 0.47, NULL, 'g', 0, 2000.00, 2000.00, 'ALM2040', 'g', NULL, 1),
(54, 'Monarch Carbon Black M1300', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 0.00, 'M1300', 'g', NULL, 1),
(55, 'Cromfrog Blue MBG', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 4000.00, 'CBMBG', 'g', NULL, 1),
(56, 'Hostaperm Azul BT 728 D', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 3000.00, 'BT728D', 'g', NULL, 1),
(57, 'Rojo DPP DCC-7354', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 4000.00, 'DCC7354', 'g', NULL, 1),
(58, 'Poliolefina Clorada CP 343 1', 'g', 'aditivo', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 4295.00, 1000.00, 'CP3431', 'g', NULL, 1),
(59, 'Mixfrog Rojo Bermellon MRC 571-104SB', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 0.00, 'ROJBERM', 'g', NULL, 1),
(60, 'Amarillo Cromo DCC-1009', 'g', 'pigmento', NULL, 0.000000, NULL, NULL, 0.00, NULL, 'g', 0, 0.00, 5000.00, 'AMCRO', 'g', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_insumos`
--

CREATE TABLE `movimientos_insumos` (
  `id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha` datetime NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `movimientos_insumos`
--

INSERT INTO `movimientos_insumos` (`id`, `insumo_id`, `tipo`, `cantidad`, `fecha`, `usuario_id`, `comentario`) VALUES
(1, 4, 'salida', 2.00, '2025-10-20 18:23:27', 1, 'Empaque OP #11 — request #8'),
(2, 5, 'salida', 2.00, '2025-10-20 18:23:27', 1, 'Empaque OP #11 — request #8'),
(3, 6, 'salida', 2.00, '2025-10-20 18:23:27', 1, 'Empaque OP #11 — request #8'),
(4, 7, 'salida', 4.00, '2025-10-20 18:23:27', 1, 'Empaque OP #11 — request #8'),
(5, 33, 'salida', 12.00, '2025-10-20 18:23:41', 1, 'Empaque OP #14 — request #7'),
(6, 34, 'salida', 12.00, '2025-10-20 18:23:41', 1, 'Empaque OP #14 — request #7'),
(7, 32, 'salida', 1.00, '2025-10-20 18:23:47', 1, 'Empaque OP #7 — request #6'),
(8, 7, 'salida', 15.00, '2025-10-20 18:23:57', 1, 'Empaque OP #4 — request #4'),
(9, 10, 'salida', 15.00, '2025-10-20 18:23:57', 1, 'Empaque OP #4 — request #4'),
(10, 11, 'salida', 15.00, '2025-10-20 18:23:57', 1, 'Empaque OP #4 — request #4'),
(11, 13, 'salida', 15.00, '2025-10-20 18:23:57', 1, 'Empaque OP #4 — request #4'),
(12, 7, 'salida', 20.00, '2025-10-20 18:24:11', 1, 'Empaque OP #3 — request #3'),
(13, 10, 'salida', 20.00, '2025-10-20 18:24:11', 1, 'Empaque OP #3 — request #3'),
(14, 11, 'salida', 20.00, '2025-10-20 18:24:11', 1, 'Empaque OP #3 — request #3'),
(15, 13, 'salida', 20.00, '2025-10-20 18:24:11', 1, 'Empaque OP #3 — request #3'),
(16, 7, 'salida', 14.00, '2025-12-16 20:42:07', 1, 'Empaque OP #24 — request #13'),
(17, 14, 'salida', 14.00, '2025-12-16 20:42:07', 1, 'Empaque OP #24 — request #13'),
(18, 15, 'salida', 14.00, '2025-12-16 20:42:07', 1, 'Empaque OP #24 — request #13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_mp`
--

CREATE TABLE `movimientos_mp` (
  `id` int(11) NOT NULL,
  `mp_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha` datetime NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `origen_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `movimientos_mp`
--

INSERT INTO `movimientos_mp` (`id`, `mp_id`, `tipo`, `cantidad`, `fecha`, `usuario_id`, `comentario`, `origen_id`) VALUES
(1, 11, 'entrada', 174000.00, '2025-10-05 08:58:06', 1, 'OC #2, línea 3, lote FACTB64449', NULL),
(2, 14, 'entrada', 173400.00, '2025-10-05 08:58:06', 1, 'OC #2, línea 4, lote FACTB64449', NULL),
(3, 58, 'entrada', 5000.00, '2025-10-05 14:08:41', 1, 'OC #3, línea 5, lote 6240051000', NULL),
(4, 11, 'entrada', 174000.00, '2025-10-08 18:27:55', 1, 'OC #5, línea 8, lote B 64472', NULL),
(5, 12, 'entrada', 176400.00, '2025-10-08 18:27:55', 1, 'OC #5, línea 9, lote B 64472', NULL),
(6, 28, 'entrada', 20000.00, '2025-10-09 18:05:43', 1, 'OC #4, línea 6, lote 240700671', NULL),
(7, 28, 'entrada', 6000.00, '2025-10-09 18:05:43', 1, 'OC #4, línea 7, lote MATEXIST', NULL),
(8, 12, 'entrada', 176100.00, '2025-10-10 18:54:42', 1, '', NULL),
(9, 12, 'salida', 176100.00, '2025-10-10 18:54:59', 1, '', NULL),
(10, 36, 'entrada', 18000.00, '2025-10-10 13:46:49', 1, 'OC #9, línea 16, lote 2516299', NULL),
(11, 14, 'salida', 33003.68, '2025-10-13 08:53:20', 1, 'Consumo OP #2, lote prod L20251013-0002, lote recep #2', NULL),
(12, 11, 'salida', 163121.64, '2025-10-13 08:53:20', 1, 'Consumo OP #2, lote prod L20251013-0002, lote recep #1', NULL),
(13, 11, 'salida', 705.00, '2025-10-15 17:43:44', 1, 'Consumo OP #1, lote prod L20251015-0001, lote recep #1', NULL),
(14, 58, 'salida', 705.00, '2025-10-15 17:43:44', 1, 'Consumo OP #1, lote prod L20251015-0001, lote recep #3', NULL),
(15, 14, 'salida', 34740.72, '2025-10-15 17:46:15', 1, 'Consumo OP #6, lote prod L20251015-0006, lote recep #2', NULL),
(16, 11, 'salida', 171706.98, '2025-10-15 17:46:15', 1, 'Consumo OP #6, lote prod L20251015-0006, lote recep #1', NULL),
(17, 13, 'salida', 20564.91, '2025-10-15 17:46:15', 1, 'Consumo OP #6, lote prod L20251015-0006, lote recep #11', NULL),
(18, 21, 'salida', 71877.34, '2025-10-15 17:46:15', 1, 'Consumo OP #6, lote prod L20251015-0006, lote recep #18', NULL),
(19, 12, 'salida', 52710.05, '2025-10-15 17:46:15', 1, 'Consumo OP #6, lote prod L20251015-0006, lote recep #5', NULL),
(20, 28, 'salida', 6000.00, '2025-10-16 14:28:27', 1, 'Consumo OP #3, lote prod L20251016-0003, lote recep #7', NULL),
(21, 28, 'salida', 3043.76, '2025-10-16 14:28:27', 1, 'Consumo OP #3, lote prod L20251016-0003, lote recep #6', NULL),
(22, 12, 'salida', 6330.63, '2025-10-16 14:28:27', 1, 'Consumo OP #3, lote prod L20251016-0003, lote recep #5', NULL),
(23, 11, 'salida', 1588.02, '2025-10-16 14:28:27', 1, 'Consumo OP #3, lote prod L20251016-0003, lote recep #1', NULL),
(24, 11, 'salida', 1125.11, '2025-10-16 14:28:27', 1, 'Consumo OP #3, lote prod L20251016-0003, lote recep #4', NULL),
(25, 28, 'salida', 6782.82, '2025-10-16 14:29:12', 1, 'Consumo OP #4, lote prod L20251016-0004, lote recep #6', NULL),
(26, 12, 'salida', 4747.97, '2025-10-16 14:29:12', 1, 'Consumo OP #4, lote prod L20251016-0004, lote recep #5', NULL),
(27, 11, 'salida', 2034.85, '2025-10-16 14:29:12', 1, 'Consumo OP #4, lote prod L20251016-0004, lote recep #4', NULL),
(28, 28, 'salida', 3843.60, '2025-10-16 14:30:01', 1, 'Consumo OP #5, lote prod L20251016-0005, lote recep #6', NULL),
(29, 12, 'salida', 2690.52, '2025-10-16 14:30:01', 1, 'Consumo OP #5, lote prod L20251016-0005, lote recep #5', NULL),
(30, 11, 'salida', 1153.08, '2025-10-16 14:30:01', 1, 'Consumo OP #5, lote prod L20251016-0005, lote recep #4', NULL),
(31, 36, 'entrada', 18000.00, '2025-10-16 17:41:33', 1, 'OC #100000, línea 51, lote 2517698', NULL),
(32, 15, 'salida', 3500.00, '2025-10-16 17:43:23', 1, 'Consumo OP #12, lote prod L20251016-0012, lote recep #12', NULL),
(33, 12, 'salida', 7000.00, '2025-10-16 17:43:23', 1, 'Consumo OP #12, lote prod L20251016-0012, lote recep #5', NULL),
(34, 19, 'salida', 10.18, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #16', NULL),
(35, 46, 'salida', 10.18, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #31', NULL),
(36, 47, 'salida', 10.18, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #32', NULL),
(37, 48, 'salida', 15.28, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #33', NULL),
(38, 45, 'salida', 8.15, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #30', NULL),
(39, 12, 'salida', 2036.69, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #5', NULL),
(40, 17, 'salida', 2036.69, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #14', NULL),
(41, 26, 'salida', 12220.14, '2025-10-16 17:43:39', 1, 'Consumo OP #8, lote prod L20251016-0008, lote recep #21', NULL),
(42, 8, 'entrada', 25000.00, '2025-10-16 17:44:54', 1, 'OC #7, línea 13, lote 1626530065', NULL),
(43, 19, 'salida', 10.00, '2025-10-16 17:46:53', 1, 'Consumo OP #13, lote prod L20251016-0013, lote recep #16', NULL),
(44, 11, 'salida', 14000.00, '2025-10-16 17:46:53', 1, 'Consumo OP #13, lote prod L20251016-0013, lote recep #4', NULL),
(45, 22, 'salida', 200.00, '2025-10-16 17:46:53', 1, 'Consumo OP #13, lote prod L20251016-0013, lote recep #19', NULL),
(46, 8, 'salida', 24500.00, '2025-10-16 17:46:53', 1, 'Consumo OP #13, lote prod L20251016-0013, lote recep #41', NULL),
(47, 17, 'salida', 8500.00, '2025-10-16 17:46:53', 1, 'Consumo OP #13, lote prod L20251016-0013, lote recep #14', NULL),
(48, 45, 'salida', 254.40, '2025-10-17 12:01:24', 1, 'Consumo OP #14, lote prod L20251017-0014, lote recep #30', NULL),
(49, 21, 'salida', 6500.00, '2025-10-17 12:03:53', 1, 'Consumo OP #16, lote prod L20251017-0016, lote recep #18', NULL),
(50, 12, 'salida', 6500.00, '2025-10-17 12:03:53', 1, 'Consumo OP #16, lote prod L20251017-0016, lote recep #5', NULL),
(51, 16, 'salida', 2000.00, '2025-10-17 12:03:53', 1, 'Consumo OP #16, lote prod L20251017-0016, lote recep #13', NULL),
(52, 13, 'salida', 504.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #11', NULL),
(53, 12, 'salida', 3800.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #5', NULL),
(54, 21, 'salida', 1200.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #18', NULL),
(55, 11, 'salida', 2504.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #4', NULL),
(56, 20, 'salida', 48.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #17', NULL),
(57, 19, 'salida', 48.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #16', NULL),
(58, 18, 'salida', 600.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #15', NULL),
(59, 17, 'salida', 35160.00, '2025-10-17 12:04:40', 1, 'Consumo OP #15, lote prod L20251017-0015, lote recep #14', NULL),
(60, 16, 'entrada', 20000.00, '2025-10-17 12:13:45', 1, 'OC #1, línea 1, lote C-16160B', NULL),
(61, 51, 'entrada', 5000.00, '2025-10-17 12:13:46', 1, 'OC #1, línea 2, lote 30ALU25-503', NULL),
(62, 36, 'entrada', 18000.00, '2025-10-17 19:55:50', 1, 'OC #100001, línea 52, lote 2412531', NULL),
(63, 51, 'salida', 4348.88, '2025-10-17 20:15:58', 1, 'Consumo OP #19, lote prod L20251018-0019, lote recep #46', NULL),
(64, 48, 'salida', 114.44, '2025-10-17 20:15:58', 1, 'Consumo OP #19, lote prod L20251018-0019, lote recep #33', NULL),
(65, 12, 'salida', 5035.54, '2025-10-17 20:15:58', 1, 'Consumo OP #19, lote prod L20251018-0019, lote recep #5', NULL),
(66, 11, 'salida', 5035.54, '2025-10-17 20:15:58', 1, 'Consumo OP #19, lote prod L20251018-0019, lote recep #4', NULL),
(67, 24, 'entrada', 13180.00, '2025-10-20 18:31:03', 1, 'OC #8, línea 14, lote NO EXPLICA', NULL),
(68, 23, 'entrada', 14600.00, '2025-10-20 18:31:03', 1, 'OC #8, línea 15, lote NO EXPLICA', NULL),
(69, 24, 'salida', 4134.00, '2025-10-20 18:37:52', 1, 'Consumo OP #20, lote prod L20251021-0020, lote recep #51', NULL),
(70, 23, 'salida', 4134.00, '2025-10-20 18:37:52', 1, 'Consumo OP #20, lote prod L20251021-0020, lote recep #52', NULL),
(71, 11, 'salida', 1195.86, '2025-10-20 18:39:18', 1, 'Consumo OP #21, lote prod L20251021-0021, lote recep #4', NULL),
(72, 12, 'salida', 874.05, '2025-10-20 18:39:18', 1, 'Consumo OP #21, lote prod L20251021-0021, lote recep #5', NULL),
(73, 21, 'salida', 715.14, '2025-10-20 18:39:18', 1, 'Consumo OP #21, lote prod L20251021-0021, lote recep #18', NULL),
(74, 13, 'salida', 357.57, '2025-10-20 18:39:18', 1, 'Consumo OP #21, lote prod L20251021-0021, lote recep #11', NULL),
(75, 14, 'salida', 385.38, '2025-10-20 18:39:18', 1, 'Consumo OP #21, lote prod L20251021-0021, lote recep #2', NULL),
(76, 8, 'entrada', 25000.00, '2025-10-20 18:44:03', 1, 'OC #100004, línea 57, lote 8733590193', NULL),
(77, 28, 'entrada', 20000.00, '2025-10-21 14:21:23', 1, 'OC #900003, línea 60, lote 2407006171', NULL),
(78, 38, 'entrada', 18500.00, '2025-10-21 14:26:20', 5, 'OC #900002, línea 58, lote 241251', NULL),
(79, 36, 'entrada', 18000.00, '2025-10-21 14:26:20', 5, 'OC #900002, línea 59, lote 240700671', NULL),
(80, 12, 'salida', 15670.25, '2025-10-21 14:50:35', 1, 'Consumo OP #22, lote prod L20251021-0022, lote recep #5', NULL),
(81, 13, 'salida', 4075.85, '2025-10-21 14:50:35', 1, 'Consumo OP #22, lote prod L20251021-0022, lote recep #11', NULL),
(82, 11, 'salida', 13612.54, '2025-10-21 14:50:35', 1, 'Consumo OP #22, lote prod L20251021-0022, lote recep #4', NULL),
(83, 14, 'salida', 1721.35, '2025-10-21 14:50:35', 1, 'Consumo OP #22, lote prod L20251021-0022, lote recep #2', NULL),
(84, 11, 'entrada', 348000.00, '2025-11-07 18:00:44', 1, 'OC #900006, línea 63, lote NO EXPLICA', NULL),
(85, 21, 'entrada', 180400.00, '2025-11-07 18:00:44', 1, 'OC #900006, línea 64, lote NO EXPLICA', NULL),
(86, 12, 'entrada', 176400.00, '2025-11-07 18:00:44', 1, 'OC #900006, línea 65, lote NO EXPLICA', NULL),
(87, 13, 'entrada', 45000.00, '2025-11-07 18:00:44', 1, 'OC #900006, línea 66, lote NO EXPLICA', NULL),
(88, 12, 'entrada', 5974.01, '2025-11-10 19:37:02', 1, '', NULL),
(89, 12, 'salida', 5974.01, '2025-11-10 19:37:59', 1, '', NULL),
(90, 36, 'entrada', 3000.00, '2025-11-10 19:38:32', 1, '', NULL),
(91, 28, 'salida', 4322.99, '2025-11-10 19:39:56', 1, '', NULL),
(92, 14, 'salida', 15662.99, '2025-11-10 19:40:15', 1, '', NULL),
(93, 36, 'entrada', 3000.00, '2025-11-10 19:59:50', 1, '', NULL),
(94, 36, 'entrada', 3000.00, '2025-11-10 20:12:54', 1, '', NULL),
(95, 36, 'salida', 3000.00, '2025-11-10 20:13:22', 1, '', NULL),
(96, 36, 'entrada', 3000.00, '2025-11-10 20:14:35', 1, '', NULL),
(97, 36, 'entrada', 3000.00, '2025-11-10 20:28:55', 1, '', NULL),
(98, 11, 'entrada', 45678.99, '2025-11-10 20:29:16', 1, '', NULL),
(99, 36, 'salida', 4503.53, '2025-11-10 21:08:03', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #56', NULL),
(100, 17, 'salida', 1000.79, '2025-11-10 21:08:03', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #14', NULL),
(101, 11, 'salida', 2501.96, '2025-11-10 21:08:03', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #4', NULL),
(102, 12, 'salida', 2501.96, '2025-11-10 21:08:03', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #5', NULL),
(103, 48, 'salida', 45.04, '2025-11-10 21:08:03', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #33', NULL),
(104, 19, 'salida', 3.00, '2025-11-10 21:08:04', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #16', NULL),
(105, 45, 'salida', 12.01, '2025-11-10 21:08:04', 1, 'Consumo OP #24, lote prod L20251111-0024, lote recep #30', NULL),
(106, 11, 'salida', 2000.55, '2026-01-05 17:50:17', 1, 'Consumo OP #28, lote prod L20260105-0028, lote recep #4', NULL),
(107, 12, 'salida', 2000.55, '2026-01-05 17:50:17', 1, 'Consumo OP #28, lote prod L20260105-0028, lote recep #5', NULL),
(108, 11, 'salida', 600.17, '2026-01-05 17:51:02', 1, 'Consumo OP #29, lote prod L20260105-0029, lote recep #4', NULL),
(109, 12, 'salida', 600.17, '2026-01-05 17:51:02', 1, 'Consumo OP #29, lote prod L20260105-0029, lote recep #5', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nomina`
--

CREATE TABLE `nomina` (
  `id` int(11) NOT NULL,
  `nombre_empleado` varchar(100) DEFAULT NULL,
  `puesto` varchar(100) DEFAULT NULL,
  `sueldo_mensual` decimal(10,2) DEFAULT NULL,
  `horas_estimadas` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `oc_tiene_recepciones`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `oc_tiene_recepciones` (
`oc_id` int(10) unsigned
,`n_recepciones` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_compra`
--

CREATE TABLE `ordenes_compra` (
  `id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `entrega_domicilio` tinyint(1) NOT NULL DEFAULT 0,
  `solicitante_id` int(10) UNSIGNED NOT NULL,
  `autorizador_id` int(10) UNSIGNED DEFAULT NULL,
  `modificador_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `estado` enum('pendiente','autorizada','rechazada','pagada','cerrada') NOT NULL DEFAULT 'pendiente',
  `moneda` varchar(3) NOT NULL DEFAULT 'MXN',
  `tipo_cambio` decimal(14,6) NOT NULL DEFAULT 1.000000,
  `subtotal_neto` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `iva_monto` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `total_con_iva` decimal(14,4) NOT NULL DEFAULT 0.0000,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fecha_autorizacion` datetime DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `fecha_modificacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `ordenes_compra`
--

INSERT INTO `ordenes_compra` (`id`, `proveedor_id`, `entrega_domicilio`, `solicitante_id`, `autorizador_id`, `modificador_id`, `fecha_emision`, `estado`, `moneda`, `tipo_cambio`, `subtotal_neto`, `iva_monto`, `total_con_iva`, `total`, `fecha_autorizacion`, `fecha_pago`, `fecha_modificacion`) VALUES
(1, 3, 0, 5, 1, 1, '2025-10-04', 'cerrada', 'MXN', 1.000000, 9498.3369, 1519.7300, 11018.0669, 0.00, '2025-10-09 17:54:37', '2025-10-09 17:55:03', '2025-10-10 08:39:25'),
(2, 17, 0, 1, 1, 1, '2025-10-04', 'cerrada', 'MXN', 1.000000, 8700.0870, 1392.0100, 10092.0970, 0.00, '2025-10-05 08:51:47', '2025-10-05 08:51:51', '2025-10-05 08:51:47'),
(3, 3, 0, 1, 1, 1, '2025-10-05', 'cerrada', 'MXN', 1.000000, 6458.0000, 1033.2800, 7491.2800, 0.00, '2025-10-05 08:51:36', '2025-10-05 08:51:55', '2025-10-05 08:51:36'),
(4, 11, 0, 1, 1, 1, '2025-10-08', 'cerrada', 'MXN', 1.000000, 3768.1800, 602.9100, 4371.0900, 0.00, '2025-10-08 18:09:59', '2025-10-08 18:10:05', '2025-10-09 18:01:08'),
(5, 17, 0, 1, 1, 1, '2025-10-08', 'cerrada', 'MXN', 1.000000, 9838.8480, 1574.2200, 11413.0680, 0.00, '2025-10-08 18:11:15', '2025-10-08 18:27:04', '2025-10-08 18:11:15'),
(6, 9, 0, 1, 1, 1, '2025-10-09', 'cerrada', 'MXN', 1.000000, 7147.6000, 1143.6200, 8291.2200, 0.00, '2025-10-08 18:17:25', '2025-10-08 18:19:10', '2025-10-08 18:17:25'),
(7, 1, 0, 1, 1, 1, '2025-10-09', 'cerrada', 'MXN', 1.000000, 1828.4250, 292.5500, 2120.9750, 0.00, '2025-10-09 17:55:34', '2025-10-10 08:10:34', '2025-10-09 17:55:34'),
(8, 17, 0, 1, 1, 1, '2025-10-10', 'cerrada', 'MXN', 1.000000, 774.1818, 123.8700, 898.0518, 0.00, '2025-10-10 09:24:56', '2025-10-16 17:53:42', '2025-10-10 09:24:56'),
(9, 4, 0, 1, 1, 1, '2025-10-10', 'cerrada', 'MXN', 1.000000, 1571.7420, 251.4800, 1823.2220, 0.00, '2025-10-10 13:46:13', '2025-10-10 13:46:20', '2025-10-10 13:46:13'),
(99998, 19, 0, 1, NULL, NULL, '2025-10-15', 'cerrada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL),
(100000, 4, 0, 1, 1, 1, '2025-10-16', 'cerrada', 'MXN', 1.000000, 1583.4600, 253.3500, 1836.8100, 0.00, '2025-10-15 20:32:17', '2025-10-15 20:32:24', '2025-10-15 20:32:17'),
(100001, 4, 0, 1, 1, 1, '2025-10-17', 'cerrada', 'MXN', 1.000000, 1573.2000, 251.7100, 1824.9100, 0.00, '2025-10-17 18:14:59', '2025-10-17 18:15:11', '2025-10-17 18:14:59'),
(100002, 9, 0, 1, 1, 1, '2025-10-18', 'cerrada', 'MXN', 1.000000, 3302.2000, 528.3500, 3830.5500, 0.00, '2025-10-17 21:27:21', '2025-10-17 21:27:30', '2025-10-17 21:27:21'),
(100003, 3, 0, 4, 1, 1, '2025-10-20', 'autorizada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, '2026-01-05 17:52:15', NULL, '2026-01-05 18:01:53'),
(100004, 1, 0, 1, 1, 1, '2025-10-20', 'cerrada', 'MXN', 1.000000, 1831.8500, 293.1000, 2124.9500, 0.00, '2025-10-20 17:30:39', '2025-10-20 17:31:18', '2025-10-20 17:30:39'),
(900002, 4, 0, 1, 1, 1, '2025-10-21', 'cerrada', 'MXN', 1.000000, 3207.4850, 513.2000, 3720.6850, 0.00, '2025-10-21 11:20:37', '2025-10-21 11:29:23', '2025-10-21 11:20:37'),
(900003, 11, 0, 5, 1, 1, '2025-10-20', 'cerrada', 'MXN', 18.480800, 2513.3800, 402.1400, 2915.5200, 0.00, '2025-10-21 14:20:44', '2025-10-21 14:20:53', '2025-10-21 14:20:44'),
(900004, 8, 0, 1, 1, 1, '2025-10-21', 'pagada', 'MXN', 1.000000, 11000.0000, 1760.0000, 12760.0000, 0.00, '2025-10-21 17:24:34', '2025-10-21 17:31:38', '2025-10-21 17:24:34'),
(900005, 8, 0, 1, 1, 1, '2025-10-28', 'pagada', 'MXN', 1.000000, 18400.0000, 2944.0000, 21344.0000, 0.00, '2025-10-28 06:51:41', '2025-11-07 18:02:58', '2025-10-28 06:51:41'),
(900006, 17, 0, 1, 1, 1, '2025-11-07', 'cerrada', 'MXN', 1.000000, 20799.1220, 3327.8600, 24126.9820, 0.00, '2025-11-07 17:59:22', '2025-11-07 17:59:56', '2025-11-07 17:59:22'),
(900007, 20, 0, 1, NULL, NULL, '2025-11-10', 'cerrada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL),
(900008, 20, 0, 1, NULL, NULL, '2025-11-10', 'cerrada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL),
(900009, 21, 0, 1, NULL, NULL, '2025-11-10', 'cerrada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL),
(900010, 2, 0, 4, 1, 1, '2025-11-11', 'autorizada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, '2026-01-05 18:03:30', NULL, '2026-01-05 18:04:02'),
(900011, 11, 0, 4, 1, 1, '2025-11-11', 'autorizada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, '2026-01-05 17:52:54', NULL, '2026-01-05 17:54:07'),
(900012, 8, 0, 1, 1, 1, '2026-01-06', 'autorizada', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, '2026-01-05 18:05:54', NULL, '2026-01-05 18:05:54'),
(900013, 11, 0, 1, NULL, NULL, '2026-01-06', 'pendiente', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL),
(900014, 4, 0, 1, NULL, NULL, '2026-01-06', 'pendiente', 'MXN', 1.000000, 0.0000, 0.0000, 0.0000, 0.00, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_produccion`
--

CREATE TABLE `ordenes_produccion` (
  `id` int(11) NOT NULL,
  `ficha_id` int(11) DEFAULT NULL,
  `cantidad_a_producir` decimal(10,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','completada') DEFAULT 'pendiente',
  `fecha_inicio` date DEFAULT NULL,
  `hora_inicio` datetime DEFAULT NULL,
  `hora_fin` datetime DEFAULT NULL,
  `lote` varchar(50) DEFAULT NULL,
  `usuario_creador` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `estado_autorizacion` enum('pendiente','autorizada','rechazada') DEFAULT 'pendiente',
  `autorizado_por` int(11) DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unidad` varchar(2) NOT NULL DEFAULT 'g',
  `cancelada` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_cancelacion` text DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `cancelado_en` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `ordenes_produccion`
--

INSERT INTO `ordenes_produccion` (`id`, `ficha_id`, `cantidad_a_producir`, `fecha`, `estado`, `fecha_inicio`, `hora_inicio`, `hora_fin`, `lote`, `usuario_creador`, `producto_id`, `estado_autorizacion`, `autorizado_por`, `cantidad`, `unidad`, `cancelada`, `motivo_cancelacion`, `cancelado_por`, `cancelado_en`) VALUES
(1, 44, 846.00, '2025-10-04', 'completada', '2025-10-15', '2025-10-01 17:43:00', '2025-10-01 17:43:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(2, 18, 334020.00, '2025-10-06', 'completada', '2025-10-13', '2025-10-06 08:52:00', '2025-10-06 17:53:00', NULL, 4, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(3, 53, 18087.52, '2025-10-07', 'completada', '2025-10-16', '2025-10-07 10:10:00', '2025-10-07 17:30:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(4, 53, 13560.00, '2025-10-10', 'completada', '2025-10-16', '2025-10-08 14:28:00', '2025-10-08 17:28:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(5, 39, 7200.00, '2025-10-10', 'completada', '2025-10-16', '2025-10-09 11:29:00', '2025-10-09 14:29:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(6, 18, 334020.00, '2025-10-13', 'completada', '2025-10-15', '2025-10-06 10:44:00', '2025-10-07 17:45:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(7, 54, 20180.00, '2025-10-13', 'completada', '2025-10-16', '2025-10-13 15:47:00', '2025-10-13 14:47:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(8, 23, 16653.00, '2025-10-13', 'completada', '2025-10-16', '2025-10-09 17:43:00', '2025-10-09 17:43:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(9, 37, 10864.00, '2025-10-14', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'rechazada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(10, 36, 7243.00, '2025-10-14', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(11, 30, 8429.00, '2025-10-14', 'completada', '2025-10-17', '2025-10-10 12:05:00', '2025-10-10 12:05:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(12, 20, 10500.00, '2025-10-16', 'completada', '2025-10-16', '2025-10-16 17:43:00', '2025-10-16 17:43:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(13, 24, 47210.00, '2025-10-16', 'completada', '2025-10-16', '2025-10-10 09:46:00', '2025-10-10 14:46:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(14, 56, 226.68, '2025-10-17', 'completada', '2025-10-17', '2025-10-16 17:00:00', '2025-10-16 19:15:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(15, 22, 58424.00, '2025-10-17', 'completada', '2025-10-17', '2025-10-10 12:04:00', '2025-10-10 17:04:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(16, 19, 15000.00, '2025-10-17', 'completada', '2025-10-17', '2025-10-08 12:03:00', '2025-10-08 18:03:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(17, 29, 14540.00, '2025-10-17', 'completada', '2025-10-17', '2025-10-14 12:07:00', '2025-10-14 18:07:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(18, 38, 31290.00, '2025-10-18', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(19, 29, 14540.00, '2025-10-18', 'completada', '2025-10-18', '2025-10-14 10:15:00', '2025-10-14 13:30:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(20, 57, 8250.00, '2025-10-21', 'completada', '2025-10-21', '2025-10-18 11:30:00', '2025-10-18 12:35:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(21, 55, 3552.00, '2025-10-21', 'completada', '2025-10-21', '2025-10-14 09:30:00', '2025-10-14 10:40:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(22, 51, 35460.00, '2025-10-21', 'completada', '2025-10-21', '2025-10-17 10:50:00', '2025-10-17 11:50:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(23, 18, 668040.00, '2025-10-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(24, 59, 10704.00, '2025-11-11', 'completada', '2025-11-11', '2025-11-07 18:07:00', '2025-11-07 21:07:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(25, 26, 33360.00, '2025-11-11', 'pendiente', NULL, NULL, NULL, NULL, 4, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(26, 57, 66144.00, '2025-11-13', 'pendiente', NULL, NULL, NULL, NULL, 4, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(27, 20, 19.00, '2025-11-21', 'pendiente', NULL, NULL, NULL, NULL, 4, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(28, 34, 36780.00, '2026-01-05', 'completada', '2026-01-05', '2026-01-05 10:00:00', '2026-01-05 22:45:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(29, 34, 11034.00, '2026-01-05', 'completada', '2026-01-05', '2026-01-05 10:50:00', '2026-01-05 11:05:00', NULL, 1, NULL, 'autorizada', 1, 0.00, 'g', 0, NULL, NULL, NULL),
(30, 18, 528300.00, '2026-01-16', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(31, 38, 42000.00, '2026-01-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(32, 39, 21600.00, '2026-01-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(33, 53, 9000.00, '2026-01-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(34, 51, 17730.00, '2026-01-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(35, 61, 13500.00, '2026-01-27', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(36, 24, 23605.00, '2026-01-28', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL),
(37, 18, 501030.00, '2026-02-17', 'pendiente', NULL, NULL, NULL, NULL, 1, NULL, 'pendiente', NULL, 0.00, 'g', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_produccion_cancelaciones`
--

CREATE TABLE `ordenes_produccion_cancelaciones` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `motivo` text NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_venta`
--

CREATE TABLE `ordenes_venta` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `distribuidor_id` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `estado` enum('pendiente','proceso_surtido','Listo_entrega','entregado') DEFAULT 'pendiente',
  `usuario_creador` int(11) DEFAULT NULL,
  `incluye_paquete` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `ordenes_venta`
--

INSERT INTO `ordenes_venta` (`id`, `cliente_id`, `distribuidor_id`, `fecha`, `fecha_entrega`, `estado`, `usuario_creador`, `incluye_paquete`) VALUES
(2, 7, 1, '2025-10-06', '2025-10-08', 'entregado', 1, 1),
(3, NULL, 10, '2025-10-08', '2025-10-09', 'entregado', 1, 0),
(4, NULL, 9, '2025-10-08', '2025-10-10', 'entregado', 1, 0),
(5, NULL, 8, '2025-10-08', '2025-10-11', 'pendiente', 1, 0),
(6, NULL, 9, '2025-10-14', '2025-10-14', 'entregado', 1, 0),
(7, NULL, 1, '2025-10-16', '2025-10-15', 'entregado', 1, 0),
(8, 2, 1, '2025-10-16', '2025-10-20', 'pendiente', 5, 0),
(9, NULL, 8, '2025-10-16', '2025-10-17', 'pendiente', 5, 0),
(10, NULL, 1, '2025-10-17', '2025-10-17', 'pendiente', 5, 0),
(11, NULL, 1, '2025-10-20', '2025-10-20', 'pendiente', 5, 0),
(12, NULL, 10, '2025-10-20', '2025-10-20', 'pendiente', 5, 0),
(13, NULL, 9, '2025-10-20', '2025-10-21', 'pendiente', 5, 0),
(14, 2, 1, '2025-11-06', '2025-11-10', 'pendiente', 5, 0),
(15, NULL, 11, '2025-11-07', '2025-11-07', 'pendiente', 5, 0),
(16, NULL, 12, '2026-01-05', '2026-01-05', 'entregado', 1, 0),
(18, 2, 1, '2026-01-05', '2026-01-07', 'pendiente', 5, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_mp`
--

CREATE TABLE `orden_mp` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) DEFAULT NULL,
  `mp_id` int(11) DEFAULT NULL,
  `cantidad_usada` decimal(10,2) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `packaging_kits`
--

CREATE TABLE `packaging_kits` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `presentacion_id` int(11) NOT NULL,
  `insumo_comercial_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `packaging_kits`
--

INSERT INTO `packaging_kits` (`id`, `producto_id`, `presentacion_id`, `insumo_comercial_id`, `cantidad`) VALUES
(15, 15, 20, 4, 1.00),
(17, 15, 20, 7, 2.00),
(16, 15, 20, 5, 1.00),
(14, 15, 20, 6, 1.00),
(5, 18, 19, 10, 1.00),
(6, 18, 19, 11, 1.00),
(7, 18, 19, 12, 1.00),
(8, 18, 19, 7, 1.00),
(9, 18, 19, 13, 1.00),
(10, 18, 21, 8, 1.00),
(11, 18, 21, 9, 1.00),
(12, 18, 21, 13, 1.00),
(13, 18, 21, 7, 2.00),
(18, 17, 21, 8, 1.00),
(19, 17, 21, 9, 1.00),
(20, 17, 21, 13, 1.00),
(21, 17, 21, 7, 2.00),
(320, 19, 21, 7, 2.00),
(317, 19, 19, 7, 1.00),
(314, 19, 19, 10, 1.00),
(315, 19, 19, 11, 1.00),
(316, 19, 19, 13, 1.00),
(318, 19, 21, 8, 1.00),
(319, 19, 21, 9, 1.00),
(321, 19, 21, 13, 1.00),
(237, 27, 18, 14, 1.00),
(239, 27, 18, 16, 1.00),
(238, 27, 18, 15, 1.00),
(240, 27, 18, 13, 1.00),
(241, 27, 18, 17, 1.00),
(242, 27, 18, 7, 2.00),
(248, 27, 20, 18, 1.00),
(243, 27, 20, 4, 1.00),
(247, 27, 20, 13, 1.00),
(244, 27, 20, 5, 1.00),
(245, 27, 20, 7, 2.00),
(246, 27, 20, 17, 1.00),
(136, 28, 17, 13, 1.00),
(134, 28, 17, 22, 1.00),
(135, 28, 17, 11, 1.00),
(137, 28, 17, 7, 1.00),
(129, 28, 15, 19, 1.00),
(130, 28, 15, 11, 1.00),
(132, 28, 15, 7, 1.00),
(131, 28, 15, 23, 1.00),
(133, 28, 17, 25, 1.00),
(138, 28, 21, 8, 1.00),
(139, 28, 21, 9, 1.00),
(140, 28, 21, 7, 2.00),
(141, 28, 21, 13, 1.00),
(189, 29, 18, 10, 1.00),
(190, 29, 18, 26, 1.00),
(192, 29, 18, 13, 1.00),
(193, 29, 18, 7, 1.00),
(191, 29, 18, 11, 1.00),
(187, 29, 16, 7, 1.00),
(184, 29, 16, 24, 1.00),
(186, 29, 16, 11, 1.00),
(185, 29, 16, 21, 1.00),
(188, 29, 16, 13, 1.00),
(152, 30, 20, 4, 1.00),
(153, 30, 20, 5, 1.00),
(154, 30, 20, 6, 1.00),
(155, 30, 20, 7, 2.00),
(156, 31, 20, 4, 1.00),
(157, 31, 20, 5, 1.00),
(158, 31, 20, 6, 1.00),
(159, 31, 20, 7, 2.00),
(160, 32, 20, 4, 1.00),
(161, 32, 20, 5, 1.00),
(162, 32, 20, 6, 1.00),
(163, 32, 20, 7, 2.00),
(164, 33, 20, 4, 1.00),
(165, 33, 20, 5, 1.00),
(166, 33, 20, 6, 1.00),
(167, 33, 20, 7, 2.00),
(168, 35, 20, 4, 1.00),
(169, 35, 20, 5, 1.00),
(170, 35, 20, 6, 1.00),
(171, 35, 20, 7, 2.00),
(179, 37, 20, 7, 2.00),
(178, 37, 20, 6, 1.00),
(177, 37, 20, 5, 1.00),
(176, 37, 20, 4, 1.00),
(180, 38, 20, 4, 1.00),
(181, 38, 20, 5, 1.00),
(182, 38, 20, 6, 1.00),
(183, 38, 20, 7, 2.00),
(207, 39, 18, 27, 1.00),
(206, 39, 18, 11, 1.00),
(205, 39, 18, 7, 1.00),
(204, 39, 18, 13, 1.00),
(203, 39, 18, 10, 1.00),
(208, 39, 20, 4, 1.00),
(209, 39, 20, 5, 1.00),
(210, 39, 20, 28, 1.00),
(211, 39, 20, 7, 2.00),
(212, 41, 20, 4, 1.00),
(213, 41, 20, 5, 1.00),
(214, 41, 20, 6, 1.00),
(215, 41, 20, 7, 2.00),
(216, 44, 20, 4, 1.00),
(217, 44, 20, 5, 1.00),
(218, 44, 20, 6, 1.00),
(219, 44, 20, 7, 2.00),
(221, 45, 23, 29, 1.00),
(222, 45, 23, 30, 1.00),
(223, 46, 20, 4, 1.00),
(224, 46, 20, 5, 1.00),
(225, 46, 20, 6, 1.00),
(226, 46, 20, 7, 2.00),
(227, 48, 20, 4, 1.00),
(228, 48, 20, 5, 1.00),
(229, 48, 20, 6, 1.00),
(230, 48, 20, 7, 2.00),
(252, 49, 18, 13, 1.00),
(251, 49, 18, 16, 1.00),
(250, 49, 18, 15, 1.00),
(253, 49, 18, 17, 1.00),
(249, 49, 18, 14, 1.00),
(254, 49, 18, 7, 2.00),
(255, 49, 20, 4, 1.00),
(256, 49, 20, 5, 1.00),
(257, 49, 20, 18, 1.00),
(258, 49, 20, 13, 1.00),
(259, 49, 20, 17, 1.00),
(260, 49, 20, 7, 2.00),
(261, 50, 18, 14, 1.00),
(262, 50, 18, 15, 1.00),
(263, 50, 18, 16, 1.00),
(264, 50, 18, 13, 1.00),
(265, 50, 18, 17, 1.00),
(266, 50, 18, 7, 2.00),
(267, 50, 20, 4, 1.00),
(268, 50, 20, 5, 1.00),
(269, 50, 20, 18, 1.00),
(270, 50, 20, 13, 1.00),
(271, 50, 20, 17, 1.00),
(272, 50, 20, 7, 2.00),
(273, 51, 18, 14, 1.00),
(274, 51, 18, 15, 1.00),
(275, 51, 18, 16, 1.00),
(276, 51, 18, 13, 1.00),
(277, 51, 18, 17, 1.00),
(278, 51, 18, 7, 2.00),
(279, 51, 20, 4, 1.00),
(280, 51, 20, 5, 1.00),
(281, 51, 20, 18, 1.00),
(282, 51, 20, 13, 1.00),
(283, 51, 20, 17, 1.00),
(284, 51, 20, 7, 2.00),
(293, 52, 17, 22, 1.00),
(294, 52, 17, 13, 1.00),
(295, 52, 17, 7, 1.00),
(296, 52, 19, 10, 1.00),
(297, 52, 19, 13, 1.00),
(298, 52, 19, 7, 1.00),
(299, 52, 21, 8, 1.00),
(300, 52, 21, 13, 1.00),
(301, 52, 21, 7, 2.00),
(302, 53, 18, 10, 1.00),
(303, 53, 18, 11, 1.00),
(304, 53, 18, 13, 1.00),
(305, 53, 18, 7, 1.00),
(306, 54, 18, 10, 1.00),
(307, 54, 18, 11, 1.00),
(308, 54, 18, 13, 1.00),
(309, 54, 18, 7, 1.00),
(310, 54, 21, 13, 1.00),
(311, 54, 21, 8, 1.00),
(312, 54, 21, 9, 1.00),
(313, 54, 21, 7, 2.00),
(322, 19, 22, 31, 1.00),
(323, 19, 22, 13, 1.00),
(324, 19, 22, 7, 2.00),
(334, 55, 20, 5, 1.00),
(333, 55, 20, 4, 1.00),
(336, 55, 20, 7, 2.00),
(335, 55, 20, 13, 1.00),
(337, 55, 22, 32, 1.00),
(343, 57, 25, 34, 1.00),
(342, 57, 25, 33, 1.00),
(349, 59, 18, 15, 1.00),
(348, 59, 18, 14, 1.00),
(351, 59, 18, 13, 1.00),
(350, 59, 18, 7, 2.00),
(352, 59, 18, 35, 1.00),
(357, 60, 26, 15, 1.00),
(356, 60, 26, 14, 1.00),
(358, 60, 26, 7, 1.00),
(359, 61, 15, 19, 1.00),
(360, 61, 15, 11, 1.00),
(361, 61, 15, 7, 1.00),
(376, 62, 21, 7, 2.00),
(375, 62, 21, 9, 1.00),
(377, 62, 21, 13, 1.00),
(374, 62, 21, 8, 1.00),
(372, 62, 18, 7, 1.00),
(371, 62, 18, 11, 1.00),
(373, 62, 18, 13, 1.00),
(370, 62, 18, 10, 1.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `packaging_requests`
--

CREATE TABLE `packaging_requests` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `solicitante_id` int(11) NOT NULL,
  `estado` enum('pendiente','autorizada','rechazada') NOT NULL DEFAULT 'pendiente',
  `autorizador_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `autorizado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `packaging_requests`
--

INSERT INTO `packaging_requests` (`id`, `orden_id`, `solicitante_id`, `estado`, `autorizador_id`, `creado_en`, `autorizado_en`) VALUES
(1, 1, 1, 'pendiente', NULL, '2025-10-15 17:43:44', NULL),
(3, 3, 1, 'autorizada', 1, '2025-10-16 14:28:27', '2025-10-20 18:24:11'),
(4, 4, 1, 'autorizada', 1, '2025-10-16 14:29:12', '2025-10-20 18:23:57'),
(6, 7, 1, 'autorizada', 1, '2025-10-16 17:47:34', '2025-10-20 18:23:47'),
(7, 14, 1, 'autorizada', 1, '2025-10-17 12:01:24', '2025-10-20 18:23:41'),
(8, 11, 1, 'autorizada', 1, '2025-10-17 12:05:59', '2025-10-20 18:23:27'),
(9, 19, 1, 'autorizada', 4, '2025-10-17 20:15:58', '2025-10-20 17:24:23'),
(13, 24, 1, 'autorizada', 1, '2025-11-10 21:08:04', '2025-12-16 20:42:07'),
(14, 28, 1, 'pendiente', NULL, '2026-01-05 17:50:17', NULL),
(15, 29, 1, 'pendiente', NULL, '2026-01-05 17:51:02', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `packaging_request_items`
--

CREATE TABLE `packaging_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `insumo_comercial_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `cantidad_solicitada` decimal(10,2) DEFAULT NULL,
  `cantidad_autorizada` decimal(10,2) DEFAULT NULL,
  `aprobado` tinyint(1) NOT NULL DEFAULT 1,
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `packaging_request_items`
--

INSERT INTO `packaging_request_items` (`id`, `request_id`, `insumo_comercial_id`, `cantidad`, `cantidad_solicitada`, `cantidad_autorizada`, `aprobado`, `motivo`) VALUES
(1, 1, 29, 50.00, 50.00, NULL, 1, NULL),
(2, 1, 30, 50.00, 50.00, NULL, 1, NULL),
(3, 3, 7, 20.00, 20.00, 20.00, 1, NULL),
(4, 3, 10, 20.00, 20.00, 20.00, 1, NULL),
(5, 3, 11, 20.00, 20.00, 20.00, 1, NULL),
(6, 3, 13, 20.00, 20.00, 20.00, 1, NULL),
(7, 4, 7, 15.00, 15.00, 15.00, 1, NULL),
(8, 4, 10, 15.00, 15.00, 15.00, 1, NULL),
(9, 4, 11, 15.00, 15.00, 15.00, 1, NULL),
(10, 4, 13, 15.00, 15.00, 15.00, 1, NULL),
(11, 6, 32, 1.00, 1.00, 1.00, 1, NULL),
(12, 7, 33, 12.00, 12.00, 12.00, 1, NULL),
(13, 7, 34, 12.00, 12.00, 12.00, 1, NULL),
(14, 8, 4, 2.00, 2.00, 2.00, 1, NULL),
(15, 8, 5, 2.00, 2.00, 2.00, 1, NULL),
(16, 8, 6, 2.00, 2.00, 2.00, 1, NULL),
(17, 8, 7, 4.00, 4.00, 4.00, 1, NULL),
(18, 9, 4, 4.00, 4.00, NULL, 1, NULL),
(19, 9, 5, 4.00, 4.00, NULL, 1, NULL),
(20, 9, 6, 4.00, 4.00, NULL, 1, NULL),
(21, 9, 7, 8.00, 8.00, NULL, 1, NULL),
(22, 13, 7, 14.00, 14.00, 14.00, 1, NULL),
(23, 13, 14, 14.00, 14.00, 14.00, 1, NULL),
(24, 13, 15, 14.00, 14.00, 14.00, 1, NULL),
(25, 14, 4, 10.00, 10.00, NULL, 1, NULL),
(26, 14, 5, 10.00, 10.00, NULL, 1, NULL),
(27, 14, 6, 10.00, 10.00, NULL, 1, NULL),
(28, 14, 7, 20.00, 20.00, NULL, 1, NULL),
(29, 15, 4, 3.00, 3.00, NULL, 1, NULL),
(30, 15, 5, 3.00, 3.00, NULL, 1, NULL),
(31, 15, 6, 3.00, 3.00, NULL, 1, NULL),
(32, 15, 7, 6.00, 6.00, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parametros_sistema`
--

CREATE TABLE `parametros_sistema` (
  `clave` varchar(64) NOT NULL,
  `valor` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `parametros_sistema`
--

INSERT INTO `parametros_sistema` (`clave`, `valor`) VALUES
('presentacion_gramos_id', '10'),
('litros_por_cubeta', '19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_cliente`
--

CREATE TABLE `precios_cliente` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `lista_precio_id` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presentaciones`
--

CREATE TABLE `presentaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(20) DEFAULT NULL,
  `volumen_ml` int(11) DEFAULT NULL,
  `slug` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `presentaciones`
--

INSERT INTO `presentaciones` (`id`, `nombre`, `volumen_ml`, `slug`) VALUES
(10, 'Gramos', NULL, 'gramos'),
(15, 'Envase 119 ml', 119, 'ml_119'),
(16, 'Envase 237 ml', 237, 'ml_236'),
(17, 'Envase 473 ml', 473, 'ml_473'),
(18, 'Envase 946 ml', 946, 'ml_946'),
(19, 'Envase 1000 ml', 1000, 'ml_1000'),
(20, 'Envase 3785 ml', 3785, 'ml_3785'),
(21, 'Envase 4000 ml', 4000, 'ml_4000'),
(22, 'Envase 19000 ml', 19000, 'ml_19000'),
(23, 'Envase 30 ml', 30, 'ml_30'),
(24, 'Envase 20000 ml', 20000, 'ml_20000'),
(25, 'Envase 20 ml', 20, 'ml_20'),
(26, 'Envase 821 ml', 821, 'ml_821');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_consumos`
--

CREATE TABLE `produccion_consumos` (
  `id` int(11) NOT NULL,
  `produccion_id` int(11) NOT NULL,
  `mp_id` int(10) UNSIGNED NOT NULL,
  `lote_recepcion` int(11) DEFAULT NULL,
  `cantidad_consumida` decimal(12,4) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `produccion_consumos`
--

INSERT INTO `produccion_consumos` (`id`, `produccion_id`, `mp_id`, `lote_recepcion`, `cantidad_consumida`, `creado_en`) VALUES
(3, 4, 11, 1, 705.0000, '2025-10-15 17:43:44'),
(4, 4, 58, 3, 705.0000, '2025-10-15 17:43:44'),
(5, 6, 14, 2, 34740.7155, '2025-10-15 17:46:15'),
(6, 6, 11, 1, 171706.9847, '2025-10-15 17:46:15'),
(7, 6, 13, 11, 20564.9063, '2025-10-15 17:46:15'),
(8, 6, 21, 18, 71877.3424, '2025-10-15 17:46:15'),
(9, 6, 12, 5, 52710.0511, '2025-10-15 17:46:15'),
(10, 8, 28, 7, 6000.0000, '2025-10-16 14:28:27'),
(11, 8, 28, 6, 3043.7600, '2025-10-16 14:28:27'),
(12, 8, 12, 5, 6330.6320, '2025-10-16 14:28:27'),
(13, 8, 11, 1, 1588.0150, '2025-10-16 14:28:27'),
(14, 8, 11, 4, 1125.1130, '2025-10-16 14:28:27'),
(15, 10, 28, 6, 6782.8200, '2025-10-16 14:29:12'),
(16, 10, 12, 5, 4747.9740, '2025-10-16 14:29:12'),
(17, 10, 11, 4, 2034.8460, '2025-10-16 14:29:12'),
(18, 13, 28, 6, 3843.5980, '2025-10-16 14:30:01'),
(19, 13, 12, 5, 2690.5186, '2025-10-16 14:30:01'),
(20, 13, 11, 4, 1153.0794, '2025-10-16 14:30:01'),
(21, 14, 15, 12, 3500.0000, '2025-10-16 17:43:23'),
(22, 14, 12, 5, 7000.0000, '2025-10-16 17:43:23'),
(23, 15, 19, 16, 10.1835, '2025-10-16 17:43:39'),
(24, 15, 46, 31, 10.1835, '2025-10-16 17:43:39'),
(25, 15, 47, 32, 10.1835, '2025-10-16 17:43:39'),
(26, 15, 48, 33, 15.2752, '2025-10-16 17:43:39'),
(27, 15, 45, 30, 8.1468, '2025-10-16 17:43:39'),
(28, 15, 12, 5, 2036.6905, '2025-10-16 17:43:39'),
(29, 15, 17, 14, 2036.6905, '2025-10-16 17:43:39'),
(30, 15, 26, 21, 12220.1431, '2025-10-16 17:43:39'),
(31, 17, 19, 16, 10.0000, '2025-10-16 17:46:53'),
(32, 17, 11, 4, 14000.0000, '2025-10-16 17:46:53'),
(33, 17, 22, 19, 200.0000, '2025-10-16 17:46:53'),
(34, 17, 8, 41, 24500.0000, '2025-10-16 17:46:53'),
(35, 17, 17, 14, 8500.0000, '2025-10-16 17:46:53'),
(36, 23, 45, 30, 254.4000, '2025-10-17 12:01:24'),
(37, 24, 21, 18, 6500.0000, '2025-10-17 12:03:53'),
(38, 24, 12, 5, 6500.0000, '2025-10-17 12:03:53'),
(39, 24, 16, 13, 2000.0000, '2025-10-17 12:03:53'),
(40, 25, 13, 11, 504.0000, '2025-10-17 12:04:40'),
(41, 25, 12, 5, 3800.0000, '2025-10-17 12:04:40'),
(42, 25, 21, 18, 1200.0000, '2025-10-17 12:04:40'),
(43, 25, 11, 4, 2504.0000, '2025-10-17 12:04:40'),
(44, 25, 20, 17, 48.0000, '2025-10-17 12:04:40'),
(45, 25, 19, 16, 48.0000, '2025-10-17 12:04:40'),
(46, 25, 18, 15, 600.0000, '2025-10-17 12:04:40'),
(47, 25, 17, 14, 35160.0000, '2025-10-17 12:04:40'),
(48, 35, 51, 46, 4348.8756, '2025-10-17 20:15:58'),
(49, 35, 48, 33, 114.4441, '2025-10-17 20:15:58'),
(50, 35, 12, 5, 5035.5402, '2025-10-17 20:15:58'),
(51, 35, 11, 4, 5035.5402, '2025-10-17 20:15:58'),
(52, 37, 24, 51, 4134.0000, '2025-10-20 18:37:52'),
(53, 37, 23, 52, 4134.0000, '2025-10-20 18:37:52'),
(54, 39, 11, 4, 1195.8649, '2025-10-20 18:39:18'),
(55, 39, 12, 5, 874.0541, '2025-10-20 18:39:18'),
(56, 39, 21, 18, 715.1351, '2025-10-20 18:39:18'),
(57, 39, 13, 11, 357.5676, '2025-10-20 18:39:18'),
(58, 39, 14, 2, 385.3784, '2025-10-20 18:39:18'),
(59, 41, 12, 5, 15670.2538, '2025-10-21 14:50:35'),
(60, 41, 13, 11, 4075.8488, '2025-10-21 14:50:35'),
(61, 41, 11, 4, 13612.5437, '2025-10-21 14:50:35'),
(62, 41, 14, 2, 1721.3536, '2025-10-21 14:50:35'),
(63, 43, 36, 56, 4503.5348, '2025-11-10 21:08:03'),
(64, 43, 17, 14, 1000.7855, '2025-11-10 21:08:03'),
(65, 43, 11, 4, 2501.9638, '2025-11-10 21:08:03'),
(66, 43, 12, 5, 2501.9638, '2025-11-10 21:08:03'),
(67, 43, 48, 33, 45.0353, '2025-11-10 21:08:03'),
(68, 43, 19, 16, 3.0024, '2025-11-10 21:08:04'),
(69, 43, 45, 30, 12.0094, '2025-11-10 21:08:04'),
(70, 46, 11, 4, 2000.5546, '2026-01-05 17:50:17'),
(71, 46, 12, 5, 2000.5546, '2026-01-05 17:50:17'),
(72, 49, 11, 4, 600.1664, '2026-01-05 17:51:02'),
(73, 49, 12, 5, 600.1664, '2026-01-05 17:51:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_consumos_sp`
--

CREATE TABLE `produccion_consumos_sp` (
  `id` int(11) NOT NULL,
  `produccion_final_id` int(11) NOT NULL,
  `subproducto_id` int(11) NOT NULL,
  `pt_origen_id` int(11) NOT NULL,
  `cantidad_g` decimal(12,2) NOT NULL,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `produccion_consumos_sp`
--

INSERT INTO `produccion_consumos_sp` (`id`, `produccion_final_id`, `subproducto_id`, `pt_origen_id`, `cantidad_g`, `creado_en`) VALUES
(1, 15, 21, 14, 305.50, '2025-10-16 17:43:39'),
(2, 19, 24, 15, 15248.88, '2025-10-16 17:47:34'),
(3, 19, 25, 17, 5157.12, '2025-10-16 17:47:34'),
(4, 25, 21, 14, 560.00, '2025-10-17 12:04:40'),
(5, 25, 20, 24, 14000.00, '2025-10-17 12:04:40'),
(6, 29, 25, 17, 2998.26, '2025-10-17 12:05:59'),
(7, 29, 23, 25, 5396.87, '2025-10-17 12:05:59'),
(8, 43, 21, 14, 144.11, '2025-11-10 21:08:03'),
(9, 46, 23, 25, 32789.09, '2026-01-05 17:50:17'),
(10, 49, 23, 25, 9836.73, '2026-01-05 17:51:02');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_mo`
--

CREATE TABLE `produccion_mo` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `rol` enum('operador','ayudante') DEFAULT 'operador',
  `min_prod` int(11) DEFAULT 0,
  `min_setup` int(11) DEFAULT 0,
  `min_limpieza` int(11) DEFAULT 0,
  `min_manto` int(11) DEFAULT 0,
  `costo_hora_aplicado` decimal(10,2) DEFAULT NULL,
  `fecha` date NOT NULL DEFAULT curdate(),
  `notas` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `sistema` varchar(100) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `densidad_kg_por_l` decimal(10,4) DEFAULT NULL,
  `es_para_venta` tinyint(1) NOT NULL DEFAULT 0,
  `creado_por` int(11) NOT NULL,
  `es_subproducto` tinyint(1) DEFAULT 0,
  `confidencial` tinyint(1) DEFAULT 0,
  `producto_padre_id` int(11) DEFAULT NULL,
  `unidad_venta` varchar(20) NOT NULL DEFAULT 'g'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `sistema`, `categoria`, `densidad_kg_por_l`, `es_para_venta`, `creado_por`, `es_subproducto`, `confidencial`, `producto_padre_id`, `unidad_venta`) VALUES
(19, 'Reductor Base Color', NULL, NULL, 0.8790, 1, 1, 0, 0, NULL, 'l'),
(20, 'CAB Dispersado', NULL, NULL, 0.9240, 0, 1, 1, 0, NULL, 'g'),
(21, 'ACS 150 Preparado (dispersion)', NULL, NULL, 0.9200, 0, 1, 1, 0, NULL, 'g'),
(22, 'Matizante (Dispersion)', NULL, NULL, 1.2050, 0, 1, 1, 0, NULL, 'g'),
(23, 'Vehículo Base Color Acrilica', NULL, NULL, 0.9450, 0, 1, 1, 0, NULL, 'g'),
(24, 'Vehículo Esmalte Acrilico', NULL, NULL, 0.9800, 0, 1, 1, 0, NULL, 'g'),
(25, 'Pasta Blanca Universal', NULL, NULL, 1.5200, 0, 1, 1, 0, NULL, 'g'),
(26, 'Pasta Negra Normal', NULL, NULL, 0.9500, 0, 1, 1, 0, NULL, 'g'),
(27, 'Fondo de Relleno Gris 2K', NULL, NULL, 1.3900, 1, 1, 0, 0, NULL, 'l'),
(28, 'FO05 Catalizador Fondos 8:1', NULL, NULL, 1.0300, 1, 1, 0, 0, NULL, 'l'),
(29, 'FO01 Catalizador Fondos 4:1', NULL, NULL, 0.9960, 1, 1, 0, 0, NULL, 'l'),
(30, 'B.C. Aluminio Mediano', NULL, NULL, 0.9600, 1, 1, 0, 0, NULL, 'l'),
(31, 'B.C. Blanco', NULL, NULL, 1.1090, 1, 1, 0, 0, NULL, 'l'),
(32, 'B.C. Aluminio Fino', NULL, NULL, 0.9600, 1, 1, 0, 0, NULL, 'l'),
(33, 'B.C. Aluminio Grueso', NULL, NULL, 0.9760, 1, 1, 0, 0, NULL, 'l'),
(34, 'Pasta Negro Intenso', NULL, NULL, 0.9300, 0, 1, 1, 0, NULL, 'g'),
(35, 'B.C. Vehiculo de Ajuste', NULL, NULL, 0.9720, 1, 1, 0, 0, NULL, 'l'),
(36, 'Pasta Azul de Prusia', NULL, NULL, 0.9820, 0, 1, 1, 0, NULL, 'g'),
(37, 'B.C. Negro normal', NULL, NULL, 0.9534, 1, 1, 0, 0, NULL, 'l'),
(38, 'B.C. Negro Intenso', NULL, NULL, 0.9420, 1, 1, 0, 0, NULL, 'l'),
(39, 'PG211 - Pro Gloss (Parte A)', NULL, NULL, 0.9680, 1, 1, 0, 0, NULL, 'l'),
(40, 'HP01 - Catalizador de Transparentes', NULL, NULL, 0.9560, 1, 1, 0, 0, NULL, 'l'),
(41, 'B.C. Azul de Prusia', NULL, NULL, 0.9510, 1, 1, 0, 0, NULL, 'l'),
(42, 'Pasta Azul Monastral', NULL, NULL, 0.9300, 0, 1, 1, 0, NULL, 'g'),
(43, 'Pasta Rojo Claro', NULL, NULL, 0.9650, 0, 1, 1, 0, NULL, 'g'),
(44, 'B.C. Rojo Claro', NULL, NULL, 0.9488, 1, 1, 0, 0, NULL, 'l'),
(45, 'AdheGrip CP50 (Aditivo Adherente)', NULL, NULL, 0.9400, 1, 1, 0, 0, NULL, 'l'),
(46, 'B.C. Azul Monastral', NULL, NULL, 0.9430, 1, 1, 0, 0, NULL, 'l'),
(47, 'Pasta Rojo Intenso', NULL, NULL, 0.9800, 0, 1, 1, 0, NULL, 'g'),
(48, 'B.C. Rojo Intenso', NULL, NULL, 0.9540, 1, 1, 0, 0, NULL, 'l'),
(49, 'Fondo de Relleno Blanco 2K', NULL, NULL, 1.4900, 1, 1, 0, 0, NULL, 'l'),
(50, 'Fondo de Relleno Negro 2K', NULL, NULL, 1.3290, 1, 1, 0, 0, NULL, 'l'),
(51, 'Fondo de Relleno Rojo 2K', NULL, NULL, 1.3390, 1, 1, 0, 0, NULL, 'l'),
(52, 'Reductor Uretanico', NULL, NULL, 0.8770, 1, 1, 0, 0, NULL, 'l'),
(53, 'DF21 - Dry Fast (Parte A)', NULL, NULL, 0.9336, 1, 1, 0, 0, NULL, 'l'),
(54, 'CU01 - Catalizador Universal', NULL, NULL, 0.9560, 1, 1, 0, 0, NULL, 'l'),
(55, 'EA Blanco', NULL, NULL, 1.0740, 1, 1, 0, 0, NULL, 'l'),
(56, 'Solvente Esfumador', NULL, NULL, 0.8820, 1, 1, 0, 0, NULL, 'l'),
(57, 'Acelerador de Secado 2K', NULL, NULL, 1.0600, 1, 1, 0, 0, NULL, 'l'),
(58, 'ProClean Solvente Desengrasante', NULL, NULL, 0.6890, 1, 1, 0, 0, NULL, 'l'),
(59, 'Esmalte Alta Temperatura 1,000°C Negro Mate', NULL, NULL, 1.1400, 1, 1, 0, 0, NULL, 'l'),
(60, 'C5 - Clear5 (Parte A)', NULL, NULL, 0.9320, 1, 1, 0, 0, NULL, 'l'),
(61, 'HDC5 - Catalizador Clear 5', NULL, NULL, 1.0500, 1, 1, 0, 0, NULL, 'l'),
(62, 'CM100 - Catalizador Monocapas', NULL, NULL, 0.9000, 0, 1, 0, 0, NULL, 'g');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_presentaciones`
--

CREATE TABLE `productos_presentaciones` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `presentacion_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `productos_presentaciones`
--

INSERT INTO `productos_presentaciones` (`id`, `producto_id`, `presentacion_id`) VALUES
(298, 27, 20),
(297, 27, 18),
(89, 26, 10),
(88, 25, 10),
(87, 24, 10),
(86, 23, 10),
(79, 22, 10),
(77, 21, 10),
(76, 20, 10),
(381, 19, 24),
(380, 19, 22),
(379, 19, 21),
(352, 52, 24),
(249, 44, 20),
(133, 28, 21),
(132, 28, 17),
(131, 28, 15),
(210, 29, 18),
(209, 29, 16),
(248, 43, 10),
(176, 34, 10),
(145, 30, 20),
(155, 31, 20),
(165, 32, 20),
(175, 33, 20),
(187, 36, 10),
(186, 35, 20),
(236, 39, 20),
(198, 37, 20),
(208, 38, 20),
(235, 39, 18),
(232, 40, 18),
(231, 40, 17),
(247, 42, 10),
(246, 41, 20),
(378, 19, 19),
(351, 52, 22),
(350, 52, 21),
(261, 45, 23),
(273, 47, 10),
(272, 46, 20),
(300, 49, 20),
(284, 48, 20),
(299, 49, 18),
(312, 50, 20),
(311, 50, 18),
(324, 51, 20),
(323, 51, 18),
(349, 52, 19),
(348, 52, 17),
(418, 57, 25),
(412, 56, 22),
(377, 54, 21),
(364, 53, 18),
(376, 54, 18),
(398, 55, 22),
(397, 55, 20),
(411, 56, 21),
(410, 56, 19),
(433, 58, 22),
(432, 58, 21),
(431, 58, 19),
(482, 62, 21),
(448, 59, 18),
(463, 60, 26),
(481, 62, 18),
(478, 61, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_terminados`
--

CREATE TABLE `productos_terminados` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `presentacion_id` int(11) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `lote_produccion` varchar(50) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `productos_terminados`
--

INSERT INTO `productos_terminados` (`id`, `orden_id`, `producto_id`, `presentacion_id`, `cantidad`, `lote_produccion`, `fecha`) VALUES
(3, 1, 45, 23, 50.00, 'L20251015-0001', '2025-10-15'),
(4, 1, 45, 10, 1410.00, 'L20251015-0001', '2025-10-15'),
(5, 6, 19, 22, 20.00, 'L20251015-0006', '2025-10-15'),
(6, 6, 19, 10, 351600.00, 'L20251015-0006', '2025-10-15'),
(7, 3, 54, 18, 20.00, 'L20251016-0003', '2025-10-16'),
(8, 3, 54, 10, 18087.52, 'L20251016-0003', '2025-10-16'),
(9, 4, 54, 18, 15.00, 'L20251016-0004', '2025-10-16'),
(10, 4, 54, 10, 13565.64, 'L20251016-0004', '2025-10-16'),
(11, 5, 40, 17, 1.00, 'L20251016-0005', '2025-10-16'),
(12, 5, 40, 18, 8.00, 'L20251016-0005', '2025-10-16'),
(13, 5, 40, 10, 7687.20, 'L20251016-0005', '2025-10-16'),
(14, 12, 21, 10, 10500.00, 'L20251016-0012', '2025-10-16'),
(15, 8, 24, 10, 16653.00, 'L20251016-0008', '2025-10-16'),
(16, 8, 21, 10, -305.50, 'L20251016-0008', '2025-10-16'),
(17, 13, 25, 10, 47210.00, 'L20251016-0013', '2025-10-16'),
(18, 7, 55, 22, 1.00, 'L20251016-0007', '2025-10-16'),
(19, 7, 55, 10, 20406.00, 'L20251016-0007', '2025-10-16'),
(20, 7, 24, 10, -15248.88, 'L20251016-0007', '2025-10-16'),
(21, 7, 25, 10, -5157.12, 'L20251016-0007', '2025-10-16'),
(22, 14, 57, 25, 12.00, 'L20251017-0014', '2025-10-17'),
(23, 14, 57, 10, 254.40, 'L20251017-0014', '2025-10-17'),
(24, 16, 20, 10, 15000.00, 'L20251017-0016', '2025-10-17'),
(25, 15, 23, 10, 58424.00, 'L20251017-0015', '2025-10-17'),
(26, 15, 21, 10, -560.00, 'L20251017-0015', '2025-10-17'),
(27, 15, 20, 10, -14000.00, 'L20251017-0015', '2025-10-17'),
(28, 11, 31, 20, 2.00, 'L20251017-0011', '2025-10-17'),
(29, 11, 31, 10, 8395.13, 'L20251017-0011', '2025-10-17'),
(30, 11, 25, 10, -2998.26, 'L20251017-0011', '2025-10-17'),
(31, 11, 23, 10, -5396.87, 'L20251017-0011', '2025-10-17'),
(34, 19, 30, 20, 4.00, 'L20251018-0019', '2025-10-18'),
(35, 19, 30, 10, 14534.40, 'L20251018-0019', '2025-10-18'),
(36, 20, 58, 21, 3.00, 'L20251021-0020', '2025-10-21'),
(37, 20, 58, 10, 8268.00, 'L20251021-0020', '2025-10-21'),
(38, 21, 56, 21, 1.00, 'L20251021-0021', '2025-10-21'),
(39, 21, 56, 10, 3528.00, 'L20251021-0021', '2025-10-21'),
(40, 22, 52, 24, 2.00, 'L20251021-0022', '2025-10-21'),
(41, 22, 52, 10, 35080.00, 'L20251021-0022', '2025-10-21'),
(42, 24, 60, 26, 14.00, 'L20251111-0024', '2025-11-11'),
(43, 24, 60, 10, 10712.41, 'L20251111-0024', '2025-11-11'),
(44, 24, 21, 10, -144.11, 'L20251111-0024', '2025-11-11'),
(45, 28, 35, 20, 10.00, 'L20260105-0028', '2026-01-05'),
(46, 28, 35, 10, 36790.20, 'L20260105-0028', '2026-01-05'),
(47, 28, 23, 10, -32789.09, 'L20260105-0028', '2026-01-05'),
(48, 29, 35, 20, 3.00, 'L20260105-0029', '2026-01-05'),
(49, 29, 35, 10, 11037.06, 'L20260105-0029', '2026-01-05'),
(50, 29, 23, 10, -9836.73, 'L20260105-0029', '2026-01-05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=activo,0=inactivo o sin datos',
  `entrega_domicilio` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `contacto`, `email`, `telefono`, `direccion`, `activo`, `entrega_domicilio`, `created_at`, `updated_at`) VALUES
(1, 'INTERTRADE', 'FÃ¡tima MuÃ±iz', 'fatima@intertrade.com.mx', '4773227318', '', 1, 0, '2025-06-07 14:37:35', '2025-06-23 18:35:48'),
(2, 'MYQUISA', 'Servicio a Clientes', 'myquisa@hotmail.com', '3314498366', 'Calle Gorrion 864, Morelos, Romita, 44910 Guadalajara, Jal.', 1, 1, '2025-06-07 21:12:57', '2025-09-09 11:42:32'),
(3, 'QUIMICA RANA', 'CÃ©sar Martinez', 'cmartinez@quimicarana.com', '5522700619', 'Trigo 382, La Nogalera, 44470 Guadalajara, Jal.', 1, 1, '2025-06-08 14:05:28', '2025-06-23 15:01:45'),
(4, 'DIQUISA', 'Carlos de Leon', 'ventasdiquisa@hotmail.com', '3318485731', 'Calle 5 # 1376, Colon Industrial, 44940 Guadalajara, Jal.', 1, 1, '2025-06-08 14:08:24', '2025-09-09 11:41:44'),
(5, 'NASEDA', 'Ana Isabel Sandoval', 'tienda@naseda.com.mx', '3314796210', 'Calle 4 # 1964, Colon Industrial, 44940 Guadalajara, Jal.', 1, 1, '2025-06-08 14:10:00', '2025-09-09 11:41:28'),
(6, 'CARTOJAL', 'Fernando Camacho', 'cartojal@hotmail.com', '3335060074', 'Artemio Alpizar # 1534 Col. Echeverria, Guadalajara, Jalisco C.P. 44970', 1, 1, '2025-06-09 07:57:32', '2025-06-23 14:03:29'),
(7, 'DIKEMH', 'Barbara', '', '3331852369', NULL, 1, 0, '2025-06-09 14:53:38', '2025-06-09 14:53:38'),
(8, 'REACCIONES QUIMICAS', 'Noemi Ponce', 'nponceb@reacciones.com', '3337196794', 'Calle 1 2760, Alamo Industrial, 44440 Guadalajara, Jal.', 1, 0, '2025-06-09 18:31:36', '2025-06-23 15:02:27'),
(9, 'ECONOENVASES', 'Sandra Renteria', 'sandra.renteria@laeconomicagdl.com', '3320101617', 'Av. Perif. Pte. Manuel Gomez Morin 2880-B, Paraisos del Colli, 45069 Zapopan, Jal.', 1, 0, '2025-06-21 22:19:17', '2025-09-09 11:42:05'),
(10, 'ESFERA DIGITAL', 'Digital u Offset', '', '3336133020', 'Calle Enrique Gonzalez Martinez 326, Zona Centro, 44100 Guadalajara, Jal.', 1, 1, '2025-06-21 22:19:55', '2025-09-09 11:42:20'),
(11, 'CORPORACION MEXICANA DE POLIMEROS', 'Francisco Lopez', 'flopez@cmp.mx', '5519636737', 'Paqueteria: Autoexpress Villareal', 1, 0, '2025-06-23 18:18:33', '2025-09-09 11:43:04'),
(12, 'NUODEX', 'Sergio Gradilla', 'noudex@gmail.com', '3317611789', 'C. 26 # 2570 Col. Colon Industrial C.P. 44940, Guadalajara, Jalisco', 1, 0, '2025-09-09 11:41:08', '2025-09-09 11:41:08'),
(13, 'SINTETIC MEXICANA', 'Ing Alice', '', '5566776730', '', 1, 0, '2025-09-09 13:09:43', '2025-09-09 13:09:43'),
(16, 'Carga Inicial', NULL, NULL, NULL, NULL, 1, 0, '2025-09-11 09:37:38', '2025-09-11 09:37:38'),
(17, 'BISMARCK', 'Adan Aguirre', '', '3316068468', 'Paraiso # 1644 esq. Tabachin Col. Fresno Guadalajara, Jalisco', 1, 1, '2025-09-17 10:18:25', '2025-09-17 10:18:25'),
(19, 'PROVEEDOR BACKFILL EXISTENCIAS', NULL, NULL, NULL, NULL, 1, 0, '2025-10-15 17:34:18', '2025-10-15 17:34:18'),
(20, 'AJUSTE INVENTARIO', NULL, NULL, NULL, NULL, 1, 0, '2025-11-10 13:37:02', '2025-11-10 13:37:02'),
(21, 'AJUSTE INV MP', NULL, NULL, NULL, NULL, 1, 0, '2025-11-10 13:59:50', '2025-11-10 13:59:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_ic`
--

CREATE TABLE `proveedores_ic` (
  `insumo_id` int(11) NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_mp`
--

CREATE TABLE `proveedores_mp` (
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `mp_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores_mp`
--

INSERT INTO `proveedores_mp` (`proveedor_id`, `mp_id`) VALUES
(1, 8),
(1, 56),
(2, 9),
(2, 10),
(2, 43),
(2, 44),
(3, 15),
(3, 16),
(3, 22),
(3, 29),
(3, 34),
(3, 37),
(3, 41),
(3, 42),
(3, 48),
(3, 51),
(3, 52),
(3, 53),
(3, 54),
(3, 55),
(3, 57),
(3, 58),
(3, 59),
(3, 60),
(4, 36),
(4, 38),
(4, 39),
(4, 40),
(5, 20),
(5, 30),
(5, 35),
(5, 49),
(7, 11),
(7, 12),
(7, 13),
(7, 14),
(7, 18),
(7, 21),
(7, 23),
(7, 24),
(7, 25),
(8, 17),
(8, 19),
(8, 26),
(8, 27),
(11, 28),
(11, 46),
(11, 47),
(12, 31),
(12, 32),
(12, 33),
(13, 45),
(17, 11),
(17, 12),
(17, 13),
(17, 14),
(17, 21),
(17, 23),
(17, 24),
(17, 50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recepciones_compra_lineas`
--

CREATE TABLE `recepciones_compra_lineas` (
  `id` int(11) NOT NULL,
  `orden_compra_id` int(11) NOT NULL,
  `linea_id` int(10) UNSIGNED NOT NULL,
  `cantidad_recibida` decimal(12,3) NOT NULL DEFAULT 0.000,
  `cantidad_disponible` decimal(12,3) NOT NULL DEFAULT 0.000,
  `precio_unitario_neto` decimal(14,6) DEFAULT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `tipo_cambio` decimal(14,6) DEFAULT NULL,
  `costo_unitario_mxn` decimal(14,6) DEFAULT NULL,
  `gasto_prorrateado_unitario_mxn` decimal(14,6) NOT NULL DEFAULT 0.000000,
  `lote` varchar(50) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `factura_numero` varchar(50) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `recepcionador_id` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `recepciones_compra_lineas`
--

INSERT INTO `recepciones_compra_lineas` (`id`, `orden_compra_id`, `linea_id`, `cantidad_recibida`, `cantidad_disponible`, `precio_unitario_neto`, `moneda`, `tipo_cambio`, `costo_unitario_mxn`, `gasto_prorrateado_unitario_mxn`, `lote`, `fecha_ingreso`, `factura_numero`, `comentario`, `recepcionador_id`, `creado_en`) VALUES
(1, 2, 3, 174000.000, 0.000, 0.024713, 'MXN', 1.000000, 0.024713, 0.000000, 'FACTB64449', '2025-10-04', 'B64449', 'Se recibieron tambos verdes', 1, '2025-10-05 08:58:06'),
(2, 2, 4, 173400.000, 136552.552, 0.025375, 'MXN', 1.000000, 0.025375, 0.000000, 'FACTB64449', '2025-10-04', 'B64449', 'Se recibieron tambos verdes', 1, '2025-10-05 08:58:06'),
(3, 3, 5, 5000.000, 4295.000, 1.291600, 'MXN', 1.000000, 1.291600, 0.000000, '6240051000', '2025-10-02', 'QRG 3053301', NULL, 1, '2025-10-05 14:08:41'),
(4, 5, 8, 174000.000, 128236.328, 0.024712, 'MXN', 1.000000, 0.024712, 0.000000, 'B 64472', '2025-10-07', 'B 64472', NULL, 1, '2025-10-08 18:27:55'),
(5, 5, 9, 176400.000, 63901.600, 0.031400, 'MXN', 1.000000, 0.031400, 0.000000, 'B 64472', '2025-10-07', 'B 64472', NULL, 1, '2025-10-08 18:27:55'),
(6, 4, 6, 20000.000, 6329.822, 0.125050, 'MXN', 1.000000, 0.125050, 0.000000, '240700671', '2025-10-08', '00151767', NULL, 1, '2025-10-09 18:05:43'),
(7, 4, 7, 6000.000, 0.000, 0.125050, 'MXN', 1.000000, 0.125050, 0.000000, 'MATEXIST', '2025-10-02', 'anterior', 'Material Existente', 1, '2025-10-09 18:05:43'),
(8, 9, 16, 18000.000, 18000.000, 0.087319, 'MXN', 1.000000, 0.087319, 0.000000, '2516299', '2025-10-10', '10062470', NULL, 1, '2025-10-10 13:46:49'),
(9, 99998, 20, 5200.000, 5200.000, 0.080000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Dioxido de Titanio R706', 1, '2025-10-15 17:34:18'),
(10, 99998, 21, 34000.000, 34000.000, 0.020000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Talco TVX', 1, '2025-10-15 17:34:18'),
(11, 99998, 22, 64536.000, 39033.677, 0.040000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Butil Cellosolve', 1, '2025-10-15 17:34:18'),
(12, 99998, 23, 6200.000, 2700.000, 0.430000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Troythix 150 ACS ', 1, '2025-10-15 17:34:18'),
(13, 99998, 24, 10000.000, 8000.000, 0.310000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP CAB 381-20.0 ', 1, '2025-10-15 17:34:18'),
(14, 99998, 25, 140000.000, 93302.523, 0.090000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP AC 7534 50 ', 1, '2025-10-15 17:34:18'),
(15, 99998, 26, 78000.000, 77400.000, 0.040000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP D.O.P.', 1, '2025-10-15 17:34:18'),
(16, 99998, 27, 1000.000, 928.815, 0.260000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Troysol AFL', 1, '2025-10-15 17:34:18'),
(17, 99998, 28, 4000.000, 3952.000, 0.460000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP BIK 300', 1, '2025-10-15 17:34:18'),
(18, 99998, 29, 194563.000, 114270.523, 0.030000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Acetato de Etilo', 1, '2025-10-15 17:34:18'),
(19, 99998, 30, 7500.000, 7300.000, 0.310000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Troysperse 98-C', 1, '2025-10-15 17:34:18'),
(20, 99998, 31, 12672.000, 12672.000, 0.010000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Metanol', 1, '2025-10-15 17:34:18'),
(21, 99998, 32, 45000.000, 32779.857, 0.060000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP AL 5301 50X', 1, '2025-10-15 17:34:18'),
(22, 99998, 33, 20000.000, 20000.000, 0.060000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP AL 110 50XFB', 1, '2025-10-15 17:34:18'),
(23, 99998, 34, 8400.000, 8400.000, 0.070000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Lecitina de Soya', 1, '2025-10-15 17:34:18'),
(24, 99998, 35, 5000.000, 5000.000, 0.880000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP BIK 331', 1, '2025-10-15 17:34:18'),
(25, 99998, 36, 13000.000, 13000.000, 0.060000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Kpol 8211', 1, '2025-10-15 17:34:18'),
(26, 99998, 37, 4000.000, 4000.000, 0.100000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Desmordur L75', 1, '2025-10-15 17:34:18'),
(27, 99998, 38, 13500.000, 13500.000, 0.150000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Sylysia SY 440', 1, '2025-10-15 17:34:18'),
(28, 99998, 39, 15000.000, 15000.000, 0.050000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Zeospheres G 400', 1, '2025-10-15 17:34:18'),
(29, 99998, 40, 30000.000, 30000.000, 0.010000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Silice 306', 1, '2025-10-15 17:34:18'),
(30, 99998, 41, 11000.000, 10725.444, 0.510000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP DBTDL', 1, '2025-10-15 17:34:18'),
(31, 99998, 42, 3000.000, 2989.817, 0.400000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP CMP-AP-012 (5530) Absorbedor UV', 1, '2025-10-15 17:34:18'),
(32, 99998, 43, 2000.000, 1989.817, 0.330000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP CMP-AP-004 (353) Estabilizador de luz HALS', 1, '2025-10-15 17:34:18'),
(33, 99998, 44, 6000.000, 5825.246, 0.480000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Troysol S367', 1, '2025-10-15 17:34:18'),
(34, 99998, 45, 25400.000, 25400.000, 0.770000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP Rehobyk 410', 1, '2025-10-15 17:34:18'),
(35, 99998, 46, 15440.000, 15440.000, 0.060000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP PM Acetato', 1, '2025-10-15 17:34:18'),
(36, 99998, 47, 1700.000, 1700.000, 0.660000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP ALU M4015', 1, '2025-10-15 17:34:18'),
(37, 99998, 48, 2000.000, 2000.000, 0.470000, 'MXN', 1.000000, NULL, 0.000000, 'EXIST-20251015', '2025-10-15', 'ANTERIOR', 'Backfill: stock existente sin RCL para MP ALU M2040', 1, '2025-10-15 17:34:18'),
(40, 100000, 51, 18000.000, 18000.000, 0.087970, 'MXN', 1.000000, 0.087970, 0.000000, '2517698', '2025-10-15', '10062661', NULL, 1, '2025-10-16 17:41:33'),
(41, 7, 13, 25000.000, 500.000, 0.073137, 'MXN', 1.000000, 0.073137, 0.000000, '1626530065', '2025-10-10', 'FI24121', NULL, 1, '2025-10-16 17:44:54'),
(42, 6, 10, 20.000, 20.000, 184.580000, 'MXN', 1.000000, 184.580000, 0.000000, 'NO EXPLICA', '2025-10-06', 'GDL81269', NULL, 1, '2025-10-17 09:12:20'),
(43, 6, 11, 100.000, 100.000, 34.560000, 'MXN', 1.000000, 34.560000, 0.000000, 'NO EXPLICA', '2025-10-06', 'GDL81269', NULL, 1, '2025-10-17 09:12:20'),
(44, 6, 12, 100.000, 100.000, 0.000000, 'MXN', 1.000000, 0.000000, 0.000000, 'NO EXPLICA', '2025-10-06', 'GDL81269', NULL, 1, '2025-10-17 09:12:20'),
(45, 1, 1, 20000.000, 20000.000, 0.303316, 'MXN', 1.000000, 0.303316, 0.000000, 'C-16160B', '2025-10-10', 'QRG 3053364', NULL, 1, '2025-10-17 12:13:45'),
(46, 1, 2, 5000.000, 651.124, 0.523826, 'MXN', 1.000000, 0.523826, 0.000000, '30ALU25-503', '2025-10-09', 'QRM 1202098', NULL, 1, '2025-10-17 12:13:45'),
(47, 100001, 52, 18000.000, 18000.000, 0.087400, 'MXN', 1.000000, 0.087400, 0.000000, '2412531', '2025-10-17', '10062699', NULL, 1, '2025-10-17 19:55:50'),
(48, 100002, 53, 104.000, 104.000, 16.200000, 'MXN', 1.000000, 16.200000, 0.000000, 'NO EXPLICA', '2025-10-17', 'GDL 81677', NULL, 1, '2025-10-17 21:29:54'),
(49, 100002, 54, 104.000, 104.000, 2.000000, 'MXN', 1.000000, 2.000000, 0.000000, 'NO EXPLICA', '2025-10-17', 'GDL 81677', NULL, 1, '2025-10-17 21:29:54'),
(50, 100002, 55, 9.000, 9.000, 156.600000, 'MXN', 1.000000, 156.600000, 0.000000, 'NO EXPLICA', '2025-10-17', 'GDL 81677', 'llegaron tapas equivocadas, se devolvieron y se entregaran las correctas.', 1, '2025-10-17 21:29:54'),
(51, 8, 14, 13180.000, 9046.000, 0.029620, 'MXN', 1.000000, 0.029620, 0.000000, 'NO EXPLICA', '2025-10-18', 'B 64710', NULL, 1, '2025-10-20 18:31:03'),
(52, 8, 15, 14600.000, 10466.000, 0.026287, 'MXN', 1.000000, 0.026287, 0.000000, 'NO EXPLICA', '2025-10-18', 'B 64710', NULL, 1, '2025-10-20 18:31:03'),
(53, 100004, 57, 25000.000, 25000.000, 0.073274, 'MXN', 1.000000, 0.073274, 0.000000, '8733590193', '2025-10-20', 'FI 24177', NULL, 1, '2025-10-20 18:44:03'),
(54, 900003, 60, 20000.000, 20000.000, 0.125669, 'MXN', 18.480800, 2.322464, 0.000000, '2407006171', '2025-10-21', '00152090', NULL, 1, '2025-10-21 14:21:23'),
(55, 900002, 58, 18500.000, 18500.000, 0.088330, 'MXN', 1.000000, 0.088330, 0.000000, '241251', NULL, '10062727', NULL, 5, '2025-10-21 14:26:20'),
(56, 900002, 59, 18000.000, 10496.465, 0.087410, 'MXN', 1.000000, 0.087410, 0.000000, '240700671', NULL, NULL, NULL, 5, '2025-10-21 14:26:20'),
(57, 900006, 63, 348000.000, 348000.000, 0.025552, 'MXN', 1.000000, 0.025552, 0.000000, 'NO EXPLICA', '2025-10-28', 'B-64870', NULL, 1, '2025-11-07 18:00:44'),
(58, 900006, 64, 180400.000, 180400.000, 0.029090, 'MXN', 1.000000, 0.029090, 0.000000, 'NO EXPLICA', '2025-10-28', 'B-64870', NULL, 1, '2025-11-07 18:00:44'),
(59, 900006, 65, 176400.000, 176400.000, 0.030500, 'MXN', 1.000000, 0.030500, 0.000000, 'NO EXPLICA', '2025-10-28', 'B-64870', NULL, 1, '2025-11-07 18:00:44'),
(60, 900006, 66, 45000.000, 45000.000, 0.028422, 'MXN', 1.000000, 0.028422, 0.000000, 'NO EXPLICA', '2025-10-28', 'B-64870', NULL, 1, '2025-11-07 18:00:44'),
(61, 900007, 67, 5974.010, 5974.010, 0.000000, 'MXN', 1.000000, 0.000000, 0.000000, 'AJUSTE-20251110-193702', '2025-11-10', 'AJUSTE INV', 'Ajuste inventario manual desde inventario_mp', 1, '2025-11-10 13:37:02'),
(62, 900008, 68, 3000.000, 3000.000, 0.000000, 'MXN', 1.000000, 0.000000, 0.000000, 'AJUSTE-20251110-193832', '2025-11-10', 'AJUSTE INV', 'Ajuste inventario manual desde inventario_mp', 1, '2025-11-10 13:38:32'),
(63, 900009, 69, 3000.000, 3000.000, NULL, NULL, NULL, NULL, 0.000000, NULL, '2025-11-10', NULL, 'AJUSTE INV MP', 1, '2025-11-10 13:59:50'),
(64, 900009, 70, 3000.000, 3000.000, NULL, NULL, NULL, NULL, 0.000000, NULL, '2025-11-10', NULL, 'AJUSTE INV MP', 1, '2025-11-10 14:12:54'),
(65, 900009, 71, 3000.000, 3000.000, NULL, NULL, NULL, NULL, 0.000000, NULL, '2025-11-10', NULL, 'AJUSTE INV MP', 1, '2025-11-10 14:14:35');

--
-- Disparadores `recepciones_compra_lineas`
--
DELIMITER $$
CREATE TRIGGER `rcl_bi_defaults` BEFORE INSERT ON `recepciones_compra_lineas` FOR EACH ROW BEGIN
  IF NEW.cantidad_disponible IS NULL THEN
    SET NEW.cantidad_disponible = NEW.cantidad_recibida;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `rcl_bu_sync_disponible` BEFORE UPDATE ON `recepciones_compra_lineas` FOR EACH ROW BEGIN
  IF NEW.cantidad_recibida <> OLD.cantidad_recibida THEN
    SET NEW.cantidad_disponible =
      GREATEST(0, (IFNULL(OLD.cantidad_disponible,0) + (NEW.cantidad_recibida - OLD.cantidad_recibida)));
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requerimientos_produccion`
--

CREATE TABLE `requerimientos_produccion` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `orden_venta_id` bigint(20) UNSIGNED NOT NULL,
  `linea_venta_id` bigint(20) UNSIGNED NOT NULL,
  `producto_id` bigint(20) UNSIGNED NOT NULL,
  `presentacion_id` bigint(20) UNSIGNED NOT NULL,
  `cantidad_requerida` decimal(14,3) NOT NULL,
  `estado` enum('pendiente','en_proceso','cubierto','cancelado') DEFAULT 'pendiente',
  `fecha_objetivo` date DEFAULT NULL,
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `requerimientos_produccion`
--

INSERT INTO `requerimientos_produccion` (`id`, `orden_venta_id`, `linea_venta_id`, `producto_id`, `presentacion_id`, `cantidad_requerida`, `estado`, `fecha_objetivo`, `creado_por`, `creado_en`) VALUES
(1, 1, 1, 54, 18, 20.000, 'pendiente', '2025-10-08', 1, '2025-10-07 05:22:02'),
(2, 1, 2, 19, 22, 20.000, 'pendiente', '2025-10-08', 1, '2025-10-07 05:22:02'),
(3, 2, 3, 54, 18, 20.000, 'pendiente', '2025-10-08', 1, '2025-10-07 05:22:02'),
(4, 2, 4, 19, 22, 20.000, 'pendiente', '2025-10-08', 1, '2025-10-07 05:22:02'),
(5, 3, 5, 52, 24, 2.000, 'pendiente', '2025-10-09', 1, '2025-10-08 17:21:49'),
(6, 4, 6, 54, 18, 15.000, 'pendiente', '2025-10-08', 1, '2025-10-08 17:23:15'),
(7, 5, 7, 52, 24, 1.000, 'pendiente', '2025-10-11', 1, '2025-10-09 00:20:18'),
(8, 5, 8, 19, 24, 1.000, 'pendiente', '2025-10-11', 1, '2025-10-09 00:20:18'),
(9, 5, 9, 31, 20, 2.000, 'pendiente', '2025-10-11', 1, '2025-10-09 00:20:18'),
(10, 6, 10, 55, 22, 1.000, 'pendiente', '2025-10-14', 1, '2025-10-14 15:40:12'),
(11, 6, 11, 56, 21, 1.000, 'pendiente', '2025-10-14', 1, '2025-10-14 15:40:12'),
(12, 7, 12, 30, 20, 2.000, 'pendiente', '2025-10-15', 1, '2025-10-16 20:26:50'),
(13, 8, 13, 39, 20, 12.000, 'pendiente', '2025-10-20', 5, '2025-10-16 22:38:05'),
(14, 8, 14, 40, 18, 16.000, 'pendiente', '2025-10-20', 5, '2025-10-16 22:38:05'),
(15, 8, 15, 27, 20, 15.000, 'pendiente', '2025-10-20', 5, '2025-10-16 22:38:05'),
(16, 8, 16, 29, 18, 15.000, 'pendiente', '2025-10-20', 5, '2025-10-16 22:38:05'),
(17, 9, 17, 31, 20, 6.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(18, 9, 18, 40, 18, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(19, 9, 19, 40, 17, 3.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(20, 9, 20, 27, 18, 6.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(21, 9, 21, 28, 15, 6.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(22, 9, 22, 27, 20, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(23, 9, 23, 28, 17, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(24, 9, 24, 19, 24, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(25, 9, 25, 52, 24, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-16 23:26:53'),
(26, 10, 26, 37, 20, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(27, 10, 27, 31, 20, 2.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(28, 10, 28, 30, 20, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(29, 10, 29, 32, 20, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(30, 10, 30, 33, 20, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(31, 10, 31, 48, 20, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(32, 10, 32, 44, 20, 1.000, 'pendiente', '2025-10-17', 5, '2025-10-17 17:51:29'),
(33, 11, 33, 31, 20, 3.000, 'pendiente', '2025-10-20', 5, '2025-10-20 16:48:39'),
(34, 12, 35, 33, 20, 1.000, 'pendiente', '2025-10-20', 5, '2025-10-20 16:49:17'),
(35, 12, 36, 31, 20, 2.000, 'pendiente', '2025-10-20', 5, '2025-10-20 16:49:17'),
(36, 13, 39, 55, 22, 1.000, 'pendiente', '2025-10-21', 5, '2025-10-20 23:17:22'),
(37, 13, 40, 55, 20, 1.000, 'pendiente', '2025-10-21', 5, '2025-10-20 23:17:22'),
(38, 14, 46, 52, 21, 5.000, 'pendiente', '2025-11-10', 5, '2025-11-07 00:03:12'),
(39, 15, 47, 27, 18, 20.000, 'pendiente', '2025-11-07', 5, '2025-11-07 23:49:55'),
(40, 15, 48, 28, 15, 20.000, 'pendiente', '2025-11-07', 5, '2025-11-07 23:49:55'),
(41, 16, 49, 35, 20, 10.000, 'pendiente', '2026-01-05', 1, '2026-01-05 17:57:45'),
(42, 17, 50, 35, 20, 10.000, 'pendiente', '2026-01-05', 5, '2026-01-05 23:39:25'),
(43, 18, 51, 39, 20, 12.000, 'pendiente', '2026-01-05', 5, '2026-01-05 23:40:20'),
(44, 18, 52, 40, 18, 24.000, 'pendiente', '2026-01-05', 5, '2026-01-05 23:40:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas_venta`
--

CREATE TABLE `reservas_venta` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `orden_venta_id` bigint(20) UNSIGNED NOT NULL,
  `linea_venta_id` bigint(20) UNSIGNED NOT NULL,
  `producto_id` bigint(20) UNSIGNED NOT NULL,
  `presentacion_id` bigint(20) UNSIGNED NOT NULL,
  `lote_codigo` varchar(64) DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `estado` enum('activa','liberada','consumida') DEFAULT 'activa',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `reservas_venta`
--

INSERT INTO `reservas_venta` (`id`, `orden_venta_id`, `linea_venta_id`, `producto_id`, `presentacion_id`, `lote_codigo`, `cantidad`, `estado`, `creado_por`, `creado_en`) VALUES
(1, 2, 4, 19, 22, 'L20251015-0006', 20.000, 'liberada', 1, '2025-10-16 00:13:00'),
(2, 2, 3, 54, 18, 'L20251016-0003', 20.000, 'liberada', 1, '2025-10-16 20:31:26'),
(3, 2, 4, 19, 22, 'L20251015-0006', 20.000, 'liberada', 1, '2025-10-16 20:31:47'),
(4, 2, 3, 54, 18, 'L20251016-0003', 20.000, 'consumida', 1, '2025-10-16 20:32:38'),
(5, 2, 4, 19, 22, 'L20251015-0006', 20.000, 'consumida', 1, '2025-10-16 20:32:38'),
(6, 6, 10, 55, 22, 'L20251016-0007', 1.000, 'liberada', 1, '2025-10-16 23:47:46'),
(7, 4, 6, 54, 18, 'L20251016-0004', 15.000, 'consumida', 1, '2025-10-16 23:48:22'),
(8, 7, 12, 30, 20, 'L20251018-0019', 2.000, 'consumida', 1, '2025-10-18 02:16:21'),
(9, 6, 10, 55, 22, 'L20251016-0007', 1.000, 'liberada', 1, '2025-10-18 02:56:55'),
(10, 6, 10, 55, 22, 'L20251016-0007', 1.000, 'consumida', 1, '2025-10-21 00:39:41'),
(11, 6, 11, 56, 21, 'L20251021-0021', 1.000, 'consumida', 1, '2025-10-21 00:39:41'),
(12, 3, 5, 52, 24, 'L20251021-0022', 2.000, 'consumida', 1, '2025-10-21 20:50:44'),
(13, 16, 49, 35, 20, 'L20260105-0028', 10.000, 'consumida', 1, '2026-01-07 00:24:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas_venta_backup`
--

CREATE TABLE `reservas_venta_backup` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `orden_venta_id` bigint(20) UNSIGNED NOT NULL,
  `linea_venta_id` bigint(20) UNSIGNED NOT NULL,
  `producto_id` bigint(20) UNSIGNED NOT NULL,
  `presentacion_id` bigint(20) UNSIGNED NOT NULL,
  `lote_codigo` varchar(64) DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL,
  `estado` enum('activa','liberada','consumida') DEFAULT 'activa',
  `creado_por` bigint(20) UNSIGNED DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salidas_ov`
--

CREATE TABLE `salidas_ov` (
  `id` int(11) NOT NULL,
  `ov_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `presentacion_id` int(11) DEFAULT NULL,
  `cantidad_salida` decimal(10,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `surtidos_venta`
--

CREATE TABLE `surtidos_venta` (
  `id` int(11) NOT NULL,
  `orden_venta_id` int(11) NOT NULL,
  `linea_venta_id` int(11) NOT NULL,
  `lote_produccion` varchar(50) NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `fecha_surtido` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `surtidos_venta`
--

INSERT INTO `surtidos_venta` (`id`, `orden_venta_id`, `linea_venta_id`, `lote_produccion`, `cantidad`, `fecha_surtido`) VALUES
(1, 2, 3, 'L20251016-0003', 20.00, '2025-10-16 14:32:49'),
(2, 2, 4, 'L20251015-0006', 20.00, '2025-10-16 14:32:49'),
(3, 4, 6, 'L20251016-0004', 15.00, '2025-10-17 09:03:21'),
(4, 7, 12, 'L20251018-0019', 2.00, '2025-10-17 22:07:56'),
(5, 6, 10, 'L20251016-0007', 1.00, '2025-10-20 18:39:49'),
(6, 6, 11, 'L20251021-0021', 1.00, '2025-10-20 18:39:49'),
(7, 3, 5, 'L20251021-0022', 2.00, '2025-10-21 14:50:53'),
(8, 16, 49, 'L20260105-0028', 10.00, '2026-01-06 18:25:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `uso_cajas`
--

CREATE TABLE `uso_cajas` (
  `id` int(11) NOT NULL,
  `ov_id` int(11) DEFAULT NULL,
  `cantidad_cajas` int(11) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `total_cajas` decimal(10,2) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `rol` enum('admin','gerente','operaciones','produccion','logistica') DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ver_precios` tinyint(1) DEFAULT 0,
  `ver_formulas` tinyint(1) DEFAULT 0,
  `ver_reportes` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `nomina_diaria_mxn` decimal(10,2) DEFAULT NULL,
  `jornada_horas` decimal(5,2) DEFAULT 8.00,
  `tipo_usuario` enum('operativo','administrativo') DEFAULT 'operativo',
  `incluye_en_indirectos` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `rol`, `correo`, `contrasena`, `creado_en`, `ver_precios`, `ver_formulas`, `ver_reportes`, `activo`, `deleted_at`, `nomina_diaria_mxn`, `jornada_horas`, `tipo_usuario`, `incluye_en_indirectos`) VALUES
(1, 'Alex Fernandez', 'admin', 'alejandro@gpoferro.com', '$2b$12$tkpQVHqzpp0SWOapbKBbFOUIj2uuoeyVt7oUxVDJTiIjcx53dz3QK', '2025-06-03 19:07:50', 1, 1, 1, 1, NULL, 400.00, 8.00, 'administrativo', 1),
(3, 'Logistica', 'logistica', 'logistica@a4paints.com', '$2y$10$hugwXjao02mwb6MAMGJ8O.z2dgQsiWM3UyzA6nSuZmho39HwDBIcC', '2025-06-09 19:17:29', 0, 0, 0, 1, NULL, 200.00, 8.00, 'operativo', 1),
(4, 'Jose Garcia', 'produccion', 'produccion@a4paints.com', '$2y$10$apfD.im.9tUzcpGvAmiMnOllbYBmBVCBy18qG1igkZHHIqjrfrIMO', '2025-08-22 06:36:02', 1, 1, 0, 1, NULL, 700.00, 8.00, 'operativo', 1),
(5, 'Andrea Romero', 'gerente', 'andrea@gpoferro.com', '$2y$10$T9kiVrQ/OGjhUETi0IBIS.nkPEzCahx5Fs/XLplHA9.anHYyKJ.ua', '2025-08-27 01:14:12', 1, 1, 1, 1, NULL, 200.00, 8.00, 'administrativo', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha_entrega` date NOT NULL,
  `modalidad` enum('unidad','paquete') NOT NULL DEFAULT 'unidad',
  `tipo_envio` enum('local','foraneo') NOT NULL DEFAULT 'local',
  `usuario_id` int(11) NOT NULL,
  `creado_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_costeo_empaque`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_costeo_empaque` (
`orden_id` int(11)
,`costo_empaque` decimal(44,8)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_costeo_lote`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_costeo_lote` (
`orden_id` int(11)
,`lote` varchar(50)
,`fecha` date
,`producto` varchar(100)
,`costo_mp` decimal(64,16)
,`costo_empaque` decimal(44,8)
,`mano_obra` decimal(54,10)
,`indirectos` decimal(34,2)
,`piezas` decimal(32,2)
,`costo_total` decimal(65,16)
,`costo_unitario` decimal(65,20)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_costeo_mp`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_costeo_mp` (
`orden_id` int(11)
,`costo_mp_lote` decimal(63,16)
,`costo_mp_fallback` decimal(48,10)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_indirectos_lote`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_indirectos_lote` (
`orden_id` int(11)
,`indirectos` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_mano_obra_directa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_mano_obra_directa` (
`orden_id` int(11)
,`mo_directa` decimal(54,10)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_piezas_lote`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_piezas_lote` (
`orden_id` int(11)
,`piezas` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_traza_lote`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_traza_lote` (
`lote_produccion` varchar(50)
,`pt_id` int(11)
,`orden_id` int(11)
,`fecha` date
,`mp_id` int(10) unsigned
,`mp_nombre` varchar(100)
,`cantidad_consumida` decimal(12,4)
,`lote_mp` varchar(50)
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accesos_log`
--
ALTER TABLE `accesos_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `ajustes_insumos`
--
ALTER TABLE `ajustes_insumos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `insumo_id` (`insumo_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `autorizador_id` (`autorizador_id`);

--
-- Indices de la tabla `ajustes_mp`
--
ALTER TABLE `ajustes_mp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mp_id` (`mp_id`),
  ADD KEY `solicitante_id` (`solicitante_id`),
  ADD KEY `autorizado_por` (`autorizado_por`);

--
-- Indices de la tabla `autorizaciones_compra`
--
ALTER TABLE `autorizaciones_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_oc` (`orden_compra_id`),
  ADD KEY `idx_aut` (`autorizador_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes_mayorista`
--
ALTER TABLE `clientes_mayorista`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cliente_mayorista` (`cliente_id`,`mayorista_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_mayorista` (`mayorista_user_id`);

--
-- Indices de la tabla `compras_mp`
--
ALTER TABLE `compras_mp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mp_id` (`mp_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `costeo_mano_obra`
--
ALTER TABLE `costeo_mano_obra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `empleado_id` (`empleado_id`),
  ADD KEY `idx_cmo_orden` (`orden_id`),
  ADD KEY `idx_cmo_usuario_fecha` (`usuario_id`,`fecha`);

--
-- Indices de la tabla `costos_indirectos_asignados`
--
ALTER TABLE `costos_indirectos_asignados`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_asig` (`periodo_inicio`,`periodo_fin`,`orden_id`,`fuente`),
  ADD KEY `orden_id` (`orden_id`);

--
-- Indices de la tabla `costos_indirectos_periodo`
--
ALTER TABLE `costos_indirectos_periodo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pool` (`periodo_inicio`,`periodo_fin`,`fuente`);

--
-- Indices de la tabla `costos_lote`
--
ALTER TABLE `costos_lote`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_id` (`orden_id`);

--
-- Indices de la tabla `C_canal_usuarios`
--
ALTER TABLE `C_canal_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email` (`email`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indices de la tabla `C_channels_prospectos`
--
ALTER TABLE `C_channels_prospectos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distribuidor_user_id` (`distribuidor_user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `razon_social` (`razon_social`);

--
-- Indices de la tabla `C_channels_prospecto_evidencias`
--
ALTER TABLE `C_channels_prospecto_evidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prospecto_id` (`prospecto_id`);

--
-- Indices de la tabla `C_distribuidores_registro`
--
ALTER TABLE `C_distribuidores_registro`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `razon_social` (`razon_social`),
  ADD KEY `email` (`email`),
  ADD KEY `idx_mayorista_user_id` (`mayorista_user_id`);

--
-- Indices de la tabla `C_distribuidor_documentos`
--
ALTER TABLE `C_distribuidor_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registro_id` (`registro_id`);

--
-- Indices de la tabla `C_encuestas_satisfaccion`
--
ALTER TABLE `C_encuestas_satisfaccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `mayorista_user_id` (`mayorista_user_id`);

--
-- Indices de la tabla `distribuidores_clientes`
--
ALTER TABLE `distribuidores_clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distribuidor_id` (`distribuidor_id`),
  ADD KEY `cliente_id` (`cliente_id`);

--
-- Indices de la tabla `entregas_venta`
--
ALTER TABLE `entregas_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`);

--
-- Indices de la tabla `fichas_produccion`
--
ALTER TABLE `fichas_produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `ficha_mp`
--
ALTER TABLE `ficha_mp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ficha_id` (`ficha_id`),
  ADD KEY `mp_id` (`mp_id`);

--
-- Indices de la tabla `gastos_compra`
--
ALTER TABLE `gastos_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oc_id` (`oc_id`);

--
-- Indices de la tabla `gastos_fijos`
--
ALTER TABLE `gastos_fijos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activo` (`activo`,`vigente_desde`),
  ADD KEY `categoria` (`categoria`);

--
-- Indices de la tabla `historial_precios_cliente`
--
ALTER TABLE `historial_precios_cliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `lista_precio_id` (`lista_precio_id`);

--
-- Indices de la tabla `insumos_comerciales`
--
ALTER TABLE `insumos_comerciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_insumo_prov` (`proveedor_id`);

--
-- Indices de la tabla `insumos_proveedores`
--
ALTER TABLE `insumos_proveedores`
  ADD PRIMARY KEY (`insumo_id`,`proveedor_id`),
  ADD KEY `proveedor_id` (`proveedor_id`);

--
-- Indices de la tabla `lineas_compra`
--
ALTER TABLE `lineas_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_oc` (`orden_compra_id`),
  ADD KEY `idx_mp` (`mp_id`),
  ADD KEY `idx_lineas_ic_id` (`ic_id`),
  ADD KEY `idx_oc_mp` (`orden_compra_id`,`mp_id`);

--
-- Indices de la tabla `lineas_venta`
--
ALTER TABLE `lineas_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`),
  ADD KEY `insumo_comercial_id` (`insumo_comercial_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `listas_precios`
--
ALTER TABLE `listas_precios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `materias_primas`
--
ALTER TABLE `materias_primas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `movimientos_insumos`
--
ALTER TABLE `movimientos_insumos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `insumo_id` (`insumo_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `movimientos_mp`
--
ALTER TABLE `movimientos_mp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mp_id` (`mp_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_mov_mp_origen` (`origen_id`);

--
-- Indices de la tabla `nomina`
--
ALTER TABLE `nomina`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proveedor` (`proveedor_id`),
  ADD KEY `idx_solicitante` (`solicitante_id`),
  ADD KEY `idx_autorizador` (`autorizador_id`),
  ADD KEY `fk_oc_modificador` (`modificador_id`);

--
-- Indices de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_creador` (`usuario_creador`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `autorizado_por` (`autorizado_por`),
  ADD KEY `ficha_id` (`ficha_id`),
  ADD KEY `idx_op_cancelada` (`cancelada`);

--
-- Indices de la tabla `ordenes_produccion_cancelaciones`
--
ALTER TABLE `ordenes_produccion_cancelaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_opc_op` (`orden_id`),
  ADD KEY `fk_opc_user` (`usuario_id`);

--
-- Indices de la tabla `ordenes_venta`
--
ALTER TABLE `ordenes_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_creador` (`usuario_creador`),
  ADD KEY `distribuidor_id` (`distribuidor_id`);

--
-- Indices de la tabla `orden_mp`
--
ALTER TABLE `orden_mp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_id` (`orden_id`),
  ADD KEY `mp_id` (`mp_id`);

--
-- Indices de la tabla `packaging_kits`
--
ALTER TABLE `packaging_kits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kit` (`producto_id`,`presentacion_id`,`insumo_comercial_id`);

--
-- Indices de la tabla `packaging_requests`
--
ALTER TABLE `packaging_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `solicitante_id` (`solicitante_id`),
  ADD KEY `autorizador_id` (`autorizador_id`),
  ADD KEY `idx_pr_orden` (`orden_id`);

--
-- Indices de la tabla `packaging_request_items`
--
ALTER TABLE `packaging_request_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_request_item` (`request_id`,`insumo_comercial_id`),
  ADD KEY `idx_pri_request` (`request_id`),
  ADD KEY `idx_pri_insumo` (`insumo_comercial_id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `insumo_comercial_id` (`insumo_comercial_id`);

--
-- Indices de la tabla `parametros_sistema`
--
ALTER TABLE `parametros_sistema`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `precios_cliente`
--
ALTER TABLE `precios_cliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `lista_precio_id` (`lista_precio_id`);

--
-- Indices de la tabla `presentaciones`
--
ALTER TABLE `presentaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `ux_presentaciones_slug` (`slug`);

--
-- Indices de la tabla `produccion_consumos`
--
ALTER TABLE `produccion_consumos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_consumos_prod_idx` (`produccion_id`),
  ADD KEY `fk_consumos_mp_idx` (`mp_id`),
  ADD KEY `idx_pc_prod` (`produccion_id`),
  ADD KEY `idx_pc_mp` (`mp_id`),
  ADD KEY `produccion_id` (`produccion_id`),
  ADD KEY `mp_id` (`mp_id`);

--
-- Indices de la tabla `produccion_consumos_sp`
--
ALTER TABLE `produccion_consumos_sp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produccion_final_id` (`produccion_final_id`),
  ADD KEY `pt_origen_id` (`pt_origen_id`);

--
-- Indices de la tabla `produccion_mo`
--
ALTER TABLE `produccion_mo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_id` (`orden_id`),
  ADD KEY `fecha` (`fecha`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_padre_id` (`producto_padre_id`),
  ADD KEY `creado_por` (`creado_por`);

--
-- Indices de la tabla `productos_presentaciones`
--
ALTER TABLE `productos_presentaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `presentacion_id` (`presentacion_id`);

--
-- Indices de la tabla `productos_terminados`
--
ALTER TABLE `productos_terminados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `presentacion_id` (`presentacion_id`),
  ADD KEY `idx_pt_lote` (`lote_produccion`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `proveedores_ic`
--
ALTER TABLE `proveedores_ic`
  ADD PRIMARY KEY (`insumo_id`,`proveedor_id`),
  ADD KEY `idx_proveedores_ic_insumo` (`insumo_id`),
  ADD KEY `idx_proveedores_ic_proveedor` (`proveedor_id`);

--
-- Indices de la tabla `proveedores_mp`
--
ALTER TABLE `proveedores_mp`
  ADD PRIMARY KEY (`proveedor_id`,`mp_id`),
  ADD KEY `mp_id` (`mp_id`);

--
-- Indices de la tabla `recepciones_compra_lineas`
--
ALTER TABLE `recepciones_compra_lineas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recepcionador_id` (`recepcionador_id`),
  ADD KEY `idx_rcl_linea` (`linea_id`),
  ADD KEY `idx_rcl_oc` (`orden_compra_id`),
  ADD KEY `idx_rcl_disp` (`cantidad_disponible`),
  ADD KEY `idx_rcl_fecha_id` (`fecha_ingreso`,`id`);

--
-- Indices de la tabla `requerimientos_produccion`
--
ALTER TABLE `requerimientos_produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`),
  ADD KEY `linea_venta_id` (`linea_venta_id`),
  ADD KEY `producto_id` (`producto_id`,`presentacion_id`,`estado`);

--
-- Indices de la tabla `reservas_venta`
--
ALTER TABLE `reservas_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`),
  ADD KEY `linea_venta_id` (`linea_venta_id`),
  ADD KEY `producto_id` (`producto_id`,`presentacion_id`,`estado`),
  ADD KEY `lote_codigo` (`lote_codigo`);

--
-- Indices de la tabla `reservas_venta_backup`
--
ALTER TABLE `reservas_venta_backup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`),
  ADD KEY `linea_venta_id` (`linea_venta_id`),
  ADD KEY `producto_id` (`producto_id`,`presentacion_id`,`estado`),
  ADD KEY `lote_codigo` (`lote_codigo`);

--
-- Indices de la tabla `salidas_ov`
--
ALTER TABLE `salidas_ov`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ov_id` (`ov_id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `presentacion_id` (`presentacion_id`);

--
-- Indices de la tabla `surtidos_venta`
--
ALTER TABLE `surtidos_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orden_venta_id` (`orden_venta_id`),
  ADD KEY `linea_venta_id` (`linea_venta_id`);

--
-- Indices de la tabla `uso_cajas`
--
ALTER TABLE `uso_cajas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ov_id` (`ov_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `idx_usuarios_activo` (`activo`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accesos_log`
--
ALTER TABLE `accesos_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ajustes_insumos`
--
ALTER TABLE `ajustes_insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ajustes_mp`
--
ALTER TABLE `ajustes_mp`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `autorizaciones_compra`
--
ALTER TABLE `autorizaciones_compra`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `clientes_mayorista`
--
ALTER TABLE `clientes_mayorista`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `compras_mp`
--
ALTER TABLE `compras_mp`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contactos`
--
ALTER TABLE `contactos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `costeo_mano_obra`
--
ALTER TABLE `costeo_mano_obra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `costos_indirectos_asignados`
--
ALTER TABLE `costos_indirectos_asignados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `costos_indirectos_periodo`
--
ALTER TABLE `costos_indirectos_periodo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `costos_lote`
--
ALTER TABLE `costos_lote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `C_canal_usuarios`
--
ALTER TABLE `C_canal_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `C_channels_prospectos`
--
ALTER TABLE `C_channels_prospectos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `C_channels_prospecto_evidencias`
--
ALTER TABLE `C_channels_prospecto_evidencias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `C_distribuidores_registro`
--
ALTER TABLE `C_distribuidores_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `C_distribuidor_documentos`
--
ALTER TABLE `C_distribuidor_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `C_encuestas_satisfaccion`
--
ALTER TABLE `C_encuestas_satisfaccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `distribuidores_clientes`
--
ALTER TABLE `distribuidores_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `entregas_venta`
--
ALTER TABLE `entregas_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `fichas_produccion`
--
ALTER TABLE `fichas_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT de la tabla `ficha_mp`
--
ALTER TABLE `ficha_mp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=307;

--
-- AUTO_INCREMENT de la tabla `gastos_compra`
--
ALTER TABLE `gastos_compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `gastos_fijos`
--
ALTER TABLE `gastos_fijos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_precios_cliente`
--
ALTER TABLE `historial_precios_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `insumos_comerciales`
--
ALTER TABLE `insumos_comerciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `lineas_compra`
--
ALTER TABLE `lineas_compra`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT de la tabla `lineas_venta`
--
ALTER TABLE `lineas_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de la tabla `listas_precios`
--
ALTER TABLE `listas_precios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `materias_primas`
--
ALTER TABLE `materias_primas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de la tabla `movimientos_insumos`
--
ALTER TABLE `movimientos_insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `movimientos_mp`
--
ALTER TABLE `movimientos_mp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT de la tabla `nomina`
--
ALTER TABLE `nomina`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=900015;

--
-- AUTO_INCREMENT de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `ordenes_produccion_cancelaciones`
--
ALTER TABLE `ordenes_produccion_cancelaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ordenes_venta`
--
ALTER TABLE `ordenes_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `orden_mp`
--
ALTER TABLE `orden_mp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `packaging_kits`
--
ALTER TABLE `packaging_kits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=378;

--
-- AUTO_INCREMENT de la tabla `packaging_requests`
--
ALTER TABLE `packaging_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `packaging_request_items`
--
ALTER TABLE `packaging_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT de la tabla `precios_cliente`
--
ALTER TABLE `precios_cliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `presentaciones`
--
ALTER TABLE `presentaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `produccion_consumos`
--
ALTER TABLE `produccion_consumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de la tabla `produccion_consumos_sp`
--
ALTER TABLE `produccion_consumos_sp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `produccion_mo`
--
ALTER TABLE `produccion_mo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT de la tabla `productos_presentaciones`
--
ALTER TABLE `productos_presentaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=483;

--
-- AUTO_INCREMENT de la tabla `productos_terminados`
--
ALTER TABLE `productos_terminados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `recepciones_compra_lineas`
--
ALTER TABLE `recepciones_compra_lineas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `requerimientos_produccion`
--
ALTER TABLE `requerimientos_produccion`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de la tabla `reservas_venta`
--
ALTER TABLE `reservas_venta`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `reservas_venta_backup`
--
ALTER TABLE `reservas_venta_backup`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `salidas_ov`
--
ALTER TABLE `salidas_ov`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `surtidos_venta`
--
ALTER TABLE `surtidos_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `uso_cajas`
--
ALTER TABLE `uso_cajas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Estructura para la vista `oc_tiene_recepciones`
--
DROP TABLE IF EXISTS `oc_tiene_recepciones`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `oc_tiene_recepciones`  AS SELECT `oc`.`id` AS `oc_id`, count(`r`.`id`) AS `n_recepciones` FROM ((`ordenes_compra` `oc` left join `lineas_compra` `lc` on(`lc`.`orden_compra_id` = `oc`.`id`)) left join `recepciones_compra_lineas` `r` on(`r`.`linea_id` = `lc`.`id`)) GROUP BY `oc`.`id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_costeo_empaque`
--
DROP TABLE IF EXISTS `vw_costeo_empaque`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_costeo_empaque`  AS SELECT `pr`.`orden_id` AS `orden_id`, sum(`ri`.`cantidad` * coalesce(`ic`.`precio_unitario`,0)) AS `costo_empaque` FROM ((`packaging_requests` `pr` join `packaging_request_items` `ri` on(`ri`.`request_id` = `pr`.`id`)) join `insumos_comerciales` `ic` on(`ic`.`id` = `ri`.`insumo_comercial_id`)) WHERE `pr`.`estado` = 'autorizada' GROUP BY `pr`.`orden_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_costeo_lote`
--
DROP TABLE IF EXISTS `vw_costeo_lote`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_costeo_lote`  AS SELECT `op`.`id` AS `orden_id`, `op`.`lote` AS `lote`, `op`.`fecha` AS `fecha`, `p`.`nombre` AS `producto`, coalesce(`mpc`.`costo_mp_lote`,0) + coalesce(`mpc`.`costo_mp_fallback`,0) AS `costo_mp`, coalesce(`ce`.`costo_empaque`,0) AS `costo_empaque`, coalesce(`modir`.`mo_directa`,0) AS `mano_obra`, coalesce(`ind`.`indirectos`,0) AS `indirectos`, coalesce(`pl`.`piezas`,0) AS `piezas`, coalesce(`mpc`.`costo_mp_lote`,0) + coalesce(`mpc`.`costo_mp_fallback`,0) + coalesce(`ce`.`costo_empaque`,0) + coalesce(`modir`.`mo_directa`,0) + coalesce(`ind`.`indirectos`,0) AS `costo_total`, CASE WHEN coalesce(`pl`.`piezas`,0) > 0 THEN (coalesce(`mpc`.`costo_mp_lote`,0) + coalesce(`mpc`.`costo_mp_fallback`,0) + coalesce(`ce`.`costo_empaque`,0) + coalesce(`modir`.`mo_directa`,0) + coalesce(`ind`.`indirectos`,0)) / `pl`.`piezas` ELSE 0 END AS `costo_unitario` FROM ((((((`ordenes_produccion` `op` left join `productos` `p` on(`p`.`id` = `op`.`producto_id`)) left join `vw_costeo_mp` `mpc` on(`mpc`.`orden_id` = `op`.`id`)) left join `vw_costeo_empaque` `ce` on(`ce`.`orden_id` = `op`.`id`)) left join `vw_mano_obra_directa` `modir` on(`modir`.`orden_id` = `op`.`id`)) left join `vw_indirectos_lote` `ind` on(`ind`.`orden_id` = `op`.`id`)) left join `vw_piezas_lote` `pl` on(`pl`.`orden_id` = `op`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_costeo_mp`
--
DROP TABLE IF EXISTS `vw_costeo_mp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_costeo_mp`  AS SELECT `pc`.`produccion_id` AS `orden_id`, sum(`pc`.`cantidad_consumida` * (coalesce(`rcl`.`costo_unitario_mxn`,`rcl`.`precio_unitario_neto` * coalesce(`rcl`.`tipo_cambio`,1),0) + coalesce(`rcl`.`gasto_prorrateado_unitario_mxn`,0))) AS `costo_mp_lote`, sum(case when `pc`.`lote_recepcion` is null then `pc`.`cantidad_consumida` * coalesce(`mp`.`precio_estimado`,`mp`.`precio_unitario`,0) else 0 end) AS `costo_mp_fallback` FROM ((`produccion_consumos` `pc` left join `recepciones_compra_lineas` `rcl` on(`rcl`.`id` = `pc`.`lote_recepcion`)) join `materias_primas` `mp` on(`mp`.`id` = `pc`.`mp_id`)) GROUP BY `pc`.`produccion_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_indirectos_lote`
--
DROP TABLE IF EXISTS `vw_indirectos_lote`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_indirectos_lote`  AS SELECT `costos_indirectos_asignados`.`orden_id` AS `orden_id`, coalesce(sum(`costos_indirectos_asignados`.`monto_mxn`),0) AS `indirectos` FROM `costos_indirectos_asignados` GROUP BY `costos_indirectos_asignados`.`orden_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_mano_obra_directa`
--
DROP TABLE IF EXISTS `vw_mano_obra_directa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_mano_obra_directa`  AS SELECT `pm`.`orden_id` AS `orden_id`, sum((coalesce(`pm`.`min_prod`,0) + coalesce(`pm`.`min_setup`,0)) / 60.0 * coalesce(`pm`.`costo_hora_aplicado`,(select `u`.`nomina_diaria_mxn` / nullif(`u`.`jornada_horas`,0) from `usuarios` `u` where `u`.`id` = `pm`.`user_id`))) AS `mo_directa` FROM `produccion_mo` AS `pm` GROUP BY `pm`.`orden_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_piezas_lote`
--
DROP TABLE IF EXISTS `vw_piezas_lote`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `vw_piezas_lote`  AS SELECT `productos_terminados`.`orden_id` AS `orden_id`, coalesce(sum(`productos_terminados`.`cantidad`),0) AS `piezas` FROM `productos_terminados` GROUP BY `productos_terminados`.`orden_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_traza_lote`
--
DROP TABLE IF EXISTS `v_traza_lote`;

CREATE ALGORITHM=UNDEFINED DEFINER=`apaintsc`@`localhost` SQL SECURITY DEFINER VIEW `v_traza_lote`  AS SELECT `pt`.`lote_produccion` AS `lote_produccion`, `pt`.`id` AS `pt_id`, `pt`.`orden_id` AS `orden_id`, `pt`.`fecha` AS `fecha`, `pc`.`mp_id` AS `mp_id`, `mp`.`nombre` AS `mp_nombre`, `pc`.`cantidad_consumida` AS `cantidad_consumida`, `rcl`.`lote` AS `lote_mp` FROM (((`productos_terminados` `pt` left join `produccion_consumos` `pc` on(`pc`.`produccion_id` = `pt`.`id`)) left join `materias_primas` `mp` on(`mp`.`id` = `pc`.`mp_id`)) left join `recepciones_compra_lineas` `rcl` on(`rcl`.`id` = `pc`.`lote_recepcion`)) ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `autorizaciones_compra`
--
ALTER TABLE `autorizaciones_compra`
  ADD CONSTRAINT `autorizaciones_compra_ibfk_1` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`),
  ADD CONSTRAINT `autorizaciones_compra_ibfk_2` FOREIGN KEY (`autorizador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `C_channels_prospecto_evidencias`
--
ALTER TABLE `C_channels_prospecto_evidencias`
  ADD CONSTRAINT `C_channels_prospecto_evidencias_ibfk_1` FOREIGN KEY (`prospecto_id`) REFERENCES `C_channels_prospectos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `C_distribuidor_documentos`
--
ALTER TABLE `C_distribuidor_documentos`
  ADD CONSTRAINT `C_distribuidor_documentos_ibfk_1` FOREIGN KEY (`registro_id`) REFERENCES `C_distribuidores_registro` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entregas_venta`
--
ALTER TABLE `entregas_venta`
  ADD CONSTRAINT `entregas_venta_ibfk_1` FOREIGN KEY (`orden_venta_id`) REFERENCES `ordenes_venta` (`id`);

--
-- Filtros para la tabla `gastos_compra`
--
ALTER TABLE `gastos_compra`
  ADD CONSTRAINT `fk_gastos_compra_oc` FOREIGN KEY (`oc_id`) REFERENCES `ordenes_compra` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `lineas_compra`
--
ALTER TABLE `lineas_compra`
  ADD CONSTRAINT `fk_lineas_ic` FOREIGN KEY (`ic_id`) REFERENCES `insumos_comerciales` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `lineas_compra_ibfk_1` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`),
  ADD CONSTRAINT `lineas_compra_ibfk_2` FOREIGN KEY (`mp_id`) REFERENCES `materias_primas` (`id`);

--
-- Filtros para la tabla `ordenes_compra`
--
ALTER TABLE `ordenes_compra`
  ADD CONSTRAINT `fk_oc_modificador` FOREIGN KEY (`modificador_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `ordenes_compra_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `ordenes_compra_ibfk_2` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `ordenes_compra_ibfk_3` FOREIGN KEY (`autorizador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `produccion_consumos`
--
ALTER TABLE `produccion_consumos`
  ADD CONSTRAINT `fk_consumos_mp` FOREIGN KEY (`mp_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_consumos_prod` FOREIGN KEY (`produccion_id`) REFERENCES `productos_terminados` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_mp` FOREIGN KEY (`mp_id`) REFERENCES `materias_primas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pc_pt` FOREIGN KEY (`produccion_id`) REFERENCES `productos_terminados` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `produccion_consumos_sp`
--
ALTER TABLE `produccion_consumos_sp`
  ADD CONSTRAINT `fk_spc_pt_final` FOREIGN KEY (`produccion_final_id`) REFERENCES `productos_terminados` (`id`),
  ADD CONSTRAINT `fk_spc_pt_origen` FOREIGN KEY (`pt_origen_id`) REFERENCES `productos_terminados` (`id`);

--
-- Filtros para la tabla `proveedores_ic`
--
ALTER TABLE `proveedores_ic`
  ADD CONSTRAINT `fk_proveedores_ic_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos_comerciales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_proveedores_ic_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `proveedores_mp`
--
ALTER TABLE `proveedores_mp`
  ADD CONSTRAINT `proveedores_mp_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proveedores_mp_ibfk_2` FOREIGN KEY (`mp_id`) REFERENCES `materias_primas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recepciones_compra_lineas`
--
ALTER TABLE `recepciones_compra_lineas`
  ADD CONSTRAINT `fk_rcl_linea` FOREIGN KEY (`linea_id`) REFERENCES `lineas_compra` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `surtidos_venta`
--
ALTER TABLE `surtidos_venta`
  ADD CONSTRAINT `surtidos_venta_ibfk_1` FOREIGN KEY (`orden_venta_id`) REFERENCES `ordenes_venta` (`id`),
  ADD CONSTRAINT `surtidos_venta_ibfk_2` FOREIGN KEY (`linea_venta_id`) REFERENCES `lineas_venta` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
