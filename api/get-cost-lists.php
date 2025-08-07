<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Transport mode parametresini al
    $transport_mode = $_GET['mode'] ?? '';

    // Base query
    $sql = "
        SELECT cl.*, tm.name as transport_mode_name
        FROM cost_lists cl
        LEFT JOIN transport_modes tm ON cl.transport_mode_id = tm.id
        WHERE cl.is_active = 1
    ";

    $params = [];

    // Eğer transport mode belirtilmişse, o moda özel veya genel listeleri getir
    if (!empty($transport_mode)) {
        // Transport mode ID'sini al (case-insensitive)
        $stmt = $db->prepare("SELECT id FROM transport_modes WHERE LOWER(name) = LOWER(?) OR name = ?");
        $stmt->execute([$transport_mode, $transport_mode]);
        $mode_data = $stmt->fetch();

        if ($mode_data) {
            $sql .= " AND (cl.transport_mode_id = ? OR cl.transport_mode_id IS NULL)";
            $params[] = $mode_data['id'];
        } else {
            // Sadece genel listeleri getir
            $sql .= " AND cl.transport_mode_id IS NULL";
        }
    } else {
        // Tüm aktif listeleri getir
    }

    $sql .= " ORDER BY cl.transport_mode_id IS NULL DESC, cl.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cost_lists = $stmt->fetchAll();

    // Response formatı
    $response = [
        'success' => true,
        'cost_lists' => array_map(function($list) {
            return [
                'id' => (int) $list['id'],
                'name' => $list['name'],
                'description' => $list['description'],
                'file_name' => $list['file_name'],
                'file_path' => $list['file_path'],
                'transport_mode_name' => $list['transport_mode_name'],
                'created_at' => $list['created_at'],
                'file_size_formatted' => formatFileSize($list['file_size']),
                'file_extension' => strtolower(pathinfo($list['file_name'], PATHINFO_EXTENSION))
            ];
        }, $cost_lists)
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Maliyet listeleri yüklenirken hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
}
?>