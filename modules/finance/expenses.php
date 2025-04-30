<?php
// modules/finance/expenses.php - Giderler listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Gider kategorileri (ileride veritabanından çekilebilir)
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

// Filtreleme parametreleri
$filter_category = $_GET['category'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_search = $_GET['search'] ?? '';

// Giderleri getir
function getExpenses($filters = [])
{
    $where_clauses = [];
    $params = [];

    if (!empty($filters['category'])) {
        $where_clauses[] = "category = ?";
        $params[] = $filters['category'];
    }

    if (!empty($filters['month'])) {
        $where_clauses[] = "expense_date LIKE ?";
        $params[] = $filters['month'] . '%';
    }

    if (!empty($filters['search'])) {
        $where_clauses[] = "(description LIKE ? OR receipt_number LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $query = "SELECT * FROM expenses 
              $where_sql
              ORDER BY expense_date DESC, id DESC";

    return safeQuery($query, $params)->fetchAll();
}

// Filtreleri uygula
$filters = [
    'category' => $filter_category,
    'month' => $filter_month,
    'search' => $filter_search
];

$expenses = getExpenses($filters);

// Toplam tutarları hesapla
$total_amount = 0;
$category_totals = [];

foreach ($expenses as $expense) {
    $total_amount += $expense['amount'];
    if (!isset($category_totals[$expense['category']])) {
        $category_totals[$expense['category']] = 0;
    }
    $category_totals[$expense['category']] += $expense['amount'];
}

$page_title = 'Giderler';
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Giderler</h2>
    <div>
        <a href="add-expense.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Gider Ekle
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
                <label for="category" class="form-label">Kategori</label>
                <select class="form-select" id="category" name="category">
                    <option value="">Tümü</option>
                    <?php foreach ($expense_categories as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filter_category == $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label for="search" class="form-label">Ara</label>
                <input type="text" class="form-control" id="search" name="search"
                    value="<?php echo htmlspecialchars($filter_search); ?>"
                    placeholder="Açıklama veya fiş numarası...">
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
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="card-title">Toplam Gider</h6>
                <h4 class="card-text"><?php echo formatMoney($total_amount); ?></h4>
            </div>
        </div>
    </div>

    <?php if (!empty($category_totals)): ?>
        <?php
        arsort($category_totals); // En yüksek tutardan en düşüğe sırala
        $top_categories = array_slice($category_totals, 0, 2, true);
        ?>
        <?php foreach ($top_categories as $category => $amount): ?>
            <div class="col-md-4">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="card-title"><?php echo $expense_categories[$category] ?? $category; ?></h6>
                        <h4 class="card-text"><?php echo formatMoney($amount); ?></h4>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Giderler Tablosu -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tarih</th>
                        <th>Kategori</th>
                        <th>Açıklama</th>
                        <th>Tutar</th>
                        <th>Fiş No</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo $expense['id']; ?></td>
                            <td><?php echo formatDate($expense['expense_date']); ?></td>
                            <td><?php echo $expense_categories[$expense['category']] ?? $expense['category']; ?></td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td><?php echo formatMoney($expense['amount']); ?></td>
                            <td><?php echo htmlspecialchars($expense['receipt_number'] ?? '-'); ?></td>
                            <td>
                                <a href="edit-expense.php?id=<?php echo $expense['id']; ?>"
                                    class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="delete-expense.php?id=<?php echo $expense['id']; ?>"
                                    class="btn btn-sm btn-danger" title="Sil"
                                    onclick="return confirm('Bu gider kaydını silmek istediğinizden emin misiniz?');">
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

<!-- Kategori Bazlı Grafik (ileride eklenebilir) -->
<?php if (!empty($category_totals)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Kategori Dağılımı</h5>
        </div>
        <div class="card-body">
            <canvas id="categoryChart" height="300"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Kategori grafiği
        const categoryData = {
            labels: [
                <?php foreach ($category_totals as $category => $amount): ?> '<?php echo $expense_categories[$category] ?? $category; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($category_totals as $amount): ?>
                        <?php echo $amount; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                    '#FF9F40', '#FF6384', '#C9CBCF', '#7CFC00', '#00CED1'
                ]
            }]
        };

        const ctx = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: categoryData,
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
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>