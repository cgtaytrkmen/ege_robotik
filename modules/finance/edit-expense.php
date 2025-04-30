<?php
// modules/finance/edit-expense.php - Gider düzenleme
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

// ID kontrolü
$expense_id = $_GET['id'] ?? 0;
if (!$expense_id) {
    setAlert('Geçersiz gider ID!', 'danger');
    redirect('modules/finance/expenses.php');
}

// Gider bilgilerini getir
$query = "SELECT * FROM expenses WHERE id = ?";
$expense = safeQuery($query, [$expense_id])->fetch();

if (!$expense) {
    setAlert('Gider kaydı bulunamadı!', 'danger');
    redirect('modules/finance/expenses.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $expense_data = [
            'amount' => clean($_POST['amount']),
            'expense_date' => clean($_POST['expense_date']),
            'category' => clean($_POST['category']),
            'description' => clean($_POST['description'] ?? ''),
            'receipt_number' => !empty($_POST['receipt_number']) ? clean($_POST['receipt_number']) : null,
            'id' => $expense_id
        ];

        // Validasyon
        if ($expense_data['amount'] <= 0) {
            throw new Exception('Geçerli bir tutar giriniz.');
        }

        $sql = "UPDATE expenses SET 
                amount = :amount,
                expense_date = :expense_date,
                category = :category,
                description = :description,
                receipt_number = :receipt_number
                WHERE id = :id";

        safeQuery($sql, $expense_data);
        setAlert('Gider başarıyla güncellendi!', 'success');
        redirect('modules/finance/expenses.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Gider Düzenle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Gider Düzenle</h2>
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
                        value="<?php echo $expense['expense_date']; ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="amount" class="form-label">Tutar (₺)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                        value="<?php echo $expense['amount']; ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="category" class="form-label">Kategori</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Kategori Seçin</option>
                        <?php foreach ($expense_categories as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $expense['category'] == $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="receipt_number" class="form-label">Fiş/Fatura No</label>
                    <input type="text" class="form-control" id="receipt_number" name="receipt_number"
                        value="<?php echo htmlspecialchars($expense['receipt_number'] ?? ''); ?>">
                </div>

                <div class="col-md-12 mb-3">
                    <label for="description" class="form-label">Açıklama</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($expense['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Güncelle
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