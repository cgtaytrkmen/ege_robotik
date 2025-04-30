<?php
// index.php - Admin ana sayfası (Haftalık Yapı İle)
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

// İstatistikleri getir
function getDashboardStats()
{
    // Aktif öğrenci sayısı
    $students_query = "SELECT COUNT(*) as count FROM students WHERE status = 'active'";
    $students_count = db()->query($students_query)->fetch()['count'];

    // Deneme dersi öğrenci sayısı
    $trial_query = "SELECT COUNT(*) as count FROM students WHERE status = 'trial'";
    $trial_count = db()->query($trial_query)->fetch()['count'];

    // Bu ayın geliri
    $current_month = date('Y-m');
    $income_query = "SELECT SUM(amount) as total FROM payments WHERE payment_date LIKE '$current_month%' AND status = 'paid'";
    $income = db()->query($income_query)->fetch()['total'] ?? 0;

    // Bu ayın gideri
    $expense_query = "SELECT SUM(amount) as total FROM expenses WHERE expense_date LIKE '$current_month%'";
    $expenses = db()->query($expense_query)->fetch()['total'] ?? 0;

    // Bugünkü yoklama durumu
    $today = date('Y-m-d');
    $attendance_query = "SELECT 
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
                         FROM attendance 
                         WHERE attendance_date = '$today'";
    $attendance = db()->query($attendance_query)->fetch();

    return [
        'students_count' => $students_count,
        'trial_count' => $trial_count,
        'income' => $income,
        'expenses' => $expenses,
        'net_income' => $income - $expenses,
        'attendance' => $attendance
    ];
}

// Yaklaşan ödemeleri getir
function getDuePayments($days = 7, $limit = 5)
{
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));
    $current_period = getCurrentPeriod();
    $period_id = $current_period['id'];

    // Kaydedilmiş ve yaklaşan ödemeler
    $query = "SELECT p.*, s.first_name, s.last_name 
              FROM payments p
              JOIN students s ON p.student_id = s.id
              WHERE p.period_id = ? AND p.status IN ('pending', 'late') 
              AND p.due_date BETWEEN ? AND ?
              ORDER BY p.due_date ASC
              LIMIT ?";

    return safeQuery($query, [$period_id, $today, $end_date, $limit])->fetchAll();
}

// Aktif haftayı getir
function getCurrentWeek()
{
    $current_period = getCurrentPeriod();
    if (!$current_period) return null;

    $today = date('Y-m-d');
    $query = "SELECT * FROM period_weeks 
              WHERE period_id = ? AND ? BETWEEN start_date AND end_date
              LIMIT 1";

    return safeQuery($query, [$current_period['id'], $today])->fetch();
}

// Haftanın derslerini getir
function getWeekLessons($week)
{
    if (!$week) return [];

    $query = "SELECT l.*, c.name as classroom_name, 
              CASE l.day
                WHEN 'Monday' THEN 'Pazartesi'
                WHEN 'Tuesday' THEN 'Salı'
                WHEN 'Wednesday' THEN 'Çarşamba'
                WHEN 'Thursday' THEN 'Perşembe'
                WHEN 'Friday' THEN 'Cuma'
                WHEN 'Saturday' THEN 'Cumartesi'
                WHEN 'Sunday' THEN 'Pazar'
              END as day_turkish,
              (SELECT COUNT(*) FROM student_classrooms sc WHERE sc.classroom_id = l.classroom_id AND sc.status = 'active') as student_count
              FROM lessons l
              JOIN classrooms c ON l.classroom_id = c.id
              WHERE l.period_id = ? AND l.status = 'active'
              ORDER BY FIELD(l.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), l.start_time";

    return safeQuery($query, [$week['period_id']])->fetchAll();
}

// Hafta bazlı yoklama özeti
function getWeekAttendance($week)
{
    if (!$week) return null;

    $query = "SELECT 
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused,
                COUNT(*) as total
              FROM attendance a
              WHERE a.period_week_id = ? OR (a.attendance_date BETWEEN ? AND ?)";

    return safeQuery($query, [$week['id'], $week['start_date'], $week['end_date']])->fetch();
}

// Hafta bazlı ödemeler
function getWeekPayments($week)
{
    if (!$week) return null;

    $query = "SELECT SUM(amount) as total, COUNT(*) as count
              FROM payments
              WHERE (period_week_id = ? OR (payment_date BETWEEN ? AND ?))
              AND status = 'paid'";

    return safeQuery($query, [$week['id'], $week['start_date'], $week['end_date']])->fetch();
}

$stats = getDashboardStats();
$due_payments = getDuePayments();
$current_week = getCurrentWeek();
$week_lessons = getWeekLessons($current_week);
$week_attendance = getWeekAttendance($current_week);
$week_payments = getWeekPayments($current_week);

$page_title = 'Yönetim Paneli';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Aktif Öğrenci</h5>
                <h2 class="card-text"><?php echo $stats['students_count']; ?></h2>
                <i class="bi bi-people-fill float-end display-6"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Deneme Dersi</h5>
                <h2 class="card-text"><?php echo $stats['trial_count']; ?></h2>
                <i class="bi bi-person-plus-fill float-end display-6"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Aylık Gelir</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['income']); ?></h2>
                <i class="bi bi-cash-stack float-end display-6"></i>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-<?php echo $stats['net_income'] >= 0 ? 'success' : 'danger'; ?> text-white">
            <div class="card-body">
                <h5 class="card-title">Net Kar</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['net_income']); ?></h2>
                <i class="bi bi-graph-up-arrow float-end display-6"></i>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <!-- Sol Sütun -->
    <div class="col-md-8">
        <!-- Aktif Hafta Bilgisi -->
        <?php if ($current_week): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <?php echo htmlspecialchars($current_week['name']); ?>
                        <small>(<?php echo formatDate($current_week['start_date']); ?> - <?php echo formatDate($current_week['end_date']); ?>)</small>
                        <?php if ($current_week['is_free']): ?>
                            <span class="badge bg-warning text-dark">Ücretsiz Hafta</span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <!-- Hafta Yoklama Özeti -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h6 class="card-title mb-0">Haftalık Yoklama</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($week_attendance && $week_attendance['total'] > 0): ?>
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <div class="h3 mb-0 text-success"><?php echo $week_attendance['present']; ?></div>
                                                <small class="text-muted">Katıldı</small>
                                            </div>
                                            <div class="col-3">
                                                <div class="h3 mb-0 text-danger"><?php echo $week_attendance['absent']; ?></div>
                                                <small class="text-muted">Gelmedi</small>
                                            </div>
                                            <div class="col-3">
                                                <div class="h3 mb-0 text-warning"><?php echo $week_attendance['late']; ?></div>
                                                <small class="text-muted">Geç</small>
                                            </div>
                                            <div class="col-3">
                                                <div class="h3 mb-0 text-info"><?php echo $week_attendance['excused']; ?></div>
                                                <small class="text-muted">İzinli</small>
                                            </div>
                                        </div>

                                        <?php
                                        $attendance_rate = 0;
                                        if ($week_attendance['total'] > 0) {
                                            $attendance_rate = round((($week_attendance['present'] + $week_attendance['late']) / $week_attendance['total']) * 100);
                                        }
                                        ?>

                                        <div class="mt-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Devam Oranı:</span>
                                                <span><?php echo $attendance_rate; ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-success" role="progressbar"
                                                    style="width: <?php echo $attendance_rate; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle"></i> Bu hafta için henüz yoklama kaydı bulunmuyor.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Hafta Ödeme Özeti -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-success text-white">
                                    <h6 class="card-title mb-0">Haftalık Tahsilat</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($week_payments && $week_payments['count'] > 0): ?>
                                        <div class="text-center">
                                            <div class="h2 mb-0"><?php echo formatMoney($week_payments['total']); ?></div>
                                            <p class="text-muted mb-0">
                                                <?php echo $week_payments['count']; ?> ödeme
                                            </p>
                                        </div>

                                        <hr>

                                        <div class="d-grid">
                                            <a href="modules/finance/payments.php?week_id=<?php echo $current_week['id']; ?>" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-cash"></i> Ödeme Detayları
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info mb-0">
                                            <i class="bi bi-info-circle"></i> Bu hafta için henüz ödeme kaydı bulunmuyor.
                                        </div>
                                        <div class="d-grid mt-2">
                                            <a href="modules/finance/add-payment.php?week_id=<?php echo $current_week['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="bi bi-plus-circle"></i> Ödeme Ekle
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hafta Ders Programı -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Haftalık Ders Programı</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($week_lessons)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Gün</th>
                                                <th>Saat</th>
                                                <th>Sınıf</th>
                                                <th>Öğrenci</th>
                                                <th>İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($week_lessons as $lesson): ?>
                                                <?php
                                                $today = date('l'); // İngilizce gün adı (Monday, Tuesday, vb.)
                                                $is_today = ($today === $lesson['day']);
                                                $row_class = $is_today ? 'table-primary' : '';
                                                ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td><?php echo $lesson['day_turkish']; ?></td>
                                                    <td><?php echo substr($lesson['start_time'], 0, 5); ?> - <?php echo substr($lesson['end_time'], 0, 5); ?></td>
                                                    <td><?php echo htmlspecialchars($lesson['classroom_name']); ?></td>
                                                    <td><?php echo $lesson['student_count']; ?> öğrenci</td>
                                                    <td>
                                                        <?php if ($is_today): ?>
                                                            <a href="modules/attendance/take.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-success">
                                                                <i class="bi bi-check2-square"></i> Yoklama
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="modules/schedule/view.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-info">
                                                                <i class="bi bi-eye"></i> Detay
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Bu dönem için henüz ders programı oluşturulmamış.
                                    <a href="modules/schedule/" class="alert-link">Ders programı oluşturmak için tıklayın</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Aktif hafta bulunamadı.
                        <a href="modules/weeks/" class="alert-link">Hafta yapısını oluşturmak için tıklayın</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Hızlı Erişim -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Hızlı Erişim</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <a href="modules/students/add.php" class="btn btn-outline-primary btn-lg w-100">
                            <i class="bi bi-person-plus"></i> Yeni Öğrenci
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="modules/attendance/today.php" class="btn btn-outline-success btn-lg w-100">
                            <i class="bi bi-calendar-check"></i> Yoklama Al
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="modules/finance/add-payment.php" class="btn btn-outline-danger btn-lg w-100">
                            <i class="bi bi-cash"></i> Ödeme Ekle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Sütun -->
    <div class="col-md-4">
        <!-- Yaklaşan Ödemeler -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Yaklaşan Ödemeler</h5>
                <a href="modules/finance/payments.php?filter=due" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
            </div>
            <div class="card-body">
                <?php if (count($due_payments) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($due_payments as $payment): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php
                                            echo formatDate($payment['due_date']);
                                            $due_date = strtotime($payment['due_date']);
                                            $today = strtotime(date('Y-m-d'));
                                            $diff_days = floor(($due_date - $today) / (60 * 60 * 24));
                                            if ($diff_days <= 3) {
                                                echo ' <span class="badge bg-danger">' . $diff_days . ' gün</span>';
                                            } else {
                                                echo ' <span class="badge bg-warning text-dark">' . $diff_days . ' gün</span>';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-danger"><?php echo formatMoney($payment['amount']); ?></span>
                                </div>
                                <div class="mt-2">
                                    <a href="modules/finance/add-payment.php?payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-cash"></i> Ödeme Al
                                    </a>
                                    <a href="modules/finance/edit-payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Yaklaşan ödeme bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bugünkü Yoklama -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Bugünkü Yoklama</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-center">
                        <h3 class="mb-0 text-success"><?php echo $stats['attendance']['present'] ?? 0; ?></h3>
                        <small class="text-muted">Gelen</small>
                    </div>
                    <div class="text-center">
                        <h3 class="mb-0 text-danger"><?php echo $stats['attendance']['absent'] ?? 0; ?></h3>
                        <small class="text-muted">Gelmeyen</small>
                    </div>
                    <div class="text-center">
                        <h3 class="mb-0 text-primary">
                            <?php
                            $total = ($stats['attendance']['present'] ?? 0) + ($stats['attendance']['absent'] ?? 0);
                            echo $total > 0 ? round(($stats['attendance']['present'] ?? 0) / $total * 100) : 0;
                            ?>%
                        </h3>
                        <small class="text-muted">Devam</small>
                    </div>
                </div>
                <a href="modules/attendance/index.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-list-check"></i> Yoklama Sayfasına Git
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>