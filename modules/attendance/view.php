<?php
// modules/attendance/view.php - Yoklama görüntüleme sayfası (Konu bilgisi eklenmiş)
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

// Yoklama bilgilerini getir
$attendance_query = "SELECT a.*, s.first_name, s.last_name, s.birth_date, p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone as parent_phone
                   FROM attendance a
                   JOIN students s ON a.student_id = s.id
                   LEFT JOIN student_parents sp ON s.id = sp.student_id AND sp.is_primary = 1
                   LEFT JOIN parents p ON sp.parent_id = p.id
                   WHERE a.lesson_id = ? AND a.attendance_date = ?
                   ORDER BY s.first_name, s.last_name";
$attendance_records = safeQuery($attendance_query, [$lesson_id, $attendance_date])->fetchAll();

// İşlenen konu bilgisini getir
$topic_query = "SELECT * FROM topics WHERE lesson_id = ? AND date = ? LIMIT 1";
$topic = safeQuery($topic_query, [$lesson_id, $attendance_date])->fetch();

// Gelmeyen öğrencilerin sayısı
$absent_count = 0;
foreach ($attendance_records as $record) {
    if ($record['status'] === 'absent') {
        $absent_count++;
    }
}

$page_title = 'Yoklama Görüntüle - ' . $lesson['classroom_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yoklama Görüntüle
        <small class="text-muted">(<?php echo htmlspecialchars($lesson['classroom_name']); ?>)</small>
    </h2>
    <div>
        <a href="edit.php?lesson_id=<?php echo $lesson_id; ?>&date=<?php echo $attendance_date; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Düzenle
        </a>
        <a href="index.php?date=<?php echo $attendance_date; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Ders Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <p><strong>Tarih:</strong> <?php echo formatDate($attendance_date); ?></p>
            </div>
            <div class="col-md-3">
                <p><strong>Gün:</strong> <?php echo $lesson['day']; ?></p>
            </div>
            <div class="col-md-3">
                <p><strong>Saat:</strong> <?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></p>
            </div>
            <div class="col-md-3">
                <p>
                    <strong>Katılım:</strong>
                    <?php
                    $present_count = count($attendance_records) - $absent_count;
                    $attendance_percentage = count($attendance_records) > 0 ? round(($present_count / count($attendance_records)) * 100) : 0;
                    echo $present_count . '/' . count($attendance_records) . ' (' . $attendance_percentage . '%)';
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- İşlenen Konu Bilgisi -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">İşlenen Konu</h5>
    </div>
    <div class="card-body">
        <?php if ($topic): ?>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Konu Başlığı:</strong> <?php echo htmlspecialchars($topic['topic_title']); ?></p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Durum:</strong>
                        <?php
                        $status_badges = [
                            'completed' => 'success',
                            'planned' => 'info',
                            'cancelled' => 'danger'
                        ];
                        $status_labels = [
                            'completed' => 'Tamamlandı',
                            'planned' => 'Planlandı',
                            'cancelled' => 'İptal Edildi'
                        ];
                        $badge_class = $status_badges[$topic['status']] ?? 'secondary';
                        $label = $status_labels[$topic['status']] ?? $topic['status'];
                        ?>
                        <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                    </p>
                </div>
                <?php if (!empty($topic['description'])): ?>
                    <div class="col-md-12">
                        <p><strong>Açıklama:</strong></p>
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($topic['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <a href="../topics/view.php?id=<?php echo $topic['id']; ?>" class="btn btn-info btn-sm">
                    <i class="bi bi-book"></i> Konu Detaylarını Görüntüle
                </a>
                <a href="../topics/edit.php?id=<?php echo $topic['id']; ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-pencil"></i> Konu Düzenle
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu ders için konu bilgisi girilmemiş.
                <a href="edit.php?lesson_id=<?php echo $lesson_id; ?>&date=<?php echo $attendance_date; ?>" class="alert-link">Konu eklemek için tıklayın</a>.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Öğrenci Yoklama Listesi</h5>
    </div>
    <div class="card-body">
        <?php if (empty($attendance_records)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Bu ders için yoklama kaydı bulunamadı!
            </div>
        <?php else: ?>
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Gelen</h5>
                            <h3 class="card-text mb-0"><?php echo $present_count; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Gelmeyen</h5>
                            <h3 class="card-text mb-0"><?php echo $absent_count; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Geç Kalan</h5>
                            <h3 class="card-text mb-0">
                                <?php
                                $late_count = 0;
                                foreach ($attendance_records as $record) {
                                    if ($record['status'] === 'late') {
                                        $late_count++;
                                    }
                                }
                                echo $late_count;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">İzinli</h5>
                            <h3 class="card-text mb-0">
                                <?php
                                $excused_count = 0;
                                foreach ($attendance_records as $record) {
                                    if ($record['status'] === 'excused') {
                                        $excused_count++;
                                    }
                                }
                                echo $excused_count;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Öğrenci</th>
                            <th>Yaş</th>
                            <th>Veli</th>
                            <th>İletişim</th>
                            <th>Telafi</th>
                            <th>Notlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $index => $record): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="../../modules/students/view.php?id=<?php echo $record['student_id']; ?>">
                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo calculateAge($record['birth_date']); ?></td>
                                <td><?php echo htmlspecialchars($record['parent_first_name'] . ' ' . $record['parent_last_name']); ?></td>
                                <td><?php echo formatPhone($record['parent_phone']); ?></td>
                                <td>
                                    <?php if ($record['status'] === 'absent' || $record['status'] === 'late' || $record['status'] === 'excused'): ?>
                                        <?php
                                        // Check if there's already a makeup lesson for this student on this date
                                        $check_makeup_query = "SELECT id, status FROM makeup_lessons 
                              WHERE student_id = ? AND original_lesson_id = ? AND original_date = ?";
                                        $existing_makeup = safeQuery($check_makeup_query, [
                                            $record['student_id'],
                                            $lesson_id,
                                            $attendance_date
                                        ])->fetch();
                                        ?>

                                        <?php if ($existing_makeup): ?>
                                            <a href="../makeup/edit.php?id=<?php echo $existing_makeup['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i> Telafi Düzenle
                                            </a>
                                        <?php else: ?>
                                            <a href="../makeup/add.php?student_id=<?php echo $record['student_id']; ?>&lesson_id=<?php echo $lesson_id; ?>&date=<?php echo $attendance_date; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-calendar-plus"></i> Telafi Ekle
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>