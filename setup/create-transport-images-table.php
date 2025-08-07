<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Transport referans resimleri tablosunu oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS transport_reference_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_mode_id INT NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            image_description TEXT,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE CASCADE,
            INDEX idx_transport_mode (transport_mode_id),
            INDEX idx_active (is_active),
            INDEX idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ transport_reference_images tablosu başarıyla oluşturuldu!\n";

    // Test için transport_modes tablosunu kontrol edelim
    $stmt = $db->prepare("SELECT id, name, slug FROM transport_modes WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $modes = $stmt->fetchAll();

    echo "\n📋 Mevcut Transport Modes:\n";
    foreach ($modes as $mode) {
        echo "- ID: {$mode['id']}, Name: {$mode['name']}, Slug: {$mode['slug']}\n";
    }

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>