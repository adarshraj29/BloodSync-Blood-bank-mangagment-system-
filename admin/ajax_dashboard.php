<?php
session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

$total_donors   = (int)$conn->query("SELECT COUNT(*) as c FROM donors WHERE is_active=1")->fetch_assoc()['c'];
$total_units    = (int)$conn->query("SELECT SUM(units) as c FROM blood_stock")->fetch_assoc()['c'];
$total_requests = (int)$conn->query("SELECT COUNT(*) as c FROM blood_requests")->fetch_assoc()['c'];
$pending_req    = (int)$conn->query("SELECT COUNT(*) as c FROM blood_requests WHERE status='Pending'")->fetch_assoc()['c'];

// Blood stock data
$stock_raw = $conn->query("SELECT bg.name, bs.units FROM blood_stock bs JOIN blood_groups bg ON bs.blood_group_id=bg.id ORDER BY bg.id");
$stock_data = [];
while ($r = $stock_raw->fetch_assoc()) {
    $stock_data[] = ['name'=>$r['name'], 'units'=>(int)$r['units']];
}

// Recent requests HTML
$recent = $conn->query("SELECT br.*, bg.name as blood_name, h.name as hospital_name
    FROM blood_requests br JOIN blood_groups bg ON br.blood_group_id=bg.id
    LEFT JOIN hospitals h ON br.hospital_id=h.id
    ORDER BY br.created_at DESC LIMIT 6");

$urgency_class = ['Normal'=>'badge-normal','Urgent'=>'badge-urgent','Critical'=>'badge-critical'];
$status_class  = ['Pending'=>'warning','Approved'=>'success','Rejected'=>'danger'];

ob_start();
if (!$recent || $recent->num_rows === 0) {
    echo '<tr><td colspan="7" class="text-center py-4 text-muted">No requests yet.</td></tr>';
} else {
    while ($r = $recent->fetch_assoc()) {
        $uc = $urgency_class[$r['urgency']] ?? 'badge-normal';
        $sc = $status_class[$r['status']]   ?? 'secondary';
        echo '<tr class="bs-req-row">';
        echo '<td class="ps-4 fw-semibold small">'.htmlspecialchars($r['requester_name']).'</td>';
        echo '<td><span class="bg-badge">'.htmlspecialchars($r['blood_name']).'</span></td>';
        echo '<td>'.(int)$r['units_needed'].'</td>';
        echo '<td class="text-muted small">'.htmlspecialchars($r['hospital_name']??'N/A').'</td>';
        echo '<td><span class="badge '.$uc.'">'.htmlspecialchars($r['urgency']).'</span></td>';
        echo '<td><span class="badge bg-'.$sc.'">'.htmlspecialchars($r['status']).'</span></td>';
        echo '<td class="text-muted small">'.date('d M Y',strtotime($r['created_at'])).'</td>';
        echo '</tr>';
    }
}
$recent_html = ob_get_clean();

echo json_encode([
    'total_donors'   => $total_donors,
    'total_units'    => $total_units,
    'total_requests' => $total_requests,
    'pending_req'    => $pending_req,
    'stock_data'     => $stock_data,
    'recent_html'    => $recent_html,
    'timestamp'      => date('H:i:s'),
]);
