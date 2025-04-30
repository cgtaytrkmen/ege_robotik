<?php
// modules/students/index.php - Öğrenci listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Öğrencileri getir (mevcut dönem için)
// Eğer student_parents tablosu varsa onu kullan, yoksa parent_id'den çek
$check_table = db()->query("SHOW TABLES LIKE 'student_parents'")->fetch();
if ($check_table) {
    // Çoklu veli sistemi aktif
    $query = "SELECT s.*, 
              GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as parent_names,
              GROUP_CONCAT(p.phone SEPARATOR ', ') as parent_phones,
              MAX(CASE WHEN spx.is_primary = 1 THEN p.first_name ELSE NULL END) as parent_first_name,
              MAX(CASE WHEN spx.is_primary = 1 THEN p.last_name ELSE NULL END) as parent_last_name,
              MAX(CASE WHEN spx.is_primary = 1 THEN p.phone ELSE NULL END) as parent_phone,
              sp.status as period_status, sp.enrollment_date as period_enrollment_date
              FROM students s
              LEFT JOIN student_parents spx ON s.id = spx.student_id
              LEFT JOIN parents p ON spx.parent_id = p.id
              LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
              WHERE sp.period_id IS NOT NULL
              GROUP BY s.id
              ORDER BY s.id DESC";
} else {
    // Tek veli sistemi
    $query = "SELECT s.*, p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone as parent_phone,
              sp.status as period_status, sp.enrollment_date as period_enrollment_date
              FROM students s
              LEFT JOIN parents p ON s.parent_id = p.id
              LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
              WHERE sp.period_id IS NOT NULL
              ORDER BY s.id DESC";
}
$students = safeQuery($query, [$current_period['id']])->fetchAll();

$page_title = 'Öğrenci Listesi - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Listesi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Öğrenci
        </a>
        <a href="trial-list.php" class="btn btn-outline-info">
            <i class="bi bi-person-badge"></i> Deneme Dersi Öğrencileri
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad Soyad</th>
                        <th>Yaş</th>
                        <th>Veli</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                        <th>Döneme Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><?php echo calculateAge($student['birth_date']); ?></td>
                            <td><?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?></td>
                            <td><?php echo formatPhone($student['parent_phone']); ?></td>
                            <td>
                                <?php
                                $status_badges = [
                                    'active' => 'success',
                                    'passive' => 'secondary',
                                    'trial' => 'info',
                                    'completed' => 'primary'
                                ];
                                $status_labels = [
                                    'active' => 'Aktif',
                                    'passive' => 'Pasif',
                                    'trial' => 'Deneme',
                                    'completed' => 'Tamamlandı'
                                ];
                                $period_status = $student['period_status'] ?? 'passive';
                                $badge_class = $status_badges[$period_status] ?? 'secondary';
                                $label = $status_labels[$period_status] ?? $period_status;
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                            </td>
                            <td><?php echo formatDate($student['period_enrollment_date'] ?? $student['enrollment_date']); ?></td>
                            <td>
                                <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($period_status !== 'active'): ?>
                                    <a href="activate.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-success" title="Aktifleştir">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="delete.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" title="Sil"
                                    onclick="return confirm('Bu öğrenciyi silmek istediğinizden emin misiniz?');">
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