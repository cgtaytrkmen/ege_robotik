<?php
// modules/curriculum/index.php - Müfredat yönetimi ana sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Müfredatları getir
$query = "SELECT c.*, p.name as period_name,
          (SELECT COUNT(*) FROM classroom_curriculum WHERE curriculum_id = c.id) as class_count,
          (SELECT COUNT(*) FROM student_curriculum_progress WHERE curriculum_id = c.id) as student_count
          FROM curriculum c
          JOIN periods p ON c.period_id = p.id
          ORDER BY c.age_group, c.name";
$curricula = db()->query($query)->fetchAll();

$page_title = 'Müfredat Yönetimi';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Müfredat Yönetimi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Müfredat
        </a>
        <a href="assign.php" class="btn btn-success">
            <i class="bi bi-link"></i> Sınıf Ataması
        </a>
    </div>
</div>

<!-- Müfredat İstatistikleri -->
<div class="row mb-4">
    <?php
    $total_curricula = count($curricula);
    $active_curricula = count(array_filter($curricula, function ($c) {
        return $c['status'] == 'active';
    }));
    $total_classes = array_sum(array_column($curricula, 'class_count'));
    $total_students = array_sum(array_column($curricula, 'student_count'));
    ?>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Toplam Müfredat</h5>
                <h2 class="card-text"><?php echo $total_curricula; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Aktif Müfredat</h5>
                <h2 class="card-text"><?php echo $active_curricula; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Sınıf Ataması</h5>
                <h2 class="card-text"><?php echo $total_classes; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Takip Eden Öğrenci</h5>
                <h2 class="card-text"><?php echo $total_students; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <?php if (empty($curricula)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Henüz müfredat kaydı bulunmuyor.
                    <a href="add.php" class="alert-link">Yeni müfredat oluşturmak için tıklayın</a> veya
                    <a href="../../check_curriculum_table.php" class="alert-link">örnek verileri yükleyin</a>.
                </div>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Müfredat Adı</th>
                            <th>Yaş Grubu</th>
                            <th>Dönem</th>
                            <th>Hafta</th>
                            <th>Sınıf Sayısı</th>
                            <th>Öğrenci Sayısı</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($curricula as $curriculum): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($curriculum['name']); ?></td>
                                <td><?php echo htmlspecialchars($curriculum['age_group']); ?></td>
                                <td><?php echo htmlspecialchars($curriculum['period_name']); ?></td>
                                <td><?php echo $curriculum['total_weeks']; ?></td>
                                <td><?php echo $curriculum['class_count']; ?></td>
                                <td><?php echo $curriculum['student_count']; ?></td>
                                <td>
                                    <?php if ($curriculum['status'] == 'active'): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $curriculum['id']; ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $curriculum['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="topics.php?id=<?php echo $curriculum['id']; ?>" class="btn btn-sm btn-success" title="Haftalık Konular">
                                        <i class="bi bi-list-check"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $curriculum['id']; ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Bu müfredatı silmek istediğinizden emin misiniz? İlgili tüm veriler silinecektir.');" title="Sil">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>