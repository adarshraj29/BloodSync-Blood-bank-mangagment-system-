<?php
// ============================================================
// BloodSync - Forgot Password (Step 1: Enter email + send OTP)
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) redirect('/blood_bank/user/dashboard.php');

// Resend OTP
if (isset($_GET['resend']) && isset($_SESSION['reset_identifier'])) {
    $identifier = $_SESSION['reset_identifier'];
    $is_phone   = $_SESSION['reset_is_phone'];
    $user_email = $_SESSION['reset_user_email'] ?? $identifier;
    $otp        = str_pad(random_int(0,999999), 6, '0', STR_PAD_LEFT);
    $expires    = date('Y-m-d H:i:s', time() + 300);
    $conn->query("DELETE FROM otp_codes WHERE email='$identifier' AND purpose='reset'");
    $stmt = $conn->prepare("INSERT INTO otp_codes (email,otp,purpose,expires_at) VALUES (?,?,'reset',?)");
    $stmt->bind_param("sss", $identifier, $otp, $expires);
    $stmt->execute();
    
    // Always send via email since there is no SMS gateway
    send_otp($user_email, $otp, 'email');

    $_SESSION['reset_expiry'] = time() + 300;
    $_SESSION['reset_otp']    = $otp;
    redirect('/blood_bank/auth/forgot_password.php?step=2&resent=1');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim(clean($conn, $_POST['identifier']));
    $is_phone   = preg_match('/^[6-9][0-9]{9}$/', $identifier);
    $is_email   = filter_var($identifier, FILTER_VALIDATE_EMAIL);

    if (empty($identifier) || (!$is_phone && !$is_email)) {
        $error = 'Please enter a valid email address or 10-digit mobile number.';
    } else {
        $query = $is_phone ? "SELECT id,name,email FROM users WHERE phone='$identifier'" : "SELECT id,name,email FROM users WHERE email='$identifier'";
        $user = $conn->query($query)->fetch_assoc();
        
        if (!$user) {
            // Don't reveal if user exists — security best practice
            $success = "If this account exists, an OTP has been sent to its registered email.";
        } else {
            $otp     = str_pad(random_int(0,999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', time() + 300);
            $conn->query("DELETE FROM otp_codes WHERE email='$identifier' AND purpose='reset'");
            $stmt = $conn->prepare("INSERT INTO otp_codes (email,otp,purpose,expires_at) VALUES (?,?,'reset',?)");
            $stmt->bind_param("sss", $identifier, $otp, $expires);
            $stmt->execute();
            
            // Always dispatch to the user's registered email address as fallback for SMS
            $target_email = $user['email'];
            $sent = send_otp($target_email, $otp, 'email');
            
            if (!$sent) {
                $error = 'Failed to send OTP. Please try again later.';
            } else {
                $_SESSION['reset_identifier'] = $identifier;
                $_SESSION['reset_is_phone']   = $is_phone;
                $_SESSION['reset_user_email'] = $target_email;
                $_SESSION['reset_expiry']     = time() + 300;
                $_SESSION['reset_otp']        = $otp; // Demo only
                redirect('/blood_bank/auth/forgot_password.php?step=2');
            }
        }
    }
}

// Step 2: Verify OTP
if (isset($_GET['step']) && $_GET['step'] == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // handled in reset_password.php
}

$step = (int)($_GET['step'] ?? 1);
$otp_remaining = max(0, ($_SESSION['reset_expiry'] ?? 0) - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Forgot Password | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
    <style>
        html,body{height:auto;overflow-y:auto;}
        .auth-page{overflow-y:auto!important;align-items:flex-start!important;padding:30px 16px 60px!important;}
        .hands-bg,.auth-overlay,.thread-svg{position:fixed!important;}
    </style>
</head>
<body class="auth-page">
<div class="hands-bg"><img src="/blood_bank/assets/hands_bg.jpg" class="hands-bg-img" alt=""></div>
<div class="auth-overlay"></div>
<svg class="thread-svg" viewBox="0 0 1440 900" preserveAspectRatio="none">
    <path class="thread-glow" d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
    <path class="thread-path"  d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
</svg>

<div class="auth-card" style="max-width:420px;">
    <img src="/blood_bank/assets/logo.png" class="auth-logo" alt="BloodSync">
    <div class="auth-site-name">Blood<span class="sync-text">Sync</span></div>

    <?php if ($step === 1): ?>
    <!-- Step 1: Email entry -->
    <div class="auth-tagline">Reset Your Password</div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small mb-3"><i class="bi bi-x-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" id="fpForm">
        <div class="mb-3">
            <label class="form-label fw-semibold small">Registered Email or Mobile <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                <input type="text" name="identifier" id="fpIdentifier" class="form-control"
                    placeholder="e.g. you@example.com or 9876543210" required autofocus>
            </div>
            <div id="fpIdentifierMsg" style="font-size:11.5px;margin-top:4px;"></div>
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold mb-3">
            <i class="bi bi-send me-2"></i>Send OTP
        </button>
    </form>

    <?php else: ?>
    <!-- Step 2: OTP verification -->
    <div class="auth-tagline">Enter OTP to Continue</div>

    <div class="text-center mb-3">
        <div style="font-size:2.4rem;margin-bottom:8px;">📩</div>
        <div class="fw-semibold" style="font-size:0.95rem;">Check your email</div>
        <div class="text-muted small mt-1">
            OTP sent to the registered email for <strong style="color:#c0392b;"><?= htmlspecialchars($_SESSION['reset_identifier'] ?? '') ?></strong>
        </div>
    </div>

    <?php if (!empty($_SESSION['reset_otp'])): ?>
    <div class="alert alert-info py-2 small text-center mb-3">
        <i class="bi bi-info-circle me-1"></i><strong>Demo OTP:</strong> <?= $_SESSION['reset_otp'] ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/blood_bank/auth/reset_password.php" id="otpForm">
        <input type="hidden" name="step" value="verify">
        <div class="otp-inputs mb-2">
            <?php for($i=0;$i<6;$i++): ?>
            <input type="text" name="otp_digits[]" maxlength="1" inputmode="numeric" class="otp-single" autocomplete="off">
            <?php endfor; ?>
        </div>
        <div class="otp-timer mb-3">
            Expires in <span class="timer-num" id="timerDisplay">5:00</span>
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold mb-2">
            <i class="bi bi-shield-check me-2"></i>Verify OTP
        </button>
    </form>
    <div class="d-flex justify-content-between mt-2">
        <a href="/blood_bank/auth/forgot_password.php?resend=1" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Resend OTP
        </a>
        <a href="/blood_bank/auth/forgot_password.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:10px;color:#adb5bd;font-size:12px;margin:14px 0;">
        <div style="flex:1;height:1px;background:#e9ecef;"></div>or<div style="flex:1;height:1px;background:#e9ecef;"></div>
    </div>
    <p class="text-center small text-muted mb-0">
        Remembered it? <a href="/blood_bank/auth/login.php" class="fw-semibold" style="color:#c0392b;">Login</a>
    </p>
</div>

<script>
// Identifier live validation
const fe=document.getElementById('fpIdentifier'),fm=document.getElementById('fpIdentifierMsg');
if(fe){fe.addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){fm.innerHTML='';this.classList.remove('is-valid','is-invalid');return;}
    const isPhone = /^[6-9][0-9]{9}$/.test(v);
    const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
    const ok = isPhone || isEmail;
    this.classList.toggle('is-valid',ok);this.classList.toggle('is-invalid',!ok);
    fm.style.color=ok?'#27ae60':'#c0392b';
    fm.innerHTML=ok?'<i class="bi bi-check-circle-fill me-1"></i>Valid format ✓':'<i class="bi bi-x-circle-fill me-1"></i>Enter a valid email or 10-digit mobile.';
});}

// OTP inputs
const oi=document.querySelectorAll('.otp-single');
oi.forEach((el,idx)=>{
    el.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,1);this.classList.toggle('filled',!!this.value);if(this.value&&idx<oi.length-1)oi[idx+1].focus();});
    el.addEventListener('keydown',function(e){if(e.key==='Backspace'&&!this.value&&idx>0)oi[idx-1].focus();});
    el.addEventListener('paste',function(e){const d=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');oi.forEach((inp,i)=>{if(d[i]){inp.value=d[i];inp.classList.add('filled');}});oi[Math.min(d.length,oi.length-1)].focus();e.preventDefault();});
});

// Timer
const te=document.getElementById('timerDisplay');
if(te){let r=<?= $otp_remaining ?: 300 ?>;function tick(){if(r<=0){te.textContent='Expired';te.style.color='#c0392b';return;}const m=Math.floor(r/60),s=r%60;te.textContent=m+':'+(s<10?'0':'')+s;if(r<=60)te.style.color='#c0392b';r--;setTimeout(tick,1000);}tick();}
</script>
</body>
</html>
