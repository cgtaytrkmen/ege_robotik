<?php
// modules/finance/delete-expense.php - Gider silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$expense_id = $_GET['id'] ?? 0;
if (!$expense_id) {
    setAlert('Geçersiz gider ID!', 'danger');
    redirect('modules/finance/expenses.php');
}

try {
    // Gider var mı kontrol et
    $check_query = "SELECT id FROM expenses WHERE id = ?";
    $stmt = safeQuery($check_query, [$expense_id]);

    if ($stmt->rowCount() == 0) {
        throw new Exception('Gider kaydı bulunamadı!');
    }

    // Gideri sil
    $delete_query = "DELETE FROM expenses WHERE id = ?";
    safeQuery($delete_query, [$expense_id]);

    setAlert('Gider kaydı başarıyla silindi!', 'success');
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

redirect('modules/finance/expenses.php');
