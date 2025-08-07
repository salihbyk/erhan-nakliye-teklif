<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/update-functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Update işlemini gerçekleştir
if ($_POST['action'] === 'install_update' && isset($_FILES['update_package'])) {
    try {
        $uploadedFile = $_FILES['update_package'];

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Dosya yüklenirken hata oluştu.');
        }

        // Dosya türü kontrolü
        $fileInfo = pathinfo($uploadedFile['name']);
        if (strtolower($fileInfo['extension']) !== 'zip') {
            throw new Exception('Sadece .zip dosyaları kabul edilir.');
        }

        // Geçici dizin oluştur
        $tempDir = '../temp_update_' . time();
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('Geçici dizin oluşturulamadı.');
        }

        // ZIP dosyasını geçici dizine taşı
        $zipPath = $tempDir . '/update.zip';
        if (!move_uploaded_file($uploadedFile['tmp_name'], $zipPath)) {
            throw new Exception('Dosya taşınamadı.');
        }

        // ZIP dosyasını aç
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception('ZIP dosyası açılamadı.');
        }

        // ZIP içeriğini kontrol et
        $extractPath = $tempDir . '/extracted';
        if (!mkdir($extractPath, 0755, true)) {
            throw new Exception('Çıkarma dizini oluşturulamadı.');
        }

        // ZIP dosyasını çıkar
        if (!$zip->extractTo($extractPath)) {
            throw new Exception('ZIP dosyası çıkarılamadı.');
        }
        $zip->close();

        // Update config dosyasını oku
        $configPath = $extractPath . '/update_config.json';
        if (!file_exists($configPath)) {
            throw new Exception('Güncelleme yapılandırma dosyası bulunamadı.');
        }

        $config = json_decode(file_get_contents($configPath), true);
        if (!$config) {
            throw new Exception('Geçersiz yapılandırma dosyası.');
        }

        // Veritabanı backup'ı al
        $backupResult = createSystemBackup('pre_update', 'Güncelleme öncesi otomatik yedek');
        if (!$backupResult['success']) {
            throw new Exception('Veritabanı yedeklemesi başarısız: ' . $backupResult['error']);
        }

        // Veritabanı migration'larını çalıştır
        if (isset($config['migrations']) && !empty($config['migrations'])) {
            $database = new Database();
            $db = $database->getConnection();

            foreach ($config['migrations'] as $migration) {
                $migrationPath = $extractPath . '/migrations/' . $migration;
                if (file_exists($migrationPath)) {
                    $migrationResult = executeMigration($migrationPath, $config['version']);
                    if (!$migrationResult['success']) {
                        throw new Exception('Migration hatası (' . $migration . '): ' . $migrationResult['error']);
                    }
                }
            }
        }

        // Dosyaları kopyala
        if (isset($config['files']) && !empty($config['files'])) {
            foreach ($config['files'] as $file) {
                $sourcePath = $extractPath . '/files/' . $file['source'];
                $targetPath = '../' . $file['target'];

                if (file_exists($sourcePath)) {
                    // Hedef dizini oluştur
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    // Mevcut dosyayı yedekle
                    if (file_exists($targetPath)) {
                        $backupPath = $targetPath . '.backup.' . time();
                        copy($targetPath, $backupPath);
                    }

                    // Yeni dosyayı kopyala
                    $syncResult = syncFile($sourcePath, $targetPath, true);
                    if (!$syncResult['success']) {
                        throw new Exception('Dosya kopyalanamadı: ' . $file['target'] . ' - ' . $syncResult['error']);
                    }
                }
            }
        }

        // Geçici dosyaları temizle
        deleteDirectory($tempDir);

        // Güncelleme logunu kaydet
        logSystemUpdate($config['version'], $config['description'], true, basename($backupResult['file']));
        updateSystemVersion($config['version']);

        $message = 'Güncelleme başarıyla yüklendi! Versiyon: ' . $config['version'];

    } catch (Exception $e) {
        $error = $e->getMessage();

        // Geçici dosyaları temizle
        if (isset($tempDir) && is_dir($tempDir)) {
            cleanupUpdateProcess($tempDir);
        }
    }
}

// Mevcut versiyon bilgisini al
$currentVersion = getCurrentSystemVersion();

// Son güncellemeleri al
$updates = getSystemUpdateHistory();

function deleteDirectory($dir) {
    cleanupUpdateProcess($dir);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Güncelleme - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem;
            margin-left: 250px;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .upload-area {
            border: 2px dashed var(--secondary-color);
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(147, 197, 253, 0.1));
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 197, 253, 0.15));
            transform: scale(1.02);
        }

        .upload-area.dragover {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(5, 150, 105, 0.1), rgba(52, 211, 153, 0.15));
        }

        .version-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .update-item {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            backdrop-filter: blur(10px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
        }

        .progress {
            height: 8px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(135deg, var(--success-color), #10b981);
            border-radius: 50px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 mb-1" style="color: var(--primary-color); font-weight: 700;">
                        <i class="fas fa-cloud-download-alt me-3"></i>Sistem Güncelleme
                    </h1>
                    <p class="text-muted mb-0">Sisteminizdeki yeni özellikleri ve düzeltmeleri yükleyin</p>
                </div>
                <div class="version-badge">
                    <i class="fas fa-tag me-2"></i>v<?= htmlspecialchars($currentVersion) ?>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success glass-card mb-4">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger glass-card mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Güncelleme Yükleme -->
                <div class="col-lg-8 mb-4">
                    <div class="glass-card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0" style="color: var(--primary-color); font-weight: 600;">
                                <i class="fas fa-upload me-2"></i>Güncelleme Paketi Yükle
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="updateForm" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="install_update">

                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;"></i>
                                    <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Güncelleme dosyasını sürükleyip bırakın</h5>
                                    <p class="text-muted mb-3">veya</p>
                                    <label for="update_package" class="btn btn-primary mb-3">
                                        <i class="fas fa-folder-open me-2"></i>Dosya Seç
                                    </label>
                                    <input type="file" id="update_package" name="update_package" accept=".zip" style="display: none;" required>
                                    <p class="small text-muted mb-0">Sadece .zip dosyaları kabul edilir</p>
                                </div>

                                <div id="selectedFile" class="mt-3" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-file-archive me-2"></i>
                                        <span id="fileName"></span>
                                        <span id="fileSize" class="text-muted"></span>
                                    </div>
                                </div>

                                <div id="uploadProgress" class="mt-3" style="display: none;">
                                    <div class="progress mb-2">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <p class="small text-muted mb-0">Güncelleme yükleniyor...</p>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="createBackup" checked disabled>
                                        <label class="form-check-label text-muted" for="createBackup">
                                            <i class="fas fa-shield-alt me-1"></i>Otomatik yedekleme
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="installBtn" disabled>
                                        <i class="fas fa-cogs me-2"></i>Güncellemeyi Yükle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Güncelleme Geçmişi -->
                <div class="col-lg-4 mb-4">
                    <div class="glass-card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0" style="color: var(--primary-color); font-weight: 600;">
                                <i class="fas fa-history me-2"></i>Güncelleme Geçmişi
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($updates)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clock text-muted" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                    <p class="text-muted mb-0">Henüz güncelleme yapılmadı</p>
                                </div>
                            <?php else: ?>
                                <div class="update-history">
                                    <?php foreach ($updates as $update): ?>
                                    <div class="update-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-primary">v<?= htmlspecialchars($update['version']) ?></span>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($update['update_date'])) ?></small>
                                        </div>
                                        <p class="mb-0 small"><?= htmlspecialchars($update['description']) ?></p>
                                        <?php if ($update['success'] == 0): ?>
                                            <span class="badge bg-danger">Başarısız</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sistem Bilgileri -->
            <div class="row">
                <div class="col-12">
                    <div class="glass-card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0" style="color: var(--primary-color); font-weight: 600;">
                                <i class="fas fa-info-circle me-2"></i>Sistem Bilgileri
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <i class="fas fa-server text-primary" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                        <h6 class="mb-1">PHP Versiyon</h6>
                                        <span class="badge bg-success"><?= PHP_VERSION ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <i class="fas fa-database text-primary" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                        <h6 class="mb-1">MySQL Versiyon</h6>
                                        <span class="badge bg-success">
                                            <?php
                                            try {
                                                $database = new Database();
                                                $db = $database->getConnection();
                                                $version = $db->query('SELECT VERSION()')->fetch();
                                                echo explode('-', $version[0])[0];
                                            } catch (Exception $e) {
                                                echo 'Bilinmiyor';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <i class="fas fa-memory text-primary" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                        <h6 class="mb-1">Bellek Limiti</h6>
                                        <span class="badge bg-info"><?= ini_get('memory_limit') ?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <i class="fas fa-upload text-primary" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                        <h6 class="mb-1">Max Upload</h6>
                                        <span class="badge bg-info"><?= ini_get('upload_max_filesize') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('update_package');
            const selectedFile = document.getElementById('selectedFile');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const installBtn = document.getElementById('installBtn');
            const updateForm = document.getElementById('updateForm');

            // Drag & Drop olayları
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect(files[0]);
                }
            });

            // Dosya seçim olayı
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            function handleFileSelect(file) {
                if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
                    alert('Sadece .zip dosyaları kabul edilir!');
                    return;
                }

                fileName.textContent = file.name;
                fileSize.textContent = ' (' + formatFileSize(file.size) + ')';
                selectedFile.style.display = 'block';
                installBtn.disabled = false;
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Form gönderimi
            updateForm.addEventListener('submit', function(e) {
                if (!confirm('Güncellemeyi yüklemek istediğinizden emin misiniz? Bu işlem sırasında sistem geçici olarak erişilemez olabilir.')) {
                    e.preventDefault();
                    return;
                }

                installBtn.disabled = true;
                installBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Yükleniyor...';

                // Progress bar göster
                document.getElementById('uploadProgress').style.display = 'block';

                // Simüle edilmiş progress (gerçek implementasyonda AJAX kullanılabilir)
                let progress = 0;
                const progressBar = document.querySelector('.progress-bar');
                const interval = setInterval(() => {
                    progress += Math.random() * 15;
                    if (progress >= 90) {
                        progress = 90;
                        clearInterval(interval);
                    }
                    progressBar.style.width = progress + '%';
                }, 200);
            });
        });
    </script>
    <script src="includes/sidebar.js"></script>
</body>
</html>
