<?php
// Maliyet listesi tablosunu oluşturmak için migration script
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Maliyet listesi tablosu oluşturuluyor...\n";

    // Maliyet listesi tablosu oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS cost_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT,
            mime_type VARCHAR(100),
            transport_mode_id INT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_transport_mode (transport_mode_id),
            INDEX idx_active (is_active),
            FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ cost_lists tablosu oluşturuldu.\n";

    // Quotes tablosuna cost_list_id kolonu ekle (varsa hata vermez)
    try {
        $db->exec("ALTER TABLE quotes ADD COLUMN cost_list_id INT DEFAULT NULL");
        echo "✅ quotes tablosuna cost_list_id kolonu eklendi.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ cost_list_id kolonu zaten mevcut.\n";
        } else {
            throw $e;
        }
    }

    // Index ekle
    try {
        $db->exec("ALTER TABLE quotes ADD INDEX idx_cost_list (cost_list_id)");
        echo "✅ cost_list_id için index eklendi.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "ℹ️ cost_list_id index zaten mevcut.\n";
        } else {
            throw $e;
        }
    }

    // Foreign key ekle
    try {
        $db->exec("ALTER TABLE quotes ADD FOREIGN KEY (cost_list_id) REFERENCES cost_lists(id) ON DELETE SET NULL");
        echo "✅ cost_list_id için foreign key eklendi.\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️ cost_list_id foreign key zaten mevcut.\n";
        } else {
            throw $e;
        }
    }

    // uploads klasörünü oluştur
    $upload_dir = '../uploads/cost-lists/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✅ uploads/cost-lists klasörü oluşturuldu.\n";
    } else {
        echo "ℹ️ uploads/cost-lists klasörü zaten mevcut.\n";
    }

    echo "\n🎉 Maliyet listesi migration başarıyla tamamlandı!\n\n";
    echo "Artık admin/cost-lists.php sayfasını kullanabilirsiniz.\n";

} catch (Exception $e) {
    echo "❌ Hata oluştu: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>