<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Smart Match';

$blood_groups = $conn->query("SELECT * FROM blood_groups");
$bg_id        = isset($_GET['bg']) ? (int)$_GET['bg'] : 0;
$units_needed = isset($_GET['units']) ? (int)$_GET['units'] : 1;
$city_filter  = isset($_GET['city']) ? clean($conn, $_GET['city']) : '';

$matches = [];
$stock_ok = false;

if ($bg_id) {
    // Check current stock
    $stock = $conn->query("SELECT bs.units, bg.name as bg_name
        FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id
        WHERE bs.blood_group_id=$bg_id")->fetch_assoc();
    $stock_ok = $stock && $stock['units'] >= $units_needed;

    // Compatible blood groups (simplified compatibility chart)
    $compat = [
        1 => [1,5],       // A+  can donate to A+, AB+
        2 => [1,2,5,6],   // A-  can donate to A+, A-, AB+, AB-
        3 => [3,5],       // B+  can donate to B+, AB+
        4 => [3,4,5,6],   // B-  can donate to B+, B-, AB+, AB-
        5 => [5],         // AB+ can donate to AB+ only
        6 => [1,2,3,4,5,6,7,8], // O- universal donor
        7 => [5,7],       // AB+ receives from all (but as donor: AB+, AB-)
        8 => [5,6,7,8],   // AB- can donate to AB+, AB-
    ];
    // For receiving: who can donate to this blood group
    $donors_bg = $compat[$bg_id] ?? [$bg_id];
    $bg_list   = implode(',', $donors_bg);

    $city_where = $city_filter ? "AND (d.city LIKE '%$city_filter%' OR d.state LIKE '%$city_filter%')" : '';

    $matches = $conn->query("
        SELECT d.*, bg.name as bg_name,
            DATEDIFF(NOW(), d.last_donation_date) as days_since,
            (CASE
                WHEN d.last_donation_date IS NULL THEN 100
                WHEN DATEDIFF(NOW(), d.last_donation_date) >= 90 THEN DATEDIFF(NOW(), d.last_donation_date)
                ELSE 0
            END) as score
        FROM donors d
        JOIN blood_groups bg ON d.blood_group_id=bg.id
        WHERE d.is_active=1
          AND d.blood_group_id IN ($bg_list)
          AND (d.last_donation_date IS NULL OR DATEDIFF(NOW(), d.last_donation_date) >= 90)
          $city_where
        ORDER BY score DESC, d.last_donation_date ASC
        LIMIT 20
    ");
}

// Distinct cities for filter
$cities = $conn->query("SELECT DISTINCT city FROM donors WHERE city IS NOT NULL AND city != '' ORDER BY city");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-stars me-2 text-danger"></i>Smart Donor Match</h1>
    <p class="text-muted mb-4">Automatically finds the best eligible donors based on blood group compatibility and donation history.</p>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Blood Group Needed <span class="text-danger">*</span></label>
                    <select name="bg" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php while ($bg = $blood_groups->fetch_assoc()): ?>
                        <option value="<?= $bg['id'] ?>" <?= $bg_id==$bg['id']?'selected':'' ?>><?= $bg['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Units Needed</label>
                    <input type="number" name="units" class="form-control" min="1" max="20" value="<?= $units_needed ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">City / State (Optional)</label>
                    <input type="text" name="city" class="form-control" placeholder="e.g. Patna, Bihar" value="<?= htmlspecialchars($city_filter) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-search me-1"></i>Find Donors
                    </button>
                </div>
                <?php if ($bg_id): ?>
                <div class="col-md-2">
                    <a href="smart_match.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($bg_id): ?>
    <!-- Stock Status -->
    <div class="alert alert-<?= $stock_ok ? 'success' : 'danger' ?> d-flex align-items-center mb-4">
        <i class="bi bi-<?= $stock_ok ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2 fs-5"></i>
        <div>
            <?php if ($stock_ok): ?>
                <strong>Stock Available:</strong> <?= $stock['units'] ?> units of <?= $stock['bg_name'] ?> in stock. Request can be fulfilled directly.
            <?php else: ?>
                <strong>Stock Low / Empty:</strong> Only <?= $stock['units'] ?? 0 ?> units available. You need donors to donate now.
            <?php endif; ?>
        </div>
    </div>

    <!-- Matched Donors -->
    <div class="card">
        <div class="card-header py-3 px-4 d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-person-check me-2 text-danger"></i>
                Best Matched Donors
                <?php if ($city_filter): ?><span class="badge bg-secondary ms-2">Near: <?= htmlspecialchars($city_filter) ?></span><?php endif; ?>
            </span>
            <span class="text-muted small"><?= $matches ? $matches->num_rows : 0 ?> eligible donors found</span>
        </div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">Rank</th>
                    <th>Donor Name</th>
                    <th>Blood Group</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Last Donated</th>
                    <th>Days Since</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php if (!$matches || $matches->num_rows === 0): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-emoji-frown fs-2 d-block mb-2"></i>
                        No eligible donors found for this blood group.
                        <?php if ($city_filter): ?><br><a href="?bg=<?=$bg_id?>&units=<?=$units_needed?>">Try without city filter</a><?php endif; ?>
                    </td></tr>
                <?php else: $rank=1; while ($d = $matches->fetch_assoc()): ?>
                <tr <?= $rank===1?'style="background:rgba(220,53,69,0.04);"':'' ?>>
                    <td class="ps-4">
                        <?= $rank===1?'🥇':($rank===2?'🥈':($rank===3?'🥉':"#$rank")) ?>
                    </td>
                    <td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                    <td><span class="bg-badge"><?= $d['bg_name'] ?></span></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= htmlspecialchars($d['city'] ?? '—') ?></td>
                    <td><?= $d['last_donation_date'] ? date('d M Y', strtotime($d['last_donation_date'])) : '<span class="text-success">Never (fresh)</span>' ?></td>
                    <td>
                        <?php if ($d['last_donation_date']): ?>
                            <span class="fw-semibold text-success"><?= (int)$d['days_since'] ?> days</span>
                        <?php else: ?>
                            <span class="badge bg-success">First time</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-success">✓ Eligible</span></td>
                    <td>
                        <a href="/blood_bank/admin/add_donation.php?donor_id=<?= $d['id'] ?>"
                           class="btn btn-sm btn-danger">Log Donation</a>
                        <a href="/blood_bank/admin/appointments.php?donor_id=<?= $d['id'] ?>"
                           class="btn btn-sm btn-outline-primary ms-1">Book Appt</a>
                    </td>
                </tr>
                <?php $rank++; endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Compatibility Info -->
    <div class="alert alert-info mt-3 small">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Compatibility Note:</strong> Results include donors with compatible blood groups, not just exact match.
        Donors are ranked by longest time since last donation (most rested first). Only donors eligible (90+ days gap) are shown.
    </div>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
