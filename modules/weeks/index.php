<?php
// modules/weeks/index.php - Hafta yönetimi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Hafta oluşturma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_weeks'])) {
    try {
        db()->beginTransaction();

        // Mevcut hafta verileri silinecek mi?
        $clear_existing = isset($_POST['clear_existing']) ? 1 : 0;

        // Eğer mevcut haftaları silme seçeneği seçildiyse
        if ($clear_existing) {
            $delete_sql = "DELETE FROM period_weeks WHERE period_id = ?";
            safeQuery($delete_sql, [$current_period['id']]);
        }

        // Dönem başlangıç ve bitiş tarihleri
        $start_date = new DateTime($current_period['start_date']);
        $end_date = new DateTime($current_period['end_date']);

        // Ücretsiz ders haftası için başlangıç tarihi
        $free_week_start = isset($_POST['free_week_start']) ? new DateTime($_POST['free_week_start']) : null;
        $free_week_end = null;

        if ($free_week_start) {
            $free_week_end = clone $free_week_start;
            $free_week_end->modify('+6 days'); // 1 hafta (7 gün - 1)
        }

        // Haftalık döngü
        $current_date = clone $start_date;
        $week_number = 1;

        while ($current_date <= $end_date) {
            $week_start = clone $current_date;
            $week_end = clone $current_date;
            $week_end->modify('+6 days'); // 1 hafta (7 gün - 1)

            // Eğer hafta sonu dönem bitiş tarihini geçiyorsa, düzelt
            if ($week_end > $end_date) {
                $week_end = clone $end_date;
            }

            // Hafta ismi
            $week_name = $week_number . '. Hafta';
            $is_free = 0;

            // Ücretsiz ders haftası kontrolü
            if ($free_week_start && $week_start == $free_week_start) {
                $week_name = 'Ücretsiz Ders Haftası';
                $is_free = 1;
            }

            // Mevcut hafta kontrolü
            $check_sql = "SELECT id FROM period_weeks WHERE period_id = ? AND 
                          ((start_date <= ? AND end_date >= ?) OR 
                           (start_date <= ? AND end_date >= ?) OR
                           (start_date >= ? AND end_date <= ?))";
            $check_params = [
                $current_period['id'],
                $week_start->format('Y-m-d'),
                $week_start->format('Y-m-d'),
                $week_end->format('Y-m-d'),
                $week_end->format('Y-m-d'),
                $week_start->format('Y-m-d'),
                $week_end->format('Y-m-d')
            ];

            $existing_week = safeQuery($check_sql, $check_params)->fetch();

            // Eğer çakışan hafta yoksa ekle
            if (!$existing_week) {
                $insert_sql = "INSERT INTO period_weeks (period_id, week_number, name, start_date, end_date, is_free) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $insert_params = [
                    $current_period['id'],
                    $week_number,
                    $week_name,
                    $week_start->format('Y-m-d'),
                    $week_end->format('Y-m-d'),
                    $is_free
                ];

                safeQuery($insert_sql, $insert_params);

                // Eğer bu ücretsiz hafta değilse, hafta numarasını artır
                if (!$is_free) {
                    $week_number++;
                }
            }

            // Bir sonraki haftaya geç
            $current_date->modify('+7 days');
        }

        db()->commit();
        setAlert('Hafta yapısı başarıyla oluşturuldu!', 'success');
        redirect('modules/weeks/index.php');
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Hafta ekleme/güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_week'])) {
    try {
        $week_id = intval($_POST['week_id']);
        $week_name = clean($_POST['week_name']);
        $start_date = clean($_POST['start_date']);
        $end_date = clean($_POST['end_date']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $notes = clean($_POST['notes'] ?? '');

        // Geçerlilik kontrolü
        if (empty($week_name) || empty($start_date) || empty($end_date)) {
            throw new Exception('Lütfen tüm alanları doldurun!');
        }

        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception('Başlangıç tarihi bitiş tarihinden sonra olamaz!');
        }

        // Hafta numarasını hesapla - son hafta numarasını bul ve bir artır
        $week_number = 1; // Varsayılan değer
        if ($week_id == 0) { // Yeni ekleme ise
            $last_week_query = "SELECT MAX(week_number) as max_week FROM period_weeks WHERE period_id = ? AND is_free = 0";
            $last_week_result = safeQuery($last_week_query, [$current_period['id']])->fetch();
            if ($last_week_result && $last_week_result['max_week']) {
                $week_number = $last_week_result['max_week'] + 1;
            }
        }

        if ($week_id > 0) {
            // Güncelleme
            $update_sql = "UPDATE period_weeks SET name = ?, start_date = ?, end_date = ?, is_free = ?, notes = ? WHERE id = ?";
            safeQuery($update_sql, [$week_name, $start_date, $end_date, $is_free, $notes, $week_id]);
            setAlert('Hafta başarıyla güncellendi!', 'success');
        } else {
            // Yeni ekleme
            $insert_sql = "INSERT INTO period_weeks (period_id, week_number, name, start_date, end_date, is_free, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            safeQuery($insert_sql, [$current_period['id'], $week_number, $week_name, $start_date, $end_date, $is_free, $notes]);
            setAlert('Hafta başarıyla eklendi!', 'success');
        }

        redirect('modules/weeks/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Hafta silme işlemi
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    try {
        $week_id = intval($_GET['delete']);

        // İlişkili kayıtlar olup olmadığını kontrol et
        $check_attendance = "SELECT COUNT(*) as count FROM attendance WHERE period_week_id = ?";
        $attendance_count = safeQuery($check_attendance, [$week_id])->fetch()['count'];

        $check_topics = "SELECT COUNT(*) as count FROM topics WHERE period_week_id = ?";
        $topics_count = safeQuery($check_topics, [$week_id])->fetch()['count'];

        if ($attendance_count > 0 || $topics_count > 0) {
            throw new Exception('Bu haftaya ait ' . $attendance_count . ' yoklama ve ' . $topics_count . ' konu kaydı var. Önce bunları silin.');
        }

        // Silebiliyorsak sil
        $delete_sql = "DELETE FROM period_weeks WHERE id = ? AND period_id = ?";
        safeQuery($delete_sql, [$week_id, $current_period['id']]);

        setAlert('Hafta başarıyla silindi!', 'success');
        redirect('modules/weeks/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

// Hafta listesini getir
$weeks_query = "SELECT * FROM period_weeks WHERE period_id = ? ORDER BY start_date";
$weeks = safeQuery($weeks_query, [$current_period['id']])->fetchAll();

$page_title = 'Hafta Yönetimi - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Hafta Yönetimi
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWeeksModal">
            <i class="bi bi-calendar-plus"></i> Otomatik Hafta Oluştur
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWeekModal">
            <i class="bi bi-plus-circle"></i> Manuel Hafta Ekle
        </button>
    </div>
</div>

<!-- Hafta Bilgileri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Dönem Hafta Yapısı</h5>
    </div>
    <div class="card-body">
        <?php if (empty($weeks)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Bu dönem için henüz hafta yapısı oluşturulmamış.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Hafta No</th>
                            <th>Hafta Adı</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Durum</th>
                            <th>Notlar</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weeks as $week): ?>
                            <tr>
                                <td><?php echo $week['week_number']; ?></td>
                                <td><?php echo htmlspecialchars($week['name']); ?></td>
                                <td><?php echo formatDate($week['start_date']); ?></td>
                                <td><?php echo formatDate($week['end_date']); ?></td>
                                <td>
                                    <?php if ($week['is_free']): ?>
                                        <span class="badge bg-info">Ücretsiz</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($week['notes'] ?? ''); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary edit-week-btn"
                                        data-week-id="<?php echo $week['id']; ?>"
                                        data-week-name="<?php echo htmlspecialchars($week['name']); ?>"
                                        data-start-date="<?php echo $week['start_date']; ?>"
                                        data-end-date="<?php echo $week['end_date']; ?>"
                                        data-is-free="<?php echo $week['is_free']; ?>"
                                        data-notes="<?php echo htmlspecialchars($week['notes'] ?? ''); ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?delete=<?php echo $week['id']; ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Bu haftayı silmek istediğinizden emin misiniz?');">
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

<!-- Otomatik Hafta Oluşturma Modal -->
<div class="modal fade" id="createWeeksModal" tabindex="-1" aria-labelledby="createWeeksModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createWeeksModalLabel">Otomatik Hafta Oluştur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu işlem, seçilen dönem için haftalık yapıyı otomatik olarak oluşturacaktır.
                    </div>

                    <div class="mb-3">
                        <label for="free_week_start" class="form-label">Ücretsiz Ders Haftası (İsteğe Bağlı)</label>
                        <input type="date" class="form-control" id="free_week_start" name="free_week_start"
                            min="<?php echo $current_period['start_date']; ?>"
                            max="<?php echo $current_period['end_date']; ?>">
                        <small class="form-text text-muted">Ücretsiz ders haftasının başlangıç tarihini seçin (Pazartesi olması önerilir).</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="clear_existing" name="clear_existing">
                        <label class="form-check-label" for="clear_existing">Mevcut hafta yapısını temizle</label>
                        <small class="form-text text-muted d-block">Bu seçenek, mevcut tüm hafta verilerini siler ve yeniden oluşturur.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="generate_weeks" class="btn btn-primary">Haftaları Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manuel Hafta Ekleme Modal -->
<div class="modal fade" id="addWeekModal" tabindex="-1" aria-labelledby="addWeekModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWeekModalLabel">Manuel Hafta Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="week_id" id="week_id" value="0">

                    <div class="mb-3">
                        <label for="week_name" class="form-label">Hafta Adı *</label>
                        <input type="text" class="form-control" id="week_name" name="week_name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Başlangıç Tarihi *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required
                                min="<?php echo $current_period['start_date']; ?>"
                                max="<?php echo $current_period['end_date']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label">Bitiş Tarihi *</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                min="<?php echo $current_period['start_date']; ?>"
                                max="<?php echo $current_period['end_date']; ?>">
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_free" name="is_free">
                        <label class="form-check-label" for="is_free">Ücretsiz Ders Haftası</label>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notlar</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="update_week" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hafta düzenleme butonları
        const editButtons = document.querySelectorAll('.edit-week-btn');

        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const weekId = this.getAttribute('data-week-id');
                const weekName = this.getAttribute('data-week-name');
                const startDate = this.getAttribute('data-start-date');
                const endDate = this.getAttribute('data-end-date');
                const isFree = this.getAttribute('data-is-free');
                const notes = this.getAttribute('data-notes');

                // Modal alanlarını doldur
                document.getElementById('week_id').value = weekId;
                document.getElementById('week_name').value = weekName;
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
                document.getElementById('is_free').checked = isFree == '1';
                document.getElementById('notes').value = notes;

                // Modalı göster
                const modal = new bootstrap.Modal(document.getElementById('addWeekModal'));
                modal.show();
            });
        });

        // Start date değiştiğinde end date'i 6 gün sonraya ayarla
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + 6);

            // End date'i formatlayıp set et
            const year = endDate.getFullYear();
            const month = String(endDate.getMonth() + 1).padStart(2, '0');
            const day = String(endDate.getDate()).padStart(2, '0');
            document.getElementById('end_date').value = `${year}-${month}-${day}`;
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>