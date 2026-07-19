<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Donation Log';

$log = $conn->query("SELECT dl.*, d.name as donor_name, bg.name as bg_name, u.name as added_by_name
    FROM donation_log dl
    JOIN donors d ON dl.donor_id=d.id
    JOIN blood_groups bg ON d.blood_group_id=bg.id
    LEFT JOIN users u ON dl.added_by=u.id
    ORDER BY dl.created_at DESC");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0"><i class="bi bi-journal-text me-2 text-danger"></i>Donation Log</h1>
        <a href="/blood_bank/admin/add_donation.php" class="btn btn-danger btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Log Donation
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">Donation logged and stock updated successfully.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">#</th><th>Donor</th><th>Blood Group</th>
                    <th>Units</th><th>Date</th><th>Notes</th><th>Logged By</th>
                </tr></thead>
                <tbody>
                <?php if ($log->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No donations logged yet.</td></tr>
                <?php else: $i=1; while ($l = $log->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4"><?= $i++ ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($l['donor_name']) ?></td>
                    <td><span class="bg-badge"><?= $l['bg_name'] ?></span></td>
                    <td class="text-success fw-bold">+<?= $l['units'] ?></td>
                    <td><?= date('d M Y', strtotime($l['donated_date'])) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($l['notes'] ?? '—') ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($l['added_by_name'] ?? '—') ?></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
