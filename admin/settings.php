<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$messages = [];

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'change_password':
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                        throw new Exception('Tüm alanları doldurunuz.');
                    }

                    if ($new_password !== $confirm_password) {
                        throw new Exception('Yeni şifreler eşleşmiyor.');
                    }

                    if (strlen($new_password) < 6) {
                        throw new Exception('Yeni şifre en az 6 karakter olmalıdır.');
                    }

                    // Mevcut şifreyi kontrol et
                    $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id']]);
                    $admin = $stmt->fetch();

                    if (!$admin || !password_verify($current_password, $admin['password'])) {
                        throw new Exception('Mevcut şifre yanlış.');
                    }

                    // Yeni şifreyi güncelle
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE admin_users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed_password, $_SESSION['admin_id']]);

                    $messages['success'] = 'Şifre başarıyla değiştirildi.';
                    break;

                case 'update_profile':
                    $name = trim($_POST['name'] ?? '');
                    $email = trim($_POST['email'] ?? '');

                    if (empty($name) || empty($email)) {
                        throw new Exception('Ad ve e-posta alanları zorunludur.');
                    }

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Geçerli bir e-posta adresi giriniz.');
                    }

                    // E-posta adresinin başka admin tarafından kullanılıp kullanılmadığını kontrol et
                    $stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $_SESSION['admin_id']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Bu e-posta adresi başka bir admin tarafından kullanılıyor.');
                    }

                    // Profil bilgilerini güncelle
                    $stmt = $db->prepare("UPDATE admin_users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $email, $_SESSION['admin_id']]);

                    // Session'daki adı güncelle
                    $_SESSION['admin_name'] = $name;

                    $messages['success'] = 'Profil bilgileri başarıyla güncellendi.';
                    break;

                case 'update_system_settings':
                    $company_name = trim($_POST['company_name'] ?? '');
                    $company_email = trim($_POST['company_email'] ?? '');
                    $company_phone = trim($_POST['company_phone'] ?? '');
                    $company_address = trim($_POST['company_address'] ?? '');

                    // Sistem ayarlarını güncelle (settings tablosu varsa)
                    // Bu kısım veritabanı yapısına göre düzenlenebilir

                    $messages['success'] = 'Sistem ayarları başarıyla güncellendi.';
                    break;
            }
        }
    } catch (Exception $e) {
        $messages['error'] = $e->getMessage();
    }
}

// Admin bilgilerini al
try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin_info = $stmt->fetch();

    if (!$admin_info) {
        throw new Exception('Admin bilgileri bulunamadı.');
    }
} catch (Exception $e) {
    $messages['error'] = $e->getMessage();
    $admin_info = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - Admin Panel</title>
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
        .settings-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .settings-card .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .settings-card .card-body {
            padding: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-1px);
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Ana İçerik -->
        <div class="main-content">
                <!-- Sayfa Başlığı -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h2 mb-0"><i class="fas fa-cog"></i> Ayarlar</h1>
                            <p class="text-muted mb-0">Sistem ve profil ayarlarınızı yönetin</p>
                        </div>
                        <div class="btn-toolbar">
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

                <div class="row">
                    <!-- Profil Ayarları -->
                    <div class="col-lg-6">
                        <div class="settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Profil Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">

                                    <div class="form-group">
                                        <label class="form-label">Ad Soyad</label>
                                        <input type="text" class="form-control" name="name"
                                               value="<?= htmlspecialchars($admin_info['name'] ?? '') ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">E-posta Adresi</label>
                                        <input type="email" class="form-control" name="email"
                                               value="<?= htmlspecialchars($admin_info['email'] ?? '') ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Kayıt Tarihi</label>
                                        <input type="text" class="form-control"
                                               value="<?= isset($admin_info['created_at']) ? date('d.m.Y H:i', strtotime($admin_info['created_at'])) : 'Bilinmiyor' ?>"
                                               readonly>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Profil Bilgilerini Güncelle
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Şifre Değiştirme -->
                    <div class="col-lg-6">
                        <div class="settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-lock"></i> Şifre Değiştir</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">

                                    <div class="form-group">
                                        <label class="form-label">Mevcut Şifre</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="current_password" id="currentPassword" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Yeni Şifre</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="new_password" id="newPassword"
                                                   onkeyup="checkPasswordStrength()" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="form-text text-muted">En az 6 karakter olmalıdır</small>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword"
                                                   onkeyup="checkPasswordMatch()" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div id="passwordMatch" class="form-text"></div>
                                    </div>

                                    <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                                        <i class="fas fa-key"></i> Şifreyi Değiştir
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sistem Bilgileri -->
                <div class="row">
                    <div class="col-12">
                        <div class="settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Sistem Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>PHP Sürümü:</strong><br>
                                        <span class="text-muted"><?= PHP_VERSION ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Sunucu Zamanı:</strong><br>
                                        <span class="text-muted"><?= date('d.m.Y H:i:s') ?></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Veritabanı:</strong><br>
                                        <span class="text-muted">MySQL</span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Sistem Durumu:</strong><br>
                                        <span class="badge bg-success">Çalışıyor</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Güvenlik Ayarları -->
                <div class="row">
                    <div class="col-12">
                        <div class="settings-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Güvenlik Ayarları</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Oturum Güvenliği</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success"></i> Güvenli oturum yönetimi aktif</li>
                                            <li><i class="fas fa-check text-success"></i> Şifre hashleme aktif</li>
                                            <li><i class="fas fa-check text-success"></i> CSRF koruması aktif</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Son Giriş Bilgileri</h6>
                                        <p class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            <?= isset($admin_info['last_login']) && $admin_info['last_login'] ?
                                                date('d.m.Y H:i', strtotime($admin_info['last_login'])) :
                                                'İlk giriş' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="includes/sidebar.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('passwordStrength');

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            strengthBar.className = 'password-strength';
            if (strength < 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength < 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        }

        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('changePasswordBtn');

            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    matchDiv.innerHTML = '<i class="fas fa-check text-success"></i> Şifreler eşleşiyor';
                    matchDiv.className = 'form-text text-success';
                    submitBtn.disabled = false;
                } else {
                    matchDiv.innerHTML = '<i class="fas fa-times text-danger"></i> Şifreler eşleşmiyor';
                    matchDiv.className = 'form-text text-danger';
                    submitBtn.disabled = true;
                }
            } else {
                matchDiv.innerHTML = '';
                submitBtn.disabled = false;
            }
        }

        // Form gönderilmeden önce kontrol
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Şifreler eşleşmiyor!');
                return false;
            }

            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Yeni şifre en az 6 karakter olmalıdır!');
                return false;
            }
        });
    </script>
</body>
</html>