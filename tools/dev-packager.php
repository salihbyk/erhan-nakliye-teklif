<?php
/**
 * Yerel Geliştirme Paket Oluşturucu
 * Değişiklik yaptığınızda hızlıca paket oluşturmak için
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class DevPackager {
    private $version;
    private $description;
    private $packageName;
    private $excludedPaths = [
        'tools/',
        'temp_update_*',
        'backups/',
        'uploads/',
        'vendor/',
        '.git/',
        '.gitignore',
        'README.md',
        'composer.json',
        'composer.lock',
        '*.log',
        '*.backup.*',
        '*.bat',
        '*.zip',
        'config/database.php',  // Config dosyalarını koru
        'europagr_teklif.sql',
        'check_db.bat',
        'import_db.bat',
        'test_connection.php'
    ];

    public function __construct() {
        $this->loadCurrentVersion();
    }

    private function loadCurrentVersion() {
        $versionFile = '../version.txt';
        if (file_exists($versionFile)) {
            $this->version = trim(file_get_contents($versionFile));
        } else {
            $this->version = '1.0.0';
        }
    }

    public function incrementVersion($type = 'patch') {
        $parts = explode('.', $this->version);
        $major = (int)($parts[0] ?? 1);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $this->version = "$major.$minor.$patch";

        // version.txt dosyasını güncelle
        file_put_contents('../version.txt', $this->version);

        return $this->version;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    public function createPackage() {
        try {
            $timestamp = time();
            $this->packageName = "update_package_{$this->version}_{$timestamp}";

            echo "📦 Paket oluşturuluyor: {$this->packageName}\n";
            echo "🔢 Versiyon: {$this->version}\n";
            echo "📝 Açıklama: {$this->description}\n\n";

            // Geçici dizin oluştur
            $packageDir = $this->packageName;
            if (!mkdir($packageDir, 0755, true)) {
                throw new Exception('Paket dizini oluşturulamadı');
            }

            // Alt dizinleri oluştur
            mkdir($packageDir . '/files', 0755, true);
            mkdir($packageDir . '/migrations', 0755, true);

            // Dosyaları kopyala (config hariç)
            $this->copyProjectFiles($packageDir . '/files');

            // Son migration dosyalarını ekle
            $this->copyRecentMigrations($packageDir . '/migrations');

            // Yapılandırma dosyası oluştur
            $this->createConfigFile($packageDir);

            // ZIP oluştur
            $zipFile = $this->createZipFile($packageDir);

            // Geçici dizini temizle
            $this->deleteDirectory($packageDir);

            return [
                'success' => true,
                'file' => $zipFile,
                'version' => $this->version,
                'size' => filesize($zipFile)
            ];

        } catch (Exception $e) {
            if (isset($packageDir) && is_dir($packageDir)) {
                $this->deleteDirectory($packageDir);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function copyProjectFiles($targetDir) {
        $sourceDir = '..';
        $copiedFiles = [];

        echo "📁 Proje dosyaları kopyalanıyor...\n";

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($sourceDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                if (!$this->isExcluded($relativePath)) {
                    $targetPath = $targetDir . '/' . $relativePath;
                    $targetDirPath = dirname($targetPath);

                    if (!is_dir($targetDirPath)) {
                        mkdir($targetDirPath, 0755, true);
                    }

                    if (copy($file->getPathname(), $targetPath)) {
                        $copiedFiles[] = $relativePath;
                        echo "  ✓ {$relativePath}\n";
                    }
                }
            }
        }

        echo "📊 Toplam {" . count($copiedFiles) . "} dosya kopyalandı\n\n";
        return $copiedFiles;
    }

    private function copyRecentMigrations($targetDir) {
        $setupDir = '../setup';
        $migrationFiles = [];

        echo "🔄 Migration dosyaları kontrol ediliyor...\n";

        if (is_dir($setupDir)) {
            $files = glob($setupDir . '/*.sql');
            $recentFiles = [];

            foreach ($files as $file) {
                // Son 30 gün içinde değişen migration dosyaları
                if (filemtime($file) > time() - (30 * 24 * 60 * 60)) {
                    $recentFiles[] = $file;
                }
            }

            foreach ($recentFiles as $file) {
                $filename = basename($file);
                if (copy($file, $targetDir . '/' . $filename)) {
                    $migrationFiles[] = $filename;
                    echo "  ✓ {$filename}\n";
                }
            }
        }

        if (empty($migrationFiles)) {
            echo "  ℹ️  Yeni migration dosyası bulunamadı\n";
        }

        echo "\n";
        return $migrationFiles;
    }

    private function createConfigFile($packageDir) {
        $config = [
            'version' => $this->version,
            'description' => $this->description,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'dev-packager',
            'migrations' => $this->getMigrationFiles($packageDir . '/migrations'),
            'files' => $this->getFileList($packageDir . '/files'),
            'config_protection' => true, // Config dosyalarını koru
            'excluded_paths' => $this->excludedPaths
        ];

        $configFile = $packageDir . '/update_config.json';
        file_put_contents(
            $configFile,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        echo "⚙️  Yapılandırma dosyası oluşturuldu\n";
    }

    private function getMigrationFiles($migrationDir) {
        $migrations = [];
        if (is_dir($migrationDir)) {
            $files = scandir($migrationDir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                    $migrations[] = $file;
                }
            }
        }
        return $migrations;
    }

    private function getFileList($filesDir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($filesDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($filesDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                $files[] = [
                    'source' => $relativePath,
                    'target' => $relativePath
                ];
            }
        }

        return $files;
    }

    private function createZipFile($packageDir) {
        $zipFile = $this->packageName . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('ZIP dosyası oluşturulamadı');
        }

        echo "🗜️  ZIP dosyası oluşturuluyor...\n";

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($packageDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
        echo "  ✓ {$zipFile} oluşturuldu\n\n";

        return $zipFile;
    }

    private function isExcluded($path) {
        foreach ($this->excludedPaths as $excludedPath) {
            if (fnmatch($excludedPath, $path)) {
                return true;
            }
        }
        return false;
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
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    echo "🚀 Yerel Geliştirme Paket Oluşturucu\n";
    echo "=====================================\n\n";

    $packager = new DevPackager();

    // Versiyon türünü sor
    echo "Versiyon artırma türü:\n";
    echo "1) Patch (1.0.0 -> 1.0.1) - Küçük düzeltmeler\n";
    echo "2) Minor (1.0.0 -> 1.1.0) - Yeni özellikler\n";
    echo "3) Major (1.0.0 -> 2.0.0) - Büyük değişiklikler\n";
    echo "Seçiminiz (1-3) [1]: ";

    $choice = trim(fgets(STDIN));
    if (empty($choice)) $choice = '1';

    $versionType = match($choice) {
        '2' => 'minor',
        '3' => 'major',
        default => 'patch'
    };

    // Versiyonu artır
    $newVersion = $packager->incrementVersion($versionType);
    echo "🔢 Yeni versiyon: {$newVersion}\n\n";

    // Açıklama al
    echo "Güncelleme açıklaması: ";
    $description = trim(fgets(STDIN));
    if (empty($description)) {
        $description = "Versiyon {$newVersion} güncellemesi";
    }

    $packager->setDescription($description);

    // Paketi oluştur
    echo "\n📦 Paket oluşturuluyor...\n";
    echo "========================\n";

    $result = $packager->createPackage();

    if ($result['success']) {
        echo "✅ Paket başarıyla oluşturuldu!\n\n";
        echo "📁 Dosya: {$result['file']}\n";
        echo "🔢 Versiyon: {$result['version']}\n";
        echo "📊 Boyut: " . number_format($result['size'] / 1024, 2) . " KB\n\n";
        echo "🎯 Bu dosyayı admin panelindeki 'Sistem Güncelleme' bölümünden yükleyebilirsiniz.\n";
        echo "🔗 URL: http://localhost/erhan/admin/update-manager.php\n";
    } else {
        echo "❌ Hata: {$result['error']}\n";
        exit(1);
    }
}

// Web arayüzü için AJAX endpoint
else if (isset($_POST['action']) && $_POST['action'] === 'create_dev_package') {
    header('Content-Type: application/json');

    $versionType = $_POST['version_type'] ?? 'patch';
    $description = $_POST['description'] ?? '';

    if (empty($description)) {
        echo json_encode(['success' => false, 'error' => 'Açıklama gerekli']);
        exit;
    }

    $packager = new DevPackager();
    $newVersion = $packager->incrementVersion($versionType);
    $packager->setDescription($description);

    $result = $packager->createPackage();
    $result['version'] = $newVersion;

    echo json_encode($result);
    exit;
}
?>
