<?php
// modules/students/edit.php - Öğrenci düzenleme
require_once '../../config/config.php';

// Admin kontrolü
checkAdmin();

// Dönem kontrolü
checkPeriodSelection();
$current_period = getCurrentPeriod();

// ID kontrolü
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$student_id) {
    setAlert('Geçersiz öğrenci ID!', 'danger');
    redirect('modules/students/index.php');
}

// Öğrenci ve döneme özel bilgileri getir
$query = "SELECT s.*, p.id as parent_id, p.first_name as parent_first_name, p.last_name as parent_last_name, 
          p.tcno as parent_tcno, p.phone as parent_phone, p.email as parent_email, p.address as parent_address, 
          p.relationship, sp.status as period_status
          FROM students s
          LEFT JOIN parents p ON s.parent_id = p.id
          LEFT JOIN student_periods sp ON s.id = sp.student_id AND sp.period_id = ?
          WHERE s.id = ?";
$student = safeQuery($query, [$current_period['id'], $student_id])->fetch();

if (!$student) {
    setAlert('Öğrenci bulunamadı!', 'danger');
    redirect('modules/students/index.php');
}

// Mevcut velileri getir (kardeş kaydı için)
$parent_query = "SELECT id, first_name, last_name, tcno, phone FROM parents WHERE id != ? ORDER BY first_name, last_name";
$parents = safeQuery($parent_query, [$student['parent_id']])->fetchAll();

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Öğrenci bilgilerini güncelle
        $student_data = [
            'first_name' => clean($_POST['first_name']),
            'last_name' => clean($_POST['last_name']),
            'tcno' => clean($_POST['tcno']),
            'birth_date' => clean($_POST['birth_date']),
            'phone' => clean($_POST['phone']),
            'school' => clean($_POST['school']),
            'monthly_fee' => clean($_POST['monthly_fee']),
            'payment_day' => clean($_POST['payment_day']),
            'sibling_student' => isset($_POST['sibling_student']) ? 1 : 0,
            'sibling_discount' => isset($_POST['sibling_student']) ? clean($_POST['sibling_discount']) : 0,
            'net_fee' => clean($_POST['net_fee']),
            'status' => clean($_POST['status']),
            'id' => $student_id
        ];

        // Veli değişikliği kontrolü
        $is_parent_changed = isset($_POST['existing_parent']) && $_POST['existing_parent'] === 'existing';

        if ($is_parent_changed) {
            $student_data['parent_id'] = clean($_POST['parent_id']);
        } else {
            $student_data['parent_id'] = $student['parent_id'];

            // Mevcut veli bilgilerini güncelle
            $parent_data = [
                'first_name' => clean($_POST['parent_first_name']),
                'last_name' => clean($_POST['parent_last_name']),
                'tcno' => clean($_POST['parent_tcno']),
                'phone' => clean($_POST['parent_phone']),
                'email' => clean($_POST['parent_email']),
                'address' => clean($_POST['parent_address']),
                'relationship' => clean($_POST['relationship']),
                'id' => $student['parent_id']
            ];

            $parent_sql = "UPDATE parents SET first_name = :first_name, last_name = :last_name, tcno = :tcno, 
                          phone = :phone, email = :email, address = :address, relationship = :relationship 
                          WHERE id = :id";
            safeQuery($parent_sql, $parent_data);
        }

        // Öğrenci bilgilerini güncelle
        $student_sql = "UPDATE students SET first_name = :first_name, last_name = :last_name, tcno = :tcno, 
                       birth_date = :birth_date, phone = :phone, school = :school, monthly_fee = :monthly_fee, 
                       payment_day = :payment_day, sibling_student = :sibling_student, sibling_discount = :sibling_discount, 
                       net_fee = :net_fee, parent_id = :parent_id, status = :status WHERE id = :id";
        safeQuery($student_sql, $student_data);

        // Dönem durumunu güncelle
        $period_data = [
            'status' => clean($_POST['period_status']),
            'student_id' => $student_id,
            'period_id' => $current_period['id']
        ];

        $period_sql = "UPDATE student_periods SET status = :status 
                      WHERE student_id = :student_id AND period_id = :period_id";
        safeQuery($period_sql, $period_data);

        setAlert('Öğrenci başarıyla güncellendi!', 'success');
        redirect('modules/students/view.php?id=' . $student_id);
    } catch (Exception $e) {
        setAlert('Hata: ' . $e->getMessage(), 'danger');
    }
}

$page_title = 'Öğrenci Düzenle - ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Öğrenci Düzenle
        <small class="text-muted">(<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>)</small>
    </h2>
    <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <!-- Öğrenci Bilgileri -->
            <div class="row">
                <div class="col-md-12">
                    <h5 class="mb-3">Öğrenci Bilgileri</h5>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="first_name" class="form-label">Ad</label>
                    <input type="text" class="form-control" id="first_name" name="first_name"
                        value="<?php echo htmlspecialchars((string)($student['first_name'] ?? '')); ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="last_name" class="form-label">Soyad</label>
                    <input type="text" class="form-control" id="last_name" name="last_name"
                        value="<?php echo htmlspecialchars((string)($student['last_name'] ?? '')); ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="tcno" class="form-label">TC Kimlik No</label>
                    <input type="text" class="form-control" id="tcno" name="tcno" maxlength="11"
                        value="<?php echo htmlspecialchars((string)($student['tcno'] ?? '')); ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="birth_date" class="form-label">Doğum Tarihi</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date"
                        value="<?php echo $student['birth_date']; ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="tel" class="form-control" id="phone" name="phone"
                        value="<?php echo htmlspecialchars((string)($student['phone'] ?? '')); ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="school" class="form-label">Okulu</label>
                    <input type="text" class="form-control" id="school" name="school"
                        value="<?php echo htmlspecialchars((string)($student['school'] ?? '')); ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="monthly_fee" class="form-label">Aylık Ödeme Tutarı (₺)</label>
                    <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" step="0.01"
                        value="<?php echo $student['monthly_fee']; ?>" required>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="payment_day" class="form-label">Ödeme Günü</label>
                    <select class="form-select" id="payment_day" name="payment_day" required>
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $student['payment_day'] ? 'selected' : ''; ?>>
                                <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" id="sibling_student" name="sibling_student"
                            <?php echo $student['sibling_student'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sibling_student">
                            Kardeş Öğrenci
                        </label>
                    </div>
                </div>

                <div class="col-md-3 mb-3" id="sibling_discount_div" style="display: <?php echo $student['sibling_student'] ? 'block' : 'none'; ?>;">
                    <label for="sibling_discount" class="form-label">Kardeş İndirim Tutarı (₺)</label>
                    <input type="number" class="form-control" id="sibling_discount" name="sibling_discount" step="0.01"
                        value="<?php echo $student['sibling_discount']; ?>">
                </div>

                <div class="col-md-3 mb-3">
                    <label for="net_fee" class="form-label">Net Ücret (₺)</label>
                    <input type="number" class="form-control" id="net_fee" name="net_fee" step="0.01"
                        value="<?php echo $student['net_fee']; ?>" readonly>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Genel Durum</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?php echo $student['status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="passive" <?php echo $student['status'] == 'passive' ? 'selected' : ''; ?>>Pasif</option>
                        <option value="trial" <?php echo $student['status'] == 'trial' ? 'selected' : ''; ?>>Deneme</option>
                    </select>
                </div>

                <div class="col-md-3 mb-3">
                    <label for="period_status" class="form-label">Dönem Durumu</label>
                    <select class="form-select" id="period_status" name="period_status">
                        <option value="active" <?php echo $student['period_status'] == 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="passive" <?php echo $student['period_status'] == 'passive' ? 'selected' : ''; ?>>Pasif</option>
                        <option value="trial" <?php echo $student['period_status'] == 'trial' ? 'selected' : ''; ?>>Deneme</option>
                        <option value="completed" <?php echo $student['period_status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <!-- Veli Bilgileri -->
            <div class="alert alert-info mb-4">
                <h5 class="alert-heading">Veli Değiştir</h5>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="existing_parent" id="keep_parent" value="keep" checked>
                    <label class="form-check-label" for="keep_parent">
                        Mevcut Veliyi Koru
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="existing_parent" id="change_parent" value="existing">
                    <label class="form-check-label" for="change_parent">
                        Başka Bir Veli Seç
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

            <div id="parent_info_section">
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="mb-3">Veli Bilgileri</h5>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_first_name" class="form-label">Veli Adı</label>
                        <input type="text" class="form-control" id="parent_first_name" name="parent_first_name"
                            value="<?php echo htmlspecialchars($student['parent_first_name']); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_last_name" class="form-label">Veli Soyadı</label>
                        <input type="text" class="form-control" id="parent_last_name" name="parent_last_name"
                            value="<?php echo htmlspecialchars($student['parent_last_name']); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="parent_tcno" class="form-label">Veli TC Kimlik No</label>
                        <input type="text" class="form-control" id="parent_tcno" name="parent_tcno" maxlength="11"
                            value="<?php echo htmlspecialchars($student['parent_tcno']); ?>" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="relationship" class="form-label">Yakınlık Derecesi</label>
                        <select class="form-select" id="relationship" name="relationship">
                            <option value="anne" <?php echo $student['relationship'] == 'anne' ? 'selected' : ''; ?>>Anne</option>
                            <option value="baba" <?php echo $student['relationship'] == 'baba' ? 'selected' : ''; ?>>Baba</option>
                            <option value="vasi" <?php echo $student['relationship'] == 'vasi' ? 'selected' : ''; ?>>Vasi</option>
                            <option value="diger" <?php echo $student['relationship'] == 'diger' ? 'selected' : ''; ?>>Diğer</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_phone" class="form-label">Veli Telefon</label>
                        <input type="tel" class="form-control" id="parent_phone" name="parent_phone"
                            value="<?php echo htmlspecialchars($student['parent_phone']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_email" class="form-label">Veli E-posta</label>
                        <input type="email" class="form-control" id="parent_email" name="parent_email"
                            value="<?php echo htmlspecialchars($student['parent_email']); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="parent_address" class="form-label">Adres</label>
                        <textarea class="form-control" id="parent_address" name="parent_address" rows="1"><?php echo htmlspecialchars($student['parent_address']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Güncelle
                    </button>
                    <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> İptal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Veli Arama Modal -->
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

        // Veli değiştirme seçenekleri
        const keepParentRadio = document.getElementById('keep_parent');
        const changeParentRadio = document.getElementById('change_parent');
        const existingParentSection = document.getElementById('existing_parent_section');
        const parentInfoSection = document.getElementById('parent_info_section');

        changeParentRadio.addEventListener('change', function() {
            if (this.checked) {
                existingParentSection.style.display = 'block';
                parentInfoSection.style.display = 'none';
                // Veli alanlarını zorunlu olmaktan çıkar
                document.querySelectorAll('#parent_info_section input').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        });

        keepParentRadio.addEventListener('change', function() {
            if (this.checked) {
                existingParentSection.style.display = 'none';
                parentInfoSection.style.display = 'block';
                // Sadece temel veli alanlarını zorunlu yap
                document.getElementById('parent_first_name').setAttribute('required', 'required');
                document.getElementById('parent_last_name').setAttribute('required', 'required');
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
</script>

<?php require_once '../../includes/footer.php'; ?>