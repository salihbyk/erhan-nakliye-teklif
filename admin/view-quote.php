<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

checkAdminSession();

$quote_id = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($quote_id)) {
    die('Teklif numarası belirtilmemiş.');
}

// AJAX inline editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $field = isset($_POST['field']) ? $_POST['field'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $quote_id = $_GET['id'] ?? null;
    $quote_number = isset($_POST['quote_number']) ? $_POST['quote_number'] : $quote_id;

    if ($action === 'update_field' && $field) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Güvenlik kontrolü - sadece belirli alanları güncellemeye izin ver
            $customer_fields = ['first_name', 'last_name', 'email', 'phone', 'company'];
            $quote_fields = ['origin', 'destination', 'weight', 'volume', 'pieces', 'cargo_type', 'trade_type', 'description', 'final_price', 'notes', 'services_content', 'optional_services_content', 'terms_content', 'unit_price', 'transport_process_text', 'start_date', 'delivery_date', 'additional_section1_title', 'additional_section1_content', 'additional_section2_title', 'additional_section2_content', 'transport_mode_id', 'custom_transport_name', 'intro_text', 'greeting_text', 'container_type', 'show_reference_images'];

            // Özel alanlar - bunlar için özel handling gerekiyor
            $special_fields = ['quote_price_label', 'quote_date', 'validity', 'transport_type',
                              'custom_services_title', 'custom_transport_process_title', 'custom_terms_title',
                              'custom_section_title'];

            if (in_array($field, $customer_fields)) {
                // Müşteri bilgilerini güncelle
                $stmt = $db->prepare("
                    UPDATE customers c
                    JOIN quotes q ON c.id = q.customer_id
                    SET c.$field = ?
                    WHERE q.quote_number = ?
                ");
                $stmt->execute([$value, $quote_id]);
            } elseif (in_array($field, $quote_fields)) {
                // Teklif bilgilerini güncelle
                $stmt = $db->prepare("UPDATE quotes SET $field = ?, updated_at = NOW() WHERE quote_number = ?");
                $stmt->execute([$value, $quote_number]);
            } elseif ($field === 'full_name') {
                // Ad soyad birlikte güncellenirse
                $names = explode(' ', $value, 2);
                $first_name = isset($names[0]) ? $names[0] : '';
                $last_name = isset($names[1]) ? $names[1] : '';

                $stmt = $db->prepare("
                    UPDATE customers c
                    JOIN quotes q ON c.id = q.customer_id
                    SET c.first_name = ?, c.last_name = ?
                    WHERE q.quote_number = ?
                ");
                $stmt->execute([$first_name, $last_name, $quote_id]);
            } elseif (strpos($field, 'custom_') === 0) {
                // Custom alanlar için özel handling
                // Bu alanları JSON olarak quotes tablosunda custom_fields sütununda saklayacağız

                // custom_fields kolonu var mı kontrol et ve gerekirse ekle
                try {
                    $checkStmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields'");
                    $checkStmt->execute();
                    $columnExists = $checkStmt->fetch();

                    if (!$columnExists) {
                        $db->exec("ALTER TABLE quotes ADD COLUMN custom_fields JSON DEFAULT NULL AFTER description");
                    }
                } catch (Exception $e) {
                    // Hata logla ama devam et
                    error_log("Custom fields column check error: " . $e->getMessage());
                }

                // Önce mevcut custom_fields'ı al
                $stmt = $db->prepare("SELECT custom_fields FROM quotes WHERE quote_number = ?");
                $stmt->execute([$quote_number]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                $custom_fields = [];
                if ($result && $result['custom_fields']) {
                    $custom_fields = json_decode($result['custom_fields'], true) ?: [];
                }

                // Yeni değeri ekle/güncelle
                $custom_fields[$field] = $value;

                // JSON olarak geri kaydet
                $stmt = $db->prepare("UPDATE quotes SET custom_fields = ?, updated_at = NOW() WHERE quote_number = ?");
                $stmt->execute([json_encode($custom_fields), $quote_number]);
            } elseif (in_array($field, $special_fields)) {
                // Özel alanlar için handling
                if ($field === 'quote_price_label') {
                    // Bu alan sadece görüntü amaçlı, veritabanında saklanmaz
                    // Başarılı response döndür ama hiçbir şey yapma
                } elseif ($field === 'quote_date') {
                    // Teklif tarihi - created_at alanını güncelle
                    $stmt = $db->prepare("UPDATE quotes SET created_at = ?, updated_at = NOW() WHERE quote_number = ?");
                    $stmt->execute([$value, $quote_number]);
                } elseif ($field === 'validity') {
                    // Geçerlilik tarihi - valid_until alanını güncelle
                    $stmt = $db->prepare("UPDATE quotes SET valid_until = ?, updated_at = NOW() WHERE quote_number = ?");
                    $stmt->execute([$value, $quote_number]);
                } elseif ($field === 'transport_type') {
                    // Taşıma türü - custom_transport_name alanını güncelle
                    $stmt = $db->prepare("UPDATE quotes SET custom_transport_name = ?, updated_at = NOW() WHERE quote_number = ?");
                    $stmt->execute([$value, $quote_number]);
                } elseif (in_array($field, ['custom_services_title', 'custom_transport_process_title', 'custom_terms_title', 'custom_section_title'])) {
                    // Bu başlıklar JSON olarak custom_fields içinde saklanacak
                    // Önce mevcut custom_fields'ı al
                    $stmt = $db->prepare("SELECT custom_fields FROM quotes WHERE quote_number = ?");
                    $stmt->execute([$quote_number]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    $custom_fields = [];
                    if ($result && $result['custom_fields']) {
                        $custom_fields = json_decode($result['custom_fields'], true) ?: [];
                    }

                    // Yeni değeri ekle/güncelle
                    $custom_fields[$field] = $value;

                    // JSON olarak geri kaydet
                    $stmt = $db->prepare("UPDATE quotes SET custom_fields = ?, updated_at = NOW() WHERE quote_number = ?");
                    $stmt->execute([json_encode($custom_fields), $quote_number]);
                }
            } else {
                throw new Exception('Bu alan düzenlenemez');
            }

            echo json_encode(['success' => true, 'message' => 'Güncellendi']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

        // Maliyet listesi güncelleme
    if ($action === 'update_cost_list') {
        try {
            $cost_list_id = $_POST['cost_list_id'] ?? null;

            // Debug log
            error_log("Update Cost List: cost_list_id=" . ($cost_list_id ?: 'null') . ", quote_id=" . ($quote_id ?: 'null'));

            if (!$quote_id) {
                throw new Exception('Teklif ID bulunamadı');
            }

            // Database connection kontrolü
            if (!isset($db)) {
                $database = new Database();
                $db = $database->getConnection();
            }

            // Eğer cost_list_id boşsa null yap
            if (empty($cost_list_id)) {
                $cost_list_id = null;
            }

            $stmt = $db->prepare("UPDATE quotes SET cost_list_id = ? WHERE quote_number = ?");
            $result = $stmt->execute([$cost_list_id, $quote_id]);

            if (!$result) {
                throw new Exception('Veritabanı güncelleme başarısız');
            }

            echo json_encode(['success' => true, 'message' => 'Maliyet listesi güncellendi']);
            exit;

        } catch (Exception $e) {
            error_log("Update Cost List Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$revision = $_GET['rev'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Veritabanı tablo kontrolü ve otomatik oluşturma
    try {
        // custom_fields kolonu var mı kontrol et
        $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields'");
        $stmt->execute();
        $columnExists = $stmt->fetch();

        if (!$columnExists) {
            // custom_fields kolonu yoksa ekle
            $db->exec("ALTER TABLE quotes ADD COLUMN custom_fields JSON DEFAULT NULL AFTER description");
            error_log("Custom fields column added to quotes table");
        }
    } catch (Exception $e) {
        // Eğer tablo kendisi yoksa oluştur
        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS quotes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    quote_number VARCHAR(50) UNIQUE NOT NULL,
                    customer_id INT NOT NULL,
                    transport_mode_id INT NOT NULL,
                    origin VARCHAR(255) NOT NULL,
                    destination VARCHAR(255) NOT NULL,
                    weight DECIMAL(10,2) DEFAULT NULL,
                    volume DECIMAL(10,2) DEFAULT NULL,
                    pieces INT DEFAULT NULL,
                    cargo_type ENUM('ev_esyasi', 'kisisel_esya', 'ticari_esya') DEFAULT 'ev_esyasi',
                    trade_type ENUM('ithalat', 'ihracat') DEFAULT 'ithalat',
                    description TEXT DEFAULT NULL,
                    custom_fields JSON DEFAULT NULL,
                    final_price DECIMAL(10,2) NOT NULL,
                    notes TEXT DEFAULT NULL,
                    services_content TEXT DEFAULT NULL,
                    optional_services_content TEXT DEFAULT NULL,
                    terms_content TEXT DEFAULT NULL,
                    unit_price DECIMAL(10,2) DEFAULT NULL,
                    transport_process_text TEXT DEFAULT NULL,
                    start_date DATE DEFAULT NULL,
                    delivery_date DATE DEFAULT NULL,
                    additional_section1_title VARCHAR(255) DEFAULT NULL,
                    additional_section1_content TEXT DEFAULT NULL,
                    additional_section2_title VARCHAR(255) DEFAULT NULL,
                    additional_section2_content TEXT DEFAULT NULL,
                    custom_transport_name VARCHAR(255) DEFAULT NULL,
                    intro_text TEXT DEFAULT NULL,
                    greeting_text TEXT DEFAULT NULL,
                    container_type VARCHAR(100) DEFAULT NULL,
                    show_reference_images TINYINT(1) DEFAULT 1,
                    selected_template_id INT DEFAULT NULL,
                    cost_list_id INT DEFAULT NULL,
                    language ENUM('tr', 'en') DEFAULT 'tr',
                    currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
                    valid_until DATE NOT NULL,
                    status ENUM('pending', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
                    approval_token VARCHAR(255) DEFAULT NULL,
                    revision_number INT DEFAULT 0,
                    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
                    delivery_status ENUM('not_started', 'in_progress', 'delivered') DEFAULT 'not_started',
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_quote_number (quote_number),
                    INDEX idx_customer_id (customer_id),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log("Quotes table created with custom_fields column");
        } else {
            error_log("Database schema check error: " . $e->getMessage());
        }
    }

    // additional_costs tablosunu kontrol et ve oluştur
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'additional_costs'");
        $stmt->execute();
        $tableExists = $stmt->fetch();

        if (!$tableExists) {
            // additional_costs tablosunu oluştur
            $db->exec("
                CREATE TABLE IF NOT EXISTS additional_costs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    quote_id INT NOT NULL,
                    description VARCHAR(500) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
                    is_additional TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_quote_id (quote_id),
                    INDEX idx_is_additional (is_additional)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            error_log("Additional costs table created");
        }
    } catch (Exception $e) {
        error_log("Additional costs table check error: " . $e->getMessage());
    }

    // Revision varsa quote_number'ı güncelle
    if ($revision) {
        $quote_id_with_rev = $quote_id . '_rev' . $revision;
    } else {
        $quote_id_with_rev = $quote_id;
    }

    // Teklifi ve müşteri bilgilerini al
    $stmt = $db->prepare("
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_name, tm.icon as transport_icon, tm.template,
               qt.services_content as template_services_content, qt.terms_content as template_terms_content, qt.transport_process_content as template_transport_process_content, qt.currency, qt.language,
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

    // Eğer revision ile bulunamadıysa, orijinal teklifi dene
    if (!$quote && $revision) {
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch();
    }

    if (!$quote) {
        die('Teklif bulunamadı.');
    }

    // Maliyet listelerini al (dosyası mevcut olanları)
    $stmt = $db->prepare("
        SELECT id, name, file_name, transport_mode_id
        FROM cost_lists
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute();
    $all_cost_lists = $stmt->fetchAll();

    // Maliyet listelerini al (dosya kontrolünü geçici olarak kaldır)
    $cost_lists = $all_cost_lists;

    // Geçerlilik kontrolü
    $is_expired = strtotime($quote['valid_until']) < time();

    // Dil ayarları
    $is_english = ($quote['language'] ?? 'tr') === 'en';

    // Para birimi
    $currency = $quote['currency'] ?? 'TL';

    // Çeviriler
    $translations = [
        'tr' => [
            'quote_title' => 'EV EŞYASI TAŞIMA FİYAT TEKLİFİ',
            'our_quote_price' => 'TEKLİF FİYATIMIZ',
            'price_info' => 'Fiyat Bilgisi',
            'customer_info' => 'Müşteri Bilgileri',
            'transport_details' => 'Taşıma Detayları',
            'cargo_info' => 'Yük Bilgileri',
            'name_surname' => 'Ad Soyad',
            'company' => 'Şirket',
            'email' => 'E-posta',
            'phone' => 'Telefon',
            'quote_date' => 'Teklif Tarihi',
            'validity' => 'Geçerlilik',
            'transport_type' => 'Taşıma Türü',
            'origin' => 'Çıkış Noktası',
            'destination' => 'Varış Noktası',
            'start_date' => 'Yükleme Tarihi',
            'delivery_date' => 'Teslim Tarihi',
            'status' => 'Durum',
            'weight' => 'Ağırlık',
            'volume' => 'Hacim',
            'pieces' => 'Parça Sayısı',
            'cargo_type' => 'Yük Türü',
            'trade_type' => 'İşlem Türü',
            'description' => 'Açıklama',
            'active' => 'Aktif',
            'expired' => 'Süresi Dolmuş',
            'not_specified' => 'Belirtilmemiş',
            'services' => 'Hizmetlerimiz',
            'terms' => 'Şartlar',
            'information' => 'Bilgilendirme',
            'additional_costs' => 'Ek Maliyetler',
            'unit_price' => 'Birim m³ Fiyatı',
            'customs_fee' => 'Gümrük Hizmet Bedeli',
            'add_cost' => 'Maliyet Ekle',
            'cost_description' => 'Açıklama',
            'cost_amount' => 'Tutar',
            'save' => 'Kaydet',
            'edit' => 'Düzenle',
            'delete' => 'Sil',
            // Admin panel çevirileri
            'admin_edit_mode' => 'Admin Düzenleme Modu',
            'back_return' => 'Tekliflere Dön',
            'customer_view' => 'Müşteri Görünümü',
            // Transport mode çevirileri
            'transport_modes' => [
                'Karayolu' => 'Karayolu',
                'Havayolu' => 'Havayolu',
                'Denizyolu' => 'Denizyolu',
                'Demiryolu' => 'Demiryolu',
                'Kombine' => 'Kombine'
            ]
        ],
        'en' => [
            'quote_title' => 'HOUSEHOLD GOODS TRANSPORT PRICE QUOTE',
            'our_quote_price' => 'OUR QUOTE PRICE',
            'price_info' => 'Price Information',
            'customer_info' => 'Customer Information',
            'transport_details' => 'Transport Details',
            'cargo_info' => 'Cargo Information',
            'name_surname' => 'Name Surname',
            'company' => 'Company',
            'email' => 'Email',
            'phone' => 'Phone',
            'quote_date' => 'Quote Date',
            'validity' => 'Validity',
            'transport_type' => 'Transport Type',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'start_date' => 'Start Date',
            'delivery_date' => 'Delivery Date',
            'status' => 'Status',
            'weight' => 'Weight',
            'volume' => 'Volume',
            'pieces' => 'Pieces',
            'cargo_type' => 'Cargo Type',
            'trade_type' => 'Trade Type',
            'description' => 'Description',
            'active' => 'Active',
            'expired' => 'Expired',
            'not_specified' => 'Not Specified',
            'services' => 'Our Services',
            'terms' => 'Terms',
            'information' => 'Information',
            'additional_costs' => 'Additional Costs',
            'unit_price' => 'Unit m³ Price',
            'customs_fee' => 'Customs Service Fee',
            'add_cost' => 'Add Cost',
            'cost_description' => 'Description',
            'cost_amount' => 'Amount',
            'save' => 'Save',
            'edit' => 'Edit',
            'delete' => 'Delete',
            // Admin panel çevirileri
            'admin_edit_mode' => 'Admin Edit Mode',
            'back_return' => 'Back to Quotes',
            'customer_view' => 'Customer View',
            // Transport mode çevirileri
            'transport_modes' => [
                'Karayolu' => 'Highway',
                'Havayolu' => 'Airway',
                'Denizyolu' => 'Seaway',
                'Demiryolu' => 'Railway',
                'Kombine' => 'Combined'
            ]
        ]
    ];

    $t = $translations[$is_english ? 'en' : 'tr'];

    // Transport mode çeviri fonksiyonu
    function translateTransportMode($transport_name, $translations) {
        return $translations['transport_modes'][$transport_name] ?? $transport_name;
    }

} catch (Exception $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}

// Para birimi formatı
function formatPriceWithCurrency($price, $currency) {
    $formatted_price = number_format($price, 0, ',', '.');
    switch($currency) {
        case 'USD':
            return '$' . $formatted_price;
        case 'EUR':
            return '€' . $formatted_price;
        case 'TL':
        default:
            return $formatted_price . ' TL';
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $is_english ? 'en' : 'tr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teklif Düzenle - <?php echo htmlspecialchars($quote['quote_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.4;
            font-size: 12px;
        }

        /* Admin Header */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-header h3 {
            margin: 0;
            font-size: 18px;
        }

        .admin-actions {
            display: flex;
            gap: 10px;
        }

        .btn-admin {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .btn-admin:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-1px);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            box-sizing: border-box;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px 35px;
            border-bottom: 3px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            max-width: 245px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .company-info h1 {
            color: #2c5aa0;
            font-size: 24px;
            font-weight: 600;
            margin: 0;
        }

        .company-info p {
            color: #666;
            font-size: 12px;
            margin: 0;
        }

        .contact-info {
            text-align: right;
            font-size: 12px;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .phone-number {
            color: #2c5aa0 !important;
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 15px;
            display: inline-block;
            order: -1;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .phone-number:hover {
            transform: scale(1.05);
            text-shadow: 0 2px 4px rgba(44, 90, 160, 0.3);
        }

        .phone-number span {
            color: #2c5aa0 !important;
            margin-right: 5px;
        }

        .quote-number-header {
            color: #2c5aa0 !important;
            font-weight: 700;
            font-size: 16px;
            margin-top: 8px;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .quote-number-header:hover {
            transform: scale(1.05);
            text-shadow: 0 2px 4px rgba(44, 90, 160, 0.3);
        }

        .address-info {
            font-size: 11px;
            line-height: 1.3;
        }

        /* Main Title */
        .main-title {
            background: #2c5aa0;
            color: white;
            text-align: center;
            padding: 7px;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Content Area */
        .content {
            margin-top: 0px;
        }

        /* Information Section */
        .info-side {
            background: #f8f9fa;
            padding: 15px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            position: relative;
        }

        .section-label {
            background: #ffc107;
            color: #333;
            padding: 8px 15px;
            font-weight: 600;
            font-size: 12px;
            min-width: 150px;
            text-align: center;
            display: inline-block;
            vertical-align: middle;
        }

        .section-title {
            background: #2c5aa0;
            color: white;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 12px;
            flex: 1;
        }

        /* Form Sections */
        .form-section {
            border: 1px solid #e0e0e0;
            margin-bottom: 15px;
        }

        .form-content {
            padding: 15px;
            background: white;
        }

        .form-row {
            display: flex;
            margin-bottom: 10px;
            align-items: center;
        }

        .form-label {
            min-width: 120px;
            font-weight: 500;
            color: #333;
            font-size: 12px;
        }

        .form-value {
            flex: 1;
            padding: 6px 10px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 12px;
            color: #333;
            margin-left: 15px;
        }

        /* Price Section */
        .price-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            padding: 28px 25px;
            text-align: center;
            margin: 18px 0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(200, 230, 201, 0.12);
            border: 2px solid rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .price-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease-in-out;
        }

        .price-section:hover::before {
            left: 100%;
        }

        .price-section:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 15px 40px rgba(200, 230, 201, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .price-label {
            font-size: 13px;
            margin-bottom: 15px;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            opacity: 0.8;
            position: relative;
            z-index: 2;
            text-shadow: 0 1px 2px rgba(255,255,255,0.3);
        }

        .price-amount {
            font-size: 36px;
            font-weight: 800;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(255,255,255,0.2);
            letter-spacing: 0.5px;
            line-height: 1.1;
        }

        /* Info Content */
        .info-content {
            font-size: 12px;
            line-height: 1.5;
        }

        .info-side h4 {
            color: #2c5aa0 !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            margin: 20px 0 15px 0 !important;
            text-transform: uppercase !important;
            position: relative !important;
            padding-left: 15px !important;
            padding-bottom: 8px !important;
        }

        .info-side h4::before {
            content: '' !important;
            position: absolute !important;
            left: 0 !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 4px !important;
            height: 20px !important;
            background: #ffc107 !important;
            border-radius: 2px !important;
        }

        .info-side h4::after {
            content: '' !important;
            position: absolute !important;
            bottom: 0 !important;
            left: 15px !important;
            right: 0 !important;
            height: 2px !important;
            background: linear-gradient(to right, #ffc107 0%, #ffc107 30%, transparent 100%) !important;
        }

        .info-content ul {
            margin: 8px 0;
            padding-left: 18px;
        }

        .info-content li {
            margin-bottom: 4px;
            font-size: 11px;
        }

        .info-content p {
            font-size: 12px;
            margin: 8px 0;
        }

        .highlight-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
        }

        .highlight-box h4 {
            color: #1976d2 !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            margin: 0 0 6px 0 !important;
            border: none !important;
            padding: 0 !important;
            text-transform: none !important;
            position: static !important;
        }

        .highlight-box h4::before,
        .highlight-box h4::after {
            display: none !important;
        }

        .highlight-box p {
            font-size: 11px;
            margin: 0;
        }

        /* Additional Costs */
        .additional-costs-list {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background: #f8f9fa;
        }

        .cost-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 11px;
        }

        .cost-item:last-child {
            border-bottom: none;
        }

        .cost-item:nth-child(even) {
            background: white;
        }

        .cost-description {
            font-weight: 500;
            color: #333;
        }

        .cost-amount {
            font-weight: 600;
            color: #2c5aa0;
        }

        .add-cost-form {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }



        /* Approval Buttons */
        .approval-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-action {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #2c5aa0;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            color: white;
        }

        .btn-approve {
            background: #28a745;
            color: white;
            font-weight: 600;
        }

        .btn-approve:hover {
            background: #218838;
            color: white;
        }

        .btn-pdf {
            background: #dc3545;
            color: white;
            font-weight: 600;
        }

        .btn-pdf:hover {
            background: #c82333;
            color: white;
        }



        /* Footer */
        .footer {
            background: #2c5aa0;
            color: white;
            padding: 20px;
            margin-top: 30px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .footer strong {
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }

            .info-side {
                border-left: none;
                border-top: 1px solid #e0e0e0;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .contact-info {
                align-items: center;
            }

            .footer-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .footer-left,
            .footer-right {
                text-align: center;
            }
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            body {
                background: white !important;
                font-size: 9px !important;
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .container {
                box-shadow: none !important;
                margin: 0 !important;
                max-width: 100% !important;
                padding: 0 !important;
                page-break-inside: avoid;
            }

            /* Admin header gizle */
            .admin-header {
                display: none !important;
            }

            /* Header kompakt hale getir */
            .header {
                padding: 10px 15px !important;
                background: white !important;
                border-bottom: 2px solid #e0e0e0 !important;
                flex-direction: row !important;
                justify-content: space-between !important;
            }

            .logo {
                max-width: 150px !important;
                height: auto !important;
            }

            .contact-info {
                font-size: 9px !important;
                text-align: right !important;
            }

            .phone-number {
                font-size: 10px !important;
                margin-bottom: 5px !important;
            }

            .quote-number-header {
                font-size: 10px !important;
                margin-top: 3px !important;
            }

            /* Main title kompakt */
            .main-title {
                padding: 8px !important;
                font-size: 12px !important;
            }

            /* Content alanını tek sütun yap */
            .content {
                display: block !important;
                grid-template-columns: none !important;
                margin-top: 5px !important;
                min-height: auto !important;
            }

            /* Bilgilendirme alanı print */
            .info-header-moved {
                padding: 15px 20px !important;
                background: white !important;
                text-align: left !important;
            }

            .info-header-moved p {
                font-size: 10px !important;
                line-height: 1.4 !important;
                margin: 8px 0 !important;
            }

            .info-header-moved p:first-child {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #2c5aa0 !important;
                margin-bottom: 12px !important;
            }

            .info-side {
                background: white !important;
                padding: 10px !important;
            }

            /* Form sections kompakt */
            .form-section {
                margin-bottom: 5px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .section-label {
                padding: 3px 8px !important;
                font-size: 8px !important;
                min-width: 100px !important;
            }

            .section-title {
                padding: 3px 10px !important;
                font-size: 8px !important;
            }

            .form-content {
                padding: 5px !important;
            }

            .form-row {
                margin-bottom: 3px !important;
                display: flex !important;
                align-items: center !important;
            }

            .form-label {
                min-width: 80px !important;
                font-size: 8px !important;
            }

            .form-value {
                padding: 2px 4px !important;
                font-size: 8px !important;
                margin-left: 8px !important;
                flex: 1 !important;
            }

            /* Price section kompakt */
            .price-section {
                padding: 8px 10px !important;
                margin: 5px 0 !important;
                border-radius: 4px !important;
            }

            .price-label {
                font-size: 8px !important;
                margin-bottom: 4px !important;
            }

            .price-amount {
                font-size: 16px !important;
            }

            /* Highlight box kompakt */
            .highlight-box {
                padding: 5px !important;
                margin: 5px 0 !important;
            }

            .highlight-box h4 {
                font-size: 7px !important;
                margin: 0 0 2px 0 !important;
            }

            .highlight-box p {
                font-size: 7px !important;
                margin: 0 !important;
            }

            /* Info side kompakt */
            .info-side h4 {
                font-size: 9px !important;
                margin: 8px 0 5px 0 !important;
                padding-left: 8px !important;
                padding-bottom: 3px !important;
            }

            .info-side h4::before {
                width: 2px !important;
                height: 12px !important;
            }

            .info-content {
                font-size: 7px !important;
                line-height: 1.2 !important;
            }

            .info-content ul {
                margin: 3px 0 !important;
                padding-left: 10px !important;
            }

            .info-content li {
                margin-bottom: 1px !important;
                font-size: 7px !important;
            }

            .info-content p {
                font-size: 7px !important;
                margin: 3px 0 !important;
            }

            /* Footer kompakt */
            .footer {
                padding: 8px 15px !important;
                margin-top: 10px !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .footer-content {
                font-size: 7px !important;
                flex-direction: row !important;
                justify-content: space-between !important;
            }

            .footer strong {
                font-size: 8px !important;
            }

            .footer-left, .footer-right {
                text-align: left !important;
            }

            .footer-right {
                text-align: right !important;
            }

            /* Edit indicators gizle */
            .edit-indicator {
                display: none !important;
            }

            /* Sayfa kırılmalarını kontrol et */
            .form-section,
            .price-section,
            .highlight-box {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* Sayfa boyutu optimizasyonu */
            @page {
                size: A4;
                margin: 8mm;
            }

            /* Çok uzun içerikleri kırp */
            .form-value {
                max-width: none !important;
                overflow: visible !important;
                text-overflow: clip !important;
                white-space: normal !important;
            }

            /* Flexbox düzenlemeleri */
            .header {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
            }

            .logo-section {
                flex: 0 0 auto !important;
            }

            .contact-info {
                flex: 0 0 auto !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-end !important;
            }

            /* Clearfix */
            .content::after {
                content: "";
                display: table;
                clear: both;
            }
        }

        /* Modern Gallery Styles */
        .modern-gallery-modal .modal-dialog {
            max-width: 95vw;
            margin: 1rem auto;
        }

        .modern-gallery-modal .modal-content {
            background: #000;
            border: none;
            border-radius: 0;
            height: 95vh;
        }

        .modern-gallery-modal .modal-header {
            background: rgba(0,0,0,0.8);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1rem 1.5rem;
        }

        .modern-gallery-modal .modal-title {
            color: white;
            font-weight: 600;
        }

        .modern-gallery-modal .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #111;
            height: calc(95vh - 80px);
            overflow-y: auto;
        }

        .gallery-item {
            position: relative;
            background: #222;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            aspect-ratio: 4/3;
        }

        .gallery-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gallery-item:hover img {
            transform: scale(1.05);
        }

        .gallery-item-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 20px 15px 15px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .gallery-item:hover .gallery-item-overlay {
            transform: translateY(0);
        }

        .gallery-item-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .gallery-item-desc {
            font-size: 0.8rem;
            opacity: 0.8;
            line-height: 1.3;
        }

        /* Fullscreen Modal */
        .fullscreen-modal {
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
        }

        .fullscreen-modal .modal-dialog {
            max-width: 100vw;
            height: 100vh;
            margin: 0;
        }

        .fullscreen-modal .modal-content {
            background: transparent;
            border: none;
            height: 100vh;
            border-radius: 0;
        }

        .swiper-container {
            width: 100%;
            height: 100vh;
        }

        .swiper-slide {
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
        }

        .swiper-slide img {
            max-width: 90%;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .swiper-button-next,
        .swiper-button-prev {
            color: white;
            background: rgba(255,255,255,0.1);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 18px;
            font-weight: bold;
        }

        .swiper-pagination-bullet {
            background: rgba(255,255,255,0.5);
            opacity: 1;
            width: 12px;
            height: 12px;
        }

        .swiper-pagination-bullet-active {
            background: white;
        }

        .fullscreen-controls {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 10px;
        }

        .fullscreen-btn {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .fullscreen-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }

        .image-info-overlay {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }

        .image-info-overlay.show {
            opacity: 1;
            transform: translateY(0);
        }

        .image-info-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .image-info-desc {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Modern Reference Gallery Button */
        .reference-gallery-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 40px 30px;
            margin: 30px 0;
            border: 1px solid #dee2e6;
            position: relative;
            overflow: hidden;
        }

        .reference-gallery-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s;
        }

        .reference-gallery-section:hover::before {
            animation: shine 1.5s ease-in-out;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .reference-gallery-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .reference-gallery-header h3 {
            color: #2c3e50;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 8px;
            position: relative;
        }

        .reference-gallery-header p {
            color: #6c757d;
            font-size: 1rem;
            margin: 0;
            line-height: 1.6;
        }

        .modern-gallery-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-width: 280px;
        }

        .modern-gallery-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .modern-gallery-btn:active {
            transform: translateY(-1px);
        }

        .modern-gallery-btn i {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .gallery-description {
            background: rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
            backdrop-filter: blur(10px);
        }

        .gallery-description .company-name {
            color: #667eea;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .gallery-features {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .gallery-feature {
            display: flex;
            align-items: center;
            color: #495057;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .gallery-feature i {
            color: #667eea;
            margin-right: 8px;
            font-size: 1.1rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .reference-gallery-section {
                padding: 30px 20px;
                margin: 20px 0;
                border-radius: 15px;
            }

            .reference-gallery-header h3 {
                font-size: 1.3rem;
            }

            .modern-gallery-btn {
                padding: 16px 30px;
                font-size: 1rem;
                min-width: 250px;
            }

            .gallery-features {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }

            .gallery-feature {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .reference-gallery-section {
                padding: 25px 15px;
            }

            .modern-gallery-btn {
                padding: 14px 25px;
                font-size: 0.95rem;
                min-width: 220px;
            }

            .reference-gallery-header h3 {
                font-size: 1.2rem;
            }

            .gallery-description {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Admin Header -->
        <div class="admin-header">
            <h3><i class="fas fa-edit"></i> <?php echo $t['admin_edit_mode']; ?></h3>
            <div class="admin-actions">
                <a href="quotes.php" class="btn-admin">
                    <i class="fas fa-arrow-left"></i> <?php echo $t['back_return']; ?>
                </a>
                <a href="../view-quote.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                   target="_blank" class="btn-admin">
                    <i class="fas fa-external-link-alt"></i> <?php echo $t['customer_view']; ?>
                </a>
                <a href="../api/generate-pdf.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                   target="_blank" class="btn-admin" style="background: #dc3545; border-color: #dc3545;">
                    <i class="fas fa-file-pdf"></i> <?= $is_english ? 'Download PDF' : 'PDF İndir' ?>
                </a>
            </div>
        </div>
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="https://www.europatrans.com.tr/themes/europatrans/img/europatrans-logo.png"
                     alt="Europatrans Logo" class="logo">
            </div>
            <div class="contact-info">
                <div class="phone-number"><span>📞</span>444 6 995</div>
                <div class="quote-number-header">
                    <?= $is_english ? 'Quote No' : 'Teklif No' ?> : <?php echo htmlspecialchars($quote['quote_number']); ?>
                    <?php if ($quote['revision_number'] > 0): ?>
                        <br><span style="font-size: 12px; color: #ffc107;">
                            <?= $is_english ? 'Revision' : 'Revizyon' ?> <?php echo $quote['revision_number']; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Main Title -->
        <div class="main-title">
            <?= $t['quote_title'] ?>
        </div>

        <!-- Info Header - With Contact Info -->
        <div style="padding: 15px 15px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start;">
                <!-- Left Side - Main Content -->
                <div style="text-align: left;">
                    <p style="font-size: 16px; font-weight: 600; color: #2c5aa0; margin-bottom: 20px; line-height: 1.6;">
                        <?php if (!empty($quote['greeting_text'])): ?>
                            <span class="editable" data-field="greeting_text" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?= $quote['greeting_text'] ?>
                            </span>
                        <?php else: ?>
                            <span class="editable" data-field="greeting_text" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <strong><?= ($is_english ? 'Dear' : 'Sayın') ?>
                                <span class="editable" data-field="full_name" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?= htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']) ?>
                                </span><?= ($is_english ? ',' : ',') ?></strong>
                            </span>
                        <?php endif; ?>
                    </p>
                    <p style="font-size: 15px; color: #333; line-height: 1.7; margin: 0; font-weight: 400;">
                        <span class="editable" data-field="intro_text" data-type="textarea" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; display: block;"
                              onclick="editField(this)" title="Düzenlemek için tıklayın">
                            <?= !empty($quote['intro_text']) ? $quote['intro_text'] : ($is_english ? 'As Europatrans Global Logistics Transportation, we offer and undertake to carry out the international transportation of your goods under the conditions specified below.' : 'Europatrans Global Lojistik Taşımacılık olarak eşyalarınızın uluslararası taşımasını, aşağıda belirtilen şartlar dahilinde yapmayı teklif ve taahhüt ederiz.') ?>
                        </span>
                    </p>
                </div>

                                                <!-- Right Side - Contact Info -->
                <div style="background: rgba(255,255,255,0.9); padding: 16px; border-radius: 6px; border: 1px solid rgba(44,90,160,0.15); box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: right;">
                    <h4 style="color: #2c5aa0; font-size: 13px; font-weight: 600; margin-bottom: 12px; margin-top: 0; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">
                        <?= $is_english ? 'Contact Information' : 'İletişim Bilgileri' ?>
                    </h4>

                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 12px;">
                            <?= $t['email'] ?>:
                        </span>
                        <span class="editable" data-field="email" data-type="email"
                              style="cursor: pointer; padding: 2px 6px; border-radius: 3px; background: transparent; transition: all 0.2s; font-size: 12px; color: #333; border: 1px solid transparent;"
                              onclick="editField(this)" title="Düzenlemek için tıklayın">
                            <?php echo htmlspecialchars($quote['email']); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 0; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 12px;">
                            <?= $t['phone'] ?>:
                        </span>
                        <span class="editable" data-field="phone"
                              style="cursor: pointer; padding: 2px 6px; border-radius: 3px; background: transparent; transition: all 0.2s; font-size: 12px; color: #333; border: 1px solid transparent;"
                              onclick="editField(this)" title="Düzenlemek için tıklayın">
                            <?php echo htmlspecialchars($quote['phone']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Section - New Layout Under Intro Text -->
        <div style="padding: 15px 15px; background: white; border-bottom: 1px solid #e0e0e0;">
            <div style="max-width: 1000px;"></div>
                <!-- Main Price Table -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 12px 20px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); font-weight: 600; font-size: 13px; width: 220px; color: #155724; border-right: 1px solid rgba(255,255,255,0.3);">
                            <span class="editable" data-field="quote_price_label" style="cursor: pointer; padding: 2px 4px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?= $t['our_quote_price'] ?>
                            </span>
                        </td>
                        <td style="padding: 12px 20px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); text-align: right; font-size: 20px; font-weight: 700; color: #155724; cursor: pointer;" onclick="editMainPrice()" title="Fiyatı düzenlemek için tıklayın">
                            <span id="mainPriceDisplay"><?php echo formatPriceWithCurrency($quote['final_price'], $currency); ?></span>
                            <input type="text" id="mainPriceEdit" style="display: none; width: 100%; text-align: right; border: none; font-size: 20px; font-weight: 700; color: #155724; background: transparent;" onblur="saveMainPrice()" onkeypress="handleMainPriceKeypress(event)" value="<?php echo $quote['final_price']; ?>">
                        </td>
                    </tr>
                </table>

                <!-- Additional Costs Table -->
                <div id="additionalCostsTable">
                    <!-- Ek maliyetler buraya eklenecek -->
                            </div>

                                <!-- Add Cost Button -->
                <div style="margin-bottom: 15px;">
                    <button type="button" onclick="addNewCost()" style="padding: 6px 12px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 11px; cursor: pointer; box-shadow: 0 2px 4px rgba(40,167,69,0.3); transition: all 0.2s ease;">
                        <i class="fas fa-plus"></i> Ek teklif
                                </button>
                </div>



                                                                                                                                <!-- General Information - Compact 2 Column Layout -->
                <div class="form-section" style="margin: 0px 0;">
                    <div class="section-header">
                        <div class="section-label"><?= $is_english ? 'General Transportation Information' : 'Taşımaya Dair Genel Bilgiler' ?></div>
                        <div class="section-title"></div>
                    </div>

                    <!-- Content in 2 columns - Compact Layout -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; background: white; padding: 15px 20px;">

                                                <!-- Left Column -->
                        <div>
                            <?php if (!empty($quote['company'])): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['company'] ?>:</span>
                                <span class="editable" data-field="company" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo htmlspecialchars($quote['company']); ?>
                                </span>
                            </div>
                                                        <?php endif; ?>




                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['quote_date'] ?>:</span>
                                <span class="editable" data-field="quote_date" data-type="date" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo formatDate($quote['created_at']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['validity'] ?>:</span>
                                <span class="editable" data-field="validity" data-type="date" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo formatDate($quote['valid_until']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['transport_type'] ?>:</span>
                                <span class="editable" data-field="transport_type" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo htmlspecialchars(!empty($quote['custom_transport_name']) ? $quote['custom_transport_name'] : translateTransportMode($quote['transport_name'], $t)); ?>
                                </span>
                            </div>

                            <?php if (strtolower($quote['transport_name']) === 'havayolu'): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['weight'] ?>:</span>
                                <span class="editable" data-field="weight" data-type="number" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo !empty($quote['weight']) ? number_format($quote['weight'], 0, ',', '.') . ' kg' : ($is_english ? 'Click to add weight' : 'Ağırlık eklemek için tıklayın'); ?>
                                </span>
                            </div>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['pieces'] ?>:</span>
                                <span class="editable" data-field="pieces" data-type="number" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo !empty($quote['pieces']) ? number_format($quote['pieces'], 0, ',', '.') : ($is_english ? 'Click to add pieces' : 'Parça sayısı eklemek için tıklayın'); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>


                        <!-- Right Column -->
                        <div>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['origin'] ?>:</span>
                                <span class="editable" data-field="origin" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo htmlspecialchars($quote['origin']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['destination'] ?>:</span>
                                <span class="editable" data-field="destination" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo htmlspecialchars($quote['destination']); ?>
                                </span>
                            </div>

                            <?php if (!empty($quote['start_date']) && $quote['start_date'] !== '0000-00-00' && $quote['start_date'] !== null): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['start_date'] ?>:</span>
                                <span class="editable" data-field="start_date" data-type="date" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo formatDate($quote['start_date']); ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($quote['delivery_date']) && $quote['delivery_date'] !== '0000-00-00' && $quote['delivery_date'] !== null): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['delivery_date'] ?>:</span>
                                <span class="editable" data-field="delivery_date" data-type="date" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo formatDate($quote['delivery_date']); ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['volume'] ?>:</span>
                                <span class="editable" data-field="volume" data-type="number" data-step="0.01" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                      onclick="editField(this)" title="Düzenlemek için tıklayın">
                                    <?php echo !empty($quote['volume']) ? number_format($quote['volume'], 2, ',', '.') . ' m³' : ($is_english ? 'Click to add volume' : 'Hacim eklemek için tıklayın'); ?>
                                </span>
                            </div>


                        </div>

                        <!-- Custom Field'lar buraya dinamik olarak eklenecek -->
                        <div id="additionalGeneralInfoRows" style="display: contents;">
                            <!-- Dinamik olarak eklenen satırlar buraya gelecek -->
                        </div>
                    </div>

                    <!-- Yeni Satır Ekle Butonu -->
                    <div style="background: white; padding: 15px 20px; border-top: 1px solid #eee;">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addGeneralInfoRow()" style="font-size: 12px;">
                            <i class="fas fa-plus"></i> Yeni Satır Ekle
                        </button>
                    </div>
                </div>
                    </div>

        <!-- Content -->
        <div class="content">
            <!-- Sıralama Kontrolleri -->
            <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                <div style="font-size: 14px; color: #666;">
                    <i class="fas fa-info-circle"></i> Bölümleri sıralamak için sol taraftaki okları kullanın
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="resetSectionOrder()" title="Varsayılan sıralamaya dön">
                    <i class="fas fa-undo"></i> Sıralamayı Sıfırla
                </button>
            </div>

            <!-- Information Section -->
            <div class="info-side">

                <!-- Services Section -->
                <div class="form-section" data-section="services">
                    <div class="section-header">
                        <div class="section-label">
                            <span class="editable" data-field="custom_services_title" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?php
                                $custom_fields = !empty($quote['custom_fields']) ? json_decode($quote['custom_fields'], true) : [];
                                echo !empty($custom_fields['custom_services_title']) ? htmlspecialchars($custom_fields['custom_services_title']) : $t['services'];
                                ?>
                            </span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce quotes tablosundaki services_content'i kontrol et, yoksa template'den al
                        $services_content = $quote['services_content'] ?? $quote['template_services_content'] ?? '';
                        if (!empty($services_content)): ?>
                            <!-- Hizmetler içeriği -->
                            <?php
                            // Şablon değişkenlerini değiştir
                            $services_content = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $services_content);
                            $services_content = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $services_content);
                            $services_content = str_replace('{origin}', htmlspecialchars($quote['origin']), $services_content);
                            $services_content = str_replace('{destination}', htmlspecialchars($quote['destination']), $services_content);
                            $services_content = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $services_content);
                            $services_content = str_replace('{volume}', number_format($quote['volume'] ?? 0, 2, ',', '.'), $services_content);
                            $services_content = str_replace('{pieces}', $quote['pieces'] ?? $t['not_specified'], $services_content);
                            $services_content = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $services_content);
                            $services_content = str_replace('{valid_until}', formatDate($quote['valid_until']), $services_content);
                            $services_content = str_replace('{cargo_type}', $quote['cargo_type'] ?? 'Genel', $services_content);
                            $services_content = str_replace('{trade_type}', $quote['trade_type'] ?? '', $services_content);
                            $services_content = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $services_content);
                            $services_content = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $services_content);

                            echo '<div class="editable" data-field="services_content" data-type="textarea" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 100px;" onclick="editField(this)" title="Düzenlemek için tıklayın">' . $services_content . '<span class="edit-indicator"></span></div>';
                            ?>
                        <?php else: ?>
                            <!-- Boş hizmetler içeriği -->
                            <div class="editable" data-field="services_content" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 100px; color: #888; font-style: italic;" onclick="editField(this)" title="Düzenlemek için tıklayın">
                                Hizmetler için içerik eklemek üzere tıklayın...<span class="edit-indicator"></span>
                            </div>
                        <?php endif; ?>

                        <!-- Maliyet Listesi Seçimi -->
                        <div class="mt-4" style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 15px;">
                            <h5 style="color: #2c5aa0; margin-bottom: 15px; font-size: 16px;">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Maliyet Listesi
                            </h5>
                            <div class="mb-3">
                                <label class="form-label" style="font-weight: 500; color: #333; margin-bottom: 8px;">
                                    Maliyet Listesi Seç:
                                </label>

                                <select class="form-select" id="costListSelect" onchange="updateCostList()"
                                        style="border: 1px solid #ddd; border-radius: 6px; padding: 8px 12px;">
                                    <option value="">Maliyet listesi seçiniz</option>
                                    <?php foreach ($cost_lists as $cost_list): ?>
                                        <option value="<?php echo $cost_list['id']; ?>"
                                                <?php echo ($quote['cost_list_id'] == $cost_list['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cost_list['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!empty($quote['cost_list_name'])): ?>
                            <div class="cost-list-preview" style="background: white; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; margin-top: 10px;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div>
                                        <strong style="color: #2c5aa0;">Seçili Maliyet Listesi:</strong>
                                        <span class="badge bg-primary ms-2" style="font-size: 12px;">
                                            <?php echo htmlspecialchars($quote['cost_list_name']); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($quote['cost_list_file_path']) && file_exists($quote['cost_list_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($quote['cost_list_file_path']); ?>"
                                           target="_blank"
                                           class="btn btn-sm btn-outline-primary"
                                           style="padding: 4px 8px; font-size: 12px;">
                                            <i class="fas fa-download"></i> Dosyayı İndir
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Maliyet Listesi Linki -->
                        <?php if (!empty($quote['cost_list_name'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                            <?php if (!empty($quote['cost_list_file_path']) && file_exists($quote['cost_list_file_path'])): ?>
                                <p><?= $is_english ? 'You can download the detailed cost list' : 'Detaylı maliyet listesini' ?>
                                   <a href="<?= htmlspecialchars($quote['cost_list_file_path']) ?>"
                                      target="_blank"
                                      style="color: #2c5aa0; text-decoration: underline; font-weight: 500;">
                                      <?= $is_english ? 'from here' : 'buradan indirebilirsiniz' ?>
                                   </a><?= $is_english ? '.' : '.' ?>
                                </p>
                            <?php else: ?>

                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Transport Process -->
                <div class="form-section" data-section="transport_process">
                    <div class="section-header">
                        <div class="section-label">
                            <span class="editable" data-field="custom_transport_process_title" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?php
                                echo !empty($custom_fields['custom_transport_process_title']) ? htmlspecialchars($custom_fields['custom_transport_process_title']) : ($is_english ? 'Transport Process' : 'Taşınma Süreci');
                                ?>
                            </span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce veritabanından kaydedilmiş transport_process_text'i kontrol et
                        if (!empty($quote['transport_process_text'])) {
                            $transport_process_text = $quote['transport_process_text'];
                        } elseif (!empty($quote['template_transport_process_content'])) {
                            // Seçilen şablondan içerik al
                            $transport_process_text = $quote['template_transport_process_content'];
                        } else {
                            // Eğer veritabanında kayıtlı değer yoksa, taşıma moduna göre varsayılan süreç metni belirle
                            $transport_process_text = '';
                            $transport_mode_lower = strtolower($quote['transport_name']);

                            // Eğer üstte özel bir içerik atanmadıysa varsayılan metni oluştur
                            if (empty($transport_process_text)) {
                            if ($is_english) {
                                // İngilizce süreç metinleri
                                if (strpos($transport_mode_lower, 'karayolu') !== false) {
                                    $transport_process_text = "1-Presentation and mutual signing of the offer,<br>
2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>
3-Preparation of customs documents,<br>
4-Collection of goods from the loading address,<br>
5-Customs clearance of the goods and departure,<br>
6-Payment of the remaining balance,<br>
7-Customs clearance procedures in the destination country,<br>
8-Delivery of goods to the delivery address.";
                                } elseif (strpos($transport_mode_lower, 'deniz') !== false) {
                                    $transport_process_text = "1-Presentation and mutual signing of the offer,<br>
2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>
3-Container reservation from the shipping company,<br>
4-Preparation of customs documents,<br>
5-Collection of goods from the loading address,<br>
6-Customs clearance of the goods and departure,<br>
7-Payment of the remaining balance,<br>
8-Customs clearance procedures in the destination country,<br>
9-Delivery of goods to the delivery address.";
                                } elseif (strpos($transport_mode_lower, 'hava') !== false) {
                                    $transport_process_text = "1-Presentation and mutual signing of the offer,<br>
2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>
3-Cargo reservation from the airline company,<br>
4-Preparation of customs documents,<br>
5-Collection of goods from the loading address,<br>
6-Customs clearance of the goods and departure,<br>
7-Payment of the remaining balance,<br>
8-Customs clearance procedures in the destination country,<br>
9-Delivery of goods to the delivery address.";
                                } else {
                                    // Varsayılan karayolu süreci
                                    $transport_process_text = "1-Presentation and mutual signing of the offer,<br>
2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>
3-Preparation of customs documents,<br>
4-Collection of goods from the loading address,<br>
5-Customs clearance of the goods and departure,<br>
6-Payment of the remaining balance,<br>
7-Customs clearance procedures in the destination country,<br>
8-Delivery of goods to the delivery address.";
                                }
                            } else {
                                // Türkçe süreç metinleri
                                if (strpos($transport_mode_lower, 'karayolu') !== false) {
                                    $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>
2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>
3-Gümrük evraklarının hazırlanması,<br>
4-Eşyaların yükleme adresinden alınması,<br>
5-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>
6-Kalan bakiye ödemesinin yapılması,<br>
7-Varış ülke gümrük açılım işlemlerinin yapılması,<br>
8-Eşyanın teslimat adresine teslimi şeklindedir.";
                                } elseif (strpos($transport_mode_lower, 'deniz') !== false) {
                                    $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>
2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>
3-Gemi firmasından konteynır rezervasyonunun yapılması,<br>
4-Gümrük evraklarının hazırlanması,<br>
5-Eşyaların yükleme adresinden alınması,<br>
6-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>
7-Kalan bakiye ödemesinin yapılması,<br>
8-Varış ülke gümrük açılım işlemlerinin yapılması,<br>
9-Eşyanın teslimat adresine teslimi şeklindedir.";
                                } elseif (strpos($transport_mode_lower, 'hava') !== false) {
                                    $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>
2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>
3-Havayolu şirketinden kargo rezervasyonunun yapılması,<br>
4-Gümrük evraklarının hazırlanması,<br>
5-Eşyaların yükleme adresinden alınması,<br>
6-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>
7-Kalan bakiye ödemesinin yapılması,<br>
8-Varış ülke gümrük açılım işlemlerinin yapılması,<br>
9-Eşyanın teslimat adresine teslimi şeklindedir.";
                                } else {
                                    // Varsayılan karayolu süreci
                                    $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>
2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>
3-Gümrük evraklarının hazırlanması,<br>
4-Eşyaların yükleme adresinden alınması,<br>
5-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>
6-Kalan bakiye ödemesinin yapılması,<br>
7-Varış ülke gümrük açılım işlemlerinin yapılması,<br>
8-Eşyanın teslimat adresine teslimi şeklindedir.";
                                }
                                }
                            }
                        }
                        ?>

                                                <div class="transport-process-content">
                            <div class="editable" data-field="transport_process_text" data-type="textarea" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 150px;"
                                 onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?php echo $transport_process_text; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms Section -->
                <div class="form-section" data-section="terms">
                    <div class="section-header">
                        <div class="section-label">
                            <span class="editable" data-field="custom_terms_title" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                  onclick="editField(this)" title="Düzenlemek için tıklayın">
                                <?php
                                echo !empty($custom_fields['custom_terms_title']) ? htmlspecialchars($custom_fields['custom_terms_title']) : $t['terms'];
                                ?>
                            </span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce quotes tablosundaki terms_content'i kontrol et, yoksa template'den al
                        $terms_content = $quote['terms_content'] ?? $quote['template_terms_content'] ?? '';
                        if (!empty($terms_content)): ?>
                            <!-- Şartlar içeriği -->
                            <?php
                            // Şablon değişkenlerini değiştir
                            $terms_content = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $terms_content);
                            $terms_content = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $terms_content);
                            $terms_content = str_replace('{origin}', htmlspecialchars($quote['origin']), $terms_content);
                            $terms_content = str_replace('{destination}', htmlspecialchars($quote['destination']), $terms_content);
                            $terms_content = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $terms_content);
                            $terms_content = str_replace('{volume}', number_format($quote['volume'] ?? 0, 2, ',', '.'), $terms_content);
                            $terms_content = str_replace('{pieces}', $quote['pieces'] ?? $t['not_specified'], $terms_content);
                            $terms_content = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $terms_content);
                            $terms_content = str_replace('{valid_until}', formatDate($quote['valid_until']), $terms_content);
                            $terms_content = str_replace('{cargo_type}', $quote['cargo_type'] ?? 'Genel', $terms_content);
                            $terms_content = str_replace('{trade_type}', $quote['trade_type'] ?? '', $terms_content);
                            $terms_content = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $terms_content);
                            $terms_content = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $terms_content);

                            echo '<div class="editable" data-field="terms_content" data-type="textarea" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 100px;" onclick="editField(this)" title="Düzenlemek için tıklayın">' . $terms_content . '<span class="edit-indicator"></span></div>';
                            ?>
                        <?php else: ?>
                            <!-- Boş şartlar içeriği -->
                            <div class="editable" data-field="terms_content" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 100px; color: #888; font-style: italic;" onclick="editField(this)" title="Düzenlemek için tıklayın">
                                Şartlar için içerik eklemek üzere tıklayın...<span class="edit-indicator"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Section 1 -->
                <?php if (!empty($quote['additional_section1_title']) || !empty($quote['additional_section1_content'])): ?>
                <div class="form-section" id="additional-section-1" data-section="additional1">
                    <div class="section-header" style="position: relative;">
                        <div class="section-label editable" data-field="additional_section1_title" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;" onclick="editField(this)" title="Düzenlemek için tıklayın">
                            <?php echo htmlspecialchars(isset($quote['additional_section1_title']) && !empty($quote['additional_section1_title']) ? $quote['additional_section1_title'] : 'Ek Bölüm 1'); ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeAdditionalSection(1)" title="Bu bölümü kaldır" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); padding: 2px 6px; font-size: 12px;">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        $additional_content1 = isset($quote['additional_section1_content']) ? $quote['additional_section1_content'] : '';
                        if (!empty($additional_content1)):
                            // Şablon değişkenlerini değiştir
                            $additional_content1 = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $additional_content1);
                            $additional_content1 = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $additional_content1);
                            $additional_content1 = str_replace('{origin}', htmlspecialchars($quote['origin']), $additional_content1);
                            $additional_content1 = str_replace('{destination}', htmlspecialchars($quote['destination']), $additional_content1);
                            $additional_content1 = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $additional_content1);
                            $additional_content1 = str_replace('{volume}', number_format($quote['volume'] ?? 0, 2, ',', '.'), $additional_content1);
                            $additional_content1 = str_replace('{pieces}', $quote['pieces'] ?? $t['not_specified'], $additional_content1);
                            $additional_content1 = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $additional_content1);
                            $additional_content1 = str_replace('{valid_until}', formatDate($quote['valid_until']), $additional_content1);
                            $additional_content1 = str_replace('{cargo_type}', $quote['cargo_type'] ?? 'Genel', $additional_content1);
                            $additional_content1 = str_replace('{trade_type}', $quote['trade_type'] ?? '', $additional_content1);
                            $additional_content1 = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $additional_content1);
                            $additional_content1 = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $additional_content1);

                            echo '<div class="editable" data-field="additional_section1_content" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 60px;" onclick="editField(this)" title="Düzenlemek için tıklayın">' . $additional_content1 . '<span class="edit-indicator"></span></div>';
                        else:
                            echo '<div class="editable" data-field="additional_section1_content" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 60px; color: #888; font-style: italic;" onclick="editField(this)" title="Düzenlemek için tıklayın">Bu bölüm için içerik eklemek üzere tıklayın...<span class="edit-indicator"></span></div>';
                        endif;
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Section 2 -->
                <?php if (!empty($quote['additional_section2_title']) || !empty($quote['additional_section2_content'])): ?>
                <div class="form-section" id="additional-section-2" data-section="additional2">
                    <div class="section-header" style="position: relative;">
                        <div class="section-label editable" data-field="additional_section2_title" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;" onclick="editField(this)" title="Düzenlemek için tıklayın">
                            <?php echo htmlspecialchars(isset($quote['additional_section2_title']) && !empty($quote['additional_section2_title']) ? $quote['additional_section2_title'] : 'Ek Bölüm 2'); ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeAdditionalSection(2)" title="Bu bölümü kaldır" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); padding: 2px 6px; font-size: 12px;">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        $additional_content2 = isset($quote['additional_section2_content']) ? $quote['additional_section2_content'] : '';
                        if (!empty($additional_content2)):
                            // Şablon değişkenlerini değiştir
                            $additional_content2 = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $additional_content2);
                            $additional_content2 = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $additional_content2);
                            $additional_content2 = str_replace('{origin}', htmlspecialchars($quote['origin']), $additional_content2);
                            $additional_content2 = str_replace('{destination}', htmlspecialchars($quote['destination']), $additional_content2);
                            $additional_content2 = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $additional_content2);
                            $additional_content2 = str_replace('{volume}', number_format($quote['volume'] ?? 0, 2, ',', '.'), $additional_content2);
                            $additional_content2 = str_replace('{pieces}', $quote['pieces'] ?? $t['not_specified'], $additional_content2);
                            $additional_content2 = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $additional_content2);
                            $additional_content2 = str_replace('{valid_until}', formatDate($quote['valid_until']), $additional_content2);
                            $additional_content2 = str_replace('{cargo_type}', $quote['cargo_type'] ?? 'Genel', $additional_content2);
                            $additional_content2 = str_replace('{trade_type}', $quote['trade_type'] ?? '', $additional_content2);
                            $additional_content2 = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $additional_content2);
                            $additional_content2 = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $additional_content2);

                            echo '<div class="editable" data-field="additional_section2_content" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 60px;" onclick="editField(this)" title="Düzenlemek için tıklayın">' . $additional_content2 . '<span class="edit-indicator"></span></div>';
                        else:
                            echo '<div class="editable" data-field="additional_section2_content" style="cursor: pointer; padding: 4px 8px; border-radius: 3px; transition: background 0.2s; min-height: 60px; color: #888; font-style: italic;" onclick="editField(this)" title="Düzenlemek için tıklayın">Bu bölüm için içerik eklemek üzere tıklayın...<span class="edit-indicator"></span></div>';
                        endif;
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ek Bölüm Ekleme Butonu -->
                <div class="form-section" style="text-align: center; padding: 20px; border: 2px dashed #ddd; background: #f8f9fa; margin-bottom: 20px;">
                    <?php if (empty($quote['additional_section1_title']) && empty($quote['additional_section1_content'])): ?>
                        <button type="button" class="btn btn-success" onclick="addAdditionalSection(1)" style="margin-right: 10px;">
                            <i class="fas fa-plus"></i> Ek Bölüm 1 Ekle
                        </button>
                    <?php endif; ?>

                    <?php if (empty($quote['additional_section2_title']) && empty($quote['additional_section2_content'])): ?>
                        <button type="button" class="btn btn-success" onclick="addAdditionalSection(2)">
                            <i class="fas fa-plus"></i> Ek Bölüm 2 Ekle
                        </button>
                    <?php endif; ?>

                    <?php if ((!empty($quote['additional_section1_title']) || !empty($quote['additional_section1_content'])) && (!empty($quote['additional_section2_title']) || !empty($quote['additional_section2_content']))): ?>
                        <p style="color: #666; margin: 0; font-style: italic;">Tüm ek bölümler aktif</p>
                    <?php endif; ?>
                </div>

                <!-- Kompakt Müşteri Görünümü Ayarları -->
                <div style="background: #f1f3f4; border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 13px; color: #555; font-weight: 500;">
                            <i class="fas fa-eye me-1" style="color: #666;"></i>
                            Referans görselleri müşteri tarafında gösterilsin mi?
                        </span>

                        <label style="display: inline-flex; align-items: center; cursor: pointer; margin: 0;">
                            <input type="checkbox" id="showReferenceImages"
                                   data-field="show_reference_images"
                                   data-quote-id="<?php echo $quote['quote_number']; ?>"
                                   onchange="toggleReferenceImages(this)"
                                   <?php echo (($quote['show_reference_images'] ?? 0) == 1) ? 'checked' : ''; ?>
                                   style="margin-right: 6px; transform: scale(0.9);">
                            <span style="font-size: 12px; color: #666;">
                                <?php echo (($quote['show_reference_images'] ?? 0) == 1) ? 'Göster' : 'Gizle'; ?>
                            </span>
                        </label>
                    </div>
                </div>



            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-content">
                <div class="footer-left">
                    <strong>Europatrans Global Lojistik</strong><br>
                    Şehit Cengiz Karaca Mah. Sokulu Mehmet Paşa Cad. No: 186/A 06460 Çankaya/Ankara
                </div>
                <div class="footer-right">
                    <strong>www.europatrans.com.tr</strong><br>
                    info@europatrans.com.tr | Tel: 444 6 995
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Global variables for managing costs
        let additionalCosts = [];

        // Load existing additional costs on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAdditionalCosts();
            loadFromLocalStorage(); // Yerel depolamadan da yükle

            // Button durumunu başlangıçta da güncelle
            setTimeout(() => {
                updateAddCostButton();
            }, 500);
        });

        // Load additional costs from server
        function loadAdditionalCosts() {
            const quoteId = <?= json_encode($quote['id']) ?>;

            fetch(`../api/get-additional-costs.php?quote_id=${quoteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.costs && data.costs.length > 0) {
                    additionalCosts = data.costs.map(cost => ({
                        id: cost.id || Date.now() + Math.random(),
                        name: cost.name || cost.description,
                        description: cost.description || '',
                        amount: parseFloat(cost.amount),
                        currency: cost.currency || '<?= $currency ?>'
                    }));
                    renderAdditionalCostsTable();
                } else {
                    renderAdditionalCostsTable();
                }
            })
            .catch(error => {
                console.error('Error loading costs:', error);
                renderAdditionalCostsTable();
            });
        }

        // Render additional costs table
        function renderAdditionalCostsTable() {
            const table = document.getElementById('additionalCostsTable');

            if (additionalCosts.length === 0) {
                table.innerHTML = '';
                updateAddCostButton();
                return;
            }

                        let html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;">';

            additionalCosts.forEach((cost, index) => {
                const formattedAmount = formatPrice(cost.amount, cost.currency);
                html += `
                    <tr>
                        <td style="padding: 8px 16px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); font-weight: 500; font-size: 12px; width: 220px; position: relative; color: #155724; border-right: 1px solid rgba(255,255,255,0.3);">
                            <div onclick="editCostName(${index})" style="cursor: pointer;" title="Düzenlemek için tıklayın">
                                <span id="costNameDisplay_${index}">${cost.name}</span>
                                <input type="text" id="costNameEdit_${index}" style="display: none; width: 90%; border: none; background: transparent; font-weight: 500; color: #155724; font-size: 12px;" value="${cost.name}" onblur="saveCostName(${index})" onkeypress="handleCostNameKeypress(event, ${index})">
                            </div>
                            <div onclick="editCostDescription(${index})" style="cursor: pointer; font-size: 10px; color: #0a4622; margin-top: 3px; font-style: italic; min-height: 12px;" title="Açıklama eklemek için tıklayın">
                                <span id="costDescDisplay_${index}">${cost.description || 'Açıklama ekleyin...'}</span>
                                <input type="text" id="costDescEdit_${index}" style="display: none; width: 90%; border: none; background: transparent; font-size: 10px; color: #0a4622; font-style: italic;" value="${cost.description}" onblur="saveCostDescription(${index})" onkeypress="handleCostDescKeypress(event, ${index})" placeholder="Açıklama girin...">
                            </div>
                        </td>
                        <td style="padding: 8px 16px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); text-align: right; font-size: 16px; font-weight: 600; color: #155724; position: relative;">
                            <span onclick="editCostAmount(${index})" style="cursor: pointer;" title="Fiyatı düzenlemek için tıklayın">${formattedAmount}</span>
                            <input type="text" id="costAmountEdit_${index}" style="display: none; width: 90%; text-align: right; border: none; background: transparent; font-size: 16px; font-weight: 600; color: #155724;" value="${cost.amount}" onblur="saveCostAmount(${index})" onkeypress="handleCostAmountKeypress(event, ${index})">
                            <button onclick="removeCost(${index})" style="position: absolute; right: 4px; top: 4px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; cursor: pointer; line-height: 1;" title="Sil">×</button>
                        </td>
                    </tr>
                `;
            });

            html += '</table>';
            table.innerHTML = html;
            updateAddCostButton();
        }

        // Update add cost button state
        function updateAddCostButton() {
            const addButton = document.querySelector('button[onclick="addNewCost()"]');
            if (!addButton) return;

            const currentCount = additionalCosts.length;
            const maxCount = 4;

            if (currentCount >= maxCount) {
                addButton.disabled = true;
                addButton.style.background = '#6c757d';
                addButton.style.cursor = 'not-allowed';
                addButton.innerHTML = '<i class="fas fa-ban"></i> Maksimum 4 ek maliyet';
                addButton.title = 'Maksimum 4 adet ek maliyet ekleyebilirsiniz';
            } else {
                addButton.disabled = false;
                addButton.style.background = '#28a745';
                addButton.style.cursor = 'pointer';
                addButton.innerHTML = `<i class="fas fa-plus"></i> Ek teklif (${currentCount}/${maxCount})`;
                addButton.title = `${maxCount - currentCount} adet daha ekleyebilirsiniz`;
            }
        }

                // Add new cost
        function addNewCost() {
            // Maksimum 4 ek maliyet sınırı
            if (additionalCosts.length >= 4) {
                alert('Maksimum 4 adet ek maliyet ekleyebilirsiniz.');
                return;
            }

            const newCost = {
                id: Date.now() + Math.random(),
                name: 'Yeni Maliyet',
                description: '',
                amount: 0,
                currency: '<?= $currency ?>'
            };

            additionalCosts.push(newCost);
            renderAdditionalCostsTable();

            // Sunucuya kaydet
            saveAdditionalCosts();

            // Focus on the new cost name for editing
            setTimeout(() => {
                const index = additionalCosts.length - 1;
                editCostName(index);
            }, 100);
        }

        // Edit cost name
        function editCostName(index) {
            const display = document.getElementById(`costNameDisplay_${index}`);
            const input = document.getElementById(`costNameEdit_${index}`);

            if (display && input) {
                display.style.display = 'none';
                input.style.display = 'inline-block';
                input.focus();
                input.select();
            }
        }

                // Save cost name
        function saveCostName(index) {
            const input = document.getElementById(`costNameEdit_${index}`);
            const display = document.getElementById(`costNameDisplay_${index}`);

            if (input && display) {
                additionalCosts[index].name = input.value || 'Yeni Maliyet';
                display.textContent = additionalCosts[index].name;
                display.style.display = 'inline';
                input.style.display = 'none';

                // Sunucuya kaydet
                saveAdditionalCosts();
            }
        }

        // Edit cost description
        function editCostDescription(index) {
            const display = document.getElementById(`costDescDisplay_${index}`);
            const input = document.getElementById(`costDescEdit_${index}`);

            if (display && input) {
                display.style.display = 'none';
                input.style.display = 'inline-block';
                input.focus();
            }
        }

                // Save cost description
        function saveCostDescription(index) {
            const input = document.getElementById(`costDescEdit_${index}`);
            const display = document.getElementById(`costDescDisplay_${index}`);

            if (input && display) {
                additionalCosts[index].description = input.value || '';
                display.textContent = additionalCosts[index].description || 'Açıklama ekleyin...';
                display.style.display = 'inline';
                input.style.display = 'none';

                // Sunucuya kaydet
                saveAdditionalCosts();
            }
        }

        // Handle cost description keypress
        function handleCostDescKeypress(event, index) {
            if (event.key === 'Enter') {
                saveCostDescription(index);
            }
        }

        // Handle cost name keypress
        function handleCostNameKeypress(event, index) {
            if (event.key === 'Enter') {
                saveCostName(index);
            }
        }

        // Edit cost amount
        function editCostAmount(index) {
            const display = document.querySelector(`#additionalCostsTable tr:nth-child(${index + 1}) td:last-child span`);
            const input = document.getElementById(`costAmountEdit_${index}`);

            if (display && input) {
                display.style.display = 'none';
                input.style.display = 'inline-block';
                input.focus();
                input.select();
            }
        }

                // Save cost amount
        function saveCostAmount(index) {
            const input = document.getElementById(`costAmountEdit_${index}`);
            const display = document.querySelector(`#additionalCostsTable tr:nth-child(${index + 1}) td:last-child span`);

            if (input && display) {
                const amount = parseFloat(input.value) || 0;
                additionalCosts[index].amount = amount;
                display.textContent = formatPrice(amount, additionalCosts[index].currency);
                display.style.display = 'inline';
                input.style.display = 'none';

                // Sunucuya kaydet
                saveAdditionalCosts();
            }
        }

        // Handle cost amount keypress
        function handleCostAmountKeypress(event, index) {
            if (event.key === 'Enter') {
                saveCostAmount(index);
            }
        }

        // Remove cost
        function removeCost(index) {
            if (confirm('Bu maliyeti silmek istediğinizden emin misiniz?')) {
                additionalCosts.splice(index, 1);
                renderAdditionalCostsTable();

                // Sunucuya kaydet
                saveAdditionalCosts();
            }
        }

        // Edit main price
        function editMainPrice() {
            const display = document.getElementById('mainPriceDisplay');
            const input = document.getElementById('mainPriceEdit');

            if (display && input) {
                display.style.display = 'none';
                input.style.display = 'inline-block';
                input.focus();
                input.select();
            }
        }

                // Save main price
        function saveMainPrice() {
            const input = document.getElementById('mainPriceEdit');
            const display = document.getElementById('mainPriceDisplay');

            if (input && display) {
                const amount = parseFloat(input.value) || 0;
                display.textContent = formatPrice(amount, '<?= $currency ?>');
                display.style.display = 'inline';
                input.style.display = 'none';

                // Sunucuya kaydet
                saveQuoteChanges();
            }
        }

        // Handle main price keypress
        function handleMainPriceKeypress(event) {
            if (event.key === 'Enter') {
                saveMainPrice();
            }
        }

                // CSS for editable fields
        const editableCSS = `
            .editable:hover {
                background-color: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
            }
            .editing {
                background-color: #fff !important;
                border: 2px solid #2c5aa0 !important;
            }
            .editable-input {
                width: 100%;
                padding: 2px 6px;
                border: 2px solid #2c5aa0;
                border-radius: 3px;
                font-size: 12px;
                background: white;
                outline: none;
                font-family: inherit;
                line-height: 1.4;
            }
            textarea.editable-input {
                min-height: 120px;
                resize: vertical;
                padding: 8px;
                line-height: 1.5;
            }
            .editable {
                cursor: pointer;
                position: relative;
                min-height: 20px;
                padding: 4px 8px;
                border-radius: 3px;
                transition: all 0.2s ease;
                border: 1px solid transparent;
            }

            /* Tüm editable değer alanları için sağa hizalama */
            .form-section .editable {
                text-align: right !important;
            }

            /* Metin içerik alanları ve etiket alanları sola hizalı kalacak */
            .form-section .editable[data-field*="label"],
            .form-section .editable[data-field*="greeting"],
            .form-section .editable[data-field*="intro"],
            .form-section .editable[data-field*="name"],
            .form-section .editable[data-field="transport_process_text"],
            .form-section .editable[data-field="services_content"],
            .form-section .editable[data-field="terms_content"],
            .form-section .editable[data-field*="content"] {
                text-align: left !important;
            }

                        /* Genel bilgiler alanındaki etiket-değer arası noktalı çizgi */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] {
                position: relative;
                overflow: visible;
            }

            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"]:before {
                content: "............................................................................................";
                position: absolute;
                top: 50%;
                left: 0;
                right: 0;
                height: 20px;
                line-height: 20px;
                text-align: center;
                color: #ccc;
                font-size: 14px;
                letter-spacing: 3px;
                z-index: 0;
                pointer-events: none;
                white-space: nowrap;
                overflow: hidden;
            }

            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span {
                position: relative;
                background: white;
                z-index: 2;
                display: inline-block;
            }

            /* Değer alanlarını sağa hizala */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:last-child,
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span.editable:last-child,
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:nth-child(2) {
                text-align: right !important;
                justify-self: end;
                padding-right: 8px;
            }
            .editable:hover {
                background-color: rgba(0, 123, 255, 0.1);
                border: 1px solid rgba(0, 123, 255, 0.3);
            }
            .editable.editing {
                background-color: #fff;
                border: 1px solid #007bff;
            }
            .editable input, .editable textarea, .editable select {
                width: 100%;
                border: none;
                background: transparent;
                font-family: inherit;
                font-size: inherit;
                font-weight: inherit;
                color: inherit;
                padding: 0;
                outline: none;
            }
            .editable textarea {
                min-height: 80px;
                resize: vertical;
            }
            .rich-editor-container {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                z-index: 10000;
                display: none;
                align-items: center;
                justify-content: center;
            }
            .rich-editor-modal {
                background: white;
                border-radius: 12px;
                width: 95%;
                max-width: 1100px;
                max-height: 90%;
                overflow: hidden;
                box-shadow: 0 15px 50px rgba(0,0,0,0.4);
                animation: modalSlideIn 0.3s ease-out;
            }
            @keyframes modalSlideIn {
                from {
                    opacity: 0;
                    transform: translateY(-50px) scale(0.9);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            .rich-editor-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                background: #f8f9fa;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .rich-editor-content {
                padding: 20px;
                max-height: 500px;
                overflow-y: auto;
            }
            .rich-editor-footer {
                padding: 15px 20px;
                border-top: 1px solid #ddd;
                background: #f8f9fa;
                text-align: right;
            }
            .rich-editor-textarea {
                width: 100%;
                min-height: 300px;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 15px;
                font-family: Arial, sans-serif;
                font-size: 14px;
                line-height: 1.6;
                background: white;
                outline: none;
            }
            .rich-editor-textarea:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
            }
            .edit-indicator {
                position: absolute;
                top: 2px;
                right: 2px;
                background: #007bff;
                color: white;
                font-size: 10px;
                padding: 2px 4px;
                border-radius: 2px;
                opacity: 0;
                transition: opacity 0.2s;
                pointer-events: none;
            }
            .editable:hover .edit-indicator {
                opacity: 1;
            }
            @media (max-width: 768px) {
                .rich-editor-modal {
                    width: 98%;
                    max-width: none;
                    height: 95vh;
                    max-height: 95vh;
                    margin: 10px;
                }
                .rich-editor-content {
                    padding: 15px;
                    max-height: calc(95vh - 140px);
                }
                .btn-toolbar {
                    flex-wrap: wrap;
                }
                .btn-group {
                    margin-bottom: 5px;
                }
                .d-none.d-md-inline {
                    display: none !important;
                }
                .form-select-sm {
                    font-size: 0.7rem;
                    padding: 2px 4px;
                }
            }

            /* Toolbar düzenlemeleri */
            .btn-toolbar .form-select {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
                margin-right: 2px;
            }

            .btn-toolbar .btn-sm {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }

            .btn-toolbar .btn-group {
                margin-right: 0.5rem;
                margin-bottom: 0.25rem;
            }
            @media print {
                .admin-header {
                    display: none !important;
                }
                .edit-indicator {
                    display: none !important;
                }
                .rich-editor-container {
                    display: none !important;
                }
            }
        `;

        // CSS'i sayfaya ekle
        if (!document.getElementById('editableStyles')) {
            const styleSheet = document.createElement('style');
            styleSheet.id = 'editableStyles';
            styleSheet.innerHTML = editableCSS;
            document.head.appendChild(styleSheet);
        }

        // Make field editable on click
        function makeEditable(element) {
            if (element.classList.contains('editing')) return;

            const field = element.getAttribute('data-field');
            const type = element.getAttribute('data-type') || 'text';
            const step = element.getAttribute('data-step');
            const currentValue = element.textContent.trim();

            // Değeri temizle (örn: "1.500 kg" -> "1500")
            let cleanValue = currentValue;
            if (type === 'number') {
                cleanValue = currentValue.replace(/[^\d.,]/g, '').replace(',', '.');
            } else if (type === 'date') {
                // Tarih formatını dönüştür
                const dateParts = currentValue.split('.');
                if (dateParts.length === 3) {
                    cleanValue = `${dateParts[2]}-${dateParts[1].padStart(2, '0')}-${dateParts[0].padStart(2, '0')}`;
                }
            }

            element.classList.add('editing');

            // Input oluştur
            let input;
            if (type === 'date') {
                input = document.createElement('input');
                input.type = 'date';
                input.value = cleanValue;
            } else if (type === 'number') {
                input = document.createElement('input');
                input.type = 'number';
                input.value = cleanValue;
                if (step) input.step = step;
            } else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.value = cleanValue;
                input.rows = 6;
                input.style.resize = 'vertical';
                input.style.minHeight = '120px';
            } else {
                input = document.createElement('input');
                input.type = type;
                input.value = cleanValue;
            }

            input.className = 'editable-input';

            // Eski içeriği sakla
            const originalContent = element.innerHTML;
            element.innerHTML = '';
            element.appendChild(input);

            input.focus();
            if (type === 'text' || type === 'email') {
                input.select();
            }

            // Kaydetme fonksiyonu
            const saveEdit = () => {
                const newValue = input.value.trim();
                element.classList.remove('editing');

                if (newValue && newValue !== cleanValue) {
                    updateGeneralInfo(field, newValue);

                    // Görüntüyü güncelle
                    if (type === 'number') {
                        const formatted = parseFloat(newValue).toLocaleString('tr-TR');
                        const unit = currentValue.includes('kg') ? ' kg' :
                                   currentValue.includes('m³') ? ' m³' : '';
                        element.innerHTML = formatted + unit;
                    } else if (type === 'date') {
                        // Tarih formatını geri çevir
                        const date = new Date(newValue);
                        const formatted = date.toLocaleDateString('tr-TR');
                        element.innerHTML = formatted;
                    } else {
                        element.innerHTML = newValue;
                    }
                } else {
                    element.innerHTML = originalContent;
                }
            };

            // İptal fonksiyonu
            const cancelEdit = () => {
                element.classList.remove('editing');
                element.innerHTML = originalContent;
            };

            // Event listeners
            input.addEventListener('blur', saveEdit);
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveEdit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        }

        // Update general information
        function updateGeneralInfo(field, value) {
            const quoteId = <?= json_encode($quote['id']) ?>;

            showSaveIndicator('Kaydediliyor...');

            // Değeri hazırla
            let updateData = {
                quote_id: quoteId,
                field: field,
                value: value
            };

            // Özel alanlar için farklı işlemler
            if (field === 'full_name') {
                // İsim soyadı ayrıştır
                const names = value.trim().split(' ');
                updateData.first_name = names[0] || '';
                updateData.last_name = names.slice(1).join(' ') || '';
                delete updateData.field;
                delete updateData.value;
            }

            fetch('../api/update-general-info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(updateData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSaveIndicator('✓ Kaydedildi', 'success');
                } else {
                    showSaveIndicator('❌ Hata: ' + (data.error || 'Bilinmeyen hata'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showSaveIndicator('❌ Bağlantı hatası', 'error');
            });
        }

        // Save quote changes to server
        function saveQuoteChanges() {
            const quoteId = <?= json_encode($quote['id']) ?>;
            const mainPriceInput = document.getElementById('mainPriceEdit');
            const mainPrice = mainPriceInput ? mainPriceInput.value : 0;

            showSaveIndicator('Kaydediliyor...');

            fetch('../api/update-quote-price.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quote_id: quoteId,
                    final_price: parseFloat(mainPrice) || 0
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSaveIndicator('✓ Kaydedildi', 'success');
                    clearLocalStorage(); // Başarılı kaydetme sonrası yerel depolamayı temizle
                } else {
                    showSaveIndicator('❌ Hata: ' + data.message, 'error');
                    saveToLocalStorage(); // Hata durumunda yerel depolamaya kaydet
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showSaveIndicator('❌ Bağlantı hatası', 'error');
                saveToLocalStorage(); // Bağlantı hatası durumunda yerel depolamaya kaydet
            });
        }

        // Save additional costs to server
        function saveAdditionalCosts() {
            const quoteId = <?= json_encode($quote['id']) ?>;

            showSaveIndicator('Kaydediliyor...');

            fetch('../api/save-additional-costs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quote_id: quoteId,
                    costs: additionalCosts
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSaveIndicator('✓ Kaydedildi', 'success');
                    clearLocalStorage(); // Başarılı kaydetme sonrası yerel depolamayı temizle
                } else {
                    showSaveIndicator('❌ Hata: ' + data.message, 'error');
                    saveToLocalStorage(); // Hata durumunda yerel depolamaya kaydet
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showSaveIndicator('❌ Bağlantı hatası', 'error');
                saveToLocalStorage(); // Bağlantı hatası durumunda yerel depolamaya kaydet
            });
        }

        // Show save indicator
        function showSaveIndicator(message, type = 'info') {
            // Remove existing indicator
            const existing = document.getElementById('saveIndicator');
            if (existing) {
                existing.remove();
            }

            const indicator = document.createElement('div');
            indicator.id = 'saveIndicator';
            indicator.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 10px 20px;
                border-radius: 5px;
                color: white;
                font-size: 14px;
                font-weight: 500;
                z-index: 10000;
                transition: all 0.3s ease;
                ${type === 'success' ? 'background: #28a745;' : type === 'error' ? 'background: #dc3545;' : 'background: #6c757d;'}
            `;
            indicator.textContent = message;

            document.body.appendChild(indicator);

            // Auto remove after 3 seconds for success/error messages
            if (type !== 'info') {
                setTimeout(() => {
                    if (indicator && indicator.parentNode) {
                        indicator.style.opacity = '0';
                        setTimeout(() => {
                            if (indicator && indicator.parentNode) {
                                indicator.remove();
                            }
                        }, 300);
                    }
                }, 3000);
            }
        }

        // Save to localStorage as backup
        function saveToLocalStorage() {
            const quoteId = <?= json_encode($quote['id']) ?>;
            const mainPriceInput = document.getElementById('mainPriceEdit');
            const mainPrice = mainPriceInput ? mainPriceInput.value : document.getElementById('mainPriceDisplay').textContent.replace(/[^0-9.,]/g, '').replace(',', '.');

            const data = {
                quoteId: quoteId,
                mainPrice: mainPrice,
                additionalCosts: additionalCosts,
                timestamp: Date.now()
            };

            localStorage.setItem(`quote_${quoteId}`, JSON.stringify(data));
        }

        // Load from localStorage
        function loadFromLocalStorage() {
            const quoteId = <?= json_encode($quote['id']) ?>;
            const stored = localStorage.getItem(`quote_${quoteId}`);

            if (stored) {
                try {
                    const data = JSON.parse(stored);
                    const now = Date.now();
                    const stored_time = data.timestamp || 0;

                    // 24 saat içindeki veriler geçerli
                    if (now - stored_time < 24 * 60 * 60 * 1000) {
                        // Yerel depolamada daha yeni veriler varsa uyarı göster
                        if (confirm('Yerel depolamada kaydedilmemiş değişiklikleriniz var. Bu değişiklikleri kullanmak ister misiniz?')) {
                            if (data.mainPrice && data.mainPrice !== '0') {
                                document.getElementById('mainPriceDisplay').textContent = formatPrice(parseFloat(data.mainPrice), '<?= $currency ?>');
                            }

                            if (data.additionalCosts && data.additionalCosts.length > 0) {
                                additionalCosts = data.additionalCosts;
                                renderAdditionalCostsTable();
                            }

                            showSaveIndicator('💾 Yerel veriler yüklendi - Kaydetmek için değişiklik yapın', 'info');
                        }
                    } else {
                        // Eski verileri temizle
                        localStorage.removeItem(`quote_${quoteId}`);
                    }
                } catch (e) {
                    console.error('Yerel depolama verisi okunamadı:', e);
                }
            }
        }

        // Clear localStorage after successful save
        function clearLocalStorage() {
            const quoteId = <?= json_encode($quote['id']) ?>;
            localStorage.removeItem(`quote_${quoteId}`);
        }

        // Format price with currency
        function formatPrice(amount, currency) {
            const formatted = new Intl.NumberFormat('tr-TR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            }).format(amount);

            switch(currency) {
                case 'USD':
                    return '$' + formatted;
                case 'EUR':
                    return '€' + formatted;
                case 'TL':
                default:
                    return formatted + ' TL';
            }
        }



        // Toggle approval button based on checkbox
        function toggleApprovalButton() {
            const checkbox = document.getElementById('approvalCheckbox');
            const button = document.getElementById('approveButton');

            if (checkbox.checked) {
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
            } else {
                button.disabled = true;
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
            }
                }

        // Approve quote functionality
        function approveQuote() {
            const quoteNumber = '<?php echo $quote['quote_number']; ?>';
            const customerName = '<?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>';

            if (confirm('Teklife onay verdiğinizde firmaya onay maili gidecektir. Onaylıyor musunuz?')) {
                // Show loading
                const btn = document.querySelector('.btn-approve');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
                btn.disabled = true;

                // Send approval email
                fetch('../api/approve-quote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        quote_number: quoteNumber,
                        customer_name: customerName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Onay maili başarıyla gönderildi!');
                        // Optionally redirect or update UI
                    } else {
                        alert('Hata: ' + (data.message || 'Onay maili gönderilemedi'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bağlantı hatası oluştu!');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        }

        // Galeri modal fonksiyonu
        // Global variables for gallery
        let galleryImages = [];
        let swiper = null;

        // Admin klasöründen görsel yollarını düzeltme fonksiyonu
        function fixImagePath(imagePath) {
            // Eğer path zaten ../uploads ile başlıyorsa dokunma
            if (imagePath.startsWith('../uploads/')) {
                return imagePath;
            }
            // Eğer uploads/ ile başlıyorsa başına ../ ekle
            if (imagePath.startsWith('uploads/')) {
                return '../' + imagePath;
            }
            // Eğer / ile başlıyorsa . ekle
            if (imagePath.startsWith('/')) {
                return '..' + imagePath;
            }
            // Diğer durumlarda başına ../uploads/ ekle
            return '../uploads/' + imagePath;
        }

        function showGalleryModal(modeId, modeName) {
            const isEnglish = <?= json_encode($is_english) ?>;
            const referenceImagesText = isEnglish ? 'Reference Images' : 'Referans Resimleri';
            const loadingText = isEnglish ? 'Loading reference images...' : 'Referans resimleri yükleniyor...';
            const loadingSpinner = isEnglish ? 'Loading...' : 'Yükleniyor...';

            document.getElementById('galleryModalTitle').innerHTML = `<i class="fas fa-images me-2"></i>${modeName} ${referenceImagesText}`;

            // Loading göster
            const container = document.getElementById('galleryImagesContainer');
            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 400px; flex-direction: column;">
                    <div class="spinner-border text-light mb-3" role="status">
                        <span class="visually-hidden">${loadingSpinner}</span>
                    </div>
                    <p style="color: #ccc;">${loadingText}</p>
                </div>
            `;

            // Resimleri yükle
            fetch(`../api/get-customer-transport-images.php?mode_id=${modeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.images.length > 0) {
                        galleryImages = data.images;
                        renderGalleryGrid(data.images);
                    } else {
                        const noImagesText = isEnglish ? 'No reference images have been added for this transport mode yet.' : 'Bu taşıma modu için henüz referans resmi eklenmemiş.';
                        container.innerHTML = `
                            <div style="display: flex; align-items: center; justify-content: center; height: 400px; flex-direction: column;">
                                <i class="fas fa-image" style="font-size: 4rem; color: #555; margin-bottom: 1rem;"></i>
                                <p style="color: #ccc; text-align: center;">${noImagesText}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const errorText = isEnglish ? 'An error occurred while loading reference images.' : 'Referans resimleri yüklenirken hata oluştu.';
                    container.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: center; height: 400px; flex-direction: column;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                            <p style="color: #dc3545; text-align: center;">${errorText}</p>
                        </div>
                    `;
                });

            new bootstrap.Modal(document.getElementById('galleryModal')).show();
        }

        function renderGalleryGrid(images) {
            const container = document.getElementById('galleryImagesContainer');
            let html = '';

            images.forEach((image, index) => {
                html += `
                    <div class="gallery-item" onclick="openFullscreen(${index})">
                        <img src="${fixImagePath(image.image_path)}" alt="${image.image_name}" loading="lazy">
                        <div class="gallery-item-overlay">
                            <div class="gallery-item-title">${image.image_name}</div>
                            ${image.image_description ? `<div class="gallery-item-desc">${image.image_description}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Fullscreen functions
        function openFullscreen(startIndex = 0) {
            // Hide gallery modal
            bootstrap.Modal.getInstance(document.getElementById('galleryModal')).hide();

            // Setup swiper slides
            const swiperWrapper = document.getElementById('swiperWrapper');
            swiperWrapper.innerHTML = '';

            galleryImages.forEach((image, index) => {
                const slide = document.createElement('div');
                slide.className = 'swiper-slide';
                slide.innerHTML = `<img src="${fixImagePath(image.image_path)}" alt="${image.image_name}" loading="lazy">`;
                swiperWrapper.appendChild(slide);
            });

            // Show fullscreen modal
            const fullscreenModal = new bootstrap.Modal(document.getElementById('fullscreenModal'));
            fullscreenModal.show();

            // Initialize Swiper
            setTimeout(() => {
                if (swiper) {
                    swiper.destroy();
                }

                swiper = new Swiper('#fullscreenSwiper', {
                    initialSlide: startIndex,
                    loop: galleryImages.length > 1,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                        type: 'bullets',
                    },
                    keyboard: {
                        enabled: true,
                        onlyInViewport: false,
                    },
                    on: {
                        slideChange: function() {
                            updateImageInfo(this.activeIndex);
                        }
                    }
                });

                // Show initial image info
                updateImageInfo(startIndex);
            }, 100);
        }

        function updateImageInfo(index) {
            const image = galleryImages[index];
            if (image) {
                document.getElementById('imageInfoTitle').textContent = image.image_name;
                document.getElementById('imageInfoDesc').textContent = image.image_description || '';
            }
        }

        function toggleImageInfo() {
            const overlay = document.getElementById('imageInfoOverlay');
            overlay.classList.toggle('show');
        }

        // Auto-hide image info after 3 seconds
        let infoTimeout;
        function showImageInfoTemporary() {
            const overlay = document.getElementById('imageInfoOverlay');
            overlay.classList.add('show');

            clearTimeout(infoTimeout);
            infoTimeout = setTimeout(() => {
                overlay.classList.remove('show');
            }, 3000);
        }

        // Show info on slide change
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (document.getElementById('fullscreenModal').classList.contains('show')) {
                    if (e.key === 'Escape') {
                        bootstrap.Modal.getInstance(document.getElementById('fullscreenModal')).hide();
                    } else if (e.key === 'i' || e.key === 'I') {
                        toggleImageInfo();
                    }
                }
            });
        });

        // Rich Text Editor Variables
        let currentRichField = null;
        let currentRichElement = null;
        let currentQuoteId = null;

                // Rich Text Editor Functions
        function openRichEditor(element, field, quoteId) {
            currentRichField = field;
            currentRichElement = element;
            currentQuoteId = quoteId;

            const modal = document.getElementById('richEditorModal');
            const content = document.getElementById('richEditorContent');
            const title = document.getElementById('richEditorTitle');

            // Get current content
            let currentContent = element.innerHTML.replace(/<span class="edit-indicator"><\/span>/g, '').trim();

            // Field title mapping
            const fieldTitles = {
                'greeting_text': 'Selamlama Metni',
                'intro_text': 'Giriş Metni',
                'services_content': 'Hizmetler İçeriği',
                'terms_content': 'Şartlar İçeriği',
                'transport_process_text': 'Taşıma Süreci',
                'additional_section1_content': 'Ek Bölüm 1 İçeriği',
                'additional_section2_content': 'Ek Bölüm 2 İçeriği'
            };

            // Set content and title
            content.innerHTML = currentContent;
            title.textContent = fieldTitles[field] || 'İçerik Düzenle';

            // Show modal
            modal.style.display = 'flex';
        }

        function closeRichEditor() {
            const modal = document.getElementById('richEditorModal');
            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');

            // Source view varsa normal görünüme dön
            if (isSourceView) {
                content.style.display = 'block';
                source.style.display = 'none';
                isSourceView = false;

                // Button'u da sıfırla
                const sourceButton = modal.querySelector('[onclick="toggleSourceView()"]');
                if (sourceButton) {
                    sourceButton.classList.remove('active');
                    sourceButton.innerHTML = '<i class="fas fa-code"></i><span class="d-none d-md-inline ms-1"> Kaynak Kodu</span>';
                }
            }

            modal.style.display = 'none';
        }

        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Yeni zenginleştirilmiş fonksiyonlar
        function changeTextColor(color) {
            document.execCommand('foreColor', false, color);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        function changeBackgroundColor(color) {
            document.execCommand('hiliteColor', false, color);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Font boyutu değiştirme
        function changeFontSize(size) {
            if (size) {
                document.execCommand('fontSize', false, size);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        // Font ailesi değiştirme
        function changeFontFamily(fontFamily) {
            if (fontFamily) {
                document.execCommand('fontName', false, fontFamily);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        // Yatay çizgi ekleme
        function insertHorizontalRule() {
            document.execCommand('insertHorizontalRule', false, null);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Link ekleme
        function insertLink() {
            const url = prompt('Bağlantı URL\'sini girin:', 'https://');
            if (url && url !== 'https://') {
                document.execCommand('createLink', false, url);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        function removeFormatting() {
            if (confirm('Tüm biçimlendirmeyi kaldırmak istediğinizden emin misiniz?')) {
                const content = document.getElementById('richEditorContent');
                if (content) {
                    const text = content.innerText || content.textContent || '';
                    content.innerHTML = text.replace(/\n/g, '<br>');
                    content.focus();
                }
            }
        }

                let isSourceView = false;
        function toggleSourceView() {
            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');
            const button = event.target.closest('button');

            if (!content || !source || !button) {
                console.error('Rich editor elementleri bulunamadı');
                return;
            }

            if (!isSourceView) {
                // Normal görünümden kaynak koduna geç
                source.value = content.innerHTML;
                content.style.display = 'none';
                source.style.display = 'block';
                button.classList.add('active');
                button.innerHTML = '<i class="fas fa-eye"></i><span class="d-none d-md-inline ms-1"> Normal Görünüm</span>';
                isSourceView = true;
            } else {
                // Kaynak kodundan normal görünüme geç
                content.innerHTML = source.value;
                content.style.display = 'block';
                source.style.display = 'none';
                button.classList.remove('active');
                button.innerHTML = '<i class="fas fa-code"></i><span class="d-none d-md-inline ms-1"> Kaynak Kodu</span>';
                isSourceView = false;
            }
        }

        function uploadEditorFile(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('ajax', '1');
            formData.append('action', 'upload_editor_file');

            // Loading göster - daha güvenli button bulma
            const button = input.parentElement.querySelector('button[onclick*="richFileInput"]');
            if (!button) {
                console.error('Upload button bulunamadı');
                return;
            }
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
            button.disabled = true;

            fetch('../api/upload-file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                                if (data.success) {
                    // Dosya başarıyla yüklendi, editöre link ekle
                    const editor = document.getElementById('richEditorContent');
                    if (!editor) {
                        console.error('Editor elementi bulunamadı');
                        alert('Editor hatası: İçerik alanı bulunamadı');
                        return;
                    }

                    const fileName = file.name;
                    const fileUrl = data.url;

                    // Dosya tipine göre farklı gösterim
                    let linkHTML;
                    if (file.type.startsWith('image/')) {
                        linkHTML = `<br><img src="${fileUrl}" alt="${fileName}" style="max-width: 100%; height: auto; margin: 10px 0;"><br>`;
                    } else {
                        linkHTML = `<br><a href="${fileUrl}" target="_blank" style="color: #2c5aa0; text-decoration: underline;"><i class="fas fa-file"></i> ${fileName}</a><br>`;
                    }

                    editor.focus();
                    document.execCommand('insertHTML', false, linkHTML);

                    alert('Dosya başarıyla yüklendi ve içeriğe eklendi!');
                } else {
                    alert('Dosya yükleme hatası: ' + (data.message || 'Bilinmeyen hata'));
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Dosya yükleme sırasında hata oluştu.');
            })
            .finally(() => {
                // Loading'i kaldır
                button.innerHTML = originalHTML;
                button.disabled = false;
                input.value = ''; // Input'u temizle
            });
        }

        // Tablo fonksiyonları
        function showTableModal() {
            const tableModal = document.getElementById('tableModal');
            if (tableModal) {
                tableModal.style.display = 'flex';
            }
        }

        function closeTableModal() {
            const tableModal = document.getElementById('tableModal');
            if (tableModal) {
                tableModal.style.display = 'none';
            }
        }

        function insertTable() {
            const rows = parseInt(document.getElementById('tableRows').value) || 3;
            const cols = parseInt(document.getElementById('tableCols').value) || 3;
            const caption = document.getElementById('tableCaption').value.trim();
            const hasHeader = document.getElementById('tableHeader').checked;
            const style = document.getElementById('tableStyle').value;

            // Tablo CSS sınıflarını belirle
            let tableClass = 'table';
            switch (style) {
                case 'striped':
                    tableClass += ' table-striped';
                    break;
                case 'bordered':
                    tableClass += ' table-bordered';
                    break;
                case 'hover':
                    tableClass += ' table-hover';
                    break;
                default:
                    break;
            }

            // Tablo HTML'ini oluştur
            let tableHTML = `<table class="${tableClass}" style="width: 100%; margin: 10px 0;">`;

            // Caption ekle
            if (caption) {
                tableHTML += `<caption style="caption-side: top; text-align: center; font-weight: bold; margin-bottom: 10px;">${caption}</caption>`;
            }

            // Thead ekle (eğer header varsa)
            if (hasHeader) {
                tableHTML += '<thead><tr>';
                for (let j = 0; j < cols; j++) {
                    tableHTML += `<th style="border: 1px solid #ddd; padding: 8px; background-color: #f8f9fa;">Başlık ${j + 1}</th>`;
                }
                tableHTML += '</tr></thead>';
            }

            // Tbody ekle
            tableHTML += '<tbody>';
            const startRow = hasHeader ? 1 : 0;
            const totalRows = hasHeader ? rows : rows;

            for (let i = startRow; i < totalRows + startRow; i++) {
                tableHTML += '<tr>';
                for (let j = 0; j < cols; j++) {
                    tableHTML += `<td style="border: 1px solid #ddd; padding: 8px;">Hücre ${i + 1}-${j + 1}</td>`;
                }
                tableHTML += '</tr>';
            }
            tableHTML += '</tbody></table><br>';

            // Editöre tabloyu ekle
            const editor = document.getElementById('richEditorContent');
            if (editor) {
                editor.focus();
                document.execCommand('insertHTML', false, tableHTML);
            }

            // Modal'ı kapat
            closeTableModal();
        }

                function saveRichContent() {
            let content;

            // Source view'da mıyız kontrol et
            if (isSourceView) {
                content = document.getElementById('richEditorSource').value;
            } else {
                content = document.getElementById('richEditorContent').innerHTML;
            }

            // İçerik temizle - gereksiz HTML'leri kaldır
            content = content.trim();
            if (content === '<br>' || content === '<p></p>' || content === '<p><br></p>') {
                content = '';
            }

            // Update element content - boş olsa bile düzenlenebilir alan kalsın
            if (content === '' || content.length === 0) {
                // Boş içerik için placeholder
                const field = currentRichField;
                let placeholderText = 'Bu bölüm için içerik eklemek üzere tıklayın...';

                // Field türüne göre özel placeholder metinleri
                if (field === 'additional_section1_content') {
                    placeholderText = 'Ek bölüm 1 için içerik eklemek üzere tıklayın...';
                } else if (field === 'additional_section2_content') {
                    placeholderText = 'Ek bölüm 2 için içerik eklemek üzere tıklayın...';
                } else if (field === 'services_content') {
                    placeholderText = 'Hizmetler için içerik eklemek üzere tıklayın...';
                } else if (field === 'terms_content') {
                    placeholderText = 'Şartlar için içerik eklemek üzere tıklayın...';
                } else if (field === 'transport_process_text') {
                    placeholderText = 'Taşıma süreci için içerik eklemek üzere tıklayın...';
                } else if (field === 'greeting_text') {
                    placeholderText = 'Selamlama metni için içerik eklemek üzere tıklayın...';
                } else if (field === 'intro_text') {
                    placeholderText = 'Giriş metni için içerik eklemek üzere tıklayın...';
                }

                currentRichElement.innerHTML = '<span style="color: #888; font-style: italic;" onclick="editField(this.parentElement)">' + placeholderText + '</span><span class="edit-indicator"></span>';
                currentRichElement.style.minHeight = '60px';
                currentRichElement.style.cursor = 'pointer';
            } else {
                currentRichElement.innerHTML = content + '<span class="edit-indicator"></span>';
                currentRichElement.style.cursor = 'pointer';
            }

            // Save to server
            updateField(currentRichField, content, currentQuoteId);

            // Close modal
            closeRichEditor();
        }

        // Simple inline editing for non-rich fields
        function editField(element) {
            const field = element.getAttribute('data-field');
            const quoteId = element.getAttribute('data-quote-id') || '<?php echo $quote['quote_number']; ?>';
            const type = element.getAttribute('data-type') || 'text';

            // For rich text fields, use rich editor
            const richFields = ['greeting_text', 'intro_text', 'services_content', 'terms_content', 'transport_process_text', 'additional_section1_content', 'additional_section2_content'];
            if (richFields.includes(field)) {
                openRichEditor(element, field, quoteId);
                return;
            }

            // For simple fields, use inline editing
            if (element.classList.contains('editing')) return;

            element.classList.add('editing');
            const currentValue = element.textContent.trim();
            let cleanValue = currentValue;

            // Clean value for different types
            if (type === 'number') {
                cleanValue = currentValue.replace(/[^\d.,]/g, '').replace(',', '.');
            }

            // Create input
            let input;
            if (type === 'number') {
                input = document.createElement('input');
                input.type = 'number';
                input.value = cleanValue;
            } else if (type === 'email') {
                input = document.createElement('input');
                input.type = 'email';
                input.value = cleanValue;
            } else {
                input = document.createElement('input');
                input.type = 'text';
                input.value = cleanValue;
            }

            input.style.width = '100%';
            input.style.border = 'none';
            input.style.background = 'transparent';
            input.style.fontFamily = 'inherit';
            input.style.fontSize = 'inherit';
            input.style.fontWeight = 'inherit';
            input.style.color = 'inherit';
            input.style.outline = 'none';

            const originalContent = element.innerHTML;
            element.innerHTML = '';
            element.appendChild(input);

            input.focus();
            input.select();

            let isProcessing = false;

            const saveEdit = () => {
                if (isProcessing) return;
                isProcessing = true;

                const newValue = input.value.trim();

                // Element hala DOM'da mı kontrol et
                if (!document.body.contains(element)) {
                    console.warn('Element DOM\'dan kaldırılmış');
                    return;
                }

                element.classList.remove('editing');

                if (newValue && newValue !== cleanValue) {
                    // Update element content
                    element.innerHTML = newValue;

                    // Save to server
                    updateField(field, newValue, quoteId);
                } else {
                    element.innerHTML = originalContent;
                }
            };

            const cancelEdit = () => {
                if (isProcessing) return;
                isProcessing = true;

                // Element hala DOM'da mı kontrol et
                if (!document.body.contains(element)) {
                    console.warn('Element DOM\'dan kaldırılmış');
                    return;
                }

                element.classList.remove('editing');
                element.innerHTML = originalContent;
            };

            input.addEventListener('blur', (e) => {
                // Küçük bir gecikme ekle ki keydown event'i önce tetiklensin
                setTimeout(() => {
                    if (!isProcessing) {
                        saveEdit();
                    }
                }, 100);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur(); // blur event'ini tetikle
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            });
        }

        // Update field on server
        function updateField(field, value, quoteId) {
            return fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax=1&action=update_field&field=${encodeURIComponent(field)}&value=${encodeURIComponent(value)}&quote_number=${encodeURIComponent(quoteId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Field updated successfully');

                    // Kullanıcıya başarılı kaydetme mesajı göster
                    showAlert('Değişiklik kaydedildi', 'success');

                    return data;
                } else {
                    console.error('Error updating field:', data.message);
                    showAlert('Kaydetme hatası: ' + (data.message || 'Bilinmeyen hata'), 'error');
                    throw new Error(data.message || 'Güncelleme hatası');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Bağlantı hatası - değişiklik kaydedilemedi', 'error');
                throw error;
            });
        }

        // ESC tuşu ile modal kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('richEditorModal');
                if (modal.style.display === 'flex') {
                    closeRichEditor();
                }
            }
        });

        // Modal dışına tıklayınca kapatma - DOM yüklendikten sonra
        setTimeout(function() {
            const richModal = document.getElementById('richEditorModal');
            if (richModal) {
                richModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeRichEditor();
                    }
                });
            }
        }, 100);

        // Ek bölüm ekleme fonksiyonu
        function addAdditionalSection(sectionNumber) {
            const quoteId = '<?php echo $quote['quote_number']; ?>';
            const titleField = `additional_section${sectionNumber}_title`;
            const contentField = `additional_section${sectionNumber}_content`;

            // Varsayılan değerler
            const defaultTitle = `Ek Bölüm ${sectionNumber}`;
            const defaultContent = '';

            // İlk olarak başlığı ayarla
            updateField(titleField, defaultTitle, quoteId).then(() => {
                // Sonra içeriği ayarla
                return updateField(contentField, defaultContent, quoteId);
            }).then(() => {
                // Başarılı ise sayfayı yenile
                window.location.reload();
            }).catch(error => {
                console.error('Ek bölüm eklenirken hata:', error);
                alert('Ek bölüm eklenirken hata oluştu');
            });
        }

                // Ek bölüm kaldırma fonksiyonu
        function removeAdditionalSection(sectionNumber) {
            if (!confirm(`Ek Bölüm ${sectionNumber}'i tamamen kaldırmak istediğinizden emin misiniz?`)) {
                return;
            }

            const quoteId = '<?php echo $quote['quote_number']; ?>';
            const titleField = `additional_section${sectionNumber}_title`;
            const contentField = `additional_section${sectionNumber}_content`;

            // Başlık ve içeriği sil
            updateField(titleField, '', quoteId).then(() => {
                return updateField(contentField, '', quoteId);
            }).then(() => {
                // Başarılı ise sayfayı yenile
                window.location.reload();
            }).catch(error => {
                console.error('Ek bölüm kaldırılırken hata:', error);
                alert('Ek bölüm kaldırılırken hata oluştu');
            });
        }

                // Referans görselleri görünürlük kontrol fonksiyonu
        function toggleReferenceImages(checkbox) {
            const quoteId = checkbox.getAttribute('data-quote-id');
            const field = checkbox.getAttribute('data-field');
            const value = checkbox.checked ? '1' : '0';

            // Checkbox durumuna göre yazıyı güncelle
            const label = checkbox.nextElementSibling;
            if (label) {
                label.textContent = checkbox.checked ? 'Göster' : 'Gizle';
            }

            // Veritabanını güncelle
            updateField(field, value, quoteId).then(() => {
                console.log('Referans görselleri ayarı güncellendi');
            }).catch(error => {
                console.error('Referans görselleri ayarı güncellenirken hata:', error);
                alert('Ayar güncellenirken hata oluştu');
                // Hata durumunda checkbox'ı eski haline getir
                checkbox.checked = !checkbox.checked;
                if (label) {
                    label.textContent = checkbox.checked ? 'Göster' : 'Gizle';
                }
            });
        }

        // Maliyet listesi güncelleme fonksiyonu
        function updateCostList() {
            const select = document.getElementById('costListSelect');
            const costListId = select.value;
            const quoteId = '<?php echo $quote['quote_number']; ?>';

            // Loading göster
            const originalText = select.options[select.selectedIndex].text;
            select.disabled = true;

            // AJAX ile maliyet listesi seçimini kaydet
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_cost_list');
            formData.append('cost_list_id', costListId);

            fetch(`view-quote.php?id=${quoteId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Debug için response text'ini de log'la
                return response.text().then(text => {
                    console.log('Response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        console.error('Response was:', text);
                        throw new Error('Geçersiz JSON yanıtı alındı');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Başarı mesajı göster
                    showAlert('Maliyet listesi başarıyla güncellendi!', 'success');

                    // Sayfayı yenile
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Hata: ' + (data.message || 'Maliyet listesi güncellenemedi'));
                    select.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası: ' + error.message);
                select.disabled = false;
            });
        }

        // Alert gösterme fonksiyonu
        function showAlert(message, type = 'info') {
            // Basit alert sistemi - daha sonra bootstrap alert ile değiştirilebilir
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;

            document.body.appendChild(alertDiv);

            // 3 saniye sonra otomatik kaldır
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 3000);
        }

        // Genel bilgiler alanına yeni satır ekleme
        let generalInfoRowCounter = 0;

        // Sayfa yüklendiğinde mevcut custom alanları yükle
        function loadExistingCustomFields() {
            const quoteId = '<?php echo $quote['quote_number']; ?>';
            const customFields = <?php
                $custom_fields = !empty($quote['custom_fields']) ? json_decode($quote['custom_fields'], true) : [];
                echo json_encode($custom_fields);
            ?>;

            if (customFields && typeof customFields === 'object') {
                const container = document.getElementById('additionalGeneralInfoRows');
                if (!container) return;

                // Custom field'ları newfield_ formatında olan alanları gruplayalım
                const fieldPairs = [];
                const fieldKeys = Object.keys(customFields);

                // Önce maksimum row numarasını bulalım
                let maxRowNumber = 0;
                fieldKeys.forEach(key => {
                    const match = key.match(/custom_(?:label|value|label2|value2)_(\d+)/);
                    if (match) {
                        const rowNum = parseInt(match[1]);
                        if (rowNum > maxRowNumber) {
                            maxRowNumber = rowNum;
                        }
                    }
                });

                // Row'ları organize et
                for (let i = 1; i <= maxRowNumber; i++) {
                    const label1Key = `custom_label_${i}`;
                    const value1Key = `custom_value_${i}`;
                    const label2Key = `custom_label2_${i}`;
                    const value2Key = `custom_value2_${i}`;

                    if (customFields[label1Key] && customFields[value1Key]) {
                        fieldPairs.push({
                            rowNumber: i,
                            label1: label1Key,
                            value1: value1Key,
                            label2: customFields[label2Key] ? label2Key : null,
                            value2: customFields[value2Key] ? value2Key : null
                        });
                    }
                }

                // generalInfoRowCounter'ı güncelle
                generalInfoRowCounter = maxRowNumber;

                if (fieldPairs.length > 0) {
                    // Separator'ın kaldırılıp kaldırılmadığını kontrol et
                    const isHidden = localStorage.getItem(`quote_custom_separator_hidden_${quoteId}`) === 'true';

                    if (!isHidden) {
                    // İlk custom field'dan önce ayırıcı ekle
                    const separatorHtml = `
                        <!-- Ayırıcı Başlık ve Çizgi -->
                        <div data-separator="custom-fields" style="grid-column: 1 / -1; display: flex; align-items: center; margin: 15px 0 10px 0;">
                            <span class="editable" data-field="custom_section_title" data-quote-id="${quoteId}" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 14px; white-space: nowrap;" onclick="editField(this)" title="Başlığı düzenlemek için tıklayın">
                                ${customFields.custom_section_title || 'Ek olarak:'}
                                <span class="edit-indicator"></span>
                            </span>
                                <button type="button" class="btn btn-sm btn-link" onclick="removeCustomSeparator(this)" title="Başlığı kaldır" style="margin-left: 8px; color: #dc3545; text-decoration: none; padding: 2px 6px;">×</button>
                            <div style="flex: 1; height: 1px; background: #ddd; margin-left: 15px;"></div>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', separatorHtml);
                    }
                }

                fieldPairs.forEach(pair => {
                    const rowNumber = pair.rowNumber;

                    const rowHtml = `
                        <!-- Sol Kolon -->
                        <div id="leftColumn_${rowNumber}">
                            <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span class="editable" data-field="${pair.label1}" data-quote-id="${quoteId}" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap; min-width: 100px;"
                                      onclick="editField(this)" title="Etiket düzenlemek için tıklayın">
                                    ${customFields[pair.label1]}
                                    <span class="edit-indicator"></span>
                                </span>
                                <span class="editable" data-field="${pair.value1}" data-quote-id="${quoteId}" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;"
                                      onclick="editField(this)" title="Değer düzenlemek için tıklayın">
                                    ${customFields[pair.value1]}
                                    <span class="edit-indicator"></span>
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeGeneralInfoField('left', ${rowNumber})" style="font-size: 11px; padding: 2px 6px;" title="Bu alanı kaldır">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Sağ Kolon -->
                        <div id="rightColumn_${rowNumber}">
                            ${pair.label2 && pair.value2 ? `
                            <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span class="editable" data-field="${pair.label2}" data-quote-id="${quoteId}" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap; min-width: 100px;"
                                      onclick="editField(this)" title="Etiket düzenlemek için tıklayın">
                                    ${customFields[pair.label2]}
                                    <span class="edit-indicator"></span>
                                </span>
                                <span class="editable" data-field="${pair.value2}" data-quote-id="${quoteId}" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;"
                                      onclick="editField(this)" title="Değer düzenlemek için tıklayın">
                                    ${customFields[pair.value2]}
                                    <span class="edit-indicator"></span>
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeGeneralInfoField('right', ${rowNumber})" style="font-size: 11px; padding: 2px 6px;" title="Bu alanı kaldır">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            ` : ''}
                        </div>
                    `;

                    container.insertAdjacentHTML('beforeend', rowHtml);
                });
            }
        }

        // Custom separator kaldırma fonksiyonu
        function removeCustomSeparator(button) {
            const separator = button.closest('[data-separator="custom-fields"]');
            if (separator) {
                separator.remove();
                // LocalStorage'a kaydet ki tekrar gösterilmesin
                const quoteId = '<?php echo $quote['quote_number']; ?>';
                localStorage.setItem(`quote_custom_separator_hidden_${quoteId}`, 'true');
            }
        }

        // Sıralamayı sıfırla fonksiyonu
        function resetSectionOrder() {
            const container = document.querySelector('.content .info-side');
            if (!container) return;

            // LocalStorage'dan sıralamayı sil
            const quoteId = '<?php echo $quote['quote_number']; ?>';
            localStorage.removeItem(`quote_section_order_${quoteId}`);

            // Varsayılan sıralama
            const defaultOrder = ['services', 'transport_process', 'terms', 'additional1', 'additional2'];

            // Bölümleri varsayılan sıraya göre yeniden düzenle
            defaultOrder.forEach(sectionKey => {
                const section = container.querySelector(`.form-section[data-section="${sectionKey}"]`);
                if (section) {
                    container.appendChild(section);
                }
            });

            // Numaraları güncelle
            updateSectionNumbers();

            // Kullanıcıya bilgi ver
            showAlert('Sıralama varsayılan haline döndürüldü', 'success');
        }

        // Alert gösterme fonksiyonu (eğer yoksa)
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            // 3 saniye sonra otomatik kapat
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Sıralama sistemi için fonksiyonlar
        function initSectionOrdering() {
            const container = document.querySelector('.content .info-side');
            if (!container) return;

            // Tüm section'lara sıra numarası ve kontroller ekle
            const sections = container.querySelectorAll('.form-section[data-section]');
            sections.forEach((section, index) => {
                // Sıra kontrollerini ekle - section-label'ın sağına
                const sectionLabel = section.querySelector('.section-label');
                if (sectionLabel && !section.querySelector('.section-order-controls')) {
                    const controls = document.createElement('div');
                    controls.className = 'section-order-controls';
                    controls.style.cssText = 'display: inline-flex; flex-direction: column; gap: 1px; margin-left: 8px; vertical-align: top;';
                    controls.innerHTML = `
                        <button type="button" class="btn btn-sm section-order-btn" onclick="moveSection(this, -1)" title="Yukarı taşı"
                                style="padding: 1px 5px; height: 18px; line-height: 1; background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 2px; transition: all 0.2s;"
                                onmouseover="this.style.background='#fff'; this.style.borderColor='#999';" onmouseout="this.style.background='rgba(255,255,255,0.9)'; this.style.borderColor='#ddd';">
                            <i class="fas fa-chevron-up" style="font-size: 9px; color: #666;"></i>
                        </button>
                        <button type="button" class="btn btn-sm section-order-btn" onclick="moveSection(this, 1)" title="Aşağı taşı"
                                style="padding: 1px 5px; height: 18px; line-height: 1; background: rgba(255,255,255,0.9); border: 1px solid #ddd; border-radius: 2px; transition: all 0.2s;"
                                onmouseover="this.style.background='#fff'; this.style.borderColor='#999';" onmouseout="this.style.background='rgba(255,255,255,0.9)'; this.style.borderColor='#ddd';">
                            <i class="fas fa-chevron-down" style="font-size: 9px; color: #666;"></i>
                        </button>
                    `;
                    // Section-label'ın yanına ekle
                    sectionLabel.style.display = 'inline-block';
                    sectionLabel.parentNode.insertBefore(controls, sectionLabel.nextSibling);
                }
            });

            // LocalStorage'dan sıralamayı yükle
            loadSectionOrder();
        }

        function moveSection(button, direction) {
            const section = button.closest('.form-section');
            const container = section.parentElement;
            const sections = Array.from(container.querySelectorAll('.form-section[data-section]'));
            const currentIndex = sections.indexOf(section);
            const newIndex = currentIndex + direction;

            if (newIndex >= 0 && newIndex < sections.length) {
                if (direction === -1) {
                    // Yukarı taşı
                    container.insertBefore(section, sections[newIndex]);
                } else {
                    // Aşağı taşı
                    if (sections[newIndex + 1]) {
                        container.insertBefore(section, sections[newIndex + 1]);
                    } else {
                        container.appendChild(section);
                    }
                }

                // Numaraları güncelle
                updateSectionNumbers();
                // Sıralamayı kaydet
                saveSectionOrder();
            }
        }

        function updateSectionNumbers() {
            const container = document.querySelector('.content .info-side');
            if (!container) return;

            const sections = container.querySelectorAll('.form-section[data-section]');
            sections.forEach((section, index) => {
                // İlk ve son elemanlar için butonları devre dışı bırak
                const upButton = section.querySelector('button[onclick*="moveSection(this, -1)"]');
                const downButton = section.querySelector('button[onclick*="moveSection(this, 1)"]');

                if (upButton) {
                    upButton.disabled = (index === 0);
                    upButton.style.opacity = (index === 0) ? '0.3' : '1';
                    upButton.style.cursor = (index === 0) ? 'not-allowed' : 'pointer';
                    if (index === 0) {
                        upButton.onmouseover = null;
                        upButton.onmouseout = null;
                        upButton.style.background = 'rgba(255,255,255,0.5)';
                        upButton.style.borderColor = '#e0e0e0';
                    } else {
                        upButton.onmouseover = function() {
                            this.style.background='#fff';
                            this.style.borderColor='#999';
                        };
                        upButton.onmouseout = function() {
                            this.style.background='rgba(255,255,255,0.9)';
                            this.style.borderColor='#ddd';
                        };
                        upButton.style.background = 'rgba(255,255,255,0.9)';
                        upButton.style.borderColor = '#ddd';
                    }
                }

                if (downButton) {
                    downButton.disabled = (index === sections.length - 1);
                    downButton.style.opacity = (index === sections.length - 1) ? '0.3' : '1';
                    downButton.style.cursor = (index === sections.length - 1) ? 'not-allowed' : 'pointer';
                    if (index === sections.length - 1) {
                        downButton.onmouseover = null;
                        downButton.onmouseout = null;
                        downButton.style.background = 'rgba(255,255,255,0.5)';
                        downButton.style.borderColor = '#e0e0e0';
                    } else {
                        downButton.onmouseover = function() {
                            this.style.background='#fff';
                            this.style.borderColor='#999';
                        };
                        downButton.onmouseout = function() {
                            this.style.background='rgba(255,255,255,0.9)';
                            this.style.borderColor='#ddd';
                        };
                        downButton.style.background = 'rgba(255,255,255,0.9)';
                        downButton.style.borderColor = '#ddd';
                    }
                }
            });
        }

        function saveSectionOrder() {
            const container = document.querySelector('.content .info-side');
            if (!container) return;

            const order = Array.from(container.querySelectorAll('.form-section[data-section]'))
                .map(section => section.getAttribute('data-section'));

            const quoteId = '<?php echo $quote['quote_number']; ?>';
            localStorage.setItem(`quote_section_order_${quoteId}`, JSON.stringify(order));
        }

        function loadSectionOrder() {
            const container = document.querySelector('.content .info-side');
            if (!container) return;

            const quoteId = '<?php echo $quote['quote_number']; ?>';
            const savedOrder = localStorage.getItem(`quote_section_order_${quoteId}`);

            if (savedOrder) {
                try {
                    const order = JSON.parse(savedOrder);
                    order.forEach(sectionKey => {
                        const section = container.querySelector(`.form-section[data-section="${sectionKey}"]`);
                        if (section) {
                            container.appendChild(section);
                        }
                    });
                    updateSectionNumbers();
                } catch (e) {
                    console.error('Sıralama yüklenirken hata:', e);
                }
            }
        }

        // Sayfa yüklendiğinde custom alanları ve sıralama sistemini yükle
        document.addEventListener('DOMContentLoaded', function() {
            loadExistingCustomFields();
            initSectionOrdering();
        });

        function addGeneralInfoRow() {
            generalInfoRowCounter++;
            const container = document.getElementById('additionalGeneralInfoRows');

            if (!container) {
                console.error('additionalGeneralInfoRows container bulunamadı');
                return;
            }

            // Eğer bu ilk custom field ise ayırıcı ekle
            const existingCustomFields = container.querySelectorAll('[id^="leftColumn_"], [id^="rightColumn_"]');
            const hasSeparator = container.querySelector('[data-separator="custom-fields"]');

            if (existingCustomFields.length === 0 && !hasSeparator) {
                // Separator'ın kaldırılıp kaldırılmadığını kontrol et
                const quoteId = '<?php echo $quote['quote_number']; ?>';
                const isHidden = localStorage.getItem(`quote_custom_separator_hidden_${quoteId}`) === 'true';

                if (!isHidden) {
                const separatorHtml = `
                    <!-- Ayırıcı Başlık ve Çizgi -->
                    <div data-separator="custom-fields" style="grid-column: 1 / -1; display: flex; align-items: center; margin: 15px 0 10px 0;">
                        <span class="editable" data-field="custom_section_title" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 14px; white-space: nowrap;" onclick="editField(this)" title="Başlığı düzenlemek için tıklayın">
                                <?php echo !empty($custom_fields['custom_section_title']) ? htmlspecialchars($custom_fields['custom_section_title']) : 'Ek olarak:'; ?>
                            <span class="edit-indicator"></span>
                        </span>
                            <button type="button" class="btn btn-sm btn-link" onclick="removeCustomSeparator(this)" title="Başlığı kaldır" style="margin-left: 8px; color: #dc3545; text-decoration: none; padding: 2px 6px;">×</button>
                        <div style="flex: 1; height: 1px; background: #ddd; margin-left: 15px;"></div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', separatorHtml);
                }
            }

            const rowHtml = `
                <!-- Sol Kolon -->
                <div id="leftColumn_${generalInfoRowCounter}">
                    <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                        <span class="editable" data-field="custom_label_${generalInfoRowCounter}" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"
                              onclick="editField(this)" title="Etiket düzenlemek için tıklayın" data-placeholder="Etiket:">
                            Yeni Alan:
                            <span class="edit-indicator"></span>
                        </span>
                        <span class="editable" data-field="custom_value_${generalInfoRowCounter}" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;"
                              onclick="editField(this)" title="Değer düzenlemek için tıklayın" data-placeholder="Değer eklemek için tıklayın">
                            Değer eklemek için tıklayın
                            <span class="edit-indicator"></span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeGeneralInfoField('left', ${generalInfoRowCounter})" style="font-size: 11px; padding: 2px 6px;" title="Bu alanı kaldır">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Sağ Kolon -->
                <div id="rightColumn_${generalInfoRowCounter}">
                    <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                        <span class="editable" data-field="custom_label2_${generalInfoRowCounter}" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"
                              onclick="editField(this)" title="Etiket düzenlemek için tıklayın" data-placeholder="Etiket:">
                            Yeni Alan:
                            <span class="edit-indicator"></span>
                        </span>
                        <span class="editable" data-field="custom_value2_${generalInfoRowCounter}" data-quote-id="<?php echo $quote['quote_number']; ?>" style="cursor: pointer; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;"
                              onclick="editField(this)" title="Değer düzenlemek için tıklayın" data-placeholder="Değer eklemek için tıklayın">
                            Değer eklemek için tıklayın
                            <span class="edit-indicator"></span>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeGeneralInfoField('right', ${generalInfoRowCounter})" style="font-size: 11px; padding: 2px 6px;" title="Bu alanı kaldır">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', rowHtml);

            // Yeni alanları otomatik olarak veritabanına kaydet
            const quoteId = '<?php echo $quote['quote_number']; ?>';

                        // Sol kolon için başlangıç değerlerini kaydet
            Promise.all([
                fetch('../api/update-general-info.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        ajax: '1',
                        action: 'update_field',
                        field: `custom_label_${generalInfoRowCounter}`,
                        value: 'Yeni Alan:',
                        quote_number: quoteId
                    })
                }),

                fetch('../api/update-general-info.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        ajax: '1',
                        action: 'update_field',
                        field: `custom_value_${generalInfoRowCounter}`,
                        value: 'Değer eklemek için tıklayın',
                        quote_number: quoteId
                    })
                }),

                // Sağ kolon için başlangıç değerlerini kaydet
                fetch('../api/update-general-info.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        ajax: '1',
                        action: 'update_field',
                        field: `custom_label2_${generalInfoRowCounter}`,
                        value: 'Yeni Alan:',
                        quote_number: quoteId
                    })
                }),

                fetch('../api/update-general-info.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        ajax: '1',
                        action: 'update_field',
                        field: `custom_value2_${generalInfoRowCounter}`,
                        value: 'Değer eklemek için tıklayın',
                        quote_number: quoteId
                    })
                })
            ]).then(() => {
                showAlert('Yeni satır eklendi ve kaydedildi', 'success');
            }).catch(error => {
                console.error('Kaydetme hatası:', error);
                showAlert('Yeni satır eklendi ancak kaydedilemedi', 'warning');
            });
        }

        function removeGeneralInfoField(side, rowId) {
            const column = document.getElementById(`${side}Column_${rowId}`);
            if (column) {
                if (confirm('Bu alanı silmek istediğinizden emin misiniz?')) {
                    // Kolondaki field name'leri bul
                    const editableFields = column.querySelectorAll('.editable');
                    const fieldsToDelete = [];

                    editableFields.forEach(field => {
                        const fieldName = field.getAttribute('data-field');
                        if (fieldName && fieldName.startsWith('custom_')) {
                            fieldsToDelete.push(fieldName);
                        }
                    });

                    // Database'den custom field'ları kaldır
                    if (fieldsToDelete.length > 0) {
                        const quoteId = '<?php echo $quote['quote_number']; ?>';

                        fieldsToDelete.forEach(fieldName => {
                            fetch('../api/update-general-info.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    ajax: '1',
                                    action: 'remove_custom_field',
                                    field: fieldName,
                                    quote_number: quoteId
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    console.error('Field removal failed:', data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error removing field:', error);
                            });
                        });
                    }

                                        // Container artık grid olduğu için sadece bu kolonu kaldır
                    column.remove();

                    showAlert('Alan silindi ve kaydedildi', 'success');
                }
            }
        }

        function removeGeneralInfoRow(rowId) {
            const row = document.getElementById(`generalInfoRow_${rowId}`);
            if (row) {
                if (confirm('Bu satırı silmek istediğinizden emin misiniz?')) {
                    // Satırdaki field name'leri bul
                    const editableFields = row.querySelectorAll('.editable');
                    const fieldsToDelete = [];

                    editableFields.forEach(field => {
                        const fieldName = field.getAttribute('data-field');
                        if (fieldName && fieldName.startsWith('custom_')) {
                            fieldsToDelete.push(fieldName);
                        }
                    });

                    // Database'den custom field'ları kaldır
                    if (fieldsToDelete.length > 0) {
                        const quoteId = '<?php echo $quote['quote_number']; ?>';

                        fieldsToDelete.forEach(fieldName => {
                            fetch('../api/update-general-info.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    ajax: '1',
                                    action: 'remove_custom_field',
                                    field: fieldName,
                                    quote_number: quoteId
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) {
                                    console.error('Field removal failed:', data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error removing field:', error);
                            });
                        });
                    }

                    row.remove();
                    showAlert('Satır kaldırıldı', 'info');
                }
            }
        }

    </script>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Modern Galeri Modal -->
    <div class="modal fade modern-gallery-modal" id="galleryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="galleryModalTitle">
                        <i class="fas fa-images me-2"></i><?= $is_english ? 'Reference Images' : 'Referans Resimleri' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="galleryImagesContainer" class="gallery-grid">
                        <!-- Content will be filled by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen Swiper Modal -->
    <div class="modal fade fullscreen-modal" id="fullscreenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Controls -->
                <div class="fullscreen-controls">
                    <div class="fullscreen-btn" onclick="toggleImageInfo()">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="fullscreen-btn" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </div>
                </div>

                <!-- Swiper Container -->
                <div class="swiper-container" id="fullscreenSwiper">
                    <div class="swiper-wrapper" id="swiperWrapper">
                        <!-- Slides will be added dynamically -->
                    </div>

                    <!-- Navigation -->
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>

                    <!-- Pagination -->
                    <div class="swiper-pagination"></div>
                </div>

                <!-- Image Info Overlay -->
                <div class="image-info-overlay" id="imageInfoOverlay">
                    <div class="image-info-title" id="imageInfoTitle"></div>
                    <div class="image-info-desc" id="imageInfoDesc"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rich Text Editor Modal -->
    <div class="rich-editor-container" id="richEditorModal">
        <div class="rich-editor-modal">
            <div class="rich-editor-header">
                <h5 id="richEditorTitle">İçerik Düzenle</h5>
                <button type="button" class="btn-close" onclick="closeRichEditor()"></button>
            </div>
            <div class="rich-editor-content">
                <div class="mb-3">
                    <div class="btn-toolbar mb-2" role="toolbar">
                        <!-- Font Boyutu ve Yazı Tipi -->
                        <div class="btn-group me-2" role="group">
                            <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSize(this.value)" title="Yazı Boyutu">
                                <option value="">Boyut</option>
                                <option value="1">8pt</option>
                                <option value="2">10pt</option>
                                <option value="3">12pt</option>
                                <option value="4">14pt</option>
                                <option value="5">18pt</option>
                                <option value="6">24pt</option>
                                <option value="7">36pt</option>
                            </select>
                            <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamily(this.value)" title="Yazı Tipi">
                                <option value="">Yazı Tipi</option>
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Calibri">Calibri</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                        </div>

                        <!-- Temel Biçimlendirme -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')" title="Kalın">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')" title="İtalik">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')" title="Altı Çizili">
                                <i class="fas fa-underline"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('strikeThrough')" title="Üstü Çizili">
                                <i class="fas fa-strikethrough"></i>
                            </button>
                        </div>

                        <!-- Liste -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertUnorderedList')" title="Madde İşareti">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertOrderedList')" title="Numaralı Liste">
                                <i class="fas fa-list-ol"></i>
                            </button>
                        </div>

                        <!-- Tablo -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal()" title="Tablo Ekle">
                                <i class="fas fa-table"></i>
                            </button>
                        </div>

                        <!-- Hizalama -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyLeft')" title="Sola Hizala">
                                <i class="fas fa-align-left"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyCenter')" title="Ortala">
                                <i class="fas fa-align-center"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyRight')" title="Sağa Hizala">
                                <i class="fas fa-align-right"></i>
                            </button>
                        </div>

                        <!-- Girinti -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('indent')" title="Girinti Artır">
                                <i class="fas fa-indent"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('outdent')" title="Girinti Azalt">
                                <i class="fas fa-outdent"></i>
                            </button>
                        </div>

                        <!-- Renk ve Vurgu -->
                        <div class="btn-group me-2" role="group">
                            <input type="color" id="textColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColor(this.value)" title="Metin Rengi">
                            <input type="color" id="bgColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColor(this.value)" title="Arka Plan Rengi" value="#ffffff">
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatText('hiliteColor', '#ffff00')" title="Sarı Vurgu">
                                <i class="fas fa-highlighter"></i>
                            </button>
                        </div>

                        <!-- Format ve Stil -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('subscript')" title="Alt Simge">
                                <i class="fas fa-subscript"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('superscript')" title="Üst Simge">
                                <i class="fas fa-superscript"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRule()" title="Yatay Çizgi">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- İkinci satır araç çubuğu -->
                    <div class="btn-toolbar mb-2" role="toolbar">
                        <!-- Link ve Görsel -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-primary" onclick="insertLink()" title="Bağlantı Ekle">
                                <i class="fas fa-link"></i>
                                <span class="d-none d-md-inline ms-1">Link</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="formatText('unlink')" title="Bağlantıyı Kaldır">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </div>

                        <!-- Temizleme ve Dosya -->
                        <div class="btn-group me-2" role="group">
                            <input type="file" id="richFileInput" style="display:none;" onchange="uploadEditorFile(this)" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                            <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('richFileInput').click()" title="Dosya yükleyip içeriğe link olarak ekle">
                                <i class="fas fa-upload"></i>
                                <span class="d-none d-md-inline ms-1">Dosya Yükle</span>
                            </button>
                        </div>

                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-warning" onclick="removeFormatting()" title="Tüm biçimlendirmeyi kaldır">
                                <i class="fas fa-eraser"></i>
                                <span class="d-none d-md-inline ms-1">Biçim Temizle</span>
                            </button>
                        </div>

                        <!-- Görünüm -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-info" onclick="toggleSourceView()" title="HTML kaynak kodunu görüntüle/düzenle">
                                <i class="fas fa-code"></i>
                                <span class="d-none d-md-inline ms-1">Kaynak Kodu</span>
                            </button>
                        </div>

                        <!-- Geri al / İleri al -->
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('undo')" title="Geri Al">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('redo')" title="İleri Al">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Editör Alanı -->
                <div contenteditable="true" class="rich-editor-textarea" id="richEditorContent"></div>

                <!-- Kaynak Kodu Görünümü -->
                <textarea class="rich-editor-textarea" id="richEditorSource" style="display: none; font-family: 'Courier New', monospace; font-size: 12px;"></textarea>
            </div>
            <div class="rich-editor-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Dosya yükleme sonrası link otomatik eklenir
                    </small>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" onclick="closeRichEditor()">
                            <i class="fas fa-times"></i> İptal
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveRichContent()">
                            <i class="fas fa-save"></i> Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablo Oluşturucu Modal -->
    <div class="rich-editor-container" id="tableModal" style="display: none;">
        <div class="rich-editor-modal" style="max-width: 500px;">
            <div class="rich-editor-header">
                <h5>Tablo Oluştur</h5>
                <button type="button" class="btn-close" onclick="closeTableModal()"></button>
            </div>
            <div class="rich-editor-content">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Satır Sayısı</label>
                        <input type="number" id="tableRows" class="form-control" value="3" min="1" max="20">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Sütun Sayısı</label>
                        <input type="number" id="tableCols" class="form-control" value="3" min="1" max="10">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tablo Başlığı (Opsiyonel)</label>
                    <input type="text" id="tableCaption" class="form-control" placeholder="Tablo başlığı girin...">
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tableHeader" checked>
                        <label class="form-check-label" for="tableHeader">
                            İlk satırı başlık olarak kullan
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tablo Stili</label>
                    <select id="tableStyle" class="form-select">
                        <option value="basic">Temel</option>
                        <option value="striped">Çizgili</option>
                        <option value="bordered">Kenarlıklı</option>
                        <option value="hover">Hover Efektli</option>
                    </select>
                </div>
            </div>
            <div class="rich-editor-footer">
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="closeTableModal()">
                        <i class="fas fa-times"></i> İptal
                    </button>
                    <button type="button" class="btn btn-primary" onclick="insertTable()">
                        <i class="fas fa-table"></i> Tablo Ekle
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>