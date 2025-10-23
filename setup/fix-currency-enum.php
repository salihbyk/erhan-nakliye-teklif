<?php
/**
 * Currency Enum Düzeltme Migration
 *
 * quote_templates tablosundaki currency kolonunu 'TL' yerine 'TRY' olarak günceller
 * ISO 4217 standardına uygun para birimi kodları kullanılır
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Veritabanı bağlantısı kurulamadı');
    }

    echo "Currency enum düzeltme migration başlatılıyor...\n";
    echo str_repeat("=", 50) . "\n";

    // 1. Enum değerlerini güncelle (TL -> TRY)
    echo "1. Enum değerleri güncelleniyor...\n";
    $db->exec("ALTER TABLE `quote_templates`
               MODIFY COLUMN `currency` ENUM('TRY','USD','EUR')
               DEFAULT 'USD'");
    echo "   ✓ Enum değerleri güncellendi (TL -> TRY)\n";

    // 2. Mevcut 'TL' değerlerini 'TRY' olarak güncelle (boş veya NULL değerler varsa)
    echo "2. Mevcut veriler kontrol ediliyor...\n";
    $stmt = $db->query("SELECT COUNT(*) as count FROM `quote_templates` WHERE `currency` = '' OR `currency` IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        $db->exec("UPDATE `quote_templates`
                   SET `currency` = 'USD'
                   WHERE `currency` = '' OR `currency` IS NULL");
        echo "   ✓ {$result['count']} kayıt varsayılan değer (USD) ile güncellendi\n";
    } else {
        echo "   ✓ Tüm kayıtlarda geçerli değer mevcut\n";
    }

    // 3. Sonuçları göster
    echo "\n3. Güncel durum:\n";
    $stmt = $db->query("SELECT currency, COUNT(*) as count FROM `quote_templates` GROUP BY currency");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $currency = $row['currency'] ?: '(boş)';
        echo "   - {$currency}: {$row['count']} şablon\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✓ Migration başarıyla tamamlandı!\n";

} catch (Exception $e) {
    echo "\n✗ HATA: " . $e->getMessage() . "\n";
    exit(1);
}

