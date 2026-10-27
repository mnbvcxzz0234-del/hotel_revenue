CREATE DATABASE IF NOT EXISTS hotel_revenue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_revenue;
CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, password_hash VARCHAR(255));
CREATE TABLE IF NOT EXISTS room_types(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), total_rooms INT, base_rate DECIMAL(10,2), min_rate DECIMAL(10,2), max_rate DECIMAL(10,2));
CREATE TABLE IF NOT EXISTS bookings(id INT AUTO_INCREMENT PRIMARY KEY, guest VARCHAR(120), room_type_id INT, checkin DATE, checkout DATE, rooms INT DEFAULT 1, rate_per_night DECIMAL(10,2), status ENUM('confirmed','cancelled') DEFAULT 'confirmed', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(checkin,checkout), FOREIGN KEY(room_type_id) REFERENCES room_types(id));
CREATE TABLE IF NOT EXISTS rates(room_type_id INT, rate_date DATE, rate DECIMAL(10,2), PRIMARY KEY(room_type_id,rate_date));
CREATE TABLE IF NOT EXISTS competitors(id INT AUTO_INCREMENT PRIMARY KEY, rate_date DATE, hotel VARCHAR(100), rate DECIMAL(10,2), INDEX(rate_date));
INSERT INTO room_types(name,total_rooms,base_rate,min_rate,max_rate) SELECT 'Standard',20,1500,1000,3000 WHERE NOT EXISTS(SELECT 1 FROM room_types);
INSERT INTO room_types(name,total_rooms,base_rate,min_rate,max_rate) SELECT 'Deluxe',10,2200,1500,4500 WHERE (SELECT COUNT(*) FROM room_types)=1;
