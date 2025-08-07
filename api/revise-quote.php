<?php
// Error reporting'i kapat - production için
error_reporting(0);
ini_set('display_errors', 0);

// Output buffer'ı temizle
ob_start();
ob_clean();

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkilendirme hatası']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // POST verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_id = $input['quote_id'] ?? 0;

    if (!$quote_id) {
        echo json_encode(['success' => false, 'message' => 'Teklif ID gerekli']);
        exit;
    }

    // Orijinal teklifi getir
    $stmt = $db->prepare("
        SELECT * FROM quotes
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$quote_id]);
    $original_quote = $stmt->fetch();

    if (!$original_quote) {
        echo json_encode(['success' => false, 'message' => 'Teklif bulunamadı']);
        exit;
    }

    // Email gönderilmiş mi kontrol et
    if (empty($original_quote['email_sent_at'])) {
        echo json_encode(['success' => false, 'message' => 'Henüz email gönderilmeyen teklifler revize edilemez']);
        exit;
    }

    // Transaction başlat
    $db->beginTransaction();

    try {
        // Orijinal teklifi pasif yap
        $stmt = $db->prepare("UPDATE quotes SET is_active = 0 WHERE id = ?");
        $stmt->execute([$quote_id]);

        // Yeni revision oluştur
        $new_revision_number = $original_quote['revision_number'] + 1;
        $new_quote_number = $original_quote['quote_number'];

        // Quote number'a revision ekle (eğer yoksa)
        if ($new_revision_number == 1) {
            $new_quote_number .= '_rev1';
        } else {
            // Mevcut rev sayısını güncelle
            $new_quote_number = preg_replace('/(_rev\d+)?$/', '_rev' . $new_revision_number, $original_quote['quote_number']);
        }

        // Yeni teklif oluştur
        $stmt = $db->prepare("
            INSERT INTO quotes (
                quote_number, customer_id, transport_mode_id, origin, destination,
                weight, volume, pieces, cargo_type, trade_type, description,
                start_date, delivery_date, valid_until, selected_template_id,
                cost_list_id, calculated_price, final_price, notes, status,
                revision_number, parent_quote_id, is_active,
                email_sent_at, email_sent_count
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'priced', ?, ?, 1, NULL, 0
            )
        ");

        $result = $stmt->execute([
            $new_quote_number,
            $original_quote['customer_id'],
            $original_quote['transport_mode_id'],
            $original_quote['origin'],
            $original_quote['destination'],
            $original_quote['weight'],
            $original_quote['volume'],
            $original_quote['pieces'],
            $original_quote['cargo_type'],
            $original_quote['trade_type'],
            $original_quote['description'],
            $original_quote['start_date'],
            $original_quote['delivery_date'],
            date('Y-m-d', strtotime('+30 days')), // Yeni geçerlilik tarihi
            $original_quote['selected_template_id'],
            $original_quote['cost_list_id'],
            $original_quote['calculated_price'],
            $original_quote['final_price'],
            $original_quote['notes'],
            $new_revision_number,
            $quote_id // Parent quote ID
        ]);

        if ($result) {
            $new_quote_id = $db->lastInsertId();

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Revize başarıyla oluşturuldu',
                'new_quote_id' => $new_quote_id,
                'new_quote_number' => $new_quote_number,
                'revision_number' => $new_revision_number
            ]);
            ob_end_flush();
            exit;
        } else {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Revize oluşturulamadı']);
            ob_end_flush();
            exit;
        }

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
}
?>