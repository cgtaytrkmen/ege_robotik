<?php
// modules/curriculum/delete_assignment.php - Sınıf-müfredat atama silme işlemi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$assignment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$assignment_id) {
    setAlert('Geçersiz atama ID!', 'danger');
    redirect('modules/curriculum/assign.php');
}

// Atama bilgilerini getir
$assignment_query = "SELECT cc.*, c.name as classroom_name, cu.name as curriculum_name 
                    FROM classroom_curriculum cc
                    JOIN classrooms c ON cc.classroom_id = c.id
                    JOIN curriculum cu ON cc.curriculum_id = cu.id
                    WHERE cc.id = ?";
$assignment = safeQuery($assignment_query, [$assignment_id])->fetch();

if (!$assignment) {
    setAlert('Atama bulunamadı!', 'danger');
    redirect('modules/curriculum/assign.php');
}

try {
    // Bu atamaya bağlı öğrencilerin ilerleme durumları silinsin mi?
    $delete_progress = isset($_GET['delete_progress']) ? (bool)$_GET['delete_progress'] : false;

    db()->beginTransaction();

    // Öğrenci ilerleme kayıtlarını kontrol et
    $progress_query = "SELECT COUNT(*) as count 
                      FROM student_curriculum_progress sp
                      JOIN student_classrooms sc ON sp.student_id = sc.student_id
                      WHERE sp.curriculum_id = ? AND sc.classroom_id = ? AND sc.status = 'active'";
    $progress_count = safeQuery($progress_query, [$assignment['curriculum_id'], $assignment['classroom_id']])->fetch()['count'];

    // Öğrenci ilerleme kayıtlarını sil (eğer isteniyorsa)
    if ($delete_progress && $progress_count > 0) {
        $delete_progress_query = "DELETE sp FROM student_curriculum_progress sp
                                 JOIN student_classrooms sc ON sp.student_id = sc.student_id
                                 WHERE sp.curriculum_id = ? AND sc.classroom_id = ? AND sc.status = 'active'";
        safeQuery($delete_progress_query, [$assignment['curriculum_id'], $assignment['classroom_id']]);
    }

    // Atamayı sil
    $delete_query = "DELETE FROM classroom_curriculum WHERE id = ?";
    safeQuery($delete_query, [$assignment_id]);

    db()->commit();

    if ($delete_progress && $progress_count > 0) {
        setAlert("Atama ve {$progress_count} öğrencinin ilerleme kayıtları başarıyla silindi!", 'success');
    } else {
        setAlert('Atama başarıyla silindi!', 'success');
    }
} catch (Exception $e) {
    db()->rollBack();
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

redirect('modules/curriculum/assign.php');
