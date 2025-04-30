<?php
// modules/topics/view.php - Konu detay görüntüleme - Hata düzeltilmiş
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Konu ID kontrolü
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$topic_id) {
    setAlert('Geçersiz konu ID!', 'danger');
    redirect('modules/topics/index.php');
}

// Konu bilgisini getir
$topic_query = "SELECT t.*, l.day, l.start_time, l.end_time, l.period_id, l.id as lesson_id, 
                c.name as classroom_name, c.id as classroom_id
                FROM topics t
                JOIN lessons l ON t.lesson_id = l.id
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE t.id = ?";
$topic = safeQuery($topic_query, [$topic_id])->fetch();

if (!$topic) {
    setAlert('Konu bulunamadı!', 'danger');
    redirect('modules/topics/index.php');
}

// Bu konu farklı bir döneme ait ise uyarı ver
if ($topic['period_id'] != $current_period['id']) {
    setAlert('Bu konu farklı bir döneme ait!', 'warning');
}

// Bu derse katılan öğrencileri getir
$attendance_query = "SELECT a.*, s.first_name, s.last_name, s.birth_date
                    FROM attendance a
                    JOIN students s ON a.student_id = s.id
                    WHERE a.lesson_id = ? AND a.attendance_date = ?
                    ORDER BY s.first_name, s.last_name";
$students = safeQuery($attendance_query, [$topic['lesson_id'], $topic['date']])->fetchAll();

// İstatistikler
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$excused_count = 0;

foreach ($students as $student) {
    if ($student['status'] === 'present') {
        $present_count++;
    } elseif ($student['status'] === 'absent') {
        $absent_count++;
    } elseif ($student['status'] === 'late') {
        $late_count++;
    } elseif ($student['status'] === 'excused') {
        $excused_count++;
    }
}

$page_title = 'Konu Detayı - ' . $topic['topic_title'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Konu Detayı
        <small class="text-muted">(<?php echo htmlspecialchars($topic['topic_title']); ?>)</small>
    </h2>
    <div>
        <a href="../attendance/view.php?lesson_id=<?php echo $topic['lesson_id']; ?>&date=<?php echo $topic['date']; ?>" class="btn btn-info">
            <i class="bi bi-list-check"></i> Yoklama Detayı
        </a>
        <a href="edit.php?id=<?php echo $topic_id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Düzenle
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<!-- Konu Bilgileri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Konu Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Konu:</strong> <?php echo htmlspecialchars($topic['topic_title']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Tarih:</strong> <?php echo formatDate($topic['date']); ?></p>
            </div>
            <div class="col-md-4">
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
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <p><strong>Sınıf:</strong> <?php echo htmlspecialchars($topic['classroom_name']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Gün:</strong> <?php echo $topic['day']; ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Saat:</strong> <?php echo substr($topic['start_time'], 0, 5) . ' - ' . substr($topic['end_time'], 0, 5); ?></p>
            </div>
        </div>
        <?php if (!empty($topic['description'])): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <p><strong>Açıklama:</strong></p>
                    <div class="bg-light p-3 rounded">
                        <?php echo nl2br(htmlspecialchars($topic['description'])); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Katılım İstatistikleri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Katılım İstatistikleri</h5>
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu ders için yoklama kaydı bulunmuyor.
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Gelen</h5>
                            <h3 class="card-text mb-0"><?php echo $present_count; ?></h3>
                            <small><?php echo count($students) > 0 ? round(($present_count / count($students)) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Gelmeyen</h5>
                            <h3 class="card-text mb-0"><?php echo $absent_count; ?></h3>
                            <small><?php echo count($students) > 0 ? round(($absent_count / count($students)) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">Geç Kalan</h5>
                            <h3 class="card-text mb-0"><?php echo $late_count; ?></h3>
                            <small><?php echo count($students) > 0 ? round(($late_count / count($students)) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">İzinli</h5>
                            <h3 class="card-text mb-0"><?php echo $excused_count; ?></h3>
                            <small><?php echo count($students) > 0 ? round(($excused_count / count($students)) * 100) : 0; ?>%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kaçıranlar için telafi dersi oluşturma -->
            <?php if ($absent_count > 0): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong><?php echo $absent_count; ?></strong> öğrenci bu konuyu kaçırmış.
                    <button type="button" class="btn btn-sm btn-outline-warning ms-2" data-bs-toggle="modal" data-bs-target="#makeupLessonModal">
                        <i class="bi bi-calendar-plus"></i> Telafi Dersi Oluştur
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Öğrenci Listesi -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Derse Katılan Öğrenciler</h5>
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu ders için yoklama kaydı bulunmuyor.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Öğrenci</th>
                            <th>Yaş</th>
                            <th>Durum</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="../../modules/students/view.php?id=<?php echo $student['student_id']; ?>">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo calculateAge($student['birth_date']); ?></td>
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
                                    $badge_class = $status_badges[$student['status']] ?? 'secondary';
                                    $label = $status_labels[$student['status']] ?? $student['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($student['notes'] ?? ''); ?></td>
                                <td>
                                    <?php if ($student['status'] === 'absent'): ?>
                                        <a href="student-topics.php?student_id=<?php echo $student['student_id']; ?>&status=absent" class="btn btn-sm btn-warning">
                                            <i class="bi bi-list-ul"></i> Kaçırdığı Konular
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

<!-- Telafi Dersi Oluşturma Modal -->
<?php if ($absent_count > 0): ?>
    <div class="modal fade" id="makeupLessonModal" tabindex="-1" aria-labelledby="makeupLessonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="makeupLessonModalLabel">Telafi Dersi Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <form id="makeupLessonForm">
                        <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">

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
                            <label class="form-label">Telafi Dersine Katılacak Öğrenciler</label>
                            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width: 5%">Seç</th>
                                            <th>Öğrenci</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $hasAbsentStudents = false;
                                        foreach ($students as $student):
                                            if ($student['status'] === 'absent'):
                                                $hasAbsentStudents = true;
                                        ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="makeup_students[]" value="<?php echo $student['student_id']; ?>" id="student_<?php echo $student['student_id']; ?>" checked>
                                                        </div>
                                                    </td>
                                                    <td><label for="student_<?php echo $student['student_id']; ?>"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></label></td>
                                                    <td><span class="badge bg-danger">Gelmedi</span></td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>

                                        <?php if (!$hasAbsentStudents): ?>
                                            <tr>
                                                <td colspan="3" class="text-center">Gelmeyen öğrenci bulunmuyor.</td>
                                            </tr>
                                        <?php endif; ?>
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
                    <button type="button" class="btn btn-primary" id="saveButton">
                        <i class="bi bi-calendar-plus"></i> Telafi Dersi Oluştur
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Telafi dersi oluşturma
        const saveButton = document.getElementById('saveButton');
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                alert('Telafi dersi oluşturma özelliği ileride eklenecektir.');
                // Form verileri burada işlenecek ve API çağrısı yapılacak
                // Şu an için sadece örnek uyarı gösteriliyor

                // Form içeriğini kontrol et
                const form = document.getElementById('makeupLessonForm');
                const date = form.querySelector('#makeup_date').value;
                const startTime = form.querySelector('#makeup_start_time').value;
                const endTime = form.querySelector('#makeup_end_time').value;

                // Seçilen öğrencilerin ID'lerini al
                const selectedStudents = [];
                const checkboxes = form.querySelectorAll('input[name="makeup_students[]"]:checked');
                checkboxes.forEach(function(checkbox) {
                    selectedStudents.push(checkbox.value);
                });

                console.log('Telafi dersi bilgileri:', {
                    date: date,
                    startTime: startTime,
                    endTime: endTime,
                    students: selectedStudents,
                    notes: form.querySelector('#makeup_notes').value
                });

                // Modal'ı kapat
                const modal = bootstrap.Modal.getInstance(document.getElementById('makeupLessonModal'));
                modal.hide();
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>