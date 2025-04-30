<?php
// modules/classes/view.php - Sınıf detay görüntüleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$classroom_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$classroom_id) {
    setAlert('Geçersiz sınıf ID!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıf bilgilerini getir
$query = "SELECT * FROM classrooms WHERE id = ?";
$classroom = safeQuery($query, [$classroom_id])->fetch();

if (!$classroom) {
    setAlert('Sınıf bulunamadı!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıftaki öğrencileri getir
$students_query = "SELECT s.*, sc.enrollment_date as class_enrollment_date
                   FROM students s
                   JOIN student_classrooms sc ON s.id = sc.student_id
                   WHERE sc.classroom_id = ? AND sc.status = 'active'
                   ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$classroom_id])->fetchAll();

// Sınıfın ders programını getir
$lessons_query = "SELECT l.*, 
                  CASE l.day
                    WHEN 'Monday' THEN 'Pazartesi'
                    WHEN 'Tuesday' THEN 'Salı'
                    WHEN 'Wednesday' THEN 'Çarşamba'
                    WHEN 'Thursday' THEN 'Perşembe'
                    WHEN 'Friday' THEN 'Cuma'
                    WHEN 'Saturday' THEN 'Cumartesi'
                    WHEN 'Sunday' THEN 'Pazar'
                  END as day_turkish
                  FROM lessons l
                  WHERE l.classroom_id = ? AND l.period_id = ? AND l.status = 'active'
                  ORDER BY FIELD(l.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), l.start_time";
$lessons = safeQuery($lessons_query, [$classroom_id, $current_period['id']])->fetchAll();

// Sınıf yoklama istatistikleri
$attendance_stats_query = "SELECT 
                          COUNT(DISTINCT a.attendance_date) as total_days,
                          AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as attendance_rate
                          FROM attendance a
                          JOIN lessons l ON a.lesson_id = l.id
                          WHERE l.classroom_id = ? AND l.period_id = ?";
$attendance_stats = safeQuery($attendance_stats_query, [$classroom_id, $current_period['id']])->fetch();

$page_title = 'Sınıf Detayları - ' . $classroom['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sınıf Detayları: <?php echo htmlspecialchars($classroom['name']); ?>
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="edit.php?id=<?php echo $classroom_id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Düzenle
        </a>
        <a href="assign-students.php?id=<?php echo $classroom_id; ?>" class="btn btn-success">
            <i class="bi bi-people"></i> Öğrenci Ata
        </a>
        <a href="copy.php?id=<?php echo $classroom_id; ?>" class="btn btn-warning">
            <i class="bi bi-files"></i> Kopyala
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<div class="row">
    <!-- Sınıf Bilgileri -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sınıf Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="35%">Sınıf Adı:</th>
                        <td><?php echo htmlspecialchars($classroom['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Yaş Grubu:</th>
                        <td><?php echo htmlspecialchars($classroom['age_group']); ?></td>
                    </tr>
                    <tr>
                        <th>Kapasite:</th>
                        <td><?php echo $classroom['capacity']; ?> öğrenci</td>
                    </tr>
                    <tr>
                        <th>Doluluk:</th>
                        <td>
                            <?php
                            $occupancy = count($students) . '/' . $classroom['capacity'];
                            $occupancy_percentage = round(count($students) / $classroom['capacity'] * 100);
                            $progress_class = 'success';
                            if ($occupancy_percentage > 80) {
                                $progress_class = 'danger';
                            } else if ($occupancy_percentage > 60) {
                                $progress_class = 'warning';
                            }
                            ?>
                            <div class="progress" style="max-width: 150px; height: 20px;">
                                <div class="progress-bar bg-<?php echo $progress_class; ?>"
                                    role="progressbar"
                                    style="width: <?php echo $occupancy_percentage; ?>%"
                                    aria-valuenow="<?php echo $occupancy_percentage; ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100">
                                    <?php echo $occupancy; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($classroom['status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (!empty($classroom['description'])): ?>
                        <tr>
                            <th>Açıklama:</th>
                            <td><?php echo nl2br(htmlspecialchars($classroom['description'])); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Haftalık Ders Programı -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Haftalık Ders Programı</h5>
            </div>
            <div class="card-body">
                <?php if (count($lessons) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Gün</th>
                                    <th>Saat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $lesson): ?>
                                    <tr>
                                        <td><?php echo $lesson['day_turkish']; ?></td>
                                        <td><?php echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz ders programı oluşturulmamış.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <!-- Sınıf İstatistikleri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sınıf İstatistikleri</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <h3 class="mb-0"><?php echo count($students); ?></h3>
                        <small class="text-muted">Öğrenci</small>
                    </div>
                    <div class="col-4">
                        <h3 class="mb-0"><?php echo count($lessons); ?></h3>
                        <small class="text-muted">Haftalık Ders</small>
                    </div>
                    <div class="col-4">
                        <h3 class="mb-0"><?php echo number_format($attendance_stats['attendance_rate'] ?? 0, 1); ?>%</h3>
                        <small class="text-muted">Devam Oranı</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Öğrenci Listesi -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Öğrenci Listesi</h5>
                <a href="assign-students.php?id=<?php echo $classroom_id; ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-person-plus"></i> Öğrenci Ekle
                </a>
            </div>
            <div class="card-body">
                <?php if (count($students) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>Yaş</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <a href="../students/view.php?id=<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo calculateAge($student['birth_date']); ?></td>
                                        <td><?php echo formatDate($student['class_enrollment_date']); ?></td>
                                        <td>
                                            <a href="remove-student.php?classroom_id=<?php echo $classroom_id; ?>&student_id=<?php echo $student['id']; ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bu öğrenciyi sınıftan çıkarmak istediğinizden emin misiniz?');">
                                                <i class="bi bi-person-dash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz öğrenci atanmamış.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>