<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Mevcut teklifleri listele
$stmt = $db->prepare("SELECT id, quote_number, status, created_at, trade_type FROM quotes ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$quotes = $stmt->fetchAll();

// Test için generateQuoteNumber fonksiyonunu çağır
function generateQuoteNumber($db = null, $tradeType = 'ithalat', $isCopy = false) {
    global $database;

    if (!$db) {
        $database = new Database();
        $db = $database->getConnection();
    }

    // Trade type'ı Türkçe'ye çevir
    $tradeTypeSuffix = '';
    switch(strtolower($tradeType)) {
        case 'ithalat':
        case 'import':
            $tradeTypeSuffix = 'ithalat';
            break;
        case 'ihracat':
        case 'export':
            $tradeTypeSuffix = 'ihracat';
            break;
        default:
            $tradeTypeSuffix = 'ithalat';
    }

    // Bu yıl için son teklif numarasını al (aynı trade type için)
    // Rev'li ve normal teklifleri ayrı ayrı kontrol et
    $year = date('y'); // Son iki hane (25)

    // Önce normal teklifleri kontrol et (rev olmayan)
    $stmt = $db->prepare("
        SELECT quote_number
        FROM quotes
        WHERE quote_number LIKE ? AND quote_number NOT LIKE '%rev%'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$year . '%-' . $tradeTypeSuffix]);
    $last_normal_quote = $stmt->fetch();

    // Sonra rev'li teklifleri kontrol et
    $stmt = $db->prepare("
        SELECT quote_number
        FROM quotes
        WHERE quote_number LIKE ? AND quote_number LIKE '%rev%'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$year . '%-' . $tradeTypeSuffix]);
    $last_rev_quote = $stmt->fetch();

    $debug_info = [
        'trade_type_suffix' => $tradeTypeSuffix,
        'is_copy' => $isCopy,
        'search_pattern' => $year . '%-' . $tradeTypeSuffix,
        'last_normal_quote' => $last_normal_quote ? $last_normal_quote['quote_number'] : 'none',
        'last_rev_quote' => $last_rev_quote ? $last_rev_quote['quote_number'] : 'none'
    ];

    // En yüksek numarayı bul
    $last_number = 0;

    if ($last_normal_quote) {
        $parts = explode('-', $last_normal_quote['quote_number']);
        if (count($parts) >= 2) {
            $last_number = max($last_number, intval($parts[1]));
        }
    }

    if ($last_rev_quote) {
        $parts = explode('-', $last_rev_quote['quote_number']);
        if (count($parts) >= 2) {
            $last_number = max($last_number, intval($parts[1]));
        }
    }

    if ($last_number > 0) {
        $new_number = $last_number + 1;
    } else {
        $new_number = 1111; // İlk teklif numarası
    }

    $debug_info['calculated_new_number'] = $new_number;

    $base_number = $year . '-' . str_pad($new_number, 4, '0', STR_PAD_LEFT) . '-' . $tradeTypeSuffix;

    // Eğer kopyalama işlemiyse "rev" ekle
    if ($isCopy) {
        $final_number = $base_number . '-rev';
        $debug_info['final_quote_number'] = $final_number;
        return ['number' => $final_number, 'debug' => $debug_info];
    }

    $debug_info['final_quote_number'] = $base_number;
    return ['number' => $base_number, 'debug' => $debug_info];
}

// Test generateQuoteNumber
$test_result = generateQuoteNumber($db, 'ithalat', true);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Quotes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: monospace; }
        .debug-box { background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        .error-box { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .success-box { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Quote System Debug</h1>

        <div class="debug-box">
            <h3>Current Quotes in Database:</h3>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Quote Number</th>
                        <th>Status</th>
                        <th>Trade Type</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><?= $quote['id'] ?></td>
                        <td><strong><?= htmlspecialchars($quote['quote_number']) ?></strong></td>
                        <td><?= $quote['status'] ?></td>
                        <td><?= $quote['trade_type'] ?></td>
                        <td><?= $quote['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="success-box">
            <h3>generateQuoteNumber Test (İthalat Kopyası):</h3>
            <p><strong>Generated Number:</strong> <?= htmlspecialchars($test_result['number']) ?></p>

            <h4>Debug Information:</h4>
            <ul>
                <li><strong>Trade Type Suffix:</strong> <?= $test_result['debug']['trade_type_suffix'] ?></li>
                <li><strong>Is Copy:</strong> <?= $test_result['debug']['is_copy'] ? 'Yes' : 'No' ?></li>
                <li><strong>Search Pattern:</strong> <?= $test_result['debug']['search_pattern'] ?></li>
                <li><strong>Last Normal Quote:</strong> <?= $test_result['debug']['last_normal_quote'] ?></li>
                <li><strong>Last Rev Quote:</strong> <?= $test_result['debug']['last_rev_quote'] ?></li>
                <li><strong>Calculated New Number:</strong> <?= $test_result['debug']['calculated_new_number'] ?></li>
                <li><strong>Final Quote Number:</strong> <?= $test_result['debug']['final_quote_number'] ?></li>
            </ul>
        </div>

        <div class="debug-box">
            <h3>Manual Copy Test:</h3>
            <p>Bu butona basarak manuel olarak kopyalama test edebilirsiniz:</p>

            <?php if (!empty($quotes)): ?>
                <?php $first_quote = $quotes[0]; ?>
                <form method="POST" action="quotes.php">
                    <input type="hidden" name="action" value="copy_quote">
                    <input type="hidden" name="quote_id" value="<?= $first_quote['id'] ?>">
                    <button type="submit" class="btn btn-primary">
                        Test Copy Quote #<?= $first_quote['id'] ?> (<?= htmlspecialchars($first_quote['quote_number']) ?>)
                    </button>
                </form>
            <?php else: ?>
                <p class="text-danger">No quotes found to copy.</p>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <a href="quotes.php" class="btn btn-secondary">← Back to Quotes</a>
        </div>
    </div>
</body>
</html>
