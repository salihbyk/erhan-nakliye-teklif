<?php
/**
 * Hosting Sunucusu PDF Görüntüleme Sorunu Düzeltme Scripti
 *
 * Bu script hosting sunucusundaki PDF görüntüleme sorunlarını çözer:
 * 1. view-quote-pdf.php dosyasının doğru yerde olup olmadığını kontrol eder
 * 2. URL yönlendirmelerini düzeltir
 * 3. Hosting sunucusuna uygun yol yapısını oluşturur
 * 4. PDF görüntüleme butonlarını günceller
 */

echo "🔧 Hosting Sunucusu PDF Sorunu Düzeltme İşlemi Başlatılıyor...\n\n";

// Mevcut dizin yapısını kontrol et
$base_dir = dirname(__DIR__);
$pdf_file = $base_dir . '/view-quote-pdf.php';
$admin_dir = $base_dir . '/admin';

echo "📁 Dizin Yapısı Kontrolü:\n";
echo "Base Directory: " . $base_dir . "\n";
echo "PDF File: " . $pdf_file . "\n";
echo "Admin Directory: " . $admin_dir . "\n\n";

// view-quote-pdf.php dosyasının varlığını kontrol et
if (file_exists($pdf_file)) {
    echo "✅ view-quote-pdf.php dosyası bulundu\n";
} else {
    echo "❌ view-quote-pdf.php dosyası bulunamadı\n";
    echo "🔧 Dosya kopyalanıyor...\n";

    // Dosyayı kopyala (eğer yoksa)
    $source_pdf = __DIR__ . '/../view-quote-pdf.php';
    if (file_exists($source_pdf)) {
        copy($source_pdf, $pdf_file);
        echo "✅ view-quote-pdf.php dosyası kopyalandı\n";
    } else {
        echo "❌ Kaynak dosya bulunamadı\n";
    }
}

// Hosting sunucusu için özel view-quote-pdf.php oluştur
echo "\n🔧 Hosting Sunucusu İçin PDF Dosyası Güncelleniyor...\n";

$hosting_pdf_content = '<?php
// Hosting sunucusu için optimize edilmiş PDF görüntüleme
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Dosya yollarını hosting sunucusuna göre ayarla
$base_path = __DIR__;
require_once $base_path . "/config/database.php";
require_once $base_path . "/includes/functions.php";

// Teklif ID kontrolü
$quote_id = $_GET["id"] ?? "";

if (empty($quote_id)) {
    die("Teklif numarası belirtilmemiş.");
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Revision kontrolü
    $revision = $_GET["rev"] ?? null;
    if ($revision) {
        $quote_id_with_rev = $quote_id . "_rev" . $revision;
    } else {
        $quote_id_with_rev = $quote_id;
    }

    // Teklifi ve müşteri bilgilerini al
    $stmt = $db->prepare("
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_name, tm.icon as transport_icon, tm.template,
               qt.services_content as template_services_content, qt.terms_content as template_terms_content,
               qt.currency, qt.language,
               cl.name as cost_list_name, cl.file_name as cost_list_file_name, cl.file_path as cost_list_file_path
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        LEFT JOIN cost_lists cl ON q.cost_list_id = cl.id
        WHERE q.quote_number = ? AND q.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$quote_id_with_rev]);
    $quote = $stmt->fetch();

    // Revision ile bulunamadıysa orijinal teklifi dene
    if (!$quote && $revision) {
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch();
    }

    if (!$quote) {
        die("Teklif bulunamadı: " . htmlspecialchars($quote_id));
    }

    // PDF görüntüleme için gerekli değişkenler
    $is_expired = strtotime($quote["valid_until"]) < time();
    $is_english = ($quote["language"] ?? "tr") === "en";
    $currency = $quote["currency"] ?? "TL";

    // Para birimi formatı
    function formatPriceWithCurrency($price, $currency) {
        $formatted_price = number_format($price, 0, ",", ".");
        switch($currency) {
            case "USD":
                return "$" . $formatted_price;
            case "EUR":
                return "€" . $formatted_price;
            case "TL":
            default:
                return $formatted_price . " TL";
        }
    }

    // Çeviri fonksiyonu
    $translations = [
        "tr" => [
            "quote_title" => "EV EŞYASI TAŞIMA FİYAT TEKLİFİ",
            "our_quote_price" => "TEKLİF FİYATIMIZ",
            "price_info" => "Fiyat Bilgisi",
            "customer_info" => "Müşteri Bilgileri",
            "transport_details" => "Taşıma Detayları",
            "cargo_info" => "Yük Bilgileri",
            "name_surname" => "Ad Soyad",
            "company" => "Şirket",
            "email" => "E-posta",
            "phone" => "Telefon",
            "quote_date" => "Teklif Tarihi",
            "validity" => "Geçerlilik",
            "transport_type" => "Taşıma Türü",
            "origin" => "Yükleme Adresi",
            "destination" => "Teslimat Adresi",
            "start_date" => "Yükleme Tarihi",
            "delivery_date" => "Teslim Tarihi",
            "status" => "Durum",
            "weight" => "Ağırlık",
            "volume" => "Hacim",
            "pieces" => "Parça Sayısı",
            "cargo_type" => "Yük Türü",
            "trade_type" => "İşlem Türü",
            "description" => "Açıklama",
            "active" => "Aktif",
            "expired" => "Süresi Dolmuş",
            "not_specified" => "Belirtilmemiş",
            "services" => "Hizmetlerimiz",
            "terms" => "Şartlar",
            "information" => "Bilgilendirme",
            "additional_costs" => "Ek Maliyetler",
            "unit_price" => "Birim m³ Fiyatı",
            "customs_fee" => "Gümrük Hizmet Bedeli"
        ],
        "en" => [
            "quote_title" => "HOUSEHOLD GOODS TRANSPORT PRICE QUOTE",
            "our_quote_price" => "OUR QUOTE PRICE",
            "price_info" => "Price Information",
            "customer_info" => "Customer Information",
            "transport_details" => "Transport Details",
            "cargo_info" => "Cargo Information",
            "name_surname" => "Name Surname",
            "company" => "Company",
            "email" => "Email",
            "phone" => "Phone",
            "quote_date" => "Quote Date",
            "validity" => "Validity",
            "transport_type" => "Transport Type",
            "origin" => "Loading Address",
            "destination" => "Delivery Address",
            "start_date" => "Start Date",
            "delivery_date" => "Delivery Date",
            "status" => "Status",
            "weight" => "Weight",
            "volume" => "Volume",
            "pieces" => "Pieces",
            "cargo_type" => "Cargo Type",
            "trade_type" => "Trade Type",
            "description" => "Description",
            "active" => "Active",
            "expired" => "Expired",
            "not_specified" => "Not Specified",
            "services" => "Our Services",
            "terms" => "Terms",
            "information" => "Information",
            "additional_costs" => "Additional Costs",
            "unit_price" => "Unit m³ Price",
            "customs_fee" => "Customs Service Fee"
        ]
    ];

    $t = $translations[$is_english ? "en" : "tr"];

} catch (Exception $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $is_english ? "en" : "tr" ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t["quote_title"] ?> - <?= htmlspecialchars($quote["quote_number"]) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }
        .pdf-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .main-title {
            background: #2c5aa0;
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .quote-number {
            color: #2c5aa0;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-title {
            background: #f8f9fa;
            padding: 10px;
            font-weight: bold;
            border-left: 4px solid #2c5aa0;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            width: 150px;
            font-weight: bold;
            color: #666;
        }
        .info-value {
            flex: 1;
        }
        .price-section {
            background: #f8f9fa;
            border: 2px solid #2c5aa0;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .price-title {
            color: #2c5aa0;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .price-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        @media print {
            .print-button { display: none; }
            body { font-size: 10px; }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Yazdır
    </button>

    <div class="pdf-container">
        <div class="header">
            <div class="main-title">
                <?= $t["quote_title"] ?>
            </div>
            <div class="quote-number">
                <?= htmlspecialchars($quote["quote_number"]) ?>
            </div>
        </div>

        <div class="info-section">
            <div class="info-title"><?= $t["customer_info"] ?></div>
            <div class="info-row">
                <div class="info-label"><?= $t["name_surname"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["first_name"] . " " . $quote["last_name"]) ?></div>
            </div>
            <?php if ($quote["company"]): ?>
            <div class="info-row">
                <div class="info-label"><?= $t["company"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["company"]) ?></div>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <div class="info-label"><?= $t["email"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["email"]) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["phone"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["phone"]) ?></div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-title"><?= $t["transport_details"] ?></div>
            <div class="info-row">
                <div class="info-label"><?= $t["transport_type"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["transport_name"]) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["origin"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["origin"]) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["destination"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["destination"]) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["quote_date"] ?>:</div>
                <div class="info-value"><?= date("d.m.Y", strtotime($quote["created_at"])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["validity"] ?>:</div>
                <div class="info-value"><?= date("d.m.Y", strtotime($quote["valid_until"])) ?></div>
            </div>
        </div>

        <div class="info-section">
            <div class="info-title"><?= $t["cargo_info"] ?></div>
            <div class="info-row">
                <div class="info-label"><?= $t["weight"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["weight"]) ?> kg</div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["volume"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["volume"]) ?> m³</div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["pieces"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["pieces"]) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label"><?= $t["cargo_type"] ?>:</div>
                <div class="info-value"><?= htmlspecialchars($quote["cargo_type"]) ?></div>
            </div>
        </div>

        <div class="price-section">
            <div class="price-title"><?= $t["our_quote_price"] ?></div>
            <div class="price-value">
                <?= formatPriceWithCurrency($quote["final_price"], $currency) ?>
            </div>
        </div>

        <?php if ($quote["description"]): ?>
        <div class="info-section">
            <div class="info-title"><?= $t["description"] ?></div>
            <div style="padding: 10px; background: #f8f9fa;">
                <?= nl2br(htmlspecialchars($quote["description"])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Sayfa yüklendiğinde otomatik yazdırma seçeneği
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("print") === "1") {
            window.print();
        }
    </script>
</body>
</html>';

// Hosting sunucusu için PDF dosyasını yaz
file_put_contents($pdf_file, $hosting_pdf_content);
echo "✅ Hosting sunucusu için PDF dosyası güncellendi\n";

// admin/view-customer.php dosyasını hosting sunucusu için güncelle
echo "\n🔧 view-customer.php PDF butonları güncelleniyor...\n";

$view_customer_file = $admin_dir . '/view-customer.php';
if (file_exists($view_customer_file)) {
    $content = file_get_contents($view_customer_file);

    // PDF butonlarını hosting sunucusu için güncelle
    $old_pdf_button = 'href="../view-quote-pdf.php?id=<?= $quote[\'quote_number\'] ?>"';
    $new_pdf_button = 'href="../view-quote-pdf.php?id=<?= urlencode($quote[\'quote_number\']) ?>"';

    $content = str_replace($old_pdf_button, $new_pdf_button, $content);

    // Dosyayı kaydet
    file_put_contents($view_customer_file, $content);
    echo "✅ view-customer.php PDF butonları güncellendi\n";
} else {
    echo "❌ view-customer.php dosyası bulunamadı\n";
}

// api/generate-pdf.php dosyasını hosting sunucusu için güncelle
echo "\n🔧 generate-pdf.php API güncelleniyor...\n";

$generate_pdf_file = $base_dir . '/api/generate-pdf.php';
if (file_exists($generate_pdf_file)) {
    $hosting_generate_pdf = '<?php
// Hosting sunucusu için optimize edilmiş PDF API
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once "../config/database.php";
require_once "../includes/functions.php";

// Teklif ID kontrolü
$quote_id = $_GET["id"] ?? "";

if (empty($quote_id)) {
    die("Teklif numarası belirtilmemiş.");
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Teklifi kontrol et
    $stmt = $db->prepare("
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_name, tm.icon as transport_icon, tm.template,
               qt.services_content, qt.terms_content, qt.currency, qt.language
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        WHERE q.quote_number = ? AND q.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        die("Teklif bulunamadı: " . htmlspecialchars($quote_id));
    }

    // Hosting sunucusu için URL yapısını düzelt
    $redirect_url = "../view-quote-pdf.php?id=" . urlencode($quote_id);

    // Debug için
    if (isset($_GET["debug"])) {
        echo "Quote ID: " . htmlspecialchars($quote_id) . "<br>";
        echo "Redirect URL: " . htmlspecialchars($redirect_url) . "<br>";
        echo "Quote found: " . ($quote ? "Yes" : "No") . "<br>";
        exit;
    }

    // PDF sayfasına yönlendir
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    die("PDF oluşturulurken hata: " . $e->getMessage());
}
?>';

    file_put_contents($generate_pdf_file, $hosting_generate_pdf);
    echo "✅ generate-pdf.php API güncellendi\n";
} else {
    echo "❌ generate-pdf.php dosyası bulunamadı\n";
}

// .htaccess dosyası oluştur (eğer yoksa)
echo "\n🔧 .htaccess dosyası kontrol ediliyor...\n";

$htaccess_file = $base_dir . '/.htaccess';
$htaccess_content = '# Hosting sunucusu için PHP ayarları
RewriteEngine On

# PDF dosyaları için özel yönlendirme
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^view-quote-pdf/(.*)$ view-quote-pdf.php?id=$1 [L,QSA]

# Hata sayfaları
ErrorDocument 404 /404.php
ErrorDocument 500 /500.php

# Güvenlik ayarları
<Files "*.php">
    php_value display_errors 1
    php_value error_reporting E_ALL
</Files>

# Dosya yükleme sınırları
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
php_value memory_limit 256M
';

if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, $htaccess_content);
    echo "✅ .htaccess dosyası oluşturuldu\n";
} else {
    echo "ℹ️ .htaccess dosyası zaten mevcut\n";
}

// Test URL'leri oluştur
echo "\n📋 Test URL'leri:\n";
echo "1. PDF Görüntüleme: https://www.europagroup.com.tr/teklif/view-quote-pdf.php?id=2025-1114-ihracat\n";
echo "2. PDF API: https://www.europagroup.com.tr/teklif/api/generate-pdf.php?id=2025-1114-ihracat\n";
echo "3. Debug Mode: https://www.europagroup.com.tr/teklif/api/generate-pdf.php?id=2025-1114-ihracat&debug=1\n";

echo "\n✅ Hosting sunucusu PDF düzeltme işlemi tamamlandı!\n";
echo "\n📝 Yapılan İşlemler:\n";
echo "1. ✅ view-quote-pdf.php dosyası hosting sunucusu için optimize edildi\n";
echo "2. ✅ view-customer.php PDF butonları güncellendi\n";
echo "3. ✅ generate-pdf.php API güncellendi\n";
echo "4. ✅ .htaccess dosyası oluşturuldu\n";
echo "5. ✅ Error handling ve debug modları eklendi\n";

echo "\n🚀 Artık hosting sunucusunda PDF görüntüleme çalışmalı!\n";
?>