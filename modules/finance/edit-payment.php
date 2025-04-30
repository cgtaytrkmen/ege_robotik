<?php
// modules/finance/edit-payment.php - Ödeme düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$payment_id = $_GET['id'] ?? 0;
if (!$payment_id) {
    setAlert('Geçersiz ödeme ID!', 'danger');
    redirect('modules/finance/payments.php');
}

// Ödeme bilgilerini getir
$query = "SELECT p.*, s.first_name, s.last_name, s.net_fee 
          FROM payments p
          JOIN students s ON p.student_id = s.id
          WHERE p.id = ?";
$payment = safeQuery($query, [$payment_id])->fetch();

if (!$payment) {
    setAlert('Ödeme kaydı bulunamadı!', 'danger');
    redirect('modules/finance/payments.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payment_data = [
            'amount' => clean($_POST['amount']),
            'payment_date' => clean($_POST['payment_date']),
            'due_date' => clean($_POST['due_date']),
            'status' => clean($_POST['status']),
            'payment_type' => clean($_POST['payment_type']),
            'payment_method' => clean($_POST['payment_method'] ?? 'cash'),
            'notes' => clean($_POST['notes'] ?? ''),
            'id' => $payment_id
        ];

        $sql = "UPDATE payments SET 
                amount = :amount,
                payment_date = :payment_date,
                due_date = :due_date,
                status = :status,
                payment_type = :payment_type,
                payment_method = :payment_method,
                notes = :notes
                WHERE id = :id";

        safeQuery($sql, $payment_data);
        setAlert('Ödeme başarıyla güncellendi!', 'success');
        redirect('modules/finance/payments.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Ödeme Düzenle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Ödeme Düzenle</h2>
    <a href="payments.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Öğrenci</label>
                    <input type="text" class="form-control"
                        value="<?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>"
                        readonly>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="amount" class="form-label">Ödeme Tutarı</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                        value="<?php echo $payment['amount']; ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="payment_date" class="form-label">Ödeme Tarihi</label>
                    <input type="date" class="form-control" id="payment_date" name="payment_date"
                        value="<?php echo $payment['payment_date']; ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="due_date" class="form-label">Son Ödeme Tarihi</label>
                    <input type="date" class="form-control" id="due_date" name="due_date"
                        value="<?php echo $payment['due_date']; ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="payment_type" class="form-label">Ödeme Tipi</label>
                    <select class="form-select" id="payment_type" name="payment_type" required>
                        <option value="monthly" <?php echo $payment['payment_type'] == 'monthly' ? 'selected' : ''; ?>>Aylık</option>
                        <option value="extra" <?php echo $payment['payment_type'] == 'extra' ? 'selected' : ''; ?>>Ekstra</option>
                        <option value="makeup" <?php echo $payment['payment_type'] == 'makeup' ? 'selected' : ''; ?>>Telafi</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="payment_method" class="form-label">Ödeme Yöntemi</label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                        <option value="cash" <?php echo ($payment['payment_method'] ?? '') == 'cash' ? 'selected' : ''; ?>>Nakit</option>
                        <option value="credit_card" <?php echo ($payment['payment_method'] ?? '') == 'credit_card' ? 'selected' : ''; ?>>Kredi Kartı</option>
                        <option value="ziraat_transfer" <?php echo ($payment['payment_method'] ?? '') == 'ziraat_transfer' ? 'selected' : ''; ?>>Ziraat Havale</option>
                        <option value="enpara_transfer" <?php echo ($payment['payment_method'] ?? '') == 'enpara_transfer' ? 'selected' : ''; ?>>Enpara Havale</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="paid" <?php echo $payment['status'] == 'paid' ? 'selected' : ''; ?>>Ödendi</option>
                        <option value="pending" <?php echo $payment['status'] == 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                        <option value="late" <?php echo $payment['status'] == 'late' ? 'selected' : ''; ?>>Gecikmiş</option>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Notlar</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Güncelle
                    </button>
                    <a href="payments.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>