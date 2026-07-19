<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
$page_title = 'My Dashboard';
$uid        = (int)$_SESSION['user_id'];
$role_type  = $_SESSION['role_type'] ?? 'receiver';
$user       = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$stock      = $conn->query("SELECT bg.name, bs.units FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");

// Donor data
$donation_count=0; $total_units=0; $recent_donations=null;
if ($role_type==='donor' && !empty($user['phone'])) {
    $ph = clean($conn,$user['phone']);
    $dr = $conn->query("SELECT * FROM donors WHERE phone='$ph' AND is_active=1 LIMIT 1")->fetch_assoc();
    if ($dr) {
        $did=$dr['id'];
        $dc=$conn->query("SELECT COUNT(*) as c, SUM(units) as u FROM donation_log WHERE donor_id=$did")->fetch_assoc();
        $donation_count=(int)($dc['c']??0); $total_units=(int)($dc['u']??0);
        $recent_donations=$conn->query("SELECT dl.*,bg.name as bg_name FROM donation_log dl JOIN donors d ON dl.donor_id=d.id JOIN blood_groups bg ON d.blood_group_id=bg.id WHERE dl.donor_id=$did ORDER BY dl.donated_date DESC LIMIT 5");
    }
}
// Receiver data
$my_requests=null; $req_count=0;
if ($role_type!=='donor') {
    $my_requests=$conn->query("SELECT br.*,bg.name as blood_name,h.name as hospital_name FROM blood_requests br JOIN blood_groups bg ON br.blood_group_id=bg.id LEFT JOIN hospitals h ON br.hospital_id=h.id WHERE br.requested_by=$uid ORDER BY br.created_at DESC LIMIT 10");
    $req_count=(int)$conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE requested_by=$uid")->fetch_assoc()['c'];
}
function getBadge($c){
    if($c>=7) return['stars'=>3,'label'=>'Blood Hero','color'=>'#c0392b','icon'=>'trophy-fill'];
    if($c>=4) return['stars'=>2,'label'=>'Life Saver','color'=>'#e67e22','icon'=>'award-fill'];
    if($c>=1) return['stars'=>1,'label'=>'Donor','color'=>'#27ae60','icon'=>'heart-fill'];
    return['stars'=>0,'label'=>'New Member','color'=>'#adb5bd','icon'=>'person-fill'];
}
$badge=$getBadge=$badge=getBadge($donation_count);
$lives_saved=$donation_count*3;
$next_camp=null;
$ec=$conn->query("SHOW TABLES LIKE 'donation_camps'");
if($ec&&$ec->num_rows>0) $next_camp=$conn->query("SELECT * FROM donation_camps WHERE date>=CURDATE() AND is_active=1 ORDER BY date ASC LIMIT 1")->fetch_assoc();
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/user_header.php'; ?>
<style>
.achievement-card{background:linear-gradient(135deg,#1a0505,#2d0808);border-radius:18px;padding:22px;color:white;text-align:center;border:1px solid rgba(192,57,43,0.4);box-shadow:0 8px 32px rgba(192,57,43,0.2);position:relative;overflow:hidden;}
.achievement-card::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 50% 0%,rgba(192,57,43,0.3),transparent 70%);}
.badge-icon{width:76px;height:76px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:1.9rem;background:rgba(255,255,255,0.1);border:3px solid rgba(255,255,255,0.2);animation:badgePulse 2.5s ease-in-out infinite;position:relative;z-index:1;}
@keyframes badgePulse{0%,100%{box-shadow:0 0 0 0 rgba(192,57,43,0.5);}50%{box-shadow:0 0 0 12px rgba(192,57,43,0);}}
.stars-row{font-size:1.4rem;margin:8px 0;letter-spacing:4px;}
.stat-pill{display:inline-block;background:rgba(255,255,255,0.12);border-radius:20px;padding:5px 12px;font-size:0.75rem;font-weight:600;margin:3px;border:1px solid rgba(255,255,255,0.15);}
.blood-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;}
.blood-cell{text-align:center;border-radius:14px;padding:13px 6px;border:2px solid transparent;transition:transform 0.2s;}
.blood-cell:hover{transform:scale(1.06);}
.blood-cell .bg-name{font-size:1.1rem;color:#c0392b;font-weight:700;}
.blood-cell .bg-units{font-size:1rem;font-weight:700;}
.blood-cell.critical{background:#fff0ee;border-color:#f5c6c3;}
.blood-cell.low{background:#fff8e6;border-color:#fde68a;}
.blood-cell.ok{background:#e8f8f0;border-color:#bbf7d0;}
.ub-card .table th{background:#fff5f4;color:#c0392b;font-size:0.72rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;}
.ub-card .table td{font-size:0.85rem;}
.ub-card .table tbody tr:hover{background:#fff9f9;}
.welcome-bar{background:rgba(255,255,255,0.07);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.12);border-radius:18px;padding:18px 22px;color:white;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.wb-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid rgba(192,57,43,0.6);flex-shrink:0;}
.wb-avatar-ph{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#c0392b,#e74c3c);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;color:white;flex-shrink:0;border:2px solid rgba(192,57,43,0.4);}
.ub-pulse-ring{position:absolute;border-radius:50%;border:2px solid rgba(192,57,43,0.25);animation:ringPulse ease-out infinite;}
</style>

<div class="ub-wrap">
    <?php if(isset($_GET['welcome'])): ?>
    <div style="background:rgba(192,57,43,0.2);border:1px solid rgba(192,57,43,0.4);border-radius:16px;padding:18px 22px;color:white;text-align:center;margin-bottom:20px;">
        <div style="font-size:2.2rem;margin-bottom:6px;">🎉</div>
        <div class="fw-bold fs-5">Welcome to BloodSync!</div>
        <div style="opacity:0.75;font-size:0.85rem;margin-top:3px;">Your account is verified. Start saving lives today!</div>
    </div>
    <?php endif; ?>
    <?php if(isset($_GET['msg'])): ?>
    <div class="ub-alert-success"><i class="bi bi-check-circle-fill fs-5"></i>Blood request submitted successfully. Admin will review it shortly.</div>
    <?php endif; ?>
    <?php if(isset($_GET['emergency'])): ?>
    <div class="ub-alert-error"><i class="bi bi-exclamation-triangle-fill fs-5"></i><strong>Emergency request submitted!</strong> Admin team alerted immediately.</div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
    <div class="ub-alert-success"><i class="bi bi-check-circle-fill fs-5"></i>Profile updated successfully!</div>
    <?php endif; ?>

    <!-- Welcome bar -->
    <div class="welcome-bar">
        <?php if(!empty($user['profile_pic'])&&file_exists(__DIR__.'/../assets/uploads/profiles/'.$user['profile_pic'])): ?>
        <img src="/blood_bank/assets/uploads/profiles/<?=htmlspecialchars($user['profile_pic'])?>" class="wb-avatar" alt="">
        <?php else: ?><div class="wb-avatar-ph"><?=strtoupper(substr($user['name'],0,1))?></div><?php endif; ?>
        <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:1.1rem;">Hello, <?=htmlspecialchars($user['name'])?> 👋</div>
            <div style="opacity:0.65;font-size:0.8rem;margin-top:3px;"><?=date('l, d F Y')?></div>
        </div>
        <a href="/blood_bank/user/request_blood.php" style="background:rgba(192,57,43,0.5);border:1px solid rgba(192,57,43,0.7);color:white;border-radius:20px;padding:8px 18px;font-size:0.82rem;font-weight:700;text-decoration:none;">
            <i class="bi bi-clipboard-plus me-1"></i>Request Blood
        </a>
    </div>

    <!-- DONOR SECTION -->
    <?php if($role_type==='donor'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-5">
            <div class="ub-section-heading"><i class="bi bi-trophy-fill"></i>My Achievement</div>
            <div class="achievement-card">
                <div class="badge-icon"><i class="bi bi-<?=$badge['icon']?>" style="color:<?=$badge['color']?>;"></i></div>
                <div class="fw-bold fs-5" style="position:relative;z-index:1;"><?=$badge['label']?></div>
                <div class="stars-row" style="position:relative;z-index:1;">
                    <?php for($s=1;$s<=3;$s++): ?><span style="color:<?=$s<=$badge['stars']?'#ffd700':'rgba(255,255,255,0.15)'?>;">★</span><?php endfor; ?>
                </div>
                <div style="position:relative;z-index:1;">
                    <span class="stat-pill"><i class="bi bi-droplet-fill" style="color:#e74c3c;"></i> <?=$donation_count?> Donation<?=$donation_count!=1?'s':''?></span>
                    <span class="stat-pill"><i class="bi bi-heart-fill" style="color:#e74c3c;"></i> <?=$lives_saved?> Lives Saved</span>
                    <span class="stat-pill"><i class="bi bi-bag-fill" style="color:#e74c3c;"></i> <?=$total_units?> Units</span>
                </div>
                <div style="margin-top:12px;font-size:0.74rem;opacity:0.55;position:relative;z-index:1;">
                    <?php if($donation_count<1): ?>Make your first donation to earn the Donor badge!
                    <?php elseif($donation_count<4): ?><?=4-$donation_count?> more to reach ⭐⭐ Life Saver!
                    <?php elseif($donation_count<7): ?><?=7-$donation_count?> more to reach ⭐⭐⭐ Blood Hero!
                    <?php else: ?>You are a Blood Hero! 🏆 Thank you for saving lives.<?php endif; ?>
                </div>
                <div class="ub-pulse-ring" style="width:100px;height:100px;top:50%;left:50%;margin:-50px 0 0 -50px;animation-duration:3s;"></div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="ub-section-heading"><i class="bi bi-journal-text"></i>My Donation History</div>
            <div class="ub-card">
                <div class="ub-card-header"><span><i class="bi bi-droplet-fill"></i>Recent Donations</span><span class="badge bg-light text-danger"><?=$donation_count?> Total</span></div>
                <table class="table mb-0">
                    <thead><tr><th class="ps-3">Date</th><th>Day</th><th>Month</th><th>Blood Group</th><th>Units</th></tr></thead>
                    <tbody>
                    <?php if(!$recent_donations||$recent_donations->num_rows===0): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted"><i class="bi bi-droplet fs-2 d-block mb-2"></i>No donation records yet.<br><small>Contact admin to link your donor profile.</small></td></tr>
                    <?php else: while($d=$recent_donations->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-3 fw-semibold"><?=date('d M Y',strtotime($d['donated_date']))?></td>
                        <td class="text-muted small"><?=date('D',strtotime($d['donated_date']))?></td>
                        <td><?=date('F',strtotime($d['donated_date']))?></td>
                        <td><span class="bg-badge"><?=$d['bg_name']?></span></td>
                        <td class="fw-bold text-success">+<?=$d['units']?></td>
                    </tr>
                    <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- RECEIVER SECTION -->
    <?php if($role_type!=='donor'): ?>
    <div class="mb-4">
        <div class="ub-section-heading"><i class="bi bi-clipboard-heart-fill"></i>My Blood Requests</div>
        <div class="ub-card">
            <div class="ub-card-header"><span><i class="bi bi-clock-history"></i> Request History</span><div><span class="badge bg-light text-danger me-2"><?=$req_count?> Total</span><a href="/blood_bank/user/request_blood.php" class="btn btn-sm btn-light text-danger fw-semibold">+ New</a></div></div>
            <table class="table mb-0">
                <thead><tr><th class="ps-3">#</th><th>Blood Group</th><th>Units</th><th>Hospital</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php if(!$my_requests||$my_requests->num_rows===0): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">No requests yet. <a href="/blood_bank/user/request_blood.php" class="text-danger">Make your first request →</a></td></tr>
                <?php else: $i=1; while($r=$my_requests->fetch_assoc()): ?>
                <tr>
                    <td class="ps-3 text-muted"><?=$i++?></td>
                    <td><span class="bg-badge"><?=$r['blood_name']?></span></td>
                    <td><?=$r['units_needed']?></td>
                    <td class="text-muted small"><?=htmlspecialchars($r['hospital_name']??'N/A')?></td>
                    <td><?php $uc=['Normal'=>'badge-normal','Urgent'=>'badge-urgent','Critical'=>'badge-critical'][$r['urgency']];?><span class="badge <?=$uc?>"><?=$r['urgency']?></span></td>
                    <td><?php $sc=['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'][$r['status']];?><span class="badge bg-<?=$sc?>"><?=$r['status']?></span></td>
                    <td class="text-muted small"><?=date('d M Y',strtotime($r['created_at']))?></td>
                </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Blood Availability -->
    <div class="mb-4">
        <div class="ub-section-heading"><i class="bi bi-droplet-half"></i>Current Blood Availability</div>
        <div class="blood-grid">
            <?php while($s=$stock->fetch_assoc()): $cls=$s['units']<=2?'critical':($s['units']<=5?'low':'ok'); $clr=$s['units']<=2?'#c0392b':($s['units']<=5?'#e67e22':'#27ae60'); ?>
            <div class="blood-cell <?=$cls?>"><div class="bg-name"><?=$s['name']?></div><div class="bg-units" style="color:<?=$clr?>;"><?=$s['units']?></div><div style="font-size:10px;color:<?=$clr?>;"><?=$s['units']<=2?'Critical':($s['units']<=5?'Low':'Good')?></div></div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Camp Teaser -->
    <?php if($next_camp): ?>
    <div class="mb-4">
        <div class="ub-section-heading"><i class="bi bi-calendar-event-fill"></i>Upcoming Donation Camp</div>
        <div class="ub-card">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-3 flex-wrap">
                    <div style="width:52px;height:52px;background:linear-gradient(135deg,#c0392b,#e74c3c);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">🩸</div>
                    <div class="flex-grow-1">
                        <div class="fw-bold"><?=htmlspecialchars($next_camp['camp_name'])?></div>
                        <div class="text-muted small mt-1"><i class="bi bi-geo-alt text-danger me-1"></i><?=htmlspecialchars($next_camp['location'])?>, <?=htmlspecialchars($next_camp['city'])?></div>
                        <div class="text-muted small"><i class="bi bi-calendar3 text-danger me-1"></i><?=date('d F Y',strtotime($next_camp['date']))?> | <i class="bi bi-clock text-danger me-1"></i><?=date('h:i A',strtotime($next_camp['start_time']))?></div>
                    </div>
                    <a href="/blood_bank/user/camps.php" class="btn btn-sm btn-danger fw-semibold align-self-center">View All →</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-3">
        <div class="col-6 col-md-3"><a href="/blood_bank/user/request_blood.php" class="ub-card d-block text-center p-4 text-decoration-none"><i class="bi bi-clipboard-plus-fill fs-1 text-danger d-block mb-2"></i><div class="fw-semibold small">Request Blood</div></a></div>
        <div class="col-6 col-md-3"><a href="/blood_bank/user/emergency.php" class="ub-card d-block text-center p-4 text-decoration-none"><i class="bi bi-exclamation-triangle-fill fs-1 text-danger d-block mb-2"></i><div class="fw-semibold small">Emergency</div></a></div>
        <div class="col-6 col-md-3"><a href="/blood_bank/user/camps.php" class="ub-card d-block text-center p-4 text-decoration-none"><i class="bi bi-geo-alt-fill fs-1 text-danger d-block mb-2"></i><div class="fw-semibold small">Donation Camps</div></a></div>
        <div class="col-6 col-md-3"><a href="/blood_bank/user/support.php" class="ub-card d-block text-center p-4 text-decoration-none"><i class="bi bi-headset fs-1 text-danger d-block mb-2"></i><div class="fw-semibold small">Support</div></a></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
