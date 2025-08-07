<?php
session_start();

// Oturumu temizle
session_unset();
session_destroy();

// Giriş sayfasına yönlendir
header('Location: login.php?logout=1');
exit;
?>