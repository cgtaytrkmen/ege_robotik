<?php
// modules/curriculum/add.php - Yeni müfredat ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $curriculum_data = [
            'name' => clean($_POST['name']),
            'description' => clean($_POST['description']),
            'age_group' => clean($_POST['age_group']),
            'period_id' => intval($_POST['period_id']),
            'total_weeks' => intval($_POST['total_weeks']),
            'status' => isset($_POST['status']) ? 'active' : 'inactive'
        ];

        // Zorunlu alanlar kontrolü
        if (
            empty($curriculum_data['name']) || empty($curriculum_data['age_group']) ||
            empty($curriculum_data['period_id']) || empty($curriculum_data['total_weeks'])
        ) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Hafta sayısı kontrolü
        if ($curriculum_data['total_weeks'] < 1 || $curriculum_data['total_weeks'] > 12) {
            throw new Exception('Hafta sayısı 1 ile 12 arasında olmalıdır!');
        }

        // Aynı isimde müfredat var mı kontrolü
        $check_query = "SELECT id FROM curriculum 
                      WHERE name = ? AND period_id = ? AND age_group = ?";
        $check_result = safeQuery($check_query, [
            $curriculum_data['name'],
            $curriculum_data['period_id'],
            $curriculum_data['age_group']
        ])->fetch();

        if ($check_result) {
            throw new Exception('Bu isim, dönem ve yaş grubuyla zaten bir müfredat mevcut!');
        }

        // Yeni müfredat ekle
        $insert_query = "INSERT INTO curriculum (name, description, age_group, period_id, total_weeks, status) 
                       VALUES (:name, :description, :age_group, :period_id, :total_weeks, :status)";

        safeQuery($insert_query, $curriculum_data);
        $curriculum_id = db()->lastInsertId();

        setAlert('Müfredat başarıyla eklendi! Şimdi haftalık konuları ekleyebilirsiniz.', 'success');
        redirect('modules/curriculum/topics.php?id=' . $curriculum_id);
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Aktif dönemleri getir
$periods_query = "SELECT * FROM periods ORDER BY status DESC, start_date DESC";
$periods = db()->query($periods_query)->fetchAll();

$page_title = 'Yeni Müfredat Ekle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Müfredat Ekle</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Müfredat Bilgileri</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Müfredat Adı *</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                    <small class="form-text text-muted">Örnek: Robotik Kodlama Başlangıç - 6-8 Yaş</small>
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

                <div class="col-md-3 mb-3">
                    <label for="total_weeks" class="form-label">Toplam Hafta *</label>
                    <select class="form-select" id="total_weeks" name="total_weeks" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == 4) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> hafta
                            </option>
                        <?php endfor; ?>
                    </select>
                    <small class="form-text text-muted">Standart eğitim programı 4 hafta olarak önerilir.</small>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="period_id" class="form-label">Dönem *</label>
                    <select class="form-select" id="period_id" name="period_id" required>
                        <option value="">Seçin...</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?php echo $period['id']; ?>"
                                <?php echo ($period['id'] == $current_period['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($period['name']); ?>
                                <?php echo ($period['status'] == 'active') ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Durum</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                        <label class="form-check-label" for="status">Aktif</label>
                    </div>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                    <small class="form-text text-muted">Müfredatın genel hedefleri, öğrenme çıktıları ve içeriği hakkında bilgi verin.</small>
                </div>
            </div>

            <div class="alert alert-info">
                <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Bilgi</h5>
                <p class="mb-0">Müfredat kaydedildikten sonra, haftalık konu içeriklerini eklemeniz için yönlendirileceksiniz.</p>
                <p class="mb-0">Her yaş grubu için farklı bir müfredat oluşturmanız önerilir. Standart eğitim programı 4 hafta olarak planlanmıştır.</p>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Müfredatı Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>