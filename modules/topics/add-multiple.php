<?php
// modules/topics/add-multiple.php - Toplu konu ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// POST verisi kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setAlert('Geçersiz istek!', 'danger');
    redirect('modules/topics/index.php');
}

try {
    // Temel parametreleri al
    $topic_date = clean($_POST['date'] ?? '');
    $status = clean($_POST['status'] ?? 'planned');
    $useSameTopic = isset($_POST['useSameTopic']) && $_POST['useSameTopic'] == 1;

    // Tarih kontrolü
    if (empty($topic_date)) {
        throw new Exception('Tarih alanı zorunludur.');
    }

    // Veritabanı transaction'ı başlat
    db()->beginTransaction();

    $success_count = 0;

    // Aynı konu tüm dersler için kullanılacaksa
    if ($useSameTopic) {
        $topic_title = clean($_POST['common_topic_title'] ?? '');
        $description = clean($_POST['common_description'] ?? '');

        if (empty($topic_title)) {
            throw new Exception('Konu başlığı zorunludur.');
        }

        // O günkü tüm dersler için konu ekle
        $day_of_week = date('l', strtotime($topic_date));
        $lessons_query = "SELECT l.* FROM lessons l 
                         JOIN classrooms c ON l.classroom_id = c.id
                         WHERE l.day = ? AND l.period_id = ? AND l.status = 'active'
                         ORDER BY l.start_time";
        $lessons = safeQuery($lessons_query, [$day_of_week, $current_period['id']])->fetchAll();

        foreach ($lessons as $lesson) {
            $topic_data = [
                'lesson_id' => $lesson['id'],
                'topic_title' => $topic_title,
                'description' => $description,
                'date' => $topic_date,
                'status' => $status
            ];

            $sql = "INSERT INTO topics (lesson_id, topic_title, description, date, status) 
                    VALUES (:lesson_id, :topic_title, :description, :date, :status)";

            $result = safeQuery($sql, $topic_data);

            if ($result) {
                $success_count++;
            }
        }
    }
    // Her ders için farklı konu
    else {
        if (!isset($_POST['lessons']) || !is_array($_POST['lessons'])) {
            throw new Exception('Ders bilgileri bulunamadı.');
        }

        foreach ($_POST['lessons'] as $lesson_data) {
            $lesson_id = intval($lesson_data['lesson_id'] ?? 0);
            $topic_title = clean($lesson_data['topic_title'] ?? '');
            $description = clean($lesson_data['description'] ?? '');

            if (empty($lesson_id) || empty($topic_title)) {
                continue; // Eksik bilgi varsa bu dersi atla
            }

            $topic_data = [
                'lesson_id' => $lesson_id,
                'topic_title' => $topic_title,
                'description' => $description,
                'date' => $topic_date,
                'status' => $status
            ];

            $sql = "INSERT INTO topics (lesson_id, topic_title, description, date, status) 
                    VALUES (:lesson_id, :topic_title, :description, :date, :status)";

            $result = safeQuery($sql, $topic_data);

            if ($result) {
                $success_count++;
            }
        }
    }

    db()->commit();

    if ($success_count > 0) {
        setAlert($success_count . ' ders için konu başarıyla eklendi!', 'success');
    } else {
        setAlert('Hiçbir konu eklenemedi!', 'warning');
    }

    redirect('modules/topics/index.php?date=' . $topic_date);
} catch (Exception $e) {
    // Hata durumunda transaction'ı geri al
    db()->rollBack();
    setAlert('Hata: ' . $e->getMessage(), 'danger');
    redirect('modules/topics/add.php?date=' . ($topic_date ?? date('Y-m-d')));
}
