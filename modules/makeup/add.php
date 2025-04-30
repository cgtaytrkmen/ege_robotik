<?php
// modules/makeup/add.php - Telafi dersi ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Gerekli parametreleri al
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;
$original_date = isset($_GET['date']) ? $_GET['date'] : '';

// Parametreleri kontrol et
if (!$student_id || !$lesson_id || empty($original_date)) {
    setAlert('Geçersiz parametreler!', 'danger');
    redirect('modules/makeup/index.php');
}

// Öğrenci bilgisini getir
$student_query = "SELECT * FROM students WHERE id = ?";
$student = safeQuery($student_query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/makeup/index.php');
}

// Ders bilgisini getir
$lesson_query = "SELECT l.*, c.name as classroom_name
                FROM lessons l
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE l.id = ?";
$lesson = safeQuery($lesson_query, [$lesson_id])->fetch();

if (!$lesson) {
    setAlert('Ders bulunamadı!', 'danger');
    redirect('modules/makeup/index.php');
}

// Orijinal tarihte işlenen konuyu getir
$topic_query = "SELECT * FROM topics WHERE lesson_id = ? AND date = ? LIMIT 1";
$topic = safeQuery($topic_query, [$lesson_id, $original_date])->fetch();

// Telafi dersi var mı kontrol et
$check_query = "SELECT * FROM makeup_lessons 
               WHERE student_id = ? AND original_lesson_id = ? AND original_date = ?";
$existing_makeup = safeQuery($check_query, [$student_id, $lesson_id, $original_date])->fetch();

if ($existing_makeup) {
    setAlert('Bu ders için zaten bir telafi kaydı mevcut!', 'warning');
    redirect('modules/makeup/edit.php?id=' . $existing_makeup['id']);
}

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $makeup_date = !empty($_POST['makeup_date']) ? clean($_POST['makeup_date']) : null;
        $status = clean($_POST['status']);
        $notes = clean($_POST['notes']);

        // Telafi dersini ekle
        $makeup_data = [
            'student_id' => $student_id,
            'original_lesson_id' => $lesson_id,
            'original_date' => $original_date,
            'makeup_date' => $makeup_date,
            'topic_id' => $topic ? $topic['id'] : null,
            'status' => $status,
            'notes' => $notes
        ];

        $sql = "INSERT INTO makeup_lessons (student_id, original_lesson_id, original_date, makeup_date, topic_id, status, notes) 
                VALUES (:student_id, :original_lesson_id, :original_date, :makeup_date, :topic_id, :status, :notes)";
        safeQuery($sql, $makeup_data);

        // Eğer tamamlandı olarak işaretlendiyse, telafi yoklama kaydı oluştur
        if ($status === 'completed' && $makeup_date) {
            // Telafi yoklama kaydı oluştur
            $attendance_data = [
                'student_id' => $student_id,
                'lesson_id' => $lesson_id,
                'attendance_date' => $makeup_date,
                'status' => 'present',
                'notes' => 'Telafi dersi - Orijinal tarih: ' . formatDate($original_date)
            ];

            $check_attendance = "SELECT * FROM attendance WHERE student_id = ? AND lesson_id = ? AND attendance_date = ?";
            $existing_attendance = safeQuery($check_attendance, [$student_id, $lesson_id, $makeup_date])->fetch();

            if (!$existing_attendance) {
                $attendance_sql = "INSERT INTO attendance (student_id, lesson_id, attendance_date, status, notes) 
                                  VALUES (:student_id, :lesson_id, :attendance_date, :status, :notes)";
                safeQuery($attendance_sql, $attendance_data);
            }
        }

        setAlert('Telafi dersi başarıyla eklendi!', 'success');
        redirect('modules/makeup/student.php?id=' . $student_id);
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Telafi Dersi Ekle - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Telafi Dersi Ekle
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <div>
        <a href="student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Öğrenci Telafilerine Dön
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Orijinal Ders Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Tarih:</strong> <?php echo formatDate($original_date); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Sınıf:</strong> <?php echo htmlspecialchars($lesson['classroom_name']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Saat:</strong> <?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></p>
            </div>

            <?php if ($topic): ?>
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">İşlenen Konu</h6>
                        <p><strong><?php echo htmlspecialchars($topic['topic_title']); ?></strong></p>
                        <?php if (!empty($topic['description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($topic['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Telafi Bilgileri</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="makeup_date" class="form-label">Telafi Tarihi</label>
                    <input type="date" class="form-control" id="makeup_date" name="makeup_date" min="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">Telafi henüz gerçekleşmediyse boş bırakabilirsiniz.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Telafi Durumu</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending">Beklemede</option>
                        <option value="completed">Tamamlandı</option>
                        <option value="missed">Kaçırıldı</option>
                        <option value="cancelled">İptal Edildi</option>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Notlar</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Telafi Dersini Kaydet
                </button>
                <a href="student.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Telafi durumu değişince kontrol et
        const statusSelect = document.getElementById('status');
        const makeupDateInput = document.getElementById('makeup_date');

        statusSelect.addEventListener('change', function() {
            if (this.value === 'completed') {
                makeupDateInput.setAttribute('required', 'required');
                makeupDateInput.focus();
            } else {
                makeupDateInput.removeAttribute('required');
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>