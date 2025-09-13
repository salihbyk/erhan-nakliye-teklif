<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Teklif ID'sini al
$quote_id = $_GET['id'] ?? '';
$revision = $_GET['rev'] ?? null;

if (empty($quote_id)) {
    die('Teklif numarası belirtilmemiş.');
}

try {
    $database = new Database();
    $db = $database->getConnection();

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
               qt.services_title as template_services_title, qt.transport_process_title as template_transport_process_title, qt.terms_title as template_terms_title,
               qt.dynamic_sections as template_dynamic_sections, qt.section_order as template_section_order,
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

    // Onay durumu kontrolü
    $is_approved = ($quote['status'] ?? 'pending') === 'accepted';

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
            cursor: default;
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
        }

        .section-label {
            background: #ffc107;
            color: #333;
            padding: 8px 15px;
            font-weight: 600;
            font-size: 12px;
            min-width: 150px;
            text-align: center;
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
            cursor: default;
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
            cursor: default;
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

        /* Sayısal değerler için sağa hizalama */
        span[style*="cursor: default"][style*="padding: 2px 6px"] {
            text-align: right;
            display: block;
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
        .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:nth-child(2) {
            text-align: right !important;
            justify-self: end;
            padding-right: 8px;
        }

        /* Mobil Responsive Styles */
        @media (max-width: 768px) {
            .container {
                margin: 0;
                max-width: 100%;
                box-shadow: none;
            }

            /* Header responsive */
            .header {
                padding: 10px 15px;
                flex-direction: column;
                gap: 10px;
            }

            .logo {
                height: 40px !important;
            }

            .company-info {
                text-align: center;
            }

            .company-info h1 {
                font-size: 16px !important;
            }

            .main-title {
                font-size: 18px !important;
                padding: 15px;
            }

            /* İletişim Bilgileri alanını gizle */
            .contact-info-section {
                display: none !important;
            }

            /* Grid düzeni mobilde tek sütun yap */
            div[style*="display: grid"][style*="grid-template-columns: 2fr 1fr"] {
                display: block !important;
            }

            /* Taşımaya Dair Genel Bilgiler - mobilde 2 sütun responsive */
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 60px"],
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 40px"] {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 15px !important;
                padding: 10px 15px !important;
            }

            /* Genel bilgiler alanındaki padding'i azalt */
            div[style*="padding: 15px 20px"] {
                padding: 10px 15px !important;
            }

                        /* Noktalı çizgiyi mobilde daha kısa yap */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"]:before {
                content: "................................" !important;
                font-size: 12px !important;
                letter-spacing: 2px !important;
            }

            /* Mobilde etiket ve değer grid layout kalsın ama daha kompakt */
            div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] {
                display: grid !important;
                grid-template-columns: auto 1fr !important;
                gap: 5px !important;
                align-items: center !important;
                margin-bottom: 8px !important;
                min-height: 20px !important;
            }

            /* Değerleri mobilde sağa hizala */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:last-child,
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:nth-child(2) {
                text-align: right !important;
                justify-self: end !important;
                padding-right: 5px !important;
                font-weight: 500;
                font-size: 12px !important;
            }

            /* Etiketleri mobilde daha küçük yap */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:first-child {
                font-size: 11px !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 80px;
            }

            /* Price table responsive */
            table[style*="width: 100%"] {
                font-size: 14px !important;
            }

            table[style*="width: 100%"] td {
                padding: 8px 12px !important;
            }

            /* Mobilde sections padding'i azalt */
            div[style*="padding: 15px 15px"] {
                padding: 10px 15px !important;
            }

            /* Form sections mobilde daha compact */
            .form-section {
                margin-bottom: 15px !important;
            }

            /* Butonlar mobilde daha büyük */
            button, .btn {
                min-height: 44px !important;
                padding: 12px 20px !important;
                font-size: 16px !important;
            }

            /* Input alanları mobilde daha büyük */
            input, textarea, select {
                min-height: 44px !important;
                font-size: 16px !important;
            }
        }

        /* Çok küçük ekranlar için (dar telefonlar) */
        @media (max-width: 480px) {
            /* Çok dar ekranlarda tek sütun yap */
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 60px"],
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"][style*="gap: 40px"] {
                grid-template-columns: 1fr !important;
                gap: 5px !important;
            }

            /* Etiketleri daha da küçük yap */
            .form-section div[style*="display: grid"][style*="grid-template-columns: auto 1fr"] > span:first-child {
                font-size: 10px !important;
                max-width: 70px;
            }

            /* Container padding'i azalt */
            .container {
                padding: 0 5px;
            }
        }

        /* Özel İletişim Bilgileri gizleme kuralı */
        @media (max-width: 768px) {
            div[style*="background: rgba(255,255,255,0.9)"][style*="Contact Info"] {
                display: none !important;
            }

            /* İletişim bilgileri içeren parent container'ı da tek sütun yap */
            div[style*="grid-template-columns: 2fr 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
                        <?= !empty($quote['greeting_text']) ? $quote['greeting_text'] : '<strong>' . ($is_english ? 'Dear' : 'Sayın') . ' ' . htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']) . ($is_english ? ',' : ',') . '</strong>' ?>
                    </p>
                    <p style="font-size: 15px; color: #333; line-height: 1.7; margin: 0; font-weight: 400;">
                        <?= !empty($quote['intro_text']) ? $quote['intro_text'] : ($is_english ? 'As Europatrans Global Logistics Transportation, we offer and undertake to carry out the international transportation of your goods under the conditions specified below.' : 'Europatrans Global Lojistik Taşımacılık olarak eşyalarınızın uluslararası taşımasını, aşağıda belirtilen şartlar dahilinde yapmayı teklif ve taahhüt ederiz.') ?>
                    </p>
                </div>

                                                <!-- Right Side - Contact Info -->
                <div class="contact-info-section" style="background: rgba(255,255,255,0.9); padding: 16px; border-radius: 6px; border: 1px solid rgba(44,90,160,0.15); box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: right;">
                    <h4 style="color: #2c5aa0; font-size: 13px; font-weight: 600; margin-bottom: 12px; margin-top: 0; text-transform: uppercase; letter-spacing: 0.5px; text-align: right;">
                        <?= $is_english ? 'Contact Information' : 'İletişim Bilgileri' ?>
                    </h4>

                    <div style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 12px;">
                            <?= $t['email'] ?>:
                        </span>
                        <span style="font-size: 12px; color: #333;">
                            <?php echo htmlspecialchars($quote['email']); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 0; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
                        <span style="font-weight: 600; color: #2c5aa0; font-size: 12px;">
                            <?= $t['phone'] ?>:
                        </span>
                        <span style="font-size: 12px; color: #333;">
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
                            <span   style="cursor: default; padding: 2px 4px; border-radius: 3px; transition: background 0.2s;"
                                   >
                                <?= $t['our_quote_price'] ?>
                            </span>
                        </td>
                        <td style="padding: 12px 20px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); text-align: right; font-size: 20px; font-weight: 700; color: #155724; cursor: default;"  >
                            <span id="mainPriceDisplay"><?php echo formatPriceWithCurrency($quote['final_price'], $currency); ?></span>
                            <input type="text" id="mainPriceEdit" style="display: none; width: 100%; text-align: right; border: none; font-size: 20px; font-weight: 700; color: #155724; background: transparent;" onblur="saveMainPrice()" onkeypress="handleMainPriceKeypress(event)" value="<?php echo $quote['final_price']; ?>">
                        </td>
                    </tr>
                </table>

                <!-- Additional Costs Table -->
                <div id="additionalCostsTable">
                    <!-- Ek maliyetler buraya eklenecek -->
                </div>





                <!-- Approval Section -->
                <?php if (!$is_expired): ?>
                <div style="text-align: right; padding-top: 15px; border-top: 1px solid #e0e0e0;">

                    <?php if ($is_approved): ?>
                        <!-- Onaylandı Mesajı -->
                        <div style="margin-bottom: 15px; padding: 10px 15px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;">
                            <i class="fas fa-check-circle" style="color: #28a745; margin-right: 8px;"></i>
                            <strong><?= $is_english ? 'Quote Approved' : 'Teklif Onaylandı' ?></strong>
                            <div style="font-size: 12px; margin-top: 5px; opacity: 0.8;">
                                <?= $is_english ? 'This quote has been approved and notification sent to the company.' : 'Bu teklif onaylanmıştır ve firmaya bildirim gönderilmiştir.' ?>
                            </div>
                        </div>

                        <!-- PDF Download Button (always visible and active when approved) -->
                        <div style="margin-bottom: 15px;">
                            <a href="api/generate-pdf.php?id=<?php echo urlencode($quote['quote_number']); ?>" target="_blank" style="padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; transition: background 0.3s;">
                                <i class="fas fa-file-pdf"></i> <?= $is_english ? 'Download PDF' : 'PDF İndir' ?>
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Approval Checkbox -->
                        <div style="margin-bottom: 15px;">
                            <label style="display: inline-flex; align-items: center; cursor: default; font-size: 14px;">
                                <input type="checkbox" id="approvalCheckbox" onchange="toggleApprovalButton()" style="margin-right: 10px; transform: scale(1.2);">
                                <span><?= $is_english ? 'I have read and approve the quote' : 'Teklifi okudum onaylıyorum' ?></span>
                            </label>
                        </div>

                        <!-- Buttons Side by Side -->
                        <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end; margin-bottom: 15px;">
                            <!-- Approval Button -->
                            <button id="approveButton" onclick="approveQuote()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; font-size: 14px; font-weight: 600; cursor: not-allowed; opacity: 0.5;" disabled>
                                <i class="fas fa-check-circle"></i> <?= $is_english ? 'Approve' : 'Onayla' ?>
                            </button>

                            <!-- PDF Download Button (visible but disabled until approval) -->
                            <a href="#" onclick="return false;" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-file-pdf"></i> <?= $is_english ? 'Download PDF (Approval Required)' : 'PDF İndir (Onay Gerekli)' ?>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
                <?php endif; ?>

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
                                <span   style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo htmlspecialchars($quote['company']); ?>
                                </span>
                            </div>
                                                        <?php endif; ?>




                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['quote_date'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo formatDate($quote['created_at']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['validity'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo formatDate($quote['valid_until']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['transport_type'] ?>:</span>
                                <span   style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo htmlspecialchars(!empty($quote['custom_transport_name']) ? $quote['custom_transport_name'] : translateTransportMode($quote['transport_name'], $t)); ?>
                                </span>
                            </div>

                            <?php if (strtolower($quote['transport_name']) === 'havayolu' && !empty($quote['weight'])): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['weight'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo number_format($quote['weight'], 0, ',', '.'); ?> kg
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (strtolower($quote['transport_name']) === 'havayolu' && !empty($quote['pieces'])): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['pieces'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo number_format($quote['pieces'], 0, ',', '.'); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>


                        <!-- Right Column -->
                        <div>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['origin'] ?>:</span>
                                <span   style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo htmlspecialchars($quote['origin']); ?>
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['destination'] ?>:</span>
                                <span   style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo htmlspecialchars($quote['destination']); ?>
                                </span>
                            </div>

                            <?php if (!empty($quote['start_date']) && $quote['start_date'] !== '0000-00-00' && $quote['start_date'] !== null): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['start_date'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo formatDate($quote['start_date']); ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($quote['delivery_date']) && $quote['delivery_date'] !== '0000-00-00' && $quote['delivery_date'] !== null): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['delivery_date'] ?>:</span>
                                <span    style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo formatDate($quote['delivery_date']); ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($quote['volume'])): ?>
                            <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">
                                <span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;"><?= $t['volume'] ?>:</span>
                                <span     style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s;"
                                       >
                                    <?php echo number_format($quote['volume'], 2, ',', '.'); ?> m³
                                </span>
                            </div>
                            <?php endif; ?>


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

                            // Custom field'ları organiza et (admin ile aynı formatta)
                            $organizedFields = [];
                            $maxRowNumber = 0;

                            foreach ($fieldKeys as $key) {
                                if (preg_match('/custom_(?:label|value|label2|value2)_(\d+)/', $key, $matches)) {
                                    $rowNum = intval($matches[1]);
                                    if ($rowNum > $maxRowNumber) {
                                        $maxRowNumber = $rowNum;
                                    }
                                }
                            }

                            // Organize fields by row number
                            for ($i = 1; $i <= $maxRowNumber; $i++) {
                                $label1Key = "custom_label_$i";
                                $value1Key = "custom_value_$i";
                                $label2Key = "custom_label2_$i";
                                $value2Key = "custom_value2_$i";

                                if (isset($custom_fields[$label1Key]) && isset($custom_fields[$value1Key])) {
                                    $organizedFields[] = [
                                        'label1' => $label1Key,
                                        'value1' => $value1Key,
                                        'label2' => isset($custom_fields[$label2Key]) ? $label2Key : null,
                                        'value2' => isset($custom_fields[$value2Key]) ? $value2Key : null
                                    ];
                                }
                            }

                                                        // Ana grid container'ın içinde devam et
                            if (!empty($organizedFields)) {
                                // Section title'ı al
                                $sectionTitle = isset($custom_fields['custom_section_title']) ? htmlspecialchars($custom_fields['custom_section_title']) : 'Ek olarak:';

                                // İlk custom field'dan önce ayırıcı ekle
                                echo '<div style="grid-column: 1 / -1; display: flex; align-items: center; margin: 15px 0 10px 0;">';
                                echo '<span style="font-weight: 600; color: #2c5aa0; font-size: 14px; white-space: nowrap;">' . $sectionTitle . '</span>';
                                echo '<div style="flex: 1; height: 1px; background: #ddd; margin-left: 15px;"></div>';
                                echo '</div>';

                                foreach ($organizedFields as $pair) {
                                    // Sol kolon
                                    echo '<div>';
                                    if (isset($custom_fields[$pair['label1']]) && isset($custom_fields[$pair['value1']])) {
                                        echo '<div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">';
                                        echo '<span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;">' . htmlspecialchars($custom_fields[$pair['label1']]) . ':</span>';
                                        echo '<span style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;">';
                                        echo htmlspecialchars($custom_fields[$pair['value1']]);
                                        echo '</span>';
                                        echo '</div>';
                                    }
                                    echo '</div>';

                                    // Sağ kolon
                                    echo '<div>';
                                    if ($pair['label2'] && $pair['value2'] && isset($custom_fields[$pair['label2']]) && isset($custom_fields[$pair['value2']])) {
                                        echo '<div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center; margin-bottom: 8px; min-height: 24px;">';
                                        echo '<span style="font-weight: 600; color: #2c5aa0; font-size: 13px; white-space: nowrap;">' . htmlspecialchars($custom_fields[$pair['label2']]) . ':</span>';
                                        echo '<span style="cursor: default; padding: 2px 6px; border-radius: 3px; transition: background 0.2s; text-align: right;">';
                                        echo htmlspecialchars($custom_fields[$pair['value2']]);
                                        echo '</span>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                        }
                    }
                    ?>
                    </div>

                </div>
                    </div>

        <!-- Content -->
        <div class="content">
            <!-- Information Section -->
            <div class="info-side">

        <?php
        // Müşteri görünümünde şablon sırasına göre bölümleri render et
        $custom_fields = [];
        if (!empty($quote['custom_fields'])) {
            $cf = json_decode($quote['custom_fields'], true);
            if (is_array($cf)) { $custom_fields = $cf; }
        }

        $dynamic_sections = [];
        if (!empty($quote['template_dynamic_sections'])) {
            $dynamic_sections = json_decode($quote['template_dynamic_sections'], true) ?: [];
        }

        $section_order = [];
        if (!empty($quote['template_section_order'])) {
            $section_order = json_decode($quote['template_section_order'], true) ?: [];
        }
        if (empty($section_order)) {
            $section_order = ['services','transport','terms'];
            // Şablonda tanımlı dinamik içerikler varsa sıraya ekle
            foreach ($dynamic_sections as $k => $v) {
                if (preg_match('/^dynamic_section_(\d+)_content$/', $k, $m)) {
                    $section_order[] = 'dynamic_' . $m[1];
                }
            }
        }

        foreach ($section_order as $secKey) {
            if ($secKey === 'services') {
                // Başlık önceliği: template -> çeviri
                $services_label = !empty($quote['template_services_title']) ? $quote['template_services_title'] : $t['services'];
                echo '<div class="form-section" data-section="services">';
                echo '  <div class="section-header">';
                echo '    <div class="section-label">' . htmlspecialchars($services_label) . '</div>';
                echo '    <div class="section-title"></div>';
                echo '  </div>';
                echo '  <div class="form-content">';
                // İçerik: quote override -> template
                $services_content = $quote['services_content'] ?? $quote['template_services_content'] ?? '';
                if (!empty($services_content)) {
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
                }
                // Maliyet listesi linki
                if (!empty($quote['cost_list_name'])) {
                    echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">';
                    if (!empty($quote['cost_list_file_path']) && file_exists($quote['cost_list_file_path'])) {
                        echo '<p>' . ($is_english ? 'You can download the detailed cost list' : 'Detaylı maliyet listesini') . ' <a href="' . htmlspecialchars($quote['cost_list_file_path']) . '" target="_blank" style="color: #2c5aa0; text-decoration: underline; font-weight: 500;">' . ($is_english ? 'from here' : 'buradan indirebilirsiniz') . '</a>' . ($is_english ? '.' : '.') . '</p>';
                    } else {
                        echo '<p style="color: #666; font-style: italic;">' . ($is_english ? 'Detailed cost list:' : 'Detaylı maliyet listesi:') . ' <strong>' . htmlspecialchars($quote['cost_list_name']) . '</strong> ' . ($is_english ? ' (File not available)' : ' (Dosya mevcut değil)') . '</p>';
                    }
                    echo '</div>';
                }
                echo '  </div>';
                echo '</div>';
            } elseif ($secKey === 'transport' || $secKey === 'transport_process') {
                $trans_label = !empty($quote['template_transport_process_title']) ? $quote['template_transport_process_title'] : ($is_english ? 'Transport Process' : 'Taşınma Süreci');
                echo '<div class="form-section" data-section="transport_process">';
                echo '  <div class="section-header">';
                echo '    <div class="section-label">' . htmlspecialchars($trans_label) . '</div>';
                echo '    <div class="section-title"></div>';
                echo '  </div>';
                echo '  <div class="form-content">';
                // İçerik önceliği: quote override -> template -> varsayılan
                if (!empty($quote['transport_process_text'])) {
                    $transport_process_text = $quote['transport_process_text'];
                } elseif (!empty($quote['template_transport_process_content'])) {
                    $transport_process_text = $quote['template_transport_process_content'];
                } else {
                    $transport_process_text = '';
                    $transport_mode_lower = strtolower($quote['transport_name']);
                    if ($is_english) {
                        if (strpos($transport_mode_lower, 'karayolu') !== false) {
                            $transport_process_text = "1-Presentation and mutual signing of the offer,<br>2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>3-Preparation of customs documents,<br>4-Collection of goods from the loading address,<br>5-Customs clearance of the goods and departure,<br>6-Payment of the remaining balance,<br>7-Customs clearance procedures in the destination country,<br>8-Delivery of goods to the delivery address.";
                        } elseif (strpos($transport_mode_lower, 'deniz') !== false) {
                            $transport_process_text = "1-Presentation and mutual signing of the offer,<br>2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>3-Container reservation from the shipping company,<br>4-Preparation of customs documents,<br>5-Collection of goods from the loading address,<br>6-Customs clearance of the goods and departure,<br>7-Payment of the remaining balance,<br>8-Customs clearance procedures in the destination country,<br>9-Delivery of goods to the delivery address.";
                        } elseif (strpos($transport_mode_lower, 'hava') !== false) {
                            $transport_process_text = "1-Presentation and mutual signing of the offer,<br>2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>3-Cargo reservation from the airline company,<br>4-Preparation of customs documents,<br>5-Collection of goods from the loading address,<br>6-Customs clearance of the goods and departure,<br>7-Payment of the remaining balance,<br>8-Customs clearance procedures in the destination country,<br>9-Delivery of goods to the delivery address.";
                        } else {
                            $transport_process_text = "1-Presentation and mutual signing of the offer,<br>2-Making a 20% down payment of the offer price to the company account and making a definitive registration in our operation program,<br>3-Preparation of customs documents,<br>4-Collection of goods from the loading address,<br>5-Customs clearance of the goods and departure,<br>6-Payment of the remaining balance,<br>7-Customs clearance procedures in the destination country,<br>8-Delivery of goods to the delivery address.";
                        }
                    } else {
                        if (strpos($transport_mode_lower, 'karayolu') !== false) {
                            $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>3-Gümrük evraklarının hazırlanması,<br>4-Eşyaların yükleme adresinden alınması,<br>5-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>6-Kalan bakiye ödemesinin yapılması,<br>7-Varış ülke gümrük açılım işlemlerinin yapılması,<br>8-Eşyanın teslimat adresine teslimi şeklindedir.";
                        } elseif (strpos($transport_mode_lower, 'deniz') !== false) {
                            $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>3-Gemi firmasından konteynır rezervasyonunun yapılması,<br>4-Gümrük evraklarının hazırlanması,<br>5-Eşyaların yükleme adresinden alınması,<br>6-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>7-Kalan bakiye ödemesinin yapılması,<br>8-Varış ülke gümrük açılım işlemlerinin yapılması,<br>9-Eşyanın teslimat adresine teslimi şeklindedir.";
                        } elseif (strpos($transport_mode_lower, 'hava') !== false) {
                            $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>3-Havayolu şirketinden kargo rezervasyonunun yapılması,<br>4-Gümrük evraklarının hazırlanması,<br>5-Eşyaların yükleme adresinden alınması,<br>6-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>7-Kalan bakiye ödemesinin yapılması,<br>8-Varış ülke gümrük açılım işlemlerinin yapılması,<br>9-Eşyanın teslimat adresine teslimi şeklindedir.";
                        } else {
                            $transport_process_text = "1-Teklif sunulması ve karşılıklı imzalanması,<br>2-Şirket hesabına teklif fiyatındaki tutarın %20 si oranında ön ödeme yapılması ve operasyon programımıza kesin kayıt yapılması,<br>3-Gümrük evraklarının hazırlanması,<br>4-Eşyaların yükleme adresinden alınması,<br>5-Eşyanın gümrük işlemlerinin yapılarak yola çıkartılması,<br>6-Kalan bakiye ödemesinin yapılması,<br>7-Varış ülke gümrük açılım işlemlerinin yapılması,<br>8-Eşyanın teslimat adresine teslimi şeklindedir.";
                        }
                    }
                }
                echo '<div class="transport-process-content"><p>' . $transport_process_text . '</p></div>';
                echo '  </div>';
                echo '</div>';
            } elseif ($secKey === 'terms') {
                $terms_label = !empty($quote['template_terms_title']) ? $quote['template_terms_title'] : $t['terms'];
                echo '<div class="form-section" data-section="terms">';
                echo '  <div class="section-header">';
                echo '    <div class="section-label">' . htmlspecialchars($terms_label) . '</div>';
                echo '    <div class="section-title"></div>';
                echo '  </div>';
                echo '  <div class="form-content">';
                $terms_content = $quote['terms_content'] ?? $quote['template_terms_content'] ?? '';
                if (!empty($terms_content)) {
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
                }
                echo '  </div>';
                echo '</div>';
            } elseif (strpos($secKey, 'dynamic_') === 0) {
                $idPart = substr($secKey, strlen('dynamic_'));
                $titleKey = 'dynamic_section_' . $idPart . '_title';
                $contentKey = 'dynamic_section_' . $idPart . '_content';
                $title = $custom_fields[$titleKey] ?? ($dynamic_sections[$titleKey] ?? '');
                $content = $custom_fields[$contentKey] ?? ($dynamic_sections[$contentKey] ?? '');
                if (!empty($content)) {
                    echo '<div class="form-section" data-section="' . htmlspecialchars($secKey) . '">';
                    echo '  <div class="section-header">';
                    echo '    <div class="section-label">' . (!empty($title) ? htmlspecialchars($title) : '<span style="color:#666">Yeni Bölüm</span>') . '</div>';
                    echo '    <div class="section-title"></div>';
                    echo '  </div>';
                    echo '  <div class="form-content">' . $content . '</div>';
                    echo '</div>';
                }
            }
        }
        ?>

                                <!-- Modern Referans Görseller Bölümü -->
                <?php if (($quote['show_reference_images'] ?? 0) == 1): ?>
                <?php
                    $display_transport_name = !empty($quote['custom_transport_name'])
                        ? $quote['custom_transport_name']
                        : translateTransportMode($quote['transport_name'], $t);
                ?>
                <div class="reference-gallery-section">
                    <div class="reference-gallery-header">
                        <h3>
                            <i class="fas fa-camera me-2" style="color: #667eea;"></i>
                            <?= htmlspecialchars($display_transport_name) ?> <?= $is_english ? 'Reference Images' : 'Referans Görselleri' ?>
                        </h3>
                        <p><?= $is_english ? 'Discover our professional service quality and experiences' : 'Profesyonel hizmet kalitemizi ve deneyimlerimizi keşfedin' ?></p>
                    </div>

                    <div class="text-center">
                        <button type="button" class="modern-gallery-btn" onclick="showGalleryModal(<?= $quote['transport_mode_id'] ?>, '<?= htmlspecialchars($quote['transport_name']) ?>')">
                            <i class="fas fa-images"></i>
                            <?= $is_english ? 'View Gallery' : 'Galeriyi İncele' ?>
                        </button>
                    </div>



                    <div class="gallery-features">
                        <div class="gallery-feature">
                            <i class="fas fa-shield-alt"></i>
                            <span><?= $is_english ? 'Safe Transport' : 'Güvenli Taşıma' ?></span>
                        </div>
                        <div class="gallery-feature">
                            <i class="fas fa-award"></i>
                            <span><?= $is_english ? 'Professional Service' : 'Profesyonel Hizmet' ?></span>
                        </div>
                        <div class="gallery-feature">
                            <i class="fas fa-clock"></i>
                            <span><?= $is_english ? 'On-Time Delivery' : 'Zamanında Teslimat' ?></span>
                        </div>
                        <div class="gallery-feature">
                            <i class="fas fa-users"></i>
                            <span><?= $is_english ? 'Experienced Team' : 'Deneyimli Ekip' ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Additional Section 1 -->
                <?php if (!empty($quote['additional_section1_title']) || !empty($quote['additional_section1_content'])): ?>
                <div class="form-section" data-section="additional1">
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
                <div class="form-section" data-section="additional2">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Minimal script for gallery functionality only

        // Gallery functionality for reference images
        let galleryImages = [];
        let currentImageIndex = 0;

        // Additional costs functionality
        let additionalCosts = [];

        // Load additional costs from server
        function loadAdditionalCosts() {
            const quoteNumber = <?= json_encode($quote['quote_number']) ?>;
            const quoteId = <?= json_encode($quote['id']) ?>;
            console.log('loadAdditionalCosts called for quote:', quoteNumber, 'ID:', quoteId);


            fetch(`api/get-additional-costs.php?quote_id=${quoteId}`)
            .then(response => {

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.costs && data.costs.length > 0) {
                    additionalCosts = data.costs.map(cost => ({
                        id: cost.id,
                        name: cost.name || cost.description.split(' - ')[0] || 'Ek Maliyet',
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

        function showGalleryModal(modeId, modeName) {
            const isEnglish = <?= json_encode($is_english) ?>;
            const referenceImagesText = isEnglish ? 'Reference Images' : 'Referans Resimleri';
            const loadingText = isEnglish ? 'Loading reference images...' : 'Referans resimleri yükleniyor...';

            document.getElementById('galleryModalTitle').innerHTML = `<i class="fas fa-images me-2"></i>${modeName} ${referenceImagesText}`;

            // Loading göster
            const container = document.getElementById('galleryImagesContainer');
            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 400px; flex-direction: column;">
                    <div class="spinner-border text-light mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p style="color: #ccc;">${loadingText}</p>
                </div>
            `;

            // Resimleri yükle
            fetch(`api/get-customer-transport-images.php?mode_id=${modeId}`)
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
                    container.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: center; height: 400px; flex-direction: column;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                            <p style="color: #dc3545;">Resimler yüklenirken bir hata oluştu.</p>
                        </div>
                    `;
                });
        }

        function renderGalleryGrid(images) {
            const container = document.getElementById('galleryImagesContainer');
            let html = '<div class="row g-3">';

            images.forEach((image, index) => {
                const imagePath = fixImagePath(image.image_path);
                html += `
                    <div class="col-md-4 col-sm-6">
                        <div class="gallery-item" onclick="openImageViewer(${index})">
                            <img src="${imagePath}" alt="${image.title || 'Referans Resmi'}"
                                 class="img-fluid rounded" style="width: 100%; height: 200px; object-fit: cover; cursor: pointer;">
                            ${image.title ? `<div class="image-title mt-2" style="font-size: 12px; color: #ccc; text-align: center;">${image.title}</div>` : ''}
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function openImageViewer(index) {
            currentImageIndex = index;
            updateImageViewer();

            const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
            modal.show();
        }

        function updateImageViewer() {
            const image = galleryImages[currentImageIndex];
            if (!image) return;

            const imagePath = fixImagePath(image.image_path);
            document.getElementById('viewerImage').src = imagePath;
            document.getElementById('imageCounter').textContent = `${currentImageIndex + 1} / ${galleryImages.length}`;

            // Update navigation buttons
            document.getElementById('prevBtn').disabled = currentImageIndex === 0;
            document.getElementById('nextBtn').disabled = currentImageIndex === galleryImages.length - 1;

            // Update info overlay
            document.getElementById('imageInfoTitle').textContent = image.title || '';
            document.getElementById('imageInfoDesc').textContent = image.description || '';
        }

        function navigateImage(direction) {
            if (direction === 'prev' && currentImageIndex > 0) {
                currentImageIndex--;
            } else if (direction === 'next' && currentImageIndex < galleryImages.length - 1) {
                currentImageIndex++;
            }
            updateImageViewer();
        }

        function fixImagePath(imagePath) {
            if (!imagePath) return '';
            if (imagePath.startsWith('http')) return imagePath;
            if (imagePath.startsWith('/')) return imagePath;
            if (imagePath.startsWith('uploads/')) return imagePath;
            return 'uploads/' + imagePath;
        }

        // Toggle approval button based on checkbox
        function toggleApprovalButton() {
            const checkbox = document.getElementById('approvalCheckbox');
            const button = document.getElementById('approveButton');

            if (checkbox && button) {
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
        }

        // Approve quote functionality
        function approveQuote() {
            const quoteNumber = '<?php echo $quote['quote_number']; ?>';
            const customerName = '<?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>';

            if (confirm('Teklife onay verdiğinizde firmaya onay maili gidecektir. Onaylıyor musunuz?')) {
                // Show loading
                const btn = document.getElementById('approveButton');
                if (!btn) return;

                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
                btn.disabled = true;

                // Send approval email
                fetch('api/approve-quote.php', {
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

                        // Sayfayı yenile (onay durumu güncellenecek)
                        window.location.reload();
                    } else {
                        alert('Hata: ' + (data.message || 'Onay maili gönderilemedi'));
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bağlantı hatası oluştu!');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        }

        // Render additional costs table
        function renderAdditionalCostsTable() {
            const table = document.getElementById('additionalCostsTable');

            if (additionalCosts.length === 0) {
                table.innerHTML = '';
                return;
            }

            let html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;">';

            additionalCosts.forEach((cost, index) => {
                const formattedAmount = formatPrice(cost.amount, cost.currency);
                html += `
                    <tr>
                        <td style="padding: 8px 16px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); font-weight: 500; font-size: 12px; width: 220px; position: relative; color: #155724; border-right: 1px solid rgba(255,255,255,0.3);">
                            <div style="cursor: default;">
                                <span>${cost.name}</span>
                            </div>
                            ${cost.description ? `<div style="cursor: default; font-size: 10px; color: #0a4622; margin-top: 3px; font-style: italic;">
                                <span>${cost.description}</span>
                            </div>` : ''}
                        </td>
                        <td style="padding: 8px 16px; border: none; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); text-align: right; font-size: 16px; font-weight: 600; color: #155724;">
                            <span>${formattedAmount}</span>
                        </td>
                    </tr>
                `;
            });

            html += '</table>';
            table.innerHTML = html;
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

        // Sıralamayı uygula
        function applySectionOrder() {
            const quoteNumber = '<?php echo $quote['quote_number']; ?>';
            const savedOrder = localStorage.getItem(`quote_section_order_${quoteNumber}`);

            if (savedOrder) {
                try {
                    const order = JSON.parse(savedOrder);
                    const container = document.querySelector('.info-side');
                    if (!container) return;

                    // Mevcut section'ları al
                    const sections = Array.from(container.querySelectorAll('.form-section[data-section]'));
                    const sectionMap = {};

                    // Section'ları map'e ekle
                    sections.forEach(section => {
                        const sectionId = section.getAttribute('data-section');
                        if (sectionId) {
                            sectionMap[sectionId] = section;
                        }
                    });

                    // Sıralamaya göre yeniden düzenle
                    order.forEach(sectionId => {
                        if (sectionMap[sectionId]) {
                            container.appendChild(sectionMap[sectionId]);
                        }
                    });

                    // Sıralamada olmayan section'ları sona ekle
                    sections.forEach(section => {
                        const sectionId = section.getAttribute('data-section');
                        if (sectionId && !order.includes(sectionId)) {
                            container.appendChild(section);
                        }
                    });
                } catch (e) {
                    console.error('Sıralama uygulanamadı:', e);
                }
            }
        }

        // Auto-load additional costs when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded triggered - loading additional costs');
            loadAdditionalCosts();
            applySectionOrder(); // Sıralamayı uygula
        });

    </script>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- Modern Galeri Modal -->
    <div class="modal fade modern-gallery-modal" id="galleryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
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

                // Add new cost
        function addNewCost() {
            // Disabled for customer view - no editing allowed
            return;
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
            .editable-field:hover {
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

            fetch('api/update-general-info.php', {
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

            fetch('api/update-quote-price.php', {
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

            fetch('api/save-additional-costs.php', {
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





        // Galeri modal fonksiyonu
        // Global variables for gallery
        let galleryImages = [];
        let swiper = null;

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
            fetch(`api/get-customer-transport-images.php?mode_id=${modeId}`)
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
                        <img src="${image.image_path}" alt="${image.image_name}" loading="lazy">
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
                slide.innerHTML = `<img src="${image.image_path}" alt="${image.image_name}" loading="lazy">`;
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

        function testAdditionalCosts() {
            console.log('Testing additional costs API...');
            fetch('api/test-additional-costs.php')
            .then(response => response.json())
            .then(data => {
                console.log('Test API response:', data);
                alert('Test sonuçları console\'da - F12 açın');
            })
            .catch(error => {
                console.error('Test API error:', error);
                alert('Test hatası: ' + error.message);
            });
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

</body>
</html>