<?php
// modules/attendance/reports.php - Yoklama raporları sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Filtreleme parametreleri
$filter_classroom = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_student = isset($_GET['student']) ? intval($_GET['student']) : 0;

// Sınıfları getir
$classrooms_query = "SELECT * FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Dönemdeki aktif öğrencileri getir
$students_query = "SELECT s.id, s.first_name, s.last_name 
                  FROM students s
                  JOIN student_periods sp ON s.id = sp.student_id
                  WHERE sp.period_id = ? AND sp.status = 'active'
                  ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$current_period['id']])->fetchAll();

// Sınıf bazlı devamsızlık raporu
$classroom_report_query = "SELECT 
                          c.id, c.name as classroom_name,
                          COUNT(DISTINCT a.student_id) as total_students,
                          COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN l.id END) as total_lessons,
                          SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                          SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                          SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                          SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                          FROM classrooms c
                          LEFT JOIN lessons l ON c.id = l.classroom_id AND l.period_id = ?
                          LEFT JOIN attendance a ON l.id = a.lesson_id";

// Tarihe göre filtreleme
if (!empty($filter_month)) {
    $classroom_report_query .= " AND a.attendance_date LIKE ?";
    $params = [$current_period['id'], $filter_month . '%'];
} else {
    $params = [$current_period['id']];
}

// Sınıf filtrelemesi
if ($filter_classroom > 0) {
    $classroom_report_query .= " AND c.id = ?";
    $params[] = $filter_classroom;
}

$classroom_report_query .= " WHERE c.status = 'active'
                          GROUP BY c.id, c.name
                          ORDER BY c.name";

$classroom_reports = safeQuery($classroom_report_query, $params)->fetchAll();

// Öğrenci bazlı devamsızlık raporu
$student_report_query = "SELECT 
                        s.id, s.first_name, s.last_name,
                        COUNT(DISTINCT a.lesson_id) as total_lessons,
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
                        FROM students s
                        JOIN student_periods sp ON s.id = sp.student_id
                        LEFT JOIN attendance a ON s.id = a.student_id
                        LEFT JOIN lessons l ON a.lesson_id = l.id
                        WHERE sp.period_id = ? AND sp.status = 'active'";

// Tarihe göre filtreleme
$student_params = [$current_period['id']];
if (!empty($filter_month)) {
    $student_report_query .= " AND a.attendance_date LIKE ?";
    $student_params[] = $filter_month . '%';
}

// Sınıf filtrelemesi
if ($filter_classroom > 0) {
    $student_report_query .= " AND l.classroom_id = ?";
    $student_params[] = $filter_classroom;
}

// Öğrenci filtrelemesi
if ($filter_student > 0) {
    $student_report_query .= " AND s.id = ?";
    $student_params[] = $filter_student;
}

$student_report_query .= " GROUP BY s.id, s.first_name, s.last_name
                          ORDER BY absent_count DESC, s.first_name, s.last_name";

$student_reports = safeQuery($student_report_query, $student_params)->fetchAll();

$page_title = 'Yoklama Raporları - ' . $current_period['name'];
$chart = true; // Chart.js için gerekli
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yoklama Raporları
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Yoklama Sayfasına Dön
    </a>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="month" class="form-label">Ay</label>
                <input type="month" class="form-control" id="month" name="month" value="<?php echo $filter_month; ?>">
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label for="student" class="form-label">Öğrenci</label>
                <select class="form-select" id="student" name="student">
                    <option value="0">Tüm Öğrenciler</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo $filter_student == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
                <a href="reports.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Genel İstatistikler -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Genel İstatistikler</h5>
    </div>
    <div class="card-body">
        <?php
        // Genel yoklama istatistiklerini hesapla
        $total_present = 0;
        $total_absent = 0;
        $total_late = 0;
        $total_excused = 0;

        foreach ($classroom_reports as $report) {
            $total_present += $report['present_count'];
            $total_absent += $report['absent_count'];
            $total_late += $report['late_count'];
            $total_excused += $report['excused_count'];
        }

        $total_attendance = $total_present + $total_absent + $total_late + $total_excused;
        $attendance_percentage = $total_attendance > 0 ? round(($total_present / $total_attendance) * 100) : 0;
        ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Gelen</h5>
                        <h3 class="card-text mb-0"><?php echo $total_present; ?></h3>
                        <small><?php echo $total_attendance > 0 ? round(($total_present / $total_attendance) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Gelmeyen</h5>
                        <h3 class="card-text mb-0"><?php echo $total_absent; ?></h3>
                        <small><?php echo $total_attendance > 0 ? round(($total_absent / $total_attendance) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Geç Kalan</h5>
                        <h3 class="card-text mb-0"><?php echo $total_late; ?></h3>
                        <small><?php echo $total_attendance > 0 ? round(($total_late / $total_attendance) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">İzinli</h5>
                        <h3 class="card-text mb-0"><?php echo $total_excused; ?></h3>
                        <small><?php echo $total_attendance > 0 ? round(($total_excused / $total_attendance) * 100) : 0; ?>%</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <canvas id="generalAttendanceChart" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Sınıf Raporları -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Sınıf Bazlı Yoklama Raporu</h5>
    </div>
    <div class="card-body">
        <?php if (empty($classroom_reports)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen kriterlere uygun veri bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Sınıf</th>
                            <th>Öğrenci Sayısı</th>
                            <th>Ders Sayısı</th>
                            <th>Gelen</th>
                            <th>Gelmeyen</th>
                            <th>Geç Kalan</th>
                            <th>İzinli</th>
                            <th>Devam Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classroom_reports as $report): ?>
                            <?php
                            $classroom_total = $report['present_count'] + $report['absent_count'] + $report['late_count'] + $report['excused_count'];
                            $classroom_percentage = $classroom_total > 0 ? round(($report['present_count'] / $classroom_total) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['classroom_name']); ?></td>
                                <td><?php echo $report['total_students']; ?></td>
                                <td><?php echo $report['total_lessons']; ?></td>
                                <td class="text-success"><?php echo $report['present_count']; ?></td>
                                <td class="text-danger"><?php echo $report['absent_count']; ?></td>
                                <td class="text-warning"><?php echo $report['late_count']; ?></td>
                                <td class="text-info"><?php echo $report['excused_count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $classroom_percentage; ?>%;"><?php echo $classroom_percentage; ?>%</div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <canvas id="classroomAttendanceChart" height="100"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Öğrenci Bazlı Rapor -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Öğrenci Bazlı Yoklama Raporu</h5>
    </div>
    <div class="card-body">
        <?php if (empty($student_reports)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen kriterlere uygun veri bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Öğrenci</th>
                            <th>Toplam Ders</th>
                            <th>Gelen</th>
                            <th>Gelmeyen</th>
                            <th>Geç Kalan</th>
                            <th>İzinli</th>
                            <th>Devam Oranı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_reports as $report): ?>
                            <?php
                            $student_total = $report['present_count'] + $report['absent_count'] + $report['late_count'] + $report['excused_count'];
                            $student_percentage = $student_total > 0 ? round(($report['present_count'] / $student_total) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                                <td><?php echo $report['total_lessons']; ?></td>
                                <td class="text-success"><?php echo $report['present_count']; ?></td>
                                <td class="text-danger"><?php echo $report['absent_count']; ?></td>
                                <td class="text-warning"><?php echo $report['late_count']; ?></td>
                                <td class="text-info"><?php echo $report['excused_count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $student_percentage; ?>%;"><?php echo $student_percentage; ?>%</div>
                                    </div>
                                </td>
                                <td>
                                    <a href="student-attendance.php?student_id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-list-check"></i> Detay
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

        document.getElementById('classroom').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('student').addEventListener('change', function() {
            this.form.submit();
        });

        // Genel yoklama grafiği
        const generalCtx = document.getElementById('generalAttendanceChart').getContext('2d');
        new Chart(generalCtx, {
            type: 'pie',
            data: {
                labels: ['Geldi', 'Gelmedi', 'Geç Kaldı', 'İzinli'],
                datasets: [{
                    data: [<?php echo $total_present; ?>, <?php echo $total_absent; ?>, <?php echo $total_late; ?>, <?php echo $total_excused; ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(23, 162, 184, 0.7)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(220, 53, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(23, 162, 184, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Genel Yoklama Dağılımı'
                    }
                }
            }
        });

        <?php if (!empty($classroom_reports)): ?>
            // Sınıf bazlı yoklama grafiği
            const classroomCtx = document.getElementById('classroomAttendanceChart').getContext('2d');
            new Chart(classroomCtx, {
                type: 'bar',
                data: {
                    labels: [<?php foreach ($classroom_reports as $report) echo "'" . htmlspecialchars($report['classroom_name']) . "',"; ?>],
                    datasets: [{
                            label: 'Gelen',
                            data: [<?php foreach ($classroom_reports as $report) echo $report['present_count'] . ','; ?>],
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Gelmeyen',
                            data: [<?php foreach ($classroom_reports as $report) echo $report['absent_count'] . ','; ?>],
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Geç Kalan',
                            data: [<?php foreach ($classroom_reports as $report) echo $report['late_count'] . ','; ?>],
                            backgroundColor: 'rgba(255, 193, 7, 0.7)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'İzinli',
                            data: [<?php foreach ($classroom_reports as $report) echo $report['excused_count'] . ','; ?>],
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
                            text: 'Sınıf Bazlı Yoklama Dağılımı'
                        }
                    }
                }
            });
        <?php endif; ?>
    });
</script>

<?php require_once '../../includes/footer.php'; ?>