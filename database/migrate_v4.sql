-- ============================================================
-- BloodSync v4 Migration - Forgot Password + Appointments fix
-- ============================================================
USE blood_bank;

-- Add purpose column to otp_codes if not exists
ALTER TABLE otp_codes MODIFY COLUMN purpose ENUM('register','reset') DEFAULT 'register';

-- Add time column to donation_log if not exists
ALTER TABLE donation_log ADD COLUMN IF NOT EXISTS donated_time TIME DEFAULT '09:00:00' AFTER donated_date;

-- User appointments (self-booked by donors)
CREATE TABLE IF NOT EXISTS user_appointments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    donor_id       INT,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    blood_group_id INT NOT NULL,
    notes          TEXT,
    status         ENUM('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id),
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id)
);
