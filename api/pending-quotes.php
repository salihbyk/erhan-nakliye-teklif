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

try {
    $database = new Database();
    $db = $database->getConnection();

    // Onay bekleyen teklifler
    $stmt = $db->prepare("
        SELECT q.quote_number, q.created_at, q.final_price, q.status,
               qt.currency as template_currency,
               c.first_name, c.last_name, tm.name as transport_mode
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        WHERE q.status IN ('pending', 'sent')
        ORDER BY q.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $pending_quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($pending_quotes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
