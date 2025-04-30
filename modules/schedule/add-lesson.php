<?php
// modules/schedule/add-lesson.php - Ders ekleme (hata ayıklama)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Varsayılan gün (URL'den gelebilir)
$selected_day = $_GET['day'] ?? 'Monday';

// Türkçe gün adları
$days_tr = [
    'Monday' => 'Pazartesi',
    'Tuesday' => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday' => 'Perşembe',
    'Friday' => 'Cuma',
    'Saturday' => 'Cumartesi',
    'Sunday' => 'Pazar'
];

// Aktif sınıfları getir
$classrooms_query = "SELECT id, name, capacity, age_group FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Form verilerini al
        $classroom_id = intval($_POST['classroom_id']);
        $day = clean($_POST['day']);
        $start_time = clean($_POST['start_time']);
        $end_time = clean($_POST['end_time']);
        $notes = clean($_POST['notes'] ?? '');

        // Debug için form verilerini logla
        error_log("Form verileri: " . print_r($_POST, true));

        // Veri doğrulama
        if (empty($classroom_id) || empty($day) || empty($start_time) || empty($end_time)) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Sınıf kontrolü
        $classroom_query = "SELECT id FROM classrooms WHERE id = ? AND status = 'active'";
        $classroom = safeQuery($classroom_query, [$classroom_id])->fetch();

        if (!$classroom) {
            throw new Exception('Geçersiz sınıf seçimi!');
        }

        // Zaman formatını kontrol et
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $start_time)) {
            throw new Exception('Başlangıç saati formatı geçersiz. Lütfen HH:MM formatında girin.');
        }

        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $end_time)) {
            throw new Exception('Bitiş saati formatı geçersiz. Lütfen HH:MM formatında girin.');
        }

        // Başlangıç saati bitiş saatinden önce olmalı
        if (strtotime($start_time) >= strtotime($end_time)) {
            throw new Exception('Başlangıç saati bitiş saatinden önce olmalıdır!');
        }

        // Aynı sınıf için çakışma kontrolü
        $overlap_query = "SELECT id FROM lessons 
                         WHERE classroom_id = ? AND day = ? AND period_id = ? AND status = 'active'
                         AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?) OR (start_time >= ? AND end_time <= ?))";

        $params = [
            $classroom_id,
            $day,
            $current_period['id'],
            $start_time,
            $start_time,
            $end_time,
            $end_time,
            $start_time,
            $end_time
        ];

        $overlap_check = safeQuery($overlap_query, $params);
        if ($overlap_check === false) {
            error_log("Çakışma kontrolü SQL hatası");
            throw new Exception('Çakışma kontrolü başarısız! Veritabanı hatası.');
        }

        $overlap_result = $overlap_check->fetch();

        if ($overlap_result) {
            throw new Exception('Bu sınıf için seçilen saatlerde başka bir ders mevcut!');
        }

        // Lessons tablosunun varlığını kontrol et
        $table_check = db()->query("SHOW TABLES LIKE 'lessons'")->fetch();
        if (!$table_check) {
            error_log("Lessons tablosu bulunamadı");

            // Tablo oluştur
            $create_table_sql = "CREATE TABLE lessons (
                id INT PRIMARY KEY AUTO_INCREMENT,
                classroom_id INT NOT NULL,
                period_id INT NOT NULL,
                day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                status ENUM('active','cancelled','postponed') DEFAULT 'active',
                notes TEXT NULL,
                FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
                FOREIGN KEY (period_id) REFERENCES periods(id) ON DELETE CASCADE
            )";

            $table_created = db()->exec($create_table_sql);
            if (!$table_created) {
                error_log("Lessons tablosu oluşturulamadı: " . print_r(db()->errorInfo(), true));
                throw new Exception('Lessons tablosu bulunamadı ve oluşturulamadı!');
            }

            error_log("Lessons tablosu oluşturuldu");
        }

        // Dersi ekle
        $insert_query = "INSERT INTO lessons (classroom_id, period_id, day, start_time, end_time, notes, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'active')";

        $stmt = db()->prepare($insert_query);
        if (!$stmt) {
            error_log("SQL hazırlama hatası: " . print_r(db()->errorInfo(), true));
            throw new Exception('Ders ekleme hazırlığı başarısız!');
        }

        $insert_params = [
            $classroom_id,
            $current_period['id'],
            $day,
            $start_time,
            $end_time,
            $notes
        ];

        error_log("Ekleme sorgusu: " . $insert_query);
        error_log("Parametreler: " . print_r($insert_params, true));

        $result = $stmt->execute($insert_params);

        if (!$result) {
            error_log("SQL çalıştırma hatası: " . print_r($stmt->errorInfo(), true));
            throw new Exception('Ders ekleme işlemi başarısız! SQL Hatası: ' . implode(' - ', $stmt->errorInfo()));
        }

        $last_id = db()->lastInsertId();
        error_log("Ders başarıyla eklendi. ID: " . $last_id);

        // Başarılı mesajı
        setAlert('Ders başarıyla eklendi! (ID: ' . $last_id . ')', 'success');
        redirect('modules/schedule/index.php');
    } catch (Exception $e) {
        error_log("Hata: " . $e->getMessage());
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yeni Ders Ekle - ' . $current_period['name'];
require_once '../../includes/header.php';
?>



<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Ders Ekle
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="classroom_id" class="form-label">Sınıf *</label>
                    <select class="form-select" id="classroom_id" name="classroom_id" required>
                        <option value="">-- Sınıf Seçin --</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['id']; ?>">
                                <?php echo htmlspecialchars($classroom['name']); ?>
                                (<?php echo htmlspecialchars($classroom['age_group']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="day" class="form-label">Gün *</label>
                    <select class="form-select" id="day" name="day" required>
                        <?php foreach ($days_tr as $day_en => $day_tr): ?>
                            <option value="<?php echo $day_en; ?>" <?php echo ($day_en === $selected_day) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($day_tr); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="start_time" class="form-label">Başlangıç Saati *</label>
                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="end_time" class="form-label">Bitiş Saati *</label>
                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                </div>

                <div class="col-md-12 mb-3">
                    <label for="notes" class="form-label">Notlar</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="alert alert-info" id="schedule-info">
                <strong>Bilgi:</strong> Lütfen önce bir sınıf ve gün seçin. Mevcut ders programı burada gösterilecektir.
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Dersi Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Bitiş saati otomatik başlangıç + 1.5 saat olsun
        document.getElementById('start_time').addEventListener('change', function() {
            const startTime = this.value;
            if (startTime) {
                const [hours, minutes] = startTime.split(':').map(Number);
                let endHours = hours;
                let endMinutes = minutes + 30;

                if (endMinutes >= 60) {
                    endHours += 1;
                    endMinutes -= 60;
                }

                if (endHours >= 24) {
                    endHours -= 24;
                }

                const endTime = `${endHours.toString().padStart(2, '0')}:${endMinutes.toString().padStart(2, '0')}`;
                document.getElementById('end_time').value = endTime;
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>