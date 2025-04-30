<?php
// modules/students/view.php - Öğrenci detay görüntüleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/students/index.php');
}

// Öğrenci bilgilerini getir
$query = "SELECT s.*, 
          TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) as age,
          p.first_name as parent_first_name, p.last_name as parent_last_name, 
          p.phone as parent_phone, p.email as parent_email
          FROM students s
          LEFT JOIN student_parents sp ON s.id = sp.student_id AND sp.is_primary = 1
          LEFT JOIN parents p ON sp.parent_id = p.id
          WHERE s.id = ?";
$student = safeQuery($query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Dönemdeki kayıt durumunu kontrol et
$period_query = "SELECT * FROM student_periods WHERE student_id = ? AND period_id = ?";
$period_registration = safeQuery($period_query, [$student_id, $current_period['id']])->fetch();

// Öğrencinin atandığı sınıfları getir
$classes_query = "SELECT c.*, sc.enrollment_date
                 FROM classrooms c
                 JOIN student_classrooms sc ON c.id = sc.classroom_id
                 WHERE sc.student_id = ? AND sc.status = 'active'
                 ORDER BY c.name";
$classes = safeQuery($classes_query, [$student_id])->fetchAll();

// Öğrencinin yoklamalarını getir
$attendance_query = "SELECT a.*, l.day, l.start_time, l.end_time, c.name as classroom_name
                    FROM attendance a
                    JOIN lessons l ON a.lesson_id = l.id
                    JOIN classrooms c ON l.classroom_id = c.id
                    WHERE a.student_id = ? AND a.attendance_date BETWEEN ? AND ?
                    ORDER BY a.attendance_date DESC, l.start_time";
$attendance_params = [$student_id, $current_period['start_date'], $current_period['end_date']];
$attendance_records = safeQuery($attendance_query, $attendance_params)->fetchAll();

// Yoklama istatistikleri
$total_lessons = count($attendance_records);
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$excused_count = 0;

foreach ($attendance_records as $record) {
    if ($record['status'] == 'present') {
        $present_count++;
    } elseif ($record['status'] == 'absent') {
        $absent_count++;
    } elseif ($record['status'] == 'late') {
        $late_count++;
    } elseif ($record['status'] == 'excused') {
        $excused_count++;
    }
}

$attendance_rate = ($total_lessons > 0) ?
    round((($present_count + $late_count) / $total_lessons) * 100) : 0;

// İşlenen konuları getir
$topics_query = "SELECT t.*, l.day, l.start_time, c.name as classroom_name
                FROM topics t
                JOIN lessons l ON t.lesson_id = l.id
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE l.id IN (
                    SELECT l2.id FROM lessons l2
                    JOIN student_classrooms sc ON l2.classroom_id = sc.classroom_id
                    WHERE sc.student_id = ? AND sc.status = 'active'
                ) 
                AND t.date BETWEEN ? AND ?
                ORDER BY t.date DESC, l.start_time";
$topics_params = [$student_id, $current_period['start_date'], $current_period['end_date']];
$topics = safeQuery($topics_query, $topics_params)->fetchAll();

// Ödeme bilgilerini getir
$payments_query = "SELECT p.*
                  FROM payments p
                  WHERE p.student_id = ? AND p.period_id = ?
                  ORDER BY p.payment_date DESC";
$payments_params = [$student_id, $current_period['id']];
$payments = safeQuery($payments_query, $payments_params)->fetchAll();

$page_title = $student['first_name'] . ' ' . $student['last_name'] . ' - Öğrenci Detay';
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
            </h2>
            <div>
                <a href="edit.php?id=<?php echo $student_id; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Düzenle
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Geri Dön
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Öğrenci Bilgileri -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Öğrenci Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="140">Durum:</th>
                        <td>
                            <?php
                            $status_badges = [
                                'active' => '<span class="badge bg-success">Aktif</span>',
                                'passive' => '<span class="badge bg-secondary">Pasif</span>',
                                'trial' => '<span class="badge bg-warning text-dark">Deneme</span>'
                            ];
                            echo $status_badges[$student['status']] ?? $student['status'];

                            if ($period_registration) {
                                echo ' <span class="badge bg-info">Bu Dönemde Kayıtlı</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Kayıt Tarihi:</th>
                        <td><?php echo formatDate($student['enrollment_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Yaş:</th>
                        <td><?php echo $student['age']; ?> (<?php echo formatDate($student['birth_date']); ?>)</td>
                    </tr>
                    <tr>
                        <th>Okul:</th>
                        <td><?php echo htmlspecialchars($student['school'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Telefon:</th>
                        <td><?php echo $student['phone'] ? formatPhone($student['phone']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <th>Veli:</th>
                        <td>
                            <?php
                            if ($student['parent_first_name']) {
                                echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']);
                                echo ' (' . formatPhone($student['parent_phone']) . ')';
                            } else {
                                echo '<span class="text-muted">Veli eklenmemiş</span> ';
                                echo '<a href="add-parent.php?id=' . $student_id . '" class="btn btn-sm btn-outline-primary">Veli Ekle</a>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Aylık Ücret:</th>
                        <td>
                            <strong><?php echo formatMoney($student['monthly_fee']); ?></strong>
                            <?php if ($student['sibling_discount'] > 0): ?>
                                <small class="text-muted">
                                    (<?php echo formatMoney($student['sibling_discount']); ?> kardeş indirimi)
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Net Ücret:</th>
                        <td><strong><?php echo formatMoney($student['net_fee']); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Ödeme Günü:</th>
                        <td>Her ayın <?php echo $student['payment_day']; ?>. günü</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Atanan Sınıflar -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">
                    Atanan Sınıflar
                    <span class="badge bg-light text-dark"><?php echo count($classes); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($classes)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Bu öğrenci henüz bir sınıfa atanmamış.
                    </div>
                    <a href="../classes/" class="btn btn-primary">
                        <i class="bi bi-people"></i> Sınıf Yönetimine Git
                    </a>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($classes as $class): ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($class['name']); ?></h5>
                                    <small><?php echo formatDate($class['enrollment_date']); ?></small>
                                </div>
                                <p class="mb-1"><?php echo htmlspecialchars($class['age_group']); ?></p>
                                <small>
                                    <a href="../classes/view.php?id=<?php echo $class['id']; ?>" class="text-decoration-none">
                                        <i class="bi bi-eye"></i> Sınıf Detayı
                                    </a>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Devam Durumu -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Devam Durumu</h5>
            </div>
            <div class="card-body">
                <?php if ($total_lessons > 0): ?>
                    <div class="row text-center">
                        <div class="col-4 mb-3">
                            <div class="h1 mb-0 text-success"><?php echo $present_count; ?></div>
                            <div class="small text-muted">Katıldı</div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="h1 mb-0 text-danger"><?php echo $absent_count; ?></div>
                            <div class="small text-muted">Gelmedi</div>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="h1 mb-0 text-warning"><?php echo $late_count; ?></div>
                            <div class="small text-muted">Geç Kaldı</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h5 class="text-center">Toplam Devam Oranı: <?php echo $attendance_rate; ?>%</h5>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar"
                                style="width: <?php echo $attendance_rate; ?>%"
                                aria-valuenow="<?php echo $attendance_rate; ?>"
                                aria-valuemin="0" aria-valuemax="100">
                                <?php echo $attendance_rate; ?>%
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="../attendance/student.php?id=<?php echo $student_id; ?>" class="btn btn-primary w-100">
                            <i class="bi bi-calendar-check"></i> Tüm Yoklama Detayları
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Henüz yoklama kaydı bulunmuyor.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- İşlenen Konular -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">İşlenen Konular</h5>
            </div>
            <div class="card-body">
                <?php if (empty($topics)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu öğrenci için henüz işlenen konu kaydı bulunmuyor.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Konu</th>
                                    <th>Sınıf</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topics as $topic): ?>
                                    <tr>
                                        <td><?php echo formatDate($topic['date']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($topic['topic_title']); ?></strong>
                                            <?php if (!empty($topic['description'])): ?>
                                                <p class="small text-muted mb-0"><?php echo htmlspecialchars($topic['description']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($topic['classroom_name']); ?>
                                            <small class="d-block text-muted">
                                                <?php echo $topic['day']; ?>, <?php echo substr($topic['start_time'], 0, 5); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ödeme Bilgileri -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">Ödeme Bilgileri</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu öğrenci için henüz ödeme kaydı bulunmuyor.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Tutar</th>
                                    <th>Tür</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_paid = 0;
                                foreach ($payments as $payment):
                                    if ($payment['status'] == 'paid') {
                                        $total_paid += $payment['amount'];
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        <td><?php echo formatMoney($payment['amount']); ?></td>
                                        <td>
                                            <?php
                                            $payment_types = [
                                                'monthly' => 'Aylık Ödeme',
                                                'extra' => 'Ek Ödeme',
                                                'makeup' => 'Telafi Ders'
                                            ];
                                            echo $payment_types[$payment['payment_type']] ?? $payment['payment_type'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'paid' => '<span class="badge bg-success">Ödendi</span>',
                                                'pending' => '<span class="badge bg-warning text-dark">Bekliyor</span>',
                                                'late' => '<span class="badge bg-danger">Gecikti</span>'
                                            ];
                                            echo $status_badges[$payment['status']] ?? $payment['status'];
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="1">Toplam Ödenen:</th>
                                    <th colspan="3"><?php echo formatMoney($total_paid); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?></document_content>
</invoke>