<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Custom fields sütunu ekleniyor...\n";

    // Önce sütunun var olup olmadığını kontrol et
    $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields'");
    $stmt->execute();
    $column_exists = $stmt->fetch();

    if ($column_exists) {
        echo "Custom_fields sütunu zaten mevcut.\n";
    } else {
        // Sütunu ekle
        $sql = "ALTER TABLE quotes
                ADD COLUMN custom_fields TEXT
                COMMENT 'JSON formatında özel alanları saklar'
                AFTER show_reference_images";

        $db->exec($sql);
        echo "Custom_fields sütunu başarıyla eklendi.\n";

        // Varolan kayıtlar için varsayılan değer
        $sql2 = "UPDATE quotes SET custom_fields = '{}' WHERE custom_fields IS NULL";
        $db->exec($sql2);
        echo "Varolan kayıtlar için varsayılan değerler atandı.\n";
    }

    echo "Migrasyon tamamlandı!\n";

} catch (Exception $e) {
    echo "Hata: " . $e->getMessage() . "\n";
}
?>