<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

// OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('Dosya bulunamadı');
    }

    $file      = $_FILES['file'];
    $uploadDir = '../uploads/editor/';

    // Klasör yoksa oluştur
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Yükleme klasörü oluşturulamadı');
        }
    }

    // Güvenlik için geçerli uzantılar
    $allowedExts = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar'];
    $fileExt     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExts)) {
        throw new Exception('Bu dosya türüne izin verilmiyor');
    }

    // Benzersiz dosya adı
    $newFileName = uniqid().'_'.preg_replace('/[^a-zA-Z0-9-_\.]/','',$file['name']);
    $targetPath  = $uploadDir.$newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Dosya yüklenirken hata oluştu');
    }

    // Başarılı - relative URL oluştur
    $relativePath = str_replace('../', '', $targetPath);
    $baseUrl = dirname($_SERVER['PHP_SELF']); // /erhan/api
    $baseUrl = dirname($baseUrl); // /erhan
    $fullUrl = $baseUrl . '/' . $relativePath;

    echo json_encode([
        'success' => true,
        'url'     => $fullUrl
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
