<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session and check admin
session_start();
checkAdminSession();

// AJAX custom field management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();

    $action = $_POST['action'] ?? '';
    $field = $_POST['field'] ?? '';
    $quote_number = $_POST['quote_number'] ?? '';

    if ($action === 'remove_custom_field' && $field && $quote_number) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // custom_fields kolonu var mı kontrol et ve gerekirse ekle
            try {
                $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields'");
                $stmt->execute();
                $columnExists = $stmt->fetch();

                if (!$columnExists) {
                    $db->exec("ALTER TABLE quotes ADD COLUMN custom_fields JSON DEFAULT NULL AFTER description");
                }
            } catch (Exception $e) {
                // Hata logla ama devam et
                error_log("Custom fields column check error: " . $e->getMessage());
            }

            // Önce mevcut custom_fields'ı al
            $stmt = $db->prepare("SELECT custom_fields FROM quotes WHERE quote_number = ?");
            $stmt->execute([$quote_number]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $custom_fields = [];
            if ($result && $result['custom_fields']) {
                $custom_fields = json_decode($result['custom_fields'], true) ?: [];
            }

            // Field'ı kaldır
            if (isset($custom_fields[$field])) {
                unset($custom_fields[$field]);
            }

            // JSON olarak geri kaydet
            $stmt = $db->prepare("UPDATE quotes SET custom_fields = ?, updated_at = NOW() WHERE quote_number = ?");
            $stmt->execute([json_encode($custom_fields), $quote_number]);

            echo json_encode(['success' => true, 'message' => 'Field başarıyla kaldırıldı']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

        if ($action === 'update_field' && $field && $quote_number) {
        $value = $_POST['value'] ?? '';

        try {
            $database = new Database();
            $db = $database->getConnection();

            // custom_fields kolonu var mı kontrol et ve gerekirse ekle
            try {
                $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'custom_fields'");
                $stmt->execute();
                $columnExists = $stmt->fetch();

                if (!$columnExists) {
                    $db->exec("ALTER TABLE quotes ADD COLUMN custom_fields JSON DEFAULT NULL AFTER description");
                }
            } catch (Exception $e) {
                // Hata logla ama devam et
                error_log("Custom fields column check error: " . $e->getMessage());
            }

            // Önce mevcut custom_fields'ı al
            $stmt = $db->prepare("SELECT custom_fields FROM quotes WHERE quote_number = ?");
            $stmt->execute([$quote_number]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $custom_fields = [];
            if ($result && $result['custom_fields']) {
                $custom_fields = json_decode($result['custom_fields'], true) ?: [];
            }

            // Field'ı güncelle/ekle
            $custom_fields[$field] = $value;

            // JSON olarak geri kaydet
            $stmt = $db->prepare("UPDATE quotes SET custom_fields = ?, updated_at = NOW() WHERE quote_number = ?");
            $stmt->execute([json_encode($custom_fields), $quote_number]);

            echo json_encode(['success' => true, 'message' => 'Field başarıyla güncellendi']);
            exit;

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
?>