<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Emergency Requests';

// Close/Fulfill
if (isset($_GET['fulfill'])) {
    $id = (int)$_GET['fulfill'];
    $conn->query("UPDATE emergency_requests SET status='Fulfilled' WHERE id=$id");
    redirect('/blood_bank/admin/emergency.php?msg=fulfilled');
}
if (isset($_GET['close'])) {
    $id = (int)$_GET['close'];
    $conn->query("UPDATE emergency_requests SET status='Closed' WHERE id=$id");
    redirect('/blood_bank/admin/emergency.php?msg=closed');
}

$blood_groups = $conn->query("SELECT * FROM blood_groups");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient  = trim(clean($conn, $_POST['patient_name']));
    $phone    = trim(clean($conn, $_POST['phone']));
    $bg_id    = (int)$_POST['blood_group_id'];
    $units    = (int)$_POST['units_needed'];
    $hospital = trim(clean($conn, $_POST['hospital_name']));
    $location = trim(clean($conn, $_POST['location']));
    $message  = trim(clean($conn, $_POST['message']));
    $uid      = $_SESSION['user_id'];

    // ── Full validation ──────────────────────────────────
    if (empty($patient))
        $errors[] = 'Patient name is required.';
    elseif (strlen($patient) < 3)
        $errors[] = 'Patient name must be at least 3 characters.';
    elseif (!preg_match("/^[a-zA-Z\s'.]+$/", $patient))
        $errors[] = 'Patient name can only contain letters and spaces.';

    if (empty($phone))
        $errors[] = 'Contact phone number is required.';
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone))
        $errors[] = 'Enter a valid 10-digit Indian mobile number (starts with 6-9).';

    if (!$bg_id)
        $errors[] = 'Blood group is required.';
    else {
        $bg_chk = $conn->query("SELECT id FROM blood_groups WHERE id=$bg_id");
        if (!$bg_chk || $bg_chk->num_rows === 0) $errors[] = 'Invalid blood group selected.';
    }

    if ($units < 1)
        $errors[] = 'Units needed must be at least 1.';
    elseif ($units > 20)
        $errors[] = 'Maximum 20 units per emergency request.';

    if (!empty($message) && strlen($message) > 500)
        $errors[] = 'Message must not exceed 500 characters.';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO emergency_requests (patient_name,phone,blood_group_id,units_needed,hospital_name,location,message,requested_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiisssi", $patient, $phone, $bg_id, $units, $hospital, $location, $message, $uid);
        if ($stmt->execute()) {
            // Notify all admins
            $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
            while ($adm = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES
                    ({$adm['id']}, 'EMERGENCY: Blood Needed', 'Emergency request for $units units of blood group. Patient: $patient. Phone: $phone', 'danger')");
            }
            redirect('/blood_bank/admin/emergency.php?msg=created');
        }
    }
}

$open_requests = $conn->query("SELECT er.*, bg.name as bg_name FROM emergency_requests er
    JOIN blood_groups bg ON er.blood_group_id=bg.id WHERE er.status='Open' ORDER BY er.created_at DESC");
$all_requests  = $conn->query("SELECT er.*, bg.name as bg_name FROM emergency_requests er
    JOIN blood_groups bg ON er.blood_group_id=bg.id ORDER BY er.created_at DESC");
$open_count    = $open_requests->num_rows;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">
            <i class="bi bi-exclamation-octagon-fill me-2 text-danger"></i>Emergency Requests
        </h1>
        <?php if ($open_count > 0): ?>
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?= $open_count ?> OPEN EMERGENCY<?= $open_count>1?'S':'' ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?= $_GET['msg']==='created'?'danger':'success' ?> alert-dismissible">
        <?= ['created'=>'🚨 Emergency request posted! All admins notified.',
             'fulfilled'=>'✅ Marked as fulfilled.',
             'closed'=>'Request closed.'][$_GET['msg']] ?? 'Done.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= $e ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Open Emergencies Alert Banner -->
    <?php if ($open_count > 0): $open_requests->data_seek(0); while ($er = $open_requests->fetch_assoc()): ?>
    <div class="card border-danger mb-3" style="border-width:2px!important;background:#fff8f8;">
        <div class="card-body py-3 px-4">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:44px;height:44px;background:#dc3545;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-exclamation-lg text-white fs-4"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-danger">🚨 URGENT: <?= $er['units_needed'] ?> units of
                            <span class="bg-badge"><?= $er['bg_name'] ?></span> needed
                        </div>
                        <div class="small text-muted">
                            Patient: <strong><?= htmlspecialchars($er['patient_name']) ?></strong> |
                            Phone: <strong><?= htmlspecialchars($er['phone']) ?></strong> |
                            Hospital: <?= htmlspecialchars($er['hospital_name'] ?? 'N/A') ?> |
                            Location: <?= htmlspecialchars($er['location'] ?? 'N/A') ?>
                        </div>
                        <?php if ($er['message']): ?>
                        <div class="small text-muted">Note: <?= htmlspecialchars($er['message']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="/blood_bank/admin/smart_match.php?bg=<?= $er['blood_group_id'] ?>&units=<?= $er['units_needed'] ?>"
                       class="btn btn-danger btn-sm">Find Donors</a>
                    <a href="?fulfill=<?= $er['id'] ?>" class="btn btn-success btn-sm"
                       onclick="return confirm('Mark as fulfilled?')">Fulfilled</a>
                    <a href="?close=<?= $er['id'] ?>" class="btn btn-outline-secondary btn-sm">Close</a>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>

    <div class="row g-4">
        <!-- New Emergency Form -->
        <div class="col-lg-4">
            <div class="card border-danger" style="border-width:2px!important;">
                <div class="card-header py-3 px-4 fw-semibold text-danger bg-danger bg-opacity-10">
                    <i class="bi bi-plus-circle me-2"></i>Post Emergency Request
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Patient Name <span class="text-danger">*</span></label>
                            <input type="text" name="patient_name" class="form-control" placeholder="Full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile" maxlength="10" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label fw-semibold small">Blood Group <span class="text-danger">*</span></label>
                                <select name="blood_group_id" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <?php $blood_groups->data_seek(0); while ($bg = $blood_groups->fetch_assoc()): ?>
                                    <option value="<?= $bg['id'] ?>"><?= $bg['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-semibold small">Units <span class="text-danger">*</span></label>
                                <input type="number" name="units_needed" class="form-control" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Hospital Name</label>
                            <input type="text" name="hospital_name" class="form-control" placeholder="e.g. PMCH Patna">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Patna, Bihar">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Message</label>
                            <textarea name="message" class="form-control" rows="2" placeholder="Any additional info..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 fw-semibold">
                            <i class="bi bi-exclamation-triangle me-2"></i>Post Emergency Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- All Requests List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">All Emergency Requests</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">Patient</th><th>Blood</th><th>Units</th>
                            <th>Hospital</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php $all_requests->data_seek(0); if ($all_requests->num_rows===0): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No emergency requests yet.</td></tr>
                        <?php else: while ($er = $all_requests->fetch_assoc()):
                            $sc = ['Open'=>'danger','Fulfilled'=>'success','Closed'=>'secondary'][$er['status']];
                        ?>
                        <tr <?= $er['status']==='Open'?'style="background:rgba(220,53,69,0.04);"':'' ?>>
                            <td class="ps-4">
                                <div class="fw-semibold"><?= htmlspecialchars($er['patient_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($er['phone']) ?></div>
                            </td>
                            <td><span class="bg-badge"><?= $er['bg_name'] ?></span></td>
                            <td><?= $er['units_needed'] ?></td>
                            <td class="small"><?= htmlspecialchars($er['hospital_name'] ?? '—') ?></td>
                            <td><span class="badge bg-<?= $sc ?>"><?= $er['status'] ?></span></td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($er['created_at'])) ?></td>
                            <td>
                                <?php if ($er['status']==='Open'): ?>
                                <a href="?fulfill=<?= $er['id'] ?>" class="btn btn-sm btn-outline-success me-1">✓</a>
                                <a href="?close=<?= $er['id'] ?>"   class="btn btn-sm btn-outline-secondary">✕</a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Live phone validation
const ephEl = document.querySelector('input[name="phone"]');
if(ephEl){
    ephEl.addEventListener('keypress',e=>{if(!/[0-9]/.test(e.key))e.preventDefault();});
    ephEl.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,10);});
}
// Submit guard
document.querySelector('form')?.addEventListener('submit',function emergFormValidate(e){
    const errs=[];
    const pt=this.querySelector('[name="patient_name"]')?.value.trim();
    const ph=this.querySelector('[name="phone"]')?.value.trim();
    const bg=this.querySelector('[name="blood_group_id"]')?.value;
    const un=parseInt(this.querySelector('[name="units_needed"]')?.value||0);
    if(!pt||pt.length<3) errs.push('Patient name must be at least 3 characters.');
    if(pt&&!/^[a-zA-Z\s\'.]+$/.test(pt)) errs.push('Patient name: letters and spaces only.');
    if(!ph||!/^[6-9][0-9]{9}$/.test(ph)) errs.push('Valid 10-digit mobile number required.');
    if(!bg) errs.push('Please select a blood group.');
    if(!un||un<1||un>20) errs.push('Units must be between 1 and 20.');
    if(errs.length>0){
        e.preventDefault();
        let box=document.getElementById('jsEmErrBox');
        if(!box){box=document.createElement('div');box.id='jsEmErrBox';this.prepend(box);}
        box.className='alert alert-danger small mb-3';
        box.innerHTML='<strong><i class="bi bi-exclamation-triangle me-2"></i>Fix:</strong><ul class="mb-0 mt-1 ps-3">'+errs.map(r=>`<li>${r}</li>`).join('')+'</ul>';
        window.scrollTo({top:0,behavior:'smooth'});
    }
});
</script>
