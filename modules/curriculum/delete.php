<?php
// modules/curriculum/delete.php - Müfredat silme işlemi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$curriculum_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$curriculum_id) {
    setAlert('Geçersiz müfredat ID!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Müfredat bilgilerini getir
$curriculum_query = "SELECT * FROM curriculum WHERE id = ?";
$curriculum = safeQuery($curriculum_query, [$curriculum_id])->fetch();

if (!$curriculum) {
    setAlert('Müfredat bulunamadı!', 'danger');
    redirect('modules/curriculum/index.php');
}

try {
    db()->beginTransaction();

    // Sınıf ataması kontrolü
    $class_check = "SELECT COUNT(*) as count FROM classroom_curriculum WHERE curriculum_id = ?";
    $class_count = safeQuery($class_check, [$curriculum_id])->fetch()['count'];

    if ($class_count > 0) {
        // Sınıf atamalarını sil
        $delete_assignments = "DELETE FROM classroom_curriculum WHERE curriculum_id = ?";
        safeQuery($delete_assignments, [$curriculum_id]);
    }

    // Öğrenci ilerleme kayıtları kontrolü
    $progress_check = "SELECT COUNT(*) as count FROM student_curriculum_progress WHERE curriculum_id = ?";
    $progress_count = safeQuery($progress_check, [$curriculum_id])->fetch()['count'];

    if ($progress_count > 0) {
        // Öğrenci ilerleme kayıtlarını sil
        $delete_progress = "DELETE FROM student_curriculum_progress WHERE curriculum_id = ?";
        safeQuery($delete_progress, [$curriculum_id]);
    }

    // İlişkili topics kayıtlarındaki referansları temizle
    $update_topics = "UPDATE topics SET curriculum_weekly_topic_id = NULL WHERE curriculum_weekly_topic_id IN 
                     (SELECT id FROM curriculum_weekly_topics WHERE curriculum_id = ?)";
    safeQuery($update_topics, [$curriculum_id]);

    // Haftalık konuları sil
    $delete_topics = "DELETE FROM curriculum_weekly_topics WHERE curriculum_id = ?";
    safeQuery($delete_topics, [$curriculum_id]);

    // Müfredatı sil
    $delete_curriculum = "DELETE FROM curriculum WHERE id = ?";
    safeQuery($delete_curriculum, [$curriculum_id]);

    db()->commit();

    setAlert('Müfredat ve ilişkili tüm kayıtlar başarıyla silindi!', 'success');
} catch (Exception $e) {
    db()->rollBack();
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

redirect('modules/curriculum/index.php');
