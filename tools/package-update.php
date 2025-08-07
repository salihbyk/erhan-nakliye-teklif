<?php
/**
 * Güncelleme Paketleme Scripti
 * Bu script localhost'ta değişiklikleri paketleyerek sunucuya yüklenebilir hale getirir
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class UpdatePackager {
    private $version;
    private $description;
    private $packageDir;
    private $migrations = [];
    private $files = [];
    private $excludedPaths = [
        'tools/',
        'temp_update_*',
        'backups/',
        'uploads/',
        'vendor/',
        '.git/',
        '.htaccess',
        'config/database.php',
        '*.log',
        '*.backup.*'
    ];

    public function __construct($version, $description) {
        $this->version = $version;
        $this->description = $description;
        $this->packageDir = 'update_package_' . $version . '_' . time();
    }

    public function addMigration($migrationFile) {
        if (file_exists($migrationFile)) {
            $this->migrations[] = basename($migrationFile);
            return true;
        }
        return false;
    }

    public function addFile($source, $target = null) {
        if (file_exists($source)) {
            $this->files[] = [
                'source' => $source,
                'target' => $target ?? $source
            ];
            return true;
        }
        return false;
    }

    public function addDirectory($dir, $recursive = true) {
        if (!is_dir($dir)) {
            return false;
        }

        $iterator = $recursive
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir))
            : new DirectoryIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isFile() && !$this->isExcluded($file->getPathname())) {
                $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $this->addFile($file->getPathname(), $relativePath);
            }
        }

        return true;
    }

    private function isExcluded($path) {
        $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $path);
        $relativePath = str_replace('\\', '/', $relativePath);

        foreach ($this->excludedPaths as $excludedPath) {
            if (fnmatch($excludedPath, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    public function createPackage() {
        try {
            // Paket dizinini oluştur
            if (!mkdir($this->packageDir, 0755, true)) {
                throw new Exception('Paket dizini oluşturulamadı');
            }

            // Alt dizinleri oluştur
            mkdir($this->packageDir . '/files', 0755, true);
            mkdir($this->packageDir . '/migrations', 0755, true);

            // Migration dosyalarını kopyala
            foreach ($this->migrations as $migration) {
                $source = 'setup/' . $migration;
                $target = $this->packageDir . '/migrations/' . $migration;

                if (file_exists($source)) {
                    copy($source, $target);
                }
            }

            // Dosyaları kopyala
            foreach ($this->files as $file) {
                $source = $file['source'];
                $target = $this->packageDir . '/files/' . $file['target'];

                // Hedef dizini oluştur
                $targetDir = dirname($target);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                if (file_exists($source)) {
                    copy($source, $target);
                }
            }

            // Yapılandırma dosyasını oluştur
            $config = [
                'version' => $this->version,
                'description' => $this->description,
                'created_at' => date('Y-m-d H:i:s'),
                'migrations' => $this->migrations,
                'files' => $this->files
            ];

            file_put_contents(
                $this->packageDir . '/update_config.json',
                json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // ZIP dosyası oluştur
            $zipFile = $this->packageDir . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('ZIP dosyası oluşturulamadı');
            }

            // Tüm dosyaları ZIP'e ekle
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->packageDir)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($this->packageDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $relativePath = str_replace('\\', '/', $relativePath);
                    $zip->addFile($file->getPathname(), $relativePath);
                }
            }

            $zip->close();

            // Geçici dizini temizle
            $this->deleteDirectory($this->packageDir);

            return [
                'success' => true,
                'file' => $zipFile,
                'size' => filesize($zipFile)
            ];

        } catch (Exception $e) {
            // Temizlik
            if (is_dir($this->packageDir)) {
                $this->deleteDirectory($this->packageDir);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function getChangedFiles($basePath = '.') {
        $changedFiles = [];

        // Git kullanarak değişen dosyaları bul (git varsa)
        if (is_dir('.git')) {
            $output = shell_exec('git diff --name-only HEAD~1 2>/dev/null');
            if ($output) {
                $changedFiles = array_filter(explode("\n", trim($output)));
            }
        }

        // Alternatif: Son değişiklik tarihine göre
        if (empty($changedFiles)) {
            $recentFiles = $this->getRecentlyModifiedFiles($basePath, 7); // Son 7 gün
            $changedFiles = $recentFiles;
        }

        return $changedFiles;
    }

    private function getRecentlyModifiedFiles($dir, $days = 7) {
        $files = [];
        $cutoff = time() - ($days * 24 * 60 * 60);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() > $cutoff) {
                $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                if (!$this->isExcluded($file->getPathname())) {
                    $files[] = $relativePath;
                }
            }
        }

        return $files;
    }
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    echo "=== Güncelleme Paketleme Scripti ===\n\n";

    // Parametreleri al
    $version = $argv[1] ?? null;
    $description = $argv[2] ?? null;

    if (!$version) {
        echo "Versiyon numarası giriniz (örn: 1.2.0): ";
        $version = trim(fgets(STDIN));
    }

    if (!$description) {
        echo "Güncelleme açıklaması giriniz: ";
        $description = trim(fgets(STDIN));
    }

    $packager = new UpdatePackager($version, $description);

    // Değişen dosyaları otomatik tespit et
    echo "Değişen dosyalar tespit ediliyor...\n";
    $changedFiles = $packager->getChangedFiles();

    if (!empty($changedFiles)) {
        echo "Tespit edilen değişiklikler:\n";
        foreach ($changedFiles as $file) {
            echo "  - $file\n";
            $packager->addFile($file);
        }
        echo "\n";
    } else {
        echo "Otomatik tespit başarısız, tüm dosyalar eklenecek...\n";
        $packager->addDirectory('admin');
        $packager->addDirectory('api');
        $packager->addDirectory('includes');
        $packager->addFile('index.php');
        $packager->addFile('view-quote.php');
    }

    // Migration dosyalarını ekle
    echo "Migration dosyaları kontrol ediliyor...\n";
    $migrationFiles = glob('setup/*.sql');
    foreach ($migrationFiles as $migration) {
        if (filemtime($migration) > time() - (30 * 24 * 60 * 60)) { // Son 30 gün
            $packager->addMigration(basename($migration));
            echo "  + Migration eklendi: " . basename($migration) . "\n";
        }
    }

    // Paketi oluştur
    echo "\nGüncelleme paketi oluşturuluyor...\n";
    $result = $packager->createPackage();

    if ($result['success']) {
        echo "✓ Paket başarıyla oluşturuldu!\n";
        echo "  Dosya: " . $result['file'] . "\n";
        echo "  Boyut: " . number_format($result['size'] / 1024, 2) . " KB\n";
        echo "\nBu dosyayı admin panelindeki 'Sistem Güncelleme' bölümünden yükleyebilirsiniz.\n";
    } else {
        echo "✗ Hata: " . $result['error'] . "\n";
        exit(1);
    }
}

// Web arayüzü kullanımı
else if (isset($_POST['create_package'])) {
    header('Content-Type: application/json');

    $version = $_POST['version'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($version) || empty($description)) {
        echo json_encode(['success' => false, 'error' => 'Versiyon ve açıklama gerekli']);
        exit;
    }

    $packager = new UpdatePackager($version, $description);

    // Seçilen dosyaları ekle
    if (isset($_POST['files']) && is_array($_POST['files'])) {
        foreach ($_POST['files'] as $file) {
            $packager->addFile($file);
        }
    }

    // Seçilen migration'ları ekle
    if (isset($_POST['migrations']) && is_array($_POST['migrations'])) {
        foreach ($_POST['migrations'] as $migration) {
            $packager->addMigration($migration);
        }
    }

    $result = $packager->createPackage();
    echo json_encode($result);
    exit;
}

// Web arayüzü göster
else {
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Güncelleme Paketi Oluştur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .container { padding: 2rem 0; }
        .card {
            border: none;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 20px 20px 0 0 !important;
            border-bottom: 1px solid rgba(102, 126, 234, 0.2);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
        }
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-box-open me-2"></i>Güncelleme Paketi Oluştur
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="packageForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Versiyon</label>
                                    <input type="text" class="form-control" name="version" placeholder="1.2.0" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Açıklama</label>
                                    <input type="text" class="form-control" name="description" placeholder="Yeni özellikler ve düzeltmeler" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dahil Edilecek Dosyalar</label>
                                <div id="filesList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Dosyalar yükleniyor...
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Migration Dosyaları</label>
                                <div id="migrationsList" class="border rounded p-3">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin"></i> Migration'lar yükleniyor...
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-cogs me-2"></i>Paketi Oluştur
                            </button>
                        </form>

                        <div id="result" class="mt-4" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadFiles();
            loadMigrations();

            document.getElementById('packageForm').addEventListener('submit', function(e) {
                e.preventDefault();
                createPackage();
            });
        });

        function loadFiles() {
            // Bu kısım gerçek implementasyonda AJAX ile dosya listesi çekilecek
            const filesList = document.getElementById('filesList');
            filesList.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="files[]" value="admin/index.php" checked>
                    <label class="form-check-label">admin/index.php</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="files[]" value="admin/update-manager.php" checked>
                    <label class="form-check-label">admin/update-manager.php</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="files[]" value="index.php">
                    <label class="form-check-label">index.php</label>
                </div>
            `;
        }

        function loadMigrations() {
            const migrationsList = document.getElementById('migrationsList');
            migrationsList.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="migrations[]" value="add-update-system.sql">
                    <label class="form-check-label">add-update-system.sql</label>
                </div>
                <small class="text-muted">Son 30 gün içinde değişen migration dosyaları</small>
            `;
        }

        function createPackage() {
            const formData = new FormData(document.getElementById('packageForm'));
            formData.append('create_package', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const result = document.getElementById('result');
                result.style.display = 'block';

                if (data.success) {
                    result.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Paket başarıyla oluşturuldu!<br>
                            <strong>Dosya:</strong> ${data.file}<br>
                            <strong>Boyut:</strong> ${(data.size / 1024).toFixed(2)} KB
                            <hr>
                            <a href="${data.file}" class="btn btn-success btn-sm">
                                <i class="fas fa-download me-1"></i>İndir
                            </a>
                        </div>
                    `;
                } else {
                    result.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Hata: ${data.error}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
    <?php
}
?>
