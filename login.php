<?php
// login.php - Giriş sayfası
require_once 'config/config.php';

// Eğer zaten giriş yapılmışsa yönlendir
if (Auth::check()) {
    if (Auth::isAdmin()) {
        redirect('index.php');
    } else {
        redirect('modules/parents/index.php');
    }
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean($_POST['email']);
    $password = clean($_POST['password']);
    $type = clean($_POST['user_type'] ?? 'admin');

    if (Auth::login($email, $password, $type)) {
        if ($type === 'admin') {
            redirect('index.php');
        } else {
            redirect('modules/parents/index.php');
        }
    } else {
        setAlert('Geçersiz e-posta veya şifre!', 'danger');
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo i {
            font-size: 4rem;
            color: #0d6efd;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <i class="bi bi-robot"></i>
                <h3 class="mt-3">Ege Robotik Kodlama</h3>
                <p class="text-muted">Yönetim Paneli</p>
            </div>

            <?php if ($alert = getAlert()): ?>
                <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $alert['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="user_type" class="form-label">Kullanıcı Tipi</label>
                            <select class="form-select" id="user_type" name="user_type">
                                <option value="admin">Yönetici</option>
                                <option value="parent">Veli</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Giriş Yap
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">
                    Demo bilgileri: admin@robocode.com / admin123
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>