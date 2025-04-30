<?php
// modules/makeup/delete.php - Telafi dersi silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Telafi dersi ID kontrolü
$makeup_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$makeup_id) {
    setAlert('Geçersiz telafi dersi ID!', 'danger');
    redirect('modules/makeup/index.php');
}

try {
    // Telafi dersini getir
    $makeup_query = "SELECT * FROM makeup_lessons WHERE id = ?";
    $makeup = safeQuery($makeup_query, [$makeup_id])->fetch();

    if (!$makeup) {
        setAlert('Telafi dersi bulunamadı!', 'danger');
        redirect('modules/makeup/index.php');
    }

    $student_id = $makeup['student_id'];

    // İlişkili yoklama kaydını sil
    if ($makeup['status'] === 'completed' && $makeup['makeup_date']) {
        $delete_attendance = "DELETE FROM attendance 
                             WHERE student_id = ? AND lesson_id = ? AND attendance_date = ? 
                             AND notes LIKE '%Telafi dersi%'";
        safeQuery($delete_attendance, [$makeup['student_id'], $makeup['original_lesson_id'], $makeup['makeup_date']]);
    }

    // Telafi dersini sil
    $delete_sql = "DELETE FROM makeup_lessons WHERE id = ?";
    safeQuery($delete_sql, [$makeup_id]);

    setAlert('Telafi dersi başarıyla silindi!', 'success');

    // Eğer öğrenci ID'si varsa öğrenci sayfasına yönlendir, yoksa ana listeye
    if ($student_id) {
        redirect('modules/makeup/student.php?id=' . $student_id);
    } else {
        redirect('modules/makeup/index.php');
    }
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
    redirect('modules/makeup/index.php');
}
