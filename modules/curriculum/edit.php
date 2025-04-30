<?php
// modules/curriculum/edit.php - Müfredat düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$curriculum_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$curriculum_id) {
    setAlert('Geçersiz müfredat ID!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Müfredat bilgilerini getir
$curriculum_query = "SELECT c.*, p.name as period_name 
                   FROM curriculum c
                   JOIN periods p ON c.period_id = p.id
                   WHERE c.id = ?";
$curriculum = safeQuery($curriculum_query, [$curriculum_id])->fetch();

if (!$curriculum) {
    setAlert('Müfredat bulunamadı!', 'danger');
    redirect('modules/curriculum/index.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $update_data = [
            'name' => clean($_POST['name']),
            'description' => clean($_POST['description']),
            'age_group' => clean($_POST['age_group']),
            'period_id' => intval($_POST['period_id']),
            'total_weeks' => intval($_POST['total_weeks']),
            'status' => isset($_POST['status']) ? 'active' : 'inactive',
            'id' => $curriculum_id
        ];

        // Zorunlu alanlar kontrolü
        if (
            empty($update_data['name']) || empty($update_data['age_group']) ||
            empty($update_data['period_id']) || empty($update_data['total_weeks'])
        ) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Hafta sayısı kontrolü
        if ($update_data['total_weeks'] < 1 || $update_data['total_weeks'] > 12) {
            throw new Exception('Hafta sayısı 1 ile 12 arasında olmalıdır!');
        }

        // Hafta sayısı değiştirildi mi kontrol et
        $weeks_changed = ($update_data['total_weeks'] != $curriculum['total_weeks']);

        // Aynı isimde başka müfredat var mı kontrolü
        $check_query = "SELECT id FROM curriculum 
                      WHERE name = ? AND period_id = ? AND age_group = ? AND id != ?";
        $check_result = safeQuery($check_query, [
            $update_data['name'],
            $update_data['period_id'],
            $update_data['age_group'],
            $curriculum_id
        ])->fetch();

        if ($check_result) {
            throw new Exception('Bu isim, dönem ve yaş grubuyla zaten başka bir müfredat mevcut!');
        }

        // Müfredatı güncelle
        $update_query = "UPDATE curriculum 
                       SET name = :name, 
                           description = :description, 
                           age_group = :age_group, 
                           period_id = :period_id, 
                           total_weeks = :total_weeks, 
                           status = :status 
                       WHERE id = :id";

        safeQuery($update_query, $update_data);

        // Hafta sayısı azaltıldıysa fazla haftalık konuları temizle
        if ($weeks_changed && $update_data['total_weeks'] < $curriculum['total_weeks']) {
            $delete_topics = "DELETE FROM curriculum_weekly_topics 
                            WHERE curriculum_id = ? AND week_number > ?";
            safeQuery($delete_topics, [$curriculum_id, $update_data['total_weeks']]);
        }

        setAlert('Müfredat başarıyla güncellendi!', 'success');

        // Hafta sayısı değiştiyse konular sayfasına yönlendir
        if ($weeks_changed) {
            redirect('modules/curriculum/topics.php?id=' . $curriculum_id);
        } else {
            redirect('modules/curriculum/index.php');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Aktif dönemleri getir
$periods_query = "SELECT * FROM periods ORDER BY status DESC, start_date DESC";
$periods = db()->query($periods_query)->fetchAll();

$page_title = 'Müfredat Düzenle - ' . $curriculum['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Müfredat Düzenle: <?php echo htmlspecialchars($curriculum['name']); ?></h2>
    <div>
        <a href="topics.php?id=<?php echo $curriculum_id; ?>" class="btn btn-success">
            <i class="bi bi-list-check"></i> Haftalık Konular
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
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
                    <input type="text" class="form-control" id="name" name="name"
                        value="<?php echo htmlspecialchars($curriculum['name']); ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="age_group" class="form-label">Yaş Grubu *</label>
                    <select class="form-select" id="age_group" name="age_group" required>
                        <option value="">Seçin...</option>
                        <option value="6-8 yaş" <?php echo ($curriculum['age_group'] == '6-8 yaş') ? 'selected' : ''; ?>>6-8 yaş</option>
                        <option value="9-12 yaş" <?php echo ($curriculum['age_group'] == '9-12 yaş') ? 'selected' : ''; ?>>9-12 yaş</option>
                        <option value="13+ yaş" <?php echo ($curriculum['age_group'] == '13+ yaş') ? 'selected' : ''; ?>>13+ yaş</option>
                        <option value="Karışık" <?php echo ($curriculum['age_group'] == 'Karışık') ? 'selected' : ''; ?>>Karışık Yaş</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="total_weeks" class="form-label">Toplam Hafta *</label>
                    <select class="form-select" id="total_weeks" name="total_weeks" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($curriculum['total_weeks'] == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> hafta
                            </option>
                        <?php endfor; ?>
                    </select>
                    <small class="form-text text-muted">
                        <?php if ($curriculum['total_weeks'] > 1): ?>
                            Hafta sayısını azaltırsanız, fazla haftaların konuları silinecektir!
                        <?php endif; ?>
                    </small>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="period_id" class="form-label">Dönem *</label>
                    <select class="form-select" id="period_id" name="period_id" required>
                        <option value="">Seçin...</option>
                        <?php foreach ($periods as $period): ?>
                            <option value="<?php echo $period['id']; ?>"
                                <?php echo ($curriculum['period_id'] == $period['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($period['name']); ?>
                                <?php echo ($period['status'] == 'active') ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Durum</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="status" name="status"
                            <?php echo ($curriculum['status'] == 'active') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Aktif</label>
                    </div>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($curriculum['description']); ?></textarea>
                </div>
            </div>

            <?php if ($curriculum['total_weeks'] > 1): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Uyarı:</strong> Hafta sayısını azaltırsanız, fazla haftaların içerikleri kalıcı olarak silinecektir.
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Değişiklikleri Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>