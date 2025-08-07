<?php
/**
 * Ödeme sistemi için gerekli tabloları oluşturan migration scripti
 *
 * Çalıştırma: php setup/run-payment-system-migration.php
 */

require_once __DIR__ . '/../config/database.php';

echo "🚀 Ödeme sistemi migration scripti başlatılıyor...\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "✅ Veritabanı bağlantısı başarılı\n";

    // 1. payments tablosunu oluştur
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

    // 2. quotes tablosundaki eski payment alanlarını kontrol et
    echo "🔍 quotes tablosundaki payment alanları kontrol ediliyor...\n";
    $stmt = $db->query("DESCRIBE quotes");
    $columns = $stmt->fetchAll();

    $payment_columns = ['payment_status', 'payment_amount', 'payment_date'];
    $existing_payment_columns = [];

    foreach ($columns as $column) {
        if (in_array($column['Field'], $payment_columns)) {
            $existing_payment_columns[] = $column['Field'];
        }
    }

    if (!empty($existing_payment_columns)) {
        echo "📋 Mevcut payment alanları: " . implode(', ', $existing_payment_columns) . "\n";

        // Mevcut payment verilerini yeni sisteme aktar
        echo "🔄 Mevcut payment verilerini yeni sisteme aktarılıyor...\n";

        $stmt = $db->query("
            SELECT id, payment_status, payment_amount, payment_date
            FROM quotes
            WHERE payment_amount > 0 OR payment_status != 'pending'
        ");
        $quotes_with_payments = $stmt->fetchAll();

        foreach ($quotes_with_payments as $quote) {
            if ($quote['payment_amount'] > 0) {
                // Ödeme tipini belirle
                $payment_type = 'kaparo';
                if ($quote['payment_status'] === 'paid') {
                    $payment_type = 'toplam_bakiye';
                } elseif ($quote['payment_status'] === 'partial') {
                    $payment_type = 'ara_odeme';
                }

                // Ödeme kaydını oluştur
                $insert_stmt = $db->prepare("
                    INSERT INTO payments (quote_id, payment_type, amount, currency, payment_date, description)
                    VALUES (?, ?, ?, 'TL', ?, ?)
                ");
                $insert_stmt->execute([
                    $quote['id'],
                    $payment_type,
                    $quote['payment_amount'],
                    $quote['payment_date'] ?: date('Y-m-d'),
                    'Eski sistemden aktarılan ödeme'
                ]);

                echo "   ✅ Teklif #{$quote['id']} için ödeme kaydı oluşturuldu\n";
            }
        }

        echo "✅ Mevcut payment verileri aktarıldı\n";
    }

    // 3. Örnek ödeme verileri oluştur (test için)
    echo "🧪 Test için örnek ödeme verileri oluşturuluyor...\n";

    // İlk aktif teklifi bul
    $stmt = $db->query("SELECT id FROM quotes WHERE is_active = 1 LIMIT 1");
    $quote = $stmt->fetch();

    if ($quote) {
        $quote_id = $quote['id'];

        // Örnek ödemeler
        $sample_payments = [
            [
                'payment_type' => 'kaparo',
                'amount' => 500.00,
                'currency' => 'EUR',
                'payment_date' => date('Y-m-d', strtotime('-10 days')),
                'payment_method' => 'Banka Havalesi',
                'description' => 'İlk kaparo ödemesi'
            ],
            [
                'payment_type' => 'ara_odeme',
                'amount' => 1000.00,
                'currency' => 'EUR',
                'payment_date' => date('Y-m-d', strtotime('-5 days')),
                'payment_method' => 'Kredi Kartı',
                'description' => 'Ara ödeme'
            ]
        ];

        foreach ($sample_payments as $payment) {
            $stmt = $db->prepare("
                INSERT INTO payments (quote_id, payment_type, amount, currency, payment_date, payment_method, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $quote_id,
                $payment['payment_type'],
                $payment['amount'],
                $payment['currency'],
                $payment['payment_date'],
                $payment['payment_method'],
                $payment['description']
            ]);
        }

        echo "✅ Örnek ödeme verileri oluşturuldu\n";
    }

    // 4. Veritabanı durumunu kontrol et
    echo "\n📊 Veritabanı durumu:\n";

    $stmt = $db->query("SELECT COUNT(*) as count FROM payments");
    $payment_count = $stmt->fetch()['count'];
    echo "   - Toplam ödeme kaydı: {$payment_count}\n";

    $stmt = $db->query("
        SELECT payment_type, COUNT(*) as count
        FROM payments
        GROUP BY payment_type
    ");
    $payment_types = $stmt->fetchAll();

    foreach ($payment_types as $type) {
        echo "   - {$type['payment_type']}: {$type['count']} adet\n";
    }

    echo "\n🎉 Ödeme sistemi migration'ı tamamlandı!\n";
    echo "\n📋 Yapılan işlemler:\n";
    echo "   1. payments tablosu oluşturuldu\n";
    echo "   2. Mevcut payment verileri kontrol edildi ve aktarıldı\n";
    echo "   3. Test için örnek veriler oluşturuldu\n";
    echo "   4. Veritabanı durumu kontrol edildi\n";

    echo "\n✅ Artık edit-customer.php sayfasında yeni ödeme sistemi kullanılabilir!\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>