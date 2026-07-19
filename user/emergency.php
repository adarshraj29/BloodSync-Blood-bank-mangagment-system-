<?php
// ============================================================
// BloodSync - Emergency Blood Request (full validation)
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title = 'Emergency Blood Request';
$uid        = (int)$_SESSION['user_id'];
$user       = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$blood_groups = $conn->query("SELECT * FROM blood_groups ORDER BY id");
$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old      = $_POST;
    $patient  = trim(clean($conn, $_POST['patient_name']));
    $phone    = trim(clean($conn, $_POST['phone']));
    $bg_id    = (int)($_POST['blood_group_id'] ?? 0);
    $units    = (int)($_POST['units_needed'] ?? 0);
    $hospital = trim(clean($conn, $_POST['hospital_name']));
    $location = trim(clean($conn, $_POST['location']));
    $message  = trim(clean($conn, $_POST['message']));

    // ── Patient Name ─────────────────────────────────────
    if (empty($patient))
        $errors['patient_name'] = 'Patient name is required.';
    elseif (strlen($patient) < 3)
        $errors['patient_name'] = 'Name must be at least 3 characters.';
    elseif (!preg_match("/^[a-zA-Z\s'.]+$/", $patient))
        $errors['patient_name'] = 'Name can only contain letters and spaces.';

    // ── Phone ─────────────────────────────────────────────
    if (empty($phone))
        $errors['phone'] = 'Contact phone number is required.';
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone))
        $errors['phone'] = 'Enter a valid 10-digit Indian mobile number (starts with 6-9).';

    // ── Blood Group ───────────────────────────────────────
    if (!$bg_id)
        $errors['blood_group_id'] = 'Please select the required blood group.';

    // ── Units ─────────────────────────────────────────────
    if ($units < 1)
        $errors['units_needed'] = 'At least 1 unit is required.';
    elseif ($units > 20)
        $errors['units_needed'] = 'Maximum 20 units per request.';

    // ── Hospital ──────────────────────────────────────────
    if (empty($hospital))
        $errors['hospital_name'] = 'Hospital name is required for emergency requests.';
    elseif (strlen($hospital) < 3)
        $errors['hospital_name'] = 'Enter a valid hospital name.';

    // ── Location ──────────────────────────────────────────
    if (empty($location))
        $errors['location'] = 'Location is required so we can find nearby donors.';

    // ── Message ───────────────────────────────────────────
    if (!empty($message) && strlen($message) > 500)
        $errors['message'] = 'Message must not exceed 500 characters.';

    if (empty($errors)) {
        // Check if emergency_requests table exists
        $chk = $conn->query("SHOW TABLES LIKE 'emergency_requests'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO emergency_requests (patient_name,phone,blood_group_id,units_needed,hospital_name,location,message,requested_by) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssiisssi", $patient, $phone, $bg_id, $units, $hospital, $location, $message, $uid);
            $stmt->execute();
        }
        // Notify ALL admins immediately
        $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
        while ($adm = $admins->fetch_assoc()) {
            $notif = "🚨 EMERGENCY: $units unit(s) of blood needed for $patient. Phone: $phone. Hospital: $hospital. Location: $location";
            $conn->query("INSERT INTO notifications (user_id,title,message,type) VALUES ({$adm['id']},'🚨 EMERGENCY Blood Request','$notif','danger')");
        }
        // Confirm notification to user
        $conn->query("INSERT INTO notifications (user_id,title,message,type) VALUES ($uid,'Emergency Request Submitted','Your emergency request has been submitted. Admin team alerted immediately.','warning')");
        redirect('/blood_bank/user/dashboard.php?emergency=1');
    }
}
function fe($e,$f){return isset($e[$f])?'is-invalid':'';}
function ov($o,$f){return htmlspecialchars($o[$f]??'');}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.ub-wrap{max-width:700px;}
.field-msg{font-size:11.5px;margin-top:4px;display:flex;align-items:center;gap:4px;}
.field-msg.ok{color:#27ae60;}.field-msg.err{color:#c0392b;}
.form-control:focus,.form-select:focus{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,0.12);}
.emerg-banner{background:linear-gradient(135deg,rgba(192,57,43,0.2),rgba(192,57,43,0.08));border:1.5px solid rgba(192,57,43,0.5);border-radius:16px;padding:18px 22px;color:white;margin-bottom:24px;display:flex;align-items:center;gap:14px;}
.pulse-dot{width:14px;height:14px;border-radius:50%;background:#e74c3c;flex-shrink:0;animation:dotPulse 1.2s ease-in-out infinite;}
@keyframes dotPulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(231,76,60,0.7);}50%{transform:scale(1.2);box-shadow:0 0 0 8px rgba(231,76,60,0);}}
</style>
<div class="ub-wrap">
    <div class="ub-section-heading mt-3"><i class="bi bi-exclamation-triangle-fill" style="color:#e74c3c;"></i>Emergency Blood Request</div>

    <!-- Emergency banner -->
    <div class="emerg-banner">
        <div class="pulse-dot"></div>
        <div>
            <div class="fw-bold" style="font-size:1rem;">🚨 Emergency Mode</div>
            <div style="opacity:0.75;font-size:0.82rem;margin-top:2px;">This form is for urgent/life-threatening situations only. All admins will be alerted <strong>immediately</strong>.</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="ub-alert-error"><i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
        <div><strong><?= count($errors) ?> error(s).</strong> Please fix them before submitting.</div>
    </div>
    <?php endif; ?>

    <div class="ub-card" style="border:2px solid rgba(192,57,43,0.4);">
        <div class="ub-card-header" style="background:linear-gradient(135deg,#96281b,#c0392b);"><i class="bi bi-heart-pulse-fill"></i>Emergency Request Form</div>
        <div class="card-body p-4">
        <form method="POST" id="emergForm" novalidate>
            <div class="row g-3">

                <!-- Patient Name -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Patient Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="text" name="patient_name" id="ePatient"
                            class="form-control <?= fe($errors,'patient_name') ?>"
                            placeholder="Full name of patient"
                            value="<?= ov($old,'patient_name') ?>" maxlength="100" required>
                        <div class="invalid-feedback"><?= $errors['patient_name']??'' ?></div>
                    </div>
                    <div class="field-msg" id="ePatientMsg"></div>
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Contact Phone <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-semibold" style="font-size:0.85rem;">+91</span>
                        <input type="tel" name="phone" id="ePhone"
                            class="form-control <?= fe($errors,'phone') ?>"
                            placeholder="10-digit mobile"
                            value="<?= ov($old,'phone') ?>" maxlength="10" inputmode="numeric" required>
                        <div class="invalid-feedback"><?= $errors['phone']??'' ?></div>
                    </div>
                    <div class="field-msg" id="ePhoneMsg"></div>
                </div>

                <!-- Blood Group + Units -->
                <div class="col-md-5">
                    <label class="form-label fw-semibold small">Blood Group <span class="text-danger">*</span></label>
                    <select name="blood_group_id" id="eBg" class="form-select <?= fe($errors,'blood_group_id') ?>" required>
                        <option value="">-- Select --</option>
                        <?php while($bg=$blood_groups->fetch_assoc()): ?>
                        <option value="<?= $bg['id'] ?>" <?= (($old['blood_group_id']??'')==$bg['id'])?'selected':'' ?>><?= $bg['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback"><?= $errors['blood_group_id']??'' ?></div>
                    <div class="field-msg" id="eBgMsg"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Units Needed <span class="text-danger">*</span></label>
                    <input type="number" name="units_needed" id="eUnits"
                        class="form-control <?= fe($errors,'units_needed') ?>"
                        min="1" max="20" value="<?= ov($old,'units_needed')?:1 ?>" required>
                    <div class="invalid-feedback"><?= $errors['units_needed']??'' ?></div>
                    <div class="field-msg" id="eUnitsMsg"></div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Urgency</label>
                    <div class="form-control d-flex align-items-center gap-2" style="background:#fff0ee;border-color:#f5c6c3;color:#c0392b;font-weight:700;">
                        <i class="bi bi-exclamation-triangle-fill"></i> Critical Emergency
                    </div>
                </div>

                <!-- Hospital -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Hospital Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-hospital-fill"></i></span>
                        <input type="text" name="hospital_name" id="eHospital"
                            class="form-control <?= fe($errors,'hospital_name') ?>"
                            placeholder="e.g. PMCH Patna, AIIMS"
                            value="<?= ov($old,'hospital_name') ?>" maxlength="150" required>
                        <div class="invalid-feedback"><?= $errors['hospital_name']??'' ?></div>
                    </div>
                    <div class="field-msg" id="eHospitalMsg"></div>
                </div>

                <!-- Location -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Location / Address <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                        <input type="text" name="location" id="eLocation"
                            class="form-control <?= fe($errors,'location') ?>"
                            placeholder="Ward, Street, City"
                            value="<?= ov($old,'location') ?>" maxlength="200" required>
                        <div class="invalid-feedback"><?= $errors['location']??'' ?></div>
                    </div>
                    <div class="field-msg" id="eLocationMsg"></div>
                </div>

                <!-- Message -->
                <div class="col-12">
                    <label class="form-label fw-semibold small">Additional Information <span class="text-muted fw-normal">(Optional)</span></label>
                    <textarea name="message" id="eMessage"
                        class="form-control <?= fe($errors,'message') ?>"
                        rows="3" maxlength="500"
                        placeholder="Surgery time, doctor name, patient condition, any other details..."><?= ov($old,'message') ?></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <div class="invalid-feedback d-block"><?= $errors['message']??'' ?></div>
                        <div class="text-muted small ms-auto" id="eMsgCount">0 / 500</div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="ub-alert-info py-2 mb-3"><i class="bi bi-info-circle-fill flex-shrink-0"></i>
                        Admins will be notified immediately. You can track this on your dashboard.
                    </div>
                    <button type="submit" class="btn btn-danger px-5 fw-bold py-2" style="font-size:1rem;">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Submit Emergency Request
                    </button>
                    <a href="/blood_bank/user/dashboard.php" class="btn btn-outline-secondary ms-2 py-2">Cancel</a>
                </div>
            </div>
        </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function ok(el,msg,t){el.classList.add('is-valid');el.classList.remove('is-invalid');if(msg){msg.className='field-msg ok';msg.innerHTML='<i class="bi bi-check-circle-fill"></i> '+t;}}
function err(el,msg,t){el.classList.add('is-invalid');el.classList.remove('is-valid');if(msg){msg.className='field-msg err';msg.innerHTML='<i class="bi bi-x-circle-fill"></i> '+t;}}
function clr(el,msg){el.classList.remove('is-valid','is-invalid');if(msg)msg.innerHTML='';}

// Patient name
const ep=document.getElementById('ePatient'),epm=document.getElementById('ePatientMsg');
if(ep)ep.addEventListener('input',function(){const v=this.value.trim();if(!v){clr(this,epm);return;}if(v.length<3)err(this,epm,'Min 3 characters.');else if(!/^[a-zA-Z\s\'.]+$/.test(v))err(this,epm,'Letters and spaces only.');else ok(this,epm,'Looks good!');});

// Phone
const eph=document.getElementById('ePhone'),ephm=document.getElementById('ePhoneMsg');
if(eph){eph.addEventListener('keypress',e=>{if(!/[0-9]/.test(e.key))e.preventDefault();});eph.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,10);const v=this.value;if(!v){clr(this,ephm);return;}if(v.length<10)err(this,ephm,`${10-v.length} more digit(s) needed.`);else if(!/^[6-9]/.test(v))err(this,ephm,'Must start with 6,7,8 or 9.');else ok(this,ephm,'Valid mobile number ✓');});}

// Blood group
const ebg=document.getElementById('eBg'),ebgm=document.getElementById('eBgMsg');
if(ebg)ebg.addEventListener('change',function(){if(!this.value)err(this,ebgm,'Select a blood group.');else ok(this,ebgm,'Blood group selected ✓');});

// Units
const eu=document.getElementById('eUnits'),eum=document.getElementById('eUnitsMsg');
if(eu)eu.addEventListener('input',function(){const v=parseInt(this.value);if(!this.value){clr(this,eum);return;}if(v<1)err(this,eum,'At least 1 unit.');else if(v>20)err(this,eum,'Max 20 units.');else ok(this,eum,v+' unit(s) ✓');});

// Hospital
const eh=document.getElementById('eHospital'),ehm=document.getElementById('eHospitalMsg');
if(eh)eh.addEventListener('input',function(){const v=this.value.trim();if(!v){clr(this,ehm);return;}if(v.length<3)err(this,ehm,'Enter hospital name.');else ok(this,ehm,'Hospital entered ✓');});

// Location
const el=document.getElementById('eLocation'),elm=document.getElementById('eLocationMsg');
if(el)el.addEventListener('input',function(){const v=this.value.trim();if(!v){clr(this,elm);return;}if(v.length<3)err(this,elm,'Enter location.');else ok(this,elm,'Location entered ✓');});

// Message counter
const em=document.getElementById('eMessage'),emc=document.getElementById('eMsgCount');
if(em)em.addEventListener('input',function(){const l=this.value.length;emc.textContent=l+' / 500';emc.style.color=l>450?'#c0392b':'#adb5bd';});

// Final submit guard
document.getElementById('emergForm').addEventListener('submit',function(e){
    const errs=[];
    if(!document.getElementById('ePatient').value.trim()||document.getElementById('ePatient').value.trim().length<3)errs.push('Patient name must be at least 3 characters.');
    if(!/^[6-9][0-9]{9}$/.test(document.getElementById('ePhone').value))errs.push('Enter a valid 10-digit mobile number starting with 6-9.');
    if(!document.getElementById('eBg').value)errs.push('Please select a blood group.');
    const u=parseInt(document.getElementById('eUnits').value);if(!u||u<1||u>20)errs.push('Units must be between 1 and 20.');
    if(!document.getElementById('eHospital').value.trim())errs.push('Hospital name is required for emergency requests.');
    if(!document.getElementById('eLocation').value.trim())errs.push('Location is required.');
    if(errs.length>0){
        e.preventDefault();
        let box=document.getElementById('jsErrBox');
        if(!box){box=document.createElement('div');box.id='jsErrBox';this.prepend(box);}
        box.className='ub-alert-error mb-3';
        box.innerHTML='<i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i><div><strong>Please fix:</strong><ul class="mb-0 mt-1 ps-3">'+errs.map(r=>`<li>${r}</li>`).join('')+'</ul></div>';
        window.scrollTo({top:0,behavior:'smooth'});
    }
});
</script>
