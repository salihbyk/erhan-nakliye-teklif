<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
checkAdminSession();

// generateQuoteNumber fonksiyonu
function generateQuoteNumber($db = null, $tradeType = 'ithalat', $isCopy = false) {
    global $database;

    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }

    // Trade type'ı Türkçe'ye çevir
    $tradeTypeSuffix = '';
    switch(strtolower($tradeType)) {
        case 'ithalat':
        case 'import':
            $tradeTypeSuffix = 'ithalat';
            break;
        case 'ihracat':
        case 'export':
            $tradeTypeSuffix = 'ihracat';
            break;
        default:
            $tradeTypeSuffix = 'ithalat';
    }

        // Bu yıl için son teklif numarasını al (aynı trade type için)
    // Rev'li ve normal teklifleri ayrı ayrı kontrol et
    $year = date('y'); // Son iki hane (25)

        // Önce normal teklifleri kontrol et (rev olmayan)
    $search_pattern = $year . '%-' . $tradeTypeSuffix . '%';
    $stmt = $db->prepare("
        SELECT quote_number
        FROM quotes
        WHERE quote_number LIKE ? AND quote_number NOT LIKE '%rev%'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$search_pattern]);
    $last_normal_quote = $stmt->fetch();

    // Sonra rev'li teklifleri kontrol et
    $stmt = $db->prepare("
        SELECT quote_number
        FROM quotes
        WHERE quote_number LIKE ? AND quote_number LIKE '%rev%'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$search_pattern]);
    $last_rev_quote = $stmt->fetch();

    error_log("=== QUOTE NUMBER GENERATION DEBUG ===");
    error_log("Trade type suffix: " . $tradeTypeSuffix);
    error_log("Is copy: " . ($isCopy ? 'yes' : 'no'));
    error_log("Search pattern: " . $search_pattern);
    error_log("Last normal quote found: " . ($last_normal_quote ? $last_normal_quote['quote_number'] : 'none'));
    error_log("Last rev quote found: " . ($last_rev_quote ? $last_rev_quote['quote_number'] : 'none'));

    // En yüksek numarayı bul
    $last_number = 0;

    if ($last_normal_quote) {
        $parts = explode('-', $last_normal_quote['quote_number']);
        if (count($parts) >= 2) {
            $last_number = max($last_number, intval($parts[1]));
        }
    }

    if ($last_rev_quote) {
        $parts = explode('-', $last_rev_quote['quote_number']);
        if (count($parts) >= 2) {
            $last_number = max($last_number, intval($parts[1]));
        }
    }

    if ($last_number > 0) {
        $new_number = $last_number + 1;
    } else {
        $new_number = 1111; // İlk teklif numarası
    }

        error_log("Calculated new number: " . $new_number);

    $base_number = $year . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT) . '-' . $tradeTypeSuffix;

    // Eğer kopyalama işlemiyse "rev" ekle
    if ($isCopy) {
        $final_number = $base_number . '-rev';
        error_log("Final quote number (copy): " . $final_number);
        return $final_number;
    }

    error_log("Final quote number (normal): " . $base_number);
    return $base_number;
}

// Eğer ID parametresi varsa view-quote.php'ye yönlendir
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $quote_id = (int)$_GET['id'];
    header("Location: view-quote.php?id=$quote_id");
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // PDO error modunu ayarla
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Form işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        error_log("POST request received with action: " . $action);
        error_log("POST data: " . print_r($_POST, true));

        if ($action === 'update_price') {
            $quote_id = $_POST['quote_id'] ?? 0;
            $final_price = $_POST['final_price'] ?? 0;
            $notes = $_POST['notes'] ?? '';

            try {
                $stmt = $db->prepare("
                    UPDATE quotes
                    SET final_price = ?, notes = ?, status = 'priced', updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([$final_price, $notes, $quote_id])) {
                    setSuccessMessage('Fiyat başarıyla güncellendi.');
                } else {
                    setErrorMessage('Fiyat güncellenirken hata oluştu.');
                }
            } catch (Exception $e) {
                setErrorMessage('Hata: ' . $e->getMessage());
            }

            header('Location: quotes.php');
            exit;
        }

        if ($action === 'copy_quote') {
            $quote_id = $_POST['quote_id'] ?? 0;
            error_log("=== COPY QUOTE ACTION STARTED ===");
            error_log("Copy quote action called with quote_id: " . $quote_id);
            error_log("Current time: " . date('Y-m-d H:i:s'));

            try {
                // Kopyalanacak teklifi getir
                $stmt = $db->prepare("
                    SELECT * FROM quotes WHERE id = ?
                ");
                $stmt->execute([$quote_id]);
                $original_quote = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$original_quote) {
                    setErrorMessage('Kopyalanacak teklif bulunamadı.');
                    header('Location: quotes.php');
                    exit;
                }

                // Veritabanındaki mevcut teklifleri listele (debug için)
                $debug_stmt = $db->prepare("SELECT quote_number, created_at FROM quotes ORDER BY created_at DESC LIMIT 10");
                $debug_stmt->execute();
                $existing_quotes = $debug_stmt->fetchAll();
                error_log("Current quotes in database:");
                foreach ($existing_quotes as $eq) {
                    error_log("  - " . $eq['quote_number'] . " (created: " . $eq['created_at'] . ")");
                }

                // Yeni teklif numarası oluştur (kopyalama olduğunu belirt)
                $new_quote_number = generateQuoteNumber($db, $original_quote['trade_type'] ?? 'ithalat', true);

                                                                // Teklifi kopyala - TÜM eksik alanları da dahil et
                $stmt = $db->prepare("
                    INSERT INTO quotes (
                        quote_number, customer_id, transport_mode_id, selected_template_id,
                        container_type, custom_transport_name, origin, destination,
                        weight, volume, unit_price, pieces, cargo_type, trade_type, description,
                        pickup_date, delivery_date, start_date, valid_until, notes,
                        services_content, optional_services_content, terms_content,
                        additional_section1_title, additional_section1_content,
                        additional_section2_title, additional_section2_content,
                        transport_process_text, intro_text, show_reference_images,
                        status, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                    )
                ");

                try {
                    $result = $stmt->execute([
                        $new_quote_number,
                        $original_quote['customer_id'],
                        $original_quote['transport_mode_id'],
                        $original_quote['selected_template_id'],
                        $original_quote['container_type'],
                        $original_quote['custom_transport_name'],
                        $original_quote['origin'],
                        $original_quote['destination'],
                        $original_quote['weight'],
                        $original_quote['volume'],
                        $original_quote['unit_price'],
                        $original_quote['pieces'],
                        $original_quote['cargo_type'],
                        $original_quote['trade_type'],
                        $original_quote['description'],
                        $original_quote['pickup_date'],
                        $original_quote['delivery_date'],
                        $original_quote['start_date'],
                        $original_quote['valid_until'],
                        $original_quote['notes'],
                        $original_quote['services_content'],
                        $original_quote['optional_services_content'],
                        $original_quote['terms_content'],
                        $original_quote['additional_section1_title'],
                        $original_quote['additional_section1_content'],
                        $original_quote['additional_section2_title'],
                        $original_quote['additional_section2_content'],
                        $original_quote['transport_process_text'],
                        $original_quote['intro_text'],
                        0 // Yeni tekliflerde referans görseller varsayılan olarak kapalı
                    ]);
                } catch (PDOException $e) {
                    error_log("SQL INSERT ERROR: " . $e->getMessage());
                    error_log("Quote number attempted: " . $new_quote_number);
                    throw $e;
                }

                if ($result) {
                    $new_quote_id = $db->lastInsertId();
                    error_log("SUCCESS: New quote created with ID: " . $new_quote_id);
                    error_log("SUCCESS: Quote number: " . $new_quote_number);

                    // Verify the quote was actually inserted
                    $verify_stmt = $db->prepare("SELECT quote_number FROM quotes WHERE id = ?");
                    $verify_stmt->execute([$new_quote_id]);
                    $verified_quote = $verify_stmt->fetch();
                    error_log("VERIFICATION: Quote in DB: " . ($verified_quote ? $verified_quote['quote_number'] : 'NOT FOUND'));

                    // Ek maliyetleri de kopyala
                    $stmt = $db->prepare("
                        SELECT * FROM additional_costs WHERE quote_id = ?
                    ");
                    $stmt->execute([$quote_id]);
                    $additional_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    error_log("Found " . count($additional_costs) . " additional costs to copy");

                    foreach ($additional_costs as $cost) {
                        $stmt = $db->prepare("
                            INSERT INTO additional_costs (quote_id, description, amount, currency)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $new_quote_id,
                            $cost['description'],
                            $cost['amount'],
                            $cost['currency']
                        ]);
                    }

                    error_log("Quote copy successful: " . $new_quote_number);
                    error_log("Redirecting to quotes.php with success message");
                    setSuccessMessage("Teklif başarıyla kopyalandı. Yeni teklif numarası: #$new_quote_number");

                    // Teklifler listesine geri dön (yeni teklif orada görünecek)
                    header("Location: quotes.php");
                    exit;
                } else {
                    error_log("Quote copy failed: SQL insert returned false");
                    setErrorMessage('Teklif kopyalanırken hata oluştu.');
                }
            } catch (Exception $e) {
                error_log("Copy quote exception: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                setErrorMessage('Hata: ' . $e->getMessage());
            }

            header('Location: quotes.php');
            exit;
        }

                if ($action === 'copy_quote_with_options') {
            $original_quote_id = $_POST['original_quote_id'] ?? 0;
            $new_transport_mode_id = $_POST['transport_mode_id'] ?? 0;
            $new_template_id = $_POST['template_id'] ?? 0;
            $copy_data = isset($_POST['copy_data']) ? true : false;

            error_log("=== COPY QUOTE WITH OPTIONS ACTION STARTED ===");
            error_log("Original quote ID: " . $original_quote_id);
            error_log("New transport mode ID: " . $new_transport_mode_id);
            error_log("New template ID: " . $new_template_id);
            error_log("Copy data: " . ($copy_data ? 'true' : 'false'));
            error_log("Current time: " . date('Y-m-d H:i:s'));

            try {
                // Orijinal teklifi getir
                $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ?");
                $stmt->execute([$original_quote_id]);
                $original_quote = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$original_quote) {
                    setErrorMessage('Kopyalanacak teklif bulunamadı.');
                    header('Location: quotes.php');
                    exit;
                }

                // Seçilen template'ten trade type'ı al
                $selected_trade_type = 'ithalat'; // Default
                if ($new_template_id) {
                    $template_stmt = $db->prepare("SELECT trade_type FROM quote_templates WHERE id = ?");
                    $template_stmt->execute([$new_template_id]);
                    $template_data = $template_stmt->fetch();
                    if ($template_data && $template_data['trade_type']) {
                        $selected_trade_type = $template_data['trade_type'];
                    }
                }

                // Veritabanındaki mevcut teklifleri listele (debug için)
                $debug_stmt = $db->prepare("SELECT quote_number, created_at FROM quotes ORDER BY created_at DESC LIMIT 10");
                $debug_stmt->execute();
                $existing_quotes = $debug_stmt->fetchAll();
                error_log("Current quotes in database (with options):");
                foreach ($existing_quotes as $eq) {
                    error_log("  - " . $eq['quote_number'] . " (created: " . $eq['created_at'] . ")");
                }

                // Yeni teklif numarası oluştur (seçilen template'in trade type'ına göre, kopyalama olduğunu belirt)
                $new_quote_number = generateQuoteNumber($db, $selected_trade_type, true);

                // Kopyalama verileri
                if ($copy_data) {
                    // Tüm verileri kopyala ama yeni taşıma modu ve şablon kullan
                    $stmt = $db->prepare("
                        INSERT INTO quotes (
                            quote_number, customer_id, transport_mode_id, selected_template_id,
                            container_type, custom_transport_name, origin, destination,
                            weight, volume, unit_price, pieces, cargo_type, trade_type, description,
                            pickup_date, delivery_date, start_date, valid_until, notes,
                            services_content, optional_services_content, terms_content,
                            additional_section1_title, additional_section1_content,
                            additional_section2_title, additional_section2_content,
                            transport_process_text, intro_text, show_reference_images,
                            status, created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                        )
                    ");

                    $result = $stmt->execute([
                        $new_quote_number,
                        $original_quote['customer_id'],
                        $new_transport_mode_id,
                        $new_template_id,
                        $original_quote['container_type'],
                        $original_quote['custom_transport_name'],
                        $original_quote['origin'],
                        $original_quote['destination'],
                        $original_quote['weight'],
                        $original_quote['volume'],
                        $original_quote['unit_price'],
                        $original_quote['pieces'],
                        $original_quote['cargo_type'],
                        $selected_trade_type, // Seçilen template'in trade type'ını kullan
                        $original_quote['description'],
                        $original_quote['pickup_date'],
                        $original_quote['delivery_date'],
                        $original_quote['start_date'],
                        $original_quote['valid_until'],
                        $original_quote['notes'],
                        $original_quote['services_content'],
                        $original_quote['optional_services_content'],
                        $original_quote['terms_content'],
                        $original_quote['additional_section1_title'],
                        $original_quote['additional_section1_content'],
                        $original_quote['additional_section2_title'],
                        $original_quote['additional_section2_content'],
                        $original_quote['transport_process_text'],
                        $original_quote['intro_text'],
                        0 // Yeni tekliflerde referans görseller varsayılan olarak kapalı
                    ]);
                } else {
                    // Sadece temel bilgileri kopyala
                    $stmt = $db->prepare("
                        INSERT INTO quotes (
                            quote_number, customer_id, transport_mode_id, selected_template_id,
                            trade_type, status, created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, 'pending', NOW()
                        )
                    ");

                    $result = $stmt->execute([
                        $new_quote_number,
                        $original_quote['customer_id'],
                        $new_transport_mode_id,
                        $new_template_id,
                        $selected_trade_type // Seçilen template'in trade type'ını kullan
                    ]);
                }

                if ($result) {
                    $new_quote_id = $db->lastInsertId();
                    error_log("SUCCESS: New quote with options created with ID: " . $new_quote_id);
                    error_log("SUCCESS: Quote number: " . $new_quote_number);

                    // Verify the quote was actually inserted
                    $verify_stmt = $db->prepare("SELECT quote_number FROM quotes WHERE id = ?");
                    $verify_stmt->execute([$new_quote_id]);
                    $verified_quote = $verify_stmt->fetch();
                    error_log("VERIFICATION: Quote in DB: " . ($verified_quote ? $verified_quote['quote_number'] : 'NOT FOUND'));

                    // Ek maliyetleri de kopyala (eğer veri kopyalama seçilmişse)
                    if ($copy_data) {
                        $stmt = $db->prepare("SELECT * FROM additional_costs WHERE quote_id = ?");
                        $stmt->execute([$original_quote_id]);
                        $additional_costs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        error_log("Found " . count($additional_costs) . " additional costs to copy (with options)");

                        foreach ($additional_costs as $cost) {
                            $stmt = $db->prepare("
                                INSERT INTO additional_costs (quote_id, description, amount, currency)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $new_quote_id,
                                $cost['description'],
                                $cost['amount'],
                                $cost['currency']
                            ]);
                        }
                    }

                    error_log("Quote copy with options successful: " . $new_quote_number);
                    error_log("Redirecting to quotes.php with success message");
                    setSuccessMessage("Teklif başarıyla kopyalandı. Yeni teklif numarası: #$new_quote_number");
                } else {
                    error_log("Quote copy with options failed: SQL insert returned false");
                    setErrorMessage('Teklif kopyalanırken hata oluştu.');
                }
            } catch (Exception $e) {
                error_log("Copy quote with options exception: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                setErrorMessage('Hata: ' . $e->getMessage());
            }

            header('Location: quotes.php');
            exit;
        }

        if ($action === 'delete_quote') {
            $quote_id = $_POST['quote_id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM quotes WHERE id = ?");
            if ($stmt->execute([$quote_id])) {
                setSuccessMessage('Teklif silindi.');
            } else {
                setErrorMessage('Teklif silinirken hata oluştu.');
            }
            header('Location: quotes.php');
            exit;
        }
    }

    // Sayfalama
    $page = $_GET['page'] ?? 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Filtreleme
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    $where_conditions = [];
    $params = [];

    if (!empty($status_filter)) {
        $where_conditions[] = "q.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($search)) {
        $where_conditions[] = "(q.quote_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Toplam kayıt sayısı
    $count_sql = "
        SELECT COUNT(*) as total
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        $where_clause
        AND q.is_active = 1
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];

    // Teklifler
    $sql = "
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_name, tm.icon as transport_icon,
               qt.currency as template_currency, qt.language as template_language
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        $where_clause
        AND q.is_active = 1
        ORDER BY q.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll();

    $pagination = calculatePagination($total_records, $per_page, $page);

    // Taşıma modlarını al (kopyalama modal'ı için - Konteyner hariç)
    $stmt = $db->query("SELECT id, name FROM transport_modes WHERE is_active = 1 AND LOWER(name) != 'konteyner' ORDER BY name ASC");
    $transport_modes = $stmt->fetchAll();

} catch (Exception $e) {
    setErrorMessage('Veri yüklenirken hata oluştu: ' . $e->getMessage());
    $quotes = [];
    $transport_modes = [];
    $pagination = ['total_pages' => 1, 'current_page' => 1];
}

$messages = getMessages();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teklifler - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Modern sidebar included via external CSS */

        .main-content {
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .quotes-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
            font-size: 14px;
        }

        .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 15px 12px;
            vertical-align: middle;
        }

        .table td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f4;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .quote-number {
            font-weight: 600;
            color: #007bff;
            text-decoration: none;
        }

        .quote-number:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .customer-info {
            line-height: 1.4;
        }

        .customer-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .customer-details {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .customer-details i {
            width: 12px;
            margin-right: 5px;
            color: #007bff;
        }

        .customer-details div {
            margin-bottom: 2px;
        }

        .route-info {
            font-size: 13px;
            line-height: 1.4;
            min-width: 150px;
        }

        .route-info .fw-bold {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .route-info i.fa-map-marker-alt {
            margin-right: 5px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-priced {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-sent {
            background: #d4edda;
            color: #155724;
        }

        .price-form {
            display: block;
            width: 100%;
        }

        .price-form .input-group {
            margin-bottom: 8px;
        }

        .price-form .input-group-text {
            background: #f8f9fa;
            border-color: #dee2e6;
            color: #495057;
            font-weight: 600;
            font-size: 12px;
            min-width: 35px;
            justify-content: center;
        }

        .price-form .price-input {
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            border-left: 0;
            border-right: 0;
            background: #fff;
        }

        .price-form .price-input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            background: #f8f9ff;
        }

        .price-form .price-save-btn {
            border-left: 0;
            background: #28a745;
            border-color: #28a745;
            color: white;
            font-weight: 600;
            transition: all 0.2s ease;
            min-width: 40px;
        }

        .price-form .price-save-btn:hover {
            background: #218838;
            border-color: #1e7e34;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .price-form .notes-input {
            font-size: 11px;
            color: #6c757d;
            border-color: #e9ecef;
        }

        .price-form .notes-input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.15);
        }

        .price-form .notes-input::placeholder {
            color: #adb5bd;
            font-style: italic;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-buttons .btn {
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-copy {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #dee2e6;
            color: #495057;
            transition: all 0.3s ease;
        }

        .btn-copy:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #adb5bd;
            color: #212529;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-copy i {
            margin-right: 4px;
        }

        .transport-icon {
            margin-right: 5px;
            color: #6c757d;
        }

        .date-info {
            font-size: 12px;
            color: #6c757d;
        }

        .template-info {
            font-size: 12px;
            line-height: 1.3;
        }

        .template-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .template-details {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        .template-details .badge {
            font-size: 10px;
            padding: 2px 6px;
        }

        /* Şablon dropdown'da trade type renkleri */
        .template-option {
            padding: 8px 12px;
        }

        .trade-type-import {
            color: #28a745 !important;
            font-weight: 600;
        }

        .trade-type-export {
            color: #0d6efd !important;
            font-weight: 600;
        }

        .trade-type-other {
            color: #6c757d !important;
            font-weight: 500;
        }

        /* Select option styling için custom dropdown */
        .custom-template-dropdown {
            position: relative;
        }

        .template-dropdown-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .template-dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .template-dropdown-item.selected {
            background-color: #e3f2fd;
        }

        .template-import {
            border-left: 4px solid #28a745;
        }

        .template-export {
            border-left: 4px solid #0d6efd;
        }

        .trade-type-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        .trade-type-badge.import {
            background-color: #d4edda;
            color: #155724;
        }

        .trade-type-badge.export {
            background-color: #cce7ff;
            color: #084298;
        }
    </style>
</head>
<body>
    <div class="container-fluid">


        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-file-invoice text-primary me-2"></i>
                                Teklif Yönetimi
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Tüm nakliye tekliflerini görüntüleyin, düzenleyin ve fiyatlandırın
                            </p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge bg-primary fs-6">
                                <i class="fas fa-list me-1"></i>
                                Toplam: <?php echo $total_records; ?>
                            </span>
                            <a href="../index.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus me-1"></i>
                                Yeni Teklif
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $type => $message): ?>
                        <div class="alert alert-<?php echo $type === 'error' ? 'danger' : $type; ?> alert-dismissible fade show">
                            <i class="fas fa-<?php echo $type === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Durum Filtresi</label>
                            <select name="status" class="form-select">
                                <option value="">Tüm Durumlar</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="priced" <?php echo $status_filter === 'priced' ? 'selected' : ''; ?>>Fiyatlandırıldı</option>
                                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Gönderildi</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Arama</label>
                            <input type="text" name="search" class="form-control"
                                   placeholder="Teklif no, müşteri adı veya e-posta..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Quotes Table -->
                <div class="quotes-table">
                    <?php if (empty($quotes)): ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-inbox fa-4x text-muted"></i>
                            </div>
                            <h4 class="text-muted mb-3">Henüz teklif bulunmuyor</h4>
                            <p class="text-muted mb-4">
                                Yeni teklifler geldiğinde burada görünecek.<br>
                                Hemen yeni bir teklif oluşturmak için aşağıdaki butona tıklayın.
                            </p>
                            <a href="../index.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>
                                İlk Teklifi Oluştur
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-1"></i>Teklif No</th>
                                        <th><i class="fas fa-user me-1"></i>Müşteri</th>
                                        <th><i class="fas fa-truck me-1"></i>Taşıma</th>
                                        <th><i class="fas fa-route me-1"></i>Güzergah</th>
                                        <th><i class="fas fa-template me-1"></i>Şablon</th>
                                        <th><i class="fas fa-euro-sign me-1"></i>Fiyat & Durum</th>
                                        <th><i class="fas fa-cogs me-1"></i>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotes as $quote): ?>
                                        <tr>
                                            <!-- Teklif No -->
                                            <td>
                                                <a href="../view-quote.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                                                   target="_blank" class="quote-number">
                                                    #<?php echo htmlspecialchars($quote['quote_number']); ?>
                                                </a>
                                                <div class="date-info">
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo formatDate($quote['created_at']); ?>
                                                </div>
                                            </td>

                                            <!-- Müşteri -->
                                            <td>
                                                <div class="customer-info">
                                                    <div class="customer-name">
                                                        <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>
                                                    </div>
                                                    <div class="customer-details">
                                                        <?php if (!empty($quote['company'])): ?>
                                                            <div><i class="fas fa-building"></i> <?php echo htmlspecialchars($quote['company']); ?></div>
                                                        <?php endif; ?>
                                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($quote['email']); ?></div>
                                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($quote['phone']); ?></div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Taşıma -->
                                            <td>
                                                <i class="<?php echo htmlspecialchars($quote['transport_icon']); ?> transport-icon"></i>
                                                <?php echo htmlspecialchars($quote['transport_name']); ?>
                                            </td>

                                            <!-- Güzergah -->
                                            <td>
                                                <div class="route-info">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold text-primary">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                                <?php echo htmlspecialchars($quote['origin']); ?>
                                                            </div>
                                                            <div class="text-center my-1">
                                                                <i class="fas fa-arrow-down text-muted"></i>
                                                            </div>
                                                            <div class="fw-bold text-success">
                                                                <i class="fas fa-map-marker-alt"></i>
                                                                <?php echo htmlspecialchars($quote['destination']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Şablon -->
                                            <td>
                                                <?php if (!empty($quote['selected_template_id'])): ?>
                                                    <div class="template-info">
                                                        <div class="template-name">
                                                            <?php
                                                            // Template ID'ye göre basit isim oluştur
                                                            $template_name = 'Şablon #' . $quote['selected_template_id'];
                                                            if (!empty($quote['template_language'])) {
                                                                $template_name .= ' (' . ($quote['template_language'] === 'tr' ? 'TR' : 'EN') . ')';
                                                            }
                                                            echo htmlspecialchars($template_name);
                                                            ?>
                                                        </div>
                                                        <div class="template-details">
                                                            <span class="badge bg-info">
                                                                <?php echo $quote['template_language'] === 'tr' ? 'Türkçe' : 'English'; ?>
                                                            </span>
                                                            <span class="badge bg-secondary">
                                                                <?php echo $quote['template_currency'] ?? 'EUR'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="fas fa-minus"></i> Şablon seçilmemiş
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Fiyat -->
                                            <td>
                                                <form method="POST" action="quotes.php" class="price-form">
                                                    <input type="hidden" name="action" value="update_price">
                                                    <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                                    <?php
                                                    $currency = $quote['template_currency'] ?? 'EUR';
                                                    $currency_symbol = $currency === 'TL' ? '₺' : ($currency === 'USD' ? '$' : '€');
                                                    $current_price = !empty($quote['final_price']) ? $quote['final_price'] : '';
                                                    ?>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text"><?php echo $currency_symbol; ?></span>
                                                        <input type="number" name="final_price"
                                                               value="<?php echo $current_price; ?>"
                                                               placeholder="0.00"
                                                               step="0.01" required min="0.01"
                                                               class="form-control price-input"
                                                               title="Fiyat (<?php echo $currency; ?>)">
                                                        <button type="submit" class="btn btn-success btn-sm price-save-btn"
                                                                title="Fiyatı Kaydet">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </div>
                                                    <input type="text" name="notes"
                                                           value="<?php echo htmlspecialchars($quote['notes'] ?? ''); ?>"
                                                           placeholder="Not ekle..."
                                                           class="form-control form-control-sm mt-1 notes-input"
                                                           title="İsteğe bağlı notlar">

                                                    <?php if ($quote['status'] !== 'pending'): ?>
                                                        <div class="mt-1">
                                                            <span class="status-badge status-<?php echo $quote['status']; ?>">
                                                                <?php
                                                                $status_labels = [
                                                                    'pending' => 'Beklemede',
                                                                    'priced' => 'Fiyatlandırıldı',
                                                                    'sent' => 'Gönderildi'
                                                                ];
                                                                echo $status_labels[$quote['status']] ?? $quote['status'];
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            </td>

                                            <!-- İşlemler -->
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="view-quote.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                                                       class="btn btn-primary btn-sm" title="Detaylı Düzenle">
                                                        <i class="fas fa-edit"></i> Düzenle
                                                    </a>

                                                    <!-- Kopyala Butonu -->
                                                    <button type="button" class="btn btn-copy btn-sm"
                                                            onclick="showCopyModal(<?php echo $quote['id']; ?>, '<?php echo htmlspecialchars($quote['quote_number']); ?>', <?php echo $quote['transport_mode_id']; ?>, <?php echo $quote['selected_template_id'] ?? 'null'; ?>)"
                                                            title="Teklifi Kopyala">
                                                        <i class="fas fa-copy"></i> Kopyala
                                                    </button>

                                                    <?php if (!empty($quote['final_price']) && $quote['final_price'] > 0): ?>
                                                        <!-- Email Önizleme Butonu -->
                                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                                onclick="previewEmail(<?php echo $quote['id']; ?>)"
                                                                title="Email Önizleme">
                                                            <i class="fas fa-eye"></i> Önizleme
                                                        </button>

                                                        <?php if (empty($quote['email_sent_at'])): ?>
                                                            <!-- Email Gönder Butonu -->
                                                            <button type="button" class="btn btn-success btn-sm"
                                                                    onclick="sendQuoteEmail(<?php echo $quote['id']; ?>)"
                                                                    title="Müşteri ve Firmaya Email Gönder">
                                                                <i class="fas fa-envelope"></i> Mail Gönder
                                                            </button>
                                                        <?php else: ?>

                                                            <!-- Email Tekrar Gönder -->
                                                            <button type="button" class="btn btn-info btn-sm"
                                                                    onclick="sendQuoteEmail(<?php echo $quote['id']; ?>)"
                                                                    title="Email Tekrar Gönder">
                                                                <i class="fas fa-paper-plane"></i> Tekrar Gönder
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <form method="POST" style="display: inline;"
                                                          onsubmit="return confirm('Bu teklifi silmek istediğinizden emin misiniz?')">
                                                        <input type="hidden" name="action" value="delete_quote">
                                                        <input type="hidden" name="quote_id" value="<?php echo $quote['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Sil">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav aria-label="Sayfa navigasyonu">
                        <ul class="pagination justify-content-center">
                            <?php if ($pagination['current_page'] > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fiyat formu validation
        document.addEventListener('DOMContentLoaded', function() {
            const priceForms = document.querySelectorAll('.price-form');

            priceForms.forEach(form => {
                const priceInput = form.querySelector('input[name="final_price"]');
                const saveBtn = form.querySelector('.price-save-btn');
                const notesInput = form.querySelector('input[name="notes"]');

                // Gerçek zamanlı validation
                priceInput.addEventListener('input', function() {
                    const value = parseFloat(this.value);
                    const isValid = !isNaN(value) && value > 0;

                    saveBtn.disabled = !isValid;
                    saveBtn.style.opacity = isValid ? '1' : '0.5';

                    if (isValid) {
                        this.style.borderColor = '#28a745';
                        this.style.background = '#f8fff8';
                    } else {
                        this.style.borderColor = '#dc3545';
                        this.style.background = '#fff8f8';
                    }
                });

                // Form submit validation
                form.addEventListener('submit', function(e) {
                    const priceValue = priceInput.value;

                    if (!priceValue || parseFloat(priceValue) <= 0) {
                        e.preventDefault();
                        alert('Lütfen geçerli bir fiyat girin!');
                        priceInput.focus();
                        return false;
                    }

                    // Loading durumu
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    saveBtn.disabled = true;
                });

                // Enter tuşu ile kaydetme
                priceInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });

                // Initial validation
                priceInput.dispatchEvent(new Event('input'));
            });
        });

        // Email önizleme fonksiyonu
        function previewEmail(quoteId) {
            const btn = event.target;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
            btn.disabled = true;

            fetch('../api/preview-quote-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quote_id: quoteId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Yeni pencerede email önizlemesini göster
                    const previewWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes');
                    previewWindow.document.write(data.html);
                    previewWindow.document.close();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası oluştu: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }

        // Email gönderme fonksiyonu
        function sendQuoteEmail(quoteId) {
            if (!confirm('Bu teklifi hem müşteriye hem de firmaya (info@europatrans.com.tr) email ile göndermek istediğinizden emin misiniz?')) {
                return;
            }

            const btn = event.target;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            btn.disabled = true;

            fetch('../api/send-quote-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    quote_id: quoteId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('Email başarıyla gönderildi!');
                        location.reload(); // Sayfayı yenile
                    } else {
                        alert('Hata: ' + data.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    alert('Sunucu yanıt hatası: ' + text.substring(0, 200));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası oluştu: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }



        // Kopyalama modal fonksiyonları
        let currentCopyData = {};

        function showCopyModal(quoteId, quoteNumber, currentTransportModeId, currentTemplateId) {
            currentCopyData = {
                quoteId: quoteId,
                quoteNumber: quoteNumber,
                currentTransportModeId: currentTransportModeId,
                currentTemplateId: currentTemplateId
            };

            document.getElementById('originalQuoteNumber').textContent = quoteNumber;
            document.getElementById('copyTransportMode').value = currentTransportModeId;

            // Şablonları yükle
            loadTemplatesForCopy(currentTransportModeId, currentTemplateId);

            // Modal'ı göster
            new bootstrap.Modal(document.getElementById('copyQuoteModal')).show();
        }

        function loadTemplatesForCopy(transportModeId, currentTemplateId) {
            const templateSelect = document.getElementById('copyTemplate');
            templateSelect.innerHTML = '<option value="">Yükleniyor...</option>';

            if (!transportModeId) {
                templateSelect.innerHTML = '<option value="">Önce taşıma modu seçin</option>';
                return;
            }

            fetch(`../api/get-templates.php?transport_mode_id=${transportModeId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Template response:', data); // Debug için
                    if (data.success && data.templates && data.templates.length > 0) {
                        templateSelect.innerHTML = '<option value="">Şablon Seçiniz</option>';
                                                data.templates.forEach(template => {
                            const option = document.createElement('option');
                            option.value = template.id;

                            // Trade type Türkçe çevirisi ve renk belirleme
                            let tradeTypeText = '';
                            let tradeTypeClass = '';
                            let tradeTypeSymbol = '';

                            switch(template.trade_type) {
                                case 'ithalat':
                                case 'import':
                                    tradeTypeText = 'İthalat';
                                    tradeTypeClass = 'import';
                                    tradeTypeSymbol = '🟢'; // Yeşil emoji
                                    break;
                                case 'ihracat':
                                case 'export':
                                    tradeTypeText = 'İhracat';
                                    tradeTypeClass = 'export';
                                    tradeTypeSymbol = '🔵'; // Mavi emoji
                                    break;
                                default:
                                    tradeTypeText = template.trade_type || '';
                                    tradeTypeClass = 'other';
                                    tradeTypeSymbol = '⚪'; // Beyaz emoji
                            }

                            // Option text'ine emoji ve renk bilgisi ekle
                            option.textContent = `${tradeTypeSymbol} ${template.template_name} (${template.currency}) - ${tradeTypeText}`;
                            option.setAttribute('data-trade-type', tradeTypeClass);

                            // CSS class ekle
                            if (tradeTypeClass === 'import') {
                                option.style.backgroundColor = '#d4edda';
                                option.style.color = '#155724';
                            } else if (tradeTypeClass === 'export') {
                                option.style.backgroundColor = '#cce7ff';
                                option.style.color = '#084298';
                            }

                            if (template.id == currentTemplateId) {
                                option.selected = true;
                            }
                            templateSelect.appendChild(option);
                        });
                    } else {
                        templateSelect.innerHTML = '<option value="">Bu taşıma modu için şablon bulunamadı</option>';
                        console.log('No templates found for transport mode:', transportModeId);
                    }
                })
                .catch(error => {
                    console.error('Template yükleme hatası:', error);
                    templateSelect.innerHTML = '<option value="">Şablon yüklenirken hata oluştu</option>';
                });
        }

        // Taşıma modu değiştiğinde şablonları güncelle
        document.addEventListener('DOMContentLoaded', function() {
            const transportSelect = document.getElementById('copyTransportMode');
            if (transportSelect) {
                transportSelect.addEventListener('change', function() {
                    loadTemplatesForCopy(this.value, null);
                });
            }
        });

        function processCopyQuote() {
            console.log('processCopyQuote called with data:', currentCopyData);

            const form = document.getElementById('copyQuoteForm');
            const formData = new FormData(form);
            formData.append('action', 'copy_quote_with_options');
            formData.append('original_quote_id', currentCopyData.quoteId);

            // Debug: Form verilerini kontrol et
            for (let [key, value] of formData.entries()) {
                console.log(key, value);
            }

            console.log('Sending request to copy quote with options...');

            fetch('quotes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text.substring(0, 200));

                // Yanıt HTML içeriyorsa sayfayı yenile
                if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                    console.log('HTML response detected, reloading...');
                    window.location.reload();
                } else {
                    // JSON yanıt bekleniyorsa
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            console.log('Copy with options successful, reloading...');
                            window.location.reload();
                        } else {
                            alert('Hata: ' + data.message);
                        }
                    } catch (e) {
                        console.log('JSON parse failed, checking for success message in HTML...');
                        if (text.includes('başarıyla kopyalandı') || text.includes('Yeni teklif numarası')) {
                            console.log('Success message found in response, reloading...');
                            window.location.reload();
                        } else {
                            console.error('No success indicators found. Response:', text.substring(0, 500));
                            alert('Kopyalama işlemi tamamlandı ancak sonuç belirsiz. Sayfayı kontrol edin.');
                            window.location.reload();
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Copy with options error:', error);
                alert('Bağlantı hatası oluştu: ' + error.message);
            });

            // Modal'ı kapat
            bootstrap.Modal.getInstance(document.getElementById('copyQuoteModal')).hide();
        }

        // Değişiklik yapmadan kopyala (eski sistem)
        function copyWithoutChanges() {
            console.log('copyWithoutChanges called with data:', currentCopyData);

            const formData = new FormData();
            formData.append('action', 'copy_quote');
            formData.append('quote_id', currentCopyData.quoteId);

            console.log('Sending request to copy quote without changes...');

            fetch('quotes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text.substring(0, 200));

                // Yanıt HTML içeriyorsa sayfayı yenile
                if (text.includes('<!DOCTYPE') || text.includes('<html')) {
                    console.log('HTML response detected, reloading...');
                    window.location.reload();
                } else {
                    // JSON yanıt bekleniyorsa
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            console.log('Copy successful, reloading...');
                            window.location.reload();
                        } else {
                            alert('Hata: ' + data.message);
                        }
                    } catch (e) {
                        console.log('JSON parse failed, checking for success message in HTML...');
                        if (text.includes('başarıyla kopyalandı') || text.includes('Yeni teklif numarası')) {
                            console.log('Success message found in response, reloading...');
                            window.location.reload();
                        } else {
                            console.error('No success indicators found. Response:', text.substring(0, 500));
                            alert('Kopyalama işlemi tamamlandı ancak sonuç belirsiz. Sayfayı kontrol edin.');
                            window.location.reload();
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Copy error:', error);
                alert('Bağlantı hatası oluştu: ' + error.message);
            });

            // Modal'ı kapat
            bootstrap.Modal.getInstance(document.getElementById('copyQuoteModal')).hide();
        }
    </script>

    <!-- Kopyalama Modal -->
    <div class="modal fade" id="copyQuoteModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-copy me-2"></i>Teklif Kopyala
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Kopyalanacak Teklif:</strong> #<span id="originalQuoteNumber"></span>
                    </div>

                    <form id="copyQuoteForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-truck me-1"></i>Taşıma Modu
                                </label>
                                <select name="transport_mode_id" id="copyTransportMode" class="form-select" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($transport_modes as $mode): ?>
                                    <option value="<?= $mode['id'] ?>"><?= htmlspecialchars($mode['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Yeni teklif için taşıma modu seçin</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-file-alt me-1"></i>Şablon
                                </label>
                                <select name="template_id" id="copyTemplate" class="form-select" required>
                                    <option value="">Önce taşıma modu seçin</option>
                                </select>
                                <div class="form-text">Yeni teklif için şablon seçin</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="copy_data" id="copyData" checked>
                                    <label class="form-check-label" for="copyData">
                                        <strong>Tüm verileri kopyala</strong>
                                        <small class="text-muted d-block">Müşteri bilgileri, yük detayları, adresler ve notları kopyala</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>İptal
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="copyWithoutChanges()">
                        <i class="fas fa-arrow-right me-1"></i>Değişiklik Yapmadan İlerle
                    </button>
                    <button type="button" class="btn btn-primary" onclick="processCopyQuote()">
                        <i class="fas fa-copy me-1"></i>Seçeneklerle Kopyala
                    </button>
                </div>
            </div>
        </div>

    <script src="includes/sidebar.js"></script>
</body>
</html>