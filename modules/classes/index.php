<?php
// modules/classes/index.php - Sınıf listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// student_classrooms tablosunun varlığını kontrol et
$check_table = db()->query("SHOW TABLES LIKE 'student_classrooms'")->fetch();

// Sınıfları getir
if ($check_table) {
    // student_classrooms tablosu varsa
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM lessons l WHERE l.classroom_id = c.id AND l.period_id = ?) as lesson_count,
              (SELECT COUNT(DISTINCT sc.student_id) 
               FROM student_classrooms sc 
               WHERE sc.classroom_id = c.id AND sc.status = 'active') as student_count
              FROM classrooms c
              ORDER BY c.name";
} else {
    // student_classrooms tablosu yoksa
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM lessons l WHERE l.classroom_id = c.id AND l.period_id = ?) as lesson_count,
              0 as student_count
              FROM classrooms c
              ORDER BY c.name";
}

$classrooms = safeQuery($query, [$current_period['id']])->fetchAll();

$page_title = 'Sınıf Yönetimi - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sınıf Yönetimi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Sınıf
        </a>
        <a href="bulk-assign.php" class="btn btn-success">
            <i class="bi bi-people"></i> Toplu Öğrenci Atama
        </a>
    </div>
</div>

<!-- Sınıf İstatistikleri -->
<div class="row mb-4">
    <?php
    $total_classrooms = count($classrooms);
    $total_students = array_sum(array_column($classrooms, 'student_count'));
    $total_capacity = array_sum(array_column($classrooms, 'capacity'));
    $occupancy_rate = $total_capacity > 0 ? round($total_students / $total_capacity * 100, 1) : 0;
    ?>
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Toplam Sınıf</h5>
                <h2 class="card-text"><?php echo $total_classrooms; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Toplam Öğrenci</h5>
                <h2 class="card-text"><?php echo $total_students; ?> / <?php echo $total_capacity; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Doluluk Oranı</h5>
                <h2 class="card-text"><?php echo $occupancy_rate; ?>%</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Boş Kapasite</h5>
                <h2 class="card-text"><?php echo $total_capacity - $total_students; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sınıf Adı</th>
                        <th>Yaş Grubu</th>
                        <th>Kapasite</th>
                        <th>Doluluk</th>
                        <th>Ders Sayısı</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $classroom): ?>
                        <?php
                        $occupancy_percentage = round($classroom['student_count'] / $classroom['capacity'] * 100);
                        $occupancy_class = 'success';
                        if ($occupancy_percentage > 80) {
                            $occupancy_class = 'danger';
                        } else if ($occupancy_percentage > 60) {
                            $occupancy_class = 'warning';
                        }
                        ?>
                        <tr>
                            <td><?php echo $classroom['id']; ?></td>
                            <td><?php echo htmlspecialchars($classroom['name']); ?></td>
                            <td><?php echo htmlspecialchars($classroom['age_group']); ?></td>
                            <td><?php echo $classroom['capacity']; ?></td>
                            <td>
                                <div class="progress" style="min-width: 100px; height: 20px;">
                                    <div class="progress-bar bg-<?php echo $occupancy_class; ?>"
                                        role="progressbar"
                                        style="width: <?php echo $occupancy_percentage; ?>%"
                                        aria-valuenow="<?php echo $occupancy_percentage; ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">
                                        <?php echo $classroom['student_count']; ?>/<?php echo $classroom['capacity']; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $classroom['lesson_count']; ?></td>
                            <td>
                                <?php if ($classroom['status'] == 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="assign-students.php?id=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-success" title="Öğrenci Ata">
                                    <i class="bi bi-people"></i>
                                </a>
                                <a href="copy.php?id=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-warning" title="Kopyala">
                                    <i class="bi bi-files"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $classroom['id']; ?>" class="btn btn-sm btn-danger"
                                    title="Sil" onclick="return confirm('Bu sınıfı silmek istediğinizden emin misiniz?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>