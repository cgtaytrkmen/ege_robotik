<?php
// modules/finance/generate-monthly-payments.php - Aylık ödemeleri otomatik oluşturma
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Mevcut ay için ödemeleri kontrol et
$current_month = date('Y-m');
$existing_payments_query = "SELECT student_id FROM payments 
                           WHERE period_id = ? 
                           AND payment_type = 'monthly' 
                           AND due_date LIKE ?";
$existing_payments = safeQuery($existing_payments_query, [$current_period['id'], $current_month . '%'])->fetchAll(PDO::FETCH_COLUMN);

// Aktif öğrencileri getir (bu ay için ödeme oluşturulmamışları)
$students_query = "SELECT s.id, s.first_name, s.last_name, s.net_fee, s.payment_day
                   FROM students s
                   JOIN student_periods sp ON s.id = sp.student_id
                   WHERE sp.period_id = ? AND sp.status = 'active'
                   " . (!empty($existing_payments) ? "AND s.id NOT IN (" . implode(',', $existing_payments) . ")" : "") . "
                   ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$current_period['id']])->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_POST['selected_students'])) {
            db()->beginTransaction();

            $month = clean($_POST['month']);
            $payment_type = clean($_POST['payment_type']);

            foreach ($_POST['selected_students'] as $student_id) {
                // Öğrenci bilgilerini al
                $student = null;
                foreach ($students as $s) {
                    if ($s['id'] == $student_id) {
                        $student = $s;
                        break;
                    }
                }

                if (!$student) continue;

                // Son ödeme tarihini hesapla
                $due_date = $month . '-' . str_pad($student['payment_day'], 2, '0', STR_PAD_LEFT);

                // Ödeme kaydı oluştur
                $payment_data = [
                    'student_id' => $student_id,
                    'period_id' => $current_period['id'],
                    'amount' => $student['net_fee'],
                    'payment_date' => null, // Henüz ödenmedi
                    'due_date' => $due_date,
                    'status' => 'pending',
                    'payment_type' => $payment_type,
                    'notes' => $month . ' ayı ödemesi'
                ];

                $sql = "INSERT INTO payments (student_id, period_id, amount, payment_date, due_date, status, payment_type, notes) 
                        VALUES (:student_id, :period_id, :amount, :payment_date, :due_date, :status, :payment_type, :notes)";
                safeQuery($sql, $payment_data);
            }

            db()->commit();
            setAlert('Seçilen öğrenciler için ödeme kayıtları başarıyla oluşturuldu!', 'success');
            redirect('modules/finance/payments.php');
        } else {
            throw new Exception('Lütfen en az bir öğrenci seçin.');
        }
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Aylık Ödemeler Oluştur - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Aylık Ödemeler Oluştur
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="payments.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="alert alert-info mb-0">
            <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Bilgi</h5>
            <p class="mb-2">Bu sayfa ile aktif öğrenciler için aylık ödeme kayıtları otomatik oluşturabilirsiniz.</p>
            <ul class="mb-0">
                <li>Sadece bu ay için ödeme kaydı olmayan öğrenciler listelenir</li>
                <li>Her öğrenci için belirlenen net ücret kullanılır</li>
                <li>Son ödeme tarihi, öğrencinin ödeme günü olarak ayarlanır</li>
                <li>Ödemeler "Bekliyor" durumunda oluşturulur</li>
            </ul>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row mb-4">
                <div class="col-md-4">
                    <label for="month" class="form-label">Ay</label>
                    <input type="month" class="form-control" id="month" name="month"
                        value="<?php echo date('Y-m'); ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="payment_type" class="form-label">Ödeme Tipi</label>
                    <select class="form-select" id="payment_type" name="payment_type" required>
                        <option value="monthly" selected>Aylık</option>
                        <option value="extra">Ekstra</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-primary me-2" id="select_all">
                        <i class="bi bi-check-all"></i> Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="clear_all">
                        <i class="bi bi-x-circle"></i> Temizle
                    </button>
                </div>
            </div>

            <?php if (count($students) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="select_all_checkbox">
                                </th>
                                <th>Öğrenci</th>
                                <th width="150">Aylık Ücret</th>
                                <th width="150">Ödeme Günü</th>
                                <th width="150">Son Ödeme Tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_students[]" value="<?php echo $student['id']; ?>"
                                            class="student_checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo formatMoney($student['net_fee']); ?></td>
                                    <td>Her ayın <?php echo $student['payment_day']; ?>'i</td>
                                    <td>
                                        <?php
                                        // Seçilen ay için son ödeme tarihini oluştur
                                        $selected_month = $_POST['month'] ?? date('Y-m');
                                        $due_date = $selected_month . '-' . str_pad($student['payment_day'], 2, '0', STR_PAD_LEFT);
                                        echo formatDate($due_date);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-calendar-plus"></i> Ödemeleri Oluştur
                    </button>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Bu ay için ödeme oluşturulacak aktif öğrenci bulunmuyor.
                    Ödemeler zaten oluşturulmuş olabilir veya aktif öğrenci bulunmuyor.
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    // Tümünü seç/kaldır
    document.getElementById('select_all').addEventListener('click', function() {
        const checkboxes = document.getElementsByClassName('student_checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = true;
        }
        document.getElementById('select_all_checkbox').checked = true;
    });

    document.getElementById('clear_all').addEventListener('click', function() {
        const checkboxes = document.getElementsByClassName('student_checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = false;
        }
        document.getElementById('select_all_checkbox').checked = false;
    });

    // Üst checkbox ile tümünü seç/kaldır
    document.getElementById('select_all_checkbox').addEventListener('change', function() {
        const checkboxes = document.getElementsByClassName('student_checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    // Ay değiştiğinde sayfayı yenile
    document.getElementById('month').addEventListener('change', function() {
        // Ay değiştiğinde kontrol yapmak için formu göndermeden verileri kontrol edebilir
        // Ama şimdilik herhangi bir işlem yapmıyoruz
    });
</script>

<?php require_once '../../includes/footer.php'; ?>