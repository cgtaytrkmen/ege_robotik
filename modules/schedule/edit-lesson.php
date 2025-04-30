<?php
// modules/schedule/edit-lesson.php - Ders düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$lesson_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$lesson_id) {
    setAlert('Geçersiz ders ID!', 'danger');
    redirect('modules/schedule/index.php');
}

// Ders bilgilerini getir
$lesson_query = "SELECT l.*, c.name as classroom_name 
                FROM lessons l 
                JOIN classrooms c ON l.classroom_id = c.id 
                WHERE l.id = ? AND l.period_id = ?";
$lesson = safeQuery($lesson_query, [$lesson_id, $current_period['id']])->fetch();

if (!$lesson) {
    setAlert('Ders bulunamadı!', 'danger');
    redirect('modules/schedule/index.php');
}

// Türkçe gün adları
$days_tr = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];

// Aktif sınıfları getir
$classrooms_query = "SELECT id, name, capacity, age_group FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Form verilerini al
        $classroom_id = intval($_POST['classroom_id']);
        $day = clean($_POST['day']);
        $start_time = clean($_POST['start_time']);
        $end_time = clean($_POST['end_time']);
        $notes = clean($_POST['notes'] ?? '');
        $status = clean($_POST['status']);

        // Veri doğrulama
        if (empty($classroom_id) || empty($day) || empty($start_time) || empty($end_time)) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Sınıf kontrolü
        $classroom_query = "SELECT id FROM classrooms WHERE id = ? AND status = 'active'";
        $classroom = safeQuery($classroom_query, [$classroom_id])->fetch();

        if (!$classroom) {
            throw new Exception('Geçersiz sınıf seçimi!');
        }

        // Zaman formatını kontrol et
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $start_time)) {
            throw new Exception('Başlangıç saati formatı geçersiz. Lütfen HH:MM formatında girin.');
        }

        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $end_time)) {
            throw new Exception('Bitiş saati formatı geçersiz. Lütfen HH:MM formatında girin.');
        }

        // Başlangıç saati bitiş saatinden önce olmalı
        if (strtotime($start_time) >= strtotime($end_time)) {
            throw new Exception('Başlangıç saati bitiş saatinden önce olmalıdır!');
        }

        // Aynı sınıf için çakışma kontrolü (kendisi hariç)
        $overlap_query = "SELECT id FROM lessons 
                         WHERE classroom_id = ? AND day = ? AND period_id = ? AND status = 'active' AND id != ?
                         AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))";

        $params = [
            $classroom_id,
            $day,
            $current_period['id'],
            $lesson_id,
            $start_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        ];

        $overlap_check = safeQuery($overlap_query, $params)->fetch();

        if ($overlap_check) {
            throw new Exception('Bu sınıf için seçilen saatlerde başka bir ders mevcut!');
        }

        // Dersi güncelle
        $update_query = "UPDATE lessons 
                        SET classroom_id = ?, day = ?, start_time = ?, end_time = ?, notes = ?, status = ? 
                        WHERE id = ?";

        $result = safeQuery($update_query, [
            $classroom_id,
            $day,
            $start_time,
            $end_time,
            $notes,
            $status,
            $lesson_id
        ]);

        // Başarılı mesajı
        setAlert('Ders başarıyla güncellendi!', 'success');
        redirect('modules/schedule/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Ders Düzenle - ' . $lesson['classroom_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Ders Düzenle
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="classroom_id" class="form-label">Sınıf *</label>
                    <select class="form-select" id="classroom_id" name="classroom_id" required>
                        <option value="">-- Sınıf Seçin --</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['id']; ?>" <?php echo ($classroom['id'] == $lesson['classroom_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classroom['name']); ?>
                                (<?php echo htmlspecialchars($classroom['age_group']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="day" class="form-label">Gün *</label>
                    <select class="form-select" id="day" name="day" required>
                        <?php foreach ($days_tr as $day_en => $day_tr): ?>
                            <option value="<?php echo $day_en; ?>" <?php echo ($day_en === $lesson['day']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($day_tr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="start_time" class="form-label">Başlangıç Saati *</label>
                    <input type="time" class="form-control" id="start_time" name="start_time"
                        value="<?php echo substr($lesson['start_time'], 0, 5); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="end_time" class="form-label">Bitiş Saati *</label>
                    <input type="time" class="form-control" id="end_time" name="end_time"
                        value="<?php echo substr($lesson['end_time'], 0, 5); ?>" required>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Notlar</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($lesson['notes']); ?></textarea>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Durum *</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="active" <?php echo ($lesson['status'] === 'active') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="cancelled" <?php echo ($lesson['status'] === 'cancelled') ? 'selected' : ''; ?>>İptal Edildi</option>
                        <option value="postponed" <?php echo ($lesson['status'] === 'postponed') ? 'selected' : ''; ?>>Ertelendi</option>
                    </select>
                </div>
            </div>

            <div class="alert alert-warning" id="schedule-warning" style="display: none;">
                <strong>Uyarı:</strong> Seçtiğiniz gün ve sınıf için mevcut dersler bulunuyor. Lütfen çakışmaları önlemek için saatleri kontrol edin.
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mevcut Ders Programını Gösteren Panel -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Mevcut Ders Programı</h5>
    </div>
    <div class="card-body" id="schedule-content">
        <?php
        // Gün ve sınıf için mevcut dersleri getir (bu ders hariç)
        $day_schedule_query = "SELECT l.*, c.name as classroom_name 
                              FROM lessons l 
                              JOIN classrooms c ON l.classroom_id = c.id 
                              WHERE l.classroom_id = ? AND l.day = ? AND l.period_id = ? AND l.id != ? AND l.status = 'active'
                              ORDER BY l.start_time";

        $day_schedule = safeQuery($day_schedule_query, [
            $lesson['classroom_id'],
            $lesson['day'],
            $current_period['id'],
            $lesson_id
        ])->fetchAll();
        ?>

        <?php if (count($day_schedule) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Saat</th>
                            <th>Sınıf</th>
                            <th>Notlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($day_schedule as $schedule_item): ?>
                            <tr>
                                <td><?php echo substr($schedule_item['start_time'], 0, 5) . ' - ' . substr($schedule_item['end_time'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($schedule_item['classroom_name']); ?></td>
                                <td><?php echo htmlspecialchars($schedule_item['notes']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">Bu gün ve sınıf için başka ders bulunmuyor.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const classroomSelect = document.getElementById('classroom_id');
        const daySelect = document.getElementById('day');
        const scheduleWarning = document.getElementById('schedule-warning');

        // Sınıf veya gün değiştiğinde programı kontrol et
        function checkSchedule() {
            const classroomId = classroomSelect.value;
            const day = daySelect.value;

            if (classroomId && day) {
                // AJAX ile çakışma kontrolü yapılabilir
                // Bu örnekte basit bir uyarı gösteriyoruz

                // Gerçek projede AJAX ile çakışma kontrolü yapılmalı
                fetch(`check-conflicts.php?classroom_id=${classroomId}&day=${day}&lesson_id=<?php echo $lesson_id; ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.hasConflict) {
                            scheduleWarning.style.display = 'block';
                        } else {
                            scheduleWarning.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error checking conflicts:', error);
                    });

                // Bu demo için basit bir kontrol
                if (classroomId === '<?php echo $lesson['classroom_id']; ?>' && day === '<?php echo $lesson['day']; ?>') {
                    // Aynı sınıf ve gün - uyarı gerekmez
                    scheduleWarning.style.display = 'none';
                } else {
                    // Farklı sınıf veya gün - uyarı göster
                    scheduleWarning.style.display = 'block';
                }
            }
        }

        classroomSelect.addEventListener('change', checkSchedule);
        daySelect.addEventListener('change', checkSchedule);

        // Sayfayı yüklerken çakışma kontrolü et
        checkSchedule();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>