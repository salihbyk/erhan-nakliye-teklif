<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
checkAdminSession();

try {
    $database = new Database();
    $db = $database->getConnection();

    // E-mail şablonları tablosunu oluştur (yoksa)
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transport_mode_id INT NOT NULL,
            language ENUM('tr', 'en') DEFAULT 'tr',
            currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
            template_name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            quote_content TEXT NOT NULL,
            email_content TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email_template (transport_mode_id, language, currency)
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
            $template_name = $_POST['template_name'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $quote_content = $_POST['quote_content'] ?? '';
            $email_content = $_POST['email_content'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                // Güncelle
                $stmt = $db->prepare("
                    UPDATE email_templates
                    SET template_name = ?, subject = ?, quote_content = ?, email_content = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$template_name, $subject, $quote_content, $email_content, $is_active, $id]);
                setSuccessMessage('E-mail şablonu başarıyla güncellendi.');
            } else {
                // Yeni ekle
                $stmt = $db->prepare("
                    INSERT INTO email_templates (transport_mode_id, language, currency, template_name, subject, quote_content, email_content, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    template_name = VALUES(template_name),
                    subject = VALUES(subject),
                    quote_content = VALUES(quote_content),
                    email_content = VALUES(email_content),
                    is_active = VALUES(is_active),
                    updated_at = NOW()
                ");
                $stmt->execute([$transport_mode_id, $language, $currency, $template_name, $subject, $quote_content, $email_content, $is_active]);
                setSuccessMessage('E-mail şablonu başarıyla kaydedildi.');
            }

            header('Location: email-templates.php');
            exit;
        }

        if ($action === 'delete_template') {
            $id = $_POST['id'] ?? 0;
            $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
            if ($stmt->execute([$id])) {
                setSuccessMessage('E-mail şablonu başarıyla silindi.');
            }
            header('Location: email-templates.php');
            exit;
        }
    }

    // Taşıma modlarını al
    $stmt = $db->query("SELECT * FROM transport_modes ORDER BY name ASC");
    $transport_modes = $stmt->fetchAll();

    // E-mail şablonlarını al
    $stmt = $db->query("
        SELECT et.*, tm.name as transport_mode_name, tm.icon
        FROM email_templates et
        JOIN transport_modes tm ON et.transport_mode_id = tm.id
        ORDER BY tm.name ASC, et.language ASC, et.currency ASC
    ");
    $templates = $stmt->fetchAll();

    // Şablonları gruplara ayır
    $grouped_templates = [];
    foreach ($templates as $template) {
        $mode_name = $template['transport_mode_name'];
        if (!isset($grouped_templates[$mode_name])) {
            $grouped_templates[$mode_name] = [
                'icon' => $template['icon'],
                'templates' => []
            ];
        }
        $grouped_templates[$mode_name]['templates'][] = $template;
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
    <title>E-mail Şablonları - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <!-- Quill Editor -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
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
            display: none;
        }
        .group-content.show {
            display: block;
        }
        #quoteEditor, #emailEditor {
            height: 200px;
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
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Ana İçerik -->
        <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-envelope-open-text"></i> E-posta Şablonları</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-primary" onclick="showAddTemplateModal()">
                                <i class="fas fa-plus"></i> Yeni E-mail Şablonu
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

                <!-- Şablon Grupları -->
                <?php if (empty($grouped_templates)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Henüz e-mail şablonu oluşturulmamış</h4>
                    <p class="text-muted">İlk e-mail şablonunuzu oluşturmak için "Yeni E-mail Şablonu" butonuna tıklayın.</p>
                    <button type="button" class="btn btn-primary" onclick="showAddTemplateModal()">
                        <i class="fas fa-plus"></i> İlk E-mail Şablonunu Oluştur
                    </button>
                </div>
                <?php else: ?>
                    <?php foreach ($grouped_templates as $mode_name => $group): ?>
                    <div class="transport-group">
                        <div class="group-header" onclick="toggleGroup(this)">
                            <i class="<?= htmlspecialchars($group['icon']) ?>"></i>
                            <h4><?= htmlspecialchars($mode_name) ?></h4>
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
                                        </div>

                                        <div class="mb-2">
                                            <strong>Konu:</strong> <?= htmlspecialchars($template['subject']) ?>
                                        </div>

                                        <div class="template-preview mb-3">
                                            <?= $template['quote_content'] ?: '<em class="text-muted">İçerik henüz eklenmemiş</em>' ?>
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
                                    <div class="add-template-card" onclick="showAddTemplateModal('<?= htmlspecialchars($mode_name) ?>')">
                                        <i class="fas fa-plus fa-2x text-primary mb-2"></i>
                                        <h6 class="text-primary">Yeni E-mail Şablonu Ekle</h6>
                                        <small class="text-muted"><?= htmlspecialchars($mode_name) ?> için</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- E-mail Şablonu Düzenleme Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Yeni E-mail Şablonu</h5>
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
                                <label class="form-label">Durum</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="templateActive" checked>
                                    <label class="form-check-label">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-mail Konusu</label>
                            <input type="text" class="form-control" name="subject" id="templateSubject" required>
                            <div class="form-text">Örnek: Nakliye Teklifi - {quote_number}</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Teklif İçeriği (E-mail içinde gösterilecek)</label>
                            <div id="quoteEditor"></div>
                            <textarea name="quote_content" id="quoteContent" style="display: none;"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">E-mail İçeriği (Teklif etrafındaki metin)</label>
                            <div id="emailEditor"></div>
                            <textarea name="email_content" id="emailContent" style="display: none;"></textarea>

                            <div class="variable-helper">
                                <strong>Kullanılabilir Değişkenler:</strong> <small class="text-muted">(Tıklayarak imlecin olduğu yere ekleyin)</small><br>

                                <div class="variable-category mt-2">
                                    <strong>👤 Müşteri Bilgileri:</strong><br>
                                    <span class="variable-tag" onclick="insertVariable('{customer_name}')" title="Müşteri adı">
                                        {customer_name} <small>- Müşteri adı</small>
                                    </span>
                                </div>

                                <div class="variable-category mt-2">
                                    <strong>📋 Teklif Bilgileri:</strong><br>
                                    <span class="variable-tag" onclick="insertVariable('{quote_number}')" title="Teklif numarası">
                                        {quote_number} <small>- Teklif numarası</small>
                                    </span>
                                    <span class="variable-tag" onclick="insertVariable('{quote_content}')" title="Teklif içeriği">
                                        {quote_content} <small>- Teklif içeriği</small>
                                    </span>
                                </div>
                            </div>
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
                    <h5 class="modal-title">E-mail Şablonunu Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Bu e-mail şablonunu silmek istediğinizden emin misiniz?</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
    <script>
        let quoteQuill, emailQuill;

        // Quill editörlerini başlat
        document.addEventListener('DOMContentLoaded', function() {
            quoteQuill = new Quill('#quoteEditor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });

            emailQuill = new Quill('#emailEditor', {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });

            // Form submit edildiğinde editör içeriklerini textarea'lara aktar
            document.getElementById('templateForm').addEventListener('submit', function() {
                document.getElementById('quoteContent').value = quoteQuill.root.innerHTML;
                document.getElementById('emailContent').value = emailQuill.root.innerHTML;
            });

            // Tüm grupları başlangıçta kapalı göster
            document.querySelectorAll('.group-content').forEach(content => {
                content.classList.remove('show');
            });

            // Toggle ikonlarını başlangıç pozisyonuna ayarla
            document.querySelectorAll('.group-toggle').forEach(toggle => {
                toggle.style.transform = 'rotate(-90deg)';
            });
        });

        function showAddTemplateModal(modeName = '') {
            document.getElementById('modalTitle').textContent = 'Yeni E-mail Şablonu';
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

            quoteQuill.setContents([]);
            emailQuill.setContents([]);

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function editTemplate(template) {
            document.getElementById('modalTitle').textContent = 'E-mail Şablonu Düzenle';
            document.getElementById('templateId').value = template.id;
            document.getElementById('templateName').value = template.template_name;
            document.getElementById('transportModeId').value = template.transport_mode_id;
            document.getElementById('templateLanguage').value = template.language;
            document.getElementById('templateCurrency').value = template.currency;
            document.getElementById('templateSubject').value = template.subject;
            document.getElementById('templateActive').checked = template.is_active == 1;

            // Editörlere içerikleri yükle
            quoteQuill.root.innerHTML = template.quote_content || '';
            emailQuill.root.innerHTML = template.email_content || '';

            const modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        }

        function deleteTemplate(id, name) {
            document.getElementById('deleteTemplateId').value = id;
            document.getElementById('deleteTemplateName').textContent = name;

            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function insertVariable(variable) {
            // Son aktif editöre ekle (varsayılan olarak email editör)
            const activeEditor = emailQuill;
            const range = activeEditor.getSelection();
            if (range) {
                activeEditor.insertText(range.index, variable);
                activeEditor.setSelection(range.index + variable.length);
            } else {
                const length = activeEditor.getLength();
                activeEditor.insertText(length - 1, variable);
                activeEditor.setSelection(length - 1 + variable.length);
            }
            activeEditor.focus();
        }

        function toggleGroup(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.group-toggle');

            if (content.classList.contains('show')) {
                content.classList.remove('show');
                toggle.style.transform = 'rotate(-90deg)';
            } else {
                content.classList.add('show');
                toggle.style.transform = 'rotate(0deg)';
            }
        }

        // Form validation
        document.getElementById('templateForm').addEventListener('submit', function(e) {
            const name = document.getElementById('templateName').value.trim();
            const subject = document.getElementById('templateSubject').value.trim();

            if (!name) {
                e.preventDefault();
                alert('Şablon adı zorunludur.');
                return false;
            }

            if (!subject) {
                e.preventDefault();
                alert('E-mail konusu zorunludur.');
                return false;
            }
        });
    </script>
</body>
</html>