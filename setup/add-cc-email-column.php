<?php
/**
 * CC Email Kolonu Ekleme Migration
 *
 * customers tablosuna cc_email (carbon copy email) kolonu ekler
 * Mail gönderilirken bu adrese de kopya gönderilmesi için kullanılır
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Veritabanı bağlantısı kurulamadı');
    }

    echo "CC Email kolonu ekleme migration başlatılıyor...\n";
    echo str_repeat("=", 50) . "\n";

    // cc_email kolonu var mı kontrol et
    $stmt = $db->query("SHOW COLUMNS FROM customers LIKE 'cc_email'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "✓ cc_email kolonu zaten mevcut\n";
    } else {
        echo "1. cc_email kolonu ekleniyor...\n";
        $db->exec("ALTER TABLE `customers`
                   ADD COLUMN `cc_email` VARCHAR(255) NULL DEFAULT NULL
                   AFTER `email`");
        echo "   ✓ cc_email kolonu eklendi\n";
    }

    // Sonuçları göster
    echo "\n2. Güncel customers tablosu yapısı:\n";
    $stmt = $db->query("SHOW COLUMNS FROM customers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $marker = $col['Field'] === 'cc_email' ? '→ ' : '  ';
        echo "{$marker}{$col['Field']} - {$col['Type']}\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✓ Migration başarıyla tamamlandı!\n";

} catch (Exception $e) {
    echo "\n✗ HATA: " . $e->getMessage() . "\n";
    exit(1);
}

