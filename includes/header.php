<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
$page_title = $page_title ?? 'BloodSync';
$is_user_page = isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin';
// Header is minimal for user pages (user_header.php handles full nav)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
    <link href="/blood_bank/assets/dark_fix.css" rel="stylesheet">
</head>
<body>
<?php
// Only show admin navbar on admin pages. User pages use user_header.php navbar.
if (!$is_user_page && !in_array(basename($_SERVER['PHP_SELF']), ['login.php','register.php','verify_otp.php','forgot_password.php','reset_password.php'])):
    // Admin navbar
    $header_unread = 0;
    if (isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $nr  = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
        if ($nr) $header_unread = (int)$nr->fetch_assoc()['c'];
    }
?>
<nav class="navbar navbar-dark px-3 py-2">
    <a class="navbar-brand" href="<?= isset($_SESSION['role']) && $_SESSION['role']==='admin' ? '/blood_bank/admin/dashboard.php' : '/blood_bank/user/dashboard.php' ?>">
        <img src="/blood_bank/assets/logo.png" alt="BloodSync Logo">
        <span>Blood<span class="brand-sync">Sync</span></span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['name']) ?>
        </span>
        <?php $notif_link = '/blood_bank/admin/notifications.php'; ?>
        <a href="<?= $notif_link ?>" class="btn btn-sm btn-outline-light position-relative">
            <i class="bi bi-bell"></i>
            <?php if ($header_unread > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark" style="font-size:9px;"><?= $header_unread ?></span>
            <?php endif; ?>
        </a>
        <a href="/blood_bank/auth/logout.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
    </div>
    <?php endif; ?>
</nav>
<?php endif; ?>
