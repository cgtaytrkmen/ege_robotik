<?php
// reset_system.php - Sistem sıfırlama sayfası
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

// Sıfırlama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset']) && $_POST['confirm_code'] === 'SIFIRLA') {
    try {
        // Foreign key kontrollerini geçici olarak kapat
        db()->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Tabloları sıfırla
        $tables = [
            'attendance',
            'payments',
            'expenses',
            'topics',
            'notes',
            'student_periods',
            'student_parents',
            'students',
            'parents',
            'lessons',
            'classrooms',
            'periods'
        ];

        foreach ($tables as $table) {
            // Tablo varsa sıfırla
            $check_table = db()->query("SHOW TABLES LIKE '$table'")->fetch();
            if ($check_table) {
                db()->exec("TRUNCATE TABLE $table");
                db()->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
            }
        }

        // İlk admin hariç diğer adminleri sil
        db()->exec("DELETE FROM admins WHERE id > 1");
        db()->exec("ALTER TABLE admins AUTO_INCREMENT = 2");

        // Foreign key kontrollerini tekrar aç
        db()->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Varsayılan verileri ekle

        // Yeni dönem ekle
        $period_data = [
            'name' => date('Y') . '-' . (date('Y') + 1) . ' Güz Dönemi',
            'type' => 'fall',
            'start_date' => date('Y-09-01'),
            'end_date' => date('Y-m-d', strtotime('+6 months')),
            'status' => 'active'
        ];

        $sql = "INSERT INTO periods (name, type, start_date, end_date, status) 
                VALUES (:name, :type, :start_date, :end_date, :status)";
        safeQuery($sql, $period_data);

        // Örnek sınıflar ekle
        $classrooms = [
            ['name' => 'Robotik 101', 'capacity' => 6, 'age_group' => '6-8 yaş', 'status' => 'active'],
            ['name' => 'Robotik 102', 'capacity' => 6, 'age_group' => '9-12 yaş', 'status' => 'active'],
            ['name' => 'Robotik 103', 'capacity' => 6, 'age_group' => '13+ yaş', 'status' => 'active']
        ];

        foreach ($classrooms as $classroom) {
            $sql = "INSERT INTO classrooms (name, capacity, age_group, status) 
                    VALUES (:name, :capacity, :age_group, :status)";
            safeQuery($sql, $classroom);
        }

        setAlert('Sistem başarıyla sıfırlandı!', 'success');
        redirect('index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Sistem Sıfırlama';
require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h4 class="card-title mb-0">Sistem Sıfırlama</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5 class="alert-heading">Dikkat!</h5>
                    <p>Bu işlem tüm sistem verilerini silecektir:</p>
                    <ul>
                        <li>Tüm öğrenci kayıtları</li>
                        <li>Tüm veli kayıtları</li>
                        <li>Tüm yoklama kayıtları</li>
                        <li>Tüm ödeme kayıtları</li>
                        <li>Tüm dönem kayıtları</li>
                        <li>Tüm sınıf ve ders kayıtları</li>
                    </ul>
                    <hr>
                    <p class="mb-0"><strong>Bu işlem geri alınamaz!</strong></p>
                </div>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="confirm_code" class="form-label">Sıfırlama işlemini onaylamak için aşağıdaki kutucuğa <strong>SIFIRLA</strong> yazın:</label>
                        <input type="text" class="form-control" id="confirm_code" name="confirm_code" required>
                    </div>

                    <div class="text-center">
                        <button type="submit" name="confirm_reset" class="btn btn-danger btn-lg">
                            <i class="bi bi-exclamation-triangle"></i> Sistemi Sıfırla
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const confirmCode = document.getElementById('confirm_code').value;
        if (confirmCode !== 'SIFIRLA') {
            e.preventDefault();
            alert('Lütfen onay kodunu doğru girin!');
        } else {
            if (!confirm('Tüm verileri silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                e.preventDefault();
            }
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>