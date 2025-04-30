<?php
// modules/attendance/take.php - Yoklama alma (Haftalık yapı ile)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Parametreleri al
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$date = isset($_GET['date']) ? clean($_GET['date']) : date('Y-m-d');

// Tarih validasyonu
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Ders bilgisini getir
$lesson_query = "SELECT l.*, c.name as classroom_name, c.id as classroom_id 
                FROM lessons l
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE l.id = ?";
$lesson = safeQuery($lesson_query, [$lesson_id])->fetch();

if (!$lesson) {
    setAlert('Geçersiz ders seçimi!', 'danger');
    redirect('modules/attendance/index.php');
}

// Hafta bilgisini bul
$week_query = "SELECT * FROM period_weeks 
              WHERE period_id = ? AND ? BETWEEN start_date AND end_date
              LIMIT 1";
$week = safeQuery($week_query, [$current_period['id'], $date])->fetch();

// Eğer hafta bulunamadıysa, en yakın haftayı bul
if (!$week) {
    $nearest_week_query = "SELECT *, ABS(DATEDIFF(start_date, ?)) as date_diff 
                          FROM period_weeks 
                          WHERE period_id = ? 
                          ORDER BY date_diff ASC 
                          LIMIT 1";
    $week = safeQuery($nearest_week_query, [$date, $current_period['id']])->fetch();
}

// Sınıftaki öğrencileri getir
$students_query = "SELECT s.id, s.first_name, s.last_name, s.birth_date, 
                  TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) as age
                  FROM students s
                  JOIN student_classrooms sc ON s.id = sc.student_id
                  JOIN student_periods sp ON s.id = sp.student_id
                  WHERE sc.classroom_id = ? 
                  AND sc.status = 'active'
                  AND sp.period_id = ?
                  AND sp.status = 'active'
                  ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$lesson['classroom_id'], $current_period['id']])->fetchAll();

// Seçilen tarih için mevcut yoklama kayıtlarını kontrol et
$existing_query = "SELECT * FROM attendance 
                  WHERE lesson_id = ? AND attendance_date = ?";
$existing_attendance = safeQuery($existing_query, [$lesson_id, $date])->fetchAll();

// Yoklama kayıtlarını öğrenci ID'sine göre indexle
$attendance_data = [];
foreach ($existing_attendance as $record) {
    $attendance_data[$record['student_id']] = $record;
}

// Aynı ders için işlenmiş konuları getir
$topics_query = "SELECT * FROM topics 
                WHERE lesson_id = ? AND date = ?
                ORDER BY id DESC";
$topics = safeQuery($topics_query, [$lesson_id, $date])->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();

        // Yoklama kayıtları
        $attendance_status = $_POST['attendance'] ?? [];
        $attendance_notes = $_POST['notes'] ?? [];

        // Her öğrenci için yoklama işle
        foreach ($students as $student) {
            $student_id = $student['id'];
            $status = $attendance_status[$student_id] ?? 'absent';
            $notes = $attendance_notes[$student_id] ?? '';

            // Var olan kayıt var mı kontrol et
            if (isset($attendance_data[$student_id])) {
                // Güncelle
                $update_sql = "UPDATE attendance 
                              SET status = ?, notes = ?, period_week_id = ? 
                              WHERE id = ?";
                $params = [
                    $status,
                    $notes,
                    $week ? $week['id'] : null,
                    $attendance_data[$student_id]['id']
                ];
                safeQuery($update_sql, $params);
            } else {
                // Yeni kayıt ekle
                $insert_sql = "INSERT INTO attendance 
                              (student_id, lesson_id, attendance_date, status, notes, period_id, period_week_id) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $student_id,
                    $lesson_id,
                    $date,
                    $status,
                    $notes,
                    $current_period['id'],
                    $week ? $week['id'] : null
                ];
                safeQuery($insert_sql, $params);
            }
        }

        // Konu işleme
        $topic_title = clean($_POST['topic_title'] ?? '');
        $topic_description = clean($_POST['topic_description'] ?? '');

        if (!empty($topic_title)) {
            // Yeni konu ekle
            $topic_sql = "INSERT INTO topics 
                          (lesson_id, topic_title, description, date, status, period_week_id) 
                          VALUES (?, ?, ?, ?, 'completed', ?)";
            $topic_params = [
                $lesson_id,
                $topic_title,
                $topic_description,
                $date,
                $week ? $week['id'] : null
            ];
            safeQuery($topic_sql, $topic_params);
        }

        db()->commit();
        setAlert('Yoklama başarıyla kaydedildi!', 'success');
        redirect('modules/attendance/index.php');
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yoklama Al - ' . $lesson['classroom_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yoklama: <?php echo htmlspecialchars($lesson['classroom_name']); ?>
        <small class="text-muted">
            (<?php echo $lesson['day']; ?>,
            <?php echo substr($lesson['start_time'], 0, 5); ?>-<?php echo substr($lesson['end_time'], 0, 5); ?>)
        </small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Yoklama Bilgileri</h5>
            <div>
                <input type="date" id="attendance_date" name="attendance_date" class="form-control"
                    value="<?php echo $date; ?>"
                    min="<?php echo $current_period['start_date']; ?>"
                    max="<?php echo $current_period['end_date']; ?>">
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if ($week): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong><?php echo htmlspecialchars($week['name']); ?></strong>
                (<?php echo formatDate($week['start_date']); ?> - <?php echo formatDate($week['end_date']); ?>)
                <?php if ($week['is_free']): ?>
                    <span class="badge bg-warning text-dark">Ücretsiz Hafta</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($students)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Bu sınıfta öğrenci bulunmuyor.
                <a href="../classes/assign-students.php?id=<?php echo $lesson['classroom_id']; ?>" class="alert-link">
                    Öğrenci atama sayfasına git
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-success" id="mark-all-present">
                            <i class="bi bi-check-circle"></i> Tümünü Geldi İşaretle
                        </button>
                        <button type="button" class="btn btn-danger" id="mark-all-absent">
                            <i class="bi bi-x-circle"></i> Tümünü Gelmedi İşaretle
                        </button>
                        <button type="button" class="btn btn-secondary" id="clear-all">
                            <i class="bi bi-trash"></i> Tümünü Temizle
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Öğrenci</th>
                                <th width="140">Yaş</th>
                                <th width="300">Durum</th>
                                <th>Notlar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $existing_record = $attendance_data[$student['id']] ?? null;
                                $status = $existing_record ? $existing_record['status'] : '';
                                $notes = $existing_record ? $existing_record['notes'] : '';
                                ?>
                                <tr>
                                    <td>
                                        <a href="../students/view.php?id=<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $student['age']; ?></td>
                                    <td>
                                        <div class="btn-group attendance-buttons w-100" role="group">
                                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]"
                                                id="present_<?php echo $student['id']; ?>" value="present"
                                                <?php echo ($status === 'present') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success" for="present_<?php echo $student['id']; ?>">
                                                <i class="bi bi-check-circle"></i> Geldi
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]"
                                                id="absent_<?php echo $student['id']; ?>" value="absent"
                                                <?php echo ($status === 'absent') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger" for="absent_<?php echo $student['id']; ?>">
                                                <i class="bi bi-x-circle"></i> Gelmedi
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]"
                                                id="late_<?php echo $student['id']; ?>" value="late"
                                                <?php echo ($status === 'late') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning" for="late_<?php echo $student['id']; ?>">
                                                <i class="bi bi-clock"></i> Geç
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?php echo $student['id']; ?>]"
                                                id="excused_<?php echo $student['id']; ?>" value="excused"
                                                <?php echo ($status === 'excused') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info" for="excused_<?php echo $student['id']; ?>">
                                                <i class="bi bi-calendar-x"></i> İzinli
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control"
                                            name="notes[<?php echo $student['id']; ?>]"
                                            value="<?php echo htmlspecialchars($notes); ?>"
                                            placeholder="Özel not...">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <hr>

                <!-- Konu Ekleme -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book"></i> İşlenen Konu
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($topics)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Bu ders için <?php echo count($topics); ?> konu bulunuyor.
                            </div>

                            <div class="table-responsive mb-3">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Konu Başlığı</th>
                                            <th>Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topics as $topic): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($topic['topic_title']); ?></td>
                                                <td><?php echo htmlspecialchars($topic['description'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="topic_title" class="form-label">Konu Başlığı</label>
                            <input type="text" class="form-control" id="topic_title" name="topic_title"
                                placeholder="Bugün işlenen konu başlığı...">
                        </div>
                        <div class="mb-0">
                            <label for="topic_description" class="form-label">Açıklama / Notlar</label>
                            <textarea class="form-control" id="topic_description" name="topic_description" rows="3"
                                placeholder="Konu detayları, ödevler, hatırlatmalar..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Yoklamayı Kaydet
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tarih değiştiğinde sayfayı yenile
        document.getElementById('attendance_date').addEventListener('change', function() {
            window.location.href = 'take.php?lesson_id=<?php echo $lesson_id; ?>&date=' + this.value;
        });

        // Tümünü geldi işaretle
        document.getElementById('mark-all-present').addEventListener('click', function() {
            document.querySelectorAll('input[id^="present_"]').forEach(function(radio) {
                radio.checked = true;
            });
        });

        // Tümünü gelmedi işaretle
        document.getElementById('mark-all-absent').addEventListener('click', function() {
            document.querySelectorAll('input[id^="absent_"]').forEach(function(radio) {
                radio.checked = true;
            });
        });

        // Tümünü temizle
        document.getElementById('clear-all').addEventListener('click', function() {
            document.querySelectorAll('input[type="radio"]').forEach(function(radio) {
                radio.checked = false;
            });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>