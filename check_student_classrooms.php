<?php
// check_student_classrooms.php - Student_classrooms tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Öğrenci-Sınıf İlişki Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Öğrenci-Sınıf İlişki Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $table_exists = db()->query("SHOW TABLES LIKE 'student_classrooms'")->fetch();
        ?>
        <div class="alert alert-<?php echo $table_exists ? 'success' : 'danger'; ?>">
            student_classrooms tablosu <?php echo $table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Tablo Yapısı</h5>
            <?php
            $columns = db()->query("DESCRIBE student_classrooms")->fetchAll();
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
                'classroom_id',
                'enrollment_date',
                'status',
                'created_at'
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
<?php if ($column == 'status'): ?>
ALTER TABLE student_classrooms ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active';
<?php elseif ($column == 'enrollment_date'): ?>
ALTER TABLE student_classrooms ADD COLUMN enrollment_date DATE NOT NULL;
<?php elseif ($column == 'created_at'): ?>
ALTER TABLE student_classrooms ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
<?php endif; ?>
<?php endforeach; ?>
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
CREATE TABLE student_classrooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    classroom_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_classroom (student_id, classroom_id)
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
                $sql = "CREATE TABLE student_classrooms (
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

                db()->exec($sql);

                setAlert('student_classrooms tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_student_classrooms.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Eksik kolonları ekle
        if (isset($_POST['fix_table']) && !empty($missing_columns)) {
            try {
                foreach ($missing_columns as $column) {
                    if ($column == 'status') {
                        db()->exec("ALTER TABLE student_classrooms ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
                    } elseif ($column == 'enrollment_date') {
                        db()->exec("ALTER TABLE student_classrooms ADD COLUMN enrollment_date DATE NOT NULL");
                    } elseif ($column == 'created_at') {
                        db()->exec("ALTER TABLE student_classrooms ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_student_classrooms.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Örnek Kayıtlar</h5>
            <?php
            $relations = db()->query("SELECT sc.*, s.first_name, s.last_name, c.name as classroom_name 
                                      FROM student_classrooms sc
                                      JOIN students s ON sc.student_id = s.id
                                      JOIN classrooms c ON sc.classroom_id = c.id
                                      LIMIT 10")->fetchAll();

            if (empty($relations)): ?>
                <div class="alert alert-warning">Henüz öğrenci-sınıf ilişkisi bulunmuyor.</div>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Öğrenci</th>
                            <th>Sınıf</th>
                            <th>Kayıt Tarihi</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relations as $relation): ?>
                            <tr>
                                <td><?php echo $relation['id']; ?></td>
                                <td><?php echo htmlspecialchars($relation['first_name'] . ' ' . $relation['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($relation['classroom_name']); ?></td>
                                <td><?php echo formatDate($relation['enrollment_date']); ?></td>
                                <td><?php echo $relation['status']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>