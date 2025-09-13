<?php
/**
 * Güncelleme sistemi için yardımcı fonksiyonlar
 */

/**
 * Mevcut sistem versiyonunu al
 */
function getCurrentSystemVersion() {
    // 1) Önce veritabanı ayarı
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_version'");
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result && !empty($result['setting_value'])) {
            return $result['setting_value'];
        }
    } catch (Exception $e) {
        // Devam et
    }

    // 2) Sonra version.txt
    $versionTxtPath = __DIR__ . '/../version.txt';
    if (file_exists($versionTxtPath)) {
        $txt = trim(@file_get_contents($versionTxtPath));
        if (!empty($txt)) {
            return $txt;
        }
    }

    // 3) Varsayılan
    return '1.0.0';
}

/**
 * Sistem versiyonunu güncelle
 */
function updateSystemVersion($version) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES ('system_version', ?, 'Mevcut sistem versiyonu')
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([$version]);
    } catch (Exception $e) {
        error_log("Versiyon güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Güncelleme geçmişine kayıt ekle
 */
function logSystemUpdate($version, $description, $success = true, $backupFile = null, $notes = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            INSERT INTO system_updates (version, description, success, backup_file, notes)
            VALUES (?, ?, ?, ?, ?)
        ");

        return $stmt->execute([$version, $description, $success ? 1 : 0, $backupFile, $notes]);
    } catch (Exception $e) {
        error_log("Güncelleme log hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Migration'ı çalıştır ve kaydet
 */
function executeMigration($migrationFile, $version = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Migration daha önce çalıştırılmış mı kontrol et
        $stmt = $db->prepare("SELECT id FROM migrations WHERE migration_name = ?");
        $stmt->execute([basename($migrationFile)]);

        if ($stmt->fetch()) {
            return ['success' => true, 'message' => 'Migration zaten çalıştırılmış'];
        }

        // Migration dosyasını oku ve çalıştır
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration dosyası bulunamadı: $migrationFile");
        }

        $sql = file_get_contents($migrationFile);
        if (empty($sql)) {
            throw new Exception("Migration dosyası boş: $migrationFile");
        }

        // SQL'i çalıştır
        $db->exec($sql);

        // Migration geçmişine kaydet
        $stmt = $db->prepare("
            INSERT INTO migrations (migration_name, version, success)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([basename($migrationFile), $version]);

        return ['success' => true, 'message' => 'Migration başarıyla çalıştırıldı'];

    } catch (Exception $e) {
        // Hata durumunda kaydet
        try {
            $database = new Database();
            $db = $database->getConnection();

            $stmt = $db->prepare("
                INSERT INTO migrations (migration_name, version, success, error_message)
                VALUES (?, ?, 0, ?)
            ");
            $stmt->execute([basename($migrationFile), $version, $e->getMessage()]);
        } catch (Exception $logError) {
            error_log("Migration log hatası: " . $logError->getMessage());
        }

        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Veritabanı yedeği oluştur
 */
function createSystemBackup($type = 'manual', $description = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        // Backup dizini oluştur
        $backupDir = '../backups/database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupFile = $backupDir . '/backup_' . $type . '_' . date('Y-m-d_H-i-s') . '.sql';

        // Tabloları al
        $tables = [];
        $result = $db->query('SHOW TABLES');
        while ($row = $result->fetch()) {
            $tables[] = $row[0];
        }

        $output = "-- Veritabanı Yedeği\n";
        $output .= "-- Oluşturulma Tarihi: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Tip: $type\n\n";

        // Her tablo için
        foreach ($tables as $table) {
            // Tablo yapısı
            $result = $db->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch();
            $output .= "\n-- Tablo yapısı: $table\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $row[1] . ";\n\n";

            // Tablo verisi
            $result = $db->query("SELECT * FROM `$table`");
            $rowCount = 0;

            while ($row = $result->fetch()) {
                if ($rowCount == 0) {
                    $output .= "-- Tablo verisi: $table\n";
                }

                $values = array_map(function($value) use ($db) {
                    return $value === null ? 'NULL' : $db->quote($value);
                }, array_values($row));

                $columns = array_keys($row);
                $columnNames = '`' . implode('`, `', $columns) . '`';

                $output .= "INSERT INTO `$table` ($columnNames) VALUES (" . implode(', ', $values) . ");\n";
                $rowCount++;
            }

            if ($rowCount > 0) {
                $output .= "\n";
            }
        }

        // Dosyayı kaydet
        if (file_put_contents($backupFile, $output)) {
            $fileSize = filesize($backupFile);

            // Backup geçmişine kaydet
            $stmt = $db->prepare("
                INSERT INTO system_backups (backup_type, backup_file, backup_size, description, status)
                VALUES (?, ?, ?, ?, 'success')
            ");
            $stmt->execute([$type, basename($backupFile), $fileSize, $description]);

            return [
                'success' => true,
                'file' => $backupFile,
                'size' => $fileSize
            ];
        } else {
            throw new Exception('Backup dosyası yazılamadı');
        }

    } catch (Exception $e) {
        // Hata durumunda kaydet
        try {
            $database = new Database();
            $db = $database->getConnection();

            $stmt = $db->prepare("
                INSERT INTO system_backups (backup_type, description, status)
                VALUES (?, ?, 'failed')
            ");
            $stmt->execute([$type, $e->getMessage()]);
        } catch (Exception $logError) {
            error_log("Backup log hatası: " . $logError->getMessage());
        }

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Dosya senkronizasyonu
 */
function syncFile($source, $target, $createBackup = true) {
    try {
        // Kaynak dosya var mı kontrol et
        if (!file_exists($source)) {
            throw new Exception("Kaynak dosya bulunamadı: $source");
        }

        // Hedef dizini oluştur
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Mevcut dosyayı yedekle
        if ($createBackup && file_exists($target)) {
            $backupPath = $target . '.backup.' . time();
            if (!copy($target, $backupPath)) {
                throw new Exception("Yedekleme başarısız: $target");
            }
        }

        // Dosyayı kopyala
        if (!copy($source, $target)) {
            throw new Exception("Dosya kopyalanamadı: $source -> $target");
        }

        return ['success' => true];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Dizin senkronizasyonu
 */
function syncDirectory($sourceDir, $targetDir, $recursive = true, $createBackup = true) {
    try {
        if (!is_dir($sourceDir)) {
            throw new Exception("Kaynak dizin bulunamadı: $sourceDir");
        }

        // Hedef dizini oluştur
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir))
            : new DirectoryIterator($sourceDir);

        $syncedFiles = 0;
        $errors = [];

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $targetFile = $targetDir . DIRECTORY_SEPARATOR . $relativePath;

                $result = syncFile($file->getPathname(), $targetFile, $createBackup);

                if ($result['success']) {
                    $syncedFiles++;
                } else {
                    $errors[] = $result['error'];
                }
            }
        }

        return [
            'success' => empty($errors),
            'synced_files' => $syncedFiles,
            'errors' => $errors
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Güncelleme geçmişini al
 */
function getSystemUpdateHistory($limit = 10) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT version, description, update_date, success, notes
            FROM system_updates
            ORDER BY update_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Güncelleme geçmişi hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Migration geçmişini al
 */
function getMigrationHistory($limit = 10) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT migration_name, executed_at, version, success, error_message
            FROM migrations
            ORDER BY executed_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Migration geçmişi hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Backup geçmişini al
 */
function getBackupHistory($limit = 10) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT backup_type, backup_file, backup_size, created_at, description, status
            FROM system_backups
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);

        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Backup geçmişi hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Sistem ayarı al
 */
function getSystemSetting($key, $default = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Sistem ayarını güncelle
 */
function updateSystemSetting($key, $value, $description = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                description = COALESCE(VALUES(description), description),
                updated_at = CURRENT_TIMESTAMP
        ");

        return $stmt->execute([$key, $value, $description]);
    } catch (Exception $e) {
        error_log("Sistem ayarı güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Otomatik yedekleme kontrolü
 */
function shouldCreateAutoBackup() {
    $autoBackupEnabled = getSystemSetting('auto_backup_enabled', '1');
    if ($autoBackupEnabled !== '1') {
        return false;
    }

    $lastBackup = getSystemSetting('last_auto_backup', '');
    if (empty($lastBackup)) {
        return true;
    }

    $lastBackupTime = strtotime($lastBackup);
    $daysSinceLastBackup = (time() - $lastBackupTime) / (24 * 60 * 60);

    return $daysSinceLastBackup >= 7; // Haftalık otomatik yedekleme
}

/**
 * Güncelleme süreci temizliği
 */
function cleanupUpdateProcess($tempDir = null) {
    try {
        // Geçici dosyaları temizle
        if ($tempDir && is_dir($tempDir)) {
            $files = array_diff(scandir($tempDir), array('.', '..'));
            foreach ($files as $file) {
                $path = $tempDir . '/' . $file;
                if (is_dir($path)) {
                    cleanupUpdateProcess($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($tempDir);
        }

        // Eski backup dosyalarını temizle (30 günden eski)
        $backupDir = '../backups/database';
        if (is_dir($backupDir)) {
            $files = glob($backupDir . '/backup_*.sql');
            foreach ($files as $file) {
                if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
                    unlink($file);
                }
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Temizlik hatası: " . $e->getMessage());
        return false;
    }
}
?>
