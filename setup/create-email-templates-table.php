<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h2>E-posta Şablonları Tablosu Oluşturuluyor...</h2>";

    // E-posta şablonları tablosunu oluştur
    $sql = "
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_mode_id INT NOT NULL,
            language ENUM('tr', 'en') DEFAULT 'tr',
            currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
            template_name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            email_content TEXT NOT NULL,
            quote_content TEXT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_template (transport_mode_id, language, currency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $db->exec($sql);
    echo "<p>✅ email_templates tablosu başarıyla oluşturuldu</p>";

    echo "<hr><p><strong>✅ Tablo oluşturma işlemi tamamlandı!</strong></p>";
    echo "<p>Şimdi örnek şablonları oluşturmak için: <a href='create-sample-templates.php'>create-sample-templates.php</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>