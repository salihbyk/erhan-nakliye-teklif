<?php
/**
 * Güncelleme sistemi migration scripti
 */

require_once __DIR__ . '/../config/database.php';

try {
    echo "Güncelleme sistemi migration'ı başlatılıyor...\n";

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Veritabanı bağlantısı başarısız');
    }

    // SQL dosyasını oku
    $sqlFile = __DIR__ . '/create-update-system.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL dosyası bulunamadı: ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if (empty($sql)) {
        throw new Exception('SQL dosyası boş');
    }

    // SQL komutlarını ayır ve çalıştır
    $statements = array_filter(
        explode(';', $sql),
        function($stmt) {
            return !empty(trim($stmt)) && !preg_match('/^\s*--/', trim($stmt));
        }
    );

    $executedCount = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        try {
            $db->exec($statement);
            $executedCount++;
            echo "✓ SQL komutu çalıştırıldı\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "~ Tablo zaten mevcut, atlanıyor\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n✓ Migration başarıyla tamamlandı!\n";
    echo "Toplam çalıştırılan komut sayısı: $executedCount\n";

    // Sistem versiyonunu kontrol et
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_version'");
    $stmt->execute();
    $version = $stmt->fetch();

    if ($version) {
        echo "Mevcut sistem versiyonu: " . $version['setting_value'] . "\n";
    }

} catch (Exception $e) {
    echo "✗ Hata: " . $e->getMessage() . "\n";
    echo "Dosya: " . $e->getFile() . "\n";
    echo "Satır: " . $e->getLine() . "\n";
    exit(1);
}
?>
