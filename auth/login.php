<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? '/blood_bank/admin/dashboard.php' : '/blood_bank/user/dashboard.php');
}

$error   = '';
$do_anim = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        if (isset($user['is_blocked']) && $user['is_blocked']) {
            $error = 'Your account has been blocked by the administrator.';
        } else {
            $cols = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
            if ($cols->num_rows > 0 && !$user['email_verified']) {
                $_SESSION['pending_email'] = $user['email'];
                redirect('/blood_bank/auth/verify_otp.php?ref=login');
            }
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['role_type'] = $user['role_type'] ?? $user['role'];
            $do_anim = true;
        }
    } else {
        $error = 'Invalid email or password.';
    }
}

$redirect_url = (isset($_SESSION['role']) && $_SESSION['role']==='admin')
    ? '/blood_bank/admin/dashboard.php'
    : '/blood_bank/user/dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
    <style>
        html, body { height: auto; overflow-y: auto; }
        .auth-page { overflow-y: auto !important; align-items: flex-start !important; padding: 40px 16px 60px !important; }
        .hands-bg, .auth-overlay, .thread-svg { position: fixed !important; }
    </style>
</head>
<body class="auth-page">

<div class="hands-bg">
    <img src="/blood_bank/assets/hands_bg.jpg" class="hands-bg-img" id="handsBg" alt="">
</div>
<div class="auth-overlay"></div>

<svg class="thread-svg" viewBox="0 0 1440 900" preserveAspectRatio="none">
    <path class="thread-glow" d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
    <path class="thread-path"  d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
</svg>

<?php if ($do_anim): ?>
<div class="login-success-overlay show" id="successOverlay">
    <div class="success-heart">❤️</div>
    <div class="success-tagline">Connecting Lives, Saving Lives</div>
    <div class="success-sub">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?> — Redirecting...</div>
</div>
<?php endif; ?>

<div class="auth-card" id="loginCard">
    <img src="/blood_bank/assets/logo.png" class="auth-logo" alt="BloodSync Logo">
    <div class="auth-site-name">Blood<span class="sync-text">Sync</span></div>
    <div class="auth-tagline">Connecting Lives · Saving Lives</div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i><?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold small">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="lpass" class="form-control" placeholder="Enter password" required>
                <span class="input-group-text pass-toggle" onclick="tp('lpass',this)">
                    <i class="bi bi-eye-slash"></i>
                </span>
            </div>
        </div>
        <div class="text-end mb-2"><a href="/blood_bank/auth/forgot_password.php" style="font-size:0.8rem;color:#c0392b;text-decoration:none;font-weight:600;"><i class="bi bi-question-circle me-1"></i>Forgot Password?</a></div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold mb-3">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login to BloodSync
        </button>
    </form>

    <div class="auth-divider">or</div>
    <p class="text-center small text-muted mb-0">
        New here? <a href="/blood_bank/auth/register.php" class="text-danger fw-semibold">Create Account</a>
    </p>
    <div class="mt-3 p-3 rounded-3" style="background:#fff5f4;border:1px dashed #f5c6c3;">
        <div class="text-center small fw-semibold mb-1" style="color:#c0392b;">Demo Credentials</div>
        <div class="text-center small text-muted">admin@bloodbank.com / <strong>password</strong></div>
    </div>
</div>

<script>
function tp(id, btn) {
    const i = document.getElementById(id);
    const ic = btn.querySelector('i');
    i.type = i.type==='password' ? 'text' : 'password';
    ic.className = i.type==='password' ? 'bi bi-eye-slash' : 'bi bi-eye';
}
<?php if ($do_anim): ?>
document.getElementById('loginCard').style.display = 'none';
document.getElementById('handsBg').classList.add('join');
setTimeout(() => { window.location.href = '<?= $redirect_url ?>'; }, 2800);
<?php endif; ?>
</script>
</body>
</html>
