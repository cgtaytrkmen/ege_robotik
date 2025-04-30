<?php
// modules/curriculum/assign.php - Sınıf-Müfredat atama sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_curriculum'])) {
    try {
        db()->beginTransaction();

        $classroom_id = intval($_POST['classroom_id']);
        $curriculum_id = intval($_POST['curriculum_id']);
        $start_date = clean($_POST['start_date']);

        // Zorunlu alanlar kontrolü
        if (!$classroom_id || !$curriculum_id || !$start_date) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Tarih formatı kontrolü
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
            throw new Exception('Geçersiz tarih formatı!');
        }

        // Sınıf ve müfredat kontrolü
        $classroom_check = safeQuery("SELECT id FROM classrooms WHERE id = ?", [$classroom_id])->fetch();
        $curriculum_check = safeQuery("SELECT id FROM curriculum WHERE id = ?", [$curriculum_id])->fetch();

        if (!$classroom_check) {
            throw new Exception('Geçersiz sınıf seçimi!');
        }

        if (!$curriculum_check) {
            throw new Exception('Geçersiz müfredat seçimi!');
        }

        // Mevcut bir atama var mı kontrol et
        $check_query = "SELECT id FROM classroom_curriculum WHERE classroom_id = ? AND curriculum_id = ?";
        $existing = safeQuery($check_query, [$classroom_id, $curriculum_id])->fetch();

        if ($existing) {
            // Güncelle
            $update_query = "UPDATE classroom_curriculum SET start_date = ? WHERE classroom_id = ? AND curriculum_id = ?";
            safeQuery($update_query, [$start_date, $classroom_id, $curriculum_id]);

            setAlert('Sınıf-müfredat ataması başarıyla güncellendi!', 'success');
        } else {
            // Yeni ekle
            $insert_query = "INSERT INTO classroom_curriculum (classroom_id, curriculum_id, start_date) VALUES (?, ?, ?)";
            safeQuery($insert_query, [$classroom_id, $curriculum_id, $start_date]);

            setAlert('Sınıf-müfredat ataması başarıyla oluşturuldu!', 'success');
        }

        // Bu sınıftaki öğrenciler için otomatik ilerleme kaydı oluştur
        $students_query = "SELECT s.id FROM students s
                          JOIN student_classrooms sc ON s.id = sc.student_id
                          WHERE sc.classroom_id = ? AND sc.status = 'active'";
        $students = safeQuery($students_query, [$classroom_id])->fetchAll();

        foreach ($students as $student) {
            // Öğrenci için ilerleme kaydı var mı kontrol et
            $check_progress = "SELECT id FROM student_curriculum_progress WHERE student_id = ? AND curriculum_id = ?";
            $existing_progress = safeQuery($check_progress, [$student['id'], $curriculum_id])->fetch();

            if (!$existing_progress) {
                // Yeni ilerleme kaydı oluştur
                $insert_progress = "INSERT INTO student_curriculum_progress (student_id, curriculum_id, current_week) VALUES (?, ?, 1)";
                safeQuery($insert_progress, [$student['id'], $curriculum_id]);
            }
        }

        db()->commit();

        redirect('modules/curriculum/index.php');
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Mevcut sınıf-müfredat atamalarını getir
$assignments_query = "SELECT cc.*, c.name as classroom_name, cu.name as curriculum_name, 
                     cu.age_group, cu.total_weeks, p.name as period_name
                     FROM classroom_curriculum cc
                     JOIN classrooms c ON cc.classroom_id = c.id
                     JOIN curriculum cu ON cc.curriculum_id = cu.id
                     JOIN periods p ON cu.period_id = p.id
                     ORDER BY c.name, cu.name";
$assignments = db()->query($assignments_query)->fetchAll();

// Aktif sınıfları getir
$classrooms_query = "SELECT * FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Aktif müfredatları getir
$curricula_query = "SELECT c.*, p.name as period_name 
                  FROM curriculum c
                  JOIN periods p ON c.period_id = p.id
                  WHERE c.status = 'active' 
                  ORDER BY c.age_group, c.name";
$curricula = db()->query($curricula_query)->fetchAll();

$page_title = 'Sınıf-Müfredat Atamaları';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sınıf-Müfredat Atamaları
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="row">
    <!-- Yeni Atama Formu -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Yeni Atama Oluştur</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="classroom_id" class="form-label">Sınıf Seçin</label>
                        <select class="form-select" id="classroom_id" name="classroom_id" required>
                            <option value="">-- Sınıf Seçin --</option>
                            <?php foreach ($classrooms as $classroom): ?>
                                <option value="<?php echo $classroom['id']; ?>">
                                    <?php echo htmlspecialchars($classroom['name']); ?> (<?php echo htmlspecialchars($classroom['age_group']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="curriculum_id" class="form-label">Müfredat Seçin</label>
                        <select class="form-select" id="curriculum_id" name="curriculum_id" required>
                            <option value="">-- Müfredat Seçin --</option>
                            <?php foreach ($curricula as $curriculum): ?>
                                <option value="<?php echo $curriculum['id']; ?>" data-age-group="<?php echo htmlspecialchars($curriculum['age_group']); ?>">
                                    <?php echo htmlspecialchars($curriculum['name']); ?> (<?php echo htmlspecialchars($curriculum['age_group']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="alert alert-warning age-mismatch-warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> Seçilen sınıf ve müfredatın yaş grupları uyuşmuyor! Yine de devam etmek istiyor musunuz?
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="assign_curriculum" class="btn btn-primary">
                            <i class="bi bi-link"></i> Atamayı Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mevcut Atamalar -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Mevcut Atamalar</h5>
            </div>
            <div class="card-body">
                <?php if (empty($assignments)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Henüz sınıf-müfredat ataması yapılmamış.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Sınıf</th>
                                    <th>Müfredat</th>
                                    <th>Yaş Grubu</th>
                                    <th>Dönem</th>
                                    <th>Başlangıç</th>
                                    <th>Hafta</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['classroom_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['curriculum_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['age_group']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['period_name']); ?></td>
                                        <td><?php echo formatDate($assignment['start_date']); ?></td>
                                        <td><?php echo $assignment['total_weeks']; ?> hafta</td>
                                        <td>
                                            <a href="../curriculum/student_progress.php?classroom_id=<?php echo $assignment['classroom_id']; ?>&curriculum_id=<?php echo $assignment['curriculum_id']; ?>" class="btn btn-sm btn-info" title="İlerleme Takibi">
                                                <i class="bi bi-person-lines-fill"></i>
                                            </a>
                                            <a href="delete_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bu atamayı silmek istediğinizden emin misiniz?');" title="Sil">
                                                <i class="bi bi-trash"></i>
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
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const classroomSelect = document.getElementById('classroom_id');
        const curriculumSelect = document.getElementById('curriculum_id');
        const warningAlert = document.querySelector('.age-mismatch-warning');

        // Sınıf ve müfredat yaş grubu uyumunu kontrol et
        function checkAgeGroupMatch() {
            if (!classroomSelect.value || !curriculumSelect.value) {
                warningAlert.style.display = 'none';
                return;
            }

            const selectedClassroom = classroomSelect.options[classroomSelect.selectedIndex].text;
            const selectedCurriculum = curriculumSelect.options[curriculumSelect.selectedIndex];

            const classroomAgeGroup = selectedClassroom.match(/\((.*?)\)/)[1];
            const curriculumAgeGroup = selectedCurriculum.getAttribute('data-age-group');

            if (classroomAgeGroup !== curriculumAgeGroup) {
                warningAlert.style.display = 'block';
            } else {
                warningAlert.style.display = 'none';
            }
        }

        classroomSelect.addEventListener('change', checkAgeGroupMatch);
        curriculumSelect.addEventListener('change', checkAgeGroupMatch);
    });
</script>

<?php require_once '../../includes/footer.php'; ?>