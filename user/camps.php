<?php
// ============================================================
// BloodSync - Donation Camps Page
// Shows blood donation camps across Bihar with full details
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title = 'Donation Camps';

// Check table exists
$check = $conn->query("SHOW TABLES LIKE 'donation_camps'");
if (!$check || $check->num_rows === 0) {
    echo "<div class='alert alert-warning m-4'>Please run the migration SQL first: database/migrate_v3_user_panel.sql</div>";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$city_filter = isset($_GET['city']) ? clean($conn, $_GET['city']) : '';
$where = "WHERE is_active=1 AND date >= CURDATE()";
if ($city_filter) $where .= " AND city LIKE '%$city_filter%'";

$camps    = $conn->query("SELECT * FROM donation_camps $where ORDER BY date ASC");
$past     = $conn->query("SELECT * FROM donation_camps WHERE is_active=1 AND date < CURDATE() ORDER BY date DESC LIMIT 5");
$cities   = $conn->query("SELECT DISTINCT city FROM donation_camps WHERE is_active=1 ORDER BY city");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.user-animated-bg{position:fixed;inset:0;z-index:-1;background:linear-gradient(135deg,#0d0000 0%,#1a0505 40%,#0a0000 100%);overflow:hidden;}
.blood-drop{position:absolute;border-radius:50% 50% 50% 0;transform:rotate(-45deg);animation:floatDrop linear infinite;opacity:0;}
@keyframes floatDrop{0%{transform:rotate(-45deg) translateY(0);opacity:0;}10%{opacity:0.5;}90%{opacity:0.2;}100%{transform:rotate(-45deg) translateY(-110vh);opacity:0;}}
.ub-wrap{max-width:1000px;margin:0 auto;padding:24px 20px 80px;position:relative;z-index:1;}
.glass-card{background:var(--ub-card);border-radius:18px;border:1px solid var(--ub-card-border);box-shadow:0 8px 32px rgba(0,0,0,0.18);overflow:hidden;transition:transform 0.3s,box-shadow 0.3s;}
.glass-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,0.22);}
.section-heading{color:var(--ub-section-color);font-size:1rem;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.section-heading i{color:#e74c3c;}
.user-nav a,.user-nav button{font-size:0.8rem;border-radius:20px;padding:7px 14px;font-weight:600;text-decoration:none;cursor:pointer;transition:all 0.2s;border:1.5px solid rgba(255,255,255,0.35);background:rgba(255,255,255,0.1);color:white;display:inline-flex;align-items:center;gap:5px;}
.user-nav a:hover,.user-nav button:hover{background:rgba(255,255,255,0.2);color:white;}
.nav-btn-red{background:#c0392b!important;border-color:#c0392b!important;}

/* Camp card */
.camp-card{background:var(--ub-card);border-radius:18px;padding:20px 22px;margin-bottom:14px;border:1px solid var(--ub-card-border);box-shadow:0 4px 16px rgba(0,0,0,0.08);transition:all 0.3s ease;position:relative;overflow:hidden;}
.camp-card:hover{transform:translateY(-4px);box-shadow:0 10px 32px rgba(192,57,43,0.15);border-color:#f5c6c3;}
.camp-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#c0392b,#e74c3c);}
.camp-badge{display:inline-flex;align-items:center;gap:5px;background:#fff0ee;color:#c0392b;border-radius:20px;padding:4px 12px;font-size:0.75rem;font-weight:600;margin:3px;}
.camp-date-box{background:linear-gradient(135deg,#c0392b,#e74c3c);color:white;border-radius:12px;padding:10px 14px;text-align:center;min-width:64px;flex-shrink:0;}
.camp-date-box .day{font-size:1.6rem;font-weight:800;line-height:1;}
.camp-date-box .month{font-size:0.72rem;font-weight:600;opacity:0.9;}
.days-left{font-size:0.72rem;font-weight:700;padding:3px 10px;border-radius:20px;}
.days-left.soon{background:#fff0ee;color:#c0392b;}
.days-left.upcoming{background:#e8f8f0;color:#155c2e;}
.filter-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;border:1.5px solid var(--ub-card-border);background:var(--ub-card);color:var(--ub-text);font-size:0.8rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all 0.2s;}
.filter-chip:hover,.filter-chip.active{background:#c0392b;border-color:#c0392b;color:white;}
</style>


<div class="ub-wrap">


    <!-- Page header -->
    <div class="mb-4" style="color:var(--ub-text);">
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:4px;">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i>Blood Donation Camps
        </h1>
        <p style="opacity:0.8;font-size:0.88rem;margin:0;">Upcoming camps across Bihar — donate blood, save lives.</p>
    </div>

    <!-- City filter chips -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="camps.php" class="filter-chip <?= !$city_filter?'active':'' ?>">🗺 All Bihar</a>
        <?php $cities->data_seek(0); while ($c = $cities->fetch_assoc()): ?>
        <a href="camps.php?city=<?= urlencode($c['city']) ?>" class="filter-chip <?= $city_filter===$c['city']?'active':'' ?>">
            <?= htmlspecialchars($c['city']) ?>
        </a>
        <?php endwhile; ?>
    </div>

    <!-- Upcoming camps -->
    <div class="section-heading"><i class="bi bi-calendar-event-fill"></i>Upcoming Camps (<?= $camps->num_rows ?>)</div>

    <?php if ($camps->num_rows === 0): ?>
    <div class="camp-card text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
        <div class="fw-semibold text-muted">No upcoming camps found<?= $city_filter ? " in $city_filter" : '' ?>.</div>
        <?php if ($city_filter): ?>
        <a href="camps.php" class="btn btn-outline-danger btn-sm mt-3">View All Cities</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <?php while ($camp = $camps->fetch_assoc()):
        $days_left = (int)ceil((strtotime($camp['date']) - time()) / 86400);
        $urgency_cls = $days_left <= 3 ? 'soon' : 'upcoming';
    ?>
    <div class="camp-card">
        <div class="d-flex gap-3 align-items-start flex-wrap">
            <!-- Date box -->
            <div class="camp-date-box">
                <div class="day"><?= date('d', strtotime($camp['date'])) ?></div>
                <div class="month"><?= date('M Y', strtotime($camp['date'])) ?></div>
            </div>
            <!-- Details -->
            <div class="flex-grow-1">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-1">
                    <div class="fw-bold" style="font-size:1rem;"><?= htmlspecialchars($camp['camp_name']) ?></div>
                    <span class="days-left <?= $urgency_cls ?>">
                        <?= $days_left === 0 ? '🔴 Today!' : ($days_left === 1 ? '🔴 Tomorrow!' : "📅 In $days_left days") ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="camp-badge"><i class="bi bi-geo-alt-fill"></i><?= htmlspecialchars($camp['location']) ?></span>
                    <span class="camp-badge"><i class="bi bi-building"></i><?= htmlspecialchars($camp['city']) ?><?= $camp['district'] ? ', '.$camp['district'] : '' ?></span>
                    <span class="camp-badge"><i class="bi bi-clock-fill"></i><?= date('h:i A', strtotime($camp['start_time'])) ?><?= $camp['end_time'] ? ' – '.date('h:i A', strtotime($camp['end_time'])) : '' ?></span>
                </div>
                <div class="d-flex flex-wrap gap-3" style="font-size:0.82rem;color:#6c757d;">
                    <?php if ($camp['organizer']): ?>
                    <span><i class="bi bi-people-fill text-danger me-1"></i><?= htmlspecialchars($camp['organizer']) ?></span>
                    <?php endif; ?>
                    <?php if ($camp['contact_name']): ?>
                    <span><i class="bi bi-person-fill text-danger me-1"></i><?= htmlspecialchars($camp['contact_name']) ?></span>
                    <?php endif; ?>
                    <?php if ($camp['contact_phone']): ?>
                    <a href="tel:<?= htmlspecialchars($camp['contact_phone']) ?>" style="color:#c0392b;text-decoration:none;font-weight:600;">
                        <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($camp['contact_phone']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($camp['contact_email']): ?>
                    <a href="mailto:<?= htmlspecialchars($camp['contact_email']) ?>" style="color:#c0392b;text-decoration:none;">
                        <i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($camp['contact_email']) ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php if ($camp['description']): ?>
                <div class="mt-2 small text-muted"><?= htmlspecialchars($camp['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; endif; ?>

    <!-- Past camps -->
    <?php if ($past->num_rows > 0): ?>
    <div class="section-heading mt-4"><i class="bi bi-clock-history"></i>Past Camps</div>
    <?php while ($camp = $past->fetch_assoc()): ?>
    <div class="camp-card" style="opacity:0.65;">
        <div class="d-flex gap-3 align-items-center flex-wrap">
            <div class="camp-date-box" style="background:linear-gradient(135deg,#6c757d,#adb5bd);">
                <div class="day"><?= date('d', strtotime($camp['date'])) ?></div>
                <div class="month"><?= date('M Y', strtotime($camp['date'])) ?></div>
            </div>
            <div>
                <div class="fw-bold"><?= htmlspecialchars($camp['camp_name']) ?></div>
                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($camp['city']) ?> | <i class="bi bi-people me-1"></i><?= htmlspecialchars($camp['organizer'] ?? '—') ?></div>
            </div>
            <span class="ms-auto badge bg-secondary">Completed</span>
        </div>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>

    <!-- Info box -->
    <div class="glass-card mt-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-3">
                <div style="font-size:2rem;flex-shrink:0;">💡</div>
                <div>
                    <div class="fw-bold text-danger mb-1">Why Donate Blood?</div>
                    <div class="text-muted small" style="line-height:1.7;">
                        One blood donation can save up to <strong>3 lives</strong>. Blood cannot be manufactured — it can only come from donors like you.
                        Every 2 seconds, someone in India needs blood. Be the reason someone survives today.
                        For any camp-related queries, contact us at <a href="/blood_bank/user/support.php" class="text-danger">Support →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

