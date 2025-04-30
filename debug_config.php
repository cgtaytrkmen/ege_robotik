<?php
// debug_config.php - Hata ayıklama yapılandırması
require_once 'config/config.php';

// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

$page_title = 'Debug Yapılandırması';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Debug Yapılandırması</h4>
    </div>
    <div class="card-body">
        <h5>PHP Yapılandırması</h5>
        <table class="table table-striped">
            <tr>
                <th>PHP Versiyonu:</th>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <th>Error Reporting:</th>
                <td><?php echo error_reporting(); ?></td>
            </tr>
            <tr>
                <th>Display Errors:</th>
                <td><?php echo ini_get('display_errors'); ?></td>
            </tr>
            <tr>
                <th>Log Errors:</th>
                <td><?php echo ini_get('log_errors'); ?></td>
            </tr>
            <tr>
                <th>Error Log:</th>
                <td><?php echo ini_get('error_log'); ?></td>
            </tr>
        </table>

        <h5 class="mt-4">Veritabanı Bağlantı Testi</h5>
        <?php
        try {
            $conn = db();
            echo '<div class="alert alert-success">Veritabanı bağlantısı başarılı!</div>';

            // Test query
            $result = $conn->query("SELECT 1")->fetch();
            if ($result) {
                echo '<div class="alert alert-success">Test sorgusu başarılı!</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Hata: ' . $e->getMessage() . '</div>';
        }
        ?>

        <h5 class="mt-4">Veli Ekleme Testi</h5>
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" name="test_name" class="form-control" placeholder="Test Adı" required>
                </div>
                <div class="col-md-4">
                    <input type="email" name="test_email" class="form-control" placeholder="test@example.com" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="test_parent" class="btn btn-primary">Test Et</button>
                </div>
            </div>
        </form>

        <?php
        if (isset($_POST['test_parent'])) {
            try {
                $test_data = [
                    'first_name' => $_POST['test_name'],
                    'last_name' => 'Test',
                    'tcno' => null,
                    'phone' => '5551234567',
                    'email' => $_POST['test_email'],
                    'address' => null,
                    'relationship' => 'anne',
                    'password' => password_hash('test123', PASSWORD_DEFAULT),
                    'status' => 'active'
                ];

                echo '<h6 class="mt-3">Test Verisi:</h6>';
                echo '<pre>';
                print_r($test_data);
                echo '</pre>';

                $sql = "INSERT INTO parents (first_name, last_name, tcno, phone, email, address, relationship, password, status) 
                        VALUES (:first_name, :last_name, :tcno, :phone, :email, :address, :relationship, :password, :status)";

                $stmt = db()->prepare($sql);
                $result = $stmt->execute($test_data);

                if ($result) {
                    $last_id = db()->lastInsertId();
                    echo '<div class="alert alert-success">Veli eklendi! ID: ' . $last_id . '</div>';
                } else {
                    echo '<div class="alert alert-danger">Veritabanı hatası: ' . print_r($stmt->errorInfo(), true) . '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Hata: ' . $e->getMessage() . '</div>';
                echo '<pre>';
                echo $e->getTraceAsString();
                echo '</pre>';
            }
        }
        ?>

        <h5 class="mt-4">Son Error Log Kayıtları</h5>
        <?php
        $error_log_file = __DIR__ . '/error.log';
        if (file_exists($error_log_file)) {
            $last_errors = array_slice(file($error_log_file), -10);
            echo '<pre class="bg-light p-3">';
            foreach ($last_errors as $error) {
                echo htmlspecialchars($error);
            }
            echo '</pre>';
        } else {
            echo '<div class="alert alert-info">Error log dosyası bulunamadı.</div>';
        }
        ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>