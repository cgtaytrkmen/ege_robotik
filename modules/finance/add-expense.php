<?php
// modules/finance/add-expense.php - Gider ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Gider kategorileri
$expense_categories = [
    'electricity' => 'Elektrik',
    'water' => 'Su',
    'internet' => 'İnternet',
    'rent' => 'Kira',
    'educational_materials' => 'Eğitim Materyalleri',
    'cleaning' => 'Temizlik',
    'maintenance' => 'Bakım/Onarım',
    'equipment' => 'Ekipman',
    'software' => 'Yazılım',
    'other' => 'Diğer'
];

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $expense_data = [
            'amount' => clean($_POST['amount']),
            'expense_date' => clean($_POST['expense_date']),
            'category' => clean($_POST['category']),
            'description' => clean($_POST['description'] ?? ''),
            'receipt_number' => !empty($_POST['receipt_number']) ? clean($_POST['receipt_number']) : null
        ];

        // Validasyon
        if ($expense_data['amount'] <= 0) {
            throw new Exception('Geçerli bir tutar giriniz.');
        }

        $sql = "INSERT INTO expenses (amount, expense_date, category, description, receipt_number) 
                VALUES (:amount, :expense_date, :category, :description, :receipt_number)";

        safeQuery($sql, $expense_data);
        setAlert('Gider başarıyla eklendi!', 'success');
        redirect('modules/finance/expenses.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Gider Ekle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Gider Ekle</h2>
    <a href="expenses.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="expense_date" class="form-label">Gider Tarihi</label>
                    <input type="date" class="form-control" id="expense_date" name="expense_date"
                        value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="amount" class="form-label">Tutar (₺)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Kategori</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($expense_categories as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="receipt_number" class="form-label">Fiş/Fatura No</label>
                    <input type="text" class="form-control" id="receipt_number" name="receipt_number">
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                    <a href="expenses.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>