<?php
// modules/makeup/student.php - Öğrenci bazlı telafi dersleri görüntüleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Öğrenci ID kontrolü
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/makeup/index.php');
}

// Öğrenci bilgilerini getir
$student_query = "SELECT s.*, 
                 GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as parent_names,
                 MAX(CASE WHEN sp.is_primary = 1 THEN p.phone ELSE NULL END) as parent_phone
                 FROM students s
                 LEFT JOIN student_parents sp ON s.id = sp.student_id
                 LEFT JOIN parents p ON sp.parent_id = p.id
                 WHERE s.id = ?
                 GROUP BY s.id";
$student = safeQuery($student_query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/makeup/index.php');
}

// Tarih aralığı filtresi
$default_start_date = date('Y-m-d', strtotime('-60 days'));
$default_end_date = date('Y-m-d');

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start_date;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end_date;
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Öğrencinin devamsızlık kayıtlarını getir
$attendance_query = "SELECT a.*, 
                    l.id as lesson_id, l.day, l.start_time, l.end_time,
                    c.name as classroom_name,
                    t.id as topic_id, t.topic_title, t.description as topic_description
                    FROM attendance a
                    JOIN lessons l ON a.lesson_id = l.id
                    JOIN classrooms c ON l.classroom_id = c.id
                    LEFT JOIN topics t ON l.id = t.lesson_id AND a.attendance_date = t.date
                    WHERE a.student_id = ?
                    AND a.attendance_date BETWEEN ? AND ?
                    AND l.period_id = ?";

$params = [$student_id, $start_date, $end_date, $current_period['id']];

// Durum filtresi eklendi
if (!empty($filter_status)) {
    $attendance_query .= " AND a.status = ?";
    $params[] = $filter_status;
} else {
    // Varsayılan olarak sadece gelmeyen, geç kalan ve izinli olanları göster
    $attendance_query .= " AND a.status IN ('absent', 'late', 'excused')";
}

$attendance_query .= " ORDER BY a.attendance_date DESC";
$missed_classes = safeQuery($attendance_query, $params)->fetchAll();

// Telafi dersi alınanları getir
$makeup_query = "SELECT ml.*, 
                t.topic_title, t.description as topic_description,
                l.day as original_day, l.start_time as original_start_time, l.end_time as original_end_time,
                c.name as original_classroom_name
                FROM makeup_lessons ml
                LEFT JOIN topics t ON ml.topic_id = t.id
                LEFT JOIN lessons l ON ml.original_lesson_id = l.id
                LEFT JOIN classrooms c ON l.classroom_id = c.id
                WHERE ml.student_id = ?
                ORDER BY ml.created_at DESC";
$makeup_lessons = safeQuery($makeup_query, [$student_id])->fetchAll();

// Telafi durumuna göre map oluştur
$makeup_map = [];
foreach ($makeup_lessons as $makeup) {
    $key = $makeup['original_lesson_id'] . '_' . $makeup['original_date'];
    $makeup_map[$key] = $makeup;
}

// Devamsızlık istatistikleri
$stats_query = "SELECT 
               COUNT(*) as total_classes,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
               SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
               SUM(CASE WHEN a.status IN ('absent', 'late', 'excused') THEN 1 ELSE 0 END) as total_missed
               FROM attendance a
               JOIN lessons l ON a.lesson_id = l.id
               WHERE a.student_id = ? AND l.period_id = ?";
$stats = safeQuery($stats_query, [$student_id, $current_period['id']])->fetch();

// Telafi istatistikleri
$makeup_stats_query = "SELECT 
                      COUNT(*) as total_makeups,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                      SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_count,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
                      FROM makeup_lessons
                      WHERE student_id = ?";
$makeup_stats = safeQuery($makeup_stats_query, [$student_id])->fetch();

$page_title = 'Öğrenci Telafi Dersleri - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Telafi Dersleri
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <div>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Telafi Listesine Dön
        </a>
        <a href="../students/view.php?id=<?php echo $student_id; ?>" class="btn btn-primary">
            <i class="bi bi-person"></i> Öğrenci Detayları
        </a>
    </div>
</div>

<!-- Öğrenci Bilgileri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Öğrenci Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                <p><strong>Doğum Tarihi:</strong> <?php echo formatDate($student['birth_date']); ?> (<?php echo calculateAge($student['birth_date']); ?> yaş)</p>
            </div>
            <div class="col-md-4">
                <p><strong>Veli:</strong> <?php echo htmlspecialchars($student['parent_names'] ?? '-'); ?></p>
                <p><strong>Telefon:</strong> <?php echo formatPhone($student['parent_phone'] ?? '-'); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Okul:</strong> <?php echo htmlspecialchars($student['school']); ?></p>
                <p><strong>Durum:</strong>
                    <?php
                    $status_badges = [
                        'active' => 'success',
                        'passive' => 'secondary',
                        'trial' => 'info'
                    ];
                    $status_labels = [
                        'active' => 'Aktif',
                        'passive' => 'Pasif',
                        'trial' => 'Deneme'
                    ];
                    $badge = $status_badges[$student['status']] ?? 'secondary';
                    $label = $status_labels[$student['status']] ?? $student['status'];
                    ?>
                    <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- İstatistikler -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Devamsızlık İstatistikleri</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="mb-0"><?php echo $stats['total_classes'] ?? 0; ?></h3>
                        <small class="text-muted">Toplam Ders</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-danger"><?php echo $stats['absent_count'] ?? 0; ?></h3>
                        <small class="text-muted">Gelmedi</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-warning"><?php echo $stats['late_count'] ?? 0; ?></h3>
                        <small class="text-muted">Geç Kaldı</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-info"><?php echo $stats['excused_count'] ?? 0; ?></h3>
                        <small class="text-muted">İzinli</small>
                    </div>
                </div>
                <?php if (($stats['total_classes'] ?? 0) > 0): ?>
                    <div class="progress mt-3" style="height: 25px;">
                        <div class="progress-bar bg-danger" role="progressbar"
                            style="width: <?php echo round(($stats['absent_count'] / $stats['total_classes']) * 100); ?>%;"
                            title="Gelmedi">
                            <?php echo $stats['absent_count']; ?>
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar"
                            style="width: <?php echo round(($stats['late_count'] / $stats['total_classes']) * 100); ?>%;"
                            title="Geç Kaldı">
                            <?php echo $stats['late_count']; ?>
                        </div>
                        <div class="progress-bar bg-info" role="progressbar"
                            style="width: <?php echo round(($stats['excused_count'] / $stats['total_classes']) * 100); ?>%;"
                            title="İzinli">
                            <?php echo $stats['excused_count']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Telafi Dersi İstatistikleri</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="mb-0"><?php echo $makeup_stats['total_makeups'] ?? 0; ?></h3>
                        <small class="text-muted">Toplam Telafi</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-success"><?php echo $makeup_stats['completed_count'] ?? 0; ?></h3>
                        <small class="text-muted">Tamamlandı</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-warning"><?php echo $makeup_stats['pending_count'] ?? 0; ?></h3>
                        <small class="text-muted">Beklemede</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-0 text-danger"><?php echo ($makeup_stats['missed_count'] ?? 0) + ($makeup_stats['cancelled_count'] ?? 0); ?></h3>
                        <small class="text-muted">Kaçırıldı/İptal</small>
                    </div>
                </div>
                <?php
                $total_makeups = $makeup_stats['total_makeups'] ?? 0;
                if ($total_makeups > 0):
                ?>
                    <div class="progress mt-3" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar"
                            style="width: <?php echo round(($makeup_stats['completed_count'] / $total_makeups) * 100); ?>%;"
                            title="Tamamlandı">
                            <?php echo $makeup_stats['completed_count']; ?>
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar"
                            style="width: <?php echo round(($makeup_stats['pending_count'] / $total_makeups) * 100); ?>%;"
                            title="Beklemede">
                            <?php echo $makeup_stats['pending_count']; ?>
                        </div>
                        <div class="progress-bar bg-danger" role="progressbar"
                            style="width: <?php echo round((($makeup_stats['missed_count'] + $makeup_stats['cancelled_count']) / $total_makeups) * 100); ?>%;"
                            title="Kaçırıldı/İptal">
                            <?php echo $makeup_stats['missed_count'] + $makeup_stats['cancelled_count']; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Filtre -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="id" value="<?php echo $student_id; ?>">
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
                    <option value="">Tümü (Gelmeyen)</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Gelmedi</option>
                    <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Geç Kaldı</option>
                    <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>İzinli</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
                <a href="student.php?id=<?php echo $student_id; ?>" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Kaçırılan Dersler -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Kaçırılan Dersler</h5>
        <span class="badge bg-secondary"><?php echo count($missed_classes); ?> Ders</span>
    </div>
    <div class="card-body">
        <?php if (empty($missed_classes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen kriterlere uygun kaçırılan ders bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sınıf</th>
                            <th>Saat</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th>Telafi Durumu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missed_classes as $record): ?>
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
                            $makeup_key = $record['lesson_id'] . '_' . $record['attendance_date'];
                            $has_makeup = isset($makeup_map[$makeup_key]);

                            $makeup_status = 'none';
                            $makeup_id = null;

                            if ($has_makeup) {
                                $makeup_status = $makeup_map[$makeup_key]['status'];
                                $makeup_id = $makeup_map[$makeup_key]['id'];
                            }

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
                                <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                                <td><?php echo substr($record['start_time'], 0, 5) . ' - ' . substr($record['end_time'], 0, 5); ?></td>
                                <td>
                                    <?php if (!empty($record['topic_title'])): ?>
                                        <a href="#" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($record['topic_description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($record['topic_title']); ?>
                                        </a>
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
                                    <?php if ($makeup_status === 'none'): ?>
                                        <a href="add.php?student_id=<?php echo $student_id; ?>&lesson_id=<?php echo $record['lesson_id']; ?>&date=<?php echo $record['attendance_date']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Telafi Ekle
                                        </a>
                                    <?php else: ?>
                                        <a href="edit.php?id=<?php echo $makeup_id; ?>" class="btn btn-sm btn-warning">
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

<!-- Telafi Dersleri -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Telafi Dersleri</h5>
        <span class="badge bg-secondary"><?php echo count($makeup_lessons); ?> Telafi</span>
    </div>
    <div class="card-body">
        <?php if (empty($makeup_lessons)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Henüz telafi dersi kaydı bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Orijinal Tarih</th>
                            <th>Telafi Tarihi</th>
                            <th>Sınıf</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($makeup_lessons as $makeup): ?>
                            <?php
                            $makeup_badges = [
                                'pending' => 'warning',
                                'completed' => 'success',
                                'missed' => 'danger',
                                'cancelled' => 'dark'
                            ];

                            $makeup_labels = [
                                'pending' => 'Beklemede',
                                'completed' => 'Tamamlandı',
                                'missed' => 'Kaçırıldı',
                                'cancelled' => 'İptal Edildi'
                            ];
                            ?>
                            <tr>
                                <td><?php echo formatDate($makeup['original_date']); ?></td>
                                <td><?php echo !empty($makeup['makeup_date']) ? formatDate($makeup['makeup_date']) : '<span class="text-muted">Belirlenmedi</span>'; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($makeup['original_classroom_name'] ?? 'Belirtilmemiş'); ?>
                                    <?php if (!empty($makeup['original_start_time'])): ?>
                                        <small class="d-block text-muted">
                                            <?php echo substr($makeup['original_start_time'], 0, 5) . ' - ' . substr($makeup['original_end_time'], 0, 5); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($makeup['topic_title'])): ?>
                                        <a href="#" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($makeup['topic_description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($makeup['topic_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Konu girilmemiş</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $makeup_badges[$makeup['status']]; ?>">
                                        <?php echo $makeup_labels[$makeup['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($makeup['notes'])): ?>
                                        <a href="#" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($makeup['notes']); ?>">
                                            <?php echo htmlspecialchars(mb_substr($makeup['notes'], 0, 20)) . (mb_strlen($makeup['notes']) > 20 ? '...' : ''); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo $makeup['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </a>
                                    <a href="delete.php?id=<?php echo $makeup['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu telafi dersini silmek istediğinizden emin misiniz?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
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

        // Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>