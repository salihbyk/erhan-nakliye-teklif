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
               qt.services_content as template_services_content, qt.terms_content as template_terms_content, qt.currency, qt.language,
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
            padding: 18px;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* Content Area */
        .content {
            display: grid;
            grid-template-columns: 1fr 2.5fr;
            min-height: 600px;
            margin-top: 20px;
        }

        /* Left Side - Details */
        .details-side {
            padding: 0;
            margin-top: 30px;
        }

        /* Right Side - Information */
        .info-side {
            background: #f8f9fa;
            padding: 30px;
            border-left: 1px solid #e0e0e0;
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
            padding: 20px 40px;
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

            .details-side {
                margin-top: 0 !important;
                width: 100% !important;
                float: left !important;
                margin-bottom: 10px !important;
            }

            .info-side {
                background: white !important;
                padding: 10px !important;
                border-left: none !important;
                border-top: 1px solid #e0e0e0 !important;
                width: 100% !important;
                float: left !important;
                clear: both !important;
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

        <!-- Info Header - Moved here -->
        <div style="padding: 40px 50px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); text-align: left;">
            <div style="max-width: 800px;">
                <p style="font-size: 16px; font-weight: 600; color: #2c5aa0; margin-bottom: 20px; line-height: 1.6;">
                    <?= !empty($quote['greeting_text']) ? $quote['greeting_text'] : '<strong>' . ($is_english ? 'Dear' : 'Sayın') . ' ' . htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']) . ($is_english ? ',' : ',') . '</strong>' ?>
                </p>
                <p style="font-size: 15px; color: #333; line-height: 1.7; margin: 0; font-weight: 400;">
                    <?= !empty($quote['intro_text']) ? $quote['intro_text'] : ($is_english ? 'As Europatrans Global Logistics Transportation, we offer and undertake to carry out the international transportation of your goods under the conditions specified below.' : 'Europatrans Global Lojistik Taşımacılık olarak eşyalarınızın uluslararası taşımasını, aşağıda belirtilen şartlar dahilinde yapmayı teklif ve taahhüt ederiz.') ?>
                </p>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Left Side - Details -->
            <div class="details-side">
                <!-- Price Section - En Üstte -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['price_info'] ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="price-section">
                            <div class="price-label"><?= $t['our_quote_price'] ?></div>
                            <div class="price-amount"><?php echo formatPriceWithCurrency($quote['final_price'], $currency); ?></div>
                        </div>

                        <!-- Quote Approval Section -->
                        <?php if (!$is_expired): ?>
                        <div style="padding: 20px; border-top: 1px solid #e0e0e0; margin-top: 15px;">
                            <!-- Approval Checkbox -->
                            <div class="approval-checkbox" style="margin-bottom: 15px;">
                                <label style="display: flex; align-items: center; cursor: pointer; font-size: 14px;">
                                    <input type="checkbox" id="approvalCheckbox" onchange="toggleApprovalButton()"
                                           style="margin-right: 10px; transform: scale(1.2);">
                                    <span><?= $is_english ? 'I have read and approve the quote' : 'Teklifi okudum onaylıyorum' ?></span>
                                </label>
                            </div>

                            <!-- Approval Buttons -->
                            <div class="approval-buttons">
                                <button id="approveButton" onclick="approveQuote()" class="btn-action btn-approve" disabled
                                        style="opacity: 0.5; cursor: not-allowed;">
                                    <i class="fas fa-check-circle"></i> <?= $is_english ? 'Approve' : 'Onayla' ?>
                                </button>
                                <a href="api/generate-pdf.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                                   target="_blank" class="btn-action btn-pdf">
                                    <i class="fas fa-file-pdf"></i> <?= $is_english ? 'Download PDF' : 'PDF İndir' ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Costs -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['additional_costs'] ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="additional-costs-list" id="additionalCostsList">
                            <!-- Additional costs will be loaded here dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['customer_info'] ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="form-row">
                            <span class="form-label"><?= $t['name_surname'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?></span>
                        </div>
                        <?php if (!empty($quote['company'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['company'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['company']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['email'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['email']); ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label"><?= $t['phone'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['phone']); ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label"><?= $t['quote_date'] ?></span>
                            <span class="form-value"><?php echo formatDate($quote['created_at']); ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label"><?= $t['validity'] ?></span>
                            <span class="form-value"><?php echo formatDate($quote['valid_until']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Transport Info -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['transport_details'] ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="form-row">
                            <span class="form-label"><?= $t['transport_type'] ?></span>
                            <span class="form-value"><?php
                                $display_transport_name = !empty($quote['custom_transport_name'])
                                    ? $quote['custom_transport_name']
                                    : translateTransportMode($quote['transport_name'], $t);
                                echo htmlspecialchars($display_transport_name);
                            ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label"><?= $t['origin'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['origin']); ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label"><?= $t['destination'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['destination']); ?></span>
                        </div>
                        <?php if (!empty($quote['start_date']) && $quote['start_date'] !== '0000-00-00' && $quote['start_date'] !== null): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['start_date'] ?></span>
                            <span class="form-value"><?php echo formatDate($quote['start_date']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['delivery_date']) && $quote['delivery_date'] !== '0000-00-00' && $quote['delivery_date'] !== null): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['delivery_date'] ?></span>
                            <span class="form-value"><?php echo formatDate($quote['delivery_date']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['status'] ?></span>
                            <span class="form-value">
                                <span style="background: <?php echo $is_expired ? '#e74c3c' : '#27ae60'; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px;">
                                    <?php echo $is_expired ? $t['expired'] : $t['active']; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cargo Info - Show for all transport modes -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $t['cargo_info'] ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php if (!empty($quote['weight'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['weight'] ?></span>
                            <span class="form-value"><?php echo number_format($quote['weight'], 0, ',', '.'); ?> kg</span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['volume'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['volume'] ?></span>
                            <span class="form-value"><?php echo number_format($quote['volume'], 2, ',', '.'); ?> m³</span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['unit_price'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $is_english ? 'Unit m³ Price' : 'Birim m³ Fiyat' ?></span>
                            <span class="form-value"><?php echo number_format($quote['unit_price'], 2, ',', '.'); ?> <?= $currency ?? 'TL' ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['pieces'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['pieces'] ?></span>
                            <span class="form-value"><?php echo number_format($quote['pieces'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['cargo_type'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['cargo_type'] ?></span>
                            <span class="form-value">
                                <?php
                                if ($is_english) {
                                    $cargo_types = [
                                        'kisisel_esya' => 'Personal Effects',
                                        'ev_esyasi' => 'Household Goods',
                                        'ticari_esya' => 'Commercial Goods'
                                    ];
                                } else {
                                    $cargo_types = [
                                        'kisisel_esya' => 'Kişisel Eşya',
                                        'ev_esyasi' => 'Ev Eşyası',
                                        'ticari_esya' => 'Ticari Eşya'
                                    ];
                                }
                                echo $cargo_types[$quote['cargo_type']] ?? ($quote['cargo_type'] ?: $t['not_specified']);
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['trade_type'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['trade_type'] ?></span>
                            <span class="form-value">
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
                        <?php if (!empty($quote['description'])): ?>
                        <div class="form-row">
                            <span class="form-label"><?= $t['description'] ?></span>
                            <span class="form-value"><?php echo htmlspecialchars($quote['description']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>


            </div>

            <!-- Right Side - Information -->
            <div class="info-side">

                <!-- Transport Process -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label"><?= $is_english ? 'Transport Process' : 'Taşınma Süreci' ?></div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <h4><?= $is_english ? 'TRANSPORT PROCESS' : 'TAŞINMA SÜRECİ' ?></h4>

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
                        <?php endif; ?>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
                descriptionHtml = `<div class="cost-note" style="font-size: 11px; color: #666; font-style: italic; margin-top: 4px;">${description}</div>`;
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


    </script>
</body>
</html>