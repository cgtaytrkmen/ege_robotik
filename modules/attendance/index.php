<?php
// modules/attendance/index.php - Yoklama ana sayfası (düzeltilmiş versiyon)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Tarih filtresi
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_classroom = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;

// Sınıfları getir
$classrooms_query = "SELECT * FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Günün derslerini getir
$lessons_query = "SELECT l.*, c.name as classroom_name 
                 FROM lessons l 
                 JOIN classrooms c ON l.classroom_id = c.id 
                 WHERE l.period_id = ? AND l.status = 'active'";
$params = [$current_period['id']];

// Sınıf filtresi varsa ekle
if ($filter_classroom > 0) {
    $lessons_query .= " AND l.classroom_id = ?";
    $params[] = $filter_classroom;
}

// Gün filtrelemesi
if (date('Y-m-d') == $filter_date) {
    // Bugünün günü
    $day_of_week = date('l'); // Pazartesi, Salı, vb.
    $lessons_query .= " AND l.day = ?";
    $params[] = $day_of_week;
} else {
    // Belirli bir tarih için haftanın gününü bul
    $day_of_week = date('l', strtotime($filter_date)); // Pazartesi, Salı, vb.
    $lessons_query .= " AND l.day = ?";
    $params[] = $day_of_week;
}

$lessons_query .= " ORDER BY l.start_time";
$lessons = safeQuery($lessons_query, $params)->fetchAll();

// Sınıf ID'leri ve ders ID'lerini al
$lesson_ids = array_column($lessons, 'id');
$classroom_ids = array_column($lessons, 'classroom_id');

// Alınmış yoklamaları getir
$attendance_query = "SELECT a.*, l.start_time, l.end_time, l.day, c.name as classroom_name, l.classroom_id
                   FROM attendance a
                   JOIN lessons l ON a.lesson_id = l.id
                   JOIN classrooms c ON l.classroom_id = c.id
                   WHERE a.attendance_date = ?";
$params_attendance = [$filter_date];

// Ders ID filtreleri varsa ekle
if (!empty($lesson_ids)) {
    $placeholders = implode(',', array_fill(0, count($lesson_ids), '?'));
    $attendance_query .= " AND a.lesson_id IN ($placeholders)";
    $params_attendance = array_merge($params_attendance, $lesson_ids);
}

$attendance_query .= " GROUP BY a.lesson_id ORDER BY l.start_time";
$attendance_records = safeQuery($attendance_query, $params_attendance)->fetchAll();

// Alınmış yoklama ID'lerini al
$taken_lesson_ids = array_column($attendance_records, 'lesson_id');

$page_title = 'Yoklama Yönetimi - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yoklama Yönetimi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="reports.php" class="btn btn-info">
        <i class="bi bi-file-earmark-bar-graph"></i> Yoklama Raporları
    </a>
</div>

<!-- Tarih ve Sınıf Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <label for="date" class="form-label">Tarih</label>
                <input type="date" class="form-control" id="date" name="date" value="<?php echo $filter_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="classroom" class="form-label">Sınıf</label>
                <select class="form-select" id="classroom" name="classroom">
                    <option value="0">Tüm Sınıflar</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?php echo $classroom['id']; ?>" <?php echo $filter_classroom == $classroom['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classroom['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
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

<!-- Günün Dersleri -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><?php echo formatDate($filter_date); ?> - <?php echo $day_of_week; ?></h5>
    </div>
    <div class="card-body">
        <?php if (empty($lessons)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen tarihte ders bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Sınıf</th>
                            <th>Saat</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lesson['classroom_name']); ?></td>
                                <td><?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></td>
                                <td>
                                    <?php
                                    $current_time = date('H:i:s');
                                    $is_today = date('Y-m-d') == $filter_date;

                                    if (in_array($lesson['id'], $taken_lesson_ids)):
                                        echo '<span class="badge bg-success">Yoklama Alındı</span>';
                                    elseif (!$is_today):
                                        if (strtotime($filter_date) > strtotime(date('Y-m-d'))):
                                            echo '<span class="badge bg-warning">Gelecek Ders</span>';
                                        else:
                                            echo '<span class="badge bg-danger">Yoklama Alınmadı</span>';
                                        endif;
                                    elseif ($current_time < $lesson['start_time']):
                                        echo '<span class="badge bg-info">Yaklaşan</span>';
                                    elseif ($current_time >= $lesson['start_time'] && $current_time <= $lesson['end_time']):
                                        echo '<span class="badge bg-warning">Devam Ediyor</span>';
                                    else:
                                        echo '<span class="badge bg-secondary">Tamamlandı</span>';
                                    endif;
                                    ?>
                                </td>
                                <td>
                                    <?php if (in_array($lesson['id'], $taken_lesson_ids)): ?>
                                        <a href="view.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $filter_date; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i> Görüntüle
                                        </a>
                                        <a href="edit.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $filter_date; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </a>
                                    <?php else: ?>
                                        <a href="take.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo $filter_date; ?>" class="btn btn-sm btn-primary">
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

<!-- Son Alınan Yoklamalar -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Son Alınan Yoklamalar</h5>
    </div>
    <div class="card-body">
        <?php
        $recent_query = "SELECT a.attendance_date, l.day, l.start_time, l.id as lesson_id, 
                         c.name as classroom_name, c.id as classroom_id,
                         COUNT(a.id) as total_students,
                         SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                         SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count
                         FROM attendance a
                         JOIN lessons l ON a.lesson_id = l.id
                         JOIN classrooms c ON l.classroom_id = c.id
                         WHERE l.period_id = ?
                         GROUP BY a.attendance_date, a.lesson_id
                         ORDER BY a.attendance_date DESC, l.start_time DESC
                         LIMIT 10";
        $recent_records = safeQuery($recent_query, [$current_period['id']])->fetchAll();
        ?>

        <?php if (empty($recent_records)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Henüz yoklama kaydı bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Gün</th>
                            <th>Saat</th>
                            <th>Sınıf</th>
                            <th>Katılım</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_records as $record): ?>
                            <tr>
                                <td><?php echo formatDate($record['attendance_date']); ?></td>
                                <td><?php echo $record['day']; ?></td>
                                <td><?php echo substr($record['start_time'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                                <td>
                                    <span class="text-success"><?php echo $record['present_count']; ?></span> /
                                    <span class="text-danger"><?php echo $record['absent_count']; ?></span> /
                                    <span class="text-primary"><?php echo $record['total_students']; ?></span>
                                </td>
                                <td>
                                    <a href="view.php?lesson_id=<?php echo $record['lesson_id']; ?>&date=<?php echo $record['attendance_date']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Görüntüle
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
        // Tarihi değiştirdiğinde otomatik submit
        document.getElementById('date').addEventListener('change', function() {
            this.form.submit();
        });

        // Sınıfı değiştirdiğinde otomatik submit
        document.getElementById('classroom').addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>