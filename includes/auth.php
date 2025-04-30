<?php
// includes/auth.php - Yetkilendirme ve oturum yönetimi

class Auth
{
    // Kullanıcı giriş kontrolü
    public static function login($email, $password, $type = 'admin')
    {
        if ($type === 'admin') {
            // Admin girişi
            $sql = "SELECT * FROM admins WHERE email = ? AND status = 'active'";
            $stmt = safeQuery($sql, [$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['user_name'] = $admin['first_name'] . ' ' . $admin['last_name'];

                // Son giriş zamanını güncelle
                $update_sql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
                safeQuery($update_sql, [$admin['id']]);

                return true;
            }
        } else if ($type === 'parent') {
            // Veli girişi
            $sql = "SELECT * FROM parents WHERE email = ? AND status = 'active'";
            $stmt = safeQuery($sql, [$email]);
            $parent = $stmt->fetch();

            if ($parent && password_verify($password, $parent['password'])) {
                $_SESSION['user_id'] = $parent['id'];
                $_SESSION['user_type'] = 'parent';
                $_SESSION['user_name'] = $parent['first_name'] . ' ' . $parent['last_name'];

                // Son giriş zamanını güncelle
                $update_sql = "UPDATE parents SET last_login = NOW() WHERE id = ?";
                safeQuery($update_sql, [$parent['id']]);

                return true;
            }
        }

        return false;
    }

    // Çıkış işlemi
    public static function logout()
    {
        session_destroy();
        redirect('login.php');
    }

    // Oturum kontrolü
    public static function check()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
    }

    // Admin kontrolü
    public static function isAdmin()
    {
        return self::check() && $_SESSION['user_type'] === 'admin';
    }

    // Veli kontrolü
    public static function isParent()
    {
        return self::check() && $_SESSION['user_type'] === 'parent';
    }

    // Kullanıcı ID'si
    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    // Kullanıcı tipi
    public static function getUserType()
    {
        return $_SESSION['user_type'] ?? null;
    }

    // Kullanıcı adı
    public static function getUserName()
    {
        return $_SESSION['user_name'] ?? '';
    }

    // Şifre hashleme
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // İzin kontrolü
    public static function hasPermission($permission)
    {
        // İleride detaylı yetkilendirme için kullanılabilir
        switch ($permission) {
            case 'manage_students':
            case 'manage_classes':
            case 'manage_finance':
            case 'view_reports':
                return self::isAdmin();
            case 'view_student_info':
                return self::isAdmin() || self::isParent();
            default:
                return false;
        }
    }

    // Son aktivite kontrolü (oturum zaman aşımı için)
    public static function updateActivity()
    {
        $_SESSION['last_activity'] = time();
    }

    // Oturum zaman aşımı kontrolü (30 dakika)
    public static function checkTimeout($timeout = 1800)
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
            self::logout();
            return false;
        }
        self::updateActivity();
        return true;
    }
}

// Oturum zaman aşımı kontrolü
if (Auth::check()) {
    Auth::checkTimeout();
}
