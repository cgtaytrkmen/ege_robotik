<?php
// modules/periods/index.php - Dönem yönetimi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Tüm dönemleri getir
$periods = getAllPeriods();

$page_title = 'Dönem Yönetimi';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Dönem Yönetimi</h2>
    <a href="add.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Yeni Dönem
    </a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Dönem Adı</th>
                        <th>Tür</th>
                        <th>Başlangıç</th>
                        <th>Bitiş</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $period): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($period['name']); ?></td>
                            <td>
                                <?php
                                $type_labels = [
                                    'fall' => 'Güz Dönemi',
                                    'summer' => 'Yaz Dönemi'
                                ];
                                echo $type_labels[$period['type']] ?? $period['type'];
                                ?>
                            </td>
                            <td><?php echo formatDate($period['start_date']); ?></td>
                            <td><?php echo formatDate($period['end_date']); ?></td>
                            <td>
                                <?php if ($period['status'] == 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit.php?id=<?php echo $period['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($period['status'] != 'active'): ?>
                                    <a href="activate.php?id=<?php echo $period['id']; ?>" class="btn btn-sm btn-success" title="Aktifleştir">
                                        <i class="bi bi-check-circle"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="view.php?id=<?php echo $period['id']; ?>" class="btn btn-sm btn-info" title="Detaylar">
                                    <i class="bi bi-eye"></i>
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