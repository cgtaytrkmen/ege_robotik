<?php
// includes/functions.php - Genel yardımcı fonksiyonlar

// Güvenli veri temizleme
function clean($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


// URL yönlendirme fonksiyonu düzeltilmiş versiyonu
function redirect($url)
{
    // Eğer çıktı başlamışsa ob_clean() ile temizle
    if (ob_get_length()) {
        ob_clean();
    }

    // Herhangi bir çıktı olmaması için script'i sonlandır
    header("Location: " . BASE_URL . "/" . $url);
    exit();
}
// Tarih formatı düzenleme
function formatDate($date, $format = 'd.m.Y')
{
    return date($format, strtotime($date));
}

// Para formatı
function formatMoney($amount)
{
    return number_format($amount, 2, ',', '.') . ' ₺';
}

// Alert mesajları
function setAlert($message, $type = 'success')
{
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Dosya yükleme fonksiyonu
function uploadFile($file, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'])
{
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_types)) {
            $new_filename = uniqid() . '.' . $file_ext;
            $upload_path = UPLOAD_PATH . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                return $new_filename;
            }
        }
    }
    return false;
}

// Yaş hesaplama
function calculateAge($birthdate)
{
    $birthDate = new DateTime($birthdate);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

// Telefon numarası formatı
function formatPhone($phone)
{
    // Sadece rakamları al
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // 10 haneli Türkiye formatı
    if (strlen($phone) == 10) {
        return '0' . substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6, 2) . ' ' . substr($phone, 8, 2);
    }
    return $phone;
}

// Oturum kontrolü
function checkLogin()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        redirect('login.php');
    }
}

// Admin kontrolü
function checkAdmin()
{
    checkLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        redirect('index.php');
    }
}

// Veli kontrolü
function checkParent()
{
    checkLogin();
    if ($_SESSION['user_type'] !== 'parent') {
        redirect('index.php');
    }
}

// SQL Injection koruması için güvenli query
function safeQuery($sql, $params = [])
{
    try {
        $stmt = db()->prepare($sql);
        if (!$stmt) {
            error_log("SQL hazırlama hatası: " . print_r(db()->errorInfo(), true));
            error_log("SQL: " . $sql);
            return false;
        }

        $result = $stmt->execute($params);
        if (!$result) {
            error_log("SQL çalıştırma hatası: " . print_r($stmt->errorInfo(), true));
            error_log("SQL: " . $sql);
            error_log("Parametreler: " . print_r($params, true));
            return false;
        }

        return $stmt;
    } catch (PDOException $e) {
        error_log("Veritabanı hatası: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Parametreler: " . print_r($params, true));
        return false;
    }
}

// Dönem bilgisi getir
function getCurrentPeriod()
{
    // Oturumda dönem seçilmişse onu kullan
    if (isset($_SESSION['current_period_id'])) {
        $sql = "SELECT * FROM periods WHERE id = ?";
        $stmt = safeQuery($sql, [$_SESSION['current_period_id']]);
        $period = $stmt->fetch();

        if ($period) {
            return $period;
        }
    }

    // Aktif dönemi getir
    $sql = "SELECT * FROM periods WHERE status = 'active' AND start_date <= CURDATE() AND end_date >= CURDATE() LIMIT 1";
    $stmt = db()->query($sql);
    $period = $stmt->fetch();

    if ($period) {
        $_SESSION['current_period_id'] = $period['id'];
        return $period;
    }

    // Hiç aktif dönem yoksa en yakın dönemi getir
    $sql = "SELECT * FROM periods ORDER BY ABS(DATEDIFF(start_date, CURDATE())) LIMIT 1";
    $stmt = db()->query($sql);
    $period = $stmt->fetch();

    if ($period) {
        $_SESSION['current_period_id'] = $period['id'];
        return $period;
    }

    return null;
}

// Dönem seçimi yapılmış mı kontrol et
function checkPeriodSelection()
{
    if (!getCurrentPeriod()) {
        redirect('select-period.php');
    }
}

// Tüm dönemleri getir
function getAllPeriods()
{
    $sql = "SELECT * FROM periods ORDER BY start_date DESC";
    $stmt = db()->query($sql);
    return $stmt->fetchAll();
}

// Dönem değiştir
function changePeriod($period_id)
{
    $_SESSION['current_period_id'] = $period_id;
    return true;
}

// Sayfalama fonksiyonu
function paginate($total_records, $current_page, $per_page = 10)
{
    $total_pages = ceil($total_records / $per_page);
    $offset = ($current_page - 1) * $per_page;

    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'limit' => $per_page
    ];
}

// Türkçe karakter düzeltme
function turkishFix($str)
{
    $tr = ['ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
    $en = ['c', 'g', 'i', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'O', 'S', 'U'];
    return str_replace($tr, $en, $str);
}

// Rastgele şifre oluşturma
function generatePassword($length = 8)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// E-posta kontrolü
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Debug fonksiyonu
function debug($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
