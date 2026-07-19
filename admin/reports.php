<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Reports';

// Stats
$total_donors     = $conn->query("SELECT COUNT(*) as c FROM donors WHERE is_active=1")->fetch_assoc()['c'];
$total_donations  = $conn->query("SELECT COUNT(*) as c FROM donation_log")->fetch_assoc()['c'];
$total_requests   = $conn->query("SELECT COUNT(*) as c FROM blood_requests")->fetch_assoc()['c'];
$approved_req     = $conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Approved'")->fetch_assoc()['c'];
$rejected_req     = $conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Rejected'")->fetch_assoc()['c'];
$pending_req      = $conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Pending'")->fetch_assoc()['c'];

// Donors by blood group
$donors_by_bg = $conn->query("SELECT bg.name, COUNT(d.id) as cnt FROM blood_groups bg
    LEFT JOIN donors d ON d.blood_group_id=bg.id AND d.is_active=1 GROUP BY bg.id ORDER BY bg.id");

// Stock
$stock = $conn->query("SELECT bg.name, bs.units FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");

// Top donors - real-time from donation_log
$top_donors = $conn->query("
    SELECT d.id, d.name, d.phone, bg.name as bg_name,
           COUNT(dl.id)   AS donations,
           COALESCE(SUM(dl.units),0) AS total_units,
           MAX(dl.donated_date) AS last_donated
    FROM donors d
    JOIN blood_groups bg ON d.blood_group_id=bg.id
    LEFT JOIN donation_log dl ON dl.donor_id=d.id
    WHERE d.is_active=1
    GROUP BY d.id
    ORDER BY donations DESC, total_units DESC
    LIMIT 10
");

$bg_labels = $bg_donors = $bg_stock = [];
$donors_by_bg->data_seek(0);
while ($r = $donors_by_bg->fetch_assoc()) {
    $bg_labels[] = $r['name'];
    $bg_donors[] = (int)$r['cnt'];
}
$stock->data_seek(0);
while ($r = $stock->fetch_assoc()) {
    $bg_stock[] = (int)$r['units'];
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0"><i class="bi bi-bar-chart-line me-2 text-danger"></i>Reports</h1>
        <button onclick="window.print()" class="btn btn-outline-danger btn-sm no-print">
            <i class="bi bi-printer me-1"></i>Print Report
        </button>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-danger"><?= $total_donors ?></div>
                <div class="stat-label">Active Donors</div>
            </div>
        </div>
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-success"><?= $total_donations ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
        </div>
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-primary"><?= $total_requests ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-success"><?= $approved_req ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-danger"><?= $rejected_req ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        <div class="col-sm-4 col-xl-2">
            <div class="stat-card flex-column text-center py-3">
                <div class="stat-value text-warning"><?= $pending_req ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Donors by Blood Group</div>
                <div class="card-body"><canvas id="donorChart" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Current Stock by Blood Group</div>
                <div class="card-body"><canvas id="stockChart" height="220"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Request Fulfillment -->
    <div class="card mb-4">
        <div class="card-header py-3 px-4 fw-semibold">Request Fulfillment Rate</div>
        <div class="card-body">
            <?php if ($total_requests > 0): $rate = round($approved_req / $total_requests * 100); ?>
            <div class="d-flex align-items-center gap-3 mb-2">
                <span class="fw-semibold" style="min-width:140px;">Fulfillment Rate</span>
                <div class="progress flex-grow-1" style="height:20px;border-radius:10px;">
                    <div class="progress-bar bg-success" style="width:<?= $rate ?>%"><?= $rate ?>%</div>
                </div>
                <span class="fw-bold text-success"><?= $rate ?>%</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Donors -->
    <div class="card">
        <div class="card-header py-3 px-4 fw-semibold">Top Donors</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4">#</th><th>Donor Name</th><th>Blood Group</th>
                    <th>Phone</th><th>Donations</th>
                </tr></thead>
                <tbody>
                <?php $i=1; while ($d = $top_donors->fetch_assoc()): ?>
                <tr>
                    <td class="ps-4"><?= $i === 1 ? '🥇' : ($i === 2 ? '🥈' : ($i === 3 ? '🥉' : $i)) ?></td>
                    <?php $i++; ?>
                    <td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                    <td><span class="bg-badge"><?= $d['bg_name'] ?></span></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= $d['donations'] > 0 ? $d['donations'] : '<span class="text-muted">None yet</span>' ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
new Chart(document.getElementById('donorChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($bg_labels) ?>,
        datasets: [{ data: <?= json_encode($bg_donors) ?>,
            backgroundColor: ['#ef5350','#e53935','#42a5f5','#1e88e5','#66bb6a','#43a047','#ab47bc','#8e24aa'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'right' } } }
});
new Chart(document.getElementById('stockChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($bg_labels) ?>,
        datasets: [{ label: 'Units', data: <?= json_encode($bg_stock) ?>,
            backgroundColor: 'rgba(220,53,69,0.7)', borderRadius: 6, borderSkipped: false }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } } }
});
</script>
