<?php
// ============================================================
// BloodSync - Request Blood (with full validation)
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title  = 'Request Blood';
$uid         = (int)$_SESSION['user_id'];
$user        = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$blood_groups= $conn->query("SELECT * FROM blood_groups ORDER BY id");
$hospitals   = $conn->query("SELECT * FROM hospitals ORDER BY name");
$errors      = [];
$old         = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old          = $_POST;
    $req_name     = trim(clean($conn, $_POST['requester_name']));
    $phone        = trim(clean($conn, $_POST['phone']));
    $bg_id        = (int)($_POST['blood_group_id'] ?? 0);
    $units        = (int)($_POST['units_needed'] ?? 0);
    $hosp_id      = !empty($_POST['hospital_id']) ? (int)$_POST['hospital_id'] : null;
    $urgency      = trim(clean($conn, $_POST['urgency']));
    $notes        = trim(clean($conn, $_POST['notes']));

    // ── Requester Name ───────────────────────────────────
    if (empty($req_name))
        $errors['requester_name'] = 'Patient / requester name is required.';
    elseif (strlen($req_name) < 3)
        $errors['requester_name'] = 'Name must be at least 3 characters.';
    elseif (!preg_match("/^[a-zA-Z\s'.]+$/", $req_name))
        $errors['requester_name'] = 'Name can only contain letters and spaces.';

    // ── Phone ────────────────────────────────────────────
    if (empty($phone))
        $errors['phone'] = 'Contact phone number is required.';
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone))
        $errors['phone'] = 'Enter a valid 10-digit Indian mobile number (starts with 6-9).';

    // ── Blood Group ──────────────────────────────────────
    if (!$bg_id)
        $errors['blood_group_id'] = 'Please select the required blood group.';
    else {
        $chk = $conn->query("SELECT id FROM blood_groups WHERE id=$bg_id");
        if (!$chk || $chk->num_rows === 0) $errors['blood_group_id'] = 'Invalid blood group selected.';
    }

    // ── Units ────────────────────────────────────────────
    if ($units < 1)
        $errors['units_needed'] = 'Units needed must be at least 1.';
    elseif ($units > 20)
        $errors['units_needed'] = 'Maximum 20 units per request.';

    // ── Urgency ──────────────────────────────────────────
    if (!in_array($urgency, ['Normal','Urgent','Critical']))
        $errors['urgency'] = 'Please select urgency level.';

    // ── Notes length ─────────────────────────────────────
    if (!empty($notes) && strlen($notes) > 500)
        $errors['notes'] = 'Notes must not exceed 500 characters.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO blood_requests (requester_name,phone,blood_group_id,units_needed,hospital_id,urgency,notes,requested_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiisssi", $req_name, $phone, $bg_id, $units, $hosp_id, $urgency, $notes, $uid);
        if ($stmt->execute()) {
            // Notify admins
            $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
            while ($adm = $admins->fetch_assoc()) {
                $title = "New Blood Request: $urgency";
                $msg   = "{$_SESSION['name']} requested $units unit(s) of blood. Phone: $phone";
                $conn->query("INSERT INTO notifications (user_id,title,message,type) VALUES ({$adm['id']},'$title','$msg','".($urgency==='Critical'?'danger':($urgency==='Urgent'?'warning':'info'))."')");
            }
            redirect('/blood_bank/user/dashboard.php?msg=1');
        } else {
            $errors['general'] = 'Failed to submit request. Please try again.';
        }
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
.urgency-cards{display:flex;gap:10px;flex-wrap:wrap;}
.urgency-card{flex:1;min-width:100px;padding:12px 8px;border-radius:12px;border:2px solid #e9ecef;cursor:pointer;text-align:center;transition:all 0.2s;font-size:0.8rem;font-weight:600;color:#6c757d;}
.urgency-card:hover{border-color:#c0392b;}
.urgency-card.selected-normal{border-color:#27ae60;background:#e8f8f0;color:#155c2e;}
.urgency-card.selected-urgent{border-color:#e67e22;background:#fff5e6;color:#7c4600;}
.urgency-card.selected-critical{border-color:#c0392b;background:#fff0ee;color:#7b1f1f;}
.urgency-card i{font-size:1.3rem;display:block;margin-bottom:4px;}
</style>
<div class="ub-wrap">
    <div class="ub-section-heading mt-3"><i class="bi bi-clipboard-plus-fill"></i>Request Blood</div>

    <?php if (!empty($errors)): ?>
    <div class="ub-alert-error"><i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
        <div><strong><?=count($errors)?> error(s).</strong> Please fix them below before submitting.</div>
    </div>
    <?php endif; ?>
    <?php if (!empty($errors['general'])): ?>
    <div class="ub-alert-error"><i class="bi bi-x-circle-fill fs-5"></i><?=$errors['general']?></div>
    <?php endif; ?>

    <div class="ub-card">
        <div class="ub-card-header"><i class="bi bi-clipboard-plus"></i>Blood Request Form</div>
        <div class="card-body p-4">
        <form method="POST" id="reqForm" novalidate>

            <div class="row g-3">
                <!-- Requester Name -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Patient / Requester Name <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="requester_name" id="reqName"
                            class="form-control <?=fe($errors,'requester_name')?>"
                            placeholder="Full name of patient"
                            value="<?=ov($old,'requester_name')?>" maxlength="100" required>
                        <div class="invalid-feedback"><?=$errors['requester_name']??''?></div>
                    </div>
                    <div class="field-msg" id="reqNameMsg"></div>
                </div>

                <!-- Phone -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Contact Phone <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text fw-semibold" style="font-size:0.85rem;">+91</span>
                        <input type="tel" name="phone" id="reqPhone"
                            class="form-control <?=fe($errors,'phone')?>"
                            placeholder="10-digit mobile"
                            value="<?=ov($old,'phone')?>" maxlength="10" inputmode="numeric" required>
                        <div class="invalid-feedback"><?=$errors['phone']??''?></div>
                    </div>
                    <div class="field-msg" id="reqPhoneMsg"></div>
                </div>

                <!-- Blood Group -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Blood Group Required <span class="text-danger">*</span></label>
                    <select name="blood_group_id" id="reqBg" class="form-select <?=fe($errors,'blood_group_id')?>" required>
                        <option value="">-- Select --</option>
                        <?php while($bg=$blood_groups->fetch_assoc()): ?>
                        <option value="<?=$bg['id']?>" <?=(($old['blood_group_id']??'')==$bg['id'])?'selected':''?>><?=$bg['name']?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="invalid-feedback"><?=$errors['blood_group_id']??''?></div>
                    <div class="field-msg" id="reqBgMsg"></div>
                </div>

                <!-- Units -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Units Needed <span class="text-danger">*</span></label>
                    <input type="number" name="units_needed" id="reqUnits"
                        class="form-control <?=fe($errors,'units_needed')?>"
                        min="1" max="20" value="<?=ov($old,'units_needed')?:1?>" required>
                    <div class="invalid-feedback"><?=$errors['units_needed']??''?></div>
                    <div class="field-msg" id="reqUnitsMsg"></div>
                </div>

                <!-- Hospital -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Hospital <span class="text-muted fw-normal">(Optional)</span></label>
                    <select name="hospital_id" class="form-select">
                        <option value="">Select hospital...</option>
                        <?php $hospitals->data_seek(0); while($h=$hospitals->fetch_assoc()): ?>
                        <option value="<?=$h['id']?>" <?=(($old['hospital_id']??'')==$h['id'])?'selected':''?>><?=htmlspecialchars($h['name'])?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Urgency -->
                <div class="col-12">
                    <label class="form-label fw-semibold small">Urgency Level <span class="text-danger">*</span></label>
                    <input type="hidden" name="urgency" id="urgencyInput" value="<?=ov($old,'urgency')?:'Normal'?>">
                    <div class="urgency-cards">
                        <?php foreach([
                            ['Normal','check-circle-fill','Normal','#27ae60'],
                            ['Urgent','exclamation-circle-fill','Urgent','#e67e22'],
                            ['Critical','exclamation-triangle-fill','Critical – Life Threatening','#c0392b'],
                        ] as [$val,$icon,$label,$color]): ?>
                        <div class="urgency-card <?=(($old['urgency']??'Normal')===$val)?'selected-'.strtolower($val):''?>"
                             onclick="selectUrgency('<?=$val?>')">
                            <i class="bi bi-<?=$icon?>" style="color:<?=$color?>;"></i><?=$label?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if(!empty($errors['urgency'])): ?><div class="text-danger small mt-1"><i class="bi bi-x-circle me-1"></i><?=$errors['urgency']?></div><?php endif; ?>
                </div>

                <!-- Notes -->
                <div class="col-12">
                    <label class="form-label fw-semibold small">Additional Notes <span class="text-muted fw-normal">(Optional)</span></label>
                    <textarea name="notes" id="reqNotes" class="form-control <?=fe($errors,'notes')?>"
                        rows="3" maxlength="500"
                        placeholder="Any additional information — patient condition, doctor details, surgery time..."><?=ov($old,'notes')?></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <div class="invalid-feedback d-block"><?=$errors['notes']??''?></div>
                        <div class="text-muted small ms-auto" id="notesCount">0 / 500</div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="ub-alert-info py-2"><i class="bi bi-info-circle-fill flex-shrink-0"></i>
                        Your request will be reviewed by an admin. You will be notified once approved or rejected.
                    </div>
                    <button type="submit" class="btn btn-danger px-5 fw-semibold py-2" id="submitReqBtn">
                        <i class="bi bi-send-fill me-2"></i>Submit Blood Request
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

// Name
const rn=document.getElementById('reqName'),rnm=document.getElementById('reqNameMsg');
if(rn)rn.addEventListener('input',function(){const v=this.value.trim();if(!v){clr(this,rnm);return;}if(v.length<3)err(this,rnm,'Min 3 characters.');else if(!/^[a-zA-Z\s\'.]+$/.test(v))err(this,rnm,'Letters and spaces only.');else ok(this,rnm,'Looks good!');});

// Phone
const rp=document.getElementById('reqPhone'),rpm=document.getElementById('reqPhoneMsg');
if(rp){rp.addEventListener('keypress',e=>{if(!/[0-9]/.test(e.key))e.preventDefault();});rp.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,10);const v=this.value;if(!v){clr(this,rpm);return;}if(v.length<10)err(this,rpm,`${10-v.length} more digit(s) needed.`);else if(!/^[6-9]/.test(v))err(this,rpm,'Must start with 6,7,8 or 9.');else ok(this,rpm,'Valid mobile number ✓');});}

// Blood group
const rb=document.getElementById('reqBg'),rbm=document.getElementById('reqBgMsg');
if(rb)rb.addEventListener('change',function(){if(!this.value)err(this,rbm,'Please select a blood group.');else ok(this,rbm,'Blood group selected ✓');});

// Units
const ru=document.getElementById('reqUnits'),rum=document.getElementById('reqUnitsMsg');
if(ru)ru.addEventListener('input',function(){const v=parseInt(this.value);if(!this.value){clr(this,rum);return;}if(v<1)err(this,rum,'At least 1 unit required.');else if(v>20)err(this,rum,'Maximum 20 units per request.');else ok(this,rum,`${v} unit(s) — valid ✓`);});

// Notes counter
const nt=document.getElementById('reqNotes'),nc=document.getElementById('notesCount');
if(nt)nt.addEventListener('input',function(){const l=this.value.length;nc.textContent=l+' / 500';nc.style.color=l>450?'#c0392b':'#adb5bd';});

// Urgency selector
function selectUrgency(val){
    document.getElementById('urgencyInput').value=val;
    document.querySelectorAll('.urgency-card').forEach(c=>c.className='urgency-card');
    event.currentTarget.classList.add('selected-'+val.toLowerCase());
}

// Final submit guard
document.getElementById('reqForm').addEventListener('submit',function(e){
    const errs=[];
    const name=document.getElementById('reqName').value.trim();
    const phone=document.getElementById('reqPhone').value.trim();
    const bg=document.getElementById('reqBg').value;
    const units=parseInt(document.getElementById('reqUnits').value);
    const urgency=document.getElementById('urgencyInput').value;
    if(!name||name.length<3)errs.push('Patient name must be at least 3 characters.');
    if(!/^[6-9][0-9]{9}$/.test(phone))errs.push('Enter a valid 10-digit mobile number starting with 6-9.');
    if(!bg)errs.push('Please select a blood group.');
    if(!units||units<1||units>20)errs.push('Units must be between 1 and 20.');
    if(!urgency)errs.push('Please select an urgency level.');
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
