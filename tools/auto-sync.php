<?php
/**
 * Otomatik Senkronizasyon Scripti
 * Bu script localhost ile sunucu arasında dosya senkronizasyonu yapar
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class AutoSyncManager {
    private $config;
    private $logFile;

    public function __construct($configFile = 'sync-config.json') {
        $this->logFile = 'sync.log';
        $this->loadConfig($configFile);
    }

    private function loadConfig($configFile) {
        if (!file_exists($configFile)) {
            $this->createDefaultConfig($configFile);
        }

        $this->config = json_decode(file_get_contents($configFile), true);
        if (!$this->config) {
            throw new Exception('Geçersiz yapılandırma dosyası');
        }
    }

    private function createDefaultConfig($configFile) {
        $defaultConfig = [
            'sync_methods' => [
                'ftp' => [
                    'enabled' => false,
                    'host' => '',
                    'username' => '',
                    'password' => '',
                    'remote_path' => '/public_html/',
                    'port' => 21,
                    'passive' => true
                ],
                'sftp' => [
                    'enabled' => false,
                    'host' => '',
                    'username' => '',
                    'password' => '',
                    'private_key' => '',
                    'remote_path' => '/var/www/html/',
                    'port' => 22
                ],
                'rsync' => [
                    'enabled' => false,
                    'remote_host' => '',
                    'remote_user' => '',
                    'remote_path' => '/var/www/html/',
                    'ssh_key' => ''
                ]
            ],
            'local_path' => '../',
            'excluded_paths' => [
                'tools/',
                'temp_*',
                'backups/',
                'uploads/',
                'vendor/',
                '.git/',
                '*.log',
                '*.backup.*',
                'config/database.php'
            ],
            'auto_backup' => true,
            'compression' => true,
            'dry_run' => false
        ];

        file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        $this->log("Varsayılan yapılandırma dosyası oluşturuldu: $configFile");
    }

    public function syncToServer($method = null) {
        $this->log("Senkronizasyon başlatıldı");

        try {
            // Hangi yöntem kullanılacak
            if (!$method) {
                $method = $this->getAvailableMethod();
            }

            if (!$method) {
                throw new Exception('Hiçbir senkronizasyon yöntemi aktif değil');
            }

            $this->log("Senkronizasyon yöntemi: $method");

            // Değişen dosyaları tespit et
            $changedFiles = $this->getChangedFiles();
            $this->log("Değişen dosya sayısı: " . count($changedFiles));

            if (empty($changedFiles)) {
                $this->log("Senkronize edilecek dosya bulunamadı");
                return ['success' => true, 'message' => 'Hiç değişiklik yok'];
            }

            // Yedekleme
            if ($this->config['auto_backup']) {
                $this->createBackup();
            }

            // Senkronizasyon
            switch ($method) {
                case 'ftp':
                    return $this->syncViaFTP($changedFiles);
                case 'sftp':
                    return $this->syncViaSFTP($changedFiles);
                case 'rsync':
                    return $this->syncViaRsync($changedFiles);
                default:
                    throw new Exception("Desteklenmeyen yöntem: $method");
            }

        } catch (Exception $e) {
            $this->log("Hata: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getAvailableMethod() {
        foreach ($this->config['sync_methods'] as $method => $config) {
            if ($config['enabled']) {
                return $method;
            }
        }
        return null;
    }

    private function getChangedFiles() {
        $changedFiles = [];
        $basePath = $this->config['local_path'];

        // Git kullanarak değişen dosyaları bul
        if (is_dir($basePath . '.git')) {
            $output = shell_exec("cd $basePath && git diff --name-only HEAD~1 2>/dev/null");
            if ($output) {
                $gitFiles = array_filter(explode("\n", trim($output)));
                foreach ($gitFiles as $file) {
                    if (!$this->isExcluded($file)) {
                        $changedFiles[] = $file;
                    }
                }
            }
        }

        // Alternatif: Son değişiklik tarihine göre
        if (empty($changedFiles)) {
            $changedFiles = $this->getRecentlyModifiedFiles($basePath, 1); // Son 1 gün
        }

        return $changedFiles;
    }

    private function getRecentlyModifiedFiles($dir, $days = 1) {
        $files = [];
        $cutoff = time() - ($days * 24 * 60 * 60);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() > $cutoff) {
                $relativePath = str_replace($dir, '', $file->getPathname());
                $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

                if (!$this->isExcluded($relativePath)) {
                    $files[] = $relativePath;
                }
            }
        }

        return $files;
    }

    private function isExcluded($path) {
        foreach ($this->config['excluded_paths'] as $excludedPath) {
            if (fnmatch($excludedPath, $path)) {
                return true;
            }
        }
        return false;
    }

    private function syncViaFTP($files) {
        $config = $this->config['sync_methods']['ftp'];

        $connection = ftp_connect($config['host'], $config['port']);
        if (!$connection) {
            throw new Exception("FTP bağlantısı başarısız: " . $config['host']);
        }

        if (!ftp_login($connection, $config['username'], $config['password'])) {
            throw new Exception("FTP giriş başarısız");
        }

        if ($config['passive']) {
            ftp_pasv($connection, true);
        }

        $successCount = 0;
        $errors = [];

        foreach ($files as $file) {
            $localFile = $this->config['local_path'] . $file;
            $remoteFile = $config['remote_path'] . $file;

            if (!file_exists($localFile)) {
                continue;
            }

            // Uzak dizini oluştur
            $remoteDir = dirname($remoteFile);
            $this->createFTPDirectory($connection, $remoteDir);

            if ($this->config['dry_run']) {
                $this->log("DRY RUN: $localFile -> $remoteFile");
                $successCount++;
            } else {
                if (ftp_put($connection, $remoteFile, $localFile, FTP_BINARY)) {
                    $this->log("Yüklendi: $file");
                    $successCount++;
                } else {
                    $error = "FTP yükleme başarısız: $file";
                    $this->log($error);
                    $errors[] = $error;
                }
            }
        }

        ftp_close($connection);

        return [
            'success' => empty($errors),
            'uploaded_files' => $successCount,
            'errors' => $errors
        ];
    }

    private function createFTPDirectory($connection, $dir) {
        $parts = explode('/', trim($dir, '/'));
        $currentDir = '';

        foreach ($parts as $part) {
            $currentDir .= '/' . $part;
            @ftp_mkdir($connection, $currentDir);
        }
    }

    private function syncViaSFTP($files) {
        // SFTP implementasyonu (phpseclib gerektirir)
        if (!class_exists('phpseclib3\\Net\\SFTP')) {
            throw new Exception('SFTP için phpseclib3 gerekli');
        }

        $config = $this->config['sync_methods']['sftp'];

        $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port']);

        if (!empty($config['private_key'])) {
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($config['private_key']));
            if (!$sftp->login($config['username'], $key)) {
                throw new Exception("SFTP anahtar ile giriş başarısız");
            }
        } else {
            if (!$sftp->login($config['username'], $config['password'])) {
                throw new Exception("SFTP parola ile giriş başarısız");
            }
        }

        $successCount = 0;
        $errors = [];

        foreach ($files as $file) {
            $localFile = $this->config['local_path'] . $file;
            $remoteFile = $config['remote_path'] . $file;

            if (!file_exists($localFile)) {
                continue;
            }

            // Uzak dizini oluştur
            $remoteDir = dirname($remoteFile);
            $sftp->mkdir($remoteDir, -1, true);

            if ($this->config['dry_run']) {
                $this->log("DRY RUN: $localFile -> $remoteFile");
                $successCount++;
            } else {
                if ($sftp->put($remoteFile, $localFile, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
                    $this->log("Yüklendi: $file");
                    $successCount++;
                } else {
                    $error = "SFTP yükleme başarısız: $file";
                    $this->log($error);
                    $errors[] = $error;
                }
            }
        }

        return [
            'success' => empty($errors),
            'uploaded_files' => $successCount,
            'errors' => $errors
        ];
    }

    private function syncViaRsync($files) {
        $config = $this->config['sync_methods']['rsync'];

        // Rsync komutu oluştur
        $localPath = rtrim($this->config['local_path'], '/') . '/';
        $remotePath = $config['remote_user'] . '@' . $config['remote_host'] . ':' . $config['remote_path'];

        $excludeOptions = '';
        foreach ($this->config['excluded_paths'] as $exclude) {
            $excludeOptions .= " --exclude='$exclude'";
        }

        $sshOptions = '';
        if (!empty($config['ssh_key'])) {
            $sshOptions = "-e 'ssh -i " . $config['ssh_key'] . "'";
        }

        $dryRunOption = $this->config['dry_run'] ? '--dry-run' : '';

        $command = "rsync -avz --delete $dryRunOption $excludeOptions $sshOptions $localPath $remotePath 2>&1";

        $this->log("Rsync komutu: $command");

        $output = shell_exec($command);
        $this->log("Rsync çıktısı: $output");

        // Rsync çıktısını analiz et
        $lines = explode("\n", $output);
        $uploadedFiles = 0;
        $errors = [];

        foreach ($lines as $line) {
            if (strpos($line, 'error') !== false || strpos($line, 'failed') !== false) {
                $errors[] = $line;
            } elseif (preg_match('/^[^\/]*\s+\d+\s+/', $line)) {
                $uploadedFiles++;
            }
        }

        return [
            'success' => empty($errors),
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'output' => $output
        ];
    }

    private function createBackup() {
        $backupDir = 'backups/pre-sync';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.tar.gz';

        $command = "tar -czf $backupFile --exclude='backups' --exclude='tools' --exclude='temp_*' " . $this->config['local_path'];
        shell_exec($command);

        $this->log("Yedekleme oluşturuldu: $backupFile");
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }

    public function getConfig() {
        return $this->config;
    }

    public function updateConfig($newConfig) {
        $this->config = array_merge($this->config, $newConfig);
        file_put_contents('sync-config.json', json_encode($this->config, JSON_PRETTY_PRINT));
        return true;
    }
}

// CLI kullanımı
if (php_sapi_name() === 'cli') {
    echo "=== Otomatik Senkronizasyon Scripti ===\n\n";

    try {
        $syncManager = new AutoSyncManager();

        // Parametreleri al
        $method = $argv[1] ?? null;
        $dryRun = isset($argv[2]) && $argv[2] === '--dry-run';

        if ($dryRun) {
            $config = $syncManager->getConfig();
            $config['dry_run'] = true;
            $syncManager->updateConfig($config);
            echo "DRY RUN modu aktif\n\n";
        }

        $result = $syncManager->syncToServer($method);

        if ($result['success']) {
            echo "✓ Senkronizasyon başarılı!\n";
            echo "  Yüklenen dosya sayısı: " . ($result['uploaded_files'] ?? 0) . "\n";

            if (isset($result['output'])) {
                echo "  Detay:\n" . $result['output'] . "\n";
            }
        } else {
            echo "✗ Senkronizasyon başarısız!\n";
            echo "  Hata: " . $result['error'] . "\n";

            if (!empty($result['errors'])) {
                echo "  Hatalar:\n";
                foreach ($result['errors'] as $error) {
                    echo "    - $error\n";
                }
            }
            exit(1);
        }

    } catch (Exception $e) {
        echo "✗ Hata: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Web arayüzü kullanımı
else if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $syncManager = new AutoSyncManager();

        switch ($_POST['action']) {
            case 'sync':
                $method = $_POST['method'] ?? null;
                $result = $syncManager->syncToServer($method);
                echo json_encode($result);
                break;

            case 'get_config':
                echo json_encode(['success' => true, 'config' => $syncManager->getConfig()]);
                break;

            case 'update_config':
                $newConfig = json_decode($_POST['config'], true);
                $result = $syncManager->updateConfig($newConfig);
                echo json_encode(['success' => $result]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Web arayüzü göster
else {
    $syncManager = new AutoSyncManager();
    $config = $syncManager->getConfig();
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otomatik Senkronizasyon</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-sync-alt me-2"></i>Otomatik Senkronizasyon
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Senkronizasyon Yöntemleri</h6>

                                <!-- FTP -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="ftp_enabled"
                                                   <?= $config['sync_methods']['ftp']['enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="ftp_enabled">
                                                <strong>FTP</strong>
                                            </label>
                                        </div>
                                        <div class="ftp-config mt-2">
                                            <input type="text" class="form-control form-control-sm mb-2"
                                                   placeholder="FTP Host" value="<?= $config['sync_methods']['ftp']['host'] ?>">
                                            <input type="text" class="form-control form-control-sm mb-2"
                                                   placeholder="Kullanıcı Adı" value="<?= $config['sync_methods']['ftp']['username'] ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- SFTP -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="sftp_enabled"
                                                   <?= $config['sync_methods']['sftp']['enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="sftp_enabled">
                                                <strong>SFTP</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rsync -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rsync_enabled"
                                                   <?= $config['sync_methods']['rsync']['enabled'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="rsync_enabled">
                                                <strong>Rsync</strong>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6>Senkronizasyon İşlemleri</h6>

                                <button class="btn btn-primary btn-lg w-100 mb-3" onclick="startSync()">
                                    <i class="fas fa-upload me-2"></i>Şimdi Senkronize Et
                                </button>

                                <button class="btn btn-outline-secondary w-100 mb-3" onclick="startSync(true)">
                                    <i class="fas fa-eye me-2"></i>Test Et (Dry Run)
                                </button>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="auto_backup"
                                           <?= $config['auto_backup'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="auto_backup">
                                        Otomatik yedekleme
                                    </label>
                                </div>

                                <div id="syncResult" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function startSync(dryRun = false) {
            const button = event.target;
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Senkronize ediliyor...';

            const formData = new FormData();
            formData.append('action', 'sync');
            if (dryRun) formData.append('dry_run', '1');

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const result = document.getElementById('syncResult');

                if (data.success) {
                    result.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Senkronizasyon başarılı!<br>
                            Yüklenen dosya: ${data.uploaded_files || 0}
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

                button.disabled = false;
                button.innerHTML = originalText;
            })
            .catch(error => {
                console.error('Error:', error);
                button.disabled = false;
                button.innerHTML = originalText;
            });
        }
    </script>
</body>
</html>
    <?php
}
?>
