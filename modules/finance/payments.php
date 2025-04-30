<?php
// modules/finance/payments.php - Ödemeler listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Filtreleme parametreleri
$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_student = $_GET['student_id'] ?? '';
$filter_type = $_GET['type'] ?? '';

// Ödemeleri getir
function getPayments($period_id, $filters = [])
{
    $where_clauses = ["p.period_id = ?"];
    $params = [$period_id];

    if (!empty($filters['status'])) {
        $where_clauses[] = "p.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['month'])) {
        $where_clauses[] = "(p.payment_date LIKE ? OR p.due_date LIKE ?)";
        $params[] = $filters['month'] . '%';
        $params[] = $filters['month'] . '%';
    }

    if (!empty($filters['student_id'])) {
        $where_clauses[] = "p.student_id = ?";
        $params[] = $filters['student_id'];
    }

    if (!empty($filters['type'])) {
        $where_clauses[] = "p.payment_type = ?";
        $params[] = $filters['type'];
    }

    $where_sql = implode(' AND ', $where_clauses);

    $query = "SELECT p.*, s.first_name, s.last_name 
              FROM payments p
              JOIN students s ON p.student_id = s.id
              WHERE $where_sql
              ORDER BY p.payment_date DESC, p.id DESC";

    return safeQuery($query, $params)->fetchAll();
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
    'status' => $filter_status,
    'month' => $filter_month,
    'student_id' => $filter_student,
    'type' => $filter_type
];

$payments = getPayments($current_period['id'], $filters);

// Toplam tutarları hesapla
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;
$late_amount = 0;

foreach ($payments as $payment) {
    $total_amount += $payment['amount'];
    if ($payment['status'] == 'paid') {
        $paid_amount += $payment['amount'];
    } elseif ($payment['status'] == 'pending') {
        $pending_amount += $payment['amount'];
    } elseif ($payment['status'] == 'late') {
        $late_amount += $payment['amount'];
    }
}

$page_title = 'Ödemeler - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Ödemeler
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add-payment.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ödeme Al
        </a>
        <a href="generate-monthly-payments.php" class="btn btn-warning">
            <i class="bi bi-calendar-plus"></i> Aylık Ödemeler
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri
        </a>
    </div>
</div>

<!-- Filtreler -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="month" class="form-label">Ay</label>
                <input type="month" class="form-control" id="month" name="month"
                    value="<?php echo htmlspecialchars($filter_month); ?>">
            </div>

            <div class="col-md-3">
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

            <div class="col-md-2">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="paid" <?php echo $filter_status == 'paid' ? 'selected' : ''; ?>>Ödendi</option>
                    <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                    <option value="late" <?php echo $filter_status == 'late' ? 'selected' : ''; ?>>Gecikmiş</option>
                </select>
            </div>

            <div class="col-md-2">
                <label for="type" class="form-label">Tip</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Tümü</option>
                    <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Aylık</option>
                    <option value="extra" <?php echo $filter_type == 'extra' ? 'selected' : ''; ?>>Ekstra</option>
                    <option value="makeup" <?php echo $filter_type == 'makeup' ? 'selected' : ''; ?>>Telafi</option>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtrele
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Özet Bilgileri -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title">Toplam</h6>
                <h4 class="card-text"><?php echo formatMoney($total_amount); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Ödenen</h6>
                <h4 class="card-text"><?php echo formatMoney($paid_amount); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Bekleyen</h6>
                <h4 class="card-text"><?php echo formatMoney($pending_amount); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Gecikmiş</h6>
                <h4 class="card-text"><?php echo formatMoney($late_amount); ?></h4>
            </div>
        </div>
    </div>
</div>

<!-- Ödemeler Tablosu -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Öğrenci</th>
                        <th>Tutar</th>
                        <th>Ödeme Tarihi</th>
                        <th>Son Ödeme Tarihi</th>
                        <th>Tip</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                            <td><?php echo formatMoney($payment['amount']); ?></td>
                            <td>
                                <?php
                                if ($payment['payment_date']) {
                                    echo formatDate($payment['payment_date']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo formatDate($payment['due_date']); ?></td>
                            <td>
                                <?php
                                $type_labels = [
                                    'monthly' => 'Aylık',
                                    'extra' => 'Ekstra',
                                    'makeup' => 'Telafi'
                                ];
                                echo $type_labels[$payment['payment_type']] ?? $payment['payment_type'];
                                ?>
                            </td>
                            <td>
                                <?php
                                $status_badges = [
                                    'paid' => 'success',
                                    'pending' => 'warning',
                                    'late' => 'danger'
                                ];
                                $status_labels = [
                                    'paid' => 'Ödendi',
                                    'pending' => 'Bekliyor',
                                    'late' => 'Gecikmiş'
                                ];
                                $badge_class = $status_badges[$payment['status']] ?? 'secondary';
                                $label = $status_labels[$payment['status']] ?? $payment['status'];
                                ?>
                                <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                            </td>
                            <td>
                                <?php if ($payment['status'] != 'paid'): ?>
                                    <a href="add-payment.php?payment_id=<?php echo $payment['id']; ?>"
                                        class="btn btn-sm btn-success" title="Ödeme Al">
                                        <i class="bi bi-cash"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="edit-payment.php?id=<?php echo $payment['id']; ?>"
                                    class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($payment['status'] == 'paid'): ?>
                                    <a href="payment-receipt.php?id=<?php echo $payment['id']; ?>"
                                        class="btn btn-sm btn-secondary" title="Makbuz" target="_blank">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="delete-payment.php?id=<?php echo $payment['id']; ?>"
                                    class="btn btn-sm btn-danger" title="Sil"
                                    onclick="return confirm('Bu ödeme kaydını silmek istediğinizden emin misiniz?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>