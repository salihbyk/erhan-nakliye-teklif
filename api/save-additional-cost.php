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

    $quote_id = $input['quote_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $currency = $input['currency'] ?? 'TL';

    // Validation
    if (!$quote_id || !$name || $amount <= 0) {
        throw new Exception('Maliyet adı ve tutar gereklidir');
    }

    if (!in_array($currency, ['TL', 'USD', 'EUR'])) {
        throw new Exception('Geçersiz para birimi');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Quote var mı kontrol et
    $stmt = $db->prepare("SELECT id FROM quotes WHERE id = ? AND is_active = 1");
    $stmt->execute([$quote_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Teklif bulunamadı');
    }

    // Ek maliyeti kaydet
    $stmt = $db->prepare("
        INSERT INTO additional_costs (quote_id, name, description, amount, currency, is_additional, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$quote_id, $name, $description, $amount, $currency]);

    $cost_id = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Ek maliyet başarıyla eklendi',
        'cost_id' => $cost_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}