<?php
// modules/topics/index.php - İşlenen Konular Listesi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Filtreleme parametreleri
$filter_classroom = isset($_GET['classroom']) ? intval($_GET['classroom']) : 0;
$filter_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Sınıfları getir
$classrooms_query = "SELECT * FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// İşlenen konuları getir
$topics_query = "SELECT t.*, l.day, l.start_time, l.end_time, c.name as classroom_name, c.id as classroom_id
                FROM topics t
                JOIN lessons l ON t.lesson_id = l.id
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE l.period_id = ?";
$params = [$current_period['id']];

// Tarihe göre filtreleme
if (!empty($filter_month)) {
    $topics_query .= " AND t.date LIKE ?";
    $params[] = $filter_month . '%';
}

// Sınıf filtrelemesi
if ($filter_classroom > 0) {
    $topics_query .= " AND c.id = ?";
    $params[] = $filter_classroom;
}

// Duruma göre filtreleme
if (!empty($filter_status)) {
    $topics_query .= " AND t.status = ?";
    $params[] = $filter_status;
}

$topics_query .= " ORDER BY t.date DESC, l.start_time DESC";
$topics = safeQuery($topics_query, $params)->fetchAll();

$page_title = 'İşlenen Konular - ' . $current_period['name'];
$datatable = true;
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>İşlenen Konular
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Yeni Konu Ekle
    </a>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label for="month" class="form-label">Ay</label>
                <input type="month" class="form-control" id="month" name="month" value="<?php echo $filter_month; ?>">
            </div>
            <div class="col-md-3">
                <label for="classroom" class="form-label">Sınıf</label>
                <select class="form-select" id="classroom" name="classroom">
                    <option value="0">Tüm Sınıflar</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?php echo $classroom['id']; ?>" <?php echo $filter_classroom == $classroom['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($classroom['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Durum</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tümü</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                    <option value="planned" <?php echo $filter_status === 'planned' ? 'selected' : ''; ?>>Planlandı</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filtrele
                </button>
                <a href="index.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Konular Tablosu -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Konu Listesi</h5>
    </div>
    <div class="card-body">
        <?php if (empty($topics)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Seçilen kriterlere uygun konu bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover datatable">
                    <thead>
                        <tr>
                            <th width="10%">Tarih</th>
                            <th width="10%">Gün/Saat</th>
                            <th width="15%">Sınıf</th>
                            <th width="25%">Konu</th>
                            <th width="25%">Açıklama</th>
                            <th width="5%">Durum</th>
                            <th width="10%">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topics as $topic): ?>
                            <tr>
                                <td><?php echo formatDate($topic['date']); ?></td>
                                <td>
                                    <?php echo $topic['day']; ?><br>
                                    <small><?php echo substr($topic['start_time'], 0, 5) . ' - ' . substr($topic['end_time'], 0, 5); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($topic['classroom_name']); ?></td>
                                <td><?php echo htmlspecialchars($topic['topic_title']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($topic['description'] ?? '')); ?></td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'completed' => 'success',
                                        'planned' => 'info',
                                        'cancelled' => 'danger'
                                    ];
                                    $status_labels = [
                                        'completed' => 'Tamamlandı',
                                        'planned' => 'Planlandı',
                                        'cancelled' => 'İptal Edildi'
                                    ];
                                    $badge_class = $status_badges[$topic['status']] ?? 'secondary';
                                    $label = $status_labels[$topic['status']] ?? $topic['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $label; ?></span>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $topic['id']; ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu konuyu silmek istediğinizden emin misiniz?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Otomatik form submit
        document.getElementById('month').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('classroom').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>