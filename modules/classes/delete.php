<?php
// modules/classes/delete.php - Sınıf silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$classroom_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$classroom_id) {
    setAlert('Geçersiz sınıf ID!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıf bilgilerini getir
$query = "SELECT * FROM classrooms WHERE id = ?";
$classroom = safeQuery($query, [$classroom_id])->fetch();

if (!$classroom) {
    setAlert('Sınıf bulunamadı!', 'danger');
    redirect('modules/classes/index.php');
}

try {
    db()->beginTransaction();

    // Sınıfa atanmış öğrenci var mı kontrol et
    $check_students = "SELECT COUNT(*) as count FROM student_classrooms WHERE classroom_id = ? AND status = 'active'";
    $students_count = safeQuery($check_students, [$classroom_id])->fetch()['count'] ?? 0;

    if ($students_count > 0) {
        throw new Exception('Bu sınıfta halen ' . $students_count . ' aktif öğrenci bulunuyor. Önce öğrencileri başka sınıflara aktarın veya çıkarın.');
    }

    // Sınıfın ders programı var mı kontrol et
    $check_lessons = "SELECT COUNT(*) as count FROM lessons WHERE classroom_id = ? AND status = 'active'";
    $lessons_count = safeQuery($check_lessons, [$classroom_id])->fetch()['count'] ?? 0;

    if ($lessons_count > 0) {
        throw new Exception('Bu sınıfa ait ' . $lessons_count . ' aktif ders bulunuyor. Önce ders programını temizleyin.');
    }

    // Önce student_classrooms tablosundan ilişkili kayıtları pasif yap
    $update_relations = "UPDATE student_classrooms SET status = 'inactive' WHERE classroom_id = ?";
    safeQuery($update_relations, [$classroom_id]);

    // Sınıfı pasif yap (tamamen silmek yerine)
    $update_classroom = "UPDATE classrooms SET status = 'inactive' WHERE id = ?";
    safeQuery($update_classroom, [$classroom_id]);

    db()->commit();

    setAlert(htmlspecialchars($classroom['name']) . ' sınıfı başarıyla pasife alındı!', 'success');
    redirect('modules/classes/index.php');
} catch (Exception $e) {
    db()->rollBack();
    setAlert('Hata: ' . $e->getMessage(), 'danger');
    redirect('modules/classes/view.php?id=' . $classroom_id);
}
