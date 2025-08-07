<?php
session_start();
require_once '../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit();
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit();
}

// JSON verilerini al
$input = json_decode(file_get_contents('php://input'), true);
$customer_id = $input['customer_id'] ?? null;

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Müşteri ID gerekli']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Müşterinin varlığını kontrol et
    $check_stmt = $db->prepare("SELECT id, first_name, last_name FROM customers WHERE id = ?");
    $check_stmt->execute([$customer_id]);
    $customer = $check_stmt->fetch();

    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Müşteri bulunamadı']);
        exit();
    }

    // Müşteriye ait teklifleri kontrol et
    $quotes_stmt = $db->prepare("SELECT COUNT(*) as quote_count FROM quotes WHERE customer_id = ?");
    $quotes_stmt->execute([$customer_id]);
    $quotes_result = $quotes_stmt->fetch();

    if ($quotes_result['quote_count'] > 0) {
        // Müşteriye ait tüm verileri sil (cascade)
        $db->beginTransaction();

        // Önce ödeme kayıtlarını sil
        $delete_payments_stmt = $db->prepare("
            DELETE p FROM payments p
            INNER JOIN quotes q ON p.quote_id = q.id
            WHERE q.customer_id = ?
        ");
        $delete_payments_stmt->execute([$customer_id]);

        // Sonra teklifleri sil
        $delete_quotes_stmt = $db->prepare("DELETE FROM quotes WHERE customer_id = ?");
        $delete_quotes_stmt->execute([$customer_id]);

        // Son olarak müşteriyi sil
        $delete_customer_stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
        $delete_customer_stmt->execute([$customer_id]);

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => $customer['first_name'] . ' ' . $customer['last_name'] . ' müşterisi ve tüm ilgili veriler başarıyla silindi'
        ]);
    } else {
        // Sadece müşteriyi sil
        $delete_stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
        $delete_stmt->execute([$customer_id]);

        echo json_encode([
            'success' => true,
            'message' => $customer['first_name'] . ' ' . $customer['last_name'] . ' müşterisi başarıyla silindi'
        ]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollback();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
?>