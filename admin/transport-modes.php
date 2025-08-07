<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
checkAdminSession();

try {
    $database = new Database();
    $db = $database->getConnection();

    // Teklif şablonları tablosunu oluştur (yoksa)
    $db->exec("
        CREATE TABLE IF NOT EXISTS quote_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_mode_id INT NOT NULL,
            language ENUM('tr', 'en') DEFAULT 'tr',
            currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
            trade_type ENUM('import', 'export') DEFAULT 'import',
            template_name VARCHAR(255) NOT NULL,
            services_content TEXT,
            terms_content TEXT,
            transport_process_content TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_template_lookup (transport_mode_id, language, currency, trade_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Mevcut tabloya yeni alanları ekle (varsa hata vermez)
    try {
        $db->exec("ALTER TABLE quote_templates ADD COLUMN services_content TEXT AFTER content");
    } catch (Exception $e) {
        // Alan zaten varsa devam et
    }

    try {
        $db->exec("ALTER TABLE quote_templates ADD COLUMN terms_content TEXT AFTER services_content");
    } catch (Exception $e) {
        // Alan zaten varsa devam et
    }

    try {
        $db->exec("ALTER TABLE quote_templates ADD COLUMN transport_process_content TEXT AFTER terms_content");
    } catch (Exception $e) {
        // Alan zaten varsa devam et
    }

    try {
        $db->exec("ALTER TABLE quote_templates ADD COLUMN trade_type ENUM('import', 'export') DEFAULT 'import' AFTER currency");
    } catch (Exception $e) {
        // Alan zaten varsa devam et
    }

    try {
        $db->exec("ALTER TABLE quote_templates DROP INDEX unique_template");
        // UNIQUE constraint'i kaldırıyoruz - aynı kombinasyonda birden fazla şablon olabilsin
    } catch (Exception $e) {
        // Index yoksa devam et
    }

    // Transport referans resimleri tablosunu oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS transport_reference_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_mode_id INT NOT NULL,
            image_name VARCHAR(255) NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            image_description TEXT,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE CASCADE,
            INDEX idx_transport_mode (transport_mode_id),
            INDEX idx_active (is_active),
            INDEX idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Form işlemleri
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_template') {
            $id = $_POST['id'] ?? 0;
            $transport_mode_id = $_POST['transport_mode_id'] ?? 0;
            $language = $_POST['language'] ?? 'tr';
            $currency = $_POST['currency'] ?? 'TL';
            $trade_type = $_POST['trade_type'] ?? 'import';
            $template_name = $_POST['template_name'] ?? '';
            $services_content = $_POST['services_content'] ?? '';
            $terms_content = $_POST['terms_content'] ?? '';
            $transport_process_content = $_POST['transport_process_content'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                // Güncelle
                $stmt = $db->prepare("
                    UPDATE quote_templates
                    SET template_name = ?, services_content = ?, terms_content = ?, transport_process_content = ?, trade_type = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$template_name, $services_content, $terms_content, $transport_process_content, $trade_type, $is_active, $id]);
                setSuccessMessage('Şablon başarıyla güncellendi.');
            } else {
                // Aynı isimde şablon var mı kontrol et (opsiyonel uyarı)
                $check_stmt = $db->prepare("
                    SELECT id FROM quote_templates
                    WHERE transport_mode_id = ? AND template_name = ?
                ");
                $check_stmt->execute([$transport_mode_id, $template_name]);

                if ($check_stmt->fetch()) {
                    setErrorMessage('Bu taşıma modu için aynı isimde bir şablon zaten mevcut. Lütfen farklı bir şablon adı kullanın.');
                } else {
                    // Yeni ekle - artık aynı kombinasyonda birden fazla şablon olabilir
                    $stmt = $db->prepare("
                        INSERT INTO quote_templates (transport_mode_id, language, currency, trade_type, template_name, services_content, terms_content, transport_process_content, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$transport_mode_id, $language, $currency, $trade_type, $template_name, $services_content, $terms_content, $transport_process_content, $is_active]);
                    setSuccessMessage('Şablon başarıyla kaydedildi.');
                }
            }

            header('Location: transport-modes.php');
            exit;
        }

        if ($action === 'delete_template') {
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM quote_templates WHERE id = ?");
            if ($stmt->execute([$id])) {
                setSuccessMessage('Şablon başarıyla silindi.');
            }
            header('Location: transport-modes.php');
            exit;
        }
    }

    // Taşıma modlarını al (Konteyner hariç)
    $stmt = $db->query("SELECT * FROM transport_modes WHERE LOWER(name) != 'konteyner' ORDER BY name ASC");
    $transport_modes = $stmt->fetchAll();

    // Şablonları al (Konteyner hariç)
    $stmt = $db->query("
        SELECT qt.*, tm.name as transport_mode_name, tm.icon
        FROM quote_templates qt
        JOIN transport_modes tm ON qt.transport_mode_id = tm.id
        WHERE LOWER(tm.name) != 'konteyner'
        ORDER BY tm.name ASC, qt.language ASC, qt.currency ASC, qt.trade_type ASC
    ");
    $templates = $stmt->fetchAll();

    // Transport mode'ları ve resimlerini al (Referans Resimleri tab için)
    $stmt = $db->prepare("
        SELECT tm.id, tm.name, tm.slug,
               COUNT(tri.id) as image_count
        FROM transport_modes tm
        LEFT JOIN transport_reference_images tri ON tm.id = tri.transport_mode_id AND tri.is_active = 1
        WHERE tm.is_active = 1 AND LOWER(tm.name) != 'konteyner'
        GROUP BY tm.id, tm.name, tm.slug
        ORDER BY tm.name
    ");
    $stmt->execute();
    $transportModes = $stmt->fetchAll();

    // Şablonları trade_type ve transport_mode'a göre gruplara ayır
    $grouped_templates = [];
    foreach ($templates as $template) {
        $trade_type = $template['trade_type'];
        $mode_name = $template['transport_mode_name'];

        if (!isset($grouped_templates[$trade_type])) {
            $grouped_templates[$trade_type] = [];
        }

        if (!isset($grouped_templates[$trade_type][$mode_name])) {
            $grouped_templates[$trade_type][$mode_name] = [
                'icon' => $template['icon'],
                'templates' => []
            ];
        }

        $grouped_templates[$trade_type][$mode_name]['templates'][] = $template;
    }

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
    <title>Teklif Şablonları - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">

    <style>
        /* Modern sidebar included via external CSS */
        .transport-group {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 0;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
            overflow: hidden;
        }
        .template-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .template-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .language-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .currency-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .trade-type-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }

        /* Modal ve Editör Düzenlemeleri */
        .modal-xl {
            max-width: 1200px;
        }

        #servicesEditor .ql-container,
        #termsEditor .ql-container,
        #transportProcessEditor .ql-container {
            min-height: 150px;
            font-size: 14px;
        }

        .ql-toolbar {
            border-top: 1px solid #ccc;
            border-left: 1px solid #ccc;
            border-right: 1px solid #ccc;
        }

        .ql-container {
            border-bottom: 1px solid #ccc;
            border-left: 1px solid #ccc;
            border-right: 1px solid #ccc;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-label i {
            margin-right: 8px;
        }

        .form-label small {
            display: block;
            font-weight: 400;
            margin-top: 2px;
        }

        /* Trade Type Tabs */
        .trade-type-tabs {
            border: none;
            margin-bottom: 30px;
        }

        .trade-type-tabs .nav-link {
            border: 2px solid #e9ecef;
            border-radius: 10px !important;
            margin-right: 10px;
            padding: 12px 24px;
            color: #6c757d;
            font-weight: 600;
            transition: all 0.3s ease;
            background: white;
        }

        .trade-type-tabs .nav-link.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-color: #007bff;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }

        .trade-type-tabs .nav-link:hover:not(.active) {
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-1px);
        }

        .trade-type-tabs .nav-link i {
            margin-right: 8px;
        }
        .template-preview {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 10px;
            background: #f8f9fa;
            font-size: 0.9em;
        }
        .add-template-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border: 2px dashed #90caf9;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .add-template-card:hover {
            background: linear-gradient(135deg, #bbdefb 0%, #e1bee7 100%);
            border-color: #64b5f6;
        }
        .group-header {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            padding: 20px;
            border-bottom: 2px solid #dee2e6;
            cursor: pointer;
            transition: background-color 0.3s ease;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .group-header:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        }
        .group-header i {
            font-size: 2rem;
            margin-right: 15px;
            color: #007bff;
        }
        .group-header h4 {
            margin: 0;
            color: #495057;
            flex-grow: 1;
        }
        .group-toggle {
            font-size: 1.2rem;
            color: #6c757d;
            transition: transform 0.3s ease;
        }
        .group-content {
            padding: 20px;
            display: none !important;
        }
        .group-content.show {
            display: block !important;
        }
        #editor {
            height: 300px;
        }
        .variable-helper {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.85em;
        }
        .variable-category {
            margin-bottom: 10px;
            padding: 8px;
            background: rgba(255,255,255,0.7);
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
        .variable-tag {
            background: #007bff;
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.75em;
            margin: 3px;
            display: inline-block;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            white-space: nowrap;
        }
        .variable-tag:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .variable-tag small {
            opacity: 0.8;
            font-size: 0.9em;
        }

        /* Rich Text Editor Styles */
        .rich-editor-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .rich-editor-modal {
            background: white;
            border-radius: 12px;
            width: 95%;
            max-width: 1100px;
            max-height: 90%;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(0,0,0,0.4);
            animation: modalSlideIn 0.3s ease-out;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .rich-editor-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rich-editor-content {
            padding: 20px;
            max-height: 500px;
            overflow-y: auto;
        }
        .rich-editor-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            background: #f8f9fa;
            text-align: right;
        }
        .rich-editor-textarea {
            width: 100%;
            min-height: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            background: white;
            outline: none;
        }
        .rich-editor-textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }
        @media (max-width: 768px) {
            .rich-editor-modal {
                width: 98%;
                max-width: none;
                height: 95vh;
                max-height: 95vh;
                margin: 10px;
            }
            .rich-editor-content {
                padding: 15px;
                max-height: calc(95vh - 140px);
            }
            .btn-toolbar {
                flex-wrap: wrap;
            }
            .btn-group {
                margin-bottom: 5px;
            }
            .d-none.d-md-inline {
                display: none !important;
            }
            .form-select-sm {
                font-size: 0.7rem;
                padding: 2px 4px;
            }
        }

        /* Toolbar düzenlemeleri */
        .btn-toolbar .form-select {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-right: 2px;
        }

        .btn-toolbar .btn-sm {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Ana İçerik -->
        <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-truck"></i> Taşıma Modları Yönetimi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" onclick="showAddTemplateModal()">
                                <i class="fas fa-plus"></i> Yeni Şablon
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i> Yenile
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mesajlar -->
                <?php if (isset($messages['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($messages['success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($messages['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($messages['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Session mesajları -->
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success_message']); endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); endif; ?>

                <!-- Teklif Şablonları Bölümü -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-file-alt"></i> Teklif Şablonları</h4>
                    </div>
                    <div class="card-body">
                        <!-- Trade Type Tabs -->
                        <ul class="nav trade-type-tabs" id="tradeTypeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-content" type="button" role="tab">
                                    <i class="fas fa-arrow-down"></i> İthalat Şablonları
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export-content" type="button" role="tab">
                                    <i class="fas fa-arrow-up"></i> İhracat Şablonları
                                </button>
                            </li>
                        </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="tradeTypeTabContent">
                    <!-- İthalat Tab -->
                    <div class="tab-pane fade show active" id="import-content" role="tabpanel">
                        <?php if (empty($grouped_templates['import'])): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-arrow-down fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Henüz ithalat şablonu oluşturulmamış</h4>
                            <p class="text-muted">İlk ithalat şablonunuzu oluşturmak için "Yeni Şablon" butonuna tıklayın.</p>
                            <button type="button" class="btn btn-primary" onclick="showAddTemplateModal('', 'import')">
                                <i class="fas fa-plus"></i> İthalat Şablonu Oluştur
                            </button>
                        </div>
                        <?php else: ?>
                            <?php foreach ($grouped_templates['import'] as $mode_name => $group): ?>
                            <div class="transport-group">
                                <div class="group-header" onclick="toggleGroup(this)">
                                    <i class="<?= htmlspecialchars($group['icon']) ?>"></i>
                                    <h4><?= htmlspecialchars($mode_name) ?> - İthalat</h4>
                                    <i class="fas fa-chevron-down group-toggle"></i>
                                </div>

                                <div class="group-content">
                                    <div class="row">
                                        <?php foreach ($group['templates'] as $template): ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="template-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?= htmlspecialchars($template['template_name']) ?></h6>
                                            <div>
                                                <?php if ($template['is_active']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pasif</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="language-badge badge bg-info me-1">
                                                <?= $template['language'] == 'tr' ? 'Türkçe' : 'English' ?>
                                            </span>
                                            <span class="currency-badge badge bg-warning text-dark">
                                                <?= htmlspecialchars($template['currency']) ?>
                                            </span>
                                            <span class="trade-type-badge badge bg-<?= $template['trade_type'] == 'import' ? 'primary' : 'secondary' ?>">
                                                <?= $template['trade_type'] == 'import' ? 'İthalat' : 'İhracat' ?>
                                            </span>
                                        </div>

                                        <div class="template-preview mb-3">
                                            <?= $template['services_content'] ?: '<em class="text-muted">Hizmetler içeriği henüz eklenmemiş</em>' ?>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary flex-fill"
                                                    onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                                <i class="fas fa-edit"></i> Düzenle
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['template_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <!-- Yeni Şablon Ekleme Kartı -->
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="add-template-card" onclick="showAddTemplateModal('<?= htmlspecialchars($mode_name) ?>', 'import')">
                                        <i class="fas fa-plus fa-2x text-primary mb-2"></i>
                                        <h6 class="text-primary">Yeni İthalat Şablonu</h6>
                                        <small class="text-muted"><?= htmlspecialchars($mode_name) ?> için</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- İhracat Tab -->
                    <div class="tab-pane fade" id="export-content" role="tabpanel">
                        <?php if (empty($grouped_templates['export'])): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-arrow-up fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Henüz ihracat şablonu oluşturulmamış</h4>
                            <p class="text-muted">İlk ihracat şablonunuzu oluşturmak için "Yeni Şablon" butonuna tıklayın.</p>
                            <button type="button" class="btn btn-primary" onclick="showAddTemplateModal('', 'export')">
                                <i class="fas fa-plus"></i> İhracat Şablonu Oluştur
                            </button>
                        </div>
                        <?php else: ?>
                            <?php foreach ($grouped_templates['export'] as $mode_name => $group): ?>
                            <div class="transport-group">
                                <div class="group-header" onclick="toggleGroup(this)">
                                    <i class="<?= htmlspecialchars($group['icon']) ?>"></i>
                                    <h4><?= htmlspecialchars($mode_name) ?> - İhracat</h4>
                                    <i class="fas fa-chevron-down group-toggle"></i>
                                </div>

                                <div class="group-content">
                                    <div class="row">
                                        <?php foreach ($group['templates'] as $template): ?>
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="template-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?= htmlspecialchars($template['template_name']) ?></h6>
                                            <div>
                                                <?php if ($template['is_active']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pasif</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="language-badge badge bg-info me-1">
                                                <?= $template['language'] == 'tr' ? 'Türkçe' : 'English' ?>
                                            </span>
                                            <span class="currency-badge badge bg-warning text-dark">
                                                <?= htmlspecialchars($template['currency']) ?>
                                            </span>
                                            <span class="trade-type-badge badge bg-<?= $template['trade_type'] == 'import' ? 'primary' : 'secondary' ?>">
                                                <?= $template['trade_type'] == 'import' ? 'İthalat' : 'İhracat' ?>
                                            </span>
                                        </div>

                                        <div class="template-preview mb-3">
                                            <?= $template['services_content'] ?: '<em class="text-muted">Hizmetler içeriği henüz eklenmemiş</em>' ?>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary flex-fill"
                                                    onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                                <i class="fas fa-edit"></i> Düzenle
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="deleteTemplate(<?= $template['id'] ?>, '<?= htmlspecialchars($template['template_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <!-- Yeni Şablon Ekleme Kartı -->
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="add-template-card" onclick="showAddTemplateModal('<?= htmlspecialchars($mode_name) ?>', 'export')">
                                        <i class="fas fa-plus fa-2x text-primary mb-2"></i>
                                        <h6 class="text-primary">Yeni İhracat Şablonu</h6>
                                        <small class="text-muted"><?= htmlspecialchars($mode_name) ?> için</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Referans Resimleri Bölümü -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-images"></i> Referans Resimleri Yönetimi</h4>
                        <button type="button" class="btn btn-light btn-sm" onclick="showImageUploadModal()">
                            <i class="fas fa-upload"></i> Resim Yükle
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        <i class="fas fa-info-circle"></i>
                        Her taşıma modu için referans görselleri yükleyebilir ve yönetebilirsiniz.
                        Bu görseller müşteri teklif sayfalarında gösterilir.
                    </p>


                        <div class="row">
                            <?php foreach ($transportModes as $mode): ?>
                                <?php if (strtolower($mode['name']) !== 'konteyner'): ?>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <div class="d-flex align-items-center">
                                            <div class="transport-icon me-3">
                                                <?php
                                                $icons = [
                                                    'karayolu' => 'fa-truck',
                                                    'denizyolu' => 'fa-ship',
                                                    'havayolu' => 'fa-plane',
                                                    'konteyner' => 'fa-shipping-fast'
                                                ];
                                                $icon = $icons[$mode['slug']] ?? 'fa-truck';
                                                ?>
                                                <i class="fas <?= $icon ?> fa-2x"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($mode['name']) ?></h6>
                                                <small class="opacity-75"><?= $mode['image_count'] ?> referans görseli</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <button class="btn btn-outline-primary btn-sm w-100" onclick="viewModeImages(<?= $mode['id'] ?>, '<?= htmlspecialchars($mode['name']) ?>')" title="Görselleri İncele">
                                                    <i class="fas fa-eye"></i><br>
                                                    <small>Görüntüle</small>
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button class="btn btn-success btn-sm w-100" onclick="uploadModeImage(<?= $mode['id'] ?>, '<?= htmlspecialchars($mode['slug']) ?>')" title="Yeni Görsel Ekle">
                                                    <i class="fas fa-plus"></i><br>
                                                    <small>Ekle</small>
                                                </button>
                                            </div>
                                        </div>

                                        <?php if ($mode['image_count'] > 0): ?>
                                        <div class="mt-3 text-center">
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> Aktif
                                            </span>
                                        </div>
                                        <?php else: ?>
                                        <div class="mt-3 text-center">
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-exclamation-triangle"></i> Görsel Yok
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Şablon Düzenleme Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Yeni Şablon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="templateForm">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="id" id="templateId">

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Şablon Adı</label>
                                <input type="text" class="form-control" name="template_name" id="templateName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Taşıma Modu</label>
                                <select class="form-select" name="transport_mode_id" id="transportModeId" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($transport_modes as $mode): ?>
                                    <option value="<?= $mode['id'] ?>"><?= htmlspecialchars($mode['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dil</label>
                                <select class="form-select" name="language" id="templateLanguage" required>
                                    <option value="tr">Türkçe</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Para Birimi</label>
                                <select class="form-select" name="currency" id="templateCurrency" required>
                                    <option value="TL">Türk Lirası (TL)</option>
                                    <option value="USD">Amerikan Doları (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">İşlem Türü</label>
                                <select class="form-select" name="trade_type" id="templateTradeType" required>
                                    <option value="import">İthalat</option>
                                    <option value="export">İhracat</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Durum</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="templateActive" checked>
                                    <label class="form-check-label">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <!-- Hizmetlerimiz Bölümü -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-cogs text-primary"></i> Hizmetlerimiz İçeriği
                                <small class="text-muted">(view-quote.php sayfasında "Hizmetlerimiz" bölümünde görünecek)</small>
                            </label>
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="servicesContent" onclick="openRichEditor(this, 'services_content')">
                                İçerik eklemek için tıklayın...
                            </div>
                            <textarea name="services_content" style="display: none;"></textarea>
                        </div>

                        <!-- Taşınma Süreci Bölümü -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-truck text-success"></i> Taşınma Süreci İçeriği
                                <small class="text-muted">(Teklif sayfasında "Taşınma Süreci" bölümünde görünecek)</small>
                            </label>
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="transportProcessContent" onclick="openRichEditor(this, 'transport_process_content')">
                                İçerik eklemek için tıklayın...
                            </div>
                            <textarea name="transport_process_content" style="display: none;"></textarea>
                        </div>

                        <!-- Şartlar Bölümü -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-file-contract text-warning"></i> Şartlar İçeriği
                                <small class="text-muted">(view-quote.php sayfasında "Şartlar" bölümünde görünecek)</small>
                            </label>
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="termsContent" onclick="openRichEditor(this, 'terms_content')">
                                İçerik eklemek için tıklayın...
                            </div>
                            <textarea name="terms_content" style="display: none;"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Şablonu Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu şablonu silmek istediğinizden emin misiniz?</p>
                    <p><strong id="deleteTemplateName"></strong></p>
                    <p class="text-danger"><small>Bu işlem geri alınamaz.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_template">
                        <input type="hidden" name="id" id="deleteTemplateId">
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Rich Text Editor Modal -->
    <div class="rich-editor-container" id="richEditorModal">
        <div class="rich-editor-modal">
            <div class="rich-editor-header">
                <h5 id="richEditorTitle">İçerik Düzenle</h5>
                <button type="button" class="btn-close" onclick="closeRichEditor()"></button>
            </div>
            <div class="rich-editor-content">
                <div class="mb-3">
                    <div class="btn-toolbar mb-2" role="toolbar">
                        <!-- Font Boyutu ve Yazı Tipi -->
                        <div class="btn-group me-2" role="group">
                            <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSize(this.value)" title="Yazı Boyutu">
                                <option value="">Boyut</option>
                                <option value="1">8pt</option>
                                <option value="2">10pt</option>
                                <option value="3">12pt</option>
                                <option value="4">14pt</option>
                                <option value="5">18pt</option>
                                <option value="6">24pt</option>
                                <option value="7">36pt</option>
                            </select>
                            <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamily(this.value)" title="Yazı Tipi">
                                <option value="">Yazı Tipi</option>
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Calibri">Calibri</option>
                                <option value="Georgia">Georgia</option>
                                <option value="Verdana">Verdana</option>
                                <option value="Tahoma">Tahoma</option>
                                <option value="Courier New">Courier New</option>
                            </select>
                        </div>

                        <!-- Temel Biçimlendirme -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')" title="Kalın">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')" title="İtalik">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')" title="Altı Çizili">
                                <i class="fas fa-underline"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('strikeThrough')" title="Üstü Çizili">
                                <i class="fas fa-strikethrough"></i>
                            </button>
                        </div>

                        <!-- Liste -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertUnorderedList')" title="Madde İşareti">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertOrderedList')" title="Numaralı Liste">
                                <i class="fas fa-list-ol"></i>
                            </button>
                        </div>

                        <!-- Hizalama -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyLeft')" title="Sola Hizala">
                                <i class="fas fa-align-left"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyCenter')" title="Ortala">
                                <i class="fas fa-align-center"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('justifyRight')" title="Sağa Hizala">
                                <i class="fas fa-align-right"></i>
                            </button>
                        </div>

                        <!-- Renk -->
                        <div class="btn-group me-2" role="group">
                            <input type="color" id="textColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColor(this.value)" title="Metin Rengi">
                            <input type="color" id="bgColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColor(this.value)" title="Arka Plan Rengi" value="#ffffff">
                        </div>
                    </div>

                    <!-- İkinci satır araç çubuğu -->
                    <div class="btn-toolbar mb-2" role="toolbar">
                        <!-- Link -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-primary" onclick="insertLink()" title="Bağlantı Ekle">
                                <i class="fas fa-link"></i>
                                <span class="d-none d-md-inline ms-1">Link</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="formatText('unlink')" title="Bağlantıyı Kaldır">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </div>

                        <!-- Temizleme -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-warning" onclick="removeFormatting()" title="Tüm biçimlendirmeyi kaldır">
                                <i class="fas fa-eraser"></i>
                                <span class="d-none d-md-inline ms-1">Biçim Temizle</span>
                            </button>
                        </div>

                        <!-- Görünüm -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-info" onclick="toggleSourceView()" title="HTML kaynak kodunu görüntüle/düzenle">
                                <i class="fas fa-code"></i>
                                <span class="d-none d-md-inline ms-1">Kaynak Kodu</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div contenteditable="true" class="rich-editor-textarea" id="richEditorContent"></div>
                <textarea class="rich-editor-textarea" id="richEditorSource" style="display: none; font-family: monospace;"></textarea>
            </div>
            <div class="rich-editor-footer">
                <button type="button" class="btn btn-secondary me-2" onclick="closeRichEditor()">İptal</button>
                <button type="button" class="btn btn-primary" onclick="saveRichContent()">Kaydet</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Rich Text Editor Variables
        let currentRichField = null;
        let currentRichElement = null;
        let isSourceView = false;

        document.addEventListener('DOMContentLoaded', function() {
            // Sayfa yüklendiğinde tüm grupları kapat
            document.querySelectorAll('.group-content').forEach(content => {
                content.classList.remove('show');
                content.style.display = 'none';
            });

            // Toggle ikonlarını başlangıç pozisyonuna ayarla
            document.querySelectorAll('.group-toggle').forEach(toggle => {
                toggle.style.transform = 'rotate(-90deg)';
            });
        });

        // Rich Text Editor Functions
        function openRichEditor(element, field) {
            currentRichField = field;
            currentRichElement = element;

            const modal = document.getElementById('richEditorModal');
            const content = document.getElementById('richEditorContent');
            const title = document.getElementById('richEditorTitle');

            // Get current content
            let currentContent = element.innerHTML.replace(/İçerik eklemek için tıklayın\.\.\./, '').trim();

            // Field title mapping
            const fieldTitles = {
                'services_content': 'Hizmetler İçeriği',
                'terms_content': 'Şartlar İçeriği',
                'transport_process_content': 'Taşıma Süreci İçeriği'
            };

            // Set content and title
            content.innerHTML = currentContent;
            title.textContent = fieldTitles[field] || 'İçerik Düzenle';

            // Show modal
            modal.style.display = 'flex';
            content.focus();
        }

        function closeRichEditor() {
            document.getElementById('richEditorModal').style.display = 'none';
            currentRichField = null;
            currentRichElement = null;
            isSourceView = false;
        }

        function saveRichContent() {
            let content;

            // Source view'da mıyız kontrol et
            if (isSourceView) {
                content = document.getElementById('richEditorSource').value;
            } else {
                content = document.getElementById('richEditorContent').innerHTML;
            }

            // İçerik temizle
            content = content.trim();
            if (content === '<br>' || content === '<p></p>' || content === '<p><br></p>') {
                content = '';
            }

            // Update the element
            if (currentRichElement) {
                if (content) {
                    currentRichElement.innerHTML = content;
                } else {
                    currentRichElement.innerHTML = 'İçerik eklemek için tıklayın...';
                }

                // Update hidden textarea
                const textarea = currentRichElement.parentElement.querySelector('textarea[name="' + currentRichField + '"]');
                if (textarea) {
                    textarea.value = content;
                }
            }

            closeRichEditor();
        }

        // Text formatting functions
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        function changeTextColor(color) {
            document.execCommand('foreColor', false, color);
            document.getElementById('richEditorContent').focus();
        }

        function changeBackgroundColor(color) {
            document.execCommand('hiliteColor', false, color);
            document.getElementById('richEditorContent').focus();
        }

        function changeFontSize(size) {
            if (size) {
                document.execCommand('fontSize', false, size);
                document.getElementById('richEditorContent').focus();
            }
        }

        function changeFontFamily(fontFamily) {
            if (fontFamily) {
                document.execCommand('fontName', false, fontFamily);
                document.getElementById('richEditorContent').focus();
            }
        }

        function insertLink() {
            const url = prompt('Bağlantı URL\'sini girin:', 'https://');
            if (url && url !== 'https://') {
                document.execCommand('createLink', false, url);
                document.getElementById('richEditorContent').focus();
            }
        }

        function removeFormatting() {
            if (confirm('Tüm biçimlendirmeyi kaldırmak istediğinizden emin misiniz?')) {
                const content = document.getElementById('richEditorContent');
                if (content) {
                    const text = content.innerText || content.textContent || '';
                    content.innerHTML = text.replace(/\n/g, '<br>');
                    content.focus();
                }
            }
        }

        function insertHorizontalRule() {
            document.execCommand('insertHorizontalRule', false, null);
            document.getElementById('richEditorContent').focus();
        }

        function toggleSourceView() {
            const content = document.getElementById('richEditorContent');
            const sourceContainer = document.getElementById('richEditorSource');
            const btn = document.querySelector('[onclick="toggleSourceView()"]');

            if (!isSourceView) {
                // Switch to source view
                sourceContainer.value = content.innerHTML;
                sourceContainer.style.display = 'block';
                content.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-eye"></i> <span class="d-none d-md-inline ms-1">Görsel Mod</span>';
                isSourceView = true;
            } else {
                // Switch to visual view
                content.innerHTML = sourceContainer.value;
                content.style.display = 'block';
                sourceContainer.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-code"></i> <span class="d-none d-md-inline ms-1">Kaynak Kodu</span>';
                isSourceView = false;
                content.focus();
            }
        }

        function showAddTemplateModal(modeName = '', tradeType = 'import') {
            document.getElementById('modalTitle').textContent = 'Yeni Şablon';
            document.getElementById('templateForm').reset();
            document.getElementById('templateId').value = '';

            // Eğer mod adı verilmişse, ilgili modu seç
            if (modeName) {
                const modeSelect = document.getElementById('transportModeId');
                for (let option of modeSelect.options) {
                    if (option.text === modeName) {
                        option.selected = true;
                        break;
                    }
                }
            }

            // Form alanlarını sıfırla
            document.getElementById('templateName').value = '';
            document.getElementById('templateLanguage').value = 'tr';
            document.getElementById('templateCurrency').value = 'TL';
            document.getElementById('templateTradeType').value = tradeType;
            document.getElementById('templateActive').checked = true;

            // Tüm editör alanlarını temizle
            document.getElementById('servicesContent').innerHTML = 'İçerik eklemek için tıklayın...';
            document.getElementById('transportProcessContent').innerHTML = 'İçerik eklemek için tıklayın...';
            document.getElementById('termsContent').innerHTML = 'İçerik eklemek için tıklayın...';

            // Hidden textarea'ları temizle
            document.querySelector('textarea[name="services_content"]').value = '';
            document.querySelector('textarea[name="transport_process_content"]').value = '';
            document.querySelector('textarea[name="terms_content"]').value = '';

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function editTemplate(template) {
            document.getElementById('modalTitle').textContent = 'Şablon Düzenle';
            document.getElementById('templateId').value = template.id;
            document.getElementById('templateName').value = template.template_name;
            document.getElementById('transportModeId').value = template.transport_mode_id;
            document.getElementById('templateLanguage').value = template.language;
            document.getElementById('templateCurrency').value = template.currency;
            document.getElementById('templateTradeType').value = template.trade_type || 'import';
            document.getElementById('templateActive').checked = template.is_active == 1;

            // Editör alanlarına içerikleri yükle
            document.getElementById('servicesContent').innerHTML = template.services_content || 'İçerik eklemek için tıklayın...';
            document.getElementById('transportProcessContent').innerHTML = template.transport_process_content || 'İçerik eklemek için tıklayın...';
            document.getElementById('termsContent').innerHTML = template.terms_content || 'İçerik eklemek için tıklayın...';

            // Hidden textarea'ları güncelle
            document.querySelector('textarea[name="services_content"]').value = template.services_content || '';
            document.querySelector('textarea[name="transport_process_content"]').value = template.transport_process_content || '';
            document.querySelector('textarea[name="terms_content"]').value = template.terms_content || '';

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function deleteTemplate(id, name) {
            document.getElementById('deleteTemplateId').value = id;
            document.getElementById('deleteTemplateName').textContent = name;

            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }



        function toggleGroup(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.group-toggle');

            if (content.classList.contains('show')) {
                content.classList.remove('show');
                content.style.display = 'none';
                toggle.style.transform = 'rotate(-90deg)';
            } else {
                content.classList.add('show');
                content.style.display = 'block';
                toggle.style.transform = 'rotate(0deg)';
            }
        }

        // Form validation ve submit
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            // İçerikleri textarea'lara aktar (zaten JavaScript ile güncelleniyorlar)

            const name = document.getElementById('templateName').value.trim();
            const transportModeId = document.getElementById('transportModeId').value;

            if (!name) {
                e.preventDefault();
                alert('Şablon adı zorunludur.');
                return false;
            }

            if (!transportModeId) {
                e.preventDefault();
                alert('Taşıma modu seçilmelidir.');
                return false;
            }

            // Loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
            submitBtn.disabled = true;

            // Form başarılı olduğunda loading'i kaldır (sayfa yenilenir zaten)
        });

        // Referans resim yönetimi fonksiyonları
        function showImageUploadModal() {
            document.getElementById('imageUploadModal').querySelector('#transportModeSelect').value = '';
            document.getElementById('imageUploadModal').querySelector('#imageDescription').value = '';
            document.getElementById('imageUploadModal').querySelector('#imageFiles').value = '';
            document.getElementById('imagePreview').classList.add('d-none');
            document.getElementById('previewContainer').innerHTML = '';
            new bootstrap.Modal(document.getElementById('imageUploadModal')).show();
        }

        // Resim önizleme ve form handling
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('imageFiles');
            if (imageInput) {
                imageInput.addEventListener('change', function(e) {
                    previewImages(e.target.files);
                });
            }

            // Ana resim yükleme formu
            const uploadForm = document.getElementById('imageUploadForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    showUploadLoading('uploadLoading', 'uploadBtn');
                });
            }

            // Mode-specific resim yükleme formu
            const modeUploadForm = document.getElementById('imageModeUploadForm');
            if (modeUploadForm) {
                modeUploadForm.addEventListener('submit', function(e) {
                    showUploadLoading('uploadLoadingMode', 'uploadBtnMode');
                });
            }
        });

        function showUploadLoading(loadingId, buttonId) {
            // Loading göster
            document.getElementById(loadingId).classList.remove('d-none');

            // Buton disable et
            const btn = document.getElementById(buttonId);
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';

            // Progress bar animasyonu
            const progressBar = loadingId === 'uploadLoading' ?
                document.getElementById('uploadProgress') :
                document.getElementById('uploadProgressMode');

            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 95) progress = 95;
                progressBar.style.width = progress + '%';
            }, 200);

            // 30 saniye sonra timeout
            setTimeout(() => {
                clearInterval(interval);
                progressBar.style.width = '100%';
            }, 30000);
        }

        function previewImages(files) {
            const previewDiv = document.getElementById('imagePreview');
            const container = document.getElementById('previewContainer');

            if (files.length === 0) {
                previewDiv.classList.add('d-none');
                return;
            }

            if (files.length > 10) {
                alert('Maksimum 10 resim seçebilirsiniz!');
                return;
            }

            container.innerHTML = '';
            previewDiv.classList.remove('d-none');

            Array.from(files).forEach((file, index) => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`${file.name} dosyası 5MB'dan büyük!`);
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-md-3';
                    col.innerHTML = `
                        <div class="card">
                            <img src="${e.target.result}" class="card-img-top" style="height: 100px; object-fit: cover;">
                            <div class="card-body p-2">
                                <small class="text-muted">${file.name}</small>
                            </div>
                        </div>
                    `;
                    container.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
        }

        function uploadModeImage(modeId, modeSlug) {
            document.getElementById('uploadModeId').value = modeId;
            document.getElementById('uploadModeSlug').value = modeSlug;
            new bootstrap.Modal(document.getElementById('imageModeUploadModal')).show();
        }

        function viewModeImages(modeId, modeName) {
            document.getElementById('viewImagesModalTitle').textContent = modeName + ' Referans Resimleri';

            // Resimleri yükle
            fetch(`../api/get-transport-images.php?mode_id=${modeId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('imagesContainer');
                    if (data.success && data.images.length > 0) {
                        let html = '<div class="row">';
                        data.images.forEach(image => {
                            html += `
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <img src="../${image.image_path}" class="card-img-top" style="height: 200px; object-fit: cover;" alt="${image.image_name}">
                                        <div class="card-body">
                                            <h6 class="card-title">${image.image_name}</h6>
                                            <p class="card-text small text-muted">${image.image_description || ''}</p>
                                            <button class="btn btn-sm btn-danger" onclick="deleteImage(${image.id})">
                                                <i class="fas fa-trash"></i> Sil
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-center text-muted">Bu taşıma modu için henüz resim eklenmemiş.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('imagesContainer').innerHTML = '<p class="text-center text-danger">Resimler yüklenirken hata oluştu.</p>';
                });

            new bootstrap.Modal(document.getElementById('viewImagesModal')).show();
        }

        function deleteImage(imageId) {
            if (confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
                fetch('../api/delete-transport-image.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ image_id: imageId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Resim silinirken hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu.');
                });
            }
        }
    </script>

    <!-- Genel Resim Yükleme Modal -->
    <div class="modal fade" id="imageUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Referans Resmi Yükle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                                <form action="../api/upload-transport-image.php" method="POST" enctype="multipart/form-data" id="imageUploadForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Taşıma Modu</label>
                            <select name="transport_mode_id" id="transportModeSelect" class="form-select" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($transportModes as $mode): ?>
                                <option value="<?= $mode['id'] ?>"><?= htmlspecialchars($mode['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resim Dosyaları</label>
                            <input type="file" name="image_files[]" id="imageFiles" class="form-control" accept="image/*" multiple required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i>
                                Birden fazla resim seçebilirsiniz. Maksimum 10 resim, her biri 5MB'dan küçük olmalıdır.
                            </div>
                        </div>
                        <div class="mb-3">
                            <div id="imagePreview" class="d-none">
                                <label class="form-label">Seçilen Resimler:</label>
                                <div id="previewContainer" class="row g-2"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <textarea name="image_description" id="imageDescription" class="form-control" rows="3"></textarea>
                        </div>

                        <!-- Loading Overlay -->
                        <div id="uploadLoading" class="text-center d-none">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                            <h5>Resimler Yükleniyor...</h5>
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="uploadProgress"></div>
                            </div>
                            <p class="text-muted">Lütfen bekleyin, resimleriniz yükleniyor...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fas fa-upload"></i> Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Belirli Mode İçin Resim Yükleme Modal -->
    <div class="modal fade" id="imageModeUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Referans Resmi Yükle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                                <form action="../api/upload-transport-image.php" method="POST" enctype="multipart/form-data" id="imageModeUploadForm">
                    <input type="hidden" name="transport_mode_id" id="uploadModeId">
                    <input type="hidden" name="mode_slug" id="uploadModeSlug">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Resim Dosyaları</label>
                            <input type="file" name="image_files[]" class="form-control" accept="image/*" multiple required>
                            <div class="form-text">
                                <i class="fas fa-info-circle"></i>
                                Birden fazla resim seçebilirsiniz. Maksimum 10 resim, her biri 5MB'dan küçük olmalıdır.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <textarea name="image_description" class="form-control" rows="3"></textarea>
                        </div>

                        <!-- Loading Overlay -->
                        <div id="uploadLoadingMode" class="text-center d-none">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                            <h5>Resimler Yükleniyor...</h5>
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="uploadProgressMode"></div>
                            </div>
                            <p class="text-muted">Lütfen bekleyin, resimleriniz yükleniyor...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtnMode">
                            <i class="fas fa-upload"></i> Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Resimleri Görüntüleme Modal -->
    <div class="modal fade" id="viewImagesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewImagesModalTitle">Referans Resimleri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="imagesContainer">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Yükleniyor...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    <script src="includes/sidebar.js"></script>
</body>
</html>