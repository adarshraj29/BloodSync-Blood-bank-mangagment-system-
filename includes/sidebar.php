<?php
$current = basename($_SERVER['PHP_SELF']);

function navItem($href, $icon, $label, $current, $badge = '') {
    $file   = basename($href);
    $active = ($file === $current) ? 'active' : '';
    $b      = $badge ? "<span class='badge bg-danger ms-auto' style='font-size:10px;'>$badge</span>" : '';
    return "<a href='/blood_bank/$href' class='nav-link $active d-flex align-items-center'>
        <i class='bi bi-$icon me-2'></i>$label $b</a>";
}
function navSection($label) {
    return "<div class='sidebar-section'>$label</div>";
}

// Count open emergency requests for badge
global $conn;
$emerg_count = 0;
$notif_count = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $ec = $conn->query("SELECT COUNT(*) as c FROM emergency_requests WHERE status='Open'");
    if ($ec) $emerg_count = (int)$ec->fetch_assoc()['c'];
    $nc = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id=? AND is_read=0");
    if ($nc) { $nc->bind_param("i", $_SESSION['user_id']); $nc->execute(); $notif_count = (int)$nc->get_result()->fetch_assoc()['c']; }
}
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-droplet-fill text-danger me-2"></i>
        <span class="fw-bold">BloodBank Admin</span>
    </div>
    <nav class="sidebar-nav">
        <?= navItem('admin/dashboard.php','speedometer2','Dashboard', $current) ?>

        <?= navSection('DONORS') ?>
        <?= navItem('admin/donors.php','people','All Donors', $current) ?>
        <?= navItem('admin/add_donor.php','person-plus','Add Donor', $current) ?>
        <?= navItem('admin/smart_match.php','stars','Smart Match', $current) ?>
        <?= navItem('admin/location_search.php','geo-alt','Location Search', $current) ?>
        <?= navItem('admin/donation_log.php','journal-text','Donation Log', $current) ?>

        <?= navSection('APPOINTMENTS') ?>
        <?= navItem('admin/appointments.php','calendar-check','Appointments', $current) ?>

        <?= navSection('BLOOD & REQUESTS') ?>
        <?= navItem('admin/blood_stock.php','droplet-half','Blood Stock', $current) ?>
        <?= navItem('admin/requests.php','clipboard-plus','Blood Requests', $current) ?>
        <?= navItem('admin/emergency.php','exclamation-octagon-fill','Emergency', $current, $emerg_count ?: '') ?>
        <?= navItem('admin/hospitals.php','hospital','Hospitals', $current) ?>

        <?= navSection('ANALYTICS') ?>
        <?= navItem('admin/reports.php','bar-chart-line','Reports', $current) ?>
        <?= navItem('admin/monthly_report.php','calendar-month','Monthly Report', $current) ?>

        <?= navSection('SYSTEM') ?>
        <?= navItem('admin/notifications.php','bell','Notifications', $current, $notif_count ?: '') ?>
        <?= navItem('admin/manage_users.php','people-fill','Manage Users', $current) ?>
        <?= navItem('admin/activity_log.php','journal-text','Activity Log', $current) ?>
        <?= navItem('admin/admin_profile.php','person-circle','My Profile', $current) ?>
    </nav>
</div>
