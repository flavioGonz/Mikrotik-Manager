-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for mikrotik_manager
CREATE DATABASE IF NOT EXISTS `mikrotik_manager` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `mikrotik_manager`;

-- Dumping structure for table mikrotik_manager.routers
CREATE TABLE IF NOT EXISTS `routers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `api_user` varchar(50) DEFAULT NULL,
  `api_password` varchar(50) DEFAULT NULL,
  `lat` decimal(10,8) NOT NULL,
  `lng` decimal(11,8) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `version` varchar(20) DEFAULT NULL,
  `uptime` varchar(50) DEFAULT NULL,
  `cpu_load` int(3) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'PENDING',
  `last_checked` datetime DEFAULT NULL,
  `api_port` int(11) DEFAULT 8728,
  `api_ssl` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mikrotik_manager.routers: ~3 rows (approximately)
REPLACE INTO `routers` (`id`, `name`, `ip_address`, `api_user`, `api_password`, `lat`, `lng`, `model`, `version`, `uptime`, `cpu_load`, `status`, `last_checked`, `api_port`, `api_ssl`) VALUES
	(1, 'Seguridad Diferente', 'de350d823c03.sn.mynetname.net', 'api', 'api*2011', -34.87917166, -56.08245850, 'RB951G-2HnD', '7.19.4 (stable)', '1h52m15s', 4, 'OK', '2025-08-28 21:05:03', 8333, 0),
	(5, 'Flavio Casa1', '192.168.99.1', 'api', 'api*2011', -34.81669245, -55.97539008, NULL, NULL, NULL, NULL, 'OK', '2025-08-28 21:05:05', 8333, 0),
	(8, 'Hotel Altos del Arapey', 'd8560f8cc6a4.sn.mynetname.net', 'api', 'api*2011', -31.24333366, -57.07946777, 'RB1100Dx4', '7.16 (stable)', '6w12h49m39s', 13, 'OK', '2025-08-28 21:05:06', 8333, 0),
	(14, 'Flavio Casa', '2f7a0121f1af.sn.mynetname.net', 'api', 'api*2011', -34.81665722, -55.97549200, NULL, NULL, NULL, NULL, 'OK', '2025-08-28 21:05:35', 8333, 0),
	(15, 'Laguna blanca', 'cc210c93b0ab.sn.mynetname.net', 'api', 'api*2011', -34.90698411, -54.83862698, 'RB750Gr3', '7.19.4 (stable)', '3w3d1h16m12s', 2, 'OK', '2025-08-28 21:05:35', 8333, 0),
	(16, 'Yoffe Despachantes', '578204f18f4e.sn.mynetname.net', 'api', 'api*2011', -34.90482405, -56.20812535, 'RB2011UiAS', '7.19.4 (stable)', '2h9m44s', 7, 'OK', '2025-08-28 21:05:36', 8333, 0),
	(17, 'Abedil', '179.27.94.154', 'api', 'api*2011', -34.35137290, -57.17817307, 'L009UiGS-2HaxD', '7.19.4 (stable)', '2h59m19s', 6, 'OK', '2025-08-28 21:05:36', 8333, 0);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
