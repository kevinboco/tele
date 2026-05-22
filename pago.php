CREATE TABLE `cuentas_guardadas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `empresa` varchar(100) NOT NULL,
  `desde` date NOT NULL,
  `hasta` date NOT NULL,
  `facturado` decimal(15,2) NOT NULL,
  `porcentaje_ajuste` decimal(5,2) NOT NULL,
  `pagado` tinyint(1) NOT NULL DEFAULT 0,
  `datos_json` longtext NOT NULL,
  `comprobantes_json` longtext DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `usuario` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
