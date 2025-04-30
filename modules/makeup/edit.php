<?php
// modules/makeup/edit.php - Telafi dersi düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Telafi dersi ID kontrolü
$makeup_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$makeup_id) {
    setAlert('Geçersiz telafi dersi ID!', 'danger');
    redirect('modules/makeup/index.php');
}

// Telafi dersi bilgisini getir
$makeup_query = "SELECT ml.*,
                s.first_name, s.last_name,
                l.start_time, l.end_time, l.day,
                c.name as classroom_name,
                t.topic_title, t.description as topic_description
                FROM makeup_lessons ml
                JOIN students s ON ml.student_id = s.id
                LEFT JOIN lessons l ON ml.original_lesson_id = l.id
                LEFT JOIN classrooms c ON l.classroom_id = c.id
                LEFT JOIN topics t ON ml.topic_id = t.id
                WHERE ml.id = ?";
$makeup = safeQuery($makeup_query, [$makeup_id])->fetch();

if (!$makeup) {
    setAlert('Telafi dersi bulunamadı!', 'danger');
    redirect('modules/makeup/index.php');
}

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $old_status = $makeup['status'];
        $old_makeup_date = $makeup['makeup_date'];

        $makeup_date = !empty($_POST['makeup_date']) ? clean($_POST['makeup_date']) : null;
        $status = clean($_POST['status']);
        $notes = clean($_POST['notes']);

        // Telafi dersini güncelle
        $update_data = [
            'makeup_date' => $makeup_date,
            'status' => $status,
            'notes' => $notes,
            'id' => $makeup_id
        ];

        $sql = "UPDATE makeup_lessons 
                SET makeup_date = :makeup_date, status = :status, notes = :notes
                WHERE id = :id";
        safeQuery($sql, $update_data);

        // Eğer telafi durumu "tamamlandı" olarak değiştiyse 
        // veya tamamlandıysa ve tarihi değiştiyse yoklama kaydını güncelle
        if (($status === 'completed' && $old_status !== 'completed') ||
            ($status === 'completed' && $makeup_date !== $old_makeup_date)
        ) {

            // Eski bir yoklama kaydı varsa sil (eski telafi günü)
            if ($old_status === 'completed' && $old_makeup_date) {
                $delete_sql = "DELETE FROM attendance 
                              WHERE student_id = ? AND lesson_id = ? AND attendance_date = ? 
                              AND notes LIKE '%Telafi dersi%'";
                safeQuery($delete_sql, [$makeup['student_id'], $makeup['original_lesson_id'], $old_makeup_date]);
            }

            // Yeni yoklama kaydı oluştur
            $attendance_data = [
                'student_id' => $makeup['student_id'],
                'lesson_id' => $makeup['original_lesson_id'],
                'attendance_date' => $makeup_date,
                'status' => 'present',
                'notes' => 'Telafi dersi - Orijinal tarih: ' . formatDate($makeup['original_date'])
            ];

            $check_attendance = "SELECT * FROM attendance 
                               WHERE student_id = ? AND lesson_id = ? AND attendance_date = ?";
            $existing_attendance = safeQuery(
                $check_attendance,
                [$makeup['student_id'], $makeup['original_lesson_id'], $makeup_date]
            )->fetch();

            if (!$existing_attendance) {
                $attendance_sql = "INSERT INTO attendance (student_id, lesson_id, attendance_date, status, notes) 
                                  VALUES (:student_id, :lesson_id, :attendance_date, :status, :notes)";
                safeQuery($attendance_sql, $attendance_data);
            }
        }
        // Eğer telafi durumu "tamamlandı" değilse ve eskiden "tamamlandı" idiyse
        // yoklama kaydını sil
        else if ($status !== 'completed' && $old_status === 'completed' && $old_makeup_date) {
            $delete_sql = "DELETE FROM attendance 
                          WHERE student_id = ? AND lesson_id = ? AND attendance_date = ? 
                          AND notes LIKE '%Telafi dersi%'";
            safeQuery($delete_sql, [$makeup['student_id'], $makeup['original_lesson_id'], $old_makeup_date]);
        }

        setAlert('Telafi dersi başarıyla güncellendi!', 'success');
        redirect('modules/makeup/student.php?id=' . $makeup['student_id']);
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Telafi Dersi Düzenle - ' . $makeup['first_name'] . ' ' . $makeup['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Telafi Dersi Düzenle
        <small class="text-muted">(<?php echo htmlspecialchars($makeup['first_name'] . ' ' . $makeup['last_name']); ?>)</small>
    </h2>
    <div>
        <a href="student.php?id=<?php echo $makeup['student_id']; ?>" class="btn btn-secondary">
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
                <p><strong>Tarih:</strong> <?php echo formatDate($makeup['original_date']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Sınıf:</strong> <?php echo htmlspecialchars($makeup['classroom_name']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Saat:</strong> <?php echo substr($makeup['start_time'], 0, 5) . ' - ' . substr($makeup['end_time'], 0, 5); ?></p>
            </div>

            <?php if (!empty($makeup['topic_title'])): ?>
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">İşlenen Konu</h6>
                        <p><strong><?php echo htmlspecialchars($makeup['topic_title']); ?></strong></p>
                        <?php if (!empty($makeup['topic_description'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($makeup['topic_description'])); ?></p>
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
                    <input type="date" class="form-control" id="makeup_date" name="makeup_date"
                        value="<?php echo $makeup['makeup_date']; ?>"
                        <?php echo $makeup['status'] === 'completed' ? 'required' : ''; ?>>
                    <div class="form-text">Telafi henüz gerçekleşmediyse boş bırakabilirsiniz.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Telafi Durumu</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="pending" <?php echo $makeup['status'] === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                        <option value="completed" <?php echo $makeup['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                        <option value="missed" <?php echo $makeup['status'] === 'missed' ? 'selected' : ''; ?>>Kaçırıldı</option>
                        <option value="cancelled" <?php echo $makeup['status'] === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Notlar</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($makeup['notes']); ?></textarea>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Değişiklikleri Kaydet
                </button>
                <a href="student.php?id=<?php echo $makeup['student_id']; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
                <a href="delete.php?id=<?php echo $makeup_id; ?>" class="btn btn-danger float-end"
                    onclick="return confirm('Bu telafi dersini silmek istediğinizden emin misiniz?');">
                    <i class="bi bi-trash"></i> Telafi Dersini Sil
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