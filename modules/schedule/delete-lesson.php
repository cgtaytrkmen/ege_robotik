<?php
// modules/schedule/delete-lesson.php - Ders silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$lesson_id) {
    setAlert('Geçersiz ders ID!', 'danger');
    redirect('modules/schedule/index.php');
}

// Ders bilgilerini getir
$lesson_query = "SELECT l.*, c.name as classroom_name 
                FROM lessons l 
                JOIN classrooms c ON l.classroom_id = c.id 
                WHERE l.id = ? AND l.period_id = ?";
$lesson = safeQuery($lesson_query, [$lesson_id, $current_period['id']])->fetch();

if (!$lesson) {
    setAlert('Ders bulunamadı!', 'danger');
    redirect('modules/schedule/index.php');
}

try {
    // İlişkili yoklama kayıtlarını kontrol et
    $attendance_check = "SELECT COUNT(*) as count FROM attendance WHERE lesson_id = ?";
    $attendance_count = safeQuery($attendance_check, [$lesson_id])->fetch()['count'];

    if ($attendance_count > 0) {
        // Tamamen silme yerine pasif yap
        $update_query = "UPDATE lessons SET status = 'cancelled' WHERE id = ?";
        safeQuery($update_query, [$lesson_id]);

        setAlert('Ders iptal edildi! (İlişkili yoklama kayıtları olduğu için tamamen kaldırılmadı)', 'warning');
    } else {
        // Hiç yoklama kaydı yoksa tamamen sil
        $delete_query = "DELETE FROM lessons WHERE id = ?";
        safeQuery($delete_query, [$lesson_id]);

        setAlert(htmlspecialchars($lesson['classroom_name']) . ' sınıfı için ' .
            htmlspecialchars($lesson['day']) . ' günü dersi başarıyla silindi!', 'success');
    }
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

redirect('modules/schedule/index.php');
