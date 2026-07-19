-- ============================================================
-- Blood Bank - User Panel Features Migration v3
-- Run this in phpMyAdmin → blood_bank database → Import
-- ============================================================
USE blood_bank;

-- 1. Add profile fields to users table
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_pic  VARCHAR(200) DEFAULT NULL AFTER name,
    ADD COLUMN IF NOT EXISTS address      VARCHAR(300) DEFAULT NULL AFTER profile_pic,
    ADD COLUMN IF NOT EXISTS city         VARCHAR(100) DEFAULT NULL AFTER address,
    ADD COLUMN IF NOT EXISTS state        VARCHAR(100) DEFAULT NULL AFTER city,
    ADD COLUMN IF NOT EXISTS updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Donation camps table
CREATE TABLE IF NOT EXISTS donation_camps (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    camp_name     VARCHAR(200) NOT NULL,
    location      VARCHAR(200) NOT NULL,
    city          VARCHAR(100) NOT NULL,
    district      VARCHAR(100),
    date          DATE NOT NULL,
    start_time    TIME NOT NULL,
    end_time      TIME,
    organizer     VARCHAR(150),
    contact_name  VARCHAR(100),
    contact_phone VARCHAR(15),
    contact_email VARCHAR(100),
    description   TEXT,
    is_active     TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Sample donation camps across Bihar
INSERT INTO donation_camps (camp_name, location, city, district, date, start_time, end_time, organizer, contact_name, contact_phone, contact_email) VALUES
('Bihar Blood Donation Maha Camp', 'Gandhi Maidan, Near Gate No. 3', 'Patna', 'Patna', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '09:00:00', '17:00:00', 'Bihar Red Cross Society', 'Dr. Ramesh Kumar', '9431100000', 'brc@bihar.gov.in'),
('Life Savers Camp – Muzaffarpur', 'Town Hall, Station Road', 'Muzaffarpur', 'Muzaffarpur', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '10:00:00', '16:00:00', 'NSS Unit – BRABU', 'Prof. Sanjay Singh', '9934200000', 'nss.brabu@gmail.com'),
('Donate Blood Save Life – Gaya', 'Collectorate Ground, Civil Lines', 'Gaya', 'Gaya', DATE_ADD(CURDATE(), INTERVAL 10 DAY), '08:30:00', '14:30:00', 'Rotary Club Gaya', 'Mr. Ashok Prasad', '9876543210', 'rotary.gaya@gmail.com'),
('Bhagalpur Blood Drive', 'TN Bhagalpur University Campus', 'Bhagalpur', 'Bhagalpur', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '09:00:00', '15:00:00', 'TNBU Student Union', 'Ms. Priya Kumari', '7654321098', 'tnbu.su@gmail.com'),
('Darbhanga Camp – Jeevan Daan', 'LNMU Ground, Raj Darbhanga', 'Darbhanga', 'Darbhanga', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:00:00', '16:00:00', 'Lions Club Darbhanga', 'Mr. Vinod Jha', '9801234567', 'lions.dbg@gmail.com'),
('Purnia Blood Donation Drive', 'BN College Ground', 'Purnea', 'Purnea', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '09:30:00', '15:30:00', 'Young India Foundation', 'Ms. Neha Singh', '8877665544', 'yif.purnea@gmail.com'),
('Ara Mega Blood Camp', 'VKSU Campus, Ara', 'Ara', 'Bhojpur', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '08:00:00', '14:00:00', 'VKSU NCC Wing', 'Capt. Rajeev Tiwari', '9631478520', 'ncc.vksu@gmail.com'),
('Begusarai Life Drop Camp', 'Civil Hospital Premises', 'Begusarai', 'Begusarai', DATE_ADD(CURDATE(), INTERVAL 15 DAY), '10:00:00', '17:00:00', 'Bihar Voluntary Blood Donors Org.', 'Dr. Sunil Sharma', '9470123456', 'bvbdo@gmail.com');

-- 4. Support tickets table (optional)
CREATE TABLE IF NOT EXISTS support_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    subject    VARCHAR(200) NOT NULL,
    message    TEXT NOT NULL,
    status     ENUM('Open','Resolved') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 5. Profile pictures upload directory note
-- Create folder: blood_bank/assets/uploads/profiles/
-- (created programmatically by PHP)
