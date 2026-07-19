<?php
// ============================================================
// BloodSync Admin Dashboard v8 — Modern Redesign
// Circular blood stock UI, animated background, glassmorphism
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Dashboard';

// ── Stats ────────────────────────────────────────────────────
$total_donors   = (int)$conn->query("SELECT COUNT(*) as c FROM donors WHERE is_active=1")->fetch_assoc()['c'];
$total_requests = (int)$conn->query("SELECT COUNT(*) as c FROM blood_requests")->fetch_assoc()['c'];
$pending_req    = (int)$conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Pending'")->fetch_assoc()['c'];
$approved_req   = (int)$conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Approved'")->fetch_assoc()['c'];
$total_units    = (int)$conn->query("SELECT SUM(units) as c FROM blood_stock")->fetch_assoc()['c'];
$open_emerg     = 0;
$ec = $conn->query("SHOW TABLES LIKE 'emergency_requests'");
if ($ec && $ec->num_rows > 0) {
    $ec2 = $conn->query("SELECT COUNT(*) as c FROM emergency_requests WHERE status='Open'");
    if ($ec2) $open_emerg = (int)$ec2->fetch_assoc()['c'];
}

// ── Blood stock data ─────────────────────────────────────────
$stock_raw = $conn->query("SELECT bg.name, bs.units FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");
$stock_data = [];
while ($r = $stock_raw->fetch_assoc()) {
    $stock_data[] = ['name' => $r['name'], 'units' => (int)$r['units']];
}
$max_units = max(array_column($stock_data, 'units') ?: [1]);

// ── Recent requests ───────────────────────────────────────────
$recent = $conn->query("SELECT br.*, bg.name as blood_name, h.name as hospital_name
    FROM blood_requests br
    JOIN blood_groups bg ON br.blood_group_id=bg.id
    LEFT JOIN hospitals h ON br.hospital_id=h.id
    ORDER BY br.created_at DESC LIMIT 6");

// ── Donut chart data ──────────────────────────────────────────
$donut_labels = array_column($stock_data, 'name');
$donut_units  = array_column($stock_data, 'units');
$donut_colors = array_map(function($u) {
    if ($u <= 2)  return 'rgba(220,53,69,0.85)';
    if ($u <= 5)  return 'rgba(255,193,7,0.85)';
    return 'rgba(25,135,84,0.85)';
}, $donut_units);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>

<!-- ═══ ANIMATED BLOOD THEME BACKGROUND ════════════════════ -->
<div id="admBgCanvas" style="position:fixed;inset:0;z-index:-3;pointer-events:none;overflow:hidden;">
    <!-- SVG heartbeat lines -->
    <svg id="hbSvg" style="position:absolute;bottom:0;left:0;width:100%;height:90px;opacity:0.13;" viewBox="0 0 1440 90" preserveAspectRatio="none">
        <path id="hbPath1" d="M0,45 L160,45 L195,12 L225,78 L255,12 L285,78 L315,45 L500,45 L535,12 L565,78 L595,12 L625,78 L655,45 L840,45 L875,12 L905,78 L935,12 L965,78 L995,45 L1440,45"
            stroke="#e74c3c" stroke-width="2.5" fill="none" stroke-linecap="round"
            style="stroke-dasharray:1400;stroke-dashoffset:1400;animation:hbDraw 4s ease-in-out infinite;"/>
        <path id="hbPath2" d="M0,65 L200,65 L230,38 L255,82 L280,38 L305,82 L330,65 L520,65"
            stroke="rgba(231,76,60,0.5)" stroke-width="1.5" fill="none" stroke-linecap="round"
            style="stroke-dasharray:600;stroke-dashoffset:600;animation:hbDraw 4s ease-in-out infinite 1s;"/>
    </svg>
    <!-- Blood drop particles (injected by JS) -->
    <div id="dropContainer"></div>
    <!-- Glowing orbs -->
    <div id="orbContainer"></div>
</div>

<style>
/* ── Heartbeat animation ────────────────────────────── */
@keyframes hbDraw {
    0%   { stroke-dashoffset:1400; opacity:0; }
    10%  { opacity:1; }
    88%  { opacity:1; }
    100% { stroke-dashoffset:0; opacity:0; }
}
/* ── Blood drop float ───────────────────────────────── */
.bd-drop {
    position:absolute; border-radius:50% 50% 50% 0;
    transform:rotate(-45deg);
    animation:bdFloat linear infinite;
    pointer-events:none;
}
@keyframes bdFloat {
    0%   { transform:rotate(-45deg) translateY(0) scale(0.3); opacity:0; }
    8%   { opacity:1; }
    92%  { opacity:0.5; }
    100% { transform:rotate(-45deg) translateY(-110vh) scale(1); opacity:0; }
}
/* ── Glowing orbs ───────────────────────────────────── */
.bd-orb {
    position:absolute; border-radius:50%;
    background:radial-gradient(circle, rgba(192,57,43,0.6), rgba(140,10,10,0.2));
    animation:orbPulse ease-in-out infinite alternate;
    pointer-events:none; filter:blur(1px);
}
@keyframes orbPulse {
    from { transform:scale(0.8); opacity:0.4; }
    to   { transform:scale(1.2); opacity:0.8; }
}
/* ── Ripple rings ───────────────────────────────────── */
.bd-ring {
    position:absolute; border-radius:50%;
    border:1.5px solid rgba(192,57,43,0.2);
    animation:ringExpand ease-out infinite;
    pointer-events:none;
}
@keyframes ringExpand {
    0%   { transform:scale(0.2); opacity:0.7; }
    100% { transform:scale(5); opacity:0; }
}

/* ════════════════════════════════════════════════════════
   STAT CARDS — Enhanced glassmorphism
════════════════════════════════════════════════════════ */
.bs-stat {
    background:var(--aCard);
    border-radius:18px;
    border:1px solid var(--aBorder);
    padding:22px 20px;
    display:flex; align-items:center; gap:16px;
    position:relative; overflow:hidden;
    transition:transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    cursor:default;
}
.bs-stat::before {
    content:'';
    position:absolute; top:-40%; right:-20%;
    width:120px; height:120px; border-radius:50%;
    opacity:0.06; transition:opacity 0.3s;
}
.bs-stat:hover { transform:translateY(-5px); box-shadow:0 14px 40px rgba(0,0,0,0.15); }
.bs-stat:hover::before { opacity:0.1; }
.bs-stat.s-red::before   { background:#dc3545; }
.bs-stat.s-green::before { background:#198754; }
.bs-stat.s-blue::before  { background:#0d6efd; }
.bs-stat.s-orange::before{ background:#fd7e14; }

.bs-stat-icon {
    width:56px; height:56px; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.5rem; flex-shrink:0;
    transition:transform 0.3s ease;
}
.bs-stat:hover .bs-stat-icon { transform:scale(1.12) rotate(-5deg); }
.bs-stat-icon.red    { background:var(--aStatRedBg);    color:#dc3545; }
.bs-stat-icon.green  { background:var(--aStatGreenBg);  color:#198754; }
.bs-stat-icon.blue   { background:var(--aStatBlueBg);   color:#0d6efd; }
.bs-stat-icon.orange { background:var(--aStatOrangeBg); color:#fd7e14; }
.bs-stat-val   { font-size:2rem; font-weight:800; color:var(--aHeading); line-height:1; }
.bs-stat-label { font-size:0.78rem; color:var(--aMuted); margin-top:4px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
.bs-stat-trend { font-size:0.7rem; margin-top:6px; font-weight:600; }

/* ════════════════════════════════════════════════════════
   CIRCULAR BLOOD STOCK — Donut rings
════════════════════════════════════════════════════════ */
.bs-stock-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
}
@media(max-width:768px){ .bs-stock-grid{ grid-template-columns:repeat(4,1fr); gap:8px; } }
@media(max-width:480px){ .bs-stock-grid{ grid-template-columns:repeat(4,1fr); gap:6px; } }

.bs-ring-wrap {
    display:flex; flex-direction:column; align-items:center;
    position:relative; cursor:default;
    transition:transform 0.3s ease;
}
.bs-ring-wrap:hover { transform:scale(1.08); }

.bs-ring-canvas { position:relative; }
.bs-ring-canvas canvas { display:block; }

/* Center text inside donut */
.bs-ring-center {
    position:absolute; inset:0;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    pointer-events:none;
}
.bs-ring-bg   { font-size:1rem; font-weight:800; color:var(--aHeading); line-height:1; }
.bs-ring-name { font-size:0.6rem; color:var(--aMuted); font-weight:700; margin-top:2px; text-transform:uppercase; }

/* Status dot */
.bs-ring-status {
    display:inline-flex; align-items:center; gap:4px;
    font-size:0.62rem; font-weight:700; margin-top:5px;
    padding:2px 8px; border-radius:10px;
}
.bs-ring-status.critical { background:rgba(220,53,69,0.15); color:#dc3545; }
.bs-ring-status.low      { background:rgba(255,193,7,0.15);  color:#997400; }
.bs-ring-status.good     { background:rgba(25,135,84,0.15);  color:#198754; }
body.adm-dark .bs-ring-status.low { color:#e3b341 !important; }

/* Tooltip */
.bs-ring-tooltip {
    position:absolute; bottom:110%; left:50%; transform:translateX(-50%);
    background:rgba(20,20,20,0.9); color:white;
    padding:6px 12px; border-radius:8px; font-size:0.75rem; font-weight:600;
    white-space:nowrap; pointer-events:none;
    opacity:0; transition:opacity 0.2s;
    z-index:100; backdrop-filter:blur(4px);
}
.bs-ring-wrap:hover .bs-ring-tooltip { opacity:1; }
.bs-ring-tooltip::after {
    content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%);
    border:5px solid transparent; border-top-color:rgba(20,20,20,0.9);
}

/* ════════════════════════════════════════════════════════
   DONUT DISTRIBUTION CHART CARD
════════════════════════════════════════════════════════ */
.bs-chart-card {
    background:var(--aCard);
    border:1px solid var(--aBorder);
    border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    overflow:hidden;
    transition:box-shadow 0.3s;
}
.bs-chart-card:hover { box-shadow:0 12px 40px rgba(0,0,0,0.14); }
.bs-chart-header {
    padding:16px 20px;
    display:flex; align-items:center; justify-content:space-between;
    border-bottom:1px solid var(--aBorder);
}
.bs-chart-title { font-weight:700; font-size:0.95rem; color:var(--aHeading); display:flex; align-items:center; gap:8px; }

/* ════════════════════════════════════════════════════════
   RECENT REQUESTS TABLE
════════════════════════════════════════════════════════ */
.bs-table-card {
    background:var(--aCard);
    border:1px solid var(--aBorder);
    border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    overflow:hidden;
}
.bs-req-row { transition:background 0.15s; }
.bs-req-row:hover { background:var(--aTableHover) !important; }

/* ════════════════════════════════════════════════════════
   QUICK ACTION CARDS
════════════════════════════════════════════════════════ */
.bs-quick {
    background:var(--aCard);
    border:1px solid var(--aBorder);
    border-radius:16px; padding:18px 16px;
    text-align:center; text-decoration:none;
    display:block; transition:all 0.3s ease;
    box-shadow:0 2px 12px rgba(0,0,0,0.06);
    position:relative; overflow:hidden;
}
.bs-quick::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,transparent 60%,rgba(192,57,43,0.06));
    opacity:0; transition:opacity 0.3s;
}
.bs-quick:hover {
    transform:translateY(-6px);
    box-shadow:0 16px 40px rgba(192,57,43,0.18);
    border-color:rgba(192,57,43,0.4);
    text-decoration:none;
}
.bs-quick:hover::after { opacity:1; }
.bs-quick i { font-size:1.8rem; display:block; margin-bottom:8px; color:#c0392b; }
.bs-quick .bs-ql { font-size:0.78rem; font-weight:700; color:var(--aHeading); text-transform:uppercase; letter-spacing:.04em; }

/* ════════════════════════════════════════════════════════
   EMERGENCY BANNER
════════════════════════════════════════════════════════ */
.bs-emerg-banner {
    background:linear-gradient(135deg,rgba(192,57,43,0.12),rgba(192,57,43,0.06));
    border:1.5px solid rgba(192,57,43,0.4);
    border-left:4px solid #c0392b;
    border-radius:14px; padding:14px 20px;
    display:flex; align-items:center; gap:14px; flex-wrap:wrap;
    animation:emergPulse 2s ease-in-out infinite;
}
@keyframes emergPulse {
    0%,100% { box-shadow:0 0 0 0 rgba(192,57,43,0.3); }
    50%      { box-shadow:0 0 0 8px rgba(192,57,43,0); }
}

/* ════════════════════════════════════════════════════════
   SECTION LABELS
════════════════════════════════════════════════════════ */
.bs-section-label {
    font-size:0.7rem; font-weight:800; text-transform:uppercase;
    letter-spacing:.1em; color:var(--aMuted); margin-bottom:12px;
    display:flex; align-items:center; gap:6px;
}
.bs-section-label::after {
    content:''; flex:1; height:1px; background:var(--aBorder);
}
</style>

<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content" style="position:relative;z-index:1;">

    <!-- ── Top bar ─────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:var(--aHeading);margin:0;display:flex;align-items:center;gap:10px;">
                <i class="bi bi-speedometer2 text-danger"></i>Dashboard
            </h1>
            <div style="color:var(--aMuted);font-size:0.83rem;margin-top:4px;">
                Welcome back, <strong style="color:var(--aHeading);"><?= htmlspecialchars($_SESSION['name']) ?></strong>
                &nbsp;·&nbsp; <?= date('l, d F Y') ?>
                &nbsp;·&nbsp; <span id="liveClock" style="font-weight:600;color:#c0392b;"></span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($open_emerg > 0): ?>
            <a href="/blood_bank/admin/emergency.php" class="btn btn-danger btn-sm fw-semibold">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?= $open_emerg ?> Emergency
            </a>
            <?php endif; ?>
            <a href="/blood_bank/admin/add_donor.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-person-plus me-1"></i>Add Donor
            </a>
            <button onclick="dashboardAutoRefresh()" class="btn btn-outline-secondary btn-sm" title="Refresh data">
                <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
            </button>
        </div>
    </div>

    <!-- ── Emergency banner ────────────────────────────────── -->
    <?php if ($open_emerg > 0): ?>
    <div class="bs-emerg-banner mb-4">
        <div style="width:42px;height:42px;border-radius:50%;background:#dc3545;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-exclamation-lg text-white fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <strong class="text-danger">🚨 <?= $open_emerg ?> Open Emergency Request<?= $open_emerg>1?'s':'' ?>!</strong>
            <span style="color:var(--aMuted);font-size:0.82rem;margin-left:8px;">Immediate action required.</span>
        </div>
        <a href="/blood_bank/admin/emergency.php" class="btn btn-danger btn-sm fw-semibold">View Now →</a>
    </div>
    <?php endif; ?>

    <!-- ══ STAT CARDS ══════════════════════════════════════════ -->
    <div class="bs-section-label"><i class="bi bi-grid-3x3-gap-fill text-danger"></i>Overview</div>
    <div class="row g-3 mb-4">
        <?php $stats = [
            ['Total Donors',   $total_donors,   'people-fill',     'red',    '+Active', ''],
            ['Units in Stock', $total_units,    'droplet-fill',    'green',  'All groups', ''],
            ['Total Requests', $total_requests, 'clipboard-plus',  'blue',   "$approved_req approved", ''],
            ['Pending',        $pending_req,    'hourglass-split', 'orange', 'Needs review', ''],
        ];
        foreach ($stats as $i => [$lbl,$val,$icon,$clr,$trend,$arrow]): ?>
        <div class="col-6 col-xl-3">
            <div class="bs-stat s-<?= $clr ?>">
                <div class="bs-stat-icon <?= $clr ?>">
                    <i class="bi bi-<?= $icon ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="bs-stat-val" data-target="<?= $val ?>" id="sv<?= $i ?>">0</div>
                    <div class="bs-stat-label"><?= $lbl ?></div>
                    <div class="bs-stat-trend text-muted"><?= $trend ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ══ BLOOD STOCK — CIRCULAR UI ══════════════════════════ -->
    <div class="row g-4 mb-4">

        <!-- Circular donut rings -->
        <div class="col-xl-7">
            <div class="bs-chart-card h-100">
                <div class="bs-chart-header">
                    <div class="bs-chart-title">
                        <i class="bi bi-droplet-fill text-danger"></i>Blood Stock — Live
                    </div>
                    <a href="/blood_bank/admin/blood_stock.php" class="btn btn-sm btn-outline-danger">Manage</a>
                </div>
                <div class="card-body p-4">
                    <div class="bs-stock-grid" id="stockRingGrid">
                        <?php foreach ($stock_data as $s):
                            $u   = $s['units'];
                            $nm  = $s['name'];
                            $max = max($max_units, 20);
                            $pct = min(100, round($u / $max * 100));
                            if ($u <= 2)      { $status='critical'; $color='#dc3545'; $sLabel='Critical'; }
                            elseif ($u <= 5)  { $status='low';      $color='#ffc107'; $sLabel='Low'; }
                            else              { $status='good';     $color='#198754'; $sLabel='Good'; }
                        ?>
                        <div class="bs-ring-wrap" id="ring_<?= str_replace(['+','-'],'_',$nm) ?>">
                            <div class="bs-ring-tooltip"><?= $u ?> units available</div>
                            <div class="bs-ring-canvas" style="width:80px;height:80px;">
                                <canvas width="80" height="80"
                                    data-units="<?= $u ?>"
                                    data-max="<?= $max ?>"
                                    data-color="<?= $color ?>"
                                    data-name="<?= $nm ?>"
                                    class="stock-ring-canvas">
                                </canvas>
                                <div class="bs-ring-center">
                                    <div class="bs-ring-bg"><?= $u ?></div>
                                    <div class="bs-ring-name"><?= $nm ?></div>
                                </div>
                            </div>
                            <div class="bs-ring-status <?= $status ?>"><?= $sLabel ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legend -->
                    <div class="d-flex gap-4 mt-4 pt-3" style="border-top:1px solid var(--aBorder);font-size:0.75rem;font-weight:600;">
                        <span style="color:#dc3545;"><span style="font-size:1rem;margin-right:4px;">●</span>Critical (&lt;3 units)</span>
                        <span style="color:#997400;"><span style="font-size:1rem;margin-right:4px;">●</span>Low (&lt;6 units)</span>
                        <span style="color:#198754;"><span style="font-size:1rem;margin-right:4px;">●</span>Good (6+ units)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donut distribution chart -->
        <div class="col-xl-5">
            <div class="bs-chart-card h-100">
                <div class="bs-chart-header">
                    <div class="bs-chart-title">
                        <i class="bi bi-pie-chart-fill text-danger"></i>Stock Distribution
                    </div>
                    <span style="font-size:0.72rem;color:var(--aMuted);">Total: <?= $total_units ?> units</span>
                </div>
                <div class="card-body p-3 d-flex flex-column align-items-center justify-content-center">
                    <div style="position:relative;width:200px;height:200px;">
                        <canvas id="stockDonut" width="200" height="200"></canvas>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                            <div style="font-size:1.8rem;font-weight:800;color:var(--aHeading);line-height:1;"><?= $total_units ?></div>
                            <div style="font-size:0.7rem;color:var(--aMuted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Total Units</div>
                        </div>
                    </div>
                    <!-- Custom legend -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;margin-top:16px;width:100%;">
                        <?php foreach ($stock_data as $s):
                            $u = $s['units'];
                            $clr = $u<=2?'#dc3545':($u<=5?'#ffc107':'#198754');
                        ?>
                        <div style="display:flex;align-items:center;gap:6px;font-size:0.75rem;">
                            <span style="width:10px;height:10px;border-radius:50%;background:<?= $clr ?>;flex-shrink:0;display:inline-block;"></span>
                            <span style="color:var(--aHeading);font-weight:600;"><?= $s['name'] ?></span>
                            <span style="color:var(--aMuted);margin-left:auto;"><?= $s['units'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ QUICK ACTIONS ═══════════════════════════════════════ -->
    <div class="bs-section-label"><i class="bi bi-lightning-fill text-danger"></i>Quick Actions</div>
    <div class="row g-3 mb-4">
        <?php $actions = [
            ['/blood_bank/admin/add_donor.php',    'person-plus-fill',    'Add Donor'],
            ['/blood_bank/admin/blood_stock.php',  'droplet-half',        'Manage Stock'],
            ['/blood_bank/admin/requests.php',     'clipboard-check-fill','Requests'],
            ['/blood_bank/admin/emergency.php',    'exclamation-triangle-fill','Emergency'],
            ['/blood_bank/admin/smart_match.php',  'stars',               'Smart Match'],
            ['/blood_bank/admin/reports.php',      'bar-chart-line-fill', 'Reports'],
            ['/blood_bank/admin/manage_users.php', 'people-fill',         'Users'],
        ]; foreach ($actions as [$href,$icon,$label]): ?>
        <div class="col-6 col-sm-3 col-xl-<?= count($actions) <= 4 ? 3 : (count($actions) <= 6 ? 2 : 2) ?>">
            <a href="<?= $href ?>" class="bs-quick">
                <i class="bi bi-<?= $icon ?>"></i>
                <div class="bs-ql"><?= $label ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ══ RECENT REQUESTS ════════════════════════════════════ -->
    <div class="bs-section-label"><i class="bi bi-clock-history text-danger"></i>Recent Requests</div>
    <div class="bs-table-card">
        <div class="bs-chart-header">
            <div class="bs-chart-title">
                <i class="bi bi-list-ul text-danger"></i>Latest Blood Requests
            </div>
            <a href="/blood_bank/admin/requests.php" class="btn btn-sm btn-outline-danger">View All</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="table mb-0" style="min-width:600px;">
                <thead><tr>
                    <th class="ps-4">Requester</th>
                    <th>Blood Group</th>
                    <th>Units</th>
                    <th>Hospital</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr></thead>
                <tbody id="recentRequestsTbody">
                <?php if (!$recent || $recent->num_rows===0): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No requests yet.</td></tr>
                <?php else: while ($r = $recent->fetch_assoc()):
                    $uc = ['Normal'=>'badge-normal','Urgent'=>'badge-urgent','Critical'=>'badge-critical'][$r['urgency']] ?? 'badge-normal';
                    $sc = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'][$r['status']] ?? 'secondary';
                ?>
                <tr class="bs-req-row">
                    <td class="ps-4 fw-semibold small"><?= htmlspecialchars($r['requester_name']) ?></td>
                    <td><span class="bg-badge"><?= $r['blood_name'] ?></span></td>
                    <td><?= $r['units_needed'] ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($r['hospital_name']??'N/A') ?></td>
                    <td><span class="badge <?= $uc ?>"><?= $r['urgency'] ?></span></td>
                    <td><span class="badge bg-<?= $sc ?>"><?= $r['status'] ?></span></td>
                    <td class="text-muted small"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /main-content -->
</div><!-- /d-flex -->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
/* ════════════════════════════════════════════════════════════
   ANIMATED BLOOD BACKGROUND
════════════════════════════════════════════════════════════ */
(function(){
    const drops = document.getElementById('dropContainer');
    const orbs  = document.getElementById('orbContainer');
    if (!drops || !orbs) return;

    // Blood drops
    for (let i = 0; i < 16; i++) {
        const d = document.createElement('div');
        const s = 5 + Math.random() * 14;
        d.className = 'bd-drop';
        d.style.cssText = `
            width:${s}px; height:${s*1.3}px;
            background:radial-gradient(circle at 35% 35%, rgba(231,76,60,0.8), rgba(150,20,20,0.5));
            left:${Math.random()*100}%;
            bottom:${-s}px;
            animation-duration:${10+Math.random()*16}s;
            animation-delay:${Math.random()*12}s;
            box-shadow:0 0 ${s}px rgba(192,57,43,0.3);
        `;
        drops.appendChild(d);
    }

    // Glowing orbs
    [[8,20],[75,40],[45,70],[88,15],[25,60]].forEach(([x,y],i) => {
        const orb = document.createElement('div');
        const s   = 60 + Math.random() * 100;
        orb.className = 'bd-orb';
        orb.style.cssText = `
            width:${s}px; height:${s}px;
            left:${x}%; top:${y}%;
            animation-duration:${4+Math.random()*4}s;
            animation-delay:${i*0.8}s;
        `;
        orbs.appendChild(orb);
    });

    // Ripple rings
    [[15,25],[65,55],[85,30]].forEach(([x,y],i) => {
        const ring = document.createElement('div');
        const s    = 80 + Math.random() * 100;
        ring.className = 'bd-ring';
        ring.style.cssText = `
            width:${s}px; height:${s}px;
            left:${x}%; top:${y}%;
            margin:-${s/2}px 0 0 -${s/2}px;
            animation-duration:${5+Math.random()*3}s;
            animation-delay:${i*1.2}s;
        `;
        orbs.appendChild(ring);
    });
})();

/* ════════════════════════════════════════════════════════════
   LIVE CLOCK
════════════════════════════════════════════════════════════ */
function updateClock(){
    const el = document.getElementById('liveClock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateClock(); setInterval(updateClock, 1000);

/* ════════════════════════════════════════════════════════════
   ANIMATED STAT COUNTERS
════════════════════════════════════════════════════════════ */
document.querySelectorAll('.bs-stat-val[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target) || 0;
    if (target === 0) { el.textContent = '0'; return; }
    let current = 0;
    const step  = Math.max(1, Math.ceil(target / 40));
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current;
        if (current >= target) clearInterval(timer);
    }, 28);
});

/* ════════════════════════════════════════════════════════════
   CIRCULAR DONUT RING CHARTS (Canvas API)
════════════════════════════════════════════════════════════ */
function drawRing(canvas, units, maxUnits, color) {
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;
    const cx = W/2, cy = H/2, r = W*0.38, sw = W*0.13;
    const pct = Math.max(0, Math.min(1, units / maxUnits));

    // Dark mode check
    const isDark = document.body.classList.contains('adm-dark');
    const trackColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.07)';

    ctx.clearRect(0, 0, W, H);

    // Track (background ring)
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI*2);
    ctx.strokeStyle = trackColor;
    ctx.lineWidth   = sw;
    ctx.lineCap     = 'round';
    ctx.stroke();

    // Glow effect
    if (pct > 0) {
        ctx.shadowColor = color;
        ctx.shadowBlur  = 8;
    }

    // Filled arc
    ctx.beginPath();
    ctx.arc(cx, cy, r, -Math.PI/2, -Math.PI/2 + Math.PI*2*pct);
    ctx.strokeStyle = color;
    ctx.lineWidth   = sw;
    ctx.lineCap     = 'round';
    ctx.stroke();
    ctx.shadowBlur  = 0;
}

function initAllRings() {
    document.querySelectorAll('.stock-ring-canvas').forEach(canvas => {
        const units = parseInt(canvas.dataset.units) || 0;
        const max   = parseInt(canvas.dataset.max)   || 20;
        const color = canvas.dataset.color;
        // Animate fill
        let progress = 0;
        const target = units / max;
        const anim   = setInterval(() => {
            progress = Math.min(progress + target/30, target);
            drawRing(canvas, progress * max, max, color);
            if (progress >= target) clearInterval(anim);
        }, 20);
    });
}

/* ════════════════════════════════════════════════════════════
   STOCK DONUT DISTRIBUTION CHART
════════════════════════════════════════════════════════════ */
function initDonut() {
    const canvas = document.getElementById('stockDonut');
    if (!canvas) return;

    const labels = <?= json_encode($donut_labels) ?>;
    const units  = <?= json_encode($donut_units) ?>;
    const colors = <?= json_encode($donut_colors) ?>;

    // Filter out zeros
    const filtered = labels.map((l,i) => ({l, u:units[i], c:colors[i]})).filter(x => x.u > 0);

    if (window._stockDonutChart) window._stockDonutChart.destroy();

    const isDark = document.body.classList.contains('adm-dark');
    Chart.defaults.color = isDark ? '#8b949e' : '#6c757d';

    window._stockDonutChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: filtered.map(x => x.l),
            datasets: [{
                data:            filtered.map(x => x.u),
                backgroundColor: filtered.map(x => x.c),
                borderColor:     isDark ? '#161b22' : '#ffffff',
                borderWidth: 3,
                hoverOffset: 10,
                borderRadius: 4,
            }]
        },
        options: {
            responsive:false,
            cutout:'68%',
            animation:{ animateRotate:true, duration:1000, easing:'easeInOutQuart' },
            plugins:{
                legend:{ display:false },
                tooltip:{
                    backgroundColor: isDark ? 'rgba(22,27,34,0.95)' : 'rgba(10,10,10,0.88)',
                    titleColor:'#ffffff', bodyColor:'rgba(255,255,255,0.8)',
                    padding:10, cornerRadius:10, borderWidth:1,
                    borderColor: isDark ? '#30363d' : 'rgba(255,255,255,0.15)',
                    callbacks:{
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} unit${ctx.parsed!==1?'s':''}`,
                    }
                }
            }
        }
    });
}

/* ════════════════════════════════════════════════════════════
   INIT ALL CHARTS
════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function(){
    initAllRings();
    initDonut();
});

/* ════════════════════════════════════════════════════════════
   REDRAW ON THEME CHANGE
════════════════════════════════════════════════════════════ */
const _origToggle = window.admToggleMode;
window.admToggleMode = function(){
    if (_origToggle) _origToggle();
    setTimeout(() => { initAllRings(); initDonut(); }, 350);
};

/* ════════════════════════════════════════════════════════════
   AUTO-REFRESH (60s) — AJAX
════════════════════════════════════════════════════════════ */
function dashboardAutoRefresh() {
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.style.animation = 'spin 0.8s linear infinite';

    fetch('/blood_bank/admin/ajax_dashboard.php?t=' + Date.now())
        .then(r => r.json())
        .then(d => {
            // Update stat counters
            const vals = document.querySelectorAll('.bs-stat-val');
            if (vals[0] && d.total_donors   !== undefined) vals[0].textContent = d.total_donors;
            if (vals[1] && d.total_units    !== undefined) vals[1].textContent = d.total_units;
            if (vals[2] && d.total_requests !== undefined) vals[2].textContent = d.total_requests;
            if (vals[3] && d.pending_req    !== undefined) vals[3].textContent = d.pending_req;
            // Update recent requests
            if (d.recent_html) {
                const tb = document.getElementById('recentRequestsTbody');
                if (tb) tb.innerHTML = d.recent_html;
            }
            // Update ring charts with fresh stock data
            if (d.stock_data && Array.isArray(d.stock_data)) {
                const maxU = Math.max(...d.stock_data.map(x=>x.units), 20);
                document.querySelectorAll('.stock-ring-canvas').forEach(canvas => {
                    const name  = canvas.dataset.name;
                    const found = d.stock_data.find(x => x.name === name);
                    if (!found) return;
                    const u = found.units;
                    const color = u<=2 ? '#dc3545' : (u<=5 ? '#ffc107' : '#198754');
                    // Update canvas data attrs
                    canvas.dataset.units = u;
                    canvas.dataset.max   = maxU;
                    canvas.dataset.color = color;
                    // Redraw
                    drawRing(canvas, u, maxU, color);
                    // Update center text
                    const center = canvas.parentElement.querySelector('.bs-ring-bg');
                    if (center) center.textContent = u;
                    // Update status badge
                    const wrap = canvas.closest('.bs-ring-wrap');
                    if (wrap) {
                        const badge = wrap.querySelector('.bs-ring-status');
                        if (badge) {
                            badge.className = 'bs-ring-status ' + (u<=2?'critical':u<=5?'low':'good');
                            badge.textContent = u<=2 ? 'Critical' : (u<=5 ? 'Low' : 'Good');
                        }
                        const tooltip = wrap.querySelector('.bs-ring-tooltip');
                        if (tooltip) tooltip.textContent = u + ' units available';
                    }
                });
                // Redraw donut
                initDonut();
            }
            if (icon) icon.style.animation = '';
        })
        .catch(() => { if (icon) icon.style.animation = ''; });
}

// Spin animation for refresh icon
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { to { transform:rotate(360deg); } }';
document.head.appendChild(spinStyle);

setInterval(dashboardAutoRefresh, 60000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) dashboardAutoRefresh(); });
</script>
