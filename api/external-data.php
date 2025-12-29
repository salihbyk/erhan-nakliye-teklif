<?php
/**
 * Dış Sistemler İçin Veri Okuma API'si
 * 
 * Bu API, müşterileri, teklifleri, onay durumlarını ve ödeme durumlarını 
 * JSON formatında döndürür.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// React uygulaması için özel action desteği
$action = $_GET['action'] ?? '';

if ($action === 'transport_modes' || $action === 'customers') {
    // React için direkt veri döndür (API key kontrolü yok)
    require_once '../config/database.php';
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($action === 'transport_modes') {
            $stmt = $db->query("
                SELECT id, name, slug, icon, is_active
                FROM transport_modes
                WHERE is_active = 1
                ORDER BY name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($data);
        } elseif ($action === 'customers') {
            $stmt = $db->query("
                SELECT id, first_name, last_name, email, phone, company
                FROM customers
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($data);
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Normal API key kontrolü
$api_key = "europatrans_secret_key_123"; 

// getallheaders() her ortamda olmayabilir (örn: PHP-FPM/Nginx bazen farklı davranabilir)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$provided_key = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $_GET['api_key'] ?? '';

if ($provided_key !== $api_key) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Yetkisiz erişim. Lütfen geçerli bir X-API-KEY başlığı veya api_key parametresi sağlayın.'
    ]);
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Filtreleme seçenekleri (isteğe bağlı)
    $status = $_GET['status'] ?? null;
    $payment_status = $_GET['payment_status'] ?? null;

    $sql = "
        SELECT 
            q.id as quote_id,
            q.quote_number,
            q.origin,
            q.destination,
            q.status as approval_status,
            q.payment_status,
            q.payment_amount,
            q.final_price,
            q.currency,
            q.delivery_status,
            q.created_at as quote_date,
            c.id as customer_id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.company,
            tm.name as transport_mode
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
        WHERE 1=1
    ";

    $params = [];

    if ($status) {
        $sql .= " AND q.status = ?";
        $params[] = $status;
    }

    if ($payment_status) {
        $sql .= " AND q.payment_status = ?";
        $params[] = $payment_status;
    }

    $sql .= " ORDER BY q.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll();

    // Her teklif için ödeme detaylarını da ekleyebiliriz (isteğe bağlı)
    $include_payments = isset($_GET['include_payments']) && $_GET['include_payments'] == '1';
    
    if ($include_payments && !empty($quotes)) {
        foreach ($quotes as &$quote) {
            $stmt_p = $db->prepare("SELECT * FROM payments WHERE quote_id = ? ORDER BY payment_date DESC");
            $stmt_p->execute([$quote['quote_id']]);
            $quote['payment_details'] = $stmt_p->fetchAll();
        }
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'count' => count($quotes),
        'data' => $quotes
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sunucu hatası: ' . $e->getMessage()
    ]);
}
