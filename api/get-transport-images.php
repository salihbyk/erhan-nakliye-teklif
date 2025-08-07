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

try {
    $mode_id = $_GET['mode_id'] ?? '';

    if (empty($mode_id)) {
        throw new Exception('Taşıma modu ID\'si gerekli');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Resimleri al
    $stmt = $db->prepare("
        SELECT id, image_name, image_path, image_description, upload_date, display_order
        FROM transport_reference_images
        WHERE transport_mode_id = ? AND is_active = 1
        ORDER BY display_order ASC, upload_date DESC
    ");
    $stmt->execute([$mode_id]);
    $images = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'images' => $images
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>