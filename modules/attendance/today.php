<?php
// modules/attendance/today.php - Bugünkü dersler için yoklama sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Bugünün tarihi
$today_date = date('Y-m-d');
$day_of_week = date('l'); // Pazartesi, Salı, vb.

// Bugünkü dersleri getir
$lessons_query = "SELECT l.*, c.name as classroom_name, c.id as classroom_id
                 FROM lessons l
                 JOIN classrooms c ON l.classroom_id = c.id
                 WHERE l.day = ? AND l.period_id = ? AND l.status = 'active'
                 ORDER BY l.start_time";
$lessons = safeQuery($lessons_query, [$day_of_week, $current_period['id']])->fetchAll();

// Bugün alınmış yoklamaları kontrol et
$attendance_query = "SELECT DISTINCT lesson_id FROM attendance WHERE attendance_date = ?";
$taken_attendance = safeQuery($attendance_query, [$today_date])->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Bugünkü Yoklama - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Bugünkü Yoklamalar
        <small class="text-muted">(<?php echo formatDate($today_date); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Yoklama Sayfasına Dön
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Bugünkü Dersler</h5>
    </div>
    <div class="card-body">
        <?php if (empty($lessons)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bugün için planlanmış ders bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Saat</th>
                            <th>Sınıf</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($lesson['classroom_name']); ?></td>
                                <td>
                                    <?php
                                    $current_time = date('H:i:s');
                                    $attendance_taken = in_array($lesson['id'], $taken_attendance);

                                    if ($attendance_taken):
                                        echo '<span class="badge bg-success">Yoklama Alındı</span>';
                                    elseif ($current_time < $lesson['start_time']):
                                        echo '<span class="badge bg-info">Yaklaşan</span>';
                                    elseif ($current_time >= $lesson['start_time'] && $current_time <= $lesson['end_time']):
                                        echo '<span class="badge bg-warning">Devam Ediyor</span>';
                                    else:
                                        echo '<span class="badge bg-danger">Yoklama Alınmadı</span>';
                                    endif;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($attendance_taken): ?>
                                        <a href="view.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $today_date; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Görüntüle
                                        </a>
                                        <a href="edit.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $today_date; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </a>
                                    <?php else: ?>
                                        <a href="take.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $today_date; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-list-check"></i> Yoklama Al
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

<!-- Günün Yoklama Özeti -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Bugünkü Yoklama Özeti</h5>
    </div>
    <div class="card-body">
        <?php
        $summary_query = "SELECT 
                         COUNT(a.id) as total_records,
                         COUNT(DISTINCT a.student_id) as total_students,
                         SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                         SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                         SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                         SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                         FROM attendance a
                         WHERE a.attendance_date = ?";
        $summary = safeQuery($summary_query, [$today_date])->fetch();

        $total_attendance = $summary['total_records'] ?? 0;
        $attendance_percentage = $total_attendance > 0 ?
            round(($summary['present_count'] / $total_attendance) * 100) : 0;
        ?>

        <?php if ($total_attendance > 0): ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title">Toplam Öğrenci</h6>
                            <h3 class="card-text"><?php echo $summary['total_students']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Gelen</h6>
                            <h3 class="card-text"><?php echo $summary['present_count']; ?></h3>
                            <small><?php echo $attendance_percentage; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h6 class="card-title">Gelmeyen</h6>
                            <h3 class="card-text"><?php echo $summary['absent_count']; ?></h3>
                            <small><?php echo $total_attendance > 0 ? round(($summary['absent_count'] / $total_attendance) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h6 class="card-title">Geç Kalan</h6>
                            <h3 class="card-text"><?php echo $summary['late_count']; ?></h3>
                            <small><?php echo $total_attendance > 0 ? round(($summary['late_count'] / $total_attendance) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Devam Yüzdesi Çubuğu -->
            <div class="mt-4">
                <h6>Genel Devam Oranı</h6>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendance_percentage; ?>%;" aria-valuenow="<?php echo $attendance_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo $attendance_percentage; ?>%
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bugün için henüz yoklama kaydı bulunmuyor.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>