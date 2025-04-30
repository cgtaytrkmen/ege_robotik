<?php
// modules/classes/add.php - Yeni sınıf ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'name' => clean($_POST['name']),
            'capacity' => intval($_POST['capacity']),
            'age_group' => clean($_POST['age_group']),
            'description' => clean($_POST['description']),
            'status' => 'active'
        ];

        // Zorunlu alanlar kontrolü
        if (empty($data['name']) || empty($data['capacity']) || empty($data['age_group'])) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        // Kapasite kontrolü (en fazla 6 kişi)
        if ($data['capacity'] < 1 || $data['capacity'] > MAX_CLASS_CAPACITY) {
            throw new Exception('Sınıf kapasitesi 1 ile ' . MAX_CLASS_CAPACITY . ' arasında olmalıdır!');
        }

        // Aynı isimde aktif sınıf var mı kontrolü
        $check_query = "SELECT id FROM classrooms WHERE name = ? AND status = 'active'";
        $stmt = safeQuery($check_query, [$data['name']]);

        if ($stmt && $stmt->rowCount() > 0) {
            throw new Exception('Bu isimde aktif bir sınıf zaten var!');
        }

        // Sınıfı ekle
        $sql = "INSERT INTO classrooms (name, capacity, age_group, description, status) 
                VALUES (:name, :capacity, :age_group, :description, :status)";

        safeQuery($sql, $data);

        setAlert('Sınıf başarıyla eklendi!', 'success');
        redirect('modules/classes/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yeni Sınıf Ekle - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Sınıf Ekle
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Sınıf Adı *</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                    <small class="form-text text-muted">Örnek: Robotik 101, Kodlama Başlangıç</small>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="capacity" class="form-label">Kapasite *</label>
                    <input type="number" class="form-control" id="capacity" name="capacity"
                        min="1" max="<?php echo MAX_CLASS_CAPACITY; ?>" value="6" required>
                    <small class="form-text text-muted">Maksimum <?php echo MAX_CLASS_CAPACITY; ?> öğrenci</small>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="age_group" class="form-label">Yaş Grubu *</label>
                    <select class="form-select" id="age_group" name="age_group" required>
                        <option value="">Seçin...</option>
                        <option value="6-8 yaş">6-8 yaş</option>
                        <option value="9-12 yaş">9-12 yaş</option>
                        <option value="13+ yaş">13+ yaş</option>
                        <option value="Karışık">Karışık Yaş</option>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    <small class="form-text text-muted">Sınıf için özel notlar, müfredat bilgisi vb.</small>
                </div>
            </div>

            <div class="alert alert-info">
                <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Bilgi</h5>
                <p class="mb-0">• Her sınıf maksimum <?php echo MAX_CLASS_CAPACITY; ?> öğrenci kapasitelidir.</p>
                <p class="mb-0">• Sınıf adları benzersiz olmalıdır.</p>
                <p class="mb-0">• Yaş grubu öğrenci atamaları için referans olarak kullanılacaktır.</p>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>