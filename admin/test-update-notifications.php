<?php
/**
 * Güncelleme bildirimleri test scripti
 */

require_once '../config/database.php';
require_once '../includes/update-functions.php';
require_once 'update-notifications.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Güncelleme Bildirimleri Test</h2>";

try {
    $notifications = new UpdateNotifications();

    echo "<h3>1. Sistem Bilgileri</h3>";
    echo "Mevcut versiyon: " . getCurrentSystemVersion() . "<br>";
    echo "Sunucu URL: " . getSystemSetting('update_server_url', 'Varsayılan') . "<br>";
    echo "Proje ID: " . getSystemSetting('project_id', 'Varsayılan') . "<br>";

    echo "<h3>2. Güncelleme Kontrolü Test</h3>";
    $updateInfo = $notifications->checkForUpdates(true);
    echo "<pre>";
    print_r($updateInfo);
    echo "</pre>";

    echo "<h3>3. JSON Test</h3>";
    if (isset($_GET['json'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $updateInfo]);
        exit;
    } else {
        echo '<a href="?json=1">JSON formatında test et</a>';
    }

    echo "<h3>4. AJAX Test</h3>";
    echo '<button onclick="testAjax()">AJAX Test</button>';
    echo '<div id="ajaxResult"></div>';

    echo '<script>
    function testAjax() {
        fetch("update-notifications.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=check_updates"
        })
        .then(response => response.text())
        .then(text => {
            document.getElementById("ajaxResult").innerHTML = "<pre>" + text + "</pre>";
        })
        .catch(error => {
            document.getElementById("ajaxResult").innerHTML = "<pre>Error: " + error + "</pre>";
        });
    }
    </script>';

} catch (Exception $e) {
    echo "<div style='color: red;'>Hata: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
