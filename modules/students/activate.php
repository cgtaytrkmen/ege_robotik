<?php
// modules/students/activate.php - Öğrenci aktifleştirme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/students/index.php');
}

// Öğrenci bilgisini getir
$query = "SELECT s.*, sp.status as period_status
          FROM students s
          LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
          WHERE s.id = ?";
$student = safeQuery($query, [$current_period['id'], $student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Eğer öğrenci zaten aktifse yönlendir
if ($student['period_status'] === 'active') {
    setAlert('Öğrenci zaten aktif durumda!', 'info');
    redirect('modules/students/view.php?id=' . $student_id);
}

try {
    // Öğrenciyi aktifleştir
    $update_data = [
        'status' => 'active',
        'student_id' => $student_id,
        'period_id' => $current_period['id']
    ];

    // Önce öğrencinin mevcut dönemde kaydı var mı kontrol et
    $check_query = "SELECT id FROM student_periods WHERE student_id = ? AND period_id = ?";
    $check_result = safeQuery($check_query, [$student_id, $current_period['id']])->fetch();

    if ($check_result) {
        // Mevcut dönem kaydını güncelle
        $update_sql = "UPDATE student_periods SET status = :status 
                      WHERE student_id = :student_id AND period_id = :period_id";
        safeQuery($update_sql, $update_data);
    } else {
        // Yeni dönem kaydı oluştur
        $insert_data = [
            'student_id' => $student_id,
            'period_id' => $current_period['id'],
            'enrollment_date' => date('Y-m-d'),
            'status' => 'active'
        ];

        $insert_sql = "INSERT INTO student_periods (student_id, period_id, enrollment_date, status) 
                      VALUES (:student_id, :period_id, :enrollment_date, :status)";
        safeQuery($insert_sql, $insert_data);
    }

    // Öğrencinin genel durumunu da aktif yap
    $update_student_sql = "UPDATE students SET status = 'active' WHERE id = ?";
    safeQuery($update_student_sql, [$student_id]);

    // İlk aylık ödemeyi oluştur
    $first_payment_date = date('Y-m-') . sprintf('%02d', $student['payment_day']);
    if (strtotime($first_payment_date) < time()) {
        // Eğer bu ayın ödeme günü geçmişse, gelecek ayı ayarla
        $first_payment_date = date('Y-m-d', strtotime($first_payment_date . ' +1 month'));
    }

    $payment_data = [
        'student_id' => $student_id,
        'period_id' => $current_period['id'],
        'amount' => $student['net_fee'],
        'due_date' => $first_payment_date,
        'status' => 'pending',
        'payment_type' => 'monthly'
    ];

    $payment_sql = "INSERT INTO payments (student_id, period_id, amount, due_date, status, payment_type) 
                   VALUES (:student_id, :period_id, :amount, :due_date, :status, :payment_type)";
    safeQuery($payment_sql, $payment_data);

    setAlert('Öğrenci başarıyla aktifleştirildi ve ilk ödeme kaydı oluşturuldu!', 'success');
    redirect('modules/students/view.php?id=' . $student_id);
} catch (Exception $e) {
    setAlert('Hata: ' . $e->getMessage(), 'danger');
    redirect('modules/students/index.php');
}
