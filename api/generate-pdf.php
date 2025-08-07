<?php
// Hosting sunucusu için optimize edilmiş PDF API
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once "../config/database.php";
require_once "../includes/functions.php";

// Teklif ID kontrolü
$quote_id = $_GET["id"] ?? "";

if (empty($quote_id)) {
    die("Teklif numarası belirtilmemiş.");
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Teklifi kontrol et
    $stmt = $db->prepare("
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_name, tm.icon as transport_icon, tm.template,
               qt.services_content, qt.terms_content, qt.currency, qt.language
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        JOIN transport_modes tm ON q.transport_mode_id = tm.id
        LEFT JOIN quote_templates qt ON q.selected_template_id = qt.id
        WHERE q.quote_number = ? AND q.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        die("Teklif bulunamadı: " . htmlspecialchars($quote_id));
    }

    // Hosting sunucusu için URL yapısını düzelt
    $redirect_url = "../view-quote-pdf.php?id=" . urlencode($quote_id);

    // Debug için
    if (isset($_GET["debug"])) {
        echo "Quote ID: " . htmlspecialchars($quote_id) . "<br>";
        echo "Redirect URL: " . htmlspecialchars($redirect_url) . "<br>";
        echo "Quote found: " . ($quote ? "Yes" : "No") . "<br>";
        exit;
    }

    // PDF sayfasına yönlendir
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    die("PDF oluşturulurken hata: " . $e->getMessage());
}
?>