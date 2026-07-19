-- ============================================================
-- Blood Bank Management System - Feature Migration
-- Run this AFTER importing blood_bank.sql
-- ============================================================

USE blood_bank;

-- ── 1. Add city & state to donors (location search) ─────────
ALTER TABLE donors
    ADD COLUMN IF NOT EXISTS city  VARCHAR(100) DEFAULT NULL AFTER address,
    ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL AFTER city;

-- ── 2. Appointments table ────────────────────────────────────
CREATE TABLE IF NOT EXISTS appointments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    donor_id      INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME NOT NULL,
    status        ENUM('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
    notes         TEXT,
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id)   REFERENCES donors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ── 3. Emergency requests table ─────────────────────────────
CREATE TABLE IF NOT EXISTS emergency_requests (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    patient_name   VARCHAR(100) NOT NULL,
    phone          VARCHAR(15)  NOT NULL,
    blood_group_id INT NOT NULL,
    units_needed   INT NOT NULL,
    hospital_name  VARCHAR(150),
    location       VARCHAR(200),
    message        TEXT,
    status         ENUM('Open','Fulfilled','Closed') DEFAULT 'Open',
    requested_by   INT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id),
    FOREIGN KEY (requested_by)   REFERENCES users(id)
);

-- ── 4. Notifications table ───────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    type       ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ── 5. Monthly report cache (optional helper view) ───────────
CREATE OR REPLACE VIEW monthly_donations AS
    SELECT
        DATE_FORMAT(donated_date, '%Y-%m') AS month,
        COUNT(*)                           AS total_donations,
        SUM(units)                         AS total_units
    FROM donation_log
    GROUP BY DATE_FORMAT(donated_date, '%Y-%m')
    ORDER BY month DESC;
