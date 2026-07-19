<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Hospitals';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM hospitals WHERE id=$id");
    redirect('/blood_bank/admin/hospitals.php?msg=deleted');
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old     = $_POST;
    $name    = trim(clean($conn, $_POST['name']));
    $address = trim(clean($conn, $_POST['address']));
    $phone   = trim(clean($conn, $_POST['phone']));
    $email   = trim(clean($conn, $_POST['email']));
    $id      = (int)($_POST['id'] ?? 0);

    // ── Validation ──────────────────────────────────────
    if (empty($name))
        $errors['name'] = 'Hospital name is required.';
    elseif (strlen($name) < 3)
        $errors['name'] = 'Name must be at least 3 characters.';
    elseif (strlen($name) > 150)
        $errors['name'] = 'Name must not exceed 150 characters.';

    if (!empty($phone)) {
        if (!preg_match('/^[0-9\-\+\(\)\s]{7,15}$/', $phone))
            $errors['phone'] = 'Enter a valid phone number (7-15 digits).';
    }

    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Enter a valid email address.';
    }

    // Check duplicate name (exclude self on edit)
    if (empty($errors['name'])) {
        $dup_where = $id ? "AND id != $id" : '';
        $dup = $conn->query("SELECT id FROM hospitals WHERE name='$name' $dup_where");
        if ($dup && $dup->num_rows > 0)
            $errors['name'] = 'A hospital with this name already exists.';
    }

    if (empty($errors)) {
        if ($id) {
            $stmt = $conn->prepare("UPDATE hospitals SET name=?,address=?,phone=?,email=? WHERE id=?");
            $stmt->bind_param("ssssi", $name, $address, $phone, $email, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hospitals (name,address,phone,email) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name, $address, $phone, $email);
        }
        $stmt->execute();
        redirect('/blood_bank/admin/hospitals.php?msg=saved');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM hospitals WHERE id=".(int)$_GET['edit'])->fetch_assoc();
    if ($edit) $old = $edit;
}

$hospitals = $conn->query("SELECT * FROM hospitals ORDER BY created_at DESC");

function fe($e,$f){return isset($e[$f])?'is-invalid':'';}
function ov($o,$f){return htmlspecialchars($o[$f]??'');}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-hospital me-2 text-danger"></i>Hospitals</h1>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?= $_GET['msg']==='deleted'?'warning':'success' ?> alert-dismissible">
        <i class="bi bi-<?= $_GET['msg']==='deleted'?'trash':'check-circle' ?>-fill me-2"></i>
        <?= $_GET['msg']==='deleted' ? 'Hospital removed.' : 'Hospital saved successfully.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong><?= count($errors) ?> error(s).</strong> Please fix them below.
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">
                    <i class="bi bi-<?= $edit ? 'pencil' : 'plus-circle' ?>-fill me-2 text-danger"></i>
                    <?= $edit ? 'Edit Hospital' : 'Add New Hospital' ?>
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="hospForm" novalidate>
                        <?php if ($edit): ?>
                        <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Hospital Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" id="hospName"
                                class="form-control <?= fe($errors,'name') ?>"
                                placeholder="e.g. PMCH Patna"
                                value="<?= ov($old,'name') ?>" maxlength="150" required>
                            <div class="invalid-feedback"><?= $errors['name']??'' ?></div>
                            <div id="hospNameMsg" style="font-size:11.5px;margin-top:4px;"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                maxlength="300"
                                placeholder="Full address..."><?= ov($old,'address') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" id="hospPhone"
                                    class="form-control <?= fe($errors,'phone') ?>"
                                    placeholder="e.g. 0612-2345678"
                                    value="<?= ov($old,'phone') ?>" maxlength="15">
                            </div>
                            <div class="invalid-feedback"><?= $errors['phone']??'' ?></div>
                            <div id="hospPhoneMsg" style="font-size:11.5px;margin-top:4px;"></div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" id="hospEmail"
                                    class="form-control <?= fe($errors,'email') ?>"
                                    placeholder="hospital@example.com"
                                    value="<?= ov($old,'email') ?>" maxlength="100">
                            </div>
                            <div class="invalid-feedback"><?= $errors['email']??'' ?></div>
                            <div id="hospEmailMsg" style="font-size:11.5px;margin-top:4px;"></div>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 fw-semibold">
                            <i class="bi bi-<?= $edit?'save':'plus-circle' ?> me-2"></i>
                            <?= $edit ? 'Update Hospital' : 'Add Hospital' ?>
                        </button>
                        <?php if ($edit): ?>
                        <a href="/blood_bank/admin/hospitals.php" class="btn btn-outline-secondary w-100 mt-2">
                            Cancel Edit
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-list-ul me-2 text-danger"></i>All Hospitals</span>
                    <span class="badge bg-danger"><?= $hospitals->num_rows ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($hospitals->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hospitals added yet.</td></tr>
                        <?php else: $i=1; while ($h = $hospitals->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><?= $i++ ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($h['name']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($h['phone']??'—') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($h['email']??'—') ?></td>
                            <td>
                                <a href="?edit=<?= $h['id'] ?>"
                                   class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?delete=<?= $h['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Delete this hospital?')">
                                    <i class="bi bi-trash"></i>
                                </a>
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
function setV(el,msg,ok,txt){
    el.classList.toggle('is-valid',ok); el.classList.toggle('is-invalid',!ok);
    if(msg){msg.style.color=ok?'#27ae60':'#c0392b'; msg.innerHTML=ok?'<i class="bi bi-check-circle-fill me-1"></i>'+txt:'<i class="bi bi-x-circle-fill me-1"></i>'+txt;}
}
function clrV(el,msg){ el.classList.remove('is-valid','is-invalid'); if(msg) msg.innerHTML=''; }

// Hospital name
const hn=document.getElementById('hospName'), hnm=document.getElementById('hospNameMsg');
if(hn) hn.addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){clrV(this,hnm);return;}
    if(v.length<3)         setV(this,hnm,false,'Min 3 characters.');
    else if(v.length>150)  setV(this,hnm,false,'Max 150 characters.');
    else                   setV(this,hnm,true,'Looks good!');
});

// Phone
const hph=document.getElementById('hospPhone'), hphm=document.getElementById('hospPhoneMsg');
if(hph) hph.addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){clrV(this,hphm);return;}
    const ok=/^[0-9\-\+\(\)\s]{7,15}$/.test(v);
    setV(this,hphm,ok,ok?'Valid phone ✓':'7-15 digits only.');
});

// Email
const hem=document.getElementById('hospEmail'), hemm=document.getElementById('hospEmailMsg');
if(hem) hem.addEventListener('input',function(){
    const v=this.value.trim();
    if(!v){clrV(this,hemm);return;}
    const ok=/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
    setV(this,hemm,ok,ok?'Valid email ✓':'Invalid email format.');
});

// Submit guard
document.getElementById('hospForm').addEventListener('submit',function(e){
    const errs=[];
    const name=document.getElementById('hospName').value.trim();
    if(!name||name.length<3) errs.push('Hospital name must be at least 3 characters.');
    const phone=document.getElementById('hospPhone').value.trim();
    if(phone && !/^[0-9\-\+\(\)\s]{7,15}$/.test(phone)) errs.push('Enter a valid phone number.');
    const email=document.getElementById('hospEmail').value.trim();
    if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) errs.push('Enter a valid email address.');
    if(errs.length>0){
        e.preventDefault();
        let box=document.getElementById('jsErrBox');
        if(!box){box=document.createElement('div');box.id='jsErrBox';this.prepend(box);}
        box.className='alert alert-danger mt-2 small';
        box.innerHTML='<strong><i class="bi bi-exclamation-triangle me-2"></i>Fix:</strong><ul class="mb-0 mt-1 ps-3">'+errs.map(r=>`<li>${r}</li>`).join('')+'</ul>';
        window.scrollTo({top:0,behavior:'smooth'});
    }
});
</script>
