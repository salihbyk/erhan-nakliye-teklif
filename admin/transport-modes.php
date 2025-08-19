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

        // AJAX işlemleri
    if (isset($_GET['action']) && $_GET['action'] === 'get_template') {
        header('Content-Type: application/json');
        $id = $_GET['id'] ?? 0;

        $stmt = $db->prepare("SELECT * FROM quote_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($template) {
            // HTML decode yap
            if (isset($template['services_content'])) {
                $template['services_content'] = html_entity_decode($template['services_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (isset($template['transport_process_content'])) {
                $template['transport_process_content'] = html_entity_decode($template['transport_process_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (isset($template['terms_content'])) {
                $template['terms_content'] = html_entity_decode($template['terms_content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            echo json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo json_encode(['error' => 'Template not found']);
        }
        exit;
    }

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
                    SET template_name = ?, services_content = ?, terms_content = ?, transport_process_content = ?, trade_type = ?, currency = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$template_name, $services_content, $terms_content, $transport_process_content, $trade_type, $currency, $is_active, $id]);
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
        /* Minimal ve kibar tasarım */
        body {
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #2d3748;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px; /* Sidebar width + margin */
            padding: 20px 30px;
            min-height: 100vh;
            background: white;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Minimal header tasarımı */
        .page-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 24px 0;
            margin-bottom: 32px;
        }

        .page-header h1 {
            margin: 0;
            font-weight: 600;
            font-size: 1.875rem;
            color: #1a202c;
        }

        .page-header p {
            margin: 4px 0 0;
            color: #718096;
            font-size: 0.875rem;
        }

        .page-header .btn {
            background: #4f46e5;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .page-header .btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }

        .page-header .btn-outline-secondary {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .page-header .btn-outline-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Dinamik renkli card tasarımı */
        .content-section {
            background: white;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            margin-bottom: 24px;
            overflow: hidden;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .section-header {
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 20px;
            transition: border-bottom-color 0.3s ease;
        }

        .section-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.125rem;
            color: #1a202c;
        }

        /* Dinamik başlık */
        .dynamic-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .dynamic-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .dynamic-header-text h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
            color: #1f2937;
        }

        .dynamic-header-text p {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Dinamik renk sınıfları */

        /* İthalat - Mavi tema */
        .theme-import .content-section {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1);
        }

        .theme-import .section-header {
            border-bottom-color: #3b82f6;
        }

        .theme-import .dynamic-header {
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1);
        }

        /* İhracat - Yeşil tema */
        .theme-export .content-section {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.1);
        }

        .theme-export .section-header {
            border-bottom-color: #10b981;
        }

        .theme-export .dynamic-header {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.1);
        }

        /* Karayolu - Turuncu tema */
        .theme-karayolu .template-card:hover {
            border-color: #f97316;
            box-shadow: 0 2px 8px rgba(249, 115, 22, 0.15);
        }

        .theme-karayolu .add-template-card:hover {
            border-color: #f97316;
            background: #fff7ed;
        }

                /* Denizyolu - Mavi tema */
        .theme-denizyolu .template-card:hover,
        .theme-deniz .template-card:hover,
        .theme-deniz-yolu .template-card:hover {
            border-color: #0ea5e9;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.15);
        }

        .theme-denizyolu .add-template-card:hover,
        .theme-deniz .add-template-card:hover,
        .theme-deniz-yolu .add-template-card:hover {
            border-color: #0ea5e9;
            background: #f0f9ff;
        }

                /* Havayolu - Mor tema */
        .theme-havayolu .template-card:hover,
        .theme-hava .template-card:hover,
        .theme-hava-yolu .template-card:hover {
            border-color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.15);
        }

        .theme-havayolu .add-template-card:hover,
        .theme-hava .add-template-card:hover,
        .theme-hava-yolu .add-template-card:hover {
            border-color: #8b5cf6;
            background: #faf5ff;
        }

        /* Referans resimi kartları için dinamik renkler */
        .theme-import .card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .theme-export .card:hover {
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .section-body {
            padding: 20px;
        }

        /* Renkli transport mode tabs */
        .transport-mode-tabs {
            border: none;
            margin-bottom: 20px;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 6px;
        }

        .transport-mode-tabs .nav-link {
            border: none;
            border-radius: 6px;
            margin-right: 6px;
            padding: 10px 16px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
            background: transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        /* Karayolu - Turuncu */
        .transport-mode-tabs .nav-link[data-mode*="karayolu"].active {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
            transform: translateY(-1px);
        }

        .transport-mode-tabs .nav-link[data-mode*="karayolu"]:hover:not(.active) {
            background: #fed7aa;
            color: #ea580c;
        }

        /* Denizyolu - Mavi */
        .transport-mode-tabs .nav-link[data-mode*="deniz"].active,
        .transport-mode-tabs .nav-link[data-mode*="yolu"].active {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
            transform: translateY(-1px);
        }

        .transport-mode-tabs .nav-link[data-mode*="deniz"]:hover:not(.active),
        .transport-mode-tabs .nav-link[data-mode*="yolu"]:hover:not(.active) {
            background: #bae6fd;
            color: #0284c7;
        }

        /* Havayolu - Mor */
        .transport-mode-tabs .nav-link[data-mode*="hava"].active {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            transform: translateY(-1px);
        }

        .transport-mode-tabs .nav-link[data-mode*="hava"]:hover:not(.active) {
            background: #ddd6fe;
            color: #7c3aed;
        }

        /* Varsayılan aktif stil */
        .transport-mode-tabs .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            transform: translateY(-1px);
        }

        .transport-mode-tabs .nav-link:hover:not(.active) {
            background: rgba(255, 255, 255, 0.8);
            color: #475569;
        }

        .transport-mode-tabs .nav-link i {
            margin-right: 8px;
            font-size: 0.875rem;
        }

        /* Dinamik renkli template card */
        .template-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .template-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }
        /* Minimal badge tasarımı */
        .language-badge, .currency-badge, .trade-type-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 500;
            margin-right: 6px;
        }

        .language-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        .currency-badge {
            background: #fef3c7;
            color: #92400e;
        }

        .trade-type-badge {
            background: #d1fae5;
            color: #065f46;
        }

        /* Minimal template preview */
        .template-preview {
            max-height: 120px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px;
            background: #f9fafb;
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 12px;
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

        /* Renkli Trade Type Tabs */
        .trade-type-tabs {
            border: none;
            margin-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .trade-type-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            margin-right: 8px;
            padding: 14px 20px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.875rem;
            background: #f8f9fa;
            position: relative;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .trade-type-tabs .nav-link:before {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 0;
            height: 3px;
            transition: width 0.3s ease;
        }

        /* İthalat tab - Mavi */
        .trade-type-tabs .nav-link[data-trade="import"]:before {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .trade-type-tabs .nav-link[data-trade="import"].active {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .trade-type-tabs .nav-link[data-trade="import"]:hover:not(.active) {
            color: #2563eb;
            background: #eff6ff;
        }

        /* İhracat tab - Yeşil */
        .trade-type-tabs .nav-link[data-trade="export"]:before {
            background: linear-gradient(135deg, #10b981, #047857);
        }

        .trade-type-tabs .nav-link[data-trade="export"].active {
            color: #047857;
            background: #d1fae5;
            border-color: #10b981;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .trade-type-tabs .nav-link[data-trade="export"]:hover:not(.active) {
            color: #059669;
            background: #ecfdf5;
        }

        .trade-type-tabs .nav-link.active:before {
            width: 100%;
        }

        .trade-type-tabs .nav-link i {
            margin-right: 8px;
            font-size: 1rem;
        }
        /* Modern template preview */
        .template-preview {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            padding: 15px;
            background: linear-gradient(135deg, #f8f9ff 0%, #fff5f5 100%);
            font-size: 0.9em;
            line-height: 1.6;
        }

        /* Minimal add template card */
        .add-template-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 140px;
            padding: 20px;
            text-align: center;
            border-radius: 6px;
            border: 2px dashed #d1d5db;
            background: #fafbfc;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .add-template-card:hover {
            border-color: #4f46e5;
            background: #f8faff;
        }

        .add-template-card i {
            font-size: 1.5rem;
            color: #9ca3af;
            margin-bottom: 4px;
        }

        .add-template-card:hover i {
            color: #4f46e5;
        }

        .add-template-card h6 {
            margin: 0;
            font-weight: 500;
            font-size: 0.875rem;
            color: #374151;
        }

        .add-template-card small {
            margin-top: 2px;
            display: block;
            font-size: 0.75rem;
            color: #6b7280;
        }
        /* Modern group header */
        .group-header {
            display: flex;
            align-items: center;
            margin-bottom: 0;
            padding: 25px 30px;
            border-bottom: none;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .group-header:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            transition: all 0.4s ease;
        }

        .group-header:hover:before {
            left: 0;
        }

        .group-header:hover {
            transform: translateX(5px);
        }

        .group-header i {
            font-size: 2.5rem;
            margin-right: 20px;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .group-header h4 {
            margin: 0;
            color: white;
            flex-grow: 1;
            font-weight: 500;
            font-size: 1.4rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .group-toggle {
            font-size: 1.4rem;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .group-toggle:hover {
            color: white;
            transform: scale(1.1);
        }
        /* Modern group content */
        .group-content {
            padding: 30px;
            display: none !important;
            background: white;
            animation: slideDown 0.3s ease-out;
        }

        .group-content.show {
            display: block !important;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-truck me-3"></i> Taşıma Modları Yönetimi</h1>
                        <p class="mb-0 opacity-75">Teklif şablonlarınızı ve referans görsellerinizi yönetin</p>
                    </div>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <a href="edit-template.php" class="btn">
                                <i class="fas fa-plus me-2"></i> Yeni Şablon
                            </a>
                            <button type="button" class="btn" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i> Yenile
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
                <div class="content-section">
                    <div class="section-header">
                        <h4><i class="fas fa-file-alt me-2"></i> Teklif Şablonları</h4>
                    </div>
                    <div class="section-body">
                        <!-- Trade Type Tabs -->
                        <ul class="nav trade-type-tabs" id="tradeTypeTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="import-tab" data-bs-toggle="tab" data-bs-target="#import-content" type="button" role="tab" data-trade="import">
                                    <i class="fas fa-download"></i> İthalat
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export-content" type="button" role="tab" data-trade="export">
                                    <i class="fas fa-upload"></i> İhracat
                                </button>
                            </li>
                        </ul>

                        <!-- Dinamik Header -->
                        <div class="dynamic-header" id="dynamicHeader">
                            <div class="dynamic-header-icon" id="dynamicIcon">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="dynamic-header-text">
                                <h5 id="dynamicTitle">İthalat Şablonları</h5>
                                <p id="dynamicSubtitle">Şablon seçmek için yukarıdaki modlardan birini seçin</p>
                            </div>
                        </div>

                        <!-- Tab Content -->
                        <div class="tab-content" id="tradeTypeTabContent">
                            <!-- İthalat Tab -->
                            <div class="tab-pane fade show active" id="import-content" role="tabpanel">
                                <?php if (empty($grouped_templates['import'])): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-download fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Henüz ithalat şablonu oluşturulmamış</h5>
                                    <p class="text-muted mb-4">İlk ithalat şablonunuzu oluşturmak için aşağıdaki butona tıklayın.</p>
                                    <a href="edit-template.php?trade_type=import" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i> İthalat Şablonu Oluştur
                                    </a>
                                </div>
                                <?php else: ?>
                                                                        <!-- Transport Mode Tabs -->
                                    <ul class="nav transport-mode-tabs" id="importModeTabs" role="tablist">
                                        <?php $first = true; foreach ($grouped_templates['import'] as $mode_name => $group): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?= $first ? 'active' : '' ?>"
                                                    id="import-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#import-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>"
                                                    type="button" role="tab"
                                                    data-mode="<?= htmlspecialchars(strtolower($mode_name)) ?>"
                                                    data-mode-name="<?= htmlspecialchars($mode_name) ?>"
                                                    data-icon="<?= htmlspecialchars($group['icon']) ?>">
                                                <i class="<?= htmlspecialchars($group['icon']) ?>"></i> <?= htmlspecialchars($mode_name) ?>
                                            </button>
                                        </li>
                                        <?php $first = false; endforeach; ?>
                                    </ul>

                                    <!-- Transport Mode Content -->
                                    <div class="tab-content" id="importModeTabContent">
                                        <?php $first = true; foreach ($grouped_templates['import'] as $mode_name => $group): ?>
                                        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>"
                                             id="import-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>"
                                             role="tabpanel">

                                            <div class="row">
                                                <?php foreach ($group['templates'] as $template): ?>
                                                <div class="col-lg-6 col-xl-4 mb-3">
                                                    <div class="template-card">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="mb-0 fw-500"><?= htmlspecialchars($template['template_name']) ?></h6>
                                                            <span class="badge <?= $template['is_active'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.7rem;">
                                                                <?= $template['is_active'] ? 'Aktif' : 'Pasif' ?>
                                                            </span>
                                                        </div>

                                                        <div class="mb-2">
                                                            <span class="language-badge">
                                                                <?= $template['language'] == 'tr' ? 'TR' : 'EN' ?>
                                                            </span>
                                                            <span class="currency-badge">
                                                                <?= htmlspecialchars($template['currency']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="template-preview mb-3">
                                                            <?= $template['services_content'] ?: '<em class="text-muted">İçerik henüz eklenmemiş</em>' ?>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <a href="edit-template.php?id=<?= $template['id'] ?>"
                                                               class="btn btn-sm btn-outline-primary flex-fill">
                                                                <i class="fas fa-edit"></i> Düzenle
                                                            </a>
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
                                                    <a href="edit-template.php?mode=<?= rawurlencode($mode_name) ?>&trade_type=import"
                                                       class="add-template-card text-decoration-none">
                                                        <i class="fas fa-plus"></i>
                                                        <h6>Yeni Şablon</h6>
                                                        <small><?= htmlspecialchars($mode_name) ?> için</small>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php $first = false; endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- İhracat Tab -->
                            <div class="tab-pane fade" id="export-content" role="tabpanel">
                                <?php if (empty($grouped_templates['export'])): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-upload fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Henüz ihracat şablonu oluşturulmamış</h5>
                                    <p class="text-muted mb-4">İlk ihracat şablonunuzu oluşturmak için aşağıdaki butona tıklayın.</p>
                                    <a href="edit-template.php?trade_type=export" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i> İhracat Şablonu Oluştur
                                    </a>
                                </div>
                                <?php else: ?>
                                                                        <!-- Transport Mode Tabs -->
                                    <ul class="nav transport-mode-tabs" id="exportModeTabs" role="tablist">
                                        <?php $first = true; foreach ($grouped_templates['export'] as $mode_name => $group): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?= $first ? 'active' : '' ?>"
                                                    id="export-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>-tab"
                                                    data-bs-toggle="tab"
                                                    data-bs-target="#export-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>"
                                                    type="button" role="tab"
                                                    data-mode="<?= htmlspecialchars(strtolower($mode_name)) ?>"
                                                    data-mode-name="<?= htmlspecialchars($mode_name) ?>"
                                                    data-icon="<?= htmlspecialchars($group['icon']) ?>">
                                                <i class="<?= htmlspecialchars($group['icon']) ?>"></i> <?= htmlspecialchars($mode_name) ?>
                                            </button>
                                        </li>
                                        <?php $first = false; endforeach; ?>
                                    </ul>

                                    <!-- Transport Mode Content -->
                                    <div class="tab-content" id="exportModeTabContent">
                                        <?php $first = true; foreach ($grouped_templates['export'] as $mode_name => $group): ?>
                                        <div class="tab-pane fade <?= $first ? 'show active' : '' ?>"
                                             id="export-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $mode_name))) ?>"
                                             role="tabpanel">

                                            <div class="row">
                                                <?php foreach ($group['templates'] as $template): ?>
                                                <div class="col-lg-6 col-xl-4 mb-3">
                                                    <div class="template-card">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="mb-0 fw-500"><?= htmlspecialchars($template['template_name']) ?></h6>
                                                            <span class="badge <?= $template['is_active'] ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.7rem;">
                                                                <?= $template['is_active'] ? 'Aktif' : 'Pasif' ?>
                                                            </span>
                                                        </div>

                                                        <div class="mb-2">
                                                            <span class="language-badge">
                                                                <?= $template['language'] == 'tr' ? 'TR' : 'EN' ?>
                                                            </span>
                                                            <span class="currency-badge">
                                                                <?= htmlspecialchars($template['currency']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="template-preview mb-3">
                                                            <?= $template['services_content'] ?: '<em class="text-muted">İçerik henüz eklenmemiş</em>' ?>
                                                        </div>

                                                        <div class="d-flex gap-2">
                                                            <a href="edit-template.php?id=<?= $template['id'] ?>"
                                                               class="btn btn-sm btn-outline-primary flex-fill">
                                                                <i class="fas fa-edit"></i> Düzenle
                                                            </a>
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
                                                    <a href="edit-template.php?mode=<?= rawurlencode($mode_name) ?>&trade_type=export"
                                                       class="add-template-card text-decoration-none">
                                                        <i class="fas fa-plus"></i>
                                                        <h6>Yeni Şablon</h6>
                                                        <small><?= htmlspecialchars($mode_name) ?> için</small>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php $first = false; endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Referans Resimleri Bölümü -->
                <div class="content-section">
                    <div class="section-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-images me-2"></i> Referans Resimleri</h4>
                        <button type="button" class="btn btn-primary btn-sm" onclick="showImageUploadModal()">
                            <i class="fas fa-upload me-2"></i> Resim Yükle
                        </button>
                    </div>
                    <div class="section-body">
                        <p class="text-muted mb-4" style="font-size: 0.875rem;">
                            <i class="fas fa-info-circle me-1"></i>
                            Her taşıma modu için referans görselleri yükleyebilir ve yönetebilirsiniz.
                        </p>


                        <div class="row">
                            <?php foreach ($transportModes as $mode): ?>
                                <?php if (strtolower($mode['name']) !== 'konteyner'): ?>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card h-100 border" style="border-radius: 8px;">
                                    <div class="card-body text-center">
                                        <div class="mb-3">
                                            <?php
                                            $icons = [
                                                'karayolu' => 'fa-truck',
                                                'denizyolu' => 'fa-ship',
                                                'havayolu' => 'fa-plane',
                                                'konteyner' => 'fa-shipping-fast'
                                            ];
                                            $icon = $icons[$mode['slug']] ?? 'fa-truck';
                                            ?>
                                            <i class="fas <?= $icon ?> fa-2x text-primary"></i>
                                        </div>
                                        <h6 class="card-title mb-1"><?= htmlspecialchars($mode['name']) ?></h6>
                                        <small class="text-muted d-block mb-3"><?= $mode['image_count'] ?> görsel</small>

                                        <div class="d-grid gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="viewModeImages(<?= $mode['id'] ?>, '<?= htmlspecialchars($mode['name']) ?>')">
                                                <i class="fas fa-eye me-1"></i> Görüntüle
                                            </button>
                                            <button class="btn btn-primary btn-sm" onclick="uploadModeImage(<?= $mode['id'] ?>, '<?= htmlspecialchars($mode['slug']) ?>')">
                                                <i class="fas fa-plus me-1"></i> Yeni Ekle
                                            </button>
                                        </div>

                                        <div class="mt-3">
                                            <?php if ($mode['image_count'] > 0): ?>
                                                <span class="badge bg-success" style="font-size: 0.7rem;">
                                                    <i class="fas fa-check me-1"></i> Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning" style="font-size: 0.7rem;">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> Görsel Yok
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="servicesContent" data-field="services_content">
                            </div>
                            <textarea name="services_content" style="display: none;"></textarea>
                        </div>

                        <!-- Taşınma Süreci Bölümü -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-truck text-success"></i> Taşınma Süreci İçeriği
                                <small class="text-muted">(Teklif sayfasında "Taşınma Süreci" bölümünde görünecek)</small>
                            </label>
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="transportProcessContent" data-field="transport_process_content">
                            </div>
                            <textarea name="transport_process_content" style="display: none;"></textarea>
                        </div>

                        <!-- Şartlar Bölümü -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-file-contract text-warning"></i> Şartlar İçeriği
                                <small class="text-muted">(view-quote.php sayfasında "Şartlar" bölümünde görünecek)</small>
                            </label>
                            <div class="border rounded p-3" style="min-height: 100px; cursor: pointer;" id="termsContent" data-field="terms_content">
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
        let templateModalInstance = null;
        let templateModalWasOpen = false;
        let templateContentCache = {}; // İçerikleri saklamak için cache

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

            // Modal event listener'ları kaldır
            const templateModal = document.getElementById('templateModal');
            if (templateModal) {
                // Önce tüm event listener'ları kaldır
                templateModal.removeEventListener('show.bs.modal', () => {});
                templateModal.removeEventListener('shown.bs.modal', () => {});
            }

            // Rich editor click events - event delegation kullan
            document.addEventListener('click', function(e) {
                if (e.target.matches('#servicesContent, #transportProcessContent, #termsContent')) {
                    e.preventDefault();
                    const field = e.target.getAttribute('data-field');
                    if (field) {
                        openRichEditor(e.target, field);
                    }
                }
            });

            // Dinamik başlık güncellemesi
            initializeDynamicHeader();

            // İlk tema ayarını yap
            setTimeout(updateDynamicHeader, 100);
        });

        // Dinamik başlık sistem
        function initializeDynamicHeader() {
            // Trade type tab değişikliklerini dinle
            document.querySelectorAll('#tradeTypeTabs .nav-link').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    setTimeout(updateDynamicHeader, 50);
                });
            });

            // Transport mode tab değişikliklerini dinle - hem import hem export için
            document.addEventListener('shown.bs.tab', function(e) {
                if (e.target.classList.contains('nav-link') && e.target.closest('.transport-mode-tabs')) {
                    setTimeout(updateDynamicHeader, 50);
                }
            });

            // İlk yükleme
            updateDynamicHeader();
        }

                        function updateDynamicHeader() {
            const activeTradeTab = document.querySelector('#tradeTypeTabs .nav-link.active');
            const dynamicIcon = document.getElementById('dynamicIcon');
            const dynamicTitle = document.getElementById('dynamicTitle');
            const dynamicSubtitle = document.getElementById('dynamicSubtitle');

            if (!activeTradeTab || !dynamicIcon || !dynamicTitle || !dynamicSubtitle) {
                return;
            }

            const tradeType = activeTradeTab.getAttribute('data-trade');
            const tradeText = tradeType === 'import' ? 'İthalat' : 'İhracat';
            const tradeIcon = tradeType === 'import' ? 'fa-download' : 'fa-upload';

            // Önceki tema sınıflarını temizle
            document.body.className = document.body.className.replace(/theme-\w+/g, '').trim();

            // Trade type tema sınıfını ekle
            document.body.classList.add(`theme-${tradeType}`);

            // Aktif mod tabını bul - aktif trade type'a göre
            let activeModeTab = null;

            // İthalat seçiliyse import mod tablarından aktif olanı bul
            if (tradeType === 'import') {
                activeModeTab = document.querySelector('#importModeTabs .nav-link.active');
            }
            // İhracat seçiliyse export mod tablarından aktif olanı bul
            else if (tradeType === 'export') {
                activeModeTab = document.querySelector('#exportModeTabs .nav-link.active');
            }

            // Mod renkleri
            const modeColors = {
                'karayolu': { bg: 'linear-gradient(135deg, #f97316, #ea580c)', icon: 'fas fa-truck' },
                'denizyolu': { bg: 'linear-gradient(135deg, #0ea5e9, #0284c7)', icon: 'fas fa-ship' },
                'deniz': { bg: 'linear-gradient(135deg, #0ea5e9, #0284c7)', icon: 'fas fa-ship' },
                'deniz-yolu': { bg: 'linear-gradient(135deg, #0ea5e9, #0284c7)', icon: 'fas fa-ship' },
                'havayolu': { bg: 'linear-gradient(135deg, #8b5cf6, #7c3aed)', icon: 'fas fa-plane' },
                'hava': { bg: 'linear-gradient(135deg, #8b5cf6, #7c3aed)', icon: 'fas fa-plane' },
                'hava-yolu': { bg: 'linear-gradient(135deg, #8b5cf6, #7c3aed)', icon: 'fas fa-plane' }
            };

            // Trade type renkleri
            const tradeColors = {
                'import': 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                'export': 'linear-gradient(135deg, #10b981, #047857)'
            };

            if (activeModeTab) {
                const modeName = activeModeTab.getAttribute('data-mode-name');
                const modeKey = activeModeTab.getAttribute('data-mode');
                const modeIcon = activeModeTab.getAttribute('data-icon');

                // Mod tema sınıfını ekle (boşlukları temizle)
                const cleanModeKey = modeKey ? modeKey.replace(/\s+/g, '-').toLowerCase() : '';
                if (cleanModeKey) {
                    document.body.classList.add(`theme-${cleanModeKey}`);
                }

                const color = modeColors[cleanModeKey] || modeColors[modeKey] || { bg: tradeColors[tradeType], icon: modeIcon };

                dynamicIcon.style.background = color.bg;
                dynamicIcon.innerHTML = `<i class="${color.icon}"></i>`;
                dynamicTitle.textContent = `${tradeText} - ${modeName}`;

                // Template sayısını hesapla
                const activeContent = document.querySelector(`#${tradeType}-${modeKey.replace(' ', '-')}`);
                const templateCount = activeContent ? activeContent.querySelectorAll('.template-card:not(.add-template-card)').length : 0;

                dynamicSubtitle.textContent = `${templateCount} şablon bulundu`;
            } else {
                dynamicIcon.style.background = tradeColors[tradeType];
                dynamicIcon.innerHTML = `<i class="fas ${tradeIcon}"></i>`;
                dynamicTitle.textContent = `${tradeText} Şablonları`;
                dynamicSubtitle.textContent = 'Şablon seçmek için yukarıdaki modlardan birini seçin';
            }
        }

        // Rich Text Editor Functions
        function openRichEditor(element, field) {
            currentRichField = field;
            currentRichElement = element;

            const modal = document.getElementById('richEditorModal');
            const content = document.getElementById('richEditorContent');
            const title = document.getElementById('richEditorTitle');

            // Template modal aktifse geçici olarak kapat (Bootstrap focus trap'i engellemek için)
            const templateModalEl = document.getElementById('templateModal');
            if (templateModalEl) {
                const existing = bootstrap.Modal.getInstance(templateModalEl);
                if (existing) {
                    templateModalInstance = existing;
                } else {
                    templateModalInstance = new bootstrap.Modal(templateModalEl);
                }
                // Eğer görünürse kapat
                if (templateModalEl.classList.contains('show')) {
                    templateModalWasOpen = true;
                    templateModalInstance.hide();
                } else {
                    templateModalWasOpen = false;
                }
            }

            // Get current content - placeholder text'i kontrol et
            let currentContent = element.innerHTML;

            // Eğer sadece placeholder varsa boş döndür
            if (currentContent.includes('<span style="color: #999;">İçerik eklemek için tıklayın...</span>')) {
                currentContent = '';
            } else {
                currentContent = currentContent.replace(/İçerik eklemek için tıklayın\.\.\./, '').trim();
            }

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

            // Eğer template modalı önceden açıksa tekrar göster
            if (templateModalWasOpen && templateModalInstance) {
                templateModalInstance.show();
                templateModalWasOpen = false;
            }
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
            document.getElementById('servicesContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
            document.getElementById('transportProcessContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
            document.getElementById('termsContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';

            // Hidden textarea'ları temizle
            document.querySelector('textarea[name="services_content"]').value = '';
            document.querySelector('textarea[name="transport_process_content"]').value = '';
            document.querySelector('textarea[name="terms_content"]').value = '';

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function editTemplate(templateId) {
            // AJAX ile şablon verilerini al
            fetch(`?action=get_template&id=${templateId}`)
                .then(response => response.json())
                .then(template => {
                    console.log('Template data received:', template);

                    document.getElementById('modalTitle').textContent = 'Şablon Düzenle';
                    document.getElementById('templateId').value = template.id;
                    document.getElementById('templateName').value = template.template_name;
                    document.getElementById('transportModeId').value = template.transport_mode_id;
                    document.getElementById('templateLanguage').value = template.language;
                    document.getElementById('templateCurrency').value = template.currency;
                    document.getElementById('templateTradeType').value = template.trade_type || 'import';
                    document.getElementById('templateActive').checked = template.is_active == 1;

                    // Debug için içerikleri logla
                    console.log('Services content:', template.services_content);
                    console.log('Transport process content:', template.transport_process_content);
                    console.log('Terms content:', template.terms_content);

                    // Editör alanlarına içerikleri yükle
                    const servicesEl = document.getElementById('servicesContent');
                    const transportEl = document.getElementById('transportProcessContent');
                    const termsEl = document.getElementById('termsContent');

                    console.log('Elements found:', {
                        services: !!servicesEl,
                        transport: !!transportEl,
                        terms: !!termsEl
                    });

                                        if (servicesEl) {
                        servicesEl.innerHTML = template.services_content || '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        console.log('Services content set to:', servicesEl.innerHTML);
                    }

                    if (transportEl) {
                        transportEl.innerHTML = template.transport_process_content || '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        console.log('Transport content set to:', transportEl.innerHTML);
                    }

                    if (termsEl) {
                        termsEl.innerHTML = template.terms_content || '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        console.log('Terms content set to:', termsEl.innerHTML);
                    }

                    // Hidden textarea'ları güncelle
                    document.querySelector('textarea[name="services_content"]').value = template.services_content || '';
                    document.querySelector('textarea[name="transport_process_content"]').value = template.transport_process_content || '';
                    document.querySelector('textarea[name="terms_content"]').value = template.terms_content || '';

                    // İçerikleri cache'e al
                    window.templateContentCache = {
                        services: template.services_content || '',
                        transport: template.transport_process_content || '',
                        terms: template.terms_content || ''
                    };

                    // Modal'ı aç
                    const modalEl = document.getElementById('templateModal');
                    const modal = new bootstrap.Modal(modalEl);

                    // Modal tamamen açıldığında içerikleri set et
                                        modalEl.addEventListener('shown.bs.modal', function onModalShown() {
                        console.log('Modal shown event - restoring content from cache');
                        console.log('Cache content:', window.templateContentCache);

                        // Cache'ten içerikleri geri yükle
                        if (window.templateContentCache.services) {
                            document.getElementById('servicesContent').innerHTML = window.templateContentCache.services;
                        } else {
                            document.getElementById('servicesContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        }

                        if (window.templateContentCache.transport) {
                            document.getElementById('transportProcessContent').innerHTML = window.templateContentCache.transport;
                        } else {
                            document.getElementById('transportProcessContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        }

                        if (window.templateContentCache.terms) {
                            document.getElementById('termsContent').innerHTML = window.templateContentCache.terms;
                        } else {
                            document.getElementById('termsContent').innerHTML = '<span style="color: #999;">İçerik eklemek için tıklayın...</span>';
                        }

                        console.log('Content restored successfully');

                        // Event listener'ı kaldır
                        modalEl.removeEventListener('shown.bs.modal', onModalShown);
                    });

                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading template:', error);
                    alert('Şablon yüklenirken hata oluştu!');
                });
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