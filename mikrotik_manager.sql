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
  `status` varchar(10) NOT NULL DEFAULT 'PENDING',
  `last_checked` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table mikrotik_manager.routers: ~0 rows (approximately)
REPLACE INTO `routers` (`id`, `name`, `ip_address`, `api_user`, `api_password`, `lat`, `lng`, `status`, `last_checked`) VALUES
	(1, 'Seguridad Diferente', '2f7a0121f1af.sn.mynetname.net', NULL, NULL, -34.88353709, -56.07533455, 'OK', '2025-08-28 12:27:33'),
	(3, 'Cliente C - Casa', '1.1.1.1', NULL, NULL, -34.89592802, -56.17052078, 'OK', '2025-08-28 12:24:06'),
	(5, 'Flavio Casa', '192.168.99.1', 'api', 'api*2011', -34.81665722, -55.97549200, 'FAIL', '2025-08-28 12:24:10');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
