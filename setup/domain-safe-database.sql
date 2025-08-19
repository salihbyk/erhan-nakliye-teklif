-- Domain ortamı için güvenli veritabanı kurulum dosyası
-- Foreign key kısıtlamaları olmadan çalışır
-- Bu dosyayı phpMyAdmin'de veya MySQL komut satırında çalıştırın

-- Veritabanını seç (eğer yoksa oluştur)
CREATE DATABASE IF NOT EXISTS nakliye_teklif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nakliye_teklif;

-- SQL ayarları
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Foreign key kontrollerini kapat
SET FOREIGN_KEY_CHECKS = 0;

-- Mevcut tabloları sil (eğer varsa)
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
-- Tablo yapıları (Foreign Key kısıtlamaları olmadan)
-- --------------------------------------------------------

-- 1. Admin kullanıcıları tablosu
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

-- 2. Müşteriler tablosu
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

-- 3. Taşıma modları tablosu
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

-- 4. Maliyet listeleri tablosu
CREATE TABLE `cost_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `transport_mode_id` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode` (`transport_mode_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Teklifler tablosu
CREATE TABLE `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_number` varchar(20) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transport_mode_id` int(11) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `volume` decimal(10,3) DEFAULT NULL,
  `pieces` int(11) DEFAULT NULL,
  `cargo_type` enum('genel','hassas','soguk','tehlikeli') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `calculated_price` decimal(10,2) DEFAULT NULL,
  `final_price` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','sent','accepted','rejected','expired') DEFAULT 'pending',
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `cost_list_id` int(11) DEFAULT NULL,
  `container_type` varchar(50) DEFAULT NULL,
  `custom_fields` text DEFAULT NULL,
  `show_reference_images` tinyint(1) DEFAULT 0,
  `payment_status` enum('pending','partial','completed') DEFAULT 'pending',
  `delivery_status` enum('pending','in_transit','delivered') DEFAULT 'pending',
  `revision_count` int(11) DEFAULT 0,
  `last_revision_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quote_number` (`quote_number`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_cost_list` (`cost_list_id`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_delivery_status` (`delivery_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ek maliyetler tablosu
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

-- 7. E-posta logları tablosu
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

-- 8. E-posta şablonları tablosu
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `transport_mode_id` int(11) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body` longtext NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transport_mode_id` (`transport_mode_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Ödemeler tablosu
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

-- 10. Teklif şablonları tablosu
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

-- 11. Taşıma görselleri tablosu
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

-- 12. Taşıma referans görselleri tablosu
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
-- Temel verileri ekle
-- --------------------------------------------------------

-- Admin kullanıcısı ekle (şifre: admin123)
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sistem Yöneticisi', 'admin', 1);

-- Taşıma modlarını ekle
INSERT INTO `transport_modes` (`name`, `description`, `is_active`) VALUES
('karayolu', 'Karayolu Taşımacılığı', 1),
('denizyolu', 'Denizyolu Taşımacılığı', 1),
('havayolu', 'Havayolu Taşımacılığı', 1);

-- İşlemi tamamla
COMMIT;

-- Foreign key kontrollerini tekrar aç (isteğe bağlı)
-- SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Domain ortamı için güvenli veritabanı başarıyla oluşturuldu!' as message;
