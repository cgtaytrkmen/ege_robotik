<?php
// modules/finance/index.php - Finans ana sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Finans istatistiklerini getir
function getFinanceStats($period_id)
{
    // Bu ayın geliri
    $current_month = date('Y-m');
    $income_query = "SELECT SUM(amount) as total FROM payments 
                     WHERE period_id = ? AND payment_date LIKE ? AND status = 'paid'";
    $income = safeQuery($income_query, [$period_id, $current_month . '%'])->fetch()['total'] ?? 0;

    // Bu ayın gideri
    $expense_query = "SELECT SUM(amount) as total FROM expenses 
                      WHERE expense_date LIKE ?";
    $expenses = safeQuery($expense_query, [$current_month . '%'])->fetch()['total'] ?? 0;

    // Bekleyen ödemeler
    $pending_query = "SELECT SUM(amount) as total FROM payments 
                      WHERE period_id = ? AND status = 'pending'";
    $pending = safeQuery($pending_query, [$period_id])->fetch()['total'] ?? 0;

    // Gecikmiş ödemeler (kayıtlı olanlar)
    $late_query = "SELECT SUM(amount) as total FROM payments 
                   WHERE period_id = ? AND status = 'late'";
    $late = safeQuery($late_query, [$period_id])->fetch()['total'] ?? 0;

    // Potansiyel gecikmiş ödemeler (henüz oluşturulmamış)
    $potential_late_query = "SELECT COUNT(*) as count, SUM(s.net_fee) as total
                            FROM students s
                            JOIN student_periods sp ON s.id = sp.student_id
                            WHERE sp.period_id = ? AND sp.status = 'active'
                            AND NOT EXISTS (
                                SELECT 1 FROM payments p 
                                WHERE p.student_id = s.id 
                                AND p.period_id = sp.period_id 
                                AND DATE_FORMAT(p.due_date, '%Y-%m') = ?
                            )
                            AND CONCAT(?, '-', LPAD(s.payment_day, 2, '0')) < CURDATE()";

    $potential_late = safeQuery($potential_late_query, [$period_id, $current_month, $current_month])->fetch();
    $total_late = $late + ($potential_late['total'] ?? 0);

    // Toplam öğrenci sayısı
    $student_query = "SELECT COUNT(DISTINCT student_id) as count FROM student_periods 
                      WHERE period_id = ? AND status = 'active'";
    $student_count = safeQuery($student_query, [$period_id])->fetch()['count'] ?? 0;

    // Beklenen aylık gelir
    $expected_income_query = "SELECT SUM(s.net_fee) as total FROM students s 
                             JOIN student_periods sp ON s.id = sp.student_id 
                             WHERE sp.period_id = ? AND sp.status = 'active'";
    $expected_income = safeQuery($expected_income_query, [$period_id])->fetch()['total'] ?? 0;

    return [
        'income' => $income,
        'expenses' => $expenses,
        'net_income' => $income - $expenses,
        'pending' => $pending,
        'late' => $total_late,
        'student_count' => $student_count,
        'expected_income' => $expected_income
    ];
}

// Son ödemeleri getir
function getRecentPayments($period_id, $limit = 10)
{
    $query = "SELECT p.*, s.first_name, s.last_name 
              FROM payments p
              JOIN students s ON p.student_id = s.id
              WHERE p.period_id = ?
              ORDER BY p.payment_date DESC, p.id DESC
              LIMIT ?";

    return safeQuery($query, [$period_id, $limit])->fetchAll();
}

// Yaklaşan ödemeleri getir
function getDuePayments($period_id, $days = 7)
{
    $today = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));

    // Kaydedilmiş ve yaklaşan ödemeler
    $query = "SELECT p.*, s.first_name, s.last_name 
              FROM payments p
              JOIN students s ON p.student_id = s.id
              WHERE p.period_id = ? AND p.status IN ('pending', 'late') 
              AND p.due_date BETWEEN ? AND ?
              ORDER BY p.due_date ASC";

    $recorded_payments = safeQuery($query, [$period_id, $today, $end_date])->fetchAll();

    // Henüz oluşturulmamış ama yaklaşan ödemeler
    $current_month = date('Y-m');
    $query = "SELECT s.id, s.first_name, s.last_name, s.net_fee, s.payment_day
              FROM students s
              JOIN student_periods sp ON s.id = sp.student_id
              WHERE sp.period_id = ? AND sp.status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM payments p 
                  WHERE p.student_id = s.id 
                  AND p.period_id = sp.period_id 
                  AND DATE_FORMAT(p.due_date, '%Y-%m') = ?
              )";

    $active_students = safeQuery($query, [$period_id, $current_month])->fetchAll();

    $potential_payments = [];
    foreach ($active_students as $student) {
        $due_date = $current_month . '-' . str_pad($student['payment_day'], 2, '0', STR_PAD_LEFT);

        // Eğer tarih bu ay içinde ve önümüzdeki 7 gün içindeyse
        if (strtotime($due_date) >= strtotime($today) && strtotime($due_date) <= strtotime($end_date)) {
            $potential_payments[] = [
                'id' => 'potential_' . $student['id'],
                'student_id' => $student['id'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'amount' => $student['net_fee'],
                'due_date' => $due_date,
                'status' => 'potential_pending'
            ];
        }
    }

    // Tüm ödemeleri birleştir ve tarihe göre sırala
    $all_payments = array_merge($recorded_payments, $potential_payments);
    usort($all_payments, function ($a, $b) {
        return strtotime($a['due_date']) - strtotime($b['due_date']);
    });

    // İlk 5 ödemeyi döndür
    return array_slice($all_payments, 0, 5);
}

$stats = getFinanceStats($current_period['id']);
$recent_payments = getRecentPayments($current_period['id']);
$due_payments = getDuePayments($current_period['id']);

$page_title = 'Finans Yönetimi - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Finans Yönetimi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add-payment.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ödeme Al
        </a>
        <a href="add-expense.php" class="btn btn-outline-primary">
            <i class="bi bi-cart-plus"></i> Gider Ekle
        </a>
        <a href="late-payments.php" class="btn btn-outline-danger">
            <i class="bi bi-exclamation-triangle"></i> Gecikmiş Ödemeler
        </a>
        <a href="reports.php" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-bar-graph"></i> Raporlar
        </a>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Bu Ay Gelir</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['income']); ?></h2>
                <small>Aktif Öğrenci: <?php echo $stats['student_count']; ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5 class="card-title">Bu Ay Gider</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['expenses']); ?></h2>
                <small>Net: <?php echo formatMoney($stats['net_income']); ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Bekleyen</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['pending']); ?></h2>
                <small>Gecikmiş: <?php echo formatMoney($stats['late']); ?></small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Aylık Hedef</h5>
                <h2 class="card-text"><?php echo formatMoney($stats['expected_income']); ?></h2>
                <small><?php echo round(($stats['income'] / $stats['expected_income']) * 100); ?>% Tahsil</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Yaklaşan Ödemeler -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Yaklaşan Ödemeler</h5>
                <a href="payments.php?filter=due" class="btn btn-sm btn-outline-primary">Tümü</a>
            </div>
            <div class="card-body">
                <?php if (count($due_payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Öğrenci</th>
                                    <th>Tutar</th>
                                    <th>Son Ödeme</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($due_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td><?php echo formatMoney($payment['amount']); ?></td>
                                        <td>
                                            <?php
                                            $due_date = strtotime($payment['due_date']);
                                            $today = strtotime(date('Y-m-d'));
                                            $diff_days = floor(($due_date - $today) / (60 * 60 * 24));

                                            echo formatDate($payment['due_date']);
                                            if ($diff_days <= 3) {
                                                echo ' <span class="badge bg-danger">' . $diff_days . ' gün</span>';
                                            } else {
                                                echo ' <span class="badge bg-warning text-dark">' . $diff_days . ' gün</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['status'] == 'potential_pending'): ?>
                                                <a href="add-payment.php?student_id=<?php echo $payment['student_id']; ?>&amount=<?php echo $payment['amount']; ?>&due_date=<?php echo $payment['due_date']; ?>"
                                                    class="btn btn-sm btn-success" title="Ödeme Oluştur">
                                                    <i class="bi bi-plus-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="add-payment.php?payment_id=<?php echo $payment['id']; ?>"
                                                    class="btn btn-sm btn-success" title="Ödeme Al">
                                                    <i class="bi bi-cash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Yaklaşan ödeme bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Son Ödemeler -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Son Ödemeler</h5>
                <a href="payments.php" class="btn btn-sm btn-outline-primary">Tümü</a>
            </div>
            <div class="card-body">
                <?php if (count($recent_payments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Öğrenci</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td><?php echo formatMoney($payment['amount']); ?></td>
                                        <td><?php echo !empty($payment['payment_date']) ? formatDate($payment['payment_date']) : '-'; ?></td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Henüz ödeme kaydı bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı İşlemler -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="payments.php" class="btn btn-outline-primary btn-lg w-100">
                            <i class="bi bi-list-ul"></i> Tüm Ödemeler
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="expenses.php" class="btn btn-outline-primary btn-lg w-100">
                            <i class="bi bi-cart"></i> Tüm Giderler
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="add-payment.php?bulk=1" class="btn btn-outline-success btn-lg w-100">
                            <i class="bi bi-cash-stack"></i> Toplu Ödeme
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="generate-monthly-payments.php" class="btn btn-outline-warning btn-lg w-100">
                            <i class="bi bi-calendar-plus"></i> Aylık Ödemeler
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>