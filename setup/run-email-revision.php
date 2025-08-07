<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Email ve revision tracking alanları ekleniyor...\n";

    // Alanları tek tek ekle, hata durumunda devam et
    $alterations = [
        "ALTER TABLE quotes ADD COLUMN email_sent_at TIMESTAMP NULL",
        "ALTER TABLE quotes ADD COLUMN email_sent_count INT DEFAULT 0",
        "ALTER TABLE quotes ADD COLUMN revision_number INT DEFAULT 0",
        "ALTER TABLE quotes ADD COLUMN parent_quote_id INT NULL",
        "ALTER TABLE quotes ADD COLUMN is_active BOOLEAN DEFAULT 1",
        "ALTER TABLE quotes ADD INDEX idx_email_sent (email_sent_at)",
        "ALTER TABLE quotes ADD INDEX idx_revision (revision_number)",
        "ALTER TABLE quotes ADD INDEX idx_parent_quote (parent_quote_id)",
        "ALTER TABLE quotes ADD INDEX idx_active (is_active)"
    ];

    foreach ($alterations as $sql) {
        try {
            $db->exec($sql);
            echo "✅ Başarılı: " . substr($sql, 0, 50) . "...\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "ℹ️ Zaten mevcut: " . substr($sql, 0, 50) . "...\n";
            } else {
                echo "❌ Hata: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n🎉 Migration tamamlandı!\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>