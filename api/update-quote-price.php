<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../config/database.php';

try {
    // JSON verilerini al
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Geçersiz JSON verisi');
    }

    $quote_id = $input['quote_id'] ?? null;
    $final_price = $input['final_price'] ?? null;

    if (!$quote_id || $final_price === null) {
        throw new Exception('Eksik parametreler');
    }

    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    // Teklif fiyatını güncelle
    $stmt = $db->prepare("UPDATE quotes SET final_price = ? WHERE id = ?");
    $result = $stmt->execute([$final_price, $quote_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Fiyat başarıyla güncellendi']);
    } else {
        throw new Exception('Fiyat güncellenirken hata oluştu');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>