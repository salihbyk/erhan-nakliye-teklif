<?php
/**
 * Git Tabanlı Otomatik Güncelleme Sistemi
 * GitHub'dan otomatik güncelleme kontrolü ve yükleme
 */

class GitAutoUpdate {
    private $repoOwner = 'salihbyk'; // GitHub kullanıcı adınız
    private $repoName = 'erhan-nakliye-teklif'; // Repository adı
    private $currentVersion;
    private $updateDir;
    private $backupDir;
    private $projectRoot;
    private $githubToken; // Opsiyonel - private repo için
    private $config;

    public function __construct($config = []) {
        // Config dosyasını yükle
        $this->loadConfig();

        // Parametrelerle override et
        $this->repoOwner = $config['repo_owner'] ?? $this->config['github']['owner'];
        $this->repoName = $config['repo_name'] ?? $this->config['github']['repo'];
        $this->githubToken = $config['github_token'] ?? $this->config['github']['token'];

        $this->projectRoot = dirname(__DIR__);
        $this->updateDir = $this->projectRoot . '/updates';
        $this->backupDir = $this->projectRoot . '/backups';
        $this->currentVersion = $this->getCurrentVersion();

        // Dizinleri oluştur
        if (!is_dir($this->updateDir)) {
            mkdir($this->updateDir, 0755, true);
        }
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Config dosyasını yükle
     */
    private function loadConfig() {
        $configFile = __DIR__ . '/auto-update-config.json';

        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true);
        } else {
            // Varsayılan config
            $this->config = [
                'github' => [
                    'owner' => 'salihbyk',
                    'repo' => 'erhan-nakliye-teklif',
                    'token' => null
                ],
                'settings' => [
                    'backup_before_update' => true
                ],
                'excluded_paths' => []
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
     * GitHub'dan en son release'i kontrol et
     */
    public function checkForUpdates() {
        try {
            $url = "https://api.github.com/repos/{$this->repoOwner}/{$this->repoName}/releases/latest";

            $options = [
                'http' => [
                    'header' => [
                        'User-Agent: PHP',
                        'Accept: application/vnd.github.v3+json'
                    ]
                ]
            ];

            if ($this->githubToken) {
                $options['http']['header'][] = 'Authorization: Bearer ' . $this->githubToken;
            }

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                // Release yoksa tag'leri kontrol et
                return $this->checkForUpdatesByTags();
            }

            $release = json_decode($response, true);

            if (!$release || !isset($release['tag_name'])) {
                throw new Exception('Release bilgisi alınamadı');
            }

            $latestVersion = ltrim($release['tag_name'], 'v');

            return [
                'success' => true,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => version_compare($latestVersion, $this->currentVersion, '>'),
                'download_url' => $release['zipball_url'] ?? null,
                'release_notes' => $release['body'] ?? '',
                'published_at' => $release['published_at'] ?? ''
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Tag'leri kontrol et (release yoksa)
     */
    private function checkForUpdatesByTags() {
        try {
            $url = "https://api.github.com/repos/{$this->repoOwner}/{$this->repoName}/tags";

            $options = [
                'http' => [
                    'header' => [
                        'User-Agent: PHP',
                        'Accept: application/vnd.github.v3+json'
                    ]
                ]
            ];

            if ($this->githubToken) {
                $options['http']['header'][] = 'Authorization: Bearer ' . $this->githubToken;
            }

            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('Tag bilgileri alınamadı');
            }

            $tags = json_decode($response, true);

            if (empty($tags)) {
                return [
                    'success' => true,
                    'current_version' => $this->currentVersion,
                    'latest_version' => $this->currentVersion,
                    'update_available' => false
                ];
            }

            // En yüksek versiyonu bul
            $versions = [];
            foreach ($tags as $tag) {
                $version = ltrim($tag['name'], 'v');
                if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                    $versions[] = $version;
                }
            }

            if (empty($versions)) {
                throw new Exception('Geçerli versiyon tag\'i bulunamadı');
            }

            usort($versions, 'version_compare');
            $latestVersion = end($versions);

            // İlgili tag'in bilgilerini bul
            $latestTag = null;
            foreach ($tags as $tag) {
                if (ltrim($tag['name'], 'v') === $latestVersion) {
                    $latestTag = $tag;
                    break;
                }
            }

            return [
                'success' => true,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => version_compare($latestVersion, $this->currentVersion, '>'),
                'download_url' => $latestTag ? $latestTag['zipball_url'] : null,
                'release_notes' => '',
                'published_at' => ''
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Güncellemeyi indir ve yükle
     */
    public function downloadAndInstall($updateInfo) {
        try {
            if (!$updateInfo['update_available']) {
                return [
                    'success' => false,
                    'error' => 'Güncelleme mevcut değil'
                ];
            }

            // Yedekleme yap
            $backupResult = $this->createBackup();
            if (!$backupResult['success']) {
                throw new Exception('Yedekleme başarısız: ' . $backupResult['error']);
            }

            // Güncellemeyi indir
            $downloadResult = $this->downloadUpdate($updateInfo['download_url'], $updateInfo['latest_version']);
            if (!$downloadResult['success']) {
                throw new Exception('İndirme başarısız: ' . $downloadResult['error']);
            }

            // Güncellemeyi uygula
            $installResult = $this->installUpdate($downloadResult['file'], $updateInfo['latest_version']);
            if (!$installResult['success']) {
                // Hata durumunda geri al
                $this->rollback($backupResult['backup_file']);
                throw new Exception('Yükleme başarısız: ' . $installResult['error']);
            }

            // Temizlik
            @unlink($downloadResult['file']);

            return [
                'success' => true,
                'message' => 'Güncelleme başarıyla yüklendi',
                'version' => $updateInfo['latest_version'],
                'backup' => $backupResult['backup_file']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Yedekleme oluştur
     */
    private function createBackup() {
        try {
            $backupName = 'backup_' . $this->currentVersion . '_' . date('YmdHis') . '.zip';
            $backupFile = $this->backupDir . '/' . $backupName;

            $zip = new ZipArchive();
            if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Yedekleme dosyası oluşturulamadı');
            }

            // Önemli dosya ve dizinleri yedekle
            $this->addToZip($zip, $this->projectRoot . '/admin');
            $this->addToZip($zip, $this->projectRoot . '/api');
            $this->addToZip($zip, $this->projectRoot . '/includes');
            $this->addToZip($zip, $this->projectRoot . '/assets');
            $this->addToZip($zip, $this->projectRoot . '/config');

            // Kök dizindeki PHP dosyalarını yedekle
            $files = glob($this->projectRoot . '/*.php');
            foreach ($files as $file) {
                $localPath = str_replace($this->projectRoot . '/', '', $file);
                $zip->addFile($file, $localPath);
            }

            $zip->close();

            return [
                'success' => true,
                'backup_file' => $backupFile
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ZIP'e dizin ekle
     */
    private function addToZip($zip, $dir, $baseDir = '') {
        if (!is_dir($dir)) return;

        if ($baseDir === '') {
            $baseDir = $this->projectRoot . '/';
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $localPath = str_replace($baseDir, '', $filePath);
                $localPath = str_replace('\\', '/', $localPath);

                // Belirli dosyaları hariç tut
                if ($this->shouldExclude($localPath)) {
                    continue;
                }

                $zip->addFile($filePath, $localPath);
            }
        }
    }

    /**
     * Dosyanın hariç tutulup tutulmayacağını kontrol et
     */
    private function shouldExclude($path) {
        $excludePatterns = $this->config['excluded_paths'] ?? [
            '/uploads/',
            '/backups/',
            '/updates/',
            '/vendor/',
            '/.git/',
            '/tools/auto-update.log',
            'config/database.php'
        ];

        foreach ($excludePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Güncellemeyi indir
     */
    private function downloadUpdate($downloadUrl, $version) {
        try {
            $tempFile = $this->updateDir . '/update_' . $version . '_' . time() . '.zip';

            $options = [
                'http' => [
                    'header' => [
                        'User-Agent: PHP',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'follow_location' => 1,
                    'timeout' => 300
                ]
            ];

            if ($this->githubToken) {
                $options['http']['header'][] = 'Authorization: Bearer ' . $this->githubToken;
            }

            $context = stream_context_create($options);
            $content = @file_get_contents($downloadUrl, false, $context);

            if ($content === false) {
                throw new Exception('Dosya indirilemedi');
            }

            if (file_put_contents($tempFile, $content) === false) {
                throw new Exception('Dosya kaydedilemedi');
            }

            return [
                'success' => true,
                'file' => $tempFile
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Güncellemeyi yükle
     */
    private function installUpdate($zipFile, $version) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception('ZIP dosyası açılamadı');
            }

            // Geçici dizine çıkar
            $tempDir = $this->updateDir . '/temp_' . time();
            mkdir($tempDir, 0755, true);

            $zip->extractTo($tempDir);
            $zip->close();

            // GitHub zip yapısını kontrol et (genelde owner-repo-hash/ şeklinde bir klasör içinde)
            $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
            if (count($dirs) === 1 && is_dir($dirs[0])) {
                $sourceDir = $dirs[0];
            } else {
                $sourceDir = $tempDir;
            }

            // Dosyaları kopyala
            $this->copyFiles($sourceDir, $this->projectRoot);

            // Migration dosyalarını çalıştır
            $this->runMigrations($sourceDir);

            // Version dosyasını güncelle
            file_put_contents($this->projectRoot . '/version.txt', $version);

            // Geçici dizini temizle
            $this->deleteDirectory($tempDir);

            // Cache temizle
            $this->clearCache();

            return [
                'success' => true,
                'message' => 'Güncelleme başarıyla yüklendi'
            ];

        } catch (Exception $e) {
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
            $targetPath = $destination . '/' . $relativePath;

            // Belirli dosyaları kopyalama
            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // Hedef dizini oluştur
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                // Dosyayı kopyala
                copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Migration dosyalarını çalıştır
     */
    private function runMigrations($sourceDir) {
        $migrationDir = $sourceDir . '/setup';
        if (!is_dir($migrationDir)) {
            return;
        }

        // Database bağlantısını al
        require_once $this->projectRoot . '/config/database.php';

        $migrations = glob($migrationDir . '/*.sql');
        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration);
            if ($sql) {
                try {
                    $db->exec($sql);
                    error_log("Migration uygulandı: " . basename($migration));
                } catch (Exception $e) {
                    error_log("Migration hatası: " . basename($migration) . " - " . $e->getMessage());
                }
            }
        }

        // PHP migration dosyaları
        $phpMigrations = glob($migrationDir . '/*.php');
        foreach ($phpMigrations as $migration) {
            try {
                require_once $migration;
                error_log("PHP Migration çalıştırıldı: " . basename($migration));
            } catch (Exception $e) {
                error_log("PHP Migration hatası: " . basename($migration) . " - " . $e->getMessage());
            }
        }
    }

    /**
     * Geri alma işlemi
     */
    private function rollback($backupFile) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($backupFile) === TRUE) {
                $zip->extractTo($this->projectRoot);
                $zip->close();
                return true;
            }
        } catch (Exception $e) {
            error_log("Rollback hatası: " . $e->getMessage());
        }
        return false;
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
     * Cache temizle
     */
    private function clearCache() {
        // OPcache varsa temizle
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * Güncellemeleri otomatik kontrol et (cron job için)
     */
    public function autoCheck() {
        $logFile = $this->projectRoot . '/tools/auto-update.log';

        $updateInfo = $this->checkForUpdates();

        $logEntry = date('Y-m-d H:i:s') . ' - ';

        if ($updateInfo['success']) {
            if ($updateInfo['update_available']) {
                $logEntry .= "Yeni güncelleme mevcut: v{$updateInfo['latest_version']}\n";

                // Admin'e bildirim gönder
                $this->notifyAdmin($updateInfo);
            } else {
                $logEntry .= "Sistem güncel (v{$updateInfo['current_version']})\n";
            }
        } else {
            $logEntry .= "Kontrol hatası: {$updateInfo['error']}\n";
        }

        file_put_contents($logFile, $logEntry, FILE_APPEND);

        return $updateInfo;
    }

    /**
     * Admin'e bildirim gönder
     */
    private function notifyAdmin($updateInfo) {
        // Bildirim dosyası oluştur
        $notificationFile = $this->projectRoot . '/admin/update-notification.json';

        $notification = [
            'timestamp' => time(),
            'current_version' => $updateInfo['current_version'],
            'latest_version' => $updateInfo['latest_version'],
            'release_notes' => $updateInfo['release_notes'],
            'published_at' => $updateInfo['published_at']
        ];

        file_put_contents($notificationFile, json_encode($notification, JSON_PRETTY_PRINT));
    }
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    $config = [
        'repo_owner' => $argv[2] ?? 'salihbyk',
        'repo_name' => $argv[3] ?? 'erhan-nakliye-teklif',
        'github_token' => $argv[4] ?? null
    ];

    $updater = new GitAutoUpdate($config);

    $action = $argv[1] ?? 'check';

    switch ($action) {
        case 'check':
            echo "Güncellemeler kontrol ediliyor...\n";
            $result = $updater->checkForUpdates();

            if ($result['success']) {
                echo "Mevcut versiyon: v{$result['current_version']}\n";
                echo "Son versiyon: v{$result['latest_version']}\n";

                if ($result['update_available']) {
                    echo "\n✓ Yeni güncelleme mevcut!\n";
                    if ($result['release_notes']) {
                        echo "\nSürüm notları:\n{$result['release_notes']}\n";
                    }
                } else {
                    echo "\n✓ Sistem güncel.\n";
                }
            } else {
                echo "✗ Hata: {$result['error']}\n";
            }
            break;

        case 'install':
            echo "Güncelleme kontrol ediliyor ve yükleniyor...\n";
            $updateInfo = $updater->checkForUpdates();

            if ($updateInfo['success'] && $updateInfo['update_available']) {
                echo "v{$updateInfo['latest_version']} yükleniyor...\n";

                $result = $updater->downloadAndInstall($updateInfo);

                if ($result['success']) {
                    echo "\n✓ {$result['message']}\n";
                    echo "Yeni versiyon: v{$result['version']}\n";
                    echo "Yedek dosyası: {$result['backup']}\n";
                } else {
                    echo "\n✗ Hata: {$result['error']}\n";
                }
            } else {
                echo "Güncelleme mevcut değil veya kontrol hatası.\n";
            }
            break;

        case 'auto':
            $result = $updater->autoCheck();
            // Cron job için sessiz çalışma
            exit($result['success'] ? 0 : 1);
            break;

        default:
            echo "Kullanım: php git-auto-update.php [check|install|auto] [repo-owner] [repo-name] [github-token]\n";
            echo "Örnek: php git-auto-update.php check myusername myrepo\n";
            exit(1);
    }
}
?>
