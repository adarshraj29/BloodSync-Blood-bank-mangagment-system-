<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
require_once __DIR__ . '/../includes/activity.php';
$page_title = 'Admin Profile';
$uid  = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$errors = []; $success = '';

// Profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    if ($file['error'] === 0) {
        if (!in_array($file['type'], $allowed)) $errors['pic'] = 'Only JPG, PNG, GIF or WEBP allowed.';
        elseif ($file['size'] > 2*1024*1024) $errors['pic'] = 'Image must be under 2MB.';
        else {
            $dir = __DIR__ . '/../assets/uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fn  = 'admin_'.$uid.'_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
                if (!empty($user['profile_pic']) && file_exists($dir.$user['profile_pic'])) unlink($dir.$user['profile_pic']);
                $conn->query("UPDATE users SET profile_pic='$fn' WHERE id=$uid");
                $user['profile_pic'] = $fn;
                logActivity($conn, $uid, $_SESSION['name'], 'Profile Picture Updated', 'Admin changed profile picture');
                $success = 'Profile picture updated successfully!';
            }
        }
    }
}

// Profile info update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim(clean($conn, $_POST['name']));
    $city    = trim(clean($conn, $_POST['city']));
    $state   = trim(clean($conn, $_POST['state']));
    $address = trim(clean($conn, $_POST['address']));
    if (empty($name)) $errors['name'] = 'Name is required.';
    elseif (strlen($name) < 3) $errors['name'] = 'Name must be at least 3 characters.';
    elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) $errors['name'] = 'Name must contain only letters.';
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users SET name=?,city=?,state=?,address=? WHERE id=?");
        $stmt->bind_param("ssssi", $name, $city, $state, $address, $uid);
        if ($stmt->execute()) {
            $_SESSION['name'] = $name;
            $user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
            logActivity($conn, $uid, $name, 'Profile Updated', 'Admin updated profile info');
            $success = 'Profile updated successfully!';
        }
    }
}

// Password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $cur  = $_POST['current_pass'];
    $new  = $_POST['new_pass'];
    $conf = $_POST['confirm_pass'];
    $common = ['password','123456','12345678','password123','qwerty','abc123','111111'];
    if (!password_verify($cur, $user['password']))
        $errors['pass'] = 'Current password is incorrect.';
    elseif (password_verify($new, $user['password']))
        $errors['pass'] = 'New password cannot be the same as your current password.';
    elseif (strlen($new) < 8)
        $errors['pass'] = 'New password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $new))
        $errors['pass'] = 'Must contain at least 1 uppercase letter (A-Z).';
    elseif (!preg_match('/[a-z]/', $new))
        $errors['pass'] = 'Must contain at least 1 lowercase letter (a-z).';
    elseif (!preg_match('/[0-9]/', $new))
        $errors['pass'] = 'Must contain at least 1 number (0-9).';
    elseif (!preg_match('/[!@#$%^&*]/', $new))
        $errors['pass'] = 'Must contain at least 1 special character (!@#$%^&*).';
    elseif (in_array(strtolower($new), $common))
        $errors['pass'] = 'Password is too common. Choose a stronger one.';
    elseif ($new !== $conf)
        $errors['pass'] = 'Passwords do not match.';
    else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
        logActivity($conn, $uid, $_SESSION['name'], 'Password Changed', 'Admin changed password');
        $success = 'Password changed successfully!';
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-person-circle me-2 text-danger"></i>Admin Profile</h1>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= $e ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tab nav -->
    <ul class="nav nav-pills mb-4" id="profileTabs">
        <li class="nav-item"><a class="nav-link active" href="#" onclick="showTab('info',this);return false;">👤 Profile Info</a></li>
        <li class="nav-item"><a class="nav-link ms-2" href="#" onclick="showTab('password',this);return false;">🔒 Change Password</a></li>
        <li class="nav-item"><a class="nav-link ms-2" href="#" onclick="showTab('account',this);return false;">⚙️ Account Details</a></li>
    </ul>

    <!-- TAB 1: Profile Info -->
    <div id="tab-info">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header py-3 px-4 fw-semibold"><i class="bi bi-camera-fill me-2 text-danger"></i>Profile Picture</div>
                    <div class="card-body p-4 text-center">
                        <form method="POST" enctype="multipart/form-data">
                            <div style="position:relative;display:inline-block;cursor:pointer;margin-bottom:16px;" onclick="document.getElementById('picInput').click()">
                                <?php if (!empty($user['profile_pic']) && file_exists(__DIR__.'/../assets/uploads/profiles/'.$user['profile_pic'])): ?>
                                <img src="/blood_bank/assets/uploads/profiles/<?= htmlspecialchars($user['profile_pic']) ?>" id="picPreview"
                                    style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid #c0392b;box-shadow:0 4px 20px rgba(192,57,43,0.3);">
                                <?php else: ?>
                                <div id="picPlaceholder" style="width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:800;color:white;border:4px solid rgba(192,57,43,0.4);margin:0 auto;">
                                    <?= strtoupper(substr($user['name'],0,1)) ?>
                                </div>
                                <img src="" id="picPreview" style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:4px solid #c0392b;display:none;">
                                <?php endif; ?>
                                <div style="position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.2s;color:white;flex-direction:column;gap:4px;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                                    <i class="bi bi-camera-fill fs-4"></i><span style="font-size:0.7rem;font-weight:600;">Change</span>
                                </div>
                            </div>
                            <input type="file" name="profile_pic" id="picInput" accept="image/*" class="d-none" onchange="previewPic(this)">
                            <div class="text-muted small mb-3">JPG, PNG, GIF or WEBP · Max 2MB</div>
                            <button type="submit" class="btn btn-danger btn-sm w-100 fw-semibold"><i class="bi bi-upload me-1"></i>Upload</button>
                        </form>
                        <div class="mt-3 pt-3 border-top">
                            <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="text-danger small fw-semibold">Administrator</div>
                            <?php if (!empty($user['city'])): ?>
                            <div class="text-muted small"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($user['city']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header py-3 px-4 fw-semibold"><i class="bi bi-pencil-fill me-2 text-danger"></i>Edit Profile</div>
                    <div class="card-body p-4">
                        <form method="POST" novalidate>
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                                        value="<?= htmlspecialchars($user['name']) ?>" maxlength="100" required>
                                    <div class="invalid-feedback"><?= $errors['name']??'' ?></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Email (read-only)</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city']??'') ?>" maxlength="100" placeholder="e.g. Patna">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">State</label>
                                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($user['state']??'') ?>" maxlength="100" placeholder="e.g. Bihar">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold small">Address</label>
                                    <textarea name="address" class="form-control" rows="2" maxlength="300" placeholder="Full address..."><?= htmlspecialchars($user['address']??'') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-danger px-4 fw-semibold"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: Change Password -->
    <div id="tab-password" class="d-none">
        <div class="card" style="max-width:500px;">
            <div class="card-header py-3 px-4 fw-semibold"><i class="bi bi-lock-fill me-2 text-danger"></i>Change Password</div>
            <div class="card-body p-4">
                <?php if (!empty($errors['pass'])): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-x-circle me-2"></i><?= $errors['pass'] ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Current Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_pass" id="curP" class="form-control" required>
                            <span class="input-group-text" style="cursor:pointer" onclick="tp('curP',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="new_pass" id="newP" class="form-control" required>
                            <span class="input-group-text" style="cursor:pointer" onclick="tp('newP',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                        <div style="height:5px;border-radius:4px;background:#e9ecef;overflow:hidden;margin-top:6px;"><div id="sBar" style="height:100%;border-radius:4px;transition:width 0.4s,background 0.4s;width:0;"></div></div>
                        <div id="sLbl" style="font-size:11px;font-weight:600;margin-top:2px;"></div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_pass" id="confP" class="form-control" required>
                            <span class="input-group-text" style="cursor:pointer" onclick="tp('confP',this)"><i class="bi bi-eye-slash"></i></span>
                        </div>
                        <div id="confMsg" style="font-size:11.5px;margin-top:4px;"></div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-semibold"><i class="bi bi-shield-lock me-2"></i>Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 3: Account Details -->
    <div id="tab-account" class="d-none">
        <div class="card" style="max-width:500px;">
            <div class="card-header py-3 px-4 fw-semibold"><i class="bi bi-person-badge-fill me-2 text-danger"></i>Account Details</div>
            <div class="card-body p-4">
                <?php foreach ([
                    ['Name', $user['name']],
                    ['Email', $user['email']],
                    ['Role', 'Administrator'],
                    ['City', $user['city'] ?? '—'],
                    ['State', $user['state'] ?? '—'],
                    ['Member Since', date('d F Y', strtotime($user['created_at']))],
                ] as [$l,$v]): ?>
                <div class="d-flex gap-3 align-items-center py-2 border-bottom">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--ab-muted,#adb5bd);min-width:90px;"><?= $l ?></div>
                    <div class="fw-semibold small"><?= htmlspecialchars($v) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function showTab(n,el){
    document.querySelectorAll('[id^="tab-"]').forEach(t=>t.classList.add('d-none'));
    document.getElementById('tab-'+n).classList.remove('d-none');
    document.querySelectorAll('#profileTabs .nav-link').forEach(t=>t.classList.remove('active'));
    if(el)el.classList.add('active');
    return false;
}
function previewPic(input){
    if(input.files&&input.files[0]){
        const r=new FileReader();
        r.onload=function(e){
            const p=document.getElementById('picPreview'),ph=document.getElementById('picPlaceholder');
            p.src=e.target.result;p.style.display='block';
            if(ph)ph.style.display='none';
        };
        r.readAsDataURL(input.files[0]);
    }
}
function tp(id,btn){const i=document.getElementById(id),ic=btn.querySelector('i');i.type=i.type==='password'?'text':'password';ic.className=i.type==='password'?'bi bi-eye-slash':'bi bi-eye';}
const np=document.getElementById('newP'),sb=document.getElementById('sBar'),sl=document.getElementById('sLbl');
if(np){np.addEventListener('input',function(){
    const v=this.value;let sc=0;
    if(v.length>=8)sc++;if(/[A-Z]/.test(v))sc++;if(/[a-z]/.test(v))sc++;if(/[0-9]/.test(v))sc++;if(/[!@#$%^&*]/.test(v))sc++;
    const w=['0%','20%','40%','65%','85%','100%'],cl=['#ccc','#e74c3c','#e67e22','#f1c40f','#27ae60','#27ae60'],lb=['','Very Weak','Weak','Fair','Strong','Very Strong'];
    if(sb){sb.style.width=w[sc];sb.style.background=cl[sc];}if(sl){sl.textContent=lb[sc];sl.style.color=cl[sc];}
    const cf=document.getElementById('confP');if(cf&&cf.value)chkC(cf,v);
});}
function chkC(el,pv){const m=document.getElementById('confMsg');if(!el||!el.value){if(m)m.innerHTML='';return;}const ok=el.value===pv;el.classList.toggle('is-valid',ok);el.classList.toggle('is-invalid',!ok);if(m){m.style.color=ok?'#27ae60':'#c0392b';m.innerHTML=ok?'<i class="bi bi-check-circle-fill me-1"></i>Passwords match ✓':'<i class="bi bi-x-circle-fill me-1"></i>Passwords do not match.';}}
const cp=document.getElementById('confP');if(cp)cp.addEventListener('input',function(){chkC(this,document.getElementById('newP')?.value||'');});
<?php if (!empty($errors['pass'])): ?>showTab('password', document.querySelector('[onclick*="password"]'));<?php endif; ?>
</script>
