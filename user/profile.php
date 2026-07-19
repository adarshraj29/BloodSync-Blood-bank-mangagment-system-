<?php
// ============================================================
// BloodSync - User Profile Management
// Features: Edit name, city, state, upload profile picture
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title = 'My Profile';
$uid  = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

$errors  = [];
$success = '';

// ── Handle profile picture upload ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file     = $_FILES['profile_pic'];
    $allowed  = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if ($file['error'] === 0) {
        if (!in_array($file['type'], $allowed))
            $errors['pic'] = 'Only JPG, PNG, GIF or WEBP images allowed.';
        elseif ($file['size'] > $max_size)
            $errors['pic'] = 'Image must be smaller than 2MB.';
        else {
            // Create upload dir if not exists
            $upload_dir = __DIR__ . '/../assets/uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $uid . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // Delete old pic
                if (!empty($user['profile_pic'])) {
                    $old = $upload_dir . $user['profile_pic'];
                    if (file_exists($old)) unlink($old);
                }
                $conn->query("UPDATE users SET profile_pic='$filename' WHERE id=$uid");
                $user['profile_pic'] = $filename;
                $success = 'Profile picture updated successfully!';
            } else {
                $errors['pic'] = 'Upload failed. Please try again.';
            }
        }
    }
}

// ── Handle profile info update ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim(clean($conn, $_POST['name']));
    $city    = trim(clean($conn, $_POST['city']));
    $state   = trim(clean($conn, $_POST['state']));
    $address = trim(clean($conn, $_POST['address']));

    // Validate name
    if (empty($name))
        $errors['name'] = 'Name is required.';
    elseif (strlen($name) < 3)
        $errors['name'] = 'Name must be at least 3 characters.';
    elseif (!preg_match("/^[a-zA-Z\s]+$/", $name))
        $errors['name'] = 'Name can only contain letters and spaces.';

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET name=?, city=?, state=?, address=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $city, $state, $address, $uid);
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $user['name']     = $name;
            $user['city']     = $city;
            $user['state']    = $state;
            $user['address']  = $address;
            $success = 'Profile updated successfully!';
        } else {
            $errors['general'] = 'Update failed. Please try again.';
        }
    }
}

// ── Handle password change (with same-password check) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_pass'];
    $newpass = $_POST['new_pass'];
    $confirm = $_POST['confirm_pass'];
    $common  = ['password','123456','12345678','password123','qwerty','abc123','111111','bloodbank','bloodsync'];

    if (!password_verify($current, $user['password']))
        $errors['pass'] = 'Current password is incorrect.';
    elseif (password_verify($newpass, $user['password']))
        $errors['pass'] = 'New password cannot be the same as your current password.';
    elseif (strlen($newpass) < 8)
        $errors['pass'] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $newpass))
        $errors['pass'] = 'Must contain at least 1 uppercase letter (A-Z).';
    elseif (!preg_match('/[a-z]/', $newpass))
        $errors['pass'] = 'Must contain at least 1 lowercase letter (a-z).';
    elseif (!preg_match('/[0-9]/', $newpass))
        $errors['pass'] = 'Must contain at least 1 number (0-9).';
    elseif (!preg_match('/[!@#$%^&*]/', $newpass))
        $errors['pass'] = 'Must contain at least 1 special character (!@#$%^&*).';
    elseif (in_array(strtolower($newpass), $common))
        $errors['pass'] = 'Password is too common. Choose a stronger one.';
    elseif ($newpass !== $confirm)
        $errors['pass'] = 'Passwords do not match.';
    else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
        $success = 'Password changed successfully!';
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.user-animated-bg{position:fixed;inset:0;z-index:-1;background:linear-gradient(135deg,#0d0000 0%,#1a0505 40%,#0a0000 100%);overflow:hidden;}
.blood-drop{position:absolute;border-radius:50% 50% 50% 0;transform:rotate(-45deg);animation:floatDrop linear infinite;opacity:0;}
@keyframes floatDrop{0%{transform:rotate(-45deg) translateY(0);opacity:0;}10%{opacity:0.5;}90%{opacity:0.2;}100%{transform:rotate(-45deg) translateY(-110vh);opacity:0;}}
.ub-wrap{max-width:900px;margin:0 auto;padding:24px 20px 80px;position:relative;z-index:1;}
.glass-card{background:rgba(255,255,255,0.97);border-radius:18px;border:1px solid rgba(255,255,255,0.3);box-shadow:0 8px 32px rgba(0,0,0,0.18);overflow:hidden;transition:transform 0.3s,box-shadow 0.3s;margin-bottom:20px;}
.glass-card:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(0,0,0,0.22);}
.glass-card .card-header{background:linear-gradient(135deg,#c0392b,#e74c3c);color:white;padding:14px 20px;font-weight:700;font-size:0.95rem;border:none;}
.section-heading{color:white;font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.section-heading i{color:#e74c3c;}
.user-nav a,.user-nav button{font-size:0.8rem;border-radius:20px;padding:7px 14px;font-weight:600;text-decoration:none;cursor:pointer;transition:all 0.2s;border:1.5px solid rgba(255,255,255,0.35);background:rgba(255,255,255,0.1);color:white;display:inline-flex;align-items:center;gap:5px;}
.user-nav a:hover,.user-nav button:hover{background:rgba(255,255,255,0.2);color:white;}
.nav-btn-red{background:#c0392b!important;border-color:#c0392b!important;}

/* Profile picture area */
.profile-pic-wrap{position:relative;display:inline-block;cursor:pointer;}
.profile-pic-img{width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #c0392b;box-shadow:0 4px 20px rgba(192,57,43,0.3);display:block;}
.profile-pic-placeholder{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;font-size:2.8rem;font-weight:800;color:white;border:4px solid rgba(192,57,43,0.4);box-shadow:0 4px 20px rgba(192,57,43,0.3);}
.pic-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;color:white;font-size:1.5rem;flex-direction:column;gap:4px;}
.pic-overlay span{font-size:0.7rem;font-weight:600;}
.profile-pic-wrap:hover .pic-overlay{opacity:1;}

/* Tab pills */
.profile-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.profile-tab{padding:8px 18px;border-radius:20px;border:1.5px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.1);color:white;font-size:0.82rem;font-weight:600;cursor:pointer;transition:all 0.2s;text-decoration:none;}
.profile-tab.active,.profile-tab:hover{background:rgba(192,57,43,0.5);border-color:#c0392b;color:white;}

/* Field validation */
.field-ok{border-color:#27ae60!important;box-shadow:0 0 0 3px rgba(39,174,96,0.12)!important;}
.field-err{border-color:#c0392b!important;box-shadow:0 0 0 3px rgba(192,57,43,0.12)!important;}
.field-msg{font-size:11.5px;margin-top:4px;display:flex;align-items:center;gap:4px;}
.field-msg.ok{color:#27ae60;}
.field-msg.err{color:#c0392b;}

/* Info row */
.info-row{display:flex;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f5;}
.info-row:last-child{border-bottom:none;}
.info-label{font-size:0.75rem;color:#adb5bd;font-weight:600;text-transform:uppercase;letter-spacing:.05em;min-width:90px;}
.info-value{font-size:0.9rem;font-weight:600;color:#212529;}

/* Password strength */
.strength-bar-wrap{height:4px;border-radius:4px;background:#e9ecef;overflow:hidden;margin-top:5px;}
.strength-bar{height:100%;border-radius:4px;transition:width 0.4s,background 0.4s;width:0;}
</style>


<div class="ub-wrap">


    <div class="mb-4" style="color:white;">
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:4px;"><i class="bi bi-person-circle text-danger me-2"></i>My Profile</h1>
        <p style="opacity:0.65;font-size:0.88rem;margin:0;">Manage your account information and profile picture.</p>
    </div>

    <?php if ($success): ?>
    <div style="background:rgba(39,174,96,0.2);border:1px solid rgba(39,174,96,0.5);border-radius:14px;padding:14px 20px;color:#d5f5e3;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="bi bi-check-circle-fill fs-5"></i><?= $success ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors['general'])): ?>
    <div style="background:rgba(192,57,43,0.2);border:1px solid rgba(192,57,43,0.5);border-radius:14px;padding:14px 20px;color:#ffcccc;margin-bottom:20px;">
        <i class="bi bi-x-circle-fill me-2"></i><?= $errors['general'] ?>
    </div>
    <?php endif; ?>

    <!-- Tab navigation -->
    <div class="profile-tabs">
        <a href="#info"     class="profile-tab active" onclick="showTab('info',this)">👤 Profile Info</a>
        <a href="#password" class="profile-tab"         onclick="showTab('password',this)">🔒 Change Password</a>
        <a href="#account"  class="profile-tab"         onclick="showTab('account',this)">⚙️ Account Details</a>
    </div>

    <!-- ── TAB 1: Profile Info ─────────────────────────────── -->
    <div id="tab-info">
        <div class="row g-4">
            <!-- Profile Picture Card -->
            <div class="col-md-4">
                <div class="glass-card">
                    <div class="card-header"><i class="bi bi-camera-fill me-2"></i>Profile Picture</div>
                    <div class="card-body p-4 text-center">
                        <form method="POST" enctype="multipart/form-data" id="picForm">
                            <!-- Picture display -->
                            <div class="profile-pic-wrap mb-3" onclick="document.getElementById('picInput').click()">
                                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__ . '/../assets/uploads/profiles/' . $user['profile_pic'])): ?>
                                <img src="/blood_bank/assets/uploads/profiles/<?= htmlspecialchars($user['profile_pic']) ?>"
                                     class="profile-pic-img" id="picPreview" alt="Profile">
                                <?php else: ?>
                                <div class="profile-pic-placeholder" id="picPlaceholder">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <img src="" class="profile-pic-img d-none" id="picPreview" alt="Profile">
                                <?php endif; ?>
                                <div class="pic-overlay"><i class="bi bi-camera-fill fs-4"></i><span>Change</span></div>
                            </div>

                            <input type="file" name="profile_pic" id="picInput"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                class="d-none" onchange="previewPic(this)">

                            <?php if (!empty($errors['pic'])): ?>
                            <div class="text-danger small mb-2"><i class="bi bi-x-circle me-1"></i><?= $errors['pic'] ?></div>
                            <?php endif; ?>

                            <div class="text-muted small mb-3">JPG, PNG, GIF or WEBP<br>Max size: 2MB</div>

                            <button type="submit" class="btn btn-danger btn-sm w-100 fw-semibold">
                                <i class="bi bi-upload me-1"></i>Upload Picture
                            </button>
                        </form>

                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                            <div style="font-size:0.75rem;color:#c0392b;font-weight:600;">
                                <?= ucfirst($_SESSION['role_type'] ?? 'user') ?>
                            </div>
                            <?php if (!empty($user['city'])): ?>
                            <div class="text-muted" style="font-size:0.75rem;margin-top:2px;">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($user['city']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Info Card -->
            <div class="col-md-8">
                <div class="glass-card">
                    <div class="card-header"><i class="bi bi-pencil-fill me-2"></i>Edit Profile Information</div>
                    <div class="card-body p-4">
                        <form method="POST" id="profileForm" novalidate>
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row g-3">

                                <!-- Name -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" name="name" id="editName"
                                            class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                                            value="<?= htmlspecialchars($user['name']) ?>"
                                            maxlength="100" placeholder="Your full name" required>
                                        <div class="invalid-feedback"><?= $errors['name'] ?? '' ?></div>
                                    </div>
                                    <div class="field-msg" id="nameMsg"></div>
                                </div>

                                <!-- Email (read-only) -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f8f9fa;">
                                    </div>
                                    <div class="form-text small">Email cannot be changed.</div>
                                </div>

                                <!-- City -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">City</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-building"></i></span>
                                        <input type="text" name="city" id="editCity"
                                            class="form-control"
                                            value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                                            maxlength="100" placeholder="e.g. Patna">
                                    </div>
                                </div>

                                <!-- State -->
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">State</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-map"></i></span>
                                        <input type="text" name="state" id="editState"
                                            class="form-control"
                                            value="<?= htmlspecialchars($user['state'] ?? '') ?>"
                                            maxlength="100" placeholder="e.g. Bihar">
                                    </div>
                                </div>

                                <!-- Address -->
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Full Address</label>
                                    <textarea name="address" class="form-control" rows="3"
                                        maxlength="300"
                                        placeholder="House no, Street, Locality..."><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                    <div class="form-text small">Optional. Max 300 characters.</div>
                                </div>

                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-danger px-5 fw-semibold">
                                        <i class="bi bi-check-circle me-2"></i>Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 2: Change Password ──────────────────────────── -->
    <div id="tab-password" class="d-none">
        <div class="glass-card" style="max-width:520px;">
            <div class="card-header"><i class="bi bi-lock-fill me-2"></i>Change Password</div>
            <div class="card-body p-4">
                <?php if (!empty($errors['pass'])): ?>
                <div class="alert alert-danger py-2 small mb-3">
                    <i class="bi bi-x-circle me-2"></i><?= $errors['pass'] ?>
                </div>
                <?php endif; ?>
                <form method="POST" id="passForm">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_pass" id="curPass" class="form-control" placeholder="Enter current password" required>
                            <span class="input-group-text" style="cursor:pointer;" onclick="tp('curPass',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="new_pass" id="newPass" class="form-control" placeholder="Strong new password" required>
                            <span class="input-group-text" style="cursor:pointer;" onclick="tp('newPass',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                        <div class="strength-bar-wrap"><div class="strength-bar" id="strengthBar"></div></div>
                        <div id="strengthLabel" style="font-size:11px;margin-top:3px;font-weight:600;"></div>
                        <div class="form-text small">Min 8 chars · Uppercase · Number · Special character</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_pass" id="confPass" class="form-control" placeholder="Repeat new password" required>
                            <span class="input-group-text" style="cursor:pointer;" onclick="tp('confPass',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                        <div class="field-msg" id="confMsg"></div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-semibold">
                        <i class="bi bi-shield-lock me-2"></i>Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: Account Details ──────────────────────────── -->
    <div id="tab-account" class="d-none">
        <div class="glass-card" style="max-width:520px;">
            <div class="card-header"><i class="bi bi-person-badge-fill me-2"></i>Account Details</div>
            <div class="card-body p-4">
                <div class="info-row">
                    <div class="info-label">Name</div>
                    <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Not added') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Role</div>
                    <div class="info-value">
                        <span style="background:#fff0ee;color:#c0392b;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;">
                            <?= ucfirst($user['role_type'] ?? $user['role']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">City</div>
                    <div class="info-value"><?= htmlspecialchars($user['city'] ?? '—') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">State</div>
                    <div class="info-value"><?= htmlspecialchars($user['state'] ?? '—') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Verified</div>
                    <div class="info-value">
                        <?php $ver = $user['email_verified'] ?? 1; ?>
                        <span style="background:<?= $ver?'#e8f8f0':'#fff0ee' ?>;color:<?= $ver?'#155c2e':'#c0392b' ?>;padding:3px 10px;border-radius:20px;font-size:0.8rem;font-weight:600;">
                            <?= $ver ? '✓ Verified' : '✗ Not Verified' ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?= date('d F Y', strtotime($user['created_at'])) ?></div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>


// ── Tab switching ─────────────────────────────────────────────
function showTab(name, el) {
    document.querySelectorAll('[id^="tab-"]').forEach(t => t.classList.add('d-none'));
    document.getElementById('tab-' + name).classList.remove('d-none');
    document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
    if (el) el.classList.add('active');
    return false;
}

// ── Profile picture preview before upload ────────────────────
function previewPic(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const prev = document.getElementById('picPreview');
            const ph   = document.getElementById('picPlaceholder');
            prev.src = e.target.result;
            prev.classList.remove('d-none');
            if (ph) ph.classList.add('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ── Password toggle ───────────────────────────────────────────
function tp(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

// ── Name live validation ──────────────────────────────────────
const nameEl  = document.getElementById('editName');
const nameMsg = document.getElementById('nameMsg');
if (nameEl) {
    nameEl.addEventListener('input', function(){
        const v = this.value.trim();
        if (!v) { this.classList.remove('field-ok','field-err'); nameMsg.innerHTML=''; return; }
        const ok = v.length>=3 && /^[a-zA-Z\s]+$/.test(v);
        this.classList.toggle('field-ok', ok);
        this.classList.toggle('field-err', !ok);
        nameMsg.className = 'field-msg ' + (ok?'ok':'err');
        nameMsg.innerHTML = ok
            ? '<i class="bi bi-check-circle-fill"></i> Looks good!'
            : '<i class="bi bi-x-circle-fill"></i> ' + (v.length<3?'Min 3 characters.':'Letters and spaces only.');
    });
}

// ── New password strength ─────────────────────────────────────
const newPassEl = document.getElementById('newPass');
const sBar      = document.getElementById('strengthBar');
const sLbl      = document.getElementById('strengthLabel');
if (newPassEl) {
    newPassEl.addEventListener('input', function(){
        const v = this.value;
        let sc = 0;
        if(v.length>=8)        sc++;
        if(/[A-Z]/.test(v))    sc++;
        if(/[a-z]/.test(v))    sc++;
        if(/[0-9]/.test(v))    sc++;
        if(/[!@#$%^&*]/.test(v)) sc++;
        const w  = ['0%','20%','40%','65%','85%','100%'];
        const cl = ['#ccc','#e74c3c','#e67e22','#f1c40f','#27ae60','#27ae60'];
        const lb = ['','Very Weak','Weak','Fair','Strong','Very Strong'];
        if(sBar){sBar.style.width=w[sc];sBar.style.background=cl[sc];}
        if(sLbl){sLbl.textContent=lb[sc];sLbl.style.color=cl[sc];}
        // confirm match check
        const cf=document.getElementById('confPass');
        if(cf&&cf.value) checkConf(cf,v);
    });
}

function checkConf(el,pass){
    const m=document.getElementById('confMsg');
    if(!el.value){m.innerHTML='';return;}
    const ok=el.value===pass;
    m.className='field-msg '+(ok?'ok':'err');
    m.innerHTML=ok?'<i class="bi bi-check-circle-fill"></i> Passwords match ✓':'<i class="bi bi-x-circle-fill"></i> Passwords do not match.';
}
const confEl=document.getElementById('confPass');
if(confEl){confEl.addEventListener('input',function(){checkConf(this,document.getElementById('newPass').value);});}

// ── Open correct tab on load if errors ───────────────────────
<?php if (!empty($errors['pass'])): ?>
showTab('password', document.querySelector('[onclick*="password"]'));
<?php endif; ?>
</script>
