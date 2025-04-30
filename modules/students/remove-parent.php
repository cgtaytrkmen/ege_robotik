<?php
// modules/students/remove-parent.php - Öğrenciden veli kaldırma
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;

if (!$student_id || !$parent_id) {
    setAlert('Geçersiz parametreler!', 'danger');
    redirect('modules/students/index.php');
}

try {
    // İlişkiyi kontrol et
    $check_query = "SELECT * FROM student_parents WHERE student_id = ? AND parent_id = ?";
    $relation = safeQuery($check_query, [$student_id, $parent_id])->fetch();

    if (!$relation) {
        setAlert('Bu veli öğrenci ile ilişkili değil!', 'danger');
        redirect('modules/students/view.php?id=' . $student_id);
    }

    // Başka öğrencisi var mı kontrol et
    $count_query = "SELECT COUNT(*) as count FROM student_parents WHERE student_id = ?";
    $parent_count = safeQuery($count_query, [$student_id])->fetch()['count'];

    if ($parent_count <= 1) {
        setAlert('Öğrencinin en az bir velisi olmalıdır!', 'danger');
        redirect('modules/students/view.php?id=' . $student_id);
    }

    // İlişkiyi sil
    $delete_query = "DELETE FROM student_parents WHERE student_id = ? AND parent_id = ?";
    safeQuery($delete_query, [$student_id, $parent_id]);

    // Eğer silinen veli ana veli ise başka bir veliyi ana veli yap
    if ($relation['is_primary']) {
        $update_query = "UPDATE student_parents SET is_primary = 1 
                        WHERE student_id = ? 
                        LIMIT 1";
        safeQuery($update_query, [$student_id]);
    }

    setAlert('Veli başarıyla kaldırıldı!', 'success');
    redirect('modules/students/view.php?id=' . $student_id);
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
    redirect('modules/students/view.php?id=' . $student_id);
}
