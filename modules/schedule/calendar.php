<?php
// modules/schedule/calendar.php - Takvim görünümü
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Aktif sınıfları getir
$classrooms_query = "SELECT id, name, age_group FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Ders programını getir (sonra JSON'a dönüştürülecek)
$schedule_query = "SELECT l.*, c.name as classroom_name, c.age_group 
                  FROM lessons l
                  JOIN classrooms c ON l.classroom_id = c.id
                  WHERE l.period_id = ? AND l.status IN ('active', 'postponed')
                  ORDER BY l.day, l.start_time";
$lessons = safeQuery($schedule_query, [$current_period['id']])->fetchAll();

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

$page_title = 'Takvim Görünümü - ' . $current_period['name'];
$calendar = true; // FullCalendar CDN'ini dahil etmek için
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Takvim Görünümü
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add-lesson.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Ders Ekle
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Listeye Dön
        </a>
    </div>
</div>

<!-- Hızlı Sınıf Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <label for="classroom-filter" class="form-label">Sınıf Filtresi:</label>
                <select id="classroom-filter" class="form-select">
                    <option value="all">Tüm Sınıflar</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?php echo $classroom['id']; ?>">
                            <?php echo htmlspecialchars($classroom['name']); ?> (<?php echo htmlspecialchars($classroom['age_group']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="view-type" class="form-label">Görünüm:</label>
                <select id="view-type" class="form-select">
                    <option value="timeGridWeek">Haftalık</option>
                    <option value="dayGridMonth">Aylık</option>
                    <option value="timeGridDay">Günlük</option>
                    <option value="listWeek">Liste</option>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Ders Detayı Modalı -->
<div class="modal fade" id="lessonModal" tabindex="-1" aria-labelledby="lessonModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lessonModalLabel">Ders Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="lessonModalBody">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-primary" id="editLessonBtn">
                    <i class="bi bi-pencil"></i> Düzenle
                </a>
                <a href="#" class="btn btn-success" id="attendanceBtn">
                    <i class="bi bi-check2-square"></i> Yoklama
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // FullCalendar'ı başlat
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            locale: 'tr',
            firstDay: 1, // Pazartesi
            slotMinTime: '08:00:00',
            slotMaxTime: '22:00:00',
            allDaySlot: false,
            height: 'auto',
            navLinks: true,
            selectable: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            events: [
                <?php
                $current_date = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
                $day_map = [
                    'Monday' => 1,
                    'Tuesday' => 2,
                    'Wednesday' => 3,
                    'Thursday' => 4,
                    'Friday' => 5,
                    'Saturday' => 6,
                    'Sunday' => 0
                ];

                foreach ($lessons as $lesson):
                    // Dersin yapıldığı haftanın günü
                    $day_num = $day_map[$lesson['day']];

                    // O güne ilerlemek için fark hesapla (pazartesi 1, pazar 0)
                    $current_day_num = (int)$current_date->format('w'); // 0 (pazar) - 6 (cumartesi)
                    if ($current_day_num == 0) $current_day_num = 7; // Pazar'ı 7 olarak ele alalım

                    $diff = $day_num - $current_day_num;

                    // Hedef güne gidecek tarihi hesapla
                    $target_date = clone $current_date;
                    $target_date->modify($diff . ' day');

                    // Tarih ve saat bilgisini oluştur
                    $start_time = $target_date->format('Y-m-d') . 'T' . $lesson['start_time'];
                    $end_time = $target_date->format('Y-m-d') . 'T' . $lesson['end_time'];

                    // Durum rengini belirle
                    $color = '#3788d8'; // varsayılan mavi
                    $title_prefix = '';

                    if ($lesson['status'] == 'cancelled') {
                        $color = '#dc3545'; // kırmızı
                        $title_prefix = '[İPTAL] ';
                    } else if ($lesson['status'] == 'postponed') {
                        $color = '#fd7e14'; // turuncu
                        $title_prefix = '[ERTELENDİ] ';
                    }

                ?> {
                        id: '<?php echo $lesson['id']; ?>',
                        title: '<?php echo $title_prefix . htmlspecialchars($lesson['classroom_name']); ?>',
                        start: '<?php echo $start_time; ?>',
                        end: '<?php echo $end_time; ?>',
                        classroomId: '<?php echo $lesson['classroom_id']; ?>',
                        color: '<?php echo $color; ?>',
                        extendedProps: {
                            classroom: '<?php echo htmlspecialchars($lesson['classroom_name']); ?>',
                            ageGroup: '<?php echo htmlspecialchars($lesson['age_group']); ?>',
                            day: '<?php echo htmlspecialchars($days_tr[$lesson['day']]); ?>',
                            notes: '<?php echo htmlspecialchars($lesson['notes']); ?>'
                        }
                    },
                <?php endforeach; ?>
            ],
            eventClick: function(info) {
                // Modal'ı aç ve ders bilgilerini göster
                var lesson = info.event;
                var lessonId = lesson.id;
                var modal = new bootstrap.Modal(document.getElementById('lessonModal'));

                // Modal içeriğini güncelle
                document.getElementById('lessonModalLabel').textContent = lesson.title;

                var modalBody = document.getElementById('lessonModalBody');
                modalBody.innerHTML = `
                <table class="table table-bordered">
                    <tr>
                        <th>Sınıf:</th>
                        <td>${lesson.extendedProps.classroom}</td>
                    </tr>
                    <tr>
                        <th>Yaş Grubu:</th>
                        <td>${lesson.extendedProps.ageGroup}</td>
                    </tr>
                    <tr>
                        <th>Gün:</th>
                        <td>${lesson.extendedProps.day}</td>
                    </tr>
                    <tr>
                        <th>Saat:</th>
                        <td>${lesson.start.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'})} - ${lesson.end.toLocaleTimeString('tr-TR', {hour: '2-digit', minute:'2-digit'})}</td>
                    </tr>
                    <tr>
                        <th>Notlar:</th>
                        <td>${lesson.extendedProps.notes || '-'}</td>
                    </tr>
                </table>
            `;

                // Butonları güncelle
                document.getElementById('editLessonBtn').href = 'edit-lesson.php?id=' + lessonId;
                document.getElementById('attendanceBtn').href = '../attendance/take.php?lesson_id=' + lessonId;

                modal.show();

                return false; // Olayı durdur
            },
            dateClick: function(info) {
                // Tıklanan tarihe ders ekleme sayfasına yönlendir
                var clickedDate = info.date;
                var dayOfWeek = clickedDate.getDay(); // 0: Pazar, 1: Pazartesi, ...

                // Gün adını belirle
                var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var day = days[dayOfWeek];

                // Ders ekleme sayfasına yönlendir
                window.location.href = 'add-lesson.php?day=' + day;
            }
        });

        calendar.render();

        // Görünüm değiştirme
        document.getElementById('view-type').addEventListener('change', function() {
            calendar.changeView(this.value);
        });

        // Sınıf filtreleme
        document.getElementById('classroom-filter').addEventListener('change', function() {
            var classroomId = this.value;

            if (classroomId === 'all') {
                // Tüm etkinlikleri göster
                calendar.getEvents().forEach(function(event) {
                    event.setProp('display', 'auto');
                });
            } else {
                // Seçilen sınıfın derslerini göster, diğerlerini gizle
                calendar.getEvents().forEach(function(event) {
                    if (event.extendedProps.classroomId === classroomId) {
                        event.setProp('display', 'auto');
                    } else {
                        event.setProp('display', 'none');
                    }
                });
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>