<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Teklif ID'sini al
$quote_id = $_GET['id'] ?? '';

if (empty($quote_id)) {
    die('Teklif numarası belirtilmemiş.');
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Revision varsa quote_number'ı güncelle
    $revision = $_GET['rev'] ?? null;
    if ($revision) {
        $quote_id_with_rev = $quote_id . '_rev' . $revision;
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

    // Eğer revision ile bulunamadıysa, orijinal teklifi dene
    if (!$quote && $revision) {
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch();
    }

    if (!$quote) {
        die('Teklif bulunamadı.');
    }

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
            'origin' => 'Yükleme Adresi',
            'destination' => 'Teslimat Adresi',
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
            'origin' => 'Loading Address',
            'destination' => 'Delivery Address',
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
    <title><?= $t['quote_title'] ?> - <?php echo htmlspecialchars($quote['quote_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: white;
            color: #333;
            line-height: 1.3;
            font-size: 11px;
        }

        .pdf-container {
            max-width: 100%;
            margin: 0;
            background: white;
            padding: 15px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 5px 20px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0px;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        .contact-info {
            text-align: right;
            font-size: 10px;
            color: #333;
        }

        .phone-number {
            color: #2c5aa0;
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 8px;
        }

        .quote-number-header {
            color: #2c5aa0;
            font-weight: 700;
            font-size: 12px;
        }

        /* Main Title */
        .main-title {
            background: #2c5aa0;
    color: white;
    text-align: center;
    padding: 3px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 0px;
        }

        /* Content Area */
        .content {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 15px;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }

        .section-label {
            background: #ffc107;
            color: #333;
            padding: 6px 12px;
            font-weight: 600;
            font-size: 10px;
            min-width: 120px;
            text-align: center;
        }

        .section-title {
            background: #2c5aa0;
            color: white;
            padding: 6px 15px;
            font-weight: 600;
            font-size: 10px;
            flex: 1;
        }

        /* Form Sections */
        .form-section {
            border: 1px solid #e0e0e0;
            margin-bottom: 10px;
        }

        .form-content {
            padding: 10px;
            background: white;
        }

        .form-row {
            display: flex;
            margin-bottom: 6px;
            align-items: center;
        }

        .form-label {
            min-width: 90px;
            font-weight: 500;
            color: #333;
            font-size: 10px;
        }

        .form-value {
            flex: 1;
            padding: 4px 8px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            font-size: 10px;
            color: #333;
            margin-left: 10px;
        }

        /* Price Section */
        .price-section {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            color: #2e7d32;
            padding: 5px;
            text-align: center;
            margin: 0px 0;
            border-radius: 8px;
        }

        .price-label {
            font-size: 9px;
            margin-bottom: 3px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .price-amount {
            font-size: 24px;
            font-weight: 800;
        }

        /* Info Content */
        .info-content {
            font-size: 10px;
            line-height: 1.4;
        }

        .info-side h4 {
            color: #2c5aa0;
            font-size: 11px;
            font-weight: 700;
            margin: 12px 0 8px 0;
            text-transform: uppercase;
            position: relative;
            padding-left: 12px;
            padding-bottom: 6px;
        }

        .info-side h4::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 15px;
            background: #ffc107;
            border-radius: 2px;
        }

        .info-side h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 12px;
            right: 0;
            height: 1px;
            background: linear-gradient(to right, #ffc107 0%, #ffc107 30%, transparent 100%);
        }

        .info-content ul {
            margin: 6px 0;
            padding-left: 15px;
        }

        .info-content li {
            margin-bottom: 3px;
            font-size: 9px;
        }

        .info-content p {
            font-size: 10px;
            margin: 6px 0;
        }

        .highlight-box {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
            padding: 10px;
            margin: 10px 0;
        }

        .highlight-box h4 {
            color: #1976d2;
            font-size: 9px;
            font-weight: 600;
            margin: 0 0 4px 0;
            border: none;
            padding: 0;
            text-transform: none;
            position: static;
        }

        .highlight-box h4::before,
        .highlight-box h4::after {
            display: none;
        }

        .highlight-box p {
            font-size: 9px;
            margin: 0;
        }

        /* Additional Costs */
        .additional-costs-list {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .cost-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 8px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 9px;
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
            height: 16px;
            line-height: 16px;
            text-align: center;
            color: #ddd;
            font-size: 8px;
            letter-spacing: 2px;
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
            padding: 2px 4px;
        }

        /* Değer alanlarını sağa hizala */
        .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:last-child,
        .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:nth-child(2) {
            text-align: right !important;
            justify-self: end;
            padding-right: 4px;
            font-weight: 500;
        }

        /* Etiket alanları */
        .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:first-child {
            padding-left: 4px;
            font-weight: 600;
        }

        /* Footer */
        .footer {
            background: #2c5aa0;
            color: white;
            padding: 12px 20px;
            margin-top: 15px;
            font-size: 9px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .footer strong {
            font-size: 10px;
        }

        /* PDF specific styles */
        @media print {
            @page {
                size: A4;
                margin: 8mm;
            }

            body {
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            .pdf-container {
                padding: 0;
            }
        }

        /* Auto print on load */
        @media screen {
            .pdf-controls {
                position: fixed;
                top: 10px;
                right: 10px;
                z-index: 1000;
                background: white;
                padding: 10px;
                border-radius: 5px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            }
        }

        /* PDF çıktısında butonları gizle */
        @media print {
            .pdf-controls {
                display: none !important;
            }
            .contact-info-section {
                display: block !important;
            }
        }

                /* Mobile responsive */
        @media (max-width: 768px) {
            .container {
                margin: 0;
                box-shadow: none;
            }

            .header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                text-align: left !important;
                gap: 0 !important;
            }

            .logo {
                max-width: 120px !important;
            }

            .contact-info {
                text-align: right !important;
                font-size: 8px !important;
            }

            .phone-number {
                font-size: 9px !important;
                margin-bottom: 4px !important;
            }

            .quote-number-header {
                font-size: 9px !important;
            }

            .contact-info-section {
                display: block !important;
            }

            .content {
                display: block;
            }

            /* Taşımaya Dair Genel Bilgiler - mobilde 2 sütun responsive */
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 40px"] {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 15px !important;
            }

            /* İçerik satırları */
            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"][style*="gap: 5px"] {
                display: grid !important;
                grid-template-columns: auto 1fr !important;
                gap: 5px !important;
            }

            /* Değerleri mobilde sağa hizala */
            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:last-child,
            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:nth-child(2) {
                text-align: right !important;
                justify-self: end !important;
                padding-right: 5px !important;
                font-weight: 500;
                font-size: 8px !important;
            }

            /* Etiketleri mobilde daha küçük yap */
            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:first-child {
                font-size: 8px !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 80px;
            }

            /* Butonlar/inputs mobil dostu */
            button, input {
                min-height: 44px;
                font-size: 16px;
            }
        }

        /* Çok küçük ekranlar için (dar telefonlar) */
        @media (max-width: 480px) {
            /* Çok dar ekranlarda tek sütun yap */
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 40px"] {
                grid-template-columns: 1fr !important;
                gap: 5px !important;
            }

            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:first-child {
                font-size: 7px !important;
                max-width: 70px;
            }

            .container {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="pdf-controls">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i> PDF İndir
        </button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">
            <i class="fas fa-times"></i> Kapat
        </button>
    </div>

    <div class="pdf-container">
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
                        <br><span style="font-size: 10px; color: #ffc107;">
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
                    <p style="font-size: 11px; font-weight: 600; color: #2c5aa0; margin-bottom: 12px; line-height: 1.4;">
                        <?= !empty($quote['greeting_text']) ? $quote['greeting_text'] : '<strong>' . ($is_english ? 'Dear' : 'Sayın') . ' ' . htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']) . ($is_english ? ',' : ',') . '</strong>' ?>
                    </p>
                    <p style="font-size: 10px; color: #333; line-height: 1.5; margin: 0; font-weight: 400;">
                        <?= !empty($quote['intro_text']) ? $quote['intro_text'] : ($is_english ? 'As Europatrans Global Logistics Transportation, we offer and undertake to carry out the international transportation of your goods under the conditions specified below.' : 'Europatrans Global Lojistik Taşımacılık olarak eşyalarınızın uluslararası taşımasını, aşağıda belirtilen şartlar dahilinde yapmayı teklif ve taahhüt ederiz.') ?>
                    </p>
                </div>

                <!-- Right Side - Contact Info -->
                <div class="contact-info-section" style="background: rgba(255,255,255,0.9); padding: 12px; border-radius: 6px; border: 1px solid rgba(44,90,160,0.15); box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: right;">
                    <h4 style="color: #2c5aa0; font-size: 9px; font-weight: 600; margin-bottom: 8px; margin-top: 0; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">
                        <?= $is_english ? 'Contact Information' : 'İletişim Bilgileri' ?>
                    </h4>

                    <div style="margin-bottom: 6px; display: flex; align-items: center; gap: 4px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 8px;">
                            <?= $t['email'] ?>:
                        </span>
                        <span style="font-size: 8px; color: #333;">
                            <?php echo htmlspecialchars($quote['email']); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 0; display: flex; align-items: center; gap: 4px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 8px;">
                            <?= $t['phone'] ?>:
                        </span>
                        <span style="font-size: 8px; color: #333;">
                            <?php echo htmlspecialchars($quote['phone']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Section - New Layout Under Intro Text -->
        <div style="padding: 15px 15px; background: white; border-bottom: 1px solid #e0e0e0;">
            <div style="max-width: 1000px;">
                <!-- Main Price Table -->
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;">
                    <tr>
                        <td style="padding: 8px 15px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); font-weight: 600; font-size: 12px; width: 180px; color: #155724; border-right: 1px solid rgba(255,255,255,0.3);">
                            <?= $t['our_quote_price'] ?>
                        </td>
                        <td style="padding: 8px 15px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); text-align: right; font-size: 16px; font-weight: 700; color: #155724;">
                            <?php echo formatPriceWithCurrency($quote['final_price'], $currency); ?>
                        </td>
                    </tr>
                </table>

                <!-- Additional Costs Table -->
                <div id="additionalCostsList">
                    <!-- Additional costs will be loaded here dynamically -->
                </div>

                <!-- Approval Section -->
                <?php if ($quote['status'] === 'accepted' || $quote['status'] === 'approved'): ?>
                <div style="text-align: right; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                    <div style="color: #28a745; font-size: 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                        <span style="color: #28a745; font-size: 12px;">✓</span>
                        <span>
                            <strong><?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></strong>
                            <?= $is_english ? ' has read and approved this quote.' : ' olarak bu teklifi okudum onaylıyorum.' ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- General Information - Compact 2 Column Layout -->
        <div class="form-section" style="margin: 0px 0;">
            <div class="section-header">
                <div class="section-label"><?= $is_english ? 'General Transportation Information' : 'Taşımaya Dair Genel Bilgiler' ?></div>
                <div class="section-title"></div>
            </div>

            <!-- Content in 2 columns - Compact Layout -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: white; padding: 10px 15px;">

                <!-- Left Column -->
                <div>
                    <?php if (!empty($quote['company'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['company'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo htmlspecialchars($quote['company']); ?></span>
                    </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['quote_date'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo formatDate($quote['created_at']); ?></span>
                    </div>

                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['validity'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo formatDate($quote['valid_until']); ?></span>
                    </div>

                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['transport_type'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo htmlspecialchars(!empty($quote['custom_transport_name']) ? $quote['custom_transport_name'] : translateTransportMode($quote['transport_name'], $t)); ?></span>
                    </div>

                    <?php if (!empty($quote['weight'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['weight'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo number_format($quote['weight'], 0, ',', '.'); ?> kg</span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['volume'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['volume'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo number_format($quote['volume'], 2, ',', '.'); ?> m³</span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['pieces'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['pieces'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo number_format($quote['pieces'], 0, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column -->
                <div>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['origin'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo htmlspecialchars($quote['origin']); ?></span>
                    </div>

                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['destination'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo htmlspecialchars($quote['destination']); ?></span>
                    </div>

                    <?php if (!empty($quote['start_date']) && $quote['start_date'] !== '0000-00-00' && $quote['start_date'] !== null): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['start_date'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo formatDate($quote['start_date']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['delivery_date']) && $quote['delivery_date'] !== '0000-00-00' && $quote['delivery_date'] !== null): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['delivery_date'] ?>:</span>
                        <span style="font-size: 9px;"><?php echo formatDate($quote['delivery_date']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['cargo_type'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['cargo_type'] ?>:</span>
                        <span style="font-size: 9px;">
                            <?php
                            if ($is_english) {
                                $cargo_types = [
                                    'ev_esyasi' => 'Household Goods',
                                    'kisisel_esya' => 'Personal Effects',
                                    'ticari_esya' => 'Commercial Goods'
                                ];
                            } else {
                                $cargo_types = [
                                    'ev_esyasi' => 'Ev Eşyası',
                                    'kisisel_esya' => 'Kişisel Eşya',
                                    'ticari_esya' => 'Ticari Eşya'
                                ];
                            }
                            echo $cargo_types[$quote['cargo_type']] ?? htmlspecialchars($quote['cargo_type']);
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($quote['trade_type'])): ?>
                    <div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;"><?= $t['trade_type'] ?>:</span>
                        <span style="font-size: 9px;">
                            <?php
                            if ($is_english) {
                                $trade_types = [
                                    'ithalat' => 'Import',
                                    'ihracat' => 'Export'
                                ];
                            } else {
                                $trade_types = [
                                    'ithalat' => 'İthalat',
                                    'ihracat' => 'İhracat'
                                ];
                            }
                            echo $trade_types[$quote['trade_type']] ?? htmlspecialchars($quote['trade_type']);
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Custom Fields (Özel Alanlar) -->
            <?php
            // Custom alanları varsa göster
            if (!empty($quote['custom_fields'])) {
                $custom_fields = json_decode($quote['custom_fields'], true);
                if ($custom_fields && is_array($custom_fields) && count($custom_fields) > 0) {
                    // Custom alanları grup halinde organize et (her 4 alan 1 satır - 2 left, 2 right)
                    $fieldKeys = array_keys($custom_fields);
                    $fieldPairs = [];

                    for ($i = 0; $i < count($fieldKeys); $i += 4) {
                        $label1 = isset($fieldKeys[$i]) ? $fieldKeys[$i] : null;
                        $value1 = isset($fieldKeys[$i + 1]) ? $fieldKeys[$i + 1] : null;
                        $label2 = isset($fieldKeys[$i + 2]) ? $fieldKeys[$i + 2] : null;
                        $value2 = isset($fieldKeys[$i + 3]) ? $fieldKeys[$i + 3] : null;

                        if ($label1 && $value1) {
                            $fieldPairs[] = [
                                'label1' => $label1,
                                'value1' => $value1,
                                'label2' => $label2,
                                'value2' => $value2
                            ];
                        }
                    }

                    // Her satır için HTML oluştur
                    foreach ($fieldPairs as $pair) {
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: white; padding: 0 15px 6px 15px;">';

                        // Sol kolon
                        echo '<div>';
                        if (isset($custom_fields[$pair['label1']]) && isset($custom_fields[$pair['value1']])) {
                            echo '<div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">';
                            echo '<span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;">' . htmlspecialchars($custom_fields[$pair['label1']]) . ':</span>';
                            echo '<span style="font-size: 9px;">';
                            echo htmlspecialchars($custom_fields[$pair['value1']]);
                            echo '</span>';
                            echo '</div>';
                        }
                        echo '</div>';

                        // Sağ kolon
                        echo '<div>';
                        if ($pair['label2'] && $pair['value2'] && isset($custom_fields[$pair['label2']]) && isset($custom_fields[$pair['value2']])) {
                            echo '<div style="display: grid; grid-template-columns: auto 1fr; gap: 5px; align-items: center; margin-bottom: 6px; min-height: 18px;">';
                            echo '<span style="font-weight: 600; color: #2c5aa0; font-size: 9px; white-space: nowrap;">' . htmlspecialchars($custom_fields[$pair['label2']]) . ':</span>';
                            echo '<span style="font-size: 9px;">';
                            echo htmlspecialchars($custom_fields[$pair['value2']]);
                            echo '</span>';
                            echo '</div>';
                        }
                        echo '</div>';

                        echo '</div>';
                    }
                }
            }
            ?>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Left Side - Details -->
            <div class="details-side">

            </div>

            <!-- Right Side - Information -->
            <div class="info-side">

                <!-- Services Section -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['services'] ?></div>
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

                            echo $services_content;
                            ?>

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
                                <p style="color: #666; font-style: italic;">
                                    <?= $is_english ? 'Detailed cost list:' : 'Detaylı maliyet listesi:' ?>
                                    <strong><?= htmlspecialchars($quote['cost_list_name']) ?></strong>
                                    <?= $is_english ? ' (File not available)' : ' (Dosya mevcut değil)' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Transport Process -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $is_english ? 'Transport Process' : 'Taşınma Süreci' ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce veritabanından kaydedilmiş transport_process_text'i kontrol et
                        if (!empty($quote['transport_process_text'])) {
                            $transport_process_text = $quote['transport_process_text'];
                        } else {
                            // Eğer veritabanında kayıtlı değer yoksa, taşıma moduna göre varsayılan süreç metni belirle
                            $transport_process_text = '';
                            $transport_mode_lower = strtolower($quote['transport_name']);

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
                        ?>

                        <div class="transport-process-content">
                            <p><?php echo $transport_process_text; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Terms Section -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['terms'] ?></div>
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

                            echo $terms_content;
                            ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Section 1 -->
                <?php if (!empty($quote['additional_section1_title']) || !empty($quote['additional_section1_content'])): ?>
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= htmlspecialchars($quote['additional_section1_title'] ?? 'Ek Bölüm 1') ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        $additional_content1 = $quote['additional_section1_content'] ?? '';
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

                            echo $additional_content1;
                        endif;
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Section 2 -->
                <?php if (!empty($quote['additional_section2_title']) || !empty($quote['additional_section2_content'])): ?>
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= htmlspecialchars($quote['additional_section2_title'] ?? 'Ek Bölüm 2') ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        $additional_content2 = $quote['additional_section2_content'] ?? '';
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

                            echo $additional_content2;
                        endif;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
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

    <script>
        // Load existing additional costs on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAdditionalCosts();
        });

        // Load additional costs from server (view only)
        function loadAdditionalCosts() {
            const quoteId = <?= json_encode($quote['id']) ?>;

            fetch(`api/get-additional-costs.php?quote_id=${quoteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.costs && data.costs.length > 0) {
                    const costsList = document.getElementById('additionalCostsList');

                    data.costs.forEach(cost => {
                        addCostToList(cost.name || cost.description, cost.description, cost.amount, cost.currency);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading costs:', error);
            });
        }

        // Add cost to list (view only)
        function addCostToList(name, description, amount, currency) {
            const costsList = document.getElementById('additionalCostsList');
            const costItem = document.createElement('div');
            costItem.className = 'cost-item';

            const formattedAmount = formatPrice(amount, currency);

            let descriptionHtml = '';
            if (description && description.trim()) {
                descriptionHtml = `<div class="cost-note" style="font-size: 9px; color: #666; font-style: italic; margin-top: 2px;">${description}</div>`;
            }

            costItem.innerHTML = `
                <div class="cost-content" style="flex: 1;">
                    <div class="cost-description">${name}</div>
                    ${descriptionHtml}
                </div>
                <div class="cost-amount">${formattedAmount}</div>
            `;

            costsList.appendChild(costItem);
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



        // Fix image path for relative paths
        function fixImagePath(imagePath) {
            if (imagePath && !imagePath.startsWith('http') && !imagePath.startsWith('/')) {
                return imagePath;
            }
            return imagePath;
        }

        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                // Butonları print dialog açılmadan önce gizle
                const controls = document.querySelector('.pdf-controls');
                if (controls) {
                    controls.style.display = 'none';
                }

                // Print dialog'u aç
                window.print();

                // Print dialog kapatıldıktan sonra butonları tekrar göster
                setTimeout(function() {
                    if (controls) {
                        controls.style.display = 'block';
                    }
                }, 1000);
            }, 500);
        };
    </script>
</body>
</html>