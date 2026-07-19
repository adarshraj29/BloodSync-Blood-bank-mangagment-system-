<?php
// ============================================================
// BloodSync - Activity Logger Helper
// Include this file and call logActivity() anywhere
// ============================================================
function logActivity($conn, $user_id, $user_name, $action, $detail = '') {
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, user_name VARCHAR(100),
        action VARCHAR(200) NOT NULL, detail TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare(
        "INSERT INTO activity_log (user_id, user_name, action, detail, ip_address)
         VALUES (?,?,?,?,?)"
    );
    if ($stmt) {
        $stmt->bind_param("issss", $user_id, $user_name, $action, $detail, $ip);
        $stmt->execute();
    }
}

// ── Send simulated SMS notification ─────────────────────────
function sendSMS($conn, $user_id, $phone, $message) {
    $conn->query("CREATE TABLE IF NOT EXISTS sms_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT, phone VARCHAR(15), message TEXT,
        status ENUM('Sent','Failed','Simulated') DEFAULT 'Simulated',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // In production: integrate Twilio / MSG91 / Fast2SMS API here
    // For now: simulate and log
    $stmt = $conn->prepare(
        "INSERT INTO sms_log (user_id, phone, message, status) VALUES (?,?,'?','Simulated')"
    );
    // Direct query for safety
    $msg_safe = $conn->real_escape_string($message);
    $phone_safe = $conn->real_escape_string($phone ?? '');
    $conn->query("INSERT INTO sms_log (user_id, phone, message, status)
        VALUES ($user_id, '$phone_safe', '$msg_safe', 'Simulated')");

    // Also send as in-app notification
    $title = "Donation Confirmed 🩸";
    $notif = $conn->real_escape_string($message);
    $conn->query("INSERT INTO notifications (user_id, title, message, type)
        VALUES ($user_id, '$title', '$notif', 'success')");
    return true;
}
?>
