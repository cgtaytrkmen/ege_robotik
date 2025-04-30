<?php
// modules/topics/edit.php - Konu düzenleme sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Konu ID kontrolü
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$topic_id) {
    setAlert('Geçersiz konu ID!', 'danger');
    redirect('modules/topics/index.php');
}

// Konu bilgisini getir
$topic_query = "SELECT t.*, l.day, l.start_time, l.end_time, l.period_id, l.id as lesson_id, 
                c.name as classroom_name, c.id as classroom_id
                FROM topics t
                JOIN lessons l ON t.lesson_id = l.id
                JOIN classrooms c ON l.classroom_id = c.id
                WHERE t.id = ?";
$topic = safeQuery($topic_query, [$topic_id])->fetch();

if (!$topic) {
    setAlert('Konu bulunamadı!', 'danger');
    redirect('modules/topics/index.php');
}

// Bu konu farklı bir döneme ait ise uyarı ver
if ($topic['period_id'] != $current_period['id']) {
    setAlert('Bu konu farklı bir döneme ait! Düzenleme yapabilirsiniz ancak dikkatli olun.', 'warning');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Konu bilgilerini güncelle
        $topic_title = clean($_POST['topic_title'] ?? '');
        $topic_description = clean($_POST['topic_description'] ?? '');
        $topic_status = clean($_POST['topic_status'] ?? 'completed');
        $topic_date = clean($_POST['topic_date'] ?? $topic['date']);

        if (empty($topic_title)) {
            throw new Exception('Konu başlığı boş olamaz!');
        }

        $update_data = [
            'topic_title' => $topic_title,
            'description' => $topic_description,
            'status' => $topic_status,
            'date' => $topic_date,
            'id' => $topic_id
        ];

        $sql = "UPDATE topics SET topic_title = :topic_title, description = :description, 
                status = :status, date = :date WHERE id = :id";
        $result = safeQuery($sql, $update_data);

        if ($result) {
            setAlert('Konu başarıyla güncellendi!', 'success');
            redirect('modules/topics/view.php?id=' . $topic_id);
        } else {
            throw new Exception('Konu güncellenirken bir hata oluştu!');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Konu Düzenle - ' . $topic['topic_title'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Konu Düzenle
        <small class="text-muted">(<?php echo htmlspecialchars($topic['topic_title']); ?>)</small>
    </h2>
    <a href="view.php?id=<?php echo $topic_id; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Ders Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <p><strong>Sınıf:</strong> <?php echo htmlspecialchars($topic['classroom_name']); ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Gün:</strong> <?php echo $topic['day']; ?></p>
            </div>
            <div class="col-md-4">
                <p><strong>Saat:</strong> <?php echo substr($topic['start_time'], 0, 5) . ' - ' . substr($topic['end_time'], 0, 5); ?></p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Konu Bilgileri</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="topic_title" class="form-label">Konu Başlığı *</label>
                        <input type="text" class="form-control" id="topic_title" name="topic_title" value="<?php echo htmlspecialchars($topic['topic_title']); ?>" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="topic_date" class="form-label">Tarih</label>
                        <input type="date" class="form-control" id="topic_date" name="topic_date" value="<?php echo $topic['date']; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="topic_status" class="form-label">Durum</label>
                        <select class="form-select" id="topic_status" name="topic_status">
                            <option value="completed" <?php echo $topic['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                            <option value="planned" <?php echo $topic['status'] === 'planned' ? 'selected' : ''; ?>>Planlandı</option>
                            <option value="cancelled" <?php echo $topic['status'] === 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="mb-3">
                        <label for="topic_description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="topic_description" name="topic_description" rows="5"><?php echo htmlspecialchars($topic['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Değişiklikleri Kaydet
            </button>
            <a href="view.php?id=<?php echo $topic_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-x"></i> İptal
            </a>
        </div>
    </div>
</form>

<?php require_once '../../includes/footer.php'; ?>