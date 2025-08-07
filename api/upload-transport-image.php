<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin oturum kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);

    // Debug için session bilgilerini logla
    error_log('Session debug: ' . print_r($_SESSION, true));

    // Form gönderimi ise redirect et, JSON değil
    if (isset($_POST['transport_mode_id'])) {
        $_SESSION['error_message'] = 'Oturum süresi dolmuş. Lütfen tekrar giriş yapın.';
        header('Location: ../admin/login.php');
    } else {
        echo json_encode(['success' => false, 'message' => 'Yetki gerekli']);
    }
    exit();
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Sadece POST istekleri kabul edilir']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Form verilerini al
    $transport_mode_id = $_POST['transport_mode_id'] ?? '';
    $image_description = $_POST['image_description'] ?? '';
    $mode_slug = $_POST['mode_slug'] ?? '';

    if (empty($transport_mode_id)) {
        throw new Exception('Taşıma modu ID\'si gerekli');
    }

        // Dosya kontrolü
    if (!isset($_FILES['image_files']) || empty($_FILES['image_files']['name'][0])) {
        throw new Exception('Hiç dosya seçilmemiş');
    }

    $files = $_FILES['image_files'];
    $fileCount = count($files['name']);

    // Maksimum dosya sayısı kontrolü
    if ($fileCount > 10) {
        throw new Exception('Maksimum 10 dosya yükleyebilirsiniz');
    }

    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    // Her dosyayı kontrol et
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception('Dosya yükleme hatası: ' . $files['name'][$i]);
        }

        // Dosya türü kontrolü
        if (!in_array($files['type'][$i], $allowed_types)) {
            throw new Exception($files['name'][$i] . ' dosyası desteklenmiyor. Sadece resim dosyaları kabul edilir (JPEG, PNG, GIF, WebP)');
        }

        // Dosya boyutu kontrolü (5MB max)
        if ($files['size'][$i] > 5 * 1024 * 1024) {
            throw new Exception($files['name'][$i] . ' dosyası 5MB\'dan büyük');
        }

        $uploaded_files[] = $i;
    }

    // Transport mode kontrolü
    $stmt = $db->prepare("SELECT slug FROM transport_modes WHERE id = ? AND is_active = 1");
    $stmt->execute([$transport_mode_id]);
    $transport_mode = $stmt->fetch();

    if (!$transport_mode) {
        throw new Exception('Geçersiz taşıma modu');
    }

    // Eğer mode_slug gönderilmediyse veritabanından al
    if (empty($mode_slug)) {
        $mode_slug = $transport_mode['slug'];
    }

        // Upload dizini oluştur
    $upload_dir = '../uploads/transport-images/' . $mode_slug . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $uploaded_count = 0;
    $failed_files = [];

    // Her dosyayı yükle
    foreach ($uploaded_files as $index) {
        try {
            // Dosya adını oluştur (unique)
            $file_extension = pathinfo($files['name'][$index], PATHINFO_EXTENSION);
            $file_name = uniqid() . '_' . time() . '_' . $index . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            // Dosyayı taşı
            if (!move_uploaded_file($files['tmp_name'][$index], $file_path)) {
                throw new Exception('Dosya taşınamadı');
            }

            // Veritabanına kaydet
            $relative_path = 'uploads/transport-images/' . $mode_slug . '/' . $file_name;
            $original_name = pathinfo($files['name'][$index], PATHINFO_FILENAME);

            $stmt = $db->prepare("
                INSERT INTO transport_reference_images
                (transport_mode_id, image_name, image_path, image_description, is_active, display_order)
                VALUES (?, ?, ?, ?, 1, ?)
            ");

            $stmt->execute([
                $transport_mode_id,
                $original_name,
                $relative_path,
                $image_description,
                $index
            ]);

            $uploaded_count++;
        } catch (Exception $e) {
            $failed_files[] = $files['name'][$index] . ': ' . $e->getMessage();
        }
    }

    // Sonuç mesajı
    if ($uploaded_count > 0) {
        $message = $uploaded_count . ' resim başarıyla yüklendi';
        if (!empty($failed_files)) {
            $message .= '. Başarısız: ' . implode(', ', $failed_files);
        }
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = 'Hiçbir resim yüklenemedi: ' . implode(', ', $failed_files);
    }

    // Redirect et
    header('Location: ../admin/transport-modes.php');
    exit();

} catch (Exception $e) {
    // Hata durumunda dosyayı sil (eğer oluşturulduysa)
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }

    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ../admin/transport-modes.php');
    exit();
}
?>