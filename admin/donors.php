<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Donors';

// Delete donor
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("UPDATE donors SET is_active=0 WHERE id=$id");
    redirect('/blood_bank/admin/donors.php?msg=deleted');
}

$search = isset($_GET['search']) ? clean($conn, $_GET['search']) : '';
$bg_filter = isset($_GET['bg']) ? (int)$_GET['bg'] : 0;

$where = "WHERE d.is_active=1";
if ($search) $where .= " AND (d.name LIKE '%$search%' OR d.phone LIKE '%$search%')";
if ($bg_filter) $where .= " AND d.blood_group_id=$bg_filter";

$donors = $conn->query("SELECT d.*, bg.name as bg_name FROM donors d
    JOIN blood_groups bg ON d.blood_group_id=bg.id $where ORDER BY d.created_at DESC");
$blood_groups = $conn->query("SELECT * FROM blood_groups");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0"><i class="bi bi-people me-2 text-danger"></i>Donors</h1>
        <a href="/blood_bank/admin/add_donor.php" class="btn btn-danger btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add Donor
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible">
            <?= $_GET['msg']==='deleted' ? 'Donor removed.' : 'Donor saved successfully.' ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by name or phone..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="bg" class="form-select form-select-sm">
                        <option value="">All Blood Groups</option>
                        <?php $blood_groups->data_seek(0); while ($bg = $blood_groups->fetch_assoc()): ?>
                        <option value="<?= $bg['id'] ?>" <?= $bg_filter==$bg['id']?'selected':'' ?>><?= $bg['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-danger btn-sm">Filter</button>
                    <a href="/blood_bank/admin/donors.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">#</th><th>Name</th><th>Age</th><th>Blood Group</th>
                    <th>Phone</th><th>Last Donation</th><th>Eligible</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if ($donors->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No donors found.</td></tr>
                <?php else: $i=1; while ($d = $donors->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4"><?= $i++ ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                    <td><?= $d['age'] ?></td>
                    <td><span class="bg-badge"><?= $d['bg_name'] ?></span></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= $d['last_donation_date'] ? date('d M Y', strtotime($d['last_donation_date'])) : 'Never' ?></td>
                    <td>
                        <?php
                        $eligible = true;
                        if ($d['last_donation_date']) {
                            $days = (time() - strtotime($d['last_donation_date'])) / 86400;
                            $eligible = $days >= 90;
                        }
                        ?>
                        <span class="badge bg-<?= $eligible ? 'success' : 'warning text-dark' ?>">
                            <?= $eligible ? 'Yes' : 'No (90-day wait)' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/blood_bank/admin/edit_donor.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/blood_bank/admin/add_donation.php?donor_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Log Donation">
                            <i class="bi bi-droplet-plus"></i>
                        </a>
                        <a href="?delete=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger"
                           onclick="return confirm('Remove this donor?')">
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
