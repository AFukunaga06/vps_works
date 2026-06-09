-- MySQL dump 10.13  Distrib 8.4.7, for Linux (x86_64)
--
-- Host: localhost    Database: church_attendance
-- ------------------------------------------------------
-- Server version	8.4.7-0ubuntu0.25.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `attendance_date` date NOT NULL,
  `roster_id` int NOT NULL,
  `is_present` tinyint(1) DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date_roster` (`attendance_date`,`roster_id`),
  KEY `roster_id` (`roster_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`roster_id`) REFERENCES `roster` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,'2026-02-27',1,0,NULL,'2026-02-27 10:33:31'),(2,'2026-02-27',2,0,NULL,'2026-02-27 10:33:31'),(3,'2026-02-27',3,0,NULL,'2026-02-27 10:33:31'),(4,'2026-02-27',4,0,NULL,'2026-02-27 10:33:31'),(5,'2026-02-27',5,0,NULL,'2026-02-27 10:33:31'),(6,'2026-02-27',6,0,NULL,'2026-02-27 10:33:31'),(7,'2026-02-27',7,0,NULL,'2026-02-27 10:33:31'),(8,'2026-02-27',8,0,NULL,'2026-02-27 10:33:31'),(9,'2026-02-27',9,0,NULL,'2026-02-27 10:33:31'),(10,'2026-02-27',10,0,NULL,'2026-02-27 10:33:31'),(11,'2026-02-27',11,0,NULL,'2026-02-27 10:33:31'),(12,'2026-02-27',12,0,NULL,'2026-02-27 10:33:31'),(13,'2026-02-27',13,0,NULL,'2026-02-27 10:33:31'),(14,'2026-02-27',14,0,NULL,'2026-02-27 10:33:31'),(15,'2026-02-27',15,0,NULL,'2026-02-27 10:33:31'),(16,'2026-02-27',16,0,NULL,'2026-02-27 10:33:31'),(17,'2026-02-27',17,0,NULL,'2026-02-27 10:33:31'),(18,'2026-02-27',18,0,NULL,'2026-02-27 10:33:31'),(19,'2026-02-27',19,0,NULL,'2026-02-27 10:33:31'),(20,'2026-02-27',20,0,NULL,'2026-02-27 10:33:31'),(21,'2026-02-27',21,0,NULL,'2026-02-27 10:33:31'),(22,'2026-02-27',22,0,NULL,'2026-02-27 10:33:31'),(23,'2026-02-27',23,0,NULL,'2026-02-27 10:33:31'),(24,'2026-02-27',24,0,NULL,'2026-02-27 10:33:31'),(25,'2026-02-27',25,0,NULL,'2026-02-27 10:33:31'),(26,'2026-02-27',26,0,NULL,'2026-02-27 10:33:31'),(27,'2026-02-27',27,0,NULL,'2026-02-27 10:33:31'),(28,'2026-02-27',28,0,NULL,'2026-02-27 10:33:31'),(29,'2026-02-27',29,0,NULL,'2026-02-27 10:33:31'),(30,'2026-02-27',30,0,NULL,'2026-02-27 10:33:31'),(31,'2026-02-27',31,0,NULL,'2026-02-27 10:33:31'),(32,'2026-02-27',32,0,NULL,'2026-02-27 10:33:31'),(33,'2026-02-27',33,0,NULL,'2026-02-27 10:33:31'),(34,'2026-02-27',34,0,NULL,'2026-02-27 10:33:31'),(35,'2026-02-27',35,0,NULL,'2026-02-27 10:33:31'),(36,'2026-02-27',36,0,NULL,'2026-02-27 10:33:31'),(37,'2026-02-27',37,0,NULL,'2026-02-27 10:33:31'),(38,'2026-02-27',38,0,NULL,'2026-02-27 10:33:31'),(39,'2026-02-27',39,0,NULL,'2026-02-27 10:33:31'),(40,'2026-02-27',40,0,NULL,'2026-02-27 10:33:31'),(241,'2026-03-08',1,0,NULL,'2026-03-01 17:47:15'),(242,'2026-03-08',2,0,NULL,'2026-03-01 17:47:15'),(243,'2026-03-08',3,1,NULL,'2026-03-01 17:47:15'),(244,'2026-03-08',4,1,NULL,'2026-03-01 17:47:15'),(245,'2026-03-08',5,1,NULL,'2026-03-01 17:47:15'),(246,'2026-03-08',6,0,NULL,'2026-03-01 17:47:15'),(247,'2026-03-08',7,0,NULL,'2026-03-01 17:47:15'),(248,'2026-03-08',8,0,NULL,'2026-03-01 17:47:15'),(249,'2026-03-08',9,0,NULL,'2026-03-01 17:47:15'),(250,'2026-03-08',10,0,NULL,'2026-03-01 17:47:15'),(251,'2026-03-08',11,0,NULL,'2026-03-01 17:47:15'),(252,'2026-03-08',12,0,NULL,'2026-03-01 17:47:15'),(253,'2026-03-08',13,0,NULL,'2026-03-01 17:47:15'),(254,'2026-03-08',14,0,NULL,'2026-03-01 17:47:15'),(255,'2026-03-08',15,0,NULL,'2026-03-01 17:47:15'),(256,'2026-03-08',16,0,NULL,'2026-03-01 17:47:15'),(257,'2026-03-08',17,0,NULL,'2026-03-01 17:47:15'),(258,'2026-03-08',18,0,NULL,'2026-03-01 17:47:15'),(259,'2026-03-08',19,0,NULL,'2026-03-01 17:47:15'),(260,'2026-03-08',20,0,NULL,'2026-03-01 17:47:15'),(261,'2026-03-08',21,0,NULL,'2026-03-01 17:47:15'),(262,'2026-03-08',22,0,NULL,'2026-03-01 17:47:15'),(263,'2026-03-08',23,0,NULL,'2026-03-01 17:47:15'),(264,'2026-03-08',24,0,NULL,'2026-03-01 17:47:15'),(265,'2026-03-08',25,1,NULL,'2026-03-01 17:47:15'),(266,'2026-03-08',26,1,NULL,'2026-03-01 17:47:15'),(267,'2026-03-08',27,1,NULL,'2026-03-01 17:47:15'),(268,'2026-03-08',28,0,NULL,'2026-03-01 17:47:15'),(269,'2026-03-08',29,0,NULL,'2026-03-01 17:47:15'),(270,'2026-03-08',30,0,NULL,'2026-03-01 17:47:15'),(271,'2026-03-08',31,0,NULL,'2026-03-01 17:47:15'),(272,'2026-03-08',32,0,NULL,'2026-03-01 17:47:15'),(273,'2026-03-08',33,0,NULL,'2026-03-01 17:47:15'),(274,'2026-03-08',34,0,NULL,'2026-03-01 17:47:15'),(275,'2026-03-08',35,0,NULL,'2026-03-01 17:47:15'),(276,'2026-03-08',36,0,NULL,'2026-03-01 17:47:15'),(277,'2026-03-08',37,0,NULL,'2026-03-01 17:47:15'),(278,'2026-03-08',38,0,NULL,'2026-03-01 17:47:15'),(279,'2026-03-08',39,0,NULL,'2026-03-01 17:47:15'),(280,'2026-03-08',40,0,NULL,'2026-03-01 17:47:15'),(321,'2026-03-01',1,1,NULL,'2026-03-01 23:00:05'),(322,'2026-03-01',2,1,NULL,'2026-03-01 23:00:05'),(323,'2026-03-01',3,0,NULL,'2026-03-01 23:00:05'),(324,'2026-03-01',4,1,NULL,'2026-03-01 23:00:05'),(325,'2026-03-01',5,0,NULL,'2026-03-01 23:00:05'),(326,'2026-03-01',6,0,NULL,'2026-03-01 23:00:05'),(327,'2026-03-01',7,0,NULL,'2026-03-01 23:00:05'),(328,'2026-03-01',8,0,NULL,'2026-03-01 23:00:05'),(329,'2026-03-01',9,0,NULL,'2026-03-01 23:00:05'),(330,'2026-03-01',10,0,NULL,'2026-03-01 23:00:05'),(331,'2026-03-01',11,0,NULL,'2026-03-01 23:00:05'),(332,'2026-03-01',12,0,NULL,'2026-03-01 23:00:05'),(333,'2026-03-01',13,0,NULL,'2026-03-01 23:00:05'),(334,'2026-03-01',14,0,NULL,'2026-03-01 23:00:05'),(335,'2026-03-01',15,0,NULL,'2026-03-01 23:00:05'),(336,'2026-03-01',16,0,NULL,'2026-03-01 23:00:05'),(337,'2026-03-01',17,0,NULL,'2026-03-01 23:00:05'),(338,'2026-03-01',18,0,NULL,'2026-03-01 23:00:05'),(339,'2026-03-01',19,0,NULL,'2026-03-01 23:00:05'),(340,'2026-03-01',20,0,NULL,'2026-03-01 23:00:05'),(341,'2026-03-01',21,0,NULL,'2026-03-01 23:00:05'),(342,'2026-03-01',22,1,NULL,'2026-03-01 23:00:05'),(343,'2026-03-01',23,1,NULL,'2026-03-01 23:00:05'),(344,'2026-03-01',24,1,NULL,'2026-03-01 23:00:05'),(345,'2026-03-01',25,0,NULL,'2026-03-01 23:00:05'),(346,'2026-03-01',26,0,NULL,'2026-03-01 23:00:05'),(347,'2026-03-01',27,0,NULL,'2026-03-01 23:00:05'),(348,'2026-03-01',28,0,NULL,'2026-03-01 23:00:05'),(349,'2026-03-01',29,0,NULL,'2026-03-01 23:00:05'),(350,'2026-03-01',30,0,NULL,'2026-03-01 23:00:05'),(351,'2026-03-01',31,0,NULL,'2026-03-01 23:00:05'),(352,'2026-03-01',32,0,NULL,'2026-03-01 23:00:05'),(353,'2026-03-01',33,0,NULL,'2026-03-01 23:00:05'),(354,'2026-03-01',34,0,NULL,'2026-03-01 23:00:05'),(355,'2026-03-01',35,0,NULL,'2026-03-01 23:00:05'),(356,'2026-03-01',36,0,NULL,'2026-03-01 23:00:05'),(357,'2026-03-01',37,0,NULL,'2026-03-01 23:00:05'),(358,'2026-03-01',38,0,NULL,'2026-03-01 23:00:05'),(359,'2026-03-01',39,0,NULL,'2026-03-01 23:00:05'),(360,'2026-03-01',40,0,NULL,'2026-03-01 23:00:05');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `confirmed_dates`
--

DROP TABLE IF EXISTS `confirmed_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `confirmed_dates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `attend_date` date NOT NULL,
  `confirmed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date` (`attend_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `confirmed_dates`
--

LOCK TABLES `confirmed_dates` WRITE;
/*!40000 ALTER TABLE `confirmed_dates` DISABLE KEYS */;
INSERT INTO `confirmed_dates` VALUES (1,'2026-02-27','2026-02-27 10:33:31'),(7,'2026-03-08','2026-03-01 17:47:15'),(8,'2026-03-01','2026-03-01 23:00:05');
/*!40000 ALTER TABLE `confirmed_dates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `csrf_tokens`
--

DROP TABLE IF EXISTS `csrf_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `csrf_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `csrf_tokens`
--

LOCK TABLES `csrf_tokens` WRITE;
/*!40000 ALTER TABLE `csrf_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `csrf_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL,
  `gender` enum('male','female') NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_members` (`year`,`gender`,`name`),
  KEY `idx_members` (`year`,`gender`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roster`
--

DROP TABLE IF EXISTS `roster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roster` (
  `id` int NOT NULL AUTO_INCREMENT,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_newcomer` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roster`
--

LOCK TABLES `roster` WRITE;
/*!40000 ALTER TABLE `roster` DISABLE KEYS */;
INSERT INTO `roster` VALUES (1,'male',1,'石川智也',0,'2026-02-26 22:18:08'),(2,'male',2,'伊藤誠',0,'2026-02-26 22:18:08'),(3,'male',3,'岡田博',0,'2026-02-26 22:18:08'),(4,'male',4,'加藤龍一',0,'2026-02-26 22:18:08'),(5,'male',5,'小林大輝',0,'2026-02-26 22:18:08'),(6,'male',6,'坂本賢一',0,'2026-02-26 22:18:08'),(7,'male',7,'佐藤健二',0,'2026-02-26 22:18:08'),(8,'male',8,'鈴木一郎',0,'2026-02-26 22:18:08'),(9,'male',9,'高橋直樹',0,'2026-02-26 22:18:08'),(10,'male',10,'田中太郎',0,'2026-02-26 22:18:08'),(11,'male',11,'中島俊介',0,'2026-02-26 22:18:08'),(12,'male',12,'中村翔',0,'2026-02-26 22:18:08'),(13,'male',13,'藤原勇',0,'2026-02-26 22:18:08'),(14,'male',14,'前田康太',0,'2026-02-26 22:18:08'),(15,'male',15,'松田浩二',0,'2026-02-26 22:18:08'),(16,'male',16,'森田隆',0,'2026-02-26 22:18:08'),(17,'male',17,'山崎拓也',0,'2026-02-26 22:18:08'),(18,'male',18,'山田雄介',0,'2026-02-26 22:18:08'),(19,'male',19,'吉田明',0,'2026-02-26 22:18:08'),(20,'male',20,'渡辺浩',0,'2026-02-26 22:18:08'),(21,'female',1,'池田千尋',0,'2026-02-26 22:18:08'),(22,'female',2,'井上さくら',0,'2026-02-26 22:18:08'),(23,'female',3,'上田莉奈',0,'2026-02-26 22:18:08'),(24,'female',4,'内田玲奈',0,'2026-02-26 22:18:08'),(25,'female',5,'岡本春菜',0,'2026-02-26 22:18:08'),(26,'female',6,'小川理恵',0,'2026-02-26 22:18:08'),(27,'female',7,'木村愛',0,'2026-02-26 22:18:08'),(28,'female',8,'近藤詩織',0,'2026-02-26 22:18:08'),(29,'female',9,'斎藤恵',0,'2026-02-26 22:18:08'),(30,'female',10,'清水由美',0,'2026-02-26 22:18:08'),(31,'female',11,'西村明日香',0,'2026-02-26 22:18:08'),(32,'female',12,'橋本麻衣',0,'2026-02-26 22:18:08'),(33,'female',13,'長谷川彩',0,'2026-02-26 22:18:08'),(34,'female',14,'林奈々',0,'2026-02-26 22:18:08'),(35,'female',15,'藤井美穂',0,'2026-02-26 22:18:08'),(36,'female',16,'松本美咲',0,'2026-02-26 22:18:08'),(37,'female',17,'村田瑞希',0,'2026-02-26 22:18:08'),(38,'female',18,'森優子',0,'2026-02-26 22:18:08'),(39,'female',19,'山口真理',0,'2026-02-26 22:18:08'),(40,'female',20,'山本花子',0,'2026-02-26 22:18:08');
/*!40000 ALTER TABLE `roster` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `failed_attempts` int DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','REDACTED_PASSWORD_HASH','admin',0,NULL,'2026-02-11 22:59:03','2026-02-11 23:24:24');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workdays`
--

DROP TABLE IF EXISTS `workdays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workdays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL,
  `date` date NOT NULL,
  `is_workday` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_workdays` (`year`,`date`),
  KEY `idx_workdays` (`year`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workdays`
--

LOCK TABLES `workdays` WRITE;
/*!40000 ALTER TABLE `workdays` DISABLE KEYS */;
/*!40000 ALTER TABLE `workdays` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-01 23:31:21
