<?php
// modules/curriculum/view.php - Müfredat detayları görüntüleme sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$curriculum_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$curriculum_id) {
    setAlert('Geçersiz müfredat ID!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Müfredat bilgilerini getir
$curriculum_query = "SELECT c.*, p.name as period_name 
                    FROM curriculum c
                    JOIN periods p ON c.period_id = p.id
                    WHERE c.id = ?";
$curriculum = safeQuery($curriculum_query, [$curriculum_id])->fetch();

if (!$curriculum) {
    setAlert('Müfredat bulunamadı!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Haftalık konuları getir
$topics_query = "SELECT * FROM curriculum_weekly_topics 
                WHERE curriculum_id = ? 
                ORDER BY week_number";
$weekly_topics = safeQuery($topics_query, [$curriculum_id])->fetchAll();

// Bu müfredata atanmış sınıfları getir
$classes_query = "SELECT cc.*, c.name as classroom_name, c.age_group,
                 (SELECT COUNT(*) FROM student_classrooms sc WHERE sc.classroom_id = c.id AND sc.status = 'active') as student_count
                 FROM classroom_curriculum cc
                 JOIN classrooms c ON cc.classroom_id = c.id
                 WHERE cc.curriculum_id = ?
                 ORDER BY c.name";
$assigned_classes = safeQuery($classes_query, [$curriculum_id])->fetchAll();

// Müfredatın kullanım istatistiklerini hesapla
$total_assigned_classes = count($assigned_classes);
$total_assigned_students = 0;
foreach ($assigned_classes as $class) {
    $total_assigned_students += intval($class['student_count']);
}

// Öğrenci ilerleme istatistiklerini getir
$progress_query = "SELECT 
                  COUNT(*) as total_students,
                  AVG(current_week) as avg_week,
                  COUNT(CASE WHEN current_week = ? THEN 1 END) as completed_students
                  FROM student_curriculum_progress 
                  WHERE curriculum_id = ?";
$progress_stats = safeQuery($progress_query, [$curriculum['total_weeks'], $curriculum_id])->fetch();

$page_title = 'Müfredat Detayları - ' . $curriculum['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Müfredat Detayları</h2>
    <div>
        <a href="edit.php?id=<?php echo $curriculum_id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Düzenle
        </a>
        <a href="topics.php?id=<?php echo $curriculum_id; ?>" class="btn btn-success">
            <i class="bi bi-list-check"></i> Haftalık Konuları Düzenle
        </a>
        <a href="assign.php" class="btn btn-info">
            <i class="bi bi-link"></i> Sınıf Ataması
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<!-- Müfredat Özeti Kartı -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title"><?php echo htmlspecialchars($curriculum['name']); ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="30%">Yaş Grubu:</th>
                        <td><span class="badge bg-info"><?php echo htmlspecialchars($curriculum['age_group']); ?></span></td>
                    </tr>
                    <tr>
                        <th>Dönem:</th>
                        <td><?php echo htmlspecialchars($curriculum['period_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Toplam Hafta:</th>
                        <td><?php echo $curriculum['total_weeks']; ?> hafta</td>
                    </tr>
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($curriculum['status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="col-md-6">
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Atanmış Sınıf</h6>
                                <h3 class="mb-0"><?php echo $total_assigned_classes; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Toplam Öğrenci</h6>
                                <h3 class="mb-0"><?php echo $total_assigned_students; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Ortalama İlerleme</h6>
                                <h3 class="mb-0">
                                    <?php
                                    $avg_week = $progress_stats['avg_week'] ?? 0;
                                    echo number_format($avg_week, 1);
                                    ?>
                                    <small>/<?php echo $curriculum['total_weeks']; ?></small>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card bg-warning text-dark h-100">
                            <div class="card-body text-center">
                                <h6 class="card-title">Tamamlayan</h6>
                                <h3 class="mb-0">
                                    <?php echo $progress_stats['completed_students'] ?? 0; ?>
                                    <small>/<?php echo $progress_stats['total_students'] ?? 0; ?></small>
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($curriculum['description'])): ?>
            <div class="mt-3">
                <h6>Açıklama:</h6>
                <div class="p-3 bg-light rounded">
                    <?php echo nl2br(htmlspecialchars($curriculum['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Haftalık Konular -->
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Haftalık Konular</h5>
                <a href="topics.php?id=<?php echo $curriculum_id; ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil"></i> Düzenle
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($weekly_topics)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle"></i> Bu müfredat için henüz haftalık konular eklenmemiş.
                        <a href="topics.php?id=<?php echo $curriculum_id; ?>" class="alert-link">Konuları eklemek için tıklayın</a>.
                    </div>
                <?php else: ?>
                    <div class="accordion" id="topicsAccordion">
                        <?php foreach ($weekly_topics as $index => $topic): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?php echo ($index != 0) ? 'collapsed' : ''; ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $topic['id']; ?>">
                                        <span class="badge bg-primary me-2">Hafta <?php echo $topic['week_number']; ?></span>
                                        <?php echo htmlspecialchars($topic['topic_title']); ?>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $topic['id']; ?>" class="accordion-collapse collapse <?php echo ($index == 0) ? 'show' : ''; ?>"
                                    data-bs-parent="#topicsAccordion">
                                    <div class="accordion-body">
                                        <?php if (!empty($topic['description'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-primary">Açıklama:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($topic['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($topic['learning_objectives'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-success">Öğrenme Hedefleri:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($topic['learning_objectives'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($topic['materials_needed'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-info">Gerekli Malzemeler:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($topic['materials_needed'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($topic['homework'])): ?>
                                            <div class="mb-3">
                                                <h6 class="text-warning">Ev Ödevi:</h6>
                                                <p><?php echo nl2br(htmlspecialchars($topic['homework'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Atanmış Sınıflar -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Atanmış Sınıflar</h5>
                <a href="assign.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus"></i> Yeni Atama
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($assigned_classes)): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i> Bu müfredat henüz hiçbir sınıfa atanmamış.
                        <a href="assign.php" class="alert-link">Sınıf ataması yapmak için tıklayın</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Sınıf</th>
                                    <th>Yaş Grubu</th>
                                    <th>Öğrenci</th>
                                    <th>Başlangıç</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_classes as $class): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($class['classroom_name']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($class['age_group']); ?></span></td>
                                        <td><?php echo $class['student_count']; ?></td>
                                        <td><?php echo formatDate($class['start_date']); ?></td>
                                        <td>
                                            <a href="../curriculum/student_progress.php?classroom_id=<?php echo $class['classroom_id']; ?>&curriculum_id=<?php echo $curriculum_id; ?>"
                                                class="btn btn-sm btn-success" title="İlerleme Takibi">
                                                <i class="bi bi-person-lines-fill"></i>
                                            </a>
                                            <a href="delete_assignment.php?id=<?php echo $class['id']; ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bu atamayı silmek istediğinizden emin misiniz?');"
                                                title="Atamayı Sil">
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

<?php require_once '../../includes/footer.php'; ?>