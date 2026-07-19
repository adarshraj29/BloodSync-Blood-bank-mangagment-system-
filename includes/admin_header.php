<?php
// ============================================================
// BloodSync Admin Header v6 - Full fix
// Background, Dark/Light mode, Profile dropdown, Logout modal
// ============================================================
if (!file_exists(__DIR__ . '/activity.php')) {
    function logActivity($c,$uid,$name,$action,$detail=''){}
    function sendSMS($c,$uid,$phone,$msg){ return true; }
} else {
    require_once __DIR__ . '/activity.php';
}
$_admin_uid    = (int)($_SESSION['user_id'] ?? 0);
$_admin_user   = $conn->query("SELECT * FROM users WHERE id=$_admin_uid")->fetch_assoc();
$_admin_unread = (int)$conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$_admin_uid AND is_read=0")->fetch_assoc()['c'];
$_curr_page    = basename($_SERVER['PHP_SELF']);
$_notif_prev   = $conn->query("SELECT * FROM notifications WHERE user_id=$_admin_uid AND is_read=0 ORDER BY created_at DESC LIMIT 3");
$_has_pic      = !empty($_admin_user['profile_pic']) && file_exists(__DIR__.'/../assets/uploads/profiles/'.($_admin_user['profile_pic']??''));
$_pic_url      = $_has_pic ? '/blood_bank/assets/uploads/profiles/'.htmlspecialchars($_admin_user['profile_pic']) : '';
$_init         = strtoupper(substr($_admin_user['name']??'A',0,1));
$_first_name   = htmlspecialchars(explode(' ',$_admin_user['name']??'Admin')[0]);
?>
<style>
/* ════════════════════════════════════════════════════════════
   ADMIN PANEL CSS VARIABLES
════════════════════════════════════════════════════════════ */
:root {
    --aRed:#c0392b; --aRedL:#e74c3c; --aRedD:#96281b;
    /* Light (default) */
    --aBg:#f5f5f5; --aCard:#ffffff; --aBorder:#e8ecef;
    --aText:#212529; --aMuted:#6c757d; --aHeading:#2c3e50;
    --aSidebarBg:#ffffff; --aSidebarBorder:#e3e6ea;
    --aSidebarText:#495057; --aSidebarHover:#fff5f4;
    --aSidebarActive:#dc3545; --aSidebarActiveText:#fff;
    --aSectionLabel:#adb5bd;
    --aNavBg:linear-gradient(135deg,#96281b 0%,#c0392b 100%);
    --aNavText:#ffffff;
    --aInputBg:#ffffff; --aInputBorder:#ced4da; --aInputText:#212529;
    --aTableHead:#f8f9fa; --aTableHover:#fff9f9;
    --aBadgeBg:#fff0f0; --aBadgeText:#c0392b;
    --aStatRedBg:#fff0ee; --aStatGreenBg:#e8f8f0;
    --aStatBlueBg:#e8f0ff; --aStatOrangeBg:#fff5e8;
    --aAlertDanger:#fff0ee; --aAlertDangerText:#7b1f1f;
    --aAlertSuccess:#e8f8f0; --aAlertSuccessText:#155c2e;
    --aAlertWarning:#fff8e6; --aAlertWarningText:#7c5300;
    --aAlertInfo:#e8f4ff; --aAlertInfoText:#0c4a6e;
    --aProgress:#f1f3f4;
    --aBgImgOpacity:0.2;
    --aOverlay:rgba(245,245,245,0.75);
}
body.adm-dark {
    --aBg:#0d1117; --aCard:#161b22; --aBorder:#30363d;
    --aText:#e6edf3; --aMuted:#8b949e; --aHeading:#e6edf3;
    --aSidebarBg:#161b22; --aSidebarBorder:#30363d;
    --aSidebarText:#c9d1d9; --aSidebarHover:rgba(192,57,43,0.15);
    --aSidebarActive:#c0392b; --aSidebarActiveText:#ffffff;
    --aSectionLabel:#484f58;
    --aNavBg:linear-gradient(135deg,#1a0808 0%,#2d1010 100%);
    --aNavText:#e6edf3;
    --aInputBg:#21262d; --aInputBorder:#30363d; --aInputText:#e6edf3;
    --aTableHead:#21262d; --aTableHover:rgba(192,57,43,0.08);
    --aBadgeBg:rgba(192,57,43,0.2); --aBadgeText:#ff9999;
    --aStatRedBg:rgba(192,57,43,0.18); --aStatGreenBg:rgba(25,135,84,0.18);
    --aStatBlueBg:rgba(13,110,253,0.18); --aStatOrangeBg:rgba(253,126,20,0.18);
    --aAlertDanger:rgba(192,57,43,0.2); --aAlertDangerText:#ffaaaa;
    --aAlertSuccess:rgba(25,135,84,0.2); --aAlertSuccessText:#aaffcc;
    --aAlertWarning:rgba(255,193,7,0.15); --aAlertWarningText:#ffe066;
    --aAlertInfo:rgba(13,110,253,0.15); --aAlertInfoText:#aad4ff;
    --aProgress:#30363d;
    --aBgImgOpacity:0.25;
    --aOverlay:rgba(13,17,23,0.8);
}

/* ── Background image (fixed, behind everything) ─────────── */
body.admin-page::before {
    content:'';
    position:fixed; inset:0; z-index:-2;
    background:url('/blood_bank/assets/admin_bg.avif') center/cover no-repeat;
    opacity:var(--aBgImgOpacity);
    transition:opacity 0.5s ease;
    pointer-events:none;
}
body.admin-page::after {
    content:'';
    position:fixed; inset:0; z-index:-1;
    background:var(--aOverlay);
    transition:background 0.4s ease;
    pointer-events:none;
}

/* ── Global transitions ───────────────────────────────────── */
body.admin-page {
    background:var(--aBg) !important;
    color:var(--aText) !important;
    transition:background 0.4s ease, color 0.3s ease;
}
body.admin-page *:not(script):not(style) {
    transition:
        background-color 0.3s ease,
        border-color 0.3s ease,
        color 0.3s ease,
        box-shadow 0.3s ease;
}

/* ── Navbar ───────────────────────────────────────────────── */
body.admin-page .navbar {
    background:var(--aNavBg) !important;
    box-shadow:0 2px 18px rgba(0,0,0,0.3) !important;
}
body.admin-page .navbar-brand { color:var(--aNavText) !important; font-weight:700; }
body.admin-page .brand-sync   { color:#ffd0cc !important; }
body.adm-dark .brand-sync     { color:#ff9999 !important; }

/* ── Sidebar ──────────────────────────────────────────────── */
body.admin-page .sidebar {
    background:var(--aSidebarBg) !important;
    border-right:1px solid var(--aSidebarBorder) !important;
    box-shadow:2px 0 16px rgba(0,0,0,0.12) !important;
}
body.admin-page .sidebar-brand {
    color:var(--aHeading) !important;
    border-bottom:1px solid var(--aSidebarBorder) !important;
}
body.admin-page .sidebar-section { color:var(--aSectionLabel) !important; }
body.admin-page .sidebar-nav .nav-link { color:var(--aSidebarText) !important; }
body.admin-page .sidebar-nav .nav-link:hover {
    background:var(--aSidebarHover) !important; color:#c0392b !important;
}
body.admin-page .sidebar-nav .nav-link.active {
    background:var(--aSidebarActive) !important;
    color:var(--aSidebarActiveText) !important;
    border-left-color:var(--aSidebarActive) !important;
}

/* ── Main content ─────────────────────────────────────────── */
body.admin-page .main-content { background:transparent !important; }
body.admin-page .page-title   { color:var(--aHeading) !important; }
body.admin-page h1,body.admin-page h2,body.admin-page h3,
body.admin-page h4,body.admin-page h5,body.admin-page h6 { color:var(--aHeading) !important; }
body.admin-page p,body.admin-page span,
body.admin-page td,body.admin-page label,
body.admin-page li { color:var(--aText) !important; }
body.admin-page .text-muted { color:var(--aMuted) !important; }
body.admin-page small { color:var(--aMuted) !important; }
body.admin-page strong { color:var(--aText) !important; }

/* ── Cards ────────────────────────────────────────────────── */
body.admin-page .card {
    background:var(--aCard) !important;
    border:1px solid var(--aBorder) !important;
    color:var(--aText) !important;
}
body.admin-page .card-header {
    background:var(--aCard) !important;
    border-bottom:1px solid var(--aBorder) !important;
    color:var(--aHeading) !important;
}
body.admin-page .card-body { color:var(--aText) !important; }

/* ── Stat cards ───────────────────────────────────────────── */
body.admin-page .stat-card { background:var(--aCard) !important; border-color:var(--aBorder) !important; }
body.admin-page .stat-value { color:var(--aHeading) !important; font-weight:700; }
body.admin-page .stat-label { color:var(--aMuted) !important; }
body.admin-page .stat-icon.red    { background:var(--aStatRedBg)    !important; color:#dc3545 !important; }
body.admin-page .stat-icon.green  { background:var(--aStatGreenBg)  !important; color:#198754 !important; }
body.admin-page .stat-icon.blue   { background:var(--aStatBlueBg)   !important; color:#0d6efd !important; }
body.admin-page .stat-icon.orange { background:var(--aStatOrangeBg) !important; color:#fd7e14 !important; }

/* ── Tables ───────────────────────────────────────────────── */
body.admin-page .table {
    color:var(--aText) !important;
    border-color:var(--aBorder) !important;
}
body.admin-page .table th {
    background:var(--aTableHead) !important;
    color:var(--aMuted) !important;
    border-bottom:2px solid var(--aBorder) !important;
    font-size:0.75rem; text-transform:uppercase; letter-spacing:.05em; font-weight:600;
}
body.admin-page .table td {
    color:var(--aText) !important;
    border-color:var(--aBorder) !important;
    vertical-align:middle;
}
body.admin-page .table tbody tr:hover { background:var(--aTableHover) !important; }

/* ── Forms ────────────────────────────────────────────────── */
body.admin-page .form-control,
body.admin-page .form-select,
body.admin-page textarea,
body.admin-page input[type="text"],
body.admin-page input[type="email"],
body.admin-page input[type="password"],
body.admin-page input[type="number"],
body.admin-page input[type="date"],
body.admin-page input[type="tel"] {
    background:var(--aInputBg) !important;
    border-color:var(--aInputBorder) !important;
    color:var(--aInputText) !important;
}
body.admin-page .form-control:focus,
body.admin-page .form-select:focus {
    border-color:#c0392b !important;
    box-shadow:0 0 0 3px rgba(192,57,43,0.15) !important;
    background:var(--aInputBg) !important;
    color:var(--aInputText) !important;
}
body.admin-page .input-group-text {
    background:var(--aTableHead) !important;
    border-color:var(--aInputBorder) !important;
    color:var(--aMuted) !important;
}
body.admin-page .form-label { color:var(--aHeading) !important; font-weight:600; }
body.admin-page .form-text  { color:var(--aMuted) !important; }
body.admin-page ::placeholder { color:var(--aMuted) !important; opacity:1 !important; }
body.adm-dark select option { background:#161b22 !important; color:#e6edf3 !important; }
body.adm-dark input[type="date"] { color-scheme:dark; }

/* ── Alerts ───────────────────────────────────────────────── */
body.admin-page .alert-danger  { background:var(--aAlertDanger)  !important; color:var(--aAlertDangerText)  !important; border:none !important; border-radius:10px !important; }
body.admin-page .alert-success { background:var(--aAlertSuccess) !important; color:var(--aAlertSuccessText) !important; border:none !important; border-radius:10px !important; }
body.admin-page .alert-warning { background:var(--aAlertWarning) !important; color:var(--aAlertWarningText) !important; border:none !important; border-radius:10px !important; }
body.admin-page .alert-info    { background:var(--aAlertInfo)    !important; color:var(--aAlertInfoText)    !important; border:none !important; border-radius:10px !important; }
body.admin-page .alert .btn-close { filter:var(--aText)==='#e6edf3' ? invert(1) : none; }
body.adm-dark .alert .btn-close { filter:invert(1); }

/* ── Badges ───────────────────────────────────────────────── */
body.admin-page .bg-badge { background:var(--aBadgeBg) !important; color:var(--aBadgeText) !important; border-color:var(--aBadgeBg) !important; }

/* ── Buttons (outline) ────────────────────────────────────── */
body.adm-dark .btn-outline-secondary { color:#8b949e !important; border-color:#30363d !important; }
body.adm-dark .btn-outline-primary   { color:#79a8ff !important; border-color:#79a8ff !important; }
body.adm-dark .btn-outline-success   { color:#56d364 !important; border-color:#56d364 !important; }
body.adm-dark .btn-outline-danger    { color:#ff7b7b !important; border-color:#ff7b7b !important; }
body.adm-dark .btn-light             { background:var(--aTableHead) !important; color:var(--aText) !important; border-color:var(--aBorder) !important; }
body.adm-dark .badge.bg-light        { background:var(--aTableHead) !important; color:var(--aText) !important; }

/* ── Progress bars ────────────────────────────────────────── */
body.admin-page .progress { background:var(--aProgress) !important; }

/* ── Stock colors always visible ─────────────────────────── */
body.admin-page .stock-critical { color:#e74c3c !important; font-weight:700; }
body.admin-page .stock-low      { color:#fd7e14 !important; font-weight:600; }
body.admin-page .stock-ok       { color:#2ecc71 !important; font-weight:600; }

/* ── Badge urgency ────────────────────────────────────────── */
body.adm-dark .badge-normal   { background:rgba(46,204,113,0.2) !important; color:#56d364 !important; }
body.adm-dark .badge-urgent   { background:rgba(253,126,20,0.2) !important; color:#fd9a3d !important; }
body.adm-dark .badge-critical { background:rgba(220,53,69,0.2)  !important; color:#ff7b7b !important; }

/* ════ NAVBAR ADMIN RIGHT ACTIONS ═══════════════════════════ */
.adm-nav-right { display:flex; align-items:center; gap:10px; }

/* Mode button */
.adm-mode-btn {
    width:36px; height:36px; border-radius:50%;
    border:1px solid rgba(255,255,255,0.25);
    background:rgba(255,255,255,0.1); color:white;
    font-size:0.9rem; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    transition:all 0.25s;
}
.adm-mode-btn:hover { background:rgba(255,255,255,0.2); }

/* Profile button */
.adm-prof-wrap { position:relative; }
.adm-prof-btn {
    display:flex; align-items:center; gap:9px;
    background:rgba(255,255,255,0.1);
    border:1px solid rgba(255,255,255,0.22);
    border-radius:28px; padding:4px 14px 4px 4px;
    cursor:pointer; color:white; transition:all 0.22s;
}
.adm-prof-btn:hover { background:rgba(255,255,255,0.2); border-color:rgba(255,255,255,0.4); }
.adm-av-img    { width:30px; height:30px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.4); flex-shrink:0; }
.adm-av-letter { width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,0.25); font-size:0.85rem; font-weight:800; color:white; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.adm-pname  { font-size:0.8rem; font-weight:600; color:white; display:block; line-height:1.2; }
.adm-prole  { font-size:0.66rem; color:rgba(255,255,255,0.5); display:block; }
.adm-chev   { font-size:0.62rem; color:rgba(255,255,255,0.5); transition:transform 0.25s; margin-left:2px; }
.adm-chev.open { transform:rotate(180deg); }
.adm-notif-dot {
    position:absolute; top:-3px; right:-3px;
    background:#ffd700; color:#000; font-size:9px; font-weight:700;
    padding:1px 5px; border-radius:10px; min-width:16px; text-align:center;
    border:2px solid #c0392b;
}
@media(max-width:500px){ .adm-pname,.adm-prole{ display:none; } }

/* ── Profile Dropdown ─────────────────────────────────────── */
.adm-dropdown {
    position:absolute; top:calc(100% + 10px); right:0;
    width:288px; background:var(--aCard);
    border:1px solid var(--aBorder);
    border-radius:16px;
    box-shadow:0 20px 56px rgba(0,0,0,0.28);
    overflow:hidden; display:none;
    animation:admDdPop 0.22s cubic-bezier(0.34,1.56,0.64,1);
    z-index:2000;
}
.adm-dropdown.open { display:block; }
@keyframes admDdPop { from{opacity:0;transform:translateY(-10px) scale(0.95);}to{opacity:1;transform:translateY(0) scale(1);} }

.adm-dd-head {
    padding:14px 18px;
    background:linear-gradient(135deg,#c0392b,#e74c3c);
    display:flex; align-items:center; gap:12px; color:white;
}
.adm-dd-av        { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,0.4); }
.adm-dd-av-letter { width:42px; height:42px; border-radius:50%; background:rgba(255,255,255,0.22); font-size:1.1rem; font-weight:800; color:white; display:flex; align-items:center; justify-content:center; border:2px solid rgba(255,255,255,0.4); }
.adm-dd-name  { font-size:0.88rem; font-weight:700; }
.adm-dd-email { font-size:0.7rem; opacity:0.8; word-break:break-all; }
.adm-dd-items { padding:6px 0; }
.adm-dd-item {
    display:flex; align-items:center; gap:10px;
    padding:9px 16px; font-size:0.84rem; font-weight:500;
    color:var(--aText) !important;
    text-decoration:none; cursor:pointer;
    background:none; border:none; width:100%; text-align:left;
    transition:background 0.15s;
}
.adm-dd-item:hover { background:var(--aSidebarHover) !important; color:#c0392b !important; }
.adm-dd-item i { color:#c0392b !important; width:17px; font-size:0.9rem; flex-shrink:0; }
.adm-dd-divider { height:1px; background:var(--aBorder); margin:4px 0; }
.adm-dd-notif-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:5px 16px 3px; font-size:0.7rem; font-weight:700;
    color:var(--aMuted); text-transform:uppercase; letter-spacing:.05em;
}
.adm-dd-notif-count { background:#c0392b; color:white; border-radius:10px; padding:1px 7px; font-size:0.67rem; font-weight:700; }
.adm-dd-notif-item { display:flex; align-items:flex-start; gap:8px; padding:7px 16px; border-bottom:1px solid var(--aBorder); }
.adm-dd-notif-title { font-size:0.78rem; font-weight:600; color:var(--aText); }
.adm-dd-notif-time  { font-size:0.68rem; color:var(--aMuted); }
.adm-dd-logout { color:#c0392b !important; font-weight:700 !important; }
.adm-dd-logout:hover { background:var(--aAlertDanger) !important; }

/* ── Logout modal ─────────────────────────────────────────── */
.adm-backdrop {
    position:fixed; inset:0; z-index:9999;
    background:rgba(0,0,0,0.72); backdrop-filter:blur(5px);
    display:none; align-items:center; justify-content:center;
}
.adm-backdrop.show { display:flex; }
.adm-modal {
    background:var(--aCard); color:var(--aText);
    border-radius:22px; padding:38px 34px; text-align:center;
    max-width:380px; width:90%;
    box-shadow:0 24px 64px rgba(0,0,0,0.4);
    border:1px solid var(--aBorder);
    animation:admModalPop 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes admModalPop { from{transform:scale(0.6);opacity:0;}to{transform:scale(1);opacity:1;} }
.adm-modal h5 { color:var(--aHeading) !important; }
.adm-modal p  { color:var(--aMuted) !important; }
</style>

<!-- ═══ LOGOUT MODAL ════════════════════════════════════════ -->
<div class="adm-backdrop" id="admLogoutBackdrop" onclick="if(event.target===this)admCloseLogout()">
    <div class="adm-modal">
        <div style="font-size:3rem;margin-bottom:12px;">👋</div>
        <h5 class="fw-bold mb-2">Are you sure you want to logout?</h5>
        <p style="font-size:0.9rem;margin-bottom:24px;">You will need to login again to access the admin panel.</p>
        <div class="d-flex gap-3 justify-content-center">
            <a href="/blood_bank/auth/logout.php" class="btn btn-danger px-4 fw-semibold">
                <i class="bi bi-box-arrow-right me-2"></i>Yes, Logout
            </a>
            <button onclick="admCloseLogout()" class="btn btn-outline-secondary px-4 fw-semibold">
                <i class="bi bi-x-lg me-2"></i>Cancel
            </button>
        </div>
    </div>
</div>

<script>
// Apply class immediately to avoid flash
document.documentElement.classList.add('admin-page');
(function(){
    document.body.classList.add('admin-page');
    if(localStorage.getItem('adm-mode')==='dark') document.body.classList.add('adm-dark');
})();

document.addEventListener('DOMContentLoaded', function(){
    document.body.classList.add('admin-page');

    // ── Remove old duplicate nav items (bell, logout from header.php) ──
    document.querySelectorAll('.navbar a[href*="logout"], .navbar a[href*="notification"]').forEach(el=>{
        const p = el.closest('.d-flex');
        if(p && p !== document.querySelector('.adm-nav-right')) p.remove();
    });
    // Remove old admin name span
    document.querySelectorAll('.navbar .text-white.small').forEach(el => el.remove());

    // ── Inject right side of navbar ──
    if(document.getElementById('admNavRight')) return;
    const navbar = document.querySelector('.navbar');
    if(!navbar) return;

    const picHTML = <?= json_encode($_has_pic) ?> ?
        `<img src="<?= $_pic_url ?>" class="adm-av-img" alt="">` :
        `<div class="adm-av-letter"><?= $_init ?></div>`;

    const ddPicHTML = <?= json_encode($_has_pic) ?> ?
        `<img src="<?= $_pic_url ?>" class="adm-dd-av" alt="">` :
        `<div class="adm-dd-av-letter"><?= $_init ?></div>`;

    let notifsHTML = '';
    <?php if ($_notif_prev && $_notif_prev->num_rows > 0): $_notif_prev->data_seek(0); while($n=$_notif_prev->fetch_assoc()): $nc=['success'=>'check-circle-fill','danger'=>'exclamation-octagon-fill','warning'=>'exclamation-triangle-fill','info'=>'info-circle-fill'][$n['type']]??'bell-fill'; $nclr=['success'=>'#27ae60','danger'=>'#c0392b','warning'=>'#e67e22','info'=>'#0d6efd'][$n['type']]??'#6c757d'; ?>
    notifsHTML += `<div class="adm-dd-notif-item"><i class="bi bi-<?= $nc ?>" style="color:<?= $nclr ?>;font-size:0.9rem;flex-shrink:0;margin-top:2px;"></i><div><div class="adm-dd-notif-title"><?= htmlspecialchars(substr($n['title'],0,32)) ?></div><div class="adm-dd-notif-time"><?= date('d M, h:i A',strtotime($n['created_at'])) ?></div></div></div>`;
    <?php endwhile; endif; ?>

    navbar.insertAdjacentHTML('beforeend', `
    <div class="adm-nav-right" id="admNavRight">
        <button class="adm-mode-btn" id="admModeBtn" onclick="admToggleMode()" title="Toggle theme">
            <i class="bi bi-moon-fill" id="admModeIcon"></i>
        </button>
        <div class="adm-prof-wrap" id="admProfWrap">
            <button class="adm-prof-btn" onclick="admToggleDD(event)">
                ${picHTML}
                <div><span class="adm-pname"><?= $_first_name ?></span><span class="adm-prole">Admin</span></div>
                <?php if($_admin_unread>0): ?><span class="adm-notif-dot"><?= $_admin_unread ?></span><?php endif; ?>
                <i class="bi bi-chevron-down adm-chev" id="admChev"></i>
            </button>
            <div class="adm-dropdown" id="admDropdown">
                <div class="adm-dd-head">
                    ${ddPicHTML}
                    <div>
                        <div class="adm-dd-name"><?= htmlspecialchars($_admin_user['name']??'Admin') ?></div>
                        <div class="adm-dd-email"><?= htmlspecialchars($_admin_user['email']??'') ?></div>
                    </div>
                </div>
                <div class="adm-dd-items">
                    <a href="/blood_bank/admin/admin_profile.php" class="adm-dd-item"><i class="bi bi-person-circle"></i>My Profile</a>
                    <div class="adm-dd-divider"></div>
                    <div class="adm-dd-notif-head"><span><i class="bi bi-bell-fill me-1"></i>Notifications</span><?php if($_admin_unread>0): ?><span class="adm-dd-notif-count"><?= $_admin_unread ?></span><?php endif; ?></div>
                    ${notifsHTML}
                    <a href="/blood_bank/admin/notifications.php" class="adm-dd-item" style="font-size:0.79rem;color:#c0392b;font-weight:600;"><i class="bi bi-arrow-right-circle-fill" style="color:#c0392b;"></i>View all<?= $_admin_unread>0?" ($_admin_unread)":'' ?></a>
                    <div class="adm-dd-divider"></div>
                    <a href="/blood_bank/admin/manage_users.php"  class="adm-dd-item"><i class="bi bi-people-fill"></i>Manage Users</a>
                    <div class="adm-dd-divider"></div>
                    <button onclick="admShowLogout()" class="adm-dd-item adm-dd-logout"><i class="bi bi-box-arrow-right"></i>Logout</button>
                </div>
            </div>
        </div>
    </div>`);

    // Apply saved mode after injection
    if(localStorage.getItem('adm-mode')==='dark') admApplyDark();
    else admApplyLight();
});

/* ── Theme mode ──────────────────────────────────────────── */
function admApplyDark(){
    document.body.classList.add('admin-page','adm-dark');
    const ic=document.getElementById('admModeIcon');
    if(ic) ic.className='bi bi-sun-fill';
    localStorage.setItem('adm-mode','dark');
}
function admApplyLight(){
    document.body.classList.add('admin-page');
    document.body.classList.remove('adm-dark');
    const ic=document.getElementById('admModeIcon');
    if(ic) ic.className='bi bi-moon-fill';
    localStorage.setItem('adm-mode','light');
}
function admToggleMode(){
    document.body.classList.contains('adm-dark') ? admApplyLight() : admApplyDark();
}

/* ── Dropdown ────────────────────────────────────────────── */
function admToggleDD(e){
    e.stopPropagation();
    const dd=document.getElementById('admDropdown'),ch=document.getElementById('admChev');
    if(!dd) return;
    const open=dd.classList.contains('open');
    dd.classList.toggle('open',!open);
    if(ch) ch.classList.toggle('open',!open);
}
document.addEventListener('click',function(e){
    const w=document.getElementById('admProfWrap');
    if(w&&!w.contains(e.target)){
        document.getElementById('admDropdown')?.classList.remove('open');
        document.getElementById('admChev')?.classList.remove('open');
    }
});
document.addEventListener('keydown',e=>{
    if(e.key==='Escape'){ admCloseLogout(); document.getElementById('admDropdown')?.classList.remove('open'); }
});

/* ── Logout modal ────────────────────────────────────────── */
function admShowLogout(){
    document.getElementById('admDropdown')?.classList.remove('open');
    document.getElementById('admChev')?.classList.remove('open');
    document.getElementById('admLogoutBackdrop').classList.add('show');
}
function admCloseLogout(){
    document.getElementById('admLogoutBackdrop').classList.remove('show');
}
</script>

<style>
/* ════════════════════════════════════════════════════════════
   ADMIN DARK MODE EXTRA FIXES — form messages, misc
════════════════════════════════════════════════════════════ */
/* Field validation messages */
body.adm-dark .invalid-feedback { color:#ff9999 !important; }
body.adm-dark .valid-feedback   { color:#56d364 !important; }
body.adm-dark .form-check-label { color:var(--aText) !important; }

/* Nav pills / tabs */
body.adm-dark .nav-pills .nav-link       { color:var(--aText) !important; }
body.adm-dark .nav-pills .nav-link.active{ background:#c0392b !important; color:white !important; }
body.adm-dark .nav-tabs .nav-link        { color:var(--aText) !important; border-color:var(--aBorder) !important; }
body.adm-dark .nav-tabs .nav-link.active { background:var(--aCard) !important; color:#c0392b !important; border-color:var(--aBorder) !important; }

/* Modals */
body.adm-dark .modal-content { background:var(--aCard) !important; color:var(--aText) !important; border-color:var(--aBorder) !important; }
body.adm-dark .modal-header  { border-bottom-color:var(--aBorder) !important; }
body.adm-dark .modal-footer  { border-top-color:var(--aBorder) !important; }
body.adm-dark .btn-close     { filter:invert(1); }

/* Dropdowns */
body.adm-dark .dropdown-menu { background:var(--aCard) !important; border-color:var(--aBorder) !important; }
body.adm-dark .dropdown-item { color:var(--aText) !important; }
body.adm-dark .dropdown-item:hover { background:var(--aSidebarHover) !important; }

/* Pagination */
body.adm-dark .page-link { background:var(--aCard) !important; border-color:var(--aBorder) !important; color:var(--aText) !important; }
body.adm-dark .page-item.active .page-link { background:#c0392b !important; border-color:#c0392b !important; }

/* Chart.js canvas — ensure bg transparent */
body.admin-page canvas { background:transparent !important; }

/* Specific text overrides */
body.admin-page .fw-semibold { color:inherit !important; }
body.admin-page .fw-bold     { color:inherit !important; }

/* Info boxes */
body.adm-dark .alert-info small { color:var(--aAlertInfoText) !important; }

/* Timeline / log items */
body.adm-dark .adm-dd-notif-title { color:var(--aText) !important; }
body.adm-dark .adm-dd-notif-time  { color:var(--aMuted) !important; }
body.adm-dark .adm-dd-item        { color:var(--aText) !important; }
body.adm-dark .adm-dropdown       { background:var(--aCard) !important; border-color:var(--aBorder) !important; }

/* Sidebar active badge */
body.admin-page .sidebar-nav .badge { font-size:9px; padding:2px 6px; }

/* Fix progress bars in dark */
body.adm-dark .progress-bar.bg-success { background-color:#56d364 !important; }
body.adm-dark .progress-bar.bg-warning { background-color:#e3b341 !important; }
body.adm-dark .progress-bar.bg-danger  { background-color:#ff7b7b !important; }
body.adm-dark .progress-bar            { background-color:#c0392b !important; }

/* Strength bar */
body.adm-dark #sBar, body.adm-dark #strengthBar { background-color:#c0392b !important; }

/* Blood group badge always readable */
body.admin-page .bg-badge {
    background:var(--aBadgeBg) !important;
    color:var(--aBadgeText) !important;
    border:1px solid var(--aBadgeBg) !important;
    font-weight:700; border-radius:20px;
    padding:3px 10px; font-size:0.78rem;
}

/* Dashboard emergency alert */
body.adm-dark [style*="background:#fff0ee"] {
    background:rgba(192,57,43,0.15) !important;
    border-left-color:#c0392b !important;
}
body.adm-dark [style*="color:#dc3545"] { color:#ff9999 !important; }

/* Ensure ALL text in dark mode is visible */
body.adm-dark .card *:not(.badge):not(.btn):not(.bg-badge):not(i) {
    color:var(--aText);
}
body.adm-dark .card .text-muted { color:var(--aMuted) !important; }
body.adm-dark .card .text-danger { color:#ff9999 !important; }
body.adm-dark .card .text-success { color:#56d364 !important; }
body.adm-dark .card .text-warning { color:#e3b341 !important; }
body.adm-dark .card .text-primary { color:#79a8ff !important; }
body.adm-dark .card .text-info    { color:#aad4ff !important; }

/* Sidebar brand image */
body.admin-page .sidebar-brand img { filter:none; }
body.adm-dark .sidebar-brand img { filter:brightness(1.2); }
</style>
