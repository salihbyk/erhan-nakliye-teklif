<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

try {
    // Hem mode (slug) hem de transport_mode_id (ID) destekle
    $transport_mode = $_GET['mode'] ?? '';
    $transport_mode_id = $_GET['transport_mode_id'] ?? '';
    $trade_type = $_GET['trade_type'] ?? '';

    $database = new Database();
    $db = $database->getConnection();

    $mode_id = null;

    if (!empty($transport_mode_id)) {
        // ID ile arama
        $stmt = $db->prepare("SELECT id FROM transport_modes WHERE id = ? AND is_active = 1");
        $stmt->execute([$transport_mode_id]);
        $mode = $stmt->fetch();
        if ($mode) {
            $mode_id = $mode['id'];
        }
    } elseif (!empty($transport_mode)) {
        // Slug ile arama
        $stmt = $db->prepare("SELECT id FROM transport_modes WHERE slug = ? AND is_active = 1");
        $stmt->execute([$transport_mode]);
        $mode = $stmt->fetch();
        if ($mode) {
            $mode_id = $mode['id'];
        }
    }

    if (!$mode_id) {
        throw new Exception('Geçersiz taşıma modu');
    }

    // Şablonları al - trade_type filtrelemesi isteğe bağlı
    $sql = "
        SELECT id, template_name, language, currency, trade_type, services_content, terms_content, transport_process_content, is_active
        FROM quote_templates
        WHERE transport_mode_id = ? AND is_active = 1
    ";
    $params = [$mode_id];

    if (!empty($trade_type)) {
        $sql .= " AND trade_type = ?";
        $params[] = $trade_type;
    }

    $sql .= " ORDER BY language ASC, currency ASC, template_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $templates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>