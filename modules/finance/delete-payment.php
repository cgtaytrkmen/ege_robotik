<?php
// modules/finance/delete-payment.php - Ödeme silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$payment_id = $_GET['id'] ?? 0;
if (!$payment_id) {
    setAlert('Geçersiz ödeme ID!', 'danger');
    redirect('modules/finance/payments.php');
}

try {
    // Ödeme var mı kontrol et
    $check_query = "SELECT id FROM payments WHERE id = ?";
    $stmt = safeQuery($check_query, [$payment_id]);

    if ($stmt->rowCount() == 0) {
        throw new Exception('Ödeme kaydı bulunamadı!');
    }

    // Ödemeyi sil
    $delete_query = "DELETE FROM payments WHERE id = ?";
    safeQuery($delete_query, [$payment_id]);

    setAlert('Ödeme kaydı başarıyla silindi!', 'success');
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

redirect('modules/finance/payments.php');
