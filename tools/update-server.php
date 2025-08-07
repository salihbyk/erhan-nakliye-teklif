<?php
/**
 * Güncelleme Sunucusu API
 * WordPress benzeri güncelleme dağıtım sunucusu
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

class UpdateServer {
    private $updatesDir;
    private $projectsFile;
    private $allowedProjects;

    public function __construct() {
        $this->updatesDir = __DIR__ . '/updates-repository';
        $this->projectsFile = $this->updatesDir . '/projects.json';

        if (!is_dir($this->updatesDir)) {
            mkdir($this->updatesDir, 0755, true);
        }

        $this->loadProjects();
    }

    private function loadProjects() {
        if (file_exists($this->projectsFile)) {
            $this->allowedProjects = json_decode(file_get_contents($this->projectsFile), true) ?? [];
        } else {
            $this->allowedProjects = [
                'nakliye-teklif-system' => [
                    'name' => 'Nakliye Teklif Sistemi',
                    'api_key' => hash('sha256', 'nakliye-teklif-system' . time()),
                    'latest_version' => '1.0.0',
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
            $this->saveProjects();
        }
    }

    private function saveProjects() {
        file_put_contents($this->projectsFile, json_encode($this->allowedProjects, JSON_PRETTY_PRINT));
    }

    /**
     * API route handler
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $endpoint = basename($path);

        try {
            switch ($endpoint) {
                case 'check-updates':
                    return $this->checkUpdates();

                case 'upload-update':
                    return $this->uploadUpdate();

                case 'download-update':
                    return $this->downloadUpdate();

                case 'get-projects':
                    return $this->getProjects();

                case 'create-project':
                    return $this->createProject();

                default:
                    return $this->error('Geçersiz endpoint', 404);
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Güncellemeleri kontrol et
     */
    private function checkUpdates() {
        $projectId = $_GET['project_id'] ?? '';
        $currentVersion = $_GET['current_version'] ?? '';

        if (empty($projectId) || empty($currentVersion)) {
            return $this->error('project_id ve current_version gerekli');
        }

        if (!isset($this->allowedProjects[$projectId])) {
            return $this->error('Geçersiz proje ID');
        }

        $project = $this->allowedProjects[$projectId];
        $latestVersion = $project['latest_version'];

        // Versiyon karşılaştırma
        if (version_compare($currentVersion, $latestVersion, '<')) {
            $updateInfo = $this->getUpdateInfo($projectId, $latestVersion);

            return $this->success([
                'update_available' => true,
                'latest_version' => $latestVersion,
                'current_version' => $currentVersion,
                'update_info' => $updateInfo,
                'download_url' => $this->getDownloadUrl($projectId, $latestVersion)
            ]);
        } else {
            return $this->success([
                'update_available' => false,
                'latest_version' => $latestVersion,
                'current_version' => $currentVersion,
                'message' => 'Sisteminiz güncel'
            ]);
        }
    }

    /**
     * Güncelleme yükle
     */
    private function uploadUpdate() {
        $projectId = $_POST['project_id'] ?? '';
        $apiKey = $_POST['api_key'] ?? '';
        $version = $_POST['version'] ?? '';
        $description = $_POST['description'] ?? '';
        $changelog = $_POST['changelog'] ?? '';

        // Kimlik doğrulama
        if (!$this->authenticate($projectId, $apiKey)) {
            return $this->error('Kimlik doğrulama başarısız', 401);
        }

        if (empty($version) || empty($description)) {
            return $this->error('version ve description gerekli');
        }

        // Dosya yükleme kontrolü
        if (!isset($_FILES['package']) || $_FILES['package']['error'] !== UPLOAD_ERR_OK) {
            return $this->error('Paket dosyası yüklenemedi');
        }

        $uploadedFile = $_FILES['package'];

        // Dosya türü kontrolü
        if (pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) !== 'zip') {
            return $this->error('Sadece ZIP dosyaları kabul edilir');
        }

        // Proje dizini oluştur
        $projectDir = $this->updatesDir . '/' . $projectId;
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        // Dosyayı kaydet
        $fileName = $projectId . '_' . $version . '.zip';
        $filePath = $projectDir . '/' . $fileName;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            return $this->error('Dosya kaydedilemedi');
        }

        // Update bilgilerini kaydet
        $updateInfo = [
            'version' => $version,
            'description' => $description,
            'changelog' => json_decode($changelog, true),
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'uploaded_at' => date('Y-m-d H:i:s'),
            'checksum' => hash_file('sha256', $filePath)
        ];

        $this->saveUpdateInfo($projectId, $version, $updateInfo);

        // Proje bilgilerini güncelle
        $this->allowedProjects[$projectId]['latest_version'] = $version;
        $this->allowedProjects[$projectId]['last_update'] = date('Y-m-d H:i:s');
        $this->saveProjects();

        return $this->success([
            'message' => 'Güncelleme başarıyla yüklendi',
            'version' => $version,
            'file_size' => $updateInfo['file_size']
        ]);
    }

    /**
     * Güncelleme indir
     */
    private function downloadUpdate() {
        $projectId = $_GET['project_id'] ?? '';
        $version = $_GET['version'] ?? '';

        if (empty($projectId) || empty($version)) {
            return $this->error('project_id ve version gerekli');
        }

        if (!isset($this->allowedProjects[$projectId])) {
            return $this->error('Geçersiz proje ID');
        }

        $fileName = $projectId . '_' . $version . '.zip';
        $filePath = $this->updatesDir . '/' . $projectId . '/' . $fileName;

        if (!file_exists($filePath)) {
            return $this->error('Güncelleme dosyası bulunamadı');
        }

        // Dosyayı indir
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }

    /**
     * Projeleri listele
     */
    private function getProjects() {
        $projects = [];
        foreach ($this->allowedProjects as $id => $project) {
            $projects[] = [
                'id' => $id,
                'name' => $project['name'],
                'latest_version' => $project['latest_version'],
                'created_at' => $project['created_at']
            ];
        }

        return $this->success(['projects' => $projects]);
    }

    /**
     * Yeni proje oluştur
     */
    private function createProject() {
        $name = $_POST['name'] ?? '';
        $id = $_POST['id'] ?? '';

        if (empty($name) || empty($id)) {
            return $this->error('name ve id gerekli');
        }

        if (isset($this->allowedProjects[$id])) {
            return $this->error('Bu ID zaten kullanılıyor');
        }

        $this->allowedProjects[$id] = [
            'name' => $name,
            'api_key' => hash('sha256', $id . time() . rand()),
            'latest_version' => '1.0.0',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->saveProjects();

        return $this->success([
            'message' => 'Proje oluşturuldu',
            'project_id' => $id,
            'api_key' => $this->allowedProjects[$id]['api_key']
        ]);
    }

    /**
     * Kimlik doğrulama
     */
    private function authenticate($projectId, $apiKey) {
        if (!isset($this->allowedProjects[$projectId])) {
            return false;
        }

        return $this->allowedProjects[$projectId]['api_key'] === $apiKey;
    }

    /**
     * Update bilgilerini kaydet
     */
    private function saveUpdateInfo($projectId, $version, $updateInfo) {
        $infoFile = $this->updatesDir . '/' . $projectId . '/' . $version . '.json';
        file_put_contents($infoFile, json_encode($updateInfo, JSON_PRETTY_PRINT));
    }

    /**
     * Update bilgilerini al
     */
    private function getUpdateInfo($projectId, $version) {
        $infoFile = $this->updatesDir . '/' . $projectId . '/' . $version . '.json';

        if (file_exists($infoFile)) {
            return json_decode(file_get_contents($infoFile), true);
        }

        return null;
    }

    /**
     * Download URL oluştur
     */
    private function getDownloadUrl($projectId, $version) {
        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $currentPath = dirname($_SERVER['REQUEST_URI']);

        return $baseUrl . $currentPath . '/download-update?' . http_build_query([
            'project_id' => $projectId,
            'version' => $version
        ]);
    }

    /**
     * Başarılı yanıt
     */
    private function success($data) {
        return json_encode(['success' => true] + $data);
    }

    /**
     * Hata yanıtı
     */
    private function error($message, $code = 400) {
        http_response_code($code);
        return json_encode(['success' => false, 'error' => $message]);
    }
}

// API çalıştır
$server = new UpdateServer();
echo $server->handleRequest();
?>
