<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['pending_email'])) {
    redirect('/blood_bank/auth/login.php');
}

$email   = $_SESSION['pending_email'];
$errors  = [];
$ref     = $_GET['ref'] ?? 'login';

// Resend
if (isset($_GET['resend'])) {
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300);
    $conn->query("DELETE FROM otp_codes WHERE email='$email'");
    $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp, expires_at) VALUES (?,?,?)");
    $stmt->bind_param("sss", $email, $otp, $expires);
    $stmt->execute();
    $_SESSION['otp_expiry'] = time() + 300;
    $_SESSION['debug_otp']  = $otp;
    redirect('/blood_bank/auth/verify_otp.php?ref='.$ref.'&resent=1');
}

// Generate OTP on first visit
if (!isset($_SESSION['otp_expiry'])) {
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300);
    $conn->query("DELETE FROM otp_codes WHERE email='$email'");
    $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp, expires_at) VALUES (?,?,?)");
    $stmt->bind_param("sss", $email, $otp, $expires);
    $stmt->execute();
    $_SESSION['otp_expiry'] = time() + 300;
    $_SESSION['debug_otp']  = $otp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = implode('', array_map('trim', (array)($_POST['otp_digits'] ?? [])));
    $row     = $conn->query("SELECT * FROM otp_codes WHERE email='$email' AND is_used=0 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

    if (!$row)                                    $errors[] = 'OTP not found. Please request a new one.';
    elseif (time() > strtotime($row['expires_at'])) $errors[] = 'OTP expired. Click Resend OTP.';
    elseif ($row['otp'] !== $entered)             $errors[] = 'Incorrect OTP. Please try again.';
    else {
        $conn->query("UPDATE otp_codes SET is_used=1 WHERE id={$row['id']}");
        $conn->query("UPDATE users SET email_verified=1 WHERE email='$email'");

        // Log user in
        $user = $conn->query("SELECT * FROM users WHERE email='$email'")->fetch_assoc();
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['role_type'] = $user['role_type'] ?? $user['role'];
        }
        unset($_SESSION['pending_email'], $_SESSION['otp_expiry'], $_SESSION['debug_otp']);
        $dest = ($user['role'] === 'admin') ? '/blood_bank/admin/dashboard.php' : '/blood_bank/user/dashboard.php?welcome=1';
        redirect($dest);
    }
}

$otp_remaining = max(0, ($_SESSION['otp_expiry'] ?? 0) - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
</head>
<body class="auth-page">

<div class="hands-bg">
    <img src="/blood_bank/assets/hands_bg.jpg" class="hands-bg-img" alt="">
</div>
<div class="auth-overlay"></div>
<svg class="thread-svg" viewBox="0 0 1440 900" preserveAspectRatio="none">
    <path class="thread-glow" d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
    <path class="thread-path"  d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
</svg>

<div class="auth-card" style="max-width:420px;">
    <img src="/blood_bank/assets/logo.png" class="auth-logo" alt="BloodSync">
    <div class="auth-site-name">Blood<span class="sync-text">Sync</span></div>

    <?php if (isset($_GET['resent'])): ?>
    <div class="alert alert-success py-2 small mb-3 text-center">
        <i class="bi bi-check-circle me-1"></i>New OTP sent successfully!
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-x-circle me-2"></i><?= $errors[0] ?>
    </div>
    <?php endif; ?>

    <div class="text-center mb-3">
        <div style="font-size:2.8rem;margin-bottom:8px;">📩</div>
        <div class="fw-bold" style="font-size:1.05rem;">Email Verification</div>
        <div class="text-muted small mt-1">
            Enter the 6-digit OTP sent to<br>
            <strong class="text-danger"><?= htmlspecialchars($email) ?></strong>
        </div>
    </div>

    <form method="POST" id="otpForm">
        <div class="otp-inputs" id="otpInputs">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" name="otp_digits[]" maxlength="1" inputmode="numeric"
                class="otp-single" autocomplete="off">
            <?php endfor; ?>
        </div>

        <?php if (isset($_SESSION['debug_otp'])): ?>
        <div class="alert alert-info py-2 small text-center mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Demo OTP:</strong> <?= $_SESSION['debug_otp'] ?>
            <span class="text-muted">(remove in production)</span>
        </div>
        <?php endif; ?>

        <div class="otp-timer mb-3">
            OTP expires in <span class="timer-num" id="timerDisplay">5:00</span>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold mb-2">
            <i class="bi bi-shield-check me-2"></i>Verify OTP
        </button>
    </form>

    <div class="d-flex justify-content-between mt-2">
        <a href="?ref=<?= $ref ?>&resend=1" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Resend OTP
        </a>
        <a href="/blood_bank/auth/login.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </div>
</div>

<script>
const otpIn = document.querySelectorAll('.otp-single');
otpIn.forEach((el, idx) => {
    el.addEventListener('input', function(){
        this.value = this.value.replace(/\D/g,'').slice(0,1);
        this.classList.toggle('filled', !!this.value);
        if (this.value && idx < otpIn.length-1) otpIn[idx+1].focus();
    });
    el.addEventListener('keydown', function(e){
        if (e.key==='Backspace' && !this.value && idx > 0) otpIn[idx-1].focus();
    });
    el.addEventListener('paste', function(e){
        const paste = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
        otpIn.forEach((inp,i) => { if(paste[i]){ inp.value=paste[i]; inp.classList.add('filled'); }});
        otpIn[Math.min(paste.length, otpIn.length-1)].focus();
        e.preventDefault();
    });
});

// Countdown
let remaining = <?= $otp_remaining ?>;
const timerEl = document.getElementById('timerDisplay');
function tick() {
    if (remaining <= 0) { timerEl.textContent='Expired'; timerEl.style.color='#e74c3c'; return; }
    const m = Math.floor(remaining/60), s = remaining%60;
    timerEl.textContent = m+':'+(s<10?'0':'')+s;
    if (remaining <= 60) timerEl.style.color='#e74c3c';
    remaining--; setTimeout(tick, 1000);
}
tick();
</script>
</body>
</html>
