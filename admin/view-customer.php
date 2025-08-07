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

// Müşteri bilgilerini getir
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit();
}

// Müşterinin tekliflerini getir
$quotes_stmt = $db->prepare("
    SELECT
        q.*,
        tm.name as transport_mode_name,
        tm.icon as transport_mode_icon,
        qt.currency as template_currency
    FROM quotes q
    LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
    LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
    WHERE q.customer_id = ?
    ORDER BY q.created_at DESC
");
$quotes_stmt->execute([$customer_id]);
$quotes = $quotes_stmt->fetchAll();

// Müşteri istatistikleri
$stats_stmt = $db->prepare("
    SELECT
        COUNT(*) as total_quotes,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as approved_quotes,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_quotes,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_quotes,
        SUM(CASE WHEN status = 'accepted' THEN final_price ELSE 0 END) as total_revenue,
        AVG(CASE WHEN status = 'accepted' THEN final_price ELSE NULL END) as avg_quote_value,
        MIN(created_at) as first_quote_date,
        MAX(created_at) as last_quote_date
    FROM quotes
    WHERE customer_id = ?
");
$stats_stmt->execute([$customer_id]);
$stats = $stats_stmt->fetch();

// Taşıma modları dağılımı
$transport_stats = $db->prepare("
    SELECT
        tm.name,
        tm.icon,
        COUNT(*) as quote_count
    FROM quotes q
    LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
    WHERE q.customer_id = ?
    GROUP BY tm.id, tm.name, tm.icon
    ORDER BY quote_count DESC
");
$transport_stats->execute([$customer_id]);
$transport_modes = $transport_stats->fetchAll();

function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="badge badge-warning"><i class="fas fa-clock"></i> Bekliyor</span>';
        case 'accepted':
            return '<span class="badge badge-success"><i class="fas fa-check"></i> Onaylandı</span>';
        case 'approved':
            return '<span class="badge badge-success"><i class="fas fa-check"></i> Onaylandı</span>';
        case 'rejected':
            return '<span class="badge badge-danger"><i class="fas fa-times"></i> Reddedildi</span>';
        case 'sent':
            return '<span class="badge badge-info"><i class="fas fa-paper-plane"></i> Gönderildi</span>';
        default:
            return '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }
}

function formatPrice($price) {
    return number_format($price, 2) . ' ₺';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?> - Müşteri Detay</title>
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(25, 83, 185, 0.08);
            border: 1px solid rgba(25, 83, 185, 0.08);
            position: relative;
            overflow: hidden;
        }

        .customer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), #007bff, #6f42c1);
            border-radius: 20px 20px 0 0;
        }

        .customer-avatar-large {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #007bff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.6rem;
            margin-right: 1.2rem;
            box-shadow: 0 8px 20px rgba(25, 83, 185, 0.2);
            position: relative;
        }

        .customer-avatar-large::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            z-index: -1;
        }

        .customer-info h2 {
            color: #1a202c;
            font-weight: 600;
            margin: 0 0 0.3rem 0;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
        }

        .customer-meta {
            color: #64748b;
            font-size: 0.85rem;
            margin: 0 0 0.8rem 0;
            font-weight: 500;
        }

        .customer-meta .separator {
            margin: 0 8px;
            color: #cbd5e0;
        }

        .contact-info {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: #4a5568;
            background: rgba(255, 255, 255, 0.7);
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            border: 1px solid rgba(25, 83, 185, 0.05);
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .contact-item:hover {
            background: rgba(25, 83, 185, 0.05);
            transform: translateY(-1px);
        }

        .contact-item i {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .stat-icon.total { background: linear-gradient(135deg, var(--primary-color), #1a4a87); }
        .stat-icon.approved { background: linear-gradient(135deg, var(--success-color), #1e7e34); }
        .stat-icon.pending { background: linear-gradient(135deg, var(--warning-color), #e55a1c); }
        .stat-icon.revenue { background: linear-gradient(135deg, var(--info-color), #138496); }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 0;
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

        .badge-primary { background: var(--primary-color); }
        .badge-success { background: var(--success-color); }
        .badge-warning { background: var(--warning-color); }
        .badge-danger { background: var(--danger-color); }
        .badge-info { background: var(--info-color); }

        .transport-mode {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .transport-icon {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        /* Minimal transport modes styles */
        .transport-modes-mini {
            border-top: 1px solid rgba(25, 83, 185, 0.08);
            padding-top: 0.8rem;
            margin-top: 0.8rem;
        }

        .transport-badge {
            background: rgba(25, 83, 185, 0.06);
            color: var(--primary-color);
            padding: 0.3rem 0.7rem;
            border-radius: 14px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid rgba(25, 83, 185, 0.1);
            transition: all 0.2s ease;
        }

        .transport-badge:hover {
            background: rgba(25, 83, 185, 0.1);
            transform: translateY(-1px);
        }

        .badge-count {
            background: var(--primary-color);
            color: white;
            font-size: 0.65rem;
            padding: 2px 5px;
            border-radius: 8px;
            margin-left: 2px;
            min-width: 16px;
            text-align: center;
            font-weight: 600;
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
        }

        .btn-view {
            background: var(--info-color);
            color: white;
            border: none;
        }

        .btn-view:hover {
            background: #138496;
            color: white;
            transform: translateY(-1px);
        }

        .back-btn {
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .customer-header {
                padding: 1.2rem 1.5rem;
                border-radius: 16px;
            }

            .customer-header .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .customer-avatar-large {
                margin: 0 auto 1rem;
                width: 60px;
                height: 60px;
                font-size: 1.4rem;
            }

            .customer-info h2 {
                font-size: 1.4rem;
            }

            .customer-meta {
                font-size: 0.8rem;
            }

            .contact-info {
                justify-content: center;
                gap: 0.8rem;
            }

            .contact-item {
                font-size: 0.8rem;
                padding: 0.3rem 0.7rem;
            }

            .transport-badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.6rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
        <div class="back-btn d-flex justify-content-between align-items-center">
            <a href="customers.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Müşteriler Listesi
            </a>
            <a href="edit-customer.php?id=<?= $customer['id'] ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Müşteri Bilgilerini Düzenle
            </a>
        </div>

        <!-- Customer Header -->
        <div class="customer-header">
            <div class="d-flex align-items-center">
                <div class="customer-avatar-large">
                    <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                </div>
                <div class="customer-info flex-grow-1">
                    <h2><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></h2>
                    <div class="customer-meta">
                        Müşteri #<?= $customer['id'] ?>
                        <?php if ($customer['company']): ?>
                            <span class="separator">•</span><?= htmlspecialchars($customer['company']) ?>
                        <?php endif; ?>
                        <span class="separator">•</span>Kayıt: <?= date('d.m.Y', strtotime($customer['created_at'])) ?>
                    </div>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope text-primary"></i>
                            <span><?= htmlspecialchars($customer['email']) ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone text-success"></i>
                            <span><?= htmlspecialchars($customer['phone']) ?></span>
                        </div>
                        <?php if ($customer['company']): ?>
                        <div class="contact-item">
                            <i class="fas fa-building text-info"></i>
                            <span><?= htmlspecialchars($customer['company']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Kullanılan Taşıma Modları - Minimal -->
                    <?php if (!empty($transport_modes)): ?>
                    <div class="transport-modes-mini mt-3">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <small class="text-muted me-2">
                                <i class="fas fa-shipping-fast me-1"></i>
                                Kullanılan Modlar:
                            </small>
                            <?php foreach ($transport_modes as $mode): ?>
                            <div class="transport-badge">
                                <i class="<?= $mode['icon'] ?> me-1"></i>
                                <?= htmlspecialchars($mode['name']) ?>
                                <span class="badge-count"><?= $mode['quote_count'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Teklifler Listesi - EN ÜST -->
        <div class="section-card">
            <h4 class="section-title">
                <i class="fas fa-list"></i>
                Teklif Geçmişi
            </h4>

            <?php if (!empty($quotes)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Teklif No</th>
                            <th>Taşıma Modu</th>
                            <th>Güzergah</th>
                            <th>Durum</th>
                            <th>Fiyat</th>
                            <th>Tarih</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <span class="quote-number"><?= htmlspecialchars($quote['quote_number']) ?></span>
                            </td>
                            <td>
                                <div class="transport-mode">
                                    <i class="<?= $quote['transport_mode_icon'] ?> transport-icon"></i>
                                    <span><?= htmlspecialchars($quote['transport_mode_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($quote['origin']) ?></strong>
                                    <br>
                                    <i class="fas fa-arrow-down text-muted"></i>
                                    <br>
                                    <strong><?= htmlspecialchars($quote['destination']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <?= getStatusBadge($quote['status']) ?>
                            </td>
                            <td>
                                <?php if ($quote['final_price'] > 0): ?>
                                    <?php
                                        // admin/quotes.php ile aynı mantığı kullan
                                        $currency = $quote['template_currency'] ?? 'EUR';
                                        $currency_symbol = $currency === 'TL' ? '₺' : ($currency === 'USD' ? '$' : '€');
                                        $price = number_format($quote['final_price'], 0, ',', '.');

                                        // Renk belirleme
                                        $currency_color = '#28a745'; // Success yeşili
                                        if ($currency === 'USD') {
                                            $currency_color = '#28a745'; // USD için yeşil
                                        } elseif ($currency === 'EUR') {
                                            $currency_color = '#007bff'; // EUR için mavi
                                        } elseif ($currency === 'TL') {
                                            $currency_color = '#ffc107'; // TL için sarı
                                        }
                                    ?>
                                    <strong style="color: <?= $currency_color ?>;">
                                        <?= $currency_symbol ?><?= $price ?>
                                    </strong>
                                <?php else: ?>
                                    <span class="text-muted">Hesaplanıyor...</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date('d.m.Y H:i', strtotime($quote['created_at'])) ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($quote['status'] === 'accepted' || $quote['status'] === 'approved'): ?>
                                    <!-- Onaylanmış teklifler için PDF görünümü -->
                                    <a href="../view-quote-pdf.php?id=<?= urlencode($quote['quote_number']) ?>" target="_blank" class="btn btn-action btn-view">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <!-- Admin düzenleme erişimi -->
                                    <a href="view-quote.php?id=<?= $quote['quote_number'] ?>" class="btn btn-action" style="background: #6c757d; color: white; font-size: 0.8rem;">
                                        <i class="fas fa-cog"></i> Admin
                                    </a>
                                <?php else: ?>
                                    <!-- Onaylanmamış teklifler için düzenleme -->
                                    <a href="view-quote.php?id=<?= $quote['quote_number'] ?>" class="btn btn-action btn-view">
                                        <i class="fas fa-eye"></i> Görüntüle
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-file-alt text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">Bu müşteriye ait henüz teklif bulunmuyor.</p>
                <a href="quotes.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Yeni Teklif Oluştur
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 class="stat-value"><?= $stats['total_quotes'] ?></h3>
                <p class="stat-label">Toplam Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="stat-value"><?= $stats['approved_quotes'] ?></h3>
                <p class="stat-label">Onaylı Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="stat-value"><?= $stats['pending_quotes'] ?></h3>
                <p class="stat-label">Bekleyen Teklif</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 class="stat-value"><?= $stats['rejected_quotes'] ?></h3>
                <p class="stat-label">Reddedilen Teklif</p>
            </div>
        </div>




    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sayfa yüklendiğinde animasyonlar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';

                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>