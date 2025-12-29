<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

// transport_modes tablosunun yapısını kontrol et
$stmt = $pdo->query('DESCRIBE transport_modes');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "transport_modes Tablo Kolonları:\n";
foreach ($columns as $column) {
    echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
}

// Örnek bir kayıt al
echo "\nÖrnek Kayıt:\n";
$stmt = $pdo->query('SELECT * FROM transport_modes LIMIT 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
