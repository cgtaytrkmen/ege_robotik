<?php
// modules/attendance/edit.php - Yoklama düzenleme sayfası (Konu düzenleme özelliği ile)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Ders ve tarih kontrolü
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$attendance_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (!$lesson_id) {
    setAlert('Geçersiz ders ID!', 'danger');
    redirect('modules/attendance/index.php');
}

// Ders bilgisini getir
$lesson_query = "SELECT l.*, c.name as classroom_name, c.id as classroom_id
                FROM lessons l
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE l.id = ? AND l.period_id = ?";
$lesson = safeQuery($lesson_query, [$lesson_id, $current_period['id']])->fetch();

if (!$lesson) {
    setAlert('Ders bulunamadı!', 'danger');
    redirect('modules/attendance/index.php');
}

// Bu derse kayıtlı öğrencileri getir
$students_query = "SELECT s.*, sc.status as class_status
                  FROM students s
                  JOIN student_classrooms sc ON s.id = sc.student_id
                  JOIN student_periods sp ON s.id = sp.student_id
                  WHERE sc.classroom_id = ? 
                  AND sp.period_id = ?
                  AND sc.status = 'active'
                  AND sp.status = 'active'
                  ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$lesson['classroom_id'], $current_period['id']])->fetchAll();

// Yoklama bilgilerini getir
$attendance_query = "SELECT * FROM attendance WHERE lesson_id = ? AND attendance_date = ?";
$attendance_records = safeQuery($attendance_query, [$lesson_id, $attendance_date])->fetchAll();

// Yoklama bilgilerini öğrenci ID'sine göre düzenle
$student_attendance = [];
foreach ($attendance_records as $record) {
    $student_attendance[$record['student_id']] = $record;
}

// İşlenen konu bilgisini getir
$topic_query = "SELECT * FROM topics WHERE lesson_id = ? AND date = ? LIMIT 1";
$topic = safeQuery($topic_query, [$lesson_id, $attendance_date])->fetch();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();

        // Öğrenci sayısı
        $student_count = count($students);
        $success_count = 0;

        // Her öğrenci için yoklama kaydını güncelle
        foreach ($students as $student) {
            $status = $_POST['status_' . $student['id']] ?? 'absent';
            $notes = $_POST['notes_' . $student['id']] ?? null;

            if (isset($student_attendance[$student['id']])) {
                // Mevcut kaydı güncelle
                $update_data = [
                    'status' => $status,
                    'notes' => $notes,
                    'id' => $student_attendance[$student['id']]['id']
                ];

                $sql = "UPDATE attendance SET status = :status, notes = :notes WHERE id = :id";
                $result = safeQuery($sql, $update_data);
            } else {
                // Yeni kayıt oluştur
                $insert_data = [
                    'student_id' => $student['id'],
                    'lesson_id' => $lesson_id,
                    'attendance_date' => $attendance_date,
                    'status' => $status,
                    'notes' => $notes
                ];

                $sql = "INSERT INTO attendance (student_id, lesson_id, attendance_date, status, notes) 
                        VALUES (:student_id, :lesson_id, :attendance_date, :status, :notes)";
                $result = safeQuery($sql, $insert_data);
            }

            if ($result) {
                $success_count++;
            }
        }

        // İşlenen konu bilgisini güncelle
        $topic_title = clean($_POST['topic_title'] ?? '');
        $topic_description = clean($_POST['topic_description'] ?? '');
        $topic_status = clean($_POST['topic_status'] ?? 'completed');

        if (!empty($topic_title)) {
            if ($topic) {
                // Mevcut kaydı güncelle
                $topic_data = [
                    'topic_title' => $topic_title,
                    'description' => $topic_description,
                    'status' => $topic_status,
                    'id' => $topic['id']
                ];

                $sql = "UPDATE topics SET topic_title = :topic_title, description = :description, status = :status WHERE id = :id";
                safeQuery($sql, $topic_data);
            } else {
                // Yeni kayıt oluştur
                $topic_data = [
                    'lesson_id' => $lesson_id,
                    'topic_title' => $topic_title,
                    'description' => $topic_description,
                    'date' => $attendance_date,
                    'status' => $topic_status
                ];

                $sql = "INSERT INTO topics (lesson_id, topic_title, description, date, status) 
                        VALUES (:lesson_id, :topic_title, :description, :date, :status)";
                safeQuery($sql, $topic_data);
            }
        }

        db()->commit();

        if ($success_count == $student_count) {
            setAlert('Yoklama ve konu bilgisi başarıyla güncellendi!', 'success');
        } else {
            setAlert($success_count . ' öğrenci için yoklama güncellendi! ' . ($student_count - $success_count) . ' kayıt başarısız.', 'warning');
        }

        redirect('modules/attendance/index.php?date=' . $attendance_date);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yoklama Düzenle - ' . $lesson['classroom_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yoklama Düzenle
        <small class="text-muted">(<?php echo htmlspecialchars($lesson['classroom_name']); ?>)</small>
    </h2>
    <a href="index.php?date=<?php echo $attendance_date; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Ders Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Tarih:</strong> <?php echo formatDate($attendance_date); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Gün:</strong> <?php echo $lesson['day']; ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Saat:</strong> <?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <!-- İşlenen Konu Bilgisi -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">İşlenen Konu</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="topic_title" class="form-label">Konu Başlığı</label>
                        <input type="text" class="form-control" id="topic_title" name="topic_title" value="<?php echo htmlspecialchars($topic['topic_title'] ?? ''); ?>" placeholder="Örn: Mblock Programlama, Lego Başlangıç, vb.">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="topic_status" class="form-label">Durum</label>
                        <select class="form-select" id="topic_status" name="topic_status">
                            <option value="completed" <?php echo ($topic['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                            <option value="planned" <?php echo ($topic['status'] ?? '') === 'planned' ? 'selected' : ''; ?>>Planlandı</option>
                            <option value="cancelled" <?php echo ($topic['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="topic_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="topic_description" name="topic_description" rows="3" placeholder="Konu hakkında detaylı açıklama girebilirsiniz (opsiyonel)"><?php echo htmlspecialchars($topic['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Öğrenci Listesi</h5>
            <div>
                <button type="button" class="btn btn-success btn-sm" id="markAllPresent">
                    <i class="bi bi-check-all"></i> Tümünü Geldi İşaretle
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="markAllAbsent">
                    <i class="bi bi-x-lg"></i> Tümünü Gelmedi İşaretle
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> Bu sınıfa kayıtlı öğrenci bulunamadı!
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="25%">Öğrenci</th>
                                <th width="20%">Durum</th>
                                <th width="50%">Notlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <?php
                                $current_status = isset($student_attendance[$student['id']]) ?
                                    $student_attendance[$student['id']]['status'] : 'present';
                                $current_notes = isset($student_attendance[$student['id']]) ?
                                    $student_attendance[$student['id']]['notes'] : '';
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <input type="radio" class="btn-check status-radio" name="status_<?php echo $student['id']; ?>" id="present_<?php echo $student['id']; ?>" value="present" <?php echo $current_status === 'present' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success btn-sm" for="present_<?php echo $student['id']; ?>">
                                                <i class="bi bi-check-lg"></i> Geldi
                                            </label>

                                            <input type="radio" class="btn-check status-radio" name="status_<?php echo $student['id']; ?>" id="absent_<?php echo $student['id']; ?>" value="absent" <?php echo $current_status === 'absent' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger btn-sm" for="absent_<?php echo $student['id']; ?>">
                                                <i class="bi bi-x-lg"></i> Gelmedi
                                            </label>

                                            <input type="radio" class="btn-check status-radio" name="status_<?php echo $student['id']; ?>" id="late_<?php echo $student['id']; ?>" value="late" <?php echo $current_status === 'late' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning btn-sm" for="late_<?php echo $student['id']; ?>">
                                                <i class="bi bi-clock"></i> Geç Kaldı
                                            </label>

                                            <input type="radio" class="btn-check status-radio" name="status_<?php echo $student['id']; ?>" id="excused_<?php echo $student['id']; ?>" value="excused" <?php echo $current_status === 'excused' ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info btn-sm" for="excused_<?php echo $student['id']; ?>">
                                                <i class="bi bi-journal-check"></i> İzinli
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="notes_<?php echo $student['id']; ?>" placeholder="Not ekleyin (opsiyonel)" value="<?php echo htmlspecialchars($current_notes); ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" onclick="window.history.back();">
                    <i class="bi bi-x"></i> İptal
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Değişiklikleri Kaydet
                </button>
            </div>
        </div>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tümünü Geldi İşaretle
        document.getElementById('markAllPresent').addEventListener('click', function() {
            document.querySelectorAll('input[id^="present_"]').forEach(function(radio) {
                radio.checked = true;
            });
        });

        // Tümünü Gelmedi İşaretle
        document.getElementById('markAllAbsent').addEventListener('click', function() {
            document.querySelectorAll('input[id^="absent_"]').forEach(function(radio) {
                radio.checked = true;
            });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>