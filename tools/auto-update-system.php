<?php
/**
 * Otomatik Güncelleme Sistemi
 * WordPress benzeri otomatik güncelleme ve bildirim sistemi
 */

class AutoUpdateSystem {
    private $updateServerUrl;
    private $projectId;
    private $apiKey;
    private $localVersion;
    private $updatesDir;

    public function __construct($config = []) {
        $this->updateServerUrl = $config['update_server_url'] ?? 'https://updates.your-domain.com/api';
        $this->projectId = $config['project_id'] ?? 'nakliye-teklif-system';
        $this->apiKey = $config['api_key'] ?? $this->generateApiKey();
        $this->updatesDir = __DIR__ . '/../updates';
        $this->localVersion = $this->getCurrentVersion();

        if (!is_dir($this->updatesDir)) {
            mkdir($this->updatesDir, 0755, true);
        }
    }

    /**
     * Değişiklikleri tespit et ve güncelleme paketi oluştur
     */
    public function detectChangesAndCreateUpdate() {
        try {
            // Git değişikliklerini tespit et
            $changes = $this->detectGitChanges();

            if (empty($changes['files'])) {
                return ['success' => true, 'message' => 'Hiç değişiklik yok'];
            }

            // Yeni versiyon numarası oluştur
            $newVersion = $this->generateNewVersion();

            // Güncelleme paketi oluştur
            $packageResult = $this->createUpdatePackage($newVersion, $changes);

            if (!$packageResult['success']) {
                throw new Exception('Paket oluşturma başarısız: ' . $packageResult['error']);
            }

            // Güncelleme sunucusuna yükle
            $uploadResult = $this->uploadToUpdateServer($packageResult['file'], $newVersion, $changes);

            if (!$uploadResult['success']) {
                throw new Exception('Sunucuya yükleme başarısız: ' . $uploadResult['error']);
            }

            // Lokal versiyonu güncelle
            $this->updateLocalVersion($newVersion);

            // Bildirim gönder
            $this->notifyClients($newVersion, $changes['description']);

            return [
                'success' => true,
                'version' => $newVersion,
                'package' => $packageResult['file'],
                'changes' => $changes
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Git değişikliklerini tespit et
     */
    private function detectGitChanges() {
        $basePath = dirname(__DIR__);
        $changes = [
            'files' => [],
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'migrations' => [],
            'description' => ''
        ];

        // Git status kontrolü
        $gitStatus = shell_exec("cd $basePath && git status --porcelain 2>/dev/null");

        if (empty($gitStatus)) {
            // Son commit'ten beri değişiklik yok, son commit'i kontrol et
            $lastCommit = shell_exec("cd $basePath && git log -1 --name-status --pretty=format:'%s' 2>/dev/null");
            if ($lastCommit) {
                $lines = explode("\n", $lastCommit);
                $changes['description'] = array_shift($lines);

                foreach ($lines as $line) {
                    if (preg_match('/^([AMD])\s+(.+)$/', $line, $matches)) {
                        $status = $matches[1];
                        $file = $matches[2];

                        if (!$this->isExcludedFile($file)) {
                            $changes['files'][] = $file;

                            switch ($status) {
                                case 'A': $changes['added'][] = $file; break;
                                case 'M': $changes['modified'][] = $file; break;
                                case 'D': $changes['deleted'][] = $file; break;
                            }

                            if (strpos($file, 'setup/') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                                $changes['migrations'][] = basename($file);
                            }
                        }
                    }
                }
            }
        } else {
            // Uncommitted değişiklikler var
            $lines = explode("\n", trim($gitStatus));
            $changes['description'] = 'Otomatik güncelleme: ' . count($lines) . ' dosya değişti';

            foreach ($lines as $line) {
                if (preg_match('/^(.)(.)\s+(.+)$/', $line, $matches)) {
                    $file = $matches[3];

                    if (!$this->isExcludedFile($file)) {
                        $changes['files'][] = $file;
                        $changes['modified'][] = $file;

                        if (strpos($file, 'setup/') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                            $changes['migrations'][] = basename($file);
                        }
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * Dosyanın hariç tutulup tutulmayacağını kontrol et
     */
    private function isExcludedFile($file) {
        $excludedPatterns = [
            'tools/',
            'updates/',
            'backups/',
            'uploads/',
            'vendor/',
            '.git/',
            '*.log',
            '*.backup.*',
            'config/database.php',
            'sync-config.json',
            '*.zip'
        ];

        foreach ($excludedPatterns as $pattern) {
            if (fnmatch($pattern, $file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Yeni versiyon numarası oluştur
     */
    private function generateNewVersion() {
        $current = $this->localVersion;
        $parts = explode('.', $current);

        // Patch versiyon arttır
        $parts[2] = (int)$parts[2] + 1;

        return implode('.', $parts);
    }

    /**
     * Güncelleme paketi oluştur
     */
    private function createUpdatePackage($version, $changes) {
        require_once 'package-update.php';

        try {
            $packager = new UpdatePackager($version, $changes['description']);

            // Değişen dosyaları ekle
            foreach ($changes['files'] as $file) {
                $packager->addFile($file);
            }

            // Migration dosyalarını ekle
            foreach ($changes['migrations'] as $migration) {
                $packager->addMigration($migration);
            }

            $result = $packager->createPackage();

            if ($result['success']) {
                // Updates dizinine taşı
                $targetFile = $this->updatesDir . '/' . basename($result['file']);
                rename($result['file'], $targetFile);
                $result['file'] = $targetFile;
            }

            return $result;

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Güncelleme sunucusuna yükle
     */
    private function uploadToUpdateServer($packageFile, $version, $changes) {
        $postData = [
            'project_id' => $this->projectId,
            'api_key' => $this->apiKey,
            'version' => $version,
            'description' => $changes['description'],
            'changelog' => json_encode($changes),
            'package' => new CURLFile($packageFile)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->updateServerUrl . '/upload-update');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return $result ?: ['success' => true];
        } else {
            return ['success' => false, 'error' => "HTTP $httpCode: $response"];
        }
    }

    /**
     * İstemcilere bildirim gönder
     */
    private function notifyClients($version, $description) {
        $notification = [
            'type' => 'update_available',
            'version' => $version,
            'description' => $description,
            'timestamp' => time()
        ];

        // WebHook gönder (opsiyonel)
        $this->sendWebHook($notification);

        // Bildirim dosyası oluştur
        file_put_contents(
            $this->updatesDir . '/latest_notification.json',
            json_encode($notification, JSON_PRETTY_PRINT)
        );
    }

    /**
     * WebHook gönder
     */
    private function sendWebHook($data) {
        $webhookUrl = getenv('UPDATE_WEBHOOK_URL');
        if (!$webhookUrl) return;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Mevcut versiyonu al
     */
    private function getCurrentVersion() {
        $versionFile = dirname(__DIR__) . '/version.txt';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return '1.0.0';
    }

    /**
     * Lokal versiyonu güncelle
     */
    private function updateLocalVersion($version) {
        $versionFile = dirname(__DIR__) . '/version.txt';
        file_put_contents($versionFile, $version);
    }

    /**
     * API anahtarı oluştur
     */
    private function generateApiKey() {
        return hash('sha256', $this->projectId . time() . rand());
    }

    /**
     * Sunucudan güncellemeleri kontrol et
     */
    public function checkForUpdates() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->updateServerUrl . '/check-updates?' . http_build_query([
            'project_id' => $this->projectId,
            'current_version' => $this->localVersion
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return ['success' => false, 'error' => "HTTP $httpCode"];
    }

    /**
     * Güncellemeyi indir ve yükle
     */
    public function downloadAndInstallUpdate($updateInfo) {
        try {
            // Güncellemeyi indir
            $downloadResult = $this->downloadUpdate($updateInfo['download_url']);
            if (!$downloadResult['success']) {
                throw new Exception('İndirme başarısız: ' . $downloadResult['error']);
            }

            // Güncellemeyi yükle
            require_once dirname(__DIR__) . '/includes/update-functions.php';

            $installResult = $this->installUpdate($downloadResult['file'], $updateInfo);
            if (!$installResult['success']) {
                throw new Exception('Yükleme başarısız: ' . $installResult['error']);
            }

            return ['success' => true, 'message' => 'Güncelleme başarıyla yüklendi'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Güncellemeyi indir
     */
    private function downloadUpdate($downloadUrl) {
        $tempFile = $this->updatesDir . '/temp_update_' . time() . '.zip';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $data) {
            if (file_put_contents($tempFile, $data)) {
                return ['success' => true, 'file' => $tempFile];
            } else {
                return ['success' => false, 'error' => 'Dosya yazılamadı'];
            }
        } else {
            return ['success' => false, 'error' => "İndirme başarısız: HTTP $httpCode"];
        }
    }

    /**
     * Güncellemeyi yükle
     */
    private function installUpdate($zipFile, $updateInfo) {
        // Bu fonksiyon update-manager.php'deki kodu kullanacak
        // Simdilik basit bir implementasyon

        return ['success' => true, 'message' => 'Güncelleme yüklendi'];
    }
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    echo "=== Otomatik Güncelleme Sistemi ===\n\n";

    $action = $argv[1] ?? 'detect';

    $config = [
        'update_server_url' => $argv[2] ?? 'https://updates.your-domain.com/api',
        'project_id' => 'nakliye-teklif-system'
    ];

    $autoUpdate = new AutoUpdateSystem($config);

    switch ($action) {
        case 'detect':
            echo "Değişiklikler tespit ediliyor...\n";
            $result = $autoUpdate->detectChangesAndCreateUpdate();
            break;

        case 'check':
            echo "Güncellemeler kontrol ediliyor...\n";
            $result = $autoUpdate->checkForUpdates();
            break;

        default:
            echo "Kullanım: php auto-update-system.php [detect|check] [update-server-url]\n";
            exit(1);
    }

    if ($result['success']) {
        echo "✓ İşlem başarılı!\n";
        if (isset($result['version'])) {
            echo "  Versiyon: " . $result['version'] . "\n";
        }
        if (isset($result['message'])) {
            echo "  Mesaj: " . $result['message'] . "\n";
        }
    } else {
        echo "✗ Hata: " . $result['error'] . "\n";
        exit(1);
    }
}
?>
