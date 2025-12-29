<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once '../config/database.php';

// Oturum kontrolü (geliştirme için geçici olarak devre dışı bırakılabilir)
// if (!isset($_SESSION['admin_id'])) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

try {
    $database = new Database();
    $db = $database->getConnection();

    // Widget İstatistikleri
    $stats_sql = "
        SELECT 
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'priced' THEN 1 ELSE 0 END) as priced_count,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN final_price IS NOT NULL AND final_price > 0 THEN final_price ELSE 0 END) as total_value,
            SUM(CASE WHEN status = 'sent' AND final_price IS NOT NULL THEN final_price ELSE 0 END) as sent_value,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
            SUM(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as week_count
        FROM quotes
        WHERE is_active = 1
    ";
    $stats_stmt = $db->query($stats_sql);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Toplam müşteri sayısı
    $customer_stmt = $db->query("SELECT COUNT(*) as total FROM customers");
    $total_customers = $customer_stmt->fetch()['total'];

    // Bu ayki teklifler
    $monthly_stmt = $db->query("SELECT COUNT(*) as total FROM quotes WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $monthly_quotes = $monthly_stmt->fetch()['total'];

    // Para birimlerine göre gelir
    $revenue_stmt = $db->query("
        SELECT
            SUM(CASE WHEN qt.currency = 'TL' OR qt.currency IS NULL THEN q.final_price ELSE 0 END) as revenue_tl,
            SUM(CASE WHEN qt.currency = 'USD' THEN q.final_price ELSE 0 END) as revenue_usd,
            SUM(CASE WHEN qt.currency = 'EUR' THEN q.final_price ELSE 0 END) as revenue_eur,
            SUM(CASE WHEN qt.currency = 'GBP' THEN q.final_price ELSE 0 END) as revenue_gbp
        FROM quotes q
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        WHERE q.status = 'accepted'
    ");
    $revenue_result = $revenue_stmt->fetch();

    // Beklenen gelir
    $pending_revenue_stmt = $db->query("
        SELECT
            SUM(CASE WHEN qt.currency = 'TL' OR qt.currency IS NULL THEN q.final_price ELSE 0 END) as pending_tl,
            SUM(CASE WHEN qt.currency = 'USD' THEN q.final_price ELSE 0 END) as pending_usd,
            SUM(CASE WHEN qt.currency = 'EUR' THEN q.final_price ELSE 0 END) as pending_eur,
            SUM(CASE WHEN qt.currency = 'GBP' THEN q.final_price ELSE 0 END) as pending_gbp
        FROM quotes q
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        WHERE q.status IN ('pending', 'sent')
    ");
    $pending_result = $pending_revenue_stmt->fetch();

    $response = [
        'total_quotes' => $stats['total_count'] ?? 0,
        'monthly_quotes' => $monthly_quotes,
        'total_customers' => $total_customers,
        'pending_count' => $stats['pending_count'] ?? 0,
        'priced_count' => $stats['priced_count'] ?? 0,
        'sent_count' => $stats['sent_count'] ?? 0,
        'approved_count' => $stats['approved_count'] ?? 0,
        'today_count' => $stats['today_count'] ?? 0,
        'week_count' => $stats['week_count'] ?? 0,
        'revenue_tl' => $revenue_result['revenue_tl'] ?? 0,
        'revenue_usd' => $revenue_result['revenue_usd'] ?? 0,
        'revenue_eur' => $revenue_result['revenue_eur'] ?? 0,
        'revenue_gbp' => $revenue_result['revenue_gbp'] ?? 0,
        'pending_tl' => $pending_result['pending_tl'] ?? 0,
        'pending_usd' => $pending_result['pending_usd'] ?? 0,
        'pending_eur' => $pending_result['pending_eur'] ?? 0,
        'pending_gbp' => $pending_result['pending_gbp'] ?? 0,
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
