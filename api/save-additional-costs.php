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
    $costs = $input['costs'] ?? [];

    if (!$quote_id) {
        throw new Exception('Teklif ID gereklidir');
    }

    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    // Quote var mı kontrol et
    $stmt = $db->prepare("SELECT id FROM quotes WHERE id = ? AND is_active = 1");
    $stmt->execute([$quote_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Teklif bulunamadı');
    }

    // Transaction başlat
    $db->beginTransaction();

    // Önce mevcut ek maliyetleri sil (is_additional = 1 olanları)
    $stmt = $db->prepare("DELETE FROM additional_costs WHERE quote_id = ? AND is_additional = 1");
    $stmt->execute([$quote_id]);

    // Yeni ek maliyetleri ekle
    if (!empty($costs)) {
        $stmt = $db->prepare("
            INSERT INTO additional_costs (quote_id, description, amount, currency, is_additional, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");

        foreach ($costs as $cost) {
            $name = trim($cost['name'] ?? '');
            $description = trim($cost['description'] ?? '');
            $amount = floatval($cost['amount'] ?? 0);
            $currency = $cost['currency'] ?? 'TL';

            // Name ve description'ı birleştir
            $final_description = $name;
            if ($description && $description !== $name) {
                $final_description .= ($name ? ' - ' : '') . $description;
            }

            // Validation
            if (!$final_description) {
                throw new Exception('Maliyet açıklaması gereklidir');
            }

            if (!in_array($currency, ['TL', 'USD', 'EUR'])) {
                $currency = 'TL'; // Default değer
            }

            $stmt->execute([$quote_id, $final_description, $amount, $currency]);
        }
    }

    // Transaction commit
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ek maliyetler başarıyla kaydedildi',
        'count' => count($costs)
    ]);

} catch (Exception $e) {
    // Transaction rollback
    if ($db && $db->inTransaction()) {
        $db->rollback();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}
?>