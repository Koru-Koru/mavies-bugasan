-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mavies_bugasan
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `admin_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_name` varchar(90) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varbinary(16) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Owner','owner@maviesbugasan.ph','$2y$12$Fco1AyS4JqwHWGwTfhvwiOdQrItmKKFVxeEZ4/aN0NBilmdUKxbze',1,0,NULL,'2026-10-11 00:24:15','\0\0','2026-10-10 01:18:45','2026-10-11 00:24:15');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_tokens`
--

DROP TABLE IF EXISTS `auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_tokens` (
  `token_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject` enum('customer','admin') NOT NULL,
  `subject_id` int(10) unsigned NOT NULL,
  `purpose` enum('verify_email','password_reset') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `uq_auth_tokens_hash` (`token_hash`),
  KEY `idx_auth_tokens_lookup` (`subject`,`subject_id`,`purpose`,`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_tokens`
--

LOCK TABLES `auth_tokens` WRITE;
/*!40000 ALTER TABLE `auth_tokens` DISABLE KEYS */;
INSERT INTO `auth_tokens` VALUES (1,'customer',3,'verify_email','ea7ed6bdba7fede3f765ebc701a0a94f80c409345fa2e7be7b4dbd9996022c68','2026-10-11 00:34:09','2026-10-11 00:24:11','2026-10-11 00:24:09');
/*!40000 ALTER TABLE `auth_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(90) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `address_bldg` varchar(90) DEFAULT NULL,
  `address_street` varchar(120) DEFAULT NULL,
  `address_brgy` varchar(120) DEFAULT NULL,
  `address_city` varchar(90) NOT NULL DEFAULT '',
  `address_notes` varchar(200) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_walk_in` tinyint(1) NOT NULL DEFAULT 0,
  `customer_type` enum('New','Returning') NOT NULL DEFAULT 'New',
  `terms_accepted_at` datetime DEFAULT NULL,
  `blacklisted` tinyint(1) NOT NULL DEFAULT 0,
  `blacklist_reason` varchar(255) DEFAULT NULL,
  `blacklisted_at` datetime DEFAULT NULL,
  `blacklisted_by` int(10) unsigned DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uq_customers_phone` (`phone`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_name` (`last_name`,`first_name`),
  KEY `idx_customers_active` (`is_active`,`blacklisted`),
  KEY `fk_customers_blacklisted_by` (`blacklisted_by`),
  CONSTRAINT `fk_customers_blacklisted_by` FOREIGN KEY (`blacklisted_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `chk_customers_blacklisted` CHECK (`blacklisted` = 0 or `blacklist_reason` is not null and `blacklisted_at` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'Live','User','09171234567','live@example.com','$2y$12$MKPQg7srxzkZSqPrg4HOF.6RByv3L404OzClURzf0NoIsipB3BXTK',NULL,NULL,NULL,'',NULL,0,NULL,1,0,'New','2026-10-10 01:19:35',0,NULL,NULL,NULL,'2026-10-11 00:30:09','2026-10-10 01:19:35','2026-10-11 00:30:09'),(2,'Live2','User','09182223333','live2@example.com','$2y$12$Xx1PIZScO/e22XxqAIU8x.nZLJpvWRNjeVe8ZW4oUsT13lfar5lW6',NULL,NULL,NULL,'',NULL,0,NULL,1,0,'New','2026-10-10 01:25:07',0,NULL,NULL,NULL,'2026-10-10 10:41:20','2026-10-10 01:25:07','2026-10-11 00:09:20'),(3,'Live','Tester','09791649447','liveui1791649447@example.com','$2y$12$Xfay.PUfckUBi/yK7Te9l.KkJaM6ez96eFH64whmwtmzq0DoOEjz6',NULL,NULL,NULL,'Live City',NULL,1,'2026-10-11 00:24:11',1,0,'New','2026-10-11 00:24:09',0,NULL,NULL,NULL,'2026-10-11 00:24:12','2026-10-11 00:24:09','2026-10-11 00:24:16');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `damage_logs`
--

DROP TABLE IF EXISTS `damage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `damage_logs` (
  `damage_log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `damage_code` varchar(20) NOT NULL,
  `inventory_id` int(10) unsigned NOT NULL,
  `variety_name` varchar(80) NOT NULL,
  `damage_type` varchar(80) NOT NULL,
  `sacks` int(11) NOT NULL,
  `date_logged` date NOT NULL,
  `notes` text DEFAULT NULL,
  `logged_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`damage_log_id`),
  UNIQUE KEY `uq_damage_code` (`damage_code`),
  KEY `idx_damage_date` (`date_logged`),
  KEY `idx_damage_inventory` (`inventory_id`),
  KEY `fk_damage_admin` (`logged_by`),
  CONSTRAINT `fk_damage_admin` FOREIGN KEY (`logged_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_damage_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  CONSTRAINT `chk_damage_sacks` CHECK (`sacks` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `damage_logs`
--

LOCK TABLES `damage_logs` WRITE;
/*!40000 ALTER TABLE `damage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `damage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `inventory_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `category` enum('Local','Imported','Japanese','Thailand') NOT NULL DEFAULT 'Local',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `wholesale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uq_inventory_name` (`name`),
  KEY `idx_inventory_active` (`is_active`),
  CONSTRAINT `chk_inventory_stock` CHECK (`stock` >= 0),
  CONSTRAINT `chk_inventory_prices` CHECK (`unit_price` >= 0 and `wholesale_price` >= 0),
  CONSTRAINT `chk_inventory_sacks` CHECK (`stock` <= 100000)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES (1,'Coco Pandan','Imported',0.00,0.00,0,10,1,NULL,'2026-10-10 00:31:16','2026-10-10 00:31:16'),(2,'Sweet Hasmin','Imported',0.00,0.00,0,10,1,NULL,'2026-10-10 00:31:16','2026-10-10 00:31:16'),(3,'SP1 Dinorado','Imported',1380.00,1350.00,30,10,1,NULL,'2026-10-10 00:31:16','2026-10-11 00:28:34'),(4,'Ube Pandan','Imported',1330.00,1330.00,0,10,1,NULL,'2026-10-10 00:31:16','2026-10-10 00:31:16'),(5,'Coco Japonica','Japanese',1350.00,1320.00,15,10,1,NULL,'2026-10-10 00:31:16','2026-10-11 00:28:34'),(6,'Saporro Japanese Rice','Japanese',1350.00,1320.00,0,10,1,NULL,'2026-10-10 00:31:16','2026-10-10 00:31:16'),(7,'Sakura Japanese Rice','Japanese',1350.00,1320.00,25,10,1,NULL,'2026-10-10 00:31:16','2026-10-11 00:28:34'),(8,'Coco Thai (Alsa)','Thailand',0.00,0.00,0,10,1,NULL,'2026-10-10 00:31:17','2026-10-10 00:31:17'),(9,'Royal Sheep (Alsa)','Thailand',1185.00,1155.00,40,10,1,NULL,'2026-10-10 00:31:17','2026-10-11 00:28:34'),(10,'Magic Castle (Alsa)','Thailand',1220.00,1190.00,18,10,1,NULL,'2026-10-10 00:31:17','2026-10-11 00:28:34'),(11,'Planters (Alsa)','Thailand',1185.00,1155.00,12,10,1,NULL,'2026-10-10 00:31:17','2026-10-11 00:28:34'),(12,'MCL Denorado','Local',1380.00,1330.00,20,10,1,NULL,'2026-10-10 00:31:17','2026-10-11 00:28:34'),(13,'Boy Kanin','Local',1180.00,1180.00,38,10,1,NULL,'2026-10-10 00:31:17','2026-10-10 01:25:08'),(14,'Buko Pandan','Local',0.00,0.00,0,10,1,NULL,'2026-10-10 00:31:17','2026-10-10 00:31:17'),(15,'SP1 Angelica','Local',0.00,0.00,0,10,1,NULL,'2026-10-10 00:31:17','2026-10-10 00:31:17');
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2025_01_01_000001_create_core_tables',1),(2,'2025_01_01_000002_create_v_orders_view',1),(3,'2025_01_01_000003_backfill_payment_allocations',1),(4,'2025_01_01_000004_add_customer_type_and_terms',2),(5,'2025_01_01_000005_add_payment_method_to_orders',1),(6,'2025_01_01_000006_add_payment_proof_to_orders',1),(7,'2025_01_01_000007_add_courier_to_orders',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `order_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `inventory_id` int(10) unsigned NOT NULL,
  `variety_name` varchar(80) NOT NULL,
  `sacks` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_unit_price` decimal(10,2) DEFAULT NULL,
  `staff_note` varchar(255) DEFAULT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`sacks` * coalesce(`final_unit_price`,`unit_price`)) STORED,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_item_id`),
  KEY `idx_items_order` (`order_id`),
  KEY `idx_items_inventory` (`inventory_id`),
  CONSTRAINT `fk_items_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_items_sacks` CHECK (`sacks` > 0),
  CONSTRAINT `chk_items_sacks_max` CHECK (`sacks` <= 500),
  CONSTRAINT `chk_items_price` CHECK (`unit_price` >= 0),
  CONSTRAINT `chk_items_final_price` CHECK (`final_unit_price` is null or `final_unit_price` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,13,'Boy Kanin',2,1180.00,NULL,NULL,2360.00,'2026-10-10 01:25:08','2026-10-10 01:25:08');
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_status_history` (
  `history_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `idx_history_order` (`order_id`,`created_at`),
  KEY `fk_history_admin` (`changed_by`),
  CONSTRAINT `fk_history_admin` FOREIGN KEY (`changed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_history`
--

LOCK TABLES `order_status_history` WRITE;
/*!40000 ALTER TABLE `order_status_history` DISABLE KEYS */;
INSERT INTO `order_status_history` VALUES (1,1,NULL,'Pending',NULL,'Order placed','2026-10-10 01:25:08'),(2,1,'Pending','Confirmed',1,NULL,'2026-10-10 01:25:10'),(3,1,'Confirmed','Ready for Pickup',1,NULL,'2026-10-10 01:28:12'),(4,1,'Ready for Pickup','Delivered',1,NULL,'2026-10-10 01:28:13');
/*!40000 ALTER TABLE `order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) NOT NULL,
  `customer_id` int(10) unsigned NOT NULL,
  `fulfillment_method` enum('Pickup','In-house','Courier') NOT NULL DEFAULT 'Pickup',
  `payment_method` enum('Cash','GCash','Maya') DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `courier_name` varchar(80) DEFAULT NULL,
  `courier_link` varchar(500) DEFAULT NULL,
  `address_bldg` varchar(90) DEFAULT NULL,
  `address_street` varchar(120) DEFAULT NULL,
  `address_brgy` varchar(120) DEFAULT NULL,
  `address_city` varchar(90) DEFAULT NULL,
  `address_notes` varchar(200) DEFAULT NULL,
  `status` enum('Pending','Confirmed','Ready for Pickup','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `confirmed_total` decimal(12,2) DEFAULT NULL,
  `staff_notes` text DEFAULT NULL,
  `created_by_admin` int(10) unsigned DEFAULT NULL,
  `confirmed_by_admin` int(10) unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_orders_code` (`order_code`),
  KEY `idx_orders_customer` (`customer_id`,`created_at`),
  KEY `idx_orders_status` (`status`,`created_at`),
  KEY `idx_orders_delivered` (`delivered_at`),
  KEY `idx_orders_created` (`created_at`),
  KEY `fk_orders_created_by` (`created_by_admin`),
  KEY `fk_orders_confirmed_by` (`confirmed_by_admin`),
  CONSTRAINT `fk_orders_confirmed_by` FOREIGN KEY (`confirmed_by_admin`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by_admin`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `chk_orders_delivered_at` CHECK (`status` <> 'Delivered' or `delivered_at` is not null),
  CONSTRAINT `chk_orders_cancelled` CHECK (`status` <> 'Cancelled' or `cancelled_at` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-000001',2,'Pickup',NULL,NULL,NULL,NULL,NULL,'Delivered',NULL,NULL,NULL,1,'2026-10-10 01:25:10','2026-10-10 01:28:13',NULL,NULL,'2026-10-10 01:25:08','2026-10-10 01:28:13');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_allocations`
--

DROP TABLE IF EXISTS `payment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_allocations` (
  `allocation_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`allocation_id`),
  KEY `idx_alloc_payment` (`payment_id`),
  KEY `idx_alloc_order` (`order_id`),
  CONSTRAINT `fk_alloc_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_alloc_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_allocations`
--

LOCK TABLES `payment_allocations` WRITE;
/*!40000 ALTER TABLE `payment_allocations` DISABLE KEYS */;
INSERT INTO `payment_allocations` VALUES (1,1,1,2360.00,'2026-10-10 01:25:10');
/*!40000 ALTER TABLE `payment_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `payment_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('Cash','Bank Transfer','GCash','Maya','Check','Other') NOT NULL DEFAULT 'Cash',
  `reference_no` varchar(80) DEFAULT NULL,
  `received_by` int(10) unsigned NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  KEY `idx_payments_order` (`order_id`),
  KEY `idx_payments_received` (`received_at`),
  KEY `idx_payments_active` (`order_id`,`voided_at`),
  KEY `fk_payments_admin` (`received_by`),
  CONSTRAINT `fk_payments_admin` FOREIGN KEY (`received_by`) REFERENCES `admins` (`admin_id`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_payments_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_payments_void` CHECK (`voided_at` is null and `voided_by` is null and `void_reason` is null or `voided_at` is not null and `voided_by` is not null and `void_reason` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,2360.00,'Cash',NULL,1,NULL,'2026-10-10 01:25:10',NULL,NULL,NULL,'2026-10-10 01:25:10');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `bucket` varchar(80) NOT NULL,
  `identifier` varchar(190) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  `window_end` datetime NOT NULL,
  PRIMARY KEY (`bucket`,`identifier`),
  KEY `idx_rate_limits_window` (`window_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(400) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('business_address','Public Market St, Brgy. Poblacion, Marikina City','2026-10-10 00:31:17'),('business_contact','+63 917 123 4567','2026-10-10 00:31:17'),('business_facebook','https://www.facebook.com/profile.php?id=61578261067165','2026-10-10 00:31:17'),('business_name','Mavies Bugasan','2026-10-10 00:31:17'),('delivery_note','Same-day delivery within Quezon City and Marikina for orders placed before 2 PM.','2026-10-10 00:31:17');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `movement_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_id` int(10) unsigned NOT NULL,
  `move_type` enum('In','Out','Adjustment') NOT NULL,
  `qty` int(11) NOT NULL,
  `reason` varchar(200) NOT NULL,
  `ref_type` enum('order','damage','manual','seed') DEFAULT NULL,
  `ref_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`movement_id`),
  KEY `idx_movements_inventory` (`inventory_id`,`created_at`),
  KEY `fk_movements_admin` (`created_by`),
  CONSTRAINT `fk_movements_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  CONSTRAINT `chk_movements_qty` CHECK (`qty` <> 0)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,13,'Adjustment',40,'Acceptance run','manual',NULL,1,'2026-10-10 01:25:06'),(2,13,'Out',-2,'Reserved for order #1','order',1,NULL,'2026-10-10 01:25:08'),(3,9,'Adjustment',40,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(4,7,'Adjustment',25,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(5,3,'Adjustment',30,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(6,12,'Adjustment',20,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(7,5,'Adjustment',15,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(8,10,'Adjustment',18,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:34'),(9,11,'Adjustment',12,'Seed stock for customer UI testing','manual',NULL,1,'2026-10-11 00:28:35');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transaction_log`
--

DROP TABLE IF EXISTS `transaction_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction_log` (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_type` enum('customer','admin','system') NOT NULL DEFAULT 'system',
  `actor_id` int(10) unsigned DEFAULT NULL,
  `actor_label` varchar(120) DEFAULT NULL,
  `action_type` varchar(60) NOT NULL,
  `description` varchar(400) DEFAULT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varbinary(16) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_log_created` (`created_at`),
  KEY `idx_log_action` (`action_type`,`created_at`),
  KEY `idx_log_actor` (`actor_type`,`actor_id`,`created_at`),
  KEY `idx_log_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transaction_log`
--

LOCK TABLES `transaction_log` WRITE;
/*!40000 ALTER TABLE `transaction_log` DISABLE KEYS */;
INSERT INTO `transaction_log` VALUES (1,'system',NULL,NULL,'SETUP_OWNER_CREATED','owner@maviesbugasan.ph','admin',1,'\0\0','2026-10-10 01:18:45'),(2,'customer',1,'Live User','CUSTOMER_REGISTERED',NULL,'customer',1,'\0\0','2026-10-10 01:19:35'),(3,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-10 01:19:37'),(4,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-10 01:25:05'),(5,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 40 (Acceptance run)','inventory',13,'\0\0','2026-10-10 01:25:06'),(6,'customer',2,'Live2 User','CUSTOMER_REGISTERED',NULL,'customer',2,'\0\0','2026-10-10 01:25:07'),(7,'customer',2,'Live2 User','ORDER_CREATED','ORD-000001','order',1,'\0\0','2026-10-10 01:25:09'),(8,'admin',1,'Owner','PAYMENT_RECORDED','₱2,360.00 via Cash across 1 order(s)','payment',1,'\0\0','2026-10-10 01:25:10'),(9,'admin',1,'Owner','ORDER_STATUS_CHANGED','Pending -> Confirmed','order',1,'\0\0','2026-10-10 01:25:10'),(10,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-10 01:27:43'),(11,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-10 01:28:11'),(12,'admin',1,'Owner','ORDER_STATUS_CHANGED','Confirmed -> Ready for Pickup','order',1,'\0\0','2026-10-10 01:28:12'),(13,'admin',1,'Owner','ORDER_STATUS_CHANGED','Ready for Pickup -> Delivered','order',1,'\0\0','2026-10-10 01:28:13'),(14,'customer',2,'Live2 User','CUSTOMER_LOGIN',NULL,'customer',2,'\0\0','2026-10-10 01:29:55'),(15,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-10 01:33:33'),(16,'customer',2,'Live2 User','CUSTOMER_LOGIN',NULL,'customer',2,'\0\0','2026-10-10 01:35:38'),(17,'customer',2,'Live2 User','CUSTOMER_LOGIN',NULL,'customer',2,'\0\0','2026-10-10 10:41:21'),(18,'customer',3,'Live Tester','CUSTOMER_REGISTERED',NULL,'customer',3,'\0\0','2026-10-11 00:24:09'),(19,'customer',3,'Live Tester','EMAIL_VERIFIED',NULL,'customer',3,'\0\0','2026-10-11 00:24:11'),(20,'customer',3,'Live Tester','CUSTOMER_LOGIN',NULL,'customer',3,'\0\0','2026-10-11 00:24:12'),(21,'customer',3,'Live Tester','CUSTOMER_UPDATED',NULL,'customer',3,'\0\0','2026-10-11 00:24:13'),(22,'admin',1,'Owner','ADMIN_LOGIN',NULL,'admin',1,'\0\0','2026-10-11 00:24:15'),(23,'admin',1,'Owner','CUSTOMER_UPDATED','Live Tester','customer',3,'\0\0','2026-10-11 00:24:16'),(24,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 40 (Seed stock for customer UI testing)','inventory',9,'\0\0','2026-10-11 00:28:34'),(25,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 25 (Seed stock for customer UI testing)','inventory',7,'\0\0','2026-10-11 00:28:34'),(26,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 30 (Seed stock for customer UI testing)','inventory',3,'\0\0','2026-10-11 00:28:34'),(27,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 20 (Seed stock for customer UI testing)','inventory',12,'\0\0','2026-10-11 00:28:34'),(28,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 15 (Seed stock for customer UI testing)','inventory',5,'\0\0','2026-10-11 00:28:34'),(29,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 18 (Seed stock for customer UI testing)','inventory',10,'\0\0','2026-10-11 00:28:34'),(30,'admin',1,'Owner','STOCK_ADJUSTED','Stock 0 -> 12 (Seed stock for customer UI testing)','inventory',11,'\0\0','2026-10-11 00:28:35'),(31,'customer',1,'Live User','CUSTOMER_LOGIN',NULL,'customer',1,'\0\0','2026-10-11 00:30:09'),(32,'customer',1,'Live User','CUSTOMER_LOGOUT',NULL,'customer',1,'\0\0','2026-10-11 00:31:33');
/*!40000 ALTER TABLE `transaction_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_orders`
--

DROP TABLE IF EXISTS `v_orders`;
/*!50001 DROP VIEW IF EXISTS `v_orders`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_orders` AS SELECT
 1 AS `order_id`,
  1 AS `order_code`,
  1 AS `customer_id`,
  1 AS `first_name`,
  1 AS `last_name`,
  1 AS `customer_name`,
  1 AS `phone`,
  1 AS `customer_email`,
  1 AS `blacklisted`,
  1 AS `fulfillment_method`,
  1 AS `payment_method`,
  1 AS `payment_proof_path`,
  1 AS `courier_name`,
  1 AS `courier_link`,
  1 AS `status`,
  1 AS `staff_notes`,
  1 AS `confirmed_total`,
  1 AS `created_by_admin`,
  1 AS `confirmed_by_admin`,
  1 AS `confirmed_at`,
  1 AS `delivered_at`,
  1 AS `cancelled_at`,
  1 AS `cancel_reason`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `total_sacks`,
  1 AS `items_subtotal`,
  1 AS `items_total`,
  1 AS `item_count`,
  1 AS `items_priced`,
  1 AS `amount_due`,
  1 AS `amount_paid`,
  1 AS `balance`,
  1 AS `payment_status`,
  1 AS `is_delivered`,
  1 AS `classification` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'mavies_bugasan'
--

--
-- Final view structure for view `v_orders`
--

/*!50001 DROP VIEW IF EXISTS `v_orders`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_orders` AS select `o`.`order_id` AS `order_id`,`o`.`order_code` AS `order_code`,`o`.`customer_id` AS `customer_id`,`c`.`first_name` AS `first_name`,`c`.`last_name` AS `last_name`,concat(`c`.`first_name`,' ',`c`.`last_name`) AS `customer_name`,`c`.`phone` AS `phone`,`c`.`email` AS `customer_email`,`c`.`blacklisted` AS `blacklisted`,`o`.`fulfillment_method` AS `fulfillment_method`,`o`.`payment_method` AS `payment_method`,`o`.`payment_proof_path` AS `payment_proof_path`,`o`.`courier_name` AS `courier_name`,`o`.`courier_link` AS `courier_link`,`o`.`status` AS `status`,`o`.`staff_notes` AS `staff_notes`,`o`.`confirmed_total` AS `confirmed_total`,`o`.`created_by_admin` AS `created_by_admin`,`o`.`confirmed_by_admin` AS `confirmed_by_admin`,`o`.`confirmed_at` AS `confirmed_at`,`o`.`delivered_at` AS `delivered_at`,`o`.`cancelled_at` AS `cancelled_at`,`o`.`cancel_reason` AS `cancel_reason`,`o`.`created_at` AS `created_at`,`o`.`updated_at` AS `updated_at`,(select coalesce(sum(`oi`.`sacks`),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`) AS `total_sacks`,(select coalesce(sum(`oi`.`subtotal`),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`) AS `items_subtotal`,(select coalesce(sum(`oi`.`sacks` * coalesce(`oi`.`final_unit_price`,`oi`.`unit_price`)),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`) AS `items_total`,(select count(0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`) AS `item_count`,(select count(0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id` and `oi`.`final_unit_price` is not null) AS `items_priced`,coalesce(`o`.`confirmed_total`,(select coalesce(sum(`oi`.`sacks` * coalesce(`oi`.`final_unit_price`,`oi`.`unit_price`)),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`)) AS `amount_due`,(select coalesce(sum(`pa`.`amount`),0) from (`payment_allocations` `pa` join `payments` `p` on(`p`.`payment_id` = `pa`.`payment_id`)) where `pa`.`order_id` = `o`.`order_id` and `p`.`voided_at` is null) AS `amount_paid`,round(coalesce(`o`.`confirmed_total`,(select coalesce(sum(`oi`.`sacks` * coalesce(`oi`.`final_unit_price`,`oi`.`unit_price`)),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`)) - (select coalesce(sum(`pa`.`amount`),0) from (`payment_allocations` `pa` join `payments` `p` on(`p`.`payment_id` = `pa`.`payment_id`)) where `pa`.`order_id` = `o`.`order_id` and `p`.`voided_at` is null),2) AS `balance`,case when `o`.`status` = 'Cancelled' then 'Cancelled' when abs(coalesce(`o`.`confirmed_total`,(select coalesce(sum(`oi`.`sacks` * coalesce(`oi`.`final_unit_price`,`oi`.`unit_price`)),0) from `order_items` `oi` where `oi`.`order_id` = `o`.`order_id`)) - (select coalesce(sum(`pa`.`amount`),0) from (`payment_allocations` `pa` join `payments` `p` on(`p`.`payment_id` = `pa`.`payment_id`)) where `pa`.`order_id` = `o`.`order_id` and `p`.`voided_at` is null)) < 0.005 then 'Paid' when (select coalesce(sum(`pa`.`amount`),0) from (`payment_allocations` `pa` join `payments` `p` on(`p`.`payment_id` = `pa`.`payment_id`)) where `pa`.`order_id` = `o`.`order_id` and `p`.`voided_at` is null) > 0 then 'Partial' else 'Unpaid' end AS `payment_status`,case when `o`.`status` = 'Delivered' then 1 else 0 end AS `is_delivered`,case when (select count(0) from `orders` `o2` where `o2`.`customer_id` = `o`.`customer_id` and `o2`.`status` <> 'Cancelled') >= 2 then 'Returning' else 'New' end AS `classification` from (`orders` `o` join `customers` `c` on(`c`.`customer_id` = `o`.`customer_id`)) */;
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

-- Dump completed on 2026-10-11  1:27:55
