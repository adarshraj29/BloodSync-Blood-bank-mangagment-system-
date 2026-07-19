<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Location Search';

$city      = isset($_GET['city'])  ? clean($conn, $_GET['city'])  : '';
$state     = isset($_GET['state']) ? clean($conn, $_GET['state']) : '';
$bg_filter = isset($_GET['bg'])    ? (int)$_GET['bg']             : 0;

$blood_groups = $conn->query("SELECT * FROM blood_groups");

// Get distinct states & cities for suggestions
$states = $conn->query("SELECT DISTINCT state FROM donors WHERE state IS NOT NULL AND state!='' ORDER BY state");
$cities = $conn->query("SELECT DISTINCT city  FROM donors WHERE city  IS NOT NULL AND city !='' ORDER BY city");

$donors = null;
$searched = ($city || $state || $bg_filter);

if ($searched) {
    $where = "WHERE d.is_active=1";
    if ($city)      $where .= " AND d.city  LIKE '%$city%'";
    if ($state)     $where .= " AND d.state LIKE '%$state%'";
    if ($bg_filter) $where .= " AND d.blood_group_id=$bg_filter";

    $donors = $conn->query("SELECT d.*, bg.name as bg_name,
        CASE WHEN d.last_donation_date IS NULL OR DATEDIFF(NOW(),d.last_donation_date)>=90 THEN 1 ELSE 0 END as eligible
        FROM donors d JOIN blood_groups bg ON d.blood_group_id=bg.id
        $where ORDER BY eligible DESC, d.name ASC");
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-geo-alt me-2 text-danger"></i>Location-Based Donor Search</h1>
    <p class="text-muted mb-4">Find donors near a specific city or state. Eligible donors are shown first.</p>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">City</label>
                    <input type="text" name="city" class="form-control" placeholder="e.g. Patna" value="<?= htmlspecialchars($city) ?>" list="city-list">
                    <datalist id="city-list">
                        <?php $cities->data_seek(0); while ($c = $cities->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($c['city']) ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">State</label>
                    <input type="text" name="state" class="form-control" placeholder="e.g. Bihar" value="<?= htmlspecialchars($state) ?>" list="state-list">
                    <datalist id="state-list">
                        <?php $states->data_seek(0); while ($s = $states->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($s['state']) ?>">
                        <?php endwhile; ?>
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Blood Group</label>
                    <select name="bg" class="form-select">
                        <option value="">All Blood Groups</option>
                        <?php $blood_groups->data_seek(0); while ($bg = $blood_groups->fetch_assoc()): ?>
                        <option value="<?= $bg['id'] ?>" <?= $bg_filter==$bg['id']?'selected':'' ?>><?= $bg['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
                <?php if ($searched): ?>
                <div class="col-md-1">
                    <a href="location_search.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($searched): ?>
    <!-- Results -->
    <div class="card">
        <div class="card-header py-3 px-4 d-flex justify-content-between">
            <span class="fw-semibold">
                Search Results
                <?php if ($city || $state): ?>
                <span class="text-muted fw-normal small ms-2">
                    for <?= htmlspecialchars(implode(', ', array_filter([$city,$state]))) ?>
                </span>
                <?php endif; ?>
            </span>
            <span class="text-muted small"><?= $donors ? $donors->num_rows : 0 ?> donors found</span>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">Name</th>
                    <th>Blood Group</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Last Donated</th>
                    <th>Eligible</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (!$donors || $donors->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">
                        <i class="bi bi-geo fs-2 d-block mb-2"></i>
                        No donors found in this location.
                        <br><small>Try a broader search or check if donor city/state info has been filled in.</small>
                    </td></tr>
                <?php else: while ($d = $donors->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                    <td><span class="bg-badge"><?= $d['bg_name'] ?></span></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= htmlspecialchars($d['city'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['state'] ?? '—') ?></td>
                    <td class="small"><?= $d['last_donation_date'] ? date('d M Y', strtotime($d['last_donation_date'])) : 'Never' ?></td>
                    <td>
                        <span class="badge bg-<?= $d['eligible']?'success':'warning text-dark' ?>">
                            <?= $d['eligible']?'Yes':'No' ?>
                        </span>
                    </td>
                    <td>
                        <a href="/blood_bank/admin/edit_donor.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($d['eligible']): ?>
                        <a href="/blood_bank/admin/add_donation.php?donor_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-droplet-plus"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info mt-3 small">
        <i class="bi bi-lightbulb me-2"></i>
        <strong>Tip:</strong> If city/state shows "—", edit the donor profile to add their location. This enables location-based matching.
    </div>
    <?php else: ?>

    <!-- Info when no search yet -->
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card text-center p-4">
                <i class="bi bi-geo-alt-fill text-danger fs-1 mb-3"></i>
                <h6 class="fw-semibold">Filter by City</h6>
                <p class="text-muted small">Find all donors in a specific city like Patna, Delhi, Mumbai.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-4">
                <i class="bi bi-map text-primary fs-1 mb-3"></i>
                <h6 class="fw-semibold">Filter by State</h6>
                <p class="text-muted small">Search across an entire state like Bihar, UP, Maharashtra.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center p-4">
                <i class="bi bi-droplet-fill text-success fs-1 mb-3"></i>
                <h6 class="fw-semibold">Filter by Blood Group</h6>
                <p class="text-muted small">Combine location with blood group for pinpoint results.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
