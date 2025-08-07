<?php
session_start();
require_once '../config/database.php';

// JSON header
header('Content-Type: application/json');

// Admin oturum kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetki gerekli']);
    exit();
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit();
}

try {
    // JSON input'u al
    $input = json_decode(file_get_contents('php://input'), true);
    $image_id = $input['image_id'] ?? '';

    if (empty($image_id)) {
        throw new Exception('Resim ID\'si gerekli');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Resim bilgilerini al
    $stmt = $db->prepare("
        SELECT image_path
        FROM transport_reference_images
        WHERE id = ?
    ");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch();

    if (!$image) {
        throw new Exception('Resim bulunamadı');
    }

    // Dosyayı sil
    $file_path = '../' . $image['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Veritabanından sil
    $stmt = $db->prepare("DELETE FROM transport_reference_images WHERE id = ?");
    $stmt->execute([$image_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Resim başarıyla silindi'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>