<?php
// modules/topics/student-topics.php - Öğrenci bazlı konuları listeleme
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
    redirect('modules/topics/index.php');
}

// Öğrenci bilgisini getir
$student_query = "SELECT s.*, sp.status as period_status
                 FROM students s
                 LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
                 WHERE s.id = ?";
$student = safeQuery($student_query, [$current_period['id'], $student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/topics/index.php');
}

// Filtreleme parametreleri
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Öğrencinin sınıfını bul
$classroom_query = "SELECT c.id, c.name 
                   FROM classrooms c
                   JOIN student_classrooms sc ON c.id = sc.classroom_id
                   WHERE sc.student_id = ? AND sc.status = 'active'
                   LIMIT 1";
$classroom = safeQuery($classroom_query, [$student_id])->fetch();

// Öğrencinin katıldığı dersleri ve işlenen konuları getir
$topics_query = "SELECT t.*, l.day, l.start_time, l.end_time, c.name as classroom_name, 
                 a.status as attendance_status, a.notes as attendance_notes
                 FROM attendance a
                 JOIN lessons l ON a.lesson_id = l.id
                 JOIN classrooms c ON l.classroom_id = c.id
                 LEFT JOIN topics t ON a.lesson_id = t.lesson_id AND a.attendance_date = t.date
                 WHERE a.student_id = ? AND l.period_id = ?";
$params = [$student_id, $current_period['id']];

// Tarih filtrelemesi
if (!empty($filter_month)) {
    $topics_query .= " AND a.attendance_date LIKE ?";
    $params[] = $filter_month . '%';
}

// Yoklama durumuna göre filtreleme
if (!empty($filter_status)) {
    $topics_query .= " AND a.status = ?";
    $params[] = $filter_status;
}

$topics_query .= " ORDER BY a.attendance_date DESC, l.start_time";
$topics = safeQuery($topics_query, $params)->fetchAll();

// Gelmeyen dersleri belirle
$missed_topics = [];
foreach ($topics as $topic) {
    if ($topic['attendance_status'] === 'absent' && !empty($topic['topic_title'])) {
        $missed_topics[] = $topic;
    }
}

// Gelmediği ders sayısı
$absent_count = 0;
foreach ($topics as $topic) {
    if ($topic['attendance_status'] === 'absent') {
        $absent_count++;
    }
}

$page_title = 'Öğrenci Konuları - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Konuları
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <div>
        <a href="../../modules/students/view.php?id=<?php echo $student_id; ?>" class="btn btn-info">
            <i class="bi bi-person"></i> Öğrenci Detayları
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Konulara Dön
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
            </div>
            <div class="col-md-4">
                <p><strong>Yaş:</strong> <?php echo calculateAge($student['birth_date']); ?></p>
            </div>
            <div class="col-md-4">
                <p>
                    <strong>Durum:</strong>
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
                    $badge_class = $status_badges[$student['period_status']] ?? 'secondary';
                    $label = $status_labels[$student['period_status']] ?? $student['period_status'];
                    ?>
                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                </p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <p><strong>Sınıf:</strong> <?php echo $classroom ? htmlspecialchars($classroom['name']) : 'Sınıf atanmamış'; ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Kayıt Tarihi:</strong> <?php echo formatDate($student['enrollment_date']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Katılmadığı Ders Sayısı:</strong> <span class="text-danger"><?php echo $absent_count; ?></span></p>
            </div>
        </div>
    </div>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
            <div class="col-md-5">
                <label for="month" class="form-label">Ay</label>
                <input type="month" class="form-control" id="month" name="month" value="<?php echo $filter_month; ?>">
            </div>
            <div class="col-md-5">
                <label for="status" class="form-label">Yoklama Durumu</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="present" <?php echo $filter_status === 'present' ? 'selected' : ''; ?>>Geldi</option>
                    <option value="absent" <?php echo $filter_status === 'absent' ? 'selected' : ''; ?>>Gelmedi</option>
                    <option value="late" <?php echo $filter_status === 'late' ? 'selected' : ''; ?>>Geç Kaldı</option>
                    <option value="excused" <?php echo $filter_status === 'excused' ? 'selected' : ''; ?>>İzinli</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Telafi Edilmesi Gereken Konular -->
<?php if (!empty($missed_topics)): ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Telafi Edilmesi Gereken Konular</h5>
            <span class="badge bg-danger"><?php echo count($missed_topics); ?> Konu</span>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Bu öğrenci <strong><?php echo count($missed_topics); ?></strong> dersi kaçırmış ve bu konuların telafi edilmesi gerekiyor.
                <button type="button" class="btn btn-sm btn-outline-warning ms-2" data-bs-toggle="modal" data-bs-target="#createMakeupModal">
                    <i class="bi bi-calendar-plus"></i> Toplu Telafi Dersi Oluştur
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sınıf</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missed_topics as $topic): ?>
                            <tr>
                                <td><?php echo formatDate($topic['date']); ?></td>
                                <td><?php echo htmlspecialchars($topic['classroom_name']); ?></td>
                                <td>
                                    <?php if ($topic['id']): ?>
                                        <a href="../topics/view.php?id=<?php echo $topic['id']; ?>">
                                            <?php echo htmlspecialchars($topic['topic_title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($topic['topic_title']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-danger">Gelmedi</span>
                                    <?php if (!empty($topic['attendance_notes'])): ?>
                                        <small>(<?php echo htmlspecialchars($topic['attendance_notes']); ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../attendance/view.php?lesson_id=<?php echo $topic['lesson_id']; ?>&date=<?php echo $topic['date']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Yoklama Detayı
                                    </a>
                                    <?php if ($topic['id']): ?>
                                        <button type="button" class="btn btn-sm btn-warning create-makeup"
                                            data-topic-id="<?php echo $topic['id']; ?>"
                                            data-topic-title="<?php echo htmlspecialchars($topic['topic_title']); ?>">
                                            <i class="bi bi-calendar-plus"></i> Telafi Oluştur
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Tüm Konular -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Tüm İşlenen Konular</h5>
    </div>
    <div class="card-body">
        <?php if (empty($topics)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu öğrenci için katılım kaydı bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Gün/Saat</th>
                            <th>Sınıf</th>
                            <th>Konu</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td><?php echo formatDate($topic['date']); ?></td>
                                <td>
                                    <?php echo $topic['day']; ?><br>
                                    <small><?php echo substr($topic['start_time'], 0, 5) . ' - ' . substr($topic['end_time'], 0, 5); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($topic['classroom_name']); ?></td>
                                <td>
                                    <?php if (!empty($topic['topic_title'])): ?>
                                        <?php if (!empty($topic['id'])): ?>
                                            <a href="../topics/view.php?id=<?php echo $topic['id']; ?>">
                                                <?php echo htmlspecialchars($topic['topic_title']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($topic['topic_title']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Konu girilmemiş</span>
                                    <?php endif; ?>
                                </td>
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
                                    $badge_class = $status_badges[$topic['attendance_status']] ?? 'secondary';
                                    $label = $status_labels[$topic['attendance_status']] ?? $topic['attendance_status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                    <?php if (!empty($topic['attendance_notes'])): ?>
                                        <br><small><?php echo htmlspecialchars($topic['attendance_notes']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../attendance/view.php?lesson_id=<?php echo $topic['lesson_id']; ?>&date=<?php echo $topic['date']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> Yoklama Detayı
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

<!-- Toplu Telafi Dersi Oluşturma Modal -->
<div class="modal fade" id="createMakeupModal" tabindex="-1" aria-labelledby="createMakeupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createMakeupModalLabel">Toplu Telafi Dersi Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="makeupForm">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <div class="mb-3">
                        <label for="makeup_title" class="form-label">Telafi Dersi Başlığı</label>
                        <input type="text" class="form-control" id="makeup_title" name="makeup_title" required placeholder="Telafi dersi için başlık">
                    </div>

                    <div class="mb-3">
                        <label for="makeup_date" class="form-label">Telafi Dersi Tarihi</label>
                        <input type="date" class="form-control" id="makeup_date" name="makeup_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="makeup_start_time" class="form-label">Başlangıç Saati</label>
                            <input type="time" class="form-control" id="makeup_start_time" name="makeup_start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="makeup_end_time" class="form-label">Bitiş Saati</label>
                            <input type="time" class="form-control" id="makeup_end_time" name="makeup_end_time" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Telafi Edilecek Konular</label>
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">Seç</th>
                                        <th>Tarih</th>
                                        <th>Konu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missed_topics as $index => $topic): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="makeup_topics[]" value="<?php echo $topic['id']; ?>" id="topic_<?php echo $index; ?>" checked>
                                                </div>
                                            </td>
                                            <td><?php echo formatDate($topic['date']); ?></td>
                                            <td><label for="topic_<?php echo $index; ?>"><?php echo htmlspecialchars($topic['topic_title']); ?></label></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="makeup_notes" class="form-label">Notlar</label>
                        <textarea class="form-control" id="makeup_notes" name="makeup_notes" rows="3" placeholder="Telafi dersi ile ilgili notlar..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="createMakeupButton">
                    <i class="bi bi-calendar-plus"></i> Telafi Dersi Oluştur
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tekli Telafi Dersi Modal -->
<div class="modal fade" id="singleMakeupModal" tabindex="-1" aria-labelledby="singleMakeupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="singleMakeupModalLabel">Telafi Dersi Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="singleMakeupForm">
                    <input type="hidden" name="topic_id" id="single_topic_id">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <div class="mb-3">
                        <label for="single_topic_title" class="form-label">Konu</label>
                        <input type="text" class="form-control" id="single_topic_title" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="single_makeup_date" class="form-label">Telafi Dersi Tarihi</label>
                        <input type="date" class="form-control" id="single_makeup_date" name="single_makeup_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="single_makeup_start_time" class="form-label">Başlangıç Saati</label>
                            <input type="time" class="form-control" id="single_makeup_start_time" name="single_makeup_start_time" required>
                        </div>
                        <div class="col-md-6">
                            <label for="single_makeup_end_time" class="form-label">Bitiş Saati</label>
                            <input type="time" class="form-control" id="single_makeup_end_time" name="single_makeup_end_time" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="single_makeup_notes" class="form-label">Notlar</label>
                        <textarea class="form-control" id="single_makeup_notes" name="single_makeup_notes" rows="3" placeholder="Telafi dersi ile ilgili notlar..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="createSingleMakeupButton">
                    <i class="bi bi-calendar-plus"></i> Telafi Dersi Oluştur
                </button>
            </div>
        </div>
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

        // Toplu telafi dersi oluşturma
        document.getElementById('createMakeupButton').addEventListener('click', function() {
            alert('Telafi dersi oluşturma özelliği ileride eklenecektir.');
            // Burada form verilerini toplayıp AJAX ile gönderme işlemi yapılacak

            // Modal'ı kapat
            const modal = bootstrap.Modal.getInstance(document.getElementById('createMakeupModal'));
            modal.hide();
        });

        // Tekli telafi dersi oluşturma butonlarına olay dinleyicisi ekle
        document.querySelectorAll('.create-makeup').forEach(button => {
            button.addEventListener('click', function() {
                const topicId = this.getAttribute('data-topic-id');
                const topicTitle = this.getAttribute('data-topic-title');

                document.getElementById('single_topic_id').value = topicId;
                document.getElementById('single_topic_title').value = topicTitle;

                // Modal'ı aç
                const modal = new bootstrap.Modal(document.getElementById('singleMakeupModal'));
                modal.show();
            });
        });

        // Tekli telafi dersi oluşturma
        document.getElementById('createSingleMakeupButton').addEventListener('click', function() {
            alert('Telafi dersi oluşturma özelliği ileride eklenecektir.');
            // Burada form verilerini toplayıp AJAX ile gönderme işlemi yapılacak

            // Modal'ı kapat
            const modal = bootstrap.Modal.getInstance(document.getElementById('singleMakeupModal'));
            modal.hide();
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>