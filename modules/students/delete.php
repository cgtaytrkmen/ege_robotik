<?php
// modules/students/delete.php - Öğrenci silme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/students/index.php');
}

// Öğrenci bilgisini getir
$query = "SELECT s.*, p.first_name as parent_first_name, p.last_name as parent_last_name
          FROM students s
          LEFT JOIN parents p ON s.parent_id = p.id
          WHERE s.id = ?";
$student = safeQuery($query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // İlgili verileri kontrol et
        $check_payments = safeQuery("SELECT COUNT(*) as count FROM payments WHERE student_id = ?", [$student_id])->fetch();
        $check_attendance = safeQuery("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?", [$student_id])->fetch();

        if ($check_payments['count'] > 0 || $check_attendance['count'] > 0) {
            // Öğrenciyi pasif yap
            $update_sql = "UPDATE students SET status = 'passive' WHERE id = ?";
            safeQuery($update_sql, [$student_id]);

            // Dönem kayıtlarını da pasif yap
            $update_periods_sql = "UPDATE student_periods SET status = 'passive' WHERE student_id = ?";
            safeQuery($update_periods_sql, [$student_id]);

            setAlert('Öğrenci pasif duruma alındı. (Ödeme veya yoklama kayıtları bulunduğu için silinemedi)', 'warning');
        } else {
            // Öğrenciyi sil
            $delete_student_periods = "DELETE FROM student_periods WHERE student_id = ?";
            safeQuery($delete_student_periods, [$student_id]);

            $delete_student = "DELETE FROM students WHERE id = ?";
            safeQuery($delete_student, [$student_id]);

            setAlert('Öğrenci başarıyla silindi!', 'success');
        }

        redirect('modules/students/index.php');
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
        redirect('modules/students/view.php?id=' . $student_id);
    }
}

$page_title = 'Öğrenci Sil - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h4 class="card-title mb-0">Öğrenci Silme Onayı</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h5 class="alert-heading">Dikkat!</h5>
                    <p>Aşağıdaki öğrenciyi silmek üzeresiniz:</p>
                    <hr>
                    <p class="mb-0">
                        <strong>Öğrenci:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?><br>
                        <strong>TC No:</strong> <?php echo htmlspecialchars($student['tcno']); ?><br>
                        <strong>Veli:</strong> <?php echo htmlspecialchars($student['parent_first_name'] . ' ' . $student['parent_last_name']); ?>
                    </p>
                </div>

                <div class="alert alert-info">
                    <p class="mb-0">
                        <i class="bi bi-info-circle"></i> Not: Eğer öğrencinin ödeme veya yoklama kayıtları varsa, öğrenci silinmez ancak "pasif" duruma alınır.
                    </p>
                </div>

                <form method="POST" action="">
                    <div class="text-center">
                        <button type="submit" class="btn btn-danger me-2">
                            <i class="bi bi-trash"></i> Evet, Öğrenciyi Sil
                        </button>
                        <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>