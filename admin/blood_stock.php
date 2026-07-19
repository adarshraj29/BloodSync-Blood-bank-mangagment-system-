<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireAdmin();
$page_title = 'Blood Stock';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bg_id  = (int)$_POST['blood_group_id'];
    $change = (int)$_POST['units'];
    $type   = $_POST['type']; // add or deduct
    $reason = clean($conn, $_POST['reason']);

    $current = $conn->query("SELECT units FROM blood_stock WHERE blood_group_id=$bg_id")->fetch_assoc()['units'];
    $new_units = $type === 'add' ? $current + $change : $current - $change;

    if ($new_units < 0) {
        $error = 'Not enough stock to deduct.';
    } else {
        $conn->query("UPDATE blood_stock SET units=$new_units WHERE blood_group_id=$bg_id");
        $uid = $_SESSION['user_id'];
        $diff = $type === 'add' ? $change : -$change;
        $conn->query("INSERT INTO stock_log (blood_group_id, change_units, reason, changed_by) VALUES ($bg_id, $diff, '$reason', $uid)");
        $success = 'Stock updated successfully.';
    }
}

$stock = $conn->query("SELECT bs.*, bg.name as bg_name FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");
$log   = $conn->query("SELECT sl.*, bg.name as bg_name, u.name as changed_by_name
    FROM stock_log sl JOIN blood_groups bg ON sl.blood_group_id=bg.id
    JOIN users u ON sl.changed_by=u.id ORDER BY sl.created_at DESC LIMIT 15");
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="d-flex">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content">
    <h1 class="page-title"><i class="bi bi-droplet-half me-2 text-danger"></i>Blood Stock Management</h1>

    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Update Stock Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Update Stock</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Blood Group *</label>
                            <select name="blood_group_id" class="form-select" required>
                                <?php $stock->data_seek(0); while ($s = $stock->fetch_assoc()): ?>
                                <option value="<?= $s['blood_group_id'] ?>"><?= $s['bg_name'] ?> (<?= $s['units'] ?> units)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Action *</label>
                            <select name="type" class="form-select" required>
                                <option value="add">Add Units (Donation Received)</option>
                                <option value="deduct">Deduct Units (Dispatched)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Units *</label>
                            <input type="number" name="units" class="form-control" min="1" placeholder="Number of units" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Reason</label>
                            <input type="text" name="reason" class="form-control" placeholder="e.g. Walk-in donation, Request #5">
                        </div>
                        <button type="submit" class="btn btn-danger w-100">Update Stock</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stock Circular Cards + Table -->
        <div class="col-lg-8">
            <!-- Circular stock overview -->
            <div class="card mb-4">
                <div class="card-header py-3 px-4 fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-droplet-fill me-2 text-danger"></i>Stock Overview</span>
                    <span class="badge bg-danger"><?= array_sum(array_column(iterator_to_array($stock), 'units')) ?? '—' ?> units total</span>
                </div>
                <div class="card-body p-4">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;" id="stockRingGrid">
                        <?php $stock->data_seek(0); $stock_arr=[]; while($s=$stock->fetch_assoc()) $stock_arr[]=$s; $max_u=max(array_column($stock_arr,'units')?:[1]); foreach($stock_arr as $s): $u=(int)$s['units']; $color=$u<=2?'#dc3545':($u<=5?'#ffc107':'#198754'); $status_lbl=$u<=2?'Critical':($u<=5?'Low':'Good'); $status_cls=$u<=2?'danger':($u<=5?'warning':'success'); ?>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;transition:transform 0.3s;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                            <div style="position:relative;width:80px;height:80px;">
                                <canvas width="80" height="80"
                                    class="stock-ring-canvas"
                                    data-units="<?= $u ?>"
                                    data-max="<?= max($max_u,20) ?>"
                                    data-color="<?= $color ?>"
                                    data-name="<?= $s['bg_name'] ?>">
                                </canvas>
                                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                                    <div style="font-size:1.05rem;font-weight:800;color:var(--aHeading);line-height:1;"><?= $u ?></div>
                                    <div style="font-size:0.58rem;color:var(--aMuted);font-weight:700;text-transform:uppercase;"><?= $s['bg_name'] ?></div>
                                </div>
                            </div>
                            <span class="badge bg-<?= $status_cls ?>" style="font-size:0.65rem;"><?= $status_lbl ?></span>
                            <span style="font-size:0.68rem;color:var(--aMuted);"><?= date('d M', strtotime($s['updated_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <!-- Stock table -->
            <div class="card mb-4">
                <div class="card-header py-3 px-4 fw-semibold">Detailed Stock Levels</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">Blood Group</th>
                            <th>Units Available</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach($stock_arr as $s):
                            $cls = $s['units'] <= 2 ? 'stock-critical' : ($s['units'] <= 5 ? 'stock-low' : 'stock-ok');
                            $status = $s['units'] <= 2 ? 'Critical' : ($s['units'] <= 5 ? 'Low' : 'Good');
                            $badgec = $s['units'] <= 2 ? 'danger' : ($s['units'] <= 5 ? 'warning text-dark' : 'success');
                        ?>
                        <tr>
                            <td class="ps-4"><span class="bg-badge"><?= $s['bg_name'] ?></span></td>
                            <td class="<?= $cls ?> fw-bold"><?= $s['units'] ?> units</td>
                            <td><span class="badge bg-<?= $badgec ?>"><?= $status ?></span></td>
                            <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($s['updated_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stock Log -->
            <div class="card">
                <div class="card-header py-3 px-4 fw-semibold">Recent Stock Changes</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr>
                            <th class="ps-4">Blood Group</th><th>Change</th><th>Reason</th><th>By</th><th>Date</th>
                        </tr></thead>
                        <tbody>
                        <?php while ($l = $log->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><span class="bg-badge"><?= $l['bg_name'] ?></span></td>
                            <td class="<?= $l['change_units'] > 0 ? 'text-success' : 'text-danger' ?> fw-bold">
                                <?= $l['change_units'] > 0 ? '+' : '' ?><?= $l['change_units'] ?>
                            </td>
                            <td><?= htmlspecialchars($l['reason'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($l['changed_by_name']) ?></td>
                            <td class="text-muted small"><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
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
function drawRing(canvas, units, maxUnits, color) {
    const ctx=canvas.getContext('2d');
    const W=canvas.width,H=canvas.height,cx=W/2,cy=H/2,r=W*0.38,sw=W*0.13;
    const pct=Math.max(0,Math.min(1,units/maxUnits));
    const isDark=document.body.classList.contains('adm-dark');
    const track=isDark?'rgba(255,255,255,0.08)':'rgba(0,0,0,0.07)';
    ctx.clearRect(0,0,W,H);
    ctx.beginPath();ctx.arc(cx,cy,r,0,Math.PI*2);ctx.strokeStyle=track;ctx.lineWidth=sw;ctx.lineCap='round';ctx.stroke();
    if(pct>0){ctx.shadowColor=color;ctx.shadowBlur=8;}
    ctx.beginPath();ctx.arc(cx,cy,r,-Math.PI/2,-Math.PI/2+Math.PI*2*pct);ctx.strokeStyle=color;ctx.lineWidth=sw;ctx.lineCap='round';ctx.stroke();
    ctx.shadowBlur=0;
}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.stock-ring-canvas').forEach(canvas=>{
        const u=parseInt(canvas.dataset.units)||0,mx=parseInt(canvas.dataset.max)||20,cl=canvas.dataset.color;
        let p=0,t=u/mx;
        const a=setInterval(()=>{p=Math.min(p+t/30,t);drawRing(canvas,p*mx,mx,cl);if(p>=t)clearInterval(a);},20);
    });
});
</script>
