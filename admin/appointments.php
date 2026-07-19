<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Appointments';

// Cancel appointment
if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    $conn->query("UPDATE appointments SET status='Cancelled' WHERE id=$id");
    redirect('/blood_bank/admin/appointments.php?msg=cancelled');
}

// Mark complete
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    $conn->query("UPDATE appointments SET status='Completed' WHERE id=$id");
    redirect('/blood_bank/admin/appointments.php?msg=completed');
}

$errors = [];
$donors = $conn->query("SELECT d.*, bg.name as bg_name FROM donors d JOIN blood_groups bg ON d.blood_group_id=bg.id WHERE d.is_active=1 ORDER BY d.name");

// Pre-select donor from smart match
$pre_donor = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donor_id = (int)$_POST['donor_id'];
    $date     = clean($conn, $_POST['scheduled_date']);
    $time     = clean($conn, $_POST['scheduled_time']);
    $notes    = clean($conn, $_POST['notes']);
    $uid      = $_SESSION['user_id'];

    if (!$donor_id) $errors[] = 'Please select a donor.';
    if (empty($date)) $errors[] = 'Please select a date.';
    elseif ($date < date('Y-m-d')) $errors[] = 'Appointment date cannot be in the past.';
    if (empty($time)) $errors[] = 'Please select a time.';

    // Check no duplicate appointment on same day
    if (!$errors) {
        $dup = $conn->query("SELECT id FROM appointments WHERE donor_id=$donor_id AND scheduled_date='$date' AND status='Scheduled'");
        if ($dup->num_rows > 0) $errors[] = 'This donor already has an appointment on this date.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO appointments (donor_id, scheduled_date, scheduled_time, notes, created_by) VALUES (?,?,?,?,?)");
        $stmt->bind_param("isssi", $donor_id, $date, $time, $notes, $uid);
        if ($stmt->execute()) {
            // Create notification for the system
            $donor_name = $conn->query("SELECT name FROM donors WHERE id=$donor_id")->fetch_assoc()['name'];
            $msg = "Appointment booked for donor $donor_name on $date at $time.";
            $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($uid, 'Appointment Booked', '$msg', 'success')");
            redirect('/blood_bank/admin/appointments.php?msg=booked');
        } else {
            $errors[] = 'Failed to book appointment.';
        }
    }
}

$status_filter = isset($_GET['status']) ? clean($conn, $_GET['status']) : 'Scheduled';
$where = $status_filter ? "WHERE a.status='$status_filter'" : '';

$appointments = $conn->query("SELECT a.*, d.name as donor_name, d.phone as donor_phone, bg.name as bg_name
    FROM appointments a
    JOIN donors d ON a.donor_id=d.id
    JOIN blood_groups bg ON d.blood_group_id=bg.id
    $where ORDER BY a.scheduled_date ASC, a.scheduled_time ASC");

// Today's count
$today_count = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE scheduled_date='".date('Y-m-d')."' AND status='Scheduled'")->fetch_assoc()['c'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-calendar-check me-2 text-danger"></i>Donation Appointments</h1>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible">
        <?= ['booked'=>'Appointment booked successfully!','cancelled'=>'Appointment cancelled.','completed'=>'Marked as completed.'][$_GET['msg']] ?? 'Done.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($today_count > 0): ?>
    <div class="alert alert-warning">
        <i class="bi bi-calendar-event me-2"></i>
        <strong><?= $today_count ?> appointment(s) scheduled for today.</strong>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Book Appointment Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Book New Appointment</div>
                <div class="card-body p-4">
                    <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e): ?><div><i class="bi bi-x-circle me-1"></i><?= $e ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Donor <span class="text-danger">*</span></label>
                            <select name="donor_id" class="form-select" required>
                                <option value="">-- Select Donor --</option>
                                <?php $donors->data_seek(0); while ($d = $donors->fetch_assoc()): ?>
                                <option value="<?= $d['id'] ?>" <?= $pre_donor==$d['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($d['name']) ?> (<?= $d['bg_name'] ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
                            <input type="date" name="scheduled_date" class="form-control"
                                min="<?= date('Y-m-d') ?>" value="<?= $_POST['scheduled_date'] ?? '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Time <span class="text-danger">*</span></label>
                            <select name="scheduled_time" class="form-select" required>
                                <option value="">-- Select Time --</option>
                                <?php
                                $slots = ['09:00','09:30','10:00','10:30','11:00','11:30',
                                          '12:00','14:00','14:30','15:00','15:30','16:00','16:30'];
                                foreach ($slots as $s):
                                ?>
                                <option value="<?= $s ?>" <?= (($_POST['scheduled_time']??'')===$s)?'selected':'' ?>>
                                    <?= date('h:i A', strtotime($s)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="bi bi-calendar-plus me-2"></i>Book Appointment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Appointments List -->
        <div class="col-lg-8">
            <!-- Filter tabs -->
            <div class="d-flex gap-2 mb-3">
                <?php foreach (['Scheduled','Completed','Cancelled',''] as $s): ?>
                <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status_filter===$s?'btn-danger':'btn-outline-secondary' ?>">
                    <?= $s ?: 'All' ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">Donor</th>
                            <th>Blood Group</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($appointments->num_rows === 0): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No appointments found.</td></tr>
                        <?php else: while ($a = $appointments->fetch_assoc()):
                            $sc = ['Scheduled'=>'primary','Completed'=>'success','Cancelled'=>'secondary'][$a['status']];
                            $isToday = ($a['scheduled_date'] === date('Y-m-d'));
                        ?>
                        <tr <?= $isToday?'style="background:rgba(220,53,69,0.04);"':'' ?>>
                            <td class="ps-4">
                                <div class="fw-semibold"><?= htmlspecialchars($a['donor_name']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($a['donor_phone']) ?></div>
                            </td>
                            <td><span class="bg-badge"><?= $a['bg_name'] ?></span></td>
                            <td>
                                <?= date('d M Y', strtotime($a['scheduled_date'])) ?>
                                <?php if ($isToday): ?><span class="badge bg-warning text-dark ms-1">Today</span><?php endif; ?>
                            </td>
                            <td><?= date('h:i A', strtotime($a['scheduled_time'])) ?></td>
                            <td><span class="badge bg-<?= $sc ?>"><?= $a['status'] ?></span></td>
                            <td>
                                <?php if ($a['status'] === 'Scheduled'): ?>
                                <a href="?complete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-success me-1"
                                   onclick="return confirm('Mark as completed?')">
                                    <i class="bi bi-check-lg"></i>
                                </a>
                                <a href="?cancel=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Cancel this appointment?')">
                                    <i class="bi bi-x-lg"></i>
                                </a>
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
