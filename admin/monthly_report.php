<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Monthly Report';

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Monthly donations
$monthly = $conn->query("SELECT
    DATE_FORMAT(donated_date,'%b') as month_name,
    MONTH(donated_date) as month_num,
    COUNT(*) as total_donations,
    SUM(units) as total_units
    FROM donation_log
    WHERE YEAR(donated_date)=$selected_year
    GROUP BY MONTH(donated_date) ORDER BY month_num");

// Monthly requests
$monthly_req = $conn->query("SELECT
    DATE_FORMAT(created_at,'%b') as month_name,
    MONTH(created_at) as month_num,
    COUNT(*) as total,
    SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
    SUM(units_needed) as units_requested
    FROM blood_requests
    WHERE YEAR(created_at)=$selected_year
    GROUP BY MONTH(created_at) ORDER BY month_num");

// Blood usage by group
$usage = $conn->query("SELECT bg.name, SUM(ABS(sl.change_units)) as used
    FROM stock_log sl JOIN blood_groups bg ON sl.blood_group_id=bg.id
    WHERE sl.change_units < 0 AND YEAR(sl.created_at)=$selected_year
    GROUP BY sl.blood_group_id ORDER BY used DESC");

// Top donors this year
$top = $conn->query("SELECT d.name, bg.name as bg_name, COUNT(dl.id) as cnt, SUM(dl.units) as total_units
    FROM donation_log dl JOIN donors d ON dl.donor_id=d.id
    JOIN blood_groups bg ON d.blood_group_id=bg.id
    WHERE YEAR(dl.donated_date)=$selected_year
    GROUP BY dl.donor_id ORDER BY cnt DESC LIMIT 5");

// Year summary
$summary = $conn->query("SELECT
    COUNT(*) as total_donations,
    SUM(units) as total_units,
    COUNT(DISTINCT donor_id) as unique_donors
    FROM donation_log WHERE YEAR(donated_date)=$selected_year")->fetch_assoc();

$req_summary = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status='Pending'  THEN 1 ELSE 0 END) as pending
    FROM blood_requests WHERE YEAR(created_at)=$selected_year")->fetch_assoc();

// Chart arrays
$months_all = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$don_data   = array_fill(0, 12, 0);
$req_data   = array_fill(0, 12, 0);
$monthly->data_seek(0);
while ($r = $monthly->fetch_assoc())    $don_data[$r['month_num']-1] = (int)$r['total_units'];
$monthly_req->data_seek(0);
while ($r = $monthly_req->fetch_assoc()) $req_data[$r['month_num']-1] = (int)$r['total'];

$usage_labels = $usage_vals = [];
$usage->data_seek(0);
while ($r = $usage->fetch_assoc()) { $usage_labels[]=$r['name']; $usage_vals[]=(int)$r['used']; }

// Available years
$years = [];
$yr = $conn->query("SELECT DISTINCT YEAR(donated_date) as y FROM donation_log ORDER BY y DESC");
while ($r = $yr->fetch_assoc()) $years[] = $r['y'];
if (!in_array(date('Y'), $years)) array_unshift($years, (int)date('Y'));
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">
            <i class="bi bi-calendar-month me-2 text-danger"></i>Monthly Report <?= $selected_year ?>
        </h1>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" class="d-flex gap-2">
                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $y==$selected_year?'selected':'' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button onclick="window.print()" class="btn btn-outline-danger btn-sm no-print">
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
    </div>

    <!-- Year Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-droplet-fill"></i></div>
                <div><div class="stat-value"><?= $summary['total_donations'] ?? 0 ?></div><div class="stat-label">Total Donations <?= $selected_year ?></div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-bag-plus-fill"></i></div>
                <div><div class="stat-value"><?= $summary['total_units'] ?? 0 ?></div><div class="stat-label">Units Collected</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
                <div><div class="stat-value"><?= $summary['unique_donors'] ?? 0 ?></div><div class="stat-label">Unique Donors</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-clipboard-check"></i></div>
                <div><div class="stat-value"><?= $req_summary['approved'] ?? 0 ?></div><div class="stat-label">Requests Fulfilled</div></div>
            </div>
        </div>
    </div>

    <!-- Monthly Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Monthly Blood Units Collected vs Requests</div>
                <div class="card-body"><canvas id="monthlyChart" height="130"></canvas></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Blood Usage by Group</div>
                <div class="card-body">
                    <?php if (empty($usage_vals)): ?>
                        <p class="text-muted text-center py-3">No usage data yet.</p>
                    <?php else: ?>
                    <canvas id="usageChart" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Table + Top Donors -->
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Month-by-Month Breakdown</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">Month</th>
                            <th>Donations</th>
                            <th>Units Collected</th>
                            <th>Requests</th>
                            <th>Approved</th>
                        </tr></thead>
                        <tbody>
                        <?php
                        // Build indexed arrays
                        $don_map = $req_map = [];
                        $monthly->data_seek(0);
                        while ($r = $monthly->fetch_assoc()) $don_map[$r['month_num']] = $r;
                        $monthly_req->data_seek(0);
                        while ($r = $monthly_req->fetch_assoc()) $req_map[$r['month_num']] = $r;
                        $any = false;
                        for ($m=1; $m<=12; $m++):
                            $d = $don_map[$m] ?? null;
                            $r = $req_map[$m] ?? null;
                            if (!$d && !$r) continue;
                            $any = true;
                        ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><?= date('F', mktime(0,0,0,$m,1)) ?></td>
                            <td><?= $d['total_donations'] ?? 0 ?></td>
                            <td><?= $d['total_units'] ?? 0 ?></td>
                            <td><?= $r['total'] ?? 0 ?></td>
                            <td>
                                <?php $app = $r['approved'] ?? 0; $tot = $r['total'] ?? 0; ?>
                                <?= $app ?>
                                <?php if ($tot > 0): ?>
                                <span class="text-muted small">(<?= round($app/$tot*100) ?>%)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endfor;
                        if (!$any): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No data for <?= $selected_year ?>.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">🏆 Top Donors <?= $selected_year ?></div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th class="ps-4">#</th><th>Donor</th><th>Group</th><th>Times</th><th>Units</th></tr></thead>
                        <tbody>
                        <?php $top->data_seek(0); $i=1;
                        if ($top->num_rows===0): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No donations this year yet.</td></tr>
                        <?php else: while ($t = $top->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><?= $i===1?'🥇':($i===2?'🥈':($i===3?'🥉':"#$i")) ?></td>
                            <?php $i++; ?>
                            <td class="fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
                            <td><span class="bg-badge"><?= $t['bg_name'] ?></span></td>
                            <td><?= $t['cnt'] ?>×</td>
                            <td class="text-success fw-bold"><?= $t['total_units'] ?></td>
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
<script>
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($months_all) ?>,
        datasets: [
            {
                label: 'Units Collected',
                data: <?= json_encode($don_data) ?>,
                backgroundColor: 'rgba(220,53,69,0.75)',
                borderRadius: 4
            },
            {
                label: 'Requests',
                data: <?= json_encode($req_data) ?>,
                backgroundColor: 'rgba(13,110,253,0.6)',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
<?php if (!empty($usage_vals)): ?>
new Chart(document.getElementById('usageChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($usage_labels) ?>,
        datasets: [{
            data: <?= json_encode($usage_vals) ?>,
            backgroundColor: ['#ef5350','#e53935','#42a5f5','#1e88e5','#66bb6a','#43a047','#ab47bc','#8e24aa']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
<?php endif; ?>
</script>
