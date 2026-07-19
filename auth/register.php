<?php
session_start();
require_once __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) redirect('/blood_bank/admin/dashboard.php');

$errors = [];
$old    = [];

// ── STEP 1: Submit form ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    $old   = $_POST;
    $name  = trim(clean($conn, $_POST['name']));
    $email = trim(clean($conn, $_POST['email']));
    $phone = trim(clean($conn, $_POST['phone']));
    $pass  = $_POST['password'];
    $conf  = $_POST['confirm'];
    $role  = trim(clean($conn, $_POST['role_type']));

    // NAME
    if (empty($name))
        $errors['name'] = 'Full name is required.';
    elseif (strlen($name) < 3)
        $errors['name'] = 'Name must be at least 3 characters.';
    elseif (strlen($name) > 100)
        $errors['name'] = 'Name must not exceed 100 characters.';
    elseif (!preg_match("/^[a-zA-Z\s]+$/", $name))
        $errors['name'] = 'Only letters and spaces allowed. No numbers or symbols.';

    // EMAIL
    if (empty($email))
        $errors['email'] = 'Email address is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address (e.g. name@gmail.com).';
    elseif (strlen($email) > 100)
        $errors['email'] = 'Email must not exceed 100 characters.';
    else {
        $dup = $conn->prepare("SELECT id FROM users WHERE email=?");
        $dup->bind_param("s", $email);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0)
            $errors['email'] = 'Email already registered. Please login instead.';
    }

    // PHONE
    if (empty($phone))
        $errors['phone'] = 'Phone number is required.';
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone))
        $errors['phone'] = 'Enter a valid 10-digit Indian mobile number starting with 6-9.';

    // PASSWORD
    $common = ['password','123456','12345678','password123','qwerty','abc123','111111','bloodbank','bloodsync','letmein','welcome'];
    if (empty($pass))
        $errors['password'] = 'Password is required.';
    elseif (strlen($pass) < 8)
        $errors['password'] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $pass))
        $errors['password'] = 'Must contain at least 1 uppercase letter (A-Z).';
    elseif (!preg_match('/[a-z]/', $pass))
        $errors['password'] = 'Must contain at least 1 lowercase letter (a-z).';
    elseif (!preg_match('/[0-9]/', $pass))
        $errors['password'] = 'Must contain at least 1 number (0-9).';
    elseif (!preg_match('/[!@#$%^&*]/', $pass))
        $errors['password'] = 'Must contain at least 1 special character (!@#$%^&*).';
    elseif (in_array(strtolower($pass), $common))
        $errors['password'] = 'Password is too common. Choose a stronger one.';

    // CONFIRM PASSWORD
    if (empty($conf))
        $errors['confirm'] = 'Please confirm your password.';
    elseif ($pass !== $conf)
        $errors['confirm'] = 'Passwords do not match.';

    // ROLE
    if (!in_array($role, ['donor', 'receiver']))
        $errors['role_type'] = 'Please select your role (Donor or Receiver).';

    if (empty($errors)) {
        // Generate OTP
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300);

        $conn->query("DELETE FROM otp_codes WHERE email='$email'");
        $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp, expires_at) VALUES (?,?,?)");
        $stmt->bind_param("sss", $email, $otp, $expires);
        $stmt->execute();

        // Only proceed if email was actually sent
        $sent = send_otp($email, $otp, 'email');

        if (!$sent) {
            // Clean up and show a real error — do NOT redirect to OTP screen
            $conn->query("DELETE FROM otp_codes WHERE email='$email'");
            $errors['email'] = 'Failed to send OTP to this email. Please check the address and try again.';
        } else {
            $_SESSION['reg_pending'] = [
                'name'      => $name,
                'email'     => $email,
                'phone'     => $phone,
                'password'  => password_hash($pass, PASSWORD_DEFAULT),
                'role_type' => $role,
                'otp'       => $otp
            ];
            $_SESSION['reg_step']   = 2;
            $_SESSION['otp_email']  = $email;
            $_SESSION['otp_expiry'] = time() + 300;
            redirect('/blood_bank/auth/register.php');
        }
    }
}

// ── STEP 2: Verify OTP ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    $entered = implode('', array_map('trim', (array)($_POST['otp_digits'] ?? [])));
    $email   = $_SESSION['otp_email'] ?? '';
    $row     = $conn->query("SELECT * FROM otp_codes WHERE email='$email' AND is_used=0 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

    if (!$row) {
        $errors['otp'] = 'OTP not found. Please go back and try again.';
    } elseif (time() > strtotime($row['expires_at'])) {
        $errors['otp'] = 'OTP has expired. Click Resend OTP.';
    } elseif ($row['attempts'] >= 3) {
        $errors['otp'] = 'Too many failed attempts. Please resend OTP.';
        $conn->query("DELETE FROM otp_codes WHERE id={$row['id']}");
        unset($_SESSION['reg_pending']['otp']);
    } elseif ($row['otp'] !== $entered) {
        $conn->query("UPDATE otp_codes SET attempts = attempts + 1 WHERE id={$row['id']}");
        $rem = 2 - $row['attempts'];
        $errors['otp'] = $rem > 0 ? "Incorrect OTP. You have $rem attempt(s) left." : "Incorrect OTP. Too many failed attempts. Please resend.";
        if ($rem <= 0) {
            $conn->query("DELETE FROM otp_codes WHERE id={$row['id']}");
            unset($_SESSION['reg_pending']['otp']);
        }
    } else {
        $conn->query("UPDATE otp_codes SET is_used=1 WHERE id={$row['id']}");
        $p    = $_SESSION['reg_pending'];
        $hash = $p['password'];
        $rt   = $p['role_type'];
        $ph   = $p['phone'];
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, role_type, phone, email_verified) VALUES (?,?,?,'user',?,?,1)");
        $stmt->bind_param("sssss", $p['name'], $p['email'], $hash, $rt, $ph);
        $stmt->execute();
        $uid = $conn->insert_id;

        $_SESSION['user_id']   = $uid;
        $_SESSION['name']      = $p['name'];
        $_SESSION['role']      = 'user';
        $_SESSION['role_type'] = $rt;
        unset($_SESSION['reg_pending'], $_SESSION['reg_step'], $_SESSION['otp_email'], $_SESSION['otp_expiry']);
        redirect('/blood_bank/user/dashboard.php?welcome=1');
    }
}

// ── RESEND OTP ───────────────────────────────────────────────
if (isset($_GET['resend']) && isset($_SESSION['otp_email'])) {
    $email   = $_SESSION['otp_email'];
    $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300);
    $conn->query("DELETE FROM otp_codes WHERE email='$email'");
    $stmt = $conn->prepare("INSERT INTO otp_codes (email, otp, expires_at) VALUES (?,?,?)");
    $stmt->bind_param("sss", $email, $otp, $expires);
    $stmt->execute();

    // Resend via email only (no SMS gateway available)
    send_otp($email, $otp, 'email');

    if (isset($_SESSION['reg_pending'])) $_SESSION['reg_pending']['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 300;
    redirect('/blood_bank/auth/register.php?resent=1');
}

// ── BACK ─────────────────────────────────────────────────────
if (isset($_GET['back'])) {
    unset($_SESSION['reg_step'], $_SESSION['reg_pending'], $_SESSION['otp_email'], $_SESSION['otp_expiry']);
    redirect('/blood_bank/auth/register.php');
}

$current_step  = $_SESSION['reg_step']   ?? 1;
$otp_remaining = max(0, ($_SESSION['otp_expiry'] ?? 0) - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | BloodSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/blood_bank/assets/style.css" rel="stylesheet">
    <style>
        html, body { height: auto; overflow-y: auto; }
        .auth-page { overflow-y: auto !important; overflow-x: hidden !important; align-items: flex-start !important; padding: 30px 16px 80px !important; min-height: 100vh; }
        .hands-bg, .auth-overlay, .thread-svg { position: fixed !important; }
        .auth-card { position: relative; z-index: 10; margin: 0 auto; }
        /* ── fix card scroll so button is always visible ── */

        .auth-card  { margin: 0 auto; }

        /* validation feedback inline */
        .field-msg {
            font-size: 11.5px; margin-top: 4px; min-height: 16px;
            display: flex; align-items: center; gap: 4px;
        }
        .field-msg.ok   { color: #27ae60; }
        .field-msg.err  { color: #c0392b; }
        .field-msg.hint { color: #6c757d; }

        /* password rules checklist */
        .pass-rules { margin-top: 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 3px 12px; }
        .rule { font-size: 11px; color: #adb5bd; display: flex; align-items: center; gap: 5px; transition: color 0.25s; }
        .rule.pass { color: #27ae60; }
        .rule.fail { color: #c0392b; }
        .rule i { font-size: 12px; }

        /* char counter */
        .char-count { font-size: 11px; color: #adb5bd; float: right; }

        /* role cards */
        .role-selector { display: flex; gap: 10px; }
        .role-card { flex:1; padding:12px 6px; border-radius:12px; border:2px solid #e9ecef;
            cursor:pointer; text-align:center; transition:all 0.25s; font-size:0.78rem;
            font-weight:600; color:#6c757d; user-select:none; }
        .role-card:hover { border-color:#c0392b; color:#c0392b; background:#fff9f8; }
        .role-card.selected { border-color:#c0392b; background:#fff5f4; color:#c0392b; }
        .role-card i { font-size:1.4rem; display:block; margin-bottom:4px; }

        /* step dots */
        .step-indicator { display:flex; gap:6px; justify-content:center; margin-bottom:18px; }
        .step-dot { width:8px; height:8px; border-radius:50%; background:#e9ecef; transition:all 0.3s; }
        .step-dot.active { background:#c0392b; width:24px; border-radius:4px; }

        /* OTP inputs */
        .otp-inputs { display:flex; gap:10px; justify-content:center; margin:16px 0; }
        .otp-inputs input { width:46px; height:52px; text-align:center; font-size:1.4rem;
            font-weight:700; border:2px solid #e9ecef; border-radius:10px;
            transition:all 0.2s; outline:none; }
        .otp-inputs input:focus { border-color:#c0392b; box-shadow:0 0 0 3px rgba(192,57,43,0.12); }
        .otp-inputs input.filled { border-color:#c0392b; background:#fff5f4; }

        /* submit button always visible */
        .submit-wrap { margin-top: 20px; }
        .btn-register { background: linear-gradient(135deg,#c0392b,#e74c3c);
            border:none; color:#fff; font-weight:700; font-size:0.95rem;
            padding:13px; border-radius:12px; width:100%; cursor:pointer;
            transition:all 0.3s; box-shadow:0 4px 14px rgba(192,57,43,0.4); }
        .btn-register:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(192,57,43,0.5); }
        .btn-register:active { transform:translateY(0); }

        /* strength bar */
        .strength-bar-wrap { height:5px; border-radius:4px; background:#e9ecef; overflow:hidden; margin-top:6px; }
        .strength-bar { height:100%; border-radius:4px; transition:width 0.4s ease,background 0.4s ease; width:0; }
        .strength-label { font-size:11px; font-weight:600; margin-top:3px; }
    </style>
</head>
<body class="auth-page">

<!-- Animated hands background -->
<div class="hands-bg">
    <img src="/blood_bank/assets/hands_bg.jpg" class="hands-bg-img" alt="">
</div>
<div class="auth-overlay"></div>
<svg class="thread-svg" viewBox="0 0 1440 900" preserveAspectRatio="none">
    <path class="thread-glow" d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
    <path class="thread-path"  d="M 100,800 C 320,580 520,760 720,420 C 920,100 1120,580 1340,180"/>
</svg>

<!-- ══════════════════════════════════════════════════════════
     AUTH CARD
══════════════════════════════════════════════════════════ -->
<div class="auth-card" style="max-width:480px;">

    <!-- Logo & brand -->
    <img src="/blood_bank/assets/logo.png" class="auth-logo" alt="BloodSync Logo">
    <div class="auth-site-name">Blood<span class="sync-text">Sync</span></div>
    <div class="auth-tagline">Create Your Account</div>

    <!-- Step indicator -->
    <div class="step-indicator">
        <div class="step-dot <?= $current_step==1?'active':'' ?>"></div>
        <div class="step-dot <?= $current_step==2?'active':'' ?>"></div>
    </div>

<?php if ($current_step == 1): ?>
<!-- ══ STEP 1 — REGISTRATION FORM ══════════════════════════ -->

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong><?= count($errors) ?> error(s) found.</strong> Please fix them below.
    </div>
    <?php endif; ?>

    <form method="POST" id="regForm" novalidate autocomplete="off">
        <input type="hidden" name="step1" value="1">

        <!-- ── Role Selection ─────────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">I am registering as <span class="text-danger">*</span></label>
            <div class="role-selector">
                <label class="role-card <?= (($old['role_type']??'')==='donor')?'selected':'' ?>" id="roleCardDonor">
                    <input type="radio" name="role_type" value="donor" <?= (($old['role_type']??'')==='donor')?'checked':'' ?>>
                    <i class="bi bi-droplet-fill"></i>Donor
                </label>
                <label class="role-card <?= (($old['role_type']??'')==='receiver')?'selected':'' ?>" id="roleCardReceiver">
                    <input type="radio" name="role_type" value="receiver" <?= (($old['role_type']??'')==='receiver')?'checked':'' ?>>
                    <i class="bi bi-person-heart"></i>Receiver
                </label>
            </div>
            <div class="field-msg err" id="roleMsg">
                <?= isset($errors['role_type']) ? '<i class="bi bi-x-circle"></i>'.$errors['role_type'] : '' ?>
            </div>
        </div>

        <!-- ── Full Name ───────────────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">
                Full Name <span class="text-danger">*</span>
                <span class="char-count" id="nameCount">0/100</span>
            </label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" name="name" id="regName"
                    class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                    placeholder="e.g. Rahul Kumar"
                    value="<?= htmlspecialchars($old['name']??'') ?>"
                    maxlength="100" autocomplete="off">
            </div>
            <div class="field-msg <?= isset($errors['name'])?'err':'hint' ?>" id="nameMsg">
                <?php if (isset($errors['name'])): ?>
                    <i class="bi bi-x-circle"></i><?= $errors['name'] ?>
                <?php else: ?>
                    <i class="bi bi-info-circle"></i>Letters and spaces only. Min 3 characters.
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Email ──────────────────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" id="regEmail"
                    class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                    placeholder="you@gmail.com"
                    value="<?= htmlspecialchars($old['email']??'') ?>"
                    maxlength="100" autocomplete="off">
            </div>
            <div class="field-msg <?= isset($errors['email'])?'err':'hint' ?>" id="emailMsg">
                <?php if (isset($errors['email'])): ?>
                    <i class="bi bi-x-circle"></i><?= $errors['email'] ?>
                <?php else: ?>
                    <i class="bi bi-info-circle"></i>A 6-digit OTP will be sent to verify this email.
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Phone ──────────────────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">Mobile Number <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text fw-semibold" style="font-size:0.85rem;">+91</span>
                <input type="tel" name="phone" id="regPhone"
                    class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>"
                    placeholder="10-digit mobile number"
                    value="<?= htmlspecialchars($old['phone']??'') ?>"
                    maxlength="10" inputmode="numeric" autocomplete="off">
            </div>
            <div class="field-msg <?= isset($errors['phone'])?'err':'hint' ?>" id="phoneMsg">
                <?php if (isset($errors['phone'])): ?>
                    <i class="bi bi-x-circle"></i><?= $errors['phone'] ?>
                <?php else: ?>
                    <i class="bi bi-info-circle"></i>Must start with 6, 7, 8 or 9. Exactly 10 digits.
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Password ───────────────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="regPass"
                    class="form-control <?= isset($errors['password'])?'is-invalid':'' ?>"
                    placeholder="Create a strong password"
                    maxlength="64" autocomplete="new-password">
                <span class="input-group-text" style="cursor:pointer;" onclick="togglePass('regPass',this)">
                    <i class="bi bi-eye-slash"></i>
                </span>
            </div>
            <!-- Strength bar -->
            <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
            <div class="strength-label" id="strengthLabel"></div>
            <!-- Rules checklist -->
            <div class="pass-rules" id="passRules">
                <div class="rule" id="r-len"><i class="bi bi-circle"></i> 8+ characters</div>
                <div class="rule" id="r-up" ><i class="bi bi-circle"></i> 1 uppercase (A-Z)</div>
                <div class="rule" id="r-low"><i class="bi bi-circle"></i> 1 lowercase (a-z)</div>
                <div class="rule" id="r-num"><i class="bi bi-circle"></i> 1 number (0-9)</div>
                <div class="rule" id="r-spl"><i class="bi bi-circle"></i> 1 special (!@#$%^&*)</div>
                <div class="rule" id="r-com"><i class="bi bi-circle"></i> Not a common password</div>
            </div>
            <?php if (isset($errors['password'])): ?>
            <div class="field-msg err mt-1"><i class="bi bi-x-circle"></i><?= $errors['password'] ?></div>
            <?php endif; ?>
        </div>

        <!-- ── Confirm Password ───────────────────────── -->
        <div class="mb-3">
            <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="confirm" id="regConf"
                    class="form-control <?= isset($errors['confirm'])?'is-invalid':'' ?>"
                    placeholder="Re-enter your password"
                    maxlength="64" autocomplete="new-password">
                <span class="input-group-text" style="cursor:pointer;" onclick="togglePass('regConf',this)">
                    <i class="bi bi-eye-slash"></i>
                </span>
            </div>
            <div class="field-msg <?= isset($errors['confirm'])?'err':'hint' ?>" id="confMsg">
                <?php if (isset($errors['confirm'])): ?>
                    <i class="bi bi-x-circle"></i><?= $errors['confirm'] ?>
                <?php else: ?>
                    <i class="bi bi-info-circle"></i>Must match the password above.
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Submit ─────────────────────────────────── -->
        <div class="submit-wrap">
            <button type="submit" class="btn-register" id="submitBtn">
                <i class="bi bi-send me-2"></i>Send OTP &amp; Continue →
            </button>
        </div>
    </form>

    <div style="display:flex;align-items:center;gap:10px;color:#adb5bd;font-size:12px;margin:14px 0;">
        <div style="flex:1;height:1px;background:#e9ecef;"></div>or<div style="flex:1;height:1px;background:#e9ecef;"></div>
    </div>
    <p class="text-center small text-muted mb-0">
        Already have an account?
        <a href="/blood_bank/auth/login.php" class="fw-semibold" style="color:#c0392b;">Login here</a>
    </p>

<?php else: ?>
<!-- ══ STEP 2 — OTP VERIFICATION ══════════════════════════ -->

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-x-circle me-2"></i><?= $errors['otp'] ?? 'Invalid OTP.' ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['resent'])): ?>
    <div class="alert alert-success py-2 small mb-3 text-center">
        <i class="bi bi-check-circle me-1"></i>New OTP sent successfully!
    </div>
    <?php endif; ?>

    <div class="text-center mb-3">
        <div style="font-size:2.6rem;margin-bottom:8px;">📩</div>
        <div class="fw-bold" style="font-size:1rem;">Verify Your Email</div>
        <div class="text-muted small mt-1">
            We sent a 6-digit OTP to<br>
            <strong style="color:#c0392b;"><?= htmlspecialchars($_SESSION['otp_email'] ?? '') ?></strong>
        </div>
    </div>

    <form method="POST" id="otpForm">
        <input type="hidden" name="step2" value="1">
        <div class="otp-inputs">
            <?php for ($i = 0; $i < 6; $i++): ?>
            <input type="text" name="otp_digits[]" maxlength="1"
                inputmode="numeric" class="otp-single" autocomplete="off">
            <?php endfor; ?>
        </div>

        <?php if (isset($_SESSION['reg_pending']['otp'])): ?>
        <div class="alert alert-info py-2 small text-center mb-3" style="background:#e8f4ff;color:#0c4a6e;">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Demo OTP:</strong> <?= $_SESSION['reg_pending']['otp'] ?>
            <small class="text-muted">(Remove this in production)</small>
        </div>
        <?php endif; ?>

        <div style="text-align:center;font-size:12px;color:#6c757d;margin-bottom:14px;">
            OTP expires in <span id="timerDisplay" style="color:#c0392b;font-weight:700;">5:00</span>
        </div>

        <button type="submit" class="btn-register">
            <i class="bi bi-shield-check me-2"></i>Verify OTP &amp; Create Account
        </button>
    </form>

    <div class="d-flex justify-content-between mt-3">
        <a href="?resend=1" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Resend OTP
        </a>
        <a href="?back=1" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Go Back
        </a>
    </div>

<?php endif; ?>
</div><!-- /auth-card -->

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT — REAL-TIME VALIDATION
══════════════════════════════════════════════════════════ -->
<script>
// ── Helpers ───────────────────────────────────────────────────
function setOk(inputEl, msgEl, text) {
    inputEl.classList.add('is-valid');
    inputEl.classList.remove('is-invalid');
    if (msgEl) { msgEl.className='field-msg ok'; msgEl.innerHTML='<i class="bi bi-check-circle-fill"></i> '+text; }
}
function setErr(inputEl, msgEl, text) {
    inputEl.classList.add('is-invalid');
    inputEl.classList.remove('is-valid');
    if (msgEl) { msgEl.className='field-msg err'; msgEl.innerHTML='<i class="bi bi-x-circle-fill"></i> '+text; }
}
function setHint(inputEl, msgEl, text) {
    inputEl.classList.remove('is-valid','is-invalid');
    if (msgEl) { msgEl.className='field-msg hint'; msgEl.innerHTML='<i class="bi bi-info-circle"></i> '+text; }
}
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    inp.type  = inp.type==='password' ? 'text' : 'password';
    ico.className = inp.type==='password' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

// ── Role cards ────────────────────────────────────────────────
document.querySelectorAll('.role-card').forEach(card => {
    card.addEventListener('click', function(){
        document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input').checked = true;
        document.getElementById('roleMsg').innerHTML = '';
    });
});

// ── Name validation ───────────────────────────────────────────
const nameEl  = document.getElementById('regName');
const nameMsg = document.getElementById('nameMsg');
const nameCnt = document.getElementById('nameCount');
if (nameEl) {
    nameEl.addEventListener('input', function(){
        const v = this.value;
        nameCnt.textContent = v.length + '/100';
        if (v.length === 0) {
            setHint(this, nameMsg, 'Letters and spaces only. Min 3 characters.');
            return;
        }
        if (v.trim().length < 3)
            setErr(this, nameMsg, 'Name must be at least 3 characters.');
        else if (v.length > 100)
            setErr(this, nameMsg, 'Name must not exceed 100 characters.');
        else if (!/^[a-zA-Z\s]+$/.test(v))
            setErr(this, nameMsg, 'Only letters and spaces allowed. No numbers or symbols.');
        else
            setOk(this, nameMsg, 'Looks great!');
    });
}

// ── Email validation ──────────────────────────────────────────
const emailEl  = document.getElementById('regEmail');
const emailMsg = document.getElementById('emailMsg');
if (emailEl) {
    emailEl.addEventListener('input', function(){
        const v = this.value.trim();
        if (v.length === 0) {
            setHint(this, emailMsg, 'A 6-digit OTP will be sent to verify this email.');
            return;
        }
        const emailRegex = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(v))
            setErr(this, emailMsg, 'Enter a valid email (e.g. name@gmail.com).');
        else if (v.length > 100)
            setErr(this, emailMsg, 'Email must not exceed 100 characters.');
        else
            setOk(this, emailMsg, 'Valid email format ✓');
    });
}

// ── Phone validation ──────────────────────────────────────────
const phoneEl  = document.getElementById('regPhone');
const phoneMsg = document.getElementById('phoneMsg');
if (phoneEl) {
    // Allow digits only
    phoneEl.addEventListener('keypress', e => { if (!/[0-9]/.test(e.key)) e.preventDefault(); });
    phoneEl.addEventListener('input', function(){
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
        const v = this.value;
        if (v.length === 0) {
            setHint(this, phoneMsg, 'Must start with 6, 7, 8 or 9. Exactly 10 digits.');
            return;
        }
        if (v.length < 10)
            setErr(this, phoneMsg, `Enter ${10 - v.length} more digit(s). Need exactly 10.`);
        else if (!/^[6-9]/.test(v))
            setErr(this, phoneMsg, 'Number must start with 6, 7, 8 or 9.');
        else
            setOk(this, phoneMsg, 'Valid Indian mobile number ✓');
    });
}

// ── Password strength + rules ─────────────────────────────────
const passEl    = document.getElementById('regPass');
const strengthB = document.getElementById('strengthBar');
const strengthL = document.getElementById('strengthLabel');
const common    = ['password','123456','12345678','password123','qwerty','abc123','111111','bloodbank','bloodsync','letmein','welcome'];

function checkRule(id, pass, regex) {
    const el = document.getElementById(id);
    if (!el) return false;
    const ok = regex.test(pass);
    el.className   = 'rule ' + (ok ? 'pass' : (pass.length > 0 ? 'fail' : ''));
    el.querySelector('i').className = ok ? 'bi bi-check-circle-fill' : (pass.length > 0 ? 'bi bi-x-circle' : 'bi bi-circle');
    return ok;
}

if (passEl) {
    passEl.addEventListener('input', function(){
        const v    = this.value;
        const r1   = checkRule('r-len', v, /.{8,}/);
        const r2   = checkRule('r-up',  v, /[A-Z]/);
        const r3   = checkRule('r-low', v, /[a-z]/);
        const r4   = checkRule('r-num', v, /[0-9]/);
        const r5   = checkRule('r-spl', v, /[!@#$%^&*]/);
        const r6el = document.getElementById('r-com');
        const r6ok = !common.includes(v.toLowerCase());
        if (r6el) {
            r6el.className = 'rule ' + (v.length>0 ? (r6ok?'pass':'fail') : '');
            r6el.querySelector('i').className = v.length===0 ? 'bi bi-circle' : (r6ok ? 'bi bi-check-circle-fill' : 'bi bi-x-circle');
        }

        const score  = [r1,r2,r3,r4,r5,r6ok].filter(Boolean).length;
        const pcts   = ['0%','18%','36%','54%','72%','90%','100%'];
        const colors = ['#ccc','#e74c3c','#e67e22','#f39c12','#27ae60','#27ae60','#1e8449'];
        const labels = ['','Very Weak 😟','Weak 😐','Fair 🙂','Strong 💪','Very Strong 🔒','Excellent 🛡️'];
        if (strengthB) { strengthB.style.width=pcts[score]; strengthB.style.background=colors[score]; }
        if (strengthL) { strengthL.textContent=labels[score]; strengthL.style.color=colors[score]; }

        // update confirm match
        const cf = document.getElementById('regConf');
        const cm = document.getElementById('confMsg');
        if (cf && cf.value) validateConfirm(cf, cm, v);
    });
}

// ── Confirm password ──────────────────────────────────────────
const confEl  = document.getElementById('regConf');
const confMsg = document.getElementById('confMsg');
function validateConfirm(el, msg, passVal) {
    if (!el || !el.value) { setHint(el, msg, 'Must match the password above.'); return; }
    if (el.value === passVal)
        setOk(el, msg, 'Passwords match ✓');
    else
        setErr(el, msg, 'Passwords do not match.');
}
if (confEl) {
    confEl.addEventListener('input', function(){
        const pv = document.getElementById('regPass').value;
        validateConfirm(this, confMsg, pv);
    });
}

// ── Final submit guard ────────────────────────────────────────
const regForm = document.getElementById('regForm');
if (regForm) {
    regForm.addEventListener('submit', function(e){
        const errs = [];
        const name  = document.getElementById('regName').value.trim();
        const email = document.getElementById('regEmail').value.trim();
        const phone = document.getElementById('regPhone').value.trim();
        const pass  = document.getElementById('regPass').value;
        const conf  = document.getElementById('regConf').value;
        const role  = document.querySelector('input[name="role_type"]:checked');

        if (!role)                                   errs.push('Please select your role (Donor or Receiver).');
        if (!name || name.length < 3)               errs.push('Full name must be at least 3 characters.');
        if (!/^[a-zA-Z\s]+$/.test(name))            errs.push('Name can only contain letters and spaces.');
        if (!/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(email)) errs.push('Enter a valid email address.');
        if (!/^[6-9][0-9]{9}$/.test(phone))         errs.push('Enter a valid 10-digit mobile number starting with 6-9.');
        if (pass.length < 8)                        errs.push('Password must be at least 8 characters.');
        if (!/[A-Z]/.test(pass))                    errs.push('Password must contain at least 1 uppercase letter.');
        if (!/[a-z]/.test(pass))                    errs.push('Password must contain at least 1 lowercase letter.');
        if (!/[0-9]/.test(pass))                    errs.push('Password must contain at least 1 number.');
        if (!/[!@#$%^&*]/.test(pass))               errs.push('Password must contain at least 1 special character (!@#$%^&*).');
        if (pass !== conf)                           errs.push('Passwords do not match.');

        if (errs.length > 0) {
            e.preventDefault();
            let box = document.getElementById('jsErrBox');
            if (!box) { box = document.createElement('div'); box.id='jsErrBox'; regForm.prepend(box); }
            box.className = 'alert alert-danger py-2 small mb-3';
            box.innerHTML = '<strong><i class="bi bi-exclamation-triangle me-2"></i>Please fix:</strong><ul class="mb-0 mt-1 ps-3">'
                + errs.map(r=>`<li>${r}</li>`).join('') + '</ul>';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending OTP...';
        }
    });
}

// ── OTP inputs ────────────────────────────────────────────────
const otpIn = document.querySelectorAll('.otp-single');
otpIn.forEach((el, idx) => {
    el.addEventListener('input', function(){
        this.value = this.value.replace(/\D/g,'').slice(0,1);
        this.classList.toggle('filled', !!this.value);
        if (this.value && idx < otpIn.length-1) otpIn[idx+1].focus();
    });
    el.addEventListener('keydown', function(e){
        if (e.key==='Backspace' && !this.value && idx > 0) otpIn[idx-1].focus();
        if (e.key==='ArrowLeft'  && idx > 0)               otpIn[idx-1].focus();
        if (e.key==='ArrowRight' && idx < otpIn.length-1)  otpIn[idx+1].focus();
    });
    el.addEventListener('paste', function(e){
        const d = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
        otpIn.forEach((inp,i) => { if(d[i]){ inp.value=d[i]; inp.classList.add('filled'); }});
        otpIn[Math.min(d.length, otpIn.length-1)].focus();
        e.preventDefault();
    });
});

// ── OTP countdown timer ───────────────────────────────────────
const timerEl = document.getElementById('timerDisplay');
if (timerEl) {
    let rem = <?= $otp_remaining ?? 300 ?>;
    function tick() {
        if (rem <= 0) { timerEl.textContent='Expired'; return; }
        const m = Math.floor(rem/60), s = rem%60;
        timerEl.textContent = m + ':' + (s<10?'0':'') + s;
        if (rem <= 60) timerEl.style.color='#e74c3c';
        rem--; setTimeout(tick, 1000);
    }
    tick();
}
</script>
</body>
</html>
