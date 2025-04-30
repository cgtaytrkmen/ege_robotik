<?php
// modules/makeup/index.php - Telafi Dersleri Ana Sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Tarih aralığı filtresi
$default_start_date = date('Y-m-d', strtotime('-30 days'));
$default_end_date = date('Y-m-d');

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Gelmeyen veya geç kalan öğrencilerin yoklama kayıtlarını getir
$attendance_query = "SELECT a.*, 
                    s.id as student_id, s.first_name, s.last_name,
                    l.id as lesson_id, l.day, l.start_time, l.end_time,
                    c.name as classroom_name,
                    t.id as topic_id, t.topic_title
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    JOIN lessons l ON a.lesson_id = l.id
                    JOIN classrooms c ON l.classroom_id = c.id
                    LEFT JOIN topics t ON l.id = t.lesson_id AND a.attendance_date = t.date
                    LEFT JOIN student_periods sp ON s.id = sp.student_id
                    WHERE a.attendance_date BETWEEN ? AND ?
                    AND sp.period_id = ?
                    AND sp.status = 'active'
                    AND a.status IN ('absent', 'late', 'excused')";

$params = [$start_date, $end_date, $current_period['id']];

// Durum filtresi eklendi
if (!empty($filter_status)) {
    $attendance_query .= " AND a.status = ?";
    $params[] = $filter_status;
}

$attendance_query .= " ORDER BY a.attendance_date DESC, s.first_name, s.last_name";
$attendance_records = safeQuery($attendance_query, $params)->fetchAll();

// Telafi dersi verilenleri belirle 
$makeup_lessons = [];
$makeup_query = "SELECT ml.* FROM makeup_lessons ml WHERE ml.original_date BETWEEN ? AND ?";
$makeup_results = safeQuery($makeup_query, [$start_date, $end_date])->fetchAll();

// Telafi dersi ID'lerini bir diziye ekle
$makeup_map = [];
foreach ($makeup_results as $makeup) {
    $key = $makeup['student_id'] . '_' . $makeup['original_date'] . '_' . $makeup['original_lesson_id'];
    $makeup_map[$key] = $makeup;
}

$page_title = 'Telafi Dersleri - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Telafi Dersleri
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="../attendance/index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Yoklama Sayfasına Dön
    </a>
</div>

<!-- Filtre -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Gelmedi</option>
                    <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Geç Kaldı</option>
                    <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>İzinli</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
                <a href="index.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Telafi Gerektiren Yoklamalar</h5>
    </div>
    <div class="card-body">
        <?php if (empty($attendance_records)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen tarih aralığında kayıp ders bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Öğrenci</th>
                            <th>Sınıf</th>
                            <th>Saat</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th>Telafi Durumu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <?php
                            $status_badges = [
                                'present' => 'success',
                                'absent' => 'danger',
                                'late' => 'warning',
                                'excused' => 'info'
                            ];
                            $status_labels = [
                                'present' => 'Geldi',
                                'absent' => 'Gelmedi',
                                'late' => 'Geç Kaldı',
                                'excused' => 'İzinli'
                            ];

                            // Telafi durumunu kontrol et
                            $makeup_key = $record['student_id'] . '_' . $record['attendance_date'] . '_' . $record['lesson_id'];
                            $has_makeup = isset($makeup_map[$makeup_key]);
                            $makeup_status = $has_makeup ? $makeup_map[$makeup_key]['status'] : 'none';

                            $makeup_badges = [
                                'none' => 'secondary',
                                'pending' => 'warning',
                                'completed' => 'success',
                                'missed' => 'danger',
                                'cancelled' => 'dark'
                            ];

                            $makeup_labels = [
                                'none' => 'Telafi Yok',
                                'pending' => 'Beklemede',
                                'completed' => 'Tamamlandı',
                                'missed' => 'Kaçırıldı',
                                'cancelled' => 'İptal Edildi'
                            ];
                            ?>
                            <tr>
                                <td><?php echo formatDate($record['attendance_date']); ?></td>
                                <td>
                                    <a href="student.php?id=<?php echo $record['student_id']; ?>">
                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                                <td><?php echo substr($record['start_time'], 0, 5) . ' - ' . substr($record['end_time'], 0, 5); ?></td>
                                <td>
                                    <?php if (!empty($record['topic_title'])): ?>
                                        <span title="<?php echo htmlspecialchars($record['topic_title']); ?>">
                                            <?php echo htmlspecialchars(mb_substr($record['topic_title'], 0, 30) . (mb_strlen($record['topic_title']) > 30 ? '...' : '')); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Konu girilmemiş</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_badges[$record['status']]; ?>">
                                        <?php echo $status_labels[$record['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $makeup_badges[$makeup_status]; ?>">
                                        <?php echo $makeup_labels[$makeup_status]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!$has_makeup): ?>
                                        <a href="add.php?student_id=<?php echo $record['student_id']; ?>&lesson_id=<?php echo $record['lesson_id']; ?>&date=<?php echo $record['attendance_date']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Telafi Ekle
                                        </a>
                                    <?php else: ?>
                                        <a href="edit.php?id=<?php echo $makeup_map[$makeup_key]['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tarih değiştirildiğinde otomatik form gönderimi
        document.getElementById('start_date').addEventListener('change', function() {
            if (document.getElementById('end_date').value) {
                this.form.submit();
            }
        });

        document.getElementById('end_date').addEventListener('change', function() {
            if (document.getElementById('start_date').value) {
                this.form.submit();
            }
        });

        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>