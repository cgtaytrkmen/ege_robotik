<?php
// modules/periods/view.php - Dönem detay sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

$id = $_GET['id'] ?? 0;

// Dönem bilgilerini getir
$query = "SELECT * FROM periods WHERE id = ?";
$stmt = safeQuery($query, [$id]);
$period = $stmt->fetch();

if (!$period) {
    setAlert('Dönem bulunamadı!', 'danger');
    redirect('modules/periods/index.php');
}

// Dönemdeki öğrenci sayısını getir
$student_query = "SELECT COUNT(*) as count 
                  FROM student_periods 
                  WHERE period_id = ? AND status = 'active'";
$student_count = safeQuery($student_query, [$id])->fetch()['count'];

// Döneme ait gelir toplamı
$income_query = "SELECT SUM(amount) as total 
                 FROM payments 
                 WHERE period_id = ? AND status = 'paid'";
$total_income = safeQuery($income_query, [$id])->fetch()['total'] ?? 0;

// Dönemdeki ders sayısı
$lesson_query = "SELECT COUNT(*) as count 
                 FROM lessons 
                 WHERE period_id = ?";
$lesson_count = safeQuery($lesson_query, [$id])->fetch()['count'];

$page_title = 'Dönem Detayı';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dönem Detayı: <?php echo htmlspecialchars($period['name']); ?></h2>
    <div>
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
            <i class="bi bi-pencil"></i> Düzenle
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Dönem Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="30%">Dönem Adı:</th>
                        <td><?php echo htmlspecialchars($period['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Dönem Türü:</th>
                        <td>
                            <?php
                            $type_labels = [
                                'fall' => 'Güz Dönemi',
                                'summer' => 'Yaz Dönemi'
                            ];
                            echo $type_labels[$period['type']] ?? $period['type'];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Başlangıç Tarihi:</th>
                        <td><?php echo formatDate($period['start_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Bitiş Tarihi:</th>
                        <td><?php echo formatDate($period['end_date']); ?></td>
                    </tr>
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($period['status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Dönem İstatistikleri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Öğrenci Sayısı</h5>
                                <h2 class="card-text"><?php echo $student_count; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Toplam Gelir</h5>
                                <h2 class="card-text"><?php echo formatMoney($total_income); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Ders Sayısı</h5>
                                <h2 class="card-text"><?php echo $lesson_count; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Dönem İşlemleri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <a href="../students/?period_id=<?php echo $id; ?>" class="btn btn-outline-primary btn-lg w-100 mb-3">
                    <i class="bi bi-people"></i> Öğrenci Listesi
                </a>
            </div>
            <div class="col-md-4">
                <a href="../finance/income.php?period_id=<?php echo $id; ?>" class="btn btn-outline-success btn-lg w-100 mb-3">
                    <i class="bi bi-cash-stack"></i> Gelir Raporu
                </a>
            </div>
            <div class="col-md-4">
                <a href="../schedule/?period_id=<?php echo $id; ?>" class="btn btn-outline-info btn-lg w-100 mb-3">
                    <i class="bi bi-calendar3"></i> Ders Programı
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>