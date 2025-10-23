<?php
/**
 * Geçerlilik Tarihi Güncelleme Test
 * 
 * Belirli bir teklif için valid_until değerini kontrol eder
 */

require_once 'config/database.php';

if (!isset($_GET['quote_id'])) {
    die('Kullanım: test-validity-update.php?quote_id=25-1112-ithalat');
}

$quote_id = $_GET['quote_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Teklif: {$quote_id}</h2>";
    echo str_repeat("=", 60) . "<br>";
    
    // Teklif bilgilerini al
    $stmt = $db->prepare("
        SELECT quote_number, created_at, valid_until, updated_at 
        FROM quotes 
        WHERE quote_number = ?
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quote) {
        die("Teklif bulunamadı!");
    }
    
    echo "<strong>Teklif Numarası:</strong> {$quote['quote_number']}<br>";
    echo "<strong>Oluşturma Tarihi:</strong> {$quote['created_at']}<br>";
    echo "<strong>Geçerlilik Tarihi (valid_until):</strong> {$quote['valid_until']}<br>";
    echo "<strong>Son Güncelleme:</strong> {$quote['updated_at']}<br><br>";
    
    // Tarih farkını hesapla
    $created = new DateTime($quote['created_at']);
    $valid = new DateTime($quote['valid_until']);
    $diff = $created->diff($valid);
    
    echo "<strong>Geçerlilik Süresi:</strong> {$diff->days} gün<br><br>";
    
    echo str_repeat("=", 60) . "<br>";
    
    if ($diff->days == 15) {
        echo "<span style='color: green;'>✓ Geçerlilik süresi 15 gün (DOĞRU)</span><br>";
    } elseif ($diff->days == 30) {
        echo "<span style='color: orange;'>⚠ Geçerlilik süresi 30 gün (ESKİ DEĞER)</span><br>";
    } else {
        echo "<span style='color: blue;'>ℹ Geçerlilik süresi {$diff->days} gün (MANUEL AYARLANMIŞ)</span><br>";
    }
    
    echo "<br><br>";
    echo "<h3>Test Güncelleme</h3>";
    echo "Geçerlilik tarihini test etmek için admin panelden tarihi değiştirin, sonra bu sayfayı yenileyin.<br><br>";
    
    echo "<a href='view-quote.php?id={$quote_id}' target='_blank' style='padding: 8px 16px; background: #2c5aa0; color: white; text-decoration: none; border-radius: 4px;'>Müşteri Görünümü</a> ";
    echo "<a href='admin/view-quote.php?id={$quote_id}' target='_blank' style='padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Admin Paneli</a> ";
    echo "<a href='view-quote-pdf.php?id={$quote_id}' target='_blank' style='padding: 8px 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;'>PDF Görünümü</a>";
    
} catch (Exception $e) {
    echo "HATA: " . $e->getMessage();
}

