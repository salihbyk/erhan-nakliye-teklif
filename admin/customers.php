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

// Filtre parametreleri
$payment_filter = $_GET['payment_filter'] ?? '';
$delivery_filter = $_GET['delivery_filter'] ?? '';

// WHERE koşulları oluştur
$where_conditions = [];
$params = [];

if ($payment_filter) {
    $where_conditions[] = "q.payment_status = ?";
    $params[] = $payment_filter;
}

if ($delivery_filter) {
    $where_conditions[] = "q.delivery_status = ?";
    $params[] = $delivery_filter;
}

$where_clause = !empty($where_conditions) ? 'AND ' . implode(' AND ', $where_conditions) : '';

// Müşterileri getir (yeni sütunlarla)
$stmt = $db->prepare("
    SELECT DISTINCT
        c.id,
        c.first_name,
        c.last_name,
        c.email,
        c.phone,
        c.company,
        c.created_at,
        COUNT(q.id) as total_quotes,
        COUNT(CASE WHEN q.status = 'accepted' THEN 1 END) as approved_quotes,
        COUNT(CASE WHEN q.payment_status = 'paid' THEN 1 END) as paid_quotes,
        COUNT(CASE WHEN q.delivery_status = 'in_transit' THEN 1 END) as in_transit_quotes,
        COUNT(CASE WHEN q.delivery_status = 'delivered' THEN 1 END) as delivered_quotes,
        SUM(CASE WHEN q.status = 'accepted' THEN q.final_price ELSE 0 END) as total_revenue,
        SUM(CASE WHEN q.payment_status = 'paid' THEN q.payment_amount ELSE 0 END) as paid_amount,
        MAX(q.created_at) as last_quote_date,
        GROUP_CONCAT(DISTINCT tm.name SEPARATOR ', ') as transport_modes
    FROM customers c
    INNER JOIN quotes q ON c.id = q.customer_id
    LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
    WHERE 1=1 $where_clause
    GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.created_at
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

// İstatistikler (yeni sütunlarla)
$stats_stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT c.id) as total_customers,
        COUNT(DISTINCT CASE WHEN q.payment_status = 'paid' THEN c.id END) as customers_with_payment,
        COUNT(DISTINCT CASE WHEN q.delivery_status = 'in_transit' THEN c.id END) as customers_in_transit,
        COUNT(DISTINCT CASE WHEN q.delivery_status = 'delivered' THEN c.id END) as customers_delivered,
        SUM(CASE WHEN q.status = 'accepted' THEN q.final_price ELSE 0 END) as total_revenue,
        SUM(CASE WHEN q.payment_status = 'paid' THEN q.payment_amount ELSE 0 END) as total_paid,
        AVG(CASE WHEN q.status = 'accepted' THEN q.final_price ELSE NULL END) as avg_quote_value
    FROM customers c
    LEFT JOIN quotes q ON c.id = q.customer_id
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteriler - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
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

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }

        .page-title {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.customers { background: linear-gradient(135deg, var(--primary-color), #1a4a87); }
        .stat-icon.approved { background: linear-gradient(135deg, var(--success-color), #1e7e34); }
        .stat-icon.revenue { background: linear-gradient(135deg, var(--warning-color), #e55a1c); }
        .stat-icon.average { background: linear-gradient(135deg, var(--info-color), #138496); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .customers-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }



        /* Tablo responsive düzenlemeleri */
        .table td {
            vertical-align: middle;
            padding: 0.75rem 0.5rem;
        }


            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-color);
        }

        .table-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-box {
            max-width: 300px;
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
            border-bottom: 2px solid #dee2e6;
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

        .customer-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .customer-avatar {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), #1a4a87);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .customer-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .customer-details small {
            color: #6c757d;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
        }

        .badge-primary { background: var(--primary-color); }
        .badge-success { background: var(--success-color); }
        .badge-warning { background: var(--warning-color); }
        .badge-info { background: var(--info-color); }

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

        .btn-delete {
            background: var(--danger-color);
            color: white;
            border: none;
            margin-left: 5px;
        }

        .btn-delete:hover {
            background: #c82333;
            color: white;
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .dropdown-menu {
            min-width: 280px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .dropdown-item {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .btn-pdf {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        .revenue-display {
            font-weight: 600;
            color: var(--success-color);
        }

        .transport-modes {
            font-size: 0.85rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .customer-avatar {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
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
                <a class="nav-link active" href="customers.php">Müşteriler</a>
                <a class="nav-link" href="logout.php">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users"></i>
                Müşteriler
            </h1>
            <p class="mb-0 text-muted">Onaylanan tekliflere sahip müşterileri görüntüleyin ve yönetin</p>
        </div>

        <!-- İstatistik Kartları -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="stat-value"><?= number_format($stats['total_customers']) ?></h3>
                <p class="stat-label">Toplam Müşteri</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3 class="stat-value"><?= number_format($stats['customers_with_payment']) ?></h3>
                <p class="stat-label">Ödemesi Yapılan</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-truck"></i>
                </div>
                <h3 class="stat-value"><?= number_format($stats['customers_in_transit']) ?></h3>
                <p class="stat-label">Taşıması Devam Eden</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon average">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="stat-value"><?= number_format($stats['customers_delivered']) ?></h3>
                <p class="stat-label">Teslim Edilenler</p>
            </div>
        </div>

        <!-- Müşteriler Tablosu -->
        <div class="customers-table">
            <div class="table-header">
                <h4 class="table-title">
                    <i class="fas fa-table"></i>
                    Müşteri Listesi
                </h4>
                <div class="search-box">
                    <input type="text" class="form-control" id="searchInput" placeholder="Müşteri ara...">
                </div>
            </div>

            <!-- Filtreler -->
            <div class="mb-3">
                <form method="GET" class="row g-2">
                    <div class="col-md-4">
                        <select name="payment_filter" class="form-select">
                            <option value="">Tüm Ödeme Durumları</option>
                            <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Ödeme Bekliyor</option>
                            <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Ödeme Tamamlandı</option>
                            <option value="partial" <?= $payment_filter === 'partial' ? 'selected' : '' ?>>Kısmi Ödeme</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="delivery_filter" class="form-select">
                            <option value="">Tüm Teslimat Durumları</option>
                            <option value="pending" <?= $delivery_filter === 'pending' ? 'selected' : '' ?>>Teslimat Bekliyor</option>
                            <option value="in_transit" <?= $delivery_filter === 'in_transit' ? 'selected' : '' ?>>Taşıma Devam Ediyor</option>
                            <option value="delivered" <?= $delivery_filter === 'delivered' ? 'selected' : '' ?>>Teslim Edildi</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filtrele
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="customers.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times"></i> Temizle
                        </a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table" id="customersTable">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>İletişim</th>
                            <th>Teklifler</th>
                            <th>Ödeme Durumu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <div class="customer-info">
                                    <div class="customer-avatar">
                                        <?= strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?>
                                    </div>
                                    <div class="customer-details">
                                        <h6><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></h6>
                                        <small>Müşteri #<?= $customer['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <i class="fas fa-envelope text-primary me-1"></i>
                                    <small><?= htmlspecialchars($customer['email']) ?></small>
                                </div>
                                <div>
                                    <i class="fas fa-phone text-success me-1"></i>
                                    <small><?= htmlspecialchars($customer['phone']) ?></small>
                                </div>
                            </td>

                            <td>
                                <div>
                                    <span class="badge badge-primary"><?= $customer['total_quotes'] ?> Toplam</span>
                                </div>
                                <div class="mt-1">
                                    <span class="badge badge-success"><?= $customer['approved_quotes'] ?> Onaylı</span>
                                </div>
                            </td>
                            <td>
                                <?php if ($customer['paid_quotes'] > 0): ?>
                                    <span class="badge bg-success"><?= $customer['paid_quotes'] ?> Ödendi</span>
                                <?php else: ?>
                                    <!-- Kısmi ödeme kontrolü -->
                                    <?php
                                    $partial_payment_stmt = $db->prepare("
                                        SELECT COUNT(*) as partial_count
                                        FROM quotes
                                        WHERE customer_id = ? AND payment_status = 'partial'
                                    ");
                                    $partial_payment_stmt->execute([$customer['id']]);
                                    $partial_result = $partial_payment_stmt->fetch();

                                    if ($partial_result['partial_count'] > 0): ?>
                                        <span class="badge bg-warning">Kısmi Ödeme</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Ödeme Yapılmadı</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="action-buttons">
                                    <a href="view-customer.php?id=<?= $customer['id'] ?>" class="btn btn-action btn-view">
                                        <i class="fas fa-eye"></i> Detay
                                    </a>
                                                                        <?php
                                    // Müşteriye ait tüm teklifleri getir
                                    $pdf_stmt = $db->prepare("
                                        SELECT quote_number, status, created_at, final_price
                                        FROM quotes
                                        WHERE customer_id = ?
                                        ORDER BY created_at DESC
                                    ");
                                    $pdf_stmt->execute([$customer['id']]);
                                    $customer_quotes = $pdf_stmt->fetchAll();

                                    if (count($customer_quotes) > 0): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-action btn-pdf dropdown-toggle" type="button" data-bs-toggle="dropdown" style="background: #6f42c1; border-color: #6f42c1; color: white;">
                                                <i class="fas fa-file-pdf"></i> PDF'ler (<?= count($customer_quotes) ?>)
                                            </button>
                                                                                        <ul class="dropdown-menu">
                                                <?php foreach ($customer_quotes as $quote):
                                                    // Durum renklerini belirle
                                                    $status_color = match($quote['status']) {
                                                        'accepted' => '#28a745',
                                                        'pending' => '#ffc107',
                                                        'sent' => '#17a2b8',
                                                        'rejected' => '#dc3545',
                                                        'expired' => '#6c757d',
                                                        default => '#6c757d'
                                                    };

                                                    $status_text = match($quote['status']) {
                                                        'accepted' => 'Onaylandı',
                                                        'pending' => 'Bekliyor',
                                                        'sent' => 'Gönderildi',
                                                        'rejected' => 'Reddedildi',
                                                        'expired' => 'Süresi Doldu',
                                                        default => 'Bilinmiyor'
                                                    };
                                                ?>
                                                <li>
                                                    <a class="dropdown-item" href="../api/generate-pdf.php?id=<?= urlencode($quote['quote_number']) ?>" target="_blank">
                                                        <i class="fas fa-file-pdf text-danger me-2"></i>
                                                        <div style="display: inline-block;">
                                                            <div style="font-weight: 500; display: flex; align-items: center; gap: 8px;">
                                                                <?= htmlspecialchars($quote['quote_number']) ?>
                                                                <span class="badge" style="background: <?= $status_color ?>; color: white; font-size: 8px; padding: 2px 6px;">
                                                                    <?= $status_text ?>
                                                                </span>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?= date('d.m.Y', strtotime($quote['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </a>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <span class="btn btn-action btn-secondary" style="opacity: 0.5; cursor: not-allowed;">
                                            <i class="fas fa-file-pdf"></i> PDF Yok
                                        </span>
                                    <?php endif; ?>
                                    <button class="btn btn-action btn-delete" onclick="deleteCustomer(<?= $customer['id'] ?>, '<?= addslashes($customer['first_name'] . ' ' . $customer['last_name']) ?>')">
                                        <i class="fas fa-trash"></i> Sil
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($customers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users text-muted" style="font-size: 4rem;"></i>
                <h5 class="text-muted mt-3">Henüz müşteri bulunmuyor</h5>
                <p class="text-muted">İlk teklif oluşturulduğunda müşteriler burada görünecektir.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Arama fonksiyonu
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#customersTable tbody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Müşteri silme fonksiyonu
        function deleteCustomer(customerId, customerName) {
            if (confirm(`"${customerName}" müşterisini silmek istediğinizden emin misiniz?\n\nBu işlem müşteriye ait tüm teklifleri ve ödeme kayıtlarını da silecektir.\n\nBu işlem geri alınamaz!`)) {
                // Loading göster
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Siliniyor...';
                button.disabled = true;

                fetch('../api/delete-customer.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_id: customerId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Başarılı mesaj göster
                        alert('✅ ' + data.message);

                        // Satırı tablodan kaldır
                        const row = button.closest('tr');
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-100%)';

                        setTimeout(() => {
                            row.remove();

                            // Eğer tablo boş kaldıysa sayfayı yenile
                            const remainingRows = document.querySelectorAll('#customersTable tbody tr');
                            if (remainingRows.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert('❌ Hata: ' + data.message);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Bir hata oluştu. Lütfen tekrar deneyin.');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        }

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