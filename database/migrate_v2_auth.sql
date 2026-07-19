-- ============================================================
-- BloodSync v2 - Auth & Branding Migration
-- Run this in phpMyAdmin after previous migrations
-- ============================================================

USE blood_bank;

-- Add role + email_verified + profile columns to users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role_type ENUM('admin','donor','receiver') DEFAULT 'receiver' AFTER role,
    ADD COLUMN IF NOT EXISTS phone      VARCHAR(15)  DEFAULT NULL AFTER role_type,
    ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0 AFTER phone,
    ADD COLUMN IF NOT EXISTS is_active  TINYINT(1) DEFAULT 1 AFTER email_verified;

-- OTP table
CREATE TABLE IF NOT EXISTS otp_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    otp        VARCHAR(6)   NOT NULL,
    purpose    ENUM('register','reset') DEFAULT 'register',
    expires_at DATETIME NOT NULL,
    is_used    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);

-- Update default admin to verified
UPDATE users SET email_verified=1, role_type='admin', is_active=1 WHERE role='admin';
