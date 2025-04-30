<?php
// modules/parents/parent-password.php - Veli şifre yönetimi
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$parent_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$parent_id) {
    setAlert('Geçersiz veli ID!', 'danger');
    redirect('modules/students/index.php');
}

// Veli bilgisini getir
$query = "SELECT * FROM parents WHERE id = ?";
$parent = safeQuery($query, [$parent_id])->fetch();

if (!$parent) {
    setAlert('Veli bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Şifre sıfırlama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        // Yeni şifre oluştur
        $new_password = generatePassword();
        $hashed_password = Auth::hashPassword($new_password);

        // Veritabanında güncelle
        $update_sql = "UPDATE parents SET password = ? WHERE id = ?";
        safeQuery($update_sql, [$hashed_password, $parent_id]);

        setAlert('Veli şifresi başarıyla sıfırlandı. Yeni şifre: ' . $new_password, 'success');
        redirect('modules/students/view.php?id=' . $_GET['student_id']);
    }
}

$page_title = 'Veli Şifre Yönetimi - ' . $parent['first_name'] . ' ' . $parent['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Veli Şifre Yönetimi</h2>
    <a href="view.php?id=<?php echo $_GET['student_id']; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <h5>Veli Bilgileri</h5>
        <table class="table">
            <tr>
                <th width="30%">Ad Soyad:</th>
                <td><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></td>
            </tr>
            <tr>
                <th>E-posta:</th>
                <td><?php echo htmlspecialchars($parent['email']); ?></td>
            </tr>
            <tr>
                <th>Telefon:</th>
                <td><?php echo formatPhone($parent['phone']); ?></td>
            </tr>
        </table>

        <hr>

        <h5>Şifre İşlemleri</h5>

        <form method="POST" action="">
            <div class="mb-3">
                <button type="submit" name="reset_password" class="btn btn-warning"
                    onclick="return confirm('Veli şifresini sıfırlamak istediğinizden emin misiniz?');">
                    <i class="bi bi-key"></i> Şifreyi Sıfırla
                </button>
            </div>
        </form>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Şifreyi sıfırlarsanız, sistem yeni bir şifre oluşturacak ve size gösterecek.
            Bu şifreyi veliye iletmelisiniz.
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>