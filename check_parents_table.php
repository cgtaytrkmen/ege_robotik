<?php
// check_parents_table.php - Parents tablosu yapısını kontrol et
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

$page_title = 'Parents Tablosu Kontrolü';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Parents Tablosu Kontrolü</h4>
    </div>
    <div class="card-body">
        <h5>Tablo Yapısı</h5>
        <?php
        $columns = db()->query("DESCRIBE parents")->fetchAll();
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
            'first_name',
            'last_name',
            'tcno',
            'phone',
            'email',
            'address',
            'relationship',
            'password',
            'status',
            'last_login',
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
<?php if ($column == 'status'): ?>
ALTER TABLE parents ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active';
<?php elseif ($column == 'last_login'): ?>
ALTER TABLE parents ADD COLUMN last_login DATETIME NULL;
<?php elseif ($column == 'created_at'): ?>
ALTER TABLE parents ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
<?php elseif ($column == 'updated_at'): ?>
ALTER TABLE parents ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
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
                    if ($column == 'status') {
                        db()->exec("ALTER TABLE parents ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
                    } elseif ($column == 'last_login') {
                        db()->exec("ALTER TABLE parents ADD COLUMN last_login DATETIME NULL");
                    } elseif ($column == 'created_at') {
                        db()->exec("ALTER TABLE parents ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                    } elseif ($column == 'updated_at') {
                        db()->exec("ALTER TABLE parents ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    }
                }

                setAlert('Eksik kolonlar başarıyla eklendi!', 'success');
                redirect('check_parents_table.php');
            } catch (Exception $e) {
                setAlert('Hata: ' . $e->getMessage(), 'danger');
            }
        }
        ?>

        <h5 class="mt-4">Mevcut Kayıtlar</h5>
        <?php
        $parents = db()->query("SELECT * FROM parents LIMIT 10")->fetchAll();
        if (empty($parents)): ?>
            <div class="alert alert-warning">Veli kaydı bulunamadı.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parents as $parent): ?>
                        <tr>
                            <td><?php echo $parent['id']; ?></td>
                            <td><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($parent['email']); ?></td>
                            <td><?php echo htmlspecialchars($parent['phone']); ?></td>
                            <td><?php echo $parent['status'] ?? 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>