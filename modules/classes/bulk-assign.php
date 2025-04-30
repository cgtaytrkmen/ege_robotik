<?php
// modules/classes/bulk-assign.php - Toplu öğrenci atama
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Aktif sınıfları getir
$classrooms_query = "SELECT id, name, capacity, age_group, 
                    (SELECT COUNT(*) FROM student_classrooms 
                     WHERE classroom_id = c.id AND status = 'active') as student_count
                    FROM classrooms c
                    WHERE c.status = 'active'
                    ORDER BY c.name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Aktif dönemdeki atanmamış öğrencileri getir
$students_query = "SELECT s.*, 
                   TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) as age
                   FROM students s
                   JOIN student_periods sp ON s.id = sp.student_id
                   WHERE sp.period_id = ? AND sp.status = 'active' AND s.status = 'active'
                   ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$current_period['id']])->fetchAll();

// Her öğrencinin mevcut sınıf durumunu kontrol et
foreach ($students as &$student) {
    $class_query = "SELECT c.name 
                   FROM classrooms c
                   JOIN student_classrooms sc ON c.id = sc.classroom_id
                   WHERE sc.student_id = ? AND sc.status = 'active'";
    $class_result = safeQuery($class_query, [$student['id']])->fetchAll();

    if (!empty($class_result)) {
        $class_names = [];
        foreach ($class_result as $class) {
            $class_names[] = $class['name'];
        }
        $student['current_classes'] = implode(', ', $class_names);
    } else {
        $student['current_classes'] = '';
    }
}
unset($student); // Referansı temizle

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignments'])) {
    try {
        db()->beginTransaction();

        $assignments = $_POST['assignments'] ?? [];
        $changed_count = 0;

        if (empty($assignments)) {
            throw new Exception('Lütfen en az bir atama yapın!');
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

        // Her sınıfın mevcut doluluk durumunu takip et
        $classroom_counts = [];
        foreach ($classrooms as $classroom) {
            $classroom_counts[$classroom['id']] = [
                'capacity' => $classroom['capacity'],
                'current' => $classroom['student_count'],
                'assigned' => 0
            ];
        }

        // Öğrenci atamalarını işle
        foreach ($assignments as $student_id => $classroom_id) {
            if (empty($classroom_id)) continue; // Sınıf seçilmemişse atla

            $student_id = intval($student_id);
            $classroom_id = intval($classroom_id);

            // Kapasite kontrolü
            $capacity = $classroom_counts[$classroom_id]['capacity'];
            $current = $classroom_counts[$classroom_id]['current'];
            $assigned = $classroom_counts[$classroom_id]['assigned'];

            if ($current + $assigned >= $capacity) {
                throw new Exception("Sınıf kapasite sınırına ulaşıldı! " . getClassroomName($classrooms, $classroom_id));
            }

            // Mevcut ilişki var mı kontrol et
            $check_query = "SELECT id, status FROM student_classrooms 
                           WHERE student_id = ? AND classroom_id = ?";
            $relation = safeQuery($check_query, [$student_id, $classroom_id])->fetch();

            if ($relation) {
                // İlişki var ama pasif ise aktifleştir
                if ($relation['status'] === 'inactive') {
                    $update_query = "UPDATE student_classrooms 
                                    SET status = 'active', enrollment_date = CURDATE() 
                                    WHERE id = ?";
                    safeQuery($update_query, [$relation['id']]);
                    $changed_count++;
                }
                // İlişki zaten aktif ise bir şey yapma
            } else {
                // Yeni ilişki oluştur
                $insert_query = "INSERT INTO student_classrooms 
                                (student_id, classroom_id, enrollment_date, status) 
                                VALUES (?, ?, CURDATE(), 'active')";
                safeQuery($insert_query, [$student_id, $classroom_id]);
                $changed_count++;
            }

            // Takibi güncelle
            $classroom_counts[$classroom_id]['assigned']++;
        }

        db()->commit();

        if ($changed_count > 0) {
            setAlert($changed_count . ' öğrenci başarıyla atandı!', 'success');
        } else {
            setAlert('Herhangi bir değişiklik yapılmadı.', 'info');
        }

        redirect('modules/classes/index.php');
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Sınıf adını bul
function getClassroomName($classrooms, $id)
{
    foreach ($classrooms as $classroom) {
        if ($classroom['id'] == $id) {
            return $classroom['name'];
        }
    }
    return '';
}

$page_title = 'Toplu Öğrenci Atama';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Toplu Öğrenci Atama
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<?php if (empty($students)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Atanabilecek öğrenci bulunmuyor.
    </div>
<?php else: ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo count($students); ?></h3>
                    <p class="card-text mb-0">Toplam Öğrenci</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo count(array_filter($students, function ($s) {
                                            return empty($s['current_classes']);
                                        })); ?></h3>
                    <p class="card-text mb-0">Sınıfsız Öğrenci</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?php echo count($classrooms); ?></h3>
                    <p class="card-text mb-0">Aktif Sınıf</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <?php
                    $total_capacity = array_sum(array_column($classrooms, 'capacity'));
                    $total_students = array_sum(array_column($classrooms, 'student_count'));
                    $available = $total_capacity - $total_students;
                    ?>
                    <h3 class="mb-0"><?php echo $available; ?></h3>
                    <p class="card-text mb-0">Boş Kontenjan</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Öğrenci Atamaları</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="assignment-form">
                <!-- Filtreleme ve Arama -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="search-input" class="form-control" placeholder="Öğrenci adı, okul veya sınıf ile ara...">
                            <button type="button" id="clear-search" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Temizle
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="age-filter" class="form-select">
                            <option value="">- Yaş Filtresi -</option>
                            <option value="6-8">6-8 yaş</option>
                            <option value="9-12">9-12 yaş</option>
                            <option value="13+">13+ yaş</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="class-filter" class="form-select">
                            <option value="">- Sınıf Durumu -</option>
                            <option value="no-class">Sınıfı Olmayanlar</option>
                            <option value="has-class">Sınıfı Olanlar</option>
                        </select>
                    </div>
                </div>

                <!-- Toplu Atama -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select id="bulk-assign-class" class="form-select">
                            <option value="">- Toplu Sınıf Ataması -</option>
                            <?php foreach ($classrooms as $classroom): ?>
                                <?php
                                $available = $classroom['capacity'] - $classroom['student_count'];
                                if ($available <= 0) continue;
                                ?>
                                <option value="<?php echo $classroom['id']; ?>" data-available="<?php echo $available; ?>">
                                    <?php echo htmlspecialchars($classroom['name']); ?>
                                    (<?php echo $classroom['age_group']; ?> - <?php echo $available; ?> boş)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="btn-group">
                            <button type="button" id="apply-to-selected" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Seçili Öğrencilere Uygula
                            </button>
                            <button type="button" id="select-all-visible" class="btn btn-outline-secondary">
                                <i class="bi bi-check2-all"></i> Görünenleri Seç
                            </button>
                            <button type="button" id="unselect-all" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Seçimi Temizle
                            </button>
                        </div>
                        <span id="counter" class="badge bg-info ms-2">0 öğrenci seçildi</span>
                    </div>
                </div>

                <!-- Öğrenci Tablosu -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" id="select-all">
                                </th>
                                <th>Ad Soyad</th>
                                <th>Yaş</th>
                                <th>Okul</th>
                                <th>Mevcut Sınıf</th>
                                <th>Sınıf Atama</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $age = $student['age'];
                                $age_group = '';
                                if ($age >= 6 && $age <= 8) $age_group = '6-8';
                                elseif ($age >= 9 && $age <= 12) $age_group = '9-12';
                                elseif ($age >= 13) $age_group = '13+';

                                $has_class = !empty($student['current_classes']) ? 'has-class' : 'no-class';
                                ?>
                                <tr class="student-row"
                                    data-age="<?php echo $age; ?>"
                                    data-age-group="<?php echo $age_group; ?>"
                                    data-class-status="<?php echo $has_class; ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['school'] . ' ' . $student['current_classes'])); ?>">
                                    <td>
                                        <input type="checkbox" class="student-select" value="<?php echo $student['id']; ?>">
                                    </td>
                                    <td>
                                        <a href="../students/view.php?id=<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $age; ?></td>
                                    <td><?php echo htmlspecialchars($student['school']); ?></td>
                                    <td><?php echo htmlspecialchars($student['current_classes'] ?: '-'); ?></td>
                                    <td>
                                        <select name="assignments[<?php echo $student['id']; ?>]" class="form-select classroom-select">
                                            <option value="">- Sınıf Seçin -</option>
                                            <?php foreach ($classrooms as $classroom): ?>
                                                <?php
                                                $available = $classroom['capacity'] - $classroom['student_count'];
                                                $disabled = $available <= 0 ? 'disabled' : '';
                                                $match_age = false;

                                                // Yaş grubuna göre tavsiye et
                                                if (
                                                    ($age_group === '6-8' && $classroom['age_group'] === '6-8 yaş') ||
                                                    ($age_group === '9-12' && $classroom['age_group'] === '9-12 yaş') ||
                                                    ($age_group === '13+' && $classroom['age_group'] === '13+ yaş')
                                                ) {
                                                    $match_age = true;
                                                }
                                                ?>
                                                <option value="<?php echo $classroom['id']; ?>" <?php echo $disabled; ?>
                                                    class="<?php echo $match_age ? 'text-success fw-bold' : ''; ?>"
                                                    data-available="<?php echo $available; ?>">
                                                    <?php echo htmlspecialchars($classroom['name']); ?>
                                                    (<?php echo $available; ?>/<?php echo $classroom['capacity']; ?> boş)
                                                    <?php echo $match_age ? ' ✓' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="save_assignments" class="btn btn-success">
                        <i class="bi bi-save"></i> Tüm Atamaları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Değişkenler
            const searchInput = document.getElementById('search-input');
            const ageFilter = document.getElementById('age-filter');
            const classFilter = document.getElementById('class-filter');
            const selectAll = document.getElementById('select-all');
            const selectAllVisible = document.getElementById('select-all-visible');
            const unselectAll = document.getElementById('unselect-all');
            const studentRows = document.querySelectorAll('.student-row');
            const counter = document.getElementById('counter');
            const bulkAssignClass = document.getElementById('bulk-assign-class');
            const applyToSelected = document.getElementById('apply-to-selected');
            const clearSearch = document.getElementById('clear-search');

            // Filtreleme fonksiyonu
            function applyFilters() {
                const searchText = searchInput.value.toLowerCase();
                const selectedAge = ageFilter.value;
                const selectedClassStatus = classFilter.value;
                let visibleCount = 0;

                studentRows.forEach(row => {
                    let visible = true;

                    // Metin araması
                    if (searchText) {
                        const searchData = row.getAttribute('data-search');
                        if (!searchData.includes(searchText)) {
                            visible = false;
                        }
                    }

                    // Yaş filtresi
                    if (selectedAge && visible) {
                        const ageGroup = row.getAttribute('data-age-group');
                        if (ageGroup !== selectedAge) {
                            visible = false;
                        }
                    }

                    // Sınıf durumu filtresi
                    if (selectedClassStatus && visible) {
                        const classStatus = row.getAttribute('data-class-status');
                        if (classStatus !== selectedClassStatus) {
                            visible = false;
                        }
                    }

                    // Görünürlüğü ayarla
                    if (visible) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // "Tümünü seç" checkbox'ını sıfırla
                selectAll.checked = false;

                // Sayacı güncelle
                updateCounter();
            }

            // Sayaç güncelleme
            function updateCounter() {
                const selectedCount = document.querySelectorAll('.student-select:checked').length;
                counter.textContent = selectedCount + ' öğrenci seçildi';
            }

            // Arama ve filtreleri dinle
            searchInput.addEventListener('input', applyFilters);
            ageFilter.addEventListener('change', applyFilters);
            classFilter.addEventListener('change', applyFilters);

            // Tümünü seç/kaldır
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.student-row:not([style*="display: none"]) .student-select').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateCounter();
            });

            // Görünenleri seç
            selectAllVisible.addEventListener('click', function() {
                document.querySelectorAll('.student-row:not([style*="display: none"]) .student-select').forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateCounter();
            });

            // Seçimi temizle
            unselectAll.addEventListener('click', function() {
                document.querySelectorAll('.student-select').forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateCounter();
            });

            // Aramaları temizle
            clearSearch.addEventListener('click', function() {
                searchInput.value = '';
                ageFilter.value = '';
                classFilter.value = '';
                applyFilters();
            });

            // Seçili öğrencilere sınıf ata
            applyToSelected.addEventListener('click', function() {
                const classroomId = bulkAssignClass.value;

                if (!classroomId) {
                    alert('Lütfen bir sınıf seçin!');
                    return;
                }

                const selectedStudents = document.querySelectorAll('.student-select:checked');

                if (selectedStudents.length === 0) {
                    alert('Lütfen en az bir öğrenci seçin!');
                    return;
                }

                // Kapasite kontrolü
                const availableOption = bulkAssignClass.options[bulkAssignClass.selectedIndex];
                const availableSpots = parseInt(availableOption.getAttribute('data-available'));

                if (selectedStudents.length > availableSpots) {
                    alert(`Seçilen sınıfta yeterli kontenjan yok! En fazla ${availableSpots} öğrenci atayabilirsiniz.`);
                    return;
                }

                // Seçili öğrencilere sınıf ata
                selectedStudents.forEach(checkbox => {
                    const studentId = checkbox.value;
                    const row = checkbox.closest('tr');
                    const selectBox = row.querySelector('.classroom-select');
                    selectBox.value = classroomId;
                });

                alert(`${selectedStudents.length} öğrenci için sınıf ataması yapıldı. Değişiklikleri kaydetmek için "Tüm Atamaları Kaydet" butonuna tıklayın.`);
            });

            // Öğrenci seçimleri değiştiğinde sayacı güncelle
            document.querySelectorAll('.student-select').forEach(checkbox => {
                checkbox.addEventListener('change', updateCounter);
            });

            // Form gönderilmeden önce ek kontrol
            document.getElementById('assignment-form').addEventListener('submit', function(e) {
                const atLeastOneAssignment = Array.from(document.querySelectorAll('.classroom-select')).some(select => select.value !== '');

                if (!atLeastOneAssignment) {
                    e.preventDefault();
                    alert('En az bir öğrenciye sınıf ataması yapmalısınız!');
                }
            });

            // İlk yükleme
            updateCounter();
        });
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>