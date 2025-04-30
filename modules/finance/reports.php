<?php
// modules/finance/reports.php - Finans raporları
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Rapor tarihleri
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Ayın ilk günü
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Ayın son günü

// Özet rapor verileri
function getFinancialSummary($period_id, $start_date, $end_date)
{
    // Toplam gelir
    $income_query = "SELECT SUM(amount) as total FROM payments 
                     WHERE period_id = ? AND status = 'paid' 
                     AND payment_date BETWEEN ? AND ?";
    $income = safeQuery($income_query, [$period_id, $start_date, $end_date])->fetch()['total'] ?? 0;

    // Bekleyen gelir
    $pending_query = "SELECT SUM(amount) as total FROM payments 
                      WHERE period_id = ? AND status = 'pending' 
                      AND due_date BETWEEN ? AND ?";
    $pending = safeQuery($pending_query, [$period_id, $start_date, $end_date])->fetch()['total'] ?? 0;

    // Gecikmiş gelir
    $late_query = "SELECT SUM(amount) as total FROM payments 
                   WHERE period_id = ? AND status = 'late' 
                   AND due_date BETWEEN ? AND ?";
    $late = safeQuery($late_query, [$period_id, $start_date, $end_date])->fetch()['total'] ?? 0;

    // Toplam gider
    $expense_query = "SELECT SUM(amount) as total FROM expenses 
                      WHERE expense_date BETWEEN ? AND ?";
    $expenses = safeQuery($expense_query, [$start_date, $end_date])->fetch()['total'] ?? 0;

    return [
        'income' => $income,
        'pending' => $pending,
        'late' => $late,
        'expenses' => $expenses,
        'net_income' => $income - $expenses,
        'expected_total' => $income + $pending + $late
    ];
}

// Aylık gelir/gider grafiği için veri
function getMonthlyData($period_id, $start_date, $end_date)
{
    // Aylık gelirler
    $income_query = "SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total 
                     FROM payments 
                     WHERE period_id = ? AND status = 'paid' 
                     AND payment_date BETWEEN ? AND ?
                     GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                     ORDER BY month";
    $income_data = safeQuery($income_query, [$period_id, $start_date, $end_date])->fetchAll();

    // Aylık giderler
    $expense_query = "SELECT DATE_FORMAT(expense_date, '%Y-%m') as month, SUM(amount) as total 
                      FROM expenses 
                      WHERE expense_date BETWEEN ? AND ?
                      GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
                      ORDER BY month";
    $expense_data = safeQuery($expense_query, [$start_date, $end_date])->fetchAll();

    return [
        'income' => $income_data,
        'expenses' => $expense_data
    ];
}

// Kategori bazlı gider raporu
function getExpensesByCategory($start_date, $end_date)
{
    $query = "SELECT category, SUM(amount) as total 
              FROM expenses 
              WHERE expense_date BETWEEN ? AND ?
              GROUP BY category
              ORDER BY total DESC";
    return safeQuery($query, [$start_date, $end_date])->fetchAll();
}

// Öğrenci bazlı ödeme raporu
function getPaymentsByStudent($period_id, $start_date, $end_date)
{
    $query = "SELECT s.id, s.first_name, s.last_name, 
              SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END) as paid_amount,
              SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_amount,
              SUM(CASE WHEN p.status = 'late' THEN p.amount ELSE 0 END) as late_amount,
              SUM(p.amount) as total_amount
              FROM students s
              JOIN payments p ON s.id = p.student_id
              WHERE p.period_id = ? AND p.due_date BETWEEN ? AND ?
              GROUP BY s.id
              ORDER BY s.first_name, s.last_name";
    return safeQuery($query, [$period_id, $start_date, $end_date])->fetchAll();
}

$summary = getFinancialSummary($current_period['id'], $start_date, $end_date);
$monthly_data = getMonthlyData($current_period['id'], $start_date, $end_date);
$expense_categories = getExpensesByCategory($start_date, $end_date);
$student_payments = getPaymentsByStudent($current_period['id'], $start_date, $end_date);

$page_title = 'Finans Raporları - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Finans Raporları
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="bi bi-printer"></i> Yazdır
        </button>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri
        </a>
    </div>
</div>

<!-- Tarih Filtreleri -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="start_date" name="start_date"
                    value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="end_date" name="end_date"
                    value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Filtrele
                </button>
                <a href="reports.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">Tahsil Edilen</h6>
                <h3 class="card-text"><?php echo formatMoney($summary['income']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="card-title">Bekleyen</h6>
                <h3 class="card-text"><?php echo formatMoney($summary['pending']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Giderler</h6>
                <h3 class="card-text"><?php echo formatMoney($summary['expenses']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-<?php echo $summary['net_income'] >= 0 ? 'primary' : 'danger'; ?> text-white">
            <div class="card-body">
                <h6 class="card-title">Net Kar/Zarar</h6>
                <h3 class="card-text"><?php echo formatMoney($summary['net_income']); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Gelir/Gider Grafiği -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Aylık Gelir/Gider Grafiği</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Gider Kategorileri ve Öğrenci Ödemeleri -->
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Gider Kategorileri</h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Öğrenci Bazlı Ödemeler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Öğrenci</th>
                                <th>Ödenen</th>
                                <th>Bekleyen</th>
                                <th>Toplam</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                    <td class="text-success"><?php echo formatMoney($payment['paid_amount']); ?></td>
                                    <td class="text-warning"><?php echo formatMoney($payment['pending_amount']); ?></td>
                                    <td><?php echo formatMoney($payment['total_amount']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Aylık gelir/gider grafiği
    const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: [
                <?php
                $months = [];
                foreach ($monthly_data['income'] as $data) {
                    $months[$data['month']] = $data['month'];
                }
                foreach ($monthly_data['expenses'] as $data) {
                    $months[$data['month']] = $data['month'];
                }
                ksort($months);
                echo "'" . implode("','", $months) . "'";
                ?>
            ],
            datasets: [{
                label: 'Gelir',
                data: [
                    <?php
                    foreach ($months as $month) {
                        $found = false;
                        foreach ($monthly_data['income'] as $data) {
                            if ($data['month'] === $month) {
                                echo $data['total'] . ',';
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) echo '0,';
                    }
                    ?>
                ],
                backgroundColor: 'rgba(40, 167, 69, 0.7)'
            }, {
                label: 'Gider',
                data: [
                    <?php
                    foreach ($months as $month) {
                        $found = false;
                        foreach ($monthly_data['expenses'] as $data) {
                            if ($data['month'] === $month) {
                                echo $data['total'] . ',';
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) echo '0,';
                    }
                    ?>
                ],
                backgroundColor: 'rgba(220, 53, 69, 0.7)'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Gider kategorileri grafiği
    const categoryChart = new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: {
            labels: [
                <?php
                foreach ($expense_categories as $category) {
                    $label = $expense_categories[$category['category']] ?? $category['category'];
                    echo "'" . $label . "',";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php
                    foreach ($expense_categories as $category) {
                        echo $category['total'] . ',';
                    }
                    ?>
                ],
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#7CFC00', '#00CED1'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Gider Kategorileri Dağılımı'
                }
            }
        }
    });
</script>

<!-- Detaylı Rapor -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detaylı Finans Raporu</h5>
                <button onclick="exportToExcel()" class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Excel'e Aktar
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="detailReport">
                        <thead>
                            <tr class="table-light">
                                <th colspan="2">ÖZET RAPOR</th>
                                <th colspan="2">
                                    <?php echo formatDate($start_date) . ' - ' . formatDate($end_date); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="2"><strong>GELİRLER</strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td>Tahsil Edilen Ödemeler</td>
                                <td class="text-end"><?php echo formatMoney($summary['income']); ?></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td>Bekleyen Ödemeler</td>
                                <td class="text-end"><?php echo formatMoney($summary['pending']); ?></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td>Gecikmiş Ödemeler</td>
                                <td class="text-end"><?php echo formatMoney($summary['late']); ?></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="table-success">
                                <td><strong>TOPLAM BEKLENEN GELİR</strong></td>
                                <td class="text-end"><strong><?php echo formatMoney($summary['expected_total']); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong>GİDERLER</strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <?php foreach ($expense_categories as $category): ?>
                                <tr>
                                    <td><?php echo $expense_categories[$category['category']] ?? $category['category']; ?></td>
                                    <td class="text-end"><?php echo formatMoney($category['total']); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-danger">
                                <td><strong>TOPLAM GİDER</strong></td>
                                <td class="text-end"><strong><?php echo formatMoney($summary['expenses']); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="<?php echo $summary['net_income'] >= 0 ? 'table-primary' : 'table-danger'; ?>">
                                <td><strong>NET KAR/ZARAR</strong></td>
                                <td class="text-end"><strong><?php echo formatMoney($summary['net_income']); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Excel'e aktarma fonksiyonu
    function exportToExcel() {
        // Basit bir HTML tablosunu Excel'e aktarma
        var table = document.getElementById('detailReport');
        var html = table.outerHTML;
        var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);

        var downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);
        downloadLink.href = url;
        downloadLink.download = 'finans_raporu_' + '<?php echo date('Y-m-d'); ?>' + '.xls';
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
</script>

<style>
    /* Print stili */
    @media print {
        .btn {
            display: none !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .card-header {
            background: none !important;
            border-bottom: 1px solid #000 !important;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>