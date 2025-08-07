<?php
// PHP 7.4 uyumluluk için error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
checkAdminSession();

try {
    $database = new Database();
    $db = $database->getConnection();

    // Form işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_cost_list') {
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $transport_mode_id = $_POST['transport_mode_id'] ?? null;

            if (empty($name)) {
                setErrorMessage('Maliyet listesi adı gereklidir.');
            } elseif (empty($_FILES['cost_file']['name'])) {
                setErrorMessage('Dosya seçilmelidir.');
            } else {
                $upload_dir = '../uploads/cost-lists/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file = $_FILES['cost_file'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'xlsx', 'xls', 'doc', 'docx', 'csv'];

                if (!in_array($file_extension, $allowed_extensions)) {
                    setErrorMessage('Sadece PDF, Excel, Word ve CSV dosyaları kabul edilir.');
                } else {
                    $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_name;
                    // Veritabanında saklanacak yol (ana dizinden erişilebilir)
                    $db_file_path = 'uploads/cost-lists/' . $unique_name;

                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $stmt = $db->prepare("
                            INSERT INTO cost_lists (name, description, file_name, file_path, file_size, mime_type, transport_mode_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");

                        if ($stmt->execute([
                            $name,
                            $description,
                            $file['name'],
                            $db_file_path,
                            $file['size'],
                            $file['type'],
                            $transport_mode_id ?: null
                        ])) {
                            setSuccessMessage('Maliyet listesi başarıyla yüklendi.');
                        } else {
                            setErrorMessage('Veritabanına kaydedilirken hata oluştu.');
                            unlink($file_path); // Dosyayı sil
                        }
                    } else {
                        setErrorMessage('Dosya yüklenirken hata oluştu.');
                    }
                }
            }
            header('Location: cost-lists.php');
            exit;
        }

        if ($action === 'delete_cost_list') {
            $id = $_POST['id'] ?? 0;

            // Önce dosya yolunu al
            $stmt = $db->prepare("SELECT file_path FROM cost_lists WHERE id = ?");
            $stmt->execute([$id]);
            $cost_list = $stmt->fetch();

            if ($cost_list) {
                // Dosyayı sil
                $full_file_path = '../' . $cost_list['file_path'];
                if (file_exists($full_file_path)) {
                    unlink($full_file_path);
                }

                // Veritabanından sil
                $stmt = $db->prepare("DELETE FROM cost_lists WHERE id = ?");
                if ($stmt->execute([$id])) {
                    setSuccessMessage('Maliyet listesi silindi.');
                } else {
                    setErrorMessage('Silme işleminde hata oluştu.');
                }
            }
            header('Location: cost-lists.php');
            exit;
        }

        if ($action === 'toggle_status') {
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("UPDATE cost_lists SET is_active = NOT is_active WHERE id = ?");
            if ($stmt->execute([$id])) {
                setSuccessMessage('Durum güncellendi.');
            }
            header('Location: cost-lists.php');
            exit;
        }
    }

    // Taşıma modlarını al
    $stmt = $db->query("SELECT * FROM transport_modes ORDER BY name ASC");
    $transport_modes = $stmt->fetchAll();

    // Maliyet listelerini al
    $stmt = $db->query("
        SELECT cl.*, tm.name as transport_mode_name
        FROM cost_lists cl
        LEFT JOIN transport_modes tm ON cl.transport_mode_id = tm.id
        ORDER BY cl.created_at DESC
    ");
    $cost_lists = $stmt->fetchAll();

} catch (Exception $e) {
    setErrorMessage('Veri yüklenirken hata oluştu: ' . $e->getMessage());
}

$messages = getMessages();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maliyet Listeleri - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        /* Modern sidebar included via external CSS */
        .main-content {
            padding: 30px;
        }
        .page-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .cost-list-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .cost-list-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .file-icon {
            font-size: 2rem;
            margin-right: 15px;
        }
        .file-info {
            flex: 1;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .upload-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="fas fa-file-excel text-primary me-2"></i>
                                Maliyet Listeleri
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Taşıma modları için maliyet listelerini yükleyin ve yönetin
                            </p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fas fa-upload me-1"></i> Yeni Liste Yükle
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $type => $message): ?>
                        <div class="alert alert-<?php echo $type === 'error' ? 'danger' : $type; ?> alert-dismissible fade show">
                            <i class="fas fa-<?php echo $type === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Cost Lists -->
                <div class="row">
                    <?php if (empty($cost_lists)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-file-excel fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">Henüz maliyet listesi yüklenmemiş</h4>
                                <p class="text-muted">İlk maliyet listenizi yüklemek için yukarıdaki butonu kullanın.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="fas fa-upload me-1"></i> İlk Listeyi Yükle
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cost_lists as $list): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="cost-list-card">
                                    <div class="d-flex align-items-start">
                                        <div class="file-icon text-primary">
                                            <?php
                                            $extension = strtolower(pathinfo($list['file_name'], PATHINFO_EXTENSION));
                                            switch($extension) {
                                                case 'pdf':
                                                    $icon = 'fas fa-file-pdf text-danger';
                                                    break;
                                                case 'xlsx':
                                                case 'xls':
                                                    $icon = 'fas fa-file-excel text-success';
                                                    break;
                                                case 'doc':
                                                case 'docx':
                                                    $icon = 'fas fa-file-word text-primary';
                                                    break;
                                                case 'csv':
                                                    $icon = 'fas fa-file-csv text-info';
                                                    break;
                                                default:
                                                    $icon = 'fas fa-file text-secondary';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="file-info">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($list['name']); ?></h5>
                                            <p class="text-muted mb-1 small"><?php echo htmlspecialchars($list['file_name']); ?></p>
                                            <?php if ($list['transport_mode_name']): ?>
                                                <span class="badge bg-primary mb-1">
                                                    <?php echo htmlspecialchars($list['transport_mode_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-<?php echo $list['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $list['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                            <?php if ($list['description']): ?>
                                                <p class="small text-muted mt-1 mb-0"><?php echo htmlspecialchars($list['description']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d.m.Y H:i', strtotime($list['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="file-actions mt-3">
                                        <a href="../<?php echo htmlspecialchars($list['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Görüntüle
                                        </a>
                                        <a href="../<?php echo htmlspecialchars($list['file_path']); ?>" download class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-download"></i> İndir
                                        </a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $list['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $list['is_active'] ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $list['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                <?php echo $list['is_active'] ? 'Pasifleştir' : 'Aktifleştir'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu maliyet listesini silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_cost_list">
                                            <input type="hidden" name="id" value="<?php echo $list['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i> Sil
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i> Yeni Maliyet Listesi Yükle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_cost_list">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Liste Adı *</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   placeholder="Örn: İstanbul-Berlin Maliyet Listesi">
                        </div>
                        <div class="mb-3">
                            <label for="transport_mode_id" class="form-label">Taşıma Modu</label>
                            <select class="form-select" id="transport_mode_id" name="transport_mode_id">
                                <option value="">Genel (Tüm modlar)</option>
                                <?php foreach ($transport_modes as $mode): ?>
                                    <option value="<?php echo $mode['id']; ?>"><?php echo htmlspecialchars($mode['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Maliyet listesi hakkında kısa açıklama..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="cost_file" class="form-label">Dosya *</label>
                            <input type="file" class="form-control" id="cost_file" name="cost_file" required
                                   accept=".pdf,.xlsx,.xls,.doc,.docx,.csv">
                            <div class="form-text">
                                Desteklenen formatlar: PDF, Excel (.xlsx, .xls), Word (.doc, .docx), CSV
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
</body>
</html>