<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Veritabanı bağlantısı başarılı.\n";

    // Quotes tablosunun mevcut sütunlarını kontrol et
    $stmt = $db->query("DESCRIBE quotes");
    $columns = $stmt->fetchAll();

    $has_cost_list_id = false;
    $has_cost_list_file = false;

    echo "Quotes tablosundaki mevcut sütunlar:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";

        if ($column['Field'] === 'cost_list_id') {
            $has_cost_list_id = true;
        }
        if ($column['Field'] === 'cost_list_file') {
            $has_cost_list_file = true;
        }
    }

    // cost_list_id alanı yoksa ekle
    if (!$has_cost_list_id) {
        echo "\ncost_list_id alanı bulunamadı, ekleniyor...\n";
        $db->exec("ALTER TABLE quotes ADD COLUMN cost_list_id INT DEFAULT NULL");
        echo "✅ cost_list_id alanı eklendi.\n";

        // Index ekle
        try {
            $db->exec("ALTER TABLE quotes ADD INDEX idx_cost_list (cost_list_id)");
            echo "✅ cost_list_id için index eklendi.\n";
        } catch (Exception $e) {
            echo "ℹ️ cost_list_id index zaten mevcut veya eklenemedi.\n";
        }
    } else {
        echo "\n✅ cost_list_id alanı zaten mevcut.\n";
    }

    // cost_lists tablosunun var olup olmadığını kontrol et
    $stmt = $db->query("SHOW TABLES LIKE 'cost_lists'");
    $table_exists = $stmt->fetch();

    if (!$table_exists) {
        echo "\ncost_lists tablosu bulunamadı, oluşturuluyor...\n";

        $sql = "
        CREATE TABLE cost_lists (
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
        )";

        $db->exec($sql);
        echo "✅ cost_lists tablosu oluşturuldu.\n";
    } else {
        echo "\n✅ cost_lists tablosu zaten mevcut.\n";
    }

    // Foreign key constraint ekle (varsa hata vermez)
    if ($has_cost_list_id || !$has_cost_list_id) {
        try {
            $db->exec("ALTER TABLE quotes ADD FOREIGN KEY (cost_list_id) REFERENCES cost_lists(id) ON DELETE SET NULL");
            echo "✅ cost_list_id için foreign key eklendi.\n";
        } catch (Exception $e) {
            echo "ℹ️ cost_list_id foreign key zaten mevcut.\n";
        }
    }

    // Uploads klasörünü kontrol et
    $upload_dir = __DIR__ . '/../uploads/cost-lists/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✅ uploads/cost-lists klasörü oluşturuldu.\n";
    } else {
        echo "✅ uploads/cost-lists klasörü zaten mevcut.\n";
    }

    echo "\n🎉 Migration tamamlandı!\n";
    echo "Artık admin/view-quote.php sayfasında maliyet listesi dropdown'ı çalışacaktır.\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>