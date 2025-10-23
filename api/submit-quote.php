<?php
// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST istekleri kabul edilir');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Geçersiz JSON verisi');
    }

    // Veri doğrulama
    $required_fields = ['firstName', 'lastName', 'email', 'phone', 'transportMode', 'origin', 'destination', 'tradeType'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception('Gerekli alan eksik: ' . $field);
        }
    }

    // E-posta formatı kontrolü
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Geçersiz e-posta formatı');
    }

    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Veritabanı bağlantı hatası');
    }

    // Email templates tablosunu oluştur (transaction dışında)
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transport_mode_id INT NOT NULL,
                language ENUM('tr', 'en') DEFAULT 'tr',
                currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
                template_name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                quote_content TEXT NOT NULL,
                email_content TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_email_template (transport_mode_id, language, currency)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        // Tablo zaten varsa devam et
    }

    $db->beginTransaction();

    try {
        // Müşteriyi kontrol et ve ekle
        $customer_id = findOrCreateCustomer($db, $input);

        // Taşıma modunu kontrol et
        $transport_mode = getTransportMode($db, $input['transportMode']);
        if (!$transport_mode) {
            throw new Exception('Geçersiz taşıma modu');
        }

        // Manuel fiyat girişi için varsayılan fiyat (admin panelinden güncellenecek)
        $default_price = 0.00;

        // Teklif numarası oluştur (trade type ile birlikte)
        $quote_number = generateQuoteNumber($db, $input['tradeType']);

        // Teklifi veritabanına kaydet (fiyat 0 olarak, admin panelinden güncellenecek)
        $quote_id = saveQuote($db, [
            'quote_number' => $quote_number,
            'customer_id' => $customer_id,
            'transport_mode_id' => $transport_mode['id'],
            'container_type' => $input['containerType'] ?? null,
            'origin' => $input['origin'],
            'destination' => $input['destination'],
            'weight' => $input['weight'] ?: 0,
            'volume' => $input['volume'] ?? null,
            'unit_price' => $input['unitPrice'] ?? null,
            'pieces' => $input['pieces'] ?? null,
            'cargo_type' => $input['cargo_type'] ?? 'genel',
            'trade_type' => $input['tradeType'],
            'description' => $input['description'] ?? null,
            'calculated_price' => $default_price,
            'final_price' => $default_price,
            'valid_until' => date('Y-m-d', strtotime('+15 days')),
            'start_date' => $input['startDate'] ?? null,
            'delivery_date' => $input['deliveryDate'] ?? null,
            'selected_template_id' => !empty($input['selectedTemplate']) ? $input['selectedTemplate'] : null,
            'cost_list_id' => !empty($input['selectedCostList']) ? $input['selectedCostList'] : null
        ]);

        // Transaction'ı commit et
        $db->commit();

        // Seçilen şablondan e-mail şablonu oluştur (transaction dışında)
        if (!empty($input['selectedTemplate'])) {
            createEmailTemplateFromQuoteTemplate($db, $input['selectedTemplate'], $transport_mode['id']);
        }

        // E-posta gönderme (fiyat 0 olduğu için şimdilik göndermeyelim)
        $email_sent = false;
        // E-posta admin panelinden fiyat girildikten sonra gönderilecek

        echo json_encode([
            'success' => true,
            'message' => 'Teklif başarıyla oluşturuldu. Fiyat bilgisi en kısa sürede e-posta ile gönderilecektir.',
            'quoteId' => $quote_number,
            'price' => 'Hesaplanıyor...',
            'emailSent' => false
        ]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    error_log('Quote submission error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

// Yardımcı fonksiyonlar

function findOrCreateCustomer($db, $customer_data) {
    // Önce mevcut müşteriyi kontrol et
    $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$customer_data['email']]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Mevcut müşteriyi güncelle
        $stmt = $db->prepare("
            UPDATE customers
            SET first_name = ?, last_name = ?, phone = ?, company = ?, cc_email = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $customer_data['firstName'],
            $customer_data['lastName'],
            $customer_data['phone'],
            $customer_data['company'] ?? null,
            $customer_data['cc_email'] ?? null,
            $existing['id']
        ]);
        return $existing['id'];
    } else {
        // Yeni müşteri ekle
        $stmt = $db->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, company, cc_email)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $customer_data['firstName'],
            $customer_data['lastName'],
            $customer_data['email'],
            $customer_data['phone'],
            $customer_data['company'] ?? null,
            $customer_data['cc_email'] ?? null
        ]);
        return $db->lastInsertId();
    }
}

function getTransportMode($db, $mode_slug) {
    $stmt = $db->prepare("SELECT * FROM transport_modes WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$mode_slug]);
    return $stmt->fetch();
}

function calculatePrice($transport_mode, $cargo_data) {
    $base_price = $transport_mode['base_price'];
    $weight_price = $transport_mode['price_per_kg'] * $cargo_data['weight'];
    $volume_price = 0;

    if (!empty($cargo_data['volume']) && $transport_mode['price_per_m3'] > 0) {
        $volume_price = $transport_mode['price_per_m3'] * $cargo_data['volume'];
    }

    // Mesafe hesabı (basit hesaplama - gerçek uygulamada Google Maps API kullanılabilir)
    $distance_price = $transport_mode['price_per_km'] * estimateDistance($cargo_data['origin'], $cargo_data['destination']);

    $total_price = $base_price + $weight_price + $volume_price + $distance_price;

    // Minimum fiyat kontrolü
    if ($total_price < $transport_mode['min_price']) {
        $total_price = $transport_mode['min_price'];
    }

    return round($total_price, 2);
}

function estimateDistance($origin, $destination) {
    // Basit mesafe tahmini - gerçek uygulamada mapping API kullanın
    $major_cities = [
        'istanbul' => ['lat' => 41.0082, 'lng' => 28.9784],
        'ankara' => ['lat' => 39.9334, 'lng' => 32.8597],
        'izmir' => ['lat' => 38.4192, 'lng' => 27.1287],
        'bursa' => ['lat' => 40.1826, 'lng' => 29.0665],
        'antalya' => ['lat' => 36.8969, 'lng' => 30.7133]
    ];

    $origin_key = strtolower(trim($origin));
    $destination_key = strtolower(trim($destination));

    // Şehir bulunamazsa varsayılan mesafe
    if (!isset($major_cities[$origin_key]) || !isset($major_cities[$destination_key])) {
        return 500; // 500 km varsayılan
    }

    // Basit koordinat mesafesi hesaplama
    $lat1 = $major_cities[$origin_key]['lat'];
    $lng1 = $major_cities[$origin_key]['lng'];
    $lat2 = $major_cities[$destination_key]['lat'];
    $lng2 = $major_cities[$destination_key]['lng'];

    $distance = sqrt(pow($lat2 - $lat1, 2) + pow($lng2 - $lng1, 2)) * 111; // Yaklaşık km

    return max($distance, 50); // Minimum 50 km
}

function generateQuoteNumber($db = null, $tradeType = 'ithalat') {
    global $database;

    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }

    // Trade type'ı Türkçe/İngilizce'ye çevir
    $tradeTypeSuffix = '';
    switch(strtolower($tradeType)) {
        case 'ithalat':
            $tradeTypeSuffix = 'ithalat';
            break;
        case 'ihracat':
            $tradeTypeSuffix = 'ihracat';
            break;
        case 'import':
            $tradeTypeSuffix = 'import';
            break;
        case 'export':
            $tradeTypeSuffix = 'export';
            break;
        default:
            $tradeTypeSuffix = 'ithalat';
    }

    // Bu yıl için son teklif numarasını al (aynı trade type için)
    $year = date('y'); // Son iki hane (25)
    $stmt = $db->prepare("
        SELECT quote_number
        FROM quotes
        WHERE quote_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$year . '%-' . $tradeTypeSuffix]);
    $last_quote = $stmt->fetch();

    if ($last_quote) {
        // Son numarayı parse et (2025-1234-ithalat formatından 1234'ü al)
        $parts = explode('-', $last_quote['quote_number']);
        if (count($parts) >= 2) {
            $last_number = intval($parts[1]);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1111; // Başlangıç numarası
        }
    } else {
        $new_number = 1111; // İlk teklif numarası
    }

    return $year . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT) . '-' . $tradeTypeSuffix;
}

function saveQuote($db, $quote_data) {
    $stmt = $db->prepare("
        INSERT INTO quotes (
            quote_number, customer_id, transport_mode_id, container_type, origin, destination,
            weight, volume, unit_price, pieces, cargo_type, trade_type, description, calculated_price,
            final_price, valid_until, start_date, delivery_date, selected_template_id, cost_list_id, show_reference_images
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $quote_data['quote_number'],
        $quote_data['customer_id'],
        $quote_data['transport_mode_id'],
        $quote_data['container_type'] ?? null,
        $quote_data['origin'],
        $quote_data['destination'],
        $quote_data['weight'],
        $quote_data['volume'],
        $quote_data['unit_price'] ?? null,
        $quote_data['pieces'],
        $quote_data['cargo_type'],
        $quote_data['trade_type'],
        $quote_data['description'],
        $quote_data['calculated_price'],
        $quote_data['final_price'],
        $quote_data['valid_until'],
        $quote_data['start_date'] ?? null,
        $quote_data['delivery_date'] ?? null,
        $quote_data['selected_template_id'] ?? null,
        $quote_data['cost_list_id'] ?? null
    ]);

    return $db->lastInsertId();
}

function updateQuoteStatus($db, $quote_id, $status) {
    $stmt = $db->prepare("UPDATE quotes SET status = ? WHERE id = ?");
    $stmt->execute([$status, $quote_id]);
}

function createEmailTemplateFromQuoteTemplate($db, $template_id, $transport_mode_id) {
    try {
        // Quote template'i al
        $stmt = $db->prepare("SELECT * FROM quote_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $quote_template = $stmt->fetch();

        if (!$quote_template) {
            return false;
        }

        // E-mail şablonu oluştur veya güncelle
        $email_subject = "Nakliye Teklifi - {quote_number}";
        $email_content = "
            <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>
                <h2 style='color: #2c5aa0;'>Sayın {customer_name},</h2>
                <p>Talebiniz doğrultusunda hazırladığımız nakliye teklifimiz aşağıdadır:</p>
                <hr>
                {quote_content}
                <hr>
                <p>Teklifinizi görüntülemek için <a href='" . $_SERVER['HTTP_HOST'] . "/view-quote.php?id={quote_number}'>buraya tıklayın</a>.</p>
                <p>Herhangi bir sorunuz olması halinde bizimle iletişime geçebilirsiniz.</p>
                <p>Saygılarımızla,<br>Europatrans Global Lojistik</p>
            </div>
        ";

        $stmt = $db->prepare("
            INSERT INTO email_templates (
                transport_mode_id, language, currency, template_name,
                subject, quote_content, email_content, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            template_name = VALUES(template_name),
            subject = VALUES(subject),
            quote_content = VALUES(quote_content),
            email_content = VALUES(email_content),
            updated_at = NOW()
        ");

        $stmt->execute([
            $transport_mode_id,
            $quote_template['language'],
            $quote_template['currency'],
            'E-mail: ' . $quote_template['template_name'],
            $email_subject,
            $quote_template['content'],
            $email_content,
            1
        ]);

        return true;

    } catch (Exception $e) {
        error_log('Email template creation error: ' . $e->getMessage());
        return false;
    }
}
?>