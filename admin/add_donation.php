<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity.php';
requireAdmin();
$page_title = 'Log Donation';

$donor_id = (int)($_GET['donor_id'] ?? 0);
$donor = $donor_id ? $conn->query("SELECT d.*, bg.name as bg_name FROM donors d JOIN blood_groups bg ON d.blood_group_id=bg.id WHERE d.id=$donor_id")->fetch_assoc() : null;

$donors = $conn->query("SELECT d.*, bg.name as bg_name FROM donors d JOIN blood_groups bg ON d.blood_group_id=bg.id WHERE d.is_active=1 ORDER BY d.name");
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $did   = (int)$_POST['donor_id'];
    $units = (int)$_POST['units'];
    $date  = clean($conn, $_POST['donated_date']);
    $notes = clean($conn, $_POST['notes']);
    $uid   = $_SESSION['user_id'];

    $d = $conn->query("SELECT d.*, bg.id as bg_id FROM donors d JOIN blood_groups bg ON d.blood_group_id=bg.id WHERE d.id=$did")->fetch_assoc();

    $stmt = $conn->prepare("INSERT INTO donation_log (donor_id, units, donated_date, notes, added_by) VALUES (?,?,?,?,?)");
    $stmt->bind_param("iissi", $did, $units, $date, $notes, $uid);
    $stmt->execute();

    // Update stock
    $conn->query("UPDATE blood_stock SET units=units+$units WHERE blood_group_id={$d['bg_id']}");
    $reason = "Donation from donor #$did";
    $conn->query("INSERT INTO stock_log (blood_group_id, change_units, reason, changed_by) VALUES ({$d['bg_id']}, $units, '$reason', $uid)");

    // Update last donation date
    $conn->query("UPDATE donors SET last_donation_date='$date' WHERE id=$did");

    // Send SMS/notification to donor
    $donor_data = $conn->query("SELECT d.*, u.phone as user_phone, u.id as user_id FROM donors d LEFT JOIN users u ON u.phone=d.phone WHERE d.id=$did LIMIT 1")->fetch_assoc();
    if ($donor_data) {
        $sms_msg = "Dear {$donor_data['name']}, you have successfully donated blood. Thank you for saving lives ❤️ — BloodSync";
        if (!empty($donor_data['user_id'])) {
            sendSMS($conn, $donor_data['user_id'], $donor_data['phone'], $sms_msg);
        }
        logActivity($conn, $uid, $_SESSION['name'], 'Blood Donation Logged', "Logged donation for donor: {$donor_data['name']} (ID:$did), Units: $units");
    }
    redirect('/blood_bank/admin/donation_log.php?msg=added');
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-droplet-plus me-2 text-danger"></i>Log Donation</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <div class="card" style="max-width:560px;">
        <div class="card-body p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Donor *</label>
                    <select name="donor_id" class="form-select" required>
                        <option value="">Select Donor</option>
                        <?php while ($d = $donors->fetch_assoc()): ?>
                        <option value="<?= $d['id'] ?>" <?= $donor_id==$d['id']?'selected':'' ?>>
                            <?= htmlspecialchars($d['name']) ?> (<?= $d['bg_name'] ?>) - <?= $d['phone'] ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Units Donated *</label>
                    <input type="number" name="units" class="form-control" value="1" min="1" max="5" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Donation Date *</label>
                    <input type="date" name="donated_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger px-4">Log Donation</button>
                <a href="/blood_bank/admin/donors.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
