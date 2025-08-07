-- Güncelleme sistemi için gerekli tablolar

-- Güncelleme geçmişi tablosu
CREATE TABLE IF NOT EXISTS `system_updates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `version` varchar(20) NOT NULL,
    `description` text,
    `update_date` timestamp DEFAULT CURRENT_TIMESTAMP,
    `success` tinyint(1) DEFAULT 1,
    `backup_file` varchar(255),
    `notes` text,
    PRIMARY KEY (`id`),
    INDEX `idx_version` (`version`),
    INDEX `idx_date` (`update_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration geçmişi tablosu
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `migration_name` varchar(255) NOT NULL,
    `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `version` varchar(20),
    `success` tinyint(1) DEFAULT 1,
    `error_message` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_migration` (`migration_name`),
    INDEX `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistem ayarları tablosu (eğer yoksa)
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `description` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_setting` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistem versiyonu ayarını ekle
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('system_version', '1.0.0', 'Mevcut sistem versiyonu')
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `updated_at` = CURRENT_TIMESTAMP;

-- Son güncelleme tarihi ayarını ekle
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('last_update_check', '', 'Son güncelleme kontrol tarihi')
ON DUPLICATE KEY UPDATE
    `updated_at` = CURRENT_TIMESTAMP;

-- Güncelleme ayarları
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('auto_backup_enabled', '1', 'Otomatik yedekleme etkin mi?')
ON DUPLICATE KEY UPDATE
    `updated_at` = CURRENT_TIMESTAMP;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`)
VALUES ('update_notifications', '1', 'Güncelleme bildirimleri etkin mi?')
ON DUPLICATE KEY UPDATE
    `updated_at` = CURRENT_TIMESTAMP;

-- Yedekleme geçmişi tablosu
CREATE TABLE IF NOT EXISTS `system_backups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `backup_type` enum('manual', 'auto', 'pre_update') DEFAULT 'manual',
    `backup_file` varchar(255) NOT NULL,
    `backup_size` bigint(20),
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `description` text,
    `status` enum('success', 'failed') DEFAULT 'success',
    PRIMARY KEY (`id`),
    INDEX `idx_type` (`backup_type`),
    INDEX `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
