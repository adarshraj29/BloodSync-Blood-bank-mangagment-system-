<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Activity Log';

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, user_name VARCHAR(100),
    action VARCHAR(200) NOT NULL, detail TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id), INDEX idx_created (created_at)
)");

// ── Filters (all work independently and together) ─────────
$filter_user   = isset($_GET['user'])   ? trim(clean($conn, $_GET['user']))   : '';
$filter_action = isset($_GET['action']) ? trim(clean($conn, $_GET['action'])) : '';
$date_from     = isset($_GET['from'])   ? trim(clean($conn, $_GET['from']))   : '';
$date_to       = isset($_GET['to'])     ? trim(clean($conn, $_GET['to']))     : '';
$per_page      = 50;
$page_num      = max(1, (int)($_GET['pg'] ?? 1));
$offset        = ($page_num - 1) * $per_page;

// Only show logs for users who belong to THIS blood bank (not external)
// i.e. user_id must exist in our users table OR user_id is NULL (system log)
$conditions = ["(al.user_id IN (SELECT id FROM users) OR al.user_id IS NULL)"];

if ($filter_user !== '')
    $conditions[] = "(al.user_name LIKE '%$filter_user%' OR al.user_id IN (SELECT id FROM users WHERE name LIKE '%$filter_user%' OR email LIKE '%$filter_user%'))";

if ($filter_action !== '')
    $conditions[] = "al.action LIKE '%$filter_action%'";

if ($date_from !== '')
    $conditions[] = "DATE(al.created_at) >= '$date_from'";

if ($date_to !== '')
    $conditions[] = "DATE(al.created_at) <= '$date_to'";

$where = "WHERE " . implode(" AND ", $conditions);

// Total count for pagination
$total_count = (int)$conn->query("SELECT COUNT(*) as c FROM activity_log al $where")->fetch_assoc()['c'];
$total_pages = max(1, ceil($total_count / $per_page));

// Fetch logs with user join for extra info
$logs = $conn->query("
    SELECT al.*, u.email as user_email, u.role_type as user_role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    $where
    ORDER BY al.created_at DESC
    LIMIT $per_page OFFSET $offset
");

// Distinct actions (from THIS blood bank users only)
$distinct_actions = $conn->query("
    SELECT DISTINCT action FROM activity_log al
    WHERE al.user_id IN (SELECT id FROM users) OR al.user_id IS NULL
    ORDER BY action
");

function actionMeta($action) {
    $a = strtolower($action);
    if (str_contains($a,'login'))      return ['bi-box-arrow-in-right','#0d6efd','Login'];
    if (str_contains($a,'logout'))     return ['bi-box-arrow-right','#6c757d','Logout'];
    if (str_contains($a,'block'))      return ['bi-lock-fill','#dc3545','Block'];
    if (str_contains($a,'unblock'))    return ['bi-unlock-fill','#198754','Unblock'];
    if (str_contains($a,'donation'))   return ['bi-droplet-fill','#dc3545','Donation'];
    if (str_contains($a,'request'))    return ['bi-clipboard-plus','#0d6efd','Request'];
    if (str_contains($a,'profile'))    return ['bi-person-fill','#6f42c1','Profile'];
    if (str_contains($a,'password'))   return ['bi-shield-lock-fill','#fd7e14','Password'];
    if (str_contains($a,'appoint'))    return ['bi-calendar-check-fill','#20c997','Appointment'];
    if (str_contains($a,'approved'))   return ['bi-check-circle-fill','#198754','Approved'];
    if (str_contains($a,'rejected'))   return ['bi-x-circle-fill','#dc3545','Rejected'];
    if (str_contains($a,'emergency'))  return ['bi-exclamation-triangle-fill','#dc3545','Emergency'];
    if (str_contains($a,'stock'))      return ['bi-droplet-half','#0d6efd','Stock'];
    return ['bi-activity','#6c757d','Activity'];
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title mb-0">
            <i class="bi bi-journal-text me-2 text-danger"></i>User Activity Log
        </h1>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-danger"><?= number_format($total_count) ?> records</span>
            <?php if ($filter_user || $filter_action || $date_from || $date_to): ?>
            <span class="badge bg-warning text-dark">Filtered</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filter Form ──────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header py-3 px-4 fw-semibold">
            <i class="bi bi-funnel-fill me-2 text-danger"></i>Filter Activity Log
        </div>
        <div class="card-body py-3">
            <form method="GET" id="filterForm" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">User Name / Email</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="user" class="form-control"
                            placeholder="Search user..."
                            value="<?= htmlspecialchars($filter_user) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Action Type</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">— All Actions —</option>
                        <?php if ($distinct_actions): while ($a = $distinct_actions->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($a['action']) ?>"
                            <?= $filter_action === $a['action'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['action']) ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">From Date</label>
                    <input type="date" name="from" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">To Date</label>
                    <input type="date" name="to" class="form-control form-control-sm"
                        value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i>Apply Filter
                    </button>
                    <a href="/blood_bank/admin/activity_log.php" class="btn btn-outline-secondary btn-sm w-100 mt-1">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Results ──────────────────────────────────────── -->
    <?php if ($filter_user || $filter_action || $date_from || $date_to): ?>
    <div class="alert alert-info mb-3 py-2 small">
        <i class="bi bi-info-circle me-2"></i>
        Showing filtered results:
        <?php if ($filter_user): ?><strong>User:</strong> "<?= htmlspecialchars($filter_user) ?>" <?php endif; ?>
        <?php if ($filter_action): ?><strong>Action:</strong> "<?= htmlspecialchars($filter_action) ?>" <?php endif; ?>
        <?php if ($date_from): ?><strong>From:</strong> <?= htmlspecialchars($date_from) ?> <?php endif; ?>
        <?php if ($date_to): ?><strong>To:</strong> <?= htmlspecialchars($date_to) ?> <?php endif; ?>
        — Found <strong><?= $total_count ?></strong> record(s).
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead><tr>
                    <th class="ps-4" style="width:40px;">#</th>
                    <th style="width:160px;">Action</th>
                    <th style="width:160px;">User</th>
                    <th>Detail</th>
                    <th style="width:100px;">IP</th>
                    <th style="width:150px;">Date & Time</th>
                </tr></thead>
                <tbody>
                <?php if (!$logs || $logs->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center py-5">
                    <i class="bi bi-journal-x fs-2 d-block mb-2 text-muted"></i>
                    <span class="text-muted">No activity logs found<?= ($filter_user||$filter_action) ? ' for these filters' : '' ?>.</span>
                    <?php if ($filter_user || $filter_action || $date_from || $date_to): ?>
                    <br><a href="/blood_bank/admin/activity_log.php" class="btn btn-sm btn-outline-danger mt-2">Clear filters</a>
                    <?php endif; ?>
                </td></tr>
                <?php else: $i = ($page_num-1)*$per_page+1; while ($log = $logs->fetch_assoc()):
                    [$icon,$color,$label] = actionMeta($log['action']);
                ?>
                <tr>
                    <td class="ps-4 text-muted small"><?= $i++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:28px;height:28px;border-radius:8px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi bi-<?= $icon ?>" style="color:<?= $color ?>;font-size:0.8rem;"></i>
                            </div>
                            <span class="fw-semibold small"><?= htmlspecialchars($log['action']) ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($log['user_name'] ?? '—') ?></div>
                        <div class="text-muted" style="font-size:0.7rem;">
                            <?= htmlspecialchars($log['user_email'] ?? '') ?>
                            <?php if (!empty($log['user_role'])): ?>
                            <span class="badge bg-secondary ms-1" style="font-size:9px;"><?= ucfirst($log['user_role']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="small" style="max-width:240px;word-break:break-word;">
                        <?= htmlspecialchars(substr($log['detail'] ?? '—', 0, 80)) ?><?= strlen($log['detail']??'')>80?'...':'' ?>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                    <td class="small text-muted"><?= date('d M Y<br>h:i A', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer py-3 d-flex justify-content-between align-items-center">
            <span class="small text-muted">
                Page <?= $page_num ?> of <?= $total_pages ?>
                (<?= number_format($total_count) ?> total)
            </span>
            <div class="d-flex gap-1">
                <?php
                $qs = http_build_query(['user'=>$filter_user,'action'=>$filter_action,'from'=>$date_from,'to'=>$date_to]);
                for ($p=max(1,$page_num-2); $p<=min($total_pages,$page_num+2); $p++): ?>
                <a href="?<?= $qs ?>&pg=<?= $p ?>"
                   class="btn btn-sm <?= $p===$page_num?'btn-danger':'btn-outline-secondary' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
