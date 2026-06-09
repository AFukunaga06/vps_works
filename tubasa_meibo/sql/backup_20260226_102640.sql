-- MySQL dump 10.13  Distrib 8.4.7, for Linux (x86_64)
--
-- Host: localhost    Database: tubasa_meibo
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
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `attend_date` date NOT NULL,
  `status` enum('出席','欠席','遅刻','早退') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '出席',
  `note` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_date` (`member_id`,`attend_date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (1,30,'2026-02-26','出席','','2026-02-26 10:20:20'),(2,23,'2026-02-26','欠席','','2026-02-26 10:20:20'),(3,39,'2026-02-26','出席','','2026-02-26 10:20:20'),(4,40,'2026-02-26','欠席','','2026-02-26 10:20:20'),(5,35,'2026-02-26','欠席','','2026-02-26 10:20:20'),(6,33,'2026-02-26','出席','','2026-02-26 10:20:20'),(7,24,'2026-02-26','出席','','2026-02-26 10:20:20'),(8,38,'2026-02-26','出席','','2026-02-26 10:20:20'),(9,28,'2026-02-26','欠席','','2026-02-26 10:20:20'),(10,26,'2026-02-26','出席','','2026-02-26 10:20:20'),(11,31,'2026-02-26','出席','','2026-02-26 10:20:20'),(12,32,'2026-02-26','出席','','2026-02-26 10:20:20'),(13,36,'2026-02-26','出席','','2026-02-26 10:20:20'),(14,25,'2026-02-26','出席','','2026-02-26 10:20:20'),(15,34,'2026-02-26','出席','','2026-02-26 10:20:20'),(16,22,'2026-02-26','出席','','2026-02-26 10:20:20'),(17,37,'2026-02-26','出席','','2026-02-26 10:20:20'),(18,29,'2026-02-26','出席','','2026-02-26 10:20:20'),(19,27,'2026-02-26','出席','','2026-02-26 10:20:20'),(20,21,'2026-02-26','出席','','2026-02-26 10:20:20'),(21,19,'2026-02-26','欠席','','2026-02-26 10:20:20'),(22,5,'2026-02-26','出席','','2026-02-26 10:20:20'),(23,15,'2026-02-26','出席','','2026-02-26 10:20:20'),(24,9,'2026-02-26','出席','','2026-02-26 10:20:20'),(25,8,'2026-02-26','出席','','2026-02-26 10:20:20'),(26,20,'2026-02-26','欠席','','2026-02-26 10:20:20'),(27,3,'2026-02-26','出席','','2026-02-26 10:20:20'),(28,2,'2026-02-26','出席','','2026-02-26 10:20:20'),(29,11,'2026-02-26','出席','','2026-02-26 10:20:20'),(30,1,'2026-02-26','欠席','','2026-02-26 10:20:20'),(31,16,'2026-02-26','出席','','2026-02-26 10:20:20'),(32,7,'2026-02-26','出席','','2026-02-26 10:20:20'),(33,14,'2026-02-26','出席','','2026-02-26 10:20:20'),(34,17,'2026-02-26','欠席','','2026-02-26 10:20:20'),(35,13,'2026-02-26','出席','','2026-02-26 10:20:20'),(36,18,'2026-02-26','出席','','2026-02-26 10:20:20'),(37,12,'2026-02-26','出席','','2026-02-26 10:20:20'),(38,4,'2026-02-26','欠席','','2026-02-26 10:20:20'),(39,10,'2026-02-26','出席','','2026-02-26 10:20:20'),(40,6,'2026-02-26','出席','','2026-02-26 10:20:20');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kana` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,'田中太郎','タナカタロウ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(2,'鈴木一郎','スズキイチロウ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(3,'佐藤健二','サトウケンジ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(4,'山田雄介','ヤマダユウスケ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(5,'伊藤誠','イトウマコト','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(6,'渡辺浩','ワタナベヒロシ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(7,'中村翔','ナカムラショウ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(8,'小林大輝','コバヤシダイキ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(9,'加藤龍一','カトウリュウイチ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(10,'吉田明','ヨシダアキラ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(11,'高橋直樹','タカハシナオキ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(12,'山崎拓也','ヤマザキタクヤ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(13,'松田浩二','マツダコウジ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(14,'藤原勇','フジワライサム','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(15,'岡田博','オカダヒロシ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(16,'中島俊介','ナカジマシュンスケ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(17,'前田康太','マエダコウタ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(18,'森田隆','モリタタカシ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(19,'石川智也','イシカワトモヤ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(20,'坂本賢一','サカモトケンイチ','男性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(21,'山本花子','ヤマモトハナコ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(22,'松本美咲','マツモトミサキ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(23,'井上さくら','イノウエサクラ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(24,'木村愛','キムラアイ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(25,'林奈々','ハヤシナナ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(26,'清水由美','シミズユミ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(27,'山口真理','ヤマグチマリ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(28,'斎藤恵','サイトウメグミ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(29,'森優子','モリユウコ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(30,'池田千尋','イケダチヒロ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(31,'西村明日香','ニシムラアスカ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(32,'橋本麻衣','ハシモトマイ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(33,'小川理恵','オガワリエ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(34,'藤井美穂','フジイミホ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(35,'岡本春菜','オカモトハルナ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(36,'長谷川彩','ハセガワアヤ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(37,'村田瑞希','ムラタミズキ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(38,'近藤詩織','コンドウシオリ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(39,'上田莉奈','ウエダリナ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31'),(40,'内田玲奈','ウチダレナ','女性',1,'2026-02-26 10:10:31','2026-02-26 10:10:31');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `v_daily`
--

DROP TABLE IF EXISTS `v_daily`;
/*!50001 DROP VIEW IF EXISTS `v_daily`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_daily` AS SELECT 
 1 AS `attend_date`,
 1 AS `total`,
 1 AS `present`,
 1 AS `absent`,
 1 AS `late`,
 1 AS `early_leave`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_monthly`
--

DROP TABLE IF EXISTS `v_monthly`;
/*!50001 DROP VIEW IF EXISTS `v_monthly`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_monthly` AS SELECT 
 1 AS `ym`,
 1 AS `total`,
 1 AS `present`,
 1 AS `absent`,
 1 AS `late`,
 1 AS `early_leave`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_yearly`
--

DROP TABLE IF EXISTS `v_yearly`;
/*!50001 DROP VIEW IF EXISTS `v_yearly`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_yearly` AS SELECT 
 1 AS `yr`,
 1 AS `total`,
 1 AS `present`,
 1 AS `absent`,
 1 AS `late`,
 1 AS `early_leave`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_daily`
--

/*!50001 DROP VIEW IF EXISTS `v_daily`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_daily` AS select `attendance`.`attend_date` AS `attend_date`,count(0) AS `total`,sum((`attendance`.`status` = '出席')) AS `present`,sum((`attendance`.`status` = '欠席')) AS `absent`,sum((`attendance`.`status` = '遅刻')) AS `late`,sum((`attendance`.`status` = '早退')) AS `early_leave` from `attendance` group by `attendance`.`attend_date` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_monthly`
--

/*!50001 DROP VIEW IF EXISTS `v_monthly`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_monthly` AS select date_format(`attendance`.`attend_date`,'%Y-%m') AS `ym`,count(0) AS `total`,sum((`attendance`.`status` = '出席')) AS `present`,sum((`attendance`.`status` = '欠席')) AS `absent`,sum((`attendance`.`status` = '遅刻')) AS `late`,sum((`attendance`.`status` = '早退')) AS `early_leave` from `attendance` group by `ym` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_yearly`
--

/*!50001 DROP VIEW IF EXISTS `v_yearly`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_yearly` AS select year(`attendance`.`attend_date`) AS `yr`,count(0) AS `total`,sum((`attendance`.`status` = '出席')) AS `present`,sum((`attendance`.`status` = '欠席')) AS `absent`,sum((`attendance`.`status` = '遅刻')) AS `late`,sum((`attendance`.`status` = '早退')) AS `early_leave` from `attendance` group by `yr` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-26 10:26:40
