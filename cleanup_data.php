<?php
// cleanup_data.php - Belirli verileri temizleme sayfası
require_once 'config/config.php';

// Admin kontrolü
checkAdmin();

// Temizleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Seçilen tabloları temizle
        $selected_tables = $_POST['tables'] ?? [];

        if (!empty($selected_tables)) {
            db()->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($selected_tables as $table) {
                // Güvenlik kontrolü
                if (in_array($table, ['students', 'parents', 'attendance', 'payments', 'lessons', 'classrooms', 'periods'])) {
                    // Eğer students tablosu seçildiyse ilişkili tabloları da temizle
                    if ($table === 'students') {
                        db()->exec("TRUNCATE TABLE student_periods");
                        db()->exec("TRUNCATE TABLE student_parents");
                        db()->exec("TRUNCATE TABLE attendance");
                        db()->exec("TRUNCATE TABLE payments");
                    }

                    // Eğer parents tablosu seçildiyse ilişkili tabloyu da temizle
                    if ($table === 'parents') {
                        db()->exec("TRUNCATE TABLE student_parents");
                    }

                    // Eğer periods tablosu seçildiyse ilişkili tabloları da temizle
                    if ($table === 'periods') {
                        db()->exec("TRUNCATE TABLE student_periods");
                        db()->exec("TRUNCATE TABLE lessons");
                        db()->exec("TRUNCATE TABLE payments");
                    }

                    db()->exec("TRUNCATE TABLE $table");
                    db()->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
                }
            }

            db()->exec("SET FOREIGN_KEY_CHECKS = 1");

            setAlert('Seçilen veriler başarıyla temizlendi!', 'success');
        } else {
            setAlert('Lütfen temizlenecek veri tipini seçin!', 'warning');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Veri Temizleme';
require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">Veri Temizleme</h4>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Bu işlem seçilen verileri kalıcı olarak silecektir.
        </div>

        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6">
                    <h5>Temizlenecek Veriler</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="students" id="check_students">
                        <label class="form-check-label" for="check_students">
                            Öğrenciler <small class="text-muted">(İlişkili yoklama ve ödeme kayıtları da silinir)</small>
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="parents" id="check_parents">
                        <label class="form-check-label" for="check_parents">
                            Veliler
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="attendance" id="check_attendance">
                        <label class="form-check-label" for="check_attendance">
                            Yoklama Kayıtları
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="payments" id="check_payments">
                        <label class="form-check-label" for="check_payments">
                            Ödeme Kayıtları
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="lessons" id="check_lessons">
                        <label class="form-check-label" for="check_lessons">
                            Ders Kayıtları
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="classrooms" id="check_classrooms">
                        <label class="form-check-label" for="check_classrooms">
                            Sınıflar
                        </label>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="periods" id="check_periods">
                        <label class="form-check-label" for="check_periods">
                            Dönemler <small class="text-muted">(İlişkili dersler de silinir)</small>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5>Önemli Notlar</h5>
                    <ul>
                        <li>Bu işlem geri alınamaz</li>
                        <li>Öğrenciler silindiğinde ilişkili yoklama ve ödeme kayıtları da silinir</li>
                        <li>Dönemler silindiğinde ilişkili ders programı da silinir</li>
                        <li>Veliler silindiğinde öğrenci-veli ilişkileri de silinir</li>
                    </ul>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Seçilen verileri silmek istediğinizden emin misiniz?');">
                    <i class="bi bi-trash"></i> Seçilen Verileri Temizle
                </button>
                <a href="index.php" class="btn btn-secondary">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>