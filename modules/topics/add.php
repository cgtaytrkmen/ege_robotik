<?php
// modules/topics/add.php - Yeni konu ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Tarih seçilmişse al, seçilmemişse bugünü kullan
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_lesson = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;

// O günkü dersleri getir
function getLessonsForDate($date, $period_id)
{
    // Haftanın günü
    $day_of_week = date('l', strtotime($date)); // Pazartesi, Salı, vb.

    $query = "SELECT l.*, c.name as classroom_name
              FROM lessons l
              JOIN classrooms c ON l.classroom_id = c.id
              WHERE l.day = ? AND l.period_id = ? AND l.status = 'active'
              ORDER BY l.start_time";

    return safeQuery($query, [$day_of_week, $period_id])->fetchAll();
}

$lessons = getLessonsForDate($selected_date, $current_period['id']);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Parametreleri al
        $lesson_id = isset($_POST['lesson_id']) ? intval($_POST['lesson_id']) : 0;
        $topic_title = clean($_POST['topic_title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $topic_date = clean($_POST['date'] ?? $selected_date);
        $status = clean($_POST['status'] ?? 'planned');

        // Zorunlu alanları kontrol et
        if (empty($lesson_id) || empty($topic_title) || empty($topic_date)) {
            throw new Exception('Ders, konu başlığı ve tarih alanları zorunludur.');
        }

        // Konu ekle
        $topic_data = [
            'lesson_id' => $lesson_id,
            'topic_title' => $topic_title,
            'description' => $description,
            'date' => $topic_date,
            'status' => $status
        ];

        $sql = "INSERT INTO topics (lesson_id, topic_title, description, date, status) 
                VALUES (:lesson_id, :topic_title, :description, :date, :status)";

        $result = safeQuery($sql, $topic_data);

        if ($result) {
            setAlert('Konu başarıyla eklendi!', 'success');
            redirect('modules/topics/index.php?date=' . $topic_date);
        } else {
            throw new Exception('Konu eklenirken bir hata oluştu.');
        }
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yeni Konu Ekle';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Konu Ekle</h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="date" class="form-label">Tarih</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo $selected_date; ?>" required>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="lesson_id" class="form-label">Ders</label>
                    <select class="form-select" id="lesson_id" name="lesson_id" required>
                        <option value="">Ders Seçin</option>
                        <?php if (empty($lessons)): ?>
                            <option disabled>Bu tarihte ders bulunmuyor</option>
                        <?php else: ?>
                            <?php foreach ($lessons as $lesson): ?>
                                <option value="<?php echo $lesson['id']; ?>" <?php echo $selected_lesson == $lesson['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lesson['classroom_name']) . ' - ' .
                                        substr($lesson['start_time'], 0, 5) . ' - ' .
                                        substr($lesson['end_time'], 0, 5); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="planned">Planlandı</option>
                        <option value="completed">Tamamlandı</option>
                        <option value="cancelled">İptal Edildi</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="topic_title" class="form-label">Konu Başlığı</label>
                <input type="text" class="form-control" id="topic_title" name="topic_title" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>

                <?php if (!empty($lessons)): ?>
                    <button type="button" class="btn btn-success ms-2" id="createAllButton">
                        <i class="bi bi-list-check"></i> Tüm Dersler İçin Konu Oluştur
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($lessons)): ?>
    <!-- Tüm Dersler İçin Konu Oluşturma Modalı -->
    <div class="modal fade" id="createAllModal" tabindex="-1" aria-labelledby="createAllModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createAllModalLabel">Tüm Dersler İçin Konu Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="add-multiple.php" id="createAllForm">
                        <input type="hidden" name="date" value="<?php echo $selected_date; ?>">

                        <div class="mb-3">
                            <label class="form-label">Tarih: <strong><?php echo formatDate($selected_date); ?></strong></label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-select" name="status" id="bulk_status">
                                <option value="planned">Planlandı</option>
                                <option value="completed">Tamamlandı</option>
                                <option value="cancelled">İptal Edildi</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Aynı Konuyu Tüm Derslere Uygulamak İster misiniz?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="useSameTopic" name="useSameTopic" value="1">
                                <label class="form-check-label" for="useSameTopic">
                                    Evet, tüm derslere aynı konuyu ata
                                </label>
                            </div>
                        </div>

                        <div id="sameTopicSection">
                            <div class="mb-3">
                                <label class="form-label">Konu Başlığı</label>
                                <input type="text" class="form-control" name="common_topic_title" id="common_topic_title">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Açıklama</label>
                                <textarea class="form-control" name="common_description" id="common_description" rows="3"></textarea>
                            </div>
                        </div>

                        <div id="individualTopicsSection">
                            <h5 class="mt-4">Dersler ve Konular</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Ders</th>
                                            <th>Konu Başlığı</th>
                                            <th>Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lessons as $index => $lesson): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($lesson['classroom_name']) . ' - ' .
                                                        substr($lesson['start_time'], 0, 5); ?>
                                                    <input type="hidden" name="lessons[<?php echo $index; ?>][lesson_id]" value="<?php echo $lesson['id']; ?>">
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control topic-title" name="lessons[<?php echo $index; ?>][topic_title]" required>
                                                </td>
                                                <td>
                                                    <textarea class="form-control topic-description" name="lessons[<?php echo $index; ?>][description]" rows="2"></textarea>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" id="submitAllForm">Kaydet</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tarih değiştiğinde sayfayı yenile
        document.getElementById('date').addEventListener('change', function() {
            window.location.href = 'add.php?date=' + this.value;
        });

        <?php if (!empty($lessons)): ?>
            // Tüm dersler için konu oluşturma modalı
            const createAllButton = document.getElementById('createAllButton');
            const createAllModal = new bootstrap.Modal(document.getElementById('createAllModal'));
            const useSameTopicCheckbox = document.getElementById('useSameTopic');
            const sameTopicSection = document.getElementById('sameTopicSection');
            const individualTopicsSection = document.getElementById('individualTopicsSection');
            const submitAllForm = document.getElementById('submitAllForm');
            const createAllForm = document.getElementById('createAllForm');

            // Modal açma butonu
            createAllButton.addEventListener('click', function() {
                createAllModal.show();
            });

            // Aynı konu checkbox'ı değiştiğinde
            useSameTopicCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    sameTopicSection.style.display = 'block';
                    individualTopicsSection.style.display = 'none';
                } else {
                    sameTopicSection.style.display = 'none';
                    individualTopicsSection.style.display = 'block';
                }
            });

            // Ortak konu başlığı değiştiğinde
            document.getElementById('common_topic_title').addEventListener('input', function() {
                document.querySelectorAll('.topic-title').forEach(function(input) {
                    input.value = document.getElementById('common_topic_title').value;
                });
            });

            // Ortak açıklama değiştiğinde
            document.getElementById('common_description').addEventListener('input', function() {
                document.querySelectorAll('.topic-description').forEach(function(textarea) {
                    textarea.value = document.getElementById('common_description').value;
                });
            });

            // Form gönderme butonu
            submitAllForm.addEventListener('click', function() {
                createAllForm.submit();
            });

            // Sayfa yüklendiğinde varsayılan görünüm
            sameTopicSection.style.display = 'none';
        <?php endif; ?>
    });
</script>

<?php require_once '../../includes/footer.php'; ?>