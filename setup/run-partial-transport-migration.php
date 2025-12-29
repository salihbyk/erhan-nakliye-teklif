<?php
// Partial transport migration runner
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h2>Parsiyel Taşıma Kolonu Ekleme Migration</h2>";

    // Check if column already exists
    $stmt = $db->prepare("SHOW COLUMNS FROM quotes LIKE 'partial_transport'");
    $stmt->execute();
    $columnExists = $stmt->fetch();

    if ($columnExists) {
        echo "<p style='color: orange;'>✓ partial_transport kolonu zaten mevcut.</p>";
    } else {
        // Add partial_transport column
        $db->exec("ALTER TABLE quotes ADD COLUMN partial_transport TINYINT(1) DEFAULT 0 AFTER trade_type");
        echo "<p style='color: green;'>✓ partial_transport kolonu başarıyla eklendi.</p>";

        // Add index
        $db->exec("ALTER TABLE quotes ADD INDEX idx_partial_transport (partial_transport)");
        echo "<p style='color: green;'>✓ Index başarıyla eklendi.</p>";
    }

    echo "<p style='color: blue;'>Migration başarıyla tamamlandı!</p>";
    echo "<p><a href='../admin/index.php'>Admin Panele Dön</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Detay: " . $e->getTraceAsString() . "</p>";
}
?>
