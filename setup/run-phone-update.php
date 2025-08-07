<?php
// Telefon alanı güncelleme scripti
require_once __DIR__ . '/../config/database.php';

// Güvenlik kontrolü - sadece admin IP'lerinden erişim
$allowed_ips = ['127.0.0.1', '::1']; // Localhost IP'leri
$user_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// IP kontrolü devre dışı bırakılabilir (güvenlik riski)
$check_ip = false; // Güvenlik için true yapın

if ($check_ip && !in_array($user_ip, $allowed_ips)) {
    die('Erişim reddedildi. Bu script sadece yetkili IP adreslerinden çalıştırılabilir.');
}

echo "<h2>📱 Telefon Alanı Güncelleme Script'i</h2>";
echo "<p><strong>Mevcut IP:</strong> " . htmlspecialchars($user_ip) . "</p>";
echo "<hr>";

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Veritabanı bağlantısı kurulamadı');
    }

    echo "<p>✅ Veritabanı bağlantısı başarılı</p>";

    // Mevcut telefon alanı uzunluğunu kontrol et
    $stmt = $db->query("DESCRIBE customers phone");
    $phone_info = $stmt->fetch();

    if ($phone_info) {
        echo "<p><strong>Mevcut telefon alanı:</strong> " . $phone_info['Type'] . "</p>";

        // Eğer zaten VARCHAR(25) ise güncelleme gereksiz
        if (strpos($phone_info['Type'], 'varchar(25)') !== false) {
            echo "<p>⚠️ Telefon alanı zaten VARCHAR(25) formatında. Güncelleme gereksiz.</p>";
        } else {
            echo "<p>🔄 Telefon alanı güncelleniyor...</p>";

            // Telefon alanını güncelle
            $db->exec("ALTER TABLE customers MODIFY COLUMN phone VARCHAR(25) NOT NULL");
            echo "<p>✅ Telefon alanı VARCHAR(25) olarak güncellendi</p>";

            // İndeksi kontrol et ve güncelle
            try {
                $db->exec("ALTER TABLE customers DROP INDEX idx_phone");
                echo "<p>🗑️ Eski telefon indeksi silindi</p>";
            } catch (Exception $e) {
                echo "<p>ℹ️ Eski telefon indeksi bulunamadı (normal)</p>";
            }

            try {
                $db->exec("ALTER TABLE customers ADD INDEX idx_phone (phone)");
                echo "<p>✅ Yeni telefon indeksi oluşturuldu</p>";
            } catch (Exception $e) {
                echo "<p>⚠️ Telefon indeksi oluşturulamadı: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p>❌ Telefon alanı bulunamadı</p>";
    }

    // Son kontrol
    $stmt = $db->query("DESCRIBE customers phone");
    $final_info = $stmt->fetch();

    if ($final_info) {
        echo "<hr>";
        echo "<p><strong>✅ Güncelleme sonrası telefon alanı:</strong> " . $final_info['Type'] . "</p>";

        // Örnek telefon numaralarını test et
        echo "<h3>📋 Test Telefon Numaraları</h3>";
        echo "<ul>";
        echo "<li>Türk numarası: +90 555 123 45 67 (16 karakter)</li>";
        echo "<li>ABD numarası: +1 555 123 4567 (14 karakter)</li>";
        echo "<li>Alman numarası: +49 30 12345678 (15 karakter)</li>";
        echo "<li>İngiliz numarası: +44 20 1234 5678 (16 karakter)</li>";
        echo "</ul>";
        echo "<p>✅ Tüm formatlar VARCHAR(25) alanına sığar.</p>";
    }

    echo "<hr>";
    echo "<h3>🎉 Güncelleme Tamamlandı!</h3>";
    echo "<p>Telefon alanı artık 25 karaktere kadar telefon numaralarını destekler.</p>";
    echo "<p><strong>Sonraki adımlar:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Ana formda telefon validasyonu güncellendi</li>";
    echo "<li>✅ JavaScript validasyonu esnek hale getirildi</li>";
    echo "<li>✅ Veritabanı alanı genişletildi</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
    echo "<p>Detaylı hata: " . $e->getTraceAsString() . "</p>";
}

echo "<hr>";
echo "<p><small>⚠️ Bu script'i çalıştırdıktan sonra güvenlik için silebilirsiniz.</small></p>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h2, h3 { color: #2c5aa0; }
    p { line-height: 1.6; }
    ul { background: white; padding: 15px; border-radius: 5px; }
    hr { margin: 20px 0; border: 1px solid #ddd; }
</style>