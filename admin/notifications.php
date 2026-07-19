<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Notifications';

$uid = $_SESSION['user_id'];

// Mark all read
if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$uid");
    redirect('/blood_bank/admin/notifications.php?msg=read');
}

// Mark single read
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$id AND user_id=$uid");
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM notifications WHERE id=$id AND user_id=$uid");
    redirect('/blood_bank/admin/notifications.php');
}

$notifs = $conn->query("SELECT * FROM notifications WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50");
$unread = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0")->fetch_assoc()['c'];
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">
            <i class="bi bi-bell me-2 text-danger"></i>Notifications
            <?php if ($unread > 0): ?>
            <span class="badge bg-danger ms-2"><?= $unread ?> new</span>
            <?php endif; ?>
        </h1>
        <?php if ($unread > 0): ?>
        <a href="?mark_all=1" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check2-all me-1"></i>Mark all as read
        </a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible">All notifications marked as read.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($notifs->num_rows === 0): ?>
    <div class="card text-center py-5">
        <i class="bi bi-bell-slash fs-1 text-muted d-block mb-3"></i>
        <p class="text-muted">No notifications yet. They will appear here when events happen.</p>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <?php while ($n = $notifs->fetch_assoc()):
                $icon  = ['info'=>'info-circle','success'=>'check-circle-fill','warning'=>'exclamation-triangle-fill','danger'=>'exclamation-octagon-fill'][$n['type']];
                $color = ['info'=>'#0d6efd','success'=>'#198754','warning'=>'#ffa000','danger'=>'#dc3545'][$n['type']];
            ?>
            <div class="d-flex align-items-start gap-3 p-3 border-bottom <?= !$n['is_read']?'bg-light':'' ?>"
                 style="<?= !$n['is_read']?'border-left:3px solid '.$color.'!important':'' ?>">
                <div style="width:36px;height:36px;border-radius:50%;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                    <i class="bi bi-<?= $icon ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="fw-semibold <?= !$n['is_read']?'text-dark':'text-muted' ?>" style="font-size:13px;">
                            <?= htmlspecialchars($n['title']) ?>
                            <?php if (!$n['is_read']): ?>
                            <span class="badge bg-danger ms-2" style="font-size:9px;">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small ms-3 flex-shrink-0">
                            <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                    <div class="text-muted small mt-1"><?= htmlspecialchars($n['message']) ?></div>
                </div>
                <div class="d-flex gap-1 flex-shrink-0">
                    <?php if (!$n['is_read']): ?>
                    <a href="?read=<?= $n['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Mark read">
                        <i class="bi bi-check2"></i>
                    </a>
                    <?php endif; ?>
                    <a href="?delete=<?= $n['id'] ?>" class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Delete this notification?')" title="Delete">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
