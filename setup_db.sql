CREATE DATABASE IF NOT EXISTS capstone_db;
USE capstone_db;

-- USERS TABLE
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','constructor') NOT NULL
);

-- MATERIALS TABLE
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    price DECIMAL(10,2),
    supplier VARCHAR(100),
    date DATE,
    time VARCHAR(10),
    purpose VARCHAR(255),
    quantity INT,
    total_amount DECIMAL(10,2)
);

-- DAILY REPORTS TABLE
CREATE TABLE construction_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    constructor_id INT,
    report_date DATE,
    start_time VARCHAR(10),
    end_time VARCHAR(10),
    status ENUM('complete','ongoing'),
    description TEXT,
    proof_image VARCHAR(255),
    materials_left TEXT,
    FOREIGN KEY (constructor_id) REFERENCES users(id)
);

-- Example admin user (password: adminpass)
INSERT INTO users (username, password, role) VALUES ('admin','adminpass','admin');
-- Example constructor (password: constructor1)
INSERT INTO users (username, password, role) VALUES ('constructor1','constructor1','constructor');