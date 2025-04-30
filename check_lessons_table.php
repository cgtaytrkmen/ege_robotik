<?php
// check_lessons_table.php - Lessons tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Dersler Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Dersler Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $table_exists = db()->query("SHOW TABLES LIKE 'lessons'")->fetch();
        ?>
        <div class="alert alert-<?php echo $table_exists ? 'success' : 'danger'; ?>">
            Lessons tablosu <?php echo $table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Tablo Yapısı</h5>
            <?php
            $columns = db()->query("DESCRIBE lessons")->fetchAll();
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
                'classroom_id',
                'period_id',
                'day',
                'start_time',
                'end_time',
                'status',
                'notes'
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
<?php if (in_array('classroom_id', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN classroom_id INT NOT NULL AFTER id;
<?php endif; ?>
<?php if (in_array('period_id', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN period_id INT NOT NULL AFTER classroom_id;
<?php endif; ?>
<?php if (in_array('day', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL AFTER period_id;
<?php endif; ?>
<?php if (in_array('start_time', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN start_time TIME NOT NULL AFTER day;
<?php endif; ?>
<?php if (in_array('end_time', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN end_time TIME NOT NULL AFTER start_time;
<?php endif; ?>
<?php if (in_array('status', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN status ENUM('active','cancelled','postponed') DEFAULT 'active' AFTER end_time;
<?php endif; ?>
<?php if (in_array('notes', $missing_columns)): ?>
ALTER TABLE lessons ADD COLUMN notes TEXT NULL AFTER status;
<?php endif; ?>
                </pre>

                <form method="POST" action="">
                    <button type="submit" name="fix_table" class="btn btn-primary">
                        <i class="bi bi-tools"></i> Eksik Kolonları Ekle
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <h5 class="mt-4">Tablo Oluşturma</h5>
            <pre class="bg-light p-3">
CREATE TABLE lessons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    classroom_id INT NOT NULL,
    period_id INT NOT NULL,
    day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('active','cancelled','postponed') DEFAULT 'active',
    notes TEXT NULL,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
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
                $sql = "CREATE TABLE lessons (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    classroom_id INT NOT NULL,
                    period_id INT NOT NULL,
                    day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    status ENUM('active','cancelled','postponed') DEFAULT 'active',
                    notes TEXT NULL,
                    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
                    FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
                )";

                db()->exec($sql);

                setAlert('Lessons tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_lessons_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Eksik kolonları ekle
        if (isset($_POST['fix_table']) && !empty($missing_columns)) {
            try {
                foreach ($missing_columns as $column) {
                    if ($column == 'classroom_id') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN classroom_id INT NOT NULL AFTER id");
                    } else if ($column == 'period_id') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN period_id INT NOT NULL AFTER classroom_id");
                    } else if ($column == 'day') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL AFTER period_id");
                    } else if ($column == 'start_time') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN start_time TIME NOT NULL AFTER day");
                    } else if ($column == 'end_time') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN end_time TIME NOT NULL AFTER start_time");
                    } else if ($column == 'status') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN status ENUM('active','cancelled','postponed') DEFAULT 'active' AFTER end_time");
                    } else if ($column == 'notes') {
                        db()->exec("ALTER TABLE lessons ADD COLUMN notes TEXT NULL AFTER status");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_lessons_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">Örnek Kayıtlar</h5>
        <?php
        $lessons = db()->query("SELECT * FROM lessons LIMIT 10")->fetchAll();
        if (empty($lessons)): ?>
            <div class="alert alert-warning">Henüz ders kaydı bulunmuyor.</div>

            <h5 class="mt-4">Test Ders Ekle</h5>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Sınıf</label>
                        <select name="test_classroom_id" class="form-control" required>
                            <?php
                            $classrooms = db()->query("SELECT id, name FROM classrooms WHERE status = 'active'")->fetchAll();
                            foreach ($classrooms as $classroom) {
                                echo '<option value="' . $classroom['id'] . '">' . htmlspecialchars($classroom['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Dönem</label>
                        <select name="test_period_id" class="form-control" required>
                            <?php
                            $periods = db()->query("SELECT id, name FROM periods WHERE status = 'active'")->fetchAll();
                            foreach ($periods as $period) {
                                echo '<option value="' . $period['id'] . '">' . htmlspecialchars($period['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Gün</label>
                        <select name="test_day" class="form-control" required>
                            <option value="Monday">Pazartesi</option>
                            <option value="Tuesday">Salı</option>
                            <option value="Wednesday">Çarşamba</option>
                            <option value="Thursday">Perşembe</option>
                            <option value="Friday">Cuma</option>
                            <option value="Saturday">Cumartesi</option>
                            <option value="Sunday">Pazar</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Başlangıç</label>
                        <input type="time" name="test_start_time" class="form-control" value="14:00" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Bitiş</label>
                        <input type="time" name="test_end_time" class="form-control" value="15:30" required>
                    </div>
                </div>
                <button type="submit" name="add_test_lesson" class="btn btn-primary">Test Ders Ekle</button>
            </form>

            <?php
            // Test ders ekle
            if (isset($_POST['add_test_lesson'])) {
                try {
                    $test_data = [
                        'classroom_id' => $_POST['test_classroom_id'],
                        'period_id' => $_POST['test_period_id'],
                        'day' => $_POST['test_day'],
                        'start_time' => $_POST['test_start_time'],
                        'end_time' => $_POST['test_end_time'],
                        'status' => 'active',
                        'notes' => 'Test ders'
                    ];

                    $sql = "INSERT INTO lessons (classroom_id, period_id, day, start_time, end_time, status, notes) 
                            VALUES (:classroom_id, :period_id, :day, :start_time, :end_time, :status, :notes)";

                    $stmt = db()->prepare($sql);
                    $result = $stmt->execute($test_data);

                    if ($result) {
                        setAlert('Test ders başarıyla eklendi!', 'success');
                        redirect('check_lessons_table.php');
                    } else {
                        setAlert('Ders eklenirken hata oluştu: ' . implode(' - ', $stmt->errorInfo()), 'danger');
                    }
                } catch (Exception $e) {
                    setAlert('Hata: ' . $e->getMessage(), 'danger');
                }
            }
            ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sınıf ID</th>
                            <th>Dönem ID</th>
                            <th>Gün</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lessons as $lesson): ?>
                            <tr>
                                <td><?php echo $lesson['id']; ?></td>
                                <td><?php echo $lesson['classroom_id']; ?></td>
                                <td><?php echo $lesson['period_id']; ?></td>
                                <td><?php echo $lesson['day']; ?></td>
                                <td><?php echo $lesson['start_time']; ?></td>
                                <td><?php echo $lesson['end_time']; ?></td>
                                <td><?php echo $lesson['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>