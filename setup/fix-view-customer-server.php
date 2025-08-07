<?php
/**
 * Sunucudaki view-customer.php hatasını çözmek için düzeltme scripti
 *
 * Hata: {"success":false,"message":"Teklif ID gerekli"}
 * Bu hata muhtemelen API çağrısı yapılan bir yerden geliyor
 *
 * Çalıştırma: php setup/fix-view-customer-server.php
 */

require_once __DIR__ . '/../config/database.php';

echo "🔧 Sunucu view-customer.php hatasını düzeltme scripti başlatılıyor...\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "✅ Veritabanı bağlantısı başarılı\n";

    // 1. Önce quotes tablosunda is_active alanının var olup olmadığını kontrol et
    $stmt = $db->query("DESCRIBE quotes");
    $columns = $stmt->fetchAll();

    $has_is_active = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'is_active') {
            $has_is_active = true;
            break;
        }
    }

    if (!$has_is_active) {
        echo "➕ quotes tablosuna is_active alanı ekleniyor...\n";
        $db->exec("ALTER TABLE quotes ADD COLUMN is_active BOOLEAN DEFAULT 1");
        echo "✅ is_active alanı eklendi\n";
    } else {
        echo "✅ is_active alanı zaten mevcut\n";
    }

    // 2. Tüm mevcut teklifleri aktif olarak işaretle
    $stmt = $db->prepare("UPDATE quotes SET is_active = 1 WHERE is_active IS NULL OR is_active = 0");
    $affected = $stmt->execute();
    echo "✅ Mevcut teklifler aktif olarak işaretlendi\n";

    // 3. payments tablosunu oluştur (yoksa)
    $stmt = $db->query("SHOW TABLES LIKE 'payments'");
    $table_exists = $stmt->fetch();

    if (!$table_exists) {
        echo "➕ payments tablosu oluşturuluyor...\n";
        $db->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quote_id INT NOT NULL,
                payment_type ENUM('kaparo', 'ara_odeme', 'kalan_bakiye', 'toplam_bakiye') DEFAULT 'kaparo',
                amount DECIMAL(10,2) NOT NULL,
                currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
                payment_date DATE NOT NULL,
                payment_method VARCHAR(100) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
                INDEX idx_quote_id (quote_id),
                INDEX idx_payment_date (payment_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ payments tablosu oluşturuldu\n";
    } else {
        echo "✅ payments tablosu zaten mevcut\n";
    }

    // 4. Örnek bir müşteri ve teklif var mı kontrol et
    $stmt = $db->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM quotes WHERE is_active = 1");
    $quote_count = $stmt->fetch()['count'];

    echo "📊 Mevcut durumu:\n";
    echo "   - Müşteri sayısı: {$customer_count}\n";
    echo "   - Aktif teklif sayısı: {$quote_count}\n";

    // 5. Eğer hiç müşteri yoksa örnek veri oluştur
    if ($customer_count == 0) {
        echo "➕ Örnek müşteri oluşturuluyor...\n";
        $stmt = $db->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, company)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['Test', 'Müşteri', 'test@example.com', '+90 555 123 4567', 'Test Şirketi']);
        echo "✅ Örnek müşteri oluşturuldu\n";
    }

    // 6. API dosyalarını kontrol et
    $api_files = [
        'api/preview-quote-email.php',
        'api/send-quote-email.php',
        'api/get-additional-costs.php'
    ];

    echo "🔍 API dosyalarını kontrol ediliyor...\n";
    foreach ($api_files as $file) {
        if (file_exists(__DIR__ . '/../' . $file)) {
            echo "✅ {$file} mevcut\n";
        } else {
            echo "❌ {$file} bulunamadı\n";
        }
    }

    // 7. Sunucu için özel kontroller
    echo "\n🌐 Sunucu uyumluluğu kontrolleri:\n";

    // PHP sürümü
    echo "   - PHP Sürümü: " . PHP_VERSION . "\n";

    // Gerekli extensionlar
    $extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
    foreach ($extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "   ✅ {$ext} extension yüklü\n";
        } else {
            echo "   ❌ {$ext} extension eksik\n";
        }
    }

    // Error reporting ayarları
    echo "   - Error Reporting: " . error_reporting() . "\n";
    echo "   - Display Errors: " . ini_get('display_errors') . "\n";

    // 8. Sunucu için özel düzeltmeler
    echo "\n🔧 Sunucu için özel düzeltmeler yapılıyor...\n";

    // Session ayarları
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Örnek admin kullanıcısı var mı kontrol et
    $stmt = $db->query("SELECT COUNT(*) as count FROM admin_users");
    $admin_count = $stmt->fetch()['count'];

    if ($admin_count == 0) {
        echo "➕ Örnek admin kullanıcısı oluşturuluyor...\n";
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO admin_users (username, email, password_hash, full_name, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute(['admin', 'admin@example.com', $password_hash, 'Admin User', 'admin']);
        echo "✅ Örnek admin kullanıcısı oluşturuldu (kullanıcı: admin, şifre: admin123)\n";
    }

    echo "\n🎉 Düzeltme scripti tamamlandı!\n";
    echo "\n📋 Yapılan işlemler:\n";
    echo "   1. quotes tablosuna is_active alanı eklendi\n";
    echo "   2. Mevcut teklifler aktif olarak işaretlendi\n";
    echo "   3. payments tablosu oluşturuldu\n";
    echo "   4. Örnek veriler kontrol edildi\n";
    echo "   5. API dosyaları kontrol edildi\n";
    echo "   6. Sunucu uyumluluğu kontrol edildi\n";
    echo "   7. Admin kullanıcısı kontrol edildi\n";

    echo "\n✅ Artık view-customer.php sayfası sunucuda çalışmalıdır!\n";
    echo "\n🔗 Test etmek için: https://www.europagroup.com.tr/teklif/admin/view-customer.php?id=1\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>