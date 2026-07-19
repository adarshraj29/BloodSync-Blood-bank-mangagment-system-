<?php
// ============================================================
// BloodSync - Reset Password (Step 2+3)
// Verify OTP → Set new password
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) redirect('/blood_bank/user/dashboard.php');

$error   = '';
$success = '';
$step    = 'verify'; // 'verify' or 'reset'

// ── Step: Verify OTP ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'verify') {
    $entered    = implode('', array_map('trim', (array)($_POST['otp_digits'] ?? [])));
    $identifier = $_SESSION['reset_identifier'] ?? '';

    if (empty($identifier)) {
        redirect('/blood_bank/auth/forgot_password.php');
    }

    $row = $conn->query(
        "SELECT * FROM otp_codes WHERE email='$identifier' AND purpose='reset' AND is_used=0
         ORDER BY created_at DESC LIMIT 1"
    )->fetch_assoc();

    if (!$row) {
        $error = 'OTP not found. Please go back and request a new one.';
    } elseif (time() > strtotime($row['expires_at'])) {
        $error = 'OTP has expired. Please request a new one.';
    } elseif ($row['attempts'] >= 3) {
        $error = 'Too many failed attempts. Please request a new OTP.';
        $conn->query("DELETE FROM otp_codes WHERE id={$row['id']}");
        unset($_SESSION['reset_identifier'], $_SESSION['reset_is_phone'], $_SESSION['reset_expiry'], $_SESSION['reset_otp']);
    } elseif ($row['otp'] !== $entered) {
        $conn->query("UPDATE otp_codes SET attempts = attempts + 1 WHERE id={$row['id']}");
        $rem = 2 - $row['attempts'];
        $error = $rem > 0 ? "Incorrect OTP. You have $rem attempt(s) left." : "Incorrect OTP. Too many failed attempts. Please request a new one.";
        if ($rem <= 0) {
            $conn->query("DELETE FROM otp_codes WHERE id={$row['id']}");
            unset($_SESSION['reset_identifier'], $_SESSION['reset_is_phone'], $_SESSION['reset_expiry'], $_SESSION['reset_otp']);
        }
    } else {
        // OTP valid — mark used and allow password reset
        $conn->query("UPDATE otp_codes SET is_used=1 WHERE id={$row['id']}");
        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_identifier'] = $identifier;
        $step = 'reset';
    }

    if ($error) {
        // Show OTP form again with error
        $otp_remaining = max(0, ($_SESSION['reset_expiry'] ?? 0) - time());
    }
}

// ── Step: Set new password ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'reset') {
    if (empty($_SESSION['reset_verified'])) redirect('/blood_bank/auth/forgot_password.php');

    $identifier = $_SESSION['reset_identifier'] ?? '';
    $is_phone   = $_SESSION['reset_is_phone'] ?? false;
    $newpass    = $_POST['new_pass']     ?? '';
    $confirm    = $_POST['confirm_pass'] ?? '';

    // Fetch current password hash
    $query   = $is_phone ? "SELECT password FROM users WHERE phone='$identifier'" : "SELECT password FROM users WHERE email='$identifier'";
    $userRow  = $conn->query($query)->fetch_assoc();

    $common   = ['password','123456','12345678','password123','qwerty','abc123','111111','bloodbank','bloodsync'];

    if (empty($newpass))
        $error = 'New password is required.';
    elseif (strlen($newpass) < 8)
        $error = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $newpass))
        $error = 'Must contain at least 1 uppercase letter (A-Z).';
    elseif (!preg_match('/[a-z]/', $newpass))
        $error = 'Must contain at least 1 lowercase letter (a-z).';
    elseif (!preg_match('/[0-9]/', $newpass))
        $error = 'Must contain at least 1 number (0-9).';
    elseif (!preg_match('/[!@#$%^&*]/', $newpass))
        $error = 'Must contain at least 1 special character (!@#$%^&*).';
    elseif (in_array(strtolower($newpass), $common))
        $error = 'Password is too common. Choose a stronger one.';
    elseif ($newpass !== $confirm)
        $error = 'Passwords do not match.';
    elseif ($userRow && password_verify($newpass, $userRow['password']))
        $error = 'New password cannot be the same as your current password.';
    else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $updateQuery = $is_phone ? "UPDATE users SET password='$hash' WHERE phone='$identifier'" : "UPDATE users SET password='$hash' WHERE email='$identifier'";
        $conn->query($updateQuery);
        // Clean up session
        unset($_SESSION['reset_identifier'], $_SESSION['reset_is_phone'], $_SESSION['reset_verified'],
              $_SESSION['reset_expiry'], $_SESSION['reset_otp']);
        $success = 'Password reset successfully! You can now login with your new password.';
    }

    if (!$success) $step = 'reset';
}

// Determine which form to show
if (!empty($_SESSION['reset_verified']) && $step !== 'verify') $step = 'reset';

$otp_remaining = max(0, ($_SESSION['reset_expiry'] ?? 0) - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Reset Password | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
    <style>
        html,body{height:auto;overflow-y:auto;}
        .auth-page{overflow-y:auto!important;align-items:flex-start!important;padding:30px 16px 60px!important;}
        .hands-bg,.auth-overlay,.thread-svg{position:fixed!important;}
        .field-msg{font-size:11.5px;margin-top:4px;display:flex;align-items:center;gap:4px;}
        .field-msg.ok{color:#27ae60;}.field-msg.err{color:#c0392b;}
        .strength-bar-wrap{height:5px;border-radius:4px;background:#e9ecef;overflow:hidden;margin-top:6px;}
        .strength-bar{height:100%;border-radius:4px;transition:width 0.4s,background 0.4s;width:0;}
        .pass-rules{margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:3px 12px;}
        .rule{font-size:11px;color:#adb5bd;display:flex;align-items:center;gap:4px;transition:color 0.2s;}
        .rule.pass{color:#27ae60;}.rule.fail{color:#c0392b;}
    </style>
</head>
<body class="auth-page">

<div class="hands-bg"><img src="/blood_bank/assets/hands_bg.jpg" class="hands-bg-img" alt=""></div>
<div class="auth-overlay"></div>
<svg class="thread-svg" viewBox="0 0 1440 900" preserveAspectRatio="none">
    <path class="thread-glow" d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
    <path class="thread-path"  d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
</svg>

<div class="auth-card" style="max-width:440px;">
    <img src="/blood_bank/assets/logo.png" class="auth-logo" alt="BloodSync">
    <div class="auth-site-name">Blood<span class="sync-text">Sync</span></div>

    <?php if ($success): ?>
    <!-- Success state -->
    <div class="text-center py-2">
        <div style="font-size:3rem;margin-bottom:12px;">✅</div>
        <div class="fw-bold fs-5 mb-2">Password Reset!</div>
        <p class="text-muted small mb-4">Your password has been updated successfully.</p>
        <a href="/blood_bank/auth/login.php" class="btn btn-danger w-100 fw-semibold py-2">
            <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
        </a>
    </div>

    <?php elseif ($step === 'reset'): ?>
    <!-- Set new password form -->
    <div class="auth-tagline">Set New Password</div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-x-circle me-2"></i><?= $error ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="resetForm" novalidate>
        <input type="hidden" name="step" value="reset">

        <div class="mb-3">
            <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="new_pass" id="newPass"
                    class="form-control" placeholder="Create strong password" maxlength="64" required>
                <span class="input-group-text" style="cursor:pointer;" onclick="tp('newPass',this)">
                    <i class="bi bi-eye-slash"></i>
                </span>
            </div>
            <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
            <div id="strengthLabel" style="font-size:11px;font-weight:600;margin-top:2px;"></div>
            <div class="pass-rules" id="passRules">
                <div class="rule" id="r-len"><i class="bi bi-circle"></i> 8+ characters</div>
                <div class="rule" id="r-up" ><i class="bi bi-circle"></i> Uppercase (A-Z)</div>
                <div class="rule" id="r-low"><i class="bi bi-circle"></i> Lowercase (a-z)</div>
                <div class="rule" id="r-num"><i class="bi bi-circle"></i> Number (0-9)</div>
                <div class="rule" id="r-spl"><i class="bi bi-circle"></i> Special (!@#$%^&*)</div>
                <div class="rule" id="r-com"><i class="bi bi-circle"></i> Not common</div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="confirm_pass" id="confPass"
                    class="form-control" placeholder="Repeat new password" maxlength="64" required>
                <span class="input-group-text" style="cursor:pointer;" onclick="tp('confPass',this)">
                    <i class="bi bi-eye-slash"></i>
                </span>
            </div>
            <div class="field-msg" id="confMsg"></div>
        </div>

        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold">
            <i class="bi bi-shield-lock me-2"></i>Reset Password
        </button>
    </form>

    <?php else: ?>
    <!-- OTP Verification form (shown if error on verify) -->
    <div class="auth-tagline">Verify OTP</div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-x-circle me-2"></i><?= $error ?>
    </div>
    <?php endif; ?>

    <div class="text-center mb-3">
        <div style="font-size:2.4rem;margin-bottom:8px;">📩</div>
        <div class="text-muted small">
            Enter OTP sent to <strong style="color:#c0392b;"><?= htmlspecialchars($_SESSION['reset_identifier'] ?? '') ?></strong>
        </div>
    </div>

    <?php if (!empty($_SESSION['reset_otp'])): ?>
    <div class="alert alert-info py-2 small text-center mb-3">
        <i class="bi bi-info-circle me-1"></i><strong>Demo OTP:</strong> <?= $_SESSION['reset_otp'] ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="step" value="verify">
        <div class="otp-inputs mb-2">
            <?php for($i=0;$i<6;$i++): ?>
            <input type="text" name="otp_digits[]" maxlength="1"
                inputmode="numeric" class="otp-single" autocomplete="off">
            <?php endfor; ?>
        </div>
        <div class="otp-timer mb-3">
            Expires in <span class="timer-num" id="timerDisplay">5:00</span>
        </div>
        <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold mb-2">
            <i class="bi bi-shield-check me-2"></i>Verify OTP
        </button>
    </form>
    <a href="/blood_bank/auth/forgot_password.php?resend=1" class="btn btn-outline-danger btn-sm w-100">
        <i class="bi bi-arrow-repeat me-1"></i>Resend OTP
    </a>
    <?php endif; ?>

    <p class="text-center small text-muted mt-3 mb-0">
        <a href="/blood_bank/auth/login.php" style="color:#c0392b;" class="fw-semibold">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>
    </p>
</div>

<script>
function tp(id,btn){const i=document.getElementById(id),ic=btn.querySelector('i');i.type=i.type==='password'?'text':'password';ic.className=i.type==='password'?'bi bi-eye-slash':'bi bi-eye';}

// Password strength
const common=['password','123456','12345678','password123','qwerty','abc123','111111','bloodbank','bloodsync'];
const pEl=document.getElementById('newPass');
const sBar=document.getElementById('strengthBar');
const sLbl=document.getElementById('strengthLabel');
function chkRule(id,v,rx){const el=document.getElementById(id);if(!el)return false;const ok=rx.test(v);el.className='rule '+(v.length>0?(ok?'pass':'fail'):'');el.querySelector('i').className=ok?'bi bi-check-circle-fill':(v.length>0?'bi bi-x-circle':'bi bi-circle');return ok;}
if(pEl){pEl.addEventListener('input',function(){
    const v=this.value;
    const r1=chkRule('r-len',v,/.{8,}/),r2=chkRule('r-up',v,/[A-Z]/),r3=chkRule('r-low',v,/[a-z]/);
    const r4=chkRule('r-num',v,/[0-9]/),r5=chkRule('r-spl',v,/[!@#$%^&*]/);
    const r6ok=!common.includes(v.toLowerCase());
    const r6El=document.getElementById('r-com');
    if(r6El){r6El.className='rule '+(v.length>0?(r6ok?'pass':'fail'):'');r6El.querySelector('i').className=v.length===0?'bi bi-circle':(r6ok?'bi bi-check-circle-fill':'bi bi-x-circle');}
    const sc=[r1,r2,r3,r4,r5,r6ok].filter(Boolean).length;
    const w=['0%','18%','36%','54%','72%','90%','100%'];
    const cl=['#ccc','#e74c3c','#e67e22','#f1c40f','#27ae60','#27ae60','#1e8449'];
    const lb=['','Very Weak','Weak','Fair','Strong','Very Strong','Excellent'];
    if(sBar){sBar.style.width=w[sc];sBar.style.background=cl[sc];}
    if(sLbl){sLbl.textContent=lb[sc];sLbl.style.color=cl[sc];}
    const cf=document.getElementById('confPass');
    if(cf&&cf.value)chkConf(cf,v);
});}

function chkConf(el,pv){const m=document.getElementById('confMsg');if(!el||!el.value){if(m)m.innerHTML='';return;}const ok=el.value===pv;el.classList.toggle('is-valid',ok);el.classList.toggle('is-invalid',!ok);if(m){m.className='field-msg '+(ok?'ok':'err');m.innerHTML=ok?'<i class="bi bi-check-circle-fill"></i> Passwords match ✓':'<i class="bi bi-x-circle-fill"></i> Passwords do not match.';}}
const cEl=document.getElementById('confPass');
if(cEl)cEl.addEventListener('input',function(){chkConf(this,document.getElementById('newPass')?.value||'');});

// OTP inputs
const oi=document.querySelectorAll('.otp-single');
oi.forEach((el,idx)=>{el.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,1);this.classList.toggle('filled',!!this.value);if(this.value&&idx<oi.length-1)oi[idx+1].focus();});el.addEventListener('keydown',function(e){if(e.key==='Backspace'&&!this.value&&idx>0)oi[idx-1].focus();});});

// Timer
const te=document.getElementById('timerDisplay');
if(te){let r=<?= $otp_remaining ?: 300 ?>;function tick(){if(r<=0){te.textContent='Expired';te.style.color='#c0392b';return;}const m=Math.floor(r/60),s=r%60;te.textContent=m+':'+(s<10?'0':'')+s;if(r<=60)te.style.color='#c0392b';r--;setTimeout(tick,1000);}tick();}
</script>
</body>
</html>
