<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "custom_fields_order sütunu ekleniyor...\n";

    // Önce sütunun var olup olmadığını kontrol et
    $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields_order'");
    $stmt->execute();
    $column_exists = $stmt->fetch();

    if ($column_exists) {
        echo "custom_fields_order sütunu zaten mevcut.\n";
    } else {
        // Sütunu ekle
        $sql = "ALTER TABLE quotes
                ADD COLUMN custom_fields_order TEXT
                COMMENT 'JSON formatında özel alanların sırasını saklar'
                AFTER custom_fields";

        $db->exec($sql);
        echo "custom_fields_order sütunu başarıyla eklendi.\n";

        // Varolan kayıtlar için varsayılan değer
        $sql2 = "UPDATE quotes SET custom_fields_order = '[]' WHERE custom_fields_order IS NULL";
        $db->exec($sql2);
        echo "Varsayılan değerler ayarlandı.\n";
    }

    echo "\nMigration başarıyla tamamlandı!\n";

} catch (Exception $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    exit(1);
}
