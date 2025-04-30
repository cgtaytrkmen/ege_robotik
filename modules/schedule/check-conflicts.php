<?php
// modules/schedule/check-conflicts.php - Ders çakışma kontrolü API
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Parametreleri al
$classroom_id = isset($_GET['classroom_id']) ? intval($_GET['classroom_id']) : 0;
$day = isset($_GET['day']) ? clean($_GET['day']) : '';
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;

// Verileri kontrol et
if (!$classroom_id || !$day) {
    echo json_encode(['error' => 'Geçersiz parametreler', 'hasConflict' => false]);
    exit;
}

// Seçilen gün ve sınıf için dersleri getir (verilen ders hariç)
$query = "SELECT l.*, c.name as classroom_name 
          FROM lessons l 
          JOIN classrooms c ON l.classroom_id = c.id 
          WHERE l.classroom_id = ? AND l.day = ? AND l.period_id = ? AND l.status = 'active'";

$params = [$classroom_id, $day, $current_period['id']];

// Eğer ders ID'si verilmişse, bu dersi hariç tut
if ($lesson_id) {
    $query .= " AND l.id != ?";
    $params[] = $lesson_id;
}

$lessons = safeQuery($query, $params)->fetchAll();

// JSON yanıtı oluştur
$response = [
    'hasConflict' => count($lessons) > 0,
    'lessons' => []
];

// Ders bilgilerini ekle
foreach ($lessons as $lesson) {
    $response['lessons'][] = [
        'id' => $lesson['id'],
        'classroom' => $lesson['classroom_name'],
        'start_time' => substr($lesson['start_time'], 0, 5),
        'end_time' => substr($lesson['end_time'], 0, 5)
    ];
}

// JSON çıktı
header('Content-Type: application/json');
echo json_encode($response);
