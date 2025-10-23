<?php
/**
 * Git Release Package Creator
 * GitHub release için otomatik paket oluşturma aracı
 */

class GitPackageCreator {
    private $projectRoot;
    private $version;
    private $config;
    private $excludedPaths = [];

    public function __construct() {
        $this->projectRoot = dirname(__DIR__);
        $this->loadConfig();
    }

    /**
     * Config dosyasını yükle
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/auto-update-config.json';

        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
            $this->excludedPaths = $this->config['excluded_paths'] ?? [];
        } else {
            $this->excludedPaths = [
                'uploads/',
                'backups/',
                'updates/',
                'vendor/',
                '.git/',
                '*.log',
                '*.backup.*',
                'config/database.php',
                'tools/auto-update-config.json',
                '.github/',
                'temp_*',
                '*.zip'
            ];
        }
    }

    /**
     * Mevcut versiyonu al
     */
    private function getCurrentVersion() {
        $versionFile = $this->projectRoot . '/version.txt';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return '1.0.0';
    }

    /**
     * Yeni versiyon oluştur
     */
    public function createNewVersion($type = 'patch') {
        $current = $this->getCurrentVersion();
        $parts = explode('.', $current);

        switch ($type) {
            case 'major':
                $parts[0] = (int)$parts[0] + 1;
                $parts[1] = 0;
                $parts[2] = 0;
                break;
            case 'minor':
                $parts[1] = (int)$parts[1] + 1;
                $parts[2] = 0;
                break;
            case 'patch':
            default:
                $parts[2] = (int)$parts[2] + 1;
                break;
        }

        $this->version = implode('.', $parts);
        return $this->version;
    }

    /**
     * Version dosyasını güncelle
     */
    public function updateVersionFile() {
        $versionFile = $this->projectRoot . '/version.txt';
        file_put_contents($versionFile, $this->version);
        return true;
    }

    /**
     * Release paketi oluştur
     */
    public function createReleasePackage($description = '') {
        try {
            if (!$this->version) {
                throw new Exception('Versiyon belirtilmedi');
            }

            // Paket adı
            $packageName = "erhan-nakliye-teklif-v{$this->version}";
            $packageDir = $this->projectRoot . "/temp_{$packageName}";
            $packageZip = $this->projectRoot . "/{$packageName}.zip";

            // Önceki paketleri temizle
            if (file_exists($packageZip)) {
                unlink($packageZip);
            }
            if (is_dir($packageDir)) {
                $this->deleteDirectory($packageDir);
            }

            // Geçici dizin oluştur
            mkdir($packageDir, 0755, true);

            // Dosyaları kopyala
            $this->copyFiles($this->projectRoot, $packageDir);

            // Örnek config dosyası oluştur
            if (file_exists($packageDir . '/config/database.php')) {
                copy(
                    $packageDir . '/config/database.php',
                    $packageDir . '/config/database.example.php'
                );
                unlink($packageDir . '/config/database.php');
            }

            // README dosyası oluştur
            $this->createReadme($packageDir, $description);

            // update_config.json oluştur
            $this->createUpdateConfig($packageDir, $description);

            // ZIP oluştur
            $zip = new ZipArchive();
            if ($zip->open($packageZip, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('ZIP dosyası oluşturulamadı');
            }

            $this->addDirectoryToZip($zip, $packageDir, '');
            $zip->close();

            // Geçici dizini temizle
            $this->deleteDirectory($packageDir);

            // Changelog oluştur
            $changelog = $this->generateChangelog();

            return [
                'success' => true,
                'version' => $this->version,
                'file' => $packageZip,
                'size' => filesize($packageZip),
                'changelog' => $changelog
            ];

        } catch (Exception $e) {
            // Temizlik
            if (isset($packageDir) && is_dir($packageDir)) {
                $this->deleteDirectory($packageDir);
            }

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Dosyaları kopyala
     */
    private function copyFiles($source, $destination) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $sourcePath = $item->getPathname();
            $relativePath = str_replace($source . '/', '', $sourcePath);

            // Windows path ayarı
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativePath = str_replace($source . '\\', '', $relativePath);

            // Hariç tutulacak dosyaları kontrol et
            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            $targetPath = $destination . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Dosyanın hariç tutulup tutulmayacağını kontrol et
     */
    private function shouldExclude($path) {
        // Kendimizi hariç tut
        if (strpos($path, 'temp_erhan-nakliye-teklif') === 0) {
            return true;
        }

        foreach ($this->excludedPaths as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
            // Dizin kontrolü
            if (strpos($path, rtrim($pattern, '/') . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * ZIP'e dizin ekle
     */
    private function addDirectoryToZip($zip, $dir, $zipPath) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = str_replace($dir . '/', '', $filePath);
                $relativePath = str_replace($dir . '\\', '', $relativePath);
                $relativePath = str_replace('\\', '/', $relativePath);

                $zip->addFile($filePath, $zipPath . $relativePath);
            }
        }
    }

    /**
     * README dosyası oluştur
     */
    private function createReadme($dir, $description) {
        $readme = "# Nakliye Teklif Sistemi v{$this->version}\n\n";

        if ($description) {
            $readme .= "## Bu Sürümdeki Yenilikler\n\n{$description}\n\n";
        }

        $readme .= "## Kurulum\n\n";
        $readme .= "1. Dosyaları web sunucunuza yükleyin\n";
        $readme .= "2. `config/database.example.php` dosyasını `config/database.php` olarak kopyalayın\n";
        $readme .= "3. Veritabanı bağlantı bilgilerinizi güncelleyin\n";
        $readme .= "4. Admin paneline giriş yaparak sistem ayarlarını yapılandırın\n\n";

        $readme .= "## Güncelleme\n\n";
        $readme .= "Mevcut bir sistemi güncelliyorsanız:\n";
        $readme .= "1. Mevcut sisteminizin yedeğini alın\n";
        $readme .= "2. Admin panel > Sistem Güncelleme bölümünden bu paketi yükleyin\n";
        $readme .= "3. Otomatik güncelleme işlemi tamamlanacaktır\n\n";

        $readme .= "## Sistem Gereksinimleri\n\n";
        $readme .= "- PHP 7.4 veya üzeri\n";
        $readme .= "- MySQL 5.7 veya üzeri\n";
        $readme .= "- PHP Extensions: PDO, ZIP, JSON, CURL\n\n";

        $readme .= "## Destek\n\n";
        $readme .= "Sorunlar için GitHub Issues bölümünü kullanabilirsiniz.\n";

        file_put_contents($dir . '/README.md', $readme);
    }

    /**
     * Update config dosyası oluştur
     */
    private function createUpdateConfig($dir, $description) {
        // setup/ klasöründeki migration dosyalarını bul
        $migrations = [];
        $setupDir = $dir . '/setup';

        if (is_dir($setupDir)) {
            $files = scandir($setupDir);
            foreach ($files as $file) {
                if (preg_match('/^migration_v[\d_]+\.php$/', $file)) {
                    $migrations[] = $file;
                }
            }
        }

        // Config array oluştur
        $config = [
            'version' => $this->version,
            'release_date' => date('Y-m-d H:i:s'),
            'description' => $description ?: "Versiyon {$this->version} güncellemesi",
            'php_version' => '7.4.0',
            'mysql_version' => '5.7.0',
            'migrations' => $migrations,
            'files' => [
                [
                    'source' => 'index.php',
                    'target' => 'index.php',
                    'backup' => false
                ],
                [
                    'source' => 'admin/',
                    'target' => 'admin/',
                    'backup' => false
                ],
                [
                    'source' => 'api/',
                    'target' => 'api/',
                    'backup' => false
                ],
                [
                    'source' => 'assets/',
                    'target' => 'assets/',
                    'backup' => false
                ],
                [
                    'source' => 'includes/',
                    'target' => 'includes/',
                    'backup' => false
                ],
                [
                    'source' => 'setup/',
                    'target' => 'setup/',
                    'backup' => false
                ],
                [
                    'source' => 'tools/',
                    'target' => 'tools/',
                    'backup' => false
                ],
                [
                    'source' => 'templates/',
                    'target' => 'templates/',
                    'backup' => false
                ],
                [
                    'source' => 'version.txt',
                    'target' => 'version.txt',
                    'backup' => false
                ]
            ],
            'protected_files' => [
                'config/database.php',
                'uploads/',
                'backups/',
                '.git/',
                '.htaccess'
            ],
            'post_update_scripts' => [],
            'notes' => [
                'Güncelleme öncesi mutlaka yedek alın',
                'Güncelleme sırasında site geçici olarak erişilemez olabilir',
                'Veritabanı migration\'ları otomatik olarak çalıştırılacaktır'
            ]
        ];

        file_put_contents(
            $dir . '/update_config.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Changelog oluştur
     */
    private function generateChangelog() {
        $changelog = "# Changelog - v{$this->version}\n\n";
        $changelog .= "Release Date: " . date('Y-m-d') . "\n\n";

        // Git log'dan değişiklikleri al
        $gitLog = shell_exec("cd {$this->projectRoot} && git log --oneline --no-merges -20 2>&1");

        if ($gitLog && strpos($gitLog, 'fatal:') === false) {
            $changelog .= "## Recent Changes\n\n";
            $lines = explode("\n", trim($gitLog));
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $changelog .= "- " . preg_replace('/^[a-f0-9]+ /', '', $line) . "\n";
                }
            }
        }

        return $changelog;
    }

    /**
     * Dizini sil
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
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

    /**
     * Git commit ve tag oluştur
     */
    public function createGitRelease($message = '') {
        if (!$this->version) {
            return ['success' => false, 'error' => 'Versiyon belirtilmedi'];
        }

        $commands = [
            "cd {$this->projectRoot}",
            "git add -A",
            "git commit -m \"Release v{$this->version}" . ($message ? ": {$message}" : "") . "\"",
            "git tag -a v{$this->version} -m \"Version {$this->version}" . ($message ? ": {$message}" : "") . "\"",
            "git push origin main",
            "git push origin v{$this->version}"
        ];

        $output = [];
        foreach ($commands as $cmd) {
            $result = shell_exec($cmd . " 2>&1");
            $output[] = $cmd . "\n" . $result;
        }

        return [
            'success' => true,
            'output' => implode("\n\n", $output)
        ];
    }
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    echo "=== Git Release Package Creator ===\n\n";

    $creator = new GitPackageCreator();

    // Parametreleri al
    $action = $argv[1] ?? 'create';

    switch ($action) {
        case 'create':
            $versionType = $argv[2] ?? 'patch';
            $description = $argv[3] ?? '';

            echo "Yeni versiyon oluşturuluyor ({$versionType})...\n";
            $version = $creator->createNewVersion($versionType);
            echo "Yeni versiyon: v{$version}\n\n";

            echo "Paket oluşturuluyor...\n";
            $result = $creator->createReleasePackage($description);

            if ($result['success']) {
                echo "✓ Paket oluşturuldu!\n";
                echo "  Dosya: " . basename($result['file']) . "\n";
                echo "  Boyut: " . number_format($result['size'] / 1024 / 1024, 2) . " MB\n\n";

                // Version dosyasını güncelle
                $creator->updateVersionFile();
                echo "✓ Version dosyası güncellendi\n\n";

                echo "Git release oluşturmak ister misiniz? (y/n): ";
                $answer = trim(fgets(STDIN));

                if (strtolower($answer) === 'y') {
                    echo "\nGit release oluşturuluyor...\n";
                    $gitResult = $creator->createGitRelease($description);
                    echo $gitResult['output'] . "\n";

                    if ($gitResult['success']) {
                        echo "\n✓ Git release oluşturuldu!\n";
                        echo "GitHub Actions otomatik olarak release package'ı oluşturacak.\n";
                    }
                }
            } else {
                echo "✗ Hata: " . $result['error'] . "\n";
            }
            break;

        case 'version':
            echo "Mevcut versiyon: v" . $creator->getCurrentVersion() . "\n";
            break;

        default:
            echo "Kullanım: php git-package-creator.php [create|version] [major|minor|patch] [description]\n";
            echo "Örnek: php git-package-creator.php create patch \"Bug fixes and improvements\"\n";
            exit(1);
    }
}
?>
