<?php
// modules/periods/add.php - Yeni dönem ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $period_data = [
            'name' => clean($_POST['name']),
            'type' => clean($_POST['type']),
            'start_date' => clean($_POST['start_date']),
            'end_date' => clean($_POST['end_date']),
            'status' => clean($_POST['status'] ?? 'inactive')
        ];

        // Eğer aktif olarak işaretlendiyse, diğer aktif dönemleri pasif yap
        if ($period_data['status'] === 'active') {
            $sql = "UPDATE periods SET status = 'inactive' WHERE status = 'active'";
            db()->exec($sql);
        }

        $sql = "INSERT INTO periods (name, type, start_date, end_date, status) 
                VALUES (:name, :type, :start_date, :end_date, :status)";
        safeQuery($sql, $period_data);

        setAlert('Dönem başarıyla eklendi!', 'success');
        redirect('modules/periods/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yeni Dönem Ekle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Dönem Ekle</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Dönem Adı</label>
                    <input type="text" class="form-control" id="name" name="name" required
                        placeholder="Örn: 2024-2025 Güz Dönemi">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="type" class="form-label">Dönem Türü</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Seçiniz</option>
                        <option value="fall">Güz Dönemi</option>
                        <option value="summer">Yaz Dönemi</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-select" id="status" name="status">
                        <option value="inactive">Pasif</option>
                        <option value="active">Aktif</option>
                    </select>
                    <small class="form-text text-muted">
                        Aktif olarak seçerseniz diğer dönemler pasif olacaktır.
                    </small>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>