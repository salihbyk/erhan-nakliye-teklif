<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

checkAdminSession();

try {
    $database = new Database();
    $db = $database->getConnection();

    // Sayfalama
    $page = $_GET['page'] ?? 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Filtreleme
    $status_filter = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';

    $where_conditions = [];
    $params = [];

    // Arama koşulları
    if (!empty($search)) {
        $where_conditions[] = "(q.quote_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.company LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    }

    // Durum filtresi
    if ($status_filter === 'sent') {
        $where_conditions[] = "q.email_sent_at IS NOT NULL";
    } elseif ($status_filter === 'not_sent') {
        $where_conditions[] = "q.email_sent_at IS NULL AND q.final_price > 0";
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Toplam kayıt sayısı
    $count_sql = "
        SELECT COUNT(*) as total
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        $where_clause
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];

    // Email logları getir
    $sql = "
        SELECT
            q.id,
            q.quote_number,
            q.final_price,
            q.email_sent_at,
            q.email_sent_count,
            q.created_at,
            q.updated_at,
            q.status,
            c.first_name,
            c.last_name,
            c.email,
            c.company,
            tm.name as transport_name,
            tm.icon as transport_icon
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        $where_clause
        ORDER BY q.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $quotes = $stmt->fetchAll();

    // İstatistikler
    $stats_sql = "
        SELECT
            COUNT(*) as total_quotes,
            SUM(CASE WHEN email_sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent_emails,
            SUM(CASE WHEN email_sent_at IS NULL AND final_price > 0 THEN 1 ELSE 0 END) as pending_emails,
            SUM(CASE WHEN final_price IS NULL OR final_price = 0 THEN 1 ELSE 0 END) as no_price_quotes
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
    ";
    $stmt = $db->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch();

    $pagination = calculatePagination($total_records, $per_page, $page);

} catch (Exception $e) {
    setErrorMessage('Veri yüklenirken hata oluştu: ' . $e->getMessage());
    $quotes = [];
    $stats = ['total_quotes' => 0, 'sent_emails' => 0, 'pending_emails' => 0, 'no_price_quotes' => 0];
    $pagination = ['total_pages' => 1, 'current_page' => 1];
}

$messages = getMessages();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-posta Logları - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid;
        }

        .stats-card.total { border-left-color: #007bff; }
        .stats-card.sent { border-left-color: #28a745; }
        .stats-card.pending { border-left-color: #ffc107; }
        .stats-card.no-price { border-left-color: #dc3545; }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .main-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-sent {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-no-price {
            background-color: #f8d7da;
            color: #721c24;
        }

        .quote-number {
            font-weight: 600;
            color: #007bff;
            text-decoration: none;
        }

        .quote-number:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .customer-info {
            font-size: 0.9rem;
        }

        .customer-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .customer-email {
            color: #6c757d;
        }

        .company-name {
            color: #17a2b8;
            font-style: italic;
        }

        .transport-icon {
            margin-right: 5px;
            color: #6c757d;
        }

        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .btn-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }

        .btn-filter:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-envelope-open me-2"></i>E-posta Logları
                    </h1>
                    <p class="mb-0 opacity-75">Gönderilen e-postalar ve durumları</p>
                </div>
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-1"></i>Ana Sayfa
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Mesajlar -->
        <?php if ($messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
                    <?= $message['text'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- İstatistikler -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="stats-number"><?= number_format($stats['total_quotes']) ?></div>
                    <div>Toplam Teklif</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card sent">
                    <div class="stats-number"><?= number_format($stats['sent_emails']) ?></div>
                    <div>Gönderilen E-postalar</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending">
                    <div class="stats-number"><?= number_format($stats['pending_emails']) ?></div>
                    <div>Gönderilmemiş E-postalar</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card no-price">
                    <div class="stats-number"><?= number_format($stats['no_price_quotes']) ?></div>
                    <div>Fiyatlandırılmamış</div>
                </div>
            </div>
        </div>

        <!-- Filtreleme -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Durum Filtresi</label>
                    <select name="status" class="form-select">
                        <option value="">Tüm Durumlar</option>
                        <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Gönderilen E-postalar</option>
                        <option value="not_sent" <?= $status_filter === 'not_sent' ? 'selected' : '' ?>>Gönderilmemiş E-postalar</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Arama</label>
                    <input type="text" name="search" class="form-control" placeholder="Teklif no, müşteri adı, e-posta..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-search me-1"></i>Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- Email Logları Tablosu -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>E-posta Logları
                    <small class="text-muted">(<?= number_format($total_records) ?> kayıt)</small>
                </h5>
            </div>

            <?php if (empty($quotes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Kayıt bulunamadı</h5>
                    <p class="text-muted">Filtreleri değiştirerek tekrar deneyin.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag me-1"></i>Teklif No</th>
                                <th><i class="fas fa-user me-1"></i>Müşteri</th>
                                <th><i class="fas fa-truck me-1"></i>Taşıma</th>
                                <th><i class="fas fa-euro-sign me-1"></i>Fiyat</th>
                                <th><i class="fas fa-envelope me-1"></i>E-posta Durumu</th>
                                <th><i class="fas fa-calendar me-1"></i>Tarihler</th>
                                <th><i class="fas fa-cogs me-1"></i>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quotes as $quote): ?>
                                <tr>
                                    <!-- Teklif No -->
                                    <td>
                                        <a href="../view-quote.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                                           target="_blank" class="quote-number">
                                            #<?php echo htmlspecialchars($quote['quote_number']); ?>
                                        </a>
                                    </td>

                                    <!-- Müşteri -->
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name">
                                                <?php echo htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']); ?>
                                            </div>
                                            <?php if (!empty($quote['company'])): ?>
                                                <div class="company-name">
                                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($quote['company']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="customer-email">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($quote['email']); ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Taşıma -->
                                    <td>
                                        <i class="<?php echo htmlspecialchars($quote['transport_icon']); ?> transport-icon"></i>
                                        <?php echo htmlspecialchars($quote['transport_name']); ?>
                                    </td>

                                    <!-- Fiyat -->
                                    <td>
                                        <?php if ($quote['final_price'] > 0): ?>
                                            <strong><?php echo number_format($quote['final_price'], 2); ?> TL</strong>
                                        <?php else: ?>
                                            <span class="text-muted">Fiyatlandırılmamış</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- E-posta Durumu -->
                                    <td>
                                        <?php if ($quote['email_sent_at']): ?>
                                            <span class="status-badge status-sent">
                                                <i class="fas fa-check-circle"></i> Gönderildi
                                            </span>
                                            <?php if ($quote['email_sent_count'] > 1): ?>
                                                <small class="d-block text-muted mt-1">
                                                    (<?php echo $quote['email_sent_count']; ?> kez gönderildi)
                                                </small>
                                            <?php endif; ?>
                                        <?php elseif ($quote['final_price'] > 0): ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Gönderilmemiş
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-no-price">
                                                <i class="fas fa-times-circle"></i> Fiyat Yok
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tarihler -->
                                    <td>
                                        <small>
                                            <div><strong>Oluşturulma:</strong></div>
                                            <div><?php echo formatDateTime($quote['created_at']); ?></div>
                                            <?php if ($quote['email_sent_at']): ?>
                                                <div class="mt-1"><strong>E-posta:</strong></div>
                                                <div><?php echo formatDateTime($quote['email_sent_at']); ?></div>
                                            <?php endif; ?>
                                        </small>
                                    </td>

                                    <!-- İşlemler -->
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="view-quote.php?id=<?php echo urlencode($quote['quote_number']); ?>"
                                               class="btn btn-primary btn-sm" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($quote['final_price'] > 0): ?>
                                                <?php if (empty($quote['email_sent_at'])): ?>
                                                    <!-- Email Gönder Butonu -->
                                                    <button type="button" class="btn btn-success btn-sm"
                                                            onclick="sendQuoteEmail(<?php echo $quote['id']; ?>)"
                                                            title="Email Gönder">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Email Tekrar Gönder -->
                                                    <button type="button" class="btn btn-info btn-sm"
                                                            onclick="sendQuoteEmail(<?php echo $quote['id']; ?>)"
                                                            title="Email Tekrar Gönder">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sayfalama -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                                <li class="page-item <?= $i == $pagination['current_page'] ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Email gönderme fonksiyonu (quotes.php'den kopyalandı)
        function sendQuoteEmail(quoteId) {
            if (!confirm('Bu teklifin e-postasını göndermek istediğinizden emin misiniz?')) {
                return;
            }

            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            fetch('quotes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_email&quote_id=${quoteId}`
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        alert('E-posta başarıyla gönderildi!');
                        window.location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response text:', text);
                    alert('Sunucu yanıt hatası: ' + text.substring(0, 200));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bağlantı hatası oluştu: ' + error.message);
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>