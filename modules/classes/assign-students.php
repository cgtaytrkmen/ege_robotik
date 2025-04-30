<?php
// modules/classes/assign-students.php - Sınıfa öğrenci atama (düzeltilmiş versiyon)
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
$current_students_query = "SELECT s.id, s.first_name, s.last_name, sc.enrollment_date
                           FROM students s
                           JOIN student_classrooms sc ON s.id = sc.student_id
                           WHERE sc.classroom_id = ? AND sc.status = 'active'
                           ORDER BY s.first_name, s.last_name";
$current_students = safeQuery($current_students_query, [$classroom_id])->fetchAll();
$current_student_ids = array_column($current_students, 'id');

// Sınıfa atanabilecek tüm aktif öğrencileri getir
$available_students_query = "SELECT s.*, 
                            TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) as age,
                            GROUP_CONCAT(c.name SEPARATOR ', ') as current_classes
                            FROM students s
                            LEFT JOIN student_classrooms sc ON s.id = sc.student_id AND sc.status = 'active'
                            LEFT JOIN classrooms c ON sc.classroom_id = c.id
                            WHERE s.status = 'active'
                            AND s.id IN (
                                SELECT student_id FROM student_periods 
                                WHERE period_id = ? AND status = 'active'
                            )
                            GROUP BY s.id
                            ORDER BY s.first_name, s.last_name";
$available_students = safeQuery($available_students_query, [$current_period['id']])->fetchAll();

// Öğrenci atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_students'])) {
    try {
        db()->beginTransaction();

        $selected_students = $_POST['students'] ?? [];
        $available_capacity = $classroom['capacity'] - count($current_students);

        if (count($selected_students) > $available_capacity) {
            throw new Exception('Sınıf kapasitesi yetersiz! En fazla ' . $available_capacity . ' öğrenci ekleyebilirsiniz.');
        }

        // student_classrooms tablosunun var olup olmadığını kontrol et
        $table_check = db()->query("SHOW TABLES LIKE 'student_classrooms'")->fetch();

        if (!$table_check) {
            // Tablo yoksa oluştur
            $create_table_sql = "CREATE TABLE student_classrooms (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                classroom_id INT NOT NULL,
                enrollment_date DATE NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
                UNIQUE KEY unique_student_classroom (student_id, classroom_id)
            )";
            db()->exec($create_table_sql);
        }

        foreach ($selected_students as $student_id) {
            // Öğrenci zaten bu sınıfta değilse ekle
            if (!in_array($student_id, $current_student_ids)) {
                $data = [
                    'student_id' => $student_id,
                    'classroom_id' => $classroom_id,
                    'enrollment_date' => date('Y-m-d'),
                    'status' => 'active'
                ];

                $sql = "INSERT INTO student_classrooms (student_id, classroom_id, enrollment_date, status) 
                        VALUES (:student_id, :classroom_id, :enrollment_date, :status)";
                safeQuery($sql, $data);
            }
        }

        db()->commit();
        setAlert('Öğrenciler başarıyla atandı!', 'success');
        redirect('modules/classes/view.php?id=' . $classroom_id);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Öğrenci Atama - ' . $classroom['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Atama: <?php echo htmlspecialchars($classroom['name']); ?>
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="view.php?id=<?php echo $classroom_id; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Atanabilecek Öğrenciler</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php
                    $available_capacity = $classroom['capacity'] - count($current_students);
                    if ($available_capacity > 0):
                    ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Bu sınıfa en fazla <strong><?php echo $available_capacity; ?></strong> öğrenci daha ekleyebilirsiniz.
                        </div>

                        <!-- Arama ve Filtreleme -->
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <input type="text" id="student_search" class="form-control" placeholder="Öğrenci ara (ad, soyad, okul)...">
                            </div>
                            <div class="col-md-3">
                                <select id="age_filter" class="form-select">
                                    <option value="">- Yaş Filtresi -</option>
                                    <option value="6-8">6-8 yaş</option>
                                    <option value="9-12">9-12 yaş</option>
                                    <option value="13+">13+ yaş</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="show_no_class" checked>
                                    <label class="form-check-label" for="show_no_class">Sadece sınıfı olmayanları göster</label>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="students_table">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="select_all">
                                        </th>
                                        <th>Ad Soyad</th>
                                        <th>Yaş</th>
                                        <th>Mevcut Sınıfları</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_students as $student): ?>
                                        <?php if (!in_array($student['id'], $current_student_ids)): ?>
                                            <?php
                                            $student_age = $student['age'];
                                            $age_group = '';
                                            if ($student_age >= 6 && $student_age <= 8) $age_group = '6-8';
                                            elseif ($student_age >= 9 && $student_age <= 12) $age_group = '9-12';
                                            elseif ($student_age >= 13) $age_group = '13+';

                                            $has_class = !empty($student['current_classes']);
                                            $search_text = strtolower($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['school']);
                                            ?>
                                            <tr data-age="<?php echo $student_age; ?>"
                                                data-age-group="<?php echo $age_group; ?>"
                                                data-has-class="<?php echo $has_class ? 'yes' : 'no'; ?>"
                                                data-search="<?php echo htmlspecialchars($search_text); ?>"
                                                class="student-row">
                                                <td>
                                                    <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" class="student-checkbox">
                                                </td>
                                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                <td><?php echo $student_age; ?></td>
                                                <td><?php echo htmlspecialchars($student['current_classes'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            <button type="submit" name="assign_students" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Seçili Öğrencileri Ata
                            </button>
                            <span id="selected_count" class="ms-2 badge bg-info">0 öğrenci seçildi</span>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> Sınıf kapasitesi dolu! Daha fazla öğrenci ekleyemezsiniz.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sınıftaki Öğrenciler</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    <strong>Kapasite:</strong> <?php echo count($current_students); ?>/<?php echo $classroom['capacity']; ?>
                </p>

                <?php if (count($current_students) > 0): ?>
                    <ul class="list-group">
                        <?php foreach ($current_students as $student): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                <a href="remove-student.php?classroom_id=<?php echo $classroom_id; ?>&student_id=<?php echo $student['id']; ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Bu öğrenciyi sınıftan çıkarmak istediğinizden emin misiniz?');">
                                    <i class="bi bi-person-dash"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz öğrenci atanmamış.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Değişkenler
        const studentSearch = document.getElementById('student_search');
        const ageFilter = document.getElementById('age_filter');
        const showNoClass = document.getElementById('show_no_class');
        const selectAll = document.getElementById('select_all');
        const studentRows = document.querySelectorAll('.student-row');
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const selectedCount = document.getElementById('selected_count');

        // Metin araması
        studentSearch.addEventListener('input', filterStudents);

        // Yaş filtresi
        ageFilter.addEventListener('change', filterStudents);

        // Sadece sınıfı olmayanlar filtresi
        showNoClass.addEventListener('change', filterStudents);

        // Tümünü seç/kaldır
        selectAll.addEventListener('change', function() {
            const visibleRows = document.querySelectorAll('.student-row:not([style*="display: none"])');

            visibleRows.forEach(row => {
                const checkbox = row.querySelector('.student-checkbox');
                checkbox.checked = this.checked;
            });

            updateSelectedCount();
        });

        // Öğrenci checkbox'ları değiştiğinde sayacı güncelle
        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        // Görünür öğrenci sayacını güncelle
        function updateSelectedCount() {
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
            selectedCount.textContent = checkedCount + ' öğrenci seçildi';
        }

        // Tüm filtreleri uygula
        function filterStudents() {
            const searchText = studentSearch.value.toLowerCase();
            const ageValue = ageFilter.value;
            const noClassOnly = showNoClass.checked;
            let visibleRowsCount = 0;

            studentRows.forEach(row => {
                let shouldShow = true;

                // Metin araması
                if (searchText) {
                    const rowSearchText = row.getAttribute('data-search') || '';
                    if (!rowSearchText.includes(searchText)) {
                        shouldShow = false;
                    }
                }

                // Yaş filtresi
                if (ageValue && shouldShow) {
                    const rowAgeGroup = row.getAttribute('data-age-group');
                    if (rowAgeGroup !== ageValue) {
                        shouldShow = false;
                    }
                }

                // Sınıfı olmayanlar filtresi
                if (noClassOnly && shouldShow) {
                    const hasClass = row.getAttribute('data-has-class');
                    if (hasClass === 'yes') {
                        shouldShow = false;
                    }
                }

                // Görünürlüğü ayarla
                row.style.display = shouldShow ? '' : 'none';

                if (shouldShow) {
                    visibleRowsCount++;
                }
            });

            // Tümünü seç checkbox'ını sıfırla
            selectAll.checked = false;

            // Sayacı güncelle
            updateSelectedCount();
        }

        // Sayfa yüklendiğinde ilk filtrelemeyi yap
        filterStudents();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>