<?php
// ============================================================
// BloodSync - Book Donation Appointment (Donor only)
// Notifies admin + updates donation history when completed
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

// Only donors can access this
if (($_SESSION['role_type'] ?? 'receiver') !== 'donor') {
    redirect('/blood_bank/user/dashboard.php');
}

$page_title = 'Book Appointment';
$uid        = (int)$_SESSION['user_id'];
$user       = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$blood_groups = $conn->query("SELECT * FROM blood_groups ORDER BY id");
$errors     = [];
$old        = [];
$success    = '';

// ── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old           = $_POST;
    $pref_date     = trim(clean($conn, $_POST['preferred_date']));
    $pref_time     = trim(clean($conn, $_POST['preferred_time']));
    $bg_id         = (int)($_POST['blood_group_id'] ?? 0);
    $notes         = trim(clean($conn, $_POST['notes']));

    // Validate date
    if (empty($pref_date))
        $errors['preferred_date'] = 'Appointment date is required.';
    elseif ($pref_date < date('Y-m-d'))
        $errors['preferred_date'] = 'Appointment date cannot be in the past.';
    elseif ($pref_date > date('Y-m-d', strtotime('+90 days')))
        $errors['preferred_date'] = 'Appointment can only be booked up to 90 days in advance.';

    // Validate time
    if (empty($pref_time))
        $errors['preferred_time'] = 'Appointment time is required.';

    // Validate blood group
    if (!$bg_id)
        $errors['blood_group_id'] = 'Please select your blood group.';

    // Check if donor already has a pending appointment on the same date
    if (empty($errors)) {
        $chk = $conn->query("SHOW TABLES LIKE 'user_appointments'");
        if ($chk && $chk->num_rows > 0) {
            $dup = $conn->query("SELECT id FROM user_appointments WHERE user_id=$uid AND preferred_date='$pref_date' AND status IN ('Pending','Confirmed')");
            if ($dup && $dup->num_rows > 0)
                $errors['preferred_date'] = 'You already have an appointment on this date.';
        }
    }

    if (empty($errors)) {
        // Check if table exists, create if not
        $conn->query("CREATE TABLE IF NOT EXISTS user_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            donor_id INT DEFAULT NULL,
            preferred_date DATE NOT NULL,
            preferred_time TIME NOT NULL,
            blood_group_id INT NOT NULL,
            notes TEXT,
            status ENUM('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $conn->prepare(
            "INSERT INTO user_appointments (user_id, preferred_date, preferred_time, blood_group_id, notes)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param("isssi", $uid, $pref_date, $pref_time, $bg_id, $notes);

        if ($stmt->execute()) {
            $appt_id = $conn->insert_id;

            // Notify all admins
            $bg_name = $conn->query("SELECT name FROM blood_groups WHERE id=$bg_id")->fetch_assoc()['name'];
            $notif_title = "Donation Appointment Booked";
            $notif_msg   = "{$_SESSION['name']} has booked a donation appointment for $pref_date at "
                         . date('h:i A', strtotime($pref_time)) . ". Blood Group: $bg_name.";
            $notif_sql   = clean($conn, $notif_msg);

            $admins = $conn->query("SELECT id FROM users WHERE role='admin'");
            while ($adm = $admins->fetch_assoc()) {
                $conn->query("INSERT INTO notifications (user_id,title,message,type)
                    VALUES ({$adm['id']},'$notif_title','$notif_sql','success')");
            }

            // Confirm notification to donor
            $conn->query("INSERT INTO notifications (user_id,title,message,type)
                VALUES ($uid,'Appointment Booked ✓','Your donation appointment is scheduled for $pref_date at "
                . date('h:i A', strtotime($pref_time)) . ". We will confirm shortly.','success')");

            $success = "Appointment booked for <strong>$pref_date</strong> at <strong>"
                     . date('h:i A', strtotime($pref_time)) . "</strong>. Admin has been notified!";
            $old = [];
        } else {
            $errors['general'] = 'Failed to book appointment. Please try again.';
        }
    }
}

// My appointments
$conn->query("CREATE TABLE IF NOT EXISTS user_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    blood_group_id INT NOT NULL,
    notes TEXT,
    status ENUM('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$my_appointments = $conn->query(
    "SELECT ua.*, bg.name as bg_name FROM user_appointments ua
     JOIN blood_groups bg ON ua.blood_group_id=bg.id
     WHERE ua.user_id=$uid ORDER BY ua.preferred_date DESC LIMIT 10"
);

function fe($e,$f){return isset($e[$f])?'is-invalid':'';}
function ov($o,$f){return htmlspecialchars($o[$f]??'');}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.ub-wrap { max-width: 900px; }
.field-msg{font-size:11.5px;margin-top:4px;display:flex;align-items:center;gap:4px;}
.field-msg.ok{color:#27ae60;}.field-msg.err{color:#c0392b;}
.form-control:focus,.form-select:focus{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,0.12);}
.time-slots{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.time-slot{padding:7px 14px;border-radius:10px;border:1.5px solid #e9ecef;font-size:0.82rem;
    font-weight:600;cursor:pointer;transition:all 0.2s;color:#495057;}
.time-slot:hover{border-color:#c0392b;color:#c0392b;background:#fff5f4;}
.time-slot.selected{border-color:#c0392b;background:#c0392b;color:white;}
</style>

<div class="ub-wrap">
    <div class="ub-section-heading mt-3">
        <i class="bi bi-calendar-plus-fill"></i>Book Donation Appointment
    </div>

    <?php if ($success): ?>
    <div class="ub-alert-success">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= $success ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
    <div class="ub-alert-error">
        <i class="bi bi-x-circle-fill fs-5 flex-shrink-0"></i>
        <div><?= $errors['general'] ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="ub-alert-error">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
        <div><strong><?= count($errors) ?> error(s).</strong> Please fix them below.</div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Book Form -->
        <div class="col-lg-5">
            <div class="ub-card">
                <div class="ub-card-header">
                    <i class="bi bi-calendar-plus-fill"></i>Schedule Appointment
                </div>
                <div class="card-body p-4">
                    <form method="POST" id="apptForm" novalidate>

                        <!-- Donor info (readonly) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Donor</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($user['name']) ?>"
                                    disabled style="background:#f8f9fa;">
                            </div>
                        </div>

                        <!-- Blood group -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Blood Group <span class="text-danger">*</span>
                            </label>
                            <select name="blood_group_id" id="apptBg"
                                class="form-select <?= fe($errors,'blood_group_id') ?>" required>
                                <option value="">-- Select your blood group --</option>
                                <?php $blood_groups->data_seek(0); while($bg=$blood_groups->fetch_assoc()): ?>
                                <option value="<?= $bg['id'] ?>"
                                    <?= (($old['blood_group_id']??'')==$bg['id'])?'selected':'' ?>>
                                    <?= $bg['name'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback"><?= $errors['blood_group_id']??'' ?></div>
                            <div class="field-msg" id="bgMsg"></div>
                        </div>

                        <!-- Date -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Preferred Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="preferred_date" id="apptDate"
                                class="form-control <?= fe($errors,'preferred_date') ?>"
                                value="<?= ov($old,'preferred_date') ?>"
                                min="<?= date('Y-m-d') ?>"
                                max="<?= date('Y-m-d', strtotime('+90 days')) ?>"
                                required>
                            <div class="invalid-feedback"><?= $errors['preferred_date']??'' ?></div>
                            <div class="field-msg" id="dateMsg"></div>
                        </div>

                        <!-- Time slots -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                Preferred Time <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" name="preferred_time" id="apptTimeInput"
                                value="<?= ov($old,'preferred_time') ?>">
                            <?php if (isset($errors['preferred_time'])): ?>
                            <div class="text-danger small mb-1">
                                <i class="bi bi-x-circle me-1"></i><?= $errors['preferred_time'] ?>
                            </div>
                            <?php endif; ?>
                            <div class="time-slots" id="timeSlots">
                                <?php
                                $slots = [
                                    '09:00' => '9:00 AM',
                                    '09:30' => '9:30 AM',
                                    '10:00' => '10:00 AM',
                                    '10:30' => '10:30 AM',
                                    '11:00' => '11:00 AM',
                                    '11:30' => '11:30 AM',
                                    '14:00' => '2:00 PM',
                                    '14:30' => '2:30 PM',
                                    '15:00' => '3:00 PM',
                                    '15:30' => '3:30 PM',
                                    '16:00' => '4:00 PM',
                                    '16:30' => '4:30 PM',
                                ];
                                $selectedTime = $old['preferred_time'] ?? '';
                                foreach ($slots as $val => $label):
                                ?>
                                <div class="time-slot <?= $selectedTime === $val ? 'selected' : '' ?>"
                                     onclick="selectTime('<?= $val ?>', this)">
                                    <?= $label ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Notes
                                <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <textarea name="notes" class="form-control" rows="2"
                                maxlength="300"
                                placeholder="Any medical conditions, special requirements..."><?= ov($old,'notes') ?></textarea>
                        </div>

                        <div class="ub-alert-info py-2 mb-3">
                            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                            <span style="font-size:0.82rem;">Admin will confirm your appointment and update donation records after your donation.</span>
                        </div>

                        <button type="submit" class="btn btn-danger w-100 fw-semibold py-2">
                            <i class="bi bi-calendar-check me-2"></i>Book Appointment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- My Appointments list -->
        <div class="col-lg-7">
            <div class="ub-section-heading"><i class="bi bi-calendar-week-fill"></i>My Appointments</div>
            <div class="ub-card">
                <div class="ub-card-header">
                    <span><i class="bi bi-list-check"></i> Appointment History</span>
                    <span class="badge bg-light text-danger"><?= $my_appointments->num_rows ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Time</th>
                                <th>Blood Group</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($my_appointments->num_rows === 0): ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                                No appointments yet. Book your first donation appointment!
                            </td>
                        </tr>
                        <?php else: while ($a = $my_appointments->fetch_assoc()):
                            $sc = [
                                'Pending'   => 'warning',
                                'Confirmed' => 'primary',
                                'Completed' => 'success',
                                'Cancelled' => 'secondary',
                            ][$a['status']] ?? 'secondary';
                            $isToday = ($a['preferred_date'] === date('Y-m-d'));
                        ?>
                        <tr <?= $isToday ? 'style="background:rgba(192,57,43,0.04);"' : '' ?>>
                            <td class="ps-3 fw-semibold">
                                <?= date('d M Y', strtotime($a['preferred_date'])) ?>
                                <?php if ($isToday): ?>
                                <span class="badge bg-danger ms-1" style="font-size:9px;">Today</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('h:i A', strtotime($a['preferred_time'])) ?></td>
                            <td><span class="bg-badge"><?= $a['bg_name'] ?></span></td>
                            <td>
                                <span class="badge bg-<?= $sc ?>"><?= $a['status'] ?></span>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Donation tips -->
            <div class="ub-card mt-3">
                <div class="ub-card-header"><i class="bi bi-lightbulb-fill"></i> Before You Donate</div>
                <div class="card-body p-4">
                    <div class="row g-2">
                        <?php $tips = [
                            ['bi-droplet-fill','Drink plenty of water (2-3 glasses) before donating.'],
                            ['bi-egg-fried','Eat a healthy meal at least 2 hours before donation.'],
                            ['bi-moon-fill','Get at least 8 hours of sleep the night before.'],
                            ['bi-x-circle-fill','Avoid alcohol and smoking 24 hours before donation.'],
                            ['bi-file-medical-fill','Carry a valid ID proof to the donation center.'],
                            ['bi-heart-pulse-fill','Inform staff of any recent medications or illness.'],
                        ]; foreach($tips as [$icon,$tip]): ?>
                        <div class="col-12 d-flex align-items-start gap-2">
                            <i class="bi bi-<?= $icon ?> text-danger mt-1 flex-shrink-0"></i>
                            <div class="text-muted small"><?= $tip ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Time slot selector
function selectTime(val, el) {
    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('apptTimeInput').value = val;
}

// Blood group validation
const bgEl = document.getElementById('apptBg');
const bgMsg = document.getElementById('bgMsg');
if (bgEl) {
    bgEl.addEventListener('change', function(){
        if (!this.value) {
            this.classList.add('is-invalid');
            bgMsg.className='field-msg err';
            bgMsg.innerHTML='<i class="bi bi-x-circle-fill"></i> Please select your blood group.';
        } else {
            this.classList.remove('is-invalid'); this.classList.add('is-valid');
            bgMsg.className='field-msg ok';
            bgMsg.innerHTML='<i class="bi bi-check-circle-fill"></i> Blood group selected ✓';
        }
    });
}

// Date validation
const dateEl = document.getElementById('apptDate');
const dateMsg = document.getElementById('dateMsg');
if (dateEl) {
    dateEl.addEventListener('change', function(){
        const v = this.value;
        const today = new Date().toISOString().split('T')[0];
        if (!v) { this.classList.remove('is-valid','is-invalid'); dateMsg.innerHTML=''; return; }
        if (v < today) {
            this.classList.add('is-invalid'); this.classList.remove('is-valid');
            dateMsg.className='field-msg err';
            dateMsg.innerHTML='<i class="bi bi-x-circle-fill"></i> Cannot be in the past.';
        } else {
            this.classList.add('is-valid'); this.classList.remove('is-invalid');
            dateMsg.className='field-msg ok';
            const d = new Date(v);
            dateMsg.innerHTML='<i class="bi bi-check-circle-fill"></i> '
                + d.toLocaleDateString('en-IN',{weekday:'long',day:'numeric',month:'long'}) + ' ✓';
        }
    });
}

// Final submit guard
document.getElementById('apptForm').addEventListener('submit', function(e){
    const errs = [];
    if (!document.getElementById('apptBg').value)
        errs.push('Please select your blood group.');
    const dt = document.getElementById('apptDate').value;
    if (!dt) errs.push('Please select a date.');
    else if (dt < new Date().toISOString().split('T')[0])
        errs.push('Appointment date cannot be in the past.');
    if (!document.getElementById('apptTimeInput').value)
        errs.push('Please select a time slot.');
    if (errs.length > 0) {
        e.preventDefault();
        let box = document.getElementById('jsErrBox');
        if (!box) { box = document.createElement('div'); box.id='jsErrBox'; this.prepend(box); }
        box.className = 'ub-alert-error mb-3';
        box.innerHTML = '<i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>'
            + '<div><strong>Please fix:</strong><ul class="mb-0 mt-1 ps-3">'
            + errs.map(r => `<li>${r}</li>`).join('') + '</ul></div>';
        window.scrollTo({top:0,behavior:'smooth'});
    }
});
</script>
