<?php
// ============================================================
// BloodSync - Shared User Panel Header v4
// - Single profile dropdown (no duplicate bell/logout)
// - Dark / Light mode toggle
// - Animated background (user_bg.jpg)
// - Logout confirmation modal
// ============================================================
if (!isset($uid))  $uid  = (int)($_SESSION['user_id'] ?? 0);
if (!isset($user) || empty($user))
    $user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

$unread_count = (int)$conn->query(
    "SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0"
)->fetch_assoc()['c'];

$role_type    = $_SESSION['role_type'] ?? 'receiver';
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch 3 latest unread notifications for dropdown preview
$notif_preview = $conn->query(
    "SELECT * FROM notifications WHERE user_id=$uid AND is_read=0
     ORDER BY created_at DESC LIMIT 3"
);
?>

<!-- ═══════════ ANIMATED BACKGROUND ══════════════════════════ -->
<div class="ub-bg" id="ubBg">
    <div class="ub-img-layer" id="ubImgLayer"></div>
    <div class="ub-overlay"   id="ubOverlay"></div>
    <div id="ubOrbs"></div>
    <div id="ubRings"></div>
    <svg class="ub-hb-svg" viewBox="0 0 1440 100" preserveAspectRatio="none">
        <path class="ub-hb-path"
            d="M0,50 L180,50 L220,15 L255,85 L285,15 L315,85 L350,50
               L530,50 L570,15 L605,85 L635,15 L665,85 L700,50
               L880,50 L920,15 L955,85 L985,15 L1015,85 L1050,50 L1440,50"/>
    </svg>
</div>

<!-- ═══════════ LOGOUT MODAL (single, shared) ════════════════ -->
<div class="ub-backdrop" id="logoutBackdrop" onclick="if(event.target===this)closeLogout()">
    <div class="ub-modal">
        <div style="font-size:3rem;margin-bottom:12px;">👋</div>
        <h5 class="fw-bold mb-2">Are you sure you want to logout?</h5>
        <p class="text-muted small mb-4">You will need to login again to access your account.</p>
        <div class="d-flex gap-3 justify-content-center">
            <a href="/blood_bank/auth/logout.php" class="btn btn-danger px-4 fw-semibold">
                <i class="bi bi-box-arrow-right me-2"></i>Yes, Logout
            </a>
            <button onclick="closeLogout()" class="btn btn-outline-secondary px-4 fw-semibold">
                <i class="bi bi-x-lg me-2"></i>Cancel
            </button>
        </div>
    </div>
</div>

<!-- ═══════════ TOP NAVIGATION ═══════════════════════════════ -->
<nav class="ub-nav" id="ubNav">
    <div class="ub-nav-inner">

        <!-- Logo -->
        <a href="/blood_bank/user/dashboard.php" class="ub-logo">
            <img src="/blood_bank/assets/logo.png" alt="BloodSync">
            <span>Blood<span class="ub-sync">Sync</span></span>
        </a>

        <!-- Nav links -->
        <div class="ub-links" id="ubLinks">
            <a href="/blood_bank/user/dashboard.php"
               class="ub-link <?= $current_page==='dashboard.php'?'active':'' ?>">
               <i class="bi bi-house-fill"></i><span>Home</span></a>
            <a href="/blood_bank/user/camps.php"
               class="ub-link <?= $current_page==='camps.php'?'active':'' ?>">
               <i class="bi bi-geo-alt-fill"></i><span>Camps</span></a>
            <a href="/blood_bank/user/support.php"
               class="ub-link <?= $current_page==='support.php'?'active':'' ?>">
               <i class="bi bi-headset"></i><span>Support</span></a>
            <a href="/blood_bank/user/emergency.php"
               class="ub-link ub-emerg <?= $current_page==='emergency.php'?'active':'' ?>">
               <i class="bi bi-exclamation-triangle-fill"></i><span>Emergency</span></a>
        </div>

        <!-- Right: Mode toggle + Profile dropdown only -->
        <div class="ub-right">

            <!-- Dark/Light mode toggle -->
            <button class="ub-mode-btn" id="modeBtn" onclick="toggleMode()" title="Toggle dark/light mode">
                <i class="bi bi-moon-fill" id="modeIcon"></i>
            </button>

            <!-- Profile dropdown (contains notifications + logout) -->
            <div class="ub-prof-wrap" id="profWrap">
                <button class="ub-prof-btn" onclick="toggleDropdown(event)" id="profBtn">
                    <!-- Avatar -->
                    <?php if (!empty($user['profile_pic']) && file_exists(__DIR__.'/../assets/uploads/profiles/'.$user['profile_pic'])): ?>
                        <img src="/blood_bank/assets/uploads/profiles/<?= htmlspecialchars($user['profile_pic']) ?>"
                             class="ub-av-img" alt="">
                    <?php else: ?>
                        <div class="ub-av-letter"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                    <?php endif; ?>
                    <!-- Name + role -->
                    <div class="ub-prof-info">
                        <span class="ub-prof-name"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></span>
                        <span class="ub-prof-role"><?= ucfirst($role_type) ?></span>
                    </div>
                    <!-- Notification badge on button -->
                    <?php if ($unread_count > 0): ?>
                    <span class="ub-notif-dot"><?= $unread_count ?></span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down ub-chev" id="profChev"></i>
                </button>

                <!-- ── Dropdown ──────────────────────────── -->
                <div class="ub-dropdown" id="profDropdown">

                    <!-- Profile header -->
                    <div class="ub-dd-head">
                        <?php if (!empty($user['profile_pic']) && file_exists(__DIR__.'/../assets/uploads/profiles/'.$user['profile_pic'])): ?>
                            <img src="/blood_bank/assets/uploads/profiles/<?= htmlspecialchars($user['profile_pic']) ?>"
                                 class="ub-dd-av" alt="">
                        <?php else: ?>
                            <div class="ub-dd-av-letter"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="ub-dd-name"><?= htmlspecialchars($user['name']) ?></div>
                            <div class="ub-dd-email"><?= htmlspecialchars($user['email']) ?></div>
                            <span class="ub-dd-role-badge"><?= ucfirst($role_type) ?></span>
                        </div>
                    </div>

                    <!-- Menu items -->
                    <div class="ub-dd-items">
                        <a href="/blood_bank/user/profile.php" class="ub-dd-item">
                            <i class="bi bi-person-circle"></i>View / Edit Profile
                        </a>
                        <?php if ($role_type === 'donor'): ?>
                        <a href="/blood_bank/user/book_appointment.php" class="ub-dd-item">
                            <i class="bi bi-calendar-plus-fill" style="color:#27ae60;"></i>Book Appointment
                        </a>
                        <?php endif; ?>

                        <!-- Notifications with preview -->
                        <div class="ub-dd-divider"></div>
                        <div class="ub-dd-notif-head">
                            <span><i class="bi bi-bell-fill me-1"></i>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                            <span class="ub-dd-notif-count"><?= $unread_count ?> new</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($notif_preview && $notif_preview->num_rows > 0):
                            while ($n = $notif_preview->fetch_assoc()):
                                $nicon = ['success'=>'check-circle-fill','danger'=>'exclamation-octagon-fill','warning'=>'exclamation-triangle-fill','info'=>'info-circle-fill'][$n['type']] ?? 'bell-fill';
                                $ncolor= ['success'=>'#27ae60','danger'=>'#c0392b','warning'=>'#e67e22','info'=>'#0d6efd'][$n['type']] ?? '#6c757d';
                        ?>
                        <div class="ub-dd-notif-item">
                            <i class="bi bi-<?= $nicon ?>" style="color:<?= $ncolor ?>;font-size:0.9rem;flex-shrink:0;"></i>
                            <div>
                                <div style="font-size:0.78rem;font-weight:600;color:var(--ub-text);"><?= htmlspecialchars(substr($n['title'],0,30)) ?></div>
                                <div style="font-size:0.7rem;color:var(--ub-muted);"><?= date('d M, h:i A',strtotime($n['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endwhile; endif; ?>
                        <a href="/blood_bank/user/notifications.php" class="ub-dd-item" style="font-size:0.8rem;color:#c0392b;font-weight:600;">
                            <i class="bi bi-arrow-right-circle-fill" style="color:#c0392b;"></i>
                            View all notifications<?= $unread_count>0?" ($unread_count)":'' ?>
                        </a>

                        <div class="ub-dd-divider"></div>
                        <a href="/blood_bank/user/support.php" class="ub-dd-item">
                            <i class="bi bi-headset"></i>Help & Support
                        </a>
                        <button onclick="showLogout()" class="ub-dd-item ub-dd-logout">
                            <i class="bi bi-box-arrow-right"></i>Logout
                        </button>
                    </div>
                </div>
            </div><!-- /prof-wrap -->
        </div><!-- /right -->
    </div><!-- /nav-inner -->
</nav>

<!-- ═══════════ STYLES ════════════════════════════════════════ -->
<style>
/* ── CSS Variables — Light & Dark mode ──────────────────── */
:root {
    --ub-bg-dark:   rgba(8,0,0,0.82);
    --ub-bg-light:  rgba(255,255,255,0.82);
    --ub-card:      rgba(24,10,10,0.85);
    --ub-card-border: rgba(192,57,43,0.3);
    --ub-text:      #f0d0d0;
    --ub-muted:     #cc9999;
    --ub-nav-bg:    rgba(8,0,0,0.78);
    --ub-nav-text:  white;
    --ub-nav-link:  rgba(255,255,255,0.72);
    --ub-nav-hover: rgba(255,255,255,0.12);
    --ub-nav-active:rgba(192,57,43,0.35);
    --ub-dd-bg:     #1a0505;
    --ub-dd-border: rgba(192,57,43,0.3);
    --ub-dd-item-hover: rgba(192,57,43,0.1);
    --ub-section-color: #f0d0d0;
    --ub-wrap-bg:   transparent;
}
body.light-mode {
    --ub-bg-dark:   rgba(240,230,230,0.88);
    --ub-card:      rgba(255,255,255,0.98);
    --ub-text:      #1a0000;
    --ub-muted:     #5a3a3a;
    --ub-nav-bg:    rgba(255,255,255,0.88);
    --ub-nav-text:  #1a0000;
    --ub-nav-link:  rgba(20,0,0,0.7);
    --ub-nav-hover: rgba(192,57,43,0.1);
    --ub-nav-active:rgba(192,57,43,0.15);
    --ub-section-color: #1a0000;
    --ub-wrap-bg:   transparent;
    --ub-dd-bg:     #ffffff;
    --ub-dd-border: rgba(0,0,0,0.08);
    --ub-dd-item-hover: #fff5f4;
}

/* ── Animated Background ─────────────────────────────────── */
.ub-bg {
    position: fixed; inset: 0; z-index: -1; overflow: hidden;
    background: #0a0000;
    transition: background 0.5s ease;
}
body.light-mode .ub-bg { background: #fff0ee; }

.ub-img-layer {
    position: absolute; inset: 0;
    background: url('/blood_bank/assets/user_bg.jpg') center/cover no-repeat;
    opacity: 0.22;
    animation: bgBreath 9s ease-in-out infinite alternate;
    filter: saturate(1.5) brightness(0.65);
    transition: opacity 0.5s;
}
body.light-mode .ub-img-layer { opacity: 0.35; filter: saturate(1.3) brightness(0.85); }

@keyframes bgBreath {
    0%   { transform: scale(1);    opacity: 0.22; }
    100% { transform: scale(1.07); opacity: 0.3; }
}
.ub-overlay {
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse at 15% 50%, rgba(192,57,43,0.28) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 20%, rgba(120,10,10,0.22) 0%, transparent 50%),
        linear-gradient(180deg, rgba(8,0,0,0.72) 0%, rgba(8,0,0,0.35) 50%, rgba(8,0,0,0.82) 100%);
    transition: opacity 0.5s;
}
body.light-mode .ub-overlay {
    background:
        radial-gradient(ellipse at 15% 50%, rgba(192,57,43,0.15) 0%, transparent 55%),
        linear-gradient(180deg, rgba(255,240,240,0.5) 0%, rgba(255,240,240,0.2) 50%, rgba(255,240,240,0.6) 100%);
}

/* Floating orbs */
.ub-orb {
    position: absolute; border-radius: 50%;
    animation: orbFloat linear infinite;
    pointer-events: none; transition: opacity 0.5s;
}
@keyframes orbFloat {
    0%  { transform:translateY(100vh) scale(0.2); opacity:0; }
    8%  { opacity: 1; }
    92% { opacity: 0.5; }
    100%{ transform:translateY(-15vh) scale(1);   opacity:0; }
}
body.light-mode .ub-orb { opacity: 0.4 !important; }

/* Ripple rings */
.ub-ring {
    position: absolute; border-radius: 50%;
    border: 1.5px solid rgba(192,57,43,0.3);
    animation: ringPulse ease-out infinite;
    pointer-events: none;
}
body.light-mode .ub-ring { border-color: rgba(192,57,43,0.15); }
@keyframes ringPulse {
    0%  { transform:scale(0.2); opacity:0.7; }
    100%{ transform:scale(4.5); opacity:0; }
}

/* Heartbeat */
.ub-hb-svg {
    position: absolute; bottom: 0; left: 0;
    width: 100%; height: 80px; pointer-events: none; opacity: 0.18;
}
body.light-mode .ub-hb-svg { opacity: 0.12; }
.ub-hb-path {
    stroke:#e74c3c; stroke-width:2; fill:none; stroke-linecap:round;
    stroke-dasharray:1400; stroke-dashoffset:1400;
    animation: hbDraw 3.5s ease-in-out infinite;
}
@keyframes hbDraw {
    0%  { stroke-dashoffset:1400; opacity:0; }
    15% { opacity:1; }
    85% { opacity:1; }
    100%{ stroke-dashoffset:0; opacity:0; }
}

/* ── Navigation ────────────────────────────────────────────── */
.ub-nav {
    position: sticky; top: 0; z-index: 500;
    background: var(--ub-nav-bg);
    backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(192,57,43,0.25);
    box-shadow: 0 2px 24px rgba(0,0,0,0.35);
    transition: background 0.4s ease;
    padding: 0 20px;
}
.ub-nav-inner {
    max-width: 1100px; margin: 0 auto;
    display: flex; align-items: center;
    justify-content: space-between;
    height: 60px; gap: 12px;
}
.ub-logo {
    display: flex; align-items: center; gap: 9px;
    font-weight: 800; font-size: 1.15rem;
    color: var(--ub-nav-text) !important;
    text-decoration: none; flex-shrink: 0;
    transition: color 0.3s;
}
.ub-logo img {
    width: 33px; height: 33px; border-radius: 50%;
    border: 2px solid rgba(192,57,43,0.5);
    transition: transform 0.3s;
}
.ub-logo:hover img { transform: rotate(14deg) scale(1.1); }
.ub-sync { color: #ffd0cc; }
body.light-mode .ub-sync { color: #c0392b; }

.ub-links { display: flex; align-items: center; gap: 3px; }
.ub-link {
    display: flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 20px;
    color: var(--ub-nav-link); font-size: 0.81rem; font-weight: 600;
    text-decoration: none; transition: all 0.2s;
    border: 1px solid transparent;
}
.ub-link:hover {
    color: var(--ub-nav-text);
    background: var(--ub-nav-hover);
    border-color: rgba(192,57,43,0.2);
}
.ub-link.active {
    color: var(--ub-nav-text);
    background: var(--ub-nav-active);
    border-color: rgba(192,57,43,0.4);
}
.ub-emerg { color: #ff9999 !important; }
.ub-emerg:hover, .ub-emerg.active {
    background: rgba(192,57,43,0.4) !important;
    color: white !important;
    border-color: rgba(192,57,43,0.6) !important;
}
@media(max-width:600px){
    .ub-link span { display:none; }
    .ub-link { padding:7px 9px; }
}

/* Right side */
.ub-right { display: flex; align-items: center; gap: 8px; }

/* Dark/light mode toggle */
.ub-mode-btn {
    width: 36px; height: 36px; border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.8); font-size: 0.88rem;
    cursor: pointer; transition: all 0.25s;
    display: flex; align-items: center; justify-content: center;
}
.ub-mode-btn:hover { background: rgba(255,255,255,0.18); color: white; }
body.light-mode .ub-mode-btn {
    border-color: rgba(0,0,0,0.15);
    background: rgba(0,0,0,0.06);
    color: #c0392b;
}

/* Profile button */
.ub-prof-wrap { position: relative; }
.ub-prof-btn {
    display: flex; align-items: center; gap: 9px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 30px; padding: 5px 13px 5px 5px;
    cursor: pointer; transition: all 0.22s; color: white;
    position: relative;
}
.ub-prof-btn:hover {
    background: rgba(255,255,255,0.14);
    border-color: rgba(192,57,43,0.5);
}
body.light-mode .ub-prof-btn {
    background: rgba(192,57,43,0.08);
    border-color: rgba(192,57,43,0.25);
    color: #1a0000;
}
.ub-av-img {
    width: 30px; height: 30px; border-radius: 50%;
    object-fit: cover; border: 2px solid rgba(192,57,43,0.6); flex-shrink: 0;
}
.ub-av-letter {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg,#c0392b,#e74c3c);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 800; color: white; flex-shrink: 0;
}
.ub-prof-info {
    display: flex; flex-direction: column; text-align: left; line-height: 1.2;
}
.ub-prof-name { font-size: 0.8rem; font-weight: 600; color: var(--ub-nav-text); }
.ub-prof-role { font-size: 0.67rem; color: rgba(255,255,255,0.5); font-weight: 500; }
body.light-mode .ub-prof-role { color: rgba(0,0,0,0.45); }
@media(max-width:480px){ .ub-prof-info { display:none; } }
.ub-notif-dot {
    position: absolute; top: -3px; right: -3px;
    background: #e74c3c; color: white;
    font-size: 9px; font-weight: 700; padding: 1px 5px;
    border-radius: 10px; min-width: 16px; text-align: center;
    border: 2px solid rgba(8,0,0,0.8);
}
body.light-mode .ub-notif-dot { border-color: white; }
.ub-chev {
    font-size: 0.62rem; color: rgba(255,255,255,0.45);
    transition: transform 0.25s;
}
.ub-chev.open { transform: rotate(180deg); }
body.light-mode .ub-chev { color: rgba(0,0,0,0.35); }

/* ── Dropdown ──────────────────────────────────────────────── */
.ub-dropdown {
    position: absolute; top: calc(100% + 10px); right: 0;
    width: 290px; background: var(--ub-dd-bg);
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px var(--ub-dd-border);
    overflow: hidden; display: none;
    animation: ddPop 0.22s cubic-bezier(0.34,1.56,0.64,1);
    z-index: 1000;
    transition: background 0.3s;
}
.ub-dropdown.open { display: block; }
@keyframes ddPop {
    from { opacity:0; transform:translateY(-10px) scale(0.95); }
    to   { opacity:1; transform:translateY(0)     scale(1); }
}
.ub-dd-head {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 18px;
    background: linear-gradient(135deg,#c0392b,#e74c3c);
    color: white;
}
.ub-dd-av {
    width: 44px; height: 44px; border-radius: 50%;
    object-fit: cover; border: 2px solid rgba(255,255,255,0.4); flex-shrink: 0;
}
.ub-dd-av-letter {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; font-weight: 800; color: white;
    border: 2px solid rgba(255,255,255,0.4); flex-shrink: 0;
}
.ub-dd-name  { font-size: 0.88rem; font-weight: 700; }
.ub-dd-email { font-size: 0.7rem; opacity: 0.8; word-break: break-all; }
.ub-dd-role-badge {
    display: inline-block; margin-top: 3px;
    background: rgba(255,255,255,0.2); border-radius: 10px;
    padding: 1px 8px; font-size: 0.66rem; font-weight: 700;
}
.ub-dd-items { padding: 6px 0; }
.ub-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 16px; color: var(--ub-text);
    text-decoration: none; font-size: 0.84rem; font-weight: 500;
    transition: background 0.15s; cursor: pointer;
    background: none; border: none; width: 100%; text-align: left;
}
.ub-dd-item:hover { background: var(--ub-dd-item-hover); color: #c0392b; }
.ub-dd-item i { color: #c0392b; font-size: 0.92rem; width: 17px; }
.ub-dd-divider { height: 1px; background: var(--ub-card-border); margin: 4px 0; }

/* Notification preview items in dropdown */
.ub-dd-notif-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 6px 16px 4px;
    font-size: 0.72rem; font-weight: 700; color: var(--ub-muted);
    text-transform: uppercase; letter-spacing: .05em;
}
.ub-dd-notif-count {
    background: #c0392b; color: white;
    border-radius: 10px; padding: 1px 7px; font-size: 0.67rem; font-weight: 700;
}
.ub-dd-notif-item {
    display: flex; align-items: flex-start; gap: 8px;
    padding: 7px 16px; border-bottom: 1px solid var(--ub-card-border);
}
.ub-dd-logout { color: #c0392b !important; font-weight: 700 !important; }
.ub-dd-logout i { color: #c0392b !important; }
.ub-dd-logout:hover { background: #fff0ee !important; }

/* ── Logout modal ──────────────────────────────────────────── */
.ub-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.72);
    display: none; align-items: center; justify-content: center;
    backdrop-filter: blur(5px);
}
.ub-backdrop.show { display: flex; }
.ub-modal {
    background: var(--ub-card); border-radius: 22px;
    padding: 38px 34px; text-align: center;
    max-width: 380px; width: 90%;
    box-shadow: 0 24px 64px rgba(0,0,0,0.4);
    animation: modalPop 0.3s cubic-bezier(0.34,1.56,0.64,1);
    color: var(--ub-text);
}
@keyframes modalPop {
    from { transform:scale(0.6); opacity:0; }
    to   { transform:scale(1);   opacity:1; }
}

/* ── Page wrapper & glass cards ────────────────────────────── */
.ub-wrap {
    max-width: 1000px; margin: 0 auto;
    padding: 28px 20px 80px; position: relative; z-index: 1;
}
.ub-card {
    background: var(--ub-card);
    border-radius: 18px;
    border: 1px solid var(--ub-card-border);
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.4s ease;
    margin-bottom: 20px;
}
.ub-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 48px rgba(0,0,0,0.2);
}
.ub-card-header {
    background: linear-gradient(135deg,#c0392b,#e74c3c);
    color: white; padding: 13px 18px; font-weight: 700; font-size: 0.93rem;
    display: flex; align-items: center; justify-content: space-between;
}
.ub-card-header i { margin-right: 7px; }
.ub-section-heading {
    color: var(--ub-section-color); font-size: 1rem; font-weight: 700;
    margin-bottom: 13px; display: flex; align-items: center; gap: 7px;
}
.ub-section-heading i { color: #e74c3c; }
.ub-alert-success {
    background:rgba(39,174,96,0.18); border:1px solid rgba(39,174,96,0.45);
    border-radius:13px; padding:13px 17px; color:#d5f5e3;
    margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.88rem;
}
.ub-alert-error {
    background:rgba(192,57,43,0.18); border:1px solid rgba(192,57,43,0.45);
    border-radius:13px; padding:13px 17px; color:#ffcccc;
    margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.88rem;
}
.ub-alert-info {
    background:rgba(52,152,219,0.14); border:1px solid rgba(52,152,219,0.35);
    border-radius:13px; padding:13px 17px; color:#d6eaf8;
    margin-bottom:16px; display:flex; align-items:center; gap:10px; font-size:0.88rem;
}
body.light-mode .ub-alert-success { color: #155c2e; }
body.light-mode .ub-alert-error   { color: #7b1f1f; }
body.light-mode .ub-alert-info    { color: #0c4a6e; }

/* Tables inside cards */
.ub-card .table th {
    background: #fff5f4; color: #c0392b;
    font-size: 0.71rem; text-transform: uppercase;
    letter-spacing: .05em; font-weight: 700;
}
body.light-mode .ub-card .table th { background: #fff5f4; }
.ub-card .table td { font-size: 0.84rem; color: var(--ub-text); }
.ub-card .table tbody tr:hover { background: #fff9f9; }
</style>

<!-- ═══════════ SCRIPTS ═══════════════════════════════════════ -->
<script>
// ── Animated background ───────────────────────────────────────
(function(){
    const orbs  = document.getElementById('ubOrbs');
    const rings = document.getElementById('ubRings');
    if (!orbs || !rings) return;

    const orbC = [
        'radial-gradient(circle,rgba(192,57,43,0.85),rgba(140,15,15,0.4))',
        'radial-gradient(circle,rgba(231,76,60,0.7),rgba(192,57,43,0.3))',
        'radial-gradient(circle,rgba(255,110,90,0.65),rgba(200,40,40,0.25))',
    ];
    for (let i = 0; i < 22; i++) {
        const el   = document.createElement('div');
        const size = 5 + Math.random() * 18;
        el.className = 'ub-orb';
        el.style.cssText = `
            width:${size}px;height:${size}px;
            background:${orbC[i%3]};
            left:${Math.random()*100}%;
            animation-duration:${9+Math.random()*14}s;
            animation-delay:${Math.random()*12}s;
            box-shadow:0 0 ${size*1.5}px rgba(192,57,43,0.45);
        `;
        orbs.appendChild(el);
    }

    const rPos = [[12,35],[68,55],[45,75],[88,22],[30,60],[75,80]];
    rPos.forEach(([x,y],i) => {
        const ring = document.createElement('div');
        const size = 70 + Math.random()*130;
        ring.className = 'ub-ring';
        ring.style.cssText = `
            width:${size}px;height:${size}px;
            left:${x}%;top:${y}%;
            margin:-${size/2}px 0 0 -${size/2}px;
            animation-duration:${4.5+Math.random()*3}s;
            animation-delay:${i*0.7}s;
        `;
        rings.appendChild(ring);
    });
})();

// ── Dark / Light mode ─────────────────────────────────────────
(function(){
    const saved = localStorage.getItem('ub-mode') || 'dark';
    if (saved === 'light') applyLight();
    else applyDark();
})();

function applyLight(){
    document.body.classList.add('light-mode');
    const icon = document.getElementById('modeIcon');
    if (icon) icon.className = 'bi bi-sun-fill';
}
function applyDark(){
    document.body.classList.remove('light-mode');
    const icon = document.getElementById('modeIcon');
    if (icon) icon.className = 'bi bi-moon-fill';
}
function toggleMode(){
    if (document.body.classList.contains('light-mode')) {
        applyDark(); localStorage.setItem('ub-mode','dark');
    } else {
        applyLight(); localStorage.setItem('ub-mode','light');
    }
}

// ── Profile dropdown ─────────────────────────────────────────
function toggleDropdown(e){
    e.stopPropagation();
    const dd = document.getElementById('profDropdown');
    const ch = document.getElementById('profChev');
    const open = dd.classList.contains('open');
    dd.classList.toggle('open', !open);
    ch.classList.toggle('open', !open);
}
document.addEventListener('click', function(e){
    const w = document.getElementById('profWrap');
    if (w && !w.contains(e.target)){
        document.getElementById('profDropdown')?.classList.remove('open');
        document.getElementById('profChev')?.classList.remove('open');
    }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape'){
        closeLogout();
        document.getElementById('profDropdown')?.classList.remove('open');
        document.getElementById('profChev')?.classList.remove('open');
    }
});

// ── Logout modal ─────────────────────────────────────────────
function showLogout(){
    document.getElementById('profDropdown')?.classList.remove('open');
    document.getElementById('profChev')?.classList.remove('open');
    document.getElementById('logoutBackdrop').classList.add('show');
}
function closeLogout(){
    document.getElementById('logoutBackdrop').classList.remove('show');
}
</script>

<style>
/* ════════════════════════════════════════════════════════
   USER PANEL LIGHT MODE FULL FIXES
   (ensures all text, cards, tables adapt correctly)
════════════════════════════════════════════════════════ */

/* ── LIGHT MODE: Original clean background ─────────────── */
body.light-mode .ub-bg { background: #f5f5f5 !important; }
body.light-mode .ub-img-layer {
    opacity: 0.15 !important;
    filter: saturate(0.8) brightness(1.1) !important;
}
body.light-mode .ub-overlay {
    background:
        rgba(249,249,249,0.85) !important;
}

/* ── LIGHT MODE: Navigation ────────────────────────────── */
body.light-mode .ub-nav {
    background: rgba(255,255,255,0.92) !important;
    border-bottom: 1px solid rgba(192,57,43,0.2) !important;
    box-shadow: 0 2px 16px rgba(192,57,43,0.12) !important;
}
body.light-mode .ub-logo  { color: #1a0000 !important; }
body.light-mode .ub-link  { color: rgba(20,0,0,0.65) !important; }
body.light-mode .ub-link:hover,
body.light-mode .ub-link.active {
    color: #c0392b !important;
    background: rgba(192,57,43,0.08) !important;
}
body.light-mode .ub-emerg { color: #c0392b !important; }
body.light-mode .ub-mode-btn {
    border-color: rgba(0,0,0,0.15) !important;
    background: rgba(192,57,43,0.08) !important;
    color: #c0392b !important;
}
body.light-mode .ub-prof-btn {
    background: rgba(192,57,43,0.07) !important;
    border-color: rgba(192,57,43,0.2) !important;
}
body.light-mode .ub-prof-name { color: #1a0000 !important; }
body.light-mode .ub-prof-role { color: rgba(0,0,0,0.5) !important; }
body.light-mode .ub-chev      { color: rgba(0,0,0,0.35) !important; }
body.light-mode .ub-notif-dot { border-color: white !important; }

/* ── LIGHT MODE: Page wrapper & body ───────────────────── */
body.light-mode .ub-wrap { color: #1a0000 !important; }

/* ── LIGHT MODE: Glass cards ───────────────────────────── */
body.light-mode .ub-card {
    background: rgba(255,255,255,0.98) !important;
    border-color: rgba(192,57,43,0.12) !important;
    color: #1a0000 !important;
}
body.light-mode .ub-card-header {
    background: linear-gradient(135deg,#c0392b,#e74c3c) !important;
    color: white !important;
}
body.light-mode .ub-card .table th {
    background: #fff5f4 !important;
    color: #c0392b !important;
}
body.light-mode .ub-card .table td { color: #1a0000 !important; }
body.light-mode .ub-card .table tbody tr:hover { background: #fff9f9 !important; }
body.light-mode .ub-section-heading { color: #1a0000 !important; }

/* ── LIGHT MODE: Section headings ──────────────────────── */
body.light-mode .ub-section-heading { color: #1a0000 !important; }
body.light-mode h1,body.light-mode h2,body.light-mode h3,
body.light-mode h4,body.light-mode h5,body.light-mode h6 { color: #1a0000 !important; }
body.light-mode p,body.light-mode span,body.light-mode div { color: inherit; }
body.light-mode .text-muted { color: #6c3a3a !important; }
body.light-mode .fw-semibold,.fw-bold { color: inherit; }

/* ── LIGHT MODE: Alerts ────────────────────────────────── */
body.light-mode .ub-alert-success {
    background: rgba(39,174,96,0.1) !important;
    border-color: rgba(39,174,96,0.3) !important;
    color: #155c2e !important;
}
body.light-mode .ub-alert-error {
    background: rgba(192,57,43,0.1) !important;
    border-color: rgba(192,57,43,0.3) !important;
    color: #7b1f1f !important;
}
body.light-mode .ub-alert-info {
    background: rgba(13,110,253,0.08) !important;
    border-color: rgba(13,110,253,0.2) !important;
    color: #0c4a6e !important;
}

/* ── LIGHT MODE: Welcome banner ────────────────────────── */
body.light-mode .welcome-bar {
    background: rgba(255,255,255,0.85) !important;
    border-color: rgba(192,57,43,0.2) !important;
    color: #1a0000 !important;
}

/* ── LIGHT MODE: Blood grid ────────────────────────────── */
body.light-mode .blood-cell.critical { background: #fff0ee !important; border-color: #f5c6c3 !important; }
body.light-mode .blood-cell.low      { background: #fff8e6 !important; border-color: #fde68a !important; }
body.light-mode .blood-cell.ok       { background: #e8f8f0 !important; border-color: #bbf7d0 !important; }
body.light-mode .blood-cell .bg-name { color: #c0392b !important; }

/* ── LIGHT MODE: Achievement card ──────────────────────── */
body.light-mode .achievement-card {
    background: linear-gradient(135deg,#fff0ee,#ffe0dd) !important;
    border-color: rgba(192,57,43,0.3) !important;
    color: #1a0000 !important;
}
body.light-mode .achievement-card .stat-pill {
    background: rgba(192,57,43,0.1) !important;
    border-color: rgba(192,57,43,0.2) !important;
    color: #7b1f1f !important;
}
body.light-mode .achievement-card .stars-row { color: #c0392b !important; }

/* ── LIGHT MODE: Forms ─────────────────────────────────── */
body.light-mode .form-control,
body.light-mode .form-select,
body.light-mode textarea,
body.light-mode .input-group-text {
    background: #fff !important;
    border-color: #ced4da !important;
    color: #212529 !important;
}
body.light-mode .form-label { color: #1a0000 !important; }
body.light-mode .form-text  { color: #6c3a3a !important; }

/* ── DARK MODE: Full adaptation ────────────────────────── */
body:not(.light-mode) .ub-card { color: #e0e0e0 !important; }
body:not(.light-mode) .ub-card .table td { color: #e0e0e0 !important; }
body:not(.light-mode) .ub-card .table th { background: #1a0505 !important; color: #ff9999 !important; }
body:not(.light-mode) .ub-card .table tbody tr:hover { background: rgba(192,57,43,0.1) !important; }
body:not(.light-mode) .ub-card-header { background: linear-gradient(135deg,#96281b,#c0392b) !important; color: white !important; }
body:not(.light-mode) .ub-section-heading { color: #f0c0c0 !important; }
body:not(.light-mode) .text-muted { color: #cc9999 !important; }
body:not(.light-mode) .welcome-bar { color: #f0d0d0 !important; }
body:not(.light-mode) h1,body:not(.light-mode) h2,
body:not(.light-mode) h3,body:not(.light-mode) h4,
body:not(.light-mode) h5,body:not(.light-mode) h6 { color: #f0d0d0 !important; }
body:not(.light-mode) p { color: #e0c0c0 !important; }
body:not(.light-mode) .form-control,
body:not(.light-mode) .form-select,
body:not(.light-mode) textarea {
    background: rgba(40,10,10,0.8) !important;
    border-color: rgba(192,57,43,0.3) !important;
    color: #f0d0d0 !important;
}
body:not(.light-mode) .form-label { color: #f0d0d0 !important; }
body:not(.light-mode) .input-group-text {
    background: rgba(40,10,10,0.9) !important;
    border-color: rgba(192,57,43,0.3) !important;
    color: #cc8888 !important;
}
body:not(.light-mode) ::placeholder { color: #cc8888 !important; }
body:not(.light-mode) select option { background: #1a0505 !important; color: #f0d0d0 !important; }

/* ── Dropdown always readable ──────────────────────────── */
.ub-dropdown { color: #212529 !important; }
.ub-dd-item  { color: #212529 !important; }
.ub-dd-notif-title { color: #212529 !important; }

/* ── Camp cards light/dark ─────────────────────────────── */
body.light-mode .camp-card  { background: white !important; color: #1a0000 !important; }
body.light-mode .camp-badge { background: #fff0ee !important; color: #c0392b !important; }
body:not(.light-mode) .camp-card { background: rgba(30,5,5,0.85) !important; color: #f0d0d0 !important; border-color: rgba(192,57,43,0.3) !important; }
body:not(.light-mode) .camp-badge { background: rgba(192,57,43,0.2) !important; color: #f0d0d0 !important; border-color: rgba(192,57,43,0.3) !important; }
body:not(.light-mode) .days-left.soon { background: rgba(192,57,43,0.2) !important; color: #f0d0d0 !important; }
body:not(.light-mode) .days-left.upcoming { background: rgba(39,174,96,0.2) !important; color: #f0d0d0 !important; }
body:not(.light-mode) .blood-cell.critical { background: rgba(192,57,43,0.15) !important; border-color: rgba(192,57,43,0.3) !important; }
body:not(.light-mode) .blood-cell.low { background: rgba(230,126,34,0.15) !important; border-color: rgba(230,126,34,0.3) !important; }
body:not(.light-mode) .blood-cell.ok { background: rgba(39,174,96,0.15) !important; border-color: rgba(39,174,96,0.3) !important; }

/* ── Helpline cards light/dark ─────────────────────────── */
body.light-mode  .helpline { background: white !important; color: #1a0000 !important; }
body:not(.light-mode) .helpline { background: rgba(30,5,5,0.7) !important; color: #f0d0d0 !important; border-color: rgba(192,57,43,0.3) !important; }

/* ── Contact rows ──────────────────────────────────────── */
body.light-mode .contact-value { color: #1a0000 !important; }
body:not(.light-mode) .contact-value { color: #f0d0d0 !important; }
body:not(.light-mode) .contact-row:hover { background: rgba(192,57,43,0.08) !important; }
body:not(.light-mode) .contact-icon { background: rgba(255,255,255,0.1) !important; }
</style>
