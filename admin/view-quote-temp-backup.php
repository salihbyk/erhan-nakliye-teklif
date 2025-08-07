<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

checkAdminSession();

$quote_id = isset($_GET['id']) ? $_GET['id'] : '';
if (empty($quote_id)) {
    die('Teklif numarası belirtilmemiş.');
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        die('Veritabanı bağlantısı kurulamadı.');
    }

    // AJAX inline editing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');

        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $field = isset($_POST['field']) ? $_POST['field'] : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';

        if ($action === 'update_field' && $field) {
            try {
                // Güvenlik kontrolü - sadece belirli alanları güncellemeye izin ver
                $customer_fields = ['first_name', 'last_name', 'email', 'phone', 'company'];
                $quote_fields = ['origin', 'destination', 'weight', 'volume', 'pieces', 'cargo_type', 'trade_type', 'description', 'final_price', 'notes', 'services_content', 'optional_services_content', 'terms_content', 'unit_price', 'transport_process_text', 'start_date', 'delivery_date', 'additional_section1_title', 'additional_section1_content', 'additional_section2_title', 'additional_section2_content', 'transport_mode_id', 'custom_transport_name', 'intro_text', 'greeting_text'];
                $template_fields = [
                    'phone_display', 'quote_number_display', 'main_title', 'price_section_label', 'price_label',
                    'unit_price_label', 'customs_fee_text', 'customer_section_label', 'name_label', 'company_label',
                    'email_label', 'phone_label', 'quote_date_label', 'validity_label', 'transport_section_label',
                    'transport_type_label', 'origin_label', 'destination_label', 'status_label', 'cargo_section_label',
                    'weight_label', 'volume_label', 'pieces_label', 'cargo_type_label', 'trade_type_label',
                    'description_label', 'info_section_label', 'greeting_text', 'intro_text', 'general_info_section_label',
                    'general_info_title', 'services_section_label', 'included_services_title', 'included_services_list',
                    'optional_services_title', 'optional_services_list', 'terms_section_label', 'payment_title',
                    'payment_text', 'other_terms_title', 'other_terms_text', 'company_name', 'company_address',
                    'company_website', 'company_contact'
                ];

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
                    $stmt->execute([$value, $quote_id]);
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
                } elseif (in_array($field, $template_fields)) {
                    // Template alanları - şimdilik sadece başarılı dön (gerçek uygulamada template tablosuna kaydedilebilir)
                    // Bu alanlar UI'da değişir ama veritabanında saklanmaz
                } else {
                    throw new Exception('Bu alan düzenlenemez');
                }

                echo json_encode(['success' => true, 'message' => 'Güncellendi']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        } elseif ($action === 'update_cost_list') {
            try {
                // Maliyet listesi ID'sini güncelle
                $cost_list_id = isset($_POST['cost_list_id']) ? $_POST['cost_list_id'] : null;

                // Boş string'i null'a çevir
                if ($cost_list_id === '' || $cost_list_id === '0') {
                    $cost_list_id = null;
                }

                $stmt = $db->prepare("UPDATE quotes SET cost_list_id = ?, updated_at = NOW() WHERE quote_number = ?");
                $stmt->execute([$cost_list_id, $quote_id]);

                echo json_encode(['success' => true, 'message' => 'Maliyet listesi güncellendi']);
                exit;

            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
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
        WHERE q.quote_number = ?
        LIMIT 1
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        die('Teklif bulunamadı.');
    }

    // Maliyet listelerini al
    $stmt = $db->prepare("
        SELECT id, name, file_name, transport_mode_id
        FROM cost_lists
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute();
    $cost_lists = $stmt->fetchAll();

    // Transport modlarını al
    $stmt = $db->prepare("
        SELECT id, name
        FROM transport_modes
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute();
    $transport_modes = $stmt->fetchAll();

    // Geçerlilik kontrolü
    $is_expired = strtotime($quote['valid_until']) < time();

    // Dil ayarları
    $is_english = (isset($quote['language']) ? $quote['language'] : 'tr') === 'en';

    // Para birimi
    $currency = isset($quote['currency']) ? $quote['currency'] : 'TL';

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
            'select_option' => 'Seçiniz',
            'import' => 'İthalat',
            'export' => 'İhracat',
            // Admin panel çevirileri
            'admin_edit_mode' => 'Admin Düzenleme Modu',
            'back_return' => 'Geri Dön',
            'customer_view' => 'Müşteri Görünümü',
            'quote_no' => 'Teklif No',
            'unit_price' => 'Birim m3 fiyatı',
            'customs_fee' => 'Gümrük Hizmet Bedeli: +250 Euro (Türkiye ve Almanya gümrük işlemleri için bir defaya mahsus)',
            'customs_fee_en' => 'Customs Service Fee: +€250 (One-time fee for Turkey and Germany customs procedures)',
            'greeting_dear' => 'Sayın',
            'greeting_suffix' => '',
            'intro_text' => 'Europatrans Global Lojistik Taşımacılık olarak eşyalarınızın uluslararası taşımasını, aşağıda belirtilen şartlar dahilinde yapmayı teklif ve taahhüt ederiz.',
            'general_info' => 'Genel Bilgiler',
            'general_info_title' => 'TAŞIMAYA DAİR GENEL BİLGİLER',
            'transport_mode' => 'Taşıma Modu',
            'cargo_volume' => 'Eşyanın Hacmi',
            'loading_city' => 'Yükleme Şehri/Ülke',
            'delivery_city' => 'Teslimat Şehri/Ülke',
            'delivery_time' => 'Teslimat Tarihi',
            'delivery_time_text' => 'Yükleme sonrası ortalama 3-4 hafta',
            'contact_info' => 'İletişim Bilgisi',
            'included_services_title' => 'FİYAT DAHİL OLAN VE EUROPATRANS TARAFINDAN SAĞLANACAK HİZMETLERİMİZ:',
            'optional_services_title' => 'FİYAT HARİCİ OPSİYONEL/OLASI HİZMETLER ve ÜCRETLERİ:',
            'payment_method' => 'ÖDEME ŞEKLİ:',
            'payment_text' => 'Teklifin tutarının %20 si anlaşmaya varıldığında kaparo olarak, geri kalanı ise eşya yola çıktığında ödenecek. Kredi kartı ödemelerinde veya döviz havalelerinde oluşacak masraf veya komisyon bedeli eşya sahibine aittir.',
            'other_matters' => 'DİĞER HUSUSLAR:',
            'other_matters_text' => 'Yükleme ve teslimat eşyanın 1. kattan alınıp, 1. kata teslim edileceği şeklinde değerlendirilirse teklif verilmiştir. Taşımanın üst katlar olması, taşıma yapılacak yerlerde kullanılabilecek yük asansörü olmaması ve daha üst katlara merdivenler elde yapılması durumunda ilave ücret alınacaktır.',
            // Seçenek değerleri
            'select_option' => 'Seçiniz',
            'household_goods' => 'Ev Eşyası',
            'personal_effects' => 'Kişisel Eşya',
            'import' => 'İthalat',
            'export' => 'İhracat',
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
            'start_date' => 'Loading Date',
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
            'select_option' => 'Select',
            'import' => 'Import',
            'export' => 'Export',
            // Admin panel çevirileri
            'admin_edit_mode' => 'Admin Edit Mode',
            'back_return' => 'Back',
            'customer_view' => 'Customer View',
            'quote_no' => 'Quote No',
            'unit_price' => 'Unit m3 price',
            'customs_fee' => 'Gümrük Hizmet Bedeli: +250 Euro (Türkiye ve Almanya gümrük işlemleri için bir defaya mahsus)',
            'customs_fee_en' => 'Customs Service Fee: +€250 (One-time fee for Turkey and Germany customs procedures)',
            'greeting_dear' => 'Dear',
            'greeting_suffix' => '',
            'intro_text' => 'As Europatrans Global Logistics Transportation, we offer and undertake to carry out the international transportation of your goods under the conditions specified below.',
            'general_info' => 'General Information',
            'general_info_title' => 'GENERAL INFORMATION ABOUT TRANSPORTATION',
            'transport_mode' => 'Transport Mode',
            'cargo_volume' => 'Cargo Volume',
            'loading_city' => 'Loading City/Country',
            'delivery_city' => 'Delivery City/Country',
            'delivery_time' => 'Delivery Time',
            'delivery_time_text' => 'Average 3-4 weeks after loading',
            'contact_info' => 'Contact Information',
            'included_services_title' => 'SERVICES INCLUDED IN PRICE AND PROVIDED BY EUROPATRANS:',
            'optional_services_title' => 'OPTIONAL/POSSIBLE SERVICES AND FEES EXCLUDED FROM PRICE:',
            'payment_method' => 'PAYMENT METHOD:',
            'payment_text' => '20% of the quote amount will be paid as a deposit when an agreement is reached, and the remainder will be paid when the goods are shipped. Any costs or commission fees arising from credit card payments or foreign exchange transfers belong to the cargo owner.',
            'other_matters' => 'OTHER MATTERS:',
            'other_matters_text' => 'The quote is given considering that loading and delivery will be from the 1st floor to the 1st floor. If the transportation is to upper floors, there is no freight elevator available at the transportation locations, and manual carrying to upper floors is required, additional fees will be charged.',
            // Seçenek değerleri
            'select_option' => 'Select',
            'household_goods' => 'Household Goods',
            'personal_effects' => 'Personal Effects',
            'import' => 'Import',
            'export' => 'Export',
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

    // Para birimi formatı
    function formatPriceWithCurrency($price, $currency) {
        // Eğer price boş veya 0 ise, 0 göster
        if (empty($price) || $price == 0) {
            $formatted_price = '0';
        } else {
            // Ondalık kısmı varsa 2 haneli, yoksa tam sayı olarak göster
            $decimals = (floor($price) == $price) ? 0 : 2;
            $formatted_price = number_format($price, $decimals, ',', '.');
        }

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

    // Transport mode çeviri fonksiyonu
    function translateTransportMode($transport_name, $translations) {
        return isset($translations['transport_modes'][$transport_name]) ? $translations['transport_modes'][$transport_name] : $transport_name;
    }

} catch (Exception $e) {
    die('Veritabanı hatası: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?php echo $is_english ? 'en' : 'tr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teklif Düzenle - <?php echo htmlspecialchars($quote['quote_number']); ?></title>
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

        /* Admin Header */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 12px;
            position: relative;
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
            flex: 1;
        }

        .cost-amount {
            font-weight: 600;
            color: #2c5aa0;
            margin-right: 15px;
        }

        .cost-edit-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .cost-item:hover .cost-edit-actions {
            opacity: 1;
        }

        .editable-cost {
            transition: background-color 0.3s ease;
        }

        .editable-cost:hover {
            background-color: #e3f2fd !important;
        }

        .add-cost-form {
            background: #fff;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }

        .add-cost-form .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .add-cost-form .form-control,
        .add-cost-form .form-select {
            border: 2px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .add-cost-form .form-control:focus,
        .add-cost-form .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        .add-cost-form .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 6px;
            margin-right: 10px;
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

        /* Editable Styles */
        .editable {
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 3px;
            transition: all 0.2s ease;
            position: relative;
            display: inline-block;
        }

        .editable:hover {
            background: rgba(33, 150, 243, 0.1);
            box-shadow: 0 0 0 1px rgba(33, 150, 243, 0.3);
        }

        .editable.editing {
            background: #fff3cd;
            box-shadow: 0 0 0 2px #ffc107;
        }

        .editable input, .editable textarea, .editable select {
            width: 100%;
            border: none;
            background: transparent;
            font-size: inherit;
            font-family: inherit;
            color: inherit;
            padding: 2px;
            outline: none;
            resize: none;
        }

        .editable textarea {
            min-height: 40px;
            resize: vertical;
        }

        /* Rich Text Editor Styles */
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
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80%;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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

        .rich-editor-textarea ul, .rich-editor-textarea ol {
            margin: 10px 0;
            padding-left: 30px;
        }

        .rich-editor-textarea li {
            margin: 5px 0;
        }

        .rich-editor-textarea p {
            margin: 10px 0;
        }

        .rich-editor-textarea strong {
            font-weight: bold;
        }

        .rich-editor-textarea em {
            font-style: italic;
        }

        .rich-editor-textarea u {
            text-decoration: underline;
        }

        .edit-indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 6px;
            height: 6px;
            background: #2196f3;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .editable:hover .edit-indicator {
            opacity: 1;
        }

        .edit-controls {
            position: absolute;
            top: -25px;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 2px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .edit-controls button {
            border: none;
            background: none;
            padding: 2px 6px;
            margin: 0 1px;
            border-radius: 2px;
            font-size: 11px;
            cursor: pointer;
        }

        .edit-controls .btn-save {
            color: #28a745;
        }

        .edit-controls .btn-cancel {
            color: #dc3545;
        }

        .edit-controls button:hover {
            background: #f8f9fa;
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

            .edit-controls {
                position: fixed;
                top: 10px;
                right: 10px;
                left: 10px;
                width: auto;
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

            .admin-header {
                display: none !important;
            }

            .edit-indicator {
                display: none !important;
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
        }

        /* Remove Field Button */
        .btn-remove-field {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .form-row:hover .btn-remove-field {
            opacity: 1;
        }

        .btn-remove-field:hover {
            background: #c82333;
            transform: translateY(-50%) scale(1.1);
        }

        @media print {
            .btn-remove-field {
                display: none !important;
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
            </div>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="https://www.europatrans.com.tr/themes/europatrans/img/europatrans-logo.png"
                     alt="Europatrans Logo" class="logo">
            </div>
            <div class="contact-info">
                <div class="phone-number editable" data-field="phone_display" data-quote-id="<?php echo $quote['quote_number']; ?>">
                    <span>📞</span>444 6 995
                    <span class="edit-indicator"></span>
                </div>
                <div class="quote-number-header editable" data-field="quote_number_display" data-quote-id="<?php echo $quote['quote_number']; ?>">
                    <?php echo $t['quote_no']; ?> : <?php echo htmlspecialchars($quote['quote_number']); ?>
                    <span class="edit-indicator"></span>
                </div>
            </div>
        </div>

        <!-- Main Title -->
        <div class="main-title editable" data-field="main_title" data-quote-id="<?php echo $quote['quote_number']; ?>">
            <?php echo $t['quote_title']; ?>
            <span class="edit-indicator"></span>
        </div>

        <!-- Info Header - Moved here -->
        <div style="padding: 40px 50px; background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); text-align: left;">
            <div style="max-width: 800px;">
                <p class="editable" data-field="greeting_text" data-quote-id="<?php echo $quote['quote_number']; ?>" style="font-size: 16px; font-weight: 600; color: #2c5aa0; margin-bottom: 20px; line-height: 1.6;">
                    <strong><?php echo $t['greeting_dear']; ?> <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?> <?php echo $t['greeting_suffix']; ?></strong>
                    <span class="edit-indicator"></span>
                </p>
                <p class="editable" data-field="intro_text" data-quote-id="<?php echo $quote['quote_number']; ?>" style="font-size: 15px; color: #333; line-height: 1.7; margin: 0; font-weight: 400;">
                    <?php echo !empty($quote['intro_text']) ? $quote['intro_text'] : $t['intro_text']; ?>
                    <span class="edit-indicator"></span>
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
                        <div class="section-label editable" data-field="price_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo $t['price_info']; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="price-section">
                            <div class="price-label editable" data-field="price_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['our_quote_price']; ?>
                                <span class="edit-indicator"></span>
                            </div>
                            <div class="price-amount editable" data-field="final_price" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="number">
                                <?php echo formatPriceWithCurrency($quote['final_price'], $currency); ?>
                                <span class="edit-indicator"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Costs -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label">Ek Maliyetler</div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="additional-costs-list" id="additionalCostsList">
                            <!-- Additional costs will be loaded here dynamically -->
                        </div>

                        <!-- Add new cost form -->
                        <div class="add-cost-form" id="addCostForm" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Maliyet Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="costName" placeholder="Örn: Sigorta Bedeli, Nakliye Ücreti">
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Tutar <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="costAmount" placeholder="0.00" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Para Birimi</label>
                                    <select class="form-select" id="costCurrency">
                                        <option value="TL">TL</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Açıklama <span class="text-muted">(İsteğe bağlı)</span></label>
                                <textarea class="form-control" id="costDescription" rows="2" placeholder="Bu maliyet hakkında ek bilgi..."></textarea>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="saveCost()">
                                    <i class="fas fa-check"></i> Kaydet
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelAddCost()">
                                    <i class="fas fa-times"></i> İptal
                                </button>
                            </div>
                        </div>

                        <!-- Add cost button -->
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary" id="addCostBtn" onclick="showAddCostForm()">
                                <i class="fas fa-plus"></i> Ek Maliyet Ekle
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Customer Info -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="customer_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo $t['customer_info']; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="form-row">
                            <span class="form-label editable" data-field="name_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['name_surname']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="full_name" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <?php if (!empty($quote['company'])): ?>
                        <div class="form-row">
                            <span class="form-label editable" data-field="company_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['company']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="company" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo htmlspecialchars($quote['company']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="form-row">
                            <span class="form-label editable" data-field="email_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['email']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="email" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="email">
                                <?php echo htmlspecialchars($quote['email']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <div class="form-row">
                            <span class="form-label editable" data-field="phone_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['phone']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="phone" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo htmlspecialchars($quote['phone']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <div class="form-row">
                            <span class="form-label editable" data-field="quote_date_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['quote_date']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value"><?php echo formatDate($quote['created_at']); ?></span>
                        </div>
                        <div class="form-row">
                            <span class="form-label editable" data-field="validity_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['validity']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value"><?php echo formatDate($quote['valid_until']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Transport Info -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="transport_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo $t['transport_details']; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="form-row">
                            <span class="form-label editable" data-field="transport_type_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['transport_type']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                                                        <span class="form-value editable" data-field="custom_transport_name" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="text">
                                <?php
                                // Önce custom transport name'i kontrol et, yoksa varsayılan transport name'i kullan
                                $display_transport_name = !empty($quote['custom_transport_name'])
                                    ? $quote['custom_transport_name']
                                    : translateTransportMode($quote['transport_name'], $t);
                                echo htmlspecialchars($display_transport_name);
                                ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <div class="form-row">
                            <span class="form-label editable" data-field="origin_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['origin']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="origin" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo htmlspecialchars($quote['origin']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <div class="form-row">
                            <span class="form-label editable" data-field="destination_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $t['destination']; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="destination" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo htmlspecialchars($quote['destination']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <?php if (!empty($quote['start_date']) && $quote['start_date'] !== '0000-00-00' && $quote['start_date'] !== null): ?>
                        <div class="form-row">
                            <span class="form-label editable" data-field="start_date_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="start_date" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="text">
                                <?php echo formatDate($quote['start_date']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($quote['delivery_date']) && $quote['delivery_date'] !== '0000-00-00' && $quote['delivery_date'] !== null): ?>
                        <div class="form-row">
                            <span class="form-label editable" data-field="delivery_date_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="delivery_date" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="text">
                                <?php echo formatDate($quote['delivery_date']); ?>
                                <span class="edit-indicator"></span>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="form-row">
                            <span class="form-label editable" data-field="status_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value">
                                <span style="background: <?php echo $is_expired ? '#e74c3c' : '#27ae60'; ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px;">
                                    <?php echo $is_expired ? $t['expired'] : $t['active']; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cargo Info -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="cargo_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo [" PLACEHOLDER\]; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="form-row" data-field="weight">
                            <span class="form-label editable" data-field="weight_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="weight" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="number">
                                <?php echo number_format($quote['weight'], 0, ',', '.'); ?> kg
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('weight')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="volume" style="<?php echo empty($quote['volume']) ? 'display: none;' : ''; ?>">
                            <span class="form-label editable" data-field="volume_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="volume" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="number">
                                <?php echo !empty($quote['volume']) ? number_format($quote['volume'], 2, ',', '.') . ' m³' : ''; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('volume')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="unit_price" style="<?php echo [" PLACEHOLDER\]; ?>">
                            <span class="form-label editable" data-field="unit_price_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                Birim m³ Fiyat
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="unit_price" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="number">
                                <?php echo !empty($quote['unit_price']) ? number_format($quote['unit_price'], 2, ',', '.') . ' ' . $currency : ''; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('unit_price')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="pieces" style="<?php echo [" PLACEHOLDER\]; ?>">
                            <span class="form-label editable" data-field="pieces_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="pieces" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="number">
                                <?php echo !empty($quote['pieces']) ? number_format($quote['pieces'], 0, ',', '.') : ''; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('pieces')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="cargo_type" style="<?php echo [" PLACEHOLDER\]; ?>">
                            <span class="form-label editable" data-field="cargo_type_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="cargo_type" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="text">
                                <?php
                                if (!empty($quote['cargo_type'])) {
                                    // Eğer eski kod değerleri varsa, bunları çevir
                                    $cargo_types = [
                                        'kisisel_esya' => 'Kişisel Eşya',
                                        'ev_esyasi' => 'Ev Eşyası',
                                        'ticari_esya' => 'Ticari Eşya'
                                    ];
                                    echo htmlspecialchars($cargo_types[$quote['cargo_type']] ?? $quote['cargo_type']);
                                }
                                ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('cargo_type')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="trade_type" style="<?php echo [" PLACEHOLDER\]; ?>">
                            <span class="form-label editable" data-field="trade_type_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="trade_type" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="text">
                                <?php
                                if (!empty($quote['trade_type'])) {
                                    // Eğer eski kod değerleri varsa, bunları çevir
                                    $trade_types = [
                                        'ithalat' => $t['import'],
                                        'ihracat' => $t['export']
                                    ];
                                    echo htmlspecialchars($trade_types[$quote['trade_type']] ?? $quote['trade_type']);
                                }
                                ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('trade_type')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="form-row" data-field="description" style="<?php echo [" PLACEHOLDER\]; ?>">
                            <span class="form-label editable" data-field="description_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo [" PLACEHOLDER\]; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <span class="form-value editable" data-field="description" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                                <?php echo !empty($quote['description']) ? htmlspecialchars($quote['description']) : ''; ?>
                                <span class="edit-indicator"></span>
                            </span>
                            <button type="button" class="btn-remove-field" onclick="removeCargoField('description')" title="Bu alanı kaldır">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Information -->
            <div class="info-side">

                <!-- Transport Process -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="transport_process_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            Taşınma Süreci
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <h4 class="editable" data-field="transport_process_title" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            TAŞINMA SÜRECİ
                            <span class="edit-indicator"></span>
                        </h4>

                        <?php
                        // Taşıma moduna göre süreç metni belirle
                        $transport_process_text = '';
                        $transport_mode_lower = strtolower($quote['transport_name']);

                        // Dil kontrolü - URL'den lang parametresi al
                        $is_english = isset($_GET['lang']) && $_GET['lang'] === 'en';

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
                        ?>

                        <div class="transport-process-content">
                            <p class="editable" data-field="transport_process_text" data-quote-id="<?php echo $quote['quote_number']; ?>">
                                <?php echo $transport_process_text; ?>
                                <span class="edit-indicator"></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Services Section -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="services_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo $t['services']; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce quotes tablosundaki içeriği kontrol et, yoksa şablondan al
                        $services_content = isset($quote['services_content']) ? $quote['services_content'] : (isset($quote['template_services_content']) ? $quote['template_services_content'] : '');
                        if (!empty($services_content)):
                        ?>
                            <!-- Şablondan gelen hizmetler içeriği -->
                            <div class="editable" data-field="services_content" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                                <?php
                                // Şablon değişkenlerini değiştir
                                $services_content = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $services_content);
                                $services_content = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $services_content);
                                $services_content = str_replace('{origin}', htmlspecialchars($quote['origin']), $services_content);
                                $services_content = str_replace('{destination}', htmlspecialchars($quote['destination']), $services_content);
                                $services_content = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $services_content);
                                $services_content = str_replace('{volume}', number_format(isset($quote['volume']) ? $quote['volume'] : 0, 2, ',', '.'), $services_content);
                                $services_content = str_replace('{pieces}', isset($quote['pieces']) ? $quote['pieces'] : $t['not_specified'], $services_content);
                                $services_content = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $services_content);
                                $services_content = str_replace('{valid_until}', formatDate($quote['valid_until']), $services_content);
                                $services_content = str_replace('{cargo_type}', isset($quote['cargo_type']) ? $quote['cargo_type'] : 'Genel', $services_content);
                                $services_content = str_replace('{trade_type}', isset($quote['trade_type']) ? $quote['trade_type'] : '', $services_content);
                                $services_content = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $services_content);
                                $services_content = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $services_content);

                                echo $services_content;
                                ?>
                                <span class="edit-indicator"></span>
                            </div>
                        <?php endif; ?>

                        <!-- Maliyet Listesi Seçimi -->
                        <div class="mt-4">
                            <h5>Maliyet Listesi</h5>
                            <div class="mb-3">
                                <label class="form-label">Maliyet Listesi Seç:</label>
                                <select class="form-select" id="costListSelect" onchange="updateCostList()">
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
                            <div class="cost-list-preview">
                                <strong>Seçili Maliyet Listesi:</strong>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($quote['cost_list_name']); ?></span>
                                <?php if (!empty($quote['cost_list_file_path']) && file_exists($quote['cost_list_file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($quote['cost_list_file_path']); ?>"
                                       target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fas fa-download"></i> Dosyayı İndir
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        // Önce quotes tablosundaki içeriği kontrol et, yoksa şablondan al
                        $optional_services_content = isset($quote['optional_services_content']) ? $quote['optional_services_content'] : '';
                        if (!empty($optional_services_content)):
                        ?>

                            <!-- Şablondan gelen opsiyonel hizmetler içeriği -->
                            <div class="editable" data-field="optional_services_content" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                                <?php
                                // Şablon değişkenlerini değiştir
                                $optional_services_content = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $optional_services_content);
                                $optional_services_content = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $optional_services_content);
                                $optional_services_content = str_replace('{origin}', htmlspecialchars($quote['origin']), $optional_services_content);
                                $optional_services_content = str_replace('{destination}', htmlspecialchars($quote['destination']), $optional_services_content);
                                $optional_services_content = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $optional_services_content);
                                $optional_services_content = str_replace('{volume}', number_format(isset($quote['volume']) ? $quote['volume'] : 0, 2, ',', '.'), $optional_services_content);
                                $optional_services_content = str_replace('{pieces}', isset($quote['pieces']) ? $quote['pieces'] : $t['not_specified'], $optional_services_content);
                                $optional_services_content = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $optional_services_content);
                                $optional_services_content = str_replace('{valid_until}', formatDate($quote['valid_until']), $optional_services_content);
                                $optional_services_content = str_replace('{cargo_type}', isset($quote['cargo_type']) ? $quote['cargo_type'] : 'Genel', $optional_services_content);
                                $optional_services_content = str_replace('{trade_type}', isset($quote['trade_type']) ? $quote['trade_type'] : '', $optional_services_content);
                                $optional_services_content = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $optional_services_content);
                                $optional_services_content = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $optional_services_content);

                                echo $optional_services_content;
                                ?>
                                <span class="edit-indicator"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Terms Section -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="terms_section_label" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo $t['terms']; ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <?php
                        // Önce quotes tablosundaki içeriği kontrol et, yoksa şablondan al
                        $terms_content = isset($quote['terms_content']) ? $quote['terms_content'] : (isset($quote['template_terms_content']) ? $quote['template_terms_content'] : '');
                        if (!empty($terms_content)):
                        ?>
                            <!-- Şablondan gelen şartlar içeriği -->
                            <div class="editable" data-field="terms_content" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                                <?php
                                // Şablon değişkenlerini değiştir
                                $terms_content = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $terms_content);
                                $terms_content = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $terms_content);
                                $terms_content = str_replace('{origin}', htmlspecialchars($quote['origin']), $terms_content);
                                $terms_content = str_replace('{destination}', htmlspecialchars($quote['destination']), $terms_content);
                                $terms_content = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $terms_content);
                                $terms_content = str_replace('{volume}', number_format(isset($quote['volume']) ? $quote['volume'] : 0, 2, ',', '.'), $terms_content);
                                $terms_content = str_replace('{pieces}', isset($quote['pieces']) ? $quote['pieces'] : $t['not_specified'], $terms_content);
                                $terms_content = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $terms_content);
                                $terms_content = str_replace('{valid_until}', formatDate($quote['valid_until']), $terms_content);
                                $terms_content = str_replace('{cargo_type}', isset($quote['cargo_type']) ? $quote['cargo_type'] : 'Genel', $terms_content);
                                $terms_content = str_replace('{trade_type}', isset($quote['trade_type']) ? $quote['trade_type'] : '', $terms_content);
                                $terms_content = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $terms_content);
                                $terms_content = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $terms_content);

                                echo $terms_content;
                                ?>
                                <span class="edit-indicator"></span>
                            </div>
                                                <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Section 1 -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="additional_section1_title" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo htmlspecialchars(isset($quote['additional_section1_title']) ? $quote['additional_section1_title'] : 'Ek Bölüm 1'); ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="editable" data-field="additional_section1_content" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                            <?php
                            $additional_content1 = isset($quote['additional_section1_content']) ? $quote['additional_section1_content'] : '';
                            if (!empty($additional_content1)):
                                // Şablon değişkenlerini değiştir
                                $additional_content1 = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $additional_content1);
                                $additional_content1 = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $additional_content1);
                                $additional_content1 = str_replace('{origin}', htmlspecialchars($quote['origin']), $additional_content1);
                                $additional_content1 = str_replace('{destination}', htmlspecialchars($quote['destination']), $additional_content1);
                                $additional_content1 = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $additional_content1);
                                $additional_content1 = str_replace('{volume}', number_format(isset($quote['volume']) ? $quote['volume'] : 0, 2, ',', '.'), $additional_content1);
                                $additional_content1 = str_replace('{pieces}', isset($quote['pieces']) ? $quote['pieces'] : $t['not_specified'], $additional_content1);
                                $additional_content1 = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $additional_content1);
                                $additional_content1 = str_replace('{valid_until}', formatDate($quote['valid_until']), $additional_content1);
                                $additional_content1 = str_replace('{cargo_type}', isset($quote['cargo_type']) ? $quote['cargo_type'] : 'Genel', $additional_content1);
                                $additional_content1 = str_replace('{trade_type}', isset($quote['trade_type']) ? $quote['trade_type'] : '', $additional_content1);
                                $additional_content1 = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $additional_content1);
                                $additional_content1 = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $additional_content1);

                                echo $additional_content1;
                            else:
                                echo '<p>Bu bölüm için içerik eklemek üzere tıklayın...</p>';
                            endif;
                            ?>
                            <span class="edit-indicator"></span>
                        </div>
                    </div>
                </div>

                <!-- Additional Section 2 -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-label editable" data-field="additional_section2_title" data-quote-id="<?php echo $quote['quote_number']; ?>">
                            <?php echo htmlspecialchars(isset($quote['additional_section2_title']) ? $quote['additional_section2_title'] : 'Ek Bölüm 2'); ?>
                            <span class="edit-indicator"></span>
                        </div>
                        <div class="section-title"></div>
                    </div>
                    <div class="form-content">
                        <div class="editable" data-field="additional_section2_content" data-quote-id="<?php echo $quote['quote_number']; ?>" data-type="textarea">
                            <?php
                            $additional_content2 = isset($quote['additional_section2_content']) ? $quote['additional_section2_content'] : '';
                            if (!empty($additional_content2)):
                                // Şablon değişkenlerini değiştir
                                $additional_content2 = str_replace('{customer_name}', htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']), $additional_content2);
                                $additional_content2 = str_replace('{quote_number}', htmlspecialchars($quote['quote_number']), $additional_content2);
                                $additional_content2 = str_replace('{origin}', htmlspecialchars($quote['origin']), $additional_content2);
                                $additional_content2 = str_replace('{destination}', htmlspecialchars($quote['destination']), $additional_content2);
                                $additional_content2 = str_replace('{weight}', number_format($quote['weight'], 0, ',', '.'), $additional_content2);
                                $additional_content2 = str_replace('{volume}', number_format(isset($quote['volume']) ? $quote['volume'] : 0, 2, ',', '.'), $additional_content2);
                                $additional_content2 = str_replace('{pieces}', isset($quote['pieces']) ? $quote['pieces'] : $t['not_specified'], $additional_content2);
                                $additional_content2 = str_replace('{price}', formatPriceWithCurrency($quote['final_price'], $currency), $additional_content2);
                                $additional_content2 = str_replace('{valid_until}', formatDate($quote['valid_until']), $additional_content2);
                                $additional_content2 = str_replace('{cargo_type}', isset($quote['cargo_type']) ? $quote['cargo_type'] : 'Genel', $additional_content2);
                                $additional_content2 = str_replace('{trade_type}', isset($quote['trade_type']) ? $quote['trade_type'] : '', $additional_content2);
                                $additional_content2 = str_replace('{start_date}', $quote['start_date'] ? formatDate($quote['start_date']) : $t['not_specified'], $additional_content2);
                                $additional_content2 = str_replace('{delivery_date}', $quote['delivery_date'] ? formatDate($quote['delivery_date']) : $t['not_specified'], $additional_content2);

                                echo $additional_content2;
                            else:
                                echo '<p>Bu bölüm için içerik eklemek üzere tıklayın...</p>';
                            endif;
                            ?>
                            <span class="edit-indicator"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script>
        // Global variables
        const quoteId = <?php echo json_encode($quote['id']); ?>;
        const quoteNumber = <?php echo json_encode($quote['quote_number']); ?>;

        function editCost(costId) {
            const costItem = document.querySelector(`[data-cost-id="${costId}"]`);
            const name = costItem.querySelector('.cost-description').textContent;
            const noteElement = costItem.querySelector('.cost-note');
            const description = noteElement ? noteElement.textContent : '';
            const amountText = costItem.querySelector('.cost-amount').textContent;

            // Extract amount and currency from text
            let amount, currency;
            if (amountText.startsWith('$')) {
                amount = parseFloat(amountText.substring(1).replace(/[.,]/g, ''));
                currency = 'USD';
            } else if (amountText.startsWith('€')) {
                amount = parseFloat(amountText.substring(1).replace(/[.,]/g, ''));
                currency = 'EUR';
            } else {
                amount = parseFloat(amountText.replace(' TL', '').replace(/[.,]/g, ''));
                currency = 'TL';
            }

            // Fill form with current values
            document.getElementById('costName').value = name;
            document.getElementById('costDescription').value = description;
            document.getElementById('costAmount').value = amount;
            document.getElementById('costCurrency').value = currency;

            // Show form
            showAddCostForm();

            // Change save button to update
            const saveBtn = document.querySelector('#addCostForm .btn-success');
            saveBtn.setAttribute('onclick', `updateCost(${costId})`);
            saveBtn.innerHTML = '<i class="fas fa-check"></i> Güncelle';
        }

        function updateCost(costId) {
            const name = document.getElementById('costName').value.trim();
            const description = document.getElementById('costDescription').value.trim();
            const amount = parseFloat(document.getElementById('costAmount').value);
            const currency = document.getElementById('costCurrency').value;

            if (!name || !amount || amount <= 0) {
                alert('Lütfen maliyet adı ve tutarı doğru şekilde doldurunuz!');
                return;
            }

            // Show loading
            const saveBtn = document.querySelector('#addCostForm .btn-success');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Güncelleniyor...';
            saveBtn.disabled = true;

            fetch('../api/update-additional-cost.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cost_id: costId,
                    name: name,
                    description: description,
                    amount: amount,
                    currency: currency
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update in list
                    const costItem = document.querySelector(`[data-cost-id="${costId}"]`);
                    costItem.querySelector('.cost-description').textContent = name;
                    costItem.querySelector('.cost-amount').textContent = formatPrice(amount, currency);

                    // Update or add description note
                    let noteElement = costItem.querySelector('.cost-note');
                    if (description && description.trim()) {
                        if (!noteElement) {
                            noteElement = document.createElement('div');
                            noteElement.className = 'cost-note';
                            noteElement.style.cssText = 'font-size: 11px; color: #666; font-style: italic; margin-top: 4px;';
                            costItem.querySelector('.cost-content').appendChild(noteElement);
                        }
                        noteElement.textContent = description;
                    } else if (noteElement) {
                        noteElement.remove();
                    }

                    cancelAddCost();
                    // Restore save button
                    const saveBtn = document.querySelector('#addCostForm .btn-success');
                    saveBtn.setAttribute('onclick', 'saveCost()');
                    saveBtn.innerHTML = '<i class="fas fa-check"></i> Kaydet';

                    showAlert('Maliyet başarıyla güncellendi!', 'success');
                } else {
                    alert(data.message || 'Maliyet güncellenirken hata oluştu!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası!');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        function deleteCost(costId) {
            if (!confirm('Bu maliyeti silmek istediğinizden emin misiniz?')) {
                return;
            }

            fetch('../api/delete-additional-cost.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cost_id: costId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const costItem = document.querySelector(`[data-cost-id="${costId}"]`);
                    costItem.remove();
                    showAlert('Maliyet başarıyla silindi!', 'success');
                } else {
                    alert(data.message || 'Maliyet silinirken hata oluştu!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası!');
            });
        }

        function editDefaultCost(type) {
            if (type === 'unit_price') {
                // Unit price editing with modal like customs fee
                const currentElement = document.querySelector('[data-field="unit_price"]');
                const currentAmount = currentElement ? currentElement.textContent.replace(/[^\d.,]/g, '').replace(',', '.') : '0';
                const currentCurrency = currentElement ? currentElement.getAttribute('data-currency') || 'TL' : 'TL';

                const newAmount = prompt('Birim m³ fiyatını girin:', currentAmount);
                if (newAmount && !isNaN(newAmount) && parseFloat(newAmount) >= 0) {
                    // Update the display
                    const unitPriceElement = document.querySelector('[data-default="unit_price"] .cost-amount');
                    if (unitPriceElement) {
                        const formattedPrice = formatPrice(parseFloat(newAmount), currentCurrency);
                        unitPriceElement.textContent = formattedPrice;
                        unitPriceElement.setAttribute('data-amount', newAmount);
                    }

                    // Update database via AJAX
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('action', 'update_field');
                    formData.append('field', 'unit_price');
                    formData.append('value', newAmount);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Birim m³ fiyatı güncellendi!', 'success');
                            // Also update other unit_price displays on the page
                            const otherUnitPriceElements = document.querySelectorAll('[data-field="unit_price"]:not([data-default])');
                            otherUnitPriceElements.forEach(el => {
                                const formatted = new Intl.NumberFormat('tr-TR', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 2
                                }).format(parseFloat(newAmount));
                                el.textContent = formatted + ' ' + currentCurrency;
                            });
                        } else {
                            showAlert('Güncelleme hatası: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Bağlantı hatası!', 'error');
                    });
                }
            } else if (type === 'customs_fee') {
                // Custom modal for customs fee editing
                const currentAmount = 250;
                const currentCurrency = 'EUR';
                const currentDescription = 'Gümrük Hizmet Bedeli';

                const newAmount = prompt('Gümrük hizmet bedeli tutarını girin:', currentAmount);
                if (newAmount && !isNaN(newAmount) && parseFloat(newAmount) > 0) {
                    // Update the display
                    const customsElement = document.querySelector('[data-default="customs_fee"] .cost-amount');
                    if (customsElement) {
                        customsElement.textContent = '+' + parseFloat(newAmount) + ' Euro';
                        customsElement.setAttribute('data-amount', newAmount);
                    }
                    showAlert('Gümrük hizmet bedeli güncellendi!', 'success');
                }
            }
        }

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

        function showAlert(message, type = 'info') {
            const alert = document.createElement('div');
            let alertClass = 'alert-info';
            if (type === 'success') {
                alertClass = 'alert-success';
            } else if (type === 'error') {
                alertClass = 'alert-danger';
            }

            alert.className = `alert ${alertClass} alert-dismissible fade show`;
            alert.style.position = 'fixed';
            alert.style.top = '80px';
            alert.style.right = '20px';
            alert.style.zIndex = '9999';
            alert.style.minWidth = '300px';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alert);

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Additional Cost Functions
        function showAddCostForm() {
            document.getElementById('addCostForm').style.display = 'block';
            document.getElementById('addCostBtn').style.display = 'none';
        }

                function cancelAddCost() {
            document.getElementById('addCostForm').style.display = 'none';
            document.getElementById('addCostBtn').style.display = 'block';

            // Reset form
            document.getElementById('costName').value = '';
            document.getElementById('costAmount').value = '';
            document.getElementById('costCurrency').value = 'TL';
            document.getElementById('costDescription').value = '';
        }

        function saveCost() {
            const name = document.getElementById('costName').value.trim();
            const amount = parseFloat(document.getElementById('costAmount').value);
            const currency = document.getElementById('costCurrency').value;
            const description = document.getElementById('costDescription').value.trim();

            if (!name || !amount || amount <= 0) {
                alert('Lütfen maliyet adı ve tutarı doğru şekilde doldurunuz!');
                return;
            }

            // Show loading
            const saveBtn = document.querySelector('#addCostForm .btn-success');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            saveBtn.disabled = true;

            fetch('../api/save-additional-cost.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quote_id: quoteId,
                    name: name,
                    description: description,
                    amount: amount,
                    currency: currency
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add to list
                    addCostToList(data.cost_id, name, description, amount, currency);
                    cancelAddCost();
                    showAlert('Ek maliyet başarıyla eklendi!', 'success');
                } else {
                    alert(data.message || 'Ek maliyet eklenirken hata oluştu!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası!');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }

        function addCostToList(costId, name, description, amount, currency) {
            const costsList = document.getElementById('additionalCostsList');
            const costItem = document.createElement('div');
            costItem.className = 'cost-item editable-cost';
            costItem.setAttribute('data-cost-id', costId);

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
                <div class="cost-edit-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editCost(${costId})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCost(${costId})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            costsList.appendChild(costItem);
        }

        // Load existing additional costs on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAdditionalCosts();
        });

        function loadAdditionalCosts() {
            fetch(`../api/get-additional-costs.php?quote_id=${quoteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.costs && data.costs.length > 0) {
                    data.costs.forEach(cost => {
                        addCostToList(cost.id, cost.name || cost.description, cost.description, cost.amount, cost.currency);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading costs:', error);
            });
        }

        // Inline Editing Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editables = document.querySelectorAll('.editable');

            editables.forEach(editable => {
                editable.addEventListener('click', function() {
                    if (this.classList.contains('editing')) return;

                    startEditing(this);
                });
            });
        });

        function startEditing(element) {
            const originalValue = element.textContent.trim();
            const field = element.dataset.field;
            const quoteId = element.dataset.quoteId;
            const type = element.dataset.type || 'text';

            // Rich text editor için özel alanlar
            if (field === 'intro_text' || field === 'greeting_text' || field === 'services_content' || field === 'optional_services_content' || field === 'terms_content' || field === 'transport_process_text' || field === 'additional_section1_title' || field === 'additional_section1_content' || field === 'additional_section2_title' || field === 'additional_section2_content') {
                openRichEditor(element, field, quoteId);
                return;
            }

            element.classList.add('editing');

            let input;

            if (type === 'select') {
                input = document.createElement('select');
                input.className = 'form-select';
                const options = JSON.parse(element.dataset.options || '{}');

                for (const [value, text] of Object.entries(options)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = text;
                    if (text === originalValue) {
                        option.selected = true;
                    }
                    input.appendChild(option);
                }
            } else if (type === 'textarea') {
                input = document.createElement('textarea');
                input.className = 'form-control';
                input.value = originalValue === 'Açıklama ekle...' || originalValue === 'Not ekle...' || originalValue === 'Add description...' || originalValue === 'Add note...' ? '' : originalValue;
            } else {
                input = document.createElement('input');
                input.className = 'form-control';
                input.type = type;

                if (type === 'number') {
                    // Sayısal değerlerden formatı temizle
                    const numericValue = originalValue.replace(/[^\d.,]/g, '').replace(',', '.');
                    input.value = numericValue;
                    input.step = field === 'volume' || field === 'final_price' ? '0.01' : '1';
                } else {
                    input.value = originalValue;
                }
            }

            // Kontrol butonları
            const controls = document.createElement('div');
            controls.className = 'edit-controls';
            controls.innerHTML = `
                <button type="button" class="btn-save" title="<?php echo [" PLACEHOLDER\]; ?>">
                    <i class="fas fa-check"></i>
                </button>
                <button type="button" class="btn-cancel" title="<?php echo [" PLACEHOLDER\]; ?>">
                    <i class="fas fa-times"></i>
                </button>
            `;

            element.innerHTML = '';
            element.appendChild(input);
            element.appendChild(controls);

            input.focus();
            if (input.select) input.select();

            // Event listeners
            const saveBtn = controls.querySelector('.btn-save');
            const cancelBtn = controls.querySelector('.btn-cancel');

            saveBtn.addEventListener('click', () => saveEdit(element, input, originalValue, field, quoteId));
            cancelBtn.addEventListener('click', () => cancelEdit(element, originalValue));

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    saveEdit(element, input, originalValue, field, quoteId);
                } else if (e.key === 'Escape') {
                    cancelEdit(element, originalValue);
                }
            });

            input.addEventListener('blur', function(e) {
                // Blur olayını kontrol butonlarına tıklanırsa geciktir
                setTimeout(() => {
                    if (!element.contains(document.activeElement)) {
                        saveEdit(element, input, originalValue, field, quoteId);
                    }
                }, 100);
            });
        }

        function saveEdit(element, input, originalValue, field, quoteId) {
            const newValue = input.value.trim();

            if (newValue === originalValue || (input.type === 'number' && parseFloat(newValue) === parseFloat(originalValue.replace(/[^\d.,]/g, '').replace(',', '.')))) {
                cancelEdit(element, originalValue);
                return;
            }

            // Loading durumu
            element.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';

            // AJAX isteği
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_field');
            formData.append('field', field);
            formData.append('value', newValue);

            fetch(`view-quote.php?id=${quoteId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı güncelleme
                    let displayValue = newValue;

                    // Formatla
                    if (input.type === 'number') {
                        if (field === 'weight' || field === 'pieces') {
                            displayValue = new Intl.NumberFormat('tr-TR').format(parseInt(newValue));
                            if (field === 'weight') displayValue += ' kg';
                        } else if (field === 'volume') {
                            displayValue = new Intl.NumberFormat('tr-TR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(parseFloat(newValue)) + ' m³';
                        } else if (field === 'final_price') {
                            displayValue = new Intl.NumberFormat('tr-TR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(parseFloat(newValue));
                        }
                    } else if (input.tagName === 'SELECT') {
                        const options = JSON.parse(element.dataset.options || '{}');
                        displayValue = options[newValue] || newValue;
                    }

                    element.innerHTML = displayValue + '<span class="edit-indicator"></span>';
                    element.classList.remove('editing');

                    // Başarı animasyonu
                    element.style.background = '#d4edda';
                    setTimeout(() => {
                        element.style.background = '';
                    }, 1000);

                    // Özel alanlar için sayfayı yenile
                    if (field === 'full_name' || field === 'first_name' || field === 'last_name') {
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }

                } else {
                    throw new Error(data.message || 'Güncelleme başarısız');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hata: ' + error.message);
                cancelEdit(element, originalValue);
            });
        }

        function cancelEdit(element, originalValue) {
            element.innerHTML = originalValue + '<span class="edit-indicator"></span>';
            element.classList.remove('editing');
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
            .then(response => response.json())
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
                alert('Bağlantı hatası!');
                select.disabled = false;
            });
        }

        // Alert gösterme fonksiyonu
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // Sayfanın üstüne ekle
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);

            // 3 saniye sonra otomatik kapat
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    </script>

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
                        </div>
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertUnorderedList')" title="Madde İşareti">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertOrderedList')" title="Numaralı Liste">
                                <i class="fas fa-list-ol"></i>
                            </button>
                        </div>
                        <div class="btn-group" role="group">
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
                    </div>
                </div>
                <div contenteditable="true" class="rich-editor-textarea" id="richEditorContent"></div>
            </div>
            <div class="rich-editor-footer">
                <button type="button" class="btn btn-secondary me-2" onclick="closeRichEditor()">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveRichContent()">Kaydet</button>
            </div>
        </div>
    </div>

    <script>
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
            const title = document.getElementById('richEditorTitle');
            const content = document.getElementById('richEditorContent');

            // Set title based on field
            if (field === 'intro_text') {
                title.textContent = 'Giriş Metni Düzenle';
            } else if (field === 'greeting_text') {
                title.textContent = 'Selamlama Metni Düzenle';
            } else if (field === 'services_content') {
                title.textContent = 'Hizmetlerimiz İçeriği Düzenle';
            } else if (field === 'optional_services_content') {
                title.textContent = 'Opsiyonel Hizmetler İçeriği Düzenle';
            } else if (field === 'terms_content') {
                title.textContent = 'Şartlar İçeriği Düzenle';
            } else if (field === 'transport_process_text') {
                title.textContent = 'Taşınma Süreci İçeriği Düzenle';
            } else if (field === 'additional_section1_title') {
                title.textContent = 'Ek Bölüm 1 Başlığı Düzenle';
            } else if (field === 'additional_section1_content') {
                title.textContent = 'Ek Bölüm 1 İçeriği Düzenle';
            } else if (field === 'additional_section2_title') {
                title.textContent = 'Ek Bölüm 2 Başlığı Düzenle';
            } else if (field === 'additional_section2_content') {
                title.textContent = 'Ek Bölüm 2 İçeriği Düzenle';
            } else {
                title.textContent = 'İçerik Düzenle';
            }

            // Get current content (remove edit indicator)
            const currentContent = element.innerHTML.replace('<span class="edit-indicator"></span>', '');
            content.innerHTML = currentContent;

            // Show modal
            modal.style.display = 'flex';
            content.focus();
        }

        function closeRichEditor() {
            const modal = document.getElementById('richEditorModal');
            modal.style.display = 'none';
            currentRichField = null;
            currentRichElement = null;
            currentQuoteId = null;
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

        // Modal dışına tıklayınca kapatma
        document.getElementById('richEditorModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRichEditor();
            }
        });

        function formatText(command) {
            document.execCommand(command, false, null);
            document.getElementById('richEditorContent').focus();
        }

        function saveRichContent() {
            const content = document.getElementById('richEditorContent').innerHTML;

            if (!currentRichField || !currentRichElement || !currentQuoteId) {
                alert('Hata: Geçersiz düzenleme durumu');
                return;
            }

            // Show loading
            currentRichElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';

            // AJAX request
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'update_field');
            formData.append('field', currentRichField);
            formData.append('value', content);

            fetch(`view-quote.php?id=${currentQuoteId}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update element content
                    currentRichElement.innerHTML = content + '<span class="edit-indicator"></span>';

                    // Success animation
                    currentRichElement.style.background = '#d4edda';
                    setTimeout(() => {
                        currentRichElement.style.background = '';
                    }, 1000);

                    closeRichEditor();
                } else {
                    throw new Error(data.message || 'Güncelleme başarısız');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hata: ' + error.message);
                // Restore original content
                const originalContent = document.getElementById('richEditorContent').innerHTML;
                currentRichElement.innerHTML = originalContent + '<span class="edit-indicator"></span>';
                closeRichEditor();
            });
        }

        // Remove cargo field functionality
        function removeCargoField(fieldName) {
            if (confirm('Bu alanı gizlemek istediğinizden emin misiniz?')) {
                const fieldRow = document.querySelector(`[data-field="${fieldName}"]`);
                if (fieldRow) {
                    fieldRow.style.display = 'none';

                    // Show success message
                    showAlert('Alan gizlendi', 'success');
                }
            }
        }
    </script>
</body>
</html>
