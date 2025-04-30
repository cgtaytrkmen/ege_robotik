<?php
// modules/schedule/index.php - Ders programı ana sayfası
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Sınıfları getir
$classrooms_query = "SELECT id, name, age_group FROM classrooms WHERE status = 'active' ORDER BY name";
$classrooms = db()->query($classrooms_query)->fetchAll();

// Haftanın belirli bir günü için ders programını getir
function getDailySchedule($day, $period_id)
{
    $query = "SELECT l.*, c.name as classroom_name, c.age_group
              FROM lessons l
              JOIN classrooms c ON l.classroom_id = c.id
              WHERE l.day = ? AND l.period_id = ? AND l.status = 'active'
              ORDER BY l.start_time, c.name";

    return safeQuery($query, [$day, $period_id])->fetchAll();
}

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

// İngilizce gün adları
$days_en = array_keys($days_tr);

$page_title = 'Ders Programı - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Ders Programı
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <div>
        <a href="add-lesson.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Yeni Ders Ekle
        </a>
        <a href="calendar.php" class="btn btn-success">
            <i class="bi bi-calendar-week"></i> Takvim Görünümü
        </a>
    </div>
</div>

<!-- Hızlı Sınıf Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <span class="me-3"><strong>Sınıfa Göre Filtrele:</strong></span>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary active filter-btn" data-filter="all">Tümü</button>
                <?php foreach ($classrooms as $classroom): ?>
                    <button type="button" class="btn btn-outline-primary filter-btn"
                        data-filter="<?php echo $classroom['id']; ?>">
                        <?php echo htmlspecialchars($classroom['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Haftalık Ders Programı -->
<div class="weekly-schedule">
    <?php foreach ($days_en as $index => $day): ?>
        <?php
        $lessons = getDailySchedule($day, $current_period['id']);
        $has_lessons = !empty($lessons);
        ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-day"></i> <?php echo $days_tr[$day]; ?>
                </h5>
                <a href="add-lesson.php?day=<?php echo $day; ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-circle"></i> Ders Ekle
                </a>
            </div>
            <div class="card-body">
                <?php if ($has_lessons): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Saat</th>
                                    <th>Sınıf</th>
                                    <th>Yaş Grubu</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lessons as $lesson): ?>
                                    <tr class="lesson-row" data-classroom="<?php echo $lesson['classroom_id']; ?>">
                                        <td>
                                            <?php
                                            echo substr($lesson['start_time'], 0, 5) . ' - ' . substr($lesson['end_time'], 0, 5);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($lesson['classroom_name']); ?></td>
                                        <td><?php echo htmlspecialchars($lesson['age_group']); ?></td>
                                        <td>
                                            <a href="edit-lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="../attendance/take.php?lesson_id=<?php echo $lesson['id']; ?>&date=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-success">
                                                <i class="bi bi-check2-square"></i> Yoklama
                                            </a>
                                            <a href="delete-lesson.php?id=<?php echo $lesson['id']; ?>"
                                                class="btn btn-sm btn-danger"
                                                onclick="return confirm('Bu dersi silmek istediğinizden emin misiniz?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Bu gün için planlanmış ders bulunmuyor.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sınıf filtreleme
        const filterButtons = document.querySelectorAll('.filter-btn');
        const lessonRows = document.querySelectorAll('.lesson-row');

        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Aktif buton stilini güncelle
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                // Dersleri filtrele
                lessonRows.forEach(row => {
                    if (filter === 'all' || row.getAttribute('data-classroom') === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>