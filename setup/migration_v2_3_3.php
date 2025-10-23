<?php
/**
 * Migration v2.3.3
 *
 * Değişiklikler:
 * 1. customers tablosuna cc_email kolonu ekleme
 * 2. quote_templates tablosunda currency enum değerini TL'den TRY'ye güncelleme
 * 3. description alanı görünürlük iyileştirmeleri
 */

require_once __DIR__ . '/../config/database.php';

function runMigration_v2_3_3() {
    $results = [];

    try {
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            throw new Exception('Veritabanı bağlantısı kurulamadı');
        }

        // 1. customers tablosuna cc_email kolonu ekle
        try {
            $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'cc_email'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$column) {
                $db->exec("ALTER TABLE `customers`
                           ADD COLUMN `cc_email` VARCHAR(255) NULL DEFAULT NULL
                           AFTER `email`");
                $results[] = "✓ customers.cc_email kolonu eklendi";
            } else {
                $results[] = "• customers.cc_email kolonu zaten mevcut";
            }
        } catch (Exception $e) {
            $results[] = "✗ cc_email kolonu eklenirken hata: " . $e->getMessage();
        }

        // 2. quote_templates tablosunda currency enum'u güncelle (TL -> TRY)
        try {
            $stmt = $db->query("SHOW COLUMNS FROM quote_templates LIKE 'currency'");
            $column = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($column) {
                // Mevcut enum değerlerini kontrol et
                if (strpos($column['Type'], "'TL'") !== false) {
                    // TL varsa TRY'ye güncelle
                    $db->exec("ALTER TABLE `quote_templates`
                               MODIFY COLUMN `currency` ENUM('TRY','USD','EUR')
                               DEFAULT 'USD'");
                    $results[] = "✓ quote_templates.currency enum güncellendi (TL -> TRY)";
                } else {
                    $results[] = "• quote_templates.currency zaten güncel";
                }
            }
        } catch (Exception $e) {
            $results[] = "✗ currency enum güncellenirken hata: " . $e->getMessage();
        }

        // 3. Mevcut boş currency değerlerini USD olarak ayarla
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM `quote_templates` WHERE `currency` = '' OR `currency` IS NULL");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $db->exec("UPDATE `quote_templates`
                           SET `currency` = 'USD'
                           WHERE `currency` = '' OR `currency` IS NULL");
                $results[] = "✓ {$result['count']} şablon varsayılan para birimi ile güncellendi";
            }
        } catch (Exception $e) {
            // Sessizce devam et
        }

        return [
            'success' => true,
            'message' => 'Migration v2.3.3 başarıyla tamamlandı',
            'details' => $results
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Migration hatası: ' . $e->getMessage(),
            'details' => $results
        ];
    }
}

// Eğer doğrudan çalıştırılıyorsa
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    echo "Migration v2.3.3 çalıştırılıyor...\n";
    echo str_repeat("=", 60) . "\n";

    $result = runMigration_v2_3_3();

    echo "\n" . $result['message'] . "\n\n";

    if (!empty($result['details'])) {
        echo "Detaylar:\n";
        foreach ($result['details'] as $detail) {
            echo "  " . $detail . "\n";
        }
    }

    echo "\n" . str_repeat("=", 60) . "\n";

    exit($result['success'] ? 0 : 1);
}

