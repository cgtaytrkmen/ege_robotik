<?php
// config/config.php - Genel sistem ayarları

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Output buffering başlat (header already sent hatasını önlemek için)
ob_start();

// Zaman dilimi
date_default_timezone_set('Europe/Istanbul');

// Temel sabitler
define('SITE_NAME', 'Ege Robotik Robotik Kodlama Kursu');
define('BASE_URL', 'http://localhost/ege_robotik');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Oturum ayarları
session_start();

// Veritabanı bağlantısını dahil et
require_once __DIR__ . '/database.php';

// Genel fonksiyonları dahil et
require_once __DIR__ . '/../includes/functions.php';

// Yetkilendirme kontrolünü dahil et
require_once __DIR__ . '/../includes/auth.php';

// Sistem sabitleri
define('MAX_CLASS_CAPACITY', 6);
define('TRIAL_LESSON_DAYS', 7);
define('PAYMENT_DUE_DAY', 5); // Her ayın 5'i

// Sistem durumu sabitleri
define('STATUS_ACTIVE', 'active');
define('STATUS_PASSIVE', 'passive');
define('STATUS_TRIAL', 'trial');
define('STATUS_PAID', 'paid');
define('STATUS_PENDING', 'pending');
define('STATUS_LATE', 'late');

// Yoklama durumları
define('ATTENDANCE_PRESENT', 'present');
define('ATTENDANCE_ABSENT', 'absent');
define('ATTENDANCE_LATE', 'late');
define('ATTENDANCE_EXCUSED', 'excused');
