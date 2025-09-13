<?php
ob_start(); // Output buffering başlat

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Database bağlantısını kontrol et
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database bağlantısı kurulamadı");
    }
} catch (Exception $e) {
    die("Database hatası: " . $e->getMessage());
}

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$templateId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$template = null;
$transportModes = [];
$defaultMode = isset($_GET['mode']) ? $_GET['mode'] : '';
$defaultTradeType = isset($_GET['trade_type']) ? $_GET['trade_type'] : 'import';

// Taşıma modlarını al
try {
    $stmt = $db->query("SELECT * FROM transport_modes ORDER BY name");
    $transportModes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Transport modes alınamadı: " . $e->getMessage());
}

// Template ID varsa, verileri çek
if ($templateId > 0) {
    try {
        // quote_templates tablosundan oku (içerik alanları burada)
        $stmt = $db->prepare("SELECT * FROM quote_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            $_SESSION['error_message'] = 'Şablon bulunamadı.';
            header('Location: transport-modes.php?tab=templates');
            exit;
        }

        // HTML olarak saklanan içerikleri decode et ki önizleme görünsün
        if (isset($template['services_content'])) {
            $template['services_content'] = html_entity_decode($template['services_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (isset($template['transport_process_content'])) {
            $template['transport_process_content'] = html_entity_decode($template['transport_process_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (isset($template['terms_content'])) {
            $template['terms_content'] = html_entity_decode($template['terms_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Dinamik bölümleri decode et
        if (isset($template['dynamic_sections']) && !empty($template['dynamic_sections'])) {
            $template['dynamic_sections'] = json_decode($template['dynamic_sections'], true) ?: [];
        } else {
            $template['dynamic_sections'] = [];
        }

        // Bölüm sıralamasını decode et
        if (isset($template['section_order']) && !empty($template['section_order'])) {
            $template['section_order'] = json_decode($template['section_order'], true) ?: ['services', 'transport', 'terms'];
        } else {
            $template['section_order'] = ['services', 'transport', 'terms'];
        }

        // Debug: Template içeriğini kontrol et
        error_log("Template loaded: " . print_r($template, true));

    } catch (Exception $e) {
        die("Template alınamadı: " . $e->getMessage());
    }
}

// Form gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Güvence: POST ile gelen template_id varsa kullan
    if (!empty($_POST['template_id'])) {
        $templateId = (int)$_POST['template_id'];
    }
    $templateName = $_POST['template_name'] ?? '';
    $transportModeId = $_POST['transport_mode_id'] ?? '';
    $language = $_POST['language'] ?? 'tr';
    $currency = $_POST['currency'] ?? 'USD';
    $tradeType = $_POST['trade_type'] ?? 'import';
    $servicesContent = $_POST['services_content'] ?? '';
    $transportProcessContent = $_POST['transport_process_content'] ?? '';
    $termsContent = $_POST['terms_content'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Bölüm başlıkları
    $servicesTitle = $_POST['services_title'] ?? 'Hizmetlerimiz';
    $transportProcessTitle = $_POST['transport_process_title'] ?? 'Taşıma Süreci';
    $termsTitle = $_POST['terms_title'] ?? 'Şartlar ve Koşullar';

    // Dinamik bölümler için JSON verisi
    $dynamicSections = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'dynamic_section_') === 0) {
            $dynamicSections[$key] = $value;
        }
    }

    // Debug: POST verilerini kontrol et
    error_log("=== FORM SUBMISSION DEBUG ===");
    error_log("Template ID: " . $templateId);
    error_log("POST method: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("Dynamic sections: " . print_r($dynamicSections, true));
    error_log("Section order: " . print_r($sectionOrder, true));

    // Bölüm sıralaması (array olarak çalış, DB'ye JSON kaydet)
    $sectionOrderRaw = $_POST['section_order'] ?? '["services","transport","terms"]';
    $sectionOrderArr = is_array($sectionOrderRaw)
        ? $sectionOrderRaw
        : (json_decode((string)$sectionOrderRaw, true) ?: ['services','transport','terms']);

    // Dinamik bölüm anahtarları section_order'a dahil değilse ekle
    if (!empty($dynamicSections)) {
        $dynamicIds = [];
        foreach (array_keys($dynamicSections) as $k) {
            if (preg_match('/^dynamic_section_(\d+)_/', $k, $m)) {
                $dynamicIds[$m[1]] = true;
            }
        }
        foreach (array_keys($dynamicIds) as $idPart) {
            $key = 'dynamic_' . $idPart;
            if (!in_array($key, $sectionOrderArr, true)) {
                $sectionOrderArr[] = $key;
            }
        }
    }

    $sectionOrderJson = json_encode(array_values(array_unique($sectionOrderArr)));

    try {
        if ($templateId > 0) {
            // Güncelleme (quote_templates)
            $stmt = $db->prepare("UPDATE quote_templates SET
                template_name = ?,
                transport_mode_id = ?,
                language = ?,
                currency = ?,
                trade_type = ?,
                services_content = ?,
                transport_process_content = ?,
                terms_content = ?,
                services_title = ?,
                transport_process_title = ?,
                terms_title = ?,
                dynamic_sections = ?,
                section_order = ?,
                is_active = ?,
                updated_at = NOW()
                WHERE id = ?");

            $result = $stmt->execute([
                $templateName,
                $transportModeId,
                $language,
                $currency,
                $tradeType,
                $servicesContent,
                $transportProcessContent,
                $termsContent,
                $servicesTitle,
                $transportProcessTitle,
                $termsTitle,
                json_encode($dynamicSections),
                $sectionOrderJson,
                $isActive,
                $templateId
            ]);

            error_log("UPDATE result: " . ($result ? 'SUCCESS' : 'FAILED'));
            error_log("Affected rows: " . $stmt->rowCount());

            $_SESSION['success_message'] = 'Şablon başarıyla güncellendi.';
        } else {
            // Yeni ekleme (quote_templates)
            $stmt = $db->prepare("INSERT INTO quote_templates
                (template_name, transport_mode_id, language, currency, trade_type, services_content, transport_process_content, terms_content, services_title, transport_process_title, terms_title, dynamic_sections, section_order, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmt->execute([
                $templateName,
                $transportModeId,
                $language,
                $currency,
                $tradeType,
                $servicesContent,
                $transportProcessContent,
                $termsContent,
                $servicesTitle,
                $transportProcessTitle,
                $termsTitle,
                json_encode($dynamicSections),
                $sectionOrderJson,
                $isActive
            ]);

            $_SESSION['success_message'] = 'Şablon başarıyla eklendi.';
        }

        header('Location: transport-modes.php?tab=templates');
        exit;

    } catch (Exception $e) {
        $error = 'Bir hata oluştu: ' . $e->getMessage();
        error_log("EXCEPTION: " . $e->getMessage());
        error_log("TRACE: " . $e->getTraceAsString());
    }
} else {
    error_log("=== NO POST REQUEST ===");
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Template ID: " . $templateId);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $template ? 'Şablon Düzenle' : 'Yeni Şablon' ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">



    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }

        /* Rich Editor Stilleri */
        .rich-editor-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            animation: editorFadeIn 0.3s ease-out;
        }
        @keyframes editorFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
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
            font-size: 14px;
            line-height: 1.6;
            background: white;
            outline: none;
        }
        .rich-editor-textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
        }

        /* Inline Editor Stilleri */
        .rich-editor-container-inline {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            background: white;
            margin-bottom: 1rem;
        }

        .rich-editor-textarea-inline {
            width: 100%;
            min-height: 160px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            font-size: 13px;
            line-height: 1.5;
            background: white;
            outline: none;
            margin-top: 8px;
        }

        .rich-editor-textarea-inline:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        .rich-editor-textarea-inline p {
            margin-bottom: 10px;
        }

        .rich-editor-textarea-inline p:last-child {
            margin-bottom: 0;
        }

        /* Minimal toolbar boyutları */
        .btn-toolbar .btn-sm {
            font-size: 0.78rem;
            padding: 0.22rem 0.45rem;
            line-height: 1.05;
        }
        .btn-toolbar .btn-group { margin-right: 0.35rem; margin-bottom: 0.2rem; }
        .btn-toolbar .form-select.form-select-sm {
            height: 30px;
            padding: 3px 8px;
            font-size: 0.78rem;
        }

        /* Renkli bölüm başlıkları */
        .form-label.label-services { background: #e0f2fe; color: #0c4a6e; }
        .form-label.label-transport { background: #dcfce7; color: #14532d; }
        .form-label.label-terms { background: #ffedd5; color: #7c2d12; }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
        }

        .main-content {
            padding: 2rem;
            margin-left: 250px;
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid #dbeafe;
            letter-spacing: 0.2px;
        }

        .form-label {
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            background: #eef2ff;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #374151;
        }

        .editor-section {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .tox-tinymce {
            border-radius: 10px !important;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-<?= $template ? 'edit' : 'plus' ?> me-2"></i>
                <?= $template ? 'Şablon Düzenle' : 'Yeni Şablon Oluştur' ?>
            </h1>
            <a href="transport-modes.php?tab=templates" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Geri Dön
            </a>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="" onsubmit="console.log('Form submitted!'); return prepareFormSubmission();">
            <!-- Template id (POST ile güvence) -->
            <input type="hidden" name="template_id" value="<?= (int)$templateId ?>">
            <!-- Bölüm sıralaması için gizli input -->
            <input type="hidden" name="section_order" id="section_order" value="<?= htmlspecialchars(json_encode($template['section_order'] ?? ['services', 'transport', 'terms'])) ?>">

            <div class="glass-card">
                <div class="form-section">
                    <h2 class="section-title">Temel Bilgiler</h2>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Şablon Adı *</label>
                            <input type="text" name="template_name" class="form-control"
                                   value="<?= $template ? htmlspecialchars($template['template_name']) : '' ?>"
                                   placeholder="Örn: Standart İthalat Şablonu" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Taşıma Modu *</label>
                            <select name="transport_mode_id" class="form-select" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($transportModes as $mode): ?>
                                <option value="<?= $mode['id'] ?>"
                                        <?php if ($template && $template['transport_mode_id'] == $mode['id']): ?>
                                            selected
                                        <?php elseif (!$template && $defaultMode && strcasecmp($mode['name'], $defaultMode) == 0): ?>
                                            selected
                                        <?php endif; ?>>
                                    <?= htmlspecialchars($mode['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Dil</label>
                            <select name="language" class="form-select">
                                <option value="tr" <?= ($template && $template['language'] == 'tr') ? 'selected' : '' ?>>Türkçe</option>
                                <option value="en" <?= ($template && $template['language'] == 'en') ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Para Birimi</label>
                            <select name="currency" class="form-select">
                                <option value="USD" <?= ($template && $template['currency'] == 'USD') ? 'selected' : '' ?>>USD</option>
                                <option value="EUR" <?= ($template && $template['currency'] == 'EUR') ? 'selected' : '' ?>>EUR</option>
                                <option value="TRY" <?= ($template && $template['currency'] == 'TRY') ? 'selected' : '' ?>>TRY</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Ticaret Tipi</label>
                            <select name="trade_type" class="form-select">
                                <option value="import" <?= ($template && isset($template['trade_type']) && $template['trade_type'] == 'import') || (!$template && $defaultTradeType == 'import') ? 'selected' : '' ?>>İthalat</option>
                                <option value="export" <?= ($template && isset($template['trade_type']) && $template['trade_type'] == 'export') || (!$template && $defaultTradeType == 'export') ? 'selected' : '' ?>>İhracat</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Durum</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                       <?= (!$template || ($template && $template['is_active'])) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass-card">
                <div class="form-section">
                    <h2 class="section-title">Şablon İçerikleri</h2>

                    <!-- Sıralı bölümler için container -->
                    <div id="sections-container">
                        <!-- Bölümler JavaScript ile sıralı şekilde yüklenecek -->
                    </div>

                    <!-- Template bölümleri (JavaScript ile kopyalanacak) -->
                    <div style="display: none;">
                        <div class="editor-section" data-section="services" id="template-services">
                        <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <span class="editable-section-title form-label label-services"
                                  data-field="services_title"
                                  onclick="editSectionTitle(this)"
                                  style="cursor: pointer; padding: 4px 10px; border-radius: 8px; background: #e0f2fe; color: #0c4a6e; font-weight: 700; margin: 0;"
                                  title="Başlığı düzenlemek için tıklayın">
                                <?= ($template && isset($template['services_title'])) ? htmlspecialchars($template['services_title']) : 'Hizmetlerimiz' ?>
                                <i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>
                            </span>
                            <div class="section-controls">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'up')" title="Yukarı taşı">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'down')" title="Aşağı taşı">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSection(this)" title="Bölümü kaldır">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="services_title" value="<?= ($template && isset($template['services_title'])) ? htmlspecialchars($template['services_title']) : 'Hizmetlerimiz' ?>">
                        <div class="rich-editor-container-inline" data-field="services_content">
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSizeInline(this.value, 'services_content')" title="Yazı Boyutu">
                                        <option value="">Boyut</option>
                                        <option value="1">8pt</option>
                                        <option value="2">10pt</option>
                                        <option value="3">12pt</option>
                                        <option value="4">14pt</option>
                                        <option value="5">18pt</option>
                                        <option value="6">24pt</option>
                                        <option value="7">36pt</option>
                                    </select>
                                    <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamilyInline(this.value, 'services_content')" title="Yazı Tipi">
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

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','bold')" title="Kalın"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','italic')" title="İtalik"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','underline')" title="Altı Çizili"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','strikeThrough')" title="Üstü Çizili"><i class="fas fa-strikethrough"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','insertUnorderedList')" title="Madde İşareti"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','insertOrderedList')" title="Numaralı Liste"><i class="fas fa-list-ol"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','justifyLeft')" title="Sola Hizala"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','justifyCenter')" title="Ortala"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','justifyRight')" title="Sağa Hizala"><i class="fas fa-align-right"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','indent')" title="Girinti Artır"><i class="fas fa-indent"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','outdent')" title="Girinti Azalt"><i class="fas fa-outdent"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColorInline(this.value,'services_content')" title="Metin Rengi">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColorInline(this.value,'services_content')" title="Arka Plan Rengi" value="#ffffff">
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatTextInline('services_content','hiliteColor','#ffff00')" title="Sarı Vurgu"><i class="fas fa-highlighter"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','subscript')" title="Alt Simge"><i class="fas fa-subscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','superscript')" title="Üst Simge"><i class="fas fa-superscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRuleInline('services_content')" title="Yatay Çizgi"><i class="fas fa-minus"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="insertLinkInline('services_content')" title="Bağlantı Ekle"><i class="fas fa-link"></i><span class="d-none d-md-inline ms-1">Link</span></button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="formatTextInline('services_content','unlink')" title="Bağlantıyı Kaldır"><i class="fas fa-unlink"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="file" id="servicesFileInput" style="display:none;" onchange="uploadEditorFileInline(this,'services_content')" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                                    <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('servicesFileInput').click()" title="Dosya yükleyip içeriğe link olarak ekle"><i class="fas fa-upload"></i><span class="d-none d-md-inline ms-1">Dosya Yükle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="removeFormattingInline('services_content')" title="Tüm biçimlendirmeyi kaldır"><i class="fas fa-eraser"></i><span class="d-none d-md-inline ms-1">Biçim Temizle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSourceViewInline('services_content', this)" title="HTML kaynak kodunu görüntüle/düzenle"><i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span></button>
                                </div>

                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','undo')" title="Geri Al"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('services_content','redo')" title="İleri Al"><i class="fas fa-redo"></i></button>
                                </div>

                                <div class="btn-group ms-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal('services_content')" title="Tablo Ekle"><i class="fas fa-table"></i></button>
                                </div>
                            </div>

                            <div contenteditable="true" class="rich-editor-textarea-inline" id="services_content_editor" oninput="updateHiddenFieldInline('services_content')">
                                <?= ($template && isset($template['services_content']) && !empty($template['services_content'])) ? $template['services_content'] : '<p></p>' ?>
                            </div>
                            <textarea class="rich-editor-textarea" id="services_content_source" style="display:none; font-family: 'Courier New', monospace; font-size: 12px;" oninput="updateHiddenFieldInline('services_content')"></textarea>
                            <textarea name="services_content" id="services_content_input" style="display:none;"><?= ($template && isset($template['services_content'])) ? htmlspecialchars($template['services_content']) : '' ?></textarea>
                        </div>
                        </div>

                        <div class="editor-section" data-section="transport" id="template-transport">
                        <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <span class="editable-section-title form-label label-transport"
                                  data-field="transport_process_title"
                                  onclick="editSectionTitle(this)"
                                  style="cursor: pointer; padding: 4px 10px; border-radius: 8px; background: #dcfce7; color: #14532d; font-weight: 700; margin: 0;"
                                  title="Başlığı düzenlemek için tıklayın">
                                <?= ($template && isset($template['transport_process_title'])) ? htmlspecialchars($template['transport_process_title']) : 'Taşıma Süreci' ?>
                                <i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>
                            </span>
                            <div class="section-controls">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'up')" title="Yukarı taşı">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'down')" title="Aşağı taşı">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSection(this)" title="Bölümü kaldır">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="transport_process_title" value="<?= ($template && isset($template['transport_process_title'])) ? htmlspecialchars($template['transport_process_title']) : 'Taşıma Süreci' ?>">
                        <div class="rich-editor-container-inline" data-field="transport_process_content">
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSizeInline(this.value, 'transport_process_content')" title="Yazı Boyutu">
                                        <option value="">Boyut</option>
                                        <option value="1">8pt</option>
                                        <option value="2">10pt</option>
                                        <option value="3">12pt</option>
                                        <option value="4">14pt</option>
                                        <option value="5">18pt</option>
                                        <option value="6">24pt</option>
                                        <option value="7">36pt</option>
                                    </select>
                                    <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamilyInline(this.value, 'transport_process_content')" title="Yazı Tipi">
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

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','bold')" title="Kalın"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','italic')" title="İtalik"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','underline')" title="Altı Çizili"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','strikeThrough')" title="Üstü Çizili"><i class="fas fa-strikethrough"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','insertUnorderedList')" title="Madde İşareti"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','insertOrderedList')" title="Numaralı Liste"><i class="fas fa-list-ol"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','justifyLeft')" title="Sola Hizala"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','justifyCenter')" title="Ortala"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','justifyRight')" title="Sağa Hizala"><i class="fas fa-align-right"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','indent')" title="Girinti Artır"><i class="fas fa-indent"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','outdent')" title="Girinti Azalt"><i class="fas fa-outdent"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColorInline(this.value,'transport_process_content')" title="Metin Rengi">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColorInline(this.value,'transport_process_content')" title="Arka Plan Rengi" value="#ffffff">
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatTextInline('transport_process_content','hiliteColor','#ffff00')" title="Sarı Vurgu"><i class="fas fa-highlighter"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','subscript')" title="Alt Simge"><i class="fas fa-subscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','superscript')" title="Üst Simge"><i class="fas fa-superscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRuleInline('transport_process_content')" title="Yatay Çizgi"><i class="fas fa-minus"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="insertLinkInline('transport_process_content')" title="Bağlantı Ekle"><i class="fas fa-link"></i><span class="d-none d-md-inline ms-1">Link</span></button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="formatTextInline('transport_process_content','unlink')" title="Bağlantıyı Kaldır"><i class="fas fa-unlink"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="file" id="transportFileInput" style="display:none;" onchange="uploadEditorFileInline(this,'transport_process_content')" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                                    <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('transportFileInput').click()" title="Dosya yükleyip içeriğe link olarak ekle"><i class="fas fa-upload"></i><span class="d-none d-md-inline ms-1">Dosya Yükle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="removeFormattingInline('transport_process_content')" title="Tüm biçimlendirmeyi kaldır"><i class="fas fa-eraser"></i><span class="d-none d-md-inline ms-1">Biçim Temizle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSourceViewInline('transport_process_content', this)" title="HTML kaynak kodunu görüntüle/düzenle"><i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span></button>
                                </div>

                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','undo')" title="Geri Al"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('transport_process_content','redo')" title="İleri Al"><i class="fas fa-redo"></i></button>
                                </div>

                                <div class="btn-group ms-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal('transport_process_content')" title="Tablo Ekle"><i class="fas fa-table"></i></button>
                                </div>
                            </div>

                            <div contenteditable="true" class="rich-editor-textarea-inline" id="transport_process_content_editor" oninput="updateHiddenFieldInline('transport_process_content')">
                                <?= ($template && isset($template['transport_process_content']) && !empty($template['transport_process_content'])) ? $template['transport_process_content'] : '<p></p>' ?>
                            </div>
                            <textarea class="rich-editor-textarea" id="transport_process_content_source" style="display:none; font-family: 'Courier New', monospace; font-size: 12px;" oninput="updateHiddenFieldInline('transport_process_content')"></textarea>
                            <textarea name="transport_process_content" id="transport_process_content_input" style="display:none;"><?= ($template && isset($template['transport_process_content'])) ? htmlspecialchars($template['transport_process_content']) : '' ?></textarea>
                        </div>
                        </div>

                        <div class="editor-section" data-section="terms" id="template-terms">
                        <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                            <span class="editable-section-title form-label label-terms"
                                  data-field="terms_title"
                                  onclick="editSectionTitle(this)"
                                  style="cursor: pointer; padding: 4px 10px; border-radius: 8px; background: #ffedd5; color: #7c2d12; font-weight: 700; margin: 0;"
                                  title="Başlığı düzenlemek için tıklayın">
                                <?= ($template && isset($template['terms_title'])) ? htmlspecialchars($template['terms_title']) : 'Şartlar ve Koşullar' ?>
                                <i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>
                            </span>
                            <div class="section-controls">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'up')" title="Yukarı taşı">
                                    <i class="fas fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'down')" title="Aşağı taşı">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSection(this)" title="Bölümü kaldır">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="terms_title" value="<?= ($template && isset($template['terms_title'])) ? htmlspecialchars($template['terms_title']) : 'Şartlar ve Koşullar' ?>">
                        <div class="rich-editor-container-inline" data-field="terms_content">
                            <div class="btn-toolbar mb-2" role="toolbar">
                                <div class="btn-group me-2" role="group">
                                    <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSizeInline(this.value, 'terms_content')" title="Yazı Boyutu">
                                        <option value="">Boyut</option>
                                        <option value="1">8pt</option>
                                        <option value="2">10pt</option>
                                        <option value="3">12pt</option>
                                        <option value="4">14pt</option>
                                        <option value="5">18pt</option>
                                        <option value="6">24pt</option>
                                        <option value="7">36pt</option>
                                    </select>
                                    <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamilyInline(this.value, 'terms_content')" title="Yazı Tipi">
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

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','bold')" title="Kalın"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','italic')" title="İtalik"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','underline')" title="Altı Çizili"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','strikeThrough')" title="Üstü Çizili"><i class="fas fa-strikethrough"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','insertUnorderedList')" title="Madde İşareti"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','insertOrderedList')" title="Numaralı Liste"><i class="fas fa-list-ol"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','justifyLeft')" title="Sola Hizala"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','justifyCenter')" title="Ortala"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','justifyRight')" title="Sağa Hizala"><i class="fas fa-align-right"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','indent')" title="Girinti Artır"><i class="fas fa-indent"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','outdent')" title="Girinti Azalt"><i class="fas fa-outdent"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColorInline(this.value,'terms_content')" title="Metin Rengi">
                                    <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColorInline(this.value,'terms_content')" title="Arka Plan Rengi" value="#ffffff">
                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatTextInline('terms_content','hiliteColor','#ffff00')" title="Sarı Vurgu"><i class="fas fa-highlighter"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','subscript')" title="Alt Simge"><i class="fas fa-subscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','superscript')" title="Üst Simge"><i class="fas fa-superscript"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRuleInline('terms_content')" title="Yatay Çizgi"><i class="fas fa-minus"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="insertLinkInline('terms_content')" title="Bağlantı Ekle"><i class="fas fa-link"></i><span class="d-none d-md-inline ms-1">Link</span></button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="formatTextInline('terms_content','unlink')" title="Bağlantıyı Kaldır"><i class="fas fa-unlink"></i></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <input type="file" id="termsFileInput" style="display:none;" onchange="uploadEditorFileInline(this,'terms_content')" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                                    <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('termsFileInput').click()" title="Dosya yükleyip içeriğe link olarak ekle"><i class="fas fa-upload"></i><span class="d-none d-md-inline ms-1">Dosya Yükle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-warning" onclick="removeFormattingInline('terms_content')" title="Tüm biçimlendirmeyi kaldır"><i class="fas fa-eraser"></i><span class="d-none d-md-inline ms-1">Biçim Temizle</span></button>
                                </div>

                                <div class="btn-group me-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSourceViewInline('terms_content', this)" title="HTML kaynak kodunu görüntüle/düzenle"><i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span></button>
                                </div>

                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','undo')" title="Geri Al"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('terms_content','redo')" title="İleri Al"><i class="fas fa-redo"></i></button>
                                </div>

                                <div class="btn-group ms-2" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal('terms_content')" title="Tablo Ekle"><i class="fas fa-table"></i></button>
                                </div>
                            </div>

                            <div contenteditable="true" class="rich-editor-textarea-inline" id="terms_content_editor" oninput="updateHiddenFieldInline('terms_content')">
                                <?= ($template && isset($template['terms_content']) && !empty($template['terms_content'])) ? $template['terms_content'] : '<p></p>' ?>
                            </div>
                            <textarea class="rich-editor-textarea" id="terms_content_source" style="display:none; font-family: 'Courier New', monospace; font-size: 12px;" oninput="updateHiddenFieldInline('terms_content')"></textarea>
                            <textarea name="terms_content" id="terms_content_input" style="display:none;"><?= ($template && isset($template['terms_content'])) ? htmlspecialchars($template['terms_content']) : '' ?></textarea>
                        </div>
                        </div>
                    </div>

                    <!-- Yeni Bölüm Ekleme Butonu -->
                    <div class="text-center mt-3">
                        <button type="button" class="btn btn-success" onclick="addNewSection()">
                            <i class="fas fa-plus me-2"></i>Yeni Bölüm Ekle
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="transport-modes.php?tab=templates" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>İptal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Kaydet
                    </button>
                </div>
            </div>
        </form>
    </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>

    <!-- Rich Editor Modal (inline ile aynı özelliklerde, gerekirse kullanılacak) -->
    <div class="rich-editor-container" id="richEditorModal" style="display:none;">
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

                        <!-- Girinti -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('indent')" title="Girinti Artır">
                                <i class="fas fa-indent"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('outdent')" title="Girinti Azalt">
                                <i class="fas fa-outdent"></i>
                            </button>
                        </div>

                        <!-- Renk ve Vurgu -->
                        <div class="btn-group me-2" role="group">
                            <input type="color" id="textColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColor(this.value)" title="Metin Rengi">
                            <input type="color" id="bgColor" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColor(this.value)" title="Arka Plan Rengi" value="#ffffff">
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatText('hiliteColor', '#ffff00')" title="Sarı Vurgu">
                                <i class="fas fa-highlighter"></i>
                            </button>
                        </div>

                        <!-- Format ve Stil -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('subscript')" title="Alt Simge">
                                <i class="fas fa-subscript"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('superscript')" title="Üst Simge">
                                <i class="fas fa-superscript"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRule()" title="Yatay Çizgi">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    <!-- İkinci satır araç çubuğu -->
                    <div class="btn-toolbar mb-2" role="toolbar">
                        <!-- Link ve Görsel -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-primary" onclick="insertLink()" title="Bağlantı Ekle">
                                <i class="fas fa-link"></i>
                                <span class="d-none d-md-inline ms-1">Link</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="formatText('unlink')" title="Bağlantıyı Kaldır">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </div>

                        </div>

                        <!-- Tablo -->
                        <div class="btn-group me-2" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal()" title="Tablo Ekle">
                                <i class="fas fa-table"></i>
                            </button>
                        </div>

                        <!-- Temizleme ve Dosya -->
                        <div class="btn-group me-2" role="group">
                            <input type="file" id="richFileInput" style="display:none;" onchange="uploadEditorFile(this)" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                            <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('richFileInput').click()" title="Dosya yükleyip içeriğe link olarak ekle">
                                <i class="fas fa-upload"></i>
                                <span class="d-none d-md-inline ms-1">Dosya Yükle</span>
                            </button>
                        </div>

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

                        <!-- Geri al / İleri al -->
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('undo')" title="Geri Al">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('redo')" title="İleri Al">
                                <i class="fas fa-redo"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Editör Alanı -->
                <div contenteditable="true" class="rich-editor-textarea" id="richEditorContent"></div>

                <!-- Kaynak Kodu Görünümü -->
                <textarea class="rich-editor-textarea" id="richEditorSource" style="display: none; font-family: 'Courier New', monospace; font-size: 12px;"></textarea>
            </div>
            <div class="rich-editor-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Dosya yükleme sonrası link otomatik eklenir
                    </small>
                    <div>
                        <button type="button" class="btn btn-secondary me-2" onclick="closeRichEditor()">
                            <i class="fas fa-times"></i> İptal
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveRichContent()">
                            <i class="fas fa-save"></i> Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablo Oluşturucu Modal -->
    <div class="rich-editor-container" id="tableModal" style="display: none;">
        <div class="rich-editor-modal" style="max-width: 500px;">
            <div class="rich-editor-header">
                <h5>Tablo Oluştur</h5>
                <button type="button" class="btn-close" onclick="closeTableModal()"></button>
            </div>
            <div class="rich-editor-content">
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Satır Sayısı</label>
                        <input type="number" id="tableRows" class="form-control form-control-sm" value="3" min="1" max="20">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Sütun Sayısı</label>
                        <input type="number" id="tableCols" class="form-control form-control-sm" value="3" min="1" max="10">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tablo Başlığı (Opsiyonel)</label>
                    <input type="text" id="tableCaption" class="form-control form-control-sm" placeholder="Tablo başlığı girin...">
                </div>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tableHeader" checked>
                        <label class="form-check-label" for="tableHeader">İlk satırı başlık olarak kullan</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tablo Stili</label>
                    <select id="tableStyle" class="form-select form-select-sm">
                        <option value="basic">Temel</option>
                        <option value="striped">Çizgili</option>
                        <option value="bordered">Kenarlıklı</option>
                        <option value="hover">Hover Efektli</option>
                    </select>
                </div>
            </div>
            <div class="rich-editor-footer">
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary btn-sm me-2" onclick="closeTableModal()"><i class="fas fa-times"></i> İptal</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="insertTable()"><i class="fas fa-table"></i> Tablo Ekle</button>
                </div>
            </div>
        </div>
    </div>

        <script>
        // Global değişkenler
        let currentField = null;
        let isSourceView = false;

        // Rich Editor'ı aç
        function openRichEditor(field) {
            currentField = field;
            const modal = document.getElementById('richEditorModal');
            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');
            const title = document.getElementById('richEditorTitle');

            // İçeriği al
            const inputField = document.getElementById(field + '_input');
            const fieldValue = inputField.value.trim();

            // Başlığı ayarla
            let fieldTitle = '';
            if (field === 'services_content') fieldTitle = 'Hizmetlerimiz';
            else if (field === 'transport_process_content') fieldTitle = 'Taşıma Süreci';
            else if (field === 'terms_content') fieldTitle = 'Şartlar ve Koşullar';

            title.textContent = fieldTitle + ' İçeriğini Düzenle';

            // İçeriği editöre yükle
            content.innerHTML = fieldValue;
            source.value = fieldValue;

            // Modalı göster
            modal.style.display = 'flex';

            // Kaynak görünümünü sıfırla
            isSourceView = false;
            content.style.display = 'block';
            source.style.display = 'none';

            // Editöre odaklan
            content.focus();
        }

        // Rich Editor'ı kapat
        function closeRichEditor() {
            const modal = document.getElementById('richEditorModal');
            modal.style.display = 'none';
        }

        // İçeriği kaydet
        function saveRichContent() {
            if (!currentField) return;

            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');

            // Kaynak görünümündeyse önce HTML içeriğini al
            let htmlContent = isSourceView ? source.value : content.innerHTML;

            // İçeriği form alanına kaydet
            const inputField = document.getElementById(currentField + '_input');
            inputField.value = htmlContent;

            // Önizlemeyi güncelle
            const previewField = document.getElementById(currentField + '_preview');
            previewField.innerHTML = htmlContent || '<em class="text-muted">İçerik henüz eklenmemiş. Düzenlemek için butona tıklayın.</em>';

            // Modalı kapat
            closeRichEditor();
        }

                // Metin biçimlendirme
        function formatText(command, fieldName, value = null) {
            const editor = document.getElementById(fieldName + '_editor');
            if (editor) {
                editor.focus();
                document.execCommand(command, false, value);
                updateHiddenField(fieldName);
            }
        }

        // Yazı tipi değiştirme
        function changeFontFamily(fontFamily, fieldName) {
            if (fontFamily) {
                const editor = document.getElementById(fieldName + '_editor');
                if (editor) {
                    editor.focus();
                    document.execCommand('fontName', false, fontFamily);
                    updateHiddenField(fieldName);
                }
            }
        }

        // Font boyutu değiştirme
        function changeFontSize(size, fieldName) {
            if (size) {
                const editor = document.getElementById(fieldName + '_editor');
                if (editor) {
                    editor.focus();
                    document.execCommand('fontSize', false, size);
                    updateHiddenField(fieldName);
                }
            }
        }

        // Link ekleme
        function insertLink(fieldName) {
            const url = prompt('Bağlantı URL\'sini girin:', 'https://');
            if (url && url !== 'https://') {
                const editor = document.getElementById(fieldName + '_editor');
                if (editor) {
                    editor.focus();
                    document.execCommand('createLink', false, url);
                    updateHiddenField(fieldName);
                }
            }
        }

        // Hidden field'ı güncelle
        function updateHiddenField(fieldName) {
            const editor = document.getElementById(fieldName + '_editor');
            const hiddenField = document.getElementById(fieldName + '_input');

            if (editor && hiddenField) {
                hiddenField.value = editor.innerHTML;
            }
        }

        // Metin biçimlendirme (modal için)
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Yeni zenginleştirilmiş fonksiyonlar
        function changeTextColor(color) {
            document.execCommand('foreColor', false, color);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        function changeBackgroundColor(color) {
            document.execCommand('hiliteColor', false, color);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Font boyutu değiştirme (modal için)
        function changeFontSize(size) {
            if (size) {
                document.execCommand('fontSize', false, size);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        // Font ailesi değiştirme (modal için)
        function changeFontFamily(fontFamily) {
            if (fontFamily) {
                document.execCommand('fontName', false, fontFamily);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        // Yatay çizgi ekleme
        function insertHorizontalRule() {
            document.execCommand('insertHorizontalRule', false, null);
            const editor = document.getElementById('richEditorContent');
            if (editor) editor.focus();
        }

        // Link ekleme (modal için)
        function insertLink() {
            const url = prompt('Bağlantı URL\'sini girin:', 'https://');
            if (url && url !== 'https://') {
                document.execCommand('createLink', false, url);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
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

        function toggleSourceView() {
            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');
            const button = event.target.closest('button');

            if (!content || !source || !button) {
                console.error('Rich editor elementleri bulunamadı');
                return;
            }

            if (!isSourceView) {
                // Normal görünümden kaynak koduna geç
                source.value = content.innerHTML;
                content.style.display = 'none';
                source.style.display = 'block';
                button.classList.add('active');
                button.innerHTML = '<i class="fas fa-eye"></i><span class="d-none d-md-inline ms-1"> Normal Görünüm</span>';
                isSourceView = true;
            } else {
                // Kaynak kodundan normal görünüme geç
                content.innerHTML = source.value;
                content.style.display = 'block';
                source.style.display = 'none';
                button.classList.remove('active');
                button.innerHTML = '<i class="fas fa-code"></i><span class="d-none d-md-inline ms-1"> Kaynak Kodu</span>';
                isSourceView = false;
            }
        }

        // Tablo fonksiyonları
        function showTableModal(targetField) {
            window._inlineTargetField = targetField || window._inlineTargetField || null;
            const modalEl = document.getElementById('tableModal');
            if (!modalEl) {
                console.warn('tableModal bulunamadı; tablo inline olarak temel 2x2 eklenecek');
                const tableHTML = '<table class="table" style="width:100%; border-collapse:collapse; margin:10px 0;">\n<tr><th style="border:1px solid #ddd; padding:8px; background:#f8f9fa;">Başlık</th><th style="border:1px solid #ddd; padding:8px; background:#f8f9fa;">Başlık</th></tr>\n<tr><td style="border:1px solid #ddd; padding:8px;">İçerik</td><td style="border:1px solid #ddd; padding:8px;">İçerik</td></tr>\n</table>';
                if (window._inlineTargetField) {
                    const ed = document.getElementById(window._inlineTargetField + '_editor');
                    if (ed) { ed.focus(); document.execCommand('insertHTML', false, tableHTML); updateHiddenFieldInline(window._inlineTargetField); }
                }
                return;
            }
            modalEl.style.display = 'flex';
        }

        function closeTableModal() {
            document.getElementById('tableModal').style.display = 'none';
        }

        function insertTable() {
            const rows = parseInt(document.getElementById('tableRows').value);
            const cols = parseInt(document.getElementById('tableCols').value);
            const caption = document.getElementById('tableCaption').value;
            const hasHeader = document.getElementById('tableHeader').checked;
            const style = document.getElementById('tableStyle').value;

            let tableClass = 'table';
            switch(style) {
                case 'striped': tableClass = 'table table-striped'; break;
                case 'bordered': tableClass = 'table table-bordered'; break;
                case 'hover': tableClass = 'table table-hover'; break;
                default: tableClass = 'table';
            }

            let tableHTML = `<table class="${tableClass}" style="width: 100%; border-collapse: collapse; margin: 10px 0;">`;

            if (caption) {
                tableHTML += `<caption style="caption-side: top; text-align: center; font-weight: bold; margin-bottom: 10px;">${caption}</caption>`;
            }

            for (let i = 0; i < rows; i++) {
                tableHTML += '<tr>';
                for (let j = 0; j < cols; j++) {
                    if (i === 0 && hasHeader) {
                        tableHTML += '<th style="border: 1px solid #ddd; padding: 8px; background-color: #f8f9fa; font-weight: bold;">Başlık</th>';
                    } else {
                        tableHTML += '<td style="border: 1px solid #ddd; padding: 8px;">İçerik</td>';
                    }
                }
                tableHTML += '</tr>';
            }
            tableHTML += '</table>';

            // Inline hedef alan varsa inline editöre ekle
            if (window._inlineTargetField) {
                const editorInline = document.getElementById(window._inlineTargetField + '_editor');
                if (editorInline) {
                    editorInline.focus();
                    document.execCommand('insertHTML', false, tableHTML);
                    updateHiddenFieldInline(window._inlineTargetField);
                }
            } else {
                // Modal editöre ekle fallback
                const editor = document.getElementById('richEditorContent');
                if (editor) {
                    editor.focus();
                    document.execCommand('insertHTML', false, tableHTML);
                }
            }

            closeTableModal();
        }

        // Dosya yükleme fonksiyonu
        function uploadEditorFile(input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);

            // Loading efekti
            const button = document.querySelector('button[onclick*="richFileInput"]');
            const originalText = button.innerHTML;
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
                button.disabled = true;
            }

            fetch('../api/upload-file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Loading'i kaldır
                if (button) {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }

                if (data.success) {
                    const fileName = data.filename;
                    const filePath = data.file_path;

                    // Link oluştur ve editöre ekle
                    const linkHtml = `<a href="${filePath}" target="_blank" style="color: #007bff; text-decoration: underline;">${fileName}</a>`;

                    const editor = document.getElementById('richEditorContent');
                    if (editor) {
                        editor.focus();
                        document.execCommand('insertHTML', false, linkHtml + ' ');
                    }

                    // Input'u temizle
                    input.value = '';
                } else {
                    alert('Dosya yükleme hatası');
                }
            })
            .catch(error => {
                // Loading'i kaldır
                if (button) {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
                alert('Dosya yükleme hatası: ' + error);
            });
        }

        // Biçimlendirmeyi kaldır
        function removeFormatting() {
            if (confirm('Tüm biçimlendirmeyi kaldırmak istediğinizden emin misiniz?')) {
                document.execCommand('removeFormat', false, null);
                const editor = document.getElementById('richEditorContent');
                if (editor) editor.focus();
            }
        }

        // INLINE EDITÖR YARDIMCI FONKSİYONLARI
        function getInlineEditor(field) { return document.getElementById(field + '_editor'); }
        function getInlineSource(field) { return document.getElementById(field + '_source'); }
        function getInlineHidden(field) { return document.getElementById(field + '_input'); }

        function formatTextInline(field, command, value = null) {
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand(command, false, value);
            updateHiddenFieldInline(field);
        }

        function changeFontFamilyInline(fontFamily, field) {
            if (!fontFamily) return;
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand('fontName', false, fontFamily);
            updateHiddenFieldInline(field);
        }

        function changeFontSizeInline(size, field) {
            if (!size) return;
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand('fontSize', false, size);
            updateHiddenFieldInline(field);
        }

        function insertHorizontalRuleInline(field) {
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand('insertHorizontalRule', false, null);
            updateHiddenFieldInline(field);
        }

        function insertLinkInline(field) {
            const url = prompt('Bağlantı URL\'sini girin:', 'https://');
            if (url && url !== 'https://') {
                const editor = getInlineEditor(field);
                if (!editor) return;
                editor.focus();
                document.execCommand('createLink', false, url);
                updateHiddenFieldInline(field);
            }
        }

        function changeTextColorInline(color, field) {
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand('foreColor', false, color);
            updateHiddenFieldInline(field);
        }

        function changeBackgroundColorInline(color, field) {
            const editor = getInlineEditor(field);
            if (!editor) return;
            editor.focus();
            document.execCommand('hiliteColor', false, color);
            updateHiddenFieldInline(field);
        }

        function updateHiddenFieldInline(field) {
            const editor = getInlineEditor(field);
            const source = getInlineSource(field);
            const hidden = getInlineHidden(field);

            console.log('updateHiddenFieldInline called for:', field);
            console.log('Editor found:', !!editor);
            console.log('Source found:', !!source);
            console.log('Hidden found:', !!hidden);

            if (!hidden) {
                console.warn('Hidden input not found for field:', field);
                return;
            }

            // Kaynak görünümündeyse source'tan, değilse editor'den al
            if (source && source.style.display === 'block') {
                hidden.value = source.value;
                console.log('Updated from source:', source.value.substring(0, 50) + '...');
            } else if (editor) {
                hidden.value = editor.innerHTML;
                console.log('Updated from editor:', editor.innerHTML.substring(0, 50) + '...');
            }
        }

        function toggleSourceViewInline(field, buttonEl) {
            const editor = getInlineEditor(field);
            const source = getInlineSource(field);
            if (!editor || !source) return;

            if (source.style.display === 'block') {
                // Kaynak -> Görsel
                editor.innerHTML = source.value;
                editor.style.display = 'block';
                source.style.display = 'none';
                buttonEl.classList.remove('active');
                buttonEl.innerHTML = '<i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span>';
            } else {
                // Görsel -> Kaynak
                source.value = editor.innerHTML;
                editor.style.display = 'none';
                source.style.display = 'block';
                buttonEl.classList.add('active');
                buttonEl.innerHTML = '<i class="fas fa-eye"></i><span class="d-none d-md-inline ms-1">Normal Görünüm</span>';
            }

            updateHiddenFieldInline(field);
        }

        function removeFormattingInline(field) {
            const editor = getInlineEditor(field);
            if (!editor) return;
            if (confirm('Tüm biçimlendirmeyi kaldırmak istediğinizden emin misiniz?')) {
                editor.focus();
                document.execCommand('removeFormat', false, null);
                updateHiddenFieldInline(field);
            }
        }

        function uploadEditorFileInline(input, field) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);

            fetch('../api/upload-file.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const editor = getInlineEditor(field);
                        if (!editor) return;
                        const fileName = data.filename;
                        const filePath = data.file_path;
                        const linkHtml = `<a href="${filePath}" target="_blank" style="color:#007bff; text-decoration: underline;">${fileName}</a>`;
                        editor.focus();
                        document.execCommand('insertHTML', false, linkHtml + ' ');
                        updateHiddenFieldInline(field);
                    } else {
                        alert('Dosya yükleme hatası');
                    }
                    input.value = '';
                })
                .catch(() => { alert('Dosya yükleme hatası'); input.value = ''; });
        }

        // Kaynak kodu görünümünü aç/kapat
        function toggleSourceView() {
            const content = document.getElementById('richEditorContent');
            const source = document.getElementById('richEditorSource');

            if (!isSourceView) {
                // Normal görünümden kaynak koduna geç
                source.value = content.innerHTML;
                content.style.display = 'none';
                source.style.display = 'block';
                source.focus();
                isSourceView = true;
            } else {
                // Kaynak kodundan normal görünüme geç
                content.innerHTML = source.value;
                source.style.display = 'none';
                content.style.display = 'block';
                content.focus();
                isSourceView = false;
            }
        }

        // Dosya yükleme
        function uploadEditorFile(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            // Loading göster
            const button = input.parentElement.querySelector('button');
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
                button.disabled = true;
            }

            // Dosyayı yükle
            fetch('../api/upload-editor-image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Loading'i kaldır
                if (button) {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }

                if (data.location) {
                    const fileUrl = data.location;
                    const fileName = file.name;
                    const fileType = file.type;
                    let linkHTML = '';

                    // Dosya tipine göre HTML oluştur
                    if (fileType.startsWith('image/')) {
                        linkHTML = `<br><img src="${fileUrl}" alt="${fileName}" style="max-width: 100%; height: auto; margin: 10px 0;"><br>`;
                    } else {
                        linkHTML = `<br><a href="${fileUrl}" target="_blank" style="color: #2c5aa0; text-decoration: underline;"><i class="fas fa-file"></i> ${fileName}</a><br>`;
                    }

                    // İçeriğe ekle
                    const editor = document.getElementById('richEditorContent');
                    if (editor) {
                        editor.focus();
                        document.execCommand('insertHTML', false, linkHTML);
                    }
                } else {
                    alert('Dosya yükleme hatası');
                }
            })
            .catch(error => {
                // Loading'i kaldır
                if (button) {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
                alert('Dosya yükleme hatası: ' + error);
            });
        }

        // Bölüm başlığı düzenleme fonksiyonu
        function editSectionTitle(element) {
            if (element.classList.contains('editing')) return;

            // Düzenleme sırasında tıklamayı engelle
            element.style.pointerEvents = 'none';

            const field = element.getAttribute('data-field');
            const currentText = element.textContent.replace('✎', '').replace('🖉', '').trim();

            element.classList.add('editing');

            const input = document.createElement('input');
            input.type = 'text';
            input.value = currentText;
            input.className = 'form-control form-control-sm';
            input.style.display = 'inline-block';
            input.style.width = 'auto';
            input.style.minWidth = '200px';

            // Mevcut içeriği temizle ve input'u ekle
            element.innerHTML = '';
            element.appendChild(input);
            input.focus();
            input.select();

            let isSaving = false; // Çift kaydetmeyi önle

            function saveTitle() {
                if (isSaving) return;
                isSaving = true;

                const newValue = input.value.trim();
                if (newValue === '') {
                    alert('Başlık boş olamaz!');
                    input.focus();
                    isSaving = false;
                    return;
                }

                // Event listener'ları kaldır
                input.removeEventListener('blur', saveTitle);
                input.removeEventListener('keypress', handleKeypress);

                // İçeriği güncelle
                element.innerHTML = newValue + '<i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>';
                element.classList.remove('editing');
                element.style.pointerEvents = 'auto'; // Tıklamayı tekrar etkinleştir

                // Hidden input'u güncelle
                const hiddenInput = document.querySelector(`input[name="${field}"]`);
                if (hiddenInput) {
                    hiddenInput.value = newValue;
                }

                isSaving = false;
            }

            function cancelEdit() {
                if (isSaving) return;

                // Event listener'ları kaldır
                input.removeEventListener('blur', saveTitle);
                input.removeEventListener('keypress', handleKeypress);

                // Orijinal içeriği geri yükle
                element.innerHTML = currentText + '<i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>';
                element.classList.remove('editing');
                element.style.pointerEvents = 'auto'; // Tıklamayı tekrar etkinleştir
            }

            function handleKeypress(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    saveTitle();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    cancelEdit();
                }
            }

            // Event listener'ları ekle
            input.addEventListener('blur', saveTitle);
            input.addEventListener('keypress', handleKeypress);
        }

        // Bölüm sıralama fonksiyonları
        function moveSection(button, direction) {
            const section = button.closest('.editor-section');
            const container = section.parentElement;

            if (direction === 'up') {
                const prevSection = section.previousElementSibling;
                if (prevSection && prevSection.classList.contains('editor-section')) {
                    container.insertBefore(section, prevSection);
                    updateSectionOrder();
                }
            } else if (direction === 'down') {
                const nextSection = section.nextElementSibling;
                if (nextSection && nextSection.classList.contains('editor-section')) {
                    container.insertBefore(nextSection, section);
                    updateSectionOrder();
                }
            }
        }

        // Bölüm sıralamasını güncelle
        function updateSectionOrder() {
            const container = document.getElementById('sections-container');
            const sections = container.querySelectorAll('.editor-section');
            const order = [];

            sections.forEach(section => {
                const sectionType = section.getAttribute('data-section');
                order.push(sectionType);
            });

            document.getElementById('section_order').value = JSON.stringify(order);
        }

        // Bölüm kaldırma fonksiyonu
        function removeSection(button) {
            const section = button.closest('.editor-section');
            const sectionName = section.querySelector('.editable-section-title').textContent.replace('✎', '').trim();

            if (confirm(`"${sectionName}" bölümünü kaldırmak istediğinizden emin misiniz?`)) {
                section.remove();
                updateSectionOrder();
            }
        }

        // Yeni bölüm ekleme fonksiyonu
        let dynamicSectionCounter = 1;

        function addNewSection() {
            const container = document.getElementById('sections-container');

            const idPart = Date.now();
            const sectionKey = `dynamic_${idPart}`;
            const sectionHtml = `
                <div class="editor-section" data-section="${sectionKey}">
                    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <span class="editable-section-title form-label"
                              data-field="dynamic_section_${idPart}_title"
                              onclick="editSectionTitle(this)"
                              style="cursor: pointer; padding: 4px 10px; border-radius: 8px; background: #f3f4f6; color: #374151; font-weight: 700; margin: 0;"
                              title="Başlığı düzenlemek için tıklayın">
                            Yeni Bölüm
                            <i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>
                        </span>
                        <div class="section-controls">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'up')" title="Yukarı taşı">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'down')" title="Aşağı taşı">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSection(this)" title="Bölümü kaldır">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="dynamic_section_${idPart}_title" value="Yeni Bölüm">
                    <div class="rich-editor-container-inline" data-field="dynamic_section_${idPart}_content">
                        <div class="btn-toolbar mb-2" role="toolbar">
                            <div class="btn-group me-2" role="group">
                                <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSizeInline(this.value, 'dynamic_section_${idPart}_content')" title="Yazı Boyutu">
                                    <option value="">Boyut</option>
                                    <option value="1">8pt</option>
                                    <option value="2">10pt</option>
                                    <option value="3">12pt</option>
                                    <option value="4">14pt</option>
                                    <option value="5">18pt</option>
                                    <option value="6">24pt</option>
                                    <option value="7">36pt</option>
                                </select>
                                <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamilyInline(this.value, 'dynamic_section_${idPart}_content')" title="Yazı Tipi">
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

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','bold')" title="Kalın"><i class="fas fa-bold"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','italic')" title="İtalik"><i class="fas fa-italic"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','underline')" title="Altı Çizili"><i class="fas fa-underline"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','strikeThrough')" title="Üstü Çizili"><i class="fas fa-strikethrough"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','insertUnorderedList')" title="Madde İşareti"><i class="fas fa-list-ul"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','insertOrderedList')" title="Numaralı Liste"><i class="fas fa-list-ol"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','justifyLeft')" title="Sola Hizala"><i class="fas fa-align-left"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','justifyCenter')" title="Ortala"><i class="fas fa-align-center"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','justifyRight')" title="Sağa Hizala"><i class="fas fa-align-right"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','indent')" title="Girinti Artır"><i class="fas fa-indent"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','outdent')" title="Girinti Azalt"><i class="fas fa-outdent"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColorInline(this.value,'dynamic_section_${idPart}_content')" title="Metin Rengi">
                                <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColorInline(this.value,'dynamic_section_${idPart}_content')" title="Arka Plan Rengi" value="#ffffff">
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatTextInline('dynamic_section_${idPart}_content','hiliteColor','#ffff00')" title="Sarı Vurgu"><i class="fas fa-highlighter"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','subscript')" title="Alt Simge"><i class="fas fa-subscript"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','superscript')" title="Üst Simge"><i class="fas fa-superscript"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRuleInline('dynamic_section_${idPart}_content')" title="Yatay Çizgi"><i class="fas fa-minus"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-primary" onclick="insertLinkInline('dynamic_section_${idPart}_content')" title="Bağlantı Ekle"><i class="fas fa-link"></i><span class="d-none d-md-inline ms-1">Link</span></button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="formatTextInline('dynamic_section_${idPart}_content','unlink')" title="Bağlantıyı Kaldır"><i class="fas fa-unlink"></i></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <input type="file" id="dynamicFileInput_${idPart}" style="display:none;" onchange="uploadEditorFileInline(this,'dynamic_section_${idPart}_content')" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                                <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('dynamicFileInput_${idPart}').click()" title="Dosya yükleyip içeriğe link olarak ekle"><i class="fas fa-upload"></i><span class="d-none d-md-inline ms-1">Dosya Yükle</span></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-warning" onclick="removeFormattingInline('dynamic_section_${idPart}_content')" title="Tüm biçimlendirmeyi kaldır"><i class="fas fa-eraser"></i><span class="d-none d-md-inline ms-1">Biçim Temizle</span></button>
                            </div>

                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSourceViewInline('dynamic_section_${idPart}_content', this)" title="HTML kaynak kodunu görüntüle/düzenle"><i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span></button>
                            </div>

                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','undo')" title="Geri Al"><i class="fas fa-undo"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${idPart}_content','redo')" title="İleri Al"><i class="fas fa-redo"></i></button>
                            </div>

                            <div class="btn-group ms-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal('dynamic_section_${idPart}_content')" title="Tablo Ekle"><i class="fas fa-table"></i></button>
                            </div>
                        </div>

                        <div contenteditable="true" class="rich-editor-textarea-inline" id="dynamic_section_${idPart}_content_editor" oninput="updateHiddenFieldInline('dynamic_section_${idPart}_content')">
                            <p>Bu bölüm için içerik ekleyin...</p>
                        </div>
                        <textarea class="rich-editor-textarea" id="dynamic_section_${idPart}_content_source" style="display:none; font-family: 'Courier New', monospace; font-size: 12px;" oninput="updateHiddenFieldInline('dynamic_section_${idPart}_content')"></textarea>
                        <textarea name="dynamic_section_${idPart}_content" id="dynamic_section_${idPart}_content_input" style="display:none;"></textarea>
                    </div>
                </div>
            `;

            // Yeni bölümü container'a ekle
            container.insertAdjacentHTML('beforeend', sectionHtml);

            // Yeni eklenen bölümün editörünü başlat
            setTimeout(() => {
                const editor = document.getElementById(`dynamic_section_${idPart}_content_editor`);
                const hiddenInput = document.getElementById(`dynamic_section_${idPart}_content_input`);

                if (editor && hiddenInput) {
                    // İlk içeriği ayarla
                    editor.innerHTML = '<p>Bu bölüm için içerik ekleyin...</p>';
                    hiddenInput.value = '<p>Bu bölüm için içerik ekleyin...</p>';

                    // Editör event'lerini bağla
                    updateHiddenFieldInline(`dynamic_section_${idPart}_content`);

                    // Debug: Dinamik bölüm oluşturuldu
                    if (window.location.search.includes('debug')) {
                        console.log('Dynamic section created:', sectionKey);
                        console.log('Editor:', editor);
                        console.log('Hidden input:', hiddenInput);
                    }
                }
            }, 100);

            // Sıralamayı güncelle
            updateSectionOrder();

            dynamicSectionCounter++;
        }

        // Form gönderiminden önce tüm editörleri güncelle
        function prepareFormSubmission() {
            console.log('=== PREPARE FORM SUBMISSION ===');

            try {
                // Tüm inline editörleri güncelle
                const allEditors = document.querySelectorAll('[id$="_content_editor"]');
                console.log('Found editors:', allEditors.length);

                allEditors.forEach(editor => {
                    const fieldName = editor.id.replace('_editor', '');
                    updateHiddenFieldInline(fieldName);
                    console.log('Updated field:', fieldName);
                });

                // Sıralamayı güncelle
                updateSectionOrder();
                console.log('Section order updated');

                // Form verilerini kontrol et
                const formData = new FormData(document.querySelector('form'));
                console.log('=== FORM DATA ===');
                for (let [key, value] of formData.entries()) {
                    if (key.includes('template_name') || key.includes('dynamic_section_') || key.includes('services_') || key.includes('section_order')) {
                        console.log(key + ':', value);
                    }
                }

                console.log('Form submission allowed');
                return true; // Form gönderimini devam ettir

            } catch (error) {
                console.error('Error in prepareFormSubmission:', error);
                return false; // Hata durumunda form gönderimini engelle
            }
        }

        // Sayfa yüklendiğinde bölümleri sıralı şekilde yükle
        document.addEventListener('DOMContentLoaded', function() {
            loadSectionsInOrder();
        });

        function loadSectionsInOrder() {
            const container = document.getElementById('sections-container');
            const sectionOrder = <?php echo json_encode($template['section_order'] ?? ['services', 'transport', 'terms']); ?>;
            const dynamicSections = <?php echo json_encode($template['dynamic_sections'] ?? []); ?>;

            // Sıralama bilgisini güncelle
            document.getElementById('section_order').value = JSON.stringify(sectionOrder);

            // Mevcut statik bölümleri taşıyacağımız id eşlemesi
            const staticMap = {
                services: 'template-services',
                transport: 'template-transport',
                terms: 'template-terms'
            };

            sectionOrder.forEach(sectionType => {
                let sectionHtml = '';

                if (staticMap[sectionType]) {
                    // Statik bölümü kopyalamak yerine GERÇEK düğümü taşı
                    const el = document.getElementById(staticMap[sectionType]);
                    if (el) {
                        container.appendChild(el);
                    }
                } else if (sectionType.startsWith('dynamic_')) {
                    // Dinamik bölüm
                    const sectionId = sectionType.replace('dynamic_', '');
                    const titleKey = `dynamic_section_${sectionId}_title`;
                    const contentKey = `dynamic_section_${sectionId}_content`;

                    if (dynamicSections[titleKey] && dynamicSections[contentKey]) {
                        sectionHtml = createDynamicSectionHTML(sectionId, dynamicSections[titleKey], dynamicSections[contentKey]);
                    }

                    if (sectionHtml) {
                        container.insertAdjacentHTML('beforeend', sectionHtml);
                        const editor = document.getElementById(`dynamic_section_${sectionId}_content_editor`);
                        if (editor) {
                            updateHiddenFieldInline(`dynamic_section_${sectionId}_content`);
                        }
                    }
                }
            });
        }

        function createDynamicSectionHTML(sectionId, title, content) {
            return `
                <div class="editor-section" data-section="dynamic_${sectionId}">
                    <div class="section-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <span class="editable-section-title form-label"
                              data-field="dynamic_section_${sectionId}_title"
                              onclick="editSectionTitle(this)"
                              style="cursor: pointer; padding: 4px 10px; border-radius: 8px; background: #f3f4f6; color: #374151; font-weight: 700; margin: 0;"
                              title="Başlığı düzenlemek için tıklayın">
                            ${title}
                            <i class="fas fa-edit ms-2" style="font-size: 0.8em; opacity: 0.6;"></i>
                        </span>
                        <div class="section-controls">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'up')" title="Yukarı taşı">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="moveSection(this, 'down')" title="Aşağı taşı">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSection(this)" title="Bölümü kaldır">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="dynamic_section_${sectionId}_title" value="${title}">
                    <div class="rich-editor-container-inline" data-field="dynamic_section_${sectionId}_content">
                        <div class="btn-toolbar mb-2" role="toolbar">
                            <div class="btn-group me-2" role="group">
                                <select class="form-select form-select-sm" style="width: 80px;" onchange="changeFontSizeInline(this.value, 'dynamic_section_${sectionId}_content')" title="Yazı Boyutu">
                                    <option value="">Boyut</option>
                                    <option value="1">8pt</option>
                                    <option value="2">10pt</option>
                                    <option value="3">12pt</option>
                                    <option value="4">14pt</option>
                                    <option value="5">18pt</option>
                                    <option value="6">24pt</option>
                                    <option value="7">36pt</option>
                                </select>
                                <select class="form-select form-select-sm" style="width: 120px;" onchange="changeFontFamilyInline(this.value, 'dynamic_section_${sectionId}_content')" title="Yazı Tipi">
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
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','bold')" title="Kalın"><i class="fas fa-bold"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','italic')" title="İtalik"><i class="fas fa-italic"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','underline')" title="Altı Çizili"><i class="fas fa-underline"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','strikeThrough')" title="Üstü Çizili"><i class="fas fa-strikethrough"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','insertUnorderedList')" title="Madde İşareti"><i class="fas fa-list-ul"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','insertOrderedList')" title="Numaralı Liste"><i class="fas fa-list-ol"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','justifyLeft')" title="Sola Hizala"><i class="fas fa-align-left"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','justifyCenter')" title="Ortala"><i class="fas fa-align-center"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','justifyRight')" title="Sağa Hizala"><i class="fas fa-align-right"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','indent')" title="Girinti Artır"><i class="fas fa-indent"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','outdent')" title="Girinti Azalt"><i class="fas fa-outdent"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeTextColorInline(this.value,'dynamic_section_${sectionId}_content')" title="Metin Rengi">
                                <input type="color" class="btn btn-sm" style="width: 40px; height: 31px; padding: 2px;" onchange="changeBackgroundColorInline(this.value,'dynamic_section_${sectionId}_content')" title="Arka Plan Rengi" value="#ffffff">
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="formatTextInline('dynamic_section_${sectionId}_content','hiliteColor','#ffff00')" title="Sarı Vurgu"><i class="fas fa-highlighter"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','subscript')" title="Alt Simge"><i class="fas fa-subscript"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','superscript')" title="Üst Simge"><i class="fas fa-superscript"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertHorizontalRuleInline('dynamic_section_${sectionId}_content')" title="Yatay Çizgi"><i class="fas fa-minus"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-primary" onclick="insertLinkInline('dynamic_section_${sectionId}_content')" title="Bağlantı Ekle"><i class="fas fa-link"></i><span class="d-none d-md-inline ms-1">Link</span></button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="formatTextInline('dynamic_section_${sectionId}_content','unlink')" title="Bağlantıyı Kaldır"><i class="fas fa-unlink"></i></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <input type="file" id="dynamicFileInput_${sectionId}" style="display:none;" onchange="uploadEditorFileInline(this,'dynamic_section_${sectionId}_content')" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                                <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('dynamicFileInput_${sectionId}').click()" title="Dosya yükleyip içeriğe link olarak ekle"><i class="fas fa-upload"></i><span class="d-none d-md-inline ms-1">Dosya Yükle</span></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-warning" onclick="removeFormattingInline('dynamic_section_${sectionId}_content')" title="Tüm biçimlendirmeyi kaldır"><i class="fas fa-eraser"></i><span class="d-none d-md-inline ms-1">Biçim Temizle</span></button>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSourceViewInline('dynamic_section_${sectionId}_content', this)" title="HTML kaynak kodunu görüntüle/düzenle"><i class="fas fa-code"></i><span class="d-none d-md-inline ms-1">Kaynak Kodu</span></button>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','undo')" title="Geri Al"><i class="fas fa-undo"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatTextInline('dynamic_section_${sectionId}_content','redo')" title="İleri Al"><i class="fas fa-redo"></i></button>
                            </div>
                            <div class="btn-group ms-2" role="group">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showTableModal('dynamic_section_${sectionId}_content')" title="Tablo Ekle"><i class="fas fa-table"></i></button>
                            </div>
                        </div>
                        <div contenteditable="true" class="rich-editor-textarea-inline" id="dynamic_section_${sectionId}_content_editor" oninput="updateHiddenFieldInline('dynamic_section_${sectionId}_content')">
                            ${content || '<p>Bu bölüm için içerik ekleyin...</p>'}
                        </div>
                        <textarea class="rich-editor-textarea" id="dynamic_section_${sectionId}_content_source" style="display:none; font-family: 'Courier New', monospace; font-size: 12px;" oninput="updateHiddenFieldInline('dynamic_section_${sectionId}_content')"></textarea>
                        <textarea name="dynamic_section_${sectionId}_content" id="dynamic_section_${sectionId}_content_input" style="display:none;">${content || ''}</textarea>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>
