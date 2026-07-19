-- ============================================
-- Blood Bank Management System - Database
-- ============================================

CREATE DATABASE IF NOT EXISTS blood_bank;
USE blood_bank;

-- Users table (Admin + regular users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blood groups
CREATE TABLE blood_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(5) NOT NULL UNIQUE
);

INSERT INTO blood_groups (name) VALUES ('A+'),('A-'),('B+'),('B-'),('O+'),('O-'),('AB+'),('AB-');

-- Donors
CREATE TABLE donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    blood_group_id INT NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    last_donation_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id)
);

-- Blood stock
CREATE TABLE blood_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group_id INT NOT NULL UNIQUE,
    units INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id)
);

INSERT INTO blood_stock (blood_group_id, units) VALUES (1,10),(2,5),(3,8),(4,3),(5,15),(6,4),(7,6),(8,2);

-- Hospitals
CREATE TABLE hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT,
    phone VARCHAR(15),
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blood requests
CREATE TABLE blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    blood_group_id INT NOT NULL,
    units_needed INT NOT NULL,
    hospital_id INT,
    urgency ENUM('Normal','Urgent','Critical') DEFAULT 'Normal',
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    requested_by INT,
    approved_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Donation log
CREATE TABLE donation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    units INT DEFAULT 1,
    donated_date DATE NOT NULL,
    notes TEXT,
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Stock change log
CREATE TABLE stock_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group_id INT NOT NULL,
    change_units INT NOT NULL,
    reason VARCHAR(200),
    changed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_group_id) REFERENCES blood_groups(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Default admin user (password: admin123)
INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@bloodbank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample hospital
INSERT INTO hospitals (name, address, phone) VALUES
('City General Hospital', 'MG Road, Patna, Bihar', '0612-2345678'),
('Apollo Hospital', 'Boring Road, Patna, Bihar', '0612-3456789');
