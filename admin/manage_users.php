<?php
// ============================================================
// BloodSync - Manage Users (Admin)
// Block/Unblock with fixed DB column checks
// ============================================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
if (file_exists(__DIR__ . '/../includes/activity.php'))
    require_once __DIR__ . '/../includes/activity.php';
else {
    function logActivity($c,$uid,$name,$action,$detail=''){}
}
$page_title = 'Manage Users';

// ── Ensure is_blocked column exists ────────────────────────
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_blocked TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS block_reason VARCHAR(300) DEFAULT NULL");

// ── BLOCK user ─────────────────────────────────────────────
if (isset($_GET['block'])) {
    $id     = (int)$_GET['block'];
    $reason = clean($conn, urldecode($_GET['reason'] ?? 'Blocked by admin.'));

    // Safety: never block admin accounts
    $chk = $conn->query("SELECT role FROM users WHERE id=$id")->fetch_assoc();
    if ($chk && $chk['role'] !== 'admin') {
        $conn->query("UPDATE users SET is_blocked=1, block_reason='$reason' WHERE id=$id");
        $uname = $conn->query("SELECT name FROM users WHERE id=$id")->fetch_assoc()['name'] ?? 'User';
        logActivity($conn, $_SESSION['user_id'], $_SESSION['name'], 'User Blocked', "Blocked: $uname (ID:$id). Reason: $reason");
        $conn->query("INSERT INTO notifications (user_id,title,message,type)
            VALUES ($id,'Account Blocked','Your account has been blocked by admin. Please contact support.','danger')");
        redirect('/blood_bank/admin/manage_users.php?msg=blocked');
    }
    redirect('/blood_bank/admin/manage_users.php?err=cannot_block_admin');
}

// ── UNBLOCK user ────────────────────────────────────────────
if (isset($_GET['unblock'])) {
    $id = (int)$_GET['unblock'];
    $conn->query("UPDATE users SET is_blocked=0, block_reason=NULL WHERE id=$id");
    $uname = $conn->query("SELECT name FROM users WHERE id=$id")->fetch_assoc()['name'] ?? 'User';
    logActivity($conn, $_SESSION['user_id'], $_SESSION['name'], 'User Unblocked', "Unblocked: $uname (ID:$id)");
    $conn->query("INSERT INTO notifications (user_id,title,message,type)
        VALUES ($id,'Account Unblocked ✓','Your account has been unblocked. You can now login.','success')");
    redirect('/blood_bank/admin/manage_users.php?msg=unblocked');
}

// ── Filters ─────────────────────────────────────────────────
$search = isset($_GET['search']) ? trim(clean($conn, $_GET['search'])) : '';
$filter = isset($_GET['filter']) ? trim(clean($conn, $_GET['filter'])) : '';

$where = "WHERE role != 'admin'";
if ($search !== '')        $where .= " AND (name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
if ($filter === 'blocked')  $where .= " AND is_blocked=1";
if ($filter === 'active')   $where .= " AND (is_blocked=0 OR is_blocked IS NULL)";
if ($filter === 'donor')    $where .= " AND role_type='donor'";
if ($filter === 'receiver') $where .= " AND role_type='receiver'";

$users     = $conn->query("SELECT * FROM users $where ORDER BY created_at DESC");
$total_all = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE role!='admin'")->fetch_assoc()['c'];
$total_blocked = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND is_blocked=1")->fetch_assoc()['c'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title mb-0">
            <i class="bi bi-people-fill me-2 text-danger"></i>Manage Users
        </h1>
        <div class="d-flex gap-2">
            <span class="badge bg-danger py-2 px-3"><?= $total_all ?> Total</span>
            <?php if ($total_blocked > 0): ?>
            <span class="badge bg-warning text-dark py-2 px-3"><?= $total_blocked ?> Blocked</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-<?= $_GET['msg']==='blocked'?'warning':'success' ?> alert-dismissible mb-3">
        <i class="bi bi-<?= $_GET['msg']==='blocked'?'lock-fill':'unlock-fill' ?> me-2"></i>
        <?= $_GET['msg']==='blocked' ? 'User has been blocked successfully. They cannot login now.' : 'User has been unblocked. They can login again.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['err'])): ?>
    <div class="alert alert-danger alert-dismissible mb-3">
        <i class="bi bi-x-circle me-2"></i>Cannot block admin accounts.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name or email..."
                            value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="filter" class="form-select form-select-sm">
                        <option value=""   <?= $filter===''        ?'selected':'' ?>>All Users</option>
                        <option value="active"   <?= $filter==='active'  ?'selected':'' ?>>Active Only</option>
                        <option value="blocked"  <?= $filter==='blocked' ?'selected':'' ?>>Blocked Only</option>
                        <option value="donor"    <?= $filter==='donor'   ?'selected':'' ?>>Donors</option>
                        <option value="receiver" <?= $filter==='receiver'?'selected':'' ?>>Receivers</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="/blood_bank/admin/manage_users.php" class="btn btn-outline-secondary btn-sm ms-1">
                        Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users table -->
    <div class="card">
        <div class="card-body p-0">
            <div style="overflow-x:auto;">
            <table class="table mb-0" style="min-width:750px;">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users->num_rows === 0): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <i class="bi bi-people fs-2 d-block mb-2" style="color:var(--aMuted);"></i>
                        <span>No users found<?= ($search||$filter) ? ' matching your filters' : '' ?>.</span>
                        <?php if ($search || $filter): ?>
                        <br><a href="/blood_bank/admin/manage_users.php" class="btn btn-sm btn-outline-danger mt-2">Clear Filters</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else:
                    $i = 1;
                    while ($u = $users->fetch_assoc()):
                        $isBlocked = !empty($u['is_blocked']);
                        $roleType  = $u['role_type'] ?? $u['role'];
                        $roleBadge = match($roleType) {
                            'donor'    => ['danger',  'Donor'],
                            'receiver' => ['primary', 'Receiver'],
                            default    => ['secondary','User'],
                        };
                ?>
                <tr style="<?= $isBlocked ? 'opacity:0.72;' : '' ?>">
                    <td class="ps-4"><?= $i++ ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <!-- Avatar -->
                            <?php if (!empty($u['profile_pic']) && file_exists(__DIR__.'/../assets/uploads/profiles/'.$u['profile_pic'])): ?>
                            <img src="/blood_bank/assets/uploads/profiles/<?= htmlspecialchars($u['profile_pic']) ?>"
                                 style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid <?= $isBlocked?'#dc3545':'#c0392b' ?>;">
                            <?php else: ?>
                            <div style="width:34px;height:34px;border-radius:50%;background:<?= $isBlocked?'#6c757d':'linear-gradient(135deg,#c0392b,#e74c3c)' ?>;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:800;color:white;flex-shrink:0;">
                                <?= strtoupper(substr($u['name'],0,1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($u['name']) ?></div>
                                <?php if ($isBlocked && !empty($u['block_reason'])): ?>
                                <div style="font-size:0.68rem;color:#dc3545;">
                                    <i class="bi bi-lock-fill me-1"></i><?= htmlspecialchars(substr($u['block_reason'],0,35)) ?>...
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="small" style="color:var(--aMuted);"><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge bg-<?= $roleBadge[0] ?>">
                            <?= $roleBadge[1] ?>
                        </span>
                    </td>
                    <td class="small"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                    <td>
                        <?php if ($isBlocked): ?>
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Blocked</span>
                        <?php else: ?>
                        <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="small" style="color:var(--aMuted);"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($isBlocked): ?>
                        <a href="?unblock=<?= $u['id'] ?>"
                           class="btn btn-sm btn-success fw-semibold"
                           onclick="return confirm('Unblock <?= htmlspecialchars(addslashes($u['name'])) ?>? They will be able to login again.')">
                            <i class="bi bi-unlock-fill me-1"></i>Unblock
                        </a>
                        <?php else: ?>
                        <button class="btn btn-sm btn-outline-danger fw-semibold"
                            onclick="showBlockModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                            <i class="bi bi-lock-fill me-1"></i>Block
                        </button>
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
</div>

<!-- Block confirmation modal (uses admin_header's .adm-backdrop style) -->
<div class="adm-backdrop" id="blockModal" onclick="if(event.target===this)closeBlockModal()">
    <div class="adm-modal">
        <div style="font-size:2.8rem;margin-bottom:12px;">🔒</div>
        <h5 class="fw-bold mb-2">Block User</h5>
        <p id="blockModalSubtitle" style="color:var(--aMuted);font-size:0.88rem;margin-bottom:16px;"></p>
        <div class="mb-3 text-start">
            <label class="form-label fw-semibold small">Reason <span style="color:var(--aMuted);">(optional)</span></label>
            <textarea id="blockReason" class="form-control" rows="2"
                placeholder="e.g. Suspicious activity, Policy violation..."></textarea>
        </div>
        <div class="d-flex gap-3 justify-content-center">
            <a id="blockConfirmLink" href="#" class="btn btn-danger px-4 fw-semibold">
                <i class="bi bi-lock-fill me-2"></i>Yes, Block User
            </a>
            <button onclick="closeBlockModal()" class="btn btn-outline-secondary px-4 fw-semibold">
                Cancel
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
function showBlockModal(id, name) {
    document.getElementById('blockModalSubtitle').textContent = 'You are about to block: ' + name;
    document.getElementById('blockReason').value = '';
    document.getElementById('blockModal').classList.add('show');

    document.getElementById('blockConfirmLink').onclick = function(e) {
        e.preventDefault();
        const reason = encodeURIComponent(
            document.getElementById('blockReason').value.trim() || 'Blocked by admin.'
        );
        window.location.href = '/blood_bank/admin/manage_users.php?block=' + id + '&reason=' + reason;
    };
}
function closeBlockModal() {
    document.getElementById('blockModal').classList.remove('show');
}
</script>
