<?php
// modules/curriculum/student_progress.php - Öğrenci müfredat ilerleme takibi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Sınıf ID kontrolü
$classroom_id = isset($_GET['classroom_id']) ? intval($_GET['classroom_id']) : 0;

// Müfredat ID kontrolü
$curriculum_id = isset($_GET['curriculum_id']) ? intval($_GET['curriculum_id']) : 0;

// Eğer sınıf ID'si verildiyse, sınıf bilgilerini getir
$classroom = null;
if ($classroom_id) {
    $classroom_query = "SELECT * FROM classrooms WHERE id = ?";
    $classroom = safeQuery($classroom_query, [$classroom_id])->fetch();

    if (!$classroom) {
        setAlert('Sınıf bulunamadı!', 'danger');
        redirect('modules/curriculum/index.php');
    }

    // Bu sınıfa atanmış müfredatı getir (müfredat ID belirtilmemişse)
    if (!$curriculum_id) {
        $curr_query = "SELECT cc.curriculum_id 
                      FROM classroom_curriculum cc
                      WHERE cc.classroom_id = ?
                      LIMIT 1";
        $curr_result = safeQuery($curr_query, [$classroom_id])->fetch();

        if ($curr_result) {
            $curriculum_id = $curr_result['curriculum_id'];
        }
    }
}

// Eğer müfredat ID'si varsa, müfredat bilgilerini getir
$curriculum = null;
if ($curriculum_id) {
    $curriculum_query = "SELECT c.*, p.name as period_name 
                       FROM curriculum c
                       JOIN periods p ON c.period_id = p.id
                       WHERE c.id = ?";
    $curriculum = safeQuery($curriculum_query, [$curriculum_id])->fetch();

    if (!$curriculum) {
        setAlert('Müfredat bulunamadı!', 'danger');
        redirect('modules/curriculum/index.php');
    }

    // Müfredatın haftalık konularını getir
    $topics_query = "SELECT * FROM curriculum_weekly_topics 
                   WHERE curriculum_id = ? 
                   ORDER BY week_number";
    $weekly_topics = safeQuery($topics_query, [$curriculum_id])->fetchAll();
}

// Sınıf ve müfredat belirlendiyse, bu sınıftaki öğrencilerin müfredat ilerlemesini getir
$student_progress = [];
if ($classroom_id && $curriculum_id) {
    $progress_query = "SELECT s.id, s.first_name, s.last_name, s.phone, s.school,
                      sp.current_week, sp.completed_topics, sp.notes, sp.id as progress_id, 
                      TIMESTAMPDIFF(YEAR, s.birth_date, CURDATE()) as age
                      FROM students s
                      JOIN student_classrooms sc ON s.id = sc.student_id
                      LEFT JOIN student_curriculum_progress sp ON s.id = sp.student_id AND sp.curriculum_id = ?
                      WHERE sc.classroom_id = ? AND sc.status = 'active'
                      ORDER BY s.first_name, s.last_name";
    $student_progress = safeQuery($progress_query, [$curriculum_id, $classroom_id])->fetchAll();
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    try {
        db()->beginTransaction();

        // Öğrencilerin ilerlemelerini güncelle
        foreach ($_POST['student_id'] as $key => $student_id) {
            $current_week = intval($_POST['current_week'][$key]);
            $notes = clean($_POST['notes'][$key] ?? '');
            $progress_id = intval($_POST['progress_id'][$key] ?? 0);

            // Tamamlanan konuları işle
            $completed_topics = [];
            if (isset($_POST['completed_topic'][$student_id]) && is_array($_POST['completed_topic'][$student_id])) {
                $completed_topics = $_POST['completed_topic'][$student_id];
            }
            $completed_topics_json = json_encode($completed_topics);

            // Progress kaydı var mı kontrol et
            if ($progress_id > 0) {
                // Güncelle
                $update_sql = "UPDATE student_curriculum_progress 
                              SET current_week = ?, 
                                  completed_topics = ?, 
                                  notes = ? 
                              WHERE id = ?";
                safeQuery($update_sql, [$current_week, $completed_topics_json, $notes, $progress_id]);
            } else {
                // Yeni ekle
                $insert_sql = "INSERT INTO student_curriculum_progress 
                              (student_id, curriculum_id, current_week, completed_topics, notes) 
                              VALUES (?, ?, ?, ?, ?)";
                safeQuery($insert_sql, [$student_id, $curriculum_id, $current_week, $completed_topics_json, $notes]);
            }
        }

        db()->commit();

        setAlert('Öğrenci ilerleme durumları başarıyla güncellendi!', 'success');
        redirect('modules/curriculum/student_progress.php?classroom_id=' . $classroom_id . '&curriculum_id=' . $curriculum_id);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Aktif sınıfları getir
$classrooms_query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM student_classrooms sc WHERE sc.classroom_id = c.id AND sc.status = 'active') as student_count
                    FROM classrooms c 
                    WHERE c.status = 'active' 
                    ORDER BY c.name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Aktif müfredatları getir
$curricula_query = "SELECT c.*, p.name as period_name
                  FROM curriculum c
                  JOIN periods p ON c.period_id = p.id
                  WHERE c.status = 'active'
                  ORDER BY c.age_group, c.name";
$curricula = db()->query($curricula_query)->fetchAll();

$page_title = 'Öğrenci Müfredat İlerleme Takibi';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Müfredat İlerleme Takibi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Müfredat Sayfasına Dön
    </a>
</div>

<!-- Sınıf ve Müfredat Seçimi -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Sınıf ve Müfredat Seçimi</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row">
            <div class="col-md-5 mb-3">
                <label for="classroom_id" class="form-label">Sınıf Seçin</label>
                <select class="form-select" id="classroom_id" name="classroom_id" required>
                    <option value="">-- Sınıf Seçin --</option>
                    <?php foreach ($classrooms as $class): ?>
                        <option value="<?php echo $class['id']; ?>" <?php echo ($classroom_id == $class['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class['name']); ?>
                            (<?php echo htmlspecialchars($class['age_group']); ?> -
                            <?php echo $class['student_count']; ?> öğrenci)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5 mb-3">
                <label for="curriculum_id" class="form-label">Müfredat Seçin</label>
                <select class="form-select" id="curriculum_id" name="curriculum_id" required>
                    <option value="">-- Müfredat Seçin --</option>
                    <?php foreach ($curricula as $curr): ?>
                        <option value="<?php echo $curr['id']; ?>" <?php echo ($curriculum_id == $curr['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($curr['name']); ?>
                            (<?php echo htmlspecialchars($curr['age_group']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Göster
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($classroom && $curriculum): ?>
    <!-- Sınıf ve Müfredat Bilgileri -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Bilgiler</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Sınıf Bilgileri</h6>
                    <p><strong>Sınıf Adı:</strong> <?php echo htmlspecialchars($classroom['name']); ?></p>
                    <p><strong>Yaş Grubu:</strong> <?php echo htmlspecialchars($classroom['age_group']); ?></p>
                    <p><strong>Öğrenci Sayısı:</strong> <?php echo count($student_progress); ?></p>
                </div>
                <div class="col-md-6">
                    <h6>Müfredat Bilgileri</h6>
                    <p><strong>Müfredat Adı:</strong> <?php echo htmlspecialchars($curriculum['name']); ?></p>
                    <p><strong>Yaş Grubu:</strong> <?php echo htmlspecialchars($curriculum['age_group']); ?></p>
                    <p><strong>Toplam Hafta:</strong> <?php echo $curriculum['total_weeks']; ?> hafta</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Öğrenci İlerleme Durumları -->
    <form method="POST" action="">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Öğrenci İlerleme Durumları</h5>
            </div>
            <div class="card-body">
                <?php if (empty($student_progress)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu sınıfta öğrenci bulunmuyor.
                    </div>
                <?php elseif (empty($weekly_topics)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> Bu müfredat için henüz haftalık konular eklenmemiş.
                        <a href="topics.php?id=<?php echo $curriculum_id; ?>" class="alert-link">Haftalık konuları eklemek için tıklayın</a>.
                    </div>
                <?php else: ?>
                    <!-- Toplu İşlemler -->
                    <div class="mb-4 text-end">
                        <div class="btn-group">
                            <?php for ($week = 1; $week <= $curriculum['total_weeks']; $week++): ?>
                                <button type="button" class="btn btn-outline-primary set-all-week" data-week="<?php echo $week; ?>">
                                    Hepsini <?php echo $week; ?>. Haftaya Getir
                                </button>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Öğrenci</th>
                                    <th>Yaş</th>
                                    <th>Şu Anki Hafta</th>
                                    <th>Tamamlanan Konular</th>
                                    <th>Notlar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($student_progress as $index => $student): ?>
                                    <?php
                                    // Öğrencinin tamamladığı konuları JSON'dan diziye çevir
                                    $completed_topics = [];
                                    if (!empty($student['completed_topics'])) {
                                        $completed_topics = json_decode($student['completed_topics'], true) ?? [];
                                    }
                                    // Öğrencinin mevcut hafta numarası
                                    $current_week = $student['current_week'] ?? 1;
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="student_id[<?php echo $index; ?>]" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="progress_id[<?php echo $index; ?>]" value="<?php echo $student['progress_id'] ?? 0; ?>">
                                            <a href="../students/view.php?id=<?php echo $student['id']; ?>">
                                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $student['age']; ?></td>
                                        <td>
                                            <select class="form-select" name="current_week[<?php echo $index; ?>]">
                                                <?php for ($week = 1; $week <= $curriculum['total_weeks']; $week++): ?>
                                                    <option value="<?php echo $week; ?>" <?php echo ($current_week == $week) ? 'selected' : ''; ?>>
                                                        Hafta <?php echo $week; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <?php foreach ($weekly_topics as $topic): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="completed_topic[<?php echo $student['id']; ?>][]"
                                                        value="<?php echo $topic['id']; ?>"
                                                        id="topic_<?php echo $student['id']; ?>_<?php echo $topic['id']; ?>"
                                                        <?php echo in_array($topic['id'], $completed_topics) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="topic_<?php echo $student['id']; ?>_<?php echo $topic['id']; ?>">
                                                        Hafta <?php echo $topic['week_number']; ?>: <?php echo htmlspecialchars($topic['topic_title']); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <textarea class="form-control" name="notes[<?php echo $index; ?>]" rows="2"><?php echo htmlspecialchars($student['notes'] ?? ''); ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="update_progress" class="btn btn-primary">
                            <i class="bi bi-save"></i> İlerleme Durumlarını Güncelle
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tüm öğrencileri belirli bir haftaya getirme butonu
            document.querySelectorAll('.set-all-week').forEach(button => {
                button.addEventListener('click', function() {
                    const week = this.getAttribute('data-week');

                    // Tüm hafta seçicileri güncelle
                    document.querySelectorAll('select[name^="current_week"]').forEach(select => {
                        select.value = week;
                    });

                    alert(`Tüm öğrenciler ${week}. haftaya getirildi. Değişiklikleri kaydetmek için "İlerleme Durumlarını Güncelle" butonuna tıklayın.`);
                });
            });
        });
    </script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>