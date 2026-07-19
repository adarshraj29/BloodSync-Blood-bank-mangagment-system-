<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity.php';
requireAdmin();
$page_title = 'Blood Requests';

$msg = '';

// Approve or Reject
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $req_id = (int)($_GET['approve'] ?? $_GET['reject']);
    $action = isset($_GET['approve']) ? 'Approved' : 'Rejected';
    $req = $conn->query("SELECT * FROM blood_requests WHERE id=$req_id")->fetch_assoc();

    if ($req && $req['status'] === 'Pending') {
        $uid = $_SESSION['user_id'];
        if ($action === 'Approved') {
            $stock = $conn->query("SELECT units FROM blood_stock WHERE blood_group_id={$req['blood_group_id']}")->fetch_assoc();
            if ($stock['units'] < $req['units_needed']) {
                $msg = 'error:Not enough stock to approve this request.';
            } else {
                $new  = $stock['units'] - $req['units_needed'];
                $conn->query("UPDATE blood_stock SET units=$new WHERE blood_group_id={$req['blood_group_id']}");
                $reason = "Approved Request #$req_id";
                $diff = -$req['units_needed'];
                $conn->query("INSERT INTO stock_log (blood_group_id, change_units, reason, changed_by) VALUES ({$req['blood_group_id']}, $diff, '$reason', $uid)");
                $conn->query("UPDATE blood_requests SET status='Approved', approved_by=$uid WHERE id=$req_id");
                // Notify requester
                if ($req['requested_by']) {
                    $notif_msg = "Your blood request #$req_id has been APPROVED. Please collect {$req['units_needed']} unit(s) from the blood bank.";
                    $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ({$req['requested_by']}, 'Request Approved ✅', '$notif_msg', 'success')");
                }
                $msg = 'success:Request approved and stock deducted.';
            }
        } else {
            $conn->query("UPDATE blood_requests SET status='Rejected', approved_by=$uid WHERE id=$req_id");
            // Notify requester
            if ($req['requested_by']) {
                $notif_msg = "Your blood request #$req_id has been rejected. Please contact the blood bank for more information.";
                $conn->query("INSERT INTO notifications (user_id, title, message, type) VALUES ({$req['requested_by']}, 'Request Rejected ❌', '$notif_msg', 'danger')");
            }
            $msg = 'success:Request rejected.';
        }
    }
}

$status_filter = isset($_GET['status']) ? clean($conn, $_GET['status']) : '';
$where = $status_filter ? "WHERE br.status='$status_filter'" : '';

$requests = $conn->query("SELECT br.*, bg.name as blood_name, h.name as hospital_name
    FROM blood_requests br
    JOIN blood_groups bg ON br.blood_group_id=bg.id
    LEFT JOIN hospitals h ON br.hospital_id=h.id
    $where ORDER BY br.created_at DESC");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0"><i class="bi bi-clipboard-plus me-2 text-danger"></i>Blood Requests</h1>
    </div>

    <?php if ($msg): list($type, $text) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?= $type==='error'?'danger':'success' ?>"><?= $text ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="mb-3 d-flex gap-2">
        <?php foreach (['','Pending','Approved','Rejected'] as $s): ?>
        <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status_filter===$s?'btn-danger':'btn-outline-secondary' ?>">
            <?= $s ?: 'All' ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">#</th><th>Requester</th><th>Blood</th><th>Units</th>
                    <th>Hospital</th><th>Urgency</th><th>Date</th><th>Status</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($requests->num_rows === 0): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No requests found.</td></tr>
                <?php else: while ($r = $requests->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4"><?= $r['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($r['requester_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($r['phone']) ?></div>
                    </td>
                    <td><span class="bg-badge"><?= $r['blood_name'] ?></span></td>
                    <td><?= $r['units_needed'] ?></td>
                    <td><?= htmlspecialchars($r['hospital_name'] ?? 'N/A') ?></td>
                    <td>
                        <?php $uc = ['Normal'=>'badge-normal','Urgent'=>'badge-urgent','Critical'=>'badge-critical'][$r['urgency']]; ?>
                        <span class="badge <?= $uc ?>"><?= $r['urgency'] ?></span>
                    </td>
                    <td class="text-muted small"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <?php $sc = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'][$r['status']]; ?>
                        <span class="badge bg-<?= $sc ?>"><?= $r['status'] ?></span>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                        <a href="?approve=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success me-1"
                           onclick="return confirm('Approve this request?')"><i class="bi bi-check-lg"></i></a>
                        <a href="?reject=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Reject this request?')"><i class="bi bi-x-lg"></i></a>
                        <?php else: ?>
                        <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
