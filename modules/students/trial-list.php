<?php
// modules/students/trial-list.php - Deneme dersi öğrenci listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Deneme statüsündeki öğrencileri getir
$query = "SELECT s.*, p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone as parent_phone,
          sp.enrollment_date as period_enrollment_date,
          (SELECT COUNT(*) FROM attendance a 
           JOIN lessons l ON a.lesson_id = l.id 
           WHERE a.student_id = s.id AND l.period_id = sp.period_id) as attendance_count
          FROM students s
          LEFT JOIN parents p ON s.parent_id = p.id
          LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
          WHERE sp.status = 'trial'
          ORDER BY sp.enrollment_date DESC";
$trial_students = safeQuery($query, [$current_period['id']])->fetchAll();

$page_title = 'Deneme Dersi Öğrencileri - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Deneme Dersi Öğrencileri
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Öğrenci
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Tüm Öğrenciler
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
                        <th>Kayıt Tarihi</th>
                        <th>Katıldığı Ders</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trial_students as $student): ?>
                        <tr>
                            <td><?php echo $student['id']; ?></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><?php echo calculateAge($student['birth_date']); ?></td>
                            <td><?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?></td>
                            <td><?php echo formatPhone($student['parent_phone']); ?></td>
                            <td><?php echo formatDate($student['period_enrollment_date']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $student['attendance_count'] > 0 ? 'success' : 'warning'; ?>">
                                    <?php echo $student['attendance_count']; ?> ders
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="activate.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-success" title="Aktifleştir">
                                    <i class="bi bi-check-circle"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (count($trial_students) === 0): ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle"></i> Bu dönemde deneme dersi alan öğrenci bulunmuyor.
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>