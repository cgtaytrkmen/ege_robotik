<?php
// modules/topics/delete.php - Konu silme sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Konu ID kontrolü
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$topic_id) {
    setAlert('Geçersiz konu ID!', 'danger');
    redirect('modules/topics/index.php');
}

// Konu bilgisini kontrol et
$topic_query = "SELECT t.*, l.day, l.period_id FROM topics t JOIN lessons l ON t.lesson_id = l.id WHERE t.id = ?";
$topic = safeQuery($topic_query, [$topic_id])->fetch();

if (!$topic) {
    setAlert('Konu bulunamadı!', 'danger');
    redirect('modules/topics/index.php');
}

// Dönem kontrolü
$current_period = getCurrentPeriod();
if ($topic['period_id'] != $current_period['id']) {
    setAlert('Bu konu farklı bir döneme ait olduğu için silinemez!', 'warning');
    redirect('modules/topics/index.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Konuyu sil
        $delete_query = "DELETE FROM topics WHERE id = ?";
        $result = safeQuery($delete_query, [$topic_id]);

        if ($result) {
            setAlert('Konu başarıyla silindi!', 'success');
            redirect('modules/topics/index.php');
        } else {
            throw new Exception('Konu silinirken bir hata oluştu!');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Konu Sil';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Konu Sil</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="card-title mb-0">Uyarı!</h5>
    </div>
    <div class="card-body">
        <p class="mb-0">
            <strong><?php echo htmlspecialchars($topic['topic_title']); ?></strong> başlıklı konuyu silmek istediğinize emin misiniz?
        </p>
        <p class="text-danger mt-3">Bu işlem geri alınamaz!</p>

        <div class="mt-4">
            <form method="POST" action="">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Evet, Konuyu Sil
                </button>
                <a href="index.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-x"></i> İptal
                </a>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>