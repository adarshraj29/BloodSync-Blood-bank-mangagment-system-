<?php
// ============================================================
// BloodSync - Support Page
// Messages are saved to DB and visible in admin notifications
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title = 'Support & Help';
$uid  = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

$errors  = [];
$success = '';
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old     = $_POST;
    $subject = trim(clean($conn, $_POST['subject']));
    $message = trim(clean($conn, $_POST['message']));

    // ── Validation ───────────────────────────────────────
    if (empty($subject))
        $errors['subject'] = 'Please select a subject.';

    if (empty($message))
        $errors['message'] = 'Message cannot be empty.';
    elseif (strlen($message) < 10)
        $errors['message'] = 'Message must be at least 10 characters.';
    elseif (strlen($message) > 1000)
        $errors['message'] = 'Message must not exceed 1000 characters.';

    if (empty($errors)) {
        // Save to support_messages table if exists
        $chk = $conn->query("SHOW TABLES LIKE 'support_messages'");
        if ($chk && $chk->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO support_messages (user_id, subject, message) VALUES (?,?,?)");
            $stmt->bind_param("iss", $uid, $subject, $message);
            $stmt->execute();
        }

        // Notify every admin — so they can see it in admin panel notifications
        $name    = $_SESSION['name'];
        $email   = $user['email'];
        $phone   = $user['phone'] ?? 'N/A';
        $admins  = $conn->query("SELECT id FROM users WHERE role='admin'");
        $notif_title = "Support Request from $name";
        $notif_msg   = "📧 From: $name | Email: $email | Phone: $phone\n📋 Subject: $subject\n💬 Message: $message";
        // Clean for SQL
        $notif_msg_sql = clean($conn, $notif_msg);
        $notif_title_sql = clean($conn, $notif_title);
        while ($adm = $admins->fetch_assoc()) {
            $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES
                ({$adm['id']}, '$notif_title_sql', '$notif_msg_sql', 'info')");
        }
        // Confirm to user
        $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES
            ($uid, 'Support Message Sent ✓', 'Your message about \"$subject\" has been sent. Our team will respond within 24 hours.', 'success')");

        $success = "Your message has been sent successfully! Our admin team will review it and respond within 24 hours.";
        $old = []; // clear form
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.ub-wrap{max-width:960px;}
.field-msg{font-size:11.5px;margin-top:4px;display:flex;align-items:center;gap:4px;}
.field-msg.ok{color:#27ae60;}.field-msg.err{color:#c0392b;}
.form-control:focus,.form-select:focus{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,0.12);}
.contact-row{display:flex;align-items:center;gap:14px;padding:13px 18px;border-bottom:1px solid #f5f5f5;transition:background 0.15s;}
.contact-row:last-child{border-bottom:none;}
.contact-row:hover{background:#fff9f9;}
.contact-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.contact-label{font-size:0.72rem;color:#adb5bd;font-weight:700;text-transform:uppercase;letter-spacing:.05em;}
.contact-value{font-size:0.88rem;font-weight:600;color:#212529;}
.contact-link{color:#c0392b;text-decoration:none;font-weight:700;}
.contact-link:hover{text-decoration:underline;}
.helpline{background:#fff;border-radius:14px;padding:14px 16px;border:1px solid #f5c6c3;margin-bottom:10px;transition:all 0.2s;}
.helpline:hover{background:#fff5f4;transform:translateX(3px);}
.helpline-num{font-size:1.05rem;font-weight:800;color:#c0392b;letter-spacing:.5px;}
.helpline-org{font-size:0.78rem;color:#6c757d;margin-bottom:3px;}
.avail-badge{display:inline-block;font-size:0.68rem;padding:2px 8px;border-radius:20px;background:#e8f8f0;color:#155c2e;font-weight:700;}
</style>

<div class="ub-wrap">
    <div class="ub-section-heading mt-3"><i class="bi bi-headset"></i>Support & Help</div>

    <?php if ($success): ?>
    <div class="ub-alert-success"><i class="bi bi-check-circle-fill fs-5"></i><?= $success ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- LEFT: Admin contact + helplines -->
        <div class="col-lg-5">

            <!-- Admin contact -->
            <div class="ub-section-heading"><i class="bi bi-person-badge-fill"></i>Admin Contact</div>
            <div class="ub-card">
                <div class="ub-card-header"><i class="bi bi-person-badge-fill"></i>BloodSync Admin Team</div>
                <div>
                    <div class="contact-row">
                        <div class="contact-icon" style="background:#fff0ee;color:#c0392b;"><i class="bi bi-envelope-fill"></i></div>
                        <div><div class="contact-label">Email</div><a href="mailto:admin@bloodsync.com" class="contact-link">admin@bloodsync.com</a></div>
                    </div>
                    <div class="contact-row">
                        <div class="contact-icon" style="background:#e8f0ff;color:#0d6efd;"><i class="bi bi-telephone-fill"></i></div>
                        <div><div class="contact-label">Mobile</div><a href="tel:+919800000000" class="contact-link">+91 98000 00000</a></div>
                    </div>
                    <div class="contact-row">
                        <div class="contact-icon" style="background:#e8f8f0;color:#198754;"><i class="bi bi-clock-fill"></i></div>
                        <div><div class="contact-label">Hours</div><div class="contact-value">Mon–Sat, 9:00 AM – 6:00 PM</div></div>
                    </div>
                    <div class="contact-row">
                        <div class="contact-icon" style="background:#fff8e6;color:#e67e22;"><i class="bi bi-geo-alt-fill"></i></div>
                        <div><div class="contact-label">Location</div><div class="contact-value">Patna, Bihar, India</div></div>
                    </div>
                </div>
            </div>

            <!-- Emergency helplines -->
            <div class="ub-section-heading mt-2"><i class="bi bi-telephone-fill"></i>Emergency Helplines</div>
            <div class="ub-card">
                <div class="ub-card-header"><i class="bi bi-exclamation-triangle-fill"></i>National Blood Helplines</div>
                <div class="card-body p-4">
                    <div class="helpline">
                        <div class="helpline-org">🩸 Indian Blood Donors Helpline</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="tel:07966772377" class="helpline-num">079-6677-2377</a>
                            <span class="avail-badge">Available</span>
                        </div>
                    </div>
                    <div class="helpline">
                        <div class="helpline-org">🇮🇳 All India Blood Helpline</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <a href="tel:8097091234" class="helpline-num">8097-091-234</a>
                            <span class="avail-badge">Available</span>
                        </div>
                    </div>
                    <div class="helpline">
                        <div class="helpline-org">🔴 Indian Red Cross Society — National Blood Services</div>
                        <div class="row g-2 mt-1">
                            <div class="col-12 d-flex align-items-center justify-content-between">
                                <a href="tel:01123359379" class="helpline-num">011-2335-9379</a>
                                <span class="avail-badge">24×7</span>
                            </div>
                            <div class="col-6"><a href="tel:9319982104" class="helpline-num" style="font-size:0.9rem;">9319-982-104</a></div>
                            <div class="col-6"><a href="tel:9319982105" class="helpline-num" style="font-size:0.9rem;">9319-982-105</a></div>
                        </div>
                    </div>
                    <div class="helpline" style="background:#fff5f4;border-color:#c0392b;">
                        <div class="d-flex align-items-center gap-3">
                            <div style="font-size:1.8rem;">🚨</div>
                            <div>
                                <div class="fw-bold text-danger small">Critical Emergency?</div>
                                <div class="text-muted" style="font-size:0.78rem;">Use the Emergency Request form for instant admin alerts.</div>
                                <a href="/blood_bank/user/emergency.php" class="btn btn-danger btn-sm mt-2 fw-semibold">Go to Emergency →</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Support form + FAQ -->
        <div class="col-lg-7">
            <div class="ub-section-heading"><i class="bi bi-chat-dots-fill"></i>Send a Message to Admin</div>
            <div class="ub-card">
                <div class="ub-card-header"><i class="bi bi-envelope-fill"></i>Contact Support Team</div>
                <div class="card-body p-4">
                    <form method="POST" id="supportForm" novalidate>

                        <!-- From (read-only) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">From</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)" disabled style="background:#f8f9fa;font-size:0.85rem;">
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Subject <span class="text-danger">*</span></label>
                            <select name="subject" id="supSubject" class="form-select <?= isset($errors['subject'])?'is-invalid':'' ?>" required>
                                <option value="">-- Select a topic --</option>
                                <option <?= ($old['subject']??'')==='Blood request issue'?'selected':'' ?>>Blood request issue</option>
                                <option <?= ($old['subject']??'')==='Cannot find blood group'?'selected':'' ?>>Cannot find blood group</option>
                                <option <?= ($old['subject']??'')==='Donation camp inquiry'?'selected':'' ?>>Donation camp inquiry</option>
                                <option <?= ($old['subject']??'')==='Account issue'?'selected':'' ?>>Account issue</option>
                                <option <?= ($old['subject']??'')==='Technical problem'?'selected':'' ?>>Technical problem</option>
                                <option <?= ($old['subject']??'')==='Emergency not resolved'?'selected':'' ?>>Emergency not resolved</option>
                                <option <?= ($old['subject']??'')==='Other'?'selected':'' ?>>Other</option>
                            </select>
                            <div class="invalid-feedback"><?= $errors['subject']??'' ?></div>
                            <div class="field-msg" id="subjectMsg"></div>
                        </div>

                        <!-- Message -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Your Message <span class="text-danger">*</span></label>
                            <textarea name="message" id="supMessage"
                                class="form-control <?= isset($errors['message'])?'is-invalid':'' ?>"
                                rows="5" maxlength="1000"
                                placeholder="Describe your issue in detail. The more information you provide, the faster we can help you..."
                                required><?= htmlspecialchars($old['message']??'') ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <div class="invalid-feedback d-block"><?= $errors['message']??'' ?></div>
                                <div class="text-muted small ms-auto" id="msgCount">0 / 1000</div>
                            </div>
                            <div class="field-msg" id="messageMsg"></div>
                        </div>

                        <div class="ub-alert-info py-2 mb-3">
                            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                            Your message will be sent to all admins instantly via their notification panel. Expect a response within 24 hours.
                        </div>

                        <button type="submit" class="btn btn-danger px-5 fw-semibold py-2">
                            <i class="bi bi-send-fill me-2"></i>Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- FAQ -->
            <div class="ub-section-heading mt-2"><i class="bi bi-question-circle-fill"></i>Frequently Asked Questions</div>
            <div class="ub-card">
                <div class="card-body p-4">
                <?php $faqs=[
                    ['How do I request blood?','Go to Dashboard → Request Blood. Fill the form with blood group, units, and urgency level. Admin will review within 1–2 hours.'],
                    ['How long does approval take?','Requests are reviewed within 1–2 hours during working hours (9AM–6PM). Emergency requests trigger instant admin alerts.'],
                    ['Who can donate blood?','Anyone aged 18–65, weighing 50+ kg, and healthy. Donors must wait 90 days between donations.'],
                    ['How do I update my profile?','Click your name in the top navigation bar → "View / Edit Profile".'],
                    ['What if my request is rejected?','Contact support or use the Emergency button for urgent cases. Admin will provide a reason in the notification.'],
                    ['How does the achievement system work?','1–3 donations = ⭐ Donor, 4–6 = ⭐⭐ Life Saver, 7+ = ⭐⭐⭐ Blood Hero. Each donation = 3 lives saved.'],
                ]; foreach($faqs as $f): ?>
                <div style="border-bottom:1px solid #f5f5f5;padding:12px 0;">
                    <div class="fw-semibold small" style="color:#c0392b;margin-bottom:4px;">
                        <i class="bi bi-question-circle me-1"></i><?= $f[0] ?>
                    </div>
                    <div class="small text-muted"><?= $f[1] ?></div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Subject validation
const ss=document.getElementById('supSubject'),ssm=document.getElementById('subjectMsg');
if(ss)ss.addEventListener('change',function(){
    if(!this.value){this.classList.add('is-invalid');ssm.className='field-msg err';ssm.innerHTML='<i class="bi bi-x-circle-fill"></i> Please select a subject.';}
    else{this.classList.remove('is-invalid');this.classList.add('is-valid');ssm.className='field-msg ok';ssm.innerHTML='<i class="bi bi-check-circle-fill"></i> Subject selected ✓';}
});

// Message counter + validation
const sm=document.getElementById('supMessage'),mc=document.getElementById('msgCount'),mm=document.getElementById('messageMsg');
if(sm){
    sm.addEventListener('input',function(){
        const l=this.value.length;mc.textContent=l+' / 1000';mc.style.color=l>900?'#c0392b':'#adb5bd';
        if(!this.value){this.classList.remove('is-valid','is-invalid');mm.innerHTML='';return;}
        if(l<10){this.classList.add('is-invalid');this.classList.remove('is-valid');mm.className='field-msg err';mm.innerHTML='<i class="bi bi-x-circle-fill"></i> Min 10 characters.';}
        else{this.classList.add('is-valid');this.classList.remove('is-invalid');mm.className='field-msg ok';mm.innerHTML='<i class="bi bi-check-circle-fill"></i> Message ready ✓';}
    });
}

// Final submit guard
document.getElementById('supportForm').addEventListener('submit',function(e){
    const errs=[];
    if(!document.getElementById('supSubject').value)errs.push('Please select a subject.');
    const msg=document.getElementById('supMessage').value.trim();
    if(!msg||msg.length<10)errs.push('Message must be at least 10 characters.');
    if(msg.length>1000)errs.push('Message must not exceed 1000 characters.');
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
