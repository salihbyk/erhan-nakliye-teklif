<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/update-functions.php';
require_once '../tools/git-auto-update.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Git Auto Update sistemi için config
$gitUpdateConfig = [
    'repo_owner' => 'salihbyk', // GitHub kullanıcı adınız
    'repo_name' => 'erhan-nakliye-teklif',
    'github_token' => null // Private repo için token
];

$gitUpdater = new GitAutoUpdate($gitUpdateConfig);

$message = '';
$error = '';
$updateInfo = null;

// Git üzerinden güncelleme kontrolü
if (isset($_POST['action']) && $_POST['action'] === 'check_git_updates') {
    try {
        $updateInfo = $gitUpdater->checkForUpdates();

        if ($updateInfo['success']) {
            if ($updateInfo['update_available']) {
                $message = "Yeni güncelleme mevcut: v{$updateInfo['latest_version']}";
            } else {
                $message = "Sistem güncel. Mevcut versiyon: v{$updateInfo['current_version']}";
            }
        } else {
            $error = "Güncelleme kontrolü başarısız: " . $updateInfo['error'];
        }
    } catch (Exception $e) {
        $error = "Güncelleme kontrolü hatası: " . $e->getMessage();
    }
}

// Git üzerinden güncelleme yükleme
if (isset($_POST['action']) && $_POST['action'] === 'install_git_update') {
    try {
        $updateInfo = $gitUpdater->checkForUpdates();

        if ($updateInfo['success'] && $updateInfo['update_available']) {
            $result = $gitUpdater->downloadAndInstall($updateInfo);

            if ($result['success']) {
                $message = $result['message'] . " (v{$result['version']})";

                // Versiyonu sistem ayarına yaz
                if (!empty($result['version'])) {
                    updateSystemVersion($result['version']);
                }

                // Sayfayı yenile
                echo "<script>setTimeout(function() { window.location.reload(); }, 3000);</script>";
            } else {
                $error = "Güncelleme yüklenemedi: " . $result['error'];
            }
        } else {
            $error = "Güncelleme mevcut değil veya kontrol hatası.";
        }
    } catch (Exception $e) {
        $error = "Güncelleme hatası: " . $e->getMessage();
    }
}

// Otomatik paket yükleme (localhost için)
if (isset($_POST['action']) && $_POST['action'] === 'auto_upload_package' && isset($_POST['package_file'])) {
    try {
        $packageFile = $_POST['package_file'];
        $toolsPath = '../tools/' . $packageFile;

        if (!file_exists($toolsPath)) {
            throw new Exception('Paket dosyası bulunamadı: ' . $packageFile);
        }

        // Dosyayı upload dizinine kopyala
        $uploadPath = '../temp_auto_upload_' . time() . '.zip';
        if (!copy($toolsPath, $uploadPath)) {
            throw new Exception('Paket dosyası kopyalanamadı.');
        }

        // $_FILES array'ini simüle et
        $_FILES['update_package'] = [
            'name' => $packageFile,
            'tmp_name' => $uploadPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($uploadPath)
        ];

        $_POST['action'] = 'install_update';

        // Normal update işlemini başlat

    } catch (Exception $e) {
        $error = 'Otomatik yükleme hatası: ' . $e->getMessage();
    }
}

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

        // Dosyaları kopyala (config koruması ile)
        if (isset($config['files']) && !empty($config['files'])) {
            $protectedFiles = [
                'config/database.php',
                'config/config.php',
                '.env',
                'uploads/',
                'backups/'
            ];

            foreach ($config['files'] as $file) {
                $sourcePath = $extractPath . '/files/' . $file['source'];
                $targetPath = '../' . $file['target'];

                // Config dosyalarını koru
                $isProtected = false;
                foreach ($protectedFiles as $protectedPattern) {
                    if (fnmatch($protectedPattern, $file['target'])) {
                        $isProtected = true;
                        break;
                    }
                }

                if ($isProtected) {
                    continue; // Config dosyalarını atla
                }

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

        .update-info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .release-notes {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 0.75rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box i {
            color: var(--secondary-color);
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
                <!-- Git Güncelleme Kontrolü -->
                <div class="col-12 mb-4">
                    <div class="glass-card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0" style="color: var(--primary-color); font-weight: 600;">
                                <i class="fab fa-github me-2"></i>GitHub Güncellemeleri
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="check_git_updates">
                                <div class="row align-items-end">
                                    <div class="col-md-8">
                                        <div class="info-box">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Sistem GitHub üzerinden otomatik güncelleme kontrolü yapabilir.</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync-alt me-2"></i>Güncellemeleri Kontrol Et
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <?php if (isset($updateInfo) && $updateInfo): ?>
                                <?php if ($updateInfo['success']): ?>
                                    <div class="update-info-card">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="mb-3">Versiyon Bilgileri</h6>
                                                <p class="mb-1">
                                                    <strong>Mevcut Versiyon:</strong>
                                                    <span class="badge bg-secondary">v<?= htmlspecialchars($updateInfo['current_version']) ?></span>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Son Versiyon:</strong>
                                                    <span class="badge bg-<?= $updateInfo['update_available'] ? 'success' : 'secondary' ?>">
                                                        v<?= htmlspecialchars($updateInfo['latest_version']) ?>
                                                    </span>
                                                </p>
                                                <?php if ($updateInfo['published_at']): ?>
                                                <p class="mb-0">
                                                    <strong>Yayın Tarihi:</strong>
                                                    <?= date('d.m.Y H:i', strtotime($updateInfo['published_at'])) ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($updateInfo['update_available']): ?>
                                                    <h6 class="mb-3">Güncelleme Mevcut!</h6>
                                                    <?php if ($updateInfo['release_notes']): ?>
                                                        <div class="release-notes">
                                                            <h6 class="small">Sürüm Notları:</h6>
                                                            <div class="small"><?= nl2br(htmlspecialchars($updateInfo['release_notes'])) ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <form method="POST" class="mt-3" onsubmit="return confirm('Güncelleme yüklenecek. Devam etmek istiyor musunuz?');">
                                                        <input type="hidden" name="action" value="install_git_update">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="fas fa-download me-2"></i>Güncellemeyi Yükle
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="alert alert-success mb-0">
                                                        <i class="fas fa-check-circle me-2"></i>Sistem güncel!
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Hızlı Paket Oluşturma (Sadece localhost için) -->
                <?php if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false): ?>
                <div class="col-12 mb-4">
                    <div class="glass-card">
                        <div class="card-header border-0 bg-transparent">
                            <h5 class="mb-0" style="color: var(--warning-color); font-weight: 600;">
                                <i class="fas fa-tools me-2"></i>Geliştirici Araçları (Localhost)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Bu bölüm sadece yerel geliştirme ortamında görünür.
                            </div>

                            <form id="devPackageForm" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Versiyon Türü</label>
                                    <select class="form-select" name="version_type">
                                        <option value="patch">Patch (1.0.0 → 1.0.1)</option>
                                        <option value="minor">Minor (1.0.0 → 1.1.0)</option>
                                        <option value="major">Major (1.0.0 → 2.0.0)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Güncelleme Açıklaması</label>
                                    <input type="text" class="form-control" name="description" placeholder="Yeni özellikler ve düzeltmeler" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-warning d-block w-100">
                                        <i class="fas fa-magic me-2"></i>Paket Oluştur
                                    </button>
                                </div>
                            </form>

                            <div id="devPackageResult" class="mt-3" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

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

            // Geliştirici paket oluşturma formu
            const devPackageForm = document.getElementById('devPackageForm');
            if (devPackageForm) {
                devPackageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    createDevPackage();
                });
            }
        });

        function createDevPackage() {
            const form = document.getElementById('devPackageForm');
            const formData = new FormData(form);
            formData.append('action', 'create_dev_package');

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Oluşturuluyor...';

            const resultDiv = document.getElementById('devPackageResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-cogs fa-spin me-2"></i>Paket oluşturuluyor...</div>';

            fetch('../tools/dev-packager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Paket Başarıyla Oluşturuldu!</h6>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Dosya:</strong> ${data.file}<br>
                                    <strong>Versiyon:</strong> v${data.version}<br>
                                    <strong>Boyut:</strong> ${(data.size / 1024).toFixed(2)} KB
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="../tools/${data.file}" class="btn btn-success btn-sm me-2" download>
                                        <i class="fas fa-download me-1"></i>İndir
                                    </a>
                                    <button class="btn btn-primary btn-sm" onclick="autoUploadPackage('${data.file}')">
                                        <i class="fas fa-upload me-1"></i>Otomatik Yükle
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    // Formu temizle
                    form.reset();
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Hata: ${data.error}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Beklenmeyen bir hata oluştu.
                    </div>
                `;
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        function autoUploadPackage(filename) {
            if (!confirm('Bu paketi otomatik olarak yüklemek istediğinizden emin misiniz?\n\nDosya: ' + filename)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'auto_upload_package');
            formData.append('package_file', filename);

            // Loading göster
            const resultDiv = document.getElementById('devPackageResult');
            resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-cogs fa-spin me-2"></i>Paket otomatik olarak yükleniyor...</div>';

            // Sayfayı yeniden yükle (POST ile)
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'auto_upload_package';
            form.appendChild(actionInput);

            const fileInput = document.createElement('input');
            fileInput.name = 'package_file';
            fileInput.value = filename;
            form.appendChild(fileInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
    <script src="includes/sidebar.js"></script>
</body>
</html>
