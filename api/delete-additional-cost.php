<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Geçersiz JSON verisi');
    }

    $cost_id = $input['cost_id'] ?? null;

    if (!$cost_id) {
        throw new Exception('Maliyet ID gereklidir');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Maliyet var mı kontrol et
    $stmt = $db->prepare("SELECT id FROM additional_costs WHERE id = ?");
    $stmt->execute([$cost_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Maliyet bulunamadı');
    }

    // Maliyeti sil
    $stmt = $db->prepare("DELETE FROM additional_costs WHERE id = ?");
    $stmt->execute([$cost_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Maliyet başarıyla silindi'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}