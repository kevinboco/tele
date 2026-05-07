-- Estructura de la tabla `viajes`
CREATE TABLE `viajes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `ruta` varchar(150) DEFAULT NULL,
  `tipo_vehiculo` varchar(50) DEFAULT NULL,
  `empresa` varchar(255) DEFAULT NULL,
  `pago_parcial` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Estructura de la tabla `ruta_clasificacion`
CREATE TABLE `ruta_clasificacion` (
  `ruta` varchar(255) NOT NULL,
  `tipo_vehiculo` varchar(100) NOT NULL,
  `clasificacion` varchar(50) NOT NULL,
  PRIMARY KEY (`ruta`,`tipo_vehiculo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Estructura de la tabla `tarifas`
CREATE TABLE `tarifas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa` varchar(100) NOT NULL,
  `tipo_vehiculo` varchar(100) NOT NULL,
  `completo` decimal(10,2) DEFAULT 0.00,
  `medio` decimal(10,2) DEFAULT 0.00,
  `extra` decimal(10,2) DEFAULT 0.00,
  `carrotanque` decimal(10,2) DEFAULT 0.00,
  `siapana` int(11) DEFAULT 0,
  `prueba` decimal(10,2) DEFAULT 0.00,
  `riohacha_completo` decimal(10,2) DEFAULT 0.00,
  `nazareth_siapana_maicao` decimal(10,2) DEFAULT 0.00,
  `riohacha_medio` decimal(10,2) DEFAULT 0.00,
  `nazareth_siapana_flor_de_la_guajira` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa` (`empresa`,`tipo_vehiculo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;