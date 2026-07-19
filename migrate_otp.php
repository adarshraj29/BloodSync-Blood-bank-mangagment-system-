<?php
require_once __DIR__ . '/config/db.php';

// Check if attempts column exists
$result = $conn->query("SHOW COLUMNS FROM otp_codes LIKE 'attempts'");
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE otp_codes ADD COLUMN attempts INT DEFAULT 0 AFTER is_used");
    echo "Added 'attempts' column to otp_codes table.\n";
} else {
    echo "'attempts' column already exists.\n";
}
?>
