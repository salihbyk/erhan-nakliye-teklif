<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $quote_param = $_GET['quote_id'] ?? null;

    if (!$quote_param) {
        throw new Exception('Teklif ID gereklidir');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Quote'u bul (ID veya quote_number ile)
    if (is_numeric($quote_param)) {
        // Numeric ise direkt ID olarak kullan
        $stmt = $db->prepare("SELECT id FROM quotes WHERE id = ? AND is_active = 1");
        $stmt->execute([$quote_param]);
        $quote = $stmt->fetch();
        $actual_quote_id = $quote_param;
    } else {
        // String ise quote_number olarak kullan
        $stmt = $db->prepare("SELECT id FROM quotes WHERE quote_number = ? AND is_active = 1");
        $stmt->execute([$quote_param]);
        $quote = $stmt->fetch();
        $actual_quote_id = $quote ? $quote['id'] : null;
    }



    if (!$quote) {
        throw new Exception('Teklif bulunamadı');
    }

    // Ek maliyetleri getir
    $stmt = $db->prepare("
        SELECT id, description, amount, currency, created_at
        FROM additional_costs
        WHERE quote_id = ? AND is_additional = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([$actual_quote_id]);
    $costs_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Description'ı name ve description olarak ayır
    $costs = [];
    foreach ($costs_raw as $cost) {
        $description_parts = explode(' - ', $cost['description'], 2);
        $costs[] = [
            'id' => $cost['id'],
            'name' => $description_parts[0],
            'description' => isset($description_parts[1]) ? $description_parts[1] : '',
            'amount' => $cost['amount'],
            'currency' => $cost['currency'],
            'created_at' => $cost['created_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'costs' => $costs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}