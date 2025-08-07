<?php
/**
 * Sunucudaki view-customer.php hatasını çözmek için gelişmiş düzeltme scripti
 *
 * Hata: {"success":false,"message":"Teklif ID gerekli"}
 * Bu hata API çağrısından geliyor, view-customer.php sayfasını düzeltmek gerekiyor
 *
 * Çalıştırma: php setup/fix-view-customer-server-v2.php
 */

require_once __DIR__ . '/../config/database.php';

echo "🔧 Sunucu view-customer.php hatasını düzeltme scripti v2 başlatılıyor...\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "✅ Veritabanı bağlantısı başarılı\n";

    // 1. Önce mevcut view-customer.php dosyasını yedekle
    $source_file = __DIR__ . '/../admin/view-customer.php';
    $backup_file = __DIR__ . '/../admin/view-customer-backup-' . date('Y-m-d-H-i-s') . '.php';

    if (file_exists($source_file)) {
        copy($source_file, $backup_file);
        echo "✅ view-customer.php dosyası yedeklendi: " . basename($backup_file) . "\n";
    }

    // 2. Gerekli tabloları kontrol et ve oluştur
    $tables_to_check = [
        'customers' => "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(25) NOT NULL,
            company VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'quotes' => "CREATE TABLE IF NOT EXISTS quotes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            quote_number VARCHAR(20) UNIQUE NOT NULL,
            customer_id INT NOT NULL,
            transport_mode_id INT NOT NULL,
            origin VARCHAR(255) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            weight DECIMAL(10,2) NOT NULL,
            volume DECIMAL(10,3),
            pieces INT,
            cargo_type ENUM('genel', 'hassas', 'soguk', 'tehlikeli'),
            description TEXT,
            calculated_price DECIMAL(10,2),
            final_price DECIMAL(10,2),
            status ENUM('pending', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
            valid_until DATE,
            notes TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        'transport_modes' => "CREATE TABLE IF NOT EXISTS transport_modes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            icon VARCHAR(100),
            base_price DECIMAL(10,2) DEFAULT 0.00,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        'admin_users' => "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            role ENUM('admin', 'manager', 'operator') DEFAULT 'operator',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables_to_check as $table_name => $create_sql) {
        $stmt = $db->query("SHOW TABLES LIKE '$table_name'");
        if (!$stmt->fetch()) {
            echo "➕ $table_name tablosu oluşturuluyor...\n";
            $db->exec($create_sql);
            echo "✅ $table_name tablosu oluşturuldu\n";
        } else {
            echo "✅ $table_name tablosu zaten mevcut\n";
        }
    }

    // 3. Örnek veri oluştur
    echo "\n📊 Örnek veriler kontrol ediliyor...\n";

    // Transport modes
    $stmt = $db->query("SELECT COUNT(*) as count FROM transport_modes");
    $transport_count = $stmt->fetch()['count'];

    if ($transport_count == 0) {
        echo "➕ Örnek taşıma modları oluşturuluyor...\n";
        $transport_modes = [
            ['Karayolu', 'karayolu', 'fas fa-truck'],
            ['Havayolu', 'havayolu', 'fas fa-plane'],
            ['Deniz Yolu', 'denizyolu', 'fas fa-ship']
        ];

        foreach ($transport_modes as $mode) {
            $stmt = $db->prepare("INSERT INTO transport_modes (name, slug, icon) VALUES (?, ?, ?)");
            $stmt->execute($mode);
        }
        echo "✅ Örnek taşıma modları oluşturuldu\n";
    }

    // Admin user
    $stmt = $db->query("SELECT COUNT(*) as count FROM admin_users");
    $admin_count = $stmt->fetch()['count'];

    if ($admin_count == 0) {
        echo "➕ Örnek admin kullanıcısı oluşturuluyor...\n";
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@europatrans.com', $password_hash, 'Admin User', 'admin']);
        echo "✅ Örnek admin kullanıcısı oluşturuldu (kullanıcı: admin, şifre: admin123)\n";
    }

    // Customer
    $stmt = $db->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];

    if ($customer_count == 0) {
        echo "➕ Örnek müşteri oluşturuluyor...\n";
        $stmt = $db->prepare("INSERT INTO customers (first_name, last_name, email, phone, company) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Test', 'Müşteri', 'test@example.com', '+90 555 123 4567', 'Test Şirketi']);
        echo "✅ Örnek müşteri oluşturuldu\n";
    }

    // Quote
    $stmt = $db->query("SELECT COUNT(*) as count FROM quotes");
    $quote_count = $stmt->fetch()['count'];

    if ($quote_count == 0) {
        echo "➕ Örnek teklif oluşturuluyor...\n";
        $stmt = $db->prepare("
            INSERT INTO quotes (quote_number, customer_id, transport_mode_id, origin, destination, weight, final_price, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(['2025-0001-test', 1, 1, 'İstanbul', 'Ankara', 1000.00, 1500.00, 'pending']);
        echo "✅ Örnek teklif oluşturuldu\n";
    }

    // 4. view-customer.php dosyasını temizle ve düzelt
    echo "\n🔧 view-customer.php dosyası düzeltiliyor...\n";

    $view_customer_content = '<?php
session_start();
require_once \'../config/database.php\';

// Error reporting ayarları
error_reporting(E_ALL);
ini_set(\'display_errors\', 0); // Production için kapalı

// Admin kontrolü
if (!isset($_SESSION[\'admin_id\'])) {
    header(\'Location: login.php\');
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Müşteri ID kontrolü
    if (!isset($_GET[\'id\']) || !is_numeric($_GET[\'id\'])) {
        header(\'Location: customers.php\');
        exit();
    }

    $customer_id = (int)$_GET[\'id\'];

    // Müşteri bilgilerini getir
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        header(\'Location: customers.php\');
        exit();
    }

    // Müşterinin tekliflerini getir
    $quotes_stmt = $db->prepare("
        SELECT
            q.*,
            tm.name as transport_mode_name,
            tm.icon as transport_mode_icon
        FROM quotes q
        LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
        WHERE q.customer_id = ? AND (q.is_active IS NULL OR q.is_active = 1)
        ORDER BY q.created_at DESC
    ");
    $quotes_stmt->execute([$customer_id]);
    $quotes = $quotes_stmt->fetchAll();

    // Müşteri istatistikleri
    $stats_stmt = $db->prepare("
        SELECT
            COUNT(*) as total_quotes,
            COUNT(CASE WHEN status = \'accepted\' THEN 1 END) as approved_quotes,
            COUNT(CASE WHEN status = \'pending\' THEN 1 END) as pending_quotes,
            COUNT(CASE WHEN status = \'rejected\' THEN 1 END) as rejected_quotes,
            COALESCE(SUM(CASE WHEN status = \'accepted\' THEN final_price ELSE 0 END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN status = \'accepted\' THEN final_price ELSE NULL END), 0) as avg_quote_value
        FROM quotes
        WHERE customer_id = ? AND (is_active IS NULL OR is_active = 1)
    ");
    $stats_stmt->execute([$customer_id]);
    $stats = $stats_stmt->fetch();

    // Taşıma modları dağılımı
    $transport_stats = $db->prepare("
        SELECT
            COALESCE(tm.name, \'Bilinmiyor\') as name,
            COALESCE(tm.icon, \'fas fa-question\') as icon,
            COUNT(*) as quote_count
        FROM quotes q
        LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
        WHERE q.customer_id = ? AND (q.is_active IS NULL OR q.is_active = 1)
        GROUP BY tm.id, tm.name, tm.icon
        ORDER BY quote_count DESC
    ");
    $transport_stats->execute([$customer_id]);
    $transport_modes = $transport_stats->fetchAll();

} catch (Exception $e) {
    error_log("view-customer.php error: " . $e->getMessage());
    die("Bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
}

function getStatusBadge($status) {
    switch($status) {
        case \'pending\':
            return \'<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Bekliyor</span>\';
        case \'accepted\':
            return \'<span class="badge bg-success"><i class="fas fa-check"></i> Onaylandı</span>\';
        case \'approved\':
            return \'<span class="badge bg-success"><i class="fas fa-check"></i> Onaylandı</span>\';
        case \'rejected\':
            return \'<span class="badge bg-danger"><i class="fas fa-times"></i> Reddedildi</span>\';
        case \'sent\':
            return \'<span class="badge bg-info"><i class="fas fa-paper-plane"></i> Gönderildi</span>\';
        default:
            return \'<span class="badge bg-secondary">\' . htmlspecialchars($status) . \'</span>\';
    }
}

function formatPrice($price) {
    return number_format($price, 2, \',\', \'.\') . \' ₺\';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer[\'first_name\'] . \' \' . $customer[\'last_name\']) ?> - Müşteri Detay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #ffc107;
            --light-color: #f8f9fa;
        }

        body {
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-color);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a4a87 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }

        .customer-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .customer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary-color), #1a4a87);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
            margin-right: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .stat-icon.total { background: linear-gradient(135deg, var(--primary-color), #1a4a87); }
        .stat-icon.approved { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .stat-icon.pending { background: linear-gradient(135deg, #fd7e14, #e55a1c); }
        .stat-icon.revenue { background: linear-gradient(135deg, #17a2b8, #138496); }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table thead th {
            background: var(--light-color);
            border: none;
            color: #495057;
            font-weight: 600;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .quote-number {
            font-family: \'Courier New\', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 2px;
            border: none;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            color: white;
            transform: translateY(-1px);
        }

        .btn-edit {
            background: #fd7e14;
            color: white;
        }

        .btn-edit:hover {
            background: #e55a1c;
            color: white;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-user-shield"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Ana Sayfa</a>
                <a class="nav-link" href="quotes.php">Teklifler</a>
                <a class="nav-link" href="customers.php">Müşteriler</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Back Button -->
        <div class="my-3">
            <a href="customers.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Müşteriler Listesi
            </a>
        </div>

        <!-- Customer Header -->
        <div class="customer-header">
            <div class="d-flex align-items-center">
                <div class="customer-avatar">
                    <?= strtoupper(substr($customer[\'first_name\'], 0, 1) . substr($customer[\'last_name\'], 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <h2 class="mb-1"><?= htmlspecialchars($customer[\'first_name\'] . \' \' . $customer[\'last_name\']) ?></h2>
                    <div class="text-muted mb-2">
                        Müşteri #<?= $customer[\'id\'] ?>
                        <?php if ($customer[\'company\']): ?>
                            | <?= htmlspecialchars($customer[\'company\']) ?>
                        <?php endif; ?>
                        | Kayıt: <?= date(\'d.m.Y\', strtotime($customer[\'created_at\'])) ?>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <span><?= htmlspecialchars($customer[\'email\']) ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone text-success me-2"></i>
                            <span><?= htmlspecialchars($customer[\'phone\']) ?></span>
                        </div>
                        <?php if ($customer[\'company\']): ?>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-building text-info me-2"></i>
                            <span><?= htmlspecialchars($customer[\'company\']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 class="mb-0"><?= $stats[\'total_quotes\'] ?></h3>
                <p class="text-muted mb-0">Toplam Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="mb-0"><?= $stats[\'approved_quotes\'] ?></h3>
                <p class="text-muted mb-0">Onaylı Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="mb-0"><?= $stats[\'pending_quotes\'] ?></h3>
                <p class="text-muted mb-0">Bekleyen Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-lira-sign"></i>
                </div>
                <h3 class="mb-0"><?= formatPrice($stats[\'total_revenue\']) ?></h3>
                <p class="text-muted mb-0">Toplam Gelir</p>
            </div>
        </div>

        <!-- Taşıma Modları -->
        <?php if (!empty($transport_modes)): ?>
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-shipping-fast"></i>
                Kullanılan Taşıma Modları
            </h4>
            <div class="row">
                <?php foreach ($transport_modes as $mode): ?>
                <div class="col-md-4 col-sm-6 mb-3">
                    <div class="d-flex align-items-center p-3 bg-light rounded">
                        <i class="<?= htmlspecialchars($mode[\'icon\']) ?> text-primary me-3 fs-4"></i>
                        <div>
                            <strong><?= htmlspecialchars($mode[\'name\']) ?></strong>
                            <br>
                            <small class="text-muted"><?= $mode[\'quote_count\'] ?> teklif</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Teklifler Listesi -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-list"></i>
                Teklif Geçmişi
            </h4>

            <?php if (!empty($quotes)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Teklif No</th>
                            <th>Taşıma Modu</th>
                            <th>Güzergah</th>
                            <th>Durum</th>
                            <th>Fiyat</th>
                            <th>Tarih</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <span class="quote-number"><?= htmlspecialchars($quote[\'quote_number\']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="<?= htmlspecialchars($quote[\'transport_mode_icon\'] ?? \'fas fa-question\') ?> text-primary me-2"></i>
                                    <span><?= htmlspecialchars($quote[\'transport_mode_name\'] ?? \'Bilinmiyor\') ?></span>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($quote[\'origin\']) ?></strong>
                                    <br>
                                    <i class="fas fa-arrow-down text-muted"></i>
                                    <br>
                                    <strong><?= htmlspecialchars($quote[\'destination\']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <?= getStatusBadge($quote[\'status\']) ?>
                            </td>
                            <td>
                                <?php if ($quote[\'final_price\'] > 0): ?>
                                    <strong class="text-success"><?= formatPrice($quote[\'final_price\']) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Hesaplanıyor...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date(\'d.m.Y H:i\', strtotime($quote[\'created_at\'])) ?>
                                </small>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if ($quote[\'status\'] === \'accepted\' || $quote[\'status\'] === \'approved\'): ?>
                                        <a href="../view-quote-pdf.php?id=<?= urlencode($quote[\'quote_number\']) ?>" target="_blank" class="btn btn-action btn-view">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </a>
                                    <?php endif; ?>
                                    <a href="view-quote.php?id=<?= urlencode($quote[\'quote_number\']) ?>" class="btn btn-action btn-view">
                                        <i class="fas fa-eye"></i> Görüntüle
                                    </a>
                                    <a href="edit-customer.php?id=<?= $customer[\'id\'] ?>" class="btn btn-action btn-edit">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-file-alt text-muted" style="font-size: 4rem;"></i>
                <h5 class="text-muted mt-3">Henüz teklif bulunmuyor</h5>
                <p class="text-muted">Bu müşteriye ait herhangi bir teklif bulunamadı.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sadece gerekli animasyonlar - API çağrısı yok
        document.addEventListener(\'DOMContentLoaded\', function() {
            const cards = document.querySelectorAll(\'.stat-card\');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = \'0\';
                    card.style.transform = \'translateY(20px)\';
                    card.style.transition = \'all 0.5s ease\';

                    setTimeout(() => {
                        card.style.opacity = \'1\';
                        card.style.transform = \'translateY(0)\';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>';

    // Dosyayı yaz
    file_put_contents($source_file, $view_customer_content);
    echo "✅ view-customer.php dosyası güncellendi\n";

    // 5. Dosya izinlerini kontrol et
    if (is_readable($source_file) && is_writable($source_file)) {
        echo "✅ Dosya izinleri uygun\n";
    } else {
        echo "⚠️ Dosya izinleri kontrol edilmeli\n";
    }

    // 6. Test verilerini kontrol et
    echo "\n📊 Test verileri:\n";

    $stmt = $db->query("SELECT COUNT(*) as count FROM customers");
    $customer_count = $stmt->fetch()['count'];
    echo "   - Müşteri sayısı: {$customer_count}\n";

    $stmt = $db->query("SELECT COUNT(*) as count FROM quotes");
    $quote_count = $stmt->fetch()['count'];
    echo "   - Teklif sayısı: {$quote_count}\n";

    $stmt = $db->query("SELECT COUNT(*) as count FROM transport_modes");
    $transport_count = $stmt->fetch()['count'];
    echo "   - Taşıma modu sayısı: {$transport_count}\n";

    $stmt = $db->query("SELECT COUNT(*) as count FROM admin_users");
    $admin_count = $stmt->fetch()['count'];
    echo "   - Admin kullanıcı sayısı: {$admin_count}\n";

    echo "\n🎉 Düzeltme scripti tamamlandı!\n";
    echo "\n📋 Yapılan işlemler:\n";
    echo "   1. Mevcut dosya yedeklendi\n";
    echo "   2. Gerekli tablolar kontrol edildi/oluşturuldu\n";
    echo "   3. Örnek veriler oluşturuldu\n";
    echo "   4. view-customer.php dosyası temizlendi ve güncellendi\n";
    echo "   5. Dosya izinleri kontrol edildi\n";
    echo "   6. Test verileri hazırlandı\n";

    echo "\n✅ Artık view-customer.php sayfası sunucuda çalışmalıdır!\n";
    echo "\n🔗 Test etmek için: https://www.europagroup.com.tr/teklif/admin/view-customer.php?id=1\n";
    echo "\n🔑 Admin giriş bilgileri:\n";
    echo "   - Kullanıcı: admin\n";
    echo "   - Şifre: admin123\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>';

    // Dosyayı kaydet
    file_put_contents(__DIR__ . '/fix-view-customer-server-v2.php', $view_customer_content);
    echo "✅ Düzeltme scripti oluşturuldu: setup/fix-view-customer-server-v2.php\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>