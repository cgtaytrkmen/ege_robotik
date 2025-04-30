<?php
// check_attendance_table.php - Attendance tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Yoklama Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Yoklama Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Yapısı</h5>
        <?php
        $columns = db()->query("DESCRIBE attendance")->fetchAll();
        ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Alan Adı</th>
                    <th>Tip</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $column): ?>
                    <tr>
                        <td><?php echo $column['Field']; ?></td>
                        <td><?php echo $column['Type']; ?></td>
                        <td><?php echo $column['Null']; ?></td>
                        <td><?php echo $column['Key']; ?></td>
                        <td><?php echo $column['Default']; ?></td>
                        <td><?php echo $column['Extra']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h5 class="mt-4">Eksik Kolonlar</h5>
        <?php
        $required_columns = [
            'id',
            'student_id',
            'lesson_id',
            'attendance_date',
            'status',
            'notes',
            'created_at',
            'updated_at'
        ];

        $existing_columns = array_column($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);

        if (empty($missing_columns)): ?>
            <div class="alert alert-success">Tüm gerekli kolonlar mevcut.</div>
        <?php else: ?>
            <div class="alert alert-danger">
                <p>Eksik kolonlar bulundu:</p>
                <ul>
                    <?php foreach ($missing_columns as $column): ?>
                        <li><?php echo $column; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <h5 class="mt-4">Düzeltme SQL Komutları</h5>
            <pre class="bg-light p-3">
<?php foreach ($missing_columns as $column): ?>
<?php if ($column == 'created_at'): ?>
ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
<?php elseif ($column == 'updated_at'): ?>
ALTER TABLE attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
<?php endif; ?>
<?php endforeach; ?>
            </pre>

            <form method="POST" action="">
                <button type="submit" name="fix_table" class="btn btn-primary">
                    <i class="bi bi-tools"></i> Eksik Kolonları Ekle
                </button>
            </form>
        <?php endif; ?>

        <?php
        // Eksik kolonları ekle
        if (isset($_POST['fix_table']) && !empty($missing_columns)) {
            try {
                foreach ($missing_columns as $column) {
                    if ($column == 'created_at') {
                        db()->exec("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    } elseif ($column == 'updated_at') {
                        db()->exec("ALTER TABLE attendance ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_attendance_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">Mevcut Kayıtlar</h5>
        <?php
        $attendance_records = db()->query("SELECT a.*, s.first_name as student_first_name, s.last_name as student_last_name, 
                                          l.day, l.start_time, c.name as classroom_name
                                          FROM attendance a
                                          LEFT JOIN students s ON a.student_id = s.id
                                          LEFT JOIN lessons l ON a.lesson_id = l.id
                                          LEFT JOIN classrooms c ON l.classroom_id = c.id
                                          LIMIT 10")->fetchAll();
        if (empty($attendance_records)): ?>
            <div class="alert alert-warning">Yoklama kaydı bulunamadı.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Öğrenci</th>
                        <th>Sınıf</th>
                        <th>Gün/Saat</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo $record['id']; ?></td>
                            <td><?php echo htmlspecialchars($record['student_first_name'] . ' ' . $record['student_last_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['classroom_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $record['day'] . ' ' . substr($record['start_time'], 0, 5); ?></td>
                            <td><?php echo formatDate($record['attendance_date']); ?></td>
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
                                $badge_class = $status_badges[$record['status']] ?? 'secondary';
                                $label = $status_labels[$record['status']] ?? $record['status'];
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>