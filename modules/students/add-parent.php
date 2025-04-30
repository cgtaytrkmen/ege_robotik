<?php
// modules/students/add-parent.php - Öğrenciye veli ekleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// ID kontrolü
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/students/index.php');
}

// Öğrenci bilgisini getir
$query = "SELECT * FROM students WHERE id = ?";
$student = safeQuery($query, [$student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Mevcut velileri getir (bu öğrencinin velisi olmayanlar)
$parent_query = "SELECT p.* FROM parents p 
                 WHERE p.id NOT IN (SELECT parent_id FROM student_parents WHERE student_id = ?)
                 ORDER BY p.first_name, p.last_name";
$parents = safeQuery($parent_query, [$student_id])->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $is_new_parent = !isset($_POST['existing_parent']) || $_POST['existing_parent'] === 'new';

        if ($is_new_parent) {
            // Yeni veli kaydı
            $generated_password = generatePassword();
            $parent_data = [
                'first_name' => clean($_POST['parent_first_name']),
                'last_name' => clean($_POST['parent_last_name']),
                'tcno' => clean($_POST['parent_tcno']),
                'phone' => clean($_POST['parent_phone']),
                'email' => clean($_POST['parent_email']),
                'address' => clean($_POST['parent_address']),
                'relationship' => clean($_POST['relationship']),
                'password' => Auth::hashPassword($generated_password),
            ];

            $sql = "INSERT INTO parents (first_name, last_name, tcno, phone, email, address, relationship, password) 
                    VALUES (:first_name, :last_name, :tcno, :phone, :email, :address, :relationship, :password)";
            safeQuery($sql, $parent_data);
            $parent_id = db()->lastInsertId();

            // Şifreyi oturuma kaydet
            $_SESSION['last_parent_password'] = $generated_password;
        } else {
            // Mevcut veli seçildi
            $parent_id = clean($_POST['parent_id']);
        }

        // Öğrenci-veli ilişkisini oluştur
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;

        // Eğer bu ana veli olacaksa, diğer ana veliyi normal veliye çevir
        if ($is_primary) {
            $update_sql = "UPDATE student_parents SET is_primary = 0 WHERE student_id = ?";
            safeQuery($update_sql, [$student_id]);
        }

        $relation_data = [
            'student_id' => $student_id,
            'parent_id' => $parent_id,
            'is_primary' => $is_primary
        ];

        $sql = "INSERT INTO student_parents (student_id, parent_id, is_primary) VALUES (:student_id, :parent_id, :is_primary)";
        safeQuery($sql, $relation_data);

        if ($is_new_parent && isset($_SESSION['last_parent_password'])) {
            setAlert('Veli başarıyla eklendi! <br>Veli giriş bilgileri: <br>E-posta: ' . $parent_data['email'] . '<br>Şifre: ' . $_SESSION['last_parent_password'], 'success');
            unset($_SESSION['last_parent_password']);
        } else {
            setAlert('Veli başarıyla eklendi!', 'success');
        }

        redirect('modules/students/view.php?id=' . $student_id);
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Veli Ekle - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Veli Ekle
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <!-- Veli Seçimi -->
            <div class="alert alert-info mb-4">
                <h5 class="alert-heading">Veli Seçimi</h5>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="existing_parent" id="new_parent" value="new" checked>
                    <label class="form-check-label" for="new_parent">
                        Yeni Veli Bilgisi Gir
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="existing_parent" id="existing_parent" value="existing">
                    <label class="form-check-label" for="existing_parent">
                        Mevcut Veli Seç
                    </label>
                </div>

                <div id="existing_parent_section" class="mt-3" style="display: none;">
                    <select class="form-select" name="parent_id" id="parent_id">
                        <option value="">Veli Seçiniz...</option>
                        <?php foreach ($parents as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>">
                                <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>
                                (<?php echo htmlspecialchars($parent['phone']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Ana Veli Seçeneği -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary">
                    <label class="form-check-label" for="is_primary">
                        Bu veliyi ana veli olarak ayarla
                    </label>
                </div>
            </div>

            <!-- Yeni Veli Bilgileri -->
            <div id="parent_info_section">
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="mb-3">Veli Bilgileri</h5>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_first_name" class="form-label">Veli Adı</label>
                        <input type="text" class="form-control" id="parent_first_name" name="parent_first_name" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_last_name" class="form-label">Veli Soyadı</label>
                        <input type="text" class="form-control" id="parent_last_name" name="parent_last_name" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_tcno" class="form-label">Veli TC Kimlik No</label>
                        <input type="text" class="form-control" id="parent_tcno" name="parent_tcno" maxlength="11">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="relationship" class="form-label">Yakınlık Derecesi</label>
                        <select class="form-select" id="relationship" name="relationship">
                            <option value="anne">Anne</option>
                            <option value="baba">Baba</option>
                            <option value="vasi">Vasi</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_phone" class="form-label">Veli Telefon</label>
                        <input type="tel" class="form-control" id="parent_phone" name="parent_phone" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_email" class="form-label">Veli E-posta</label>
                        <input type="email" class="form-control" id="parent_email" name="parent_email" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_address" class="form-label">Adres</label>
                        <textarea class="form-control" id="parent_address" name="parent_address" rows="1"></textarea>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Kaydet
                    </button>
                    <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mevcut veli seçimi
        const existingParentRadio = document.getElementById('existing_parent');
        const newParentRadio = document.getElementById('new_parent');
        const existingParentSection = document.getElementById('existing_parent_section');
        const parentInfoSection = document.getElementById('parent_info_section');

        existingParentRadio.addEventListener('change', function() {
            if (this.checked) {
                existingParentSection.style.display = 'block';
                parentInfoSection.style.display = 'none';
                // Veli alanlarını zorunlu olmaktan çıkar
                document.querySelectorAll('#parent_info_section input[required]').forEach(input => {
                    input.removeAttribute('required');
                });
                document.getElementById('parent_id').setAttribute('required', 'required');
            }
        });

        newParentRadio.addEventListener('change', function() {
            if (this.checked) {
                existingParentSection.style.display = 'none';
                parentInfoSection.style.display = 'block';
                // Veli alanlarını zorunlu yap
                document.getElementById('parent_first_name').setAttribute('required', 'required');
                document.getElementById('parent_last_name').setAttribute('required', 'required');
                document.getElementById('parent_phone').setAttribute('required', 'required');
                document.getElementById('parent_email').setAttribute('required', 'required');
                document.getElementById('parent_id').removeAttribute('required');
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>