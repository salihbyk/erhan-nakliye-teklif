<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "Email templates tablosu oluşturuluyor...\n";

    $sql = file_get_contents('create-email-templates.sql');
    $db->exec($sql);

    echo "✅ Email templates tablosu başarıyla oluşturuldu!\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>