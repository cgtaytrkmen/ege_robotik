<?php
// create_admin.php - Admin kullanıcısı oluşturma scripti
require_once 'config/config.php';

try {
    // Önce admins tablosunu oluştur
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'manager') DEFAULT 'admin',
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    db()->exec($sql);
    echo "Admin tablosu oluşturuldu.<br>";

    // Admin kullanıcısını ekle
    $admin_data = [
        'first_name' => 'Sistem',
        'last_name' => 'Yöneticisi',
        'email' => 'admin@robocode.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin'
    ];

    // Önce email ile kontrol et
    $check_sql = "SELECT id FROM admins WHERE email = ?";
    $check_stmt = safeQuery($check_sql, [$admin_data['email']]);

    if ($check_stmt->rowCount() == 0) {
        // Admin yoksa ekle
        $insert_sql = "INSERT INTO admins (first_name, last_name, email, password, role) 
                      VALUES (:first_name, :last_name, :email, :password, :role)";
        safeQuery($insert_sql, $admin_data);
        echo "Admin kullanıcısı başarıyla oluşturuldu.<br>";
        echo "E-posta: admin@robocode.com<br>";
        echo "Şifre: admin123<br>";
    } else {
        echo "Admin kullanıcısı zaten mevcut.<br>";
    }

    echo "<br><a href='login.php'>Giriş sayfasına git</a>";
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
