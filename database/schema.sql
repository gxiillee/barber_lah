CREATE TABLE `barberos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `especialidad` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barberos`
--

LOCK TABLES `barberos` WRITE;
/*!40000 ALTER TABLE `barberos` DISABLE KEYS */;
INSERT INTO `barberos` VALUES (1,'Hassan','976000000','Corte, barba y diseÃ±o experto',1),(2,'Dani','600555666','Cortes modernos y degradados',1);
/*!40000 ALTER TABLE `barberos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bloqueos`
--

DROP TABLE IF EXISTS `bloqueos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `bloqueos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_barbero` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bloqueos_barbero_fecha` (`id_barbero`,`fecha`),
  CONSTRAINT `bloqueos_ibfk_1` FOREIGN KEY (`id_barbero`) REFERENCES `barberos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bloqueos`
--

LOCK TABLES `bloqueos` WRITE;
/*!40000 ALTER TABLE `bloqueos` DISABLE KEYS */;
INSERT INTO `bloqueos` VALUES (6,1,'2026-05-29',NULL,NULL,NULL),(7,1,'2026-05-28',NULL,NULL,NULL),(8,1,'2026-06-17',NULL,NULL,'Asuntos propios'),(17,1,'2026-06-21',NULL,NULL,'para probar');
/*!40000 ALTER TABLE `bloqueos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_web`
--

DROP TABLE IF EXISTS `config_web`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `config_web` (
  `id` varchar(50) NOT NULL,
  `direccion` varchar(255) DEFAULT '',
  `telefono` varchar(50) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `instagram` varchar(100) DEFAULT '',
  `horario_resumen` varchar(255) DEFAULT '',
  `sobre_subtitulo` varchar(255) DEFAULT '',
  `sobre_titulo` varchar(255) DEFAULT '',
  `sobre_imagen` varchar(255) DEFAULT '',
  `sobre_anios` varchar(20) DEFAULT '',
  `sobre_anios_texto` varchar(255) DEFAULT '',
  `sobre_nombre` varchar(100) DEFAULT '',
  `sobre_texto_1` text DEFAULT NULL,
  `sobre_texto_2` text DEFAULT NULL,
  `sobre_texto_3` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_web`
--

LOCK TABLES `config_web` WRITE;
/*!40000 ALTER TABLE `config_web` DISABLE KEYS */;
INSERT INTO `config_web` VALUES ('negocio','Calle Miguel Servet n\'24','612 34 56 78','info@barbershoplah.com','@barbershop_la_h','Lunâ€“Vie 9:00â€“20:00 Â· SÃ¡b 9:00â€“14:00','Barbershop La H','Sobre Nosotros','public/assets/img/logo.jpg','+10','AÃ±os de exp.','Hassan','Con mÃ¡s de 10 aÃ±os de experiencia en el mundo de la barberÃ­a, me he convertido en mucho mÃ¡s que un barbero: soy tu aliado para encontrar el estilo que te representa.','En Barbershop La H combinamos tÃ©cnica clÃ¡sica con las tendencias mÃ¡s modernas para crear un look Ãºnico que se adapte a tu personalidad y estilo de vida.','Nuestra barberÃ­a es un espacio de confianza donde el tiempo se detiene y cada cliente recibe una atenciÃ³n completamente personalizada.');
/*!40000 ALTER TABLE `config_web` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fotos_cliente`
--

DROP TABLE IF EXISTS `fotos_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `fotos_cliente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `ruta` varchar(255) NOT NULL,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fotos_cliente_usuario` (`id_usuario`),
  CONSTRAINT `fotos_cliente_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fotos_cliente`
--

LOCK TABLES `fotos_cliente` WRITE;
/*!40000 ALTER TABLE `fotos_cliente` DISABLE KEYS */;
INSERT INTO `fotos_cliente` VALUES (10,6,'public/uploads/fotos_clientes/foto_6_1779476686_0.jpg','2026-05-22 00:00:00'),(11,6,'public/uploads/fotos_clientes/foto_6_1779476686_1.jpg','2026-05-22 00:00:00'),(12,6,'public/uploads/fotos_clientes/foto_6_1779476687_2.jpg','2026-05-22 00:00:00'),(28,5,'public/uploads/fotos_clientes/foto_5_1781702017_0.jpg','2026-06-17 00:00:00'),(29,5,'public/uploads/fotos_clientes/foto_5_1781702018_1.jpg','2026-06-17 00:00:00'),(30,5,'public/uploads/fotos_clientes/foto_5_1781702020_2.jpg','2026-06-17 00:00:00'),(31,5,'public/uploads/fotos_clientes/foto_5_1781702519_0.jpg','2026-06-17 00:00:00');
/*!40000 ALTER TABLE `fotos_cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `galeria`
--

DROP TABLE IF EXISTS `galeria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `galeria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `imagen` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT '',
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  `fecha_subida` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `galeria`
--

LOCK TABLES `galeria` WRITE;
/*!40000 ALTER TABLE `galeria` DISABLE KEYS */;
INSERT INTO `galeria` VALUES (1,'public/uploads/galeria/taper-texturizado.jpeg','Taper Texturizado','',1,2,'2026-06-16 19:30:56'),(2,'public/uploads/galeria/burst-fade.jpeg','Burst Fade','',1,1,'2026-06-16 19:30:56'),(3,'public/uploads/galeria/mullet-diseno-1.jpeg','Mullet Diseno 1','',1,0,'2026-06-16 19:30:56'),(4,'public/uploads/galeria/buzz-cut.jpeg','Buzz Cut','',1,3,'2026-06-16 19:30:56'),(5,'public/uploads/galeria/mohicano-diseno-2.jpeg','Mohicano Diseno 2','',1,4,'2026-06-16 19:30:56'),(6,'public/uploads/galeria/low-fade-v.jpeg','Low Fade V','',1,5,'2026-06-16 19:30:56'),(7,'public/uploads/galeria/taper-rizado.jpeg','Taper Rizado','',1,6,'2026-06-16 19:30:56'),(8,'public/uploads/galeria/mohicano.jpeg','Mohicano','',1,7,'2026-06-16 19:30:56'),(9,'public/uploads/galeria/high-fade.jpeg','High Fade','',1,8,'2026-06-16 19:30:56'),(10,'public/uploads/galeria/mullet.jpeg','Mullet','',1,9,'2026-06-16 19:30:56'),(11,'public/uploads/galeria/lowfade.jpeg','Lowfade','',1,10,'2026-06-16 19:30:56'),(12,'public/uploads/galeria/taper.jpeg','Taper','',1,11,'2026-06-16 19:30:56');
/*!40000 ALTER TABLE `galeria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horarios`
--

DROP TABLE IF EXISTS `horarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `horarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_barbero` int(11) NOT NULL,
  `dia_semana` varchar(20) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_barbero` (`id_barbero`),
  CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`id_barbero`) REFERENCES `barberos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
INSERT INTO `horarios` VALUES (1,1,'lunes','10:00:00','14:00:00'),(2,1,'martes','10:00:00','14:00:00'),(3,1,'miercoles','10:00:00','14:00:00'),(4,1,'jueves','10:00:00','14:00:00'),(5,1,'viernes','10:00:00','14:00:00'),(6,1,'sabado','09:30:00','14:00:00'),(7,2,'lunes','15:00:00','20:00:00'),(8,2,'martes','15:00:00','20:00:00'),(9,2,'miercoles','15:00:00','20:00:00'),(10,2,'jueves','15:00:00','20:00:00'),(11,2,'viernes','15:00:00','20:00:00'),(18,1,'lunes','15:30:00','20:00:00'),(19,1,'martes','15:30:00','20:00:00'),(20,1,'miercoles','15:30:00','20:00:00'),(21,1,'jueves','15:30:00','20:00:00'),(22,1,'viernes','15:30:00','20:00:00');
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_history`
--

DROP TABLE IF EXISTS `password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `password_history_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_history`
--

LOCK TABLES `password_history` WRITE;
/*!40000 ALTER TABLE `password_history` DISABLE KEYS */;
INSERT INTO `password_history` VALUES (12,9,'$2y$10$i7WsBrgKMIyzP/YA5iA4ueqpri42Tcdic7rVmGJeaIsLK2K0.jHxa','2026-06-19 13:27:34'),(13,9,'$2y$10$8SbKFy2OKvoBRQ2UWzyDH.E2YyUrqr6UTgad8Dk2NqB3i32YIfjk2','2026-06-19 13:28:03'),(14,9,'$2y$10$F0srAWPbkdcSkf3DzisF0.xo0EZntj1OcbLvftU8MscFv3nxqZC5u','2026-06-19 13:30:41');
/*!40000 ALTER TABLE `password_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT 0.00,
  `imagen` varchar(255) DEFAULT '',
  `activo` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Cera Mate Premium','FijaciÃ³n fuerte y acabado natural',15.99,'public/uploads/productos/cera-impala.png',1,0),(4,'nfttgft','gfhddf',233.00,'public/uploads/productos/prod_1781885614_64fc7069.jpg',1,0),(5,'h5erhj5','ghsrehrew',12.00,'public/uploads/productos/prod_1781885623_087982f3.png',1,0),(6,'Ã³dtrdhejh5sj5','22234123',8989.00,'public/uploads/productos/prod_1781885635_b4107d33.png',1,0),(7,'lil.ijÃ±ij','23445',15.00,'public/uploads/productos/prod_1781885645_c5192f6c.png',1,0);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservas`
--

DROP TABLE IF EXISTS `reservas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `reservas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_barbero` int(11) NOT NULL,
  `id_servicio` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `precio_historico` decimal(6,2) NOT NULL,
  `duracion_historica` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `nota` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `motivo_cancelacion` text DEFAULT NULL,
  `recordatorio_enviado` tinyint(1) DEFAULT 0,
  `recordatorio_hoy_enviado` tinyint(1) DEFAULT 0,
  `gratis` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reserva_slot` (`id_barbero`,`fecha`,`hora`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_servicio` (`id_servicio`),
  KEY `idx_reservas_fecha` (`fecha`),
  KEY `idx_reservas_barbero_fecha` (`id_barbero`,`fecha`),
  KEY `idx_reservas_estado` (`estado`),
  KEY `idx_reservas_estado_fecha` (`estado`,`fecha`),
  CONSTRAINT `reservas_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `reservas_ibfk_2` FOREIGN KEY (`id_barbero`) REFERENCES `barberos` (`id`),
  CONSTRAINT `reservas_ibfk_3` FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservas`
--

LOCK TABLES `reservas` WRITE;
/*!40000 ALTER TABLE `reservas` DISABLE KEYS */;
INSERT INTO `reservas` VALUES (1,2,1,1,'2026-05-01','10:00:00',14.00,30,'completada',NULL,'2026-05-08 19:51:56','2026-05-08 19:51:56',NULL,0,0,0),(2,3,2,2,'2026-05-20','17:30:00',20.00,30,'completada',NULL,'2026-05-08 19:51:56','2026-05-22 17:03:37',NULL,0,0,0),(3,4,1,3,'2026-05-22','11:00:00',12.00,30,'completada','El niÃ±o tiene el pelo muy largo','2026-05-08 19:51:56','2026-05-22 17:03:37',NULL,0,0,0),(4,2,2,4,'2026-05-23','16:00:00',7.00,30,'completada',NULL,'2026-05-08 19:51:56','2026-05-22 15:38:27',NULL,0,0,0),(5,3,1,1,'2026-04-15','09:30:00',14.00,30,'completada',NULL,'2026-05-08 19:51:56','2026-05-08 19:51:56',NULL,0,0,0),(6,4,1,6,'2026-04-20','12:00:00',5.00,20,'cancelada','Cliente cancelÃ³ por enfermedad','2026-05-08 19:51:56','2026-05-08 19:51:56',NULL,0,0,0),(7,5,1,1,'2026-05-13','17:30:00',14.00,30,'completada',NULL,'2026-05-13 16:45:40','2026-05-22 17:03:37',NULL,0,0,0),(8,5,1,2,'2026-05-28','10:00:00',20.00,30,'completada',NULL,'2026-05-18 20:25:40','2026-05-22 15:38:27',NULL,0,0,0),(9,5,1,2,'2026-05-21','18:00:00',20.00,30,'completada',NULL,'2026-05-18 20:41:58','2026-05-22 17:03:37',NULL,0,0,0),(10,5,1,1,'2026-05-19','10:00:00',14.00,30,'completada',NULL,'2026-05-18 22:07:24','2026-05-22 17:03:37',NULL,0,0,0),(11,6,1,1,'2026-05-20','09:30:00',14.00,30,'completada',NULL,'2026-05-19 13:32:39','2026-05-22 17:03:37',NULL,0,0,0),(12,6,1,2,'2026-05-29','11:00:00',20.00,30,'completada',NULL,'2026-05-19 20:25:23','2026-05-22 15:38:27',NULL,0,0,0),(13,6,1,1,'2026-05-23','12:30:00',14.00,30,'completada',NULL,'2026-05-19 20:28:54','2026-05-22 15:38:27',NULL,0,0,0),(14,6,1,1,'2026-05-21','14:30:00',14.00,30,'completada',NULL,'2026-05-20 16:19:43','2026-05-22 17:03:37',NULL,0,0,0),(15,6,1,1,'2026-05-20','19:30:00',14.00,30,'completada',NULL,'2026-05-20 16:23:53','2026-05-22 17:03:37',NULL,0,0,0),(16,6,1,1,'2026-05-20','19:00:00',14.00,30,'completada',NULL,'2026-05-20 16:25:49','2026-05-22 17:03:37',NULL,0,0,0),(17,6,1,2,'2026-05-22','15:30:00',20.00,30,'completada',NULL,'2026-05-22 15:24:57','2026-05-22 17:03:37',NULL,0,0,0),(18,6,1,1,'2026-05-22','16:30:00',14.00,30,'completada',NULL,'2026-05-22 15:26:31','2026-05-22 17:05:43',NULL,0,0,0),(19,6,1,1,'2026-05-22','16:00:00',14.00,30,'completada',NULL,'2026-05-22 15:26:40','2026-05-22 17:03:37',NULL,0,0,0),(20,5,1,1,'2026-05-22','17:30:00',14.00,30,'completada',NULL,'2026-05-22 17:15:12','2026-05-22 15:38:27',NULL,0,0,0),(21,6,1,1,'2026-05-22','18:00:00',14.00,30,'completada',NULL,'2026-05-22 17:33:36','2026-05-22 15:38:27',NULL,0,0,0),(22,6,1,1,'2026-05-22','18:30:00',14.00,30,'completada',NULL,'2026-05-22 17:35:18','2026-05-22 15:38:27',NULL,0,0,0),(23,6,1,1,'2026-05-22','19:00:00',14.00,30,'completada',NULL,'2026-05-22 17:35:26','2026-05-22 15:38:27',NULL,0,0,0),(24,6,1,2,'2026-05-22','19:30:00',20.00,30,'completada',NULL,'2026-05-22 17:35:34','2026-05-22 15:38:27',NULL,0,0,0),(25,6,1,1,'2026-05-23','09:00:00',14.00,30,'completada',NULL,'2026-05-22 17:40:12','2026-05-25 15:50:32',NULL,0,0,0),(26,6,1,2,'2026-05-23','09:30:00',20.00,30,'completada',NULL,'2026-05-22 17:40:19','2026-05-25 15:50:32',NULL,0,0,0),(27,6,1,2,'2026-05-23','10:00:00',20.00,30,'completada',NULL,'2026-05-22 17:40:24','2026-05-25 15:50:32',NULL,0,0,0),(28,5,1,3,'2026-05-23','11:00:00',12.00,30,'completada',NULL,'2026-05-22 20:10:10','2026-05-25 15:50:32',NULL,0,0,0),(29,6,1,1,'2026-05-25','16:00:00',14.00,30,'completada',NULL,'2026-05-25 15:50:48','2026-05-26 16:16:48',NULL,0,0,0),(30,6,1,2,'2026-05-25','16:30:00',20.00,30,'completada',NULL,'2026-05-25 15:51:00','2026-05-26 16:16:48',NULL,0,0,0),(31,5,1,2,'2026-05-25','17:00:00',20.00,30,'completada',NULL,'2026-05-25 16:57:55','2026-05-26 16:16:48',NULL,0,0,0),(32,5,1,2,'2026-05-25','17:30:00',20.00,30,'completada',NULL,'2026-05-25 16:58:00','2026-05-26 16:16:48',NULL,0,0,0),(33,6,1,1,'2026-05-26','17:30:00',14.00,30,'completada',NULL,'2026-05-26 16:16:35','2026-05-26 18:47:14',NULL,0,0,0),(34,6,1,1,'2026-05-26','17:00:00',14.00,30,'completada',NULL,'2026-05-26 16:59:45','2026-05-26 18:47:14',NULL,0,0,0),(35,6,1,1,'2026-05-26','19:30:00',14.00,30,'completada',NULL,'2026-05-26 19:25:31','2026-05-26 20:41:21',NULL,0,0,0),(36,6,1,1,'2026-05-27','09:00:00',14.00,30,'completada',NULL,'2026-05-26 19:26:26','2026-06-03 15:52:53',NULL,0,0,0),(37,6,1,1,'2026-06-03','16:00:00',14.00,30,'completada',NULL,'2026-06-03 15:51:40','2026-06-03 17:37:36',NULL,0,0,0),(38,6,1,3,'2026-06-12','09:00:00',12.00,30,'completada',NULL,'2026-06-11 18:04:11','2026-06-11 19:34:41',NULL,0,0,0),(39,6,1,1,'2026-06-11','18:30:00',14.00,30,'completada',NULL,'2026-06-11 18:04:55','2026-06-11 19:01:53',NULL,0,0,0),(40,6,1,1,'2026-06-12','12:00:00',14.00,30,'completada',NULL,'2026-06-12 11:56:51','2026-06-12 13:00:33',NULL,0,0,0),(41,5,1,1,'2026-06-13','12:30:00',14.00,30,'no_presentado',NULL,'2026-06-13 12:15:09','2026-06-13 12:15:46',NULL,0,0,0),(42,5,1,1,'2026-06-13','13:00:00',14.00,30,'completada',NULL,'2026-06-13 12:15:20','2026-06-14 11:55:05',NULL,0,0,0),(43,6,1,2,'2026-06-13','13:30:00',20.00,30,'completada',NULL,'2026-06-13 12:17:55','2026-06-14 11:55:05',NULL,0,0,0),(44,6,1,1,'2026-06-16','09:00:00',14.00,30,'completada',NULL,'2026-06-13 12:19:40','2026-06-16 20:13:38',NULL,1,1,0),(45,6,1,2,'2026-06-16','09:30:00',20.00,30,'completada',NULL,'2026-06-13 12:21:52','2026-06-16 20:07:43',NULL,0,1,0),(47,9,1,1,'2026-06-16','12:30:00',14.00,30,'cancelada',NULL,'2026-06-15 17:05:26','2026-06-15 17:19:12','soy yo',0,0,0),(48,9,1,2,'2026-06-20','10:30:00',20.00,30,'completada',NULL,'2026-06-15 17:05:32','2026-06-16 21:48:11',NULL,0,0,0),(49,9,1,2,'2026-06-16','10:00:00',20.00,30,'completada',NULL,'2026-06-15 21:20:35','2026-06-16 20:07:42',NULL,0,1,0),(50,5,1,1,'2026-06-16','11:00:00',14.00,30,'completada',NULL,'2026-06-16 10:58:23','2026-06-16 20:07:45',NULL,0,1,0),(51,6,1,1,'2026-06-16','19:30:00',14.00,30,'completada',NULL,'2026-06-16 19:26:32','2026-06-16 20:13:38',NULL,0,1,0),(53,6,1,1,'2026-06-17','10:00:00',14.00,30,'no_presentado',NULL,'2026-06-16 20:17:35','2026-06-16 20:17:59',NULL,0,0,0),(54,6,1,1,'2026-06-17','10:30:00',14.00,30,'cancelada',NULL,'2026-06-16 20:20:10','2026-06-16 20:31:39','Asuntos propios',0,0,0),(55,6,1,1,'2026-06-17','11:00:00',14.00,30,'cancelada',NULL,'2026-06-16 20:22:51','2026-06-16 20:31:41','Asuntos propios',0,0,0),(56,5,1,1,'2026-06-18','10:00:00',14.00,30,'completada',NULL,'2026-06-16 21:02:57','2026-06-16 21:03:14',NULL,0,0,0),(57,5,1,1,'2026-06-18','10:30:00',14.00,30,'completada',NULL,'2026-06-16 21:07:14','2026-06-16 21:07:31',NULL,0,0,0),(58,5,1,1,'2026-06-18','11:00:00',14.00,30,'completada',NULL,'2026-06-16 21:13:34','2026-06-16 21:13:50',NULL,0,0,0),(59,5,1,1,'2026-06-18','11:30:00',14.00,30,'completada',NULL,'2026-06-17 15:04:10','2026-06-17 15:14:26',NULL,0,0,0),(60,5,1,1,'2026-06-18','12:00:00',14.00,30,'completada',NULL,'2026-06-17 15:11:25','2026-06-17 15:14:45',NULL,0,0,0),(61,5,1,1,'2026-06-18','12:30:00',14.00,30,'completada',NULL,'2026-06-17 23:20:39','2026-06-18 13:48:28',NULL,0,0,1),(62,5,1,1,'2026-06-21','09:00:00',14.00,30,'completada',NULL,'2026-06-17 23:32:12','2026-06-18 14:48:30',NULL,0,0,0),(63,5,1,1,'2026-06-18','15:30:00',14.00,30,'completada',NULL,'2026-06-18 13:53:45','2026-06-18 14:15:30',NULL,0,0,1),(64,5,1,1,'2026-06-18','16:00:00',14.00,30,'completada',NULL,'2026-06-18 14:22:04','2026-06-18 14:30:35',NULL,0,0,0),(65,5,1,1,'2026-06-18','16:30:00',14.00,30,'completada',NULL,'2026-06-18 14:31:07','2026-06-18 14:31:27',NULL,0,0,1),(66,5,1,1,'2026-06-18','17:00:00',14.00,30,'completada',NULL,'2026-06-18 14:33:32','2026-06-18 14:35:29',NULL,0,0,1),(67,5,1,2,'2026-06-18','17:30:00',20.00,30,'completada',NULL,'2026-06-18 14:33:41','2026-06-18 14:48:00',NULL,0,0,0),(69,5,1,1,'2026-06-18','18:00:00',14.00,30,'completada',NULL,'2026-06-18 14:50:12','2026-06-18 14:50:25',NULL,0,0,1),(70,5,1,1,'2026-06-19','10:00:00',14.00,30,'completada',NULL,'2026-06-18 15:04:07','2026-06-19 17:59:44',NULL,0,1,0),(71,5,1,1,'2026-06-18','18:30:00',14.00,30,'cancelada',NULL,'2026-06-18 15:05:25','2026-06-18 15:05:45','me voy a casa',0,0,0),(72,5,1,1,'2026-06-20','09:30:00',14.00,30,'no_presentado',NULL,'2026-06-18 15:14:32','2026-06-18 15:15:20',NULL,0,0,0),(74,5,1,1,'2026-06-20','11:30:00',14.00,30,'confirmada',NULL,'2026-06-18 15:14:53','2026-06-18 15:14:53',NULL,0,0,0),(75,2,1,1,'2026-06-20','10:00:00',15.00,30,'completada','Test nota','2026-06-19 13:26:12','2026-06-19 13:26:12',NULL,0,0,0),(76,2,1,1,'2026-06-20','11:00:00',15.00,30,'completada','Test nota','2026-06-19 13:26:44','2026-06-19 13:26:44',NULL,0,0,0),(78,2,1,1,'2026-06-20','12:00:00',15.00,30,'no_presentado','Test no presentado','2026-06-19 13:26:44','2026-06-19 13:26:44',NULL,0,0,0),(79,2,1,1,'2026-06-20','12:30:00',15.00,30,'completada','Test nota','2026-06-19 13:27:11','2026-06-19 13:27:11',NULL,0,0,0),(81,2,1,1,'2026-06-20','13:00:00',15.00,30,'no_presentado','Test no presentado','2026-06-19 13:27:11','2026-06-19 13:27:11',NULL,0,0,0),(82,2,1,1,'2026-06-20','13:30:00',15.00,30,'completada','Test nota','2026-06-19 13:27:34','2026-06-19 13:27:34',NULL,0,0,0),(83,2,1,1,'2026-06-22','10:00:00',15.00,30,'completada','Test nota','2026-06-19 13:30:41','2026-06-19 13:30:41',NULL,0,0,0),(84,2,1,1,'2026-06-29','10:00:00',15.00,30,'cancelada','Test cancelar','2026-06-19 13:30:41','2026-06-19 13:30:41','Cliente cancelo',0,0,0),(85,2,1,1,'2026-07-06','10:00:00',15.00,30,'no_presentado','Test no presentado','2026-06-19 13:30:41','2026-06-19 13:30:41',NULL,0,0,0),(86,5,1,1,'2026-06-19','15:30:00',14.00,30,'completada',NULL,'2026-06-19 14:52:39','2026-06-19 17:59:46',NULL,0,1,0),(87,5,1,1,'2026-06-19','16:00:00',14.00,30,'completada',NULL,'2026-06-19 15:02:40','2026-06-19 17:59:48',NULL,0,1,1),(88,5,1,1,'2026-06-19','18:30:00',14.00,30,'confirmada',NULL,'2026-06-19 18:09:11','2026-06-19 18:20:16',NULL,0,1,0);
/*!40000 ALTER TABLE `reservas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reset_tokens`
--

DROP TABLE IF EXISTS `reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reset_tokens`
--

LOCK TABLES `reset_tokens` WRITE;
/*!40000 ALTER TABLE `reset_tokens` DISABLE KEYS */;
INSERT INTO `reset_tokens` VALUES (1,'hassan@barberlah.com','dff00993b27d920a4484c4171e095c910a6f9fe9477e4de320293e14bc09e56e','2026-06-15 17:03:10',0,'2026-06-15 16:03:10'),(2,'hassan@barberlah.com','b9345e543cb9ee410c568a535096994d9a14fb1a65ab3ea9877935d2df75ffa5','2026-06-15 17:03:25',0,'2026-06-15 16:03:25'),(3,'hassan@barberlah.com','c19355267511f20d10365ff1e15318409cddb95b86937f79553c1b5f1a8b1501','2026-06-15 17:05:00',1,'2026-06-15 16:05:00'),(4,'hassan@barberlah.com','65f00cb527a91fb9a9be818cc0aa5e34b7c7c85aba91d2899404098a62d72d99','2026-06-15 17:06:07',1,'2026-06-15 16:06:07'),(5,'24guille08@gmail.com','895bd8e00682b28c5c14f84e0451c8f4d3420f5a472e455314db18a6e8b67a7c','2026-06-16 21:54:06',0,'2026-06-16 20:54:06'),(6,'24guille08@gmail.com','d5ea85e0a2a6bb5994b3e5adc78ff9f0d6aba1847db699ce3b306242c40edf2b','2026-06-16 21:57:07',0,'2026-06-16 20:57:07'),(7,'24guille08@gmail.com','4dd417905fca1e329cb7de69ddf9478573f8ccdcbd1eb0c0b2f21cb00e9488df','2026-06-16 21:59:29',1,'2026-06-16 20:59:29');
/*!40000 ALTER TABLE `reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `servicios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(6,2) NOT NULL,
  `duracion_min` int(11) NOT NULL DEFAULT 30,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios`
--

LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
INSERT INTO `servicios` VALUES (1,'Corte caballero',14.00,30,'Corte de pelo con acabado profesional',1),(2,'Corte + barba',20.00,30,'Corte completo con arreglo de barba',1),(3,'Corte niÃ±os',12.00,30,'Hasta 10 aÃ±os',1),(4,'Recorte de barba',7.00,30,'Perfilado y rebajado de barba',1),(5,'Perfilar cejas',5.00,15,'Limpieza y forma de cejas',1),(6,'DiseÃ±o',5.00,25,'Dibujos y lÃ­neas personalizadas',1),(7,'Guillermo AngÃ¡s',11.00,29,'teg',0),(8,'probado',11.00,15,'wfes',0),(9,'probado1',12.00,11,'fegse',0);
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;


CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `puntos_fidelidad` int(11) NOT NULL DEFAULT 0,
  `rol` varchar(10) NOT NULL DEFAULT 'cliente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `nota_interna` text DEFAULT NULL,
  `password_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_google_id` (`google_id`),
  KEY `idx_usuarios_rol` (`rol`),
  KEY `idx_usuarios_rol_created` (`rol`,`created_at`),
  KEY `idx_usuarios_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (2,NULL,'Carlos LÃ³pez','carlos@email.com','$2y$10$A3dbVUjLuZpcocTwHeouFe/s9rnW3e7aRmpGCTNAIIdU69AnpJ40C',NULL,'600111222',0,'cliente',1,'2026-05-08 19:51:56',NULL,'2026-06-15 14:02:52'),(3,'10293847566574839201','Elena G.','elena@gmail.com',NULL,'https://lh3.googleusercontent.com/a/foto_elena','600333444',3,'cliente',1,'2026-05-08 19:51:56',NULL,NULL),(4,NULL,'Marcos Ruiz','marcos@email.com','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutLkGB2',NULL,'611222333',0,'cliente',1,'2026-05-08 19:51:56',NULL,'2026-06-15 14:02:52'),(5,'113939390395279701054','Guille','24guille08@gmail.com','$2y$12$Lw3j8QspP.aAiq5aUL9.j.B1Tx9i1e/bSuGf4QCG./XnVtnRV5sX6','https://lh3.googleusercontent.com/a/ACg8ocI7qBnRr_zpuJDN0b5Kk00I58tW0QuQD3zQkA0YGSaRaJ9e27IHLA=s96-c','635479423',1,'cliente',1,'2026-05-12 21:51:21',NULL,'2026-06-16 21:01:33'),(6,'108826462427204473822','GUILLERMO ANGÃS HERRERO','gangash@campusdigitalfp.com','$2y$12$I5VOxVa621M5QjZ8J5JJ4e2W1u3kZ2Qbe9.sii6hzuxVlnfRi2AQu','https://lh3.googleusercontent.com/a/ACg8ocLhQzIvEU9CH5ajKsNrQ7fQ4SEGo5AwILdJiFIW70trB7ia3g=s96-c','635479423',0,'cliente',1,'2026-05-19 13:24:32','Viene siempre antes','2026-06-15 14:02:52'),(9,NULL,'Hassan','hassan@barberlah.com','$2y$10$RpRwNplAcmORh8UnEga8a.nni4VI.16jqtb5lWbwJs2t5hX7EtPFa',NULL,'600000000',2,'admin',1,'2026-05-25 15:03:51',NULL,'2026-06-19 13:21:54'),(11,'108052105855213400487','gxille angas','gxiillee@gmail.com',NULL,'https://lh3.googleusercontent.com/a/ACg8ocKo5x3Q2-rm_Gp1357QDNGXTQciMv72K4GCpHL4iGW_ManTvA=s96-c',NULL,0,'cliente',1,'2026-05-26 19:34:47',NULL,NULL),(12,NULL,'GUILLERMO','2897437832@gmail.com','$2y$10$heraCmGnoXzTwACQ57lEfOOYm3oNA3HO1AKG6Qvnu2saObEyrqs/u',NULL,'626328773t',0,'cliente',1,'2026-06-03 18:45:15',NULL,'2026-06-15 14:02:52');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-19 20:14:21

