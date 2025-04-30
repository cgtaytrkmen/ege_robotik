<?php
// check_period_weeks_table.php - Period_weeks tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Hafta Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Hafta Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $table_exists = db()->query("SHOW TABLES LIKE 'period_weeks'")->fetch();
        ?>
        <div class="alert alert-<?php echo $table_exists ? 'success' : 'danger'; ?>">
            period_weeks tablosu <?php echo $table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Tablo Yapısı</h5>
            <?php
            $columns = db()->query("DESCRIBE period_weeks")->fetchAll();
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
                'period_id',
                'week_number',
                'name',
                'start_date',
                'end_date',
                'is_free',
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
            <?php endif; ?>
        <?php else: ?>
            <h5 class="mt-4">Tablo Oluşturma</h5>
            <pre class="bg-light p-3">
CREATE TABLE period_weeks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    week_number INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_free TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
);
            </pre>

            <form method="POST" action="">
                <button type="submit" name="create_table" class="btn btn-primary">
                    <i class="bi bi-database-add"></i> Tabloyu Oluştur
                </button>
            </form>
        <?php endif; ?>

        <?php
        // Tabloyu oluştur
        if (isset($_POST['create_table']) && !$table_exists) {
            try {
                $sql = "CREATE TABLE period_weeks (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    period_id INT NOT NULL,
                    week_number INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    is_free TINYINT(1) DEFAULT 0,
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
                )";

                db()->exec($sql);

                setAlert('period_weeks tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_period_weeks_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">İlişkili Tablo Yapısı Kontrolü</h5>

        <h6 class="mt-3">Yoklama Tablosu (attendance)</h6>
        <?php
        // Attendance tablosunda period_week_id kolonunu kontrol et
        $attendance_column = db()->query("SHOW COLUMNS FROM attendance LIKE 'period_week_id'")->fetch();
        if ($attendance_column): ?>
            <div class="alert alert-success">attendance tablosunda period_week_id kolonu mevcut.</div>
        <?php else: ?>
            <div class="alert alert-warning">
                attendance tablosunda period_week_id kolonu bulunamadı. Eklemek için:
                <pre class="bg-light p-3 mb-0">
ALTER TABLE attendance ADD COLUMN period_week_id INT AFTER lesson_id;
ALTER TABLE attendance ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id);
                </pre>
            </div>
            <form method="POST" action="">
                <button type="submit" name="add_attendance_column" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> Kolonu Ekle
                </button>
            </form>
        <?php endif; ?>

        <h6 class="mt-3">Konular Tablosu (topics)</h6>
        <?php
        // Topics tablosunda period_week_id kolonunu kontrol et
        $topics_column = db()->query("SHOW COLUMNS FROM topics LIKE 'period_week_id'")->fetch();
        if ($topics_column): ?>
            <div class="alert alert-success">topics tablosunda period_week_id kolonu mevcut.</div>
        <?php else: ?>
            <div class="alert alert-warning">
                topics tablosunda period_week_id kolonu bulunamadı. Eklemek için:
                <pre class="bg-light p-3 mb-0">
ALTER TABLE topics ADD COLUMN period_week_id INT AFTER lesson_id;
ALTER TABLE topics ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id);
                </pre>
            </div>
            <form method="POST" action="">
                <button type="submit" name="add_topics_column" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> Kolonu Ekle
                </button>
            </form>
        <?php endif; ?>

        <h6 class="mt-3">Ödemeler Tablosu (payments)</h6>
        <?php
        // Payments tablosunda period_week_id kolonunu kontrol et
        $payments_column = db()->query("SHOW COLUMNS FROM payments LIKE 'period_week_id'")->fetch();
        if ($payments_column): ?>
            <div class="alert alert-success">payments tablosunda period_week_id kolonu mevcut.</div>
        <?php else: ?>
            <div class="alert alert-warning">
                payments tablosunda period_week_id kolonu bulunamadı. Eklemek için:
                <pre class="bg-light p-3 mb-0">
ALTER TABLE payments ADD COLUMN period_week_id INT AFTER period_id;
ALTER TABLE payments ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id);
                </pre>
            </div>
            <form method="POST" action="">
                <button type="submit" name="add_payments_column" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> Kolonu Ekle
                </button>
            </form>
        <?php endif; ?>

        <?php
        // Attendance tablosuna kolon ekleme
        if (isset($_POST['add_attendance_column']) && !$attendance_column) {
            try {
                db()->exec("ALTER TABLE attendance ADD COLUMN period_week_id INT AFTER lesson_id");
                db()->exec("ALTER TABLE attendance ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id)");
                setAlert('attendance tablosuna period_week_id kolonu eklendi!', 'success');
                redirect('check_period_weeks_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Topics tablosuna kolon ekleme
        if (isset($_POST['add_topics_column']) && !$topics_column) {
            try {
                db()->exec("ALTER TABLE topics ADD COLUMN period_week_id INT AFTER lesson_id");
                db()->exec("ALTER TABLE topics ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id)");
                setAlert('topics tablosuna period_week_id kolonu eklendi!', 'success');
                redirect('check_period_weeks_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Payments tablosuna kolon ekleme
        if (isset($_POST['add_payments_column']) && !$payments_column) {
            try {
                db()->exec("ALTER TABLE payments ADD COLUMN period_week_id INT AFTER period_id");
                db()->exec("ALTER TABLE payments ADD FOREIGN KEY (period_week_id) REFERENCES period_weeks(id)");
                setAlert('payments tablosuna period_week_id kolonu eklendi!', 'success');
                redirect('check_period_weeks_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Örnek Hafta Kayıtları</h5>
            <?php
            $weeks = db()->query("SELECT w.*, p.name as period_name FROM period_weeks w 
                                 LEFT JOIN periods p ON w.period_id = p.id
                                 ORDER BY w.start_date LIMIT 10")->fetchAll();

            if (empty($weeks)): ?>
                <div class="alert alert-info">Henüz hafta kaydı bulunmuyor.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dönem</th>
                                <th>Hafta No</th>
                                <th>Hafta Adı</th>
                                <th>Tarih Aralığı</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weeks as $week): ?>
                                <tr>
                                    <td><?php echo $week['id']; ?></td>
                                    <td><?php echo htmlspecialchars($week['period_name']); ?></td>
                                    <td><?php echo $week['week_number']; ?></td>
                                    <td><?php echo htmlspecialchars($week['name']); ?></td>
                                    <td><?php echo formatDate($week['start_date']) . ' - ' . formatDate($week['end_date']); ?></td>
                                    <td>
                                        <?php if ($week['is_free']): ?>
                                            <span class="badge bg-info">Ücretsiz</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>