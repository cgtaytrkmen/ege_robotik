<?php
// modules/students/add.php - Yeni öğrenci ekleme (debug ve düzeltmeler)
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// Mevcut velileri getir
$parent_query = "SELECT id, first_name, last_name, tcno, phone FROM parents ORDER BY first_name, last_name";
$parents = db()->query($parent_query)->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Debug için POST verilerini görelim
        error_log("POST verileri: " . print_r($_POST, true));

        db()->beginTransaction();

        // Mevcut veli mi yoksa yeni veli mi kontrolü
        $is_new_parent = !isset($_POST['existing_parent']) || $_POST['existing_parent'] === 'new';

        if ($is_new_parent) {
            // Yeni veli kaydı
            $generated_password = generatePassword();
            $parent_data = [
                'first_name' => clean($_POST['parent_first_name']),
                'last_name' => clean($_POST['parent_last_name']),
                'tcno' => !empty($_POST['parent_tcno']) ? clean($_POST['parent_tcno']) : null,
                'phone' => clean($_POST['parent_phone']),
                'email' => clean($_POST['parent_email']),
                'address' => !empty($_POST['parent_address']) ? clean($_POST['parent_address']) : null,
                'relationship' => clean($_POST['relationship']),
                'password' => Auth::hashPassword($generated_password),
                'status' => 'active'
            ];

            error_log("Veli verisi: " . print_r($parent_data, true));

            // Parents tablosunun yapısını kontrol et
            $parent_columns = db()->query("DESCRIBE parents")->fetchAll(PDO::FETCH_COLUMN);
            error_log("Parents tablosu kolonları: " . print_r($parent_columns, true));

            // Gerekli tüm alanlar var mı kontrol et
            $required_fields = ['first_name', 'last_name', 'phone', 'email', 'relationship'];
            foreach ($required_fields as $field) {
                if (empty($parent_data[$field])) {
                    throw new Exception("Zorunlu alan eksik: $field");
                }
            }

            $sql = "INSERT INTO parents (first_name, last_name, tcno, phone, email, address, relationship, password, status) 
                    VALUES (:first_name, :last_name, :tcno, :phone, :email, :address, :relationship, :password, :status)";

            error_log("SQL: " . $sql);

            $stmt = db()->prepare($sql);
            $result = $stmt->execute($parent_data);

            if (!$result) {
                error_log("Veli kayıt hatası: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Veli kaydedilemedi: " . implode(' - ', $stmt->errorInfo()));
            }

            $parent_id = db()->lastInsertId();
            error_log("Veli kaydedildi. ID: " . $parent_id);

            $_SESSION['last_parent_password'] = $generated_password;
        } else {
            // Mevcut veli seçildi
            $parent_id = clean($_POST['parent_id']);

            if (empty($parent_id)) {
                throw new Exception("Lütfen bir veli seçin");
            }
        }

        // Öğrenci kaydını oluştur
        $student_data = [
            'first_name' => clean($_POST['first_name']),
            'last_name' => clean($_POST['last_name']),
            'tcno' => !empty($_POST['tcno']) ? clean($_POST['tcno']) : null,
            'birth_date' => clean($_POST['birth_date']),
            'phone' => !empty($_POST['phone']) ? clean($_POST['phone']) : null,
            'school' => clean($_POST['school']),
            'monthly_fee' => clean($_POST['monthly_fee']),
            'payment_day' => clean($_POST['payment_day']),
            'sibling_student' => isset($_POST['sibling_student']) ? 1 : 0,
            'sibling_discount' => isset($_POST['sibling_student']) ? clean($_POST['sibling_discount']) : 0,
            'net_fee' => clean($_POST['net_fee']),
            'parent_id' => $parent_id,
            'enrollment_date' => date('Y-m-d'),
            'status' => clean($_POST['period_status'] ?? 'trial')
        ];

        error_log("Öğrenci verisi: " . print_r($student_data, true));

        $sql = "INSERT INTO students (first_name, last_name, tcno, birth_date, phone, school, monthly_fee, 
                payment_day, sibling_student, sibling_discount, net_fee, parent_id, enrollment_date, status) 
                VALUES (:first_name, :last_name, :tcno, :birth_date, :phone, :school, :monthly_fee, 
                :payment_day, :sibling_student, :sibling_discount, :net_fee, :parent_id, :enrollment_date, :status)";

        $stmt = db()->prepare($sql);
        $result = $stmt->execute($student_data);

        if (!$result) {
            error_log("Öğrenci kayıt hatası: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Öğrenci kaydedilemedi: " . implode(' - ', $stmt->errorInfo()));
        }

        $student_id = db()->lastInsertId();
        error_log("Öğrenci kaydedildi. ID: " . $student_id);

        // student_parents tablosuna ekle (eğer tablo varsa)
        $table_check = db()->query("SHOW TABLES LIKE 'student_parents'")->fetch();
        if ($table_check) {
            $relation_data = [
                'student_id' => $student_id,
                'parent_id' => $parent_id,
                'is_primary' => 1
            ];

            $sql = "INSERT INTO student_parents (student_id, parent_id, is_primary) 
                    VALUES (:student_id, :parent_id, :is_primary)";

            $stmt = db()->prepare($sql);
            $result = $stmt->execute($relation_data);

            if (!$result) {
                error_log("Student-Parent ilişki hatası: " . print_r($stmt->errorInfo(), true));
            }
        }

        // Öğrenciyi mevcut döneme kaydet
        $period_data = [
            'student_id' => $student_id,
            'period_id' => $current_period['id'],
            'enrollment_date' => date('Y-m-d'),
            'status' => clean($_POST['period_status'] ?? 'trial')
        ];

        $sql = "INSERT INTO student_periods (student_id, period_id, enrollment_date, status) 
                VALUES (:student_id, :period_id, :enrollment_date, :status)";

        $stmt = db()->prepare($sql);
        $result = $stmt->execute($period_data);

        if (!$result) {
            error_log("Dönem kayıt hatası: " . print_r($stmt->errorInfo(), true));
            throw new Exception("Dönem kaydı oluşturulamadı: " . implode(' - ', $stmt->errorInfo()));
        }

        db()->commit();

        if ($is_new_parent && isset($_SESSION['last_parent_password'])) {
            setAlert('Öğrenci başarıyla eklendi! <br>Veli giriş bilgileri: <br>E-posta: ' . $parent_data['email'] . '<br>Şifre: ' . $_SESSION['last_parent_password'], 'success');
            unset($_SESSION['last_parent_password']);
        } else {
            setAlert('Öğrenci başarıyla eklendi!', 'success');
        }

        redirect('modules/students/index.php');
    } catch (Exception $e) {
        db()->rollBack();
        error_log("Genel hata: " . $e->getMessage());
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Yeni Öğrenci Ekle - ' . $current_period['name'];
require_once '../../includes/header.php';
?>

<!-- Form HTML kısmı aynı kalacak -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yeni Öğrenci Ekle
        <small class="text-muted">(<?php echo htmlspecialchars($current_period['name']); ?>)</small>
    </h2>
    <a href="index.php" class="btn btn-secondary">
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
                        Mevcut Veli Seç (Kardeş Öğrenci)
                    </label>
                </div>

                <div id="existing_parent_section" class="mt-3" style="display: none;">
                    <input type="hidden" name="parent_id" id="parent_id">
                    <div class="input-group">
                        <input type="text" class="form-control" id="selected_parent_name" readonly placeholder="Veli seçilmedi">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#parentSearchModal">
                            <i class="bi bi-search"></i> Veli Ara
                        </button>
                    </div>
                </div>
            </div>

            <!-- Öğrenci Bilgileri -->
            <div class="row">
                <div class="col-md-12">
                    <h5 class="mb-3">Öğrenci Bilgileri</h5>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="first_name" class="form-label">Ad</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="last_name" class="form-label">Soyad</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="tcno" class="form-label">TC Kimlik No</label>
                    <input type="text" class="form-control" id="tcno" name="tcno" maxlength="11">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="birth_date" class="form-label">Doğum Tarihi</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="tel" class="form-control" id="phone" name="phone">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="school" class="form-label">Okulu</label>
                    <input type="text" class="form-control" id="school" name="school" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="monthly_fee" class="form-label">Aylık Ödeme Tutarı (₺)</label>
                    <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" step="0.01" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="payment_day" class="form-label">Ödeme Günü</label>
                    <select class="form-select" id="payment_day" name="payment_day" required>
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === 5 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="sibling_student" name="sibling_student">
                        <label class="form-check-label" for="sibling_student">
                            Kardeş Öğrenci
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3" id="sibling_discount_div" style="display: none;">
                    <label for="sibling_discount" class="form-label">Kardeş İndirim Tutarı (₺)</label>
                    <input type="number" class="form-control" id="sibling_discount" name="sibling_discount" step="0.01" value="0">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="net_fee" class="form-label">Net Ücret (₺)</label>
                    <input type="number" class="form-control" id="net_fee" name="net_fee" step="0.01" readonly>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="period_status" class="form-label">Dönem Durumu</label>
                    <select class="form-select" id="period_status" name="period_status">
                        <option value="trial">Deneme</option>
                        <option value="active">Aktif</option>
                        <option value="passive">Pasif</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <!-- Veli Bilgileri -->
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
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Veli Arama Modal (aynı kalacak) -->
<div class="modal fade" id="parentSearchModal" tabindex="-1" aria-labelledby="parentSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="parentSearchModalLabel">Veli Arama</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="modal_parent_search" placeholder="Ad, soyad, TC No veya telefon ile arayın...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover" id="parentTable">
                        <thead>
                            <tr>
                                <th>Ad Soyad</th>
                                <th>TC No</th>
                                <th>Telefon</th>
                                <th>Seç</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parents as $parent): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></td>
                                    <td>
                                        <?php if (!empty($parent['tcno'])): ?>
                                            <?php echo substr($parent['tcno'], 0, 3) . '******' . substr($parent['tcno'], -2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo !empty($parent['phone']) ? formatPhone($parent['phone']) : ''; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary select-parent"
                                            data-id="<?php echo $parent['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?>"
                                            data-bs-dismiss="modal">
                                            Seç
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ücret hesaplama fonksiyonu
        function calculateNetFee() {
            const monthlyFee = parseFloat(document.getElementById('monthly_fee').value) || 0;
            const siblingDiscount = document.getElementById('sibling_student').checked ?
                (parseFloat(document.getElementById('sibling_discount').value) || 0) : 0;

            const netFee = monthlyFee - siblingDiscount;
            document.getElementById('net_fee').value = netFee.toFixed(2);
        }

        // Kardeş öğrenci checkbox'ına göre indirim alanını göster/gizle
        const siblingCheckbox = document.getElementById('sibling_student');
        const siblingDiscountDiv = document.getElementById('sibling_discount_div');

        siblingCheckbox.addEventListener('change', function() {
            siblingDiscountDiv.style.display = this.checked ? 'block' : 'none';
            calculateNetFee();
        });

        // Ücret alanları değiştiğinde net ücreti hesapla
        document.getElementById('monthly_fee').addEventListener('input', calculateNetFee);
        document.getElementById('sibling_discount').addEventListener('input', calculateNetFee);

        // Sayfa yüklendiğinde net ücreti hesapla
        calculateNetFee();

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
            }
        });

        // Modal tabanlı veli arama
        const modalSearch = document.getElementById('modal_parent_search');
        const parentTable = document.getElementById('parentTable');
        const tableRows = parentTable.getElementsByTagName('tr');

        modalSearch.addEventListener('input', function() {
            const searchTerm = turkishFix(this.value.toLowerCase());

            for (let i = 1; i < tableRows.length; i++) {
                const row = tableRows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;

                for (let j = 0; j < cells.length - 1; j++) {
                    const cellText = turkishFix(cells[j].textContent.toLowerCase());
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }

                row.style.display = found ? '' : 'none';
            }
        });

        // Veli seçme
        document.querySelectorAll('.select-parent').forEach(button => {
            button.addEventListener('click', function() {
                const parentId = this.getAttribute('data-id');
                const parentName = this.getAttribute('data-name');

                document.getElementById('parent_id').value = parentId;
                document.getElementById('selected_parent_name').value = parentName;
            });
        });
    });

    // Türkçe karakter düzeltme fonksiyonu
    function turkishFix(str) {
        const tr = ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
        const en = ['c', 'g', 'i', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'o', 's', 'u'];
        for (let i = 0; i < tr.length; i++) {
            str = str.replace(new RegExp(tr[i], 'g'), en[i]);
        }
        return str;
    }

    // Form submit edildiğinde debug için
    document.querySelector('form').addEventListener('submit', function(e) {
        console.log('Form gönderiliyor...');
        const formData = new FormData(this);
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>