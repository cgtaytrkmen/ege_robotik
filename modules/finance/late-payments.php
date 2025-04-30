<?php
// modules/finance/late-payments.php - Gecikmiş ödemeler listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Filtreleme parametreleri
$filter_days = $_GET['days'] ?? 0; // Kaç gün geciktiği filtresi
$filter_student = $_GET['student_id'] ?? '';

// Gecikmiş ödemeleri getir
function getLatePayments($period_id, $filters = [])
{
    $where_clauses = ["p.period_id = ?", "p.status IN ('pending', 'late')", "p.due_date < CURDATE()"];
    $params = [$period_id];

    if (!empty($filters['days']) && $filters['days'] > 0) {
        $where_clauses[] = "DATEDIFF(CURDATE(), p.due_date) >= ?";
        $params[] = $filters['days'];
    }

    if (!empty($filters['student_id'])) {
        $where_clauses[] = "p.student_id = ?";
        $params[] = $filters['student_id'];
    }

    $where_sql = implode(' AND ', $where_clauses);

    $query = "SELECT p.*, s.first_name, s.last_name, pr.first_name as parent_first_name, 
              pr.last_name as parent_last_name, pr.phone as parent_phone,
              DATEDIFF(CURDATE(), p.due_date) as days_late
              FROM payments p
              JOIN students s ON p.student_id = s.id
              LEFT JOIN student_parents sp ON s.id = sp.student_id AND sp.is_primary = 1
              LEFT JOIN parents pr ON sp.parent_id = pr.id
              WHERE $where_sql
              ORDER BY days_late DESC, p.amount DESC";

    return safeQuery($query, $params)->fetchAll();
}

// Henüz oluşturulmamış ama gecikmiş olması gereken ödemeleri getir
function getPotentialLatePayments($period_id, $filters = [])
{
    $current_date = date('Y-m-d');
    $current_month = date('Y-m');

    // Son ödeme tarihi geçmiş ama ödeme kaydı oluşturulmamış öğrencileri bul
    $query = "SELECT s.id, s.first_name, s.last_name, s.net_fee, s.payment_day,
              pr.first_name as parent_first_name, pr.last_name as parent_last_name, pr.phone as parent_phone
              FROM students s
              JOIN student_periods sp ON s.id = sp.student_id
              LEFT JOIN student_parents spr ON s.id = spr.student_id AND spr.is_primary = 1
              LEFT JOIN parents pr ON spr.parent_id = pr.id
              WHERE sp.period_id = ? AND sp.status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM payments p 
                  WHERE p.student_id = s.id 
                  AND p.period_id = sp.period_id 
                  AND DATE_FORMAT(p.due_date, '%Y-%m') = ?
              )";

    $params = [$period_id, $current_month];

    if (!empty($filters['student_id'])) {
        $query .= " AND s.id = ?";
        $params[] = $filters['student_id'];
    }

    $students = safeQuery($query, $params)->fetchAll();

    $potential_payments = [];
    foreach ($students as $student) {
        $due_date = $current_month . '-' . str_pad($student['payment_day'], 2, '0', STR_PAD_LEFT);

        // Eğer son ödeme tarihi geçmişse, potansiyel gecikmiş ödeme olarak ekle
        if (strtotime($due_date) < strtotime($current_date)) {
            $days_late = floor((strtotime($current_date) - strtotime($due_date)) / (60 * 60 * 24));

            // Filtre kontrolü
            if (!empty($filters['days']) && $days_late < $filters['days']) {
                continue;
            }

            $potential_payments[] = [
                'id' => 'potential_' . $student['id'],
                'student_id' => $student['id'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'parent_first_name' => $student['parent_first_name'],
                'parent_last_name' => $student['parent_last_name'],
                'parent_phone' => $student['parent_phone'],
                'amount' => $student['net_fee'],
                'due_date' => $due_date,
                'payment_type' => 'monthly',
                'status' => 'potential_late',
                'days_late' => $days_late
            ];
        }
    }

    return $potential_payments;
}

// Öğrenci listesini getir (filtreleme için)
$students_query = "SELECT s.id, s.first_name, s.last_name 
                   FROM students s
                   JOIN student_periods sp ON s.id = sp.student_id
                   WHERE sp.period_id = ?
                   ORDER BY s.first_name, s.last_name";
$students = safeQuery($students_query, [$current_period['id']])->fetchAll();

// Filtreleri uygula
$filters = [
    'days' => $filter_days,
    'student_id' => $filter_student
];

$late_payments = getLatePayments($current_period['id'], $filters);
$potential_late_payments = getPotentialLatePayments($current_period['id'], $filters);

// Tüm gecikmiş ödemeleri birleştir
$all_late_payments = array_merge($late_payments, $potential_late_payments);

// Gecikme süresine göre sırala
usort($all_late_payments, function ($a, $b) {
    return $b['days_late'] - $a['days_late'];
});

// Toplam gecikmiş tutarı hesapla
$total_late_amount = 0;
foreach ($all_late_payments as $payment) {
    $total_late_amount += $payment['amount'];
}

$page_title = 'Gecikmiş Ödemeler - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Gecikmiş Ödemeler
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="payments.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri
        </a>
    </div>
</div>

<!-- Filtreler -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="days" class="form-label">Gecikme Süresi (En az)</label>
                <select class="form-select" id="days" name="days">
                    <option value="0">Tümü</option>
                    <option value="1" <?php echo $filter_days == '1' ? 'selected' : ''; ?>>1+ gün</option>
                    <option value="3" <?php echo $filter_days == '3' ? 'selected' : ''; ?>>3+ gün</option>
                    <option value="7" <?php echo $filter_days == '7' ? 'selected' : ''; ?>>7+ gün</option>
                    <option value="15" <?php echo $filter_days == '15' ? 'selected' : ''; ?>>15+ gün</option>
                    <option value="30" <?php echo $filter_days == '30' ? 'selected' : ''; ?>>30+ gün</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="student_id" class="form-label">Öğrenci</label>
                <select class="form-select" id="student_id" name="student_id">
                    <option value="">Tümü</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>"
                            <?php echo $filter_student == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtrele
                </button>
                <a href="late-payments.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Özet Bilgileri -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Toplam Gecikmiş Tutar</h6>
                <h4 class="card-text"><?php echo formatMoney($total_late_amount); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Gecikmiş Ödeme Sayısı</h6>
                <h4 class="card-text"><?php echo count($all_late_payments); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <h6 class="card-title">Ortalama Gecikme Süresi</h6>
                <h4 class="card-text">
                    <?php
                    if (count($all_late_payments) > 0) {
                        $total_days = array_sum(array_column($all_late_payments, 'days_late'));
                        echo round($total_days / count($all_late_payments), 1) . ' gün';
                    } else {
                        echo '0 gün';
                    }
                    ?>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Gecikmiş Ödemeler Tablosu -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>Öğrenci</th>
                        <th>Veli</th>
                        <th>Telefon</th>
                        <th>Tutar</th>
                        <th>Son Ödeme Tarihi</th>
                        <th>Gecikme</th>
                        <th>Ödeme Tipi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_late_payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['parent_first_name'] . ' ' . $payment['parent_last_name']); ?></td>
                            <td>
                                <?php if (!empty($payment['parent_phone'])): ?>
                                    <a href="tel:<?php echo $payment['parent_phone']; ?>">
                                        <?php echo formatPhone($payment['parent_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatMoney($payment['amount']); ?></td>
                            <td><?php echo formatDate($payment['due_date']); ?></td>
                            <td>
                                <?php
                                $days_late = $payment['days_late'];
                                $badge_class = $days_late >= 30 ? 'danger' : ($days_late >= 7 ? 'warning text-dark' : 'secondary');
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>">
                                    <?php echo $days_late; ?> gün
                                </span>
                            </td>
                            <td>
                                <?php
                                if ($payment['status'] == 'potential_late') {
                                    echo '<span class="badge bg-info">Oluşturulmamış</span>';
                                } else {
                                    $type_labels = [
                                        'monthly' => 'Aylık',
                                        'extra' => 'Ekstra',
                                        'makeup' => 'Telafi'
                                    ];
                                    echo $type_labels[$payment['payment_type']] ?? $payment['payment_type'];
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($payment['status'] == 'potential_late'): ?>
                                    <a href="add-payment.php?student_id=<?php echo $payment['student_id']; ?>&amount=<?php echo $payment['amount']; ?>&due_date=<?php echo $payment['due_date']; ?>"
                                        class="btn btn-sm btn-success" title="Ödeme Oluştur">
                                        <i class="bi bi-plus-circle"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="add-payment.php?payment_id=<?php echo $payment['id']; ?>"
                                        class="btn btn-sm btn-success" title="Ödeme Al">
                                        <i class="bi bi-cash"></i>
                                    </a>
                                    <a href="edit-payment.php?id=<?php echo $payment['id']; ?>"
                                        class="btn btn-sm btn-primary" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-info"
                                    onclick="sendReminder(<?php echo $payment['id']; ?>)"
                                    title="Hatırlatma Gönder">
                                    <i class="bi bi-envelope"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function sendReminder(paymentId) {
        // İleride SMS veya e-posta hatırlatma sistemi için
        alert('Hatırlatma sistemi henüz aktif değil.');
    }
</script>

<?php require_once '../../includes/footer.php'; ?>