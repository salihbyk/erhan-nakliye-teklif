<?php
session_start();
require_once '../config/database.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Dosya yüklendi mi kontrol et
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['file'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

// Dosya türü kontrolü
if (!in_array($uploadedFile['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, GIF and WebP are allowed.']);
    exit;
}

// Dosya boyutu kontrolü (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($uploadedFile['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Upload dizinini oluştur
$uploadDir = '../uploads/editor/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Benzersiz dosya adı oluştur
$fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
$fileName = uniqid() . '_' . time() . '.' . $fileExtension;
$uploadPath = $uploadDir . $fileName;

// Dosyayı taşı
if (move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
    // Görüntü boyutlarını kontrol et ve gerekirse yeniden boyutlandır
    list($width, $height) = getimagesize($uploadPath);

    // Eğer görüntü çok büyükse yeniden boyutlandır (max 1920px genişlik)
    if ($width > 1920) {
        resizeImage($uploadPath, 1920);
    }

    // Başarılı response
    $response = [
        'location' => '/erhan/uploads/editor/' . $fileName
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload file']);
}

/**
 * Görüntüyü yeniden boyutlandır
 */
function resizeImage($filePath, $maxWidth) {
    $imageInfo = getimagesize($filePath);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];

    // Yeni boyutları hesapla
    $ratio = $maxWidth / $width;
    $newWidth = $maxWidth;
    $newHeight = floor($height * $ratio);

    // Kaynak görüntüyü yükle
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($filePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($filePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($filePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($filePath);
            break;
        default:
            return false;
    }

    // Yeni görüntü oluştur
    $destination = imagecreatetruecolor($newWidth, $newHeight);

    // PNG ve GIF için şeffaflığı koru
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($destination, imagecolorallocatealpha($destination, 0, 0, 0, 127));
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
    }

    // Yeniden boyutlandır
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Kaydet
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($destination, $filePath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($destination, $filePath, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($destination, $filePath);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($destination, $filePath, 85);
            break;
    }

    // Belleği temizle
    imagedestroy($source);
    imagedestroy($destination);

    return true;
}
?>
