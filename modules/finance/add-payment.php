<?php
// modules/finance/add-payment.php - Ödeme ekleme (Haftalık yapı ile)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Öğrenci seçili mi?
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

// Ödemesi yapılacak öğrenciyi getir
if ($student_id) {
    $student_query = "SELECT s.*, sp.status as period_status 
                     FROM students s 
                     LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
                     WHERE s.id = ?";
    $student = safeQuery($student_query, [$current_period['id'], $student_id])->fetch();

    if (!$student) {
        setAlert('Öğrenci bulunamadı!', 'danger');
        redirect('modules/finance/index.php');
    }
}

// Aktif öğrencileri getir
$students_query = "SELECT s.*, sp.status as period_status
                  FROM students s
                  JOIN student_periods sp ON s.id = sp.student_id
                  WHERE sp.period_id = ? AND s.status = 'active'
                  ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$current_period['id']])->fetchAll();

// Hafta bilgilerini getir
$weeks_query = "SELECT * FROM period_weeks 
                WHERE period_id = ? 
                ORDER BY start_date";
$weeks = safeQuery($weeks_query, [$current_period['id']])->fetchAll();

// Bugünün tarihini içeren haftayı bul
$today = date('Y-m-d');
$current_week = null;

foreach ($weeks as $week) {
    if ($today >= $week['start_date'] && $today <= $week['end_date']) {
        $current_week = $week;
        break;
    }
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Form verilerini al
        $payment_student_id = intval($_POST['student_id']);
        $amount = floatval(str_replace(',', '.', $_POST['amount']));
        $payment_date = clean($_POST['payment_date']);
        $due_date = !empty($_POST['due_date']) ? clean($_POST['due_date']) : null;
        $payment_type = clean($_POST['payment_type']);
        $payment_method = clean($_POST['payment_method']);
        $notes = clean($_POST['notes'] ?? '');
        $status = clean($_POST['status']);
        $period_week_id = intval($_POST['period_week_id'] ?? 0);

        // Veri doğrulama
        if (!$payment_student_id || $amount <= 0 || !$payment_date || !$payment_type || !$payment_method || !$status) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Beklemede veya gecikmiş ödeme için son ödeme tarihi zorunlu
        if (($status === 'pending' || $status === 'late') && empty($due_date)) {
            throw new Exception('Beklemede veya gecikmiş ödemeler için son ödeme tarihi gereklidir!');
        }

        // Öğrenci kontrolü
        $student_check = "SELECT id FROM students WHERE id = ?";
        $student_exists = safeQuery($student_check, [$payment_student_id])->fetch();

        if (!$student_exists) {
            throw new Exception('Geçersiz öğrenci seçimi!');
        }

        // Ödeme ekle - period_week_id ve due_date NULL olabilir
        $payment_data = [
            'student_id' => $payment_student_id,
            'period_id' => $current_period['id'],
            'amount' => $amount,
            'payment_date' => $payment_date,
            'payment_type' => $payment_type,
            'payment_method' => $payment_method,
            'notes' => $notes,
            'status' => $status
        ];

        // Period week id varsa ekle
        if ($period_week_id > 0) {
            $payment_data['period_week_id'] = $period_week_id;
        }

        // Due date varsa ekle
        if ($due_date) {
            $payment_data['due_date'] = $due_date;
        }

        // SQL sorgusunu dinamik olarak oluştur
        $columns = array_keys($payment_data);
        $placeholders = array_map(function ($col) {
            return ':' . $col;
        }, $columns);

        $sql = "INSERT INTO payments (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = db()->prepare($sql);
        $result = $stmt->execute($payment_data);

        if (!$result) {
            throw new Exception('Ödeme kaydedilirken bir hata oluştu! ' . implode(' - ', $stmt->errorInfo()));
        }

        $last_id = db()->lastInsertId();

        setAlert('Ödeme başarıyla kaydedildi! (ID: ' . $last_id . ')', 'success');
        redirect('modules/finance/payments.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Ödeme Ekle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Ödeme
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="payments.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Ödeme Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="payment-form">
                    <div class="row mb-3">
                        <label for="student_id" class="col-sm-3 col-form-label">Öğrenci *</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">-- Öğrenci Seçin --</option>
                                <?php foreach ($students as $student_item): ?>
                                    <option value="<?php echo $student_item['id']; ?>"
                                        data-fee="<?php echo $student_item['net_fee']; ?>"
                                        <?php echo ($student_id && $student_id == $student_item['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student_item['first_name'] . ' ' . $student_item['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="amount" class="col-sm-3 col-form-label">Tutar (₺) *</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="amount" name="amount" required
                                value="<?php echo isset($student) ? $student['net_fee'] : ''; ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="payment_date" class="col-sm-3 col-form-label">Ödeme Tarihi *</label>
                        <div class="col-sm-9">
                            <input type="date" class="form-control" id="payment_date" name="payment_date"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="period_week_id" class="col-sm-3 col-form-label">Hafta</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="period_week_id" name="period_week_id">
                                <option value="">-- Hafta Seçin --</option>
                                <?php foreach ($weeks as $week): ?>
                                    <option value="<?php echo $week['id']; ?>"
                                        <?php echo ($current_week && $current_week['id'] == $week['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($week['name']); ?>
                                        (<?php echo formatDate($week['start_date']); ?> - <?php echo formatDate($week['end_date']); ?>)
                                        <?php echo $week['is_free'] ? '- Ücretsiz Hafta' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="payment_type" class="col-sm-3 col-form-label">Ödeme Türü *</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="payment_type" name="payment_type" required>
                                <option value="monthly">Aylık Ödeme</option>
                                <option value="extra">Ek Ödeme</option>
                                <option value="makeup">Telafi Dersi</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="payment_method" class="col-sm-3 col-form-label">Ödeme Şekli *</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Nakit</option>
                                <option value="credit_card">Kredi Kartı</option>
                                <option value="ziraat_transfer">Ziraat Banka Transferi</option>
                                <option value="enpara_transfer">Enpara Transferi</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="status" class="col-sm-3 col-form-label">Durum *</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="status" name="status" required>
                                <option value="paid">Ödendi</option>
                                <option value="pending">Beklemede</option>
                                <option value="late">Gecikmiş</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3" id="due_date_container" style="display: none;">
                        <label for="due_date" class="col-sm-3 col-form-label">Son Ödeme Tarihi</label>
                        <div class="col-sm-9">
                            <input type="date" class="form-control" id="due_date" name="due_date">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="notes" class="col-sm-3 col-form-label">Notlar</label>
                        <div class="col-sm-9">
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="payments.php" class="btn btn-secondary">İptal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Ödeme Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Hızlı Seçenekler</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" id="set-monthly-fee" class="btn btn-outline-primary">
                        <i class="bi bi-cash"></i> Aylık Ücret
                    </button>
                    <button type="button" id="mark-as-paid" class="btn btn-outline-success">
                        <i class="bi bi-check-circle"></i> Ödendi İşaretle
                    </button>
                    <button type="button" id="mark-as-pending" class="btn btn-outline-warning">
                        <i class="bi bi-clock"></i> Beklemede İşaretle
                    </button>
                </div>
            </div>
        </div>

        <?php if (!empty($weeks)): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Dönem Haftaları</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php foreach ($weeks as $week): ?>
                            <?php
                            $active = ($current_week && $current_week['id'] == $week['id']);
                            $free_class = $week['is_free'] ? ' list-group-item-info' : '';
                            ?>
                            <a href="#" class="list-group-item list-group-item-action<?php echo $active ? ' active' : $free_class; ?>"
                                data-week-id="<?php echo $week['id']; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($week['name']); ?></h6>
                                    <?php if ($week['is_free']): ?>
                                        <span class="badge bg-info">Ücretsiz</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1"><?php echo formatDate($week['start_date']); ?> - <?php echo formatDate($week['end_date']); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const studentSelect = document.getElementById('student_id');
        const amountInput = document.getElementById('amount');
        const statusSelect = document.getElementById('status');
        const dueDateContainer = document.getElementById('due_date_container');
        const dueDateInput = document.getElementById('due_date');
        const setMonthlyFeeBtn = document.getElementById('set-monthly-fee');
        const markAsPaidBtn = document.getElementById('mark-as-paid');
        const markAsPendingBtn = document.getElementById('mark-as-pending');
        const paymentForm = document.getElementById('payment-form');

        // Öğrenci değiştiğinde aylık ücreti güncelle
        studentSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const fee = parseFloat(selectedOption.getAttribute('data-fee'));
                if (fee) {
                    amountInput.value = fee.toFixed(2);
                }
            }
        });

        // Durum değiştiğinde son ödeme tarihini göster/gizle
        statusSelect.addEventListener('change', function() {
            if (this.value === 'pending' || this.value === 'late') {
                dueDateContainer.style.display = 'flex';

                // Son ödeme tarihi boşsa bugünden bir ay sonrasını öner
                if (!dueDateInput.value) {
                    const today = new Date();
                    today.setMonth(today.getMonth() + 1);
                    dueDateInput.value = today.toISOString().split('T')[0];
                }
            } else {
                dueDateContainer.style.display = 'none';
                dueDateInput.value = ''; // Ödendi durumunda son ödeme tarihini temizle
            }
        });

        // Aylık ücret butonu
        setMonthlyFeeBtn.addEventListener('click', function() {
            const selectedOption = studentSelect.options[studentSelect.selectedIndex];
            if (selectedOption.value) {
                const fee = parseFloat(selectedOption.getAttribute('data-fee'));
                if (fee) {
                    amountInput.value = fee.toFixed(2);
                }
            } else {
                alert('Lütfen önce bir öğrenci seçin!');
            }
        });

        // Ödendi butonu
        markAsPaidBtn.addEventListener('click', function() {
            statusSelect.value = 'paid';
            statusSelect.dispatchEvent(new Event('change')); // Değişikliği tetikle
        });

        // Beklemede butonu
        markAsPendingBtn.addEventListener('click', function() {
            statusSelect.value = 'pending';
            statusSelect.dispatchEvent(new Event('change')); // Değişikliği tetikle
        });

        // Hafta seçme
        document.querySelectorAll('.list-group-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const weekId = this.getAttribute('data-week-id');
                document.getElementById('period_week_id').value = weekId;

                // Aktif sınıfı güncelle
                document.querySelectorAll('.list-group-item').forEach(el => {
                    el.classList.remove('active');
                });
                this.classList.add('active');
            });
        });

        // Form gönderilmeden önce kontrol
        paymentForm.addEventListener('submit', function(e) {
            const status = statusSelect.value;

            // Beklemede veya gecikmiş ödemelerde son ödeme tarihi kontrolü
            if ((status === 'pending' || status === 'late') && !dueDateInput.value) {
                e.preventDefault();
                alert('Beklemede veya gecikmiş ödemeler için son ödeme tarihi girmelisiniz!');
                dueDateContainer.style.display = 'flex';
                dueDateInput.focus();
            }
        });

        // Sayfa yüklendiğinde duruma göre son ödeme tarihini göster/gizle
        statusSelect.dispatchEvent(new Event('change'));
    });
</script>

<?php require_once '../../includes/footer.php'; ?>