<?php
// check_classroom_table.php - Classrooms tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Classrooms Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Classrooms Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Varlığı</h5>
        <?php
        $table_exists = db()->query("SHOW TABLES LIKE 'classrooms'")->fetch();
        ?>
        <div class="alert alert-<?php echo $table_exists ? 'success' : 'danger'; ?>">
            Classrooms tablosu <?php echo $table_exists ? 'bulundu' : 'bulunamadı'; ?>
        </div>

        <?php if ($table_exists): ?>
            <h5 class="mt-4">Tablo Yapısı</h5>
            <?php
            $columns = db()->query("DESCRIBE classrooms")->fetchAll();
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
                'name',
                'capacity',
                'age_group',
                'description',
                'status'
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
ALTER TABLE classrooms ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active';
<?php elseif ($column == 'description'): ?>
ALTER TABLE classrooms ADD COLUMN description TEXT NULL;
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
CREATE TABLE classrooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    capacity INT DEFAULT 6,
    age_group VARCHAR(50) DEFAULT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
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
                $sql = "CREATE TABLE classrooms (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    capacity INT DEFAULT 6,
                    age_group VARCHAR(50) DEFAULT NULL,
                    description TEXT NULL,
                    status ENUM('active', 'inactive') DEFAULT 'active'
                )";

                db()->exec($sql);

                setAlert('Classrooms tablosu başarıyla oluşturuldu!', 'success');
                redirect('check_classroom_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }

        // Eksik kolonları ekle
        if (isset($_POST['fix_table']) && !empty($missing_columns)) {
            try {
                foreach ($missing_columns as $column) {
                    if ($column == 'status') {
                        db()->exec("ALTER TABLE classrooms ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
                    } elseif ($column == 'description') {
                        db()->exec("ALTER TABLE classrooms ADD COLUMN description TEXT NULL");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_classroom_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">Örnek Kayıtlar</h5>
        <?php
        $classrooms = db()->query("SELECT * FROM classrooms LIMIT 10")->fetchAll();
        if (empty($classrooms)): ?>
            <div class="alert alert-warning">Henüz sınıf kaydı bulunmuyor.</div>

            <h5 class="mt-4">Örnek Sınıf Ekle</h5>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <input type="text" name="test_name" class="form-control" placeholder="Sınıf Adı" value="Robotik 101" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <input type="number" name="test_capacity" class="form-control" placeholder="Kapasite" value="6" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="text" name="test_age_group" class="form-control" placeholder="Yaş Grubu" value="9-12 yaş" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <button type="submit" name="add_test" class="btn btn-primary">Örnek Ekle</button>
                    </div>
                </div>
            </form>

            <?php
            // Örnek sınıf ekle
            if (isset($_POST['add_test'])) {
                try {
                    $test_data = [
                        'name' => $_POST['test_name'],
                        'capacity' => $_POST['test_capacity'],
                        'age_group' => $_POST['test_age_group'],
                        'description' => 'Test sınıfı',
                        'status' => 'active'
                    ];

                    $sql = "INSERT INTO classrooms (name, capacity, age_group, description, status) 
                            VALUES (:name, :capacity, :age_group, :description, :status)";

                    $stmt = db()->prepare($sql);
                    $result = $stmt->execute($test_data);

                    if ($result) {
                        setAlert('Örnek sınıf başarıyla eklendi!', 'success');
                        redirect('check_classroom_table.php');
                    } else {
                        setAlert('Sınıf eklenirken hata oluştu: ' . implode(' - ', $stmt->errorInfo()), 'danger');
                    }
                } catch (Exception $e) {
                    setAlert('Hata: ' . $e->getMessage(), 'danger');
                }
            }
            ?>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Adı</th>
                        <th>Kapasite</th>
                        <th>Yaş Grubu</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classrooms as $classroom): ?>
                        <tr>
                            <td><?php echo $classroom['id']; ?></td>
                            <td><?php echo htmlspecialchars($classroom['name']); ?></td>
                            <td><?php echo $classroom['capacity']; ?></td>
                            <td><?php echo htmlspecialchars($classroom['age_group']); ?></td>
                            <td><?php echo $classroom['status'] ?? 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>