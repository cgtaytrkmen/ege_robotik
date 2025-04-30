<?php
// change-period.php - Dönem değiştirme
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$period_id = $_GET['id'] ?? 0;

if ($period_id > 0) {
    changePeriod($period_id);
    setAlert('Dönem başarıyla değiştirildi!', 'success');
}

// Önceki sayfaya dön
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . $referer);
exit();
