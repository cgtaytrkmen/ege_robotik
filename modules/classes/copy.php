<?php
// modules/classes/copy.php - Sınıf kopyalama
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$classroom_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$classroom_id) {
    setAlert('Geçersiz sınıf ID!', 'danger');
    redirect('modules/classes/index.php');
}

// Sınıf bilgilerini getir
$query = "SELECT * FROM classrooms WHERE id = ?";
$classroom = safeQuery($query, [$classroom_id])->fetch();

if (!$classroom) {
    setAlert('Sınıf bulunamadı!', 'danger');
    redirect('modules/classes/index.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db()->beginTransaction();

        $new_name = clean($_POST['new_name']);
        $copy_schedule = isset($_POST['copy_schedule']) ? 1 : 0;
        $copy_students = isset($_POST['copy_students']) ? 1 : 0;

        // Aynı isimde sınıf var mı kontrol et
        $check_name = "SELECT id FROM classrooms WHERE name = ? AND status = 'active'";
        $check_stmt = safeQuery($check_name, [$new_name]);

        if ($check_stmt && $check_stmt->rowCount() > 0) {
            throw new Exception('Bu isimde aktif bir sınıf zaten var!');
        }

        // Yeni sınıfı oluştur
        $new_classroom_data = [
            'name' => $new_name,
            'capacity' => $classroom['capacity'],
            'age_group' => $classroom['age_group'],
            'description' => $classroom['description'],
            'status' => 'active'
        ];

        $insert_sql = "INSERT INTO classrooms (name, capacity, age_group, description, status) 
                       VALUES (:name, :capacity, :age_group, :description, :status)";
        safeQuery($insert_sql, $new_classroom_data);

        $new_classroom_id = db()->lastInsertId();

        // Ders programını kopyala
        if ($copy_schedule) {
            $lessons_query = "SELECT * FROM lessons WHERE classroom_id = ? AND period_id = ? AND status = 'active'";
            $lessons = safeQuery($lessons_query, [$classroom_id, $current_period['id']])->fetchAll();

            foreach ($lessons as $lesson) {
                $new_lesson_data = [
                    'period_id' => $lesson['period_id'],
                    'classroom_id' => $new_classroom_id,
                    'day' => $lesson['day'],
                    'start_time' => $lesson['start_time'],
                    'end_time' => $lesson['end_time'],
                    'status' => 'active'
                ];

                $insert_lesson_sql = "INSERT INTO lessons (period_id, classroom_id, day, start_time, end_time, status) 
                                     VALUES (:period_id, :classroom_id, :day, :start_time, :end_time, :status)";
                safeQuery($insert_lesson_sql, $new_lesson_data);
            }
        }

        // Öğrencileri kopyala
        if ($copy_students) {
            // student_classrooms tablosunun varlığını kontrol et
            $table_check = db()->query("SHOW TABLES LIKE 'student_classrooms'")->fetch();

            if ($table_check) {
                $students_query = "SELECT student_id, enrollment_date FROM student_classrooms 
                                  WHERE classroom_id = ? AND status = 'active'";
                $students = safeQuery($students_query, [$classroom_id])->fetchAll();

                foreach ($students as $student) {
                    $new_relation_data = [
                        'student_id' => $student['student_id'],
                        'classroom_id' => $new_classroom_id,
                        'enrollment_date' => date('Y-m-d'),
                        'status' => 'active'
                    ];

                    $insert_relation_sql = "INSERT INTO student_classrooms (student_id, classroom_id, enrollment_date, status) 
                                          VALUES (:student_id, :classroom_id, :enrollment_date, :status)";
                    safeQuery($insert_relation_sql, $new_relation_data);
                }
            }
        }

        db()->commit();

        setAlert('Sınıf başarıyla kopyalandı!', 'success');
        redirect('modules/classes/view.php?id=' . $new_classroom_id);
    } catch (Exception $e) {
        db()->rollBack();
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Sınıf Kopyala - ' . $classroom['name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sınıf Kopyala: <?php echo htmlspecialchars($classroom['name']); ?>
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="view.php?id=<?php echo $classroom_id; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="new_name" class="form-label">Yeni Sınıf Adı *</label>
                    <input type="text" class="form-control" id="new_name" name="new_name"
                        value="<?php echo htmlspecialchars($classroom['name']); ?> - Kopya" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="copy_schedule" name="copy_schedule" checked>
                        <label class="form-check-label" for="copy_schedule">
                            Ders Programını da Kopyala
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="copy_students" name="copy_students">
                        <label class="form-check-label" for="copy_students">
                            Öğrencileri de Kopyala
                        </label>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Bilgi</h5>
                <p>Bu işlem seçilen sınıfın bir kopyasını oluşturacaktır. Yeni oluşturulacak sınıf için farklı bir isim girmelisiniz.</p>
                <p class="mb-0">Ders programını kopyalarsanız, seçilen sınıfın mevcut ders saatleri yeni sınıfa da uygulanacaktır.</p>
                <p class="mb-0">Öğrencileri kopyalarsanız, seçilen sınıftaki öğrenciler yeni sınıfa da atanacaktır. Bu durumda öğrenciler her iki sınıfta da kayıtlı olacaktır.</p>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-files"></i> Sınıfı Kopyala
                </button>
                <a href="view.php?id=<?php echo $classroom_id; ?>" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>