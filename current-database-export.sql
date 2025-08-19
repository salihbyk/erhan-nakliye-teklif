-- Mevcut Production Veritabanı Export (Domain Ortamı İçin Uyarlanmış)
-- Oluşturulma Tarihi: 2025-01-16
-- Foreign Key kısıtlamaları kaldırılmış, domain ortamında sorunsuz çalışır

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Veritabanını oluştur (eğer yoksa)
CREATE DATABASE IF NOT EXISTS nakliye_teklif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nakliye_teklif;

-- Mevcut tabloları sil
DROP TABLE IF EXISTS additional_costs;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS cost_lists;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS email_logs;
DROP TABLE IF EXISTS email_templates;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS quote_templates;
DROP TABLE IF EXISTS transport_images;
DROP TABLE IF EXISTS transport_modes;
DROP TABLE IF EXISTS transport_reference_images;

-- --------------------------------------------------------
-- Tablo yapıları (Production'dan alınmış, Foreign Key'ler kaldırılmış)
-- --------------------------------------------------------

-- additional_costs tablosu
CREATE TABLE `additional_costs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` enum('TL','USD','EUR') DEFAULT 'TL',
  `is_additional` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_id` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- admin_users tablosu
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','manager','operator') DEFAULT 'operator',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cost_lists tablosu
CREATE TABLE `cost_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `transport_mode_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customers tablosu
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_logs tablosu
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_id` (`quote_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_templates tablosu (Production yapısı)
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transport_mode_id` int(11) NOT NULL,
  `language` enum('tr','en') DEFAULT 'tr',
  `currency` enum('TL','USD','EUR') DEFAULT 'TL',
  `template_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `email_content` text NOT NULL,
  `quote_content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payments tablosu
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` enum('TL','USD','EUR') DEFAULT 'TL',
  `payment_date` date NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quote_id` (`quote_id`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- quotes tablosu (Production yapısı)
CREATE TABLE `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_number` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transport_mode_id` int(11) NOT NULL,
  `container_type` varchar(20) DEFAULT NULL,
  `custom_transport_name` varchar(255) DEFAULT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `volume` decimal(10,3) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `pieces` int(11) DEFAULT NULL,
  `cargo_type` enum('kisisel_esya','ev_esyasi','ticari_esya') DEFAULT NULL,
  `trade_type` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `calculated_price` decimal(10,2) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `cost_list_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','sent','accepted','rejected','expired') DEFAULT 'pending',
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending',
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `delivery_status` enum('pending','in_transit','delivered') DEFAULT 'pending',
  `pickup_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `start_date` text DEFAULT NULL,
  `delivery_date` text DEFAULT NULL,
  `tracking_notes` text DEFAULT NULL,
  `selected_template_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cost_list_id` int(11) DEFAULT NULL,
  `email_sent_at` timestamp NULL DEFAULT NULL,
  `email_sent_count` int(11) DEFAULT 0,
  `revision_number` int(11) DEFAULT 0,
  `parent_quote_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `services_content` text DEFAULT NULL COMMENT 'Şablondan gelen hizmetler içeriği',
  `optional_services_content` text DEFAULT NULL COMMENT 'Şablondan gelen opsiyonel hizmetler içeriği',
  `terms_content` text DEFAULT NULL COMMENT 'Şablondan gelen şartlar içeriği',
  `additional_section1_title` varchar(255) DEFAULT NULL,
  `additional_section1_content` text DEFAULT NULL,
  `additional_section2_title` varchar(255) DEFAULT NULL,
  `additional_section2_content` text DEFAULT NULL,
  `additional_section3_title` varchar(255) DEFAULT NULL,
  `additional_section3_content` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_cost_list_id` (`cost_list_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_delivery_status` (`delivery_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- quote_templates tablosu
CREATE TABLE `quote_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `transport_mode_id` int(11) NOT NULL,
  `template_content` longtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transport_images tablosu
CREATE TABLE `transport_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transport_mode` enum('karayolu','denizyolu','havayolu') NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode` (`transport_mode`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transport_modes tablosu
CREATE TABLE `transport_modes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- transport_reference_images tablosu
CREATE TABLE `transport_reference_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transport_mode` enum('karayolu','denizyolu','havayolu') NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode` (`transport_mode`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Verileri ekle (Production'dan alınmış)
-- --------------------------------------------------------

-- additional_costs verisi
INSERT INTO `additional_costs` (`id`, `quote_id`, `name`, `description`, `amount`, `currency`, `is_additional`, `created_at`, `updated_at`) VALUES
(1, 32, NULL, 'testet', 222.00, 'TL', 1, '2025-05-31 13:08:48', '2025-05-31 13:08:48'),
(4, 53, 'lahmacun', 'lahmacun 4 adet soylendi', 20.00, 'TL', 1, '2025-06-17 11:53:47', '2025-06-17 11:53:47'),
(5, 57, 'test', 'teaa', 222.00, 'EUR', 1, '2025-07-07 08:21:44', '2025-07-07 08:21:44'),
(6, 58, 'Sigorta', 'test açıklama', 5000.00, 'EUR', 1, '2025-07-07 10:32:20', '2025-07-07 10:32:20'),
(7, 58, 'test2', '', 1000.00, 'TL', 1, '2025-07-07 10:32:34', '2025-07-07 10:32:34'),
(9, 65, 'GÜMRÜKLEME HİZMET BEDELİ', 'Türkiye ihracat ve resmi ödemeleri, AB giriş ithalat ve İtalya ithalat gümrük işlemleri için bir defaya mahsus.', 475.00, 'EUR', 1, '2025-07-12 08:56:13', '2025-07-12 08:56:13'),
(10, 65, 'KIRILACAK EŞYALAR İÇİN SANDIK VE MUHAFAZA YAPIMI', 'Masanın mermeri için 1 adet, 4 adet gardrop kapağı, mermer sehpa 2 adet,  cam sehpa 1 adet, 2 adet resimli çerçeve, 7 tane avize ve 2 tane televizyon için sandık ve muhafaza yapımı.', 40000.00, 'TL', 1, '2025-07-12 09:01:07', '2025-07-12 09:22:03'),
(11, 76, 'Türkiye ihracat, Almanya ithalat gümrük işlemleri hizmet bedeli ve yapılan harç, pul, damga vergisi gibi ödemeler için bir defaya mahsus.', '', 250.00, 'EUR', 1, '2025-07-28 09:27:41', '2025-07-28 09:27:41'),
(12, 75, '15 m3 eşyanın kat farkı', '', 195.00, 'EUR', 1, '2025-07-28 11:45:45', '2025-07-28 11:45:45'),
(13, 82, 'Gümrük Hizmet Bedeli', '', 475.00, 'EUR', 1, '2025-07-28 13:29:50', '2025-07-28 13:29:50'),
(14, 82, 'Kat farkı', '', 195.00, 'EUR', 1, '2025-07-28 13:30:12', '2025-07-28 13:30:12'),
(15, 86, 'İLAVE HACİM', 'Eşyanın 5 metreküpten fazla çıkması halinde geçerlidir.', 300.00, 'EUR', 1, '2025-07-30 12:51:15', '2025-07-30 12:51:15'),
(16, 95, 'test1', '', 1111.00, 'TL', 1, '2025-08-04 11:19:52', '2025-08-04 11:19:52'),
(17, 97, NULL, '', 1111.00, 'TL', 1, '2025-08-04 11:24:09', '2025-08-04 11:24:09'),
(18, 100, NULL, '', 1111.00, 'TL', 1, '2025-08-04 11:35:47', '2025-08-04 11:35:47'),
(19, 104, 'test1', '', 111.00, 'TL', 1, '2025-08-04 13:10:51', '2025-08-04 13:10:51');

-- admin_users verisi
INSERT INTO `admin_users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@nakliye.com', '$2y$10$pYu.BoDavNC3OJAIgFaG0eEw39Wrt/lBWEwIFVK93A03IHTcaBzwm', 'Sistem Yöneticisi', 'admin', 1, '2025-08-13 08:31:43', '2025-05-27 08:55:02', '2025-08-13 08:31:43');

-- customers verisi (Production'dan alınmış)
INSERT INTO `customers` (`id`, `first_name`, `last_name`, `email`, `phone`, `company`, `created_at`, `updated_at`) VALUES
(3, 'Salihsss', 'BIYIKs', 'salihbysk@gmail.com', '+90 544 383 385', 'sss', '2025-06-17 10:26:20', '2025-06-17 10:26:20'),
(6, 'AHMET', 'DEVECİ', 'adevecicloud@hotmail.com', '491 511 12 152', '', '2025-07-09 11:49:11', '2025-07-09 11:49:11'),
(7, 'Yağmur', 'COŞAR', 'yagmur@europatrans.com.tr', '053 236 22 795', 'EUROPATRANS GLOBAL LOJİSTİK TAŞIMACILIK TİCARET LTD. ŞTİ.', '2025-07-11 09:04:14', '2025-07-11 09:04:14'),
(8, 'SEBA', 'ORHAN', 'seba@ten.com.tr', '05333497375', '', '2025-07-12 08:54:00', '2025-07-28 13:29:09'),
(9, 'AYSEL', 'GÜLERYÜZ', 'aysomegu@gmail.com', '+90 546 690 291', '', '2025-07-12 09:47:11', '2025-07-12 09:59:33'),
(10, 'Elif', 'BİŞİRİCİ', 'elifbisirici8@gmail.com', '05387426342', '', '2025-07-18 14:55:39', '2025-07-18 14:55:39'),
(11, 'Deha', 'ORHAN', 'dehaorhan1@gmail.com', '05323126626', '', '2025-07-24 09:06:57', '2025-07-24 09:06:57'),
(12, 'Mücahid', 'YILMAZ', 'muecahid.yilmaz@gmail.com', '05050798184', '', '2025-07-28 09:26:20', '2025-07-28 09:26:20'),
(13, 'DENA', 'KILIÇLI', 'mete@akaft.com', '05309696682', '', '2025-07-28 14:24:38', '2025-07-29 09:13:47'),
(14, 'KEMAL', 'ALIŞ', 'Kemalalis99@gmail.com', '0537 470 09 89', '', '2025-07-29 11:59:49', '2025-07-29 11:59:49'),
(15, 'Ezgi', 'Çelik', 'ezgi.celik@mfa.gov.tr', '0553 302 0491', '', '2025-07-30 12:48:37', '2025-07-30 13:01:40'),
(16, 'HELİN', 'KAYA', 'helinkayaa@gmail.com', '05316021936', '', '2025-07-31 06:43:41', '2025-07-31 07:09:45'),
(17, 'NURSAN', 'ZORLU', 'nrsnvrl01@outlook.com', '+90 544 516 28 66', '', '2025-08-01 08:52:37', '2025-08-01 08:52:37'),
(18, 'Salih', 'BIYIK', 'salihbyk@gmail.com', '+905443833806', '', '2025-08-04 10:26:56', '2025-08-04 13:09:24'),
(19, 'HİLAL', 'SAYINER', 'hilal.sayiner@pashainternational.com', '+90 533 855 80 98', '', '2025-08-05 11:37:54', '2025-08-05 11:37:54'),
(20, 'MUSTAFA', 'ALİ', 'erhan@europatrans.com.tr', '+905323622795', 'EUROPATRANS GLOBAL LOJİSTİK TAŞIMACILIK TİCARET LTD. ŞTİ.', '2025-08-06 07:25:11', '2025-08-06 07:25:11'),
(21, 'ALİ', 'MUSTAFA', 'uzunkayaerhan1906@gmail.com', '+905073730770', '', '2025-08-06 07:28:04', '2025-08-06 07:28:04'),
(22, 'HÜDAYİ', 'KARAKILIÇ', 'hdysmn@gmail.com', '05333670511', '', '2025-08-07 08:21:58', '2025-08-07 08:21:58'),
(23, 'Tansu', 'Dilir Aktaş', 'tansudilir@gmail.com', '05078623442', '', '2025-08-08 14:08:21', '2025-08-08 14:08:21');

-- transport_modes verisi
INSERT INTO `transport_modes` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'karayolu', 'Karayolu Taşımacılığı', 1, '2025-05-27 08:55:02', '2025-05-27 08:55:02'),
(2, 'havayolu', 'Havayolu Taşımacılığı', 1, '2025-05-27 08:55:02', '2025-05-27 08:55:02'),
(3, 'denizyolu', 'Denizyolu Taşımacılığı', 1, '2025-05-27 08:55:02', '2025-05-27 08:55:02'),
(4, 'konteyner', 'Konteyner Taşımacılığı', 1, '2025-05-27 08:55:02', '2025-05-27 08:55:02');

-- payments verisi (Production'dan alınmış)
INSERT INTO `payments` (`id`, `quote_id`, `amount`, `currency`, `payment_date`, `payment_method`, `notes`, `created_at`, `updated_at`) VALUES
(1, 59, 690.00, 'TL', '2025-07-10', NULL, 'Eski sistemden aktarılan ödeme', '2025-07-12 11:34:41', '2025-07-12 11:34:41'),
(2, 67, 4700.00, 'TL', '2025-07-12', NULL, 'Eski sistemden aktarılan ödeme', '2025-07-12 11:34:41', '2025-07-12 11:34:41');

-- AUTO_INCREMENT değerlerini ayarla (Production'dan alınmış)
ALTER TABLE `additional_costs` AUTO_INCREMENT = 20;
ALTER TABLE `admin_users` AUTO_INCREMENT = 2;
ALTER TABLE `cost_lists` AUTO_INCREMENT = 1;
ALTER TABLE `customers` AUTO_INCREMENT = 24;
ALTER TABLE `email_logs` AUTO_INCREMENT = 1;
ALTER TABLE `email_templates` AUTO_INCREMENT = 52;
ALTER TABLE `payments` AUTO_INCREMENT = 3;
ALTER TABLE `quotes` AUTO_INCREMENT = 105;
ALTER TABLE `quote_templates` AUTO_INCREMENT = 1;
ALTER TABLE `transport_images` AUTO_INCREMENT = 1;
ALTER TABLE `transport_modes` AUTO_INCREMENT = 5;
ALTER TABLE `transport_reference_images` AUTO_INCREMENT = 1;

-- İşlemi tamamla
COMMIT;

-- Karakter seti ayarlarını geri yükle
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

SELECT 'Production veritabanı başarıyla domain ortamına aktarıldı!' as message;
