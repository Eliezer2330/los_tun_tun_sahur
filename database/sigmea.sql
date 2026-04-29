-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-04-2026 a las 19:30:21
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sigmea`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_verificar_disponibilidad` (IN `p_espacio_id` INT, IN `p_dia` VARCHAR(15), IN `p_hora_ini` TIME, IN `p_hora_fin` TIME, OUT `p_disponible` TINYINT, OUT `p_conflictos` INT)   BEGIN
    SELECT COUNT(*) INTO p_conflictos
    FROM horarios
    WHERE espacio_id = p_espacio_id
      AND dia_semana  = p_dia
      AND hora_inicio < p_hora_fin
      AND hora_fin    > p_hora_ini
      AND fecha_inicio_vigencia <= CURDATE()
      AND fecha_fin_vigencia    >= CURDATE();

    SET p_disponible = IF(p_conflictos = 0, 1, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `espacios`
--

CREATE TABLE `espacios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(30) NOT NULL,
  `tipo` enum('salon','laboratorio','computo','usos_multiples') NOT NULL,
  `capacidad` smallint(5) UNSIGNED NOT NULL,
  `edificio` char(2) NOT NULL,
  `piso` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `equipamiento` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`equipamiento`)),
  `estado` enum('disponible','mantenimiento') NOT NULL DEFAULT 'disponible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `espacio_id` int(10) UNSIGNED NOT NULL,
  `docente_id` int(10) UNSIGNED NOT NULL,
  `grupo` varchar(20) NOT NULL,
  `materia` varchar(120) NOT NULL,
  `dia_semana` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `fecha_inicio_vigencia` date NOT NULL,
  `fecha_fin_vigencia` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias`
--

CREATE TABLE `incidencias` (
  `id` int(10) UNSIGNED NOT NULL,
  `espacio_id` int(10) UNSIGNED NOT NULL,
  `reportado_por` int(10) UNSIGNED NOT NULL,
  `tipo` enum('falla','ausencia_docente','otro') NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('abierta','en_proceso','cerrada') NOT NULL DEFAULT 'abierta',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_estado_espacio`
--

CREATE TABLE `log_estado_espacio` (
  `id` int(10) UNSIGNED NOT NULL,
  `espacio_id` int(10) UNSIGNED NOT NULL,
  `estado_anterior` varchar(30) NOT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `motivo` text DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_ocupacion`
--

CREATE TABLE `log_ocupacion` (
  `id` int(10) UNSIGNED NOT NULL,
  `espacio_id` int(10) UNSIGNED NOT NULL,
  `horario_id` int(10) UNSIGNED DEFAULT NULL,
  `timestamp_entrada` datetime NOT NULL,
  `timestamp_salida` datetime DEFAULT NULL,
  `ocupacion_real` smallint(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimiento`
--

CREATE TABLE `mantenimiento` (
  `id` int(10) UNSIGNED NOT NULL,
  `espacio_id` int(10) UNSIGNED NOT NULL,
  `incidencia_id` int(10) UNSIGNED DEFAULT NULL,
  `tecnico_responsable` varchar(120) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_estimada_fin` date DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizado') NOT NULL DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('admin','academico','prefecto') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `espacios`
--
ALTER TABLE `espacios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_edificio` (`edificio`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_horario_docente` (`docente_id`),
  ADD KEY `idx_horario_busqueda` (`espacio_id`,`dia_semana`,`hora_inicio`,`hora_fin`);

--
-- Indices de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_incid_usuario` (`reportado_por`),
  ADD KEY `idx_incid_estado` (`estado`),
  ADD KEY `idx_incid_espacio` (`espacio_id`);

--
-- Indices de la tabla `log_estado_espacio`
--
ALTER TABLE `log_estado_espacio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_espacio` (`espacio_id`),
  ADD KEY `idx_log_fecha` (`created_at`);

--
-- Indices de la tabla `log_ocupacion`
--
ALTER TABLE `log_ocupacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logocup_espacio` (`espacio_id`),
  ADD KEY `idx_logocup_entrada` (`timestamp_entrada`);

--
-- Indices de la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mant_espacio` (`espacio_id`),
  ADD KEY `fk_mant_incidencia` (`incidencia_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `espacios`
--
ALTER TABLE `espacios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `incidencias`
--
ALTER TABLE `incidencias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_estado_espacio`
--
ALTER TABLE `log_estado_espacio`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_ocupacion`
--
ALTER TABLE `log_ocupacion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `fk_horario_docente` FOREIGN KEY (`docente_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `fk_horario_espacio` FOREIGN KEY (`espacio_id`) REFERENCES `espacios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `incidencias`
--
ALTER TABLE `incidencias`
  ADD CONSTRAINT `fk_incid_espacio` FOREIGN KEY (`espacio_id`) REFERENCES `espacios` (`id`),
  ADD CONSTRAINT `fk_incid_usuario` FOREIGN KEY (`reportado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `mantenimiento`
--
ALTER TABLE `mantenimiento`
  ADD CONSTRAINT `fk_mant_espacio` FOREIGN KEY (`espacio_id`) REFERENCES `espacios` (`id`),
  ADD CONSTRAINT `fk_mant_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
