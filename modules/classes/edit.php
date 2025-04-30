<?php
// modules/classes/edit.php - Sınıf düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$classroom_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$classroom_id) {
    setAlert('Geçersiz sınıf ID!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıf bilgilerini getir
$query = "SELECT * FROM classrooms WHERE id = ?";
$classroom = safeQuery($query, [$classroom_id])->fetch();

if (!$classroom) {
    setAlert('Sınıf bulunamadı!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıftaki öğrenci sayısını kontrol et
$student_count_query = "SELECT COUNT(*) as count FROM student_classrooms WHERE classroom_id = ? AND status = 'active'";
$student_count = safeQuery($student_count_query, [$classroom_id])->fetch()['count'];

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'name' => clean($_POST['name']),
            'capacity' => intval($_POST['capacity']),
            'age_group' => clean($_POST['age_group']),
            'description' => clean($_POST['description']),
            'status' => clean($_POST['status'])
        ];

        // Zorunlu alanlar kontrolü
        if (empty($data['name']) || empty($data['capacity']) || empty($data['age_group'])) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        // Kapasite kontrolü (en fazla 6 kişi)
        if ($data['capacity'] < 1 || $data['capacity'] > MAX_CLASS_CAPACITY) {
            throw new Exception('Sınıf kapasitesi 1 ile ' . MAX_CLASS_CAPACITY . ' arasında olmalıdır!');
        }

        // Kapasite mevcut öğrenci sayısından az olamaz
        if ($data['capacity'] < $student_count) {
            throw new Exception('Sınıf kapasitesi mevcut öğrenci sayısından (' . $student_count . ') daha az olamaz!');
        }

        // Aynı isimde başka bir aktif sınıf var mı kontrolü
        $check_query = "SELECT id FROM classrooms WHERE name = ? AND status = 'active' AND id != ?";
        $stmt = safeQuery($check_query, [$data['name'], $classroom_id]);

        if ($stmt && $stmt->rowCount() > 0) {
            throw new Exception('Bu isimde aktif bir sınıf zaten var!');
        }

        // Sınıfı güncelle
        $sql = "UPDATE classrooms SET 
                name = :name, 
                capacity = :capacity, 
                age_group = :age_group, 
                description = :description, 
                status = :status 
                WHERE id = :id";

        $data['id'] = $classroom_id;
        safeQuery($sql, $data);

        setAlert('Sınıf başarıyla güncellendi!', 'success');
        redirect('modules/classes/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Sınıf Düzenle - ' . $classroom['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sınıf Düzenle: <?php echo htmlspecialchars($classroom['name']); ?>
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
                    <input type="text" class="form-control" id="name" name="name"
                        value="<?php echo htmlspecialchars($classroom['name']); ?>" required>
                    <small class="form-text text-muted">Örnek: Robotik 101, Kodlama Başlangıç</small>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="capacity" class="form-label">Kapasite *</label>
                    <input type="number" class="form-control" id="capacity" name="capacity"
                        min="<?php echo $student_count; ?>" max="<?php echo MAX_CLASS_CAPACITY; ?>"
                        value="<?php echo $classroom['capacity']; ?>" required>
                    <small class="form-text text-muted">
                        Minimum: <?php echo $student_count; ?> (mevcut öğrenci sayısı)<br>
                        Maksimum: <?php echo MAX_CLASS_CAPACITY; ?> öğrenci
                    </small>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="age_group" class="form-label">Yaş Grubu *</label>
                    <select class="form-select" id="age_group" name="age_group" required>
                        <option value="">Seçin...</option>
                        <option value="6-8 yaş" <?php echo $classroom['age_group'] == '6-8 yaş' ? 'selected' : ''; ?>>6-8 yaş</option>
                        <option value="9-12 yaş" <?php echo $classroom['age_group'] == '9-12 yaş' ? 'selected' : ''; ?>>9-12 yaş</option>
                        <option value="13+ yaş" <?php echo $classroom['age_group'] == '13+ yaş' ? 'selected' : ''; ?>>13+ yaş</option>
                        <option value="Karışık" <?php echo $classroom['age_group'] == 'Karışık' ? 'selected' : ''; ?>>Karışık Yaş</option>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($classroom['description'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">Sınıf için özel notlar, müfredat bilgisi vb.</small>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?php echo $classroom['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="inactive" <?php echo $classroom['status'] == 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                    </select>
                </div>
            </div>

            <?php if ($student_count > 0): ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Dikkat</h5>
                    <p class="mb-0">Bu sınıfta <?php echo $student_count; ?> öğrenci bulunmaktadır.</p>
                    <p class="mb-0">Kapasite bu sayının altına düşürülemez.</p>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Güncelle
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>