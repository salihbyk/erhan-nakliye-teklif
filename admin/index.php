<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'update-notifications.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    // İstatistikleri al
    $stats = [];

    // Toplam teklif sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM quotes");
    $stats['total_quotes'] = $stmt->fetch()['total'];

    // Bu ayki teklifler
    $stmt = $db->query("SELECT COUNT(*) as total FROM quotes WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stats['monthly_quotes'] = $stmt->fetch()['total'];

    // Toplam müşteri sayısı
    $stmt = $db->query("SELECT COUNT(*) as total FROM customers");
    $stats['total_customers'] = $stmt->fetch()['total'];

              // Toplam gelir (kabul edilen teklifler) - Para birimlerine göre
     $stmt = $db->query("
         SELECT
             SUM(CASE WHEN qt.currency = 'TL' OR qt.currency IS NULL THEN q.final_price ELSE 0 END) as revenue_tl,
             SUM(CASE WHEN qt.currency = 'USD' THEN q.final_price ELSE 0 END) as revenue_usd,
             SUM(CASE WHEN qt.currency = 'EUR' THEN q.final_price ELSE 0 END) as revenue_eur
         FROM quotes q
         LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
         WHERE q.status = 'accepted'
     ");
     $result = $stmt->fetch();
     $stats['revenue_tl'] = $result['revenue_tl'] ?? 0;
     $stats['revenue_usd'] = $result['revenue_usd'] ?? 0;
     $stats['revenue_eur'] = $result['revenue_eur'] ?? 0;

     // Beklenen gelir (onay bekleyen teklifler) - Para birimlerine göre
     $stmt = $db->query("
         SELECT
             SUM(CASE WHEN qt.currency = 'TL' OR qt.currency IS NULL THEN q.final_price ELSE 0 END) as pending_tl,
             SUM(CASE WHEN qt.currency = 'USD' THEN q.final_price ELSE 0 END) as pending_usd,
             SUM(CASE WHEN qt.currency = 'EUR' THEN q.final_price ELSE 0 END) as pending_eur
         FROM quotes q
         LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
         WHERE q.status IN ('pending', 'sent')
     ");
     $result = $stmt->fetch();
     $stats['pending_tl'] = $result['pending_tl'] ?? 0;
     $stats['pending_usd'] = $result['pending_usd'] ?? 0;
     $stats['pending_eur'] = $result['pending_eur'] ?? 0;

         // Onay bekleyen teklifler (müşteri onayı verilmemiş)
     $stmt = $db->prepare("
         SELECT q.quote_number, q.created_at, q.final_price, q.status, q.currency,
                qt.currency as template_currency,
                c.first_name, c.last_name, tm.name as transport_mode
         FROM quotes q
         JOIN customers c ON q.customer_id = c.id
         JOIN transport_modes tm ON q.transport_mode_id = tm.id
         LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
         WHERE q.status IN ('pending', 'sent')
         ORDER BY q.created_at DESC
         LIMIT 10
     ");
     $stmt->execute();
     $pending_quotes = $stmt->fetchAll();

    // Aylık grafik verileri (son 6 ay)
    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $stmt = $db->prepare("
            SELECT COUNT(*) as count, SUM(final_price) as revenue
            FROM quotes
            WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        ");
        $stmt->execute([$month]);
        $data = $stmt->fetch();
        $monthly_data[] = [
            'month' => date('M Y', strtotime($month . '-01')),
            'count' => $data['count'] ?? 0,
            'revenue' => $data['revenue'] ?? 0
        ];
    }

    // Güncelleme bildirimleri
    $updateNotifications = new UpdateNotifications();
    $updateNotification = $updateNotifications->showUpdateNotification();

} catch (Exception $e) {
    $error_message = 'Veriler yüklenirken hata oluştu: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Nakliye Teklif Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="includes/sidebar.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --card-shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
            --border-radius: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* Modern sidebar included via external CSS */

        /* Enhanced Stat Cards with Stronger Backgrounds and Color Borders */
        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            background: white;
        }

        .stat-card.primary {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.12));
            border-color: rgba(102, 126, 234, 0.2);
            border-bottom: 4px solid #667eea;
        }

        .stat-card.success {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.08), rgba(56, 161, 105, 0.12));
            border-color: rgba(72, 187, 120, 0.2);
            border-bottom: 4px solid #48bb78;
        }

        .stat-card.info {
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.08), rgba(49, 130, 206, 0.12));
            border-color: rgba(66, 153, 225, 0.2);
            border-bottom: 4px solid #4299e1;
        }

        .stat-card.warning {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.08), rgba(221, 107, 32, 0.12));
            border-color: rgba(237, 137, 54, 0.2);
            border-bottom: 4px solid #ed8936;
        }

        .stat-card:hover {
            border-color: #cbd5e0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }

        .stat-card.primary:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.08));
            border-color: rgba(102, 126, 234, 0.15);
        }

        .stat-card.success:hover {
            background: linear-gradient(135deg, rgba(72, 187, 120, 0.05), rgba(56, 161, 105, 0.08));
            border-color: rgba(72, 187, 120, 0.15);
        }

        .stat-card.info:hover {
            background: linear-gradient(135deg, rgba(66, 153, 225, 0.05), rgba(49, 130, 206, 0.08));
            border-color: rgba(66, 153, 225, 0.15);
        }

        .stat-card.warning:hover {
            background: linear-gradient(135deg, rgba(237, 137, 54, 0.05), rgba(221, 107, 32, 0.08));
            border-color: rgba(237, 137, 54, 0.15);
        }

        .stat-card .card-body {
            padding: 20px;
            position: relative;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 2px;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #718096;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            position: absolute;
            top: 16px;
            right: 16px;
        }

        .stat-icon.primary { background: #667eea; }
        .stat-icon.success { background: #48bb78; }
        .stat-icon.info { background: #4299e1; }
        .stat-icon.warning { background: #ed8936; }

        /* Minimal Action Cards */
        .action-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            background: white;
            text-decoration: none;
            color: inherit;
            height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            border-color: #cbd5e0;
        }

        .action-card i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: #4a5568;
        }

        .action-card .action-title {
            font-weight: 500;
            font-size: 0.8rem;
            text-align: center;
            color: #4a5568;
            line-height: 1.2;
        }

        .action-card.warning i { color: #ed8936; }
        .action-card.primary i { color: #667eea; }
        .action-card.success i { color: #48bb78; }
        .action-card.info i { color: #4299e1; }

        /* Main Content Area */
        .main-content {
            background: #f8fafc;
            min-height: 100vh;
            padding: 2rem;
        }

        .page-title {
            color: #2d3748;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #718096;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }



        /* Responsive improvements */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .stat-icon {
                width: 48px;
                height: 48px;
                font-size: 20px;
            }
        }


    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Güncelleme Bildirimi -->
    <?= $updateNotification ?? '' ?>

    <!-- Ana İçerik -->
    <main class="main-content">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-tachometer-alt me-3"></i>Dashboard
                        </h1>
                        <p class="page-subtitle">Hoş geldiniz! İşletmenizin genel durumunu buradan takip edebilirsiniz.</p>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-outline-primary">
                            <i class="fas fa-calendar me-2"></i>Bu Ay
                        </button>
                        <button type="button" class="btn btn-outline-info me-2" onclick="checkForUpdates()">
                            <i class="fas fa-sync-alt me-2"></i>Güncelleme Kontrol
                        </button>
                        <a href="../index.php" class="btn btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>Siteyi Görüntüle
                        </a>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>

                                 <!-- İstatistik Kartları -->
                 <div class="row mb-5">
                     <div class="col-xl col-lg-4 col-md-6 mb-4">
                         <div class="card stat-card primary">
                             <div class="card-body">
                                 <div class="stat-label">Toplam Teklif</div>
                                 <div class="stat-value"><?= number_format($stats['total_quotes']) ?></div>
                                 <div class="stat-icon primary">
                                     <i class="fas fa-file-invoice"></i>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <div class="col-xl col-lg-4 col-md-6 mb-4">
                         <div class="card stat-card success">
                             <div class="card-body">
                                 <div class="stat-label">Bu Ayki Teklifler</div>
                                 <div class="stat-value"><?= number_format($stats['monthly_quotes']) ?></div>
                                 <div class="stat-icon success">
                                     <i class="fas fa-calendar-check"></i>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <div class="col-xl col-lg-4 col-md-6 mb-4">
                         <div class="card stat-card info">
                             <div class="card-body">
                                 <div class="stat-label">Toplam Müşteri</div>
                                 <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
                                 <div class="stat-icon info">
                                     <i class="fas fa-users"></i>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <div class="col-xl col-lg-6 col-md-6 mb-4">
                         <div class="card stat-card warning">
                             <div class="card-body">
                                 <div class="stat-label">Toplam Gelir (Onaylı)</div>
                                 <div class="currency-breakdown">
                                     <?php if ($stats['revenue_tl'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['revenue_tl'], 0, ',', '.') ?> TL</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['revenue_usd'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['revenue_usd'], 0, ',', '.') ?> USD</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['revenue_eur'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['revenue_eur'], 0, ',', '.') ?> EUR</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['revenue_tl'] == 0 && $stats['revenue_usd'] == 0 && $stats['revenue_eur'] == 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span>Henüz gelir yok</span>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                                                                  <div class="stat-icon warning">
                                     <i class="fas fa-coins"></i>
                                 </div>
                             </div>
                         </div>
                     </div>

                     <div class="col-xl col-lg-6 col-md-6 mb-4">
                         <div class="card stat-card" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.08), rgba(252, 176, 64, 0.12)); border-color: rgba(255, 193, 7, 0.2); border-bottom: 4px solid #ffc107;">
                             <div class="card-body">
                                 <div class="stat-label">Beklenen Gelir</div>
                                 <div class="currency-breakdown">
                                     <?php if ($stats['pending_tl'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['pending_tl'], 0, ',', '.') ?> TL</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['pending_usd'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['pending_usd'], 0, ',', '.') ?> USD</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['pending_eur'] > 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-coins me-1"></i>
                                             <span><?= number_format($stats['pending_eur'], 0, ',', '.') ?> EUR</span>
                                         </div>
                                     <?php endif; ?>
                                     <?php if ($stats['pending_tl'] == 0 && $stats['pending_usd'] == 0 && $stats['pending_eur'] == 0): ?>
                                         <div class="currency-item">
                                             <i class="fas fa-clock me-1" style="color: #6c757d;"></i>
                                             <span style="color: #6c757d;">Beklenen gelir yok</span>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                                 <div class="stat-icon" style="background: #ffc107;">
                                     <i class="fas fa-hourglass-half"></i>
                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
                <!-- Hızlı Aksiyonlar -->
                <div class="mb-5">
                    <h3 class="h5 mb-4" style="color: #4a5568; font-weight: 500;">
                        Hızlı Aksiyonlar
                    </h3>
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="quotes.php?status=pending" class="action-card warning">
                                <i class="fas fa-clock"></i>
                                <div class="action-title">Bekleyen Teklifler</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="transport-modes.php" class="action-card primary">
                                <i class="fas fa-edit"></i>
                                <div class="action-title">Şablonları Düzenle</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="settings.php" class="action-card info">
                                <i class="fas fa-cog"></i>
                                <div class="action-title">Sistem Ayarları</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="email-logs.php" class="action-card success">
                                <i class="fas fa-envelope-open"></i>
                                <div class="action-title">E-posta Logları</div>
                            </a>
                        </div>
                    </div>

                    <!-- İkinci Satır Aksiyonlar -->
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="customers.php" class="action-card info">
                                <i class="fas fa-users"></i>
                                <div class="action-title">Müşteri Yönetimi</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="cost-lists.php" class="action-card warning">
                                <i class="fas fa-list-alt"></i>
                                <div class="action-title">Maliyet Listeleri</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="email-templates.php" class="action-card primary">
                                <i class="fas fa-envelope"></i>
                                <div class="action-title">E-posta Şablonları</div>
                            </a>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="quotes.php" class="action-card success">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <div class="action-title">Tüm Teklifler</div>
                            </a>
                        </div>
                    </div>
                                 </div>

                 <!-- Onay Bekleyen Teklifler -->
                 <div class="row">
                     <div class="col-12">
                         <div class="card">
                             <div class="card-header d-flex justify-content-between align-items-center">
                                 <h5 class="mb-0" style="color: #4a5568; font-weight: 500;">
                                     <i class="fas fa-clock me-2" style="color: #ffc107;"></i>
                                     Onay Bekleyen Teklifler
                                 </h5>
                                 <a href="quotes.php?filter=pending_sent" class="btn btn-sm btn-outline-warning">
                                     <i class="fas fa-clock me-1"></i>
                                     Tümünü Gör
                                 </a>
                             </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead style="background: #f8f9fa;">
                                            <tr>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">Teklif No</th>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">Müşteri</th>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">Taşıma Türü</th>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">Fiyat</th>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">Tarih</th>
                                                <th style="border: none; padding: 12px 16px; font-weight: 500; color: #4a5568; font-size: 0.9rem;">İşlem</th>
                                            </tr>
                                        </thead>
                                                                                 <tbody>
                                             <?php if (empty($pending_quotes)): ?>
                                             <tr>
                                                 <td colspan="6" style="padding: 40px; text-align: center; color: #718096;">
                                                     <i class="fas fa-check-circle" style="font-size: 2rem; color: #48bb78; margin-bottom: 10px;"></i>
                                                     <div style="font-weight: 500; margin-bottom: 5px;">Onay Bekleyen Teklif Yok</div>
                                                     <div style="font-size: 0.9rem;">Tüm teklifler müşteri tarafından onaylandı!</div>
                                                 </td>
                                             </tr>
                                             <?php else: ?>
                                             <?php foreach ($pending_quotes as $quote): ?>
                                             <tr class="quote-row" style="cursor: pointer; transition: background-color 0.2s;"
                                                 onclick="window.location.href='../view-quote.php?id=<?= urlencode($quote['quote_number']) ?>'"
                                                 onmouseover="this.style.backgroundColor='#fff8e1'"
                                                 onmouseout="this.style.backgroundColor=''">
                                                 <td style="padding: 12px 16px; border-color: #e2e8f0;">
                                                     <span style="font-weight: 600; color: #2d3748;"><?= htmlspecialchars($quote['quote_number']) ?></span>
                                                     <?php if ($quote['status'] === 'pending'): ?>
                                                         <span class="badge ms-2" style="background: #fff3cd; color: #856404; font-size: 0.7rem;">Bekliyor</span>
                                                     <?php elseif ($quote['status'] === 'sent'): ?>
                                                         <span class="badge ms-2" style="background: #e1f5fe; color: #0277bd; font-size: 0.7rem;">Gönderildi</span>
                                                     <?php endif; ?>
                                                 </td>
                                                 <td style="padding: 12px 16px; border-color: #e2e8f0;">
                                                     <div style="color: #2d3748;"><?= htmlspecialchars($quote['first_name'] . ' ' . $quote['last_name']) ?></div>
                                                 </td>
                                                 <td style="padding: 12px 16px; border-color: #e2e8f0;">
                                                     <span class="badge" style="background: rgba(102, 126, 234, 0.1); color: #667eea; font-weight: 500;">
                                                         <?= htmlspecialchars($quote['transport_mode']) ?>
                                                     </span>
                                                 </td>
                                                                                                  <td style="padding: 12px 16px; border-color: #e2e8f0;">
                                                     <?php
                                                         // admin/quotes.php ile aynı mantığı kullan
                                                         $currency = $quote['template_currency'] ?? 'EUR';
                                                         $currency_symbol = $currency === 'TL' ? '₺' : ($currency === 'USD' ? '$' : '€');
                                                         $price = number_format($quote['final_price'], 0, ',', '.');

                                                         // Renk belirleme
                                                         $currency_color = '#ffc107'; // TL için sarı
                                                         if ($currency === 'USD') {
                                                             $currency_color = '#28a745'; // USD için yeşil
                                                         } elseif ($currency === 'EUR') {
                                                             $currency_color = '#007bff'; // EUR için mavi
                                                         }
                                                     ?>
                                                     <span style="font-weight: 600; color: <?= $currency_color ?>;">
                                                         <?= $currency_symbol ?><?= $price ?>
                                                     </span>
                                                 </td>
                                                 <td style="padding: 12px 16px; border-color: #e2e8f0; color: #718096;">
                                                     <?= formatDate($quote['created_at']) ?>
                                                 </td>
                                                 <td style="padding: 12px 16px; border-color: #e2e8f0;">
                                                     <a href="../view-quote.php?id=<?= urlencode($quote['quote_number']) ?>"
                                                        class="btn btn-sm btn-outline-warning"
                                                        onclick="event.stopPropagation()"
                                                        style="font-size: 0.8rem;"
                                                        target="_blank">
                                                         <i class="fas fa-external-link-alt me-1"></i>
                                                         Müşteri Görünümü
                                                     </a>
                                                 </td>
                                             </tr>
                                             <?php endforeach; ?>
                                             <?php endif; ?>
                                         </tbody>
                                    </table>
                                                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>

                 <!-- Aylık Teklif Grafiği -->
                 <div class="row mb-4 mt-5">
                     <div class="col-12">
                         <div class="card">
                             <div class="card-header">
                                 <h5 class="mb-0" style="color: #4a5568; font-weight: 500;">
                                     <i class="fas fa-chart-line me-2" style="color: #667eea;"></i>
                                     Aylık Teklif Grafiği
                                 </h5>
                             </div>
                             <div class="card-body">
                                 <canvas id="monthlyChart" height="80"></canvas>
                             </div>
                         </div>
                     </div>
                 </div>

             </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Aylık grafik
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_data, 'month')) ?>,
                datasets: [{
                    label: 'Teklif Sayısı',
                    data: <?= json_encode(array_column($monthly_data, 'count')) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

                // Güncelleme kontrol fonksiyonu
        function checkForUpdates() {
            const button = event.target;
            const originalText = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Kontrol ediliyor...';

            fetch('update-notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=check_updates'
            })
            .then(response => {
                // Önce response'un ok olup olmadığını kontrol et
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                // Content-Type kontrolü
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Sunucu JSON yanıtı vermedi');
                }

                return response.json();
            })
            .then(data => {
                button.disabled = false;
                button.innerHTML = originalText;

                if (data && data.success) {
                    if (data.data && data.data.update_available) {
                        // Sayfayı yenile ki güncelleme bildirimi gösterilsin
                        location.reload();
                    } else {
                        const version = data.data ? data.data.latest_version : 'bilinmiyor';
                        showTempAlert('success', 'Sisteminiz güncel! En son versiyon: ' + version);
                    }
                } else {
                    const errorMsg = data && data.error ? data.error : 'Bilinmeyen hata';
                    showTempAlert('warning', 'Kontrol edilemedi: ' + errorMsg);
                }
            })
            .catch(error => {
                button.disabled = false;
                button.innerHTML = originalText;

                console.error('Update check error:', error);
                showTempAlert('warning', 'Güncelleme kontrolü şu anda yapılamıyor. Lütfen daha sonra tekrar deneyin.');
            });
        }

        function showTempAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 2000; min-width: 300px;';
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }
    </script>
    <script src="includes/sidebar.js"></script>
</body>
</html>