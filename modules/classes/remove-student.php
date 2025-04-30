<?php
// modules/classes/remove-student.php - Sınıftan öğrenci çıkarma
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Parametreleri kontrol et
$classroom_id = isset($_GET['classroom_id']) ? intval($_GET['classroom_id']) : 0;
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (!$classroom_id || !$student_id) {
    setAlert('Geçersiz sınıf veya öğrenci ID!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıf bilgilerini getir
$classroom_query = "SELECT * FROM classrooms WHERE id = ?";
$classroom = safeQuery($classroom_query, [$classroom_id])->fetch();

if (!$classroom) {
    setAlert('Sınıf bulunamadı!', 'danger');
    redirect('modules/classes/index.php');
}

// Öğrenci bilgilerini getir
$student_query = "SELECT first_name, last_name FROM students WHERE id = ?";
$student = safeQuery($student_query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/classes/view.php?id=' . $classroom_id);
}

try {
    // student_classrooms tablosunun varlığını kontrol et
    $table_check = db()->query("SHOW TABLES LIKE 'student_classrooms'")->fetch();

    if (!$table_check) {
        throw new Exception('Öğrenci-sınıf ilişki tablosu bulunamadı!');
    }

    // İlişki kaydını kontrol et
    $check_query = "SELECT id FROM student_classrooms WHERE student_id = ? AND classroom_id = ? AND status = 'active'";
    $relation = safeQuery($check_query, [$student_id, $classroom_id])->fetch();

    if (!$relation) {
        throw new Exception('Bu öğrenci bu sınıfta bulunmuyor!');
    }

    // Öğrencinin sınıf kaydını pasif yap
    $update_query = "UPDATE student_classrooms SET status = 'inactive' WHERE student_id = ? AND classroom_id = ?";
    safeQuery($update_query, [$student_id, $classroom_id]);

    setAlert(htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . ' sınıftan başarıyla çıkarıldı!', 'success');
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
}

// Geri dön
redirect('modules/classes/view.php?id=' . $classroom_id);
