<?php
session_start();
require_once '../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Müşteri ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: customers.php');
    exit();
}

$customer_id = (int)$_GET['id'];

// Form gönderildi mi?
if ($_POST) {
    try {
        if (isset($_POST['add_payment'])) {
            $quote_id = $_POST['quote_id'];
            $payment_type = $_POST['payment_type'];
            $amount = $_POST['amount'] ?? 0;
            $currency = $_POST['currency'] ?? 'TL';
            $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $payment_method = $_POST['payment_method'] ?? '';
            $description = $_POST['description'] ?? '';

            // Ödeme işlemini ekle
            $stmt = $db->prepare("
                INSERT INTO payments (quote_id, payment_type, amount, currency, payment_date, payment_method, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quote_id, $payment_type, $amount, $currency, $payment_date, $payment_method, $description]);

            $success_message = "Ödeme işlemi başarıyla eklendi!";
        }

        if (isset($_POST['delete_payment'])) {
            $payment_id = $_POST['payment_id'];
            $stmt = $db->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $success_message = "Ödeme işlemi silindi!";
        }

        if (isset($_POST['update_quote'])) {
            $quote_id = $_POST['quote_id'];
            $delivery_status = $_POST['delivery_status'];
            $pickup_date = !empty($_POST['pickup_date']) ? $_POST['pickup_date'] : null;
            $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : null;
            $tracking_notes = $_POST['tracking_notes'] ?? '';

            $stmt = $db->prepare("
                UPDATE quotes SET
                    delivery_status = ?,
                    pickup_date = ?,
                    delivery_date = ?,
                    tracking_notes = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $delivery_status,
                $pickup_date,
                $delivery_date,
                $tracking_notes,
                $quote_id
            ]);

            $success_message = "Teklif durumu başarıyla güncellendi!";
        }
    } catch (Exception $e) {
        $error_message = "Hata: " . $e->getMessage();
    }
}

// Müşteri bilgilerini getir
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Müşterinin tekliflerini getir (ödeme ve teslimat bilgileri ile)
$quotes_stmt = $db->prepare("
    SELECT
        q.*,
        tm.name as transport_mode_name,
        tm.icon as transport_mode_icon
    FROM quotes q
    LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
    WHERE q.customer_id = ?
    ORDER BY q.created_at DESC
");
$quotes_stmt->execute([$customer_id]);
$quotes = $quotes_stmt->fetchAll();

// Her teklif için ödeme bilgilerini getir
foreach ($quotes as &$quote) {
    $payment_stmt = $db->prepare("
        SELECT * FROM payments
        WHERE quote_id = ?
        ORDER BY payment_date DESC
    ");
    $payment_stmt->execute([$quote['id']]);
    $quote['payments'] = $payment_stmt->fetchAll();

    // Toplam ödenen tutarı hesapla
    $total_paid = 0;
    foreach ($quote['payments'] as $payment) {
        if ($payment['status'] === 'completed') {
            // Para birimi dönüşümü gerekirse burada yapılabilir
            $total_paid += $payment['amount'];
        }
    }
    $quote['total_paid'] = $total_paid;
}

function getPaymentStatusBadge($quote) {
    $total_paid = $quote['total_paid'] ?? 0;
    $final_price = $quote['final_price'] ?? 0;

    if ($total_paid == 0) {
        return '<span class="badge bg-danger"><i class="fas fa-times"></i> Ödeme Yapılmadı</span>';
    } elseif ($total_paid >= $final_price) {
        return '<span class="badge bg-success"><i class="fas fa-check"></i> Ödendi</span>';
    } else {
        return '<span class="badge bg-info"><i class="fas fa-percentage"></i> Kısmi Ödeme</span>';
    }
}

function getPaymentTypeName($type) {
    switch($type) {
        case 'kaparo':
            return 'Kaparo';
        case 'ara_odeme':
            return 'Ara Ödeme';
        case 'kalan_bakiye':
            return 'Kalan Bakiye';
        case 'toplam_bakiye':
            return 'Toplam Bakiye';
        default:
            return ucfirst($type);
    }
}

function getDeliveryStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge bg-secondary"><i class="fas fa-clock"></i> Henüz Başlamadı</span>';
        case 'in_transit':
            return '<span class="badge bg-warning"><i class="fas fa-truck"></i> Devam Ediyor</span>';
        case 'delivered':
            return '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Teslim Edildi</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

function formatPrice($price, $currency = 'TL') {
    $symbols = [
        'TL' => '₺',
        'USD' => '$',
        'EUR' => '€'
    ];

    $symbol = $symbols[$currency] ?? $currency;
    return number_format($price, 2) . ' ' . $symbol;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?> - Müşteri Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #ffc107;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #fd7e14;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a4a87 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }

        .main-content {
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        .customer-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .section-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table {
            border: none;
        }

        .table thead th {
            background: var(--light-color);
            border: none;
            color: var(--dark-color);
            font-weight: 600;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            border: none;
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f1f1;
        }

        .table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
        }

        .quote-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .btn-edit {
            background: var(--warning-color);
            color: white;
            border: none;
        }

        .btn-edit:hover {
            background: #e55a1c;
            color: white;
            transform: translateY(-1px);
        }

        .status-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .customer-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--primary-color), #1a4a87);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2rem;
            margin-right: 1.5rem;
        }

        .customer-info h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .back-btn {
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .customer-header .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .customer-avatar-large {
                margin: 0 auto 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-user-shield"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Ana Sayfa</a>
                <a class="nav-link" href="quotes.php">Teklifler</a>
                <a class="nav-link" href="customers.php">Müşteriler</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <!-- Back Button -->
        <div class="back-btn">
            <a href="view-customer.php?id=<?= $customer['id'] ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Müşteri Detayı
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Customer Header -->
        <div class="customer-header">
            <div class="d-flex align-items-center">
                <div class="customer-avatar-large">
                    <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                </div>
                <div class="customer-info flex-grow-1">
                    <h2><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></h2>
                    <div class="text-muted">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?>
                        <span class="ms-3"><i class="fas fa-phone"></i> <?= htmlspecialchars($customer['phone']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teklifler ve Durum Yönetimi -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-edit"></i>
                Teklif Durum Yönetimi
            </h4>

            <?php if (!empty($quotes)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Teklif No</th>
                            <th>Taşıma Modu</th>
                            <th>Fiyat</th>
                            <th>Ödeme Durumu</th>
                            <th>Teslimat Durumu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <span class="quote-number"><?= htmlspecialchars($quote['quote_number']) ?></span>
                                <br>
                                <small class="text-muted">
                                    <?= date('d.m.Y', strtotime($quote['created_at'])) ?>
                                </small>
                            </td>
                            <td>
                                <i class="<?= $quote['transport_mode_icon'] ?> text-primary"></i>
                                <?= htmlspecialchars($quote['transport_mode_name']) ?>
                            </td>
                            <td>
                                <?php if ($quote['final_price'] > 0): ?>
                                    <strong class="text-success"><?= formatPrice($quote['final_price']) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Hesaplanıyor...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= getPaymentStatusBadge($quote) ?>
                                <?php if (!empty($quote['total_paid']) && $quote['total_paid'] > 0): ?>
                                    <br><small class="text-success"><?= formatPrice($quote['total_paid']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= getDeliveryStatusBadge($quote['delivery_status'] ?? 'pending') ?>
                                <?php if (!empty($quote['delivery_date'])): ?>
                                    <br><small class="text-muted"><?= date('d.m.Y', strtotime($quote['delivery_date'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-action btn-edit" onclick="toggleStatusForm(<?= $quote['id'] ?>)">
                                    <i class="fas fa-edit"></i> Düzenle
                                </button>
                            </td>
                        </tr>
                        <tr id="status-form-<?= $quote['id'] ?>" style="display: none;">
                            <td colspan="6">
                                <form method="POST" class="status-form">
                                    <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
                                    <input type="hidden" name="update_quote" value="1">

                                    <!-- Ödeme İşlemleri -->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6><i class="fas fa-credit-card"></i> Ödeme İşlemleri</h6>

                                            <!-- Mevcut ödemeler -->
                                            <?php if (!empty($quote['payments'])): ?>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Tip</th>
                                                            <th>Tutar</th>
                                                            <th>Tarih</th>
                                                            <th>Açıklama</th>
                                                            <th>İşlem</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($quote['payments'] as $payment): ?>
                                                        <tr>
                                                            <td><?= getPaymentTypeName($payment['payment_type']) ?></td>
                                                            <td><?= formatPrice($payment['amount'], $payment['currency']) ?></td>
                                                            <td><?= date('d.m.Y', strtotime($payment['payment_date'])) ?></td>
                                                            <td><?= htmlspecialchars($payment['description'] ?? '') ?></td>
                                                            <td>
                                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Bu ödeme işlemini silmek istediğinizden emin misiniz?')">
                                                                    <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                                    <button type="submit" name="delete_payment" class="btn btn-sm btn-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <?php endif; ?>

                                            <!-- Yeni ödeme ekleme formu -->
                                            <div class="card">
                                                <div class="card-header">
                                                    <h6 class="mb-0">Yeni Ödeme Ekle</h6>
                                                </div>
                                                <div class="card-body">
                                                    <form method="POST">
                                                        <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
                                                        <input type="hidden" name="add_payment" value="1">

                                                        <div class="row">
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label">Ödeme Tipi</label>
                                                                <select name="payment_type" class="form-select" required>
                                                                    <option value="kaparo">Kaparo</option>
                                                                    <option value="ara_odeme">Ara Ödeme</option>
                                                                    <option value="kalan_bakiye">Kalan Bakiye</option>
                                                                    <option value="toplam_bakiye">Toplam Bakiye</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-2 mb-3">
                                                                <label class="form-label">Tutar</label>
                                                                <input type="number" name="amount" step="0.01" class="form-control" required placeholder="0.00">
                                                            </div>
                                                            <div class="col-md-2 mb-3">
                                                                <label class="form-label">Para Birimi</label>
                                                                <select name="currency" class="form-select" required>
                                                                    <option value="TL">TL (₺)</option>
                                                                    <option value="USD">USD ($)</option>
                                                                    <option value="EUR">EUR (€)</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label">Ödeme Tarihi</label>
                                                                <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                                            </div>
                                                            <div class="col-md-2 mb-3">
                                                                <label class="form-label">&nbsp;</label>
                                                                <button type="submit" class="btn btn-primary w-100">
                                                                    <i class="fas fa-plus"></i> Ekle
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Ödeme Yöntemi</label>
                                                                <input type="text" name="payment_method" class="form-control" placeholder="Nakit, Banka Havalesi, Kredi Kartı vb.">
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Açıklama</label>
                                                                <input type="text" name="description" class="form-control" placeholder="Ek açıklama...">
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Teslimat Bilgileri -->
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <h6><i class="fas fa-truck"></i> Teslimat Bilgileri</h6>
                                        </div>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="quote_id" value="<?= $quote['id'] ?>">
                                        <input type="hidden" name="update_quote" value="1">

                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Teslimat Durumu</label>
                                                <select name="delivery_status" class="form-select">
                                                    <option value="pending" <?= ($quote['delivery_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                                                    <option value="in_transit" <?= ($quote['delivery_status'] ?? '') === 'in_transit' ? 'selected' : '' ?>>Yolda</option>
                                                    <option value="delivered" <?= ($quote['delivery_status'] ?? '') === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Yükleme Tarihi</label>
                                                <input type="date" name="pickup_date" class="form-control"
                                                       value="<?= $quote['pickup_date'] ?? '' ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Teslim Tarihi</label>
                                                <input type="date" name="delivery_date" class="form-control"
                                                       value="<?= $quote['delivery_date'] ?? '' ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">&nbsp;</label>
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-save"></i> Teslimat Güncelle
                                                </button>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label class="form-label">Takip Notları</label>
                                                <textarea name="tracking_notes" class="form-control" rows="2"
                                                          placeholder="Özel notlar ve açıklamalar..."><?= htmlspecialchars($quote['tracking_notes'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-secondary" onclick="toggleStatusForm(<?= $quote['id'] ?>)">
                                            <i class="fas fa-times"></i> Kapat
                                        </button>
                                    </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-file-alt text-muted" style="font-size: 4rem;"></i>
                <h5 class="text-muted mt-3">Henüz teklif bulunmuyor</h5>
                <p class="text-muted">Bu müşteriye ait herhangi bir teklif bulunamadı.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleStatusForm(quoteId) {
            const form = document.getElementById('status-form-' + quoteId);
            if (form.style.display === 'none') {
                // Diğer formları kapat
                document.querySelectorAll('[id^="status-form-"]').forEach(f => f.style.display = 'none');
                form.style.display = 'table-row';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>