<?php
// modules/attendance/student-attendance.php - Öğrenci yoklama detayları
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Öğrenci kontrolü
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/attendance/reports.php');
}

// Filtreleme parametreleri
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Öğrenci bilgisini getir
$student_query = "SELECT s.*, p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone as parent_phone
                 FROM students s
                 LEFT JOIN student_parents sp ON s.id = sp.student_id AND sp.is_primary = 1
                 LEFT JOIN parents p ON sp.parent_id = p.id
                 WHERE s.id = ?";
$student = safeQuery($student_query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/attendance/reports.php');
}

// Öğrencinin yoklama kayıtları
$attendance_query = "SELECT a.*, l.day, l.start_time, l.end_time, c.name as classroom_name
                    FROM attendance a
                    JOIN lessons l ON a.lesson_id = l.id
                    JOIN classrooms c ON l.classroom_id = c.id
                    WHERE a.student_id = ? AND l.period_id = ?";
$params = [$student_id, $current_period['id']];

// Tarihe göre filtreleme
if (!empty($filter_month)) {
    $attendance_query .= " AND a.attendance_date LIKE ?";
    $params[] = $filter_month . '%';
}

// Duruma göre filtreleme
if (!empty($filter_status)) {
    $attendance_query .= " AND a.status = ?";
    $params[] = $filter_status;
}

$attendance_query .= " ORDER BY a.attendance_date DESC, l.start_time DESC";
$attendance_records = safeQuery($attendance_query, $params)->fetchAll();

// Katılım istatistikleri
$total_lessons = count($attendance_records);
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$excused_count = 0;

foreach ($attendance_records as $record) {
    if ($record['status'] === 'present') {
        $present_count++;
    } elseif ($record['status'] === 'absent') {
        $absent_count++;
    } elseif ($record['status'] === 'late') {
        $late_count++;
    } elseif ($record['status'] === 'excused') {
        $excused_count++;
    }
}

$attendance_percentage = $total_lessons > 0 ? round(($present_count / $total_lessons) * 100) : 0;

// Aylık katılım dağılımı için veri hazırla
$monthly_data = [];
foreach ($attendance_records as $record) {
    $month = date('Y-m', strtotime($record['attendance_date']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
            'total' => 0
        ];
    }

    $monthly_data[$month][$record['status']]++;
    $monthly_data[$month]['total']++;
}

// Ayları tarihe göre sırala
ksort($monthly_data);

$page_title = 'Öğrenci Yoklama Detayı - ' . $student['first_name'] . ' ' . $student['last_name'];
$chart = true; // Chart.js için
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Yoklama Detayı
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <a href="reports.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Raporlara Dön
    </a>
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
                <p><strong>Veli:</strong> <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?></p>
                <p><strong>Telefon:</strong> <?php echo formatPhone($student['parent_phone']); ?></p>
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

<!-- Devam Durumu İstatistikleri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Devam Durumu İstatistikleri</h5>
    </div>
    <div class="card-body">
        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h6 class="card-title">Toplam Ders</h6>
                        <h2 class="card-text"><?php echo $total_lessons; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Katıldığı</h6>
                        <h2 class="card-text"><?php echo $present_count; ?></h2>
                        <small><?php echo $total_lessons > 0 ? round(($present_count / $total_lessons) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h6 class="card-title">Katılmadığı</h6>
                        <h2 class="card-text"><?php echo $absent_count; ?></h2>
                        <small><?php echo $total_lessons > 0 ? round(($absent_count / $total_lessons) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h6 class="card-title">Geç Kaldığı</h6>
                        <h2 class="card-text"><?php echo $late_count; ?></h2>
                        <small><?php echo $total_lessons > 0 ? round(($late_count / $total_lessons) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Devam Yüzdesi Çubuğu -->
        <div class="mb-4">
            <h6>Genel Devam Oranı</h6>
            <div class="progress" style="height: 25px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendance_percentage; ?>%;" aria-valuenow="<?php echo $attendance_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?php echo $attendance_percentage; ?>%
                </div>
            </div>
        </div>

        <!-- Aylık Katılım Grafiği -->
        <h6>Aylık Katılım Dağılımı</h6>
        <canvas id="monthlyAttendanceChart" height="100"></canvas>
    </div>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
            <div class="col-md-4">
                <label for="month" class="form-label">Ay</label>
                <input type="month" class="form-control" id="month" name="month" value="<?php echo $filter_month; ?>">
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Geldi</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Gelmedi</option>
                    <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Geç Kaldı</option>
                    <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>İzinli</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
                <a href="student-attendance.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Yoklama Kayıtları -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Yoklama Kayıtları</h5>
    </div>
    <div class="card-body">
        <?php if (empty($attendance_records)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen kriterlere uygun yoklama kaydı bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Gün</th>
                            <th>Saat</th>
                            <th>Sınıf</th>
                            <th>Durum</th>
                            <th>Notlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo formatDate($record['attendance_date']); ?></td>
                                <td><?php echo $record['day']; ?></td>
                                <td><?php echo substr($record['start_time'], 0, 5) . ' - ' . substr($record['end_time'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                                <td>
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
                                    $badge_class = $status_badges[$record['status']] ?? 'secondary';
                                    $label = $status_labels[$record['status']] ?? $record['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
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
<!-- Telafi Dersleri -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Telafi Dersleri</h5>
    </div>
    <div class="card-body">
        <?php
        // Öğrencinin telafi derslerini getir
        $makeup_query = "SELECT ml.*, 
                        l.day as original_day, l.start_time as original_start_time, l.end_time as original_end_time,
                        c.name as original_classroom_name,
                        t.topic_title
                        FROM makeup_lessons ml
                        LEFT JOIN lessons l ON ml.original_lesson_id = l.id
                        LEFT JOIN classrooms c ON l.classroom_id = c.id
                        LEFT JOIN topics t ON ml.topic_id = t.id
                        WHERE ml.student_id = ?
                        ORDER BY ml.original_date DESC";
        $makeup_lessons = safeQuery($makeup_query, [$student_id])->fetchAll();
        ?>

        <?php if (empty($makeup_lessons)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu öğrenci için telafi dersi kaydı bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Orijinal Tarih</th>
                            <th>Telafi Tarihi</th>
                            <th>Sınıf/Saat</th>
                            <th>Konu</th>
                            <th>Durum</th>
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
                                    <?php echo !empty($makeup['topic_title']) ? htmlspecialchars($makeup['topic_title']) : '<span class="text-muted">Belirtilmemiş</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $makeup_badges[$makeup['status']]; ?>">
                                        <?php echo $makeup_labels[$makeup['status']]; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../makeup/edit.php?id=<?php echo $makeup['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Düzenle
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
        // Otomatik form submit
        document.getElementById('month').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        // Aylık katılım grafiği
        const monthlyCtx = document.getElementById('monthlyAttendanceChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($monthly_data as $month => $data) echo "'" . $month . "',"; ?>],
                datasets: [{
                        label: 'Geldi',
                        data: [<?php foreach ($monthly_data as $data) echo $data['present'] . ','; ?>],
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Gelmedi',
                        data: [<?php foreach ($monthly_data as $data) echo $data['absent'] . ','; ?>],
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Geç Kaldı',
                        data: [<?php foreach ($monthly_data as $data) echo $data['late'] . ','; ?>],
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'İzinli',
                        data: [<?php foreach ($monthly_data as $data) echo $data['excused'] . ','; ?>],
                        backgroundColor: 'rgba(23, 162, 184, 0.7)',
                        borderColor: 'rgba(23, 162, 184, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Aylık Katılım Dağılımı'
                    }
                }
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>