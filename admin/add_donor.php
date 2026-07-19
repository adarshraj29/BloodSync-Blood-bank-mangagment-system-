<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Add Donor';

$blood_groups = $conn->query("SELECT * FROM blood_groups");
$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old       = $_POST;
    $name      = trim(clean($conn, $_POST['name']));
    $age       = trim($_POST['age']);
    $gender    = trim(clean($conn, $_POST['gender']));
    $bg_id     = trim($_POST['blood_group_id']);
    $phone     = trim(clean($conn, $_POST['phone']));
    $email     = trim(clean($conn, $_POST['email']));
    $address   = trim(clean($conn, $_POST['address']));
    $last_date = trim($_POST['last_donation_date']);

    // NAME
    if (empty($name))
        $errors['name'] = 'Full name is required.';
    elseif (strlen($name) < 3)
        $errors['name'] = 'Name must be at least 3 characters.';
    elseif (strlen($name) > 100)
        $errors['name'] = 'Name must not exceed 100 characters.';
    elseif (!preg_match("/^[a-zA-Z\s'.]+$/", $name))
        $errors['name'] = 'Name can only contain letters and spaces.';

    // AGE
    if (empty($age))
        $errors['age'] = 'Age is required.';
    elseif (!ctype_digit($age))
        $errors['age'] = 'Age must be a whole number.';
    elseif ((int)$age < 18)
        $errors['age'] = 'Donor must be at least 18 years old.';
    elseif ((int)$age > 65)
        $errors['age'] = 'Donor must be 65 years old or younger.';

    // GENDER
    if (empty($gender) || !in_array($gender, ['Male','Female','Other']))
        $errors['gender'] = 'Please select a gender.';

    // BLOOD GROUP
    if (empty($bg_id) || !ctype_digit($bg_id))
        $errors['blood_group_id'] = 'Please select a blood group.';
    else {
        $chk = $conn->query("SELECT id FROM blood_groups WHERE id=".(int)$bg_id);
        if ($chk->num_rows === 0) $errors['blood_group_id'] = 'Invalid blood group.';
    }

    // PHONE
    if (empty($phone))
        $errors['phone'] = 'Phone number is required.';
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone))
        $errors['phone'] = 'Enter a valid 10-digit Indian mobile number (starts with 6–9).';
    else {
        $dup = $conn->prepare("SELECT id FROM donors WHERE phone=? AND is_active=1");
        $dup->bind_param("s", $phone);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0)
            $errors['phone'] = 'This phone number is already registered.';
    }

    // EMAIL (optional)
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Enter a valid email address (e.g. name@gmail.com).';
        elseif (strlen($email) > 100)
            $errors['email'] = 'Email must not exceed 100 characters.';
    }

    // ADDRESS (optional)
    if (!empty($address) && strlen($address) > 300)
        $errors['address'] = 'Address must not exceed 300 characters.';

    // CITY (optional)
    $city  = trim(clean($conn, $_POST['city'] ?? ''));
    $state = trim(clean($conn, $_POST['state'] ?? ''));
    if (!empty($city) && strlen($city) > 100)
        $errors['city'] = 'City must not exceed 100 characters.';

    // LAST DONATION DATE (optional)
    if (!empty($last_date)) {
        $d = DateTime::createFromFormat('Y-m-d', $last_date);
        if (!$d)
            $errors['last_donation_date'] = 'Invalid date format.';
        elseif ($last_date > date('Y-m-d'))
            $errors['last_donation_date'] = 'Last donation date cannot be in the future.';
        elseif ($last_date < date('Y-m-d', strtotime('-50 years')))
            $errors['last_donation_date'] = 'Date seems too far in the past.';
    }

    // SAVE
    if (empty($errors)) {
        $age_i    = (int)$age;
        $bg_i     = (int)$bg_id;
        $last_val = !empty($last_date) ? $last_date : null;
        $stmt = $conn->prepare("INSERT INTO donors (name,age,gender,blood_group_id,phone,email,address,city,state,last_donation_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sissssssss", $name, $age_i, $gender, $bg_i, $phone, $email, $address, $city, $state, $last_val);
        if ($stmt->execute())
            redirect('/blood_bank/admin/donors.php?msg=saved');
        else
            $errors['general'] = 'Database error. Please try again.';
    }
}

function fc($e, $f) { return isset($e[$f]) ? 'is-invalid' : (isset($_POST[$f]) && $_POST[$f]!=='' ? 'is-valid' : ''); }
function ov($o, $f) { return htmlspecialchars($o[$f] ?? ''); }
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-person-plus me-2 text-danger"></i>Add Donor</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong><?= count($errors) ?> error(s) found.</strong> Please fix them below before submitting.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width:700px;">
        <div class="card-header py-3 px-4">
            <span class="fw-semibold">Donor Information</span>
            <span class="text-muted small ms-2">Fields marked <span class="text-danger">*</span> are required</span>
        </div>
        <div class="card-body p-4">
        <form method="POST" id="donorForm" novalidate>
        <div class="row g-3">

            <!-- Full Name -->
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="name"
                    class="form-control <?= fc($errors,'name') ?>"
                    placeholder="e.g. Rajesh Kumar"
                    value="<?= ov($old,'name') ?>" maxlength="100" autocomplete="off">
                <div class="invalid-feedback"><?= $errors['name'] ?? '' ?></div>
                <div class="valid-feedback">Looks good!</div>
                <div class="form-text">Only letters &amp; spaces. Min 3, max 100 characters.</div>
            </div>

            <!-- Age -->
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Age <span class="text-danger">*</span></label>
                <input type="number" name="age" id="age"
                    class="form-control <?= fc($errors,'age') ?>"
                    placeholder="18 – 65"
                    value="<?= ov($old,'age') ?>" min="18" max="65">
                <div class="invalid-feedback"><?= $errors['age'] ?? '' ?></div>
                <div class="form-text">Allowed: 18 to 65 years.</div>
            </div>

            <!-- Gender -->
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                <select name="gender" id="gender" class="form-select <?= fc($errors,'gender') ?>">
                    <option value="">-- Select --</option>
                    <?php foreach (['Male','Female','Other'] as $g): ?>
                    <option value="<?= $g ?>" <?= (($old['gender']??'')===$g)?'selected':'' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= $errors['gender'] ?? '' ?></div>
            </div>

            <!-- Blood Group -->
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Blood Group <span class="text-danger">*</span></label>
                <select name="blood_group_id" id="blood_group_id" class="form-select <?= fc($errors,'blood_group_id') ?>">
                    <option value="">-- Select --</option>
                    <?php $blood_groups->data_seek(0); while ($bg = $blood_groups->fetch_assoc()): ?>
                    <option value="<?= $bg['id'] ?>" <?= (($old['blood_group_id']??'')==$bg['id'])?'selected':'' ?>><?= $bg['name'] ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="invalid-feedback"><?= $errors['blood_group_id'] ?? '' ?></div>
            </div>

            <!-- Phone -->
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-light">+91</span>
                    <input type="tel" name="phone" id="phone"
                        class="form-control <?= fc($errors,'phone') ?>"
                        placeholder="10-digit number"
                        value="<?= ov($old,'phone') ?>"
                        maxlength="10" pattern="[6-9][0-9]{9}" autocomplete="off">
                    <div class="invalid-feedback"><?= $errors['phone'] ?? '' ?></div>
                </div>
                <div class="form-text">Must start with 6/7/8/9. Exactly 10 digits.</div>
            </div>

            <!-- Email -->
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Email <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="email" name="email" id="email"
                    class="form-control <?= fc($errors,'email') ?>"
                    placeholder="name@gmail.com"
                    value="<?= ov($old,'email') ?>" maxlength="100">
                <div class="invalid-feedback"><?= $errors['email'] ?? '' ?></div>
                <div class="form-text">Leave blank if unavailable.</div>
            </div>

            <!-- Address -->
            <div class="col-12">
                <label class="form-label fw-semibold small">Address <span class="text-muted fw-normal">(Optional)</span></label>
                <textarea name="address" id="address"
                    class="form-control <?= fc($errors,'address') ?>"
                    rows="2" maxlength="300"
                    placeholder="House No, Street"><?= ov($old,'address') ?></textarea>
                <div class="d-flex justify-content-between">
                    <div class="invalid-feedback d-block"><?= $errors['address'] ?? '' ?></div>
                    <div class="form-text ms-auto" id="addr-count">0 / 300</div>
                </div>
            </div>

            <!-- City & State -->
            <div class="col-md-4">
                <label class="form-label fw-semibold small">City <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="text" name="city" class="form-control <?= fc($errors,'city') ?>"
                    placeholder="e.g. Patna" value="<?= ov($old,'city') ?>" maxlength="100">
                <div class="invalid-feedback"><?= $errors['city'] ?? '' ?></div>
                <div class="form-text">Used for location-based search.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">State <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="text" name="state" class="form-control"
                    placeholder="e.g. Bihar" value="<?= ov($old,'state') ?>" maxlength="100">
                <div class="form-text">e.g. Bihar, UP, Maharashtra.</div>
            </div>

            <!-- Last Donation Date -->
            <div class="col-md-5">
                <label class="form-label fw-semibold small">Last Donation Date <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="date" name="last_donation_date" id="last_donation_date"
                    class="form-control <?= fc($errors,'last_donation_date') ?>"
                    value="<?= ov($old,'last_donation_date') ?>"
                    max="<?= date('Y-m-d') ?>"
                    min="<?= date('Y-m-d', strtotime('-50 years')) ?>">
                <div class="invalid-feedback"><?= $errors['last_donation_date'] ?? '' ?></div>
                <div class="form-text">Leave blank if this is a first-time donor.</div>
            </div>

            <!-- Eligibility result -->
            <div class="col-md-7 d-flex align-items-end">
                <div id="elig-msg" class="w-100"></div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-12">
                <button type="submit" class="btn btn-danger px-4">
                    <i class="bi bi-person-check me-2"></i>Add Donor
                </button>
                <a href="/blood_bank/admin/donors.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>

        </div>
        </form>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
/* ── helpers ── */
function setValidity(el, ok) {
    el.classList.toggle('is-valid',   ok === true);
    el.classList.toggle('is-invalid', ok === false);
}
function clearValidity(el) {
    el.classList.remove('is-valid','is-invalid');
}

/* ── Name ── */
document.getElementById('name').addEventListener('input', function(){
    const v = this.value.trim();
    if(!v){ clearValidity(this); return; }
    setValidity(this, v.length>=3 && v.length<=100 && /^[a-zA-Z\s'.]+$/.test(v));
});

/* ── Age ── */
document.getElementById('age').addEventListener('input', function(){
    this.value = this.value.replace(/[^0-9]/g,'');
    const v = parseInt(this.value);
    if(!this.value){ clearValidity(this); return; }
    setValidity(this, !isNaN(v) && v>=18 && v<=65);
});
document.getElementById('age').addEventListener('keypress', e=>{ if(!/[0-9]/.test(e.key)) e.preventDefault(); });

/* ── Gender ── */
document.getElementById('gender').addEventListener('change', function(){
    setValidity(this, this.value !== '');
});

/* ── Blood Group ── */
document.getElementById('blood_group_id').addEventListener('change', function(){
    setValidity(this, this.value !== '');
});

/* ── Phone ── */
const ph = document.getElementById('phone');
ph.addEventListener('input', function(){
    this.value = this.value.replace(/\D/g,'').slice(0,10);
    if(!this.value){ clearValidity(this); return; }
    setValidity(this, /^[6-9][0-9]{9}$/.test(this.value));
});
ph.addEventListener('keypress', e=>{ if(!/[0-9]/.test(e.key)) e.preventDefault(); });

/* ── Email ── */
document.getElementById('email').addEventListener('input', function(){
    const v = this.value.trim();
    if(!v){ clearValidity(this); return; }
    setValidity(this, /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v));
});

/* ── Address counter ── */
const addr = document.getElementById('address');
const cnt  = document.getElementById('addr-count');
addr.addEventListener('input', function(){
    const l = this.value.length;
    cnt.textContent = l+' / 300';
    cnt.className   = 'form-text ms-auto '+(l>270?'text-danger':'text-muted');
});

/* ── Donation eligibility ── */
document.getElementById('last_donation_date').addEventListener('change', function(){
    const el = document.getElementById('elig-msg');
    if(!this.value){ el.innerHTML=''; return; }
    const days = Math.floor((new Date()-new Date(this.value))/86400000);
    if(days < 90){
        const wait = 90-days;
        el.innerHTML=`<div class="alert alert-warning py-2 small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Not eligible yet.</strong> Needs to wait <strong>${wait} more day(s)</strong> (90-day rule).
        </div>`;
    } else {
        el.innerHTML=`<div class="alert alert-success py-2 small mb-0">
            <i class="bi bi-check-circle me-1"></i>
            <strong>Eligible to donate.</strong> Last donation was ${days} days ago.
        </div>`;
    }
});

/* ── Final submit guard ── */
document.getElementById('donorForm').addEventListener('submit', function(e){
    const errs=[];
    const name = document.getElementById('name').value.trim();
    const age  = parseInt(document.getElementById('age').value);
    const ph   = document.getElementById('phone').value;
    const gen  = document.getElementById('gender').value;
    const bg   = document.getElementById('blood_group_id').value;
    const em   = document.getElementById('email').value.trim();

    if(!name || name.length<3 || !/^[a-zA-Z\s'.]+$/.test(name))
        errs.push('Full name is required (letters only, min 3 characters).');
    if(isNaN(age)||age<18||age>65)
        errs.push('Age must be between 18 and 65.');
    if(!gen)  errs.push('Please select a gender.');
    if(!bg)   errs.push('Please select a blood group.');
    if(!/^[6-9][0-9]{9}$/.test(ph))
        errs.push('Phone must be a valid 10-digit number starting with 6, 7, 8 or 9.');
    if(em && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(em))
        errs.push('Email address format is invalid.');

    if(errs.length>0){
        e.preventDefault();
        let box = document.getElementById('js-error-box');
        if(!box){ box=document.createElement('div'); box.id='js-error-box'; this.prepend(box); }
        box.className='alert alert-danger mb-3';
        box.innerHTML='<strong><i class="bi bi-x-circle me-2"></i>Please fix the following:</strong><ul class="mb-0 mt-1">'+
            errs.map(r=>`<li>${r}</li>`).join('')+'</ul>';
        window.scrollTo({top:0,behavior:'smooth'});
    }
});
</script>
