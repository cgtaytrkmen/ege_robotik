<?php
// includes/header.php - Ortak header bölümü
// Output buffering başlat
ob_start();
// Alert mesajını al
$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <?php if (isset($datatable) && $datatable): ?>
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <?php endif; ?>

    <!-- FullCalendar CSS -->
    <?php if (isset($calendar) && $calendar): ?>
        <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <?php endif; ?>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>">
                <i class="bi bi-robot"></i> EGE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (Auth::isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/students/">
                                <i class="bi bi-people"></i> Öğrenciler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/classes/">
                                <i class="bi bi-building"></i> Sınıflar
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-calendar3"></i> Dersler
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/schedule/">
                                        <i class="bi bi-calendar-week"></i> Ders Programı
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/attendance/">
                                        <i class="bi bi-calendar-check"></i> Yoklama
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/makeup/">
                                        <i class="bi bi-calendar-plus"></i> Telafi Programı
                                    </a>
                                </li>

                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/topics/">
                                        <i class="bi bi-book"></i> Konular
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/weeks/">
                                        <i class="bi bi-calendar-range"></i> Hafta Yönetimi
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/finance/">
                                <i class="bi bi-cash-stack"></i> Finans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/reports/">
                                <i class="bi bi-graph-up"></i> Raporlar
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (Auth::isParent()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/parents/">
                                <i class="bi bi-house"></i> Ana Sayfa
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/parents/attendance.php">
                                <i class="bi bi-calendar-check"></i> Devam Durumu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/parents/payments.php">
                                <i class="bi bi-credit-card"></i> Ödemeler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/modules/parents/topics.php">
                                <i class="bi bi-book"></i> İşlenen Konular
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <?php if (Auth::check()): ?>
                    <ul class="navbar-nav">
                        <?php if (Auth::isAdmin()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="bi bi-calendar3"></i>
                                    <?php
                                    $current_period = getCurrentPeriod();
                                    echo $current_period ? htmlspecialchars($current_period['name']) : 'Dönem Seç';
                                    ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach (getAllPeriods() as $period): ?>
                                        <li>
                                            <a class="dropdown-item <?php echo ($current_period && $period['id'] == $current_period['id']) ? 'active' : ''; ?>"
                                                href="<?php echo BASE_URL; ?>/change-period.php?id=<?php echo $period['id']; ?>">
                                                <?php echo htmlspecialchars($period['name']); ?>
                                                <?php if ($period['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/modules/periods/">
                                            <i class="bi bi-gear"></i> Dönem Yönetimi</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo Auth::getUserName(); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (Auth::isAdmin()): ?>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php">Profil</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/settings.php">Ayarlar</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">Çıkış Yap</a></li>
                            </ul>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Alert mesajları -->
    <?php if ($alert): ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $alert['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Ana içerik başlangıcı -->
    <main class="py-4">
        <div class="container">