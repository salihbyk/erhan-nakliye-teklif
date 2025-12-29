<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';

try {
    require_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    if ($action === 'transport_modes') {
        $stmt = $db->query("
            SELECT id, name, slug, icon, is_active
            FROM transport_modes
            WHERE is_active = 1
            ORDER BY name ASC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    } 
    elseif ($action === 'customers') {
        $stmt = $db->query("
            SELECT id, first_name, last_name, email, phone, company
            FROM customers
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action parameter. Use action=transport_modes or action=customers']);
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}
