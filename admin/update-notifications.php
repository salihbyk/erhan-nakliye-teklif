<?php
/**
 * Güncelleme Bildirimleri Sistemi
 * Admin panelinde WordPress benzeri güncelleme bildirimleri
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/update-functions.php';

class UpdateNotifications {
    private $updateServerUrl;
    private $projectId;
    private $currentVersion;
    private $checkInterval = 3600; // 1 saat

    public function __construct() {
        $this->updateServerUrl = getSystemSetting('update_server_url', 'https://updates.your-domain.com/api');
        $this->projectId = getSystemSetting('project_id', 'nakliye-teklif-system');
        $this->currentVersion = getCurrentSystemVersion();
    }

    /**
     * Güncellemeleri kontrol et
     */
    public function checkForUpdates($force = false) {
        // Son kontrol zamanını al
        $lastCheck = getSystemSetting('last_update_check', 0);
        $currentTime = time();

        // Zorunlu değilse ve yakın zamanda kontrol edilmişse atla
        if (!$force && ($currentTime - $lastCheck) < $this->checkInterval) {
            return $this->getCachedUpdateInfo();
        }

        try {
            // Güncelleme sunucusunu kontrol et
            $updateInfo = $this->fetchUpdateInfo();

            if ($updateInfo['success']) {
                // Cache'e kaydet
                updateSystemSetting('last_update_check', $currentTime);
                updateSystemSetting('cached_update_info', json_encode($updateInfo['data']));

                // Bildirim durumunu güncelle
                if ($updateInfo['data']['update_available']) {
                    updateSystemSetting('update_notification_shown', 0);
                }

                return $updateInfo['data'];
            } else {
                return ['update_available' => false, 'error' => $updateInfo['error']];
            }

        } catch (Exception $e) {
            return ['update_available' => false, 'error' => $e->getMessage()];
        }
    }

        /**
     * Güncelleme sunucusundan bilgi al
     */
    private function fetchUpdateInfo() {
        // Şimdilik offline mode - gerçek sunucu olmadığı için
        // Demo amaçlı sabit yanıt döndürür

        // Gerçek implementasyon için:
        if (strpos($this->updateServerUrl, 'localhost') !== false ||
            strpos($this->updateServerUrl, 'your-domain.com') !== false) {

            // Demo/test modu - güncelleme yok
            return [
                'success' => true,
                'data' => [
                    'update_available' => false,
                    'latest_version' => $this->currentVersion,
                    'current_version' => $this->currentVersion,
                    'message' => 'Test modu - güncel'
                ]
            ];
        }

        $url = $this->updateServerUrl . '/check-updates?' . http_build_query([
            'project_id' => $this->projectId,
            'current_version' => $this->currentVersion
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Nakliye-Teklif-System/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Bağlantı hatası: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'Sunucu hatası: HTTP ' . $httpCode];
        }

        if (empty($response)) {
            return ['success' => false, 'error' => 'Boş yanıt alındı'];
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'JSON parse hatası: ' . json_last_error_msg()];
        }

        return ['success' => true, 'data' => $data];
    }

    /**
     * Cache'lenmiş güncelleme bilgilerini al
     */
    private function getCachedUpdateInfo() {
        $cached = getSystemSetting('cached_update_info', '{}');
        return json_decode($cached, true) ?: ['update_available' => false];
    }

    /**
     * Güncelleme bildirimini göster
     */
    public function showUpdateNotification() {
        $updateInfo = $this->checkForUpdates();

        if (!$updateInfo['update_available']) {
            return '';
        }

        // Bildirim zaten gösterildi mi?
        $notificationShown = getSystemSetting('update_notification_shown', 0);
        if ($notificationShown) {
            return '';
        }

        $latestVersion = $updateInfo['latest_version'];
        $currentVersion = $updateInfo['current_version'];
        $description = $updateInfo['update_info']['description'] ?? 'Yeni güncelleme mevcut';

        return $this->renderNotificationHTML($latestVersion, $currentVersion, $description, $updateInfo);
    }

    /**
     * Bildirim HTML'ini oluştur
     */
    private function renderNotificationHTML($latestVersion, $currentVersion, $description, $updateInfo) {
        ob_start();
        ?>
        <div id="update-notification" class="alert alert-info alert-dismissible fade show position-sticky"
             style="top: 0; z-index: 1050; border-radius: 0; margin: 0; border: none;
                    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
                    color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="alert-heading mb-1">
                            <i class="fas fa-cloud-download-alt me-2"></i>
                            Yeni Güncelleme Mevcut!
                        </h6>
                        <p class="mb-0">
                            <strong>v<?= htmlspecialchars($latestVersion) ?></strong> versiyonu hazır.
                            Mevcut: v<?= htmlspecialchars($currentVersion) ?>
                        </p>
                        <small class="opacity-75"><?= htmlspecialchars($description) ?></small>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light btn-sm me-2" onclick="viewUpdateDetails()">
                            <i class="fas fa-info-circle me-1"></i>Detayları Gör
                        </button>
                        <button class="btn btn-warning btn-sm me-2" onclick="installUpdate()">
                            <i class="fas fa-download me-1"></i>Şimdi Güncelle
                        </button>
                        <button class="btn btn-outline-light btn-sm" onclick="dismissNotification()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Güncelleme Detayları Modal -->
        <div class="modal fade" id="updateDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Güncelleme Detayları - v<?= htmlspecialchars($latestVersion) ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Mevcut Versiyon:</strong> v<?= htmlspecialchars($currentVersion) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Yeni Versiyon:</strong> v<?= htmlspecialchars($latestVersion) ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <strong>Açıklama:</strong>
                            <p class="text-muted"><?= htmlspecialchars($description) ?></p>
                        </div>

                        <?php if (isset($updateInfo['update_info']['changelog'])): ?>
                        <div class="mb-3">
                            <strong>Değişiklikler:</strong>
                            <div class="changelog">
                                <?php
                                $changelog = $updateInfo['update_info']['changelog'];
                                if (isset($changelog['added']) && !empty($changelog['added'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-success me-2">Eklenen</span>
                                        <ul class="list-unstyled ms-3">
                                            <?php foreach ($changelog['added'] as $file): ?>
                                                <li><i class="fas fa-plus text-success me-1"></i><?= htmlspecialchars($file) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($changelog['modified']) && !empty($changelog['modified'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-warning me-2">Değiştirilen</span>
                                        <ul class="list-unstyled ms-3">
                                            <?php foreach ($changelog['modified'] as $file): ?>
                                                <li><i class="fas fa-edit text-warning me-1"></i><?= htmlspecialchars($file) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($changelog['deleted']) && !empty($changelog['deleted'])): ?>
                                    <div class="mb-2">
                                        <span class="badge bg-danger me-2">Silinen</span>
                                        <ul class="list-unstyled ms-3">
                                            <?php foreach ($changelog['deleted'] as $file): ?>
                                                <li><i class="fas fa-minus text-danger me-1"></i><?= htmlspecialchars($file) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Önemli:</strong> Güncelleme işlemi sırasında otomatik yedekleme alınacaktır.
                            İşlem tamamlanana kadar sayfayı kapatmayın.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="button" class="btn btn-primary" onclick="installUpdate()">
                            <i class="fas fa-download me-1"></i>Güncellemeyi Yükle
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Güncelleme Progress Modal -->
        <div class="modal fade" id="updateProgressModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-cogs text-primary me-2"></i>
                            Güncelleme Yükleniyor...
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="mb-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                        </div>
                        <div id="updateStatus">Güncelleme başlatılıyor...</div>
                        <div class="progress mt-3">
                            <div id="updateProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Güncelleme bildirimi JavaScript fonksiyonları
        function viewUpdateDetails() {
            const modal = new bootstrap.Modal(document.getElementById('updateDetailsModal'));
            modal.show();
        }

        function dismissNotification() {
            // Bildirimi gizle ve sunucuya bildir
            document.getElementById('update-notification').style.display = 'none';

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=dismiss_notification'
            });
        }

        function installUpdate() {
            // Modal'ları kapat
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('updateDetailsModal'));
            if (detailsModal) detailsModal.hide();

            // Progress modal'ını göster
            const progressModal = new bootstrap.Modal(document.getElementById('updateProgressModal'));
            progressModal.show();

            // Güncellemeyi başlat
            updateStatus('Güncelleme indiriliyor...', 20);

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=install_update'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatus('Güncelleme başarıyla tamamlandı!', 100);

                    setTimeout(() => {
                        progressModal.hide();
                        document.getElementById('update-notification').style.display = 'none';

                        // Başarı mesajı göster
                        showSuccessMessage('Güncelleme başarıyla yüklendi! Sayfa yenileniyor...');

                        // Sayfayı yenile
                        setTimeout(() => location.reload(), 2000);
                    }, 1500);
                } else {
                    updateStatus('Hata: ' + data.error, 0);
                    setTimeout(() => progressModal.hide(), 3000);
                }
            })
            .catch(error => {
                updateStatus('Bağlantı hatası: ' + error.message, 0);
                setTimeout(() => progressModal.hide(), 3000);
            });
        }

        function updateStatus(message, progress) {
            document.getElementById('updateStatus').textContent = message;
            document.getElementById('updateProgressBar').style.width = progress + '%';
        }

        function showSuccessMessage(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 2000; min-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
        }
        </script>
        <?php
        return ob_get_clean();
    }

        /**
     * AJAX isteklerini işle
     */
    public function handleAjaxRequest() {
        // JSON header ayarla
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_POST['action'])) {
            echo json_encode(['success' => false, 'error' => 'Action parameter missing']);
            exit;
        }

        try {
            switch ($_POST['action']) {
                case 'dismiss_notification':
                    updateSystemSetting('update_notification_shown', 1);
                    echo json_encode(['success' => true]);
                    break;

                case 'install_update':
                    $this->handleUpdateInstallation();
                    break;

                case 'check_updates':
                    $updateInfo = $this->checkForUpdates(true);
                    echo json_encode(['success' => true, 'data' => $updateInfo]);
                    break;

                default:
                    echo json_encode(['success' => false, 'error' => 'Invalid action']);
                    break;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Güncelleme yükleme işlemini gerçekleştir
     */
    private function handleUpdateInstallation() {
        try {
            require_once __DIR__ . '/../tools/auto-update-system.php';

            $updateSystem = new AutoUpdateSystem([
                'update_server_url' => $this->updateServerUrl,
                'project_id' => $this->projectId
            ]);

            // Güncel güncelleme bilgilerini al
            $updateInfo = $this->checkForUpdates(true);

            if (!$updateInfo['update_available']) {
                throw new Exception('Güncelleme mevcut değil');
            }

            // Güncellemeyi indir ve yükle
            $result = $updateSystem->downloadAndInstallUpdate($updateInfo);

            if ($result['success']) {
                // Başarılı güncelleme sonrası temizlik
                updateSystemSetting('update_notification_shown', 1);
                updateSystemSetting('last_update_check', 0); // Yeni kontrol için sıfırla

                echo json_encode([
                    'success' => true,
                    'message' => 'Güncelleme başarıyla yüklendi'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}

// AJAX istekleri için
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $notifications = new UpdateNotifications();
        $notifications->handleAjaxRequest();
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Initialization error: ' . $e->getMessage()]);
        exit;
    }
}
?>
