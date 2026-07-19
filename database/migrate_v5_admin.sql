-- ============================================================
-- BloodSync v5 Admin Panel Migration
-- ============================================================
USE blood_bank;

-- 1. User block/unblock
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_blocked TINYINT(1) DEFAULT 0 AFTER is_active;
ALTER TABLE users ADD COLUMN IF NOT EXISTS block_reason VARCHAR(300) DEFAULT NULL AFTER is_blocked;

-- 2. Profile columns for admin
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(200) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(300) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL;

-- 3. Activity log
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    user_name  VARCHAR(100),
    action     VARCHAR(200) NOT NULL,
    detail     TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
);

-- 4. SMS / donation notifications log
CREATE TABLE IF NOT EXISTS sms_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    phone      VARCHAR(15),
    message    TEXT,
    status     ENUM('Sent','Failed','Simulated') DEFAULT 'Simulated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Update default admin
UPDATE users SET email_verified=1, is_blocked=0 WHERE role='admin';
